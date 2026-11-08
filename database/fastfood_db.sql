-- ===================================================================
-- FASTFOOD DELIVERY PLATFORM - ALL-IN-ONE DATABASE IMPORT FILE
-- Tên CSDL   : fastfood_db
-- Tương thích: MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- Mã hóa     : utf8mb4 / utf8mb4_unicode_ci
-- Mục đích   : Import 1 lần duy nhất vào phpMyAdmin / MySQL CLI / DBeaver
-- Bao gồm    : Cấu trúc 19 bảng chuẩn 3NF + Dữ liệu mẫu (Tài khoản, Món ăn, Quán ăn)
-- ===================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Tắt kiểm tra khóa ngoại khi khởi tạo và import
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- 0. TẠO DATABASE
-- ===================================================================
CREATE DATABASE IF NOT EXISTS `fastfood_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `fastfood_db`;

-- ===================================================================
-- 1. BẢNG roles (Phân quyền người dùng)
-- ===================================================================
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tên vai trò: admin, customer, restaurant_owner, shipper',
  `description` VARCHAR(255) NULL COMMENT 'Mô tả vai trò',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  `phone` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Số điện thoại',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã mã hóa bcrypt',
  `avatar_url` VARCHAR(500) NULL COMMENT 'Ảnh đại diện',
  `status` ENUM('active', 'inactive', 'banned') DEFAULT 'active' COMMENT 'Trạng thái tài khoản',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
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
  `phone` VARCHAR(20) NOT NULL COMMENT 'Hotline quán',
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_restaurants_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_categories_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_categories_slug` (`slug`),
  INDEX `idx_categories_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 5. BẢNG products (Sản phẩm / Món ăn)
-- ===================================================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán bán món này',
  `category_id` INT UNSIGNED NOT NULL COMMENT 'FK danh mục món',
  `name` VARCHAR(200) NOT NULL COMMENT 'Tên món ăn',
  `slug` VARCHAR(220) NOT NULL COMMENT 'Slug món ăn',
  `description` TEXT NULL COMMENT 'Mô tả thành phần, khẩu phần',
  `base_price` DECIMAL(12, 2) NOT NULL COMMENT 'Giá gốc',
  `discount_price` DECIMAL(12, 2) NULL COMMENT 'Giá khuyến mãi (nếu có)',
  `is_available` TINYINT(1) DEFAULT 1 COMMENT '1 = Còn món, 0 = Tạm hết hàng',
  `is_featured` TINYINT(1) DEFAULT 0 COMMENT '1 = Món ăn nổi bật / Best-seller',
  `prep_time_minutes` INT UNSIGNED DEFAULT 15 COMMENT 'Thời gian chuẩn bị món dự kiến (phút)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_products_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_products_restaurant` (`restaurant_id`),
  INDEX `idx_products_category` (`category_id`),
  INDEX `idx_products_available` (`is_available`),
  INDEX `idx_products_price` (`base_price`, `discount_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 6. BẢNG product_images (Hình ảnh món ăn)
-- ===================================================================
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK liên kết tới sản phẩm',
  `image_url` VARCHAR(500) NOT NULL COMMENT 'Đường dẫn ảnh',
  `is_primary` TINYINT(1) DEFAULT 0 COMMENT '1 = Ảnh đại diện chính',
  `display_order` INT DEFAULT 0 COMMENT 'Thứ tự hiển thị thư viện ảnh',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_product_images_product` (`product_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 7. BẢNG addresses (Sổ địa chỉ giao hàng của người dùng)
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_addresses_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 8. BẢNG carts (Giỏ hàng của người dùng - Mỗi quán 1 giỏ hàng riêng)
-- ===================================================================
DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK người dùng sở hữu giỏ',
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán ăn của giỏ hàng hiện tại',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_carts_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY `uq_user_restaurant_cart` (`user_id`, `restaurant_id`),
  INDEX `idx_carts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 9. BẢNG cart_items (Chi tiết món ăn trong giỏ hàng)
-- ===================================================================
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK giỏ hàng',
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK sản phẩm trong giỏ',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số lượng đặt',
  `unit_price` DECIMAL(12, 2) NOT NULL COMMENT 'Đơn giá tại thời điểm đưa vào giỏ',
  `notes` VARCHAR(255) NULL COMMENT 'Ghi chú cho món ăn (ví dụ: ít ngọt, không cay)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_cart_items_cart` (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 10. BẢNG coupons (Mã giảm giá / Khuyến mãi)
-- ===================================================================
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` BIGINT UNSIGNED NULL COMMENT 'NULL nếu voucher toàn sàn, có ID nếu mã riêng của quán',
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã voucher (ví dụ: FREESHIP, GIAM20K)',
  `title` VARCHAR(150) NOT NULL COMMENT 'Tiêu đề voucher',
  `discount_type` ENUM('percentage', 'fixed') NOT NULL COMMENT 'percentage = %, fixed = số tiền cố định',
  `discount_value` DECIMAL(12, 2) NOT NULL COMMENT 'Giá trị giảm (% hoặc VNĐ)',
  `min_order_value` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Giá trị đơn tối thiểu để áp dụng',
  `max_discount_amount` DECIMAL(12, 2) NULL COMMENT 'Số tiền giảm tối đa (nếu dùng %)',
  `usage_limit` INT UNSIGNED DEFAULT 1000 COMMENT 'Tổng số lượt sử dụng tối đa',
  `usage_count` INT UNSIGNED DEFAULT 0 COMMENT 'Số lượt đã sử dụng',
  `start_date` DATETIME NOT NULL COMMENT 'Thời gian bắt đầu',
  `end_date` DATETIME NOT NULL COMMENT 'Thời gian kết thúc',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = Kích hoạt, 0 = Tạm dừng',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_coupons_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_coupons_code` (`code`),
  INDEX `idx_coupons_dates` (`start_date`, `end_date`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 11. BẢNG orders (Đơn đặt hàng đồ ăn)
-- ===================================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Mã đơn hiển thị (ví dụ: FF0123)',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Khách hàng đặt món',
  `restaurant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Quán ăn nhận đơn',
  `delivery_address_id` BIGINT UNSIGNED NOT NULL COMMENT 'Địa chỉ nhận hàng',
  `coupon_id` INT UNSIGNED NULL COMMENT 'Mã giảm giá áp dụng (nếu có)',
  `shipper_id` BIGINT UNSIGNED NULL COMMENT 'Tài xế giao hàng',
  `delivery_fee` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Phí vận chuyển',
  `subtotal` DECIMAL(12, 2) NOT NULL COMMENT 'Tổng tiền món ăn',
  `discount_amount` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Số tiền được giảm',
  `final_amount` DECIMAL(12, 2) NOT NULL COMMENT 'Số tiền thực tế thanh toán',
  `status` ENUM('pending', 'confirmed', 'preparing', 'delivering', 'delivered', 'cancelled') DEFAULT 'pending' COMMENT 'Trạng thái đơn hàng',
  `delivery_notes` VARCHAR(255) NULL COMMENT 'Ghi chú đơn hàng của khách',
  `cancel_reason` VARCHAR(255) NULL COMMENT 'Lý do hủy đơn',
  `order_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian khách bấm đặt hàng',
  `delivered_time` TIMESTAMP NULL COMMENT 'Thời gian hoàn tất giao đơn',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_address` FOREIGN KEY (`delivery_address_id`) REFERENCES `addresses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_shipper` FOREIGN KEY (`shipper_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_orders_code` (`order_code`),
  INDEX `idx_orders_user` (`user_id`),
  INDEX `idx_orders_restaurant` (`restaurant_id`),
  INDEX `idx_orders_shipper` (`shipper_id`),
  INDEX `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 12. BẢNG order_items (Chi tiết món ăn trong từng đơn hàng)
-- ===================================================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK đơn hàng',
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK sản phẩm',
  `product_name` VARCHAR(200) NOT NULL COMMENT 'Lưu cứng tên món tại thời điểm đặt',
  `unit_price` DECIMAL(12, 2) NOT NULL COMMENT 'Lưu cứng đơn giá tại thời điểm đặt',
  `quantity` INT UNSIGNED NOT NULL COMMENT 'Số lượng món',
  `subtotal` DECIMAL(12, 2) NOT NULL COMMENT 'Thành tiền = đơn giá * số lượng',
  `notes` VARCHAR(255) NULL COMMENT 'Ghi chú cụ thể món',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 13. BẢNG payments (Giao dịch thanh toán đơn hàng)
-- ===================================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'FK đơn hàng (1 đơn - 1 giao dịch)',
  `payment_method` ENUM('cod', 'vnpay', 'momo', 'banking') NOT NULL COMMENT 'Phương thức thanh toán',
  `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending' COMMENT 'Trạng thái thanh toán',
  `transaction_id` VARCHAR(100) NULL COMMENT 'Mã giao dịch từ cổng thanh toán đối tác',
  `amount` DECIMAL(12, 2) NOT NULL COMMENT 'Số tiền thanh toán',
  `payment_time` TIMESTAMP NULL COMMENT 'Thời gian thanh toán thành công',
  `provider_response` TEXT NULL COMMENT 'JSON phản hồi từ cổng thanh toán',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_payments_method` (`payment_method`),
  INDEX `idx_payments_status` (`payment_status`),
  INDEX `idx_payments_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 14. BẢNG reviews (Đánh giá và nhận xét món ăn / quán)
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `chk_reviews_rating` CHECK (`rating` >= 1 AND `rating` <= 5),
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_reviews_restaurant` (`restaurant_id`),
  INDEX `idx_reviews_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 15. BẢNG notifications (Thông báo hệ thống, đơn hàng, khuyến mãi)
-- ===================================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Người nhận thông báo',
  `title` VARCHAR(200) NOT NULL COMMENT 'Tiêu đề thông báo',
  `content` TEXT NOT NULL COMMENT 'Nội dung chi tiết',
  `type` ENUM('order', 'promotion', 'system') DEFAULT 'system' COMMENT 'Phân loại thông báo',
  `is_read` TINYINT(1) DEFAULT 0 COMMENT '0 = Chưa đọc, 1 = Đã đọc',
  `metadata` JSON NULL COMMENT 'Dữ liệu JSON kèm theo (order_id, url...)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_staff_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY `uq_restaurant_staff` (`restaurant_id`, `user_id`),
  INDEX `idx_staff_restaurant` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 17. BẢNG website_content (Đồng bộ nội dung giao diện realtime)
-- ===================================================================
DROP TABLE IF EXISTS `website_content`;
CREATE TABLE `website_content` (
  `collection_name` VARCHAR(50) PRIMARY KEY,
  `content` JSON NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 18. BẢNG chat_messages (Tin nhắn hỗ trợ trực tuyến realtime)
-- ===================================================================
DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NOT NULL,
  `sender_role` ENUM('admin', 'customer') NOT NULL,
  `message` VARCHAR(1000) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_chat_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_chat_customer_id` (`customer_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 19. BẢNG realtime_orders (Theo dõi trạng thái đơn hàng realtime)
-- ===================================================================
DROP TABLE IF EXISTS `realtime_orders`;
CREATE TABLE `realtime_orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_data` JSON NOT NULL,
  `status` ENUM('pending', 'preparing', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_realtime_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_realtime_orders_user` (`user_id`, `id`),
  INDEX `idx_realtime_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================================================================
-- ===================================================================
-- DỮ LIỆU KHỞI TẠO BAN ĐẦU (SEED DATA)
-- ===================================================================
-- ===================================================================

-- 1. Vai trò hệ thống
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Quản trị viên toàn quyền hệ thống'),
(2, 'customer', 'Khách hàng đặt món ăn'),
(3, 'restaurant_owner', 'Chủ cửa hàng, quán ăn đối tác'),
(4, 'shipper', 'Tài xế giao nhận đơn hàng')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. Tài khoản mẫu (Mật khẩu mặc định: Admin@123456)
INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `phone`, `password_hash`, `status`) VALUES
(1, 1, 'Hệ Thống Quản Trị', 'admin@fastfood.vn', '0901000001', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(2, 3, 'Nguyễn Văn Chủ Quán', 'owner@fastfood.vn', '0902000002', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(3, 2, 'Trần Khách Hàng', 'customer@fastfood.vn', '0903000003', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(4, 4, 'Lê Tài Xế Giao Hàng', 'shipper@fastfood.vn', '0904000004', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active')
ON DUPLICATE KEY UPDATE
  `role_id` = VALUES(`role_id`),
  `full_name` = VALUES(`full_name`),
  `phone` = VALUES(`phone`),
  `password_hash` = VALUES(`password_hash`),
  `status` = VALUES(`status`);

-- 3. Danh mục món ăn
INSERT INTO `categories` (`id`, `restaurant_id`, `name`, `slug`, `icon_url`, `display_order`, `is_active`) VALUES
(1, NULL, 'Cơm & Món Việt', 'com-mon-viet', '/assets/images/categories/rice.svg', 1, 1),
(2, NULL, 'Burger & Gà Rán', 'burger-ga-ran', '/assets/images/categories/burger.svg', 2, 1),
(3, NULL, 'Trà Sữa & Đồ Uống', 'tra-sua-do-uong', '/assets/images/categories/drink.svg', 3, 1),
(4, NULL, 'Pizza & Mỳ Ý', 'pizza-my-y', '/assets/images/categories/pizza.svg', 4, 1),
(5, NULL, 'Ăn Vặt & Tráng Miệng', 'an-vat-trang-mieng', '/assets/images/categories/snack.svg', 5, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Quán ăn đối tác mẫu
INSERT INTO `restaurants` (`id`, `owner_id`, `name`, `slug`, `description`, `address`, `latitude`, `longitude`, `phone`, `email`, `banner_url`, `logo_url`, `is_active`, `is_verified`, `rating_avg`, `rating_count`, `opening_time`, `closing_time`, `min_order_amount`) VALUES
(1, 2, 'Burger King Station - Buôn Ma Thuột', 'burger-king-station', 'Chuyên các loại burger bò nướng lửa, gà giòn sốt cay và khoai tây lắc.', '25 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk', 12.679500, 108.050500, '0262388888', 'burgerking@fastfood.vn', '/assets/images/hero-burger.svg', '/assets/images/logo.svg', 1, 1, 4.85, 120, '08:00:00', '23:00:00', 30000.00),
(2, 2, 'Tiệm Cơm Tấm Sài Gòn Xưa - Buôn Ma Thuột', 'tiem-com-tam-sai-gon-xua', 'Cơm tấm sườn bì chả nướng than hoa gia truyền đậm đà.', '68 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk', 12.666100, 108.045500, '0262388889', 'comtam@fastfood.vn', '/assets/images/hero-burger.svg', '/assets/images/logo.svg', 1, 1, 4.90, 350, '06:30:00', '21:30:00', 25000.00)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `address` = VALUES(`address`),
  `latitude` = VALUES(`latitude`),
  `longitude` = VALUES(`longitude`),
  `phone` = VALUES(`phone`);

-- 5. Món ăn mẫu
INSERT INTO `products` (`id`, `restaurant_id`, `category_id`, `name`, `slug`, `description`, `base_price`, `discount_price`, `is_available`, `is_featured`, `prep_time_minutes`) VALUES
(1, 1, 2, 'Burger Bò Nướng Phô Mai Tan Chảy', 'burger-bo-nuong-pho-mai', 'Thịt bò Úc 100% nướng than hoa, kèm 2 lát phô mai Cheddar béo ngậy.', 69000.00, 59000.00, 1, 1, 12),
(2, 1, 2, 'Gà Rán Giòn Sốt Cay Hàn Quốc', 'ga-ran-gion-sot-cay', '2 miếng gà giòn rụm phủ sốt cay ngọt chuẩn vị Seoul.', 75000.00, 65000.00, 1, 1, 15),
(3, 1, 3, 'Trà Sữa Trân Châu Hoàng Gia', 'tra-sua-tran-chau-hoang-gia', 'Trà đen Ceylon thơm đậm kết hợp sữa tươi thanh trùng và trân châu đường đen.', 45000.00, 39000.00, 1, 1, 5),
(4, 2, 1, 'Cơm Tấm Sườn Bì Chả Trứng Ốp La', 'com-tam-suon-bi-cha-trung', 'Dĩa cơm tấm đầy đủ sườn cốt lết dày dặn, bì chả nóng hổi, mỡ hành tép mỡ giòn rụm.', 55000.00, 49000.00, 1, 1, 10)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 6. Hình ảnh món ăn
INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `is_primary`, `display_order`) VALUES
(1, 1, '/assets/images/hero-burger.svg', 1, 1),
(2, 2, '/assets/images/hero-burger.svg', 1, 1),
(3, 3, '/assets/images/hero-burger.svg', 1, 1),
(4, 4, '/assets/images/hero-burger.svg', 1, 1)
ON DUPLICATE KEY UPDATE `image_url` = VALUES(`image_url`);

-- 7. Sổ địa chỉ mẫu
INSERT INTO `addresses` (`id`, `user_id`, `recipient_name`, `phone_number`, `street_address`, `ward`, `district`, `city`, `latitude`, `longitude`, `is_default`, `note`) VALUES
(1, 3, 'Trần Khách Hàng', '0903000003', '72 Lê Thánh Tôn, Bến Nghé', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 10.776530, 106.700980, 1, 'Giao giờ hành chính, gọi trước khi đến')
ON DUPLICATE KEY UPDATE `recipient_name` = VALUES(`recipient_name`);

-- 8. Mã giảm giá mẫu
INSERT INTO `coupons` (`id`, `restaurant_id`, `code`, `title`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `usage_count`, `start_date`, `end_date`, `is_active`) VALUES
(1, NULL, 'FASTFOODHELLO', 'Giảm 20K cho đơn hàng đầu tiên', 'fixed', 20000.00, 50000.00, 20000.00, 500, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(2, NULL, 'FREESHIP', 'Miễn phí giao hàng đơn từ 100K', 'fixed', 15000.00, 100000.00, 15000.00, 1000, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Bật lại kiểm tra khóa ngoại sau khi hoàn tất
SET FOREIGN_KEY_CHECKS = 1;

-- Hoàn tất transaction
COMMIT;
