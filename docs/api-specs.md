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
- `GET /api/v1/auth/me` - Lấy thông tin tài khoản hiện tại; yêu cầu `Authorization: Bearer <token>`.

### Đăng nhập
Request body:
```json
{ "email": "admin@fastfood.vn", "password": "••••••••" }
```

Khi thành công, `data` chứa `token` và thông tin an toàn của tài khoản (`id`, `fullName`, `email`, `phone`, `role`). Mật khẩu không được trả về.

## 3. Lộ trình Endpoints tiếp theo
- `POST /api/v1/auth/register`
- `GET /api/v1/restaurants`
- `GET /api/v1/products`
- `POST /api/v1/orders`
