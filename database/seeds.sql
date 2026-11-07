-- ===================================================================
-- FASTFOOD SEED DATA (DỮ LIỆU KHỞI TẠO BAN ĐẦU)
-- ===================================================================

USE `fastfood_db`;

-- 1. Thêm vai trò hệ thống
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Quản trị viên toàn quyền hệ thống'),
(2, 'customer', 'Khách hàng đặt món ăn'),
(3, 'restaurant_owner', 'Chủ cửa hàng, quán ăn đối tác'),
(4, 'shipper', 'Tài xế giao nhận đơn hàng')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. Thêm tài khoản mẫu (Mật khẩu mặc định: Admin@123456)
-- Hash bcrypt cho mật khẩu mẫu Admin@123456
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

-- 3. Thêm danh mục món ăn
INSERT INTO `categories` (`id`, `restaurant_id`, `name`, `slug`, `icon_url`, `display_order`, `is_active`) VALUES
(1, NULL, 'Cơm & Món Việt', 'com-mon-viet', '/assets/images/categories/rice.svg', 1, 1),
(2, NULL, 'Burger & Gà Rán', 'burger-ga-ran', '/assets/images/categories/burger.svg', 2, 1),
(3, NULL, 'Trà Sữa & Đồ Uống', 'tra-sua-do-uong', '/assets/images/categories/drink.svg', 3, 1),
(4, NULL, 'Pizza & Mỳ Ý', 'pizza-my-y', '/assets/images/categories/pizza.svg', 4, 1),
(5, NULL, 'Ăn Vặt & Tráng Miệng', 'an-vat-trang-mieng', '/assets/images/categories/snack.svg', 5, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Thêm quán ăn mẫu
INSERT INTO `restaurants` (`id`, `owner_id`, `name`, `slug`, `description`, `address`, `latitude`, `longitude`, `phone`, `email`, `is_active`, `is_verified`, `rating_avg`, `rating_count`, `opening_time`, `closing_time`, `min_order_amount`) VALUES
(1, 2, 'Burger King Station - Buôn Ma Thuột', 'burger-king-station', 'Chuyên các loại burger bò nướng lửa, gà giòn sốt cay và khoai tây lắc.', '25 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk', 12.679500, 108.050500, '0262388888', 'burgerking@fastfood.vn', 1, 1, 4.85, 120, '08:00:00', '23:00:00', 30000.00),
(2, 2, 'Tiệm Cơm Tấm Sài Gòn Xưa - Buôn Ma Thuột', 'tiem-com-tam-sai-gon-xua', 'Cơm tấm sườn bì chả nướng than hoa gia truyền đậm đà.', '68 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk', 12.666100, 108.045500, '0262388889', 'comtam@fastfood.vn', 1, 1, 4.90, 350, '06:30:00', '21:30:00', 25000.00)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `address` = VALUES(`address`),
  `latitude` = VALUES(`latitude`),
  `longitude` = VALUES(`longitude`),
  `phone` = VALUES(`phone`);

-- 5. Thêm món ăn mẫu
INSERT INTO `products` (`id`, `restaurant_id`, `category_id`, `name`, `slug`, `description`, `base_price`, `discount_price`, `is_available`, `is_featured`, `prep_time_minutes`) VALUES
(1, 1, 2, 'Burger Bò Nướng Phô Mai Tan Chảy', 'burger-bo-nuong-pho-mai', 'Thịt bò Úc 100% nướng than hoa, kèm 2 lát phô mai Cheddar béo ngậy.', 69000.00, 59000.00, 1, 1, 12),
(2, 1, 2, 'Gà Rán Giòn Sốt Cay Hàn Quốc', 'ga-ran-gion-sot-cay', '2 miếng gà giòn rụm phủ sốt cay ngọt chuẩn vị Seoul.', 75000.00, 65000.00, 1, 1, 15),
(3, 1, 3, 'Trà Sữa Trân Châu Hoàng Gia', 'tra-sua-tran-chau-hoang-gia', 'Trà đen Ceylon thơm đậm kết hợp sữa tươi thanh trùng và trân châu đường đen.', 45000.00, 39000.00, 1, 1, 5),
(4, 2, 1, 'Cơm Tấm Sườn Bì Chả Trứng Ốp La', 'com-tam-suon-bi-cha-trung', 'Dĩa cơm tấm đầy đủ sườn cốt lết dày dặn, bì chả nóng hổi, mỡ hành tép mỡ giòn rụm.', 55000.00, 49000.00, 1, 1, 10)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 6. Thêm mã giảm giá mẫu
INSERT INTO `coupons` (`id`, `restaurant_id`, `code`, `title`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `usage_count`, `start_date`, `end_date`, `is_active`) VALUES
(1, NULL, 'FASTFOODHELLO', 'Giảm 20K cho đơn hàng đầu tiên', 'fixed', 20000.00, 50000.00, 20000.00, 500, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(2, NULL, 'FREESHIP', 'Miễn phí giao hàng đơn từ 100K', 'fixed', 15000.00, 100000.00, 15000.00, 1000, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
