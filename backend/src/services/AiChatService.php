<?php
namespace FastFood\Services;

use FastFood\Config\Database;
use PDO;

/**
 * AI Chat & Support Inbox Service
 * Hỗ trợ lưu trữ phiên hội thoại (session), tin nhắn khách hàng & phản hồi AI/Admin
 */
class AiChatService {
    /**
     * Khởi tạo hoặc lấy thông tin phiên trò chuyện
     */
    public static function getOrCreateSession(string $sessionId, ?int $userId = null, ?string $customerName = null): array {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT * FROM ai_chat_sessions WHERE session_id = ? LIMIT 1");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if ($userId && (!$customerName || $customerName === 'Khách hàng' || $customerName === 'Khách vãng lai')) {
                try {
                    $uStmt = $db->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
                    $uStmt->execute([$userId]);
                    $fName = $uStmt->fetchColumn();
                    if ($fName) $customerName = $fName;
                } catch (\Exception $e) {}
            }

            $name = $customerName ?: ($userId ? "Khách hàng #{$userId}" : 'Khách vãng lai');

            if ($session) {
                $needsUpdate = false;
                $updateUser = $session['user_id'];
                $updateName = $session['customer_name'];

                if ($userId && (empty($session['user_id']) || $session['user_id'] != $userId)) {
                    $updateUser = $userId;
                    $needsUpdate = true;
                }
                if ($name && $name !== 'Khách hàng' && $name !== 'Khách vãng lai' && ($name !== $session['customer_name'] || empty($session['customer_name']) || $session['customer_name'] === 'Khách hàng' || $session['customer_name'] === 'Khách vãng lai')) {
                    $updateName = $name;
                    $needsUpdate = true;
                }
                if ($needsUpdate) {
                    $upStmt = $db->prepare("UPDATE ai_chat_sessions SET user_id = ?, customer_name = ?, updated_at = NOW() WHERE id = ?");
                    $upStmt->execute([$updateUser, $updateName, $session['id']]);
                    $session['user_id'] = $updateUser;
                    $session['customer_name'] = $updateName;
                }
                return $session;
            }

            $insStmt = $db->prepare("INSERT INTO ai_chat_sessions (session_id, user_id, customer_name, title, is_active, created_at, updated_at)
                                     VALUES (?, ?, ?, 'Hội thoại mới', 1, NOW(), NOW())");
            $insStmt->execute([$sessionId, $userId, $name]);
            $newId = (int)$db->lastInsertId();

            return [
                'id'              => $newId,
                'session_id'      => $sessionId,
                'user_id'         => $userId,
                'customer_name'   => $name,
                'title'           => 'Hội thoại mới',
                'is_active'       => 1,
                'ai_paused_until' => null,
                'ai_force_auto'   => 0,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            error_log('[AiChatService::getOrCreateSession] Error: ' . $e->getMessage());
            return [
                'session_id'    => $sessionId,
                'user_id'       => $userId,
                'customer_name' => $customerName ?: 'Khách hàng',
                'is_active'     => 1
            ];
        }
    }

