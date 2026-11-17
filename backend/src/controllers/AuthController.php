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

        $result = $this->authService->login($email, $password);
        if (!$result) {
            $this->sendError('Email hoặc mật khẩu không chính xác.', HttpStatusCodes::UNAUTHORIZED);
        }

        $this->sendSuccess('Đăng nhập thành công!', $result);
    }

    public function register(): void {
        $body = $this->getJsonBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $fullName = trim($body['fullName'] ?? 'Khách Hàng Mới');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Email không hợp lệ.', HttpStatusCodes::BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            $this->sendError('Mật khẩu tối thiểu 6 ký tự.', HttpStatusCodes::BAD_REQUEST);
        }

        try {
            $result = $this->authService->register([
                'email' => $email,
                'password' => $password,
                'fullName' => $fullName
            ]);
            $this->sendCreated('Đăng ký tài khoản thành công!', $result);
        } catch (\Exception $e) {
            $this->sendError('Email đã được đăng ký hoặc có lỗi xảy ra.', HttpStatusCodes::CONFLICT);
        }
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

