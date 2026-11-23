-- ===================================================================
-- FASTFOOD DATABASE MIGRATION: MEMBERSHIP TIERS & LOYALTY REWARD SYSTEM
-- Target: fastfood_db (MySQL / MariaDB Port 3301)
-- File: database/migrate_membership_loyalty.sql
-- ===================================================================

USE `fastfood_db`;

-- -------------------------------------------------------------------
-- 1. BỔ SUNG CỘT CHO BẢNG `users` (Hạng thành viên, điểm thưởng, chi tiêu)
-- -------------------------------------------------------------------

-- 1.1. Thêm cột member_tier
SET @col_tier_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'member_tier'
);

SET @stmt_tier = IF(@col_tier_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `member_tier` VARCHAR(20) NOT NULL DEFAULT \'BRONZE\' COMMENT \'Hạng thành viên (BRONZE, SILVER, GOLD, DIAMOND)\' AFTER `status`',
    'SELECT "Column users.member_tier already exists" AS info'
);
PREPARE s_tier FROM @stmt_tier;
EXECUTE s_tier;
DEALLOCATE PREPARE s_tier;

-- 1.2. Thêm cột points_balance
SET @col_points_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'points_balance'
);

SET @stmt_points = IF(@col_points_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `points_balance` INT NOT NULL DEFAULT 0 COMMENT \'Số dư điểm thưởng hiện tại khả dụng\' AFTER `member_tier`',
    'SELECT "Column users.points_balance already exists" AS info'
);
PREPARE s_points FROM @stmt_points;
EXECUTE s_points;
DEALLOCATE PREPARE s_points;

-- 1.3. Thêm cột total_spent
SET @col_spent_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'total_spent'
);

SET @stmt_spent = IF(@col_spent_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `total_spent` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT \'Tổng chi tiêu tích lũy từ các đơn hàng hoàn thành (VNĐ)\' AFTER `points_balance`',
    'SELECT "Column users.total_spent already exists" AS info'
);
PREPARE s_spent FROM @stmt_spent;
EXECUTE s_spent;
DEALLOCATE PREPARE s_spent;

-- 1.4. Thêm Index cho member_tier
SET @idx_tier_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND INDEX_NAME = 'idx_users_member_tier'
);

SET @stmt_idx_tier = IF(@idx_tier_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_member_tier` (`member_tier`)',
    'SELECT "Index idx_users_member_tier already exists" AS info'
);
PREPARE s_idx_tier FROM @stmt_idx_tier;
EXECUTE s_idx_tier;
DEALLOCATE PREPARE s_idx_tier;

-- 1.5. Thêm Index cho total_spent
SET @idx_spent_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND INDEX_NAME = 'idx_users_total_spent'
);

SET @stmt_idx_spent = IF(@idx_spent_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_total_spent` (`total_spent`)',
    'SELECT "Index idx_users_total_spent already exists" AS info'
);
PREPARE s_idx_spent FROM @stmt_idx_spent;
EXECUTE s_idx_spent;
DEALLOCATE PREPARE s_idx_spent;


-- -------------------------------------------------------------------
-- 2. BỔ SUNG CỘT CHO BẢNG `orders` (Điểm tích lũy, điểm sử dụng, giảm giá điểm)
-- -------------------------------------------------------------------

-- 2.1. Thêm cột points_earned
SET @col_pe_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'orders' 
      AND COLUMN_NAME = 'points_earned'
);

SET @stmt_pe = IF(@col_pe_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `points_earned` INT NOT NULL DEFAULT 0 COMMENT \'Số điểm tích lũy được từ đơn hàng này\' AFTER `total_amount`',
    'SELECT "Column orders.points_earned already exists" AS info'
);
PREPARE s_pe FROM @stmt_pe;
EXECUTE s_pe;
DEALLOCATE PREPARE s_pe;

-- 2.2. Thêm cột points_used
SET @col_pu_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'orders' 
      AND COLUMN_NAME = 'points_used'
);

SET @stmt_pu = IF(@col_pu_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `points_used` INT NOT NULL DEFAULT 0 COMMENT \'Số điểm thưởng đã sử dụng để giảm giá đơn hàng\' AFTER `points_earned`',
    'SELECT "Column orders.points_used already exists" AS info'
);
PREPARE s_pu FROM @stmt_pu;
EXECUTE s_pu;
DEALLOCATE PREPARE s_pu;

