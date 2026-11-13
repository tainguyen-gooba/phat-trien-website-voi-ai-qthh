-- ===================================================================
-- CƠ SỞ DỮ LIỆU DỰ ÁN FASTFOOD (FOOD DELIVERY PLATFORM)
-- Phiên bản: 2.0.0 (Chuẩn hóa toàn diện 23 bảng + AI Chat + Realtime)
-- Mã hóa: utf8mb4 / utf8mb4_unicode_ci
-- ===================================================================

CREATE DATABASE IF NOT EXISTS `fastfood_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `fastfood_db`;

-- Tắt kiểm tra khóa ngoại để tạo bảng sạch sẽ
SET FOREIGN_KEY_CHECKS = 0;


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


-- Bật lại kiểm tra khóa ngoại sau khi tạo bảng
SET FOREIGN_KEY_CHECKS = 1;
