# FASTFOOD - NỀN TẢNG ĐẶT ĐỒ ĂN TRỰC TUYẾN

> Hệ thống website thương mại điện tử chuyên về đặt đồ ăn trực tuyến mô hình tương tự GrabFood, được xây dựng với kiến trúc chuẩn mực, hiệu năng cao và phân tách rõ ràng.

---

## 1. CÔNG NGHỆ SỬ DỤNG
- **Frontend**: HTML5, CSS3, JavaScript thuần (Vanilla JS), Modular CSS Design System.
- **Backend**: Node.js & Express.js (RESTful API).
- **Database**: MySQL (16 bảng nghiệp vụ và 3 bảng realtime trên Engine InnoDB).
- **Authentication**: JWT (JSON Web Tokens).
- **Password Security**: bcryptjs (Mã hóa một chiều an toàn).
- **Package Manager**: npm.

---

## 2. CẤU TRÚC THƯ MỤC DỰ ÁN

```text
phat-trien-website-voi-ai-qthh/
├── backend/
│   ├── scripts/
│   │   ├── init-db.js
│   │   ├── migrate-buon-ma-thuot.js
│   │   ├── migrate-customer-profile.js
│   │   ├── migrate-realtime.js
│   │   ├── reset-demo-admin.js
│   │   ├── start-local.js
│   │   └── test-db.js
│   ├── src/
│   │   ├── config/
│   │   │   ├── app.config.js
│   │   │   └── db.config.js
│   │   ├── constants/
│   │   │   ├── httpStatusCodes.js
│   │   │   └── roles.js
│   │   ├── controllers/
│   │   │   ├── auth.controller.js
│   │   │   ├── base.controller.js
│   │   │   ├── health.controller.js
│   │   │   ├── image-upload.controller.js
│   │   │   ├── payment.controller.js
│   │   │   └── realtime.controller.js
│   │   ├── middlewares/
│   │   │   ├── auth.middleware.js
│   │   │   ├── errorHandler.middleware.js
│   │   │   ├── notFound.middleware.js
│   │   │   └── requestLogger.middleware.js
│   │   ├── models/
│   │   │   ├── auth.model.js
│   │   │   ├── BaseModel.js
│   │   │   └── health.model.js
│   │   ├── routes/
│   │   │   ├── auth.routes.js
│   │   │   ├── health.routes.js
│   │   │   ├── image-upload.routes.js
│   │   │   ├── index.js
│   │   │   ├── payment.routes.js
│   │   │   └── realtime.routes.js
│   │   ├── services/
│   │   │   ├── ai-chat.service.js
│   │   │   ├── auth.service.js
│   │   │   └── health.service.js
│   │   ├── utils/
│   │   │   ├── apiResponse.js
│   │   │   ├── customer-profile.validation.js
│   │   │   └── logger.js
│   │   ├── app.js
│   │   ├── realtime.js
│   │   └── server.js
│   ├── test/
│   │   ├── order-pricing.test.js
│   │   └── payment.controller.test.js
│   ├── .env.example
│   └── package.json
├── database/
│   ├── fastfood_db.sql
│   ├── schema.sql
│   └── seeds.sql
├── docs/
│   ├── api-specs.md
│   ├── architecture.md
│   └── database-design.md
├── frontend/
│   ├── assets/
│   │   └── images/
│   │       ├── hero-burger.svg
│   │       ├── logo-footer.svg
│   │       └── logo.svg
│   ├── css/
│   │   ├── base.css
│   │   ├── components.css
│   │   ├── home.css
│   │   ├── pages.css
│   │   ├── policies.css
│   │   ├── reset.css
│   │   ├── responsive.css
│   │   └── variables.css
│   ├── js/
│   │   ├── admin-chat.js
│   │   ├── auth.js
│   │   ├── cart.js
│   │   ├── checkout.js
│   │   ├── components.js
│   │   ├── data.js
│   │   ├── favorites.js
│   │   ├── food-detail.js
│   │   ├── foods.js
│   │   ├── home.js
│   │   ├── order-reviews.js
│   │   ├── payment.js
│   │   ├── policies.js
│   │   ├── profile.js
│   │   ├── realtime.js
│   │   ├── restaurants.js
│   │   ├── storage.js
│   │   └── tracking.js
│   ├── pages/
│   │   ├── admin/
│   │   │   └── dashboard.html
│   │   ├── auth/
│   │   │   ├── auth.css
│   │   │   ├── auth.js
│   │   │   ├── login.html
│   │   │   └── register.html
│   │   ├── customer/
│   │   │   ├── cart.html
│   │   │   ├── customer.css
│   │   │   ├── customer.js
│   │   │   └── home.html
│   │   ├── restaurant/
│   │   │   └── dashboard.html
│   │   ├── cart.html
│   │   ├── checkout.html
│   │   ├── favorites.html
│   │   ├── food-detail.html
│   │   ├── foods.html
│   │   ├── home.html
│   │   ├── login.html
│   │   ├── order-history.html
│   │   ├── order-tracking.html
│   │   ├── payment.html
│   │   ├── policies.html
│   │   ├── profile.html
│   │   ├── register.html
│   │   └── restaurants.html
│   ├── public/
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   │   ├── main.css
│   │   │   │   ├── reset.css
│   │   │   │   ├── responsive.css
│   │   │   │   └── variables.css
│   │   │   ├── images/
│   │   │   │   └── logo.svg
│   │   │   └── js/
│   │   │       ├── api.js
│   │   │       ├── config.js
│   │   │       ├── main.js
│   │   │       └── utils.js
│   │   └── index.html
│   └── index.html
├── .gitignore
├── package.json
└── README.md
```
---

