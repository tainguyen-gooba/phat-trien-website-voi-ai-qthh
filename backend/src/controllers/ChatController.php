<?php
namespace FastFood\Controllers;

use FastFood\Services\AiService;
use FastFood\Services\AiChatService;
use FastFood\Middlewares\ChatRateLimit;
use FastFood\Config\JwtConfig;
use FastFood\Constants\HttpStatusCodes;

/**
 * AI Chat Assistant Controller
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
}

