# FitFood - Website bán đồ ăn healthy

## Cấu trúc thư mục

```
NewProject/
├── Dockerfile                  ← Image PHP 8.2 + Apache (build production)
└── src/
    ├── docker-compose.yml      ← Khởi động PHP + MySQL + phpMyAdmin (dev)
    ├── README.md
    │
    ├── index.php               ← Trang chủ
    ├── menu.php                ← Thực đơn
    ├── order.php               ← Đặt hàng (chọn gói ăn + sản phẩm)
    ├── look.php                ← Hình ảnh / sự kiện
    ├── faqs.php                ← Câu hỏi thường gặp
    ├── cart.php                ← Widget giỏ hàng (localStorage, include vào navbar)
    │
    ├── register.php            ← API đăng ký (JSON)
    ├── login.php               ← API đăng nhập (JSON)
    ├── logout.php              ← Huỷ session
    │
    ├── config/
    │   └── database.php        ← Kết nối DB (dùng biến env từ Docker)
    ├── database/
    │   └── fitfood.sql         ← Tự chạy khi container MySQL khởi động lần đầu
    ├── includes/
    │   ├── login_modal.php     ← Popup đăng nhập
    │   └── register_modal.php  ← Popup đăng ký
    ├── assets/
    │   ├── css/
    │   │   └── register.css    ← Style chung cho 2 popup
    │   └── js/
    │       ├── login.js
    │       └── register.js
    └── img/                    ← Ảnh tĩnh
```

Các trang `index.php`, `menu.php`, `order.php`, `look.php`, `faqs.php` đều
nhúng `includes/login_modal.php` + `includes/register_modal.php` ở cuối body
và load `assets/js/login.js` + `assets/js/register.js`.

## Cách chạy bằng Docker

### Bước 1: Khởi động các container

Mở terminal tại thư mục `fitfood/` và chạy:

```bash
docker compose up -d
```

Lần đầu sẽ mất 1–2 phút để tải image và cài `pdo_mysql`. Các lần sau chỉ vài giây.

### Bước 2: Truy cập

- **Website FitFood:** http://localhost:8080
- **phpMyAdmin (xem DB):** http://localhost:8081 
  - User: `root` / Password: `rootpass`

Bấm nút **"Đăng ký"** trên thanh menu đen → popup hiện ra → điền form → thành công!

### Tài khoản admin mặc định

- Email: `admin@fitfood.vn`
- Password: `admin123`
- Sau khi đăng nhập thành công sẽ tự chuyển sang `admin/index.php`.

### Bước 3: Dừng hoặc khởi động lại

```bash
docker compose down          # Dừng và xóa container (DB vẫn còn trong volume)
docker compose down -v       # Dừng VÀ xóa luôn database
docker compose logs -f web   # Xem log PHP realtime
docker compose logs -f db    # Xem log MySQL
```

## Lần đầu clone về — cài dependencies (vendor)

Folder `vendor/` không được commit lên Git. Khi clone về, **không cần cài thủ công** — Docker tự động chạy `composer install` khi build.

```bash
# Lần đầu hoặc khi Dockerfile thay đổi — thêm --build để cài lại vendor
docker compose up -d --build
```

Các lần sau (chỉ sửa code PHP, không thêm/xóa thư viện):

```bash
docker compose up -d
```

> Nếu bạn tự thêm thư viện mới bằng `composer require`, chạy lại `docker compose up -d --build` để cập nhật vendor bên trong container.

## Cấu hình Google OAuth (đăng nhập bằng Google)

Tính năng đăng nhập Google yêu cầu tạo credentials riêng — **không dùng chung được** vì mỗi người cần URI callback khớp với domain của mình.

### Bước 1: Tạo Google OAuth credentials

1. Vào [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. **Create Credentials → OAuth client ID → Web application**
3. Thêm **Authorized redirect URI:** `http://localhost:8080/auth/google_callback.php`
4. Copy **Client ID** và **Client Secret**

### Bước 2: Tạo file config

```bash
cp src/auth/google_config.example.php src/auth/google_config.php
```

Mở `src/auth/google_config.php` và điền credentials vừa tạo:

```php
'client_id'     => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
'client_secret' => 'YOUR_CLIENT_SECRET',
```

> File `google_config.php` đã được `.gitignore` — credentials của bạn sẽ không bị commit lên Git.

## Kiểm tra nhanh các user đã đăng ký

```bash
docker exec -it fitfood_db mysql -ufitfood_user -pfitfood_pass fitfood -e "SELECT id, full_name, email, created_at FROM users;"
```

## Troubleshooting

**Lỗi "port is already allocated" cổng 8080/3307:**
Một ứng dụng khác đang dùng cổng đó. Mở `docker-compose.yml` và đổi cổng bên TRÁI (ví dụ `8090:80`).

**Popup bấm không hiện:**
Mở DevTools (F12) → tab Console xem lỗi JS. Thường do file `assets/js/register.js` không load được (kiểm tra tab Network).

**"Lỗi kết nối database":**
Đợi 10–20 giây sau khi `docker compose up` — MySQL cần thời gian khởi động lần đầu.

