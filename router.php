<?php
/**
 * FASTFOOD - Built-in PHP Server Router
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Chuyển hướng các trang cũ .html sang .php
$htmlToPhp = [
    '/index.html'             => '/index.php',
    '/pages/login.html'       => '/login.php',
    '/login.html'             => '/login.php',
    '/pages/register.html'    => '/register.php',
    '/register.html'          => '/register.php',
    '/pages/cart.html'        => '/cart.php',
    '/cart.html'              => '/cart.php',
    '/pages/checkout.html'    => '/checkout.php',
    '/checkout.html'          => '/checkout.php',
    '/pages/foods.html'       => '/foods.php',
    '/foods.html'             => '/foods.php',
    '/pages/restaurants.html' => '/restaurants.php',
    '/restaurants.html'       => '/restaurants.php',
    '/pages/profile.html'     => '/profile.php',
    '/profile.html'           => '/profile.php',
    '/admin/login.php'        => '/login.php?for=admin',
    '/admin/login.html'       => '/login.php?for=admin',
];

if (isset($htmlToPhp[$uri])) {
    $status = (strpos($uri, '/admin/login') === 0) ? 302 : 301;
    header('Location: ' . $htmlToPhp[$uri], true, $status);
    exit;
}

// 2. Chuyển tiếp toàn bộ API RESTful /api/v1/ sang backend/index.php
if (preg_match('#^/api/v1(?:/|$)#', $uri)) {
    require_once __DIR__ . '/backend/index.php';
    exit;
}

// 3. Phục vụ file tĩnh có sẵn (css, js, ảnh, fonts...)
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Để PHP Server tự phục vụ file tĩnh
}

// 4. Nếu là thư mục, tìm index.php trong thư mục đó
if (is_dir($filePath) && file_exists($filePath . '/index.php')) {
    require_once $filePath . '/index.php';
    exit;
}

// 5. Mặc định phục vụ index.php nếu không tìm thấy
if ($uri === '/' || $uri === '') {
    require_once __DIR__ . '/index.php';
    exit;
}

return false;
