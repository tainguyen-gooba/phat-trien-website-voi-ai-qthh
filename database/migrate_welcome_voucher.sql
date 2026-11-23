-- Migration: FASTFOOD Welcome Voucher 50.000đ & Anti-Duplication System
-- Target: fastfood_db (MySQL / MariaDB Port 3301)

USE `fastfood_db`;

-- 1. Thêm cột cccd vào bảng users nếu chưa có
SET @col_cccd_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'cccd'
);

SET @stmt_cccd = IF(@col_cccd_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `cccd` VARCHAR(20) DEFAULT NULL AFTER `phone`, ADD UNIQUE KEY `uq_users_cccd` (`cccd`)',
    'SELECT "Column users.cccd already exists" AS info'
);
PREPARE s_cccd FROM @stmt_cccd;
EXECUTE s_cccd;
DEALLOCATE PREPARE s_cccd;

-- 2. Tạo bảng lưu trữ thông tin nhận voucher và đối soát trùng lặp
CREATE TABLE IF NOT EXISTS `customer_voucher_claims` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `identity_number` varchar(50) NOT NULL COMMENT 'Số định danh cá nhân CCCD/CMND',
  `address` varchar(255) DEFAULT NULL COMMENT 'Địa chỉ nhận hàng khách xác nhận',
  `coupon_id` bigint(20) unsigned DEFAULT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_voucher` (`user_id`),
  UNIQUE KEY `uniq_identity_voucher` (`identity_number`),
  KEY `idx_phone_voucher` (`phone`),
  CONSTRAINT `fk_claims_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Đảm bảo mã mẫu NEW50K cố định 50.000đ chuẩn tồn tại trong hệ thống
INSERT INTO `coupons` (`code`, `title`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `usage_count`, `start_date`, `end_date`, `is_active`, `created_at`)
SELECT 'NEW50K', 'Voucher bạn mới - Giảm ngay 50.000đ', 'fixed', 50000.00, 0.00, 50000.00, 10000, 0, '2026-01-01 00:00:00', '2027-12-31 23:59:59', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `coupons` WHERE `code` = 'NEW50K');
