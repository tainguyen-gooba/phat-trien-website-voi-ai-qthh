# THIẾT KẾ CƠ SỞ DỮ LIỆU FASTFOOD (16 BẢNG)

Hệ thống cơ sở dữ liệu MySQL chuẩn hóa dạng chuẩn 3NF, sử dụng `InnoDB`, bộ mã `utf8mb4_unicode_ci`.

## 1. Danh sách 16 bảng
1. **`roles`**: Quản lý nhóm quyền (admin, customer, restaurant_owner, shipper).
2. **`users`**: Tài khoản người dùng toàn hệ thống, mã hóa mật khẩu bcrypt.
3. **`restaurants`**: Quán ăn/đối tác kinh doanh (tọa độ GPS, giờ hoạt động, đánh giá sao).
4. **`categories`**: Danh mục món ăn (danh mục sàn hoặc riêng của quán).
5. **`products`**: Danh sách món ăn/nước uống (giá niêm yết, giá giảm, trạng thái còn món).
6. **`product_images`**: Thư viện ảnh của từng món ăn.
7. **`addresses`**: Sổ địa chỉ giao nhận của khách hàng (kèm tọa độ GPS để tính ship).
8. **`carts`**: Giỏ hàng độc lập theo từng quán ăn của người dùng.
9. **`cart_items`**: Chi tiết từng món ăn trong giỏ và ghi chú sở thích.
10. **`coupons`**: Mã giảm giá toàn sàn hoặc riêng của từng quán ăn.
11. **`orders`**: Đơn hàng đặt món (mã đơn hiển thị, trạng thái quy trình đơn).
12. **`order_items`**: Chi tiết món trong đơn hàng (lưu cứng đơn giá tại thời điểm đặt).
13. **`payments`**: Giao dịch thanh toán (COD, VNPay, MoMo, Ngân hàng).
14. **`reviews`**: Đánh giá và nhận xét của khách hàng cho quán sau khi hoàn tất đơn.
15. **`notifications`**: Thông báo đẩy hệ thống (đơn hàng, khuyến mãi, tài khoản).
16. **`staff_assignments`**: Phân công vai trò nhân sự quản trị quán (quản lý, bếp, nhân viên).

## 2. Chiến lược Indexing tối ưu
- Khóa chính tự tăng `BIGINT UNSIGNED` hoặc `INT UNSIGNED`.
- Toàn bộ trường `slug`, `email`, `phone`, `order_code`, `code` được đánh `UNIQUE INDEX`.
- Các cột lọc trạng thái (`status`, `is_available`, `is_active`) và Foreign Keys đều có Single hoặc Composite Index.

