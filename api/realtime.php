<?php
/**
 * FASTFOOD - Realtime Engine API (/api/realtime.php)
 * Hỗ trợ Polling & Đồng bộ Realtime Đơn Hàng và Chat giữa các Tab
 */
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
        $orderCode   = trim($body['orderCode'] ?? $_GET['orderCode'] ?? '');
        $customerId  = (int)($body['customerId'] ?? $_GET['customerId'] ?? ($currentUser['id'] ?? 0));

        // A. Lấy đơn hàng mới cho Admin/Staff
        $newOrders = [];
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
                            'status'        => $r['order_status'],
                            'paymentMethod' => $r['p_method'] ?: $r['payment_method'],
                            'paymentStatus' => $r['p_status'] ?: $r['payment_status'],
                            'createdAt'     => $r['created_at']
                        ];
                    }
                }
            }
        }

        // B. Nếu đang theo dõi 1 đơn cụ thể (Khách hàng ở order-tracking.php)
        if ($orderCode) {
            $stmt = $db->prepare("SELECT order_code, order_status, payment_status, total_amount, updated_at 
                                  FROM orders WHERE order_code = ? LIMIT 1");
            $stmt->execute([$orderCode]);
            $trackedOrder = $stmt->fetch();
        }

        // C. Lấy tin nhắn chat mới
        $newMessages = [];
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

        // D. Lấy max ID hiện tại
        $maxOrderId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM orders")->fetchColumn();
        $maxChatId  = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM chat_messages")->fetchColumn();

        jsonSuccess('Poll thành công', [
            'newOrders'    => $newOrders,
            'newMessages'  => $newMessages,
            'trackedOrder' => $trackedOrder,
            'maxOrderId'   => $maxOrderId,
            'maxChatId'    => $maxChatId,
            'serverTime'   => date('Y-m-d H:i:s')
        ]);
        break;

    /**
     * 2. Gửi tin nhắn chat mới
     */
    case 'send_chat':
        $message = trim($body['message'] ?? $_POST['message'] ?? '');
        $targetCustomerId = (int)($body['customerId'] ?? $_POST['customerId'] ?? 0);
        $senderRole = 'customer';

        if (!$message) {
            jsonError('Tin nhắn không được để trống.');
        }

        if ($isAdminUser) {
            $senderRole = 'admin';
            $senderId = $currentUser['id'] ?? 1;
            if ($targetCustomerId <= 0) {
                jsonError('Admin cần chọn khách hàng để phản hồi.');
            }
        } else {
            $senderRole = 'customer';
            $senderId = $currentUser['id'] ?? 0;
            if ($targetCustomerId <= 0) {
                $targetCustomerId = $senderId;
            }
            if ($targetCustomerId <= 0) {
                $targetCustomerId = 7; // Khách vãng lai mặc định
            }
        }

        $stmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, created_at)
                              VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$targetCustomerId, $senderId, $senderRole, $message]);
        $msgId = (int)$db->lastInsertId();

        $msgRecord = [
            'id'          => $msgId,
            'customerId'  => $targetCustomerId,
            'senderId'    => $senderId,
            'senderRole'  => $senderRole,
            'sender'      => $senderRole,
            'message'     => $message,
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

    default:
        jsonError('Hành động không hợp lệ.');
}
