<?php
namespace FastFood\Controllers;

use FastFood\Services\AuthService;
use FastFood\Middlewares\AuthMiddleware;
use FastFood\Utils\CustomerProfileValidation;
use FastFood\Constants\HttpStatusCodes;

/**
 * Auth Controller
 */
class AuthController extends BaseController {
    private AuthService $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    public function login(): void {
        $body = $this->getJsonBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            $this->sendError('Vui lòng nhập đầy đủ email và mật khẩu.', HttpStatusCodes::BAD_REQUEST);
        }

        try {
            $result = $this->authService->login($email, $password);
            if (!$result) {
                $this->sendError('Email hoặc mật khẩu không chính xác.', HttpStatusCodes::UNAUTHORIZED);
            }

            $this->sendSuccess('Đăng nhập thành công!', $result);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), HttpStatusCodes::FORBIDDEN);
        }
    }

    public function register(): void {
        $body = $this->getJsonBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $fullName = trim($body['fullName'] ?? ($body['full_name'] ?? 'Khách Hàng Mới'));
        $phone = trim($body['phone'] ?? '');
        $address = trim($body['address'] ?? '');
        $role = $body['role'] ?? ($body['roleId'] ?? null);

        if (!$email || !$password) {
            $this->sendError('Vui lòng điền đầy đủ email và mật khẩu.', HttpStatusCodes::BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Email không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            $this->sendError('Mật khẩu tối thiểu 6 ký tự.', HttpStatusCodes::BAD_REQUEST);
        }

        try {
            $payload = [
                'email'     => $email,
                'password'  => $password,
                'fullName'  => $fullName,
                'phone'     => $phone,
                'address'   => $address
            ];
            if ($role !== null) {
                $payload['role'] = $role;
            }

            $result = $this->authService->register($payload);
            $this->sendCreated('Đăng ký tài khoản thành công! Tặng bạn Voucher 50.000đ cho đơn đầu tiên.', $result);
        } catch (\InvalidArgumentException $e) {
            $this->sendError($e->getMessage(), HttpStatusCodes::BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $this->sendError($e->getMessage(), HttpStatusCodes::CONFLICT);
        } catch (\Exception $e) {
            $this->sendError('Lỗi đăng ký tài khoản: ' . $e->getMessage(), HttpStatusCodes::INTERNAL_SERVER_ERROR);
        }
    }

    public function roles(): void {
        $roles = \FastFood\Constants\Roles::getAllRoles();
        $this->sendSuccess('Lấy danh sách phân quyền thành công!', ['roles' => $roles]);
    }

    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
        $this->sendSuccess('Đăng xuất thành công!');
    }

    public function me(): void {
        $user = AuthMiddleware::authenticate();
        $profile = $this->authService->getProfile($user['id']);
        $this->sendSuccess('Lấy thông tin tài khoản thành công!', ['user' => $profile]);
    }

    public function updateProfile(): void {
        $user = AuthMiddleware::authenticate();
        $body = $this->getJsonBody();
        $norm = CustomerProfileValidation::normalize($body);

        if (!$norm['valid']) {
            $this->sendError(implode(' ', $norm['errors']), HttpStatusCodes::BAD_REQUEST);
        }

        $this->authService->updateProfile($user['id'], $norm);
        $updated = $this->authService->getProfile($user['id']);
        $this->sendSuccess('Cập nhật thông tin thành công!', ['user' => $updated]);
    }
}

