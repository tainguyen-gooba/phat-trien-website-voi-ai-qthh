<?php
namespace FastFood\Controllers;

use FastFood\Services\AiService;
use FastFood\Services\AiChatService;
use FastFood\Middlewares\ChatRateLimit;
use FastFood\Middlewares\AuthMiddleware;
use FastFood\Config\JwtConfig;
use FastFood\Config\Database;
use FastFood\Constants\HttpStatusCodes;

/**
 * AI Chat & Support Inbox Controller
 */
class ChatController extends BaseController {
    public function handleChat(): void {
        ChatRateLimit::check(30);

        $body = $this->getJsonBody();
        $message = trim($body['message'] ?? ($body['prompt'] ?? ''));

        if (!$message) {
            $this->sendError('Tin nhắn không được để trống.', HttpStatusCodes::BAD_REQUEST);
        }

        $userId = null;
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $payload = JwtConfig::decode($matches[1]);
            if ($payload) $userId = (int)($payload['id'] ?? null);
        }

        $aiResponse = AiService::processPrompt($message, $userId);

        if ($userId) {
            AiChatService::saveMessage($userId, 'user', $message);
            AiChatService::saveMessage($userId, 'assistant', $aiResponse['reply']);
        }

        $this->sendSuccess('Phản hồi thành công!', $aiResponse);
    }

    public function getHistory(): void {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->sendError('Vui lòng đăng nhập để xem lịch sử trò chuyện.', HttpStatusCodes::UNAUTHORIZED);
        }

        $payload = JwtConfig::decode($matches[1]);
        if (!$payload) {
            $this->sendError('Token không hợp lệ.', HttpStatusCodes::UNAUTHORIZED);
        }

        $history = AiChatService::getHistory((int)$payload['id']);
        $this->sendSuccess('Lấy lịch sử thành công!', $history);
    }

    /**
     * Danh sách hội thoại dành cho Admin
     */
    public function getAdminConversations(): void {
        AuthMiddleware::requireAdminOrStaff();
        $db = Database::getConnection();

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

        $this->sendSuccess('Lấy danh sách hội thoại thành công!', [
            'conversations' => $conversations,
            'supportStatus' => ['adminOnline' => true, 'effectiveBusy' => false]
        ]);
    }

    /**
     * Lấy lịch sử tin nhắn của một phiên hội thoại
     */
    public function getSessionMessages(string $sessionId): void {
        AuthMiddleware::requireAdminOrStaff();
        $customerId = (int)preg_replace('/\D/', '', $sessionId);
        if ($customerId <= 0) $customerId = 2;

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM chat_messages WHERE customer_id = ? ORDER BY id ASC LIMIT 100");
        $stmt->execute([$customerId]);
        $rows = $stmt->fetchAll();

        $messages = [];
        foreach ($rows as $r) {
            $messages[] = [
                'id'         => (int)$r['id'],
                'customerId' => (int)$r['customer_id'],
                'sender'     => $r['sender_role'] === 'customer' ? 'user' : ($r['sender_role'] === 'admin' ? 'admin' : 'assistant'),
                'senderRole' => $r['sender_role'],
                'message'    => $r['message'],
                'time'       => date('H:i', strtotime($r['created_at'])),
                'createdAt'  => $r['created_at']
            ];
        }

        $this->sendSuccess('Lấy lịch sử tin nhắn thành công!', $messages);
    }

    /**
     * Admin gửi tin nhắn phản hồi trực tiếp tới khách hàng
     */
    public function sendAdminReply(string $sessionId): void {
        $admin = AuthMiddleware::requireAdminOrStaff();
        $customerId = (int)preg_replace('/\D/', '', $sessionId);
        if ($customerId <= 0) $customerId = 2;

        $body = $this->getJsonBody();
        $message = trim($body['message'] ?? '');
        if (!$message) {
            $this->sendError('Tin nhắn không được để trống.', HttpStatusCodes::BAD_REQUEST);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO chat_messages (customer_id, sender_id, sender_role, message, created_at)
                              VALUES (?, ?, 'admin', ?, NOW())");
        $stmt->execute([$customerId, $admin['id'] ?? 1, $message]);
        $msgId = (int)$db->lastInsertId();

        $msgRecord = [
            'id'         => $msgId,
            'sessionId'  => $sessionId,
            'customerId' => $customerId,
            'sender'     => 'admin',
            'senderRole' => 'admin',
            'message'    => $message,
            'time'       => date('H:i'),
            'createdAt'  => date('Y-m-d H:i:s')
        ];

        $this->sendSuccess('Đã gửi phản hồi thành công!', [
            'message'       => $msgRecord,
            'aiPausedUntil' => date('c', strtotime('+15 minutes'))
        ]);
    }

    /**
     * Bật/Tắt trạng thái bận của Admin
     */
    public function updateAdminStatus(): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $busy = !empty($body['busy']);

        $this->sendSuccess('Cập nhật trạng thái thành công!', [
            'adminOnline'   => true,
            'adminManualBusy' => $busy,
            'effectiveBusy' => $busy
        ]);
    }

    /**
     * Tự động trả lời AI cho khách hàng
     */
    public function updateSessionAutomation(string $sessionId): void {
        AuthMiddleware::requireAdminOrStaff();
        $body = $this->getJsonBody();
        $enabled = !empty($body['enabled']);

        $this->sendSuccess('Cập nhật tự động thành công!', [
            'enabled' => $enabled
        ]);
    }
}
