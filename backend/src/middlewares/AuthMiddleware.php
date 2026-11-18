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

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ApiResponse::error('Thiếu Authorization Token hoặc token không hợp lệ.', HttpStatusCodes::UNAUTHORIZED);
        }

        $token = $matches[1];
        $payload = JwtConfig::decode($token);

        if (!$payload) {
            ApiResponse::error('Token đã hết hạn hoặc không hợp lệ. Vui lòng đăng nhập lại.', HttpStatusCodes::UNAUTHORIZED);
        }

        return $payload;
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

