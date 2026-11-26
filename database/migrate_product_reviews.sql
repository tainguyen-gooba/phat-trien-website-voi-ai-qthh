-- ===================================================================
-- FASTFOOD DATABASE MIGRATION: PRODUCT CUSTOMER REVIEWS SYSTEM
-- Tạo bảng đánh giá chi tiết cho từng sản phẩm & đồng bộ điểm đánh giá
-- File: database/migrate_product_reviews.sql
-- ===================================================================

USE `fastfood_db`;

-- 1. Tạo bảng `product_reviews`
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID sản phẩm được đánh giá',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID khách hàng đánh giá',
  `order_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID đơn hàng đã mua (nếu có)',
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Điểm đánh giá từ 1 đến 5 sao',
  `comment` TEXT NULL COMMENT 'Nội dung nhận xét của khách hàng',
  `variant_info` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Kích cỡ / Topping đã đặt',
  `reply_content` TEXT NULL COMMENT 'Phản hồi từ quán / Admin',
  `replied_by` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID quản trị viên/nhân viên phản hồi',
  `replied_at` DATETIME NULL DEFAULT NULL COMMENT 'Thời điểm phản hồi',
  `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Đã mua món ăn thành công',
  `helpful_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số lượt thấy hữu ích',
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Hiển thị, 0 = Ẩn',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pr_product_approved` (`product_id`, `is_approved`, `created_at`),
  INDEX `idx_pr_rating` (`product_id`, `rating`),
  INDEX `idx_pr_user_product` (`user_id`, `product_id`),
  INDEX `idx_pr_order` (`order_id`),
  CONSTRAINT `fk_pr_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_pr_rating` CHECK (`rating` >= 1 AND `rating` <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tạo bảng `product_review_helpful` (Lưu lượt thích Hữu ích chống trùng lặp)
CREATE TABLE IF NOT EXISTS `product_review_helpful` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `review_id` BIGINT UNSIGNED NOT NULL,
  `voter_key` VARCHAR(100) NOT NULL COMMENT 'u_<user_id> hoặc s_<session_hash>',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_voter` (`review_id`, `voter_key`),
  CONSTRAINT `fk_prh_review` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bổ sung cột `rating_avg` và `rating_count` vào bảng `products`
SET @col_ravg_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'rating_avg'
);
SET @stmt_ravg = IF(@col_ravg_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER `prep_time_minutes`',
    'SELECT "Column products.rating_avg already exists" AS info'
);
PREPARE s_ravg FROM @stmt_ravg;
EXECUTE s_ravg;
DEALLOCATE PREPARE s_ravg;

SET @col_rcnt_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'rating_count'
);
SET @stmt_rcnt = IF(@col_rcnt_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `rating_avg`',
    'SELECT "Column products.rating_count already exists" AS info'
);
PREPARE s_rcnt FROM @stmt_rcnt;
EXECUTE s_rcnt;
DEALLOCATE PREPARE s_rcnt;
