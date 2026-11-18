<?php
/**
 * FASTFOOD - Đăng Xuất Quản Trị Hệ Thống
 * Hủy phiên FF_SESS_ADMIN và điều hướng về trang đăng nhập Admin
 * Phiên người dùng (FASTFOOD_SESSID) hoàn toàn không bị ảnh hưởng!
 */
require_once dirname(__DIR__) . '/config/config.php';

logoutUser();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
