<?php
/**
 * FASTFOOD - Cấu hình hệ thống chung
 */

// ====================================================================
// MULTI-TAB SESSION ISOLATION ENGINE
// Phân lập hoàn toàn phiên làm việc giữa Tab Quản Trị (/admin) và Tab Khách Hàng (/)
// Giúp mở song song nhiều tab Google Chrome mà KHÔNG BAO GIỜ bị đè tài khoản của nhau
// ====================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$referer    = $_SERVER['HTTP_REFERER'] ?? '';
$reqRole    = $_SERVER['HTTP_X_REQUESTED_ROLE'] ?? '';

// Tự động nhận diện ngữ cảnh Admin:
$isAdminContext = (
    strpos($requestUri, '/admin') !== false ||
    strpos($requestUri, 'admin/api.php') !== false ||
    preg_match('#^/admin(?:/|$|\?)#i', $requestUri) !== 0 ||
    strpos($requestUri, 'admin/index.php') !== false ||
    strpos($requestUri, 'admin/login.php') !== false ||
    strpos($requestUri, 'admin/logout.php') !== false ||
    (!empty($_GET['role']) && $_GET['role'] === 'admin') ||
    (!empty($_POST['role']) && $_POST['role'] === 'admin') ||
    (!empty($_GET['for']) && $_GET['for'] === 'admin') ||
    (!empty($_POST['for']) && $_POST['for'] === 'admin') ||
    (strcasecmp($reqRole, 'admin') === 0) ||
    // API gọi từ trang Admin
    (strpos($requestUri, '/api/') !== false && strpos($referer, '/admin') !== false)
);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    if ($isAdminContext) {
        @session_name('FF_SESS_ADMIN');
    } else {
        @session_name('FASTFOOD_SESSID');
    }
    @session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Nếu đang ở trang khách hàng mà chưa có phiên khách hàng, nhưng Admin đã đăng nhập ở FF_SESS_ADMIN
// -> Cho phép Admin truy cập website với tài khoản Admin (không ảnh hưởng nếu đã có khách hàng đăng nhập)
$GLOBALS['FF_FALLBACK_ADMIN_USER'] = null;
if (
    !$isAdminContext &&
    empty($_SESSION['customer_user']['id']) &&
    empty($_SESSION['user_id']) &&
    !empty($_COOKIE['FF_SESS_ADMIN']) &&
    preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $_COOKIE['FF_SESS_ADMIN'])
) {
    $customerSessId = session_id();
    $customerSessName = session_name();
    @session_write_close();
    @session_name('FF_SESS_ADMIN');
    @session_id($_COOKIE['FF_SESS_ADMIN']);
    @session_start();
    if (!empty($_SESSION['admin_user']['id'])) {
        $GLOBALS['FF_FALLBACK_ADMIN_USER'] = $_SESSION['admin_user'];
    }
    @session_write_close();
    // Khôi phục lại phiên khách hàng
    @session_name($customerSessName ?: 'FASTFOOD_SESSID');
    if ($customerSessId) {
        @session_id($customerSessId);
    }
    @session_start();
}

define('ROOT_PATH', dirname(__DIR__));

// Tự động tính BASE_URL chuẩn
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$dir = dirname($scriptName);

// Tìm base folder của website
$cleanDir = rtrim($dir, '/\\');
if ($cleanDir === '.' || $cleanDir === '/' || $cleanDir === '\\') {
    $cleanDir = '';
}
if (preg_match('#/(admin|api|pages|includes)#', $cleanDir, $matches)) {
    $cleanDir = substr($cleanDir, 0, strpos($cleanDir, $matches[0]));
}
$cleanDir = rtrim($cleanDir, '/\\');
if ($cleanDir !== '' && $cleanDir[0] !== '/') {
    $cleanDir = '/' . $cleanDir;
}
define('BASE_URL', rtrim($protocol . $host . $cleanDir, '/'));

// Cấu hình ngân hàng SePay / VietQR
define('SEPAY_BANK_CODE', 'BIDV');
define('SEPAY_ACCOUNT_NUMBER', '8855033140');
define('SEPAY_ACCOUNT_NAME', 'NGUYEN HIEN TAI');

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/auth.php';
