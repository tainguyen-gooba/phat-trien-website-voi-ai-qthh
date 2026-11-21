<?php
namespace FastFood\Middlewares;

use FastFood\Config\JwtConfig;
use FastFood\Utils\ApiResponse;
use FastFood\Constants\HttpStatusCodes;
use FastFood\Constants\Roles;

/**
 * Authentication Middleware
 */
class AuthMiddleware {
    public static function authenticate(): array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = JwtConfig::decode($token);
            if ($payload) {
                return $payload;
            }
        }

        // Hỗ trợ phiên đăng nhập PHP Session (Tab Admin hoặc Khách Hàng)
        if (session_status() === PHP_SESSION_NONE) {
            if (!empty($_COOKIE['FF_SESS_ADMIN'])) {
                session_name('FF_SESS_ADMIN');
            } elseif (!empty($_COOKIE['FASTFOOD_SESSID'])) {
                session_name('FASTFOOD_SESSID');
            } else {
                foreach ($_COOKIE as $k => $v) {
                    if (str_starts_with($k, 'FF_SESS_')) {
                        session_name($k);
                        break;
                    }
                }
            }
            @session_start();
        }

        $sessionUser = null;
        if (!empty($_SESSION['user_id'])) {
            $sessionUser = [
                'id'       => (int)$_SESSION['user_id'],
                'roleId'   => (int)($_SESSION['user_role_id'] ?? 4),
                'role'     => $_SESSION['user_role'] ?? 'customer',
                'fullName' => $_SESSION['user_name'] ?? 'Người dùng',
                'email'    => $_SESSION['user_email'] ?? ''
            ];
        }

        // Giải phóng khóa file session ngay lập tức để không gây nghẽn PHP đơn luồng
        session_write_close();

        if ($sessionUser) {
            return $sessionUser;
        }

        ApiResponse::error('Thiếu Authorization Token hoặc phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', HttpStatusCodes::UNAUTHORIZED);
    }

    public static function requireAdmin(): array {
        $user = self::authenticate();
        if (($user['roleId'] ?? 0) !== Roles::ADMIN) {
            ApiResponse::error('Bạn không có quyền quản trị viên.', HttpStatusCodes::FORBIDDEN);
        }
        return $user;
    }

    public static function requireAdminOrStaff(): array {
        $user = self::authenticate();
        $roleId = $user['roleId'] ?? 0;
        if ($roleId !== Roles::ADMIN && $roleId !== Roles::STAFF) {
            ApiResponse::error('Bạn không có quyền truy cập khu vực quản trị/nhân viên.', HttpStatusCodes::FORBIDDEN);
        }
        return $user;
    }
}