    /**
     * Lưu tin nhắn vào CSDL
     */
    public static function saveMessage(string $sessionId, ?int $userId, string $sender, string $message, ?array $metadata = null, string $messageType = 'text'): int {
        $db = Database::getConnection();
        try {
            $sess = self::getOrCreateSession($sessionId, $userId);
            if (!$userId && !empty($sess['user_id'])) {
                $userId = (int)$sess['user_id'];
            }

            // Chuẩn hóa messageType: 'text', 'image', 'product_card'
            $allowedTypes = ['text', 'image', 'product_card'];
            if (!in_array($messageType, $allowedTypes, true)) {
                $messageType = (!empty($metadata['type']) && in_array($metadata['type'], $allowedTypes, true)) ? $metadata['type'] : 'text';
            }

            // Khử mã độc XSS trong nội dung tin nhắn (<script> tags)
            if (stripos($message, '<script') !== false) {
                $message = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '&lt;script&gt;$1&lt;/script&gt;', $message);
                $message = str_ireplace(['<script>', '<script', '</script>'], ['&lt;script&gt;', '&lt;script', '&lt;/script&gt;'], $message);
            }

            $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
            $isRead = ($sender === 'user' ? 0 : 1);
            $stmt = $db->prepare("INSERT INTO ai_chat_messages (session_id, user_id, sender, message_type, message, metadata, is_read, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$sessionId, $userId, $sender, $messageType, $message, $metaJson, $isRead]);
            $msgId = (int)$db->lastInsertId();

            // Cập nhật updated_at cho phiên
            $upSession = $db->prepare("UPDATE ai_chat_sessions SET updated_at = NOW() WHERE session_id = ?");
            $upSession->execute([$sessionId]);

            // Đồng bộ sang bảng chat_messages (nếu có userId hoặc gán khách vãng lai)
            if ($userId) {
                try {
                    $roleMap = ['user' => 'customer', 'admin' => 'admin', 'assistant' => 'ai'];
                    $sRole = $roleMap[$sender] ?? 'ai';
                    $sId = $sender === 'admin' ? 1 : $userId;
                    $syncStmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message_type, message, is_read, created_at)
                                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $syncStmt->execute([$userId, $sId, $sRole, $messageType, mb_substr($message, 0, 1000), $isRead]);
                } catch (\Exception $ex) {
                    try {
                        $syncStmtLegacy = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, is_read, created_at)
                                                        VALUES (?, ?, ?, ?, ?, NOW())");
                        $syncStmtLegacy->execute([$userId, $sId, $sRole, mb_substr($message, 0, 1000), $isRead]);
                    } catch (\Exception $ex2) {}
                }
            }

