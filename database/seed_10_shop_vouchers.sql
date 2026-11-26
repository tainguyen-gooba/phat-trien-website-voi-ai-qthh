-- Seed: Bổ sung 10 Voucher Shop mới đa dạng 4 loại, hạng thành viên & phạm vi món áp dụng
INSERT INTO vouchers (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, applicable_scope, applicable_category_ids, applicable_keywords, applicable_label, is_public, start_date, end_date, is_active)
VALUES
('TRUALUNCH10', 'Giảm 10% Bữa Trưa Năng Lượng (Tối đa 25.000đ)', 'Ưu đãi đặt món buổi trưa áp dụng cho mọi thành viên FASTFOOD', 'percentage', 10.00, 50000.00, 25000.00, 1000, 0, 1, 'ALL', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('SNACK15K', 'Giảm 15.000đ cho đơn ăn vặt từ 45.000đ', 'Thỏa thích trà sữa, khoai tây chiên, gà rán với ưu đãi giảm ngay 15K', 'fixed', 15000.00, 45000.00, 15000.00, 1200, 0, 1, 'ALL', 'specific', '32,34,36', 'khoai tây,khoai lang,vòng hành tây,phô mai que,phô mai viên,gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà viên,trà sữa,trà chanh,trà đào,kem tươi,ăn vặt,snack', 'Đồ Ăn Vặt, Trà Sữa & Gà Rán', 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('FREESHIP25', 'Freeship Siêu Tốc (Tối đa 25.000đ)', 'Miễn phí giao hàng cho đơn hàng từ 70.000đ trên toàn khu vực', 'free_shipping', 25000.00, 70000.00, 25000.00, 1500, 0, 1, 'ALL', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('GARANBOGO', 'Mua 1 Tặng 1 - Gà Rán Giòn Cay Kèm Nước', 'Tặng ngay phần ăn phụ hoặc nước mát trị giá 30.000đ cho đơn từ 120.000đ', 'bogo', 30000.00, 120000.00, 30000.00, 400, 0, 1, 'ALL', 'specific', NULL, 'gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà miếng,da gà,combo gà,burger gà', 'Các món Gà Rán', 1, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1),
('BRONZE12', 'Chào Hạng Đồng - Giảm 12% tối đa 30.000đ', 'Ưu đãi dành cho thành viên từ Hạng Đồng (Bronze) trở lên', 'percentage', 12.00, 60000.00, 30000.00, 800, 0, 1, 'BRONZE', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('SILVER25K', 'Đặc quyền Hạng Bạc - Giảm ngay 25.000đ', 'Ưu đãi tiền mặt dành riêng cho thành viên từ Hạng Bạc (Silver) trở lên', 'fixed', 25000.00, 90000.00, 25000.00, 600, 0, 1, 'SILVER', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('SHIPBAC0D', 'Freeship Đặc Quyền Bạc (Tối đa 20.000đ)', 'Hỗ trợ phí giao hàng 0đ cho đơn hàng từ 50.000đ từ Hạng Bạc+', 'free_shipping', 20000.00, 50000.00, 20000.00, 700, 0, 1, 'SILVER', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('GOLDCHICKEN', 'Đặc quyền Vàng - Mua 1 Tặng 1 Combo Gà', 'Ưu đãi Mua 1 Tặng 1 trị giá 45.000đ cho thành viên Hạng Vàng trở lên', 'bogo', 45000.00, 140000.00, 45000.00, 350, 0, 1, 'GOLD', 'specific', NULL, 'gà rán,cánh gà,đùi gà rán,gà giòn,gà popcorn,gà miếng,da gà,combo gà,burger gà', 'Gà Rán & Combo Gà', 1, NOW(), DATE_ADD(NOW(), INTERVAL 75 DAY), 1),
('TIECTOI25', 'Đại Tiệc Cuối Tuần - Giảm 25% tối đa 70.000đ', 'Giảm sâu cho hóa đơn nhóm & gia đình từ 180.000đ (Hạng Vàng+)', 'percentage', 25.00, 180000.00, 70000.00, 400, 0, 1, 'GOLD', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1),
('DIAMOND30P', 'VIP Kim Cương - Giảm 30% tối đa 100.000đ', 'Siêu đặc quyền giảm giá lớn nhất dành riêng cho thành viên Hạng Kim Cương', 'percentage', 30.00, 200000.00, 100000.00, 200, 0, 1, 'DIAMOND', 'all', NULL, NULL, NULL, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    voucher_type = VALUES(voucher_type),
    discount_value = VALUES(discount_value),
    min_order_value = VALUES(min_order_value),
    max_discount_amount = VALUES(max_discount_amount),
    usage_limit = VALUES(usage_limit),
    user_limit = VALUES(user_limit),
    min_member_tier = VALUES(min_member_tier),
    applicable_scope = VALUES(applicable_scope),
    applicable_category_ids = VALUES(applicable_category_ids),
    applicable_keywords = VALUES(applicable_keywords),
    applicable_label = VALUES(applicable_label),
    is_public = 1,
    is_active = 1;
