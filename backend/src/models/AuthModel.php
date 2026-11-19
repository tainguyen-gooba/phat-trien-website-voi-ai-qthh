<?php
namespace FastFood\Models;

use PDO;

/**
 * Auth & User Model
 */
class AuthModel extends BaseModel {
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPhone(string $phone): ?array {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (!$clean) return null;
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone = ? OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+84', '0') = ? LIMIT 1");
        $stmt->execute([trim($phone), $clean]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT id, full_name, email, phone, address, role_id, status, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO users (full_name, email, password_hash, phone, address, role_id, status, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $data['fullName'] ?? $data['full_name'] ?? 'Khách Hàng',
            trim($data['email']),
            $data['passwordHash'] ?? $data['password_hash'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            (int)($data['roleId'] ?? $data['role_id'] ?? 2),
            $data['status'] ?? 'active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Tạo mã voucher 50% chào mừng khách hàng mới
     */
    public function createWelcomeVoucher(int $userId, string $fullName = 'Khách Hàng'): array {
        $userCode = 'NEW50-' . strtoupper(substr(md5($userId . uniqid((string)mt_rand(), true)), 0, 6));
        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+30 days'));

        // 1. Đảm bảo coupon mẫu NEW50 chung tồn tại
        try {
            $checkGlobal = $this->db->prepare("SELECT id FROM coupons WHERE code = 'NEW50' LIMIT 1");
            $checkGlobal->execute();
            if (!$checkGlobal->fetch()) {
                $insGlobal = $this->db->prepare("INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
                                                 VALUES ('NEW50', 'Voucher bạn mới - Giảm ngay 50%', 'percentage', 50.00, 0.00, 50000.00, 10000, 0, '2026-01-01 00:00:00', '2027-12-31 23:59:59', 1, NOW())");
                $insGlobal->execute();
            }
        } catch (\Exception $e) {}

        // 2. Tạo mã Voucher riêng cho khách hàng này (Giảm 50%)
        $stmt = $this->db->prepare("INSERT INTO coupons (user_id, code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active, created_at)
                                    VALUES (?, ?, ?, 'percentage', 50.00, 0.00, 50000.00, 1, 0, ?, ?, 1, NOW())");
        $stmt->execute([
            $userId,
            $userCode,
            'Voucher chào mừng bạn mới - Giảm 50%',
            $startDate,
            $endDate
        ]);
        $couponId = (int)$this->db->lastInsertId();

        // 3. Gửi thông báo chào mừng và tặng voucher
        try {
            $notif = $this->db->prepare("INSERT INTO notifications (user_id, title, content, type, is_read, created_at)
                                         VALUES (?, ?, ?, 'promotion', 0, NOW())");
            $notif->execute([
                $userId,
                '🎉 Tặng bạn mới Voucher Giảm 50%!',
                'Chào mừng ' . $fullName . ' gia nhập FASTFOOD! Bạn đã được tặng Voucher giảm trực tiếp 50% (Mã: ' . $userCode . ' hoặc NEW50) cho đơn hàng đầu tiên.'
            ]);
        } catch (\Exception $e) {}

        return [
            'id'                  => $couponId,
            'code'                => $userCode,
            'aliasCode'           => 'NEW50',
            'title'               => 'Voucher chào mừng bạn mới - Giảm 50%',
            'discount_type'       => 'percentage',
            'discount_value'      => 50.00,
            'formatted_discount'  => '50%',
            'min_order_value'     => 0.00,
            'max_discount_amount' => 50000.00,
            'expires_at'          => $endDate,
            'message'             => 'Bạn đã được tặng Voucher giảm 50% cho đơn hàng đầu tiên!'
        ];
    }

    public function updateProfile(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([
            $data['fullName'],
            $data['phone'],
            $data['address'],
            $id
        ]);
    }
}
