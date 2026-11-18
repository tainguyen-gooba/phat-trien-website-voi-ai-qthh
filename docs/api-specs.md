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
- `GET /api/v1/realtime/chat/conversations` - Lấy danh sách hội thoại của khách hàng; chỉ admin.
- `POST /api/v1/realtime/orders/:orderCode/reviews/:target` - Lưu đánh giá sao và nhận xét của tài xế (`driver`) hoặc quán (`restaurant`) cho đơn đã giao; chỉ chủ đơn.
- `GET /api/v1/realtime/orders/reviews` - Lấy đánh giá của khách hàng sau khi hoàn thành đơn; chỉ admin. Khi lưu từng đánh giá, server phát `review:new` và `notification:new` ngay sau khi giao dịch thành công.
- `POST /api/v1/payments/momo/initiate` - Tạo giao dịch MoMo cho đơn của khách đang đăng nhập; chỉ chuyển trạng thái khi IPN có chữ ký hợp lệ.
- `POST /api/v1/payments/vietqr/initiate` - Tạo mã VietQR cho đơn của khách; chỉ xác nhận thanh toán khi SePay báo webhook hợp lệ.
- `POST /api/v1/payments/:orderCode/switch-to-cod` - Đổi đơn online chưa thanh toán sang COD trong thời gian cho phép.
- `GET /api/v1/payments/:orderCode/status` - Lấy trạng thái thanh toán của đơn thuộc khách đăng nhập.
- `POST /api/v1/payments/momo/ipn` - IPN công khai của MoMo; kiểm tra HMAC-SHA256, mã đơn, số tiền và request ID.
- `POST /api/v1/payments/sepay/webhook` - Webhook SePay; xác thực `Authorization: Apikey ...`, tài khoản nhận, nội dung mã đơn và số tiền.
- `GET /api/v1/auth/me` - Lấy thông tin tài khoản hiện tại; yêu cầu `Authorization: Bearer <token>`.

Socket.IO xác thực bằng JWT qua handshake. Admin dùng `admin:content:publish` và `admin:order:status`; khách/admin gửi hỗ trợ qua `chat:send`. Trợ lý tự động trả lời theo các chủ đề hỗ trợ phổ biến; phản hồi của admin tạm dừng trợ lý trong 10 phút. Các sự kiện cập nhật được phát đến tài khoản liên quan, không phát thông tin đơn hàng cho khách không liên quan.

Trước khi chạy phiên bản có chat tự động và thanh toán realtime, cập nhật cơ sở dữ liệu bằng `npm --prefix backend run db:migrate:realtime`. Migration chuyển đơn online chưa trả sang trạng thái chờ thanh toán và thêm thời hạn giao dịch. Mỗi lượt có hiệu lực 15 phút; sau khi thất bại/hết hạn, khách có 1 giờ để thử lại hoặc đổi COD. Hết thời gian này, đơn vẫn lưu ở trạng thái thanh toán không thành công, không tự hủy và không cho thanh toán lại.

### Cấu hình thanh toán
Đặt các biến MoMo, SePay và `PAYMENT_PUBLIC_URL` trong `backend/.env` theo mẫu `backend/.env.example`. Chọn `MOMO_ENV=sandbox` khi thử nghiệm; dùng thông tin merchant production khi đưa lên thật. Không đưa khóa bí mật vào frontend hoặc Git. Production cần URL HTTPS công khai để các cổng gửi callback:
- MoMo IPN: `${PAYMENT_PUBLIC_URL}/api/v1/payments/momo/ipn`
- SePay webhook: `${PAYMENT_PUBLIC_URL}/api/v1/payments/sepay/webhook`

Checkout mới chỉ cung cấp COD, VietQR và MoMo. VietQR dùng nội dung chuyển khoản là mã đơn. Không xác nhận thanh toán dựa trên thao tác của trình duyệt; chỉ webhook khớp API key, tài khoản nhận, mã đơn và số tiền mới cập nhật thành công.
Với đơn thanh toán trực tuyến, máy chủ tính lại giá sản phẩm, topping và mã giảm giá từ dữ liệu tin cậy; nếu giỏ hàng cũ hoặc danh mục chưa có giá/topping tương ứng, khách cần tải lại giỏ hàng trước khi thanh toán.

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
