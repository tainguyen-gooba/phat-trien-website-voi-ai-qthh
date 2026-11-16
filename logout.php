<?php
/**
 * FASTFOOD - Đăng xuất tài khoản (Logout)
 */
require_once __DIR__ . '/config/config.php';

logoutUser();
header('Location: ' . getUrl('login.php'));
exit;

