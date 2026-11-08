# KIẾN TRÚC HỆ THỐNG FASTFOOD

## 1. Tổng quan
FASTFOOD là nền tảng thương mại điện tử chuyên về đặt đồ ăn trực tuyến mô hình nhiều đối tác (Multi-vendor Food Delivery), tương tự GrabFood / ShopeeFood với thương hiệu và giao diện riêng.

## 2. Mô hình phân tầng Backend (Layered Architecture)
Hệ thống tuân thủ nghiêm ngặt nguyên lý Single Responsibility (Mỗi tầng 1 nhiệm vụ chuyên biệt):

```
Client (Frontend HTML/CSS/JS thuần hoặc Mobile App)
                      │
                      ▼
               [ Middlewares ]
       (CORS, BodyParser, Logger, JWT Auth, RBAC)
                      │
                      ▼
                 [ Routes ]
         (/api/v1/health, /auth, /restaurants...)
                      │
                      ▼
               [ Controllers ]
     (Nhận req, validate input, điều phối service, ApiResponse)
                      │
                      ▼
                [ Services ]
     (Xử lý nghiệp vụ logic, tính toán đơn giá, voucher, thuế...)
                      │
                      ▼
                 [ Models ]
     (Tương tác CSDL qua connection pool & Prepared statements)
                      │
                      ▼
             [ MySQL Database ]
             (16 Bảng dữ liệu InnoDB chuẩn 3NF)
```

## 3. Cấu trúc thư mục Frontend
- Hoàn toàn độc lập với backend, không phụ thuộc framework (No React, Vue, Next.js).
- Phân chia rõ ràng:
  - `public/assets/css`: CSS Modules (`variables.css`, `reset.css`, `main.css`, `responsive.css`).
  - `public/assets/js`: JS Modules (`config.js`, `api.js`, `utils.js`, `main.js`).
  - `pages/`: Từng phân hệ chức năng riêng (`auth/`, `customer/`, `restaurant/`, `admin/`).