## 3. CƠ SỞ DỮ LIỆU 16 BẢNG CHUẨN HÓA

| STT | Bảng | Mục đích |
|---|---|---|
| 1 | `roles` | Vai trò (admin, customer, restaurant_owner, shipper) |
| 2 | `users` | Tài khoản người dùng, mã hóa mật khẩu bcrypt |
| 3 | `restaurants` | Thông tin quán ăn, giờ mở cửa, tọa độ GPS, rating |
| 4 | `categories` | Danh mục món ăn hệ thống hoặc riêng của quán |
| 5 | `products` | Món ăn, giá gốc, giá khuyến mãi, trạng thái còn món |
| 6 | `product_images`| Thư viện ảnh của món ăn |
| 7 | `addresses` | Sổ địa chỉ giao hàng của khách hàng |
| 8 | `carts` | Giỏ hàng người dùng (tách riêng từng quán) |
| 9 | `cart_items` | Món ăn trong giỏ, số lượng và ghi chú |
| 10 | `coupons` | Mã giảm giá toàn sàn hoặc riêng của quán |
| 11 | `orders` | Đơn hàng, quy trình trạng thái, phí ship, tổng tiền |
| 12 | `order_items` | Chi tiết món đã đặt (lưu cứng giá tại thời điểm mua) |
| 13 | `payments` | Giao dịch thanh toán (COD, VNPay, MoMo, Banking) |
| 14 | `reviews` | Đánh giá sao (1-5) và nhận xét của khách sau khi giao |
| 15 | `notifications`| Thông báo hệ thống, đơn hàng, khuyến mãi |
| 16 | `staff_assignments`| Phân quyền nhân viên thuộc quán (quản lý, bếp, phục vụ) |
| 17 | `website_content` | Nội dung danh mục và sản phẩm đồng bộ tức thời |
| 18 | `chat_messages` | Lịch sử chat hỗ trợ khách hàng |
| 19 | `realtime_orders` | Đơn đặt hàng và trạng thái theo dõi realtime |

---

## 4. HƯỚNG DẪN CÀI ĐẶT & CHẠY DỰ ÁN

### Bước 1: Cài đặt Dependencies
Mở terminal tại thư mục gốc của dự án (`fastfood-qtnhhh`):
```bash
npm run backend:install
```
*(Hoặc di chuyển vào `cd backend` và gõ `npm install`)*

