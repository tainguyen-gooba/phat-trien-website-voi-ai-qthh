<?php
/**
 * FASTFOOD - Cấu hình hệ thống chung
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
