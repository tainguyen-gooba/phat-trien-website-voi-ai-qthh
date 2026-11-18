<?php
/**
 * FASTFOOD - Quản lý đăng nhập, phiên Session và Phân quyền
 */

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['user_name'] ?? 'Người dùng',
        'email'     => $_SESSION['user_email'] ?? '',
        'phone'     => $_SESSION['user_phone'] ?? '',
        'role_id'   => (int)($_SESSION['user_role_id'] ?? 2),
        'role'      => $_SESSION['user_role'] ?? 'customer',
        'avatar'    => $_SESSION['user_avatar'] ?? '',
    ];
}

function isAdmin() {
    return isLoggedIn() && (int)($_SESSION['user_role_id'] ?? 0) === 1;
}

function isStaff() {
    return isLoggedIn() && (int)($_SESSION['user_role_id'] ?? 0) === 5;
}

function isCustomer() {
    return isLoggedIn() && (int)($_SESSION['user_role_id'] ?? 0) === 2;
}

function isAdminOrStaff() {
    return isAdmin() || isStaff();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getUrl('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('<h1>403 Forbidden</h1><p>Bạn không có quyền quản trị tối cao (Admin) để truy cập trang này.</p>');
    }
}

function requireAdminOrStaff() {
    requireLogin();
    if (!isAdminOrStaff()) {
        die('<h1>403 Forbidden</h1><p>Khu vực này chỉ dành cho Ban Quản Trị và Nhân viên hệ thống.</p>');
    }
}

function setUserSession($user) {
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['full_name'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['user_phone']   = $user['phone'] ?? '';
    $_SESSION['user_role_id'] = (int)$user['role_id'];
    $_SESSION['user_avatar']  = $user['avatar_url'] ?? '';

    $roleMap = [
        1 => 'admin',
        2 => 'customer',
        3 => 'restaurant',
        4 => 'shipper',
        5 => 'staff',
    ];
    $_SESSION['user_role'] = $roleMap[(int)$user['role_id']] ?? 'customer';
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

