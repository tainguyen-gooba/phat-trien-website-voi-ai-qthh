<?php
/**
 * FASTFOOD - Quản lý đăng nhập, phiên Session và Phân quyền độc lập
 * Phân lập kép (Dual-Session Isolation):
 * - $_SESSION['admin_user'] dành riêng cho Cổng Quản Trị (/admin)
 * - $_SESSION['customer_user'] dành riêng cho Trang Khách Hàng (/)
 * Giúp người dùng mở song song Admin và Khách hàng trên cùng trình duyệt mà không bao giờ bị nhảy tài khoản!
 */

function isLoggedIn() {
    global $isAdminContext;
    if ($isAdminContext) {
        return !empty($_SESSION['admin_user']['id']) || (!empty($_SESSION['user_id']) && ((int)($_SESSION['user_role_id'] ?? 0) === 1 || (int)($_SESSION['user_role_id'] ?? 0) === 5));
    }
    return !empty($_SESSION['customer_user']['id']) || (!empty($_SESSION['user_id']) && (int)($_SESSION['user_role_id'] ?? 0) !== 1 && (int)($_SESSION['user_role_id'] ?? 0) !== 5);
}

function currentUser() {
    global $isAdminContext;
    if ($isAdminContext) {
        if (!empty($_SESSION['admin_user'])) {
            return $_SESSION['admin_user'];
        }
        if (!empty($_SESSION['user_id']) && ((int)($_SESSION['user_role_id'] ?? 0) === 1 || (int)($_SESSION['user_role_id'] ?? 0) === 5)) {
            return [
                'id'        => (int)$_SESSION['user_id'],
                'full_name' => $_SESSION['user_name'] ?? 'Quản trị viên',
                'name'      => $_SESSION['user_name'] ?? 'Quản trị viên',
                'email'     => $_SESSION['user_email'] ?? '',
                'phone'     => $_SESSION['user_phone'] ?? '',
                'role_id'   => (int)($_SESSION['user_role_id'] ?? 1),
                'role'      => $_SESSION['user_role'] ?? 'admin',
                'avatar'    => $_SESSION['user_avatar'] ?? '',
                'avatar_url'=> $_SESSION['user_avatar'] ?? '',
            ];
        }
        return null;
    }

    // Ngữ cảnh Khách Hàng
    if (!empty($_SESSION['customer_user'])) {
        return $_SESSION['customer_user'];
    }
    if (!empty($_SESSION['user_id']) && (int)($_SESSION['user_role_id'] ?? 0) !== 1 && (int)($_SESSION['user_role_id'] ?? 0) !== 5) {
        return [
            'id'        => (int)$_SESSION['user_id'],
            'full_name' => $_SESSION['user_name'] ?? 'Khách hàng',
            'name'      => $_SESSION['user_name'] ?? 'Khách hàng',
            'email'     => $_SESSION['user_email'] ?? '',
            'phone'     => $_SESSION['user_phone'] ?? '',
            'role_id'   => (int)($_SESSION['user_role_id'] ?? 2),
            'role'      => $_SESSION['user_role'] ?? 'customer',
            'avatar'    => $_SESSION['user_avatar'] ?? '',
            'avatar_url'=> $_SESSION['user_avatar'] ?? '',
        ];
    }
    return null;
}

function isAdmin() {
    $user = currentUser();
    if (!$user) return false;
    return (int)($user['role_id'] ?? 0) === 1;
}

function isStaff() {
    $user = currentUser();
    if (!$user) return false;
    return (int)($user['role_id'] ?? 0) === 5;
}

function isCustomer() {
    $user = currentUser();
    if (!$user) return false;
    return (int)($user['role_id'] ?? 0) === 2;
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
    if (!isAdmin()) {
        if (!isLoggedIn()) {
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
        die('<h1>403 Forbidden</h1><p>Bạn không có quyền quản trị tối cao (Admin) để truy cập trang này.</p>');
    }
}

function requireAdminOrStaff() {
    if (!isAdminOrStaff()) {
        if (!isLoggedIn()) {
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
        die('<h1>403 Forbidden</h1><p>Khu vực này chỉ dành cho Ban Quản Trị và Nhân viên hệ thống.</p>');
    }
}

function setUserSession($user, $forceContext = null) {
    $roleId = (int)($user['role_id'] ?? 2);
    $isAdminRole = ($roleId === 1 || $roleId === 5);

    $roleMap = [
        1 => 'admin',
        2 => 'customer',
        3 => 'restaurant',
        4 => 'shipper',
        5 => 'staff',
    ];
    $roleName = $roleMap[$roleId] ?? 'customer';

    $userData = [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'name'      => $user['full_name'],
        'email'     => $user['email'] ?? '',
        'phone'     => $user['phone'] ?? '',
        'role_id'   => $roleId,
        'role'      => $roleName,
        'avatar'    => $user['avatar_url'] ?? '',
        'avatar_url'=> $user['avatar_url'] ?? '',
    ];

    if ($isAdminRole || $forceContext === 'admin') {
        $_SESSION['admin_user'] = $userData;
        $_SESSION['admin_user_id'] = (int)$user['id'];
    } else {
        $userId = (int)$user['id'];
        $_SESSION['customer_user'] = $userData;
        $_SESSION['user_id']      = $userId;
        $_SESSION['user_name']    = $user['full_name'];
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['user_phone']   = $user['phone'] ?? '';
        $_SESSION['user_role_id'] = $roleId;
        $_SESSION['user_role']    = $roleName;
        $_SESSION['user_avatar']  = $user['avatar_url'] ?? '';

        // TÁCH BIỆT GIỎ HÀNG THEO TÀI KHOẢN (Per-User Cart Isolation)
        if (!isset($_SESSION['user_carts'])) {
            $_SESSION['user_carts'] = [];
        }
        $_SESSION['cart'] = $_SESSION['user_carts'][$userId] ?? [];

        // TÁCH BIỆT MÓN ĂN YÊU THÍCH THEO TÀI KHOẢN (Per-User Favorites Isolation)
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT product_id FROM user_favorites WHERE user_id = ?");
            $stmt->execute([$userId]);
            $_SESSION['user_favorites'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            $_SESSION['user_favorites'] = [];
        }
    }
}

function logoutUser($context = null) {
    global $isAdminContext;
    $target = $context ?: ($isAdminContext ? 'admin' : 'customer');
    if ($target === 'admin') {
        unset($_SESSION['admin_user'], $_SESSION['admin_user_id']);
    } else {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid > 0 && isset($_SESSION['cart'])) {
            $_SESSION['user_carts'][$uid] = $_SESSION['cart'];
        }
        unset(
            $_SESSION['customer_user'],
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_email'],
            $_SESSION['user_phone'],
            $_SESSION['user_role_id'],
            $_SESSION['user_role'],
            $_SESSION['user_avatar'],
            $_SESSION['cart'],
            $_SESSION['user_favorites'],
            $_SESSION['applied_coupon']
        );
    }
}