---

### Bước 2: Cấu hình MySQL trong file `.env`
Mở file `backend/.env` và cập nhật thông tin MySQL của bạn (nếu dùng XAMPP thì user thường là `root` và password để trống):
```ini
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=fastfood_db
```

---

### Bước 3: Khởi tạo Cơ sở dữ liệu tự động
Dự án được trang bị sẵn script tự động kết nối, tạo database `fastfood_db`, sinh 19 bảng và nạp dữ liệu mẫu:
```bash
npm run db:init
```
*Script sẽ tự động chạy `schema.sql` và `seeds.sql`, tạo 16 bảng nghiệp vụ cùng 3 bảng realtime.*

Nếu muốn kiểm tra kết nối database độc lập bất kỳ lúc nào:
```bash
npm run db:test
```

### Bước 3.1: Cập nhật các bảng realtime
Nếu database đã được khởi tạo trước đó, tạo các bảng nội dung website, chat và đơn hàng realtime mà không xóa dữ liệu hiện có:
```bash
npm --prefix backend run db:migrate:realtime
```

### Bước 3.2: Cập nhật địa chỉ quán mẫu
Nếu database đã khởi tạo từ trước, cập nhật hai quán mẫu sang địa chỉ Buôn Ma Thuột mà không xóa dữ liệu khác:
```bash
npm --prefix backend run db:migrate:buon-ma-thuot
```

---

### Bước 4: Khởi động Backend Server
Tại thư mục gốc, gõ:
```bash
npm run dev
```
Lệnh này tự kiểm tra MySQL trước khi chạy backend. Nếu dùng XAMPP tại `C:\xampp`, dự án sẽ tự khởi động MySQL khi dịch vụ chưa chạy; backend chỉ mở sau khi MySQL sẵn sàng. Khi cài XAMPP ở vị trí khác, đặt `MYSQLD_PATH` và (nếu cần) `MYSQLD_DEFAULTS_FILE` trong `backend/.env`.
*(Chế độ development tự động reload khi sửa code với nodemon trên cổng **5000**. Dữ liệu database hiện có được giữ nguyên; lần đầu cần chạy `npm run db:init` thủ công.)*

---

### Bước 5: Truy cập Giao diện & Kiểm tra thành công

1. **Giao diện Web Frontend**:
   Mở trình duyệt truy cập:
   👉 **http://localhost:5000** hoặc **http://localhost:5000/index.html**

2. **Kiểm tra RESTful API**:
   - Thông tin API: `GET http://localhost:5000/api/v1`
   - Kiểm tra sức khỏe toàn diện: `GET http://localhost:5000/api/v1/health`
   - Kiểm tra riêng MySQL: `GET http://localhost:5000/api/v1/health/db`

---

## 5. TÀI KHOẢN MẪU ĐƯỢC NẠP SẴN (SEEDS)
Tất cả tài khoản đều có mật khẩu mặc định: **`Admin@123456`**
- **Quản trị viên (Admin)**: `admin@fastfood.vn`

Để đăng nhập admin, hãy bật dịch vụ MySQL, chạy `npm run db:init` (nếu chưa khởi tạo database), rồi chạy backend và mở `http://localhost:5000/pages/login.html`.
Nếu database đã có sẵn nhưng tài khoản admin bị thiếu hoặc sai thông tin, chạy `npm --prefix backend run db:reset-demo-admin`.
Lệnh này đồng bộ vai trò và tài khoản admin `admin@fastfood.vn` với mật khẩu mặc định `Admin@123456`, không xóa dữ liệu khác. MySQL vẫn phải đang chạy để lệnh hoạt động.
Lưu ý: `db:init` tạo lại các bảng, nên chỉ chạy trên database mới hoặc sao lưu dữ liệu trước.
- **Chủ quán ăn (Restaurant Owner)**: `owner@fastfood.vn`
- **Khách hàng (Customer)**: `customer@fastfood.vn`
- **Tài xế giao hàng (Shipper)**: `shipper@fastfood.vn`
