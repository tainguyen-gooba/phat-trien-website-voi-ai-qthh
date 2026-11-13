-- ===================================================================
-- FASTFOOD DELIVERY PLATFORM - ALL-IN-ONE DATABASE IMPORT FILE
-- Tên CSDL   : fastfood_db
-- Tương thích: MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- Mã hóa     : utf8mb4 / utf8mb4_unicode_ci
-- Mục đích   : Import 1 lần duy nhất vào phpMyAdmin / MySQL CLI / DBeaver
-- Bao gồm    : 23 Bảng chuẩn 3NF + Dữ liệu mẫu (Tài khoản, 13 Quán ăn, 260 Món ăn thật)
-- ===================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Tắt kiểm tra khóa ngoại khi khởi tạo và import
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- 0. TẠO VÀ CHỌN CƠ SỞ DỮ LIỆU
-- ===================================================================
CREATE DATABASE IF NOT EXISTS `fastfood_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `fastfood_db`;


-- Drop all existing tables in reverse dependency order
DROP TABLE IF EXISTS `ai_chat_messages`;
DROP TABLE IF EXISTS `ai_chat_sessions`;
DROP TABLE IF EXISTS `chat_automation`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `realtime_payments`;
DROP TABLE IF EXISTS `realtime_orders`;
DROP TABLE IF EXISTS `website_content`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `staff_assignments`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ===================================================================
-- 1. BẢNG roles (Phân quyền người dùng)
-- ===================================================================
CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tên vai trò: admin, customer, restaurant_owner, shipper',
  `description` VARCHAR(255) NULL COMMENT 'Mô tả vai trò',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 2. BẢNG users (Tài khoản người dùng)
-- ===================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL COMMENT 'FK liên kết tới roles',
  `full_name` VARCHAR(150) NOT NULL COMMENT 'Họ và tên',
  `email` VARCHAR(191) NOT NULL UNIQUE COMMENT 'Email đăng nhập / nhận thông báo',
  `phone` VARCHAR(20) NULL UNIQUE COMMENT 'Số điện thoại',
  `address` VARCHAR(255) NULL COMMENT 'Địa chỉ mặc định của khách hàng',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã mã hóa bcrypt',
  `avatar_url` VARCHAR(500) NULL COMMENT 'Ảnh đại diện',
  `status` ENUM('active', 'inactive', 'banned') DEFAULT 'active' COMMENT 'Trạng thái tài khoản',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_phone` (`phone`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 3. BẢNG restaurants (Cửa hàng / Quán ăn đối tác)
-- ===================================================================
DROP TABLE IF EXISTS `restaurants`;
CREATE TABLE `restaurants` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK liên kết tới users (chủ quán)',
  `name` VARCHAR(200) NOT NULL COMMENT 'Tên quán ăn',
  `slug` VARCHAR(220) NOT NULL UNIQUE COMMENT 'Đường dẫn tĩnh quán ăn',
  `description` TEXT NULL COMMENT 'Giới thiệu về quán',
  `address` VARCHAR(255) NOT NULL COMMENT 'Địa chỉ thực tế',
  `latitude` DECIMAL(10, 8) NULL COMMENT 'Tọa độ vĩ độ cho tính khoảng cách',
  `longitude` DECIMAL(11, 8) NULL COMMENT 'Tọa độ kinh độ cho tính khoảng cách',
  `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Hotline quán',
  `email` VARCHAR(191) NULL COMMENT 'Email liên hệ quán',
  `banner_url` VARCHAR(500) NULL COMMENT 'Ảnh bìa quán',
  `logo_url` VARCHAR(500) NULL COMMENT 'Logo quán',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = Mở cửa nhận đơn, 0 = Đóng cửa',
  `is_verified` TINYINT(1) DEFAULT 0 COMMENT 'Đã được Admin duyệt xác thực',
  `rating_avg` DECIMAL(3, 2) DEFAULT 5.00 COMMENT 'Điểm đánh giá trung bình (1-5)',
  `rating_count` INT UNSIGNED DEFAULT 0 COMMENT 'Số lượt đánh giá',
  `opening_time` TIME NOT NULL DEFAULT '07:00:00' COMMENT 'Giờ mở cửa',
  `closing_time` TIME NOT NULL DEFAULT '22:00:00' COMMENT 'Giờ đóng cửa',
  `min_order_amount` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Giá trị đơn hàng tối thiểu',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_restaurants_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  INDEX `idx_restaurants_slug` (`slug`),
  INDEX `idx_restaurants_rating` (`rating_avg`),
  INDEX `idx_restaurants_location` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 4. BẢNG categories (Danh mục món ăn)
-- ===================================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` BIGINT UNSIGNED NULL COMMENT 'NULL nếu là danh mục chung toàn hệ thống, có ID nếu quán tự tạo',
  `name` VARCHAR(100) NOT NULL COMMENT 'Tên danh mục (ví dụ: Cơm, Trà sữa, Burger)',
  `slug` VARCHAR(120) NOT NULL UNIQUE COMMENT 'Đường dẫn tĩnh danh mục',
  `icon_url` VARCHAR(500) NULL COMMENT 'Icon minh họa',
  `display_order` INT DEFAULT 0 COMMENT 'Thứ tự hiển thị',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = Hiển thị, 0 = Ẩn',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_categories_restaurant` (`restaurant_id`),
  CONSTRAINT `fk_categories_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_categories_slug` (`slug`),
  INDEX `idx_categories_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 5. BẢNG products (Sản phẩm món ăn)
-- ===================================================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) NULL COMMENT 'Mã sản phẩm (ví dụ: prod-1)',
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán bán món này',
  `category_id` INT UNSIGNED NOT NULL COMMENT 'FK danh mục món',
  `name` VARCHAR(200) NOT NULL COMMENT 'Tên món ăn',
  `slug` VARCHAR(220) NOT NULL COMMENT 'Slug món ăn',
  `description` TEXT NULL COMMENT 'Mô tả thành phần, khẩu phần',
  `image_url` VARCHAR(500) NULL COMMENT 'Ảnh chính món ăn',
  `base_price` DECIMAL(12, 2) NOT NULL COMMENT 'Giá gốc',
  `discount_price` DECIMAL(12, 2) NULL COMMENT 'Giá khuyến mãi (nếu có)',
  `is_available` TINYINT(1) DEFAULT 1 COMMENT '1 = Còn món, 0 = Tạm hết hàng',
  `is_featured` TINYINT(1) DEFAULT 0 COMMENT '1 = Món ăn nổi bật / Best-seller',
  `prep_time_minutes` INT UNSIGNED DEFAULT 15 COMMENT 'Thời gian chuẩn bị món dự kiến (phút)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_products_restaurant` (`restaurant_id`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  INDEX `idx_products_restaurant` (`restaurant_id`),
  INDEX `idx_products_category` (`category_id`),
  INDEX `idx_products_available` (`is_available`),
  INDEX `idx_products_price` (`base_price`, `discount_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 6. BẢNG product_images (Hình ảnh món ăn chi tiết)
-- ===================================================================
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK món ăn',
  `image_url` VARCHAR(500) NOT NULL COMMENT 'Đường dẫn ảnh',
  `is_primary` TINYINT(1) DEFAULT 0 COMMENT '1 = Ảnh đại diện chính',
  `display_order` INT DEFAULT 0 COMMENT 'Thứ tự hiển thị',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_product_images_product` (`product_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 7. BẢNG addresses (Sổ địa chỉ người dùng)
-- ===================================================================
DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK liên kết người dùng',
  `recipient_name` VARCHAR(150) NOT NULL COMMENT 'Tên người nhận',
  `phone_number` VARCHAR(20) NOT NULL COMMENT 'Số điện thoại nhận hàng',
  `street_address` VARCHAR(255) NOT NULL COMMENT 'Số nhà, tên đường, tòa nhà',
  `ward` VARCHAR(100) NULL COMMENT 'Phường / Xã',
  `district` VARCHAR(100) NULL COMMENT 'Quận / Huyện',
  `city` VARCHAR(100) NOT NULL COMMENT 'Tỉnh / Thành phố',
  `latitude` DECIMAL(10, 8) NULL COMMENT 'Tọa độ vĩ độ người nhận',
  `longitude` DECIMAL(11, 8) NULL COMMENT 'Tọa độ kinh độ người nhận',
  `is_default` TINYINT(1) DEFAULT 0 COMMENT '1 = Địa chỉ mặc định',
  `note` VARCHAR(255) NULL COMMENT 'Ghi chú chỉ dẫn giao hàng (ví dụ: gọi bảo vệ)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_addresses_user` (`user_id`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_addresses_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 8. BẢNG coupons (Mã giảm giá & Khuyến mãi)
-- ===================================================================
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NULL,
  `restaurant_id` BIGINT UNSIGNED NULL COMMENT 'NULL = Mã sàn toàn hệ thống; có ID = Mã riêng quán',
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã voucher (ví dụ: FREESHIP, GIAM50K)',
  `title` VARCHAR(200) NOT NULL COMMENT 'Tiêu đề khuyến mãi',
  `discount_type` ENUM('percentage', 'fixed') NOT NULL COMMENT 'percentage = Theo %, fixed = Giảm cố định VNĐ',
  `discount_value` DECIMAL(12, 2) NOT NULL COMMENT 'Giá trị giảm',
  `min_order_value` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Giá trị đơn tối thiểu để áp dụng',
  `max_discount_amount` DECIMAL(12, 2) NULL COMMENT 'Mức giảm tối đa (với mã %)',
  `usage_limit` INT UNSIGNED NULL COMMENT 'Lượt dùng tối đa',
  `usage_count` INT UNSIGNED DEFAULT 0 COMMENT 'Số lượt đã sử dụng',
  `start_date` DATETIME NOT NULL COMMENT 'Thời gian bắt đầu',
  `end_date` DATETIME NOT NULL COMMENT 'Thời gian kết thúc',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = Kích hoạt, 0 = Tạm dừng',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_coupons_restaurant` (`restaurant_id`),
  KEY `fk_coupons_user` (`user_id`),
  CONSTRAINT `fk_coupons_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_coupons_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_coupons_code` (`code`),
  INDEX `idx_coupons_dates` (`start_date`, `end_date`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 9. BẢNG orders (Đơn hàng chuẩn RDBMS)
-- ===================================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Mã hiển thị (ví dụ: FF-20261109-0001)',
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT 'Người đặt',
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Quán tiếp nhận',
  `shipper_id` BIGINT UNSIGNED NULL COMMENT 'Tài xế giao hàng',
  `coupon_id` BIGINT UNSIGNED NULL COMMENT 'Mã giảm giá áp dụng',
  `delivery_address` VARCHAR(255) NOT NULL COMMENT 'Địa chỉ giao hàng thực tế',
  `recipient_name` VARCHAR(150) NOT NULL COMMENT 'Tên người nhận tại thời điểm đặt',
  `recipient_phone` VARCHAR(20) NOT NULL COMMENT 'SĐT người nhận',
  `delivery_latitude` DECIMAL(10, 8) NULL COMMENT 'Tọa độ giao hàng',
  `delivery_longitude` DECIMAL(11, 8) NULL COMMENT 'Tọa độ giao hàng',
  `subtotal` DECIMAL(12, 2) NOT NULL COMMENT 'Tổng tiền món ăn',
  `shipping_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Phí giao hàng',
  `discount_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền được giảm',
  `total_amount` DECIMAL(12, 2) NOT NULL COMMENT 'Số tiền khách phải trả',
  `payment_method` ENUM('cod', 'momo', 'banking', 'vnpay') NOT NULL DEFAULT 'cod',
  `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `order_status` ENUM('placed', 'confirmed', 'preparing', 'delivering', 'completed', 'cancelled') NOT NULL DEFAULT 'placed',
  `cancellation_reason` VARCHAR(255) NULL COMMENT 'Lý do hủy đơn',
  `note_for_restaurant` VARCHAR(255) NULL COMMENT 'Lời nhắn cho quán',
  `note_for_shipper` VARCHAR(255) NULL COMMENT 'Lời nhắn cho tài xế',
  `placed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
  `delivering_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_orders_customer` (`customer_id`),
  KEY `fk_orders_restaurant` (`restaurant_id`),
  KEY `fk_orders_shipper` (`shipper_id`),
  KEY `fk_orders_coupon` (`coupon_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_shipper` FOREIGN KEY (`shipper_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_orders_customer` (`customer_id`, `placed_at`),
  INDEX `idx_orders_restaurant` (`restaurant_id`, `order_status`),
  INDEX `idx_orders_status` (`order_status`),
  INDEX `idx_orders_payment` (`payment_status`, `payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 10. BẢNG order_items (Chi tiết món ăn trong đơn hàng)
-- ===================================================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK đơn hàng',
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK món ăn',
  `product_name` VARCHAR(200) NOT NULL COMMENT 'Lưu lại tên món tại thời điểm đặt',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số lượng',
  `unit_price` DECIMAL(12, 2) NOT NULL COMMENT 'Đơn giá sau chiết khấu tại lúc mua',
  `subtotal` DECIMAL(12, 2) NOT NULL COMMENT 'quantity * unit_price',
  `special_instructions` VARCHAR(255) NULL COMMENT 'Ghi chú đặc biệt cho món',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_order_items_order` (`order_id`),
  KEY `fk_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  INDEX `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 11. BẢNG payments (Lịch sử thanh toán giao dịch)
-- ===================================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK đơn hàng',
  `transaction_code` VARCHAR(100) NULL UNIQUE COMMENT 'Mã giao dịch từ cổng thanh toán',
  `amount` DECIMAL(12, 2) NOT NULL COMMENT 'Số tiền thanh toán',
  `provider` ENUM('cod', 'momo', 'banking', 'vnpay') NOT NULL,
  `status` ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `gateway_response` LONGTEXT NULL COMMENT 'Log phản hồi từ cổng thanh toán (JSON)',
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_payments_order` (`order_id`),
  INDEX `idx_payments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 12. BẢNG reviews (Đánh giá món ăn & Quán ăn)
-- ===================================================================
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'Chỉ đánh giá sau khi hoàn tất đơn',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Người đánh giá',
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Quán được đánh giá',
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Điểm từ 1 đến 5 sao',
  `comment` TEXT NULL COMMENT 'Nội dung nhận xét',
  `reply_from_restaurant` TEXT NULL COMMENT 'Quán phản hồi lại đánh giá của khách',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_reviews_order` (`order_id`),
  KEY `fk_reviews_user` (`user_id`),
  KEY `fk_reviews_restaurant` (`restaurant_id`),
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_reviews_rating` CHECK (`rating` >= 1 AND `rating` <= 5),
  INDEX `idx_reviews_restaurant` (`restaurant_id`),
  INDEX `idx_reviews_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 13. BẢNG carts (Giỏ hàng của người dùng)
-- ===================================================================
DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK người dùng sở hữu giỏ',
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán ăn của giỏ hàng hiện tại',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_carts_user` (`user_id`),
  KEY `fk_carts_restaurant` (`restaurant_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_carts_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY `uq_user_restaurant_cart` (`user_id`, `restaurant_id`),
  INDEX `idx_carts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 14. BẢNG cart_items (Chi tiết món ăn trong giỏ hàng)
-- ===================================================================
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK giỏ hàng',
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK sản phẩm trong giỏ',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số lượng đặt',
  `unit_price` DECIMAL(12, 2) NOT NULL COMMENT 'Đơn giá tại thời điểm đưa vào giỏ',
  `notes` VARCHAR(255) NULL COMMENT 'Ghi chú cho món ăn (ví dụ: ít ngọt, không cay)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_cart_items_cart` (`cart_id`),
  KEY `fk_cart_items_product` (`product_id`),
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_cart_items_cart` (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 15. BẢNG notifications (Thông báo hệ thống cho người dùng)
-- ===================================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Người nhận thông báo',
  `title` VARCHAR(200) NOT NULL COMMENT 'Tiêu đề thông báo',
  `content` TEXT NOT NULL COMMENT 'Nội dung chi tiết',
  `type` ENUM('order', 'promotion', 'system') DEFAULT 'system' COMMENT 'Phân loại thông báo',
  `is_read` TINYINT(1) DEFAULT 0 COMMENT '0 = Chưa đọc, 1 = Đã đọc',
  `metadata` LONGTEXT NULL COMMENT 'Dữ liệu JSON kèm theo (order_id, url...)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_notifications_user` (`user_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_notifications_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 16. BẢNG staff_assignments (Phân công nhân viên quản lý quán)
-- ===================================================================
DROP TABLE IF EXISTS `staff_assignments`;
CREATE TABLE `staff_assignments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK nhân viên',
  `role_in_restaurant` ENUM('manager', 'chef', 'staff') DEFAULT 'staff' COMMENT 'Vị trí trong quán',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = Đang làm việc, 0 = Đã nghỉ',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_staff_restaurant` (`restaurant_id`),
  KEY `fk_staff_user` (`user_id`),
  CONSTRAINT `fk_staff_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY `uq_restaurant_staff` (`restaurant_id`, `user_id`),
  INDEX `idx_staff_restaurant` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 17. BẢNG realtime_orders (Đơn hàng Realtime Socket.IO & Checkout)
-- ===================================================================
DROP TABLE IF EXISTS `realtime_orders`;
CREATE TABLE `realtime_orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_data` LONGTEXT NOT NULL COMMENT 'Payload đơn hàng định dạng JSON',
  `status` ENUM('awaiting_payment', 'payment_failed', 'pending', 'preparing', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_realtime_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_realtime_orders_user` (`user_id`, `id`),
  INDEX `idx_realtime_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 18. BẢNG realtime_payments (Giao dịch thanh toán Realtime)
-- ===================================================================
DROP TABLE IF EXISTS `realtime_payments`;
CREATE TABLE `realtime_payments` (
  `order_code` VARCHAR(30) NOT NULL PRIMARY KEY,
  `payment_method` ENUM('banking', 'momo', 'vnpay') NOT NULL,
  `request_id` VARCHAR(64) NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `provider_transaction_id` VARCHAR(100) NULL UNIQUE,
  `payment_url` TEXT NULL,
  `qr_url` TEXT NULL,
  `expires_at` DATETIME NULL,
  `retry_until` DATETIME NULL,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_realtime_payments_order` FOREIGN KEY (`order_code`) REFERENCES `realtime_orders` (`order_code`) ON DELETE CASCADE,
  INDEX `idx_realtime_payments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 19. BẢNG chat_messages (Tin nhắn hỗ trợ trực tiếp CSKH Realtime)
-- ===================================================================
DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NULL,
  `sender_role` ENUM('admin', 'customer', 'ai') NOT NULL,
  `message` VARCHAR(1000) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_chat_sender` (`sender_id`),
  CONSTRAINT `fk_chat_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_chat_customer_id` (`customer_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 20. BẢNG chat_automation (Cấu hình tự động trả lời CSKH)
-- ===================================================================
DROP TABLE IF EXISTS `chat_automation`;
CREATE TABLE `chat_automation` (
  `customer_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `paused_until` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `fk_chat_automation_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 21. BẢNG website_content (Đồng bộ nội dung động CMS)
-- ===================================================================
DROP TABLE IF EXISTS `website_content`;
CREATE TABLE `website_content` (
  `collection_name` VARCHAR(50) NOT NULL PRIMARY KEY,
  `content` LONGTEXT NOT NULL COMMENT 'Nội dung CMS lưu dạng JSON',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 22. BẢNG ai_chat_sessions (Phiên hội thoại Trợ lý ảo FASTFOOD AI)
-- ===================================================================
DROP TABLE IF EXISTS `ai_chat_sessions`;
CREATE TABLE `ai_chat_sessions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` BIGINT UNSIGNED NULL,
  `customer_name` VARCHAR(150) NULL DEFAULT 'Khách hàng',
  `title` VARCHAR(255) NOT NULL DEFAULT 'Hội thoại mới',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `ai_paused_until` DATETIME NULL DEFAULT NULL,
  `ai_force_auto` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_chat_session_user` (`user_id`),
  INDEX `idx_chat_session_updated` (`updated_at`),
  INDEX `idx_chat_session_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 23. BẢNG ai_chat_messages (Lịch sử tin nhắn Trợ lý ảo FASTFOOD AI)
-- ===================================================================
DROP TABLE IF EXISTS `ai_chat_messages`;
CREATE TABLE `ai_chat_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `sender` ENUM('user', 'assistant', 'admin', 'system') NOT NULL DEFAULT 'user',
  `message` TEXT NOT NULL,
  `metadata` LONGTEXT NULL COMMENT 'JSON lưu thông tin món ăn, gợi ý hoặc tra cứu đơn hàng',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chat_msg_session` (`session_id`),
  INDEX `idx_chat_msg_user` (`user_id`),
  INDEX `idx_chat_msg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================================================================
-- DỮ LIỆU KHỞI TẠO (SEEDS DATA)
-- ===================================================================


-- 1. Thêm vai trò hệ thống (roles)
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Quản trị viên toàn quyền hệ thống'),
(2, 'customer', 'Khách hàng đặt món ăn'),
(3, 'restaurant_owner', 'Chủ cửa hàng, quán ăn đối tác'),
(4, 'shipper', 'Tài xế giao nhận đơn hàng')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. Thêm tài khoản mẫu (Mật khẩu mặc định: Admin@123456)
INSERT INTO `users` (`id`, `role_id`, `full_name`,`email`, `phone`, `address`, `password_hash`, `status`) VALUES
(1, 1, 'Hệ Thống Quản Trị', 'admin@fastfood.vn', '0901000001', 'Tòa nhà FASTFOOD, 10 Mai Hắc Đế, Buôn Ma Thuột', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(2, 3, 'Nguyễn Văn Chủ Quán', 'owner@fastfood.vn', '0902000002', '25 Nguyễn Tất Thành, Buôn Ma Thuột', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(3, 2, 'Trần Khách Hàng', 'customer@fastfood.vn', '0903000003', '72 Lê Thánh Tôn, Bến Nghé, Quận 1', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(4, 4, 'Lê Tài Xế Giao Hàng', 'shipper@fastfood.vn', '0904000004', 'Buôn Ma Thuột, Đắk Lắk', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(5, 2, 'Nguyễn Hiệp', 'nghiep2007@gmial.com', '0862033905', 'Buôn Ma Thuột, Đắk Lắk', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(6, 2, 'Khách hàng Demo', 'nguyenhiep@gmail.com', '0908888999', 'Buôn Ma Thuột, Đắk Lắk', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active')
ON DUPLICATE KEY UPDATE
  `role_id` = VALUES(`role_id`),
  `full_name` = VALUES(`full_name`),
  `password_hash` = VALUES(`password_hash`),
  `status` = VALUES(`status`);

-- 3. Danh mục món ăn (categories)
INSERT INTO `categories` (`id`, `restaurant_id`, `name`, `slug`, `icon_url`, `display_order`, `is_active`) VALUES
(1, NULL, 'Tất Cả Món', 'all', '/assets/images/categories/all.svg', 0, 1),
(2, NULL, 'Cơm & Món Việt', 'com', '/assets/images/categories/rice.svg', 1, 1),
(3, NULL, 'Burger & Gà Rán', 'burger', '/assets/images/categories/burger.svg', 2, 1),
(4, NULL, 'Trà Sữa & Đồ Uống', 'tra-sua', '/assets/images/categories/drink.svg', 3, 1),
(5, NULL, 'Pizza & Mỳ Ý', 'pizza', '/assets/images/categories/pizza.svg', 4, 1),
(6, NULL, 'Ăn Vặt & Bánh', 'an-vat', '/assets/images/categories/snack.svg', 5, 1),
(7, NULL, 'Salad & Healthy', 'healthy', '/assets/images/categories/healthy.svg', 6, 1),
(8, NULL, 'Cà Phê & Trà', 'cafe', '/assets/images/categories/coffee.svg', 7, 1),
(9, NULL, 'Sushi & Món Nhật', 'sushi', '/assets/images/categories/sushi.svg', 8, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `icon_url` = VALUES(`icon_url`);

-- 4. Danh sách 13 Nhà hàng / Quán ăn đối tác
INSERT INTO `restaurants` (`id`, `owner_id`, `name`, `slug`, `description`, `address`, `latitude`, `longitude`, `phone`, `email`, `banner_url`, `logo_url`, `is_active`, `is_verified`, `rating_avg`, `rating_count`, `opening_time`, `closing_time`, `min_order_amount`) VALUES
(1, 2, 'Burger King Station - Buôn Ma Thuột', 'res-1', 'Burger Bò Úc, Gà Giòn Cay, Khoai Tây Lắc', '25 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk', 12.679500, 108.050500, '02623888001', 'res1@fastfood.vn', 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=120&auto=format&fit=crop&q=80', 1, 1, 4.9, 382, '07:00:00', '22:30:00', 30000.00),
(2, 2, 'Cơm Tấm Sài Gòn Xưa - Buôn Ma Thuột', 'res-2', 'Cơm Sườn Nướng, Bì Chả Trứng, Canh Khổ Qua', '68 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk', 12.681500, 108.052000, '02623888002', 'res2@fastfood.vn', '/assets/images/foods/com-tam-dac-biet.jpg', '/assets/images/foods/com-tam-suon-nuong.jpg', 1, 1, 4.8, 520, '07:00:00', '22:30:00', 25000.00),
(3, 2, 'Tiệm Trà Sữa GongCha Tea & Coffee', 'res-3', 'Trà Đen Macchiato, Trân Châu Hoàng Gia, Trà Trái Cây', '91 Lê Thánh Tông, Buôn Ma Thuột, Đắk Lắk', 12.683500, 108.053500, '02623888003', 'res3@fastfood.vn', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=120&auto=format&fit=crop&q=80', 1, 1, 4.9, 640, '07:00:00', '22:30:00', 40000.00),
(4, 2, 'Pizza Hut Italia - Buôn Ma Thuột', 'res-4', 'Pizza Hải Sản, Mì Ý Sốt Bò Bằm, Bánh Tỏi', '12 Y Jút, Buôn Ma Thuột, Đắk Lắk', 12.685500, 108.055000, '02623888004', 'res4@fastfood.vn', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=120&auto=format&fit=crop&q=80', 1, 1, 4.7, 290, '07:00:00', '22:30:00', 60000.00),
(5, 2, 'Phở Bếp Việt', 'res-5', 'Phở Bò, Nước Dùng Ninh Xương', '23 Lý Thường Kiệt, Buôn Ma Thuột, Đắk Lắk', 12.687500, 108.056500, '02623888005', 'res5@fastfood.vn', 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=120&auto=format&fit=crop&q=80', 1, 1, 4.8, 126, '07:00:00', '22:30:00', 35000.00),
(6, 2, 'Sushi Maki House', 'res-6', 'Sushi Cá Hồi, Món Nhật', '88 Nguyễn Công Trứ, Buôn Ma Thuột, Đắk Lắk', 12.689500, 108.058000, '02623888006', 'res6@fastfood.vn', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=120&auto=format&fit=crop&q=80', 1, 1, 4.8, 94, '07:00:00', '22:30:00', 50000.00),
(7, 2, 'Burger Gà Giòn', 'res-7', 'Burger Gà, Sốt Bơ Đặc Biệt', '16 Hoàng Diệu, Buôn Ma Thuột, Đắk Lắk', 12.691500, 108.059500, '02623888007', 'res7@fastfood.vn', 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=120&auto=format&fit=crop&q=80', 1, 1, 4.7, 83, '07:00:00', '22:30:00', 20000.00),
(8, 2, 'Tiệm Bánh Bonjour', 'res-8', 'Bánh Croissant, Bánh Ngọt', '102 Y Moan, Buôn Ma Thuột, Đắk Lắk', 12.693500, 108.061000, '02623888008', 'res8@fastfood.vn', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=120&auto=format&fit=crop&q=80', 1, 1, 4.9, 72, '07:00:00', '22:30:00', 30000.00),
(9, 2, 'Bếp Sáng Mộc Miên', 'res-9', 'Pancake, Bữa Sáng', '41 Ama Khê, Buôn Ma Thuột, Đắk Lắk', 12.695500, 108.062500, '02623888009', 'res9@fastfood.vn', 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=120&auto=format&fit=crop&q=80', 1, 1, 4.7, 68, '07:00:00', '22:30:00', 30000.00),
(10, 2, 'Burger King Station - Chi Nhánh Nguyễn Tất Thành', 'res-10', 'Gà Rán, Món Ăn Nóng', '54 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk', 12.697500, 108.064000, '02623888010', 'res10@fastfood.vn', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=120&auto=format&fit=crop&q=80', 1, 1, 4.8, 98, '07:00:00', '22:30:00', 30000.00),
(11, 2, 'Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh', 'res-11', 'Salad, Món Ăn Healthy', '36 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk', 12.699500, 108.065500, '02623888011', 'res11@fastfood.vn', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=120&auto=format&fit=crop&q=80', 1, 1, 4.7, 64, '07:00:00', '22:30:00', 25000.00),
(12, 2, 'Pizza Hut Italia - Chi Nhánh Y Jút', 'res-12', 'Mì Ý, Món Ý', '08 Y Jút, Buôn Ma Thuột, Đắk Lắk', 12.701500, 108.067000, '02623888012', 'res12@fastfood.vn', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=120&auto=format&fit=crop&q=80', 1, 1, 4.7, 112, '07:00:00', '22:30:00', 50000.00),
(13, 2, 'Burger King Station - Chi Nhánh Lê Duẩn', 'res-13', 'Khoai Tây Chiên, Ăn Vặt', '102 Lê Duẩn, Buôn Ma Thuột, Đắk Lắk', 12.703500, 108.068500, '02623888013', 'res13@fastfood.vn', '/assets/images/foods/banh-trang-nuong.jpg', '/assets/images/foods/bap-xao.jpg', 1, 1, 4.8, 215, '07:00:00', '22:30:00', 20000.00)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`), `banner_url` = VALUES(`banner_url`), `rating_avg` = VALUES(`rating_avg`);

-- 5. Danh sách 260 Món ăn (Đầy đủ 13 Quán x 20 Món, Hình ảnh thật chuẩn xác)
INSERT INTO `products` (`id`, `product_code`, `restaurant_id`, `category_id`, `name`, `slug`, `description`, `image_url`, `base_price`, `discount_price`, `is_available`, `is_featured`, `prep_time_minutes`) VALUES
(1, 'prod-1', 1, 3, 'Burger Bò Nướng Phô Mai Tan Chảy (Double Cheese)', 'burger-bo-nuong-pho-mai', 'Thịt bò Úc 100% nướng lửa than hoa thơm nức mũi, kẹp phô mai Cheddar béo ngậy, sốt BBQ đặc chế và rau xà lách tươi giòn.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', 89000.00, 69000.00, 1, 1, 15),
(2, 'prod-2', 1, 3, 'Burger Bò Nướng Lửa Than Hoa (Whopper Classic)', 'burger-bo-whopper-classic', 'Thịt bò nướng lửa cỡ lớn đậm vị khói phương Tây, lát cà chua đỏ mọng và dưa chuột muối giòn tan.', 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80', 109000.00, 89000.00, 1, 1, 15),
(3, 'prod-3', 1, 3, 'Burger Bò Tắm Sốt Nấm Truffle Đen Thượng Hạng', 'burger-bo-sot-nam-truffle', 'Sốt kem nấm Truffle đen quý hiếm phủ trọn miếng bò nướng mọng nước, hòa quyện sốt nấm xào thơm béo.', 'https://images.unsplash.com/photo-1586190848861-99aa4a171e90?w=600&auto=format&fit=crop&q=80', 125000.00, 99000.00, 1, 1, 15),
(4, 'prod-4', 1, 3, 'Burger Bò Trứng Lòng Đào Thịt Ba Chỉ Xông Khói (Bacon)', 'burger-bo-trung-long-dao-bacon', 'Sự kết hợp hoàn hảo giữa thịt bò nướng than, trứng gà lòng đào tan chảy và 2 dải bacon áp chảo giòn rụm.', 'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=600&auto=format&fit=crop&q=80', 99000.00, 82000.00, 1, 1, 15),
(5, 'prod-5', 1, 3, 'Burger Bò Cay Sốt Ớt Jalapeno Mễ Tây Cơ', 'burger-bo-cay-jalapeno', 'Dành cho tín đồ ăn cay! Ớt Jalapeno Mexico cay nồng kết hợp sốt chipotle mayo bốc khói kích thích vị giác.', 'https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5?w=600&auto=format&fit=crop&q=80', 89000.00, 75000.00, 1, 0, 15),
(6, 'prod-6', 1, 3, 'Burger Bò 3 Tầng Siêu Khủng (Triple Beast Burger)', 'burger-bo-3-tang-sieu-khung', '3 lớp bò Úc nướng than hoa nặng gần 400g kết hợp 3 lát phô mai Cheddar tan chảy thơm lừng ngất ngây.', 'https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=600&auto=format&fit=crop&q=80', 169000.00, 139000.00, 1, 1, 15),
(7, 'prod-7', 1, 3, 'Burger Gà Phi Lê Nướng Sốt Teriyaki Mè Rang', 'burger-ga-phi-le-teriyaki', 'Thịt gà phi lê đùi rút xương ướp sốt teriyaki Nhật Bản nướng vàng óng, rắc mè trắng rang bùi thơm ngậy.', 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 79000.00, 65000.00, 1, 0, 15),
(8, 'prod-8', 1, 3, 'Burger Cá Tuyết Phi Lê Chiên Giòn Sốt Tartar', 'burger-ca-tuyet-sot-tartar', 'Cá tuyết tươi tẩm bột xù chiên vàng rụm, thịt cá ngọt trắng mềm mịn kẹp sốt tartar chanh thì là thanh mát.', 'https://images.unsplash.com/photo-1521305916504-4a1121188589?w=600&auto=format&fit=crop&q=80', 85000.00, 72000.00, 1, 0, 15),
(9, 'prod-9', 1, 3, 'Burger Tôm Hoàng Gia Giòn Rụm Sốt Kem Thousand Island', 'burger-tom-hoang-gia', 'Chả tôm tươi nguyên con xay dối giữ trọn độ giòn ngọt, vỏ ngoài chiên giòn tan chấm sốt nghìn hòn đảo chua béo.', 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 95000.00, 79000.00, 1, 1, 15),
(10, 'prod-10', 1, 3, 'Burger Bò Sốt Phô Mai Xanh (Blue Cheese Burger)', 'burger-bo-blue-cheese', 'Vị đậm đà béo sâu đặc trưng của phô mai xanh kết hợp hành tây caramen ngọt dịu và bò nướng mọng nước.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 0, 15),
(11, 'prod-11', 1, 3, 'Combo Burger Bò Phô Mai + Khoai Tây Múi Cau + Coca-Cola', 'combo-burger-bo-khoai-coca', 'Bữa ăn chuẩn Mỹ hoàn hảo gồm 1 Burger Bò Phô Mai, 1 phần khoai tây múi cau nướng muối giòn rụm và 1 ly Coca mát lạnh.', 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 129000.00, 99000.00, 1, 1, 15),
(12, 'prod-12', 1, 3, 'Combo Cặp Đôi Burger King (2 Burger Bò + 2 Khoai + 2 Nước)', 'combo-cap-doi-burger-king', 'Set ăn thịnh soạn dành cho 2 người gồm 2 Burger Bò nướng cỡ vừa, 2 phần khoai tây chiên vàng và 2 nước ngọt có ga.', 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80', 245000.00, 189000.00, 1, 1, 15),
(13, 'prod-13', 1, 3, 'Khoai Tây Múi Cau Nướng Muối Biển & Hương Thảo', 'khoai-tay-mui-cau-nuong', 'Khoai tây cắt múi cau để vỏ nướng vàng ruộm, tẩm muối biển tinh khiết và lá hương thảo thơm lừng phong cách châu Âu.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 49000.00, 39000.00, 1, 0, 15),
(14, 'prod-14', 1, 3, 'Khoai Tây Chiên Pháp Phủ Sốt Phô Mai Cheddar Nóng', 'khoai-tay-chien-sot-pho-mai', 'Khoai tây chiên vàng rộm ngập trong lớp sốt phô mai Cheddar nấu nóng sánh mịn, rắc thêm vụn thịt xông khói giòn tan.', 'https://images.unsplash.com/photo-1585109649139-366815a0d713?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 15),
(15, 'prod-15', 1, 3, 'Vòng Hành Tây Chiên Xù Giòn Rụm (Crispy Onion Rings)', 'vong-hanh-tay-chien-xu', 'Hành tây ngọt thanh tẩm bột chiên xù vàng óng, giòn rụm bên ngoài và mềm mọng bên trong, chấm cùng sốt cocktail cay ngọt.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 0, 15),
(16, 'prod-16', 1, 3, 'Phô Mai Que Kéo Sợi Mozzarella Chiên Vàng (4 que)', 'pho-mai-que-mozzarella', 'Phô mai Mozzarella nguyên chất kéo sợi dài bất tận, vỏ bột giòn rụm chấm tương cà chua ngọt cực cuốn.', 'https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=600&auto=format&fit=crop&q=80', 52000.00, 42000.00, 1, 1, 15),
(17, 'prod-17', 1, 3, 'Salad Trộn Dầu Giấm Bò Nướng BBQ Than Hoa', 'salad-tron-dau-giam-bo-nuong', 'Xà lách Romaine, cà chua bi, dưa leo và bắp ngọt trộn dầu giấm Ý, phủ những lát thịt bò nướng than hoa thơm nức.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 72000.00, 59000.00, 1, 0, 15),
(18, 'prod-18', 1, 3, 'Bánh Táo Nướng Quế Giòn Nóng Burger King (Apple Pie)', 'banh-tao-nuong-que', 'Vỏ bánh ngàn lớp giòn tan bao bọc nhân táo tươi sốt caramel thơm lừng hương quế ấm áp, dùng nóng giòn sảng khoái.', 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=600&auto=format&fit=crop&q=80', 38000.00, 29000.00, 1, 0, 15),
(19, 'prod-19', 1, 3, 'Trà Chanh Vàng Mật Ong Đá Xay Mát Lạnh', 'tra-chanh-vang-mat-ong', 'Thức uống giải ngấy tuyệt hảo sau khi thưởng thức burger! Chanh vàng mọng nước hòa quyện mật ong nguyên chất.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 0, 15),
(20, 'prod-20', 1, 3, 'Nước Ngọt Coca-Cola Tươi Mát Lạnh (Ly Lớn 500ml)', 'coca-cola-tuoi-mat-lanh', 'Nước ngọt có ga sảng khoái mát lạnh giúp bữa ăn thêm phần trọn vị và sảng khoái bùng nổ.', 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=600&auto=format&fit=crop&q=80', 22000.00, 18000.00, 1, 0, 15),
(21, 'prod-21', 2, 2, 'Cơm Tấm Sườn Cốt Lết Nướng Than Hoa Bì Chả', 'com-tam-suon-bi-cha', 'Dĩa cơm tấm hạt vỡ chuẩn vị, sườn cốt lết dày dặn ướp mật ong nướng than hoa vàng óng, bì dai giòn, chả trứng hấp béo bùi.', '/assets/images/foods/com-tam-suon-nuong.jpg', 65000.00, 55000.00, 1, 1, 15),
(22, 'prod-22', 2, 2, 'Cơm Tấm Sườn Bì Chả Trứng Ốp La (Suất Đặc Biệt)', 'com-tam-suon-bi-cha-trung-dac-biet', 'Dĩa cơm thịnh soạn đủ 4 món tinh hoa: Miếng sườn to bản nướng mềm thơm, bì thính vàng ươm, chả trứng nấm mèo và trứng ốp la lòng đào.', '/assets/images/foods/com-tam-suon-bi-cha.jpg', 75000.00, 65000.00, 1, 1, 15),
(23, 'prod-23', 2, 2, 'Cơm Tấm Sườn Cây Khổng Lồ Nướng Mật Ong Rừng', 'com-tam-suon-cay-mat-ong', 'Cọng sườn heo tơ cây dài chọn lọc, ướp mật ong rừng hoa cà phê Đắk Lắk và nước mắm nhỉ, nướng xém cạnh thơm nức mũi.', '/assets/images/foods/com-tam-dac-biet.jpg', 89000.00, 75000.00, 1, 1, 15),
(24, 'prod-24', 2, 2, 'Cơm Tấm Ba Chỉ Heo Rút Sườn Nướng Giòn Da', 'com-tam-ba-chi-heo-nuong', 'Ba chỉ heo ba chỉ nạc mỡ đan xen đều đặn, nướng lửa than hoa giòn rụm phần da và mọng nước phần thịt.', '/assets/images/foods/heo-quay.jpg', 62000.00, 52000.00, 1, 0, 15),
(25, 'prod-25', 2, 2, 'Cơm Tấm Đùi Gà Nướng Mật Ong Rừng Vàng Ruộm', 'com-tam-dui-ga-nuong-mat-ong', 'Nguyên chiếc đùi gà góc phần tư ướp gia vị cổ truyền và mật ong, nướng chín tới bóng bẩy, thịt gà mềm ngọt thơm lừng.', '/assets/images/foods/dui-ga-nuong.jpg', 65000.00, 55000.00, 1, 1, 15),
(26, 'prod-26', 2, 2, 'Cơm Tấm Đùi Gà Chiên Nước Mắm Tỏi Ớt Đậm Đà', 'com-tam-dui-ga-chien-nuoc-mam', 'Đùi gà chiên lớp da giòn rụm ngào sốt nước mắm tỏi ớt kẹo sệt chua ngọt mặn mà đưa cơm vô cùng.', 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80', 68000.00, 58000.00, 1, 0, 15),
(27, 'prod-27', 2, 2, 'Cơm Tấm Chả Cua Biển Hấp Trứng Muối', 'com-tam-cha-cua-trung-muoi', 'Chả làm từ thịt cua biển tươi, thịt heo nạc bằm, nấm mèo và lòng đỏ trứng muối bùi béo hấp cách thủy chín tới thơm lừng.', '/assets/images/foods/suon-nuong-dia.jpg', 70000.00, 59000.00, 1, 0, 15),
(28, 'prod-28', 2, 2, 'Cơm Tấm Bò Xào Cần Tây Hành Tây Sốt Dầu Hào', 'com-tam-bo-xao-can-tay', 'Thịt bò phi lê xào lửa lớn cùng cần tây, hành tây và cà chua giòn ngọt, nước sốt bò đậm đà chan cơm tấm nóng hổi.', '/assets/images/foods/bo-xao.jpg', 78000.00, 65000.00, 1, 0, 15),
(29, 'prod-29', 2, 2, 'Cơm Tấm Xíu Mại Trứng Muối Sốt Cà Chua Tươi', 'com-tam-xiu-mai-trung-muoi', 'Viên xíu mại thịt heo bằm mềm mịn ôm trọn nhân trứng muối béo ngậy, ninh trong sốt cà chua tươi sánh mịn thơm ngào ngạt.', '/assets/images/foods/banh-mi-chao.jpg', 58000.00, 48000.00, 1, 0, 15),
(30, 'prod-30', 2, 2, 'Cơm Tấm Thịt Ba Chỉ Kho Tàu Trứng Vịt Bùi Béo', 'com-tam-thit-kho-tau', 'Thịt heo ba chỉ kho nước dừa xiêm béo mềm tan trong miệng, miếng thịt màu cánh gián và quả trứng vịt kho thấm đượm gia vị.', '/assets/images/foods/thit-kho-tau.jpg', 62000.00, 52000.00, 1, 0, 15),
(31, 'prod-31', 2, 2, 'Cơm Tấm Cá Thu Kho Tiêu Tộ Đất Sài Gòn', 'com-tam-ca-thu-kho-tieu', 'Khúc cá thu tươi kho trong tộ đất với tiêu sọ Đắk Lắk cay thơm, nước cá sền sệt đậm đà chan cơm thơm ngon khó cưỡng.', '/assets/images/foods/ca-kho-to.jpg', 75000.00, 62000.00, 1, 0, 15),
(32, 'prod-32', 2, 2, 'Cơm Tấm Sườn Non Rang Muối Cay Hong Kong', 'com-tam-suon-non-rang-muoi', 'Sườn non sụn giòn sần sật tẩm bột chiên vàng, xóc muối tỏi ớt rang khô đậm đà hấp dẫn vô cùng.', 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&auto=format&fit=crop&q=80', 82000.00, 68000.00, 1, 0, 15),
(33, 'prod-33', 2, 2, 'Cơm Tấm Tôm Rim Thịt Ba Chỉ Đậm Đà Cơm Nhà', 'com-tam-tom-rim-ba-chi', 'Tôm đồng vỏ giòn thịt ngọt rim kẹo cùng thịt ba chỉ xắt hạt lựu, mặn mặn ngọt ngọt óng ả màu đường thốt nốt.', 'https://images.unsplash.com/photo-1559847844-5315695dadae?w=600&auto=format&fit=crop&q=80', 70000.00, 58000.00, 1, 0, 15),
(34, 'prod-34', 2, 2, 'Cơm Tấm Mực Nhồi Thịt Sốt Cà Chua Chua Ngọt', 'com-tam-muc-nhoi-thit', 'Mực ống tươi dày mình nhồi thịt bằm mộc nhĩ nấm hương, sốt cà chua chua thanh dậy mùi tiêu hành.', 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f1/Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_%282448208587%29.jpg/960px-Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_%282448208587%29.jpg', 78000.00, 65000.00, 1, 0, 15),
(35, 'prod-35', 2, 2, 'Tô Canh Khổ Qua Dồn Thịt Bằm Nước Trong Thanh Mát', 'canh-kho-qua-don-thit', 'Món canh truyền thống giải nhiệt tuyệt vời! Khổ qua đắng thanh dồn thịt nạc vai xay nhuyễn, nước dùng xương hầm trong ngọt lịm.', '/assets/images/foods/canh-kho-qua.jpg', 32000.00, 25000.00, 1, 1, 15),
(36, 'prod-36', 2, 2, 'Tô Canh Rong Biển Đậu Hũ Non Thịt Bằm', 'canh-rong-bien-dau-hu-non', 'Rong biển thanh mát bổ dưỡng nấu cùng đậu hũ non mềm mịn và thịt heo bằm, thơm ấm mùi gừng tươi xắt sợi.', '/assets/images/foods/canh-rong-bien.jpg', 28000.00, 22000.00, 1, 0, 15),
(37, 'prod-37', 2, 2, 'Tô Canh Chua Cá Hồi Lá Giang Bạc Hà Đậm Vị Nam Bộ', 'canh-chua-ca-hoi-la-giang', 'Vị chua thanh mát của lá giang và me tươi quyện vị béo ngậy của lườn cá hồi tươi, kèm đậu bắp, bạc hà, giá đỗ giòn ngọt.', '/assets/images/foods/canh-chua.jpg', 45000.00, 35000.00, 1, 0, 15),
(38, 'prod-38', 2, 2, 'Chả Cua Hấp Thơm Bùi Trứng Muối (Phần Thêm 1 Chiếc)', 'cha-cua-hap-phan-them', 'Phần chả cua gọi thêm cho thực khách muốn ăn thêm chả, đẫm vị thịt cua và lòng đỏ trứng muối.', '/assets/images/foods/com-tam-suon-bi-cha.jpg', 22000.00, 18000.00, 1, 0, 15),
(39, 'prod-39', 2, 2, 'Trà Sâm Dứa Hạt É Thơm Mát Giải Nhiệt', 'tra-sam-dua-hat-e', 'Trà sâm dứa Đà Nẵng thơm nức nở kết hợp hạt é mát lành, chuẩn thức uống giải nhiệt số 1 quán cơm Sài Gòn xưa.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 18000.00, 15000.00, 1, 0, 15),
(40, 'prod-40', 2, 2, 'Nước Mát Mía Lau Rễ Tranh Rong Biển Nấu Đường Phèn', 'nuoc-mat-mia-lau-re-tranh', 'Nước mát nấu từ mía lau, rễ tranh, lá thuốc dòi và rong biển, vị ngọt thanh mát dịu từ đường phèn tự nhiên.', '/assets/images/foods/nuoc-mia.jpg', 22000.00, 18000.00, 1, 0, 15),
(41, 'prod-41', 3, 4, 'Trà Sữa Trân Châu Đường Đen Hoàng Gia (Size L)', 'tra-sua-tran-chau-hoang-gia', 'Trà đen Ceylon thơm nồng kết hợp sữa tươi thanh trùng béo nhẹ và trân châu đường đen dẻo dai nấu mới mỗi 3 tiếng.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 15),
(42, 'prod-42', 3, 4, 'Trà Đen Alisan Macchiato Kem Muối Biển (Size L)', 'tra-alisan-macchiato', 'Trà núi cao Alisan Đài Loan nước vàng trong veo hương hoa thanh nhã, phủ lớp kem sữa phô mai mặn béo mịn màng dày 2cm.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 58000.00, 48000.00, 1, 1, 15),
(43, 'prod-43', 3, 4, 'Trà Sữa Oolong Nướng Trân Châu Hoàng Kim (Size L)', 'tra-sua-oolong-nuong-hoang-kim', 'Lá trà Oolong sấy than hoa thơm lừng vị khói ấm áp, quyện cùng sữa béo ngọt và trân châu hoàng kim màu mật ong trong veo dai giòn.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 62000.00, 50000.00, 1, 1, 15),
(44, 'prod-44', 3, 4, 'Trà Sữa Matcha Nhật Bản Đậu Đỏ Dẻo (Size L)', 'tra-sua-matcha-dau-do', 'Bột trà xanh Uji Kyoto nguyên chất đậm vị chát dịu kết hợp sữa béo thanh và hạt đậu đỏ Azuki ninh nhừ bùi ngọt.', 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 65000.00, 52000.00, 1, 1, 15),
(45, 'prod-45', 3, 4, 'Trà Xanh Đào Dâu Tây Thạch Nha Đam Mát Lạnh (Size L)', 'tra-xanh-dao-dau-tay', 'Trà lài thơm ngát kết hợp mứt đào giòn ngọt và dâu tây tươi mọng, topping miếng đào ngâm giòn sần sật và nha đam thanh mát.', 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 15),
(46, 'prod-46', 3, 4, 'Trà Xoài Nhiệt Đới Hạt Chia Kem Cheese (Size L)', 'tra-xoai-nhiet-doi-kem-cheese', 'Xoài cát chín vàng xay nhuyễn cùng trà xanh nhài thanh khiết, hạt chia giàu dinh dưỡng và lớp kem phô mai béo ngậy trên miệng ly.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 62000.00, 52000.00, 1, 0, 15),
(47, 'prod-47', 3, 4, 'Trà Vải Lài Hoa Hồng Thanh Mát (Size L)', 'tra-vai-lai-hoa-hong', 'Hương thơm quyến rũ của cánh hoa hồng hữu cơ hòa quyện trà lài thanh tao và những trái vải thiều trắng mọng giòn ngọt.', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 56000.00, 46000.00, 1, 0, 15),
(48, 'prod-48', 3, 4, 'Trà Sữa Khoai Môn Tươi Sợi Dẻo (Taro Milk Tea)', 'tra-sua-khoai-mon-tuoi', 'Khoai môn tươi Đà Lạt hấp chín nghiền dẻo quánh, tạo nên ly trà sữa màu tím mộng mơ béo bùi tự nhiên không phẩm màu.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 58000.00, 48000.00, 1, 0, 15),
(49, 'prod-49', 3, 4, 'Trà Sữa Thái Đỏ Kem Cheese Trân Châu Đen', 'tra-sua-thai-do-kem-cheese', 'Trà đỏ Thái Lan thơm nồng thảo mộc, sữa đặc ngọt béo ngậy và lớp kem phô mai sánh mịn phủ trên cùng.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 52000.00, 42000.00, 1, 0, 15),
(50, 'prod-50', 3, 4, 'Trà Ô Long Bưởi Đỏ Mật Ong Rừng (Size L)', 'tra-o-long-buoi-do', 'Tép bưởi đỏ mọng nước chua thanh kết hợp trà Ô Long đậm vị và mật ong hoa cà phê, thức uống bổ sung vitamin C hoàn hảo.', 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 58000.00, 48000.00, 1, 0, 15),
(51, 'prod-51', 3, 4, 'Cà Phê Muối Cố Đô Đậm Đà Kem Béo Buôn Ma Thuột', 'ca-phe-muoi-co-do', 'Cà phê Robusta Đắk Lắk nguyên chất pha phin đậm đặc, lớp kem sữa tươi đánh cùng muối biển hồng Himalaya mặn béo ngậy đỉnh cao.', 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 1, 15),
(52, 'prod-52', 3, 4, 'Cà Phê Cốt Dừa Đá Xay Béo Ngậy Thơm Nức', 'ca-phe-cot-dua-da-xay', 'Cốt dừa Bến Tre xay mịn cùng sữa đặc và đá tuyết, rót cà phê đen nguyên chất thơm lừng lên trên bề mặt.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 0, 15),
(53, 'prod-53', 3, 4, 'Americano Cam Vàng Đá Xay Thanh Mát', 'americano-cam-vang', 'Espresso nguyên chất chiết xuất áp suất cao kết hợp nước cam vàng Mỹ mọng nước và mật ong hoa rừng.', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 49000.00, 39000.00, 1, 0, 15),
(54, 'prod-54', 3, 4, 'Trà Sữa Earl Grey Bá Tước Anh Quốc (Size L)', 'tra-sua-earl-grey-ba-tuoc', 'Trà đen thượng hạng ướp tinh dầu cam Bergamot quý tộc tạo hương thơm sang trọng, vị chát êm ái quyện sữa ngậy.', 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 59000.00, 49000.00, 1, 0, 15),
(55, 'prod-55', 3, 4, 'Trà Chanh Mật Ong Kim Quất Nha Đam (Size L)', 'tra-chanh-mat-ong-kim-quat', 'Chanh tươi, tắc mật ong thơm lừng kết hợp thạch nha đam thanh mát, vị chua ngọt giải nhiệt ngày hè cực đã.', 'https://images.unsplash.com/photo-1541658016709-82535e94bc69?w=600&auto=format&fit=crop&q=80', 48000.00, 38000.00, 1, 0, 15),
(56, 'prod-56', 3, 4, 'Sữa Tươi Trân Châu Đường Đen Phun Lửa (Crème Brûlée)', 'sua-tuoi-tran-chau-creme-brulee', 'Sữa tươi thanh trùng Dalat Milk kết hợp trân châu nấu đường đen dẻo nóng, mặt trên là lớp kem trứng khè lửa giòn rụm thơm mùi caramel.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 68000.00, 55000.00, 1, 1, 15),
(57, 'prod-57', 3, 4, 'Sữa Chua Lắc Dâu Tây & Việt Quất Tươi', 'sua-chua-lac-dau-tay', 'Sữa chua lên men tự nhiên sánh mịn đánh cùng mứt việt quất và dâu tây Đà Lạt tươi giòn ngọt lịm.', 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 0, 15),
(58, 'prod-58', 3, 4, 'Sinh Tố Bơ Sáp Đắk Lắk Nước Cốt Dừa Thơm Béo', 'sinh-to-bo-dak-lak', 'Bơ sáp loại 1 hái tại vườn Buôn Ma Thuột, cơm bơ vàng ươm dẻo quánh xay cùng nước cốt dừa và sữa đặc béo ngậy ngất ngây.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 58000.00, 48000.00, 1, 1, 15),
(59, 'prod-59', 3, 4, 'Trà Sữa Hokkaido Vani Caramel Béo Thơm (Size L)', 'tra-sua-hokkaido-caramel', 'Sữa bò Hokkaido Nhật Bản danh tiếng béo đậm đà kết hợp sốt caramel thơm ngọt và vani Madagascar tự nhiên.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 60000.00, 49000.00, 1, 0, 15),
(60, 'prod-60', 3, 4, 'Bánh Bột Mì Nướng Croffle Phô Mai Trà Xanh (Croffle Matcha)', 'croffle-matcha-pho-mai', 'Bánh sừng bò ép nướng theo khuôn waffle giòn rụm bên ngoài xốp mềm bên trong, phủ sốt matcha và phô mai béo ngọt ăn kèm trà sữa.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 0, 15),
(61, 'prod-61', 4, 5, 'Pizza Hải Sản Sốt Pesto Ý Thượng Hạng (Size Vừa 9 inch)', 'pizza-hai-san-pesto', 'Tôm sú tươi giòn, mực ống tươi và nghêu biển hòa quyện cùng sốt Pesto húng tây thơm nức, ngập tràn phô mai Mozzarella kéo sợi.', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 179000.00, 139000.00, 1, 1, 15),
(62, 'prod-62', 4, 5, 'Pizza Bò Băm Nướng Phô Mai Xúc Xích BBQ (Size 9 inch)', 'pizza-bo-bam-bbq', 'Thịt bò băm ướp sốt BBQ đậm đà, xúc xích tiêu cay, nấm mỡ tươi và ớt chuông nướng giòn ngọt trên nền đế bánh xốp mềm.', 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80', 159000.00, 129000.00, 1, 1, 15),
(63, 'prod-63', 4, 5, 'Pizza 4 Loại Phô Mai Ý Kéo Sợi (Quattro Formaggi)', 'pizza-4-loai-pho-mai', 'Đỉnh cao phô mai Ý! Sự kết hợp hoàn mỹ của Mozzarella kéo sợi, Gorgonzola thơm béo, Parmesan mặn mà và Ricotta bùi ngậy.', 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600&auto=format&fit=crop&q=80', 185000.00, 145000.00, 1, 1, 15),
(64, 'prod-64', 4, 5, 'Pizza Gà Nướng Nấm Rơm & Bơ Tỏi Đút Lò', 'pizza-ga-nuong-nam', 'Thịt gà nướng xé nhỏ ngấm gia vị bơ tỏi, nấm rơm tươi ngọt dịu và sốt kem trắng Alfredo béo mịn.', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80', 149000.00, 119000.00, 1, 0, 15),
(65, 'prod-65', 4, 5, 'Pizza Xúc Xích Ý Pepperoni Truyền Thống Nướng Giòn', 'pizza-pepperoni-truyen-thong', 'Những lát xúc xích Pepperoni bò cay đỏ au nướng cong giòn trên nền sốt cà chua San Marzano và phô mai Mozzarella.', 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=600&auto=format&fit=crop&q=80', 155000.00, 125000.00, 1, 1, 15),
(66, 'prod-66', 4, 5, 'Pizza Dăm Bông & Dứa Kiểu Hawaii (Hawaiian Pizza)', 'pizza-hawaiian-dam-bong-dua', 'Vị ngọt thanh mọng nước của từng lát dứa mật kết hợp thịt dăm bông hun khói và sốt cà chua truyền thống dễ ăn cực kỳ.', 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80', 145000.00, 119000.00, 1, 1, 15),
(67, 'prod-67', 4, 5, 'Pizza Tôm Càng Sốt Bơ Tỏi Chanh Vàng Đút Lò', 'pizza-tom-cang-bo-toi', 'Tôm càng tuyển chọn tươi sống áp chảo bơ tỏi thơm ngào ngạt, rưới chút chanh vàng Ý và phủ phô mai vàng óng.', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 199000.00, 159000.00, 1, 1, 15),
(68, 'prod-68', 4, 5, 'Pizza Nấm Truffle & Rau Chân Vịt (Pizza Chay Ý)', 'pizza-nam-truffle-chay', 'Lựa chọn thanh đạm sang trọng với nấm mỡ, nấm đông cô, rau bina chân vịt tươi và sốt nấm Truffle đen quý phái.', 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80', 165000.00, 135000.00, 1, 0, 15),
(69, 'prod-69', 4, 5, 'Pizza Thịt Heo Xông Khói & Phô Mai Cheddar Vàng', 'pizza-bacon-cheddar', 'Dải thịt ba chỉ xông khói áp chảo giòn thơm ngập trong sốt phô mai Cheddar béo ngậy và hành tây ngọt dịu.', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80', 159000.00, 129000.00, 1, 0, 15),
(70, 'prod-70', 4, 5, 'Pizza Calzone Bánh Gối Ý Gấp Nửa Nhân Bò Băm Phô Mai', 'pizza-calzone-banh-goi-y', 'Pizza cuộn hình bán nguyệt truyền thống vùng Napoli, cắn vỡ lớp vỏ bánh giòn tan là dòng phô mai và thịt bò sốt cà chảy tràn.', 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 145000.00, 119000.00, 1, 0, 15),
(71, 'prod-71', 4, 5, 'Mì Ý Spaghetti Bò Băm Sốt Cà Chua (Bolognese)', 'spaghetti-bo-bam-bolognese', 'Sợi mì Ý luộc chuẩn Al Dente dai mềm, sốt bò băm hầm kỹ cùng rượu vang đỏ và cà chua bi Ý ngọt lành, rắc bột phô mai thơm phức.', 'https://images.unsplash.com/photo-1621996346565-e3d5d6281724?w=600&auto=format&fit=crop&q=80', 95000.00, 79000.00, 1, 1, 15),
(72, 'prod-72', 4, 5, 'Mì Ý Sốt Kem Hải Sản Carbonara Tôm Mực Nấm', 'spaghetti-hai-san-carbonara', 'Sốt kem tươi nấu cùng lòng đỏ trứng gà béo ngậy, tôm sú giòn ngọt và mực ống tươi đảo cùng nấm mỡ xào bơ tỏi.', 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 15),
(73, 'prod-73', 4, 5, 'Nui Ống Xoắn Đút Lò Phô Mai Mozzarella Vàng Ruộm', 'nui-dut-lo-pho-mai', 'Nui ống ngấm sốt bò bằm cà chua, mặt trên phủ kín lớp phô mai Mozzarella đút lò vàng xém thơm lừng kéo sợi hấp dẫn.', 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 89000.00, 75000.00, 1, 0, 15),
(74, 'prod-74', 4, 5, 'Bánh Mì Bơ Tỏi Nướng Giòn Kiểu Pháp (Garlic Bread)', 'banh-mi-bo-toi-phap', 'Bánh mì baguette nướng giòn rụm phết bơ lạt nguyên chất quyện tỏi bằm và lá mùi tây thơm ngát quyến rũ.', 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 42000.00, 32000.00, 1, 1, 15),
(75, 'prod-75', 4, 5, 'Cánh Gà Nướng Sốt BBQ Mật Ong (6 miếng)', 'canh-ga-nuong-bbq-mat-ong', 'Cánh gà giữa tẩm ướp sốt BBQ mật ong đậm vị khói nướng trong lò đá, da gà vàng bóng óng ả thơm lừng.', 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 85000.00, 69000.00, 1, 1, 15),
(76, 'prod-76', 4, 5, 'Mực Vòng Chiên Giòn Calamari Sốt Tartar Ý', 'muc-vong-calamari-chot-tartar', 'Từng khoanh mực tươi tẩm bột chiên giòn rụm bên ngoài nhưng bên trong mực vẫn mềm mọng nước, chấm cùng sốt tartar chanh tươi.', 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/40/Deep-fried_Calamari_Rings.jpg/960px-Deep-fried_Calamari_Rings.jpg', 79000.00, 65000.00, 1, 0, 15),
(77, 'prod-77', 4, 5, 'Khoai Tây Chiên Phủ Phô Mai & Bột Thảo Mộc Đút Lò', 'khoai-tay-phu-pho-mai-dut-lo', 'Khoai tây cọng lớn chiên vàng ươm, đút lò cùng phô mai Mozzarella và rắc lá thơm oregano chuẩn vị Ý.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 0, 15),
(78, 'prod-78', 4, 5, 'Salad Rau Trộn Kiểu Caesar với Ức Gà Nướng & Phô Mai', 'salad-caesar-uc-ga', 'Xà lách Romaine tươi giòn trộn sốt Caesar cá cơm truyền thống, bánh mì nướng giòn vụn, phô mai Parmesan và ức gà nướng áp chảo.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 72000.00, 59000.00, 1, 0, 15),
(79, 'prod-79', 4, 5, 'Bánh Lava Socola Đút Lò Tan Chảy Kem Vani Lạnh', 'banh-lava-socola-kem-vani', 'Món tráng miệng Ý quyến rũ! Xắn nhẹ lớp bánh nướng là dòng socola đen Bỉ ấm nóng tan chảy, ăn cùng viên kem vani mát lạnh tương phản.', 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=600&auto=format&fit=crop&q=80', 62000.00, 49000.00, 1, 1, 15),
(80, 'prod-80', 4, 5, 'Nước Ngọt Pepsi Tươi Có Ga Mát Lạnh (Ly Lớn 500ml)', 'pepsi-tuoi-mat-lanh', 'Thức uống có ga kinh điển giúp cân bằng vị giác và thưởng thức trọn vẹn những miếng pizza phô mai béo ngậy.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 22000.00, 18000.00, 1, 0, 15),
(81, 'prod-81', 5, 2, 'Phở Bò Tái Nạm Nước Dùng Ninh Xương Ống 18 Tiếng', 'pho-bo-tai-nam', 'Nước dùng trong veo ninh từ xương ống bò Úc cùng hoa hồi, quế chi, thảo quả 18 tiếng ngọt thanh không mì chính. Bò tái mềm mọng, nạm bò giòn béo thơm lừng.', '/assets/images/foods/pho-bo.jpg', 79000.00, 69000.00, 1, 1, 15),
(82, 'prod-82', 5, 2, 'Phở Bò Tái Lăn Áp Chảo Thơm Lừng Tỏi Gừng', 'pho-bo-tai-lan-ap-chao', 'Thịt bò bắp thái mỏng lăn áp chảo trên lửa bùng cùng tỏi đập dập và gừng già, trút lên bát phở thơm ngút ngàn dậy vị Bắc.', '/assets/images/foods/pho-bo.jpg', 89000.00, 75000.00, 1, 1, 15),
(83, 'prod-83', 5, 2, 'Bát Phở Bò Đặc Biệt (Tái, Nạm, Gầu, Gân, Bò Viên)', 'pho-bo-dac-biet-thap-cam', 'Tô phở thịnh soạn ngập tràn 5 loại thịt bò hảo hạng: Thịt bò tái mềm ngọt, nạm giòn, gầu béo ngậy, gân trong dẻo giòn và bò viên gân sần sật.', 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 15),
(84, 'prod-84', 5, 2, 'Phở Gầu Giòn Béo Ngậy Ninh Thảo Mộc', 'pho-gau-gion-thao-moc', 'Miếng gầu bò có lớp mỡ trắng giòn sần sật không hề ngấy, quyện cùng nước dùng nóng rẫy đậm đà thảo mộc phương Bắc.', 'https://images.unsplash.com/photo-1576577445504-6af96477db52?w=600&auto=format&fit=crop&q=80', 85000.00, 72000.00, 1, 0, 15),
(85, 'prod-85', 5, 2, 'Phở Bắp Bò Hoa Tươi Nhúng Tái Mềm Ngọt', 'pho-bap-bo-hoa-nhung', 'Bắp bò hoa thái mỏng như cánh lụa, nhúng vừa chín tới giữ trọn từng đường gân hoa giòn rụm và vị ngọt tự nhiên của thịt bò tươi.', 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80', 92000.00, 78000.00, 1, 0, 15),
(86, 'prod-86', 5, 2, 'Phở Bò Sốt Vang Rượu Đỏ Rực Màu Gấc Hà Nội', 'pho-bo-sot-vang-ha-noi', 'Dẻ sườn bò và gân bò hầm nhừ cùng rượu vang đỏ, hạt tiêu và sốt gấc đỏ au sánh mịn, vị thơm lừng ấm áp cực kỳ cuốn hút.', '/assets/images/foods/pho-bo.jpg', 89000.00, 75000.00, 1, 1, 15),
(87, 'prod-87', 5, 2, 'Phở Đuôi Bò Hầm Thuốc Bắc Bổ Dưỡng Đại Bổ', 'pho-duoi-bo-ham-thuoc-bac', 'Khúc đuôi bò hầm cùng kỷ tử, đương quy, táo đỏ và hạt sen trong nhiều giờ, lớp da dẻo ngậy giòn sần sật đại bổ dưỡng.', '/assets/images/foods/pho-bo.jpg', 120000.00, 95000.00, 1, 1, 15),
(88, 'prod-88', 5, 2, 'Phở Gân Bò Hầm Dẻo Giòn Sần Sật', 'pho-gan-bo-ham-deo', 'Gân bò tươi làm sạch ninh vừa độ, trong suốt như ngọc, dẻo mềm nhưng cắn vào vẫn có độ giòn sần sật thú vị.', '/assets/images/foods/pho-bo.jpg', 80000.00, 68000.00, 1, 0, 15),
(89, 'prod-89', 5, 2, 'Phở Gà Ta Thả Vườn Xé Phay Lá Chanh Tươi', 'pho-ga-ta-xe-phay', 'Thịt gà ta thả vườn Đắk Lắk da vàng ươm giòn sần sật, thịt ngọt thơm xé phay rắc lá chanh bắc thái chỉ, nước dùng gà thanh khiết.', '/assets/images/foods/pho-ga.jpg', 78000.00, 65000.00, 1, 1, 15),
(90, 'prod-90', 5, 2, 'Phở Đùi Gà Ta Chặt Miếng Chấm Muối Tiêu Chanh', 'pho-dui-ga-ta-chat', 'Nguyên chiếc đùi gà ta chặt miếng bày đẹp mắt trên tô phở, chấm đĩa muối tiêu chanh ớt hiểm cay nồng xuýt xoa.', '/assets/images/foods/pho-ga.jpg', 89000.00, 75000.00, 1, 0, 15),
(91, 'prod-91', 5, 2, 'Phở Trộn Bò Nướng Tỏi Ớt & Đậu Phộng Rang (Phở Khô)', 'pho-tron-bo-nuong', 'Bánh phở tươi trộn sốt chua ngọt đặc chế, thịt bò xào tỏi thơm phức, rau thơm, giá đỗ, hành phi và đậu phộng rang giòn thơm, kèm bát nước dùng con.', '/assets/images/foods/bun-thit-nuong.jpg', 82000.00, 68000.00, 1, 1, 15),
(92, 'prod-92', 5, 2, 'Phở Xào Bò Cải Ngọt Giòn Rụm Lửa Lớn', 'pho-xao-bo-cai-ngot', 'Bánh phở áp chảo cháy cạnh thơm mùi khói chảo gang, xào cùng thịt bò bắp mọng nước và cải ngọt xanh non mướt mát.', 'https://images.unsplash.com/photo-1555126634-323283e090fa?w=600&auto=format&fit=crop&q=80', 89000.00, 75000.00, 1, 0, 15),
(93, 'prod-93', 5, 2, 'Phở Cuốn Bò Xào Rau Thơm Nước Chấm Tỏi Ớt (5 chiếc)', 'pho-cuon-bo-xao-ha-noi', 'Bánh phở tráng mỏng mềm mướt cuốn cùng thịt bò xào gừng tỏi, rau xà lách, rau mùi húng láng, chấm nước mắm chua ngọt đu đủ giòn.', '/assets/images/foods/goi-cuon.jpg', 65000.00, 55000.00, 1, 1, 15),
(94, 'prod-94', 5, 2, 'Bánh Phở Chiên Phồng Xốt Thịt Bò Cải Xanh', 'banh-pho-chien-phong-sot-bo', 'Từng miếng bánh phở vuông vức chiên phồng xốp giòn rụm như bánh phồng tôm, rưới sốt thịt bò xào cải xanh sánh vàng óng ả.', 'https://images.unsplash.com/photo-1555126634-323283e090fa?w=600&auto=format&fit=crop&q=80', 95000.00, 79000.00, 1, 0, 15),
(95, 'prod-95', 5, 2, 'Bát Tiết Hầm Trứng Gà Ta Lòng Đào Hành Trần', 'tiet-ham-trung-ga-ta', 'Tiết bò mềm mướt không tanh hầm trong nước dùng phở bò ngọt lịm, kèm 1 quả trứng gà ta trần lòng đào béo ngậy và hành hoa.', '/assets/images/foods/pho-bo.jpg', 32000.00, 25000.00, 1, 0, 15),
(96, 'prod-96', 5, 2, 'Chén Tủy Bò Béo Ngậy Hành Trần Tiêu Sọ', 'chen-tuy-bo-beo-ngay', 'Tủy bò tươi lấy từ xương ống hầm béo ngậy, trần tái lăn cùng hành lá và hạt tiêu sọ Đắk Lắk thơm cay ấm nồng.', 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80', 38000.00, 30000.00, 1, 0, 15),
(97, 'prod-97', 5, 2, 'Quẩy Giòn Rụm Chiên Mới Ăn Kèm Phở (Phần 4 chiếc)', 'quay-gion-rum-an-pho', 'Quẩy vàng ruộm chiên nóng giòn xốp rụm, nhúng ngập vào nước dùng phở bò hút trọn tinh hoa nước hầm xương thơm ngọt.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 15000.00, 12000.00, 1, 1, 15),
(98, 'prod-98', 5, 2, 'Trứng Gà Ta Trần Nước Dùng Phở (1 quả lòng đào)', 'trung-ga-ta-tran-long-dao', 'Quả trứng gà ta nuôi thả tự nhiên trần vào nồi nước dùng sôi sùng sục, lòng trắng vừa chín tới, lòng đỏ béo dẻo tan trên đầu lưỡi.', 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80', 10000.00, 8000.00, 1, 0, 15),
(99, 'prod-99', 5, 2, 'Trà Đá Hoa Nhài Chuẩn Vị Bắc Thơm Ngát', 'tra-da-hoa-nhai-vi-bac', 'Trà búp Thái Nguyên ướp hoa nhài tươi tự nhiên, chát dịu hậu ngọt sâu và mát lạnh thơm ngát sau khi ăn bát phở nóng.', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 10000.00, 8000.00, 1, 0, 15),
(100, 'prod-100', 5, 2, 'Sữa Đậu Nành Nấu Lá Dứa Nguyên Chất (Nóng / Đá)', 'sua-dau-nanh-la-dua', 'Đậu nành hạt tuyển chọn xay nấu thủ công cùng lá dứa thơm, không chất bảo quản, sánh mịn béo bùi thanh ngọt.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 20000.00, 15000.00, 1, 0, 15),
(101, 'prod-101', 6, 9, 'Sushi Cá Hồi Cuộn Bơ Sáp & Trứng Cá Chuồn (8 khoanh)', 'sushi-ca-hoi-cuon-bo', 'Cá hồi Na Uy tươi nhập khẩu trong ngày cuộn cùng bơ sáp Đắk Lắk dẻo bùi, phủ trứng cá chuồn đỏ cam nổ lách tách trong miệng.', 'https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=600&auto=format&fit=crop&q=80', 109000.00, 89000.00, 1, 1, 20),
(102, 'prod-102', 6, 9, 'Sashimi Cá Hồi Tươi Na Uy Thượng Hạng (5 lát dày)', 'sashimi-ca-hoi-na-uy', 'Từng lát cá hồi tươi sống cắt lát dày béo ngậy vân mỡ cẩm thạch trắng tuyết, ngọt tan trên đầu lưỡi, chuẩn ăn tươi sashimi Nhật.', 'https://images.unsplash.com/photo-1534482421-64566f976cfa?w=600&auto=format&fit=crop&q=80', 145000.00, 119000.00, 1, 1, 20),
(103, 'prod-103', 6, 9, 'Sashimi Cá Ngừ Đại Dương Phú Yên Tươi Sống (5 lát)', 'sashimi-ca-ngu-dai-duong', 'Cá ngừ đại dương đỏ au màu ngọc bích, thịt săn chắc ngọt thơm giàu đạm omega-3, chấm mù tạt wasabi cay nồng bốc mũi.', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 135000.00, 109000.00, 1, 0, 20),
(104, 'prod-104', 6, 9, 'Combo Sushi Tổng Hợp Tokyo Thượng Hạng (12 miếng)', 'combo-sushi-tokyo-12-mieng', 'Set sushi đầy đặn gồm: Nigiri cá hồi, Nigiri cá ngừ, Nigiri tôm sú luộc, Nigiri bạch tuộc, Gunkan trứng cá chuồn và 6 khoanh cơm cuộn bơ cá hồi.', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 210000.00, 169000.00, 1, 1, 20),
(105, 'prod-105', 6, 9, 'Cơm Cuộn California Roll Phủ Trứng Tôm (8 khoanh)', 'california-roll-trung-tom', 'Thanh cua Nhật, dưa leo giòn mát và bơ sáp cuộn ngược rắc ngập tràn trứng tôm giòn bùi, chấm sốt mayonnaise ngọt dịu.', 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 95000.00, 79000.00, 1, 1, 20),
(106, 'prod-106', 6, 9, 'Maki Tôm Chiên Giòn Tempura Sốt Mù Tạt Ngọt (8 khoanh)', 'maki-tom-chien-tempura', 'Tôm sú tươi tẩm bột tempura Nhật Bản chiên xù vàng rụm giòn tan, cuộn cơm dẻo rưới sốt teriyaki và sốt mù tạt mật ong ngọt cay nhẹ.', 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 105000.00, 85000.00, 1, 0, 20),
(107, 'prod-107', 6, 9, 'Nigiri Bò Wagyu Khè Lửa Sốt Teriyaki (4 miếng)', 'nigiri-bo-wagyu-khe-lua', 'Thịt bò Wagyu vân mỡ cẩm thạch khè lửa tái chín tới tỏa hương thơm bơ ngất ngây, rưới sốt teriyaki và rắc tỏi phi giòn tan.', 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 149000.00, 119000.00, 1, 1, 20),
(108, 'prod-108', 6, 9, 'Nigiri Lươn Nhật Nướng Unagi Sốt Đậm Đà (4 miếng)', 'nigiri-luon-nhat-unagi', 'Lươn Nhật rút xương nướng than hoa phết sốt Unagi Kabayaki óng ả màu hổ phách, thịt lươn béo mềm tan biến trong miệng.', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 125000.00, 99000.00, 1, 0, 20),
(109, 'prod-109', 6, 9, 'Gunkan Trứng Cá Hồi Đỏ Ikura Cao Cấp (2 miếng)', 'gunkan-trung-ca-hoi-ikura', 'Thuyền rong biển ôm trọn những viên trứng cá hồi Na Uy to tròn óng ánh như hạt ngọc ruby, vỡ tung vị mặn ngọt tươi nguyên của biển cả.', 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 0, 20),
(110, 'prod-110', 6, 9, 'Cơm Cuộn Da Cá Hồi Chiên Giòn Sốt Spicy Mayo (8 khoanh)', 'cuon-da-ca-hoi-chien-gion', 'Da cá hồi tẩm muối tiêu chiên giòn tan rụm cuộn cùng rau xà lách tươi và sốt cay béo, trải nghiệm giòn rụm khó quên.', 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 89000.00, 75000.00, 1, 0, 20),
(111, 'prod-111', 6, 9, 'Mì Udon Xào Hải Sản Nhật Bản Sốt Dầu Hào Thơm Nức', 'mi-udon-xao-hai-san', 'Sợi mì Udon cọng tròn to trắng muốt dẻo dai xào lửa lớn cùng tôm sú, mực ống, bắp cải tím, nấm hương và rắc cá ngừ bào Katsuobushi.', 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 105000.00, 85000.00, 1, 1, 20),
(112, 'prod-112', 6, 9, 'Mì Ramen Xương Heo Hầm Tonkotsu Thịt Chashu', 'mi-ramen-tonkotsu-chashu', 'Nước dùng hầm từ xương ống heo 14 tiếng sánh trắng sữa béo ngậy, sợi mì ramen tươi, lát thịt Chashu cuộn nướng mềm rục và trứng ngâm tương lòng đào.', 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 20),
(113, 'prod-113', 6, 9, 'Cơm Bò Xào Hành Tây Kiểu Nhật Bản (Gyudon)', 'com-bo-gyudon-nhat-ban', 'Thịt bò Mỹ thái mỏng xào cùng hành tây ngọt thanh trong nước tương dashi và rượu mirin, phủ lên bát cơm dẻo nóng hổi kèm trứng gà trần onsen.', 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 95000.00, 79000.00, 1, 0, 20),
(114, 'prod-114', 6, 9, 'Cơm Lươn Nhật Nướng Than Hoa Sốt Teriyaki (Unadon)', 'com-luon-unadon-nhat', 'Miếng lươn Nhật lớn nướng than hoa đượm khói, tẩm đẫm nước sốt lươn mặn ngọt óng ánh đặt trên bát cơm hạt ngọc Nhật dẻo bùi.', 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 175000.00, 139000.00, 1, 1, 20),
(115, 'prod-115', 6, 9, 'Salad Rong Biển Trứng Tôm Sốt Mè Rang Nhật Bản', 'salad-rong-bien-trung-tom', 'Rong biển xanh tươi tẩm gia vị mè cay nhẹ, dưa leo xắt sợi, trứng tôm giòn sần sật trộn cùng nước sốt mè rang Kewpie thơm béo.', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 59000.00, 49000.00, 1, 0, 20),
(116, 'prod-116', 6, 9, 'Đậu Nành Nhật Bản Edamame Luộc Muối Hạt', 'dau-nanh-nhat-edamame', 'Những quả đậu nành xanh mướt luộc vừa chín tới rắc muối biển hạt tinh khiết, bóp nhẹ vỏ thưởng thức hạt đậu bùi béo ngọt lành.', 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 42000.00, 35000.00, 1, 0, 20),
(117, 'prod-117', 6, 9, 'Bánh Xèo Nhật Bản Okonomiyaki Phủ Cá Ngừ Bào Nhảy Múa', 'banh-xeo-okonomiyaki-nhat', 'Bánh xèo bột mì bắp cải ngọt giòn nhân tôm thịt, rưới sốt Otafuku nâu bóng, sốt mayonnaise zic-zac và phủ đầy lớp cá ngừ bào bốc khói nhảy múa.', 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80', 72000.00, 59000.00, 1, 1, 20),
(118, 'prod-118', 6, 9, 'Chén Canh Rong Biển Đậu Hũ Non Súp Miso Nóng Hổi', 'sup-miso-rong-bien', 'Tương đậu nành Miso trắng lên men tự nhiên nấu cùng rong biển wakame mướt mát và đậu hũ non mềm tan xắt hạt lựu.', 'https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format&fit=crop&q=80', 25000.00, 20000.00, 1, 0, 20),
(119, 'prod-119', 6, 9, 'Trà Xanh Gạo Lứt Rang Genmaicha Nhật Bản (Ấm / Ly)', 'tra-gao-lut-genmaicha', 'Lá trà xanh búp non sao cùng gạo lứt rang nở hoa thơm nức mũi, vị thanh tao hỗ trợ tiêu hóa sau bữa ăn sushi tuyệt hảo.', 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 0, 20),
(120, 'prod-120', 6, 9, 'Kem Trà Xanh Matcha Nhật Bản Đậu Đỏ Dẻo (1 viên)', 'kem-matcha-dau-do-nhat', 'Viên kem matcha béo mịn đượm vị đắng thanh quý phái từ trà xanh Kyoto, điểm xuyết hạt đậu đỏ ngọt bùi dẻo thơm.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 38000.00, 29000.00, 1, 0, 20),
(121, 'prod-121', 7, 3, 'Burger Gà Giòn Sốt Bơ Tỏi Đặc Biệt (Crispy Chicken)', 'burger-ga-gion-sot-bo', 'Đùi gà phi lê tẩm bột chiên vàng rụm lớp vỏ xù, đẫm sốt bơ tỏi thơm ngậy đặc chế, rau xà lách tươi giòn kẹp bánh mì nướng nóng.', 'https://images.unsplash.com/photo-1525059696034-4967a8e1dca2?w=600&auto=format&fit=crop&q=80', 49000.00, 39000.00, 1, 1, 10),
(122, 'prod-122', 7, 3, 'Burger Gà Giòn Cay Sốt Tabasco Mayonnaise', 'burger-ga-cay-tabasco', 'Thịt gà phi lê chiên giòn cay rực lửa, hòa quyện sốt béo mayo và ớt đỏ nồng nàn dành riêng cho người mê ăn cay.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 10),
(123, 'prod-123', 7, 3, 'Burger Gà Nướng Phô Mai Tan Chảy Kéo Sợi', 'burger-ga-nuong-pho-mai', 'Thịt gà nướng mật ong mềm mọng nước, kẹp 2 lát phô mai Mozzarella chảy tràn thơm béo hấp dẫn.', 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 59000.00, 48000.00, 1, 1, 10),
(124, 'prod-124', 7, 3, 'Burger Gà Không Xương Sốt Mật Ong Mù Tạt Vàng (Honey Mustard)', 'burger-ga-honey-mustard', 'Miếng phi lê gà chiên vàng ươm tắm sốt mù tạt mật ong thơm nồng chua ngọt kiểu Mỹ cực bắt vị.', 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 0, 10),
(125, 'prod-125', 7, 3, 'Burger Gà 2 Tầng Siêu Khổng Lồ (Double Crispy Monster)', 'burger-ga-2-tang-khong-lo', '2 miếng phi lê gà chiên xù khổng lồ kẹp cùng 2 lát phô mai và sốt bơ tỏi, no nê bùng nổ năng lượng.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 99000.00, 79000.00, 1, 1, 10),
(126, 'prod-126', 7, 3, 'Burger Gà Teriyaki Kiểu Nhật Rong Biển Sấy', 'burger-ga-teriyaki-rong-bien', 'Thịt gà áp chảo sốt teriyaki mặn ngọt óng ánh, phủ lớp rong biển sợi giòn tan và hạt mè trắng bùi thơm.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 59000.00, 49000.00, 1, 0, 10),
(127, 'prod-127', 7, 3, '2 Miếng Gà Rán Giòn Cay Truyền Thống Vàng Óng', '2-mieng-ga-ran-gion-cay', '2 miếng gà lớn (1 đùi + 1 má đùi) tẩm ướp 11 loại gia vị bí truyền, chiên áp suất vàng rộm giòn tan rụm từng thớ thịt mọng nước.', 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 78000.00, 65000.00, 1, 1, 10),
(128, 'prod-128', 7, 3, '3 Miếng Đùi Gà Rán Giòn Rụm Lớp Vỏ Vàng Ruộm', '3-mieng-dui-ga-ran', 'Set 3 chiếc đùi tỏi gà to mập chiên giòn nóng hổi, vỏ ngoài giòn rụm bên trong thịt mềm thơm ngọt tự nhiên.', 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 115000.00, 95000.00, 1, 1, 10),
(129, 'prod-129', 7, 3, 'Cánh Gà Lắc Phô Mai Béo Bùi Vỏ Giòn (Hộp 4 cánh)', 'canh-ga-lac-pho-mai', 'Cánh gà chiên giòn tan lắc đều bột phô mai cam thơm bùi ngọt mặn kích thích vị giác cực kỳ.', 'https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=600&auto=format&fit=crop&q=80', 82000.00, 68000.00, 1, 0, 10),
(130, 'prod-130', 7, 3, 'Cánh Gà Sốt Bơ Tỏi Cay Kiểu Hàn Quốc (4 cánh)', 'canh-ga-sot-bo-toi-cay', 'Cánh gà chiên giòn đảo trên chảo ngập sốt bơ tỏi ớt gochujang cay ngọt đậm đà rắc vừng rang thơm lừng.', 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 88000.00, 72000.00, 1, 1, 10),
(131, 'prod-131', 7, 3, 'Gà Popcorn Viên Chiên Giòn Lắc Rong Biển (Hộp Lớn)', 'ga-popcorn-lac-rong-bien', 'Từng viên ức gà không xương tẩm bột bắp chiên giòn rụm vừa miệng ăn, lắc cùng bột rong biển sấy thơm nức nở.', 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 10),
(132, 'prod-132', 7, 3, 'Gà Miếng Không Xương Chiên Giòn Nuggets (6 miếng)', 'ga-mieng-nuggets-6-mieng', 'Thịt gà xay nhuyễn tẩm gia vị truyền thống chiên giòn vàng đều, món ăn khoái khẩu của cả người lớn và trẻ nhỏ.', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 50000.00, 42000.00, 1, 0, 10),
(133, 'prod-133', 7, 3, 'Combo 1 Người: 1 Burger Gà Giòn + 1 Miếng Gà + 1 Nước', 'combo-1-nguoi-burger-ga', 'Bữa ăn đầy đủ no bụng gồm 1 Burger gà sốt bơ, 1 miếng gà rán giòn cay và 1 ly Pepsi tươi có ga mát lạnh.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 105000.00, 85000.00, 1, 1, 10),
(134, 'prod-134', 7, 3, 'Combo Gia Đình Gà Rán (4 Miếng Gà + 2 Burger + Khoai + 2 Nước)', 'combo-gia-dinh-ga-ran', 'Đại tiệc gà rán thịnh soạn cho cả nhà thưởng thức sum vầy cuối tuần với ngập tràn món ngon giòn rụm.', 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 260000.00, 199000.00, 1, 1, 10),
(135, 'prod-135', 7, 3, 'Khoai Tây Chiên Lắc Phô Mai Cay Kiểu Pháp', 'khoai-tay-lac-pho-mai-cay', 'Khoai tây cọng chiên vàng giòn rụm lắc ngập bột phô mai pha ớt bột Hàn Quốc cay the the béo bùi.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 42000.00, 35000.00, 1, 0, 10),
(136, 'prod-136', 7, 3, 'Bắp Cải Trộn Salad Coleslaw Thanh Mát Sốt Mayo', 'salad-bap-cai-coleslaw', 'Bắp cải trắng, bắp cải tím và cà rốt bào sợi giòn sần sật trộn sốt mayonnaise chua béo thanh mát giải ngấy món gà rán.', 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 0, 10),
(137, 'prod-137', 7, 3, 'Khoai Tây Nghiền Sốt Nâu Gravy Béo Ngậy Đậm Đà', 'khoai-tay-nghien-sot-gravy', 'Khoai tây hấp chín nghiền mịn như kem cùng bơ lạt và sữa tươi, rưới nước sốt nâu gravy hầm từ thịt gà đậm đà thơm ấm.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 10),
(138, 'prod-138', 7, 3, 'Bánh Mì Cuộn Gà Giòn Sốt Caesar Phô Mai (Chicken Twister)', 'banh-mi-cuon-ga-gion-twister', 'Vỏ bánh tortilla nướng mềm cuộn chặt miếng gà giòn rụm không xương, rau xà lách, cà chua bi và sốt Caesar phô mai thơm phức.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 59000.00, 49000.00, 1, 1, 10),
(139, 'prod-139', 7, 3, 'Trà Đào Sả Tắc Mát Lạnh Sảng Khoái (Ly Lớn)', 'tra-dao-sa-tac-mat-lanh', 'Trà đen thơm lừng hương sả đập dập, chua thanh của quất tắc và ngọt ngào của đào ngâm giòn sần sật xua tan cơn khát tức thì.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 1, 10),
(140, 'prod-140', 7, 3, 'Trà Chanh Giã Tay Quảng Đông Thơm Nồng Mùi Tinh Dầu', 'tra-chanh-gia-tay-quang-dong', 'Chanh nước hoa Quảng Đông giã tay nát bung tinh dầu thơm ngát kết hợp cốt trà Ô long đậm đặc và đá tuyết mát lạnh sảng khoái.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 0, 10),
(141, 'prod-141', 8, 6, 'Bánh Croissant Bơ Pháp Nướng Giòn Thơm Bơ Elle & Vire', 'croissant-bo-phap', 'Chiếc bánh sừng bò chuẩn kỹ thuật Pháp ngàn lớp vỏ mỏng tang giòn rụm bên ngoài, ruột xốp tổ ong mềm mướt thơm ngát bơ tươi hảo hạng.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 1, 15),
(142, 'prod-142', 8, 6, 'Bánh Pain Au Chocolat Nhân Socola Bỉ Đắng Ngọt', 'pain-au-chocolat-bi', 'Vỏ bột ngàn lớp nướng nở phồng ôm trọn 2 thanh socola đen Bỉ 70% cacao tan chảy ấm áp quyến rũ ngất ngây.', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 1, 15),
(143, 'prod-143', 8, 6, 'Bánh Sừng Bò Nhân Trứng Muối Tan Chảy Hoàng Kim', 'croissant-trung-muoi-tan-chay', 'Dòng sốt kem trứng muối lava vàng óng tan chảy bùi ngậy tuôn trào khi bẻ đôi chiếc bánh sừng bò giòn tan nóng hổi.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 15),
(144, 'prod-144', 8, 6, 'Bánh Mousse Dâu Tây Tươi Kem Mascarpone Pháp', 'mousse-dau-tay-kem-phap', 'Lớp kem mousse mịn màng màu hồng pastel mát lạnh tan ngay trong miệng, vị chua ngọt thanh tao từ dâu tây tươi và phô mai Mascarpone.', 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 68000.00, 55000.00, 1, 0, 15),
(145, 'prod-145', 8, 6, 'Bánh Tiramisu Ý Truyền Thống Cacao Cà Phê Rượu Rum', 'tiramisu-y-truyen-thong', 'Bánh sâm-panh nhúng đẫm cà phê espresso Buôn Ma Thuột hòa chút rượu rum thơm nồng, xen kẽ kem phô mai béo ngậy phủ bột cacao đắng mịn.', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80', 65000.00, 52000.00, 1, 1, 15),
(146, 'prod-146', 8, 6, 'Bánh Phô Mai Nướng Cháy Basque Burnt Cheesecake', 'basque-burnt-cheesecake', 'Bánh phô mai nướng nhiệt độ cao tạo lớp vỏ ngoài cháy xém màu caramel nồng nàn, bên trong lõi kem phô mai sánh dẻo béo ngậy tan chảy.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 70000.00, 58000.00, 1, 1, 15),
(147, 'prod-147', 8, 6, 'Bánh Su Kem Vani Vỏ Giòn Craquelin (Hộp 4 chiếc)', 'su-kem-vani-vo-gion', 'Vỏ bánh nướng phủ lớp đường giòn rụm Craquelin nứt hoa văn đẹp mắt, bơm ngập nhân kem sữa tươi vani ngọt mát béo ngậy.', 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=600&auto=format&fit=crop&q=80', 45000.00, 36000.00, 1, 0, 15),
(148, 'prod-148', 8, 6, 'Bánh Tart Trứng Bồ Đào Nha Nướng Cháy Xém (2 chiếc)', 'tart-trung-bo-dao-nha', 'Vỏ tart xếp lớp giòn rụm ngàn cánh ôm trọn phần kem trứng sữa nướng phồng xém mặt thơm nức mùi caramen béo ngậy.', 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 1, 15),
(149, 'prod-149', 8, 6, 'Bánh Donut Phủ Socola Bỉ Rắc Hạt Hạnh Nhân Nướng', 'donut-socola-hanh-nhan', 'Chiếc bánh donut vòng tròn mềm xốp thơm bơ chiên vàng đều, nhúng lớp socola Bỉ bóng bẩy và rắc hạnh nhân lát nướng giòn.', 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 15),
(150, 'prod-150', 8, 6, 'Bánh Mì Bơ Tỏi Phô Mai Kiểu Hàn Quốc (Garlic Cheese Bread)', 'banh-mi-bo-toi-han-quoc', 'Bánh mì tròn bổ múi khế bơm ngập kem phô mai béo ngậy, nhúng đẫm sốt bơ tỏi thơm ngát nướng xém vàng giòn tan vỏ.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 15),
(151, 'prod-151', 8, 6, 'Bánh Red Velvet Phủ Lớp Kem Phô Mai Cream Cheese Mịn', 'red-velvet-cream-cheese', 'Cốt bánh nhung đỏ ẩm mượt mềm tan xen kẽ từng lớp kem phô mai chua béo đặc trưng quyến rũ sang trọng.', 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=600&auto=format&fit=crop&q=80', 65000.00, 52000.00, 1, 0, 15),
(152, 'prod-152', 8, 6, 'Bánh Cupcake Socola Đậm Đà Rắc Hạt Dẻ Cười Pistachio', 'cupcake-socola-pistachio', 'Chiếc cupcake nhỏ xinh xắn vị socola đậm đà, trên chóp bắt bông kem bơ béo ngậy và hạt dẻ cười nghiền thô bùi bùi.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 0, 15),
(153, 'prod-153', 8, 6, 'Bánh Quy Bơ Hạnh Nhân Pháp Hộp Thiếc (100g)', 'banh-quy-bo-hanh-nhan', 'Bánh quy bơ giòn tan thơm lừng mùi bơ Pháp cao cấp kết hợp lát hạnh nhân mỏng dính, nhâm nhi cùng tách trà chiều cực phẩm.', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80', 80000.00, 65000.00, 1, 0, 15),
(154, 'prod-154', 8, 6, 'Bánh Macaron Pháp 5 Vị Sắc Màu (Hộp 5 chiếc)', 'macaron-phap-5-vi', 'Những chiếc macaron chân váy nở xòe xinh xắn đủ 5 vị: Dâu tây, Matcha, Socola đen, Chanh vàng và Vani Madagascar.', 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80', 95000.00, 75000.00, 1, 1, 15),
(155, 'prod-155', 8, 6, 'Bánh Bông Lan Trứng Muối Chà Bông Sốt Phô Mai Kim Sa', 'bong-lan-trung-muoi-cha-bong', 'Cốt bánh bông lan mềm xốp ẩm mịn, ngập tràn chà bông thịt heo tơi xốp, trứng muối bùi bùi và sốt phô mai kim sa chảy thơm nức mũi.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 60000.00, 49000.00, 1, 1, 15),
(156, 'prod-156', 8, 6, 'Bánh Danish Đào Tươi Kem Custard Nướng Phồng', 'danish-dao-tuoi-custard', 'Bột bánh đan lớp nở giòn rụm, ở giữa là lớp kem custard vani vàng ươm và nửa quả đào ngâm giòn ngọt thanh mọng.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 52000.00, 42000.00, 1, 0, 15),
(157, 'prod-157', 8, 6, 'Bánh Baguette Pháp Truyền Thống Nướng Lò Đá (1 ổ lớn)', 'baguette-phap-truyen-thong', 'Ổ bánh mì dài kiểu Pháp da giòn rụm nứt hoa văn chân chim, ruột xốp dai thơm nồng men chua tự nhiên.', 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 28000.00, 22000.00, 1, 0, 15),
(158, 'prod-158', 8, 6, 'Trà Hoa Cúc Mật Ong Hoa Cà Phê Ấm Áp', 'tra-hoa-cuc-mat-ong-am', 'Những đóa hoa cúc khô hãm nước sôi thơm hương thảo mộc dịu nhẹ, hòa mật ong rừng ngọt thanh giúp an thần thư thái khi ăn bánh ngọt.', 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 15),
(159, 'prod-159', 8, 6, 'Cà Phê Latte Kem Béo Hạt Dẻ Nướng Nóng Hổi', 'latte-hat-de-nuong', 'Espresso thơm nồng đánh cùng sữa tươi nóng tạo bọt nghệ thuật đẹp mắt, hòa quyện si-rô hạt dẻ nướng thơm ấm ngọt ngào.', 'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80', 52000.00, 42000.00, 1, 1, 15),
(160, 'prod-160', 8, 6, 'Trà Bá Tước Earl Grey Hương Cam Bergamot Sang Trọng', 'tra-earl-grey-nong', 'Tách trà quý tộc Anh Quốc tỏa hương thơm cam Bergamot tươi mát, hậu vị chát ngọt êm đềm kết hợp hoàn hảo cùng bánh croissant.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 0, 15),
(161, 'prod-161', 9, 6, 'Bánh Pancake Mật Ong Rừng & Dâu Tây Tươi (Set 3 chiếc)', 'pancake-mat-ong-dau-tay', 'Bánh kếp nướng vàng xốp mềm mịn thơm ngậy mùi bơ sữa tươi, rưới mật ong rừng hoa cà phê Đắk Lắk và điểm xuyết dâu tây tươi mọng.', 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80', 49000.00, 39000.00, 1, 1, 15),
(162, 'prod-162', 9, 6, 'Bánh Mì Chảo Thập Cẩm Đặc Biệt (Pate, Xíu Mại, Bò, Trứng)', 'banh-mi-chao-thap-cam', 'Chảo gang nóng sôi xèo xèo gồm pate gan béo ngậy, thịt bò mềm ngọt xào tỏi, xíu mại sốt cà, trứng ốp la lòng đào và xúc xích nướng.', '/assets/images/foods/banh-mi-chao.jpg', 68000.00, 55000.00, 1, 1, 15),
(163, 'prod-163', 9, 6, 'Bánh Mì Xíu Mại Trứng Muối Sốt Cà Cay Nóng Hổi', 'banh-mi-xiu-mai-cay-nong', 'Tô xíu mại nước ngọt thơm gồm 2 viên xíu mại nhân trứng muối mềm mịn, da heo giòn sần sật chan sốt ớt cay ấm chấm bánh mì giòn tan.', '/assets/images/foods/banh-mi-chao.jpg', 48000.00, 38000.00, 1, 1, 15),
(164, 'prod-164', 9, 6, 'Bánh Mì Kẹp Thịt Nguội Bơ Pháp & Dưa Leo Đồ Chua', 'banh-mi-thit-nguoi-bo-phap', 'Bánh mì vỏ giòn tan quét pate gan thơm nức, bơ thơm béo, kẹp thịt nguội jambon, chả lụa quết tiêu, dưa leo và ngò rí đậm đà.', 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 0, 15),
(165, 'prod-165', 9, 6, 'Bánh Mì Heo Quay Giòn Bì Rưới Nước Sốt Đậm Đà', 'banh-mi-heo-quay-gion-bi', 'Thịt heo quay da nổ giòn tan rụm từng tiếng, nạc mỡ đan xen mềm ngọt chan nước sốt heo quay bí truyền đậm đà thơm ấm.', '/assets/images/foods/heo-quay.jpg', 45000.00, 35000.00, 1, 1, 15),
(166, 'prod-166', 9, 6, 'Sandwich Bơ Quả & Trứng Gà Lòng Đào Áp Chảo (Avocado Toast)', 'sandwich-bo-trung-long-dao', 'Bánh mì gối ngũ cốc nướng giòn phết bơ sáp Đắk Lắk nghiền béo bùi, đặt quả trứng gà ốp la lòng đào rắc tiêu đen và ớt bột paprika.', 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 0, 15),
(167, 'prod-167', 9, 6, 'Xôi Xéo Gà Xé Nấm Hương Rưới Mỡ Hành Thơm Lừng', 'xoi-xeo-ga-xe-nam-huong', 'Hạt nếp nương đồ vàng óng dẻo quánh, đỗ xanh thái mỏng bùi béo, gà ta xé phay xào nấm hương thơm lừng và rưới mỡ hành hành phi giòn tan.', '/assets/images/foods/xoi-xeo.jpg', 55000.00, 45000.00, 1, 1, 15),
(168, 'prod-168', 9, 6, 'Xôi Mặn Thập Cẩm Chà Bông Lạp Xưởng Trứng Cút', 'xoi-man-thap-cam', 'Hộp xôi nóng hổi ngập tràn topping: lạp xưởng nướng thái lát, chà bông heo tơi xốp, trứng cút chiên giòn, mỡ hành và sốt tương ớt đậm đà.', '/assets/images/foods/xoi-thap-cam.jpg', 42000.00, 35000.00, 1, 0, 15),
(169, 'prod-169', 9, 6, 'Bún Bò Huế Chả Cua Giò Heo Cay Nồng Thơm Mắm Ruốc', 'bun-bo-hue-cha-cua-gio-heo', 'Nước dùng bún bò hầm xương ống dậy mùi mắm ruốc Huế và sả phi ớt bột cay nồng, khoanh giò heo ninh mềm dẻo và chả cua biển béo bùi.', '/assets/images/foods/bun-bo-hue.jpg', 68000.00, 55000.00, 1, 1, 15),
(170, 'prod-170', 9, 6, 'Hủ Tiếu Nam Vang Tôm Mực Thịt Nạc Thơm Mỡ Tỏi Phi', 'hu-tieu-nam-vang-tom-muc', 'Sợi hủ tiếu dai mềm trụng nóng, tôm sú bóc vỏ ngọt lịm, mực tươi giòn sần sật, thịt nạc xắt mỏng, trứng cút và hành phi thơm lừng bát canh xương.', '/assets/images/foods/bun-thit-nuong.jpg', 65000.00, 55000.00, 1, 1, 15),
(171, 'prod-171', 9, 6, 'Bánh Canh Cua Đồng Chả Cá Thác Lác Dai Ngon', 'banh-canh-cua-dong-cha-ca', 'Sợi bánh canh bột gạo dẻo dai nấu trong nước dùng riêu cua đồng thơm ngọt tự nhiên, chả cá thác lác Đắk Lắk quết dai giòn rụm.', '/assets/images/foods/bun-rieu.jpg', 58000.00, 48000.00, 1, 0, 15),
(172, 'prod-172', 9, 6, 'Cháo Sườn Non Hạt Sen Nấu Nấm Rơm Bổ Dưỡng', 'chao-suon-non-hat-sen', 'Gạo tẻ ninh nhừ sánh mịn như lụa cùng sườn non sụn mềm rục, hạt sen bùi béo và nấm rơm ngọt nước, rắc hành hoa tiêu đen ấm bụng.', '/assets/images/foods/pho-bo.jpg', 55000.00, 45000.00, 1, 0, 15),
(173, 'prod-173', 9, 6, 'Cháo Gà Ta Xé Phay Tiêu Sọ Cháo Nhuyễn Mịn', 'chao-ga-ta-xe-phay', 'Cháo nấu từ nước luộc gà ngọt lịm thơm béo, gà ta xé phay rắc lá tía tô, hành hoa và hạt tiêu sọ Đắk Lắk cay ấm giải cảm tuyệt vời.', '/assets/images/foods/pho-ga.jpg', 55000.00, 45000.00, 1, 0, 15),
(174, 'prod-174', 9, 6, 'Bánh Cuốn Nóng Tráng Tay Nhân Thịt Mộc Nhĩ Chả Quế', 'banh-cuon-nong-trang-tay', 'Từng lớp bánh mỏng tang tráng tay trên nồi hơi nước nóng hổi, cuộn thịt nạc bằm mộc nhĩ nấm hương giòn sần sật, rắc hành phi giòn và chả quế.', '/assets/images/foods/banh-cuon.jpg', 48000.00, 38000.00, 1, 1, 15),
(175, 'prod-175', 9, 6, 'Nui Nấu Sườn Heo & Rau Củ Tươi Ngọt Nước Súp', 'nui-nau-suon-heo-rau-cu', 'Nui vàng luộc mềm nấu cùng sườn heo ninh nhừ, cà rốt, củ cải trắng và bắp ngọt, nước súp trong veo ngọt thanh tự nhiên dễ ăn buổi sáng.', '/assets/images/foods/canh-kho-qua.jpg', 55000.00, 45000.00, 1, 0, 15),
(176, 'prod-176', 9, 6, 'Sữa Bắp Nếp Tươi Nấu Trong Ngày Thơm Béo Mịn', 'sua-bap-nep-tuoi', 'Bắp nếp ngọt thu hoạch tại vườn xay nấu tươi cùng sữa tươi thanh trùng, sánh đặc vàng óng thơm nức nở vị ngô nếp.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 22000.00, 18000.00, 1, 0, 15),
(177, 'prod-177', 9, 6, 'Sữa Hạt Sen Đậu Đỏ Dinh Dưỡng Bổ Huyết', 'sua-hat-sen-dau-do', 'Hạt sen tươi xay cùng đậu đỏ ninh mềm và đường phèn thanh ngọt, thức uống ấm áp giàu dinh dưỡng cho bữa sáng tràn đầy năng lượng.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 28000.00, 22000.00, 1, 0, 15),
(178, 'prod-178', 9, 6, 'Cà Phê Phin Sữa Đá Đậm Chất Buôn Ma Thuột', 'ca-phe-phin-sua-da-bmt', 'Hạt cà phê Robusta chín đỏ hái từ nông trường Buôn Ma Thuột rang mộc xay mịn nhỏ giọt qua phin nhôm truyền thống, hòa sữa đặc béo ngọt sánh đậm.', '/assets/images/foods/ca-phe-sua-da.jpg', 30000.00, 25000.00, 1, 1, 15),
(179, 'prod-179', 9, 6, 'Trà Gừng Mật Ong Ấm Bụng Khởi Đầu Ngày Mới', 'tra-gung-mat-ong-am-bung', 'Gừng sẻ tươi giã dập nấu sôi hòa mật ong hoa cà phê nguyên chất, thức uống thanh lọc cơ thể ấm nồng đánh thức giác quan.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 25000.00, 20000.00, 1, 0, 15),
(180, 'prod-180', 9, 6, 'Nước Cam Tươi Vắt Ép Mật Ong Nguyên Chất (Ly Lớn)', 'nuoc-cam-tuoi-vat-mat-ong', 'Cam sành miền Tây mọng nước vắt tươi 100% không pha nước lọc, ngọt thanh nhờ mật ong hoa rừng bổ sung vitamin C dồi dào.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 15),
(181, 'prod-181', 10, 3, 'Gà Rán Giòn Sốt Cay Hàn Quốc (Hot Spicy Crispy Chicken)', 'ga-ran-gion-sot-cay', 'Gà tươi rán giòn rụm lớp vỏ ngoài, phủ đẫm sốt cay nồng đặc chế kiểu Hàn Quốc, thịt mềm ngọt mọng nước bên trong.', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 92000.00, 75000.00, 1, 1, 15),
(182, 'prod-182', 10, 3, 'Gà Rán Sốt Đậu Tỏi Nồng Đượm (Soy Garlic Chicken)', 'ga-ran-sot-dau-toi', 'Miếng gà chiên giòn áo lớp sốt đậu tương lên men và tỏi phi thơm nồng, vị ngọt mặn hài hòa cực bắt vị.', 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 95000.00, 78000.00, 1, 1, 15),
(183, 'prod-183', 10, 3, 'Gà Rán Phủ Phô Mai Tuyết Trắng Béo Ngậy (Snow Cheese Chicken)', 'ga-ran-pho-mai-tuyet', 'Lớp bột phô mai tuyết ngọt bùi thơm lừng phủ kín miếng gà rán vàng rộm vừa vớt từ chảo dầu nóng hổi.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 99000.00, 82000.00, 1, 1, 15),
(184, 'prod-184', 10, 3, 'Burger Bò Nướng Sốt Tiêu Đen Phú Quốc Phô Mai', 'burger-bo-sot-tieu-den', 'Bò nướng than hoa quyện sốt tiêu đen cay ấm nồng nàn, phô mai Cheddar béo ngậy và hành tây xào caramen.', 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 88000.00, 72000.00, 1, 0, 15),
(185, 'prod-185', 10, 3, 'Burger Bò Nướng BBQ Thịt Ba Chỉ Xông Khói (Bacon King)', 'burger-bo-bacon-king', 'Sự kết hợp giữa 2 dải bacon giòn rụm, miếng bò nướng lửa thơm khói và lớp phô mai Mỹ chảy ngập tràn.', 'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 15),
(186, 'prod-186', 10, 3, 'Burger Phi Lê Gà Giòn Sốt Cay Phô Mai Chảy', 'burger-phi-le-ga-cay-pho-mai', 'Phi lê gà tẩm bột chiên xù giòn tan rụm, rưới sốt ớt cay đỏ au và lát phô mai Mozzarella chảy dẻo.', 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 79000.00, 65000.00, 1, 0, 15),
(187, 'prod-187', 10, 3, 'Cơm Gà Rán Giòn Sốt Cà Ri Nhật Bản Kèm Canh Rong Biển', 'com-ga-ran-ca-ri-nhat', 'Miếng gà rán giòn rụm thái lát đặt trên dĩa cơm trắng nóng dẻo, rưới nước sốt cà ri Nhật củ cải cà rốt đậm đà thơm béo.', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 82000.00, 68000.00, 1, 1, 15),
(188, 'prod-188', 10, 3, 'Cơm Gà Xào Nấm Sốt Teriyaki Đậm Đà Kiểu Nhật', 'com-ga-xao-nam-teriyaki', 'Thịt gà đùi xào săn cùng nấm hương tươi và sốt teriyaki bóng bẩy, rắc mè trắng rang bùi thơm đưa cơm.', 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 75000.00, 62000.00, 1, 0, 15),
(189, 'prod-189', 10, 3, 'Đùi Gà Rán Vàng Giòn Rụm Lớp Vỏ Xù (1 chiếc đùi lớn)', 'dui-ga-ran-gion-rum-lon', 'Chiếc đùi gà tỏi cỡ bự chiên xù giòn tan rụm, thịt bên trong trắng mềm mọng nước không khô xác.', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 45000.00, 36000.00, 1, 1, 15),
(190, 'prod-190', 10, 3, 'Cánh Gà Chiên Bơ Tỏi Thơm Nức Mũi (Hộp 3 cánh)', 'canh-ga-chien-bo-toi-3-canh', 'Cánh gà chiên giòn xóc bơ tỏi tươi phi vàng, thơm nức mũi chấm sốt tương ớt ngọt cực bắt mồi.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 72000.00, 59000.00, 1, 0, 15),
(191, 'prod-191', 10, 3, 'Khoai Lang Kén Lắc Phô Mai Béo Bùi Giòn Vỏ', 'khoai-lang-ken-lac-pho-mai', 'Khoai lang kén vàng ươm thơm béo vị nước cốt dừa nướng giòn rụm bên ngoài dẻo ngọt bên trong, lắc bột phô mai bùi thơm.', 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 0, 15),
(192, 'prod-192', 10, 3, 'Khoai Tây Lắc Phô Mai & Bột Rong Biển (Hộp Lớn)', 'khoai-tay-lac-rong-bien-pho-mai', 'Khoai tây chiên giòn xốc trong túi cùng bột phô mai cam béo ngậy và rong biển nướng thơm phức.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 48000.00, 38000.00, 1, 1, 15),
(193, 'prod-193', 10, 3, 'Phô Mai Viên Chiên Giòn Nhân Mozzarella Kéo Sợi (5 viên)', 'pho-mai-vien-chien-gion', 'Viên phô mai tròn vo chiên phồng vàng rụm, cắn vào dòng phô mai dẻo quánh kéo sợi dài vô tận béo ngậy ngất ngây.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 0, 15),
(194, 'prod-194', 10, 3, 'Bánh Tart Phô Mai Nướng Xém Vàng (Cheese Tart)', 'banh-tart-pho-mai-nuong', 'Vỏ tart bơ giòn rụm ôm nhân kem phô mai chua béo nướng vàng xém, món tráng miệng ngọt dịu sau khi ăn gà rán.', 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 38000.00, 30000.00, 1, 0, 15),
(195, 'prod-195', 10, 3, 'Mì Ý Gà Rán Giòn Sốt Cà Chua Phô Mai Đút Lò', 'mi-y-ga-ran-sot-ca-chua', 'Sợi mì Ý sốt cà chua đút lò phủ phô mai, ăn kèm miếng gà rán giòn rụm nóng hổi cắt lát.', 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80', 89000.00, 75000.00, 1, 1, 15),
(196, 'prod-196', 10, 3, 'Combo Gà Rán 2 Miếng + Khoai Tây Chiên + Coca-Cola', 'combo-ga-ran-2-mieng-khoai-coca', 'Combo no nê chuẩn chỉnh: 2 miếng gà rán giòn cay, 1 gói khoai tây chiên vàng và 1 ly Coca-Cola mát lạnh sảng khoái.', 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 15),
(197, 'prod-197', 10, 3, 'Combo Burger Bò Tiêu Đen + 1 Miếng Gà + 1 Ly Nước', 'combo-burger-bo-tieu-1-ga-nuoc', 'Set ăn thịnh soạn kết hợp cả burger bò nướng sốt tiêu và gà rán giòn rụm kèm nước giải khát.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 135000.00, 105000.00, 1, 0, 15),
(198, 'prod-198', 10, 3, 'Salad Gà Rán Sốt Mè Rang Nhật Bản Thanh Mát', 'salad-ga-ran-sot-me-rang', 'Xà lách tươi xanh, dưa leo, cà chua bi giòn ngọt trộn cùng những lát gà rán cắt hạt lựu và sốt mè rang béo bùi.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 60000.00, 49000.00, 1, 0, 15),
(199, 'prod-199', 10, 3, 'Trà Chanh Dây Hạt Chia Mát Lạnh Giải Nhiệt', 'tra-chanh-day-hat-chia', 'Nước cốt chanh dây tươi chua thanh thơm ngát hòa quyện hạt chia nở giòn, thức uống giải ngấy số 1 khi ăn đồ chiên rán.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 0, 15),
(200, 'prod-200', 10, 3, 'Kem Tươi Vani Ốc Quế Giòn Tan (Vanilla Soft Cone)', 'kem-tuoi-vani-oc-que', 'Cây kem tươi vani xoắn ốc mềm mịn ngọt dịu mát lạnh trên chiếc bánh ốc quế nướng giòn rụm thơm bơ.', 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 20000.00, 15000.00, 1, 0, 15),
(201, 'prod-201', 11, 7, 'Salad Ức Gà Áp Chảo Rau Củ Sốt Mè Rang Nhật Bản', 'salad-uc-ga-ap-chao', 'Ức gà phi lê áp chảo tiêu đen không dầu mềm mọng, xà lách Romaine, cà chua bi, dưa leo, ngô ngọt và sốt mè rang béo bùi chuẩn eat-clean.', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 62000.00, 52000.00, 1, 1, 15),
(202, 'prod-202', 11, 7, 'Salad Bơ Sáp Đắk Lắk Trứng Gà Luộc & Hạt Điều Rang', 'salad-bo-sap-trung-luoc', 'Bơ sáp Buôn Ma Thuột béo ngậy cắt lát, quả trứng gà ta luộc lòng đào, hạt điều rang thơm giòn và sốt sữa chua chanh mật ong thanh nhẹ.', 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80', 68000.00, 55000.00, 1, 1, 15),
(203, 'prod-203', 11, 7, 'Salad Cá Hồi Na Uy Áp Chảo Sốt Chanh Dây Olive', 'salad-ca-hoi-chanh-day', 'Khúc lườn cá hồi Na Uy áp chảo xém vàng thơm béo, giàu Omega-3, hòa cùng xà lách tím, cà chua bi và sốt chanh dây dầu olive nguyên chất.', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 105000.00, 85000.00, 1, 1, 15),
(204, 'prod-204', 11, 7, 'Salad Bò Úc Nướng Thảo Mộc Sốt Dầu Giấm Balsamic Ý', 'salad-bo-uc-balsamic', 'Thịt thăn bò Úc thái lát mỏng ướp hương thảo áp chảo chín tới mọng nước, trộn rau rocket, cà chua bi và giấm đen Balsamic đậm vị Ý.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 82000.00, 68000.00, 1, 0, 15),
(205, 'prod-205', 11, 7, 'Salad Tôm Sú Nướng Bơ Tỏi & Rau Rocket Cà Chua Bi', 'salad-tom-su-nuong-rocket', 'Tôm sú tươi bóc nõn nướng bơ tỏi thơm ngào ngạt, rau mầm cải, xà lách giòn và sốt cam tươi thanh mát tràn đầy sức sống.', 'https://images.unsplash.com/photo-1559847844-5315695dadae?w=600&auto=format&fit=crop&q=80', 79000.00, 65000.00, 1, 0, 15),
(206, 'prod-206', 11, 7, 'Cơm Gạo Lứt Ức Gà Nướng Mật Ong Rừng & Bông Cải Xanh', 'com-gao-lut-uc-ga-nuong', 'Cơm gạo lứt dẻo thơm nấu chín tới giàu chất xơ, ức gà nướng mật ong ít calo và đĩa bông cải xanh cà rốt luộc ngọt thanh.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 70000.00, 58000.00, 1, 1, 15),
(207, 'prod-207', 11, 7, 'Cơm Gạo Lứt Cá Hồi Na Uy Áp Chảo Măng Tây Nướng', 'com-gao-lut-ca-hoi-mang-tay', 'Bữa ăn giàu đạm lành mạnh với cá hồi phi lê áp chảo bơ lạt, măng tây non nướng giòn ngọt và cơm gạo lứt hạt sen bùi béo.', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 15),
(208, 'prod-208', 11, 7, 'Cơm Gạo Lứt Thăn Bò Áp Chảo Rau Củ Xào Dầu Hào Nhạt', 'com-gao-lut-than-bo', 'Thăn bò mềm xào ớt chuông, hành tây và nấm đông cô với lượng muối thấp theo chế độ giảm mỡ tăng cơ lành mạnh.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 82000.00, 68000.00, 1, 0, 15),
(209, 'prod-209', 11, 7, 'Cơm Tấm Gạo Lứt Sườn Cốt Lết Ít Muối Ăn Kèm Canh Rau', 'com-tam-gao-lut-suon-nuong', 'Biến tấu cơm tấm truyền thống trên nền gạo lứt tím than, sườn cốt lết nướng than không phẩm màu và nhiều rau dưa tươi mát.', '/assets/images/foods/suon-nuong-dia.jpg', 70000.00, 58000.00, 1, 0, 15),
(210, 'prod-210', 11, 7, 'Poke Bowl Cá Hồi Tươi Rau Mầm & Hạt Quinoa Dinh Dưỡng', 'poke-bowl-ca-hoi-quinoa', 'Tô Poke chuẩn Hawaii đầy màu sắc: cá hồi sống cắt hạt lựu, quả bơ, ngô ngọt, đậu nành edamame, hạt diêm mạch Quinoa và sốt ponzu thanh mát.', 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 15),
(211, 'prod-211', 11, 7, 'Poke Bowl Đậu Hũ Non Áp Chảo & Bơ Sáp Sốt Teriyaki (Chay)', 'poke-bowl-dau-hu-chay', 'Món chay healthy thanh lọc cơ thể với đậu hũ non nướng vàng thơm, bơ sáp, củ cải đường, dưa leo và gạo lứt dẻo thơm.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 68000.00, 55000.00, 1, 0, 15),
(212, 'prod-212', 11, 7, 'Bánh Mì Cuộn Wrap Ức Gà Rau Cải Kale & Sốt Sữa Chua', 'wrap-uc-ga-cai-kale', 'Bánh cuộn Tortilla nguyên cám gói ghém ức gà nướng áp chảo, lá cải xoăn kale giòn mát và sốt sữa chua Hy Lạp nhẹ bụng.', 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 59000.00, 49000.00, 1, 0, 15),
(213, 'prod-213', 11, 7, 'Bánh Mì Cuộn Wrap Bò Úc Rau Củ & Phô Mai Ít Béo', 'wrap-bo-uc-rau-cu', 'Thịt bò Úc xắt sợi áp chảo thơm nức cuộn cùng xà lách xoăn, cà chua bi và phô mai ít béo tốt cho tim mạch.', 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 65000.00, 55000.00, 1, 0, 15),
(214, 'prod-214', 11, 7, 'Tô Canh Bí Đỏ Thịt Nạc Bằm Nấu Nấm Tươi Dinh Dưỡng', 'canh-bi-do-thit-nac-nam', 'Bí đỏ hồ lô ngọt bùi ninh nhừ cùng nạc heo bằm và nấm rơm tươi, vị ngọt thanh tự nhiên tốt cho thị lực và não bộ.', 'https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 0, 15),
(215, 'prod-215', 11, 7, 'Tô Canh Cải Xoong Nấu Tôm Tươi Ngọt Nước Tự Nhiên', 'canh-cai-xoong-tom-tuoi', 'Rau cải xoong giòn ngọt nấu cùng tôm tươi băm dối, vị ngọt lịm từ biển cả giúp giải nhiệt và bổ sung sắt dồi dào.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 15),
(216, 'prod-216', 11, 7, 'Súp Gà Rau Củ Nấm Hương Thanh Nhẹ Ít Calo', 'sup-ga-rau-cu-nam-huong', 'Súp nấu từ nước luộc gà không mỡ, thịt ức gà xé, bắp ngọt, nấm tuyết và lòng trắng trứng gà mềm mướt ấm bụng.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 15),
(217, 'prod-217', 11, 7, 'Nước Ép Cần Tây Táo Xanh Detox Tươi Mát Thải Độc', 'nuoc-ep-can-tay-tao-xanh', 'Cần Tây hữu cơ ép tươi cùng táo xanh giòn ngọt và lát gừng tươi, không thêm đường, công thức detox thanh lọc cơ thể chuẩn khoa học.', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 1, 15),
(218, 'prod-218', 11, 7, 'Nước Ép Củ Dền Cà Rốt Cam Vàng Sáng Da Đẹp Dáng', 'nuoc-ep-cu-den-ca-rot-cam', 'Màu đỏ ruby rực rỡ từ củ dền Đà Lạt, cà rốt ngọt lịm và cam vàng mọng nước, giàu chất chống oxy hóa giúp làn da rạng rỡ.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 0, 15),
(219, 'prod-219', 11, 7, 'Sinh Tố Bơ Chuối Hạt Chia Sữa Hạt Yến Mạch (Không Đường)', 'sinh-to-bo-chuoi-yen-mach', 'Bơ sáp Buôn Ma Thuột xay cùng chuối tiêu chín tự nhiên và sữa yến mạch thơm bùi, thức uống giàu năng lượng cho người tập gym.', 'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80', 52000.00, 42000.00, 1, 1, 15),
(220, 'prod-220', 11, 7, 'Sữa Chua Hy Lạp Trái Cây Tươi & Hạt Granola Nướng Giòn', 'sua-chua-hy-lap-granola', 'Sữa chua Hy Lạp lên men đặc sánh dẻo giàu protein, phủ dâu tây tươi, việt quất và hạt granola nướng mật ong giòn rụm.', 'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80', 55000.00, 45000.00, 1, 1, 15),
(221, 'prod-221', 12, 5, 'Mì Ý Spaghetti Bò Bằm Sốt Cà Chua Bolognese Truyền Thống', 'spaghetti-bo-bam-bolognese-y-jut', 'Sợi mì luộc mềm dai vừa tới ngập tràn sốt thịt bò bằm hầm cà chua bi tươi ngọt thanh, rắc phô mai Parmesan béo bùi và lá húng quế Ý.', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80', 95000.00, 79000.00, 1, 1, 20),
(222, 'prod-222', 12, 5, 'Mì Ý Hải Sản Tôm Sú Mực Ống Sốt Pesto Húng Tây Xanh', 'spaghetti-hai-san-pesto-y-jut', 'Hương vị biển cả Địa Trung Hải! Sốt pesto xanh ngát thơm nồng lá basil và hạt thông béo bùi, tôm sú giòn ngọt và mực tươi xào bơ tỏi.', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 20),
(223, 'prod-223', 12, 5, 'Mì Ý Sốt Kem Tươi Nấm & Thịt Ba Chỉ Xông Khói Carbonara', 'spaghetti-carbonara-bacon', 'Nước sốt kem tươi sánh vàng béo ngậy nấu cùng lòng đỏ trứng gà, thịt ba chỉ hun khói áp chảo giòn thơm và nấm mỡ tươi.', 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 105000.00, 85000.00, 1, 1, 20),
(224, 'prod-224', 12, 5, 'Mì Ý Cua Biển Sốt Kem Cà Chua Hồng (Pink Sauce Crab Spaghetti)', 'spaghetti-cua-bien-pink-sauce', 'Thịt cua biển tươi gỡ miếng đảo cùng sốt kem hồng sánh mịn hòa quyện giữa vị chua thanh của cà chua và vị béo của kem whipping cream.', 'https://images.unsplash.com/photo-1621996346565-e3d5d6281724?w=600&auto=format&fit=crop&q=80', 125000.00, 99000.00, 1, 1, 20),
(225, 'prod-225', 12, 5, 'Mì Ý Tôm Tươi Xào Tỏi Ớt Dầu Olive (Spaghetti Aglio e Olio)', 'spaghetti-aglio-olio-tom', 'Món mì kinh điển nước Ý mộc mạc mà quyến rũ: Sợi mì đảo cùng dầu olive extra virgin, tỏi phi vàng ruộm, ớt hiểm cay nồng và tôm tươi giòn ngọt.', 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 89000.00, 75000.00, 1, 0, 20),
(226, 'prod-226', 12, 5, 'Mì Ý Thăn Bò Nướng Sốt Tiêu Đen Cay Nhẹ', 'spaghetti-bo-sot-tieu-den', 'Từng lát thịt thăn bò mềm thơm nướng xém cạnh, rưới sốt tiêu đen hầm vang đỏ cay ấm nồng nàn trên đĩa mì Ý nóng sốt.', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80', 105000.00, 85000.00, 1, 0, 20),
(227, 'prod-227', 12, 5, 'Mì Dẹt Fettuccine Sốt Kem Nấm Truffle Đen Quý Phái', 'fettuccine-sot-nam-truffle', 'Sợi mì dẹt bản lớn dẻo dai đẫm sốt nấm Truffle đen thơm ngát đặc trưng ẩm thực quý tộc Ý, rắc nấm mỡ xào bơ tỏi.', 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 120000.00, 95000.00, 1, 1, 20),
(228, 'prod-228', 12, 5, 'Lasagna Bò Băm Đút Lò Phô Mai Kéo Sợi Béo Ngậy', 'lasagna-bo-bam-dut-lo', 'Từng lá bột mì xếp lớp xen kẽ sốt bò băm Bolognese, sốt bechamel béo mịn và phủ ngập phô mai Mozzarella đút lò vàng ruộm thơm lừng.', 'https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 1, 20),
(229, 'prod-229', 12, 5, 'Nui Xoắn Fusilli Đút Lò Sốt Phô Mai Cheddar & Mozzarella', 'nui-xoan-dut-lo-cheddar', 'Nui xoắn giữ trọn sốt phô mai béo ngậy đút lò cháy xém mặt, vị mặn mòi thơm phức quyến rũ từng thìa.', 'https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=600&auto=format&fit=crop&q=80', 90000.00, 75000.00, 1, 0, 20),
(230, 'prod-230', 12, 5, 'Nui Ống Xào Bò Băm Nấm Rơm Sốt Cà Chua Phô Mai', 'nui-ong-xao-bo-bam-nam', 'Nui ống luộc mềm xào cùng thịt bò bằm ngấm sốt cà chua tươi và nấm rơm ngọt nước, món ăn ngon miệng hấp dẫn.', 'https://images.unsplash.com/photo-1621996346565-e3d5d6281724?w=600&auto=format&fit=crop&q=80', 85000.00, 69000.00, 1, 0, 20),
(231, 'prod-231', 12, 5, 'Mì Ý Ravioli Bánh Xếp Nhân Phô Mai Ricotta Rau Chân Vịt', 'ravioli-ricotta-rau-chan-vit', 'Bánh bột mì xếp thủ công nhồi phô mai Ricotta bùi ngậy và rau chân vịt thanh mát, tắm trong sốt bơ xô thơm lừng.', 'https://images.unsplash.com/photo-1587740908075-9e245070dfaa?w=600&auto=format&fit=crop&q=80', 110000.00, 89000.00, 1, 0, 20),
(232, 'prod-232', 12, 5, 'Pizza Margherita Truyền Thống Phô Mai Mozzarella & Lá Basil', 'pizza-margherita-y-jut', 'Chiếc pizza mang màu cờ nước Ý: Sốt cà chua đỏ au, phô mai Mozzarella tươi trắng tinh và lá húng tây xanh ngát nướng lò củi giòn xốp.', 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80', 125000.00, 99000.00, 1, 1, 20),
(233, 'prod-233', 12, 5, 'Pizza Xúc Xích Ý & Ớt Chuông Nướng Lò Than Đá', 'pizza-xuc-xich-ot-chuong', 'Xúc xích Ý đậm đà thái lát kết hợp ớt chuông Đà Lạt 3 màu nướng ngọt giòn và phô mai kéo sợi thơm phức.', 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80', 139000.00, 115000.00, 1, 0, 20),
(234, 'prod-234', 12, 5, 'Bánh Mì Nướng Bơ Tỏi Phô Mai Mozzarella Đút Lò', 'banh-mi-bo-toi-pho-mai-y-jut', 'Từng lát baguette nướng giòn rụm thơm bơ tỏi, phủ lớp phô mai Mozzarella đút lò vàng ruộm kéo sợi dài bất tận.', 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 1, 20),
(235, 'prod-235', 12, 5, 'Soup Nấm Rừng Kem Tươi Nóng Hổi Hương Thảo Mộc', 'soup-nam-rung-kem-tuoi', 'Nấm mỡ, nấm hương xào bơ xay nhuyễn cùng kem tươi béo ngậy, dùng nóng thơm lừng chấm cùng bánh mì nướng giòn.', 'https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 0, 20),
(236, 'prod-236', 12, 5, 'Salad Caesar Rau Xanh Ức Gà Nướng Phô Mai Bào Parmesan', 'salad-caesar-y-jut', 'Xà lách Romaine giòn ngọt trộn sốt Caesar cá cơm truyền thống, bánh mì vụn nướng giòn và phô mai Parmesan bào.', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 68000.00, 55000.00, 1, 0, 20),
(237, 'prod-237', 12, 5, 'Khoai Tây Nghiền Đút Lò Phô Mai Vàng Óng Kéo Sợi', 'khoai-tay-nghien-dut-lo-pho-mai', 'Khoai tây hấp mịn màng trộn bơ lạt, phủ phô mai Mozzarella đút lò thơm nức mũi béo ngậy tan trên đầu lưỡi.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 0, 20),
(238, 'prod-238', 12, 5, 'Bánh Panna Cotta Sốt Dâu Rừng Ý Mềm Mịn Thanh Mát', 'panna-cotta-dau-rung', 'Món tráng miệng Ý danh tiếng mềm mịn tan biến trong miệng như thạch sữa béo ngậy, sốt dâu rừng đỏ mọng chua ngọt quyến rũ.', 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 1, 20),
(239, 'prod-239', 12, 5, 'Trà Táo Quế Nóng Hương Thảo Mộc Ấm Áp', 'tra-tao-que-nong', 'Trà đen ủ cùng thanh quế già thơm lừng và lát táo tươi, hòa chút mật ong ngọt dịu làm ấm lòng thực khách.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 35000.00, 28000.00, 1, 0, 20),
(240, 'prod-240', 12, 5, 'Nước Khoáng Có Ga San Pellegrino Vị Chanh Vàng (Chai)', 'san-pellegrino-chanh-vang', 'Dòng nước khoáng có ga đóng chai danh tiếng nước Ý, sảng khoái với bọt khí li ti và hương chanh vàng thanh nhã.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 42000.00, 35000.00, 1, 0, 20),
(241, 'prod-241', 13, 6, 'Khoai Tây Lắc Phô Mai Béo Ngậy Giòn Rụm (Hộp Lớn)', 'khoai-tay-lac-pho-mai-le-duan', 'Khoai tây cọng chiên vàng giòn rụm, lắc đều tay trong túi giấy với ngập tràn bột phô mai cam thơm bùi ngọt mặn hấp dẫn.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 42000.00, 35000.00, 1, 1, 12),
(242, 'prod-242', 13, 6, 'Khoai Tây Lắc Rong Biển Sấy Giòn Sốt Mayonnaise', 'khoai-tay-lac-rong-bien-le-duan', 'Khoai chiên giòn tan lắc cùng bột rong biển sấy cay nhẹ thơm nồng phong cách Nhật Bản, chấm sốt mayo béo ngậy.', 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 42000.00, 35000.00, 1, 1, 12),
(243, 'prod-243', 13, 6, 'Khoai Lang Lắc Xí Muội Chua Ngọt Giòn Bùi', 'khoai-lang-lac-xi-muoi', 'Khoai lang vàng Đắk Lắk dẻo ngọt bùi chiên giòn vỏ, xóc bột xí muội ô mai chua chua ngọt ngọt mặn mặn cực cuốn.', 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 0, 12),
(244, 'prod-244', 13, 6, 'Khoai Môn Lệ Phố Chiên Giòn Nhân Trứng Muối Tán Nhuyễn', 'khoai-mon-le-pho-trung-muoi', 'Từng viên khoai môn lệ phố tròn xoe tím mộng mơ chiên xù vàng óng, cắn ngập nhân trứng muối sữa dừa béo bùi ngọt dịu.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 48000.00, 38000.00, 1, 1, 12),
(245, 'prod-245', 13, 6, 'Phô Mai Que Mozzarella Kéo Sợi Dài Bất Tận (Hộp 4 que)', 'pho-mai-que-keo-soi-le-duan', 'Lõi phô mai Mozzarella nguyên chất béo ngậy được tẩm 3 lớp bột chiên giòn vàng ruộm, kéo sợi dài cả cánh tay chấm tương cà chua ngọt.', 'https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=600&auto=format&fit=crop&q=80', 52000.00, 42000.00, 1, 1, 12),
(246, 'prod-246', 13, 6, 'Xúc Xích Đức Nướng Than Hoa Phủ Sốt Cay Phô Mai (2 chiếc)', 'xuc-xich-duc-nuong-than', 'Xúc xích Đức khía hoa nướng than hoa đượm khói giòn sần sật, rưới sốt cay phô mai và tương ớt đậm đà.', 'https://images.unsplash.com/photo-1541592106381-b31e9677c0e5?w=600&auto=format&fit=crop&q=80', 48000.00, 38000.00, 1, 1, 12),
(247, 'prod-247', 13, 6, 'Bánh Gà Phô Mai Kéo Sợi Vỏ Giòn Rụm (Set 3 chiếc)', 'banh-ga-pho-mai-keo-soi', 'Thịt ức gà tươi xay nêm nếm gia vị đậm đà ôm trọn viên phô mai Mozzarella, tẩm bột chiên xù vàng ươm giòn tan.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 0, 12),
(248, 'prod-248', 13, 6, 'Bánh Tráng Nướng Trứng Cút Thịt Bằm Phô Mai Đà Lạt', 'banh-trang-nuong-da-lat', 'Pizza Việt Nam danh tiếng! Bánh tráng nướng than hoa giòn rụm với trứng cút, thịt heo bằm, tép khô, mỡ hành, sốt bơ và phô mai béo ngậy.', '/assets/images/foods/banh-trang-nuong.jpg', 32000.00, 25000.00, 1, 1, 12),
(249, 'prod-249', 13, 6, 'Bánh Tráng Cuộn Sốt Bơ Bò Khô Trứng Cút (Hộp 5 cuốn)', 'banh-trang-cuon-sot-bo', 'Bánh tráng dẻo tôm phơi sương cuộn bò khô xé cay, trứng cút chiên, rau răm, xoài xanh và chan ngập sốt bơ trứng gà vàng ươm béo ngậy.', '/assets/images/foods/banh-trang-nuong.jpg', 35000.00, 28000.00, 1, 1, 12),
(250, 'prod-250', 13, 6, 'Gà Viên Popcorn Giòn Rụm Lắc Bột Ớt Paprika Cay Nhẹ', 'ga-popcorn-le-duan', 'Gà viên không xương tẩm ướp chiên giòn tan từng viên vừa miệng, lắc bột ớt ngọt Paprika thơm nồng hấp dẫn.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 48000.00, 39000.00, 1, 0, 12),
(251, 'prod-251', 13, 6, 'Mực Viên Chiên Nước Mắm Bơ Tỏi Giòn Sần Sật (Hộp 6 viên)', 'muc-vien-chien-nuoc-mam', 'Chả mực tươi quết nhuyễn chiên vàng ruộm ngào qua sốt nước mắm tỏi ớt kẹo sệt cay the the thơm nức.', 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/09/Squid_Ball.jpg/960px-Squid_Ball.jpg', 48000.00, 38000.00, 1, 0, 12),
(252, 'prod-252', 13, 6, 'Nem Chua Rán Phố Cổ Hà Nội Giòn Cay (Phần 5 chiếc)', 'nem-chua-ran-pho-co', 'Nem chua tẩm bột chiên xù giòn tan bên ngoài dẻo dai béo ngậy bên trong, chấm tương ớt Mường Khương chuẩn vị Hà Thành.', '/assets/images/foods/nem-ran.jpg', 55000.00, 45000.00, 1, 1, 12),
(253, 'prod-253', 13, 6, 'Chả Cá Viên Chiên Nước Mắm Tỏi Ớt Đậm Đà (Hộp 10 viên)', 'ca-vien-chien-nuoc-mam', 'Cá viên chiên phồng xù đảo trên chảo sốt mắm đường tỏi ớt kẹo quánh và rau muống giòn, món ăn vặt tuổi thơ gây nghiện.', 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 1, 12),
(254, 'prod-254', 13, 6, 'Bắp Xào Bơ Tép Khô Mỡ Hành Thơm Lừng', 'bap-xao-bo-tep-mo-hanh', 'Hạt bắp Mỹ vàng ngọt mọng nước xào bơ Tường An thơm ngào ngạt, tép khô mặn mặn ngọt ngọt và mỡ hành xanh mướt.', '/assets/images/foods/bap-xao.jpg', 32000.00, 25000.00, 1, 0, 12),
(255, 'prod-255', 13, 6, 'Bánh Hành Tây Chiên Giòn Xù Kiểu Mỹ (Onion Rings Le Duan)', 'hanh-tay-chien-gion-le-duan', 'Khoanh hành tây ngọt giòn tẩm bột chiên xù vàng óng ả chấm sốt tương cà ngọt chua thanh mát.', 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 40000.00, 32000.00, 1, 0, 12),
(256, 'prod-256', 13, 6, 'Xíu Mại Viên Hấp Trứng Cút Nóng Hổi Chấm Bánh Mì (3 viên)', 'xiu-mai-vien-trung-cut', 'Viên thịt xíu mại to mập ôm trọn trứng cút bên trong, ninh trong nước sốt cà chua ấm nồng thơm mùi hành ngò.', '/assets/images/foods/banh-mi-chao.jpg', 45000.00, 35000.00, 1, 0, 12),
(257, 'prod-257', 13, 6, 'Da Gà Chiên Giòn Rụm Chấm Muối Tiêu Chanh Ớt', 'da-ga-chien-gion-rum', 'Da gà làm sạch lọc bỏ hoàn toàn mỡ dưới da, chiên phồng xốp giòn rụm tan ngay trong miệng, rắc muối ớt cay tê tê.', 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 1, 12),
(258, 'prod-258', 13, 6, 'Trà Tắc Xí Muội Khổng Lồ Mát Lạnh (Ly Lớn 700ml)', 'tra-tac-xi-muoi-khong-lo', 'Ly trà tắc khổng lồ thơm ngào ngạt cốt quất tươi hòa quyện trái xí muội mặn ngọt chua thanh sảng khoái đánh bay cơn khát.', 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 28000.00, 22000.00, 1, 1, 12),
(259, 'prod-259', 13, 6, 'Trà Sữa Trân Châu Sợi Dừa Nướng Thơm Béo Bùi', 'tra-sua-dua-nuong', 'Trà sữa thơm ngậy mùi dừa nướng Bến Tre, topping dừa nướng sấy giòn rụm rắc trên mặt ly uống vui miệng cực kỳ.', 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 45000.00, 35000.00, 1, 0, 12),
(260, 'prod-260', 13, 6, 'Tàu Hũ Trân Châu Đường Đen Cốt Dừa Béo Ngậy', 'tau-hu-tran-chau-duong-den', 'Tàu hũ non núng nính mềm mịn tan ngay trên đầu lưỡi, chan nước cốt dừa béo ngậy và trân châu đường đen dẻo nóng ngọt ngào.', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=600&auto=format&fit=crop&q=80', 32000.00, 25000.00, 1, 1, 12)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `image_url` = VALUES(`image_url`), `base_price` = VALUES(`base_price`), `discount_price` = VALUES(`discount_price`);

-- 6. Hình ảnh đại diện chính của sản phẩm
INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `is_primary`, `display_order`) VALUES
(1, 1, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', 1, 1),
(2, 2, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80', 1, 1),
(3, 3, 'https://images.unsplash.com/photo-1586190848861-99aa4a171e90?w=600&auto=format&fit=crop&q=80', 1, 1),
(4, 4, 'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=600&auto=format&fit=crop&q=80', 1, 1),
(5, 5, 'https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5?w=600&auto=format&fit=crop&q=80', 1, 1),
(6, 6, 'https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=600&auto=format&fit=crop&q=80', 1, 1),
(7, 7, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 1, 1),
(8, 8, 'https://images.unsplash.com/photo-1521305916504-4a1121188589?w=600&auto=format&fit=crop&q=80', 1, 1),
(9, 9, 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 1, 1),
(10, 10, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', 1, 1),
(11, 11, 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 1, 1),
(12, 12, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80', 1, 1),
(13, 13, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(14, 14, 'https://images.unsplash.com/photo-1585109649139-366815a0d713?w=600&auto=format&fit=crop&q=80', 1, 1),
(15, 15, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(16, 16, 'https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=600&auto=format&fit=crop&q=80', 1, 1),
(17, 17, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(18, 18, 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=600&auto=format&fit=crop&q=80', 1, 1),
(19, 19, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(20, 20, 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=600&auto=format&fit=crop&q=80', 1, 1),
(21, 21, '/assets/images/foods/com-tam-suon-nuong.jpg', 1, 1),
(22, 22, '/assets/images/foods/com-tam-suon-bi-cha.jpg', 1, 1),
(23, 23, '/assets/images/foods/com-tam-dac-biet.jpg', 1, 1),
(24, 24, '/assets/images/foods/heo-quay.jpg', 1, 1),
(25, 25, '/assets/images/foods/dui-ga-nuong.jpg', 1, 1),
(26, 26, 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80', 1, 1),
(27, 27, '/assets/images/foods/suon-nuong-dia.jpg', 1, 1),
(28, 28, '/assets/images/foods/bo-xao.jpg', 1, 1),
(29, 29, '/assets/images/foods/banh-mi-chao.jpg', 1, 1),
(30, 30, '/assets/images/foods/thit-kho-tau.jpg', 1, 1),
(31, 31, '/assets/images/foods/ca-kho-to.jpg', 1, 1),
(32, 32, 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&auto=format&fit=crop&q=80', 1, 1),
(33, 33, 'https://images.unsplash.com/photo-1559847844-5315695dadae?w=600&auto=format&fit=crop&q=80', 1, 1),
(34, 34, 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f1/Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_%282448208587%29.jpg/960px-Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_%282448208587%29.jpg', 1, 1),
(35, 35, '/assets/images/foods/canh-kho-qua.jpg', 1, 1),
(36, 36, '/assets/images/foods/canh-rong-bien.jpg', 1, 1),
(37, 37, '/assets/images/foods/canh-chua.jpg', 1, 1),
(38, 38, '/assets/images/foods/com-tam-suon-bi-cha.jpg', 1, 1),
(39, 39, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(40, 40, '/assets/images/foods/nuoc-mia.jpg', 1, 1),
(41, 41, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(42, 42, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(43, 43, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(44, 44, 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 1, 1),
(45, 45, 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=600&auto=format&fit=crop&q=80', 1, 1),
(46, 46, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(47, 47, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 1, 1),
(48, 48, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(49, 49, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(50, 50, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 1, 1),
(51, 51, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600&auto=format&fit=crop&q=80', 1, 1),
(52, 52, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(53, 53, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 1, 1),
(54, 54, 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 1, 1),
(55, 55, 'https://images.unsplash.com/photo-1541658016709-82535e94bc69?w=600&auto=format&fit=crop&q=80', 1, 1),
(56, 56, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(57, 57, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 1, 1),
(58, 58, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(59, 59, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(60, 60, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(61, 61, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 1, 1),
(62, 62, 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80', 1, 1),
(63, 63, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600&auto=format&fit=crop&q=80', 1, 1),
(64, 64, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80', 1, 1),
(65, 65, 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=600&auto=format&fit=crop&q=80', 1, 1),
(66, 66, 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80', 1, 1),
(67, 67, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 1, 1),
(68, 68, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80', 1, 1),
(69, 69, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80', 1, 1),
(70, 70, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(71, 71, 'https://images.unsplash.com/photo-1621996346565-e3d5d6281724?w=600&auto=format&fit=crop&q=80', 1, 1),
(72, 72, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(73, 73, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(74, 74, 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 1, 1),
(75, 75, 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 1, 1),
(76, 76, 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/40/Deep-fried_Calamari_Rings.jpg/960px-Deep-fried_Calamari_Rings.jpg', 1, 1),
(77, 77, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(78, 78, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(79, 79, 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=600&auto=format&fit=crop&q=80', 1, 1),
(80, 80, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(81, 81, '/assets/images/foods/pho-bo.jpg', 1, 1),
(82, 82, '/assets/images/foods/pho-bo.jpg', 1, 1),
(83, 83, 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80', 1, 1),
(84, 84, 'https://images.unsplash.com/photo-1576577445504-6af96477db52?w=600&auto=format&fit=crop&q=80', 1, 1),
(85, 85, 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80', 1, 1),
(86, 86, '/assets/images/foods/pho-bo.jpg', 1, 1),
(87, 87, '/assets/images/foods/pho-bo.jpg', 1, 1),
(88, 88, '/assets/images/foods/pho-bo.jpg', 1, 1),
(89, 89, '/assets/images/foods/pho-ga.jpg', 1, 1),
(90, 90, '/assets/images/foods/pho-ga.jpg', 1, 1),
(91, 91, '/assets/images/foods/bun-thit-nuong.jpg', 1, 1),
(92, 92, 'https://images.unsplash.com/photo-1555126634-323283e090fa?w=600&auto=format&fit=crop&q=80', 1, 1),
(93, 93, '/assets/images/foods/goi-cuon.jpg', 1, 1),
(94, 94, 'https://images.unsplash.com/photo-1555126634-323283e090fa?w=600&auto=format&fit=crop&q=80', 1, 1),
(95, 95, '/assets/images/foods/pho-bo.jpg', 1, 1),
(96, 96, 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80', 1, 1),
(97, 97, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(98, 98, 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80', 1, 1),
(99, 99, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 1, 1),
(100, 100, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(101, 101, 'https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=600&auto=format&fit=crop&q=80', 1, 1),
(102, 102, 'https://images.unsplash.com/photo-1534482421-64566f976cfa?w=600&auto=format&fit=crop&q=80', 1, 1),
(103, 103, 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 1, 1),
(104, 104, 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 1, 1),
(105, 105, 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 1, 1),
(106, 106, 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 1, 1),
(107, 107, 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 1, 1),
(108, 108, 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 1, 1),
(109, 109, 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 1, 1),
(110, 110, 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80', 1, 1),
(111, 111, 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80', 1, 1),
(112, 112, 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80', 1, 1),
(113, 113, 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 1, 1),
(114, 114, 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 1, 1),
(115, 115, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 1, 1),
(116, 116, 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80', 1, 1),
(117, 117, 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80', 1, 1),
(118, 118, 'https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format&fit=crop&q=80', 1, 1),
(119, 119, 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 1, 1),
(120, 120, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(121, 121, 'https://images.unsplash.com/photo-1525059696034-4967a8e1dca2?w=600&auto=format&fit=crop&q=80', 1, 1),
(122, 122, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(123, 123, 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 1, 1),
(124, 124, 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80', 1, 1),
(125, 125, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(126, 126, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(127, 127, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 1, 1),
(128, 128, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 1, 1),
(129, 129, 'https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=600&auto=format&fit=crop&q=80', 1, 1),
(130, 130, 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 1, 1),
(131, 131, 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 1, 1),
(132, 132, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 1, 1),
(133, 133, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(134, 134, 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 1, 1),
(135, 135, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(136, 136, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 1, 1),
(137, 137, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(138, 138, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(139, 139, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(140, 140, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(141, 141, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(142, 142, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80', 1, 1),
(143, 143, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(144, 144, 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 1, 1),
(145, 145, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80', 1, 1),
(146, 146, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(147, 147, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=600&auto=format&fit=crop&q=80', 1, 1),
(148, 148, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 1, 1),
(149, 149, 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?w=600&auto=format&fit=crop&q=80', 1, 1),
(150, 150, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(151, 151, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=600&auto=format&fit=crop&q=80', 1, 1),
(152, 152, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(153, 153, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80', 1, 1),
(154, 154, 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80', 1, 1),
(155, 155, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(156, 156, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(157, 157, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 1, 1),
(158, 158, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600&auto=format&fit=crop&q=80', 1, 1),
(159, 159, 'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80', 1, 1),
(160, 160, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(161, 161, 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80', 1, 1),
(162, 162, '/assets/images/foods/banh-mi-chao.jpg', 1, 1),
(163, 163, '/assets/images/foods/banh-mi-chao.jpg', 1, 1),
(164, 164, 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 1, 1),
(165, 165, '/assets/images/foods/heo-quay.jpg', 1, 1),
(166, 166, 'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80', 1, 1),
(167, 167, '/assets/images/foods/xoi-xeo.jpg', 1, 1),
(168, 168, '/assets/images/foods/xoi-thap-cam.jpg', 1, 1),
(169, 169, '/assets/images/foods/bun-bo-hue.jpg', 1, 1),
(170, 170, '/assets/images/foods/bun-thit-nuong.jpg', 1, 1),
(171, 171, '/assets/images/foods/bun-rieu.jpg', 1, 1),
(172, 172, '/assets/images/foods/pho-bo.jpg', 1, 1),
(173, 173, '/assets/images/foods/pho-ga.jpg', 1, 1),
(174, 174, '/assets/images/foods/banh-cuon.jpg', 1, 1),
(175, 175, '/assets/images/foods/canh-kho-qua.jpg', 1, 1),
(176, 176, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(177, 177, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(178, 178, '/assets/images/foods/ca-phe-sua-da.jpg', 1, 1),
(179, 179, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(180, 180, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(181, 181, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 1, 1),
(182, 182, 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 1, 1),
(183, 183, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(184, 184, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80', 1, 1),
(185, 185, 'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=600&auto=format&fit=crop&q=80', 1, 1),
(186, 186, 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 1, 1),
(187, 187, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 1, 1),
(188, 188, 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80', 1, 1),
(189, 189, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80', 1, 1),
(190, 190, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(191, 191, 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 1, 1),
(192, 192, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(193, 193, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(194, 194, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 1, 1),
(195, 195, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80', 1, 1),
(196, 196, 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80', 1, 1),
(197, 197, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(198, 198, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(199, 199, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(200, 200, 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 1, 1),
(201, 201, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 1, 1),
(202, 202, 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80', 1, 1),
(203, 203, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 1, 1),
(204, 204, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(205, 205, 'https://images.unsplash.com/photo-1559847844-5315695dadae?w=600&auto=format&fit=crop&q=80', 1, 1),
(206, 206, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(207, 207, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 1, 1),
(208, 208, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(209, 209, '/assets/images/foods/suon-nuong-dia.jpg', 1, 1),
(210, 210, 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80', 1, 1),
(211, 211, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(212, 212, 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 1, 1),
(213, 213, 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80', 1, 1),
(214, 214, 'https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=600&auto=format&fit=crop&q=80', 1, 1),
(215, 215, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(216, 216, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(217, 217, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', 1, 1),
(218, 218, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(219, 219, 'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80', 1, 1),
(220, 220, 'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80', 1, 1),
(221, 221, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80', 1, 1),
(222, 222, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 1, 1),
(223, 223, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(224, 224, 'https://images.unsplash.com/photo-1621996346565-e3d5d6281724?w=600&auto=format&fit=crop&q=80', 1, 1),
(225, 225, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(226, 226, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80', 1, 1),
(227, 227, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(228, 228, 'https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(229, 229, 'https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=600&auto=format&fit=crop&q=80', 1, 1),
(230, 230, 'https://images.unsplash.com/photo-1621996346565-e3d5d6281724?w=600&auto=format&fit=crop&q=80', 1, 1),
(231, 231, 'https://images.unsplash.com/photo-1587740908075-9e245070dfaa?w=600&auto=format&fit=crop&q=80', 1, 1),
(232, 232, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80', 1, 1),
(233, 233, 'https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80', 1, 1),
(234, 234, 'https://images.unsplash.com/photo-1579684947550-22e945225d9a?w=600&auto=format&fit=crop&q=80', 1, 1),
(235, 235, 'https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=600&auto=format&fit=crop&q=80', 1, 1),
(236, 236, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80', 1, 1),
(237, 237, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(238, 238, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80', 1, 1),
(239, 239, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(240, 240, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', 1, 1),
(241, 241, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(242, 242, 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 1, 1),
(243, 243, 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80', 1, 1),
(244, 244, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 1, 1),
(245, 245, 'https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=600&auto=format&fit=crop&q=80', 1, 1),
(246, 246, 'https://images.unsplash.com/photo-1541592106381-b31e9677c0e5?w=600&auto=format&fit=crop&q=80', 1, 1),
(247, 247, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(248, 248, '/assets/images/foods/banh-trang-nuong.jpg', 1, 1),
(249, 249, '/assets/images/foods/banh-trang-nuong.jpg', 1, 1),
(250, 250, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(251, 251, 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/09/Squid_Ball.jpg/960px-Squid_Ball.jpg', 1, 1),
(252, 252, '/assets/images/foods/nem-ran.jpg', 1, 1),
(253, 253, 'https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80', 1, 1),
(254, 254, '/assets/images/foods/bap-xao.jpg', 1, 1),
(255, 255, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 1, 1),
(256, 256, '/assets/images/foods/banh-mi-chao.jpg', 1, 1),
(257, 257, 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80', 1, 1),
(258, 258, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 1),
(259, 259, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 1, 1),
(260, 260, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=600&auto=format&fit=crop&q=80', 1, 1)
ON DUPLICATE KEY UPDATE `image_url` = VALUES(`image_url`);


-- 7. Sổ địa chỉ giao hàng mẫu
INSERT INTO `addresses` (`id`, `user_id`, `recipient_name`, `phone_number`, `street_address`, `ward`, `district`, `city`, `latitude`, `longitude`, `is_default`, `note`) VALUES
(1, 3, 'Trần Khách Hàng', '0903000003', '72 Lê Thánh Tôn', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 10.776530, 106.700980, 1, 'Giao giờ hành chính, gọi trước khi đến'),
(2, 5, 'Nguyễn Hiệp', '0862033905', '10 Mai Hắc Đế, Phường Tân Thành', 'Tân Thành', 'TP. Buôn Ma Thuột', 'Đắk Lắk', 12.679500, 108.050500, 1, 'Giao tận cửa nhà')
ON DUPLICATE KEY UPDATE `recipient_name` = VALUES(`recipient_name`);

-- 8. Mã giảm giá mẫu (coupons)
INSERT INTO `coupons` (`id`, `user_id`, `restaurant_id`, `code`, `title`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `usage_count`, `start_date`, `end_date`, `is_active`) VALUES
(1, NULL, NULL, 'FASTFOODHELLO', 'Giảm 20K cho đơn hàng đầu tiên', 'fixed', 20000.00, 50000.00, 20000.00, 500, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(2, NULL, NULL, 'FREESHIP', 'Miễn phí giao hàng đơn từ 100K', 'fixed', 15000.00, 100000.00, 15000.00, 1000, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(3, NULL, NULL, 'FASTFOOD20', 'Ưu đãi đặt món nhanh giảm 20.000đ', 'fixed', 20000.00, 60000.00, 20000.00, 500, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(4, NULL, NULL, 'GIAM50K', 'Giảm ngay 50.000đ cho đơn tiệc đông người', 'fixed', 50000.00, 20000.00, 50000.00, 300, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `discount_value` = VALUES(`discount_value`);

-- 9. Thông báo mẫu (notifications)
INSERT INTO `notifications` (`id`, `user_id`, `title`, `content`, `type`, `is_read`, `metadata`) VALUES
(1, 3, 'Chào mừng bạn đến với FASTFOOD! 🎉', 'Nhận ngay mã FASTFOODHELLO giảm 20K cho đơn hàng đầu tiên của bạn hôm nay!', 'promotion', 0, '{"code":"FASTFOODHELLO"}'),
(2, 5, 'Chào mừng Nguyễn Hiệp! 🎉', 'Khám phá hơn 260 món ăn nóng hổi từ các quán ngon hàng đầu Buôn Ma Thuột!', 'system', 0, NULL)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 10. Trợ lý ảo AI - Hội thoại mẫu (ai_chat_sessions & ai_chat_messages)
INSERT INTO `ai_chat_sessions` (`id`, `session_id`, `user_id`, `customer_name`, `title`, `is_active`) VALUES
(1, 'session-welcome-demo', 1, 'Hệ Thống Quản Trị', 'Tư vấn món ăn ngon hôm nay', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `ai_chat_messages` (`id`, `session_id`, `user_id`, `sender`, `message`, `metadata`) VALUES
(1, 'session-welcome-demo', 1, 'assistant', 'Xin chào bạn! Mình là Trợ lý AI FASTFOOD 🤖. Mình có thể giúp bạn tìm món ngon theo sở thích, gợi ý combo tiết kiệm hoặc kiểm tra đơn hàng!', NULL)
ON DUPLICATE KEY UPDATE `message` = VALUES(`message`);


-- Bật lại kiểm tra khóa ngoại sau khi hoàn tất
SET FOREIGN_KEY_CHECKS = 1;

-- Hoàn tất transaction
COMMIT;
