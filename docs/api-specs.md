# ĐẶC TẢ RESTFUL API (FASTFOOD API SPECIFICATIONS)

## 1. Chuẩn phản hồi JSON (Standard Response Format)
Tất cả các API đều trả về cấu trúc chuẩn:

### Thành công:
```json
{
  "success": true,
  "message": "Thông điệp thành công",
  "data": { ... },
  "timestamp": "2026-11-05T16:00:00.000Z"
}
```

### Thất bại:
```json
{
  "success": false,
  "message": "Thông điệp mô tả lỗi",
  "errors": { ... },
  "timestamp": "2026-11-05T16:00:00.000Z"
}
```

## 2. Các Endpoint hiện tại
- `GET /api/v1` - Thông tin nền tảng API.
- `GET /api/v1/health` - Kiểm tra sức khỏe toàn diện (Server Uptime, CPU, RAM, MySQL).
- `GET /api/v1/health/db` - Kiểm tra riêng kết nối MySQL.
- `POST /api/v1/auth/login` - Đăng nhập bằng email/mật khẩu, trả về JWT và thông tin người dùng.
- `POST /api/v1/auth/register` - Tạo tài khoản khách hàng và trả về JWT để đăng nhập ngay.
- `GET /api/v1/realtime/content` - Lấy danh mục và sản phẩm mới nhất do admin đồng bộ.
- `POST /api/v1/realtime/uploads/product-image` - Tải ảnh món ăn (JPG, PNG hoặc WebP, tối đa 5 MB); yêu cầu JWT admin và gửi nội dung ảnh trực tiếp với `Content-Type` tương ứng.
- `GET /api/v1/realtime/orders` - Lấy đơn hàng của khách đã đăng nhập; admin xem danh sách đơn.
- `POST /api/v1/realtime/orders` - Tạo đơn hàng realtime; chỉ khách hàng đã đăng nhập.
- `GET /api/v1/realtime/chat/:customerId` - Lấy lịch sử chat; khách chỉ được xem chat của mình, admin được xem mọi khách.
- `GET /api/v1/auth/me` - Lấy thông tin tài khoản hiện tại; yêu cầu `Authorization: Bearer <token>`.

Socket.IO xác thực bằng JWT qua handshake. Admin dùng `admin:content:publish` và `admin:order:status`; khách/admin gửi hỗ trợ qua `chat:send`. Các sự kiện cập nhật được phát đến tài khoản liên quan, không phát thông tin đơn hàng cho khách không liên quan.

### Đăng nhập
Request body:
```json
{ "email": "admin@fastfood.vn", "password": "••••••••" }
```

Khi thành công, `data` chứa `token` và thông tin an toàn của tài khoản (`id`, `fullName`, `email`, `phone`, `role`). Mật khẩu không được trả về.

### Tải ảnh món ăn
Gửi byte ảnh trực tiếp đến endpoint với `Authorization: Bearer <token>` và `Content-Type: image/jpeg`, `image/png` hoặc `image/webp`. Phản hồi thành công chứa đường dẫn công khai trong `data.url`; ảnh được lưu dưới `/assets/uploads/`.

### Đăng ký khách hàng
Request body:
```json
{
  "fullName": "Nguyễn Văn A",
  "email": "a@example.com",
  "phone": "0901234567",
  "password": "••••••••"
}
```

Mật khẩu cần tối thiểu 6 ký tự. Email và số điện thoại phải chưa được sử dụng. Khi đăng ký thành công, API trả về JWT và thông tin tài khoản như đăng nhập.

## 3. Lộ trình Endpoints tiếp theo
- `GET /api/v1/restaurants`
- `GET /api/v1/products`
- `POST /api/v1/orders`