            return $msgId;
        } catch (\Exception $e) {
            error_log('[AiChatService::saveMessage] Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Lấy lịch sử tin nhắn của một phiên
     */
    public static function getHistory(string $sessionId, ?int $userId = null, int $limit = 150): array {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("
                SELECT * FROM (
                    SELECT m.*, COALESCE(u.full_name, s.customer_name) AS customer_name
                    FROM ai_chat_messages m
                    LEFT JOIN ai_chat_sessions s ON m.session_id = s.session_id
                    LEFT JOIN users u ON m.user_id = u.id
                    WHERE m.session_id = ?
                    ORDER BY m.id DESC
                    LIMIT ?
                ) recent_msgs
                ORDER BY recent_msgs.id ASC
            ");
            $stmt->bindValue(1, $sessionId, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Nếu chưa có tin nhắn theo sessionId và có userId, tìm thử theo userId trong cùng loại kênh (tránh lẫn AI và Admin)
            if (empty($rows) && $userId) {
                $prefix = str_starts_with($sessionId, 'admin_sess_') ? 'admin_sess_%' : (str_starts_with($sessionId, 'ai_sess_') ? 'ai_sess_%' : null);
                if ($prefix) {
                    $stmt = $db->prepare("
                        SELECT * FROM (
                            SELECT m.*, COALESCE(u.full_name, s.customer_name) AS customer_name
                            FROM ai_chat_messages m
                            LEFT JOIN ai_chat_sessions s ON m.session_id = s.session_id
                            LEFT JOIN users u ON m.user_id = u.id
                            WHERE m.user_id = ? AND m.session_id LIKE ?
                            ORDER BY m.id DESC
                            LIMIT ?
                        ) recent_msgs
                        ORDER BY recent_msgs.id ASC
                    ");
                    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                    $stmt->bindValue(2, $prefix, PDO::PARAM_STR);
                    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            $messages = [];
            foreach ($rows as $r) {
                $meta = null;
                if (!empty($r['metadata'])) {
                    $meta = is_string($r['metadata']) ? json_decode($r['metadata'], true) : $r['metadata'];
                }
                $msgType = $r['message_type'] ?? ($meta['type'] ?? 'text');
                $messages[] = [
                    'id'           => (int)$r['id'],
                    'sessionId'    => $r['session_id'],
                    'sender'       => $r['sender'], // 'user', 'assistant', 'admin'
                    'senderRole'   => $r['sender'] === 'user' ? 'customer' : ($r['sender'] === 'admin' ? 'admin' : 'assistant'),
                    'messageType'  => $msgType,
                    'customerName' => $r['customer_name'] ?? null,
                    'message'      => $r['message'],
                    'metadata'     => $meta,
                    'isRead'       => !empty($r['is_read']),
                    'is_read'      => (int)($r['is_read'] ?? 0),
                    'time'         => date('H:i', strtotime($r['created_at'])),
                    'createdAt'    => $r['created_at']
                ];
            }

            return $messages;
        } catch (\Exception $e) {
            error_log('[AiChatService::getHistory] Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Cài đặt chế độ chat toàn cục: luôn chạy chế độ Admin Trực Tiếp Chat (không bật AI tự động)
     */
    public static function getChatSettings(): array {
        return [
            'mode'            => 'admin',
            'adminOnline'     => true,
            'adminManualBusy' => false,
            'effectiveBusy'   => false,
            'updatedAt'       => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Cập nhật cài đặt chế độ chat toàn cục (luôn giữ chế độ Admin trực tiếp)
     */
    public static function updateChatSettings(array $settings): array {
        $db = Database::getConnection();
        $merged = [
            'mode'            => 'admin',
            'adminOnline'     => true,
            'adminManualBusy' => false,
            'effectiveBusy'   => false,
            'updatedAt'       => date('Y-m-d H:i:s')
        ];
        $json = json_encode($merged, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $db->prepare("
                INSERT INTO website_content (collection_name, content, updated_at)
                VALUES ('chat_settings', ?, NOW())
                ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()
            ");
            $stmt->execute([$json]);
            $db->query("UPDATE ai_chat_sessions SET ai_force_auto = 0");
        } catch (\Exception $e) {
            error_log('[AiChatService::updateChatSettings] Error: ' . $e->getMessage());
        }

        return $merged;
    }

    /**
     * AI tự động trả lời đã tắt hoàn toàn — chỉ giữ chat trực tiếp với Admin
     */
    public static function shouldAiAutoReply(string $sessionId): bool {
        return false;
    }

    /**
     * Kiểm tra trạng thái tạm dừng AI của phiên
     */
    public static function isAiPaused(string $sessionId): bool {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT ai_paused_until, ai_force_auto FROM ai_chat_sessions WHERE session_id = ? LIMIT 1");
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch();
            if (!$row) return false;

            if (!empty($row['ai_force_auto'])) return false;
            if (!empty($row['ai_paused_until'])) {
                return strtotime($row['ai_paused_until']) > time();
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Danh sách hội thoại dành cho Admin
     */
    public static function getAdminConversations(): array {
        $db = Database::getConnection();
        $conversations = [];

        try {
            // Lấy từ ai_chat_sessions có tin nhắn (tự động liên kết tài khoản thành viên theo user_id hoặc tên đầy đủ)
            $stmt = $db->query("
                SELECT s.session_id, s.user_id, s.customer_name, s.ai_paused_until, s.ai_force_auto, s.updated_at,
                       u.id AS matched_user_id, u.email, u.phone, u.avatar_url, u.full_name AS user_full_name,
                       m.message AS last_message, m.sender AS last_sender, m.created_at AS last_time,
                       COALESCE(unread.unread_count, 0) AS unread_count
                FROM ai_chat_sessions s
                LEFT JOIN users u ON (s.user_id IS NOT NULL AND s.user_id = u.id)
                                  OR (s.user_id IS NULL AND s.customer_name IS NOT NULL 
                                      AND s.customer_name NOT IN ('Khách hàng', 'Khách vãng lai') 
                                      AND s.customer_name = u.full_name)
                LEFT JOIN ai_chat_messages m ON m.id = (
                    SELECT MAX(id) FROM ai_chat_messages WHERE session_id = s.session_id
                )
                LEFT JOIN (
                    SELECT session_id, COUNT(*) AS unread_count
                    FROM ai_chat_messages
                    WHERE sender = 'user' AND is_read = 0
                    GROUP BY session_id
                ) unread ON unread.session_id = s.session_id
                WHERE m.id IS NOT NULL AND s.session_id NOT LIKE 'ai_sess_%'
                ORDER BY (CASE WHEN s.session_id LIKE 'admin_sess_%' THEN 0 ELSE 1 END), s.updated_at DESC
                LIMIT 50
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $finalUserId = $r['user_id'] ? (int)$r['user_id'] : (!empty($r['matched_user_id']) ? (int)$r['matched_user_id'] : null);
                $displayName = !empty($r['user_full_name']) ? $r['user_full_name'] : ($r['customer_name'] ?: ($finalUserId ? "Khách hàng #{$finalUserId}" : 'Khách vãng lai'));
                $channelType = str_starts_with($r['session_id'], 'admin_sess_') ? 'admin' : (str_starts_with($r['session_id'], 'ai_sess_') ? 'ai' : 'direct');
                $conversations[] = [
                    'sessionId'     => $r['session_id'],
                    'customerId'    => $finalUserId,
                    'userId'        => $finalUserId,
                    'channel'       => $channelType,
                    'customerName'  => $displayName,
                    'unreadCount'   => (int)($r['unread_count'] ?? 0),
                    'lastMessage'   => $r['last_message'] ?: '...',
                    'lastSender'    => $r['last_sender'] ?: 'user',
                    'email'         => $r['email'] ?? '',
                    'phone'         => $r['phone'] ?? '',
                    'avatar'        => $r['avatar_url'] ?? '',
                    'time'          => date('H:i', strtotime($r['last_time'] ?: $r['updated_at'])),
                    'createdAt'     => $r['last_time'] ?: $r['updated_at'],
                    'updatedAt'     => $r['last_time'] ?: $r['updated_at'],
                    'aiPausedUntil' => $r['ai_paused_until'],
                    'aiForceAuto'   => !empty($r['ai_force_auto'])
                ];
            }

            // Nếu ai_chat_sessions ít, kết hợp thêm từ bảng legacy chat_messages
            $stmtLegacy = $db->query("
                SELECT m.customer_id, m.message AS last_message, m.sender_role AS last_sender, m.created_at,
                       COALESCE(u.full_name, CONCAT('Khách hàng #', m.customer_id)) AS customer_name,
                       u.email, u.phone, u.avatar_url,
                       COALESCE(unread.unread_count, 0) AS unread_count
                FROM chat_messages m
                INNER JOIN (
                    SELECT customer_id, MAX(id) AS max_id 
                    FROM chat_messages 
                    GROUP BY customer_id
                ) latest ON m.id = latest.max_id
                LEFT JOIN (
                    SELECT customer_id, COUNT(*) AS unread_count
                    FROM chat_messages
                    WHERE sender_role = 'customer' AND is_read = 0
                    GROUP BY customer_id
                ) unread ON unread.customer_id = m.customer_id
                LEFT JOIN users u ON m.customer_id = u.id
                ORDER BY m.id DESC LIMIT 20
            ");
            $legacyRows = $stmtLegacy->fetchAll(PDO::FETCH_ASSOC);

            $existingCustomerIds = array_filter(array_column($conversations, 'customerId'));

            foreach ($legacyRows as $r) {
                if (in_array((int)$r['customer_id'], $existingCustomerIds, true)) {
                    continue;
                }
                $conversations[] = [
                    'sessionId'     => 'cust-' . $r['customer_id'],
                    'customerId'    => (int)$r['customer_id'],
                    'userId'        => (int)$r['customer_id'],
                    'customerName'  => $r['customer_name'],
                    'unreadCount'   => (int)($r['unread_count'] ?? 0),
                    'lastMessage'   => $r['last_message'],
                    'lastSender'    => $r['last_sender'] === 'customer' ? 'user' : ($r['last_sender'] === 'admin' ? 'admin' : 'assistant'),
                    'email'         => $r['email'] ?? '',
                    'phone'         => $r['phone'] ?? '',
                    'avatar'        => $r['avatar_url'] ?? '',
                    'time'          => date('H:i', strtotime($r['created_at'])),
                    'createdAt'     => $r['created_at'],
                    'updatedAt'     => $r['created_at'],
                    'aiPausedUntil' => null,
                    'aiForceAuto'   => false
                ];
            }

            $totalUnread = self::getTotalUnreadCount();

            return [
                'conversations' => $conversations,
                'totalUnread'   => $totalUnread
            ];
        } catch (\Exception $e) {
            error_log('[AiChatService::getAdminConversations] Error: ' . $e->getMessage());
            return [
                'conversations' => [],
                'totalUnread'   => 0
            ];
        }
    }

    /**
     * Đánh dấu toàn bộ tin nhắn từ khách hàng trong phiên là đã đọc
     */
    public static function markSessionAsRead(string $sessionId): int {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("UPDATE ai_chat_messages SET is_read = 1 WHERE session_id = ? AND sender = 'user' AND is_read = 0");
            $stmt->execute([$sessionId]);
            $affected = $stmt->rowCount();

            // Đồng bộ sang bảng chat_messages nếu có liên kết
            $customerId = (int)preg_replace('/\D/', '', $sessionId);
            if ($customerId > 0) {
                try {
                    $stmtLegacy = $db->prepare("UPDATE chat_messages SET is_read = 1 WHERE customer_id = ? AND sender_role = 'customer' AND is_read = 0");
                    $stmtLegacy->execute([$customerId]);
                } catch (\Exception $e) {}
            } else {
                try {
                    $sessStmt = $db->prepare("SELECT user_id FROM ai_chat_sessions WHERE session_id = ? LIMIT 1");
                    $sessStmt->execute([$sessionId]);
                    $uId = $sessStmt->fetchColumn();
                    if ($uId) {
                        $stmtLegacy = $db->prepare("UPDATE chat_messages SET is_read = 1 WHERE customer_id = ? AND sender_role = 'customer' AND is_read = 0");
                        $stmtLegacy->execute([(int)$uId]);
                    }
                } catch (\Exception $e) {}
            }

            return $affected;
        } catch (\Exception $e) {
            error_log('[AiChatService::markSessionAsRead] Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Lấy tổng số tin nhắn chưa đọc từ khách hàng
     */
    public static function getTotalUnreadCount(): int {
        $db = Database::getConnection();
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE sender = 'user' AND is_read = 0 AND session_id NOT LIKE 'ai_sess_%'");
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            error_log('[AiChatService::getTotalUnreadCount] Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Lấy danh sách tin nhắn theo session cho Admin
     */
    public static function getSessionMessages(string $sessionId): array {
        $db = Database::getConnection();

        // Nếu là phiên ai_chat_messages
        $messages = self::getHistory($sessionId, null, 100);
        if (!empty($messages)) {
            return $messages;
        }

        // Nếu là session dạng cust-123
        $customerId = (int)preg_replace('/\D/', '', $sessionId);
        if ($customerId > 0) {
            try {
                $stmt = $db->prepare("SELECT * FROM chat_messages WHERE customer_id = ? ORDER BY id ASC LIMIT 100");
                $stmt->execute([$customerId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $r) {
                    $result[] = [
                        'id'          => (int)$r['id'],
                        'sessionId'   => $sessionId,
                        'customerId'  => (int)$r['customer_id'],
                        'sender'      => $r['sender_role'] === 'customer' ? 'user' : ($r['sender_role'] === 'admin' ? 'admin' : 'assistant'),
                        'senderRole'  => $r['sender_role'],
                        'messageType' => $r['message_type'] ?? 'text',
                        'message'     => $r['message'],
                        'metadata'    => null,
                        'isRead'      => !empty($r['is_read']),
                        'is_read'     => (int)($r['is_read'] ?? 0),
                        'time'        => date('H:i', strtotime($r['created_at'])),
                        'createdAt'   => $r['created_at']
                    ];
                }
                return $result;
            } catch (\Exception $e) {}
        }

        return [];
    }

    /**
     * Admin gửi tin nhắn phản hồi
     */
    public static function sendAdminReply(string $sessionId, string $message, int $adminId = 1, ?array $metadata = null, string $messageType = 'text'): array {
        $db = Database::getConnection();
        $pauseUntil = null;

        // Chuẩn hóa messageType
        $allowedTypes = ['text', 'image', 'product_card'];
        if (!in_array($messageType, $allowedTypes, true)) {
            $messageType = (!empty($metadata['type']) && in_array($metadata['type'], $allowedTypes, true)) ? $metadata['type'] : 'text';
        }

        // Lưu vào ai_chat_messages
        $msgId = self::saveMessage($sessionId, null, 'admin', $message, $metadata, $messageType);

        // Nếu sessionId dạng cust-123 hoặc session có userId, lưu cả chat_messages
        $customerId = (int)preg_replace('/\D/', '', $sessionId);
        if ($customerId > 0) {
            try {
                $stmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message_type, message, created_at)
                                      VALUES (?, ?, 'admin', ?, ?, NOW())");
                $stmt->execute([$customerId, $adminId, $messageType, mb_substr($message, 0, 1000)]);
            } catch (\Exception $e) {
                try {
                    $stmtLegacy = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, created_at)
                                                VALUES (?, ?, 'admin', ?, NOW())");
                    $stmtLegacy->execute([$customerId, $adminId, mb_substr($message, 0, 1000)]);
                } catch (\Exception $e2) {}
            }
        }

        // Tự động đánh dấu đã đọc các tin nhắn trước đó của khách khi Admin gửi phản hồi
        self::markSessionAsRead($sessionId);

        date_default_timezone_set('Asia/Ho_Chi_Minh');
        return [
            'id'            => $msgId ?: time(),
            'sessionId'     => $sessionId,
            'sender'        => 'admin',
            'senderRole'    => 'admin',
            'messageType'   => $messageType,
            'message'       => $message,
            'metadata'      => $metadata,
            'time'          => date('H:i'),
            'createdAt'     => date('Y-m-d H:i:s'),
            'aiPausedUntil' => $pauseUntil ? date('c', strtotime($pauseUntil)) : null
        ];
    }

    /**
     * Cập nhật tự động hóa cho một phiên
     */
    public static function updateSessionAutomation(string $sessionId, bool $enabled): void {
        $db = Database::getConnection();
        try {
            if ($enabled) {
                $stmt = $db->prepare("UPDATE ai_chat_sessions SET ai_force_auto = 1, ai_paused_until = NULL, updated_at = NOW() WHERE session_id = ?");
                $stmt->execute([$sessionId]);
            } else {
                $pauseUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt = $db->prepare("UPDATE ai_chat_sessions SET ai_force_auto = 0, ai_paused_until = ?, updated_at = NOW() WHERE session_id = ?");
                $stmt->execute([$pauseUntil, $sessionId]);
            }
        } catch (\Exception $e) {
            error_log('[AiChatService::updateSessionAutomation] Error: ' . $e->getMessage());
        }
    }

    /**
     * Xóa một phiên hội thoại và tất cả tin nhắn liên quan
     */
    public static function deleteSession(string $sessionId): bool {
        $db = Database::getConnection();
        try {
            $stmt1 = $db->prepare("DELETE FROM ai_chat_messages WHERE session_id = ?");
            $stmt1->execute([$sessionId]);

            $stmt2 = $db->prepare("DELETE FROM ai_chat_sessions WHERE session_id = ?");
            $stmt2->execute([$sessionId]);

            return true;
        } catch (\Exception $e) {
            error_log('[AiChatService::deleteSession] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa toàn bộ lịch sử trò chuyện
     */
    public static function clearAllHistory(): bool {
        $db = Database::getConnection();
        try {
            $db->exec("DELETE FROM ai_chat_messages");
            $db->exec("DELETE FROM ai_chat_sessions");
            $db->exec("DELETE FROM chat_messages");
            return true;
        } catch (\Exception $e) {
            error_log('[AiChatService::clearAllHistory] Error: ' . $e->getMessage());
            return false;
        }
    }
}
