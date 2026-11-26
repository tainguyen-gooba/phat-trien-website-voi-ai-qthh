<?php
/**
 * FASTFOOD - Realtime Engine API (/api/realtime.php)
 * Hỗ trợ Polling & Đồng bộ Realtime Đơn Hàng và Chat giữa các Tab
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/helpers/response.php';

$db = getDB();
$currentUser = currentUser();
$isAdminUser = isAdminOrStaff();

// Mở khóa file session ngay lập tức để giải phóng tài nguyên cho server
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$action = $body['action'] ?? $_GET['action'] ?? $_POST['action'] ?? 'poll';

switch ($action) {
    /**
     * 1. Polling: Lấy đơn hàng và tin nhắn mới nhất
     */
    case 'poll':
        $lastOrderId = (int)($body['lastOrderId'] ?? $_GET['lastOrderId'] ?? 0);
        $lastChatId  = (int)($body['lastChatId'] ?? $_GET['lastChatId'] ?? 0);
        $lastUserId  = (int)($body['lastUserId'] ?? $_GET['lastUserId'] ?? 0);
        $lastOrderUpdate = trim($body['lastOrderUpdate'] ?? $_GET['lastOrderUpdate'] ?? '');
        $orderCode   = trim($body['orderCode'] ?? $_GET['orderCode'] ?? '');
        $customerId  = (int)($body['customerId'] ?? $_GET['customerId'] ?? ($currentUser['id'] ?? 0));

        $normalizeOrderStatus = function ($st) {
            $s = strtolower(trim((string)$st));
            if ($s === 'completed') return 'delivered';
            return $s ?: 'pending';
        };

        // A. Lấy đơn hàng mới & đơn hàng vừa cập nhật cho Admin/Staff
        $newOrders = [];
        $updatedOrders = [];
        $trackedOrder = null;
        if ($isAdminUser) {
            if ($lastOrderId > 0) {
                $stmt = $db->prepare("SELECT o.*, ro.order_data, ro.status AS rt_status,
                                             COALESCE(p.payment_method, o.payment_method) AS p_method,
                                             COALESCE(p.status, o.payment_status) AS p_status
                                      FROM orders o
                                      LEFT JOIN realtime_orders ro ON ro.order_code = o.order_code
                                      LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                                      WHERE o.id > ?
                                      ORDER BY o.id DESC LIMIT 50");
                $stmt->execute([$lastOrderId]);
                $rows = $stmt->fetchAll();
                foreach ($rows as $r) {
                    $parsed = json_decode($r['order_data'] ?? '', true);
                    if ($parsed && !empty($parsed['id'])) {
                        $newOrders[] = $parsed;
                    } else {
                        $newOrders[] = [
                            'id'            => $r['order_code'],
                            'orderNo'       => '#' . $r['order_code'],
                            'orderCode'     => $r['order_code'],
                            'customer'      => $r['recipient_name'],
                            'recipient'     => [
                                'name'    => $r['recipient_name'],
                                'phone'   => $r['recipient_phone'],
                                'address' => $r['delivery_address']
                            ],
                            'total'         => (float)$r['total_amount'],
                            'status'        => $normalizeOrderStatus($r['order_status']),
                            'paymentMethod' => $r['p_method'] ?: $r['payment_method'],
                            'paymentStatus' => $r['p_status'] ?: $r['payment_status'],
                            'createdAt'     => $r['created_at']
                        ];
                    }
                }
            }

            if ($lastOrderUpdate !== '') {
                $upStmt = $db->prepare("SELECT o.order_code, o.order_status, COALESCE(p.status, o.payment_status) AS p_status, o.updated_at
                                        FROM orders o
                                        LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                                        WHERE o.updated_at > ?
                                        ORDER BY o.updated_at DESC LIMIT 50");
                $upStmt->execute([$lastOrderUpdate]);
                foreach ($upStmt->fetchAll() as $ur) {
                    $updatedOrders[] = [
                        'orderCode'     => $ur['order_code'],
                        'order_status'  => $normalizeOrderStatus($ur['order_status']),
                        'status'        => $normalizeOrderStatus($ur['order_status']),
                        'paymentStatus' => $ur['p_status'],
                        'updatedAt'     => $ur['updated_at'],
                    ];
                }
            }
        } elseif ($customerId > 0 && $lastOrderUpdate !== '') {
            $upStmt = $db->prepare("SELECT o.order_code, o.order_status, COALESCE(p.status, o.payment_status) AS p_status, o.updated_at
                                    FROM orders o
                                    LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                                    WHERE o.customer_id = ? AND o.updated_at > ?
                                    ORDER BY o.updated_at DESC LIMIT 30");
            $upStmt->execute([$customerId, $lastOrderUpdate]);
            foreach ($upStmt->fetchAll() as $ur) {
                $updatedOrders[] = [
                    'orderCode'     => $ur['order_code'],
                    'order_status'  => $normalizeOrderStatus($ur['order_status']),
                    'status'        => $normalizeOrderStatus($ur['order_status']),
                    'paymentStatus' => $ur['p_status'],
                    'updatedAt'     => $ur['updated_at'],
                ];
            }
        }

        // B. Nếu đang theo dõi 1 đơn cụ thể (Khách hàng ở order-tracking.php)
        if ($orderCode) {
            $stmt = $db->prepare("SELECT o.order_code, COALESCE(NULLIF(o.order_status, ''), 'pending') AS order_status, COALESCE(p.status, o.payment_status) AS payment_status, o.total_amount, o.updated_at 
                                  FROM orders o
                                  LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                                  WHERE o.order_code = ? LIMIT 1");
            $stmt->execute([$orderCode]);
            $trackedOrder = $stmt->fetch();
            if ($trackedOrder) {
                $trackedOrder['order_status'] = $normalizeOrderStatus($trackedOrder['order_status']);
            }
        }

        // C. Lấy tin nhắn chat mới
        $newMessages = [];
        $unreadChatCount = 0;
        if ($isAdminUser) {
            // Admin nhận tất cả tin nhắn mới
            if ($lastChatId > 0) {
                $stmt = $db->prepare("SELECT m.*, u.full_name AS customer_name, u.avatar_url AS customer_avatar
                                      FROM chat_messages m
                                      LEFT JOIN users u ON m.customer_id = u.id
                                      WHERE m.id > ?
                                      ORDER BY m.id ASC LIMIT 50");
                $stmt->execute([$lastChatId]);
                $newMessages = $stmt->fetchAll();
            }

            // Đếm tổng tin nhắn chưa đọc từ khách hàng cho Admin
            try {
                $unreadChatCount = (int)$db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE sender = 'user' AND is_read = 0")->fetchColumn();
            } catch (\Exception $e) {
                $unreadChatCount = 0;
            }
        } elseif ($customerId > 0) {
            // Khách chỉ nhận tin nhắn của chính họ
            if ($lastChatId > 0) {
                $stmt = $db->prepare("SELECT * FROM chat_messages 
                                      WHERE customer_id = ? AND id > ?
                                      ORDER BY id ASC LIMIT 50");
                $stmt->execute([$customerId, $lastChatId]);
                $newMessages = $stmt->fetchAll();
            }
        }

        // D. Lấy max ID hiện tại & người dùng mới đăng ký cho Admin
        $maxOrderId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM orders")->fetchColumn();
        $maxChatId  = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM chat_messages")->fetchColumn();
        $maxUserId  = 0;
        $newUsers   = [];
        if ($isAdminUser) {
            $maxUserId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM users")->fetchColumn();
            if ($lastUserId > 0) {
                $roleMap = [1 => 'Quản trị viên', 2 => 'Khách hàng', 3 => 'Chủ nhà hàng', 4 => 'Tài xế', 5 => 'Nhân viên hỗ trợ'];
                $uStmt = $db->prepare("SELECT id, full_name, email, phone, role_id, status FROM users WHERE id > ? ORDER BY id ASC LIMIT 50");
                $uStmt->execute([$lastUserId]);
                foreach ($uStmt->fetchAll() as $ur) {
                    $newUsers[] = [
                        'id'     => (string)$ur['id'],
                        'name'   => $ur['full_name'],
                        'email'  => $ur['email'],
                        'phone'  => $ur['phone'] ?: 'Chưa cập nhật',
                        'role'   => $roleMap[(int)$ur['role_id']] ?? 'Khách hàng',
                        'roleId' => (int)$ur['role_id'],
                        'status' => ($ur['status'] === 'active' || $ur['status'] === 'Đang hoạt động') ? 'Đang hoạt động' : 'Đã khóa'
                    ];
                }
            }
        }

        $pollData = [
            'newOrders'     => $newOrders,
            'updatedOrders' => $updatedOrders,
            'newUsers'      => $newUsers,
            'newMessages'   => $newMessages,
            'trackedOrder'  => $trackedOrder,
            'maxOrderId'    => $maxOrderId,
            'maxChatId'     => $maxChatId,
            'maxUserId'     => $maxUserId,
            'serverTime'    => date('Y-m-d H:i:s')
        ];

        if ($isAdminUser) {
            $pollData['unreadChatCount'] = $unreadChatCount;
        }

        jsonSuccess('Poll thành công', $pollData);
        break;

    /**
     * 2. Gửi tin nhắn chat mới
     */
    case 'send_chat':
        $message = trim($body['message'] ?? $_POST['message'] ?? '');
        $targetCustomerId = (int)($body['customerId'] ?? $_POST['customerId'] ?? 0);
        $senderRole = 'customer';
        $isRead = 0;

        if (!$message) {
            jsonError('Tin nhắn không được để trống.');
        }

        if ($isAdminUser) {
            $senderRole = 'admin';
            $senderId = $currentUser['id'] ?? 1;
            if ($targetCustomerId <= 0) {
                jsonError('Admin cần chọn khách hàng để phản hồi.');
            }
            $isRead = 1;
        } else {
            $senderRole = 'customer';
            $senderId = $currentUser['id'] ?? 0;
            if ($targetCustomerId <= 0) {
                $targetCustomerId = $senderId;
            }
            if ($targetCustomerId <= 0) {
                $targetCustomerId = 7; // Khách vãng lai mặc định
            }
            $isRead = 0;
        }

        $stmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, is_read, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$targetCustomerId, $senderId, $senderRole, $message, $isRead]);
        $msgId = (int)$db->lastInsertId();

        $msgRecord = [
            'id'          => $msgId,
            'customerId'  => $targetCustomerId,
            'senderId'    => $senderId,
            'senderRole'  => $senderRole,
            'sender'      => $senderRole,
            'message'     => $message,
            'isRead'      => $isRead,
            'is_read'     => $isRead,
            'createdAt'   => date('Y-m-d H:i:s'),
            'time'        => date('H:i')
        ];

        jsonSuccess('Đã gửi tin nhắn thành công!', [
            'message' => $msgRecord
        ]);
        break;

    /**
     * 3. Lấy lịch sử tin nhắn của một khách hàng
     */
    case 'get_chat_history':
        $targetCustomerId = (int)($body['customerId'] ?? $_GET['customerId'] ?? ($currentUser['id'] ?? 0));
        if ($targetCustomerId <= 0) {
            $targetCustomerId = 7;
        }

        $stmt = $db->prepare("SELECT * FROM chat_messages WHERE customer_id = ? ORDER BY id ASC LIMIT 100");
        $stmt->execute([$targetCustomerId]);
        $rows = $stmt->fetchAll();

        $messages = [];
        foreach ($rows as $r) {
            $messages[] = [
                'id'         => (int)$r['id'],
                'customerId' => (int)$r['customer_id'],
                'sender'     => $r['sender_role'],
                'senderRole' => $r['sender_role'],
                'message'    => $r['message'],
                'time'       => date('H:i', strtotime($r['created_at'])),
                'createdAt'  => $r['created_at']
            ];
        }

        jsonSuccess('Lấy lịch sử tin nhắn thành công!', [
            'customerId' => $targetCustomerId,
            'messages'   => $messages
        ]);
        break;

    /**
     * 4. Lấy danh sách hội thoại cho Admin
     */
    case 'get_conversations':
        if (!$isAdminUser) {
            jsonError('Chỉ Admin hoặc Nhân viên mới có quyền xem danh sách hội thoại.');
        }

        $stmt = $db->query("SELECT m.customer_id, m.message AS last_message, m.sender_role AS last_sender, m.created_at,
                                   COALESCE(u.full_name, CONCAT('Khách hàng #', m.customer_id)) AS customer_name,
                                   u.email, u.phone, u.avatar_url
                            FROM chat_messages m
                            INNER JOIN (
                                SELECT customer_id, MAX(id) AS max_id 
                                FROM chat_messages 
                                GROUP BY customer_id
                            ) latest ON m.id = latest.max_id
                            LEFT JOIN users u ON m.customer_id = u.id
                            ORDER BY m.id DESC LIMIT 50");
        $rows = $stmt->fetchAll();

        $conversations = [];
        foreach ($rows as $r) {
            $conversations[] = [
                'sessionId'     => 'cust-' . $r['customer_id'],
                'customerId'    => (int)$r['customer_id'],
                'userId'        => (int)$r['customer_id'],
                'customerName'  => $r['customer_name'],
                'lastMessage'   => $r['last_message'],
                'lastSender'    => $r['last_sender'] === 'customer' ? 'user' : ($r['last_sender'] === 'admin' ? 'admin' : 'assistant'),
                'email'         => $r['email'] ?? '',
                'phone'         => $r['phone'] ?? '',
                'avatar'        => $r['avatar_url'] ?? '',
                'time'          => date('H:i', strtotime($r['created_at'])),
                'createdAt'     => $r['created_at'],
                'aiPausedUntil' => null
            ];
        }

        jsonSuccess('Lấy danh sách hội thoại thành công!', [
            'conversations' => $conversations
        ]);
        break;

    /**
     * 5. Khách hàng gửi đánh giá đơn hàng sau khi giao thành công
     */
    case 'submit_review':
        $orderCode = strtoupper(trim($body['orderCode'] ?? $_POST['orderCode'] ?? ''));
        $rating    = max(1, min(5, (int)($body['rating'] ?? $_POST['rating'] ?? 5)));
        $comment   = trim($body['comment'] ?? $_POST['comment'] ?? '');

        if (!$orderCode) {
            jsonError('Thiếu mã đơn hàng để đánh giá.');
        }

        $ordStmt = $db->prepare("SELECT id, order_code, customer_id, restaurant_id, recipient_name, order_status FROM orders WHERE order_code = ? LIMIT 1");
        $ordStmt->execute([$orderCode]);
        $ord = $ordStmt->fetch();
        if (!$ord) {
            jsonError('Không tìm thấy đơn hàng #' . $orderCode, 404);
        }

        $userId = (int)($currentUser['id'] ?? $ord['customer_id'] ?? 0);
        if ($userId <= 0) {
            $userId = (int)$db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        }
        $restaurantId = (int)($ord['restaurant_id'] ?? 1);
        if ($restaurantId <= 0) {
            $restaurantId = 1;
        }

        $existStmt = $db->prepare("SELECT id FROM reviews WHERE order_id = ? LIMIT 1");
        $existStmt->execute([(int)$ord['id']]);
        $existingId = $existStmt->fetchColumn();

        if ($existingId) {
            $upd = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$rating, $comment, (int)$existingId]);
            $reviewId = (int)$existingId;
        } else {
            $ins = $db->prepare("INSERT INTO reviews (order_id, user_id, restaurant_id, rating, comment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $ins->execute([(int)$ord['id'], $userId, $restaurantId, $rating, $comment]);
            $reviewId = (int)$db->lastInsertId();
        }

        // Đồng bộ đánh giá đơn hàng sang từng món ăn trong đơn (product_reviews)
        require_once ROOT_PATH . '/helpers/reviews.php';
        ensureProductReviewsSchema($db);
        syncOrderReviewToProducts($db, (int)$ord['id'], $userId, $rating, $comment);

        jsonSuccess('Cảm ơn bạn đã đánh giá đơn hàng!', [
            'review' => [
                'id'           => $reviewId,
                'orderCode'    => $ord['order_code'],
                'customerName' => $currentUser['full_name'] ?? $ord['recipient_name'] ?? 'Khách hàng',
                'target'       => 'restaurant',
                'rating'       => $rating,
                'comment'      => $comment,
                'submittedAt'  => date('c'),
                'isNew'        => true
            ]
        ]);
        break;

    default:
        jsonError('Hành động không hợp lệ.');
}
