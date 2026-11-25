<?php
/**
 * FASTFOOD - Đặt Lại Mật Khẩu (Reset Password Direct Link)
 * File: reset-password.php
 * Chuyển tiếp người dùng có token khôi phục đến bước 3 của quy trình đặt lại mật khẩu
 */
require_once __DIR__ . '/config/config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if (!$token) {
    header('Location: ' . getUrl('forgot-password.php?step=request'));
    exit;
}

$_GET['step'] = 'reset';
$_GET['token'] = $token;

require_once __DIR__ . '/forgot-password.php';
