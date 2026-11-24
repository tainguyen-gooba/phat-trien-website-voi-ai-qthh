-- Migration: Hoàn thiện Hệ thống Voucher Shop & Voucher Sinh Nhật
-- Bổ sung trường date_of_birth vào users và tạo các bảng:
-- vouchers, user_vouchers, voucher_usages, voucher_conditions, birthday_voucher_logs

-- 1. Bổ sung ngày sinh vào bảng users
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER cccd;

-- 2. Cập nhật bảng coupons để tương thích hoàn toàn 4 loại voucher
ALTER TABLE coupons 
MODIFY COLUMN discount_type VARCHAR(30) NOT NULL DEFAULT 'percentage';

ALTER TABLE coupons 
ADD COLUMN IF NOT EXISTS voucher_type VARCHAR(30) NOT NULL DEFAULT 'percentage' AFTER discount_type;

ALTER TABLE coupons 
ADD COLUMN IF NOT EXISTS min_member_tier VARCHAR(20) NOT NULL DEFAULT 'ALL' AFTER max_discount_amount;

-- 3. Tạo bảng vouchers (Bảng chuyên biệt quản lý Voucher Shop)
CREATE TABLE IF NOT EXISTS vouchers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    voucher_type ENUM('percentage', 'fixed', 'free_shipping', 'bogo') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    min_order_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_discount_amount DECIMAL(12,2) NULL DEFAULT NULL,
    usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    user_limit INT UNSIGNED NOT NULL DEFAULT 1,
    min_member_tier ENUM('ALL', 'BRONZE', 'SILVER', 'GOLD', 'DIAMOND') NOT NULL DEFAULT 'ALL',
    is_birthday_voucher TINYINT(1) NOT NULL DEFAULT 0,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_type (voucher_type),
    INDEX idx_tier (min_member_tier),
    INDEX idx_active_date (is_active, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng user_vouchers (Ví voucher khách hàng)
CREATE TABLE IF NOT EXISTS user_vouchers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    source ENUM('claim', 'birthday', 'loyalty', 'admin') NOT NULL DEFAULT 'claim',
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    order_id BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    UNIQUE KEY uq_user_voucher_source (user_id, voucher_id, source, assigned_at),
    INDEX idx_user (user_id),
    INDEX idx_voucher (voucher_id),
    INDEX idx_used (is_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng voucher_usages (Ghi nhận chi tiết lịch sử sử dụng voucher)
CREATE TABLE IF NOT EXISTS voucher_usages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    order_code VARCHAR(50) NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_voucher (voucher_id),
    INDEX idx_user (user_id),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng voucher_conditions (Điều kiện áp dụng voucher)
CREATE TABLE IF NOT EXISTS voucher_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_id BIGINT UNSIGNED NOT NULL,
    condition_type VARCHAR(50) NOT NULL,
    condition_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_voucher (voucher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng birthday_voucher_logs (Lịch sử tặng voucher sinh nhật hằng năm)
CREATE TABLE IF NOT EXISTS birthday_voucher_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    birth_year INT UNSIGNED NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    tier_at_birth VARCHAR(20) NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL,
    notification_sent TINYINT(1) NOT NULL DEFAULT 1,
    gifted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_year (user_id, birth_year),
    INDEX idx_user (user_id),
    INDEX idx_year (birth_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Đồng bộ các voucher mẫu ban đầu vào bảng vouchers
INSERT INTO vouchers (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_public, start_date, end_date, is_active)
VALUES
('FASTFOOD20', 'Giảm 20.000đ cho đơn hàng từ 60.000đ', 'Áp dụng cho toàn bộ menu FASTFOOD khi đạt đơn tối thiểu', 'fixed', 20000.00, 60000.00, 20000.00, 1000, 0, 1, 'ALL', 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('FREESHIP18', 'Miễn phí giao hàng (Tối đa 18.000đ)', 'Freeship mọi nẻo đường cho đơn hàng từ 100.000đ', 'free_shipping', 18000.00, 100000.00, 18000.00, 2000, 0, 1, 'ALL', 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('BURGER30', 'Ưu đãi Burger Giờ Vàng - Giảm 30.000đ', 'Giảm ngay 30k cho combo burger từ 120.000đ', 'fixed', 30000.00, 120000.00, 30000.00, 500, 0, 1, 'ALL', 1, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1),
('COMBO15', 'Giảm 15% tối đa 40.000đ cho Combo gia đình', 'Áp dụng cho mọi bữa tiệc ngon cùng người thân', 'percentage', 15.00, 80000.00, 40000.00, 800, 0, 1, 'ALL', 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('BOGOPIZZA', 'Mua 1 Tặng 1 - Mua Pizza tặng Nước/Món phụ', 'Ưu đãi đặc biệt Mua 1 Tặng 1 cho đơn hàng từ 150.000đ', 'bogo', 35000.00, 150000.00, 35000.00, 300, 0, 1, 'ALL', 1, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1),
('GOLDVIP25', 'Đặc quyền Vàng - Giảm 20% tối đa 80.000đ', 'Dành riêng cho khách hàng đạt hạng Gold & Diamond', 'percentage', 20.00, 120000.00, 80000.00, 500, 0, 1, 'GOLD', 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('DIAMOND50', 'Đặc quyền Kim Cương - Giảm 50.000đ tiền mặt', 'Dành riêng cho khách hàng VIP Diamond thân thiết', 'fixed', 50000.00, 150000.00, 50000.00, 300, 0, 1, 'DIAMOND', 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    voucher_type = VALUES(voucher_type),
    discount_value = VALUES(discount_value),
    min_order_value = VALUES(min_order_value),
    min_member_tier = VALUES(min_member_tier),
    is_active = 1;
