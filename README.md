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
fastfood-qtnhhh/
├── backend/                             # MÃ NGUỒN BACKEND (NODE.JS & EXPRESS)
│   ├── scripts/                         # Script tự động hóa
│   │   ├── init-db.js                   # Script tự động tạo database & 16 bảng & nạp seed
│   │   └── test-db.js                   # Script kiểm tra kết nối MySQL độc lập
│   ├── src/                             # Mã nguồn chính phân tầng
│   │   ├── config/                      # Cấu hình hệ thống & kết nối DB
│   │   │   ├── app.config.js            # Cấu hình biến môi trường
│   │   │   └── db.config.js             # MySQL Connection Pool
│   │   ├── constants/                   # Hằng số hệ thống
│   │   │   ├── httpStatusCodes.js       # Mã trạng thái HTTP
│   │   │   └── roles.js                 # Vai trò và trạng thái đơn hàng
│   │   ├── controllers/                 # Tầng tiếp nhận & điều phối request
│   │   │   ├── base.controller.js       # Controller cơ sở
│   │   │   └── health.controller.js     # Controller kiểm tra trạng thái
│   │   ├── middlewares/                 # Middleware trung gian
│   │   │   ├── auth.middleware.js       # Xác thực JWT & phân quyền RBAC
│   │   │   ├── errorHandler.middleware.js # Bắt lỗi toàn cục tập trung
│   │   │   ├── notFound.middleware.js   # Bắt lỗi 404 Route Not Found
│   │   │   └── requestLogger.middleware.js # Ghi log từng HTTP request
│   │   ├── models/                      # Tầng truy vấn CSDL
│   │   │   ├── BaseModel.js             # Lớp trừu tượng truy vấn MySQL
│   │   │   └── health.model.js          # Model kiểm tra DB & lấy danh sách bảng
│   │   ├── routes/                      # Định tuyến RESTful API
│   │   │   ├── health.routes.js         # Endpoint kiểm tra sức khỏe (/health)
│   │   │   └── index.js                 # Router gốc (/api/v1)
│   │   ├── services/                    # Tầng xử lý nghiệp vụ kinh doanh
│   │   │   └── health.service.js        # Logic kiểm tra sức khỏe hệ thống
│   │   ├── utils/                       # Tiện ích bổ trợ
│   │   │   ├── apiResponse.js           # Chuẩn hóa định dạng phản hồi JSON
│   │   │   └── logger.js                # Tiện ích ghi log có timestamp
│   │   ├── app.js                       # Cấu hình Express, CORS, Static files
│   │   └── server.js                    # Khởi động server HTTP & kiểm tra kết nối
│   ├── .env                             # File biến môi trường
│   ├── .env.example                     # File biến môi trường mẫu
│   └── package.json                     # Quản lý thư viện backend
│
├── frontend/                            # GIAO DIỆN FRONTEND (HTML5/CSS3/JS THUẦN)
│   ├── public/                          # Tài nguyên tĩnh công khai
│   │   ├── assets/
│   │   │   ├── css/                     # CSS phân chia theo chức năng
│   │   │   │   ├── variables.css        # Hệ thống biến màu sắc, typography
│   │   │   │   ├── reset.css            # Chuẩn hóa reset CSS
│   │   │   │   ├── main.css             # Components, layout, buttons, cards
│   │   │   │   └── responsive.css       # Tương thích màn hình di động/tablet
│   │   │   ├── js/                      # JavaScript phân chia theo chức năng
│   │   │   │   ├── config.js            # Cấu hình frontend & API Base URL
│   │   │   │   ├── api.js               # HTTP Client gọi API kèm Bearer JWT
│   │   │   │   ├── utils.js             # Định dạng tiền tệ VNĐ, ngày giờ, toast
│   │   │   │   └── main.js              # Logic kiểm tra trạng thái và UI
│   │   │   └── images/
│   │   │       └── logo.svg             # Logo nhận diện thương hiệu FASTFOOD
│   │   └── index.html                   # Trang chủ điều khiển & chẩn đoán hệ thống
│   ├── pages/                           # Các trang chức năng riêng biệt
│   │   ├── auth/                        # Phân hệ đăng nhập & đăng ký
│   │   │   ├── login.html
│   │   │   ├── register.html
│   │   │   ├── auth.css
│   │   │   └── auth.js
│   │   ├── customer/                    # Phân hệ khách hàng đặt món
│   │   │   ├── home.html
│   │   │   ├── cart.html
│   │   │   ├── customer.css
│   │   │   └── customer.js
│   │   ├── restaurant/                  # Phân hệ kênh quán ăn đối tác
│   │   │   └── dashboard.html
│   │   └── admin/                       # Phân hệ quản trị toàn sàn
│   │       └── dashboard.html
│   └── components/                      # Snippets giao diện dùng chung
│       ├── header.html
│       └── footer.html
│
├── database/                            # CƠ SỞ DỮ LIỆU
│   ├── schema.sql                       # Toàn bộ mã DDL 16 bảng chuẩn hóa 3NF
│   └── seeds.sql                        # Dữ liệu khởi tạo (Roles, Admin, Món ăn mẫu)
│
├── docs/                                # TÀI LIỆU KỸ THUẬT DỰ ÁN
│   ├── architecture.md                  # Kiến trúc hệ thống & nguyên lý phân tầng
│   ├── database-design.md               # Mô tả chi tiết 16 bảng & chiến lược đánh index
│   └── api-specs.md                     # Đặc tả API RESTful
│
├── .gitignore                           # Cấu hình bỏ qua git
├── package.json                         # File quản lý chạy script toàn dự án
└── README.md                            # Hướng dẫn chi tiết cài đặt và vận hành
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

---

### Bước 4: Khởi động Backend Server
Tại thư mục gốc, gõ:
```bash
npm run dev
```
*(Chế độ development tự động reload khi sửa code với nodemon trên cổng **5000**)*

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
