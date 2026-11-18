<?php
/**
 * FASTFOOD - Direct Auth API Handler (/api/auth.php)
 * Hỗ trợ các hành động: login, register, me, profile, roles, logout
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload FastFood namespace
spl_autoload_register(function ($class) {
    $prefix = 'FastFood\\';
    $baseDir = dirname(__DIR__) . '/backend/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use FastFood\Controllers\AuthController;
use FastFood\Middlewares\ErrorHandlerMiddleware;
use FastFood\Utils\ApiResponse;

ErrorHandlerMiddleware::register();

$auth = new AuthController();
$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$action = $body['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'login':
        $auth->login();
        break;
    case 'register':
        $auth->register();
        break;
    case 'me':
        $auth->me();
        break;
    case 'profile':
        $auth->updateProfile();
        break;
    case 'roles':
        $auth->roles();
        break;
    case 'logout':
        $auth->logout();
        break;
    default:
        if ($method === 'POST') {
            if (isset($body['fullName']) || isset($_POST['fullName'])) {
                $auth->register();
            } else {
                $auth->login();
            }
        } elseif ($method === 'GET') {
            $auth->roles();
        } else {
            ApiResponse::error('Vui lòng chỉ định hành động hợp lệ (action: login, register, me, roles, logout).');
        }
        break;
}
