-- Migration: Customer Voucher Wallet & Public Vouchers
-- FASTFOOD Customer Voucher System

CREATE TABLE IF NOT EXISTS user_saved_vouchers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    coupon_id BIGINT UNSIGNED NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_coupon (user_id, coupon_id),
    KEY idx_user_id (user_id),
    KEY idx_coupon_id (coupon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm các voucher mẫu công khai hấp dẫn nếu chưa tồn tại
INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active)
SELECT 'FASTFOOD20', 'Giảm 20.000đ cho đơn hàng từ 60.000đ', 'fixed', 20000.00, 60000.00, 20000.00, 1000, 0, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code = 'FASTFOOD20');

INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active)
SELECT 'FREESHIP18', 'Miễn phí giao hàng (Giảm 18.000đ phí ship)', 'fixed', 18000.00, 100000.00, 18000.00, 2000, 0, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code = 'FREESHIP18');

INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active)
SELECT 'BURGER30', 'Ưu đãi Burger Giờ Vàng - Giảm 30.000đ', 'fixed', 30000.00, 120000.00, 30000.00, 500, 0, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code = 'BURGER30');

INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active)
SELECT 'COMBO15', 'Giảm 15% tối đa 40.000đ cho Combo gia đình', 'percentage', 15.00, 80000.00, 40000.00, 800, 0, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code = 'COMBO15');

INSERT INTO coupons (code, title, discount_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, start_date, end_date, is_active)
SELECT 'PIZZA50', 'Đại tiệc Pizza cuối tuần - Giảm ngay 50.000đ', 'fixed', 50000.00, 200000.00, 50000.00, 300, 0, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code = 'PIZZA50');
