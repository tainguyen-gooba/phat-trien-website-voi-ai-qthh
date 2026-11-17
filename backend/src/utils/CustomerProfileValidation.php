<?php
namespace FastFood\Utils;

/**
 * Customer Profile Validation Helper
 */
class CustomerProfileValidation {
    public static function normalize(array $input): array {
        $fullName = trim($input['fullName'] ?? $input['full_name'] ?? '');
        $phone = preg_replace('/[^\d+]/', '', $input['phone'] ?? '');
        $address = trim($input['address'] ?? $input['default_address'] ?? '');

        $errors = [];
        if (mb_strlen($fullName) < 2) {
            $errors['fullName'] = 'Họ và tên tối thiểu 2 ký tự.';
        }
        if (!preg_match('/^(0|\+84)[35789]\d{8}$/', $phone)) {
            $errors['phone'] = 'Số điện thoại không hợp lệ (cần 10 số, bắt đầu bằng 03, 05, 07, 08, 09).';
        }
        if (mb_strlen($address) < 5) {
            $errors['address'] = 'Địa chỉ giao hàng tối thiểu 5 ký tự.';
        }

        return [
            'valid' => empty($errors),
            'fullName' => $fullName,
            'phone' => $phone,
            'address' => $address,
            'errors' => $errors,
        ];
    }
}

