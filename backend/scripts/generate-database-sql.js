const fs = require('fs');
const path = require('path');
const vm = require('vm');

// Load fastfoodData from frontend/js/data.js
const dataPath = path.resolve(__dirname, '../../frontend/js/data.js');
const dataContent = fs.readFileSync(dataPath, 'utf8');
const sandbox = { window: {}, localStorage: { getItem: () => null, setItem: () => null } };
sandbox.window = sandbox;
vm.runInNewContext(dataContent, sandbox);
const data = sandbox.window.FASTFOOD_DATA || sandbox.FASTFOOD_DATA;

function esc(val) {
  if (val === null || val === undefined) return 'NULL';
  if (typeof val === 'number') return String(val);
  return "'" + String(val).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

// ========================================================
// 1. SCHEMA DEFINITION (23 TABLES)
// ========================================================
const schemaTablesSql = `
-- Drop all existing tables in reverse dependency order
DROP TABLE IF EXISTS \`ai_chat_messages\`;
DROP TABLE IF EXISTS \`ai_chat_sessions\`;
DROP TABLE IF EXISTS \`chat_automation\`;
DROP TABLE IF EXISTS \`chat_messages\`;
DROP TABLE IF EXISTS \`realtime_payments\`;
DROP TABLE IF EXISTS \`realtime_orders\`;
DROP TABLE IF EXISTS \`website_content\`;
DROP TABLE IF EXISTS \`notifications\`;
DROP TABLE IF EXISTS \`staff_assignments\`;
DROP TABLE IF EXISTS \`cart_items\`;
DROP TABLE IF EXISTS \`carts\`;
DROP TABLE IF EXISTS \`reviews\`;
DROP TABLE IF EXISTS \`payments\`;
DROP TABLE IF EXISTS \`order_items\`;
DROP TABLE IF EXISTS \`orders\`;
DROP TABLE IF EXISTS \`coupons\`;
DROP TABLE IF EXISTS \`addresses\`;
DROP TABLE IF EXISTS \`product_images\`;
DROP TABLE IF EXISTS \`products\`;
DROP TABLE IF EXISTS \`categories\`;
DROP TABLE IF EXISTS \`restaurants\`;
DROP TABLE IF EXISTS \`users\`;
DROP TABLE IF EXISTS \`roles\`;

-- ===================================================================
-- 1. BẢNG roles (Phân quyền người dùng)
-- ===================================================================
CREATE TABLE \`roles\` (
  \`id\` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`name\` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tên vai trò: admin, customer, restaurant_owner, shipper',
  \`description\` VARCHAR(255) NULL COMMENT 'Mô tả vai trò',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX \`idx_roles_name\` (\`name\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 2. BẢNG users (Tài khoản người dùng)
-- ===================================================================
DROP TABLE IF EXISTS \`users\`;
CREATE TABLE \`users\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`role_id\` INT UNSIGNED NOT NULL COMMENT 'FK liên kết tới roles',
  \`full_name\` VARCHAR(150) NOT NULL COMMENT 'Họ và tên',
  \`email\` VARCHAR(191) NOT NULL UNIQUE COMMENT 'Email đăng nhập / nhận thông báo',
  \`phone\` VARCHAR(20) NULL UNIQUE COMMENT 'Số điện thoại',
  \`address\` VARCHAR(255) NULL COMMENT 'Địa chỉ mặc định của khách hàng',
  \`password_hash\` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã mã hóa bcrypt',
  \`avatar_url\` VARCHAR(500) NULL COMMENT 'Ảnh đại diện',
  \`status\` ENUM('active', 'inactive', 'banned') DEFAULT 'active' COMMENT 'Trạng thái tài khoản',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_users_role\` (\`role_id\`),
  CONSTRAINT \`fk_users_role\` FOREIGN KEY (\`role_id\`) REFERENCES \`roles\` (\`id\`) ON UPDATE CASCADE,
  INDEX \`idx_users_email\` (\`email\`),
  INDEX \`idx_users_phone\` (\`phone\`),
  INDEX \`idx_users_status\` (\`status\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 3. BẢNG restaurants (Cửa hàng / Quán ăn đối tác)
-- ===================================================================
DROP TABLE IF EXISTS \`restaurants\`;
CREATE TABLE \`restaurants\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`owner_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK liên kết tới users (chủ quán)',
  \`name\` VARCHAR(200) NOT NULL COMMENT 'Tên quán ăn',
  \`slug\` VARCHAR(220) NOT NULL UNIQUE COMMENT 'Đường dẫn tĩnh quán ăn',
  \`description\` TEXT NULL COMMENT 'Giới thiệu về quán',
  \`address\` VARCHAR(255) NOT NULL COMMENT 'Địa chỉ thực tế',
  \`latitude\` DECIMAL(10, 8) NULL COMMENT 'Tọa độ vĩ độ cho tính khoảng cách',
  \`longitude\` DECIMAL(11, 8) NULL COMMENT 'Tọa độ kinh độ cho tính khoảng cách',
  \`phone\` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Hotline quán',
  \`email\` VARCHAR(191) NULL COMMENT 'Email liên hệ quán',
  \`banner_url\` VARCHAR(500) NULL COMMENT 'Ảnh bìa quán',
  \`logo_url\` VARCHAR(500) NULL COMMENT 'Logo quán',
  \`is_active\` TINYINT(1) DEFAULT 1 COMMENT '1 = Mở cửa nhận đơn, 0 = Đóng cửa',
  \`is_verified\` TINYINT(1) DEFAULT 0 COMMENT 'Đã được Admin duyệt xác thực',
  \`rating_avg\` DECIMAL(3, 2) DEFAULT 5.00 COMMENT 'Điểm đánh giá trung bình (1-5)',
  \`rating_count\` INT UNSIGNED DEFAULT 0 COMMENT 'Số lượt đánh giá',
  \`opening_time\` TIME NOT NULL DEFAULT '07:00:00' COMMENT 'Giờ mở cửa',
  \`closing_time\` TIME NOT NULL DEFAULT '22:00:00' COMMENT 'Giờ đóng cửa',
  \`min_order_amount\` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Giá trị đơn hàng tối thiểu',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_restaurants_owner\` (\`owner_id\`),
  CONSTRAINT \`fk_restaurants_owner\` FOREIGN KEY (\`owner_id\`) REFERENCES \`users\` (\`id\`) ON UPDATE CASCADE,
  INDEX \`idx_restaurants_slug\` (\`slug\`),
  INDEX \`idx_restaurants_rating\` (\`rating_avg\`),
  INDEX \`idx_restaurants_location\` (\`latitude\`, \`longitude\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 4. BẢNG categories (Danh mục món ăn)
-- ===================================================================
DROP TABLE IF EXISTS \`categories\`;
CREATE TABLE \`categories\` (
  \`id\` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`restaurant_id\` BIGINT UNSIGNED NULL COMMENT 'NULL nếu là danh mục chung toàn hệ thống, có ID nếu quán tự tạo',
  \`name\` VARCHAR(100) NOT NULL COMMENT 'Tên danh mục (ví dụ: Cơm, Trà sữa, Burger)',
  \`slug\` VARCHAR(120) NOT NULL UNIQUE COMMENT 'Đường dẫn tĩnh danh mục',
  \`icon_url\` VARCHAR(500) NULL COMMENT 'Icon minh họa',
  \`display_order\` INT DEFAULT 0 COMMENT 'Thứ tự hiển thị',
  \`is_active\` TINYINT(1) DEFAULT 1 COMMENT '1 = Hiển thị, 0 = Ẩn',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_categories_restaurant\` (\`restaurant_id\`),
  CONSTRAINT \`fk_categories_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX \`idx_categories_slug\` (\`slug\`),
  INDEX \`idx_categories_display_order\` (\`display_order\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 5. BẢNG products (Sản phẩm món ăn)
-- ===================================================================
DROP TABLE IF EXISTS \`products\`;
CREATE TABLE \`products\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`product_code\` VARCHAR(50) NULL COMMENT 'Mã sản phẩm (ví dụ: prod-1)',
  \`restaurant_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán bán món này',
  \`category_id\` INT UNSIGNED NOT NULL COMMENT 'FK danh mục món',
  \`name\` VARCHAR(200) NOT NULL COMMENT 'Tên món ăn',
  \`slug\` VARCHAR(220) NOT NULL COMMENT 'Slug món ăn',
  \`description\` TEXT NULL COMMENT 'Mô tả thành phần, khẩu phần',
  \`image_url\` VARCHAR(500) NULL COMMENT 'Ảnh chính món ăn',
  \`base_price\` DECIMAL(12, 2) NOT NULL COMMENT 'Giá gốc',
  \`discount_price\` DECIMAL(12, 2) NULL COMMENT 'Giá khuyến mãi (nếu có)',
  \`is_available\` TINYINT(1) DEFAULT 1 COMMENT '1 = Còn món, 0 = Tạm hết hàng',
  \`is_featured\` TINYINT(1) DEFAULT 0 COMMENT '1 = Món ăn nổi bật / Best-seller',
  \`prep_time_minutes\` INT UNSIGNED DEFAULT 15 COMMENT 'Thời gian chuẩn bị món dự kiến (phút)',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_products_restaurant\` (\`restaurant_id\`),
  KEY \`fk_products_category\` (\`category_id\`),
  CONSTRAINT \`fk_products_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_products_category\` FOREIGN KEY (\`category_id\`) REFERENCES \`categories\` (\`id\`) ON UPDATE CASCADE,
  INDEX \`idx_products_available\` (\`is_available\`),
  INDEX \`idx_products_price\` (\`base_price\`, \`discount_price\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 6. BẢNG product_images (Hình ảnh món ăn chi tiết)
-- ===================================================================
DROP TABLE IF EXISTS \`product_images\`;
CREATE TABLE \`product_images\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`product_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK món ăn',
  \`image_url\` VARCHAR(500) NOT NULL COMMENT 'Đường dẫn ảnh',
  \`is_primary\` TINYINT(1) DEFAULT 0 COMMENT '1 = Ảnh đại diện chính',
  \`display_order\` INT DEFAULT 0 COMMENT 'Thứ tự hiển thị',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY \`fk_product_images_product\` (\`product_id\`),
  CONSTRAINT \`fk_product_images_product\` FOREIGN KEY (\`product_id\`) REFERENCES \`products\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX \`idx_product_images_product\` (\`product_id\`, \`is_primary\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 7. BẢNG addresses (Sổ địa chỉ người dùng)
-- ===================================================================
DROP TABLE IF EXISTS \`addresses\`;
CREATE TABLE \`addresses\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`user_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK liên kết người dùng',
  \`recipient_name\` VARCHAR(150) NOT NULL COMMENT 'Tên người nhận',
  \`phone_number\` VARCHAR(20) NOT NULL COMMENT 'Số điện thoại nhận hàng',
  \`street_address\` VARCHAR(255) NOT NULL COMMENT 'Số nhà, tên đường, tòa nhà',
  \`ward\` VARCHAR(100) NULL COMMENT 'Phường / Xã',
  \`district\` VARCHAR(100) NULL COMMENT 'Quận / Huyện',
  \`city\` VARCHAR(100) NOT NULL COMMENT 'Tỉnh / Thành phố',
  \`latitude\` DECIMAL(10, 8) NULL COMMENT 'Tọa độ vĩ độ người nhận',
  \`longitude\` DECIMAL(11, 8) NULL COMMENT 'Tọa độ kinh độ người nhận',
  \`is_default\` TINYINT(1) DEFAULT 0 COMMENT '1 = Địa chỉ mặc định',
  \`note\` VARCHAR(255) NULL COMMENT 'Ghi chú chỉ dẫn giao hàng (ví dụ: gọi bảo vệ)',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_addresses_user\` (\`user_id\`),
  CONSTRAINT \`fk_addresses_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 8. BẢNG coupons (Mã giảm giá & Khuyến mãi)
-- ===================================================================
DROP TABLE IF EXISTS \`coupons\`;
CREATE TABLE \`coupons\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`user_id\` BIGINT UNSIGNED NULL,
  \`restaurant_id\` BIGINT UNSIGNED NULL COMMENT 'NULL = Mã sàn toàn hệ thống; có ID = Mã riêng quán',
  \`code\` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã voucher (ví dụ: FREESHIP, GIAM50K)',
  \`title\` VARCHAR(200) NOT NULL COMMENT 'Tiêu đề khuyến mãi',
  \`discount_type\` ENUM('percentage', 'fixed') NOT NULL COMMENT 'percentage = Theo %, fixed = Giảm cố định VNĐ',
  \`discount_value\` DECIMAL(12, 2) NOT NULL COMMENT 'Giá trị giảm',
  \`min_order_value\` DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Giá trị đơn tối thiểu để áp dụng',
  \`max_discount_amount\` DECIMAL(12, 2) NULL COMMENT 'Mức giảm tối đa (với mã %)',
  \`usage_limit\` INT UNSIGNED NULL COMMENT 'Lượt dùng tối đa',
  \`usage_count\` INT UNSIGNED DEFAULT 0 COMMENT 'Số lượt đã sử dụng',
  \`start_date\` DATETIME NOT NULL COMMENT 'Thời gian bắt đầu',
  \`end_date\` DATETIME NOT NULL COMMENT 'Thời gian kết thúc',
  \`is_active\` TINYINT(1) DEFAULT 1 COMMENT '1 = Kích hoạt, 0 = Tạm dừng',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_coupons_restaurant\` (\`restaurant_id\`),
  KEY \`fk_coupons_user\` (\`user_id\`),
  CONSTRAINT \`fk_coupons_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_coupons_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX \`idx_coupons_code\` (\`code\`),
  INDEX \`idx_coupons_dates\` (\`start_date\`, \`end_date\`, \`is_active\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 9. BẢNG orders (Đơn hàng chuẩn RDBMS)
-- ===================================================================
DROP TABLE IF EXISTS \`orders\`;
CREATE TABLE \`orders\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`order_code\` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Mã hiển thị (ví dụ: FF-20261109-0001)',
  \`customer_id\` BIGINT UNSIGNED NOT NULL COMMENT 'Người đặt',
  \`restaurant_id\` BIGINT UNSIGNED NOT NULL COMMENT 'Quán tiếp nhận',
  \`shipper_id\` BIGINT UNSIGNED NULL COMMENT 'Tài xế giao hàng',
  \`coupon_id\` BIGINT UNSIGNED NULL COMMENT 'Mã giảm giá áp dụng',
  \`delivery_address\` VARCHAR(255) NOT NULL COMMENT 'Địa chỉ giao hàng thực tế',
  \`recipient_name\` VARCHAR(150) NOT NULL COMMENT 'Tên người nhận tại thời điểm đặt',
  \`recipient_phone\` VARCHAR(20) NOT NULL COMMENT 'SĐT người nhận',
  \`delivery_latitude\` DECIMAL(10, 8) NULL COMMENT 'Tọa độ giao hàng',
  \`delivery_longitude\` DECIMAL(11, 8) NULL COMMENT 'Tọa độ giao hàng',
  \`subtotal\` DECIMAL(12, 2) NOT NULL COMMENT 'Tổng tiền món ăn',
  \`shipping_fee\` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Phí giao hàng',
  \`discount_amount\` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền được giảm',
  \`total_amount\` DECIMAL(12, 2) NOT NULL COMMENT 'Số tiền khách phải trả',
  \`payment_method\` ENUM('cod', 'momo', 'banking', 'vnpay') NOT NULL DEFAULT 'cod',
  \`payment_status\` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  \`order_status\` ENUM('placed', 'confirmed', 'preparing', 'delivering', 'completed', 'cancelled') NOT NULL DEFAULT 'placed',
  \`cancellation_reason\` VARCHAR(255) NULL COMMENT 'Lý do hủy đơn',
  \`note_for_restaurant\` VARCHAR(255) NULL COMMENT 'Lời nhắn cho quán',
  \`note_for_shipper\` VARCHAR(255) NULL COMMENT 'Lời nhắn cho tài xế',
  \`placed_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`confirmed_at\` TIMESTAMP NULL DEFAULT NULL,
  \`delivering_at\` TIMESTAMP NULL DEFAULT NULL,
  \`completed_at\` TIMESTAMP NULL DEFAULT NULL,
  \`cancelled_at\` TIMESTAMP NULL DEFAULT NULL,
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_orders_customer\` (\`customer_id\`),
  KEY \`fk_orders_restaurant\` (\`restaurant_id\`),
  KEY \`fk_orders_shipper\` (\`shipper_id\`),
  KEY \`fk_orders_coupon\` (\`coupon_id\`),
  CONSTRAINT \`fk_orders_customer\` FOREIGN KEY (\`customer_id\`) REFERENCES \`users\` (\`id\`) ON UPDATE CASCADE,
  CONSTRAINT \`fk_orders_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON UPDATE CASCADE,
  CONSTRAINT \`fk_orders_shipper\` FOREIGN KEY (\`shipper_id\`) REFERENCES \`users\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT \`fk_orders_coupon\` FOREIGN KEY (\`coupon_id\`) REFERENCES \`coupons\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX \`idx_orders_customer\` (\`customer_id\`, \`placed_at\`),
  INDEX \`idx_orders_restaurant\` (\`restaurant_id\`, \`order_status\`),
  INDEX \`idx_orders_status\` (\`order_status\`),
  INDEX \`idx_orders_payment\` (\`payment_status\`, \`payment_method\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 10. BẢNG order_items (Chi tiết món ăn trong đơn hàng)
-- ===================================================================
DROP TABLE IF EXISTS \`order_items\`;
CREATE TABLE \`order_items\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`order_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK đơn hàng',
  \`product_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK món ăn',
  \`product_name\` VARCHAR(200) NOT NULL COMMENT 'Lưu lại tên món tại thời điểm đặt',
  \`quantity\` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số lượng',
  \`unit_price\` DECIMAL(12, 2) NOT NULL COMMENT 'Đơn giá sau chiết khấu tại lúc mua',
  \`subtotal\` DECIMAL(12, 2) NOT NULL COMMENT 'quantity * unit_price',
  \`special_instructions\` VARCHAR(255) NULL COMMENT 'Ghi chú đặc biệt cho món',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY \`fk_order_items_order\` (\`order_id\`),
  KEY \`fk_order_items_product\` (\`product_id\`),
  CONSTRAINT \`fk_order_items_order\` FOREIGN KEY (\`order_id\`) REFERENCES \`orders\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_order_items_product\` FOREIGN KEY (\`product_id\`) REFERENCES \`products\` (\`id\`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 11. BẢNG payments (Lịch sử thanh toán giao dịch)
-- ===================================================================
DROP TABLE IF EXISTS \`payments\`;
CREATE TABLE \`payments\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`order_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK đơn hàng',
  \`transaction_code\` VARCHAR(100) NULL UNIQUE COMMENT 'Mã giao dịch từ cổng thanh toán',
  \`amount\` DECIMAL(12, 2) NOT NULL COMMENT 'Số tiền thanh toán',
  \`provider\` ENUM('cod', 'momo', 'banking', 'vnpay') NOT NULL,
  \`status\` ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  \`gateway_response\` LONGTEXT NULL COMMENT 'Log phản hồi từ cổng thanh toán (JSON)',
  \`paid_at\` TIMESTAMP NULL DEFAULT NULL,
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT \`fk_payments_order\` FOREIGN KEY (\`order_id\`) REFERENCES \`orders\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX \`idx_payments_order\` (\`order_id\`),
  INDEX \`idx_payments_status\` (\`status\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 12. BẢNG reviews (Đánh giá món ăn & Quán ăn)
-- ===================================================================
DROP TABLE IF EXISTS \`reviews\`;
CREATE TABLE \`reviews\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`order_id\` BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'Chỉ đánh giá sau khi hoàn tất đơn',
  \`user_id\` BIGINT UNSIGNED NOT NULL COMMENT 'Người đánh giá',
  \`restaurant_id\` BIGINT UNSIGNED NOT NULL COMMENT 'Quán được đánh giá',
  \`rating\` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Điểm từ 1 đến 5 sao',
  \`comment\` TEXT NULL COMMENT 'Nội dung nhận xét',
  \`reply_from_restaurant\` TEXT NULL COMMENT 'Quán phản hồi lại đánh giá của khách',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_reviews_order\` (\`order_id\`),
  KEY \`fk_reviews_user\` (\`user_id\`),
  KEY \`fk_reviews_restaurant\` (\`restaurant_id\`),
  CONSTRAINT \`fk_reviews_order\` FOREIGN KEY (\`order_id\`) REFERENCES \`orders\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_reviews_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_reviews_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`chk_reviews_rating\` CHECK (\`rating\` >= 1 AND \`rating\` <= 5),
  INDEX \`idx_reviews_restaurant\` (\`restaurant_id\`),
  INDEX \`idx_reviews_rating\` (\`rating\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 13. BẢNG carts (Giỏ hàng của người dùng)
-- ===================================================================
DROP TABLE IF EXISTS \`carts\`;
CREATE TABLE \`carts\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`user_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK người dùng sở hữu giỏ',
  \`restaurant_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán ăn của giỏ hàng hiện tại',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_carts_restaurant\` (\`restaurant_id\`),
  CONSTRAINT \`fk_carts_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_carts_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY \`uq_user_restaurant_cart\` (\`user_id\`, \`restaurant_id\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 14. BẢNG cart_items (Chi tiết món ăn trong giỏ hàng)
-- ===================================================================
DROP TABLE IF EXISTS \`cart_items\`;
CREATE TABLE \`cart_items\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`cart_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK giỏ hàng',
  \`product_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK sản phẩm trong giỏ',
  \`quantity\` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số lượng đặt',
  \`unit_price\` DECIMAL(12, 2) NOT NULL COMMENT 'Đơn giá tại thời điểm đưa vào giỏ',
  \`notes\` VARCHAR(255) NULL COMMENT 'Ghi chú cho món ăn (ví dụ: ít ngọt, không cay)',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_cart_items_cart\` (\`cart_id\`),
  KEY \`fk_cart_items_product\` (\`product_id\`),
  CONSTRAINT \`fk_cart_items_cart\` FOREIGN KEY (\`cart_id\`) REFERENCES \`carts\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_cart_items_product\` FOREIGN KEY (\`product_id\`) REFERENCES \`products\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 15. BẢNG notifications (Thông báo hệ thống cho người dùng)
-- ===================================================================
DROP TABLE IF EXISTS \`notifications\`;
CREATE TABLE \`notifications\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`user_id\` BIGINT UNSIGNED NOT NULL COMMENT 'Người nhận thông báo',
  \`title\` VARCHAR(200) NOT NULL COMMENT 'Tiêu đề thông báo',
  \`content\` TEXT NOT NULL COMMENT 'Nội dung chi tiết',
  \`type\` ENUM('order', 'promotion', 'system') DEFAULT 'system' COMMENT 'Phân loại thông báo',
  \`is_read\` TINYINT(1) DEFAULT 0 COMMENT '0 = Chưa đọc, 1 = Đã đọc',
  \`metadata\` LONGTEXT NULL COMMENT 'Dữ liệu JSON kèm theo (order_id, url...)',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY \`fk_notifications_user\` (\`user_id\`),
  CONSTRAINT \`fk_notifications_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX \`idx_notifications_user\` (\`user_id\`, \`is_read\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 16. BẢNG staff_assignments (Phân công nhân viên quản lý quán)
-- ===================================================================
DROP TABLE IF EXISTS \`staff_assignments\`;
CREATE TABLE \`staff_assignments\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`restaurant_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK quán',
  \`user_id\` BIGINT UNSIGNED NOT NULL COMMENT 'FK nhân viên',
  \`role_in_restaurant\` ENUM('manager', 'chef', 'staff') DEFAULT 'staff' COMMENT 'Vị trí trong quán',
  \`is_active\` TINYINT(1) DEFAULT 1 COMMENT '1 = Đang làm việc, 0 = Đã nghỉ',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY \`fk_staff_user\` (\`user_id\`),
  CONSTRAINT \`fk_staff_restaurant\` FOREIGN KEY (\`restaurant_id\`) REFERENCES \`restaurants\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT \`fk_staff_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY \`uq_restaurant_staff\` (\`restaurant_id\`, \`user_id\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 17. BẢNG realtime_orders (Đơn hàng Realtime Socket.IO & Checkout)
-- ===================================================================
DROP TABLE IF EXISTS \`realtime_orders\`;
CREATE TABLE \`realtime_orders\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`order_code\` VARCHAR(30) NOT NULL UNIQUE,
  \`user_id\` BIGINT UNSIGNED NOT NULL,
  \`order_data\` LONGTEXT NOT NULL COMMENT 'Payload đơn hàng định dạng JSON',
  \`status\` ENUM('awaiting_payment', 'payment_failed', 'pending', 'preparing', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT \`fk_realtime_orders_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE,
  INDEX \`idx_realtime_orders_user\` (\`user_id\`, \`id\`),
  INDEX \`idx_realtime_orders_status\` (\`status\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 18. BẢNG realtime_payments (Giao dịch thanh toán Realtime)
-- ===================================================================
DROP TABLE IF EXISTS \`realtime_payments\`;
CREATE TABLE \`realtime_payments\` (
  \`order_code\` VARCHAR(30) NOT NULL PRIMARY KEY,
  \`payment_method\` ENUM('banking', 'momo', 'vnpay') NOT NULL,
  \`request_id\` VARCHAR(64) NOT NULL,
  \`amount\` BIGINT UNSIGNED NOT NULL,
  \`status\` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  \`provider_transaction_id\` VARCHAR(100) NULL UNIQUE,
  \`payment_url\` TEXT NULL,
  \`qr_url\` TEXT NULL,
  \`expires_at\` DATETIME NULL,
  \`retry_until\` DATETIME NULL,
  \`paid_at\` TIMESTAMP NULL DEFAULT NULL,
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT \`fk_realtime_payments_order\` FOREIGN KEY (\`order_code\`) REFERENCES \`realtime_orders\` (\`order_code\`) ON DELETE CASCADE,
  INDEX \`idx_realtime_payments_status\` (\`status\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 19. BẢNG chat_messages (Tin nhắn hỗ trợ trực tiếp CSKH Realtime)
-- ===================================================================
DROP TABLE IF EXISTS \`chat_messages\`;
CREATE TABLE \`chat_messages\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`customer_id\` BIGINT UNSIGNED NOT NULL,
  \`sender_id\` BIGINT UNSIGNED NULL,
  \`sender_role\` ENUM('admin', 'customer', 'ai') NOT NULL,
  \`message\` VARCHAR(1000) NOT NULL,
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY \`fk_chat_sender\` (\`sender_id\`),
  CONSTRAINT \`fk_chat_customer\` FOREIGN KEY (\`customer_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE,
  CONSTRAINT \`fk_chat_sender\` FOREIGN KEY (\`sender_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE,
  INDEX \`idx_chat_customer_id\` (\`customer_id\`, \`id\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 20. BẢNG chat_automation (Cấu hình tự động trả lời CSKH)
-- ===================================================================
DROP TABLE IF EXISTS \`chat_automation\`;
CREATE TABLE \`chat_automation\` (
  \`customer_id\` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  \`paused_until\` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT \`fk_chat_automation_customer\` FOREIGN KEY (\`customer_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 21. BẢNG website_content (Đồng bộ nội dung động CMS)
-- ===================================================================
DROP TABLE IF EXISTS \`website_content\`;
CREATE TABLE \`website_content\` (
  \`collection_name\` VARCHAR(50) NOT NULL PRIMARY KEY,
  \`content\` LONGTEXT NOT NULL COMMENT 'Nội dung CMS lưu dạng JSON',
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 22. BẢNG ai_chat_sessions (Phiên hội thoại Trợ lý ảo FASTFOOD AI)
-- ===================================================================
DROP TABLE IF EXISTS \`ai_chat_sessions\`;
CREATE TABLE \`ai_chat_sessions\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`session_id\` VARCHAR(64) NOT NULL UNIQUE,
  \`user_id\` BIGINT UNSIGNED NULL,
  \`customer_name\` VARCHAR(150) NULL DEFAULT 'Khách hàng',
  \`title\` VARCHAR(255) NOT NULL DEFAULT 'Hội thoại mới',
  \`is_active\` TINYINT(1) NOT NULL DEFAULT 1,
  \`ai_paused_until\` DATETIME NULL DEFAULT NULL,
  \`ai_force_auto\` TINYINT(1) NOT NULL DEFAULT 0,
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX \`idx_chat_session_user\` (\`user_id\`),
  INDEX \`idx_chat_session_updated\` (\`updated_at\`),
  INDEX \`idx_chat_session_created\` (\`created_at\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 23. BẢNG ai_chat_messages (Lịch sử tin nhắn Trợ lý ảo FASTFOOD AI)
-- ===================================================================
DROP TABLE IF EXISTS \`ai_chat_messages\`;
CREATE TABLE \`ai_chat_messages\` (
  \`id\` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  \`session_id\` VARCHAR(64) NOT NULL,
  \`user_id\` BIGINT UNSIGNED NULL,
  \`sender\` ENUM('user', 'assistant', 'admin', 'system') NOT NULL DEFAULT 'user',
  \`message\` TEXT NOT NULL,
  \`metadata\` LONGTEXT NULL COMMENT 'JSON lưu thông tin món ăn, gợi ý hoặc tra cứu đơn hàng',
  \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX \`idx_chat_msg_session\` (\`session_id\`),
  INDEX \`idx_chat_msg_user\` (\`user_id\`),
  INDEX \`idx_chat_msg_created\` (\`created_at\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
`;

// ========================================================
// 2. SEED DATA DEFINITION
// ========================================================

// Category map
const categoryMap = {
  'all': 1,
  'com': 2,
  'burger': 3,
  'tra-sua': 4,
  'pizza': 5,
  'an-vat': 6,
  'healthy': 7,
  'cafe': 8,
  'sushi': 9,
  'com-mon-viet': 2,
  'burger-ga-ran': 3,
  'tra-sua-do-uong': 4,
  'pizza-my-y': 5,
  'an-vat-trang-mieng': 6
};

// Generate Seeds SQL
function generateSeedsSql() {
  const lines = [];

  lines.push(`
-- 1. Thêm vai trò hệ thống (roles)
INSERT INTO \`roles\` (\`id\`, \`name\`, \`description\`) VALUES
(1, 'admin', 'Quản trị viên toàn quyền hệ thống'),
(2, 'customer', 'Khách hàng đặt món ăn'),
(3, 'restaurant_owner', 'Chủ cửa hàng, quán ăn đối tác'),
(4, 'shipper', 'Tài xế giao nhận đơn hàng')
ON DUPLICATE KEY UPDATE \`description\` = VALUES(\`description\`);

-- 2. Thêm tài khoản mẫu (Mật khẩu mặc định: Admin@123456)
INSERT INTO \`users\` (\`id\`, \`role_id\`, \`full_name\`,\`email\`, \`phone\`, \`address\`, \`password_hash\`, \`status\`) VALUES
(1, 1, 'Hệ Thống Quản Trị', 'admin@fastfood.vn', '0901000001', 'Tòa nhà FASTFOOD, 10 Mai Hắc Đế, Buôn Ma Thuột', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(2, 3, 'Nguyễn Văn Chủ Quán', 'owner@fastfood.vn', '0902000002', '25 Nguyễn Tất Thành, Buôn Ma Thuột', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(3, 2, 'Trần Khách Hàng', 'customer@fastfood.vn', '0903000003', '72 Lê Thánh Tôn, Bến Nghé, Quận 1', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(4, 4, 'Lê Tài Xế Giao Hàng', 'shipper@fastfood.vn', '0904000004', 'Buôn Ma Thuột, Đắk Lắk', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(5, 2, 'Nguyễn Hiệp', 'nghiep2007@gmial.com', '0862033905', 'Buôn Ma Thuột, Đắk Lắk', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active'),
(6, 2, 'Khách hàng Demo', 'nguyenhiep@gmail.com', '0908888999', 'Buôn Ma Thuột, Đắk Lắk', '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'active')
ON DUPLICATE KEY UPDATE
  \`role_id\` = VALUES(\`role_id\`),
  \`full_name\` = VALUES(\`full_name\`),
  \`password_hash\` = VALUES(\`password_hash\`),
  \`status\` = VALUES(\`status\`);

-- 3. Danh mục món ăn (categories)
INSERT INTO \`categories\` (\`id\`, \`restaurant_id\`, \`name\`, \`slug\`, \`icon_url\`, \`display_order\`, \`is_active\`) VALUES
(1, NULL, 'Tất Cả Món', 'all', '/assets/images/categories/all.svg', 0, 1),
(2, NULL, 'Cơm & Món Việt', 'com', '/assets/images/categories/rice.svg', 1, 1),
(3, NULL, 'Burger & Gà Rán', 'burger', '/assets/images/categories/burger.svg', 2, 1),
(4, NULL, 'Trà Sữa & Đồ Uống', 'tra-sua', '/assets/images/categories/drink.svg', 3, 1),
(5, NULL, 'Pizza & Mỳ Ý', 'pizza', '/assets/images/categories/pizza.svg', 4, 1),
(6, NULL, 'Ăn Vặt & Bánh', 'an-vat', '/assets/images/categories/snack.svg', 5, 1),
(7, NULL, 'Salad & Healthy', 'healthy', '/assets/images/categories/healthy.svg', 6, 1),
(8, NULL, 'Cà Phê & Trà', 'cafe', '/assets/images/categories/coffee.svg', 7, 1),
(9, NULL, 'Sushi & Món Nhật', 'sushi', '/assets/images/categories/sushi.svg', 8, 1)
ON DUPLICATE KEY UPDATE \`name\` = VALUES(\`name\`), \`icon_url\` = VALUES(\`icon_url\`);
`);

  // 4. Restaurants (13 quán từ data.js)
  lines.push('-- 4. Danh sách 13 Nhà hàng / Quán ăn đối tác');
  lines.push('INSERT INTO `restaurants` (`id`, `owner_id`, `name`, `slug`, `description`, `address`, `latitude`, `longitude`, `phone`, `email`, `banner_url`, `logo_url`, `is_active`, `is_verified`, `rating_avg`, `rating_count`, `opening_time`, `closing_time`, `min_order_amount`) VALUES');
  
  const resRows = data.restaurants.map((res, index) => {
    const id = index + 1; // 1 to 13
    const tags = res.tags ? res.tags.join(', ') : '';
    const phone = res.phone || '0262' + String(3888000 + id);
    const email = 'res' + id + '@fastfood.vn';
    const banner = res.banner || res.image || '';
    const logo = res.logo || banner;
    const rating = res.rating || 4.8;
    const reviewCount = res.reviewCount || 150;
    const minOrder = res.minOrder || 30000;
    const lat = 12.679500 + (index * 0.002);
    const lng = 108.050500 + (index * 0.0015);
    
    return `(${id}, 2, ${esc(res.name)}, ${esc(res.id)}, ${esc(tags)}, ${esc(res.address)}, ${lat.toFixed(6)}, ${lng.toFixed(6)}, ${esc(phone)}, ${esc(email)}, ${esc(banner)}, ${esc(logo)}, 1, 1, ${rating}, ${reviewCount}, '07:00:00', '22:30:00', ${minOrder.toFixed(2)})`;
  });
  lines.push(resRows.join(',\n') + '\nON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`), `banner_url` = VALUES(`banner_url`), `rating_avg` = VALUES(`rating_avg`);\n');

  // 5. Products (260 món ăn từ data.js)
  lines.push('-- 5. Danh sách 260 Món ăn (Đầy đủ 13 Quán x 20 Món, Hình ảnh thật chuẩn xác)');
  lines.push('INSERT INTO `products` (`id`, `product_code`, `restaurant_id`, `category_id`, `name`, `slug`, `description`, `image_url`, `base_price`, `discount_price`, `is_available`, `is_featured`, `prep_time_minutes`) VALUES');

  const prodRows = data.products.map((prod, index) => {
    const id = index + 1; // 1 to 260
    // restaurant index from res-1..res-13
    const resNum = parseInt(prod.restaurantId.replace('res-', ''), 10) || 1;
    const catId = categoryMap[prod.category] || 1;
    const basePrice = prod.oldPrice || prod.price;
    const discountPrice = prod.price;
    const isFeatured = prod.isBestSeller ? 1 : (prod.isFlashDeal ? 1 : 0);
    let prepTime = 15;
    if (prod.time) {
      const match = prod.time.match(/\d+/);
      if (match) prepTime = parseInt(match[0], 10);
    }

    return `(${id}, ${esc(prod.id)}, ${resNum}, ${catId}, ${esc(prod.name)}, ${esc(prod.slug)}, ${esc(prod.description || '')}, ${esc(prod.image)}, ${basePrice.toFixed(2)}, ${discountPrice.toFixed(2)}, 1, ${isFeatured}, ${prepTime})`;
  });

  lines.push(prodRows.join(',\n') + '\nON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `image_url` = VALUES(`image_url`), `base_price` = VALUES(`base_price`), `discount_price` = VALUES(`discount_price`);\n');

  // 6. Product images
  lines.push('-- 6. Hình ảnh đại diện chính của sản phẩm');
  lines.push('INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `is_primary`, `display_order`) VALUES');
  const imgRows = data.products.map((prod, index) => {
    const id = index + 1;
    return `(${id}, ${id}, ${esc(prod.image)}, 1, 1)`;
  });
  lines.push(imgRows.join(',\n') + '\nON DUPLICATE KEY UPDATE `image_url` = VALUES(`image_url`);\n');

  // 7. Sổ địa chỉ mẫu
  lines.push(`
-- 7. Sổ địa chỉ giao hàng mẫu
INSERT INTO \`addresses\` (\`id\`, \`user_id\`, \`recipient_name\`, \`phone_number\`, \`street_address\`, \`ward\`, \`district\`, \`city\`, \`latitude\`, \`longitude\`, \`is_default\`, \`note\`) VALUES
(1, 3, 'Trần Khách Hàng', '0903000003', '72 Lê Thánh Tôn', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 10.776530, 106.700980, 1, 'Giao giờ hành chính, gọi trước khi đến'),
(2, 5, 'Nguyễn Hiệp', '0862033905', '10 Mai Hắc Đế, Phường Tân Thành', 'Tân Thành', 'TP. Buôn Ma Thuột', 'Đắk Lắk', 12.679500, 108.050500, 1, 'Giao tận cửa nhà')
ON DUPLICATE KEY UPDATE \`recipient_name\` = VALUES(\`recipient_name\`);

-- 8. Mã giảm giá mẫu (coupons)
INSERT INTO \`coupons\` (\`id\`, \`user_id\`, \`restaurant_id\`, \`code\`, \`title\`, \`discount_type\`, \`discount_value\`, \`min_order_value\`, \`max_discount_amount\`, \`usage_limit\`, \`usage_count\`, \`start_date\`, \`end_date\`, \`is_active\`) VALUES
(1, NULL, NULL, 'FASTFOODHELLO', 'Giảm 20K cho đơn hàng đầu tiên', 'fixed', 20000.00, 50000.00, 20000.00, 500, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(2, NULL, NULL, 'FREESHIP', 'Miễn phí giao hàng đơn từ 100K', 'fixed', 15000.00, 100000.00, 15000.00, 1000, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(3, NULL, NULL, 'FASTFOOD20', 'Ưu đãi đặt món nhanh giảm 20.000đ', 'fixed', 20000.00, 60000.00, 20000.00, 500, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(4, NULL, NULL, 'GIAM50K', 'Giảm ngay 50.000đ cho đơn tiệc đông người', 'fixed', 50000.00, 20000.00, 50000.00, 300, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1)
ON DUPLICATE KEY UPDATE \`title\` = VALUES(\`title\`), \`discount_value\` = VALUES(\`discount_value\`);

-- 9. Thông báo mẫu (notifications)
INSERT INTO \`notifications\` (\`id\`, \`user_id\`, \`title\`, \`content\`, \`type\`, \`is_read\`, \`metadata\`) VALUES
(1, 3, 'Chào mừng bạn đến với FASTFOOD! 🎉', 'Nhận ngay mã FASTFOODHELLO giảm 20K cho đơn hàng đầu tiên của bạn hôm nay!', 'promotion', 0, '{"code":"FASTFOODHELLO"}'),
(2, 5, 'Chào mừng Nguyễn Hiệp! 🎉', 'Khám phá hơn 260 món ăn nóng hổi từ các quán ngon hàng đầu Buôn Ma Thuột!', 'system', 0, NULL)
ON DUPLICATE KEY UPDATE \`title\` = VALUES(\`title\`);

-- 10. Trợ lý ảo AI - Hội thoại mẫu (ai_chat_sessions & ai_chat_messages)
INSERT INTO \`ai_chat_sessions\` (\`id\`, \`session_id\`, \`user_id\`, \`customer_name\`, \`title\`, \`is_active\`) VALUES
(1, 'session-welcome-demo', 1, 'Hệ Thống Quản Trị', 'Tư vấn món ăn ngon hôm nay', 1)
ON DUPLICATE KEY UPDATE \`title\` = VALUES(\`title\`);

INSERT INTO \`ai_chat_messages\` (\`id\`, \`session_id\`, \`user_id\`, \`sender\`, \`message\`, \`metadata\`) VALUES
(1, 'session-welcome-demo', 1, 'assistant', 'Xin chào bạn! Mình là Trợ lý AI FASTFOOD 🤖. Mình có thể giúp bạn tìm món ngon theo sở thích, gợi ý combo tiết kiệm hoặc kiểm tra đơn hàng!', NULL)
ON DUPLICATE KEY UPDATE \`message\` = VALUES(\`message\`);
`);

  return lines.join('\n');
}

// ========================================================
// 3. WRITE ALL FILES
// ========================================================

const seedsSqlContent = generateSeedsSql();

// File 1: database/schema.sql
const fullSchemaSql = `-- ===================================================================
-- CƠ SỞ DỮ LIỆU DỰ ÁN FASTFOOD (FOOD DELIVERY PLATFORM)
-- Phiên bản: 2.0.0 (Chuẩn hóa toàn diện 23 bảng + AI Chat + Realtime)
-- Mã hóa: utf8mb4 / utf8mb4_unicode_ci
-- ===================================================================

CREATE DATABASE IF NOT EXISTS \`fastfood_db\`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE \`fastfood_db\`;

-- Tắt kiểm tra khóa ngoại để tạo bảng sạch sẽ
SET FOREIGN_KEY_CHECKS = 0;

${schemaTablesSql}

-- Bật lại kiểm tra khóa ngoại sau khi tạo bảng
SET FOREIGN_KEY_CHECKS = 1;
`;

// File 2: database/seeds.sql
const fullSeedsSql = `-- ===================================================================
-- FASTFOOD SEED DATA (DỮ LIỆU KHỞI TẠO ĐẦY ĐỦ 100% HIỆN TẠI)
-- Bao gồm: Phân quyền, Người dùng, 13 Quán ăn, 260 Món ăn, Voucher, Thông báo
-- ===================================================================

USE \`fastfood_db\`;

SET FOREIGN_KEY_CHECKS = 0;

${seedsSqlContent}

SET FOREIGN_KEY_CHECKS = 1;
`;

// File 3: database/fastfood_db.sql (All-in-one import file)
const allInOneSql = `-- ===================================================================
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
CREATE DATABASE IF NOT EXISTS \`fastfood_db\`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE \`fastfood_db\`;

${schemaTablesSql}

-- ===================================================================
-- DỮ LIỆU KHỞI TẠO (SEEDS DATA)
-- ===================================================================

${seedsSqlContent}

-- Bật lại kiểm tra khóa ngoại sau khi hoàn tất
SET FOREIGN_KEY_CHECKS = 1;

-- Hoàn tất transaction
COMMIT;
`;

fs.writeFileSync(path.resolve(__dirname, '../../database/schema.sql'), fullSchemaSql, 'utf8');
console.log('Created database/schema.sql');

fs.writeFileSync(path.resolve(__dirname, '../../database/seeds.sql'), fullSeedsSql, 'utf8');
console.log('Created database/seeds.sql');

fs.writeFileSync(path.resolve(__dirname, '../../database/fastfood_db.sql'), allInOneSql, 'utf8');
console.log('Created database/fastfood_db.sql');

console.log('All database files generated successfully!');
