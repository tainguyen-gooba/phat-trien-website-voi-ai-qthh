<?php
namespace FastFood\Middlewares;

use FastFood\Utils\ApiResponse;
use FastFood\Constants\HttpStatusCodes;

/**
 * Chat Rate Limit Middleware (max 30 requests per minute)
 */
class ChatRateLimit {
    public static function check(int $maxPerMinute = 30): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rate_limit_' . md5($ip);
        $tempFile = sys_get_temp_dir() . '/' . $key . '.json';

        $now = time();
        $history = [];
        if (file_exists($tempFile)) {
            $content = file_get_contents($tempFile);
            $history = json_decode($content, true) ?: [];
        }

        $history = array_filter($history, fn($ts) => $ts > ($now - 60));

        if (count($history) >= $maxPerMinute) {
            ApiResponse::error('Bạn đang gửi quá nhiều tin nhắn. Vui lòng thử lại sau 1 phút.', HttpStatusCodes::TOO_MANY_REQUESTS);
        }

        $history[] = $now;
        file_put_contents($tempFile, json_encode($history));
    }
}