-- 2.3. Thêm cột points_discount
SET @col_pd_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'orders' 
      AND COLUMN_NAME = 'points_discount'
);

SET @stmt_pd = IF(@col_pd_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `points_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT \'Số tiền quy đổi giảm giá từ điểm thưởng (VNĐ)\' AFTER `points_used`',
    'SELECT "Column orders.points_discount already exists" AS info'
);
PREPARE s_pd FROM @stmt_pd;
EXECUTE s_pd;
DEALLOCATE PREPARE s_pd;

-- 2.4. Thêm Index cho points_used
SET @idx_pu_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'orders' 
      AND INDEX_NAME = 'idx_orders_points_used'
);

SET @stmt_idx_pu = IF(@idx_pu_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_orders_points_used` (`points_used`)',
    'SELECT "Index idx_orders_points_used already exists" AS info'
);
PREPARE s_idx_pu FROM @stmt_idx_pu;
EXECUTE s_idx_pu;
DEALLOCATE PREPARE s_idx_pu;


-- -------------------------------------------------------------------
-- 3. TẠO BẢNG `point_transactions` (Sổ cái lịch sử biến động điểm thưởng)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `point_transactions` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'ID khách hàng',
  `order_id` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'ID đơn hàng liên kết (nếu có)',
  `points` INT NOT NULL COMMENT 'Số điểm biến động (+ cộng, - trừ)',
  `balance_after` INT NOT NULL COMMENT 'Số dư điểm sau biến động',
  `transaction_type` ENUM('EARN', 'REDEEM_CHECKOUT', 'REDEEM_VOUCHER', 'REFUND_REVOKE', 'REFUND_RESTORE', 'ADMIN_ADJUST') NOT NULL COMMENT 'Loại giao dịch điểm',
  `description` VARCHAR(255) DEFAULT NULL COMMENT 'Mô tả chi tiết giao dịch / mã đơn / voucher',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_point_tx_user_created` (`user_id`, `created_at` DESC),
  KEY `idx_point_tx_order` (`order_id`),
  KEY `idx_point_tx_type` (`transaction_type`),
  KEY `idx_point_tx_order_type` (`order_id`, `transaction_type`),
  CONSTRAINT `fk_point_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_point_tx_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------
