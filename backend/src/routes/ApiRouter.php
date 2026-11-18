<?php
namespace FastFood\Routes;

use FastFood\Controllers\AuthController;
use FastFood\Controllers\HealthController;
use FastFood\Controllers\ImageUploadController;
use FastFood\Controllers\PaymentController;
use FastFood\Controllers\RealtimeController;
use FastFood\Controllers\ChatController;
use FastFood\Utils\ApiResponse;
use FastFood\Constants\HttpStatusCodes;

/**
 * Main API Router
 */
class ApiRouter {
    public static function dispatch(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Normalize URI: strip project subfolders and prefix
        $path = preg_replace('#^.*?(?:/backend)?/api/v1#', '', $uri);
        $path = '/' . trim($path, '/');

        $auth = new AuthController();
        $health = new HealthController();
        $upload = new ImageUploadController();
        $payment = new PaymentController();
        $realtime = new RealtimeController();
        $chat = new ChatController();

        // 1. Health routes
        if ($path === '/health' && $method === 'GET') {
            $health->getSystemHealth();
        }
        if ($path === '/health/db' && $method === 'GET') {
            $health->getDatabaseHealth();
        }

        // 2. Auth routes
        if ($path === '/auth/login' && $method === 'POST') {
            $auth->login();
        }
        if ($path === '/auth/register' && $method === 'POST') {
            $auth->register();
        }
        if ($path === '/auth/me' && $method === 'GET') {
            $auth->me();
        }
        if ($path === '/auth/profile' && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $auth->updateProfile();
        }

        // 3. Realtime Content & Orders
        if ($path === '/realtime/content' && $method === 'GET') {
            $realtime->getContent();
        }
        if (preg_match('#^/realtime/content/([a-zA-Z0-9_-]+)$#', $path, $m) && in_array($method, ['PUT', 'POST'])) {
            $realtime->updateContent($m[1]);
        }
        if ($path === '/realtime/orders' && $method === 'GET') {
            $realtime->getOrders();
        }
        if ($path === '/realtime/orders/reviews' && $method === 'GET') {
            $realtime->getReviews();
        }
        if (preg_match('#^/realtime/orders/([^/]+)/status$#', $path, $m) && in_array($method, ['PATCH', 'POST', 'PUT'])) {
            $realtime->updateOrderStatus($m[1]);
        }
        if ($path === '/realtime/uploads/product-image' && $method === 'POST') {
            $upload->upload();
        }
        if ($path === '/realtime/chat/ai/copilot' && $method === 'POST') {
            $chat->handleChat();
        }

        // 4. Chat routes
        if ($path === '/chat/message' && $method === 'POST') {
            $chat->handleChat();
        }
        if ($path === '/chat/history' && $method === 'GET') {
            $chat->getHistory();
        }

        // 5. Payment routes
        if ($path === '/payment/sepay/webhook' && in_array($method, ['POST', 'GET'])) {
            $payment->handleWebhook();
        }
        if ($path === '/payment/vietqr' && $method === 'POST') {
            $payment->createVietQr();
        }
        if (preg_match('#^/payment/([^/]+)/status$#', $path, $m) && $method === 'GET') {
            $payment->getPaymentStatus($m[1]);
        }

        // Not Found
        ApiResponse::error("Endpoint [{$method}] {$path} không tồn tại trên hệ thống API.", HttpStatusCodes::NOT_FOUND);
    }
}