-- 4. TẠO BẢNG `membership_tiers` (Cấu hình các cấp bậc hạng thành viên)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_tiers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tier_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Mã hạng (BRONZE, SILVER, GOLD, DIAMOND)',
  `tier_name` VARCHAR(50) NOT NULL COMMENT 'Tên hiển thị hạng thành viên',
  `min_spent` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Mốc chi tiêu tích lũy tối thiểu (VNĐ)',
  `point_ratio` DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Tỷ lệ tích điểm: điểm cho mỗi 10.000 VNĐ',
  `badge_icon` VARCHAR(100) DEFAULT NULL COMMENT 'Icon biểu tượng (FontAwesome class hoặc SVG)',
  `badge_color` VARCHAR(50) DEFAULT NULL COMMENT 'Mã màu biểu tượng / huy hiệu',
  `benefits_json` TEXT DEFAULT NULL COMMENT 'Danh sách quyền lợi chi tiết (JSON array)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tier_code` (`tier_code`),
  KEY `idx_tier_min_spent` (`min_spent` ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------
-- 5. TẠO BẢNG `loyalty_settings` (Cấu hình tham số hệ thống điểm thưởng)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `loyalty_settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------
-- 6. KHỞI TẠO DỮ LIỆU MẪU CHO `membership_tiers` (4 cấp bậc tiêu chuẩn)
-- -------------------------------------------------------------------
INSERT INTO `membership_tiers` (`tier_code`, `tier_name`, `min_spent`, `point_ratio`, `badge_icon`, `badge_color`, `benefits_json`) VALUES
('BRONZE', 'Đồng', 0.00, 1.00, '🥉', '#cd7f32', 
 '["Tích lũy 1.0 điểm cho mỗi 10.000đ chi tiêu", "Nhận voucher quà tặng chào mừng bạn mới 50K", "Ưu đãi quà tặng sinh nhật thành viên", "Tham gia các chương trình khuyến mãi độc quyền"]'),

('SILVER', 'Bạc', 1000000.00, 1.50, '🥈', '#94a3b8', 
 '["Tích lũy 1.5 điểm cho mỗi 10.000đ chi tiêu (+50% so với Đồng)", "Voucher giảm giá 10% mừng thăng hạng Bạc", "Ưu tiên xử lý và chuẩn bị đơn hàng", "Quà tặng sinh nhật thành viên nâng cao", "Miễn phí đổi món phụ khi phát sinh sự cố"]'),

('GOLD', 'Vàng', 3000000.00, 2.00, '🥇', '#f59e0b', 
 '["Tích lũy 2.0 điểm cho mỗi 10.000đ chi tiêu (gấp đôi hạng Đồng)", "Voucher giảm giá 20% mừng thăng hạng Vàng", "Miễn phí vận chuyển cho đơn hàng từ 150.000đ", "Ưu tiên phục vụ và giao hàng nhanh", "Quà tặng sinh nhật cao cấp độc quyền"]'),

('DIAMOND', 'Kim Cương', 5000000.00, 3.00, '💎', '#06b6d4', 
 '["Tích lũy 3.0 điểm cho mỗi 10.000đ chi tiêu (gấp 3 lần hạng Đồng)", "Voucher giảm giá 30% mừng thăng hạng Kim Cương", "Miễn phí vận chuyển cho tất cả các đơn hàng", "Đường dây hotline hỗ trợ khách hàng VIP 24/7", "Quà tri ân đặc biệt cuối năm dành cho VIP"]')

ON DUPLICATE KEY UPDATE
    `tier_name` = VALUES(`tier_name`),
    `min_spent` = VALUES(`min_spent`),
    `point_ratio` = VALUES(`point_ratio`),
    `badge_icon` = VALUES(`badge_icon`),
    `badge_color` = VALUES(`badge_color`),
    `benefits_json` = VALUES(`benefits_json`);


-- -------------------------------------------------------------------
-- 7. KHỞI TẠO DỮ LIỆU MẪU CHO `loyalty_settings` (Quy đổi điểm, mốc tính)
-- -------------------------------------------------------------------
INSERT INTO `loyalty_settings` (`setting_key`, `setting_value`, `description`) VALUES
('point_to_vnd_rate', '100', 'Giá trị quy đổi điểm thưởng: 1 điểm = X VNĐ (Mặc định 100đ, tương ứng 100 điểm = 10.000 VNĐ)'),
('spending_step_vnd', '10000', 'Mốc chi tiêu cơ sở để tính điểm: mỗi X VNĐ đơn hàng hoàn thành được tính điểm theo tỷ lệ hạng (Mặc định 10.000 VNĐ)'),
('min_redeem_points', '100', 'Số điểm tối thiểu mỗi lần đổi quà voucher hoặc áp dụng giảm giá checkout (Mặc định 100 điểm)'),
('max_redeem_percentage', '100', 'Tỷ lệ phần trăm tối đa của giá trị đơn hàng có thể thanh toán bằng điểm (Mặc định 100%)')

ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);


-- -------------------------------------------------------------------
-- 8. AN TOÀN ĐỒNG BỘ DỮ LIỆU LỊCH SỬ (HISTORICAL BACKFILL)
-- Tính tổng chi tiêu từ các đơn hàng hoàn thành (completed) và xét hạng
-- -------------------------------------------------------------------

-- 8.1. Đồng bộ tổng chi tiêu từ các đơn hàng 'completed' vào users.total_spent
UPDATE `users` u
JOIN (
    SELECT customer_id, SUM(total_amount) AS spent
    FROM `orders`
    WHERE order_status = 'completed'
    GROUP BY customer_id
) o ON u.id = o.customer_id
SET u.total_spent = o.spent;

-- 8.2. Cập nhật hạng thành viên tương ứng dựa theo total_spent tích lũy
UPDATE `users`
SET `member_tier` = CASE
    WHEN `total_spent` >= 5000000.00 THEN 'DIAMOND'
    WHEN `total_spent` >= 3000000.00 THEN 'GOLD'
    WHEN `total_spent` >= 1000000.00 THEN 'SILVER'
    ELSE 'BRONZE'
END;
