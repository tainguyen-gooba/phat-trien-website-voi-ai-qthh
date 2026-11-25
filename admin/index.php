<?php
/**
 * FASTFOOD - Admin Console Dashboard (PHP MVC Native)
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once dirname(__DIR__) . '/config/config.php';
requireAdminOrStaff();

$currentUser = currentUser();
$isAdmin = isAdmin();
$isStaff = isStaff();
$db = getDB();

$unreadMessagesCount = 0;
if (isset($db) && $db instanceof \PDO) {
    try {
        $unreadMessagesCount = (int)($db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE sender = 'user' AND is_read = 0")->fetchColumn() ?: 0);
    } catch (\Throwable $e) {
        $unreadMessagesCount = 0;
    }
}

// 1. Categories
$categoriesRaw = $db->query("SELECT id, name, slug, icon_url, display_order, is_active FROM categories ORDER BY display_order ASC, id ASC")->fetchAll();
$categories = array_map(function($c) {
    return [
        'id' => (string)$c['id'],
        'name' => $c['name'],
        'description' => 'Danh mục ' . $c['name'],
        'itemCount' => 0,
        'status' => $c['is_active'] ? 'Đang hoạt động' : 'Đã ẩn',
        'icon' => $c['icon_url'] ?: '🍔'
    ];
}, $categoriesRaw);

// 2. Products
$productsRaw = $db->query("SELECT p.id, p.name, p.slug, p.base_price AS price, p.discount_price, p.is_available, p.image_url AS image, p.category_id, p.restaurant_id, p.options, c.name AS category, r.name AS restaurant, r.address 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                        ORDER BY p.id DESC")->fetchAll();
$products = array_map(function($p) {
    $opts = !empty($p['options']) ? (is_array($p['options']) ? $p['options'] : json_decode($p['options'], true)) : [];
    return [
        'id' => (string)$p['id'],
        'name' => $p['name'],
        'category' => $p['category'] ?: 'Khác',
        'categoryId' => (int)$p['category_id'],
        'restaurant' => $p['restaurant'] ?: 'FASTFOOD Kitchen',
        'address' => $p['address'] ?: 'Buôn Ma Thuột',
        'image' => $p['image'] ?: '',
        'price' => (int)$p['price'],
        'status' => $p['is_available'] ? 'Đang bán' : 'Tạm hết',
        'options' => $opts,
        'sizes' => $opts['sizes'] ?? [],
        'toppings' => $opts['toppings'] ?? [],
        'attributes' => $opts['attributes'] ?? []
    ];
}, $productsRaw);

// Cấu hình Best Seller & Giờ Vàng
$bsRaw = $db->query("SELECT content FROM website_content WHERE collection_name = 'bestseller' LIMIT 1")->fetchColumn();
$bsConfig = $bsRaw ? json_decode($bsRaw, true) : [
    'title'         => 'Món Ăn Bán Chạy Nhất (Best Sellers)',
    'subtitle'      => 'Được khách hàng đặt nhiều nhất trong tuần qua - Ưu đãi giờ vàng giới hạn!',
    'badge'         => '🔥 GIỜ VÀNG BEST SELLER',
    'timer_enabled' => 1,
    'end_time'      => date('Y-m-d H:i:s', strtotime('+2 days 23:59:59')),
    'discount_text' => 'Ưu đãi có hạn',
    'product_ids'   => [1, 2, 3, 5]
];

// Cấu hình Chân Trang (Footer Settings)
$footerRaw = $db->query("SELECT content FROM website_content WHERE collection_name = 'footer_settings' LIMIT 1")->fetchColumn();
$footerConfig = $footerRaw ? json_decode($footerRaw, true) : [
    'brand_desc'    => 'FASTFOOD Buôn Ma Thuột - Đặt món ngon địa phương, giao tận nơi nóng hổi và siêu tốc trong 20 phút.',
    'hotline'       => '1900 8888',
    'email'         => 'hotro@fastfood.vn',
    'map_url'       => 'https://www.google.com/maps/search/?api=1&query=Buon+Ma+Thuot%2C+Dak+Lak',
    'working_hours' => '06:00 - 23:30 hàng ngày',
    'address'       => '16 Hoàng Diệu, P. Thắng Lợi, TP. Buôn Ma Thuột',
    'copyright'     => '© ' . date('Y') . ' FASTFOOD Buôn Ma Thuột. Dự án phát triển Website đặt đồ ăn trực tuyến chuẩn PHP MVC.',
    'explore_links' => [
        ['label' => 'Trang chủ FASTFOOD', 'url' => 'index.php'],
        ['label' => 'Thực đơn hôm nay', 'url' => 'foods.php'],
        ['label' => 'Món ăn bán chạy', 'url' => 'foods.php?sort=popular'],
        ['label' => 'Khuyến mãi & Combo', 'url' => 'foods.php?sort=price-asc']
    ],
    'policy_links'  => [
        ['label' => 'Chính sách giao hàng', 'url' => 'policies.php'],
        ['label' => 'Quy định hủy đơn & phí ship', 'url' => 'policies.php#cancellation'],
        ['label' => 'Bảo mật thông tin khách hàng', 'url' => 'policies.php#privacy'],
        ['label' => 'Điều khoản dịch vụ', 'url' => 'policies.php#terms']
    ]
];

// 3. Orders
$ordersRaw = $db->query("SELECT o.order_code, o.total_amount, o.order_status, o.created_at, o.recipient_name, o.recipient_phone, o.delivery_address AS recipient_address,
                                ro.order_data, ro.status AS rt_status,
                                COALESCE(p.payment_method, o.payment_method) AS payment_method,
                                COALESCE(p.status, o.payment_status) AS payment_status
                         FROM orders o
                         LEFT JOIN realtime_orders ro ON ro.order_code = o.order_code
                         LEFT JOIN realtime_payments p ON p.order_code = o.order_code
                         ORDER BY o.id DESC LIMIT 200")->fetchAll();
$orders = [];
$statusMap = [
    'pending' => 'Chờ xác nhận',
    'awaiting_payment' => 'Chờ thanh toán',
    'preparing' => 'Đang chuẩn bị',
    'delivering' => 'Đang giao',
    'delivered' => 'Đã giao',
    'cancelled' => 'Đã hủy'
];

foreach ($ordersRaw as $row) {
    $data = json_decode($row['order_data'] ?? '', true) ?: [];
    $rawSt = $row['order_status'] ?: ($row['rt_status'] ?: 'pending');
    $stVN = $statusMap[$rawSt] ?? 'Chờ xác nhận';
    
    $paymentMethod = $row['payment_method'] ?? $data['paymentMethod'] ?? 'cod';
    $paymentLabel = ($paymentMethod === 'banking' || $paymentMethod === 'vietqr') ? 'Chuyển khoản (VietQR)' : 'Tiền mặt (COD)';

    $orders[] = [
        'id' => $row['order_code'],
        'orderNo' => $row['order_code'],
        'customer' => $row['recipient_name'] ?: ($data['customer'] ?? 'Khách hàng'),
        'phone' => $row['recipient_phone'] ?: ($data['phone'] ?? ''),
        'address' => $row['recipient_address'] ?: ($data['address'] ?? ''),
        'restaurant' => $data['restaurant'] ?? 'FASTFOOD Buôn Ma Thuột',
        'total' => (int)$row['total_amount'],
        'paymentLabel' => $paymentLabel,
        'paymentMethod' => $paymentMethod,
        'paymentStatus' => $row['payment_status'] ?? 'pending',
        'date' => date('d/m/Y H:i', strtotime($row['created_at'])),
        'createdAt' => $row['created_at'],
        'status' => $stVN,
        'items' => $data['items'] ?? []
    ];
}

// 4. Users
$rawUsers = $db->query("SELECT id, full_name AS name, email, phone, role_id, status FROM users ORDER BY id ASC")->fetchAll();
$roleMap = [1 => 'Quản trị viên', 2 => 'Khách hàng', 3 => 'Chủ nhà hàng', 4 => 'Tài xế', 5 => 'Nhân viên hỗ trợ'];
$users = [];
foreach ($rawUsers as $u) {
    $users[] = [
        'id' => (string)$u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'phone' => $u['phone'] ?: 'Chưa cập nhật',
        'role' => $roleMap[$u['role_id']] ?? 'Khách hàng',
        'roleId' => (int)$u['role_id'],
        'status' => ($u['status'] === 'active' || $u['status'] === 'Đang hoạt động') ? 'Đang hoạt động' : 'Đã khóa'
    ];
}

// 5. Restaurants
$restaurantsRaw = $db->query("SELECT id, name, address, phone, rating_avg, is_active FROM restaurants ORDER BY id ASC")->fetchAll();
$restaurants = array_map(function($r) {
    return [
        'id' => (string)$r['id'],
        'name' => $r['name'],
        'address' => $r['address'] ?: 'Buôn Ma Thuột',
        'url' => '',
        'status' => $r['is_active'] ? 'Đang hoạt động' : 'Đã ẩn'
    ];
}, $restaurantsRaw);

// 6. Reviews
$reviews = $db->query("SELECT r.*, u.full_name AS customer_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 100")->fetchAll();

$initialData = [
    'categories'  => $categories,
    'products'    => $products,
    'orders'      => $orders,
    'users'       => $users,
    'restaurants' => $restaurants,
    'reviews'     => $reviews,
    'currentUser' => [
        'id'        => $currentUser['id'],
        'fullName'  => $currentUser['full_name'],
        'email'     => $currentUser['email'],
        'role'      => $isAdmin ? 'admin' : 'staff',
        'roleLabel' => $isAdmin ? 'Quản Trị Viên' : 'Nhân Viên Hỗ Trợ',
        'roleId'    => (int)$currentUser['role_id']
    ]
];

require_once dirname(__DIR__) . '/backend/src/config/JwtConfig.php';
$adminToken = \FastFood\Config\JwtConfig::encode([
    'id' => (int)$currentUser['id'],
    'email' => $currentUser['email'],
    'fullName' => $currentUser['full_name'],
    'role' => $isAdmin ? 'admin' : 'staff',
    'roleId' => (int)$currentUser['role_id']
]);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#fff7ed">
  <title>Admin Dashboard | FASTFOOD</title>
  <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
  <style>
    :root {
      color-scheme: light;
      --orange: #f97316;
      --orange-dark: #ea580c;
      --orange-soft: #fff7ed;
      --orange-pale: #ffedd5;
      --ink: #252525;
      --muted: #8b8b8b;
      --line: #efefef;
      --canvas: #fafafa;
      --white: #fff;
      --green: #15966a;
      --red: #dc5656;
      --shadow: 0 12px 35px rgba(34, 34, 34, .06);
      font-family: "Plus Jakarta Sans", "Be Vietnam Pro", Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      background: var(--canvas);
      color: var(--ink);
      min-width: 320px;
    }

    button,
    input,
    select {
      font: inherit;
    }

    button {
      cursor: pointer;
    }

    .admin-layout {
      min-height: 100vh;
    }

    .sidebar {
      position: fixed;
      inset: 0 auto 0 0;
      z-index: 50;
      display: flex;
      flex-direction: column;
      width: 260px;
      padding: 24px 16px 20px;
      background: var(--white);
      border-right: 1px solid var(--line);
      overflow-y: auto;
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(0, 0, 0, 0.18) transparent;
    }

    .sidebar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    .sidebar::-webkit-scrollbar-thumb {
      background: rgba(0, 0, 0, 0.18);
      border-radius: 999px;
    }

    .sidebar::-webkit-scrollbar-track {
      background: transparent;
    }

    .sidebar-resizer {
      position: fixed;
      top: 0;
      bottom: 0;
      left: 260px;
      width: 10px;
      margin-left: -5px;
      cursor: col-resize;
      background: transparent;
      z-index: 60;
      user-select: none;
      touch-action: none;
      display: block;
      transition: background 0.15s ease;
    }

    .sidebar-resizer::after {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 3px;
      height: 32px;
      border-radius: 99px;
      background: #cbd5e1;
      opacity: 0.7;
      transition: all 0.2s ease;
    }

    .sidebar-resizer:hover::after,
    .sidebar-resizer.is-dragging::after {
      opacity: 1;
      height: 56px;
      background: var(--orange);
      box-shadow: 0 0 8px rgba(249, 115, 22, 0.5);
    }

    .sidebar-resizer:hover,
    .sidebar-resizer.is-dragging {
      background: rgba(249, 115, 22, 0.12);
    }

    body.is-resizing-sidebar,
    body.is-resizing-sidebar * {
      cursor: col-resize !important;
      user-select: none !important;
    }

    .sidebar-header-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 0 4px 20px;
    }

    .sidebar-header-row .brand {
      padding: 0;
    }

    .sidebar-close-btn {
      display: none;
      width: 34px;
      height: 34px;
      border-radius: 8px;
      border: 1px solid var(--line);
      background: #f8fafc;
      color: var(--ink);
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
      padding: 0;
      flex-shrink: 0;
    }

    .sidebar-close-btn:hover {
      background: #fee2e2;
      color: #dc2626;
      border-color: #fca5a5;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 0 9px 31px;
      color: var(--ink);
      text-decoration: none;
    }

    .brand-mark {
      display: grid;
      width: 40px;
      height: 40px;
      place-items: center;
      border-radius: 13px;
      color: #fff;
      background: var(--orange);
      font-size: 19px;
      font-weight: 900;
      box-shadow: 0 6px 14px rgba(249, 115, 22, .22);
    }

    .brand-name {
      font-size: 16px;
      font-weight: 800;
      letter-spacing: -.5px;
    }

    .brand-name small {
      display: block;
      margin-top: 2px;
      color: var(--muted);
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 1.25px;
    }

    .nav-label {
      margin: 9px 10px 11px;
      color: #a4a4a4;
      font-size: 10px;
      font-weight: 800;
      letter-spacing: 1.35px;
      text-transform: uppercase;
    }

    .nav-list {
      display: grid;
      gap: 5px;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 13px;
      width: 100%;
      padding: 11px 12px;
      border: 0;
      border-radius: 10px;
      background: transparent;
      color: #777;
      text-align: left;
      font-size: 13px;
      font-weight: 650;
      transition: background .16s ease, color .16s ease;
    }

    .nav-item:hover {
      background: #fafafa;
      color: var(--ink);
    }

    .nav-item.active {
      background: var(--orange-pale);
      color: var(--orange-dark);
    }

    .nav-item svg {
      width: 18px;
      height: 18px;
      flex: 0 0 auto;
    }

    .sidebar-bottom {
      margin-top: auto;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 13px 3px 0;
      padding: 9px;
      color: #777;
      text-decoration: none;
      font-size: 12px;
      font-weight: 650;
    }

    .sidebar-link:hover {
      color: var(--orange-dark);
    }

    .sidebar-link svg {
      width: 17px;
      height: 17px;
    }

    .main {
      min-height: 100vh;
      margin-left: 256px;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 10;
      display: flex;
      min-height: 76px;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding: 13px 38px;
      border-bottom: 1px solid var(--line);
      background: rgba(255, 255, 255, .94);
      backdrop-filter: blur(12px);
    }

    .topbar-left,
    .topbar-right,
    .admin-profile,
    .search-form {
      display: flex;
      align-items: center;
    }

    .topbar-left {
      gap: 13px;
    }

    .topbar-right {
      gap: 18px;
    }

    .mobile-menu {
      display: none;
      width: 38px;
      height: 38px;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      color: #555;
    }

    .mobile-menu svg {
      width: 18px;
      height: 18px;
    }

    .mobile-menu {
      transition: border-color .2s ease, color .2s ease, background .2s ease, transform .2s ease;
    }

    .mobile-menu:hover {
      border-color: #ffd1b0;
      background: #fff8f2;
      color: var(--orange-dark);
    }

    .crumb {
      color: #a0a0a0;
      font-size: 12px;
    }

    .crumb strong {
      color: #444;
      font-weight: 700;
    }

    .top-search {
      display: flex;
      width: min(390px, 36vw);
      height: 41px;
      overflow: hidden;
      border: 1px solid #eaeaea;
      border-radius: 9px;
      background: #fff;
    }

    .top-search:focus-within {
      border-color: #fdba74;
      box-shadow: 0 0 0 3px rgba(249, 115, 22, .08);
    }

    .top-search input {
      width: 100%;
      min-width: 0;
      padding: 0 12px;
      outline: 0;
      border: 0;
      background: transparent;
      color: var(--ink);
      font-size: 12px;
    }

    .top-search input::placeholder {
      color: #aaa;
    }

    .search-button,
    .primary-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border: 0;
      border-radius: 9px;
      background: var(--orange);
      color: white;
      font-size: 12px;
      font-weight: 750;
      transition: background .16s ease, transform .16s ease, box-shadow .16s ease;
    }

    .search-button {
      margin: 4px;
      padding: 0 13px;
      white-space: nowrap;
    }

    .search-button:hover,
    .primary-button:hover {
      background: var(--orange-dark);
      box-shadow: 0 5px 13px rgba(249, 115, 22, .2);
    }

    .search-button:active,
    .primary-button:active {
      transform: translateY(1px);
    }

    .search-button svg,
    .primary-button svg {
      width: 15px;
      height: 15px;
    }

    .profile-divider {
      width: 1px;
      height: 30px;
      background: var(--line);
    }

    .admin-profile {
      gap: 10px;
    }

    .avatar {
      display: grid;
      width: 37px;
      height: 37px;
      place-items: center;
      border-radius: 50%;
      background: #ffedd5;
      color: #c65a0a;
      font-size: 12px;
      font-weight: 800;
    }

    .profile-copy strong {
      display: block;
      font-size: 12px;
    }

    .profile-copy span {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 10px;
    }

    .logout-button {
      padding: 8px 10px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      color: #777;
      font-size: 10px;
      font-weight: 700;
    }

    .logout-button:hover {
      border-color: #ffd1b0;
      color: var(--orange-dark);
    }

    /* ===== ADMIN NOTIFICATIONS SYSTEM ===== */
    .notif-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .notif-bell-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      color: #555;
      cursor: pointer;
      transition: all .2s ease;
    }

    .notif-bell-btn:hover,
    .notif-bell-btn[aria-expanded="true"] {
      border-color: #ffd1b0;
      background: var(--orange-pale);
      color: var(--orange-dark);
    }

    .notif-bell-btn svg {
      width: 19px;
      height: 19px;
    }

    .notif-bell-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 18px;
      height: 18px;
      padding: 0 4px;
      border: 2px solid #fff;
      border-radius: 999px;
      background: #e11d48;
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      line-height: 1;
      animation: bell-bounce .4s ease-out;
    }

    @keyframes bell-bounce {
      0% {
        transform: scale(0.3);
      }

      70% {
        transform: scale(1.2);
      }

      100% {
        transform: scale(1);
      }
    }

    .notif-dropdown {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      z-index: 1000;
      width: 360px;
      max-width: 90vw;
      border: 1px solid #fed7aa;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 12px 36px rgba(15, 23, 42, .14), 0 2px 8px rgba(15, 23, 42, .06);
      overflow: hidden;
      animation: notif-slide-down .2s ease-out;
    }

    .notif-dropdown[hidden] {
      display: none;
    }

    @keyframes notif-slide-down {
      from {
        opacity: 0;
        transform: translateY(-8px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .notif-dropdown-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 13px 16px;
      border-bottom: 1px solid #f1f5f9;
      background: #fff7ed;
    }

    .notif-dropdown-title {
      margin: 0;
      color: #1e293b;
      font-size: 13.5px;
      font-weight: 750;
      display: inline-block;
      margin-right: 6px;
    }

    .notif-unread-tag {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 10px;
      background: #ffedd5;
      color: var(--orange-dark);
      font-size: 11px;
      font-weight: 700;
    }

    .notif-mark-all-btn {
      padding: 4px 8px;
      border: 0;
      background: transparent;
      color: #64748b;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: underline;
      transition: color .15s ease;
    }

    .notif-mark-all-btn:hover {
      color: var(--orange-dark);
    }

    .notif-list {
      max-height: 380px;
      overflow-y: auto;
      overscroll-behavior: contain;
    }

    .notif-empty {
      padding: 36px 16px;
      text-align: center;
      color: #94a3b8;
      font-size: 12.5px;
    }

    .notif-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 12px 16px;
      border-bottom: 1px solid #f8fafc;
      background: #fff;
      cursor: pointer;
      transition: background .15s ease;
      text-align: left;
      width: 100%;
      border-left: 3px solid transparent;
      box-sizing: border-box;
    }

    .notif-item:hover {
      background: #fafafa;
    }

    .notif-item.unread {
      background: #fffaf5;
      border-left-color: var(--orange);
    }

    .notif-icon {
      flex: 0 0 34px;
      height: 34px;
      border-radius: 9px;
      display: grid;
      place-items: center;
      background: #ffedd5;
      color: #c65a0a;
      font-size: 15px;
    }

    .notif-icon.order {
      background: #ffedd5;
      color: #ea580c;
    }

    .notif-icon.review {
      background: #fef08a;
      color: #a16207;
    }

    .notif-item-body {
      flex: 1;
      min-width: 0;
    }

    .notif-item-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      margin-bottom: 3px;
    }

    .notif-item-title {
      font-size: 12px;
      font-weight: 750;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .notif-item-time {
      font-size: 10.5px;
      color: #94a3b8;
      white-space: nowrap;
    }

    .notif-item-text {
      margin: 0;
      font-size: 11.5px;
      line-height: 1.4;
      color: #475569;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .nav-badge-orders {
      background: #ef4444;
      color: #fff;
      font-weight: 800;
      animation: pulse-badge 2s infinite ease-in-out;
    }

    @keyframes pulse-badge {

      0%,
      100% {
        transform: scale(1);
        opacity: 1;
      }

      50% {
        transform: scale(1.08);
        opacity: 0.88;
      }
    }

    /* Order Toast Card */
    .order-toast-card {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      width: 350px;
      max-width: calc(100vw - 32px);
      padding: 14px 16px;
      border: 1.5px solid #fb923c;
      border-radius: 14px;
      background: #ffffff;
      color: #1e293b;
      box-shadow: 0 10px 30px rgba(234, 88, 12, .22), 0 3px 10px rgba(0, 0, 0, .08);
      animation: toast-in .24s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
    }

    .order-toast-card .order-toast-icon {
      flex: 0 0 36px;
      height: 36px;
      border-radius: 10px;
      display: grid;
      place-items: center;
      background: #ffedd5;
      color: #ea580c;
      font-size: 18px;
    }

    .order-toast-card .order-toast-body {
      flex: 1;
      min-width: 0;
    }

    .order-toast-card .order-toast-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      margin-bottom: 3px;
    }

    .order-toast-card .order-toast-title {
      font-size: 13px;
      font-weight: 800;
      color: #ea580c;
    }

    .order-toast-card .order-toast-badge {
      padding: 1px 6px;
      border-radius: 6px;
      background: #fee2e2;
      color: #dc2626;
      font-size: 9.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .5px;
    }

    .order-toast-card .order-toast-detail {
      font-size: 12px;
      line-height: 1.4;
      color: #334155;
      margin-bottom: 8px;
    }

    .order-toast-card .order-toast-total {
      font-weight: 800;
      color: #ea580c;
    }

    .order-toast-card .order-toast-action {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 6px 12px;
      border: 0;
      border-radius: 7px;
      background: #ea580c;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      transition: background .15s ease;
    }

    .order-toast-card .order-toast-action:hover {
      background: #c65a0a;
    }

    .order-toast-card .order-toast-close {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 20px;
      height: 20px;
      border: 0;
      background: transparent;
      color: #94a3b8;
      cursor: pointer;
      display: grid;
      place-items: center;
      font-size: 16px;
      border-radius: 4px;
      line-height: 1;
    }

    .order-toast-card .order-toast-close:hover {
      color: #0f172a;
      background: #f1f5f9;
    }

    .content {
      width: min(100%, 1450px);
      margin: 0 auto;
      padding: 34px 38px 48px;
    }

    .page-heading {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 20px;
      margin-bottom: 25px;
    }

    .eyebrow {
      margin: 0 0 7px;
      color: var(--orange-dark);
      font-size: 10px;
      font-weight: 800;
      letter-spacing: 1.3px;
      text-transform: uppercase;
    }

    h1 {
      margin: 0;
      font-size: clamp(22px, 2.4vw, 29px);
      font-weight: 800;
      letter-spacing: -1px;
    }

    .page-description {
      margin: 7px 0 0;
      color: var(--muted);
      font-size: 12px;
    }

    .primary-button {
      min-height: 41px;
      padding: 0 15px;
      white-space: nowrap;
    }

    .date-label {
      margin-top: 9px;
      color: #aaa;
      font-size: 10px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 18px;
      margin-bottom: 28px;
    }

    .stat-card {
      min-width: 0;
      padding: 20px 22px;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      background: #ffffff;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.02);
      transition: all .2s ease;
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(249, 115, 22, 0.08);
      border-color: #fdba74;
    }

    .stat-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .stat-label {
      color: #64748b;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: -0.1px;
    }

    .stat-icon {
      display: grid;
      width: 40px;
      height: 40px;
      place-items: center;
      border-radius: 12px;
      background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
      color: var(--orange);
      border: 1px solid #fed7aa;
    }

    .stat-icon svg {
      width: 20px;
      height: 20px;
    }

    .stat-value {
      display: block;
      margin-top: 14px;
      font-size: 26px;
      font-weight: 850;
      color: #0f172a;
      letter-spacing: -0.8px;
    }

    .stat-note {
      display: block;
      margin-top: 6px;
      color: #94a3b8;
      font-size: 11px;
      font-weight: 550;
    }

    .stat-note .positive {
      color: #16a34a;
      font-weight: 750;
    }

    .table-panel {
      overflow: hidden;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      background: #ffffff;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .panel-heading {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 20px 21px 16px;
    }

    .panel-title {
      margin: 0;
      font-size: 15px;
      font-weight: 800;
      letter-spacing: -.25px;
    }

    .panel-subtitle {
      margin: 5px 0 0;
      color: #a0a0a0;
      font-size: 10px;
    }

    .panel-filters {
      display: flex;
      align-items: center;
      gap: 9px;
    }

    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .filter-select {
      min-width: 144px;
      height: 36px;
      padding: 0 10px;
      border: 1px solid #eaeaea;
      border-radius: 8px;
      outline: 0;
      background: white;
      color: #686868;
      font-size: 11px;
    }

    .filter-select:focus {
      border-color: #fdba74;
    }

    .table-wrap {
      width: 100%;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    thead {
      background: #fafafa;
    }

    th {
      padding: 11px 17px;
      color: #999;
      font-size: 9px;
      font-weight: 800;
      letter-spacing: .75px;
      text-transform: uppercase;
      white-space: nowrap;
    }

    td {
      padding: 13px 17px;
      border-top: 1px solid #f2f2f2;
      color: #666;
      font-size: 11px;
      white-space: nowrap;
    }

    tbody tr {
      transition: background .15s ease;
    }

    tbody tr:hover {
      background: #fffaf6;
    }

    td:first-child {
      color: #383838;
      font-weight: 700;
    }

    .cell-sub {
      display: block;
      margin-top: 4px;
      color: #aaa;
      font-size: 9px;
      font-weight: 500;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 9px;
      border-radius: 30px;
      font-size: 9px;
      font-weight: 750;
    }

    .status-badge::before {
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: currentColor;
      content: "";
    }

    .status-active,
    .status-delivered {
      background: #eaf8f1;
      color: #16835d;
    }

    .status-processing,
    .status-preparing {
      background: #fff4e7;
      color: #d97706;
    }

    .status-shipping {
      background: #eff6ff;
      color: #3974c6;
    }

    .status-inactive,
    .status-cancelled,
    .status-blocked,
    .status-unavailable {
      background: #f5f5f5;
      color: #888;
    }

    .row-actions {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
    }

    .action-button {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 6px 8px;
      border: 0;
      border-radius: 7px;
      background: var(--orange-soft);
      color: var(--orange-dark);
      font-size: 10px;
      font-weight: 750;
    }

    .action-button:hover {
      background: var(--orange-pale);
    }

    .action-button.delete {
      background: #fff1f1;
      color: var(--red);
    }

    .action-button.delete:hover {
      background: #ffe4e4;
    }

    .action-button svg {
      width: 13px;
      height: 13px;
    }

    .empty-state {
      padding: 42px 18px;
      text-align: center;
      color: #999;
      font-size: 12px;
    }

    .table-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 19px;
      border-top: 1px solid #f1f1f1;
    }

    .result-count {
      color: #999;
      font-size: 10px;
    }

    .pagination {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .page-button {
      display: grid;
      min-width: 29px;
      height: 29px;
      place-items: center;
      padding: 0 7px;
      border: 1px solid transparent;
      border-radius: 7px;
      background: transparent;
      color: #777;
      font-size: 10px;
    }

    .page-button:hover:not(:disabled) {
      border-color: #eee;
    }

    .page-button.current {
      border-color: var(--orange);
      background: var(--orange);
      color: #fff;
      font-weight: 800;
    }

    .page-button:disabled {
      cursor: not-allowed;
      opacity: .4;
    }

    .pagination-ellipsis {
      padding: 0 2px;
      color: #aaa;
      font-size: 10px;
    }

    .mobile-backdrop {
      display: none;
    }

    dialog {
      width: min(460px, calc(100vw - 32px));
      max-height: min(90vh, 720px);
      padding: 0;
      overflow: auto;
      border: 0;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 25px 80px rgba(0, 0, 0, .2);
    }

    dialog::backdrop {
      background: rgba(25, 25, 25, .42);
      backdrop-filter: blur(3px);
    }

    .modal-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      padding: 21px 22px 15px;
      border-bottom: 1px solid #f0f0f0;
    }

    .modal-head h2 {
      margin: 0;
      font-size: 17px;
      letter-spacing: -.4px;
    }

    .modal-head p {
      margin: 5px 0 0;
      color: #999;
      font-size: 11px;
    }

    .modal-close {
      display: grid;
      width: 30px;
      height: 30px;
      place-items: center;
      border: 0;
      border-radius: 8px;
      background: #f7f7f7;
      color: #777;
      font-size: 18px;
    }

    .modal-form {
      padding: 17px 22px 21px;
    }

    .form-fields {
      display: grid;
      gap: 13px;
    }

    .field label {
      display: block;
      margin-bottom: 6px;
      color: #555;
      font-size: 11px;
      font-weight: 700;
    }

    .field input,
    .field select {
      width: 100%;
      height: 39px;
      padding: 0 11px;
      border: 1px solid #e7e7e7;
      border-radius: 8px;
      outline: 0;
      background: #fff;
      color: #444;
      font-size: 12px;
    }

    .field input:focus,
    .field select:focus {
      border-color: #fdba74;
      box-shadow: 0 0 0 3px rgba(249, 115, 22, .08);
    }

    .toppings-editor-box {
      margin-top: 4px;
      padding: 13px;
      border: 1.5px solid #fed7aa;
      border-radius: 10px;
      background: #fffaf5;
    }

    .toppings-editor-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 4px;
    }

    .toppings-editor-header strong {
      font-size: 12px;
      color: #9a3412;
      font-weight: 800;
    }

    .toppings-editor-sub {
      margin: 0 0 10px;
      font-size: 11px;
      color: #78716c;
    }

    .btn-add-topping {
      padding: 4px 10px;
      border: 1px dashed #ea580c;
      border-radius: 6px;
      background: #fff;
      color: #ea580c;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      transition: all .16s ease;
    }

    .btn-add-topping:hover {
      background: #ea580c;
      color: #fff;
    }

    .toppings-rows-list {
      display: grid;
      gap: 7px;
    }

    .topping-form-row {
      display: grid;
      grid-template-columns: 1fr 110px 32px;
      gap: 6px;
      align-items: center;
    }

    .topping-form-row input {
      height: 34px !important;
      padding: 0 9px !important;
      font-size: 11px !important;
      border: 1px solid #e7e5e4 !important;
      border-radius: 6px !important;
      background: #fff !important;
    }

    .topping-price-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .topping-price-wrap input {
      padding-right: 22px !important;
    }

    .topping-price-wrap span {
      position: absolute;
      right: 7px;
      font-size: 11px;
      font-weight: 700;
      color: #a8a29e;
      pointer-events: none;
    }

    .topping-del-btn {
      width: 32px;
      height: 32px;
      border: 1px solid #fecdd3;
      border-radius: 6px;
      background: #fff;
      color: #e11d48;
      cursor: pointer;
      display: grid;
      place-items: center;
      font-size: 13px;
      transition: all .16s ease;
    }

    .topping-del-btn:hover {
      background: #ffe4e6;
      border-color: #fda4af;
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 21px;
    }

    .secondary-button {
      min-height: 38px;
      padding: 0 13px;
      border: 1px solid #e9e9e9;
      border-radius: 8px;
      background: #fff;
      color: #666;
      font-size: 11px;
      font-weight: 700;
    }

    .modal-actions .primary-button {
      min-height: 38px;
    }

    #toast-container {
      position: fixed;
      right: 20px;
      top: 20px;
      z-index: 100000;
      display: grid;
      gap: 8px;
    }

    .toast {
      padding: 12px 16px;
      border-radius: 9px;
      background: #252525;
      color: #fff;
      box-shadow: var(--shadow);
      font-size: 12px;
      animation: toast-in .2s ease-out;
    }

    .toast.error {
      background: #b83d3d;
    }

    @keyframes toast-in {
      from {
        opacity: 0;
        transform: translateY(8px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 1023px) and (min-width: 821px) {
      .sidebar {
        width: 250px;
        transform: translateX(0);
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 50;
        padding: 20px 14px;
        box-shadow: 4px 0 20px rgba(0,0,0,0.03);
      }

      .main {
        margin-left: 250px;
        width: calc(100% - 250px);
      }

      .mobile-menu {
        display: none !important;
      }

      .mobile-backdrop {
        display: none !important;
      }

      .sidebar-close-btn {
        display: none !important;
      }

      .nav-item {
        padding: 9px 10px;
        font-size: 13px;
        gap: 9px;
      }

      .content {
        padding-right: 20px;
        padding-left: 20px;
      }

      .topbar {
        padding-right: 20px;
        padding-left: 20px;
      }

      .stats-grid {
        gap: 10px;
      }

      .stat-card {
        padding: 15px;
      }
    }

    @media (max-width: 820px) {
      .sidebar-resizer {
        display: none !important;
      }

      .main {
        margin-left: 0 !important;
        width: 100% !important;
      }

      .mobile-menu {
        display: inline-flex !important;
      }

      .sidebar {
        width: 280px !important;
        max-width: 85vw !important;
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        z-index: 1000 !important;
        transform: translateX(-100%) !important;
        transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
        box-shadow: 15px 0 35px rgba(0, 0, 0, .15) !important;
        background: var(--white) !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
      }

      .sidebar.open {
        transform: translateX(0) !important;
      }

      .mobile-backdrop {
        position: fixed !important;
        inset: 0 !important;
        z-index: 999 !important;
        background: rgba(15, 23, 42, 0.5) !important;
        backdrop-filter: blur(2px) !important;
        -webkit-backdrop-filter: blur(2px) !important;
        display: none !important;
      }

      .mobile-backdrop.open {
        display: block !important;
      }

      .sidebar-close-btn {
        display: inline-flex !important;
      }

      .topbar {
        min-height: 68px;
        padding: 11px 16px;
      }

      .top-search {
        width: min(350px, 45vw);
      }

      .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 560px) {
      .topbar {
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
      }

      .topbar-left {
        width: 100%;
      }

      .topbar-right {
        width: 100%;
        gap: 10px;
      }

      .top-search {
        width: 100%;
        flex: 1;
      }

      .profile-divider {
        display: none;
      }

      .admin-profile {
        margin-left: auto;
      }

      .profile-copy {
        display: none;
      }

      .content {
        padding: 25px 14px 35px;
      }

      .page-heading {
        align-items: flex-start;
      }

      .page-description {
        max-width: 250px;
        line-height: 1.55;
      }

      .page-heading>.primary-button {
        min-height: 38px;
        padding: 0 10px;
        font-size: 10px;
      }

      .stats-grid {
        gap: 9px;
      }

      .stat-card {
        padding: 13px;
      }

      .stat-value {
        font-size: 20px;
      }

      .stat-label {
        font-size: 10px;
      }

      .panel-heading {
        align-items: flex-start;
        padding: 17px 14px 13px;
      }

      .panel-filters {
        width: 130px;
      }

      .filter-select {
        width: 100%;
        min-width: 0;
      }

      th,
      td {
        padding-right: 12px;
        padding-left: 12px;
      }

      .table-footer {
        align-items: flex-start;
        flex-direction: column;
        padding: 12px 14px;
      }

      .pagination {
        align-self: flex-end;
      }
    }

    @media (prefers-reduced-motion: reduce) {

      *,
      *::before,
      *::after {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
      }
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      background: radial-gradient(ellipse at 92% 3%, rgba(249, 115, 22, .055), transparent 30rem), var(--canvas);
    }

    button,
    a,
    input,
    select {
      -webkit-tap-highlight-color: transparent;
    }

    button:focus-visible,
    a:focus-visible,
    input:focus-visible,
    select:focus-visible {
      outline: 3px solid rgba(249, 115, 22, .28);
      outline-offset: 3px;
    }

    @media (min-width: 1024px) {
      .sidebar {
        width: 264px;
        padding: 29px 19px 22px;
        border-right-color: var(--line);
        box-shadow: 8px 0 32px rgba(65, 43, 26, .025);
      }
    }

    .brand {
      padding-bottom: 36px;
    }

    .brand-mark {
      border-radius: 14px;
      box-shadow: 0 8px 18px rgba(249, 115, 22, .25);
      transition: transform .25s ease, box-shadow .25s ease;
    }

    .brand:hover .brand-mark {
      transform: rotate(-6deg) scale(1.05);
      box-shadow: 0 10px 22px rgba(249, 115, 22, .3);
    }

    .nav-label {
      margin-bottom: 13px;
    }

    .nav-list {
      gap: 7px;
    }

    .nav-item {
      position: relative;
      min-height: 45px;
      padding: 11px 13px;
      border-radius: 11px;
      transition: background .2s ease, color .2s ease, transform .2s ease;
    }

    .nav-item:hover {
      transform: translateX(2px);
      background: #fff8f2;
    }

    .nav-item:active {
      transform: translateX(1px);
    }

    .nav-item.active {
      background: linear-gradient(100deg, #fff0e2, #fff8f2);
      color: var(--orange-dark);
    }

    .nav-item.active::before {
      position: absolute;
      top: 10px;
      bottom: 10px;
      left: 0;
      width: 3px;
      border-radius: 0 4px 4px 0;
      background: var(--orange);
      content: "";
    }

    .nav-item svg {
      transition: transform .2s ease;
    }

    .nav-item:hover svg,
    .nav-item.active svg {
      transform: scale(1.08);
    }

    .sidebar-link {
      transition: color .18s ease, transform .18s ease;
    }

    .sidebar-link:hover {
      transform: translateX(3px);
    }

    @media (min-width: 1024px) {
      .main {
        margin-left: 264px;
      }
    }

    .topbar {
      min-height: 78px;
      padding: 13px 40px;
      border-bottom-color: var(--line);
      background: rgba(255, 255, 255, .86);
      box-shadow: 0 5px 22px rgba(60, 42, 28, .025);
    }

    .crumb strong {
      color: var(--ink);
    }

    .top-search {
      height: 43px;
      border-color: #eaeaea;
      border-radius: 11px;
      background: #fff;
      transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .top-search:focus-within {
      background: #fff;
    }

    .search-button,
    .primary-button {
      border-radius: 10px;
      box-shadow: 0 5px 12px rgba(249, 115, 22, .13);
    }

    .search-button:hover,
    .primary-button:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 18px rgba(249, 115, 22, .23);
    }

    .admin-profile {
      padding: 5px 10px 5px 5px;
      border-radius: 30px;
      transition: background .2s ease;
    }

    .admin-profile:hover {
      background: #fff7f0;
    }

    .avatar {
      box-shadow: inset 0 0 0 1px rgba(198, 90, 10, .08);
    }

    .logout-button {
      min-height: 35px;
      padding: 0 12px;
      border-color: #eaeaea;
      border-radius: 9px;
      transition: border-color .2s ease, color .2s ease, background .2s ease;
    }

    .logout-button:hover {
      background: #fff8f2;
    }

    .content {
      padding: 36px 40px 54px;
    }

    .page-heading {
      position: relative;
      min-height: 162px;
      align-items: center;
      margin-bottom: 23px;
      padding: 27px 30px;
      overflow: hidden;
      border: 1px solid #f5e8dc;
      border-radius: 20px;
      background:
        radial-gradient(circle at 90% 15%, rgba(249, 115, 22, .11), transparent 13rem),
        linear-gradient(110deg, #fff 0%, #fffaf5 100%);
      box-shadow: 0 10px 30px rgba(95, 62, 35, .035);
      animation: dashboard-enter .5s ease both;
    }

    .page-heading>* {
      position: relative;
      z-index: 1;
    }

    .page-heading::after {
      position: absolute;
      top: -56px;
      right: 13%;
      width: 180px;
      height: 180px;
      border: 1px solid rgba(249, 115, 22, .08);
      border-radius: 50%;
      box-shadow: 0 0 0 23px rgba(249, 115, 22, .025), 0 0 0 48px rgba(249, 115, 22, .018);
      content: "";
      pointer-events: none;
    }

    .eyebrow {
      letter-spacing: 1.45px;
    }

    h1 {
      font-size: clamp(25px, 2.7vw, 33px);
      letter-spacing: -1.2px;
    }

    .page-description {
      color: #777;
      font-size: 13px;
      line-height: 1.65;
    }

    .date-label {
      color: #aaa;
    }

    .page-heading>.primary-button {
      min-height: 44px;
      padding: 0 17px;
    }

    .stats-grid {
      gap: 16px;
      margin-bottom: 25px;
    }

    .stat-card {
      position: relative;
      min-height: 145px;
      padding: 19px 20px;
      overflow: hidden;
      border-color: #f0f0f0;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(67, 45, 29, .035);
      animation: dashboard-enter .5s ease both;
      transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }

    .stat-card::after {
      position: absolute;
      right: -25px;
      bottom: -34px;
      width: 92px;
      height: 92px;
      border-radius: 50%;
      background: rgba(249, 115, 22, .035);
      content: "";
      transition: transform .3s ease, background .3s ease;
    }

    .stat-card:nth-child(1) {
      animation-delay: .06s;
    }

    .stat-card:nth-child(2) {
      animation-delay: .12s;
    }

    .stat-card:nth-child(3) {
      animation-delay: .18s;
    }

    .stat-card:nth-child(4) {
      animation-delay: .24s;
    }

    .stat-card {
      animation-name: dashboard-fade;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      border-color: #f4d8c2;
      box-shadow: 0 15px 32px rgba(67, 45, 29, .08);
    }

    .stat-card:hover::after {
      transform: scale(1.2);
      background: rgba(249, 115, 22, .06);
    }

    .stat-label {
      font-size: 12px;
    }

    .stat-icon {
      width: 39px;
      height: 39px;
      border-radius: 12px;
      transition: transform .25s ease, background .25s ease;
    }

    .stat-card:hover .stat-icon {
      transform: rotate(-5deg) scale(1.06);
      background: var(--orange-pale);
    }

    .stat-value {
      position: relative;
      z-index: 1;
      margin-top: 15px;
      font-size: 26px;
    }

    .stat-note {
      position: relative;
      z-index: 1;
      font-size: 10px;
    }

    .table-panel {
      border-color: #eee;
      border-radius: 17px;
      box-shadow: 0 12px 34px rgba(67, 45, 29, .045);
      animation: dashboard-enter .55s .12s ease both;
    }

    .panel-heading {
      padding: 22px 23px 17px;
    }

    .panel-title {
      font-size: 16px;
    }

    .panel-subtitle {
      font-size: 11px;
    }

    .filter-select {
      height: 39px;
      border-color: #eaeaea;
      border-radius: 10px;
      transition: border-color .2s ease, box-shadow .2s ease;
    }

    .filter-select:focus {
      box-shadow: 0 0 0 3px rgba(249, 115, 22, .08);
    }

    thead {
      background: #fafafa;
    }

    th {
      padding-top: 13px;
      padding-bottom: 13px;
      color: #999;
    }

    td {
      padding-top: 15px;
      padding-bottom: 15px;
      border-top-color: #f2f2f2;
    }

    tbody tr {
      transition: background .18s ease, box-shadow .18s ease;
    }

    tbody tr:hover {
      background: #fffaf6;
    }

    tbody tr:hover td:first-child {
      box-shadow: inset 3px 0 var(--orange);
    }

    .status-badge {
      padding: 6px 10px;
    }

    .action-button {
      min-height: 30px;
      padding: 0 9px;
      border-radius: 8px;
      transition: transform .18s ease, background .18s ease;
    }

    .action-button:hover {
      transform: translateY(-1px);
    }

    .table-footer {
      padding: 15px 21px;
      border-top-color: #f1f1f1;
    }

    .page-button {
      transition: color .18s ease, border-color .18s ease, background .18s ease, transform .18s ease;
    }

    .page-button:hover:not(:disabled) {
      transform: translateY(-1px);
      background: #fff8f2;
      border-color: #f5e5d7;
    }

    .page-button.current {
      box-shadow: 0 4px 10px rgba(249, 115, 22, .2);
    }

    dialog {
      border-radius: 19px;
    }

    dialog[open] {
      animation: modal-enter .22s ease both;
    }

    .modal-head {
      padding: 23px 24px 17px;
    }

    .modal-form {
      padding: 19px 24px 23px;
    }

    .modal-close,
    .secondary-button {
      transition: background .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
    }

    .modal-close:hover {
      background: #fff1e5;
      color: var(--orange-dark);
    }

    .secondary-button:hover {
      border-color: #f3d9c5;
      background: #fffaf5;
      color: var(--ink);
    }

    .image-upload {
      margin-top: 11px;
    }

    .image-upload input {
      height: auto;
      padding: 9px;
      background: #fffaf5;
    }

    .image-upload-hint {
      display: block;
      margin-top: 5px;
      color: #999;
      font-size: 10px;
      line-height: 1.5;
    }

    .image-preview {
      display: block;
      width: 100%;
      max-height: 190px;
      margin-top: 12px;
      border: 1px solid #eee;
      border-radius: 10px;
      object-fit: cover;
    }

    .image-preview[hidden] {
      display: none;
    }

    .image-preview-error {
      color: #b94a48;
    }

    .product-image-cell {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 180px;
    }

    .product-image-cell img {
      width: 40px;
      height: 40px;
      flex: 0 0 auto;
      border-radius: 7px;
      background: #fff7ed;
      object-fit: cover;
    }

    .product-image-cell span {
      overflow: hidden;
      max-width: 170px;
      color: #777;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .toast {
      border-radius: 12px;
    }

    #chat-inbox[hidden] {
      display: none !important;
    }

    .chat-inbox {
      display: grid;
      grid-template-columns: minmax(320px, 360px) minmax(0, 1fr);
      gap: 18px;
      min-height: 620px;
      height: calc(100vh - 180px);
      max-height: 880px;
    }

    .chat-inbox-panel {
      min-width: 0;
      overflow: hidden;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      background: #fff;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
      display: flex;
      flex-direction: column;
    }

    /* Left Sidebar: Conversations & Mode Switcher */
    .chat-inbox-heading {
      padding: 16px 18px;
      border-bottom: 1px solid #f1f5f9;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      gap: 12px;
      flex-shrink: 0;
    }

    .chat-heading-top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .chat-heading-brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .chat-badge-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
      border: 1px solid #fed7aa;
      display: grid;
      place-items: center;
      font-size: 18px;
    }

    .chat-heading-brand h2 {
      margin: 0;
      font-size: 15px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.2px;
    }

    .chat-heading-brand p {
      margin: 2px 0 0;
      color: #64748b;
      font-size: 11px;
    }

    .chat-refresh-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      color: #64748b;
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: all .2s ease;
    }

    .chat-refresh-icon-btn:hover {
      background: #f1f5f9;
      color: #0f172a;
      border-color: #cbd5e1;
    }

    .chat-refresh-icon-btn.spinning svg {
      animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    /* Modern Mode Switcher Card */
    .chat-mode-control-card {
      background: linear-gradient(135deg, #fffaf5 0%, #fff1e6 100%);
      border: 1px solid #fed7aa;
      border-radius: 14px;
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .chat-mode-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .chat-mode-label {
      font-size: 11px;
      font-weight: 750;
      color: #9a3412;
    }

    .chat-mode-status-tag {
      font-size: 10.5px;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 20px;
    }

    .chat-mode-status-tag.mode-auto {
      background: #f97316;
      color: #ffffff;
      box-shadow: 0 2px 6px rgba(249, 115, 22, 0.25);
    }

    .chat-mode-status-tag.mode-admin {
      background: #059669;
      color: #ffffff;
      box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
    }

    .chat-mode-switch-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      background: rgba(255, 255, 255, 0.6);
      padding: 4px;
      border-radius: 10px;
      border: 1px solid rgba(254, 215, 170, 0.8);
    }

    .btn-mode-pill {
      border: 0;
      background: transparent;
      padding: 7px 10px;
      border-radius: 8px;
      font-size: 11.5px;
      font-weight: 750;
      color: #64748b;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      transition: all .2s ease;
    }

    .btn-mode-pill:hover:not(.active) {
      background: rgba(255, 255, 255, 0.9);
      color: #0f172a;
    }

    .btn-mode-pill.active {
      background: #ea580c;
      color: #ffffff;
      box-shadow: 0 3px 8px rgba(234, 88, 12, 0.3);
    }

    .btn-mode-pill[data-mode="admin"].active {
      background: #059669;
      box-shadow: 0 3px 8px rgba(5, 150, 105, 0.3);
    }

    .chat-mode-hint {
      margin: 0;
      font-size: 10.5px;
      line-height: 1.45;
      color: #9a3412;
    }

    /* Search Box */
    .chat-search-box {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 0 10px;
      height: 36px;
      transition: all .15s ease;
    }

    .chat-search-box:focus-within {
      border-color: #f97316;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }

    .chat-search-box svg {
      color: #94a3b8;
      flex-shrink: 0;
    }

    .chat-search-box input {
      border: 0;
      background: transparent;
      outline: 0;
      width: 100%;
      font-size: 12px;
      color: #0f172a;
    }

    .chat-search-box input::placeholder {
      color: #94a3b8;
    }

    /* Conversations List */
    .chat-conversation-list {
      flex: 1;
      min-height: 0;
      overflow-y: auto;
      padding: 6px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .chat-conversation-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      width: 100%;
      padding: 12px 14px;
      border: 1px solid transparent;
      border-radius: 12px;
      background: #ffffff;
      text-align: left;
      cursor: pointer;
      transition: all .15s ease;
    }

    .chat-conversation-item:hover {
      background: #f8fafc;
      border-color: #f1f5f9;
    }

    .chat-conversation-item.active {
      background: #fff7ed;
      border-color: #fed7aa;
      border-left: 4px solid #f97316;
      box-shadow: 0 2px 8px rgba(249, 115, 22, 0.08);
    }

    .conv-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
      color: #0369a1;
      font-weight: 800;
      font-size: 15px;
      display: grid;
      place-items: center;
      flex-shrink: 0;
    }

    .chat-conversation-item.active .conv-avatar {
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: #ffffff;
    }

    .conv-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .conv-top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .conv-name {
      font-size: 13px;
      font-weight: 750;
      color: #0f172a;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .conv-time {
      font-size: 11px;
      color: #94a3b8;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .conv-bottom-row {
      display: flex;
      align-items: center;
    }

    .conv-preview {
      margin: 0;
      font-size: 12px;
      color: #64748b;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      line-height: 1.4;
    }

    .prefix-user {
      color: #0f172a;
      font-weight: 600;
    }

    .prefix-admin {
      color: #ea580c;
      font-weight: 700;
    }

    .prefix-ai {
      color: #8b5cf6;
      font-weight: 700;
    }

    .conv-meta-row {
      display: flex;
      align-items: center;
      margin-top: 2px;
    }

    .conv-tag {
      font-size: 10px;
      font-weight: 600;
      padding: 1px 6px;
      border-radius: 4px;
      background: #f1f5f9;
      color: #64748b;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .chat-empty-state {
      padding: 30px 16px;
      text-align: center;
      color: #94a3b8;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    .chat-empty-state .empty-icon {
      font-size: 28px;
    }

    .chat-empty-state p {
      margin: 0;
      font-size: 12px;
      line-height: 1.5;
    }

    /* Right Main Chat Thread */
    .chat-thread {
      display: flex;
      flex-direction: column;
      min-height: 0;
    }

    .chat-thread-heading {
      padding: 12px 18px;
      border-bottom: 1px solid #f1f5f9;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      flex-shrink: 0;
      position: relative;
    }

    .chat-customer-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
      flex: 1 1 auto;
    }

    .chat-customer-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, #fed7aa 0%, #f97316 100%);
      color: #ffffff;
      font-size: 18px;
      font-weight: 800;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(249, 115, 22, 0.2);
    }

    .chat-customer-info {
      display: flex;
      flex-direction: column;
      gap: 3px;
      min-width: 0;
    }

    .chat-customer-name-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .chat-customer-name-row h2 {
      margin: 0;
      font-size: 15px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.2px;
      white-space: normal;
      word-break: break-word;
    }

    .chat-customer-type-badge {
      font-size: 10px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 999px;
      background: #f1f5f9;
      color: #475569;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
    }

    .chat-customer-info p {
      margin: 0;
      font-size: 11.5px;
      color: #64748b;
      word-break: break-all;
      line-height: 1.4;
    }

    .chat-date-separator {
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 18px 0 10px;
      position: relative;
    }

    .chat-date-separator::before {
      content: '';
      position: absolute;
      left: 10%;
      right: 10%;
      top: 50%;
      height: 1px;
      background: #e2e8f0;
      z-index: 1;
    }

    .chat-date-separator span {
      position: relative;
      z-index: 2;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #64748b;
      font-size: 10.5px;
      font-weight: 750;
      padding: 3px 12px;
      border-radius: 20px;
      letter-spacing: 0.3px;
    }

    .chat-thread-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      flex-shrink: 0;
    }

    .chat-assistant-status-pill {
      font-size: 11px;
      font-weight: 700;
      padding: 5px 12px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
    }

    .chat-assistant-status-pill.auto {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }

    .chat-assistant-status-pill.direct {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
    }

    .chat-assistant-status-pill.paused {
      background: #fffbeb;
      color: #b45309;
      border: 1px solid #fde68a;
    }

    @media (max-width: 900px) {
      .chat-thread-heading {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .chat-customer-profile {
        width: 100%;
      }
      .chat-thread-actions {
        width: 100%;
        justify-content: flex-start;
        padding-top: 8px;
        border-top: 1px dashed #f1f5f9;
      }
    }

    /* Message List */
    .admin-chat-messages {
      flex: 1;
      min-height: 0;
      overflow-y: auto;
      padding: 20px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .chat-thread-empty {
      margin: auto;
      text-align: center;
      color: #94a3b8;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    .chat-thread-empty span {
      font-size: 32px;
    }

    .chat-thread-empty p {
      margin: 0;
      font-size: 13px;
    }

    .admin-chat-message {
      width: fit-content;
      max-width: min(80%, 600px);
      padding: 12px 16px;
      border-radius: 16px 16px 16px 4px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 14px;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    /* Outgoing Admin Message */
    .admin-chat-message.outgoing {
      align-self: flex-end;
      border-radius: 16px 16px 4px 16px;
      background: linear-gradient(135deg, #fffaf5 0%, #fff7ed 100%);
      border-color: #fed7aa;
      box-shadow: 0 2px 10px rgba(249, 115, 22, 0.08);
    }

    /* AI Assistant Message */
    .admin-chat-message.assistant {
      align-self: flex-start;
      border-radius: 16px 16px 16px 4px;
      background: linear-gradient(135deg, #faf5ff 0%, #f5f3ff 100%);
      border-color: #ddd6fe;
      box-shadow: 0 2px 10px rgba(139, 92, 246, 0.08);
    }

    /* Consecutive messages from same sender: compact spacing (4px) */
    .admin-chat-message.outgoing + .admin-chat-message.outgoing,
    .admin-chat-message:not(.outgoing):not(.assistant) + .admin-chat-message:not(.outgoing):not(.assistant),
    .admin-chat-message.assistant + .admin-chat-message.assistant,
    .admin-chat-message.same-sender-consecutive {
      margin-top: -10px;
    }

    .admin-msg-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 4px;
    }

    /* Distinct Sender Badges */
    .admin-msg-sender-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 750;
      padding: 3px 9px;
      border-radius: 999px;
      line-height: 1.25;
      letter-spacing: 0.01em;
      white-space: nowrap;
    }

    .admin-msg-sender-badge.badge-customer {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #1e40af;
    }

    .admin-msg-sender-badge.badge-admin {
      background: #fef3c7;
      border: 1px solid #fde68a;
      color: #92400e;
    }

    .admin-msg-sender-badge.badge-ai {
      background: #f3e8ff;
      border: 1px solid #d8b4fe;
      color: #6b21a8;
    }

    .admin-msg-time {
      font-size: 11px;
      font-weight: 500;
      color: #94a3b8;
      white-space: nowrap;
    }

    .admin-msg-text {
      font-size: 13.5px;
      line-height: 1.6;
      color: #1e293b;
      word-break: break-word;
    }

    .admin-chat-message.outgoing .admin-msg-text {
      color: #431407;
    }

    .admin-chat-message.assistant .admin-msg-text {
      color: #3b0764;
    }

    /* AI Copilot Bar */
    .admin-chat-ai-bar {
      padding: 10px 16px;
      background: linear-gradient(135deg, #fff7ed 0%, #fffaf5 100%);
      border-top: 1px solid #fed7aa;
      border-bottom: 1px solid #ffedd5;
      flex-shrink: 0;
    }

    .ai-bar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 8px;
    }

    .ai-bar-title {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11.5px;
      font-weight: 750;
      color: #9a3412;
    }

    .ai-sparkle {
      font-size: 14px;
    }

    .ai-action-btn {
      padding: 6px 12px;
      font-size: 11.5px;
      font-weight: 750;
      border-radius: 8px;
      border: 1px solid #f97316;
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: #ffffff;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      box-shadow: 0 2px 6px rgba(234, 88, 12, 0.2);
      transition: all .18s ease;
    }

    .ai-action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(234, 88, 12, 0.3);
    }

    .ai-action-btn.secondary {
      background: #ffffff;
      color: #c2410c;
      border-color: #fdba74;
      box-shadow: none;
    }

    .ai-action-btn.secondary:hover {
      background: #fff7ed;
      border-color: #ea580c;
    }

    .ai-action-btn.btn-sm {
      padding: 4px 10px;
      font-size: 11px;
    }

    .ai-quick-chips {
      display: flex;
      align-items: center;
      gap: 6px;
      overflow-x: auto;
      padding-bottom: 2px;
    }

    .quick-chip-label {
      font-size: 11px;
      font-weight: 700;
      color: #9a3412;
      white-space: nowrap;
    }

    .ai-chip {
      padding: 4px 10px;
      font-size: 11px;
      font-weight: 600;
      border-radius: 20px;
      border: 1px solid #fed7aa;
      background: #ffffff;
      color: #7c2d12;
      cursor: pointer;
      white-space: nowrap;
      transition: all .15s ease;
    }

    .ai-chip:hover {
      background: #f97316;
      color: #ffffff;
      border-color: #f97316;
      transform: translateY(-1px);
    }

    /* Chat Input Form */
    .admin-chat-form {
      padding: 12px 18px;
      background: #ffffff;
      border-top: 1px solid #f1f5f9;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Tools Cluster: Product Picker + Attachment Button */
    .admin-chat-tools-cluster {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .btn-attach-trigger {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 12px;
      border: 1px solid #fed7aa;
      background: #fff7ed;
      color: #ea580c;
      cursor: pointer;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }

    .btn-attach-trigger:hover {
      background: #f97316;
      color: #ffffff;
      border-color: #f97316;
      box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);
      transform: translateY(-1px);
    }

    /* Upload Preview Strip */
    .admin-chat-upload-preview {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 18px;
      background: linear-gradient(135deg, #fffaf5 0%, #fff7ed 100%);
      border-top: 1px solid #fed7aa;
      border-bottom: 1px solid #ffedd5;
      flex-shrink: 0;
      animation: fadeInPreview 0.2s ease;
    }

    @keyframes fadeInPreview {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .admin-preview-thumb-wrap {
      position: relative;
      width: 44px;
      height: 44px;
      border-radius: 8px;
      border: 1px solid #fed7aa;
      background: #ffffff;
      flex-shrink: 0;
    }

    #admin-preview-thumb {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 7px;
      display: block;
    }

    .admin-btn-remove-preview {
      position: absolute;
      top: -7px;
      right: -7px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: #ef4444;
      color: #ffffff;
      border: 2px solid #ffffff;
      font-size: 13px;
      font-weight: bold;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      transition: all 0.15s ease;
      padding: 0;
    }

    .admin-btn-remove-preview:hover {
      background: #dc2626;
      transform: scale(1.1);
    }

    .admin-preview-meta {
      display: flex;
      flex-direction: column;
      gap: 2px;
      min-width: 0;
      overflow: hidden;
    }

    .admin-preview-filename {
      font-size: 12px;
      font-weight: 600;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 260px;
    }

    .admin-preview-filesize {
      font-size: 11px;
      color: #64748b;
    }

    .admin-chat-input-wrapper {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 12px;
      padding: 4px 6px 4px 14px;
      transition: all .18s ease;
      flex: 1;
      min-width: 0;
    }

    .admin-chat-input-wrapper:focus-within {
      border-color: #f97316;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.12);
    }

    .admin-chat-input-wrapper input {
      flex: 1;
      min-width: 0;
      height: 38px;
      border: 0;
      background: transparent;
      outline: 0;
      font-size: 13.5px;
      color: #0f172a;
    }

    .admin-chat-input-wrapper input::placeholder {
      color: #94a3b8;
    }

    .admin-chat-input-wrapper button {
      height: 36px;
      padding: 0 16px;
      border: 0;
      border-radius: 9px;
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: #ffffff;
      font-size: 13px;
      font-weight: 750;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 6px rgba(249, 115, 22, 0.25);
      transition: all .18s ease;
    }

    .admin-chat-input-wrapper button:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(249, 115, 22, 0.35);
    }

    .admin-chat-input-wrapper button:disabled {
      cursor: wait;
      opacity: 0.6;
    }

    #admin-chat-feedback {
      min-height: 18px;
      margin: 0;
      padding: 0 18px 8px;
      color: #059669;
      font-size: 11px;
      font-weight: 600;
    }

    #admin-chat-feedback.error {
      color: #dc2626;
    }

    .ai-chip {
      padding: 3px 8px;
      font-size: 10px;
      border-radius: 12px;
      border: 1px solid #fed7aa;
      background: #fff;
      color: #7c2d12;
      cursor: pointer;
      white-space: nowrap;
      transition: all .15s ease;
    }

    .ai-chip:hover {
      background: #f97316;
      color: #fff;
      border-color: #f97316;
    }

    /* Kho Món Ăn FASTFOOD (Product Picker Drawer) */
    .chat-inbox-panel.chat-thread {
      position: relative;
      overflow: hidden;
    }

    .btn-picker-trigger {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 12px;
      border: 1px solid #fed7aa;
      background: #fff7ed;
      color: #ea580c;
      font-size: 12.5px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }

    .btn-picker-trigger:hover {
      background: #f97316;
      color: #fff;
      border-color: #f97316;
      box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);
    }

    .admin-product-picker-drawer {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 440px;
      max-width: 100%;
      background: #ffffff;
      border-left: 1px solid #e2e8f0;
      box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      z-index: 100;
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      animation: drawerSlideIn 0.28s ease-out;
    }

    .admin-product-picker-drawer[hidden] {
      display: none !important;
    }

    @keyframes drawerSlideIn {
      from { transform: translateX(100%); }
      to { transform: translateX(0); }
    }

    .picker-drawer-header {
      padding: 14px 18px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f8fafc;
      flex-shrink: 0;
    }

    .picker-header-title {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .picker-header-icon {
      font-size: 24px;
    }

    .picker-header-title h3 {
      margin: 0;
      font-size: 15px;
      font-weight: 800;
      color: #0f172a;
    }

    .picker-header-title p {
      margin: 2px 0 0;
      font-size: 11.5px;
      color: #64748b;
    }

    .picker-header-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .picker-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      background: #fff;
      color: #64748b;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .picker-icon-btn:hover {
      background: #f1f5f9;
      color: #0f172a;
    }

    .picker-close-btn {
      font-size: 16px;
      font-weight: 700;
    }

    .picker-close-btn:hover {
      background: #fee2e2;
      color: #dc2626;
      border-color: #fecaca;
    }

    .picker-drawer-controls {
      padding: 12px 16px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      flex-direction: column;
      gap: 10px;
      background: #fff;
      flex-shrink: 0;
    }

    .picker-search-box {
      position: relative;
      display: flex;
      align-items: center;
    }

    .picker-search-box svg {
      position: absolute;
      left: 12px;
      color: #94a3b8;
    }

    .picker-search-box input {
      width: 100%;
      padding: 8px 32px 8px 34px;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      font-size: 13px;
      outline: none;
      transition: border-color 0.15s ease;
    }

    .picker-search-box input:focus {
      border-color: #f97316;
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    .picker-clear-btn {
      position: absolute;
      right: 8px;
      border: none;
      background: transparent;
      color: #94a3b8;
      font-size: 12px;
      cursor: pointer;
    }

    .picker-status-tabs {
      display: flex;
      gap: 6px;
      overflow-x: auto;
      scrollbar-width: none;
    }

    .picker-status-tabs::-webkit-scrollbar {
      display: none;
    }

    .picker-tab {
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      color: #475569;
      font-size: 11.5px;
      font-weight: 650;
      white-space: nowrap;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .picker-tab:hover {
      background: #f1f5f9;
    }

    .picker-tab.active {
      background: #f97316;
      color: #fff;
      border-color: #f97316;
    }

    .picker-category-filter {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #64748b;
    }

    .picker-select {
      flex: 1;
      padding: 5px 10px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      font-size: 12px;
      outline: none;
    }

    .picker-product-list {
      flex: 1;
      overflow-y: auto;
      padding: 12px 16px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    /* Product Card Item in Picker */
    .picker-product-card {
      display: flex;
      gap: 12px;
      padding: 10px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      background: #fff;
      transition: all 0.15s ease;
    }

    .picker-product-card:hover {
      border-color: #fed7aa;
      background: #fffdfa;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .picker-card-thumb-wrap {
      position: relative;
      width: 72px;
      height: 72px;
      border-radius: 10px;
      overflow: hidden;
      background: #f8fafc;
      flex-shrink: 0;
      border: 1px solid #f1f5f9;
    }

    .picker-card-thumb {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .picker-card-body {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .picker-card-title-row {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 8px;
    }

    .picker-card-name {
      margin: 0;
      font-size: 13px;
      font-weight: 750;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .picker-card-category {
      font-size: 10.5px;
      color: #94a3b8;
      flex-shrink: 0;
    }

    .picker-card-price-row {
      display: flex;
      align-items: baseline;
      gap: 6px;
    }

    .picker-price-current {
      font-size: 13.5px;
      font-weight: 800;
      color: #ea580c;
    }

    .picker-price-old {
      font-size: 11px;
      color: #94a3b8;
      text-decoration: line-through;
    }

    .picker-card-desc {
      margin: 0;
      font-size: 11px;
      color: #64748b;
      line-height: 1.35;
      display: -webkit-box;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .picker-card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: 4px;
    }

    .picker-status-tag {
      font-size: 10px;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 6px;
    }

    .picker-status-tag.in_stock {
      background: #dcfce7;
      color: #166534;
    }

    .picker-status-tag.out_of_stock {
      background: #ffedd5;
      color: #9a3412;
    }

    .picker-status-tag.unavailable_today {
      background: #fee2e2;
      color: #991b1b;
    }

    .btn-picker-send-chat {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 10px;
      border-radius: 8px;
      border: none;
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .btn-picker-send-chat:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(249, 115, 22, 0.35);
    }

    .picker-drawer-footer {
      padding: 10px 16px;
      border-top: 1px solid #f1f5f9;
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 11px;
      color: #64748b;
      flex-shrink: 0;
    }

    .picker-guide-text {
      color: #f97316;
    }

    /* Message product card bubble in Admin Chat */
    .admin-msg-product-card {
      display: flex;
      gap: 10px;
      padding: 10px;
      border-radius: 12px;
      border: 1px solid #fed7aa;
      background: #ffffff;
      margin-top: 6px;
      max-width: 320px;
    }

    .admin-product-card-thumb {
      width: 60px;
      height: 60px;
      aspect-ratio: 1 / 1;
      border-radius: 8px;
      object-fit: cover;
      flex-shrink: 0;
    }

    .admin-product-card-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .admin-product-card-tag {
      font-size: 9.5px;
      font-weight: 800;
      color: #ea580c;
      text-transform: uppercase;
    }

    .admin-product-card-title {
      margin: 0;
      font-size: 12.5px;
      font-weight: 750;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .admin-product-card-price-row {
      display: flex;
      align-items: baseline;
      gap: 6px;
    }

    .admin-product-card-price {
      font-size: 13px;
      font-weight: 800;
      color: #ea580c;
    }

    .admin-product-card-old-price {
      font-size: 10.5px;
      color: #94a3b8;
      text-decoration: line-through;
    }

    .admin-product-card-preview-note {
      font-size: 10px;
      color: #16a34a;
      font-weight: 600;
    }

    /* Message Image Bubble in Admin Chat */
    .admin-msg-image-bubble {
      max-width: 320px;
      width: 100%;
      border-radius: 12px;
      overflow: hidden;
      margin-top: 6px;
      cursor: zoom-in;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .admin-msg-image-bubble:hover {
      transform: scale(1.02);
      box-shadow: 0 4px 14px rgba(0,0,0,0.12);
    }

    .admin-chat-image-thumb {
      display: block;
      width: 100%;
      max-height: 280px;
      height: auto;
      object-fit: contain;
      background: #f1f5f9;
    }

    /* Admin Lightbox Modal */
    .admin-chat-lightbox-modal {
      position: fixed;
      inset: 0;
      z-index: 999999;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: ffFadeIn 0.2s ease;
    }

    .admin-chat-lightbox-modal[hidden] {
      display: none !important;
    }

    .admin-lightbox-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(4px);
    }

    .admin-lightbox-container {
      position: relative;
      z-index: 1;
      max-width: 90vw;
      max-height: 85vh;
    }

    .admin-lightbox-img,
    .admin-lightbox-container img {
      max-width: 100%;
      max-height: 85vh;
      object-fit: contain;
      border-radius: 12px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .admin-lightbox-close {
      position: absolute;
      top: -16px;
      right: -16px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #ffffff;
      color: #0f172a;
      border: none;
      font-size: 18px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
      transition: all 0.15s ease;
    }

    .admin-lightbox-close:hover {
      background: #f97316;
      color: #ffffff;
      transform: scale(1.1);
    }

    .nav-badge-ai {
      background: linear-gradient(135deg, #8b5cf6, #ec4899);
      color: #fff;
      font-weight: 800;
    }

    .reviews-controls {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .reviews-filter-select {
      min-width: 140px;
      height: 36px;
    }

    .ai-send-icon {
      width: 16px;
      height: 16px;
    }

    /* --- Dedicated Admin AI Chatbot Panel --- */
    .ai-bot-panel {
      border: 1px solid #eee;
      border-radius: 17px;
      background: #fff;
      box-shadow: var(--shadow);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: calc(100vh - 210px);
      min-height: 520px;
    }

    .ai-bot-panel[hidden] {
      display: none !important;
    }

    .ai-bot-container {
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .ai-bot-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
      color: #fff;
    }

    .ai-bot-header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .ai-avatar-wrap {
      position: relative;
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }

    .ai-status-indicator.online {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: #22c55e;
      border: 2px solid #1e1b4b;
    }

    .ai-bot-header h2 {
      margin: 0;
      font-size: 16px;
      font-weight: 800;
      color: #fff;
      letter-spacing: .2px;
    }

    .ai-bot-header p {
      margin: 3px 0 0;
      font-size: 11px;
      color: #cbd5e1;
    }

    .ai-clear-btn {
      padding: 6px 12px;
      font-size: 11px;
      font-weight: 700;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
      cursor: pointer;
      transition: all .2s ease;
    }

    .ai-clear-btn:hover {
      background: rgba(255, 255, 255, 0.25);
    }

    .ai-bot-chat-window {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .ai-chat-bubble {
      width: fit-content;
      max-width: min(85%, 760px);
      padding: 14px 18px;
      border-radius: 16px;
      background: #fff;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
      animation: modal-enter .25s ease both;
    }

    .ai-chat-bubble.assistant {
      align-self: flex-start;
      border-left: 4px solid #8b5cf6;
      background: #ffffff;
    }

    .ai-chat-bubble.user {
      align-self: flex-end;
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      color: #fff;
      border: none;
      box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);
    }

    .ai-chat-bubble.user p {
      color: #fff !important;
    }

    .ai-chat-bubble.user strong {
      color: #fff !important;
    }

    .ai-chat-bubble.user .bubble-time {
      color: rgba(255, 255, 255, 0.8) !important;
    }

    .bubble-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
      font-size: 11px;
      color: #64748b;
    }

    .bubble-avatar {
      font-size: 14px;
    }

    .bubble-header strong {
      color: #1e293b;
      font-weight: 700;
    }

    .bubble-time {
      margin-left: auto;
      font-size: 10px;
      color: #94a3b8;
    }

    .bubble-body {
      font-size: 13px;
      line-height: 1.65;
      color: #334155;
    }

    .bubble-body p {
      margin: 0 0 8px;
    }

    .bubble-body p:last-child {
      margin-bottom: 0;
    }

    .bubble-body ul {
      margin: 6px 0 10px 18px;
      padding: 0;
    }

    .bubble-body li {
      margin-bottom: 4px;
    }

    .bubble-body blockquote {
      margin: 8px 0;
      padding: 8px 12px;
      background: #f1f5f9;
      border-left: 3px solid #f97316;
      border-radius: 0 8px 8px 0;
      font-style: italic;
    }

    .ai-prompt-suggestions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 14px;
      padding-top: 12px;
      border-top: 1px dashed #e2e8f0;
    }

    .prompt-chip {
      padding: 6px 12px;
      font-size: 11px;
      font-weight: 600;
      border-radius: 20px;
      border: 1px solid #cbd5e1;
      background: #f8fafc;
      color: #334155;
      cursor: pointer;
      transition: all .15s ease;
    }

    .prompt-chip:hover {
      background: #8b5cf6;
      color: #fff;
      border-color: #8b5cf6;
      transform: translateY(-1px);
    }

    .ai-bot-input-form {
      display: flex;
      gap: 10px;
      padding: 14px 18px;
      background: #fff;
      border-top: 1px solid #e2e8f0;
    }

    .ai-bot-input-form input {
      flex: 1;
      height: 44px;
      padding: 0 16px;
      border: 1px solid #cbd5e1;
      border-radius: 12px;
      outline: none;
      font-size: 13px;
      transition: border-color .15s ease;
    }

    .ai-bot-input-form input:focus {
      border-color: #8b5cf6;
      box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.12);
    }

    .ai-bot-input-form button {
      padding: 0 20px;
      height: 44px;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
      transition: all .15s ease;
    }

    .ai-bot-input-form button:hover {
      background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
      transform: translateY(-1px);
    }

    .ai-bot-input-form button:disabled {
      opacity: .6;
      cursor: wait;
    }

    .section-purpose {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      margin-bottom: 20px;
      padding: 17px 20px;
      border: 1px solid #f1e7de;
      border-radius: 14px;
      background: linear-gradient(110deg, #fff, #fffaf5);
      animation: dashboard-enter .4s ease both;
    }

    .section-purpose-copy {
      min-width: 0;
    }

    .section-purpose-label {
      display: block;
      margin-bottom: 5px;
      color: var(--orange-dark);
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .8px;
      text-transform: uppercase;
    }

    .section-purpose-text {
      margin: 0;
      color: #777;
      font-size: 12px;
      line-height: 1.65;
    }

    .section-purpose-metric {
      flex: 0 0 auto;
      padding-left: 18px;
      border-left: 1px solid #f0e5db;
      text-align: right;
    }

    .section-purpose-metric strong {
      display: block;
      color: var(--ink);
      font-size: 19px;
      font-weight: 800;
    }

    .section-purpose-metric span {
      display: block;
      margin-top: 3px;
      color: #999;
      font-size: 10px;
    }

    .analytics-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.5fr) minmax(260px, .85fr);
      gap: 16px;
      margin-bottom: 20px;
      animation: dashboard-enter .5s .08s ease both;
    }

    .analytics-grid[hidden] {
      display: none;
    }

    .stats-grid[hidden],
    .section-purpose[hidden] {
      display: none;
    }

    .analytics-card {
      min-width: 0;
      padding: 21px 22px;
      border: 1px solid #eee;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 10px 28px rgba(67, 45, 29, .04);
    }

    .analytics-heading {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 15px;
    }

    .analytics-title {
      margin: 0;
      font-size: 14px;
      font-weight: 800;
    }

    .analytics-caption {
      margin: 5px 0 0;
      color: #999;
      font-size: 10px;
      line-height: 1.5;
    }

    .analytics-heading-main {
      display: flex;
      flex: 1 1 auto;
      min-width: 0;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
    }

    .analytics-heading-controls {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      flex: 0 0 auto;
    }

    .revenue-time-select {
      height: 32px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid #e7ded5;
      background: #fff;
      color: #4b3e36;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      outline: none;
      transition: all .2s ease;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
    }

    .revenue-time-select:hover {
      border-color: #ea580c;
      color: #ea580c;
    }

    .revenue-time-select:focus {
      border-color: #ea580c;
      box-shadow: 0 0 0 3px rgba(234, 88, 12, .15);
    }

    .chart-summary {
      display: flex;
      gap: 9px;
    }

    .chart-summary-item {
      min-width: 90px;
      padding: 8px 11px;
      border: 1px solid #f3eee9;
      border-radius: 11px;
      background: #fffaf6;
    }

    .chart-summary-item span {
      display: block;
      color: #999;
      font-size: 9px;
    }

    .chart-summary-item strong {
      display: block;
      margin-top: 3px;
      color: #35302c;
      font-size: 12px;
    }

    .realtime-badge {
      display: inline-flex;
      flex: 0 0 auto;
      align-items: center;
      gap: 6px;
      padding: 6px 9px;
      border-radius: 20px;
      background: #fff7ed;
      color: #a75a20;
      font-size: 9px;
      font-weight: 750;
    }

    .realtime-badge::before {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #aaa;
      content: "";
    }

    .realtime-badge.connected {
      background: #eaf8f1;
      color: #16835d;
    }

    .realtime-badge.connected::before {
      background: #15966a;
      box-shadow: 0 0 0 3px rgba(21, 150, 106, .12);
    }

    .realtime-badge.disconnected {
      background: #fff1f1;
      color: #b94a48;
    }

    .realtime-badge.disconnected::before {
      background: #dc5656;
    }

    .revenue-chart {
      display: block;
      width: 100%;
      height: auto;
      overflow: visible;
    }

    .revenue-chart[hidden],
    .donut-chart[hidden],
    .donut-legend[hidden] {
      display: none;
    }

    .revenue-chart text {
      fill: #999;
      font-family: inherit;
      font-size: 10px;
    }

    .revenue-chart .chart-grid {
      stroke: #f0f0f0;
      stroke-width: 1;
      stroke-dasharray: 4 5;
    }

    .revenue-chart .chart-axis {
      stroke: #e8e4e0;
      stroke-width: 1;
    }

    .revenue-chart .chart-area {
      fill: url(#sales-area-gradient);
    }

    .revenue-chart .chart-line {
      fill: none;
      stroke: url(#sales-line-gradient);
      stroke-linecap: round;
      stroke-linejoin: round;
      stroke-width: 3.5;
    }

    .revenue-chart .chart-point-halo {
      fill: #fff2e8;
    }

    .revenue-chart .chart-point {
      fill: #fff;
      stroke: #f97316;
      stroke-width: 2.5;
      transition: r .15s ease;
    }

    .revenue-chart .chart-point-group:hover .chart-point {
      r: 6;
    }

    .revenue-chart .chart-guide {
      stroke: #fed7aa;
      stroke-width: 1;
      stroke-dasharray: 3 4;
    }

    .donut-content {
      display: flex;
      align-items: center;
      gap: 19px;
      min-height: 190px;
    }

    .donut-chart {
      position: relative;
      display: grid;
      flex: 0 0 auto;
      width: 148px;
      height: 148px;
      place-items: center;
      border-radius: 50%;
      background: #f2f2f2;
      transition: background .35s ease, transform .25s ease;
    }

    .donut-chart:hover {
      transform: scale(1.025);
    }

    .donut-chart::before {
      position: absolute;
      width: 94px;
      height: 94px;
      border-radius: 50%;
      background: #fff;
      content: "";
    }

    .donut-center {
      position: relative;
      z-index: 1;
      max-width: 80px;
      text-align: center;
    }

    .donut-center strong {
      display: block;
      overflow: hidden;
      color: var(--ink);
      font-size: 15px;
      font-weight: 800;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .donut-center span {
      display: block;
      margin-top: 4px;
      color: #999;
      font-size: 9px;
    }

    .donut-legend {
      display: grid;
      flex: 1;
      gap: 10px;
      min-width: 0;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .donut-legend li {
      display: grid;
      grid-template-columns: 9px minmax(0, 1fr) auto;
      align-items: center;
      gap: 7px;
      min-width: 0;
    }

    .legend-swatch {
      width: 8px;
      height: 8px;
      border-radius: 3px;
    }

    .legend-name {
      overflow: hidden;
      color: #777;
      font-size: 10px;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .legend-value {
      color: #555;
      font-size: 9px;
      font-weight: 750;
      white-space: nowrap;
    }

    .chart-empty {
      display: grid;
      min-height: 180px;
      place-items: center;
      color: #999;
      font-size: 11px;
      text-align: center;
    }

    .chart-empty[hidden] {
      display: none;
    }

    #reviews-section[hidden] {
      display: none;
    }

    .reviews-panel {
      overflow: hidden;
      border: 1px solid #eee;
      border-radius: 17px;
      background: #fff;
      box-shadow: var(--shadow);
    }

    .reviews-panel-heading {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
      padding: 21px 23px;
      border-bottom: 1px solid #f1f1f1;
    }

    .reviews-panel-heading h2 {
      margin: 0;
      font-size: 16px;
    }

    .reviews-panel-heading p {
      margin: 5px 0 0;
      color: #999;
      font-size: 11px;
    }

    .reviews-count {
      padding: 7px 10px;
      border-radius: 20px;
      background: #fff4e8;
      color: #c65a0a;
      font-size: 10px;
      font-weight: 750;
      white-space: nowrap;
    }

    .reviews-list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 13px;
      padding: 16px;
    }

    .review-card {
      min-width: 0;
      padding: 17px;
      border: 1px solid #f0ece8;
      border-radius: 14px;
      background: linear-gradient(145deg, #fff, #fffaf6);
    }

    .review-card-top,
    .review-card-meta {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
    }

    .review-customer {
      min-width: 0;
    }

    .review-customer strong {
      display: block;
      overflow: hidden;
      font-size: 12px;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .review-customer span,
    .review-card-meta {
      color: #999;
      font-size: 10px;
    }

    .review-target {
      flex: 0 0 auto;
      padding: 5px 8px;
      border-radius: 20px;
      background: #eef7f2;
      color: #16835d;
      font-size: 9px;
      font-weight: 750;
    }

    .review-stars {
      margin-top: 12px;
      color: #f4a723;
      font-size: 16px;
      letter-spacing: 2px;
    }

    .review-comment {
      min-height: 38px;
      margin: 7px 0 12px;
      color: #4b4540;
      font-size: 12px;
      line-height: 1.6;
      overflow-wrap: anywhere;
      white-space: pre-wrap;
    }

    .review-card-meta {
      padding-top: 10px;
      border-top: 1px solid #f1ece7;
    }

    .reviews-empty {
      grid-column: 1 / -1;
      padding: 42px 18px;
      color: #999;
      font-size: 12px;
      text-align: center;
    }

    .chart-live-note {
      margin: 9px 0 0;
      color: #999;
      font-size: 9px;
      line-height: 1.5;
    }

    .nav-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-left: auto;
      padding: 0 6px;
      min-width: 18px;
      height: 18px;
      border-radius: 999px;
      background: var(--orange);
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      line-height: 18px;
      text-align: center;
      box-sizing: border-box;
      flex-shrink: 0;
      white-space: nowrap;
      animation: badge-pulse 1.8s infinite;
    }

    @keyframes badge-pulse {
      0%, 100% {
        transform: scale(1);
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.45);
      }
      50% {
        transform: scale(1.08);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.7);
      }
    }

    .nav-badge-messages {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #fff;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 2px 7px;
      min-width: 18px;
      text-align: center;
      line-height: 1.2;
      box-shadow: 0 2px 6px rgba(239, 68, 68, 0.45);
      animation: badge-pulse 2s infinite ease-in-out;
    }

    .nav-item > span:first-of-type {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 1;
    }

    .conv-unread-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #fff;
      font-size: 0.72rem;
      font-weight: 700;
      min-width: 18px;
      height: 18px;
      line-height: 1;
      padding: 0 6px;
      border-radius: 999px;
      margin-left: 6px;
      box-shadow: 0 2px 6px rgba(239, 68, 68, 0.45);
    }

    .review-card.just-added {
      animation: review-pulse 3s ease-out;
      border-color: var(--orange);
    }

    @keyframes review-pulse {
      0% {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 0 0 4px rgba(249, 115, 22, .25);
        border-color: var(--orange);
      }

      50% {
        box-shadow: 0 0 0 4px rgba(249, 115, 22, .15);
        border-color: var(--orange);
      }

      100% {
        transform: translateY(0) scale(1);
        box-shadow: none;
        border-color: #f0ece8;
      }
    }

    .new-tag {
      display: inline-block;
      margin-left: 6px;
      padding: 2px 6px;
      border-radius: 6px;
      background: var(--orange-pale);
      color: var(--orange-dark);
      font-size: 9px;
      font-weight: 800;
      vertical-align: middle;
    }

    @keyframes dashboard-enter {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes modal-enter {
      from {
        opacity: 0;
        transform: translateY(8px) scale(.985);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    @keyframes dashboard-fade {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @media (max-width: 1080px) {
      .content {
        padding-right: 25px;
        padding-left: 25px;
      }

      .topbar {
        padding-right: 25px;
        padding-left: 25px;
      }

      .stats-grid {
        gap: 11px;
      }

      .stat-card {
        padding-right: 15px;
        padding-left: 15px;
      }

      .analytics-grid {
        grid-template-columns: minmax(0, 1.3fr) minmax(240px, .9fr);
        gap: 12px;
      }

      .analytics-card {
        padding: 18px;
      }

      .donut-content {
        gap: 13px;
      }

      .donut-chart {
        width: 128px;
        height: 128px;
      }

      .donut-chart::before {
        width: 82px;
        height: 82px;
      }
    }

    @media (max-width: 767px) {
      .topbar {
        min-height: 70px;
        padding: 11px 16px;
      }

      .content {
        padding-top: 26px;
      }

      .page-heading {
        min-height: 120px;
      }

      .analytics-grid {
        grid-template-columns: 1fr;
      }

      .donut-content {
        justify-content: flex-start;
      }

      .chat-inbox {
        grid-template-columns: 1fr;
      }

      .reviews-list {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 560px) {
      .topbar {
        gap: 10px;
        padding: 10px 14px;
      }

      .topbar-right {
        gap: 8px;
      }

      .content {
        padding: 20px 14px 36px;
      }

      .page-heading {
        min-height: 0;
        align-items: flex-start;
        margin-bottom: 17px;
        padding: 21px 17px;
        border-radius: 16px;
      }

      .page-description {
        max-width: 250px;
        line-height: 1.55;
      }

      .page-heading>.primary-button {
        min-height: 38px;
        padding: 0 10px;
        font-size: 10px;
      }

      .stats-grid {
        gap: 9px;
        margin-bottom: 17px;
      }

      .stat-card {
        min-height: 132px;
        padding: 13px;
        border-radius: 13px;
      }

      .stat-value {
        font-size: 21px;
      }

      .stat-label {
        font-size: 10px;
      }

      .panel-heading {
        align-items: flex-start;
        padding: 17px 14px 13px;
      }

      .panel-filters {
        width: 130px;
      }

      .filter-select {
        width: 100%;
        min-width: 0;
      }

      th,
      td {
        padding-right: 12px;
        padding-left: 12px;
      }

      .table-footer {
        align-items: flex-start;
        flex-direction: column;
        padding: 12px 14px;
      }

      .pagination {
        align-self: flex-end;
      }

      .section-purpose {
        align-items: flex-start;
        gap: 12px;
        padding: 15px;
      }

      .section-purpose-metric {
        padding-left: 12px;
      }

      .section-purpose-metric strong {
        font-size: 17px;
      }

      .analytics-card {
        padding: 16px 14px;
        border-radius: 14px;
      }

      .donut-content {
        gap: 14px;
      }

      .donut-chart {
        width: 118px;
        height: 118px;
      }

      .donut-chart::before {
        width: 76px;
        height: 76px;
      }

      .donut-legend {
        gap: 8px;
      }

      .chat-inbox {
        min-height: 0;
        grid-template-columns: 1fr;
        max-height: none;
      }

      .chat-conversation-list {
        max-height: 220px;
      }

      .chat-thread {
        min-height: 440px;
      }

      .chat-thread-heading {
        flex-direction: column;
      }

      #chat-assistant-status {
        text-align: left;
      }

      .admin-chat-message {
        max-width: 90%;
      }

      .analytics-heading-main {
        flex-direction: column;
      }

      .chart-summary {
        width: 100%;
      }

      .chart-summary-item {
        flex: 1;
      }
    }

    @media (max-width: 400px) {
      .page-heading {
        flex-direction: column;
        gap: 12px;
      }

      .page-heading>.primary-button {
        align-self: flex-end;
      }

      .topbar-right,
      .top-search {
        min-width: 0;
      }

      .logout-button {
        white-space: nowrap;
      }

      .stat-icon {
        width: 32px;
        height: 32px;
      }

      .stat-value {
        font-size: 19px;
      }

      .section-purpose {
        flex-direction: column;
      }

      .section-purpose-metric {
        width: 100%;
        padding: 10px 0 0;
        border-top: 1px solid #f0e5db;
        border-left: 0;
        text-align: left;
      }

      .analytics-heading {
        flex-direction: column;
      }

      .donut-content {
        flex-direction: column;
      }

      .donut-legend {
        width: 100%;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }

      .page-heading,
      .stat-card,
      .table-panel,
      dialog[open] {
        animation: none;
      }
    }

    /* === Best Seller & Giờ Vàng Configuration UI === */
    .bestseller-section {
      background: #fff;
      border-radius: 18px;
      padding: 24px;
      box-shadow: var(--shadow);
      border: 1px solid #f1f5f9;
    }

    .bs-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 18px;
      margin-bottom: 22px;
      flex-wrap: wrap;
      gap: 14px;
    }

    .bs-header-title {
      font-size: 19px;
      font-weight: 800;
      color: #0f172a;
      margin: 0 0 4px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .bs-header-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, #fb923c, #ea580c);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);
      flex-shrink: 0;
    }

    .bs-header-desc {
      font-size: 13px;
      color: #64748b;
      margin: 0;
    }

    .bs-top-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
      gap: 20px;
      margin-bottom: 26px;
      align-items: stretch;
    }

    .bs-panel-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 20px;
      display: flex;
      flex-direction: column;
    }

    .bs-panel-title {
      font-size: 15px;
      font-weight: 800;
      margin: 0 0 16px;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .bs-step-num {
      width: 24px;
      height: 24px;
      border-radius: 7px;
      background: #ffedd5;
      color: #c2410c;
      font-size: 12px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .bs-field {
      margin-bottom: 14px;
    }

    .bs-field:last-child {
      margin-bottom: 0;
    }

    .bs-label {
      display: block;
      font-size: 12.5px;
      font-weight: 700;
      color: #334155;
      margin-bottom: 6px;
    }

    .bs-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font-size: 13.5px;
      color: #0f172a;
      background: #fff;
      transition: border-color .15s, box-shadow .15s;
    }

    .bs-input:focus {
      outline: none;
      border-color: var(--orange);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.14);
    }

    .bs-toggle-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 11px 14px;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      cursor: pointer;
      user-select: none;
    }

    .bs-toggle-label {
      font-size: 13.5px;
      font-weight: 700;
      color: #0f172a;
    }

    .bs-toggle-sub {
      display: block;
      font-size: 11.5px;
      color: #64748b;
      font-weight: 500;
      margin-top: 2px;
    }

    .bs-switch {
      position: relative;
      display: inline-flex;
      width: 44px;
      height: 24px;
      flex-shrink: 0;
    }

    .bs-switch input {
      opacity: 0;
      width: 0;
      height: 0;
      position: absolute;
    }

    .bs-switch-slider {
      position: absolute;
      inset: 0;
      background: #cbd5e1;
      border-radius: 999px;
      transition: background .2s;
    }

    .bs-switch-slider::before {
      content: "";
      position: absolute;
      width: 18px;
      height: 18px;
      left: 3px;
      top: 3px;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
      transition: transform .2s;
    }

    .bs-switch input:checked + .bs-switch-slider {
      background: var(--orange-dark);
    }

    .bs-switch input:checked + .bs-switch-slider::before {
      transform: translateX(20px);
    }

    .bs-datetime-split {
      display: grid;
      grid-template-columns: 1.25fr 0.95fr;
      gap: 10px;
    }

    .bs-preset-row {
      display: flex;
      gap: 7px;
      margin-top: 10px;
      flex-wrap: wrap;
    }

    .bs-preset-btn,
    .bs-action-btn {
      padding: 6px 11px;
      border-radius: 8px;
      border: 1px solid #fed7aa;
      background: #fff7ed;
      color: #c2410c;
      font-size: 12px;
      font-weight: 700;
      transition: all .15s;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .bs-preset-btn:hover,
    .bs-action-btn:hover {
      background: #ffedd5;
      border-color: #fb923c;
    }

    .bs-preset-btn.is-active {
      background: #ea580c;
      border-color: #ea580c;
      color: #fff;
      box-shadow: 0 3px 10px rgba(234, 88, 12, 0.25);
    }

    .bs-action-btn.is-secondary {
      background: #fff;
      border-color: #cbd5e1;
      color: #475569;
    }

    .bs-action-btn.is-secondary:hover {
      background: #f1f5f9;
      border-color: #94a3b8;
    }

    /* Live Preview: Homepage Orange Board Replica */
    .bs-preview-wrap {
      background: #fff7ed;
      border: 1.5px dashed #fdba74;
      border-radius: 14px;
      padding: 20px;
      display: flex;
      flex-direction: column;
    }

    .bs-preview-board {
      background: radial-gradient(circle at 10% 18%, rgba(255, 255, 255, 0.16) 0%, transparent 40%),
        radial-gradient(circle at 90% 82%, rgba(255, 255, 255, 0.16) 0%, transparent 45%),
        linear-gradient(135deg, #f97316 0%, #ea580c 52%, #c2410c 100%);
      border-radius: 16px;
      padding: 18px;
      color: #fff;
      box-shadow: 0 12px 28px rgba(234, 88, 12, 0.25);
      display: flex;
      flex-direction: column;
      gap: 14px;
      flex: 1;
    }

    .bs-preview-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 10px;
    }

    .bs-preview-title-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 4px;
    }

    .bs-preview-title {
      font-size: 16px;
      font-weight: 900;
      color: #fff;
      line-height: 1.3;
    }

    .bs-preview-badge {
      background: rgba(255, 255, 255, 0.24);
      border: 1px solid rgba(255, 255, 255, 0.45);
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      padding: 3px 9px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      white-space: nowrap;
    }

    .bs-preview-subtitle {
      color: rgba(255, 255, 255, 0.92);
      font-size: 12px;
      display: block;
      line-height: 1.45;
    }

    .bs-preview-timer {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(0, 0, 0, 0.24);
      border: 1px solid rgba(255, 255, 255, 0.28);
      padding: 6px 10px;
      border-radius: 10px;
      font-size: 11.5px;
      font-weight: 700;
      color: #fff;
    }

    .bs-preview-digit {
      background: #fff;
      color: #ea580c;
      font-weight: 900;
      padding: 2px 6px;
      border-radius: 5px;
      min-width: 24px;
      text-align: center;
      font-variant-numeric: tabular-nums;
    }

    .bs-preview-products {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }

    .bs-preview-mini-card {
      background: #fff;
      color: #0f172a;
      border-radius: 10px;
      padding: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
      min-width: 0;
    }

    .bs-preview-mini-card img {
      width: 38px;
      height: 38px;
      border-radius: 7px;
      object-fit: cover;
      flex-shrink: 0;
    }

    .bs-preview-mini-info {
      min-width: 0;
      flex: 1;
    }

    .bs-preview-mini-name {
      display: block;
      font-size: 11.5px;
      font-weight: 700;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .bs-preview-mini-price {
      font-size: 11px;
      font-weight: 800;
      color: #ea580c;
    }

    .bs-preview-footer {
      margin-top: auto;
      padding-top: 10px;
      border-top: 1px solid rgba(255, 255, 255, 0.22);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.94);
    }

    .bs-preview-count-pill {
      background: #fff;
      color: #ea580c;
      font-weight: 800;
      padding: 2px 9px;
      border-radius: 999px;
      font-size: 12px;
    }

    /* Section 3: Product Picker */
    .bs-picker-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .bs-picker-controls {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .bs-search-box {
      position: relative;
      display: flex;
      align-items: center;
    }

    .bs-search-box input {
      padding: 8px 12px 8px 32px;
      border: 1px solid #cbd5e1;
      border-radius: 9px;
      font-size: 13px;
      width: 220px;
      background: #fff;
    }

    .bs-search-box input:focus {
      outline: none;
      border-color: var(--orange);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.14);
    }

    .bs-search-icon {
      position: absolute;
      left: 10px;
      color: #94a3b8;
      font-size: 13px;
      pointer-events: none;
    }

    .bs-filter-tabs {
      display: inline-flex;
      background: #f1f5f9;
      padding: 3px;
      border-radius: 9px;
      gap: 2px;
    }

    .bs-filter-tab {
      border: 0;
      background: transparent;
      color: #64748b;
      font-size: 12px;
      font-weight: 700;
      padding: 5px 10px;
      border-radius: 7px;
      transition: all .15s;
    }

    .bs-filter-tab.is-active {
      background: #fff;
      color: #ea580c;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
    }

    .bs-product-scroll {
      max-height: 440px;
      overflow-y: auto;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 14px;
      background: #f8fafc;
    }

    .bs-product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(265px, 1fr));
      gap: 11px;
    }

    .bs-prod-card {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 10px 12px;
      border: 1.5px solid #e2e8f0;
      background: #fff;
      border-radius: 12px;
      cursor: pointer;
      transition: transform .15s, border-color .15s, background .15s, box-shadow .15s;
    }

    .bs-prod-card:hover {
      border-color: #fdba74;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }

    .bs-prod-card.is-selected {
      border-color: #ea580c;
      background: #fff7ed;
      box-shadow: 0 4px 14px rgba(234, 88, 12, 0.12);
    }

    .bs-prod-checkbox {
      width: 18px;
      height: 18px;
      accent-color: #ea580c;
      flex-shrink: 0;
      cursor: pointer;
    }

    .bs-prod-thumb {
      width: 50px;
      height: 50px;
      border-radius: 9px;
      object-fit: cover;
      flex-shrink: 0;
      border: 1px solid #e2e8f0;
      background: #f1f5f9;
    }

    .bs-prod-info {
      min-width: 0;
      flex: 1;
    }

    .bs-prod-name {
      display: block;
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 2px;
    }

    .bs-prod-price {
      font-size: 12.5px;
      font-weight: 800;
      color: #ea580c;
    }

    @media (max-width: 1024px) {
      .bs-top-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 600px) {
      .bestseller-section {
        padding: 16px;
      }

      .bs-datetime-split,
      .bs-preview-products {
        grid-template-columns: 1fr;
      }

      .bs-search-box,
      .bs-search-box input {
        width: 100%;
      }
    }

    /* === Milestone 4: Membership & Dynamic Loyalty Styles === */
    .membership-section,
    .vouchers-section {
      background: #fff;
      border-radius: 18px;
      padding: 24px;
      box-shadow: var(--shadow);
      border: 1px solid #f1f5f9;
    }

    /* Voucher Admin Bento KPI Cards */
    .vc-kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: 14px;
      margin: 18px 0 22px;
    }
    .vc-kpi-card {
      background: #ffffff;
      border: 1px solid #f1f5f9;
      border-radius: 16px;
      padding: 16px 18px;
      display: flex;
      align-items: center;
      gap: 14px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.03);
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
    }
    .vc-kpi-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 24px rgba(234, 88, 12, 0.09);
      border-color: #fed7aa;
    }
    .vc-kpi-card::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: #ea580c;
    }
    .vc-kpi-card.kpi-active::after { background: #16a34a; }
    .vc-kpi-card.kpi-usage::after { background: #0284c7; }
    .vc-kpi-card.kpi-disc::after { background: #ea580c; }
    .vc-kpi-card.kpi-bday::after { background: #d97706; }
    .vc-kpi-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
      background: #fff7ed;
    }
    .vc-kpi-card.kpi-active .vc-kpi-icon { background: #dcfce7; }
    .vc-kpi-card.kpi-usage .vc-kpi-icon { background: #e0f2fe; }
    .vc-kpi-card.kpi-disc .vc-kpi-icon { background: #fff7ed; }
    .vc-kpi-card.kpi-bday .vc-kpi-icon { background: #fef3c7; }
    .vc-kpi-content {
      flex: 1;
      min-width: 0;
    }
    .vc-kpi-label {
      font-size: 11.5px;
      font-weight: 700;
      color: #64748b;
      margin-bottom: 2px;
    }
    .vc-kpi-val {
      font-size: 1.35rem;
      font-weight: 900;
      color: #1e293b;
      line-height: 1.2;
    }
    .vc-kpi-sub {
      font-size: 11px;
      color: #94a3b8;
      margin-top: 2px;
    }

    /* Modal Visual Type Selector */
    .vc-type-selector {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-bottom: 8px;
    }
    .vc-type-card {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 10px 8px;
      text-align: center;
      cursor: pointer;
      background: #ffffff;
      transition: all 0.18s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }
    .vc-type-card:hover {
      border-color: #fb923c;
      background: #fff7ed;
    }
    .vc-type-card.active {
      border-color: #ea580c;
      background: #fff7ed;
      box-shadow: 0 4px 12px rgba(234, 88, 12, 0.14);
    }
    .vc-type-card .vc-type-icon { font-size: 20px; line-height: 1; }
    .vc-type-card .vc-type-title { font-size: 12px; font-weight: 800; color: #1e293b; }
    .vc-type-card.active .vc-type-title { color: #ea580c; }
    .vc-type-card .vc-type-desc { font-size: 10px; color: #64748b; }

    /* Modal Live Ticket Preview */
    .vc-live-preview-box {
      background: #f8fafc;
      border: 1.5px dashed #fed7aa;
      border-radius: 16px;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .vc-live-preview-head {
      font-size: 11px;
      font-weight: 800;
      color: #9a3412;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .vc-preview-ticket {
      display: flex;
      background: #ffffff;
      border: 1.5px solid #fed7aa;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }
    .vc-prev-stub {
      width: 95px;
      flex-shrink: 0;
      background: linear-gradient(135deg, #f97316, #ea580c);
      color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px 8px;
      text-align: center;
      border-right: 2px dashed #fed7aa;
    }
    .vc-prev-body {
      flex: 1;
      padding: 10px 14px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-width: 0;
    }

    .vc-copy-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-weight: 850;
      color: #ea580c;
      background: #fff7ed;
      border: 1px solid #fed7aa;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 12px;
      letter-spacing: 0.5px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .vc-copy-pill:hover {
      background: #ffedd5;
      border-color: #f97316;
      transform: translateY(-1px);
    }
    .vc-copy-pill:active {
      transform: scale(0.97);
    }
    .status-pulse-dot {
      display: inline-block;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      margin-right: 5px;
      vertical-align: middle;
    }
    .status-pulse-dot.dot-active {
      background: #16a34a;
      box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.25);
      animation: vcPulse 2s infinite;
    }
    .status-pulse-dot.dot-locked {
      background: #ef4444;
    }
    @keyframes vcPulse {
      0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4); }
      70% { box-shadow: 0 0 0 5px rgba(22, 163, 74, 0); }
      100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
    }

    /* =========================================================================
       MILSTONE VOUCHER TOOLBAR (MODERN ORANGE - WHITE SAAS SUITE)
       ========================================================================= */
    .vc-toolbar-card {
      background: #ffffff;
      border: 1px solid #f1f5f9;
      border-radius: 16px;
      padding: 14px 18px;
      margin-bottom: 20px;
      box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .vc-toolbar-main {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .vc-toolbar-filters {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      flex: 1;
      min-width: 300px;
    }
    .vc-toolbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    /* Custom Search Box */
    .vc-search-box-wrap {
      position: relative;
      display: flex;
      align-items: center;
      min-width: 220px;
      flex: 1;
      max-width: 280px;
    }
    .vc-search-icon {
      position: absolute;
      left: 12px;
      color: #94a3b8;
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .vc-search-input {
      width: 100%;
      height: 40px;
      padding: 0 32px 0 36px;
      background: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      color: #1e293b;
      outline: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .vc-search-input::placeholder {
      color: #94a3b8;
      font-weight: 450;
    }
    .vc-search-input:focus {
      background: #ffffff;
      border-color: #f97316;
      box-shadow: 0 0 0 3.5px rgba(249, 115, 22, 0.12);
    }
    .vc-search-clear {
      position: absolute;
      right: 8px;
      background: #e2e8f0;
      border: none;
      cursor: pointer;
      color: #64748b;
      width: 18px;
      height: 18px;
      font-size: 10px;
      font-weight: 900;
      display: none;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.15s ease;
    }
    .vc-search-clear:hover {
      color: #ffffff;
      background: #ef4444;
    }

    /* Custom Select Dropdowns */
    .vc-select-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
    }
    .vc-select-icon {
      position: absolute;
      left: 11px;
      pointer-events: none;
      font-size: 13px;
      display: flex;
      align-items: center;
      z-index: 1;
    }
    .vc-select-chevron {
      position: absolute;
      right: 11px;
      color: #64748b;
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1;
    }
    .vc-custom-select {
      height: 40px;
      padding: 0 32px 0 32px;
      background: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 12.5px;
      font-weight: 600;
      color: #334155;
      outline: none;
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      white-space: nowrap;
    }
    .vc-custom-select:hover {
      background: #ffffff;
      border-color: #cbd5e1;
    }
    .vc-custom-select:focus {
      background: #ffffff;
      border-color: #f97316;
      box-shadow: 0 0 0 3.5px rgba(249, 115, 22, 0.12);
    }
    .vc-custom-select.is-active-filter {
      border-color: #f97316;
      background: #fff7ed;
      color: #ea580c;
      font-weight: 700;
    }
    .vc-custom-select option {
      font-weight: 500;
      color: #1e293b;
      background: #ffffff;
      padding: 6px 10px;
    }

    /* Reset Filters Pill Button */
    .vc-btn-reset-filters {
      height: 40px;
      padding: 0 12px;
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      color: #64748b;
      font-size: 12px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
      transition: all 0.18s ease;
      white-space: nowrap;
    }
    .vc-btn-reset-filters:hover {
      background: #e2e8f0;
      color: #0f172a;
    }

    /* Action Buttons */
    .vc-btn-bday {
      height: 40px;
      padding: 0 16px;
      background: #fff7ed;
      border: 1.5px solid #fed7aa;
      border-radius: 10px;
      color: #c2410c;
      font-size: 12.5px;
      font-weight: 750;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      cursor: pointer;
      box-shadow: 0 2px 5px rgba(234, 88, 12, 0.04);
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      white-space: nowrap;
    }
    .vc-btn-bday:hover {
      background: #ffedd5;
      border-color: #f97316;
      color: #9a3412;
      transform: translateY(-1.5px);
      box-shadow: 0 6px 14px rgba(234, 88, 12, 0.12);
    }
    .vc-btn-bday:active {
      transform: scale(0.98);
    }
    .vc-btn-bday:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .vc-btn-create {
      height: 40px;
      padding: 0 18px;
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      border: none;
      border-radius: 10px;
      color: #ffffff;
      font-size: 13px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(234, 88, 12, 0.28);
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      white-space: nowrap;
    }
    .vc-btn-create:hover {
      background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(234, 88, 12, 0.38);
    }
    .vc-btn-create:active {
      transform: scale(0.98);
      box-shadow: 0 2px 6px rgba(234, 88, 12, 0.25);
    }
    .vc-btn-create .vc-plus-badge {
      width: 18px;
      height: 18px;
      background: rgba(255, 255, 255, 0.25);
      border-radius: 5px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 900;
      line-height: 1;
    }

    .mem-header-stats {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .mem-stat-pill {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      padding: 6px 14px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
    }

    .mem-stat-label { color: #64748b; font-weight: 600; }
    .mem-stat-val { color: #ea580c; font-weight: 800; }

    .membership-tab-bar {
      display: flex;
      gap: 8px;
      border-bottom: 2px solid #f1f5f9;
      margin: 18px 0 20px;
      padding-bottom: 2px;
    }

    .membership-tab-btn {
      border: 0;
      background: transparent;
      padding: 10px 18px;
      font-size: 13px;
      font-weight: 750;
      color: #64748b;
      cursor: pointer;
      border-radius: 8px 8px 0 0;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all .18s ease;
      position: relative;
    }

    .membership-tab-btn:hover {
      color: #ea580c;
      background: #fff7ed;
    }

    .membership-tab-btn.active {
      color: #ea580c;
      font-weight: 800;
      background: #fff7ed;
      border-bottom: 2px solid #ea580c;
      margin-bottom: -2px;
    }

    .mem-controls-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
    }

    .mem-search-box {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0 12px;
      height: 38px;
      width: min(340px, 100%);
    }

    .mem-search-box input {
      border: 0;
      outline: 0;
      width: 100%;
      font-size: 12px;
      color: var(--ink);
    }

    .mem-filter-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Tier Badges */
    .tier-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 9px;
      border-radius: 20px;
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .25px;
    }

    .tier-badge-bronze { background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; }
    .tier-badge-silver { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; }
    .tier-badge-gold { background: #fef9c3; border: 1px solid #fde047; color: #a16207; }
    .tier-badge-diamond { background: #ecfeff; border: 1px solid #a5f3fc; color: #0e7490; }

    .mem-cell-user {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .mem-avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, #fb923c, #ea580c);
      color: #fff;
      display: grid;
      place-items: center;
      font-size: 12px;
      font-weight: 800;
      flex-shrink: 0;
    }

    .mem-details strong { display: block; font-size: 12px; color: #1e293b; }
    .mem-details small { color: #64748b; font-size: 10px; }

    /* Points Modal Dialog */
    .points-modal-dialog {
      width: min(780px, calc(100vw - 32px));
      max-height: min(90vh, 760px);
      padding: 0;
      border-radius: 16px;
      border: 0;
      box-shadow: 0 25px 80px rgba(0,0,0,.22);
    }

    .points-modal-body {
      padding: 16px 20px;
    }

    .mem-summary-strip {
      display: flex;
      align-items: center;
      gap: 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px 16px;
      flex-wrap: wrap;
    }

    .mem-avatar-box {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, #fb923c, #ea580c);
      color: #fff;
      display: grid;
      place-items: center;
      font-size: 15px;
      font-weight: 800;
    }

    .mem-info-box strong { display: block; font-size: 13.5px; color: #0f172a; }
    .mem-info-box span { font-size: 11px; color: #64748b; }

    .mem-stat-pill-sm {
      background: #fff;
      border: 1px solid #e2e8f0;
      padding: 4px 10px;
      border-radius: 8px;
      font-size: 11px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .pts-positive { color: #16a34a; font-weight: 800; font-size: 12px; }
    .pts-negative { color: #dc2626; font-weight: 800; font-size: 12px; }

    .tx-type-tag {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 6px;
      font-size: 9.5px;
      font-weight: 750;
    }
    .tx-type-earn { background: #dcfce7; color: #15803d; }
    .tx-type-redeem { background: #ffedd5; color: #c2410c; }
    .tx-type-voucher { background: #f3e8ff; color: #7e22ce; }
    .tx-type-restore { background: #e0f2fe; color: #0369a1; }
    .tx-type-revoke { background: #fee2e2; color: #b91c1c; }
    .tx-type-adjust { background: #f1f5f9; color: #475569; }

    .loyalty-alert-banner {
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 12.5px;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .loyalty-alert-banner.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .loyalty-alert-banner.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .loyalty-role-notice {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 16px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      color: #1e40af;
      font-size: 12px;
    }
    .loyalty-role-notice strong { display: block; font-size: 13px; margin-bottom: 2px; }
    .loyalty-role-notice p { margin: 0; color: #3b82f6; }

    /* Member Edit / Tier Promotion Modal & Action Button */
    .action-button.edit-tier {
      background: #fff7ed;
      color: #c2410c;
      border: 1px solid #fed7aa;
    }
    .action-button.edit-tier:hover {
      background: #ea580c;
      color: #ffffff;
      border-color: #ea580c;
    }
    .tier-selector-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-top: 8px;
    }
    .tier-select-card {
      position: relative;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px 10px;
      background: #ffffff;
      cursor: pointer;
      text-align: center;
      transition: all .18s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }
    .tier-select-card:hover {
      border-color: #fdba74;
      background: #fffaf5;
      transform: translateY(-1px);
    }
    .tier-select-card.is-selected {
      border-color: #ea580c;
      background: #fff7ed;
      box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
    }
    .tier-select-card.tier-bronze.is-selected { border-color: #cd7f32; background: #fff7ed; }
    .tier-select-card.tier-silver.is-selected { border-color: #64748b; background: #f8fafc; box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.14); }
    .tier-select-card.tier-gold.is-selected { border-color: #eab308; background: #fefce8; box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.18); }
    .tier-select-card.tier-diamond.is-selected { border-color: #06b6d4; background: #ecfeff; box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.16); }
    .tier-select-icon { font-size: 22px; line-height: 1; }
    .tier-select-name { font-size: 12.5px; font-weight: 800; color: #0f172a; }
    .tier-select-meta { font-size: 10px; color: #64748b; font-weight: 600; }
    .quick-pts-row {
      display: flex;
      gap: 6px;
      margin-top: 6px;
      flex-wrap: wrap;
    }
    .quick-pts-btn {
      background: #fff7ed;
      color: #c2410c;
      border: 1px solid #fed7aa;
      border-radius: 6px;
      padding: 3px 9px;
      font-size: 11px;
      font-weight: 750;
      cursor: pointer;
      transition: all .15s ease;
    }
    .quick-pts-btn:hover {
      background: #ea580c;
      color: #fff;
      border-color: #ea580c;
    }
    @media (max-width: 600px) {
      .tier-selector-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
  </style>
</head>

<body>
  <div class="admin-layout">
    <aside class="sidebar" id="sidebar" aria-label="Điều hướng quản trị">
      <div class="sidebar-header-row">
        <a class="brand" href="<?= getUrl('index.php') ?>" aria-label="FASTFOOD Admin, về trang chủ">
          <span class="brand-mark" aria-hidden="true">F</span>
          <span class="brand-name">FASTFOOD<small>ADMIN CONSOLE</small></span>
        </a>
        <button type="button" class="sidebar-close-btn" id="sidebar-close-btn" aria-label="Đóng menu" title="Đóng menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <p class="nav-label">Không gian làm việc</p>
      <nav class="nav-list" id="navigation" aria-label="Điều hướng chính">
        <button class="nav-item active" type="button" data-section="dashboard" aria-current="page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="3" width="8" height="8" rx="2" />
            <rect x="13" y="3" width="8" height="5" rx="2" />
            <rect x="13" y="10" width="8" height="11" rx="2" />
            <rect x="3" y="13" width="8" height="8" rx="2" />
          </svg>Dashboard
        </button>
        <button class="nav-item" type="button" data-section="categories">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path
              d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5z" />
            <path d="M8 8h8M8 12h8M8 16h4" />
          </svg>Danh mục
        </button>
        <button class="nav-item" type="button" data-section="products">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 8.5 12 4l8 4.5v8L12 21l-8-4.5z" />
            <path d="m4.5 8.7 7.5 4.2 7.5-4.2M12 13v8" />
          </svg>Sản phẩm
        </button>
        <button class="nav-item" type="button" data-section="restaurants" style="display:none !important;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6M8 10h.01M12 10h.01M16 10h.01" />
          </svg>Quán ăn
        </button>
        <button class="nav-item" type="button" data-section="orders">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M6 3h12l2 4v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7z" />
            <path d="M4 7h16M9 11h6M9 15h6" />
          </svg><span>Đơn hàng</span>
          <span class="nav-badge nav-badge-orders" id="orders-nav-badge" hidden>0</span>
        </button>
        <button class="nav-item" type="button" data-section="users">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="9" cy="8" r="3.5" />
            <path
              d="M3 20v-1.5A4.5 4.5 0 0 1 7.5 14h3a4.5 4.5 0 0 1 4.5 4.5V20M16 5a3.5 3.5 0 0 1 0 6.8M18 14a4.5 4.5 0 0 1 3 4.2V20" />
          </svg>Người dùng
        </button>
        <button class="nav-item" type="button" data-section="membership" id="nav-btn-membership">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="8" r="6" />
            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" />
          </svg><span>Hạng TV &amp; Điểm thưởng</span>
          <span class="nav-badge" id="membership-nav-badge" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-weight: 800; font-size: 10px; padding: 2px 6px;">LOYALTY</span>
        </button>
        <button class="nav-item" type="button" data-section="vouchers" id="nav-btn-vouchers">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
            <line x1="7" y1="7" x2="7.01" y2="7"></line>
          </svg><span>Quản lý Voucher</span>
          <span class="nav-badge" id="vouchers-nav-badge" style="background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; font-weight: 800; font-size: 10px; padding: 2px 6px;">VOUCHER</span>
        </button>
        <button class="nav-item" type="button" data-section="messages">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 5h16v12H9l-5 4z" />
            <path d="M8 9h8M8 13h5" />
          </svg><span>Tin nhắn khách hàng</span>
          <span class="nav-badge nav-badge-messages" id="messages-nav-badge" <?= $unreadMessagesCount > 0 ? '' : 'hidden' ?>><?= $unreadMessagesCount > 99 ? '99+' : $unreadMessagesCount ?></span>
        </button>
        <button class="nav-item" type="button" data-section="ai-bot">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path
              d="M12 2a2 2 0 0 1 2 2v2a8 8 0 0 1 8 8v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a8 8 0 0 1 8-8V4a2 2 0 0 1 2-2z" />
            <circle cx="9" cy="13" r="1.5" />
            <circle cx="15" cy="13" r="1.5" />
            <path d="M10 17h4" />
          </svg><span>Trợ lý AI Chatbot</span>
          <span class="nav-badge nav-badge-ai">AI</span>
        </button>
        <?php
          $totalReviewsCount = (int)($db->query("SELECT COUNT(*) FROM reviews")->fetchColumn() ?: 0);
        ?>
        <button class="nav-item" type="button" data-section="reviews">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" />
          </svg><span>Đánh giá khách hàng</span>
          <span class="nav-badge" id="reviews-nav-badge" <?= $totalReviewsCount > 0 ? '' : 'hidden' ?>><?= $totalReviewsCount > 99 ? '99+' : $totalReviewsCount ?></span>
        </button>
        <button class="nav-item" type="button" data-section="bestseller">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
          </svg><span>Best Seller & Giờ Vàng</span>
          <span class="nav-badge" style="background:#ea580c; color:#fff; font-weight:800; font-size:10px; padding:2px 6px;">HOT</span>
        </button>
        <button class="nav-item" type="button" data-section="banners">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
            <path d="m5 13 4-4 3 3 4-5 3 4"></path>
          </svg><span>Quản Lý Banner</span>
          <span class="nav-badge" style="background:#2563eb; color:#fff; font-weight:800; font-size:10px; padding:2px 6px;">NEW</span>
        </button>
        <button class="nav-item" type="button" data-section="footer-settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="15" x2="21" y2="15"></line>
            <path d="M7 19h10"></path>
          </svg><span>Cài Đặt Chân Trang</span>
        </button>
      </nav>
      <div class="sidebar-bottom">
        <a class="sidebar-link" href="<?= getUrl('index.php') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M14 5h5v14h-5M10 8l-4 4 4 4M6 12h12" />
          </svg>Về trang chủ
        </a>
      </div>
    </aside>
    <div class="sidebar-resizer" id="sidebar-resizer" role="separator" aria-orientation="vertical" title="Kéo ngang để thay đổi độ rộng menu (Nhấp đúp chuột để đặt lại mặc định)"></div>
    <div class="mobile-backdrop" id="mobile-backdrop" aria-hidden="true"></div>

    <main class="main">
      <header class="topbar">
        <div class="topbar-left">
          <button class="mobile-menu" type="button" id="mobile-menu" aria-label="Mở menu" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div class="crumb">Quản trị <span aria-hidden="true">/</span> <strong id="breadcrumb">Dashboard</strong></div>
        </div>
        <div class="topbar-right">
          <form class="top-search" id="search-form" role="search">
            <input id="search-input" type="search" placeholder="Tìm kiếm dữ liệu..." aria-label="Tìm kiếm dữ liệu">
            <button class="search-button" type="submit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.8" />
                <path d="m16 16 4.5 4.5" />
              </svg>Tìm kiếm
            </button>
          </form>
          <!-- Notification Bell & Dropdown -->
          <div class="notif-wrapper" id="notif-wrapper">
            <button class="notif-bell-btn" id="notif-bell-btn" type="button" aria-label="Thông báo đơn hàng"
              aria-expanded="false" title="Thông báo hệ thống">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <span class="notif-bell-badge" id="notif-bell-badge" hidden>0</span>
            </button>
            <div class="notif-dropdown" id="notif-dropdown" hidden>
              <div class="notif-dropdown-header">
                <div>
                  <h3 class="notif-dropdown-title">Thông báo</h3>
                  <span class="notif-unread-tag" id="notif-unread-tag">0 chưa đọc</span>
                </div>
                <button class="notif-mark-all-btn" id="notif-mark-all-btn" type="button">Đánh dấu tất cả đã đọc</button>
              </div>
              <div class="notif-list" id="notif-list" role="feed" aria-busy="false">
                <div class="notif-empty" id="notif-empty">Chưa có thông báo nào</div>
              </div>
            </div>
          </div>

          <span class="profile-divider" aria-hidden="true"></span>
          <div class="admin-profile" id="admin-profile-clickable" style="cursor: pointer; transition: opacity 0.2s;" title="Nhấn vào ảnh để đăng xuất">
            <span class="avatar" aria-hidden="true">AD</span>
            <span class="profile-copy"><strong>Quản trị viên</strong><span>Quản trị hệ thống</span></span>
          </div>
          <button class="logout-button" id="logout-button" type="button" style="display: none;">Đăng xuất</button>
        </div>
      </header>

      <div class="content">
        <section class="page-heading" aria-labelledby="page-title">
          <div>
            <p class="eyebrow">Xin chào, Quản trị viên</p>
            <h1 id="page-title">Tổng quan hệ thống</h1>
            <p class="page-description" id="page-description">Theo dõi hoạt động kinh doanh và quản lý dữ liệu của bạn.
            </p>
            <div class="date-label" id="today-label"></div>
          </div>
          <button class="primary-button" id="add-button" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M12 5v14M5 12h14" />
            </svg><span>Thêm mới</span>
          </button>
        </section>

        <section class="stats-grid" id="dashboard-stats" aria-label="Chỉ số tổng quan">
          <article class="stat-card">
            <div class="stat-top"><span class="stat-label">Tổng đơn hàng</span><span class="stat-icon"><svg
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                  <path d="M6 3h12l2 4v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7z" />
                  <path d="M4 7h16M9 11h6" />
                </svg></span></div>
            <strong class="stat-value" id="stat-orders">0</strong><span class="stat-note"><span class="positive">↗
                12%</span> so với tháng trước</span>
          </article>
          <article class="stat-card">
            <div class="stat-top"><span class="stat-label">Doanh thu</span><span class="stat-icon"><svg
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                  <path d="M12 2v20M17 6.5c0-1.7-2-3-5-3S7 4.8 7 6.5 9 9 12 9s5 1.3 5 3-2 3.5-5 3.5-5-1.3-5-3" />
                </svg></span></div>
            <strong class="stat-value" id="stat-revenue">0 đ</strong><span class="stat-note"><span class="positive">↗
                8.4%</span> so với tháng trước</span>
          </article>
          <article class="stat-card">
            <div class="stat-top"><span class="stat-label">Sản phẩm</span><span class="stat-icon"><svg
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                  <path d="M4 8.5 12 4l8 4.5v8L12 21l-8-4.5z" />
                  <path d="m4.5 8.7 7.5 4.2 7.5-4.2M12 13v8" />
                </svg></span></div>
            <strong class="stat-value" id="stat-products">0</strong><span class="stat-note">Đang được quản lý trên hệ
              thống</span>
          </article>
          <article class="stat-card">
            <div class="stat-top"><span class="stat-label">Người dùng</span><span class="stat-icon"><svg
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                  <circle cx="9" cy="8" r="3.5" />
                  <path d="M3 20v-1.5A4.5 4.5 0 0 1 7.5 14h3a4.5 4.5 0 0 1 4.5 4.5V20M16 5a3.5 3.5 0 0 1 0 6.8" />
                </svg></span></div>
            <strong class="stat-value" id="stat-users">0</strong><span class="stat-note">Tài khoản trên FASTFOOD</span>
          </article>
        </section>

        <section class="section-purpose" id="section-purpose" aria-labelledby="section-purpose-title">
          <div class="section-purpose-copy">
            <span class="section-purpose-label" id="section-purpose-title">Mục tiêu khu vực</span>
            <p class="section-purpose-text" id="section-purpose-text"></p>
          </div>
          <div class="section-purpose-metric">
            <strong id="section-purpose-value">—</strong>
            <span id="section-purpose-caption"></span>
          </div>
        </section>

        <section class="analytics-grid" id="sales-analytics" aria-label="Phân tích bán hàng 7 ngày gần nhất">
          <article class="analytics-card">
            <div class="analytics-heading">
              <div class="analytics-heading-main">
                <div>
                  <h2 class="analytics-title" id="revenue-chart-heading">Doanh thu 7 ngày gần nhất</h2>
                  <p class="analytics-caption" id="revenue-chart-caption">Tổng giá trị đơn hàng chưa bị hủy theo từng ngày</p>
                </div>
                <div class="chart-summary">
                  <div class="chart-summary-item"><span>Tổng doanh thu</span><strong id="chart-total-revenue">0
                      đ</strong></div>
                  <div class="chart-summary-item"><span>Tổng đơn hàng</span><strong id="chart-total-orders">0</strong>
                  </div>
                </div>
              </div>
              <div class="analytics-heading-controls">
                <select id="revenue-time-select" class="revenue-time-select" aria-label="Chọn khoảng thời gian doanh thu">
                  <option value="1">1 ngày</option>
                  <option value="7" selected>7 ngày</option>
                  <option value="30">1 tháng</option>
                </select>
                <span class="realtime-badge" id="realtime-status" role="status">Đang kết nối</span>
              </div>
            </div>
            <svg class="revenue-chart" id="revenue-chart" viewBox="0 0 760 270" role="img"
              aria-labelledby="revenue-chart-title revenue-chart-description"></svg>
            <div class="chart-empty" id="revenue-empty" hidden>Chưa có đơn hàng trong 7 ngày gần nhất.</div>
            <p class="chart-live-note" id="chart-live-note">Biểu đồ tự cập nhật khi khách đặt hàng thành công hoặc trạng
              thái đơn thay đổi.</p>
          </article>
          <article class="analytics-card">
            <div class="analytics-heading">
              <div>
                <h2 class="analytics-title">Tỉ trọng doanh thu món ăn</h2>
                <p class="analytics-caption" id="donut-chart-caption">Phân bổ theo sản phẩm trên các đơn hàng 7 ngày</p>
              </div>
            </div>
            <div class="donut-content">
              <div class="donut-chart" id="sales-donut" role="img" aria-label="Tỉ trọng doanh thu sản phẩm">
                <div class="donut-center"><strong id="donut-total">0 đ</strong><span>doanh thu</span></div>
              </div>
              <ul class="donut-legend" id="donut-legend" aria-label="Doanh thu theo sản phẩm"></ul>
            </div>
            <div class="chart-empty" id="donut-empty" hidden>Chưa có đơn hàng có sản phẩm trong 7 ngày gần nhất.</div>
          </article>
        </section>

        <section class="table-panel" aria-labelledby="table-title">
          <div class="panel-heading">
            <div>
              <h2 class="panel-title" id="table-title">Sản phẩm gần đây</h2>
              <p class="panel-subtitle" id="table-subtitle">Danh sách sản phẩm được cập nhật mới nhất</p>
            </div>
            <div class="panel-filters">
              <label class="sr-only" for="status-filter">Lọc theo trạng thái</label>
              <select class="filter-select" id="status-filter">
                <option value="">Tất cả trạng thái</option>
              </select>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr id="table-head"></tr>
              </thead>
              <tbody id="table-body"></tbody>
            </table>
          </div>
          <div class="table-footer">
            <span class="result-count" id="result-count" aria-live="polite"></span>
            <nav class="pagination" id="pagination" aria-label="Phân trang"></nav>
          </div>
        </section>
        <section class="chat-inbox" id="chat-inbox" aria-label="Hộp thư tin nhắn khách hàng" hidden>
          <aside class="chat-inbox-panel" aria-label="Danh sách hội thoại khách hàng">
            <div class="chat-inbox-heading">
              <div class="chat-heading-top-row">
                <div class="chat-heading-brand">
                  <span class="chat-badge-icon">💬</span>
                  <div>
                    <h2>Hộp Thư Khách Hàng</h2>
                    <p>CSKH Trực Tuyến &amp; Trợ Lý AI</p>
                  </div>
                </div>
                <button type="button" class="chat-refresh-icon-btn" id="btn-refresh-conversations" title="Tải lại danh sách tin nhắn">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                </button>
                <button type="button" class="chat-refresh-icon-btn" id="btn-clear-all-conversations" title="Xóa toàn bộ lịch sử tin nhắn" style="color: #ef4444; margin-left: 4px;">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>
              </div>

              <!-- CHUYỂN ĐỔI CHẾ ĐỘ: AI TỰ CHAT 24/7 vs ADMIN TRỰC TIẾP CHAT -->
              <div class="chat-mode-control-card">
                <div class="chat-mode-card-header">
                  <span class="chat-mode-label">Chế độ hỗ trợ hiện tại:</span>
                  <span class="chat-mode-status-tag mode-auto" id="chat-mode-current-badge">🤖 AI Tự Chat (24/7)</span>
                </div>
                <div class="chat-mode-switch-group" role="tablist">
                  <button type="button" class="btn-mode-pill active" id="btn-mode-ai" data-mode="auto" title="AI tự động trả lời khách hàng 24/7">
                    <span class="mode-icon">🤖</span>
                    <span class="mode-text">AI Tự Chat</span>
                  </button>
                  <button type="button" class="btn-mode-pill" id="btn-mode-admin" data-mode="admin" title="Admin trực tiếp đọc và chat với khách hàng (Tắt AI tự động)">
                    <span class="mode-icon">👨‍💼</span>
                    <span class="mode-text">Admin Trực Tiếp</span>
                  </button>
                </div>
                <p class="chat-mode-hint" id="chat-mode-desc-text">
                  ✨ <strong>AI Tự Chat:</strong> Trợ lý AI đang tự động phản hồi khách hàng 24/7. Bạn có thể theo dõi và can thiệp khi cần.
                </p>
              </div>

              <!-- Tìm kiếm hội thoại -->
              <div class="chat-search-box">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="chat-conv-search-input" placeholder="Tìm tên khách, SĐT, tin nhắn..." autocomplete="off">
              </div>
            </div>
            <div class="chat-conversation-list" id="chat-conversation-list" aria-live="polite"></div>
          </aside>

          <section class="chat-inbox-panel chat-thread" aria-label="Nội dung cuộc trò chuyện">
            <header class="chat-thread-heading">
              <div class="chat-customer-profile">
                <div class="chat-customer-avatar" id="chat-customer-avatar">👤</div>
                <div class="chat-customer-info">
                  <div class="chat-customer-name-row">
                    <h2 id="chat-thread-title">Chọn một khách hàng</h2>
                    <span class="chat-customer-type-badge" id="chat-customer-type-badge">Khách hàng</span>
                  </div>
                  <p id="chat-customer-meta">Chọn một hội thoại ở danh sách bên trái để bắt đầu chat</p>
                </div>
              </div>
              <div class="chat-thread-actions">
                <span class="chat-assistant-status-pill auto" id="chat-assistant-status">Đang kết nối...</span>
                <button type="button" class="ai-action-btn secondary btn-sm" id="btn-toggle-ai-automation"
                  title="Bật hoặc tạm dừng AI tự động trả lời cho riêng khách hàng này">
                  🤖 Bật AI cho khách này
                </button>
                <button type="button" class="ai-action-btn btn-sm" id="btn-delete-conv"
                  title="Xóa cuộc trò chuyện này" style="display: none; background: #fee2e2; color: #b91c1c; border-color: #fca5a5; margin-left: 6px;">
                  🗑️ Xóa hội thoại
                </button>
              </div>
            </header>

            <div class="admin-chat-messages" id="admin-chat-messages" aria-live="polite"></div>

            <!-- AI Copilot Support Toolbar for Admin -->
            <div class="admin-chat-ai-bar" id="admin-chat-ai-bar">
              <div class="ai-bar-header">
                <div class="ai-bar-title">
                  <span class="ai-sparkle">✨</span>
                  <strong>AI Copilot Hỗ Trợ Soạn Tin</strong>
                  <span class="ai-intent-tag" id="ai-detected-intent" hidden></span>
                </div>
                <div class="ai-bar-controls">
                  <button type="button" class="ai-action-btn" id="btn-ai-open-picker"
                    title="Mở Kho Món Ăn FASTFOOD để chọn món và 1-click gửi thẻ vào cuộc trò chuyện">
                    🍔 Kho Món Ăn (1-Click Gửi)
                  </button>
                  <button type="button" class="ai-action-btn" id="btn-ai-suggest-reply"
                    title="AI phân tích câu hỏi gần nhất của khách và soạn sẵn câu trả lời đầy đủ">
                    ✨ Gợi ý câu trả lời AI
                  </button>
                </div>
              </div>
              <div class="ai-quick-chips" id="ai-quick-chips">
                <span class="quick-chip-label">Mẫu nhanh:</span>
                <button type="button" class="ai-chip"
                  data-text="Dạ chào bạn, FASTFOOD đã nhận được yêu cầu và đang xử lý ngay cho bạn đây ạ!">⚡ Chào &amp; Nhận đơn</button>
                <button type="button" class="ai-chip"
                  data-text="Đơn hàng của bạn đang được quán chuẩn bị nóng hổi và sắp bàn giao cho tài xế rồi bạn nhé!">🍳 Quán đang làm món</button>
                <button type="button" class="ai-chip"
                  data-text="Tài xế FASTFOOD đang trên đường giao đến bạn, bạn chú ý điện thoại trong 5-10 phút tới nhé!">🛵 Đang giao hàng</button>
                <button type="button" class="ai-chip"
                  data-text="Dạ quán hoạt động từ 06:00 đến 23:30 tại 16 Hoàng Diệu, Buôn Ma Thuột. Bạn cần đặt món nào ạ?">🏠 Giờ mở cửa &amp; Địa chỉ</button>
                <button type="button" class="ai-chip"
                  data-text="FASTFOOD chân thành xin lỗi bạn vì sự bất tiện này. Quán xin gửi tặng bạn mã giảm giá FASTFOOD20 cho đơn hàng tiếp theo ạ!">🎁 Xin lỗi &amp; Tặng mã</button>
              </div>
            </div>

            <!-- Upload Preview Strip (Initially Hidden) -->
            <div class="admin-chat-upload-preview" id="admin-chat-upload-preview" style="display: none;">
              <div class="admin-preview-thumb-wrap">
                <img id="admin-preview-thumb" src="" alt="Xem trước ảnh" />
                <button type="button" class="admin-btn-remove-preview" id="admin-btn-remove-preview" title="Hủy ảnh">&times;</button>
              </div>
              <div class="admin-preview-meta">
                <span class="admin-preview-filename" id="admin-preview-filename"></span>
                <span class="admin-preview-filesize" id="admin-preview-filesize"></span>
              </div>
            </div>

            <form class="admin-chat-form" id="admin-chat-form">
              <div class="admin-chat-tools-cluster">
                <button type="button" class="btn-picker-trigger" id="btn-open-product-picker" title="Mở Kho Món Ăn FASTFOOD tra cứu & 1-click gửi thẻ">
                  <span>🍔 Kho Món Ăn</span>
                </button>
                <input type="file" id="admin-chat-file-input" accept="image/jpeg,image/png,image/webp" style="display: none;">
                <button type="button" class="btn-attach-trigger" id="admin-chat-attach-btn" title="Gửi hình ảnh (JPG, PNG, WEBP tối đa 5MB)">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                  </svg>
                </button>
              </div>
              <div class="admin-chat-input-wrapper">
                <input type="text" name="message" id="admin-chat-input-field" maxlength="1000" placeholder="Nhập tin nhắn phản hồi cho khách hàng..." autocomplete="off">
                <button type="submit" id="admin-chat-submit-btn" title="Gửi tin nhắn (Enter)">
                  <span>Gửi</span>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
              </div>
            </form>
            <p id="admin-chat-feedback" role="status"></p>

            <!-- Kho Món Ăn FASTFOOD (Product Picker Drawer) -->
            <aside class="admin-product-picker-drawer" id="admin-product-picker-drawer" hidden aria-label="Kho Món Ăn FASTFOOD">
              <div class="picker-drawer-header">
                <div class="picker-header-title">
                  <span class="picker-header-icon">🍔</span>
                  <div>
                    <h3>Kho Món Ăn FASTFOOD</h3>
                    <p>Tra cứu nhanh &amp; 1-click gửi thẻ món vào chat</p>
                  </div>
                </div>
                <div class="picker-header-actions">
                  <button type="button" class="picker-icon-btn" id="picker-btn-refresh" title="Làm mới danh sách món">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                  </button>
                  <button type="button" class="picker-icon-btn picker-close-btn" id="picker-btn-close" title="Đóng kho món ăn (Esc)">✕</button>
                </div>
              </div>

              <div class="picker-drawer-controls">
                <div class="picker-search-box">
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                  <input type="text" id="picker-search-input" placeholder="Tìm tên món, mã món, danh mục..." autocomplete="off">
                  <button type="button" id="picker-search-clear" class="picker-clear-btn" hidden title="Xóa tìm kiếm">✕</button>
                </div>

                <div class="picker-status-tabs" role="tablist">
                  <button type="button" class="picker-tab active" data-status="all">Tất cả (<span id="picker-cnt-all">0</span>)</button>
                  <button type="button" class="picker-tab tab-available" data-status="in_stock">Còn hàng (<span id="picker-cnt-available">0</span>)</button>
                  <button type="button" class="picker-tab tab-out" data-status="out_of_stock">Tạm hết (<span id="picker-cnt-out">0</span>)</button>
                  <button type="button" class="picker-tab tab-disabled" data-status="unavailable_today">Hôm nay không bán (<span id="picker-cnt-disabled">0</span>)</button>
                </div>

                <div class="picker-category-filter">
                  <label for="picker-cat-select">Danh mục:</label>
                  <select id="picker-cat-select" class="picker-select">
                    <option value="all">-- Tất cả danh mục món --</option>
                  </select>
                </div>
              </div>

              <div class="picker-product-list" id="picker-product-list" aria-live="polite">
                <!-- Render động danh sách món ăn qua JavaScript -->
              </div>

              <div class="picker-drawer-footer">
                <span id="picker-status-summary">Hiển thị 0 món ăn</span>
                <span class="picker-guide-text">⚡ Nhấp <strong>"Gửi vào chat"</strong> để gửi ngay thẻ món cho khách</span>
              </div>
            </aside>
          </section>
        </section>
        <!-- Section Trợ lý AI Chatbot Quản trị dành cho Admin -->
        <section class="ai-bot-panel" id="ai-bot-section" aria-label="Trợ lý AI Chatbot Quản trị" hidden>
          <div class="ai-bot-container">
            <header class="ai-bot-header">
              <div class="ai-bot-header-left">
                <div class="ai-avatar-wrap">
                  <span class="ai-avatar-icon">🤖</span>
                  <span class="ai-status-indicator online"></span>
                </div>
                <div>
                  <h2>FASTFOOD AI Co-pilot (Trợ Lý Quản Trị)</h2>
                  <p>Trợ lý AI chuyên biệt cho Admin • Tư vấn kinh doanh, chính sách vận hành &amp; CSKH 24/7</p>
                </div>
              </div>
              <div class="ai-bot-header-right">
                <button type="button" class="ai-clear-btn" id="btn-clear-ai-history" title="Làm mới cuộc trò chuyện">
                  🔄 Đoạn chat mới
                </button>
              </div>
            </header>

            <div class="ai-bot-chat-window" id="ai-bot-messages" aria-live="polite">
              <!-- Tin nhắn mở đầu của AI -->
              <article class="ai-chat-bubble assistant">
                <div class="bubble-header">
                  <span class="bubble-avatar">🤖</span>
                  <strong>Trợ lý AI FASTFOOD</strong>
                  <span class="bubble-time">Sẵn sàng</span>
                </div>
                <div class="bubble-body">
                  <p>Xin chào <strong>Quản trị viên</strong>! Tôi là <strong>FASTFOOD AI Co-pilot</strong>, trợ lý ảo
                    thông minh đồng hành cùng bạn trong việc quản lý và phát triển nền tảng đặt đồ ăn.</p>
                  <p>Bạn có thể hỏi tôi bất kỳ nghiệp vụ nào:</p>
                  <ul>
                    <li>📊 <strong>Doanh thu &amp; Vận hành:</strong> Phân tích giờ cao điểm, xu hướng đặt món của các
                      quán Buôn Ma Thuột.</li>
                    <li>📋 <strong>Chính sách hệ thống:</strong> Quy tắc tăng 3% phí ship khi hủy đơn, điều kiện áp dụng
                      mã giảm giá.</li>
                    <li>✍️ <strong>Soạn thảo nội dung:</strong> Viết tin nhắn CSKH, kịch bản giải quyết khiếu nại, thông
                      báo ưu đãi.</li>
                    <li>🍔 <strong>Thực đơn &amp; Quán ăn:</strong> Quản lý danh sách 260 món ăn, giá tiền và tùy chọn
                      topping.</li>
                  </ul>
                </div>
                <div class="ai-prompt-suggestions" id="ai-starter-prompts">
                  <button type="button" class="prompt-chip"
                    data-prompt="Phân tích tình hình doanh thu và các giờ cao điểm đặt món">📈 Phân tích doanh thu &amp;
                    Giờ cao điểm</button>
                  <button type="button" class="prompt-chip"
                    data-prompt="Chính sách phí giao hàng và quy tắc tăng 3% khi khách hủy đơn như thế nào?">📋 Chính
                    sách phí ship &amp; Hủy đơn</button>
                  <button type="button" class="prompt-chip"
                    data-prompt="Soạn mẫu thông báo khuyến mãi giảm giá 50K cuối tuần cho khách">✍️ Soạn tin nhắn khuyến
                    mãi cuối tuần</button>
                  <button type="button" class="prompt-chip"
                    data-prompt="Hướng dẫn quy trình chuẩn xử lý khi khách hàng đánh giá 1 sao vì đồ ăn giao trễ">🛡️
                    Cách xử lý khiếu nại khách hàng 1 sao</button>
                </div>
              </article>
            </div>

            <form class="ai-bot-input-form" id="ai-bot-form">
              <input type="text" id="ai-bot-input"
                placeholder="Hỏi Trợ lý AI bất kỳ điều gì về quản trị, đơn hàng, chính sách..." autocomplete="off"
                required>
              <button type="submit" id="ai-bot-send-btn">
                <svg class="ai-send-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  aria-hidden="true">
                  <path d="m22 2-7 20-4-9-9-4Z" />
                  <path d="M22 2 11 13" />
                </svg>
                <span>Gửi câu hỏi</span>
              </button>
            </form>
          </div>
        </section>
        <section class="reviews-panel" id="reviews-section" aria-label="Đánh giá khách hàng" hidden>
          <header class="reviews-panel-heading">
            <div>
              <h2>Nhận xét &amp; đánh giá gần đây</h2>
              <p>Đánh giá từ khách sau khi hoàn thành đơn hàng (tự động cập nhật thời gian thực)</p>
            </div>
            <div class="reviews-controls">
              <select class="filter-select reviews-filter-select" id="reviews-filter" aria-label="Lọc đánh giá">
                <option value="all">Tất cả đánh giá</option>
                <option value="driver">Tài xế</option>
                <option value="restaurant">Quán ăn</option>
                <option value="5">5 sao ★★★★★</option>
                <option value="4">4 sao ★★★★☆</option>
                <option value="3">3 sao ★★★☆☆</option>
                <option value="2">2 sao ★★☆☆☆</option>
                <option value="1">1 sao ★☆☆☆☆</option>
              </select>
              <span class="reviews-count" id="reviews-count">0 đánh giá</span>
            </div>
          </header>
          <div class="reviews-list" id="reviews-list" aria-live="polite">
            <p class="reviews-empty">Đang tải đánh giá...</p>
          </div>
        </section>

        <!-- BEST SELLER & FLASH DEAL CONFIGURATION SECTION -->
        <section class="bestseller-section" id="bestseller-section" hidden>
          <div class="bs-header">
            <div>
              <h2 class="bs-header-title">
                <span class="bs-header-icon">🔥</span>
                <span>Cài Đặt Mục Best Seller &amp; Giờ Vàng Đếm Ngược</span>
              </h2>
              <p class="bs-header-desc">Tùy chỉnh tiêu đề, đồng hồ đếm ngược Flash Deal và chọn danh sách món ăn bán chạy nhất hiển thị trên Trang Chủ.</p>
            </div>
            <button type="button" class="primary-button" id="btn-save-bestseller" style="background:#ea580c; border-color:#ea580c; padding:10px 22px; font-size:14px; font-weight:700; border-radius:10px; display:inline-flex; align-items:center; gap:7px; box-shadow:0 6px 16px rgba(234,88,12,.22);">
              <span>💾</span> Lưu Cài Đặt Best Seller
            </button>
          </div>

          <div class="bs-top-grid">
            <!-- Cột trái: Thông tin tiêu đề & Đồng hồ -->
            <div class="bs-panel-card">
              <h3 class="bs-panel-title"><span class="bs-step-num">1</span> Cấu hình tiêu đề &amp; Thời gian</h3>

              <div class="bs-field">
                <label class="bs-label" for="bs-cfg-title">Tiêu đề mục (Title)</label>
                <input type="text" id="bs-cfg-title" class="bs-input" value="<?= escapeHtml($bsConfig['title'] ?? 'Món Ăn Bán Chạy Nhất (Best Sellers)') ?>">
              </div>

              <div class="bs-field">
                <label class="bs-label" for="bs-cfg-subtitle">Mô tả phụ (Subtitle)</label>
                <input type="text" id="bs-cfg-subtitle" class="bs-input" value="<?= escapeHtml($bsConfig['subtitle'] ?? 'Được khách hàng đặt nhiều nhất trong tuần qua - Ưu đãi giờ vàng giới hạn!') ?>">
              </div>

              <div class="bs-field">
                <label class="bs-label" for="bs-cfg-badge">Nhãn huy hiệu (Badge Tag)</label>
                <input type="text" id="bs-cfg-badge" class="bs-input" value="<?= escapeHtml($bsConfig['badge'] ?? '🔥 GIỜ VÀNG BEST SELLER') ?>" placeholder="Ví dụ: 🔥 GIỜ VÀNG BEST SELLER">
              </div>

              <div class="bs-field">
                <label class="bs-toggle-row" for="bs-cfg-timer-enabled">
                  <div>
                    <span class="bs-toggle-label">Bật đồng hồ đếm ngược Giờ Vàng</span>
                    <span class="bs-toggle-sub">Hiển thị bộ đếm ngược Flash Countdown Timer trên Bảng Cam</span>
                  </div>
                  <span class="bs-switch">
                    <input type="checkbox" id="bs-cfg-timer-enabled" <?= !empty($bsConfig['timer_enabled']) ? 'checked' : '' ?>>
                    <span class="bs-switch-slider"></span>
                  </span>
                </label>
              </div>

              <div class="bs-field" id="bs-endtime-group" style="<?= empty($bsConfig['timer_enabled']) ? 'display:none;' : '' ?>">
                <label class="bs-label" for="bs-cfg-enddate">Thời điểm kết thúc đếm ngược</label>
                <?php 
                  $endTimestamp = !empty($bsConfig['end_time']) ? strtotime($bsConfig['end_time']) : strtotime('+2 days 23:59:59');
                  if (!$endTimestamp) $endTimestamp = strtotime('+2 days 23:59:59');
                  $rawEnd = date('Y-m-d\TH:i', $endTimestamp);
                  $rawEndDate = date('Y-m-d', $endTimestamp);
                  $rawEndTime = date('H:i', $endTimestamp);
                ?>
                <input type="hidden" id="bs-cfg-endtime" value="<?= escapeHtml($rawEnd) ?>">
                <div class="bs-datetime-split">
                  <input type="date" id="bs-cfg-enddate" class="bs-input" value="<?= escapeHtml($rawEndDate) ?>" aria-label="Ngày kết thúc">
                  <input type="time" id="bs-cfg-endtime-part" class="bs-input" value="<?= escapeHtml($rawEndTime) ?>" aria-label="Giờ kết thúc">
                </div>

                <div class="bs-preset-row">
                  <button type="button" class="bs-preset-btn" data-hours="6">+6 Tiếng</button>
                  <button type="button" class="bs-preset-btn" data-hours="12">+12 Tiếng</button>
                  <button type="button" class="bs-preset-btn" data-hours="24">+24 Tiếng</button>
                  <button type="button" class="bs-preset-btn" data-hours="72">+3 Ngày</button>
                  <button type="button" class="bs-preset-btn" data-hours="168">+7 Ngày</button>
                </div>
              </div>
            </div>

            <!-- Cột phải: Xem trước giao diện (Live Preview) -->
            <div class="bs-preview-wrap">
              <h3 class="bs-panel-title" style="color:#c2410c;"><span class="bs-step-num">2</span> Xem trước hiển thị trên Trang Chủ</h3>

              <div class="bs-preview-board">
                <div class="bs-preview-top">
                  <div style="min-width:0; flex:1;">
                    <div class="bs-preview-title-row">
                      <strong class="bs-preview-title" id="preview-bs-title"><?= escapeHtml($bsConfig['title'] ?? 'Món Ăn Bán Chạy Nhất (Best Sellers)') ?></strong>
                      <span class="bs-preview-badge" id="preview-bs-badge" style="<?= empty($bsConfig['badge']) ? 'display:none;' : '' ?>">
                        <?= escapeHtml($bsConfig['badge'] ?? '🔥 GIỜ VÀNG BEST SELLER') ?>
                      </span>
                    </div>
                    <small class="bs-preview-subtitle" id="preview-bs-subtitle"><?= escapeHtml($bsConfig['subtitle'] ?? 'Được khách hàng đặt nhiều nhất trong tuần qua') ?></small>
                  </div>

                  <div class="bs-preview-timer" id="preview-bs-timer" style="<?= empty($bsConfig['timer_enabled']) ? 'display:none;' : '' ?>">
                    <span>⏳ Kết thúc:</span>
                    <span class="bs-preview-digit" id="preview-bs-hours">00</span> :
                    <span class="bs-preview-digit" id="preview-bs-minutes">00</span> :
                    <span class="bs-preview-digit" id="preview-bs-seconds">00</span>
                  </div>
                </div>

                <div class="bs-preview-products" id="preview-bs-products"></div>

                <div class="bs-preview-footer">
                  <span>Hiển thị trực tiếp trên Bảng Cam Trang Chủ</span>
                  <span class="bs-preview-count-pill">Đã chọn <b id="preview-selected-count"><?= count($bsConfig['product_ids'] ?? [1,2,3,5]) ?></b> món</span>
                </div>
              </div>
            </div>
          </div>

          <!-- PHẦN 3: BẢNG CHỌN MÓN ĂN LÀM BEST SELLER -->
          <div>
            <div class="bs-picker-head">
              <div>
                <h3 class="bs-panel-title" style="margin-bottom:4px;"><span class="bs-step-num">3</span> Chọn danh sách món ăn Best Seller</h3>
                <p style="font-size:12.5px; color:#64748b; margin:0;">Tick chọn các món muốn hiển thị tại vị trí Best Sellers trên Trang Chủ (khuyên dùng 4 hoặc 8 món).</p>
              </div>
              <div class="bs-picker-controls">
                <div class="bs-search-box">
                  <span class="bs-search-icon">🔍</span>
                  <input type="text" id="bs-search-product" placeholder="Tìm tên món ăn...">
                </div>
                <div class="bs-filter-tabs" role="group" aria-label="Lọc món ăn">
                  <button type="button" class="bs-filter-tab is-active" data-bs-filter="all">Tất cả</button>
                  <button type="button" class="bs-filter-tab" data-bs-filter="selected">Đã chọn (<span id="bs-filter-selected-count"><?= count($bsConfig['product_ids'] ?? [1,2,3,5]) ?></span>)</button>
                </div>
                <button type="button" class="bs-action-btn" id="btn-select-top4">✨ Chọn nhanh 4 món đầu</button>
                <button type="button" class="bs-action-btn is-secondary" id="btn-clear-bestseller">Bỏ chọn hết</button>
              </div>
            </div>

            <div class="bs-product-scroll">
              <div id="bs-product-grid" class="bs-product-grid">
                <?php 
                  $selectedIds = array_map('intval', $bsConfig['product_ids'] ?? [1, 2, 3, 5]);
                  foreach ($productsRaw as $p): 
                    $pId = (int)$p['id'];
                    $pImg = getFoodImageUrl($p['image'] ?? ($p['image_url'] ?? ''));
                    $isChecked = in_array($pId, $selectedIds);
                ?>
                  <label class="bs-prod-card<?= $isChecked ? ' is-selected' : '' ?>" data-name="<?= escapeHtml(mb_strtolower($p['name'])) ?>" data-title="<?= escapeHtml($p['name']) ?>" data-price="<?= escapeHtml(formatVND($p['price'])) ?>" data-img="<?= escapeHtml($pImg) ?>">
                    <input type="checkbox" class="bs-prod-checkbox" value="<?= $pId ?>" <?= $isChecked ? 'checked' : '' ?>>
                    <img class="bs-prod-thumb" src="<?= escapeHtml($pImg) ?>" alt="<?= escapeHtml($p['name']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80';">
                    <div class="bs-prod-info">
                      <strong class="bs-prod-name" title="<?= escapeHtml($p['name']) ?>"><?= escapeHtml($p['name']) ?></strong>
                      <span class="bs-prod-price"><?= formatVND($p['price']) ?></span>
                    </div>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </section>

        <?php
          $exploreLines = [];
          if (!empty($footerConfig['explore_links']) && is_array($footerConfig['explore_links'])) {
              foreach ($footerConfig['explore_links'] as $el) {
                  $exploreLines[] = ($el['label'] ?? '') . '|' . ($el['url'] ?? '');
              }
          }
          $exploreText = implode("\n", $exploreLines);

          $policyLines = [];
          if (!empty($footerConfig['policy_links']) && is_array($footerConfig['policy_links'])) {
              foreach ($footerConfig['policy_links'] as $pl) {
                  $policyLines[] = ($pl['label'] ?? '') . '|' . ($pl['url'] ?? '');
              }
          }
          $policyText = implode("\n", $policyLines);
        ?>

        <!-- BANNER MANAGEMENT SECTION (QUẢN LÝ BANNER & HERO SLIDER) -->
        <section class="bestseller-section" id="banners-section" hidden>
          <div class="bs-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
              <h2 class="bs-header-title">
                <span class="bs-header-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">🎨</span>
                <span>Quản Lý Banner &amp; Slider Trang Chủ</span>
              </h2>
              <p class="bs-header-desc">Tùy biến hình ảnh nổi bật, tiêu đề, nhãn ưu đãi và cài đặt tốc độ tự động chuyển ảnh (1-2s) cho Hero Banner.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <button type="button" class="primary-button" id="btn-add-banner" style="background:#2563eb; border-color:#2563eb; padding:9px 18px; font-size:14px; font-weight:700; border-radius:10px; display:inline-flex; align-items:center; gap:6px;">
                <span>➕</span> Thêm Banner Mới
              </button>
              <button type="button" class="primary-button" id="btn-save-banners" style="background:#ea580c; border-color:#ea580c; padding:9px 20px; font-size:14px; font-weight:700; border-radius:10px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 6px 16px rgba(234,88,12,.22);">
                <span>💾</span> Lưu Tất Cả Thay Đổi
              </button>
            </div>
          </div>

          <!-- Tùy chỉnh tốc độ tự chuyển ảnh (1-2s) -->
          <div class="bs-panel-card" style="margin-bottom:1.5rem; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
              <div>
                <h4 style="font-weight:700; font-size:1rem; margin-bottom:0.25rem; color:#1e293b;">⚡ Thời Gian Tự Động Chuyển Slide (Auto-slide Interval)</h4>
                <p style="font-size:0.875rem; color:#64748b; margin:0;">Tùy chỉnh khoảng thời gian slider tự động đổi ảnh (Yêu cầu 1-2s):</p>
              </div>
              <div style="display:flex; align-items:center; gap:10px;">
                <select id="banner-slide-interval" class="bs-input" style="width:auto; min-width:200px; font-weight:700; padding:9px 14px; border-radius:8px; border:2px solid #cbd5e1; background:#f8fafc;">
                  <option value="1200">1.2 giây (Siêu nhanh)</option>
                  <option value="1500">1.5 giây (Nhanh)</option>
                  <option value="1800" selected>1.8 giây (Khuyên dùng - Mượt mà)</option>
                  <option value="2000">2.0 giây (2 giây)</option>
                  <option value="2500">2.5 giây</option>
                  <option value="3000">3.0 giây</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Danh sách Banner hiển thị dạng Card Grid -->
          <div id="admin-banners-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(360px, 1fr)); gap:1.25rem;">
            <!-- JS sẽ nạp danh sách banner vào đây -->
          </div>
        </section>

        <!-- FOOTER SETTINGS SECTION -->
        <section class="bestseller-section" id="footer-settings-section" hidden>
          <div class="bs-header">
            <div>
              <h2 class="bs-header-title">
                <span class="bs-header-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">⚙️</span>
                <span>Cài Đặt Thông Tin Chân Trang (Footer)</span>
              </h2>
              <p class="bs-header-desc">Chỉnh sửa thông tin liên hệ, hotline, email, giờ hoạt động, địa chỉ và các liên kết hiển thị ở chân trang website.</p>
            </div>
            <button type="button" class="primary-button" id="btn-save-footer" style="background:#ea580c; border-color:#ea580c; padding:10px 22px; font-size:14px; font-weight:700; border-radius:10px; display:inline-flex; align-items:center; gap:7px; box-shadow:0 6px 16px rgba(234,88,12,.22);">
              <span>💾</span> Lưu Cài Đặt Chân Trang
            </button>
          </div>

          <div class="bs-top-grid">
            <!-- Cột 1: Thông tin thương hiệu & Liên hệ -->
            <div class="bs-panel-card">
              <h3 class="bs-panel-title"><span class="bs-step-num">1</span> Thông tin thương hiệu &amp; Liên hệ</h3>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-brand-desc">Giới thiệu ngắn gọn thương hiệu (Cột 1)</label>
                <textarea id="ft-cfg-brand-desc" class="bs-input" rows="3" style="resize:vertical;"><?= escapeHtml($footerConfig['brand_desc'] ?? '') ?></textarea>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="bs-field">
                  <label class="bs-label" for="ft-cfg-hotline">Hotline đặt món (24/7)</label>
                  <input type="text" id="ft-cfg-hotline" class="bs-input" value="<?= escapeHtml($footerConfig['hotline'] ?? '1900 8888') ?>">
                </div>

                <div class="bs-field">
                  <label class="bs-label" for="ft-cfg-email">Email hỗ trợ khách hàng</label>
                  <input type="email" id="ft-cfg-email" class="bs-input" value="<?= escapeHtml($footerConfig['email'] ?? 'hotro@fastfood.vn') ?>">
                </div>
              </div>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-working-hours">Giờ hoạt động phục vụ</label>
                <input type="text" id="ft-cfg-working-hours" class="bs-input" value="<?= escapeHtml($footerConfig['working_hours'] ?? '06:00 - 23:30 hàng ngày') ?>">
              </div>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-address">Địa chỉ cơ sở / cửa hàng</label>
                <input type="text" id="ft-cfg-address" class="bs-input" value="<?= escapeHtml($footerConfig['address'] ?? '16 Hoàng Diệu, P. Thắng Lợi, TP. Buôn Ma Thuột') ?>">
              </div>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-map-url">Đường dẫn Google Maps (Mở khi bấm icon bản đồ)</label>
                <input type="text" id="ft-cfg-map-url" class="bs-input" value="<?= escapeHtml($footerConfig['map_url'] ?? '') ?>">
              </div>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-copyright">Dòng chữ bản quyền (Copyright dòng cuối)</label>
                <input type="text" id="ft-cfg-copyright" class="bs-input" value="<?= escapeHtml($footerConfig['copyright'] ?? '') ?>">
              </div>
            </div>

            <!-- Cột 2: Liên kết cột 2, cột 3 & Xem trước -->
            <div class="bs-panel-card">
              <h3 class="bs-panel-title"><span class="bs-step-num">2</span> Danh sách liên kết &amp; Xem trước</h3>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-explore-links">
                  Liên kết cột "Khám Phá" (Định dạng: <code>Tên hiển thị|Đường dẫn</code>)
                </label>
                <textarea id="ft-cfg-explore-links" class="bs-input" rows="4" style="resize:vertical; font-family:monospace; font-size:12.5px;"><?= escapeHtml($exploreText) ?></textarea>
                <small style="color:#64748b; font-size:11.5px; margin-top:3px; display:block;">Mỗi dòng 1 liên kết. Ví dụ: Thực đơn hôm nay|foods.php</small>
              </div>

              <div class="bs-field">
                <label class="bs-label" for="ft-cfg-policy-links">
                  Liên kết cột "Chính Sách &amp; Hỗ Trợ" (Định dạng: <code>Tên hiển thị|Đường dẫn</code>)
                </label>
                <textarea id="ft-cfg-policy-links" class="bs-input" rows="4" style="resize:vertical; font-family:monospace; font-size:12.5px;"><?= escapeHtml($policyText) ?></textarea>
                <small style="color:#64748b; font-size:11.5px; margin-top:3px; display:block;">Mỗi dòng 1 liên kết. Ví dụ: Chính sách giao hàng|policies.php</small>
              </div>

              <!-- Mini Footer Live Preview -->
              <div style="background:#0f172a; border-radius:12px; padding:16px; margin-top:14px; color:#cbd5e1; font-size:12px; border:1px solid #1e293b;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #334155; padding-bottom:8px;">
                  <strong style="color:#f8fafc; font-size:12.5px; display:flex; align-items:center; gap:6px;">
                    <span>👁️</span> Bản xem trước chân trang trực tiếp
                  </strong>
                  <span style="background:#ea580c; color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;">LIVE</span>
                </div>
                <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:12px; line-height:1.45;">
                  <div>
                    <div style="color:#f97316; font-weight:800; font-size:13px; margin-bottom:4px;">FASTFOOD BMT</div>
                    <div id="prev-ft-desc" style="color:#94a3b8; font-size:11.5px; margin-bottom:6px;"><?= escapeHtml(mb_substr($footerConfig['brand_desc'] ?? '', 0, 70)) ?>...</div>
                    <div style="color:#38bdf8;">📞 <span id="prev-ft-hotline"><?= escapeHtml($footerConfig['hotline'] ?? '') ?></span></div>
                  </div>
                  <div>
                    <div style="color:#f8fafc; font-weight:700; font-size:12px; margin-bottom:3px;">Tổng Đài Hỗ Trợ</div>
                    <div style="color:#94a3b8; font-size:11.5px;">⏰ <span id="prev-ft-hours"><?= escapeHtml($footerConfig['working_hours'] ?? '') ?></span></div>
                    <div style="color:#94a3b8; font-size:11.5px;">📍 <span id="prev-ft-addr"><?= escapeHtml(mb_substr($footerConfig['address'] ?? '', 0, 30)) ?>...</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- MEMBERSHIP & DYNAMIC LOYALTY CONFIGURATION SECTION -->
        <section class="membership-section" id="membership-section" hidden>
          <div class="bs-header">
            <div>
              <h2 class="bs-header-title">
                <span class="bs-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">👑</span>
                <span>Quản Lý Hạng Thành Viên &amp; Điểm Thưởng</span>
              </h2>
              <p class="bs-header-desc">Theo dõi sổ cái thành viên, lịch sử biến động điểm và cấu hình linh hoạt điều kiện nâng hạng / quy đổi điểm thưởng.</p>
            </div>
            <div class="mem-header-stats">
              <div class="mem-stat-pill">
                <span class="mem-stat-label">Tổng thành viên:</span>
                <strong class="mem-stat-val" id="mem-stat-total">0</strong>
              </div>
              <div class="mem-stat-pill">
                <span class="mem-stat-label">Điểm lưu hành:</span>
                <strong class="mem-stat-val" id="mem-stat-points">0 🪙</strong>
              </div>
              <div class="mem-stat-pill">
                <span class="mem-stat-label">Thành viên VIP:</span>
                <strong class="mem-stat-val" id="mem-stat-vip">0</strong>
              </div>
            </div>
          </div>

          <!-- Sub-navigation Tabs -->
          <div class="membership-tab-bar" role="tablist">
            <button type="button" class="membership-tab-btn active" id="tab-btn-members" data-tab="members">
              <span>👥</span> Danh Sách Thành Viên (Ledger)
            </button>
            <button type="button" class="membership-tab-btn" id="tab-btn-config" data-tab="config">
              <span>⚙️</span> Cấu Hình Điểm &amp; Hạng
            </button>
          </div>

          <!-- TAB 1: MEMBERS LEDGER -->
          <div class="membership-tab-content active" id="membership-tab-ledger">
            <div class="mem-controls-bar">
              <div class="mem-search-box">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="member-search-input" placeholder="Tìm tên khách hàng, email, SĐT..." autocomplete="off">
              </div>
              <div class="mem-filter-group">
                <label for="member-tier-filter" class="sr-only">Lọc theo hạng</label>
                <select id="member-tier-filter" class="filter-select">
                  <option value="">Tất cả các hạng</option>
                  <option value="BRONZE">🥉 Hạng Đồng (Bronze)</option>
                  <option value="SILVER">🥈 Hạng Bạc (Silver)</option>
                  <option value="GOLD">🥇 Hạng Vàng (Gold)</option>
                  <option value="DIAMOND">💎 Hạng Kim Cương (Diamond)</option>
                </select>
                <button type="button" class="secondary-button" id="btn-refresh-members" title="Tải lại danh sách">
                  <span>🔄</span> Làm mới
                </button>
              </div>
            </div>

            <div class="table-panel" style="margin-top: 14px;">
              <div class="table-wrap">
                <table class="membership-table">
                  <thead>
                    <tr>
                      <th style="width: 50px;">ID</th>
                      <th>Khách hàng</th>
                      <th>Hạng TV</th>
                      <th>Điểm khả dụng</th>
                      <th>Tổng chi tiêu</th>
                      <th>Ngày tham gia</th>
                      <th style="text-align: right; min-width: 220px;">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody id="membership-table-body">
                    <tr><td colspan="7" class="empty-state">Đang tải danh sách thành viên...</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="table-footer">
                <span class="result-count" id="member-result-count">Hiển thị 0 thành viên</span>
                <nav class="pagination" id="member-pagination" aria-label="Phân trang thành viên"></nav>
              </div>
            </div>
          </div>

          <!-- TAB 2: DYNAMIC LOYALTY CONFIGURATION -->
          <div class="membership-tab-content" id="membership-tab-config" hidden>
            <div id="loyalty-role-notice-container"></div>
            <div id="loyalty-config-alert" class="loyalty-alert-banner" hidden></div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b;">Bảng Điều Khiển Cấu Hình Điểm &amp; Nâng Hạng</h3>
                <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;">Mọi thay đổi sẽ có hiệu lực ngay lập tức cho các giao dịch và đơn hàng tiếp theo.</p>
              </div>
              <button type="button" class="primary-button" id="btn-save-loyalty-config" style="background:#ea580c; border-color:#ea580c; padding:10px 22px; font-size:14px; font-weight:700; border-radius:10px; display:inline-flex; align-items:center; gap:7px; box-shadow:0 6px 16px rgba(234,88,12,.22);">
                <span>💾</span> Lưu Cấu Hình
              </button>
            </div>

            <form id="form-loyalty-config">
              <div class="bs-top-grid" style="grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));">
                <!-- Card 1: Hạn mức nâng hạng (min_spent) -->
                <div class="bs-panel-card">
                  <h4 class="bs-panel-title"><span class="bs-step-num">1</span> Điều kiện Nâng Hạng (Tổng chi tiêu VNĐ)</h4>
                  <div class="bs-field">
                    <label class="bs-label">🥉 Hạng Đồng (Bronze)</label>
                    <input type="text" class="bs-input" value="0 đ (Mặc định khi đăng ký)" disabled style="background:#f1f5f9; color:#64748b;">
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-silver-min-spent">🥈 Hạng Bạc (Silver) - Chi tiêu từ (VNĐ)</label>
                    <input type="number" id="cfg-silver-min-spent" class="bs-input loyalty-cfg-input" min="0" step="50000" required>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-gold-min-spent">🥇 Hạng Vàng (Gold) - Chi tiêu từ (VNĐ)</label>
                    <input type="number" id="cfg-gold-min-spent" class="bs-input loyalty-cfg-input" min="0" step="50000" required>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-diamond-min-spent">💎 Hạng Kim Cương (Diamond) - Chi tiêu từ (VNĐ)</label>
                    <input type="number" id="cfg-diamond-min-spent" class="bs-input loyalty-cfg-input" min="0" step="50000" required>
                  </div>
                </div>

                <!-- Card 2: Tỷ lệ tích điểm (ratios) -->
                <div class="bs-panel-card">
                  <h4 class="bs-panel-title"><span class="bs-step-num">2</span> Tỷ Lệ Tích Điểm (Điểm / 10.000 VNĐ chi tiêu)</h4>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-bronze-ratio">🥉 Hạng Đồng (Mặc định 1.0)</label>
                    <input type="number" id="cfg-bronze-ratio" class="bs-input loyalty-cfg-input" min="0.1" step="0.1" required>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-silver-ratio">🥈 Hạng Bạc (Mặc định 1.5)</label>
                    <input type="number" id="cfg-silver-ratio" class="bs-input loyalty-cfg-input" min="0.1" step="0.1" required>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-gold-ratio">🥇 Hạng Vàng (Mặc định 2.0)</label>
                    <input type="number" id="cfg-gold-ratio" class="bs-input loyalty-cfg-input" min="0.1" step="0.1" required>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-diamond-ratio">💎 Hạng Kim Cương (Mặc định 3.0)</label>
                    <input type="number" id="cfg-diamond-ratio" class="bs-input loyalty-cfg-input" min="0.1" step="0.1" required>
                  </div>
                </div>

                <!-- Card 3: Quy đổi điểm & Thanh toán -->
                <div class="bs-panel-card">
                  <h4 class="bs-panel-title"><span class="bs-step-num">3</span> Tỷ Lệ Quy Đổi Điểm Thưởng &amp; Thanh Toán</h4>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-point-to-vnd">Tỷ lệ quy đổi: 1 điểm = ? VNĐ</label>
                    <input type="number" id="cfg-point-to-vnd" class="bs-input loyalty-cfg-input" min="1" step="1" required>
                    <small style="color:#64748b; font-size:11px; margin-top:3px; display:block;">Chuẩn hệ thống: 100 VNĐ/điểm (100 điểm = 10.000 VNĐ giảm giá)</small>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-spending-step">Bước tính điểm theo chi tiêu (VNĐ)</label>
                    <input type="number" id="cfg-spending-step" class="bs-input loyalty-cfg-input" min="1000" step="1000" required>
                    <small style="color:#64748b; font-size:11px; margin-top:3px; display:block;">Mặc định: 10.000 VNĐ cho mỗi bước tích điểm</small>
                  </div>
                  <div class="bs-field">
                    <label class="bs-label" for="cfg-min-redeem-points">Số điểm tối thiểu để quy đổi tại Checkout</label>
                    <input type="number" id="cfg-min-redeem-points" class="bs-input loyalty-cfg-input" min="0" step="10" required>
                    <small style="color:#64748b; font-size:11px; margin-top:3px; display:block;">Mặc định: 100 điểm</small>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </section>

        <!-- VOUCHER MANAGEMENT SECTION -->
        <section class="vouchers-section" id="vouchers-section" hidden>
          <div class="bs-header" style="margin-bottom: 8px;">
            <div>
              <h2 class="bs-header-title">
                <span class="bs-header-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">🎟️</span>
                <span>Quản Lý Voucher &amp; Mã Khuyến Mãi</span>
              </h2>
              <p class="bs-header-desc">Tạo mới, phân loại 4 hình thức ưu đãi (%, Tiền mặt, Freeship, Mua 1 tặng 1), thiết lập điều kiện hạng TV và theo dõi hiệu quả sử dụng.</p>
            </div>
          </div>

          <!-- Bento KPI Cards Overview -->
          <div class="vc-kpi-grid">
            <div class="vc-kpi-card">
              <div class="vc-kpi-icon">🎟️</div>
              <div class="vc-kpi-content">
                <div class="vc-kpi-label">Tổng Số Voucher</div>
                <div class="vc-kpi-val" id="vc-stat-total">0</div>
                <div class="vc-kpi-sub">Đang chạy: <strong id="vc-stat-active-sub" style="color:#16a34a;">0</strong></div>
              </div>
            </div>
            <div class="vc-kpi-card kpi-active">
              <div class="vc-kpi-icon">🟢</div>
              <div class="vc-kpi-content">
                <div class="vc-kpi-label">Đang Áp Dụng</div>
                <div class="vc-kpi-val" id="vc-stat-active" style="color:#16a34a;">0</div>
                <div class="vc-kpi-sub">Sẵn sàng phát hành</div>
              </div>
            </div>
            <div class="vc-kpi-card kpi-usage">
              <div class="vc-kpi-icon">📈</div>
              <div class="vc-kpi-content">
                <div class="vc-kpi-label">Lượt Đổi Voucher</div>
                <div class="vc-kpi-val" id="vc-stat-usages" style="color:#0284c7;">0</div>
                <div class="vc-kpi-sub">Lượt dùng thành công</div>
              </div>
            </div>
            <div class="vc-kpi-card kpi-disc">
              <div class="vc-kpi-icon">💸</div>
              <div class="vc-kpi-content">
                <div class="vc-kpi-label">Tổng Tiền Đã Giảm</div>
                <div class="vc-kpi-val" id="vc-stat-discount" style="color:#ea580c;">0đ</div>
                <div class="vc-kpi-sub">Ngân sách trợ giá ưu đãi</div>
              </div>
            </div>
            <div class="vc-kpi-card kpi-bday">
              <div class="vc-kpi-icon">🎂</div>
              <div class="vc-kpi-content">
                <div class="vc-kpi-label">Quà Tặng Sinh Nhật</div>
                <div class="vc-kpi-val" id="vc-stat-birthday" style="color:#d97706;">0 🎂</div>
                <div class="vc-kpi-sub">Tự động trao theo hạng</div>
              </div>
            </div>
          </div>

          <!-- Controls Toolbar (Redesigned Modern SaaS Suite) -->
          <div class="vc-toolbar-card">
            <div class="vc-toolbar-main">
              <!-- Left: Search & Filter Controls -->
              <div class="vc-toolbar-filters">
                <!-- Search Box with Vector SVG Icon & Clear Button -->
                <div class="vc-search-box-wrap">
                  <span class="vc-search-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="11" cy="11" r="8"></circle>
                      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                  </span>
                  <input type="text" id="vc-search-input" class="vc-search-input" placeholder="Tìm theo mã hoặc tiêu đề..." autocomplete="off">
                  <button type="button" class="vc-search-clear" id="vc-search-clear" title="Xóa tìm kiếm">✕</button>
                </div>

                <!-- Filter: Voucher Type -->
                <div class="vc-select-wrap">
                  <span class="vc-select-icon">🏷️</span>
                  <select id="vc-filter-type" class="vc-custom-select" title="Lọc theo loại voucher">
                    <option value="">Tất cả loại voucher</option>
                    <option value="percentage">🔥 Giảm %</option>
                    <option value="fixed">💰 Giảm tiền mặt</option>
                    <option value="free_shipping">🚚 Freeship</option>
                    <option value="bogo">🍔 Mua 1 Tặng 1</option>
                  </select>
                  <span class="vc-select-chevron">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                  </span>
                </div>

                <!-- Filter: Member Tier -->
                <div class="vc-select-wrap">
                  <span class="vc-select-icon">👑</span>
                  <select id="vc-filter-tier" class="vc-custom-select" title="Lọc theo hạng thành viên">
                    <option value="">Tất cả hạng TV</option>
                    <option value="ALL">Khách hàng chung (ALL)</option>
                    <option value="BRONZE">👑 Hạng Đồng (Bronze)</option>
                    <option value="SILVER">👑 Hạng Bạc (Silver)</option>
                    <option value="GOLD">👑 Hạng Vàng (Gold)</option>
                    <option value="DIAMOND">👑 Kim Cương (Diamond)</option>
                  </select>
                  <span class="vc-select-chevron">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                  </span>
                </div>

                <!-- Filter: Status -->
                <div class="vc-select-wrap">
                  <span class="vc-select-icon">🔘</span>
                  <select id="vc-filter-status" class="vc-custom-select" title="Lọc theo trạng thái">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">🟢 Đang hoạt động</option>
                    <option value="0">🔒 Đã khóa</option>
                  </select>
                  <span class="vc-select-chevron">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                  </span>
                </div>

                <!-- Reset Filters Button -->
                <button type="button" class="vc-btn-reset-filters" id="vc-btn-reset-filters" title="Đặt lại bộ lọc về mặc định" style="display:none;">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                    <path d="M3 3v5h5"></path>
                  </svg>
                  <span>Đặt lại</span>
                </button>
              </div>

              <!-- Right: Action Buttons -->
              <div class="vc-toolbar-actions">
                <button type="button" class="vc-btn-bday" id="btn-trigger-bday-cron" title="Quét tự động và tặng voucher cho khách hàng có sinh nhật hôm nay">
                  <span style="font-size:16px;">🎂</span>
                  <span>Quét Tặng Sinh Nhật Hôm Nay</span>
                </button>
                <button type="button" class="vc-btn-create" id="btn-open-create-voucher" title="Tạo mới chương trình ưu đãi hoặc mã voucher">
                  <span class="vc-plus-badge">＋</span>
                  <span>Tạo Voucher Mới</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Table Card -->
          <div class="table-card">
            <div class="table-wrap">
              <table id="vouchers-table" class="data-table">
                <thead>
                  <tr>
                    <th>Mã Voucher</th>
                    <th>Tiêu Đề</th>
                    <th>Hình Thức</th>
                    <th>Mức Giảm</th>
                    <th>Đơn Tối Thiểu</th>
                    <th>Giảm Tối Đa</th>
                    <th>Lượt Dùng</th>
                    <th>Hạng TV</th>
                    <th>Hạn Dùng</th>
                    <th>Trạng Thái</th>
                    <th style="text-align:right;">Thao Tác</th>
                  </tr>
                </thead>
                <tbody id="vouchers-table-body">
                  <tr><td colspan="11" class="empty-state">Đang tải danh sách voucher...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- VOUCHER CREATE / EDIT MODAL DIALOG -->
  <dialog id="voucher-admin-dialog" class="record-dialog" aria-labelledby="voucher-dialog-title" style="max-width:680px; width:92%; border-radius:18px; border:none; box-shadow:0 24px 50px rgba(0,0,0,0.22); padding:0; overflow:hidden;">
    <div class="modal-head" style="background:#fff7ed; padding:18px 22px; border-bottom:1px solid #fed7aa; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h2 id="voucher-dialog-title" style="font-size:1.15rem; font-weight:850; color:#9a3412; margin:0;">Tạo Voucher Mới</h2>
        <p style="font-size:0.8rem; color:#c2410c; margin:2px 0 0;">Thiết lập thông tin, hình thức giảm và điều kiện áp dụng.</p>
      </div>
      <button class="modal-close" type="button" onclick="closeVoucherAdminModal()" aria-label="Đóng" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#9a3412;">×</button>
    </div>

    <form id="voucher-admin-form" onsubmit="saveVoucherAdminForm(event)" style="padding:22px; display:flex; flex-direction:column; gap:16px; max-height:80vh; overflow-y:auto;">
      <input type="hidden" id="v-field-id" value="">

      <!-- Live Ticket Preview -->
      <div class="vc-live-preview-box">
        <div class="vc-live-preview-head">
          <span>👁️ Xem trước Voucher (Hiển thị phía khách hàng):</span>
        </div>
        <div class="vc-preview-ticket" id="vc-preview-ticket-wrap">
          <div class="vc-prev-stub" id="vc-prev-stub">
            <span style="font-size:1.4rem;" id="vc-prev-icon">🔥</span>
            <strong style="font-size:1.15rem; font-weight:900; margin-top:4px;" id="vc-prev-discount">15%</strong>
            <span style="font-size:9.5px; opacity:0.9; text-transform:uppercase; letter-spacing:0.5px;" id="vc-prev-stub-label">GIẢM GIÁ</span>
          </div>
          <div class="vc-prev-body">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
              <div style="min-width:0;">
                <strong style="font-size:0.92rem; color:#1e293b; display:block; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="vc-prev-title">Tiêu đề voucher</strong>
                <span style="font-size:11px; color:#64748b; margin-top:2px; display:block;" id="vc-prev-cond">Đơn tối thiểu 0đ</span>
              </div>
              <span style="background:#fff7ed; color:#ea580c; border:1px dashed #ea580c; padding:3px 7px; border-radius:6px; font-size:11.5px; font-weight:800; letter-spacing:0.5px; white-space:nowrap;" id="vc-prev-code">FASTCODE</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; font-size:10.5px; color:#94a3b8;">
              <span id="vc-prev-tier">👑 Tất cả thành viên</span>
              <span style="color:#16a34a; font-weight:700;">● Hoạt động</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Visual Voucher Type Selector -->
      <div>
        <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:6px;">Hình thức ưu đãi *</label>
        <div class="vc-type-selector" id="vc-modal-type-selector">
          <div class="vc-type-card active" data-type="percentage" onclick="window.selectVoucherTypeModal('percentage')">
            <div class="vc-type-icon">🔥</div>
            <div class="vc-type-title">Giảm %</div>
            <div class="vc-type-desc">Theo tỷ lệ %</div>
          </div>
          <div class="vc-type-card" data-type="fixed" onclick="window.selectVoucherTypeModal('fixed')">
            <div class="vc-type-icon">💰</div>
            <div class="vc-type-title">Giảm Tiền</div>
            <div class="vc-type-desc">Cố định VNĐ</div>
          </div>
          <div class="vc-type-card" data-type="free_shipping" onclick="window.selectVoucherTypeModal('free_shipping')">
            <div class="vc-type-icon">🚚</div>
            <div class="vc-type-title">Freeship</div>
            <div class="vc-type-desc">Phí giao hàng</div>
          </div>
          <div class="vc-type-card" data-type="bogo" onclick="window.selectVoucherTypeModal('bogo')">
            <div class="vc-type-icon">🍔</div>
            <div class="vc-type-title">Mua 1 Tặng 1</div>
            <div class="vc-type-desc">Tặng món quy đổi</div>
          </div>
        </div>
        <!-- Select kept sync'ed for form submission and standard validation -->
        <select id="v-field-type" class="form-control" onchange="window.updateVoucherFormTypeLabels()" style="display:none;" required>
          <option value="percentage">Giảm theo % (Phần trăm)</option>
          <option value="fixed">Giảm tiền mặt cố định (VNĐ)</option>
          <option value="free_shipping">🚚 Miễn phí vận chuyển (Freeship)</option>
          <option value="bogo">🍔 Mua 1 tặng 1 (BOGO)</option>
        </select>
      </div>

      <!-- Row 1: Code & Generator -->
      <div style="display:grid; grid-template-columns:1fr auto; gap:10px; align-items:flex-end;">
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Mã Voucher (Code) *</label>
          <input type="text" id="v-field-code" class="form-control" placeholder="VD: FASTFOOD20" style="text-transform:uppercase; font-weight:800; letter-spacing:0.5px;" required>
        </div>
        <button type="button" class="btn btn-outline" onclick="generateRandomVoucherCode()" style="white-space:nowrap; padding:0.55rem 0.85rem; font-size:0.8rem; font-weight:750;">
          ⚡ Sinh mã ngẫu nhiên
        </button>
      </div>

      <!-- Row 2: Title -->
      <div>
        <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Tiêu đề hiển thị *</label>
        <input type="text" id="v-field-title" class="form-control" placeholder="VD: Giảm 20.000đ cho đơn hàng từ 60.000đ" required>
      </div>

      <!-- Row 3: Description -->
      <div>
        <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Mô tả / Thể lệ áp dụng</label>
        <textarea id="v-field-desc" class="form-control" rows="2" placeholder="Áp dụng cho mọi món ăn trên toàn hệ thống FASTFOOD..."></textarea>
      </div>

      <!-- Row 4: Discount Value -->
      <div>
        <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;" id="lbl-discount-val">Mức giảm (% hoặc VNĐ) *</label>
        <input type="number" id="v-field-disc-val" class="form-control" min="0" step="any" placeholder="VD: 15 (nếu %) hoặc 20000" required>
      </div>

      <!-- Row 5: Min Order & Max Discount -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Đơn hàng tối thiểu (VNĐ)</label>
          <input type="number" id="v-field-min-order" class="form-control" min="0" step="1000" value="0" placeholder="0 = không yêu cầu">
        </div>
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Mức giảm tối đa (VNĐ)</label>
          <input type="number" id="v-field-max-disc" class="form-control" min="0" step="1000" placeholder="Để trống nếu không giới hạn">
        </div>
      </div>

      <!-- Row 6: Limits & Tier -->
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Tổng số phát hành</label>
          <input type="number" id="v-field-usage-limit" class="form-control" min="0" value="0" placeholder="0 = vô hạn">
        </div>
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Lượt dùng / Mỗi khách</label>
          <input type="number" id="v-field-user-limit" class="form-control" min="1" value="1" required>
        </div>
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Hạng thành viên</label>
          <select id="v-field-tier" class="form-control">
            <option value="ALL">Tất cả khách hàng</option>
            <option value="BRONZE">Từ Hạng Đồng (Bronze)+</option>
            <option value="SILVER">Từ Hạng Bạc (Silver)+</option>
            <option value="GOLD">Từ Hạng Vàng (Gold)+</option>
            <option value="DIAMOND">Chỉ Kim Cương (Diamond)</option>
          </select>
        </div>
      </div>

      <!-- Row 7: Date Range -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Ngày bắt đầu hiệu lực</label>
          <input type="datetime-local" id="v-field-start" class="form-control">
        </div>
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:750; color:#334155; margin-bottom:4px;">Ngày kết thúc (Hết hạn)</label>
          <input type="datetime-local" id="v-field-end" class="form-control">
        </div>
      </div>

      <!-- Row 8: Checkboxes -->
      <div style="display:flex; gap:20px; align-items:center; background:#f8fafc; padding:10px 14px; border-radius:10px; border:1px solid #e2e8f0;">
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem; font-weight:700; color:#334155; cursor:pointer;">
          <input type="checkbox" id="v-field-public" checked style="accent-color:#ea580c;">
          Công khai trên Sàn Săn Voucher
        </label>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem; font-weight:700; color:#334155; cursor:pointer;">
          <input type="checkbox" id="v-field-active" checked style="accent-color:#16a34a;">
          Kích hoạt hoạt động ngay
        </label>
      </div>

      <!-- Footer Buttons -->
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
        <button type="button" class="btn btn-outline" onclick="closeVoucherAdminModal()" style="font-weight:700;">Hủy Bỏ</button>
        <button type="submit" id="btn-save-voucher-submit" class="btn btn-primary" style="font-weight:750; padding:0.6rem 1.4rem;">
          💾 Lưu Voucher
        </button>
      </div>
    </form>
  </dialog>

  <dialog id="record-dialog" aria-labelledby="dialog-title">
    <div class="modal-head">
      <div>
        <h2 id="dialog-title">Thêm mới</h2>
        <p id="dialog-description">Điền thông tin để cập nhật dữ liệu.</p>
      </div>
      <button class="modal-close" type="button" id="dialog-close" aria-label="Đóng">×</button>
    </div>
    <form class="modal-form" id="record-form">
      <div class="form-fields" id="form-fields"></div>
      <div class="modal-actions">
        <button class="secondary-button" type="button" id="dialog-cancel">Hủy</button>
        <button class="primary-button" type="submit" id="save-button">Lưu thông tin</button>
      </div>
    </form>
  </dialog>

  <!-- BANNER EDIT/ADD DIALOG -->
  <dialog id="banner-dialog" style="max-width:550px; width:90%; border-radius:16px; border:none; padding:0; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
    <div class="modal-head" style="padding:1.25rem 1.5rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h2 id="banner-dialog-title" style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0;">Chỉnh Sửa Banner</h2>
        <p style="font-size:0.85rem; color:#64748b; margin-top:2px; margin-bottom:0;">Cập nhật thông tin và hình ảnh hiển thị trên slider trang chủ.</p>
      </div>
      <button class="modal-close" type="button" id="banner-dialog-close" style="font-size:1.5rem; border:none; background:none; cursor:pointer;">×</button>
    </div>
    <form class="modal-form" id="banner-form" style="padding:1.5rem;">
      <input type="hidden" id="banner-edit-id">
      <div style="display:flex; flex-direction:column; gap:1rem;">
        <div class="bs-field">
          <label class="bs-label" for="banner-title" style="font-weight:700;">Tiêu đề Banner <span style="color:#ef4444;">*</span></label>
          <input type="text" id="banner-title" class="bs-input" required placeholder="Ví dụ: Burger Bò Nướng Phô Mai" style="width:100%;">
        </div>
        <div class="bs-field">
          <label class="bs-label" for="banner-subtitle" style="font-weight:700;">Mô tả phụ (Subtitle)</label>
          <input type="text" id="banner-subtitle" class="bs-input" placeholder="Ví dụ: Burger King Station • Nóng hổi giao 15 phút" style="width:100%;">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="bs-field">
            <label class="bs-label" for="banner-badge" style="font-weight:700;">Nhãn ưu đãi (Badge)</label>
            <input type="text" id="banner-badge" class="bs-input" placeholder="Ví dụ: 🔥 ƯU ĐÃI GIẢM 50%" style="width:100%;">
          </div>
          <div class="bs-field">
            <label class="bs-label" for="banner-order" style="font-weight:700;">Thứ tự (1, 2, 3...)</label>
            <input type="number" id="banner-order" class="bs-input" value="1" min="1" style="width:100%;">
          </div>
        </div>
        <div class="bs-field">
          <label class="bs-label" for="banner-image" style="font-weight:700;">Đường dẫn ảnh Banner (16:9 Landscape) <span style="color:#ef4444;">*</span></label>
          <input type="text" id="banner-image" class="bs-input" required placeholder="https://... hoặc assets/images/foods/..." style="width:100%;">
          <div id="banner-image-preview-box" style="margin-top:8px; border-radius:8px; overflow:hidden; max-height:160px; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
            <img id="banner-image-preview" src="" alt="Xem trước ảnh" style="width:100%; height:160px; object-fit:cover; display:none;">
          </div>
        </div>
        <div class="bs-field">
          <label class="bs-label" for="banner-link" style="font-weight:700;">Liên kết điều hướng khi nhấp</label>
          <input type="text" id="banner-link" class="bs-input" placeholder="Ví dụ: foods.php?search=Burger" value="foods.php" style="width:100%;">
        </div>
        <div class="bs-field" style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" id="banner-active" checked style="width:18px; height:18px; cursor:pointer;">
          <label for="banner-active" style="cursor:pointer; font-weight:600; color:#1e293b; user-select:none;">Kích hoạt hiển thị trên slider trang chủ</label>
        </div>
      </div>
      <div class="modal-actions" style="margin-top:1.5rem; display:flex; justify-content:flex-end; gap:10px;">
        <button class="secondary-button" type="button" id="banner-dialog-cancel">Hủy</button>
        <button class="primary-button" type="submit" id="banner-save-btn" style="background:#ea580c; border-color:#ea580c; font-weight:700;">Lưu Banner</button>
      </div>
    </form>
  </dialog>

  <!-- POINTS HISTORY AUDIT MODAL -->
  <dialog id="points-history-dialog" class="points-modal-dialog">
    <div class="modal-head">
      <div>
        <h2 id="points-dialog-title" style="margin:0; font-size:1.15rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
          <span>📜</span> Lịch Sử Điểm Thưởng
        </h2>
        <p id="points-dialog-subtitle" style="margin:2px 0 0; font-size:0.8rem; color:#64748b;">Chi tiết biến động điểm tích lũy và sử dụng của thành viên.</p>
      </div>
      <button class="modal-close" type="button" id="btn-close-points-dialog" aria-label="Đóng">×</button>
    </div>

    <div class="points-modal-body">
      <!-- Member Summary Strip -->
      <div class="mem-summary-strip" id="points-mem-summary">
        <div class="mem-avatar-box" id="pts-avatar">--</div>
        <div class="mem-info-box">
          <strong class="mem-name" id="pts-name">Khách hàng</strong>
          <span class="mem-email" id="pts-email">email@example.com</span>
        </div>
        <div class="mem-stat-pill-sm">
          <span>Hạng:</span>
          <span id="pts-tier-badge" class="tier-badge">--</span>
        </div>
        <div class="mem-stat-pill-sm">
          <span>Điểm khả dụng:</span>
          <strong id="pts-balance" style="color:#ea580c; font-size:14px;">0 🪙</strong>
        </div>
        <div class="mem-stat-pill-sm">
          <span>Tổng chi tiêu:</span>
          <strong id="pts-spent" style="color:#0f172a;">0 đ</strong>
        </div>
      </div>

      <!-- Transaction Table -->
      <div class="table-wrap" style="max-height: 380px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; margin-top: 14px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
          <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 2;">
            <tr>
              <th style="padding: 10px 14px;">Thời gian</th>
              <th style="padding: 10px 14px;">Loại giao dịch</th>
              <th style="padding: 10px 14px;">Biến động điểm</th>
              <th style="padding: 10px 14px;">Mô tả / Đơn hàng</th>
              <th style="padding: 10px 14px; text-align: right;">Số dư sau GD</th>
            </tr>
          </thead>
          <tbody id="points-history-table-body">
            <tr><td colspan="5" class="empty-state">Đang tải lịch sử giao dịch...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-actions" style="padding: 14px 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
      <button class="secondary-button" type="button" id="btn-cancel-points-dialog">Đóng</button>
    </div>
  </dialog>

  <!-- MEMBER LOYALTY EDIT / TIER PROMOTION MODAL -->
  <dialog id="member-loyalty-edit-dialog" class="points-modal-dialog" style="width: min(640px, calc(100vw - 32px));">
    <form id="form-edit-member-loyalty" method="dialog">
      <div class="modal-head">
        <div>
          <h2 style="margin:0; font-size:1.15rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
            <span>👑</span> Sửa &amp; Thăng Hạng Thành Viên
          </h2>
          <p style="margin:2px 0 0; font-size:0.8rem; color:#64748b;">Nâng hạng thành viên, điều chỉnh điểm thưởng hoặc tổng chi tiêu tích lũy.</p>
        </div>
        <button class="modal-close" type="button" id="btn-close-edit-member-dialog" aria-label="Đóng">×</button>
      </div>

      <div class="points-modal-body" style="display:flex; flex-direction:column; gap:14px;">
        <input type="hidden" id="edit-mem-user-id" value="">
        <input type="hidden" id="edit-mem-tier-value" value="BRONZE">

        <!-- Member Summary Strip -->
        <div class="mem-summary-strip">
          <div class="mem-avatar-box" id="edit-mem-avatar">--</div>
          <div class="mem-info-box" style="flex:1; min-width:150px;">
            <strong id="edit-mem-name">Thành viên</strong>
            <span id="edit-mem-email">email@example.com</span>
          </div>
          <div class="mem-stat-pill-sm">
            <span>Hạng hiện tại:</span>
            <span id="edit-mem-current-tier-badge" class="tier-badge tier-badge-bronze">--</span>
          </div>
        </div>

        <!-- Tier Selector Cards -->
        <div>
          <label class="bs-label" style="font-weight:800; color:#0f172a; display:block; margin-bottom:4px;">
            Chọn Hạng Thành Viên Mới <span style="color:#ea580c;">*</span>
          </label>
          <div class="tier-selector-grid" id="edit-mem-tier-grid">
            <button type="button" class="tier-select-card tier-bronze" data-tier="BRONZE" data-min-spent="0">
              <span class="tier-select-icon">🥉</span>
              <span class="tier-select-name">Đồng</span>
              <span class="tier-select-meta">x1 điểm • 0đ</span>
            </button>
            <button type="button" class="tier-select-card tier-silver" data-tier="SILVER" data-min-spent="1000000">
              <span class="tier-select-icon">🥈</span>
              <span class="tier-select-name">Bạc</span>
              <span class="tier-select-meta">x1.5 điểm • 1Tr</span>
            </button>
            <button type="button" class="tier-select-card tier-gold" data-tier="GOLD" data-min-spent="3000000">
              <span class="tier-select-icon">🥇</span>
              <span class="tier-select-name">Vàng</span>
              <span class="tier-select-meta">x2 điểm • 3Tr</span>
            </button>
            <button type="button" class="tier-select-card tier-diamond" data-tier="DIAMOND" data-min-spent="5000000">
              <span class="tier-select-icon">💎</span>
              <span class="tier-select-name">Kim Cương</span>
              <span class="tier-select-meta">x3 điểm • 5Tr</span>
            </button>
          </div>
        </div>

        <!-- Sync Min Spent Checkbox -->
        <label style="display:flex; align-items:center; gap:8px; background:#fff7ed; border:1px solid #fed7aa; padding:9px 12px; border-radius:10px; cursor:pointer; font-size:12px; font-weight:650; color:#9a3412;">
          <input type="checkbox" id="edit-mem-sync-spent" style="width:16px; height:16px; accent-color:#ea580c; cursor:pointer;">
          <span>Tự động nâng Tổng chi tiêu đạt mốc tối thiểu của hạng mới (nếu đang thấp hơn)</span>
        </label>

        <!-- Points & Total Spent Inputs -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
          <div class="bs-field" style="margin:0;">
            <label class="bs-label" for="edit-mem-points" style="font-weight:750;">Điểm khả dụng (🪙)</label>
            <input type="number" id="edit-mem-points" class="bs-input" min="0" step="1" required style="width:100%;">
            <div class="quick-pts-row">
              <button type="button" class="quick-pts-btn" data-add-pts="100">+100 🪙</button>
              <button type="button" class="quick-pts-btn" data-add-pts="500">+500 🪙</button>
              <button type="button" class="quick-pts-btn" data-add-pts="1000">+1.000 🪙</button>
            </div>
          </div>
          <div class="bs-field" style="margin:0;">
            <label class="bs-label" for="edit-mem-spent" style="font-weight:750;">Tổng chi tiêu tích lũy (VNĐ)</label>
            <input type="number" id="edit-mem-spent" class="bs-input" min="0" step="1000" required style="width:100%;">
            <small id="edit-mem-spent-hint" style="display:block; margin-top:5px; font-size:11px; color:#64748b;">Hiện tại: 0 đ</small>
          </div>
        </div>

        <!-- Reason / Note -->
        <div class="bs-field" style="margin:0;">
          <label class="bs-label" for="edit-mem-reason" style="font-weight:750;">Ghi chú / Lý do điều chỉnh (lưu vào Lịch sử điểm)</label>
          <input type="text" id="edit-mem-reason" class="bs-input" maxlength="200" placeholder="Ví dụ: Admin thăng hạng VIP khách hàng thân thiết" style="width:100%;">
        </div>
      </div>

      <div class="modal-actions" style="padding: 14px 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap:10px;">
        <button class="secondary-button" type="button" id="btn-cancel-edit-member-dialog">Hủy</button>
        <button class="primary-button" type="submit" id="btn-save-edit-member" style="background:#ea580c; border-color:#ea580c; font-weight:800;">
          <span>💾</span> Lưu Thay Đổi
        </button>
      </div>
    </form>
  </dialog>

  <script>
    // Global Admin Messages Nav Badge API
    (function () {
      window.getMessagesNavBadgeCount = function () {
        const badge = document.getElementById('messages-nav-badge');
        if (!badge) return 0;
        if (badge.hasAttribute('hidden') || badge.style.display === 'none') return 0;
        const text = (badge.textContent || '').trim();
        if (!text) return 0;
        if (text === '99+') return 100;
        const parsed = parseInt(text, 10);
        return isNaN(parsed) ? 0 : Math.max(0, parsed);
      };

      window.updateMessagesNavBadge = function (count) {
        const badge = document.getElementById('messages-nav-badge');
        if (!badge) return;
        const parsedCount = parseInt(count, 10);
        const finalCount = isNaN(parsedCount) ? 0 : Math.max(0, parsedCount);

        if (finalCount > 0) {
          badge.textContent = finalCount > 99 ? '99+' : String(finalCount);
          badge.removeAttribute('hidden');
          badge.style.display = '';
        } else {
          badge.textContent = '0';
          badge.setAttribute('hidden', '');
        }
      };

      window.clearMessagesBadge = function () {
        window.updateMessagesNavBadge(0);
      };

      window.addEventListener('support:unread_count', function (event) {
        const count = event && event.detail !== undefined
          ? (typeof event.detail === 'object' && event.detail !== null && event.detail.count !== undefined ? event.detail.count : event.detail)
          : 0;
        window.updateMessagesNavBadge(count);
      });
    })();
  </script>
  <script>
    window.FASTFOOD_DATA = <?= json_encode($initialData, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="<?= getUrl('js/realtime.js') ?>"></script>
  <script src="<?= getUrl('js/admin-chat.js') ?>"></script>
  <script>
    (async () => {
      if (window.location.protocol === 'file:') {
        window.location.replace('http://localhost:5000/pages/admin/dashboard.html');
        return;
      }

      window.BASE_URL = <?= json_encode(BASE_URL) ?>;
      const serverToken = <?= json_encode($adminToken) ?>;
      const serverUser = <?= json_encode([
          'id' => (int)$currentUser['id'],
          'fullName' => $currentUser['full_name'],
          'email' => $currentUser['email'],
          'role' => $isAdmin ? 'admin' : 'staff',
          'roleId' => (int)$currentUser['role_id']
      ]) ?>;
      const isAdminUser = <?= $isAdmin ? 'true' : 'false' ?>;
      const isSuperAdmin = isAdminUser;
      const isStaffUser = <?= $isStaff ? 'true' : 'false' ?>;

      const STORAGE_KEY = 'fastfood-admin-dashboard-v1';
      const pageSize = 5;
      const tokenKey = 'fastfood_auth_token';
      let token = serverToken || sessionStorage.getItem(tokenKey);
      if (serverToken) {
        sessionStorage.setItem(tokenKey, serverToken);
      }

      function showAccessMessage(title, message) {
        const content = document.querySelector('.content');
        if (!content) return;
        content.innerHTML = '';
        const panel = document.createElement('section');
        panel.className = 'table-panel';
        panel.style.padding = '30px';
        const heading = document.createElement('h1');
        heading.textContent = title;
        const description = document.createElement('p');
        description.textContent = message;
        const loginLink = document.createElement('a');
        loginLink.className = 'primary-button';
        loginLink.href = '/login.php';
        loginLink.textContent = 'Đến trang đăng nhập';
        panel.append(heading, description, loginLink);
        content.appendChild(panel);
      }

      let authenticatedUser = serverUser;

      const liveSocket = (typeof window.initFastfoodRealtime === 'function') ? await window.initFastfoodRealtime(authenticatedUser) : null;
      let reviewsUiReady = false;
      const pendingReviewEvents = new Map();
      let renderedReviews = [];
      let reviewsLoaded = false;
      let reviewsRequestId = 0;

      let unreadReviewsBadgeCount = 0;
      const reviewsNavBadge = document.getElementById('reviews-nav-badge');

      function updateReviewsBadge(count) {
        if (!reviewsNavBadge) return;
        if (typeof count === 'number') {
          unreadReviewsBadgeCount = Math.max(0, count);
        }
        if (unreadReviewsBadgeCount > 0 && activeSection !== 'reviews') {
          reviewsNavBadge.textContent = unreadReviewsBadgeCount > 9 ? '9+' : String(unreadReviewsBadgeCount);
          reviewsNavBadge.hidden = false;
        } else {
          reviewsNavBadge.hidden = true;
          reviewsNavBadge.textContent = '0';
        }
      }

      function clearReviewsBadge() {
        unreadReviewsBadgeCount = 0;
        try {
          localStorage.setItem('fastfood_admin_last_seen_review_time', String(Date.now()));
        } catch {}
        if (reviewsNavBadge) {
          reviewsNavBadge.hidden = true;
          reviewsNavBadge.textContent = '0';
        }
      }

      liveSocket?.on('review:new', (review) => {
        const target = review.target === 'driver' ? 'tài xế' : 'quán ăn';
        showToast(`Đánh giá mới từ ${review.customerName || 'khách hàng'} cho ${target}: ${review.rating}/5 sao.`);
        const cleanCode = String(review.orderCode || '').replace(/^#/, '');
        const normalizedReview = {
          ...review,
          orderCode: cleanCode,
          isNew: true,
        };
        const key = `${cleanCode}:${review.target}`;
        pendingReviewEvents.set(key, normalizedReview);
        renderedReviews = mergeReviews(renderedReviews, [normalizedReview]);
        reviewsLoaded = true;

        if (reviewsUiReady && activeSection === 'reviews') {
          renderOrderReviews(renderedReviews);
        } else {
          unreadReviewsBadgeCount += 1;
          updateReviewsBadge();
        }
        loadOrderReviews({ silent: true });
      });

      if (typeof window.initAdminChatInbox === 'function') {
        window.adminChatInbox = window.initAdminChatInbox({
          socket: liveSocket,
          token: serverToken || token,
          apiUrl: '<?= BASE_URL ?>/api/v1',
        });
      }

      let dashboardReady = false;
      const pendingOrderEvents = [];
      const pendingPaymentEvents = [];
      const realtimeStatus = document.getElementById('realtime-status');
      function setRealtimeStatus(connected) {
        if (!realtimeStatus) return;
        realtimeStatus.textContent = connected ? 'Đang trực tuyến' : 'Mất kết nối';
        realtimeStatus.classList.toggle('connected', connected);
        realtimeStatus.classList.toggle('disconnected', !connected);
      }
      setRealtimeStatus(true);

      if (liveSocket) {
        liveSocket.on('connect', () => {
          setRealtimeStatus(true);
          if (dashboardReady) refreshOrders();
          if (activeSection === 'reviews') loadOrderReviews({ silent: true });
        });
        liveSocket.on('disconnect', () => setRealtimeStatus(false));
        liveSocket.on('order:new', ({ order } = {}) => {
          if (!dashboardReady) {
            pendingOrderEvents.push({ type: 'new', order });
            return;
          }
          handleNewOrder(order);
        });
        liveSocket.on('order:updated', (update) => {
          if (!dashboardReady) {
            pendingOrderEvents.push({ type: 'updated', update });
            return;
          }
          handleOrderUpdate(update);
        });
        liveSocket.on('payment:updated', (update) => {
          if (!dashboardReady) {
            pendingPaymentEvents.push(update);
            return;
          }
          handlePaymentUpdate(update);
        });
        liveSocket.on('notification:new', (notification) => {
          handleIncomingNotification(notification);
        });
        liveSocket.on('support:unread_count', ({ count } = {}) => {
          if (typeof window.updateMessagesNavBadge === 'function') {
            window.updateMessagesNavBadge(count);
          }
        });
        window.addEventListener('fastfood:notification-new', (event) => {
          if (event.detail) handleIncomingNotification(event.detail);
        });
      }

      let publishedContent = window.FASTFOOD_DATA || {};

      document.querySelector('.profile-copy strong').textContent = authenticatedUser.fullName;
      document.querySelector('.profile-copy span').textContent = authenticatedUser.email;
      document.getElementById('admin-profile-clickable')?.addEventListener('click', () => {
        if (confirm('Bạn có muốn đăng xuất khỏi trang quản trị không?')) {
          document.getElementById('logout-button')?.click();
        }
      });
      document.getElementById('logout-button').addEventListener('click', () => {
        sessionStorage.removeItem(tokenKey);
        for (const key of ['fastfood_user', 'fastfood_cart', 'fastfood_orders', 'fastfood_active_order', 'fastfood_applied_coupon', 'fastfood_available_coupons']) {
          sessionStorage.removeItem(key);
        }
        window.location.href = '<?= BASE_URL ?>/admin/logout.php';
      });

      const sections = {
        dashboard: {
          title: 'Tổng quan hệ thống', description: 'Theo dõi hoạt động kinh doanh và quản lý dữ liệu của bạn.',
          tableTitle: 'Sản phẩm gần đây', subtitle: 'Danh sách sản phẩm được cập nhật mới nhất', collection: 'products',
          goal: 'Theo dõi doanh thu, đơn hàng và sản phẩm nổi bật để nắm bắt tình hình kinh doanh trong tuần.',
          metricLabel: 'đơn mới trong 7 ngày',
        },
        categories: {
          title: 'Quản lý danh mục', description: 'Sắp xếp và cập nhật các danh mục món ăn trên hệ thống.',
          tableTitle: 'Danh sách danh mục', subtitle: 'Quản lý các nhóm sản phẩm đang hiển thị', collection: 'categories',
          goal: 'Phân nhóm món ăn rõ ràng để khách dễ tìm kiếm; rà soát danh mục đang hoạt động và số sản phẩm thuộc từng nhóm.',
          metricLabel: 'danh mục đang hoạt động',
        },
        products: {
          title: 'Quản lý sản phẩm', description: 'Theo dõi thực đơn và tình trạng kinh doanh của sản phẩm.',
          tableTitle: 'Danh sách sản phẩm', subtitle: 'Tất cả sản phẩm trên hệ thống', collection: 'products',
          goal: 'Cập nhật tên món, danh mục, quán, giá bán và tình trạng còn hàng để thực đơn hiển thị chính xác.',
          metricLabel: 'sản phẩm đang bán',
        },
        restaurants: {
          title: 'Quản lý quán ăn', description: 'Cập nhật địa chỉ và đường dẫn của các quán trên website.',
          tableTitle: 'Danh sách quán ăn', subtitle: 'Thông tin quán được hiển thị trên FASTFOOD', collection: 'restaurants',
          goal: 'Quản lý thông tin, địa chỉ và trạng thái hiển thị của từng quán để khách đặt món đúng địa điểm.',
          metricLabel: 'quán đang hoạt động',
        },
        orders: {
          title: 'Quản lý đơn hàng', description: 'Theo dõi tiến độ và thông tin các đơn hàng gần đây.',
          tableTitle: 'Danh sách đơn hàng', subtitle: 'Đơn hàng mới nhất trên FASTFOOD', collection: 'orders',
          goal: 'Theo dõi trạng thái đơn từ lúc xác nhận đến khi giao xong; xử lý đơn đang chờ và cập nhật trạng thái kịp thời.',
          metricLabel: 'đơn đang chờ xác nhận',
        },
        users: {
          title: 'Quản lý người dùng', description: 'Xem và quản lý thông tin tài khoản trên hệ thống.',
          tableTitle: 'Danh sách người dùng', subtitle: 'Tài khoản khách hàng và đối tác', collection: 'users',
          goal: 'Quản lý thông tin liên hệ, vai trò và trạng thái tài khoản để duy trì dữ liệu người dùng chính xác.',
          metricLabel: 'tài khoản đang hoạt động',
        },
      };
      const schemas = {
        categories: {
          singular: 'danh mục', statusOptions: ['Đang hoạt động', 'Đã ẩn'],
          columns: [{ key: 'name', label: 'Tên danh mục' }, { key: 'description', label: 'Mô tả' }, { key: 'itemCount', label: 'Số sản phẩm' }, { key: 'status', label: 'Trạng thái' }],
          fields: [{ key: 'name', label: 'Tên danh mục', required: true }, { key: 'description', label: 'Mô tả' }, { key: 'itemCount', label: 'Số sản phẩm', type: 'number', min: 0 }, { key: 'status', label: 'Trạng thái', type: 'status' }],
        },
        products: {
          singular: 'sản phẩm', statusOptions: ['Đang bán', 'Tạm hết'],
          columns: [{ key: 'name', label: 'Tên sản phẩm' }, { key: 'category', label: 'Danh mục' }, { key: 'restaurant', label: 'Nhà hàng' }, { key: 'address', label: 'Địa chỉ' }, { key: 'image', label: 'URL ảnh' }, { key: 'price', label: 'Giá bán' }, { key: 'toppings', label: 'Toppings' }, { key: 'status', label: 'Trạng thái' }],
          fields: [{ key: 'name', label: 'Tên sản phẩm', required: true }, { key: 'category', label: 'Danh mục', required: true }, { key: 'restaurant', label: 'Nhà hàng', required: true }, { key: 'address', label: 'Địa chỉ món ăn', required: true }, { key: 'image', label: 'URL ảnh món ăn', type: 'image' }, { key: 'price', label: 'Giá bán (đ)', type: 'number', min: 0, required: true }, { key: 'status', label: 'Trạng thái', type: 'status' }],
        },
        restaurants: {
          singular: 'quán ăn', statusOptions: ['Đang hoạt động', 'Đã ẩn'],
          columns: [{ key: 'name', label: 'Tên quán ăn' }, { key: 'address', label: 'Địa chỉ' }, { key: 'url', label: 'URL quán ăn' }, { key: 'status', label: 'Trạng thái' }],
          fields: [{ key: 'name', label: 'Tên quán ăn', required: true }, { key: 'address', label: 'Địa chỉ', required: true }, { key: 'url', label: 'URL quán ăn', type: 'url' }, { key: 'status', label: 'Trạng thái', type: 'status' }],
        },
        orders: {
          singular: 'đơn hàng', statusOptions: ['Chờ xác nhận', 'Đang chuẩn bị', 'Đang giao', 'Đã giao', 'Đã hủy'],
          columns: [{ key: 'orderNo', label: 'Mã đơn hàng' }, { key: 'customer', label: 'Khách hàng' }, { key: 'restaurant', label: 'Nhà hàng' }, { key: 'total', label: 'Tổng tiền' }, { key: 'paymentLabel', label: 'Thanh toán' }, { key: 'date', label: 'Ngày đặt' }, { key: 'status', label: 'Trạng thái' }],
          fields: [{ key: 'status', label: 'Trạng thái', type: 'status' }],
        },
        users: {
          singular: 'người dùng', statusOptions: ['Đang hoạt động', 'Không hoạt động', 'Đã khóa'],
          columns: [{ key: 'name', label: 'Họ và tên' }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Số điện thoại' }, { key: 'role', label: 'Vai trò' }, { key: 'status', label: 'Trạng thái' }],
          fields: [{ key: 'name', label: 'Họ và tên', required: true }, { key: 'email', label: 'Email', type: 'email', required: true }, { key: 'phone', label: 'Số điện thoại', type: 'tel', required: true }, { key: 'role', label: 'Vai trò', required: true }, { key: 'status', label: 'Trạng thái', type: 'status' }],
        },
      };
      const seedData = {
        restaurants: (window.FASTFOOD_DATA && Array.isArray(window.FASTFOOD_DATA.restaurants))
          ? window.FASTFOOD_DATA.restaurants
          : [],
        categories: (window.FASTFOOD_DATA && Array.isArray(window.FASTFOOD_DATA.categories))
          ? window.FASTFOOD_DATA.categories
          : [],
        products: (window.FASTFOOD_DATA && Array.isArray(window.FASTFOOD_DATA.products))
          ? window.FASTFOOD_DATA.products
          : [],
        orders: (window.FASTFOOD_DATA && Array.isArray(window.FASTFOOD_DATA.orders))
          ? window.FASTFOOD_DATA.orders
          : [],
        users: (window.FASTFOOD_DATA && Array.isArray(window.FASTFOOD_DATA.users))
          ? window.FASTFOOD_DATA.users
          : [],
      };

      let data = {
        restaurants: [...seedData.restaurants],
        categories: [...seedData.categories],
        products: [...seedData.products],
        orders: [...seedData.orders],
        users: [...seedData.users],
      };

      let activeSection = 'dashboard';
      let currentPage = 1;
      let searchTerm = '';
      let statusFilter = '';
      let editingId = null;
      let imagePreviewObjectUrl = null;
      const tableHead = document.getElementById('table-head');
      const tableBody = document.getElementById('table-body');
      const filterSelect = document.getElementById('status-filter');
      const chatInbox = document.getElementById('chat-inbox');
      const aiBotSection = document.getElementById('ai-bot-section');
      const tablePanel = document.getElementById('table-title').closest('.table-panel');
      const reviewsSection = document.getElementById('reviews-section');
      const bestsellerSection = document.getElementById('bestseller-section');
      const bannersSection = document.getElementById('banners-section');
      const footerSettingsSection = document.getElementById('footer-settings-section');
      const reviewsList = document.getElementById('reviews-list');
      const reviewsCount = document.getElementById('reviews-count');
      reviewsUiReady = true;
      const dialog = document.getElementById('record-dialog');
      const form = document.getElementById('record-form');

      function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[char]);
      }

      function showToast(message, isError = false) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast${isError ? ' error' : ''}`;
        toast.textContent = message;
        container.appendChild(toast);
        window.setTimeout(() => toast.remove(), 3400);
      }

      let reviewFilterValue = 'all';
      let reviewSearchTerm = '';

      async function loadOrderReviews(options = {}) {
        const requestId = ++reviewsRequestId;
        const rawReviews = (window.FASTFOOD_DATA && Array.isArray(window.FASTFOOD_DATA.reviews)) ? window.FASTFOOD_DATA.reviews : [];
        renderedReviews = rawReviews.map(r => ({
          id: r.id,
          orderCode: r.order_id || r.order_code || '',
          customerName: r.customer_name || 'Khách hàng',
          target: r.target || 'restaurant',
          rating: Number(r.rating || 5),
          comment: r.comment || '',
          submittedAt: r.created_at || new Date().toISOString()
        }));
        reviewsLoaded = true;
        updateReviewsBadge(renderedReviews.length);
        if (activeSection === 'reviews' || options.forceRender) {
          renderOrderReviews(renderedReviews);
        }
      }

      function mergeReviews(...groups) {
        const byOrderAndTarget = new Map();
        groups.flat().forEach((review) => {
          if (review?.orderCode && ['driver', 'restaurant'].includes(review.target)) {
            const cleanCode = String(review.orderCode).replace(/^#/, '');
            const existing = byOrderAndTarget.get(`${cleanCode}:${review.target}`);
            byOrderAndTarget.set(`${cleanCode}:${review.target}`, {
              ...existing,
              ...review,
              orderCode: cleanCode,
              isNew: review.isNew || existing?.isNew || false,
            });
          }
        });
        return [...byOrderAndTarget.values()].sort((first, second) => (
          new Date(second.submittedAt || 0).getTime() - new Date(first.submittedAt || 0).getTime()
        ));
      }

      function renderOrderReviews(reviews) {
        renderedReviews = reviews;
        let filtered = reviews;
        if (reviewFilterValue === 'driver' || reviewFilterValue === 'restaurant') {
          filtered = filtered.filter((r) => r.target === reviewFilterValue);
        } else if (['1', '2', '3', '4', '5'].includes(reviewFilterValue)) {
          filtered = filtered.filter((r) => Number(r.rating) === Number(reviewFilterValue));
        }
        if (reviewSearchTerm) {
          filtered = filtered.filter((r) =>
            String(r.orderCode || '').toLocaleLowerCase('vi').includes(reviewSearchTerm)
            || String(r.customerName || '').toLocaleLowerCase('vi').includes(reviewSearchTerm)
            || String(r.restaurantName || '').toLocaleLowerCase('vi').includes(reviewSearchTerm)
            || String(r.comment || '').toLocaleLowerCase('vi').includes(reviewSearchTerm)
          );
        }

        reviewsCount.textContent = `${filtered.length} / ${reviews.length} đánh giá`;
        reviewsList.replaceChildren();
        if (!filtered.length) {
          const empty = document.createElement('p');
          empty.className = 'reviews-empty';
          empty.textContent = reviews.length === 0
            ? 'Chưa có đánh giá nào từ khách hàng.'
            : 'Không tìm thấy đánh giá phù hợp với tìm kiếm hoặc bộ lọc.';
          reviewsList.appendChild(empty);
          return;
        }

        filtered.forEach((review) => {
          const card = document.createElement('article');
          card.className = `review-card${review.isNew ? ' just-added' : ''}`;
          const top = document.createElement('div');
          top.className = 'review-card-top';
          const customer = document.createElement('div');
          customer.className = 'review-customer';
          const name = document.createElement('strong');
          name.textContent = review.customerName || 'Khách hàng';
          if (review.isNew) {
            const newTag = document.createElement('span');
            newTag.className = 'new-tag';
            newTag.textContent = 'MỚI';
            name.appendChild(newTag);
          }
          const order = document.createElement('span');
          order.textContent = `Đơn #${review.orderCode || ''}`;
          customer.append(name, order);
          const target = document.createElement('span');
          target.className = 'review-target';
          target.textContent = review.target === 'driver' ? '🛵 Tài xế' : `🏪 Quán · ${review.restaurantName || ''}`;
          top.append(customer, target);

          const stars = document.createElement('div');
          stars.className = 'review-stars';
          stars.setAttribute('aria-label', `${review.rating} trên 5 sao`);
          stars.textContent = `${'★'.repeat(Math.max(0, Math.min(5, Number(review.rating) || 0)))}${'☆'.repeat(5 - Math.max(0, Math.min(5, Number(review.rating) || 0)))}`;
          const comment = document.createElement('p');
          comment.className = 'review-comment';
          comment.textContent = review.comment || 'Khách hàng không để lại nhận xét.';
          const meta = document.createElement('div');
          meta.className = 'review-card-meta';
          const submittedAt = new Date(review.submittedAt || '');
          meta.textContent = Number.isNaN(submittedAt.getTime())
            ? ''
            : submittedAt.toLocaleString('vi-VN', { dateStyle: 'medium', timeStyle: 'short' });
          card.append(top, stars, comment, meta);
          reviewsList.appendChild(card);
        });
      }

      document.getElementById('reviews-filter')?.addEventListener('change', (event) => {
        reviewFilterValue = event.target.value;
        renderOrderReviews(renderedReviews);
      });

      function toOrderRecord(order) {
        const orderDate = new Date(order.createdAt || '');
        const vietnamDate = Number.isNaN(orderDate.getTime()) ? '' : vietnamDateKey(orderDate);
        const statusText = order.statusText || ({
          pending: 'Chờ xác nhận',
          preparing: 'Đang chuẩn bị',
          delivering: 'Đang giao',
          delivered: 'Đã giao',
          cancelled: 'Đã hủy',
        })[order.status] || order.status;
        return {
          ...order,
          id: String(order.id),
          orderNo: `#${order.id}`,
          customer: order.recipient?.name || 'Khách hàng',
          restaurant: order.restaurantName || 'FASTFOOD Partner',
          total: Number(order.total) || 0,
          paymentMethod: String(order.paymentMethod || '').toLowerCase(),
          paymentStatus: String(order.paymentStatus || 'pending').toLowerCase(),
          paymentLabel: order.paymentStatus === 'completed' ? 'Đã thanh toán'
            : String(order.paymentMethod || '').toLowerCase() === 'momo' ? 'Chờ MoMo'
              : String(order.paymentMethod || '').toLowerCase() === 'banking' ? 'Chờ QR' : 'COD',
          date: vietnamDate || String(order.createdAt || '').slice(0, 10),
          status: statusText || 'Chờ xác nhận',
        };
      }

      function formatVietnamDateTime(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value || '');
        return new Intl.DateTimeFormat('vi-VN', {
          timeZone: 'Asia/Ho_Chi_Minh',
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hourCycle: 'h23',
        }).format(date);
      }

      async function refreshOrders() {
        try {
          const res = await fetch('api.php?action=get_all_data');
          const result = await res.json();
          if (result.success && result.data && Array.isArray(result.data.orders)) {
            data.orders = result.data.orders;
            renderAll();
          }
        } catch (error) {
          console.warn('[refreshOrders]', error);
        }
      }

      function saveRealtimeSnapshot() {
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (error) {
          showToast('Không thể lưu bản sao dữ liệu realtime trên trình duyệt.', true);
        }
      }

      // ===== ADMIN NOTIFICATIONS & REAL-TIME ALERTS =====
      let adminNotifications = [];
      let unreadNotifCount = 0;
      let unreadOrdersBadgeCount = 0;
      const notifBellBtn = document.getElementById('notif-bell-btn');
      const notifDropdown = document.getElementById('notif-dropdown');
      const notifBellBadge = document.getElementById('notif-bell-badge');
      const notifUnreadTag = document.getElementById('notif-unread-tag');
      const notifList = document.getElementById('notif-list');
      const notifMarkAllBtn = document.getElementById('notif-mark-all-btn');
      const ordersNavBadge = document.getElementById('orders-nav-badge');

      function updateOrdersNavBadge() {
        if (!ordersNavBadge) return;
        if (unreadOrdersBadgeCount > 0) {
          ordersNavBadge.textContent = unreadOrdersBadgeCount > 9 ? '9+' : String(unreadOrdersBadgeCount);
          ordersNavBadge.hidden = false;
        } else {
          ordersNavBadge.hidden = true;
          ordersNavBadge.textContent = '0';
        }
      }

      function updateNotifBellBadge() {
        unreadNotifCount = adminNotifications.filter((n) => !n.isRead).length;
        if (notifBellBadge) {
          if (unreadNotifCount > 0) {
            notifBellBadge.textContent = unreadNotifCount > 99 ? '99+' : String(unreadNotifCount);
            notifBellBadge.hidden = false;
          } else {
            notifBellBadge.hidden = true;
            notifBellBadge.textContent = '0';
          }
        }
        if (notifUnreadTag) {
          notifUnreadTag.textContent = `${unreadNotifCount} chưa đọc`;
        }
      }

      function playNewOrderSound() {
        try {
          const AudioContextClass = window.AudioContext || window.webkitAudioContext;
          if (!AudioContextClass) return;
          const ctx = new AudioContextClass();
          const now = ctx.currentTime;

          // Sound chord: Ding-dong chime (A5 880Hz -> C#6 1108Hz)
          const osc1 = ctx.createOscillator();
          const gain1 = ctx.createGain();
          osc1.type = 'sine';
          osc1.frequency.setValueAtTime(880, now);
          osc1.frequency.exponentialRampToValueAtTime(1108, now + 0.12);
          gain1.gain.setValueAtTime(0.35, now);
          gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
          osc1.connect(gain1);
          gain1.connect(ctx.destination);
          osc1.start(now);
          osc1.stop(now + 0.6);

          const osc2 = ctx.createOscillator();
          const gain2 = ctx.createGain();
          osc2.type = 'triangle';
          osc2.frequency.setValueAtTime(1320, now + 0.14);
          gain2.gain.setValueAtTime(0.25, now + 0.14);
          gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
          osc2.connect(gain2);
          gain2.connect(ctx.destination);
          osc2.start(now + 0.14);
          osc2.stop(now + 0.75);
        } catch (err) {
          console.warn('[Audio] Could not synthesize chime:', err.message);
        }
      }

      function navigateToOrders(orderCode = '') {
        const button = document.querySelector('.nav-item[data-section="orders"]');
        if (!button) return;
        activeSection = 'orders';
        currentPage = 1;
        searchTerm = orderCode ? String(orderCode).replace(/^#/, '').trim().toLocaleLowerCase('vi') : '';
        statusFilter = '';
        const searchInput = document.getElementById('search-input');
        if (searchInput) searchInput.value = orderCode ? String(orderCode).replace(/^#/, '').trim() : '';
        document.querySelectorAll('.nav-item').forEach((item) => {
          item.classList.toggle('active', item === button);
          if (item === button) item.setAttribute('aria-current', 'page');
          else item.removeAttribute('aria-current');
        });
        unreadOrdersBadgeCount = 0;
        updateOrdersNavBadge();
        renderSection();
        closeMobileMenu();
        const main = document.querySelector('.main');
        if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
      }

      function showOrderToast(order) {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const card = document.createElement('div');
        card.className = 'order-toast-card';
        const cleanId = String(order.id || order.orderCode || '').replace(/^#/, '');
        const customerName = order.recipient?.name || order.customer || order.customerName || 'Khách hàng';
        const totalAmount = Number(order.total) || 0;
        const totalStr = money(totalAmount);
        const itemsList = Array.isArray(order.items)
          ? order.items.map((it) => `${it.qty || 1}x ${it.name}`).slice(0, 2).join(', ') + (order.items.length > 2 ? '...' : '')
          : (order.itemsSummary || '');

        card.innerHTML = `
          <div class="order-toast-icon" aria-hidden="true">🛍️</div>
          <div class="order-toast-body">
            <div class="order-toast-header">
              <span class="order-toast-title">Đơn hàng mới #${escapeHtml(cleanId)}</span>
              <span class="order-toast-badge">Mới</span>
            </div>
            <div class="order-toast-detail">
              <strong>${escapeHtml(customerName)}</strong> vừa đặt món.<br>
              Tổng tiền: <span class="order-toast-total">${escapeHtml(totalStr)}</span>
              ${itemsList ? `<div style="font-size: 11px; color: #64748b; margin-top: 3px;">${escapeHtml(itemsList)}</div>` : ''}
            </div>
            <button class="order-toast-action" type="button" data-order-code="${escapeHtml(cleanId)}">
              Xem đơn hàng
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
          </div>
          <button class="order-toast-close" type="button" aria-label="Đóng">&times;</button>
        `;

        card.querySelector('.order-toast-action').addEventListener('click', () => {
          navigateToOrders(cleanId);
          card.remove();
        });

        card.querySelector('.order-toast-close').addEventListener('click', () => {
          card.remove();
        });

        container.prepend(card);
        window.setTimeout(() => {
          if (card.parentElement) card.remove();
        }, 12000);
      }

      function formatNotifTime(dateString) {
        if (!dateString) return 'Vừa xong';
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) return String(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMinutes = Math.floor(diffMs / 60000);
        if (diffMinutes < 1) return 'Vừa xong';
        if (diffMinutes < 60) return `${diffMinutes} phút trước`;
        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) return `${diffHours} giờ trước`;
        return new Intl.DateTimeFormat('vi-VN', {
          day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
        }).format(date);
      }

      function renderAdminNotifications() {
        if (!notifList) return;
        if (adminNotifications.length === 0) {
          notifList.innerHTML = '<div class="notif-empty">Chưa có thông báo nào</div>';
          return;
        }

        notifList.innerHTML = adminNotifications.map((notif) => {
          const isUnread = !notif.isRead;
          const isOrder = notif.type === 'order';
          const icon = isOrder ? '🛍️' : notif.type === 'review' ? '⭐' : '🔔';
          const iconClass = isOrder ? 'order' : notif.type === 'review' ? 'review' : '';
          const timeFormatted = formatNotifTime(notif.createdAt);
          const orderCode = notif.orderCode || notif.metadata?.orderCode || '';

          return `
            <div class="notif-item${isUnread ? ' unread' : ''}" data-notif-id="${notif.id}" data-order-code="${escapeHtml(orderCode)}" role="article" tabindex="0">
              <div class="notif-icon ${iconClass}" aria-hidden="true">${icon}</div>
              <div class="notif-item-body">
                <div class="notif-item-top">
                  <span class="notif-item-title">${escapeHtml(notif.title || 'Thông báo mới')}</span>
                  <span class="notif-item-time">${escapeHtml(timeFormatted)}</span>
                </div>
                <p class="notif-item-text">${escapeHtml(notif.content || '')}</p>
              </div>
            </div>
          `;
        }).join('');

        notifList.querySelectorAll('.notif-item').forEach((itemEl) => {
          itemEl.addEventListener('click', async () => {
            const notifId = Number(itemEl.dataset.notifId);
            const orderCode = itemEl.dataset.orderCode;
            if (notifId) await markNotificationRead(notifId);
            if (orderCode) {
              closeNotifDropdown();
              navigateToOrders(orderCode);
            }
          });
        });
      }

      async function loadAdminNotifications() {
        const pendingOrders = (data.orders || []).filter(o => o.status === 'Chờ xác nhận' || o.status === 'pending');
        adminNotifications = pendingOrders.slice(0, 10).map((o, idx) => ({
          id: `notif-${idx}`,
          title: 'Đơn hàng mới',
          message: `Đơn #${o.orderNo || o.id} từ ${o.customer || 'Khách hàng'} đang chờ xác nhận`,
          isRead: false,
          createdAt: o.date || o.createdAt || new Date().toISOString()
        }));
        updateNotifBellBadge();
        renderAdminNotifications();
      }

      async function markNotificationRead(notifId) {
        const item = adminNotifications.find((n) => n.id === notifId);
        if (item && !item.isRead) {
          item.isRead = true;
          updateNotifBellBadge();
          renderAdminNotifications();
        }
      }

      async function markAllAdminNotificationsRead() {
        adminNotifications.forEach((n) => { n.isRead = true; });
        updateNotifBellBadge();
        renderAdminNotifications();
        showToast('Đã đánh dấu tất cả thông báo là đã đọc.');
      }

      function toggleNotifDropdown() {
        if (!notifDropdown) return;
        const isHidden = notifDropdown.hidden;
        if (isHidden) {
          notifDropdown.hidden = false;
          notifBellBtn?.setAttribute('aria-expanded', 'true');
        } else {
          closeNotifDropdown();
        }
      }

      function closeNotifDropdown() {
        if (!notifDropdown) return;
        notifDropdown.hidden = true;
        notifBellBtn?.setAttribute('aria-expanded', 'false');
      }

      function handleIncomingNotification(notif) {
        if (!notif) return;
        const existingIdx = adminNotifications.findIndex((n) => n.id && notif.id && n.id === notif.id);
        if (existingIdx >= 0) {
          adminNotifications[existingIdx] = { ...adminNotifications[existingIdx], ...notif };
        } else {
          adminNotifications.unshift({
            id: notif.id || Date.now(),
            title: notif.title || 'Thông báo mới',
            content: notif.content || '',
            type: notif.type || 'order',
            orderCode: notif.orderCode || '',
            isRead: false,
            createdAt: notif.createdAt || new Date().toISOString(),
          });
        }
        updateNotifBellBadge();
        renderAdminNotifications();
      }

      function handleNewOrder(order) {
        if (!order?.id) return;
        const orderId = String(order.id).replace(/^#/, '');
        const exists = data.orders.some((item) => String(item.id).replace(/^#/, '') === orderId);
        if (!exists) {
          data.orders.unshift(toOrderRecord(order));
          saveRealtimeSnapshot();
          renderAll();
        }

        // 1. Play chime sound
        playNewOrderSound();

        // 2. Increment sidebar unread badge
        unreadOrdersBadgeCount++;
        updateOrdersNavBadge();

        // 3. Show rich notification toast card
        showOrderToast(order);

        // 4. Register notification if not present
        handleIncomingNotification({
          id: `order_${orderId}_${Date.now()}`,
          title: 'Đơn hàng mới',
          content: `Khách hàng ${order.recipient?.name || order.customer || 'Khách'} vừa đặt đơn #${orderId} (${money(order.total || 0)}).`,
          type: 'order',
          orderCode: orderId,
          createdAt: order.createdAt || new Date().toISOString(),
        });
      }

      function handleOrderUpdate(update) {
        const code = String(update?.orderCode || update?.id || '').replace(/^#/, '');
        if (!code) return;
        const rawMap = {
          pending: 'Chờ xác nhận',
          awaiting_payment: 'Chờ thanh toán',
          preparing: 'Đang chuẩn bị',
          delivering: 'Đang giao',
          delivered: 'Đã giao',
          completed: 'Đã giao',
          cancelled: 'Đã hủy'
        };
        const raw = String(update.status || update.order_status || '').toLowerCase();
        const displayStatus = update.displayStatus || rawMap[raw] || update.status || '';
        if (!displayStatus) return;

        const record = data.orders.find((item) => String(item.id).replace(/^#/, '') === code);
        if (!record) {
          refreshOrders();
          return;
        }
        if (record.status === displayStatus && (!update.paymentStatus || record.paymentStatus === update.paymentStatus)) return;
        record.status = displayStatus;
        record.statusText = displayStatus;
        if (update.paymentStatus === 'completed') {
          record.paymentStatus = 'completed';
          record.paymentLabel = 'Đã thanh toán';
        }
        saveRealtimeSnapshot();
        renderAll();
        showToast(`Đơn #${code} đã cập nhật: ${displayStatus}.`);
      }

      function handlePaymentUpdate(update) {
        if (!update?.orderCode) return;
        const record = data.orders.find((item) => String(item.id).replace(/^#/, '') === String(update.orderCode));
        if (!record) {
          refreshOrders();
          return;
        }
        record.paymentStatus = update.paymentStatus;
        record.paymentLabel = update.paymentStatus === 'completed'
          ? 'Đã thanh toán'
          : record.paymentMethod === 'momo' ? 'Chờ MoMo' : 'Chờ QR';
        saveRealtimeSnapshot();
        renderAll();
        showToast(update.paymentStatus === 'completed'
          ? `Đơn ${update.orderCode} đã thanh toán thành công.`
          : `Thanh toán đơn ${update.orderCode} chưa thành công.`,
          update.paymentStatus !== 'completed');
      }

      async function persist(collection, actionType = 'save', targetRecord = null) {
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
          const currentRecord = targetRecord || (editingId ? data[collection].find(item => item.id === editingId) : data[collection][0]);

          if (collection === 'products') {
            if (actionType === 'delete') {
              const numId = parseInt(targetRecord?.id) || 0;
              if (numId > 0) {
                await fetch('api.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'delete_product', id: numId })
                });
              }
            } else if (currentRecord) {
              const numId = parseInt(currentRecord.id) || 0;
              await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'save_product',
                  id: numId,
                  name: currentRecord.name,
                  price: currentRecord.price,
                  categoryId: currentRecord.categoryId || 1,
                  image: currentRecord.image,
                  isAvailable: currentRecord.status === 'Đang bán' ? 1 : 0,
                  options: currentRecord.options || { sizes: currentRecord.sizes || [], toppings: currentRecord.toppings || [], attributes: currentRecord.attributes || [] }
                })
              });
            }
          } else if (collection === 'categories') {
            if (actionType === 'delete') {
              const numId = parseInt(targetRecord?.id) || 0;
              if (numId > 0) {
                await fetch('api.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'delete_category', id: numId })
                });
              }
            } else if (currentRecord) {
              const numId = parseInt(currentRecord.id) || 0;
              await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'save_category',
                  id: numId,
                  name: currentRecord.name,
                  icon: currentRecord.icon || '🍔'
                })
              });
            }
          } else if (collection === 'orders') {
            if (currentRecord) {
              const cleanCode = String(currentRecord.orderNo || currentRecord.id).replace(/^#/, '').trim();
              const statusMapReverse = {
                'Chờ xác nhận': 'pending',
                'Chờ thanh toán': 'awaiting_payment',
                'Đang chuẩn bị': 'preparing',
                'Đang giao': 'delivering',
                'Đã giao': 'delivered',
                'Đã hủy': 'cancelled'
              };
              const rawStatus = statusMapReverse[currentRecord.status] || currentRecord.status;
              await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'update_order_status',
                  orderCode: cleanCode,
                  status: rawStatus
                })
              });

              // Bắn broadcast trực tiếp tới các tab khách hàng ngay lập tức
              try {
                const channel = new BroadcastChannel('fastfood_realtime');
                channel.postMessage({
                  type: 'order:updated',
                  data: {
                    id: cleanCode,
                    orderCode: cleanCode,
                    orderNo: '#' + cleanCode,
                    status: rawStatus,
                    order_status: rawStatus
                  }
                });
                channel.postMessage({ type: 'content:updated', collection: 'orders' });
              } catch(e) {}
            }
          } else if (collection === 'users') {
            if (currentRecord) {
              const numId = parseInt(currentRecord.id) || 0;
              const roleMapReverse = {
                'Quản trị viên': 1,
                'Khách hàng': 2,
                'Chủ nhà hàng': 3,
                'Tài xế': 4,
                'Nhân viên hỗ trợ': 5
              };
              const roleId = roleMapReverse[currentRecord.role] || currentRecord.roleId || 2;
              const statusVal = currentRecord.status === 'Đang hoạt động' ? 'active' : 'inactive';
              await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'save_user',
                  id: numId,
                  roleId: roleId,
                  status: statusVal
                })
              });
            }
          }

          try {
            const channel = new BroadcastChannel('fastfood_realtime');
            channel.postMessage({ type: 'content:updated', collection });
          } catch(e) {}

          return true;
        } catch (error) {
          console.warn('[Persist] Error:', error);
          return true;
        }
      }

      function money(amount) {
        return `${new Intl.NumberFormat('vi-VN').format(Number(amount) || 0)} đ`;
      }

      function statusClass(status) {
        const value = String(status || '').toLocaleLowerCase('vi');
        if (value.includes('đang giao')) return 'status-shipping';
        if (value.includes('chuẩn bị') || value.includes('chờ xác nhận')) return 'status-processing';
        if (value.includes('đã giao')) return 'status-delivered';
        if (value.includes('hủy') || value.includes('không hoạt động') || value.includes('đã khóa') || value.includes('tạm hết') || value.includes('đã ẩn')) return 'status-inactive';
        return 'status-active';
      }

      function isCancelledOrder(order) {
        const status = String(order.status || order.statusText || '').toLocaleLowerCase('vi');
        return status === 'cancelled' || status.includes('hủy');
      }

      function orderDateKey(order) {
        const value = String(order.createdAt || order.date || '');
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return vietnamDateKey(date);
      }

      function vietnamDateKey(date) {
        const parts = new Intl.DateTimeFormat('en-CA', {
          timeZone: 'Asia/Ho_Chi_Minh',
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        }).formatToParts(date).reduce((result, part) => {
          if (part.type !== 'literal') result[part.type] = part.value;
          return result;
        }, {});
        return `${parts.year}-${parts.month}-${parts.day}`;
      }

      let revenueTimeRange = 7;

      function getVietnamHour(order) {
        const val = String(order.createdAt || order.date || '');
        const date = new Date(val);
        if (Number.isNaN(date.getTime())) return 12;
        const parts = new Intl.DateTimeFormat('en-US', {
          timeZone: 'Asia/Ho_Chi_Minh',
          hour: 'numeric',
          hour12: false,
        }).formatToParts(date);
        const hourPart = parts.find((p) => p.type === 'hour');
        return hourPart ? parseInt(hourPart.value, 10) : date.getHours();
      }

      function getLastSevenDays() {
        const [year, month, day] = vietnamDateKey(new Date()).split('-').map(Number);
        const today = new Date(Date.UTC(year, month - 1, day));
        return Array.from({ length: 7 }, (_, index) => {
          const date = new Date(today.getTime() - (6 - index) * 24 * 60 * 60 * 1000);
          return {
            date,
            key: date.toISOString().slice(0, 10),
            label: new Intl.DateTimeFormat('vi-VN', {
              timeZone: 'UTC',
              day: '2-digit',
              month: '2-digit',
            }).format(date),
          };
        });
      }

      function getRevenueSlots(range) {
        if (range === 1) {
          const slotLabels = ['00:00', '03:00', '06:00', '09:00', '12:00', '15:00', '18:00', '21:00'];
          return slotLabels.map((label, index) => {
            const nextHour = (index + 1) * 3;
            const tooltipLabel = `${label} - ${String(nextHour).padStart(2, '0')}:00`;
            return {
              key: `slot-${index}`,
              slotIndex: index,
              label,
              tooltipLabel,
              showLabel: true,
              revenue: 0,
              orders: 0,
            };
          });
        }

        const [year, month, day] = vietnamDateKey(new Date()).split('-').map(Number);
        const today = new Date(Date.UTC(year, month - 1, day));
        const count = range === 30 ? 30 : 7;
        return Array.from({ length: count }, (_, index) => {
          const date = new Date(today.getTime() - (count - 1 - index) * 24 * 60 * 60 * 1000);
          const key = date.toISOString().slice(0, 10);
          const label = new Intl.DateTimeFormat('vi-VN', {
            timeZone: 'UTC',
            day: '2-digit',
            month: '2-digit',
          }).format(date);
          return {
            key,
            label,
            tooltipLabel: label,
            showLabel: count === 7 ? true : ((index % 5 === 0) || (index === count - 1)),
            revenue: 0,
            orders: 0,
          };
        });
      }

      function compactMoney(amount) {
        return `${new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(amount) || 0)} đ`;
      }

      function renderSalesCharts() {
        const slots = getRevenueSlots(revenueTimeRange);
        const salesOrders = data.orders.filter((order) => !isCancelledOrder(order));
        let matchingOrders = [];

        if (revenueTimeRange === 1) {
          const todayKey = vietnamDateKey(new Date());
          matchingOrders = salesOrders.filter((order) => orderDateKey(order) === todayKey);
          matchingOrders.forEach((order) => {
            const hour = getVietnamHour(order);
            const slotIndex = Math.min(7, Math.max(0, Math.floor(hour / 3)));
            if (slots[slotIndex]) {
              slots[slotIndex].orders += 1;
              slots[slotIndex].revenue += Math.max(0, Number(order.total) || 0);
            }
          });
        } else {
          const slotByKey = new Map(slots.map((s) => [s.key, s]));
          matchingOrders = salesOrders.filter((order) => slotByKey.has(orderDateKey(order)));
          matchingOrders.forEach((order) => {
            const slot = slotByKey.get(orderDateKey(order));
            if (slot) {
              slot.orders += 1;
              slot.revenue += Math.max(0, Number(order.total) || 0);
            }
          });
        }

        const rangeConfigs = {
          1: {
            title: 'Doanh thu hôm nay (1 ngày)',
            caption: 'Tổng giá trị đơn hàng chưa bị hủy theo từng khung giờ hôm nay',
            emptyRevenue: 'Chưa có đơn hàng nào trong ngày hôm nay.',
            donutCaption: 'Phân bổ theo sản phẩm trong ngày hôm nay',
            donutEmpty: 'Chưa có dữ liệu sản phẩm trong hôm nay.',
            donutAria: 'Tỉ trọng doanh thu sản phẩm hôm nay',
          },
          7: {
            title: 'Doanh thu 7 ngày gần nhất',
            caption: 'Tổng giá trị đơn hàng chưa bị hủy theo từng ngày',
            emptyRevenue: 'Chưa có đơn hàng trong 7 ngày gần nhất.',
            donutCaption: 'Phân bổ theo sản phẩm trên các đơn hàng 7 ngày',
            donutEmpty: 'Chưa có dữ liệu sản phẩm trong 7 ngày gần nhất.',
            donutAria: 'Tỉ trọng doanh thu sản phẩm trong 7 ngày',
          },
          30: {
            title: 'Doanh thu 1 tháng (30 ngày gần nhất)',
            caption: 'Tổng giá trị đơn hàng chưa bị hủy trong 30 ngày qua',
            emptyRevenue: 'Chưa có đơn hàng trong 30 ngày gần nhất.',
            donutCaption: 'Phân bổ theo sản phẩm trên các đơn hàng 30 ngày qua',
            donutEmpty: 'Chưa có dữ liệu sản phẩm trong 30 ngày gần nhất.',
            donutAria: 'Tỉ trọng doanh thu sản phẩm trong 30 ngày qua',
          },
        };

        const config = rangeConfigs[revenueTimeRange] || rangeConfigs[7];
        const headingEl = document.getElementById('revenue-chart-heading');
        const captionEl = document.getElementById('revenue-chart-caption');
        const emptyEl = document.getElementById('revenue-empty');
        const donutCaptionEl = document.getElementById('donut-chart-caption');
        const emptyDonut = document.getElementById('donut-empty');

        if (headingEl) headingEl.textContent = config.title;
        if (captionEl) captionEl.textContent = config.caption;
        if (emptyEl) emptyEl.textContent = config.emptyRevenue;
        if (donutCaptionEl) donutCaptionEl.textContent = config.donutCaption;
        if (emptyDonut) emptyDonut.textContent = config.donutEmpty;

        const hasRecentOrders = slots.some((s) => s.orders > 0);
        const chart = document.getElementById('revenue-chart');
        emptyEl.hidden = hasRecentOrders;
        chart.hidden = !hasRecentOrders;

        if (hasRecentOrders) {
          const chartTop = 24;
          const chartBottom = 214;
          const chartLeft = 72;
          const chartRight = 742;
          const maxRevenue = Math.max(100000, Math.ceil(Math.max(...slots.map((s) => s.revenue)) / 50000) * 50000);
          const points = slots.map((day, index) => ({
            ...day,
            x: chartLeft + index * (chartRight - chartLeft) / (slots.length - 1),
            y: chartBottom - day.revenue / maxRevenue * (chartBottom - chartTop),
          }));
          const linePath = points.slice(1).reduce((path, point, index) => {
            const previous = points[index];
            const middleX = (previous.x + point.x) / 2;
            return `${path} C${middleX.toFixed(1)},${previous.y.toFixed(1)} ${middleX.toFixed(1)},${point.y.toFixed(1)} ${point.x.toFixed(1)},${point.y.toFixed(1)}`;
          }, `M${points[0].x.toFixed(1)},${points[0].y.toFixed(1)}`);
          const areaPath = `${linePath} L${chartRight},${chartBottom} L${chartLeft},${chartBottom} Z`;
          const grid = Array.from({ length: 5 }, (_, index) => {
            const y = chartTop + index * (chartBottom - chartTop) / 4;
            const value = maxRevenue * (4 - index) / 4;
            return `<line class="chart-grid" x1="${chartLeft}" y1="${y.toFixed(1)}" x2="${chartRight}" y2="${y.toFixed(1)}"></line><text x="${chartLeft - 12}" y="${(y + 3).toFixed(1)}" text-anchor="end">${escapeHtml(compactMoney(value))}</text>`;
          }).join('');
          const labels = points.map((point) => point.showLabel !== false ? `<text x="${point.x.toFixed(1)}" y="246" text-anchor="middle">${escapeHtml(point.label)}</text>` : '').join('');
          const markers = points.map((point, index) => (
            `<g class="chart-point-group"><line class="chart-guide" x1="${point.x.toFixed(1)}" y1="${chartTop}" x2="${point.x.toFixed(1)}" y2="${chartBottom}"></line><circle class="chart-point-halo" cx="${point.x.toFixed(1)}" cy="${point.y.toFixed(1)}" r="${points.length > 15 ? 5 : (index === points.length - 1 ? 9 : 7)}"></circle><circle class="chart-point" cx="${point.x.toFixed(1)}" cy="${point.y.toFixed(1)}" r="${points.length > 15 ? 2.8 : (index === points.length - 1 ? 4.5 : 3.5)}"><title>${escapeHtml(point.tooltipLabel || point.label)}: ${escapeHtml(money(point.revenue))} · ${point.orders} đơn</title></circle></g>`
          )).join('');
          chart.innerHTML = `<title id="revenue-chart-title">${escapeHtml(config.title)}</title><desc id="revenue-chart-description">Biểu đồ đường doanh thu, tổng ${escapeHtml(money(slots.reduce((sum, day) => sum + day.revenue, 0)))} từ ${slots.reduce((sum, day) => sum + day.orders, 0)} đơn không bị hủy.</desc><defs><linearGradient id="sales-area-gradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#f97316" stop-opacity=".19"></stop><stop offset="100%" stop-color="#f97316" stop-opacity=".01"></stop></linearGradient><linearGradient id="sales-line-gradient" x1="0" x2="1" y1="0" y2="0"><stop offset="0%" stop-color="#fb923c"></stop><stop offset="100%" stop-color="#ea580c"></stop></linearGradient></defs>${grid}<line class="chart-axis" x1="${chartLeft}" y1="${chartBottom}" x2="${chartRight}" y2="${chartBottom}"></line><path class="chart-area" d="${areaPath}"></path><path class="chart-line" d="${linePath}"></path>${markers}${labels}`;
        }
        document.getElementById('chart-total-revenue').textContent = compactMoney(slots.reduce((sum, day) => sum + day.revenue, 0));
        document.getElementById('chart-total-orders').textContent = new Intl.NumberFormat('vi-VN').format(slots.reduce((sum, day) => sum + day.orders, 0));

        const productTotals = new Map();
        matchingOrders.forEach((order) => {
          if (!Array.isArray(order.items)) return;
          order.items.forEach((item) => {
            const name = String(item?.name || '').trim();
            const amount = (Number(item?.price) || 0) * (Number(item?.qty) || 0);
            if (!name || !Number.isFinite(amount) || amount <= 0) return;
            productTotals.set(name, (productTotals.get(name) || 0) + amount);
          });
        });
        const sortedProducts = [...productTotals.entries()].sort((first, second) => second[1] - first[1]);
        const topProducts = sortedProducts.slice(0, 4).map(([name, revenue]) => ({ name, revenue }));
        const otherRevenue = sortedProducts.slice(4).reduce((sum, [, revenue]) => sum + revenue, 0);
        if (otherRevenue > 0) topProducts.push({ name: 'Sản phẩm khác', revenue: otherRevenue });

        const totalProductRevenue = topProducts.reduce((sum, product) => sum + product.revenue, 0);
        const donut = document.getElementById('sales-donut');
        const legend = document.getElementById('donut-legend');
        const palette = ['#f97316', '#fb923c', '#fdba74', '#fed7aa', '#ffedd5'];
        document.getElementById('donut-total').textContent = compactMoney(totalProductRevenue);
        emptyDonut.hidden = topProducts.length > 0;
        donut.hidden = topProducts.length === 0;
        legend.hidden = topProducts.length === 0;
        if (!topProducts.length) {
          donut.style.background = '#f2f2f2';
          legend.innerHTML = '';
          donut.setAttribute('aria-label', config.donutEmpty);
          return;
        }

        let progress = 0;
        const slices = topProducts.map((product, index) => {
          const start = progress;
          progress += product.revenue / totalProductRevenue * 100;
          return `${palette[index]} ${start.toFixed(2)}% ${progress.toFixed(2)}%`;
        });
        donut.style.background = `conic-gradient(${slices.join(', ')})`;
        donut.setAttribute('aria-label', `${config.donutAria}: ${topProducts.length} nhóm sản phẩm, tổng ${money(totalProductRevenue)}`);
        legend.innerHTML = topProducts.map((product, index) => {
          const share = Math.round(product.revenue / totalProductRevenue * 100);
          return `<li><span class="legend-swatch" style="background-color:${palette[index]}" aria-hidden="true"></span><span class="legend-name" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</span><span class="legend-value">${share}% · ${escapeHtml(compactMoney(product.revenue))}</span></li>`;
        }).join('');
      }

      document.getElementById('revenue-time-select')?.addEventListener('change', (event) => {
        revenueTimeRange = Number(event.target.value) || 7;
        renderSalesCharts();
      });

      function renderStats() {
        document.getElementById('stat-orders').textContent = data.orders.length;
        document.getElementById('stat-revenue').textContent = money(data.orders.filter((order) => !isCancelledOrder(order)).reduce((sum, order) => sum + Number(order.total || 0), 0));
        document.getElementById('stat-products').textContent = data.products.length;
        document.getElementById('stat-users').textContent = data.users.length;
      }

      function filteredRecords() {
        const collection = sections[activeSection].collection;
        return data[collection].filter((record) => {
          const matchesSearch = !searchTerm || Object.values(record).some((value) => String(value).toLocaleLowerCase('vi').includes(searchTerm));
          const matchesStatus = !statusFilter || record.status === statusFilter;
          return matchesSearch && matchesStatus;
        });
      }

      function renderFilter() {
        const collection = sections[activeSection].collection;
        const options = [...new Set(data[collection].map((record) => record.status).filter(Boolean))];
        filterSelect.innerHTML = '<option value="">Tất cả trạng thái</option>';
        options.forEach((status) => {
          const option = document.createElement('option');
          option.value = status;
          option.textContent = status;
          filterSelect.appendChild(option);
        });
        filterSelect.value = statusFilter;
      }

      function renderTable() {
        const section = sections[activeSection];
        const collection = section.collection;
        const schema = schemas[collection];
        const records = filteredRecords();
        const pages = Math.max(1, Math.ceil(records.length / pageSize));
        currentPage = Math.min(currentPage, pages);
        const first = (currentPage - 1) * pageSize;
        const visibleRecords = records.slice(first, first + pageSize);
        const columns = schema.columns;

        tableHead.innerHTML = columns.map((column) => `<th scope="col">${escapeHtml(column.label)}</th>`).join('') + '<th scope="col" style="text-align:right">Thao tác</th>';
        if (!visibleRecords.length) {
          tableBody.innerHTML = `<tr><td class="empty-state" colspan="${columns.length + 1}">Không tìm thấy dữ liệu phù hợp.</td></tr>`;
        } else {
          tableBody.innerHTML = visibleRecords.map((record) => {
            const cells = columns.map((column) => {
              const value = record[column.key];
              if (column.key === 'status') return `<td><span class="status-badge ${statusClass(value)}">${escapeHtml(value)}</span></td>`;
              if (column.key === 'date' && record.createdAt) return `<td>${escapeHtml(formatVietnamDateTime(record.createdAt))}</td>`;
              if (column.key === 'toppings') {
                const sList = Array.isArray(record.sizes) ? record.sizes : (record.options?.sizes || []);
                const tList = Array.isArray(record.toppings) ? record.toppings : (record.options?.toppings || []);
                const aList = Array.isArray(record.attributes) ? record.attributes : (record.options?.attributes || []);
                const parts = [];
                if (sList.length) parts.push(`📏 ${sList.length} size`);
                if (tList.length) parts.push(`✨ ${tList.length} topping`);
                if (aList.length) parts.push(`🌶️ ${aList.length} vị`);
                const label = parts.length ? parts.join(' • ') : '0 tùy chọn';
                return `<td><span class="status-badge" style="background:#FFF7ED; color:#EA580C; font-weight:700; font-size:11px;">${escapeHtml(label)}</span></td>`;
              }
              if (column.key === 'price' || column.key === 'total') return `<td>${escapeHtml(money(value))}</td>`;
              if (column.key === 'image' && value) {
                const imageUrl = String(value).trim();
                const safeImageUrl = /^https?:\/\/\S+$/i.test(imageUrl) || /^\/assets\/uploads\/[a-zA-Z0-9._-]+$/.test(imageUrl);
                return `<td>${safeImageUrl ? `<a class="product-image-cell" href="${escapeHtml(imageUrl)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(imageUrl)}" alt="" loading="lazy"><span>${escapeHtml(imageUrl)}</span></a>` : escapeHtml(imageUrl)}</td>`;
              }
              if (column.key === 'url' && value) {
                const url = String(value).trim();
                const safeUrl = /^https?:\/\/\S+$/i.test(url) || /^(\/(?!\/)|\.{1,2}\/)\S*$/.test(url);
                return `<td>${safeUrl ? `<a href="${escapeHtml(url)}" ${/^https?:\/\//i.test(url) ? 'target="_blank" rel="noopener noreferrer"' : ''}>${escapeHtml(url)}</a>` : escapeHtml(url)}</td>`;
              }
              if (column.key === 'orderNo') return `<td>${escapeHtml(value)}<span class="cell-sub">FASTFOOD</span></td>`;
              if (column.key === 'name' || column.key === 'customer') return `<td>${escapeHtml(value)}</td>`;
              return `<td>${escapeHtml(value)}</td>`;
            }).join('');
            const orderActions = collection === 'orders'
              ? `<button class="action-button" type="button" data-action="edit" data-id="${escapeHtml(record.id)}" aria-label="Cập nhật ${escapeHtml(record.orderNo || '')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 5 5M4 20l4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11z"/></svg>Cập nhật</button>`
              : (collection === 'users' && !isSuperAdmin
                ? `<span class="status-pill status-active" style="font-size:11px; padding:4px 8px; color:var(--muted); background:#f1f5f9;">Chỉ xem</span>`
                : `<button class="action-button" type="button" data-action="edit" data-id="${escapeHtml(record.id)}" aria-label="Sửa ${escapeHtml(record.name || record.email || '')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 5 5M4 20l4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11z"/></svg>Sửa</button><button class="action-button delete" type="button" data-action="delete" data-id="${escapeHtml(record.id)}" aria-label="Xóa ${escapeHtml(record.name || record.email || '')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>Xóa</button>`);
            return `<tr>${cells}<td><div class="row-actions">${orderActions}</div></td></tr>`;
          }).join('');
        }

        const from = records.length ? first + 1 : 0;
        const to = Math.min(first + pageSize, records.length);
        document.getElementById('result-count').textContent = `Hiển thị ${from}–${to} trong tổng số ${records.length} ${schema.singular}`;
        renderPagination(pages);
      }

      function renderPagination(pages) {
        const pagination = document.getElementById('pagination');
        const buttons = [`<button class="page-button" type="button" data-page="${currentPage - 1}" aria-label="Trang trước" ${currentPage === 1 ? 'disabled' : ''}>‹</button>`];
        for (let page = 1; page <= pages; page += 1) {
          if (pages > 5 && page > 2 && page < pages - 1 && Math.abs(page - currentPage) > 1) {
            if (page === 3 || page === pages - 2) buttons.push('<span class="pagination-ellipsis" aria-hidden="true">…</span>');
            continue;
          }
          buttons.push(`<button class="page-button${page === currentPage ? ' current' : ''}" type="button" data-page="${page}" aria-label="Trang ${page}" ${page === currentPage ? 'aria-current="page"' : ''}>${page}</button>`);
        }
        buttons.push(`<button class="page-button" type="button" data-page="${currentPage + 1}" aria-label="Trang sau" ${currentPage === pages ? 'disabled' : ''}>›</button>`);
        pagination.innerHTML = buttons.join('');
      }

      function renderSectionPurpose(section) {
        const recentDayKeys = new Set(getLastSevenDays().map((day) => day.key));
        const values = {
          dashboard: data.orders.filter((order) => recentDayKeys.has(orderDateKey(order))).length,
          categories: data.categories.filter((record) => record.status === 'Đang hoạt động').length,
          products: data.products.filter((record) => record.status === 'Đang bán').length,
          restaurants: data.restaurants.filter((record) => record.status === 'Đang hoạt động').length,
          orders: data.orders.filter((order) => order.status === 'Chờ xác nhận' || order.status === 'pending').length,
          users: data.users.filter((record) => record.status === 'Đang hoạt động').length,
        };
        document.getElementById('section-purpose-text').textContent = section.goal;
        document.getElementById('section-purpose-value').textContent = new Intl.NumberFormat('vi-VN').format(values[activeSection] || 0);
        document.getElementById('section-purpose-caption').textContent = section.metricLabel;
      }

      function renderSection() {
        const section = sections[activeSection];
        const addButton = document.getElementById('add-button');
        if (activeSection === 'messages' || activeSection === 'reviews' || activeSection === 'ai-bot' || activeSection === 'bestseller' || activeSection === 'banners' || activeSection === 'footer-settings' || activeSection === 'membership' || activeSection === 'vouchers') {
          const isMessages = activeSection === 'messages';
          const isReviews = activeSection === 'reviews';
          const isAiBot = activeSection === 'ai-bot';
          const isBestseller = activeSection === 'bestseller';
          const isBanners = activeSection === 'banners';
          const isFooterSettings = activeSection === 'footer-settings';
          const isMembership = activeSection === 'membership';
          const isVouchers = activeSection === 'vouchers';
          let sectionTitle = 'Tin nhắn khách hàng';
          let sectionDesc = 'Theo dõi và trả lời tin nhắn hỗ trợ từ khách hàng.';
          if (isReviews) {
            sectionTitle = 'Đánh giá khách hàng';
            sectionDesc = 'Nhận thông báo tức thì và theo dõi nhận xét của khách sau khi hoàn thành đơn.';
          } else if (isAiBot) {
            sectionTitle = 'Trợ lý AI Chatbot Quản Trị';
            sectionDesc = 'Trợ lý ảo thông minh giải đáp nghiệp vụ, tư vấn doanh thu & hỗ trợ vận hành FASTFOOD.';
          } else if (isBestseller) {
            sectionTitle = 'Cài Đặt Best Seller & Giờ Vàng';
            sectionDesc = 'Tùy chỉnh thời gian đếm ngược Flash Deal và chọn danh sách món ăn bán chạy nhất.';
          } else if (isBanners) {
            sectionTitle = 'Quản Lý Banner & Hero Slider';
            sectionDesc = 'Tùy chỉnh các hình ảnh, nội dung banner và thời gian tự động chuyển slide (1-2s).';
          } else if (isFooterSettings) {
            sectionTitle = 'Cài Đặt Chân Trang (Footer)';
            sectionDesc = 'Chỉnh sửa thông tin liên hệ, hotline, email, địa chỉ và các liên kết hiển thị ở chân trang.';
          } else if (isMembership) {
            sectionTitle = 'Hạng Thành Viên & Điểm Thưởng';
            sectionDesc = 'Quản lý danh sách thành viên, xem lịch sử giao dịch và cấu hình tỷ lệ quy đổi điểm.';
          } else if (isVouchers) {
            sectionTitle = 'Quản Lý Voucher & Mã Khuyến Mãi';
            sectionDesc = 'Tạo mới, phân loại 4 hình thức ưu đãi, thiết lập điều kiện hạng TV và theo dõi hiệu quả sử dụng voucher.';
          }
          document.getElementById('breadcrumb').textContent = isAiBot ? 'Trợ lý AI Chatbot' : sectionTitle;
          document.getElementById('page-title').textContent = sectionTitle;
          document.getElementById('page-description').textContent = sectionDesc;
          addButton.hidden = true;
          addButton.style.display = 'none';
          document.getElementById('dashboard-stats').hidden = true;
          document.getElementById('section-purpose').hidden = true;
          document.getElementById('sales-analytics').hidden = true;
          tablePanel.hidden = true;
          chatInbox.hidden = !isMessages;
          reviewsSection.hidden = !isReviews;
          if (aiBotSection) aiBotSection.hidden = !isAiBot;
          if (bestsellerSection) bestsellerSection.hidden = !isBestseller;
          if (bannersSection) bannersSection.hidden = !isBanners;
          if (footerSettingsSection) footerSettingsSection.hidden = !isFooterSettings;
          if (membershipSection) membershipSection.hidden = !isMembership;
          const vouchersSection = document.getElementById('vouchers-section');
          if (vouchersSection) vouchersSection.hidden = !isVouchers;
          window.adminChatInbox?.setActive(isMessages);
          if (isReviews) {
            clearReviewsBadge();
            renderOrderReviews(renderedReviews);
            loadOrderReviews({ silent: renderedReviews.length > 0 });
          }
          if (isBanners) {
            loadAdminBanners();
          }
          if (isMembership && !membershipLoaded) {
            loadMembershipData();
          }
          if (isVouchers && !vouchersLoaded) {
            loadAdminVouchers();
          }
          return;
        }
        chatInbox.hidden = true;
        reviewsSection.hidden = true;
        if (aiBotSection) aiBotSection.hidden = true;
        if (bestsellerSection) bestsellerSection.hidden = true;
        if (bannersSection) bannersSection.hidden = true;
        if (footerSettingsSection) footerSettingsSection.hidden = true;
        if (membershipSection) membershipSection.hidden = true;
        const vouchersSection = document.getElementById('vouchers-section');
        if (vouchersSection) vouchersSection.hidden = true;
        tablePanel.hidden = false;
        window.adminChatInbox?.setActive(false);
        const collection = section.collection;
        document.getElementById('breadcrumb').textContent = section.title;
        document.getElementById('page-title').textContent = section.title;
        document.getElementById('page-description').textContent = section.description;
        document.getElementById('table-title').textContent = section.tableTitle;
        document.getElementById('table-subtitle').textContent = section.subtitle;
        document.querySelector('#add-button span').textContent = `Thêm ${schemas[collection].singular}`;
        addButton.hidden = collection === 'orders';
        addButton.style.display = collection === 'orders' ? 'none' : '';
        document.getElementById('dashboard-stats').hidden = activeSection !== 'dashboard';
        document.getElementById('section-purpose').hidden = activeSection !== 'dashboard';
        document.getElementById('sales-analytics').hidden = activeSection !== 'dashboard';
        renderSectionPurpose(section);
        renderFilter();
        renderTable();
      }

      function renderAll() {
        renderStats();
        renderSection();
        renderSalesCharts();
      }

      function openForm(record) {
        const collection = sections[activeSection].collection;
        const schema = schemas[collection];
        editingId = record ? record.id : null;
        document.getElementById('save-button').disabled = false;
        document.getElementById('dialog-title').textContent = `${record ? 'Chỉnh sửa' : 'Thêm'} ${schema.singular}`;
        document.getElementById('dialog-description').textContent = record ? 'Cập nhật thông tin bên dưới.' : 'Điền thông tin để thêm dữ liệu mới.';
        document.getElementById('save-button').textContent = record ? 'Lưu thay đổi' : 'Thêm mới';
        document.getElementById('form-fields').innerHTML = schema.fields.map((field) => {
          const value = record ? (field.key === 'image' ? record.image || '' : record[field.key] ?? '') : (field.type === 'status' ? schema.statusOptions[0] : '');
          const required = field.required ? 'required' : '';
          const inputType = field.type === 'status' ? 'select' : 'input';
          if (inputType === 'select') {
            const options = schema.statusOptions.map((option) => `<option value="${escapeHtml(option)}" ${value === option ? 'selected' : ''}>${escapeHtml(option)}</option>`).join('');
            return `<div class="field"><label for="field-${field.key}">${escapeHtml(field.label)}</label><select id="field-${field.key}" name="${field.key}" ${required}>${options}</select></div>`;
          }
          if (field.type === 'image') {
            return `<div class="field image-field">
              <label for="field-image">URL ảnh món ăn</label>
              <input id="field-image" name="image" type="text" maxlength="500" value="${escapeHtml(value)}" placeholder="https://..." required>
              <div class="image-upload">
                <label for="field-image-file">Hoặc tải ảnh từ thiết bị</label>
                <input id="field-image-file" type="file" accept="image/jpeg,image/png,image/webp">
                <span class="image-upload-hint">Hỗ trợ JPG, PNG, WebP; dung lượng tối đa 5 MB. Ảnh tải lên sẽ thay URL hiện tại.</span>
              </div>
              <img class="image-preview" id="image-preview" alt="Xem trước ảnh món ăn" ${value ? `src="${escapeHtml(value)}"` : 'hidden'}>
            </div>`;
          }
          const type = field.type || 'text';
          const numericAttributes = type === 'number' ? `min="${field.min ?? 0}" step="1"` : '';
          return `<div class="field"><label for="field-${field.key}">${escapeHtml(field.label)}</label><input id="field-${field.key}" name="${field.key}" type="${type}" value="${escapeHtml(value)}" ${numericAttributes} ${required}></div>`;
        }).join('');

        if (collection === 'products') {
          const sizes = Array.isArray(record?.sizes) ? record.sizes : (record?.options?.sizes || []);
          const toppings = Array.isArray(record?.toppings) ? record.toppings : (record?.options?.toppings || []);
          const attributes = Array.isArray(record?.attributes) ? record.attributes : (record?.options?.attributes || []);

          const optionsHtml = `
            <div class="options-master-box" style="margin-top:14px; display:grid; gap:12px;">
              <!-- 1. Quản lý Size -->
              <div class="toppings-editor-box" style="border-color:#bfdbfe; background:#eff6ff;">
                <div class="toppings-editor-header">
                  <strong style="color:#1d4ed8;">📏 1. Kích Cỡ / Khẩu Phần (Biến thể Size)</strong>
                  <button type="button" class="btn-add-topping" id="btn-add-size-row" style="border-color:#2563eb; color:#2563eb;">+ Thêm Size</button>
                </div>
                <p class="toppings-editor-sub">Khách sẽ chọn 1 size khi đặt món (giá cộng thêm sẽ tính vào đơn giá).</p>
                <div class="toppings-rows-list" id="sizes-rows-container">
                  ${sizes.map((s, idx) => `
                    <div class="size-form-row" style="display:grid; grid-template-columns:1fr 110px 32px; gap:6px; align-items:center;">
                      <input type="text" class="size-name-input" placeholder="Tên kích cỡ (vd: Size L (700ml))" value="${escapeHtml(s.name || '')}" required>
                      <div class="topping-price-wrap">
                        <input type="number" class="size-price-input" placeholder="Giá thêm" value="${Number(s.price) || 0}" min="0" step="1000" required>
                        <span>đ</span>
                      </div>
                      <button type="button" class="topping-del-btn" title="Xóa kích cỡ" onclick="this.closest('.size-form-row').remove()">✕</button>
                    </div>
                  `).join('')}
                </div>
              </div>

              <!-- 2. Quản lý Topping -->
              <div class="toppings-editor-box">
                <div class="toppings-editor-header">
                  <strong>✨ 2. Món Ăn Kèm / Topping (Tùy chọn)</strong>
                  <button type="button" class="btn-add-topping" id="btn-add-topping-row">+ Thêm Topping</button>
                </div>
                <p class="toppings-editor-sub">Khách hàng có thể chọn nhiều món ăn kèm hoặc topping này.</p>
                <div class="toppings-rows-list" id="toppings-rows-container">
                  ${toppings.map((t) => `
                    <div class="topping-form-row" style="display:grid; grid-template-columns:1fr 110px 32px; gap:6px; align-items:center;">
                      <input type="text" class="topping-name-input" placeholder="Tên topping (vd: Thêm phô mai)" value="${escapeHtml(t.name || '')}" required>
                      <div class="topping-price-wrap">
                        <input type="number" class="topping-price-input" placeholder="Giá" value="${Number(t.price) || 0}" min="0" step="1000" required>
                        <span>đ</span>
                      </div>
                      <button type="button" class="topping-del-btn" title="Xóa topping" onclick="this.closest('.topping-form-row').remove()">✕</button>
                    </div>
                  `).join('')}
                </div>
              </div>

              <!-- 3. Quản lý Khẩu vị / Thuộc tính -->
              <div class="toppings-editor-box" style="border-color:#bbf7d0; background:#f0fdf4;">
                <div class="toppings-editor-header">
                  <strong style="color:#15803d;">🌶️ 3. Khẩu Vị / Thuộc Tính Tùy Chọn</strong>
                  <button type="button" class="btn-add-topping" id="btn-add-attr-row" style="border-color:#16a34a; color:#16a34a;">+ Thêm Khẩu Vị</button>
                </div>
                <p class="toppings-editor-sub">Ví dụ: Mức độ cay, Lượng đường, Đá, Đế bánh... Các lựa chọn cách nhau bằng dấu phẩy.</p>
                <div class="toppings-rows-list" id="attrs-rows-container">
                  ${attributes.map((a) => {
                    const optStr = Array.isArray(a.options) ? a.options.join(', ') : (a.options || '');
                    return `
                      <div class="attr-form-row" style="display:grid; grid-template-columns:130px 1fr 32px; gap:6px; align-items:center;">
                        <input type="text" class="attr-name-input" placeholder="Tên nhóm (vd: Độ cay)" value="${escapeHtml(a.name || '')}" required>
                        <input type="text" class="attr-opts-input" placeholder="Các lựa chọn (vd: Không cay, Cay vừa)" value="${escapeHtml(optStr)}" required>
                        <button type="button" class="topping-del-btn" title="Xóa khẩu vị" onclick="this.closest('.attr-form-row').remove()">✕</button>
                      </div>
                    `;
                  }).join('')}
                </div>
              </div>
            </div>
          `;
          document.getElementById('form-fields').insertAdjacentHTML('beforeend', optionsHtml);

          // Sự kiện thêm Size
          document.getElementById('btn-add-size-row')?.addEventListener('click', () => {
            const container = document.getElementById('sizes-rows-container');
            if (!container) return;
            const newRow = document.createElement('div');
            newRow.className = 'size-form-row';
            newRow.style = 'display:grid; grid-template-columns:1fr 110px 32px; gap:6px; align-items:center;';
            newRow.innerHTML = `
              <input type="text" class="size-name-input" placeholder="Tên size (vd: Size Lớn)" value="" required>
              <div class="topping-price-wrap">
                <input type="number" class="size-price-input" placeholder="Giá thêm" value="10000" min="0" step="1000" required>
                <span>đ</span>
              </div>
              <button type="button" class="topping-del-btn" title="Xóa" onclick="this.closest('.size-form-row').remove()">✕</button>
            `;
            container.appendChild(newRow);
            newRow.querySelector('.size-name-input')?.focus();
          });

          // Sự kiện thêm Topping
          document.getElementById('btn-add-topping-row')?.addEventListener('click', () => {
            const container = document.getElementById('toppings-rows-container');
            if (!container) return;
            const newRow = document.createElement('div');
            newRow.className = 'topping-form-row';
            newRow.style = 'display:grid; grid-template-columns:1fr 110px 32px; gap:6px; align-items:center;';
            newRow.innerHTML = `
              <input type="text" class="topping-name-input" placeholder="Tên topping (vd: Trân châu...)" value="" required>
              <div class="topping-price-wrap">
                <input type="number" class="topping-price-input" placeholder="Giá" value="10000" min="0" step="1000" required>
                <span>đ</span>
              </div>
              <button type="button" class="topping-del-btn" title="Xóa" onclick="this.closest('.topping-form-row').remove()">✕</button>
            `;
            container.appendChild(newRow);
            newRow.querySelector('.topping-name-input')?.focus();
          });

          // Sự kiện thêm Khẩu vị
          document.getElementById('btn-add-attr-row')?.addEventListener('click', () => {
            const container = document.getElementById('attrs-rows-container');
            if (!container) return;
            const newRow = document.createElement('div');
            newRow.className = 'attr-form-row';
            newRow.style = 'display:grid; grid-template-columns:130px 1fr 32px; gap:6px; align-items:center;';
            newRow.innerHTML = `
              <input type="text" class="attr-name-input" placeholder="Tên nhóm (vd: Độ cay)" value="" required>
              <input type="text" class="attr-opts-input" placeholder="Các lựa chọn (vd: Không cay, Cay vừa)" value="" required>
              <button type="button" class="topping-del-btn" title="Xóa" onclick="this.closest('.attr-form-row').remove()">✕</button>
            `;
            container.appendChild(newRow);
            newRow.querySelector('.attr-name-input')?.focus();
          });
        }

        dialog.showModal();
        const imageUrlInput = document.getElementById('field-image');
        const imageFileInput = document.getElementById('field-image-file');
        const imagePreview = document.getElementById('image-preview');
        if (imagePreviewObjectUrl) {
          URL.revokeObjectURL(imagePreviewObjectUrl);
          imagePreviewObjectUrl = null;
        }
        if (imageUrlInput && imagePreview) {
          imageUrlInput.addEventListener('input', () => {
            if (imageFileInput?.files?.length) imageFileInput.value = '';
            imageUrlInput.required = true;
            if (imagePreviewObjectUrl) {
              URL.revokeObjectURL(imagePreviewObjectUrl);
              imagePreviewObjectUrl = null;
            }
            imagePreview.src = imageUrlInput.value.trim();
            imagePreview.hidden = !imageUrlInput.value.trim();
            imagePreview.classList.remove('image-preview-error');
          });
          imagePreview.addEventListener('error', () => {
            imagePreview.hidden = true;
            imagePreview.classList.add('image-preview-error');
          });
        }
        if (imageFileInput && imagePreview) {
          imageFileInput.addEventListener('change', () => {
            const file = imageFileInput.files?.[0];
            if (!file) {
              imageUrlInput.required = true;
              return;
            }
            imageUrlInput.required = false;
            if (imagePreviewObjectUrl) URL.revokeObjectURL(imagePreviewObjectUrl);
            imagePreviewObjectUrl = URL.createObjectURL(file);
            imagePreview.src = imagePreviewObjectUrl;
            imagePreview.hidden = false;
            imagePreview.classList.remove('image-preview-error');
          });
        }
        const firstInput = dialog.querySelector('input, select');
        if (firstInput) firstInput.focus();
      }

      function closeDialog() {
        dialog.close();
        form.reset();
        editingId = null;
        document.getElementById('save-button').disabled = false;
        document.getElementById('dialog-close').disabled = false;
        document.getElementById('dialog-cancel').disabled = false;
        if (imagePreviewObjectUrl) {
          URL.revokeObjectURL(imagePreviewObjectUrl);
          imagePreviewObjectUrl = null;
        }
      }

      document.getElementById('navigation').addEventListener('click', (event) => {
        const button = event.target.closest('[data-section]');
        if (!button) return;
        activeSection = button.dataset.section;
        if (activeSection === 'orders') {
          unreadOrdersBadgeCount = 0;
          updateOrdersNavBadge();
        }
        currentPage = 1;
        searchTerm = '';
        statusFilter = '';
        document.getElementById('search-input').value = '';
        document.querySelectorAll('.nav-item').forEach((item) => {
          item.classList.toggle('active', item === button);
          if (item === button) item.setAttribute('aria-current', 'page');
          else item.removeAttribute('aria-current');
        });
        renderSection();
        closeMobileMenu();
      });

      notifBellBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleNotifDropdown();
      });

      notifMarkAllBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        markAllAdminNotificationsRead();
      });

      document.addEventListener('click', (event) => {
        const wrapper = document.getElementById('notif-wrapper');
        if (wrapper && !wrapper.contains(event.target)) {
          closeNotifDropdown();
        }
      });

      document.getElementById('search-form').addEventListener('submit', (event) => {
        event.preventDefault();
        if (activeSection === 'messages' || activeSection === 'ai-bot' || activeSection === 'bestseller' || activeSection === 'banners' || activeSection === 'footer-settings' || activeSection === 'membership' || activeSection === 'vouchers') return;
        if (activeSection === 'reviews') {
          reviewSearchTerm = document.getElementById('search-input').value.trim().toLocaleLowerCase('vi');
          renderOrderReviews(renderedReviews);
          return;
        }
        searchTerm = document.getElementById('search-input').value.trim().toLocaleLowerCase('vi');
        currentPage = 1;
        renderTable();
      });
      document.getElementById('search-input').addEventListener('input', (event) => {
        if (activeSection === 'reviews') {
          reviewSearchTerm = event.target.value.trim().toLocaleLowerCase('vi');
          renderOrderReviews(renderedReviews);
          return;
        }
        if (!event.target.value && activeSection !== 'messages' && activeSection !== 'ai-bot' && activeSection !== 'bestseller' && activeSection !== 'banners' && activeSection !== 'footer-settings' && activeSection !== 'membership') {
          searchTerm = '';
          currentPage = 1;
          renderTable();
        }
      });
      filterSelect.addEventListener('change', () => {
        statusFilter = filterSelect.value;
        currentPage = 1;
        renderTable();
      });
      document.getElementById('add-button').addEventListener('click', () => {
        if (sections[activeSection].collection === 'orders') {
          showToast('Đơn hàng chỉ được tạo bởi khách hàng qua quy trình đặt hàng.', true);
          return;
        }
        openForm(null);
      });
      tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const collection = sections[activeSection].collection;
        if (collection === 'orders' && button.dataset.action === 'delete') {
          showToast('Không thể xóa đơn hàng tại đây; hãy cập nhật trạng thái để giữ đồng bộ.', true);
          return;
        }
        const record = data[collection].find((item) => item.id === button.dataset.id);
        if (!record) return;
        if (button.dataset.action === 'edit') {
          openForm(record);
          return;
        }
        if (window.confirm(`Bạn có chắc muốn xóa ${schemas[collection].singular} này không?`)) {
          const previous = data[collection];
          data[collection] = previous.filter((item) => item.id !== record.id);
          if (!await persist(collection, 'delete', record)) {
            data[collection] = previous;
            return;
          }
          renderAll();
          showToast(`Đã xóa ${schemas[collection].singular}.`);
        }
      });
      document.getElementById('pagination').addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        currentPage = Number(button.dataset.page);
        renderTable();
      });
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const collection = sections[activeSection].collection;
        const schema = schemas[collection];
        const wasEditing = Boolean(editingId);
        const saveButton = document.getElementById('save-button');
        const originalButtonText = saveButton.textContent;
        const selectedImage = collection === 'products' ? document.getElementById('field-image-file')?.files?.[0] : null;
        saveButton.disabled = true;
        document.getElementById('dialog-close').disabled = true;
        document.getElementById('dialog-cancel').disabled = true;
        try {
          if (selectedImage) {
            if (selectedImage.size > 5 * 1024 * 1024) throw new Error('Ảnh vượt quá dung lượng tối đa 5 MB.');
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(selectedImage.type)) {
              throw new Error('Vui lòng chọn ảnh JPG, PNG hoặc WebP.');
            }
            saveButton.textContent = 'Đang tải ảnh...';
            const uploadFd = new FormData();
            uploadFd.append('action', 'upload_image');
            uploadFd.append('image', selectedImage);
            const uploadResponse = await fetch('api.php', {
              method: 'POST',
              body: uploadFd
            });
            const uploadResult = await uploadResponse.json();
            if (!uploadResponse.ok || !uploadResult.success || !uploadResult.data?.url) {
              throw new Error(uploadResult.message || 'Không thể tải ảnh lên máy chủ.');
            }
            document.getElementById('field-image').value = uploadResult.data.url;
            saveButton.textContent = originalButtonText;
          }

          const values = Object.fromEntries(new FormData(form).entries());
          if (collection === 'products') {
            // 1. Thu thập Sizes
            const sizeRows = dialog.querySelectorAll('.size-form-row');
            const sizes = [];
            sizeRows.forEach((row, sIdx) => {
              const name = row.querySelector('.size-name-input')?.value.trim();
              const price = Number(row.querySelector('.size-price-input')?.value) || 0;
              if (name) sizes.push({ name, price, is_default: sIdx === 0 });
            });

            // 2. Thu thập Toppings
            const toppingRows = dialog.querySelectorAll('.topping-form-row');
            const toppings = [];
            toppingRows.forEach((row) => {
              const nameInput = row.querySelector('.topping-name-input');
              const priceInput = row.querySelector('.topping-price-input');
              const name = nameInput ? nameInput.value.trim() : '';
              const price = priceInput ? Number(priceInput.value) || 0 : 0;
              if (name) toppings.push({ name, price });
            });

            // 3. Thu thập Attributes / Khẩu vị
            const attrRows = dialog.querySelectorAll('.attr-form-row');
            const attributes = [];
            attrRows.forEach((row) => {
              const name = row.querySelector('.attr-name-input')?.value.trim();
              const rawOpts = row.querySelector('.attr-opts-input')?.value.trim() || '';
              if (name && rawOpts) {
                const optList = rawOpts.split(',').map(s => s.trim()).filter(Boolean);
                if (optList.length) attributes.push({ name, options: optList });
              }
            });

            const options = { sizes, toppings, attributes };
            values.options = options;
            values.sizes = sizes;
            values.toppings = toppings;
            values.attributes = attributes;
          }
          const imageUrl = String(values.image || '').trim();
          if (collection === 'products' && imageUrl
            && !(/^https?:\/\/\S+$/i.test(imageUrl) || /^\/assets\/uploads\/[a-zA-Z0-9._-]+$/.test(imageUrl))) {
            throw new Error('URL ảnh cần bắt đầu bằng http://, https:// hoặc là ảnh đã tải lên.');
          }
          schema.fields.forEach((field) => {
            if (field.type === 'number' && values[field.key] !== '') values[field.key] = Number(values[field.key]);
          });
          const previous = data[collection];
          if (editingId) {
            data[collection] = previous.map((record) => record.id === editingId ? { ...record, ...values } : record);
          } else {
            data[collection] = [{ id: `${collection.slice(0, 3)}-${Date.now()}`, ...values }, ...previous];
          }
          if (!await persist(collection)) {
            data[collection] = previous;
            saveButton.disabled = false;
            saveButton.textContent = originalButtonText;
            document.getElementById('dialog-close').disabled = false;
            document.getElementById('dialog-cancel').disabled = false;
            return;
          }
          closeDialog();
          currentPage = 1;
          renderAll();
          showToast(wasEditing ? `Đã cập nhật ${schema.singular}.` : `Đã thêm ${schema.singular} mới.`);
        } catch (error) {
          showToast(`Không thể lưu sản phẩm: ${error.message}`, true);
          saveButton.disabled = false;
          saveButton.textContent = originalButtonText;
          document.getElementById('dialog-close').disabled = false;
          document.getElementById('dialog-cancel').disabled = false;
        }
      });
      dialog.addEventListener('cancel', (event) => {
        if (document.getElementById('save-button').disabled) event.preventDefault();
      });

      document.getElementById('dialog-close').addEventListener('click', closeDialog);
      document.getElementById('dialog-cancel').addEventListener('click', closeDialog);
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog && !document.getElementById('save-button').disabled) closeDialog();
      });

      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('mobile-backdrop');
      const mobileMenuButton = document.getElementById('mobile-menu');
      const sidebarCloseBtn = document.getElementById('sidebar-close-btn');

      function closeMobileMenu() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
        mobileMenuButton.setAttribute('aria-expanded', 'false');
      }
      mobileMenuButton.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('open');
        backdrop.classList.toggle('open', isOpen);
        mobileMenuButton.setAttribute('aria-expanded', String(isOpen));
      });
      backdrop.addEventListener('click', closeMobileMenu);
      sidebarCloseBtn?.addEventListener('click', closeMobileMenu);

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
          closeMobileMenu();
        }
      });

      window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
          closeMobileMenu();
        }
      });

      // === Draggable Horizontal Sidebar Resizer ===
      const resizer = document.getElementById('sidebar-resizer');
      const mainContent = document.querySelector('.main');
      const DEFAULT_SIDEBAR_WIDTH = 260;
      const MIN_SIDEBAR_WIDTH = 200;
      const MAX_SIDEBAR_WIDTH = 460;

      function applySidebarWidth(width) {
        if (window.innerWidth < 768) {
          sidebar.style.width = '';
          if (mainContent) {
            mainContent.style.marginLeft = '';
            mainContent.style.width = '';
          }
          if (resizer) resizer.style.left = '';
          return;
        }
        sidebar.style.width = width + 'px';
        if (resizer) resizer.style.left = width + 'px';
        if (mainContent) {
          mainContent.style.marginLeft = width + 'px';
          mainContent.style.width = `calc(100% - ${width}px)`;
        }
      }

      // Restore saved width from localStorage on load
      const savedSidebarWidth = localStorage.getItem('fastfood_admin_sidebar_width');
      if (savedSidebarWidth && window.innerWidth >= 768) {
        const parsed = parseInt(savedSidebarWidth, 10);
        if (!isNaN(parsed) && parsed >= MIN_SIDEBAR_WIDTH && parsed <= MAX_SIDEBAR_WIDTH) {
          applySidebarWidth(parsed);
        }
      }

      if (resizer) {
        let isDragging = false;
        let startX = 0;
        let startWidth = 0;

        resizer.addEventListener('mousedown', (e) => {
          if (e.button !== 0) return;
          isDragging = true;
          startX = e.clientX;
          startWidth = sidebar.getBoundingClientRect().width;
          resizer.classList.add('is-dragging');
          document.body.classList.add('is-resizing-sidebar');
          e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
          if (!isDragging) return;
          const deltaX = e.clientX - startX;
          let newWidth = Math.round(startWidth + deltaX);
          if (newWidth < MIN_SIDEBAR_WIDTH) newWidth = MIN_SIDEBAR_WIDTH;
          if (newWidth > MAX_SIDEBAR_WIDTH) newWidth = MAX_SIDEBAR_WIDTH;
          applySidebarWidth(newWidth);
        });

        document.addEventListener('mouseup', () => {
          if (!isDragging) return;
          isDragging = false;
          resizer.classList.remove('is-dragging');
          document.body.classList.remove('is-resizing-sidebar');
          const finalWidth = sidebar.getBoundingClientRect().width;
          localStorage.setItem('fastfood_admin_sidebar_width', Math.round(finalWidth));
        });

        resizer.addEventListener('dblclick', () => {
          applySidebarWidth(DEFAULT_SIDEBAR_WIDTH);
          localStorage.setItem('fastfood_admin_sidebar_width', DEFAULT_SIDEBAR_WIDTH);
        });
      }

      const urlParams = new URLSearchParams(window.location.search);
      const initialSection = urlParams.get('section') || (window.location.hash ? window.location.hash.replace(/^#/, '') : '');
      if (initialSection && (sections[initialSection] || initialSection === 'messages' || initialSection === 'reviews' || initialSection === 'ai-bot' || initialSection === 'bestseller' || initialSection === 'banners' || initialSection === 'footer-settings' || initialSection === 'membership' || initialSection === 'vouchers')) {
        const targetBtn = document.querySelector(`.nav-item[data-section="${initialSection}"]`);
        if (targetBtn) {
          activeSection = initialSection;
          document.querySelectorAll('.nav-item').forEach((item) => {
            item.classList.toggle('active', item === targetBtn);
            if (item === targetBtn) item.setAttribute('aria-current', 'page');
            else item.removeAttribute('aria-current');
          });
        }
      }

      // === Trợ lý AI Co-pilot Chatbot Section Logic ===
      const aiBotMessages = document.getElementById('ai-bot-messages');
      const aiBotForm = document.getElementById('ai-bot-form');
      const aiBotInput = document.getElementById('ai-bot-input');
      const aiBotSendBtn = document.getElementById('ai-bot-send-btn');
      const btnClearAi = document.getElementById('btn-clear-ai-history');

      function formatAiMarkdown(text) {
        if (!text) return '';
        let escaped = escapeHtml(text);
        escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        const lines = escaped.split('\n');
        const formatted = [];
        let inList = false;
        lines.forEach((line) => {
          const trimmed = line.trim();
          if (trimmed.startsWith('- ') || trimmed.startsWith('• ')) {
            if (!inList) {
              formatted.push('<ul>');
              inList = true;
            }
            formatted.push(`<li>${trimmed.replace(/^[-•]\s*/, '')}</li>`);
          } else if (trimmed.startsWith('&gt; ') || trimmed.startsWith('> ')) {
            if (inList) {
              formatted.push('</ul>');
              inList = false;
            }
            formatted.push(`<blockquote>${trimmed.replace(/^(&gt;|>)\s*/, '')}</blockquote>`);
          } else {
            if (inList) {
              formatted.push('</ul>');
              inList = false;
            }
            if (trimmed) {
              formatted.push(`<p>${trimmed}</p>`);
            }
          }
        });
        if (inList) formatted.push('</ul>');
        return formatted.join('');
      }

      const defaultAiWelcomeHtml = `
        <article class="ai-chat-bubble assistant">
          <div class="bubble-header">
            <span class="bubble-avatar">🤖</span>
            <strong>Trợ lý AI FASTFOOD</strong>
            <span class="bubble-time">Sẵn sàng</span>
          </div>
          <div class="bubble-body">
            <p>Xin chào <strong>Quản trị viên</strong>! Tôi là <strong>FASTFOOD AI Co-pilot</strong>, trợ lý ảo thông minh đồng hành cùng bạn trong việc quản lý và phát triển nền tảng đặt đồ ăn.</p>
            <p>Bạn có thể hỏi tôi bất kỳ nghiệp vụ nào:</p>
            <ul>
              <li>📊 <strong>Doanh thu &amp; Vận hành:</strong> Phân tích giờ cao điểm, xu hướng đặt món của các quán Buôn Ma Thuột.</li>
              <li>📋 <strong>Chính sách hệ thống:</strong> Quy tắc tăng 3% phí ship khi hủy đơn, điều kiện áp dụng mã giảm giá.</li>
              <li>✍️ <strong>Soạn thảo nội dung:</strong> Viết tin nhắn CSKH, kịch bản giải quyết khiếu nại, thông báo ưu đãi.</li>
              <li>🍔 <strong>Thực đơn &amp; Quán ăn:</strong> Quản lý danh sách 260 món ăn, giá tiền và tùy chọn topping.</li>
            </ul>
          </div>
          <div class="ai-prompt-suggestions">
            <button type="button" class="prompt-chip" data-prompt="Phân tích tình hình doanh thu và các giờ cao điểm đặt món">📈 Phân tích doanh thu &amp; Giờ cao điểm</button>
            <button type="button" class="prompt-chip" data-prompt="Chính sách phí giao hàng và quy tắc tăng 3% khi khách hủy đơn như thế nào?">📋 Chính sách phí ship &amp; Hủy đơn</button>
            <button type="button" class="prompt-chip" data-prompt="Soạn mẫu thông báo khuyến mãi giảm giá 50K cuối tuần cho khách">✍️ Soạn tin nhắn khuyến mãi cuối tuần</button>
            <button type="button" class="prompt-chip" data-prompt="Hướng dẫn quy trình chuẩn xử lý khi khách hàng đánh giá 1 sao vì đồ ăn giao trễ">🛡️ Cách xử lý khiếu nại khách hàng 1 sao</button>
          </div>
        </article>
      `;

      btnClearAi?.addEventListener('click', () => {
        if (!aiBotMessages) return;
        aiBotMessages.innerHTML = defaultAiWelcomeHtml;
        showToast('Đã bắt đầu phiên làm việc mới với Trợ lý AI.');
      });

      aiBotMessages?.addEventListener('click', (event) => {
        const chip = event.target.closest('.prompt-chip');
        if (!chip) return;
        const promptText = chip.dataset.prompt;
        if (promptText && aiBotInput && aiBotForm) {
          aiBotInput.value = promptText;
          aiBotForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
      });

      aiBotForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const prompt = aiBotInput?.value.trim();
        if (!prompt || !aiBotMessages) return;

        const nowStr = new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(new Date());

        // Render user message
        const userBubble = document.createElement('article');
        userBubble.className = 'ai-chat-bubble user';
        userBubble.innerHTML = `
          <div class="bubble-header">
            <span class="bubble-avatar">👨‍💼</span>
            <strong>Bạn (Admin)</strong>
            <span class="bubble-time">${nowStr}</span>
          </div>
          <div class="bubble-body">
            <p>${escapeHtml(prompt)}</p>
          </div>
        `;
        aiBotMessages.appendChild(userBubble);
        aiBotInput.value = '';
        aiBotMessages.scrollTop = aiBotMessages.scrollHeight;

        // Render thinking indicator
        const thinkingBubble = document.createElement('article');
        thinkingBubble.className = 'ai-chat-bubble assistant';
        thinkingBubble.innerHTML = `
          <div class="bubble-header">
            <span class="bubble-avatar">🤖</span>
            <strong>Trợ lý AI FASTFOOD</strong>
            <span class="bubble-time">Đang suy nghĩ...</span>
          </div>
          <div class="bubble-body">
            <p><em>✨ Đang phân tích dữ liệu và soạn câu trả lời chuyên sâu...</em></p>
          </div>
        `;
        aiBotMessages.appendChild(thinkingBubble);
        aiBotMessages.scrollTop = aiBotMessages.scrollHeight;
        if (aiBotSendBtn) aiBotSendBtn.disabled = true;

        try {
          const response = await fetch('../api/ai-chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: prompt }),
          });
          const result = await response.json();
          thinkingBubble.remove();
          if (!response.ok || !result.success) {
            throw new Error(result.message || 'Không thể nhận phản hồi từ Trợ lý AI.');
          }

          const replyHtml = formatAiMarkdown(result.data?.reply || 'Tôi đã ghi nhận yêu cầu của bạn.');
          const replyBubble = document.createElement('article');
          replyBubble.className = 'ai-chat-bubble assistant';
          replyBubble.innerHTML = `
            <div class="bubble-header">
              <span class="bubble-avatar">🤖</span>
              <strong>Trợ lý AI FASTFOOD</strong>
              <span class="bubble-time">${new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(new Date())}</span>
            </div>
            <div class="bubble-body">
              ${replyHtml}
            </div>
          `;
          aiBotMessages.appendChild(replyBubble);
          aiBotMessages.scrollTop = aiBotMessages.scrollHeight;
        } catch (err) {
          thinkingBubble.remove();
          const errBubble = document.createElement('article');
          errBubble.className = 'ai-chat-bubble assistant';
          errBubble.innerHTML = `
            <div class="bubble-header">
              <span class="bubble-avatar">⚠️</span>
              <strong style="color:#e11d48;">Lỗi Trợ lý AI</strong>
            </div>
            <div class="bubble-body">
              <p style="color:#e11d48;">Rất tiếc: ${escapeHtml(err.message)}</p>
            </div>
          `;
          aiBotMessages.appendChild(errBubble);
          aiBotMessages.scrollTop = aiBotMessages.scrollHeight;
        } finally {
          if (aiBotSendBtn) aiBotSendBtn.disabled = false;
          aiBotInput?.focus();
        }
      });

      // === Best Seller & Flash Deal Management Logic ===
      const bsTitleInput = document.getElementById('bs-cfg-title');
      const bsSubtitleInput = document.getElementById('bs-cfg-subtitle');
      const bsBadgeInput = document.getElementById('bs-cfg-badge');
      const bsTimerEnabled = document.getElementById('bs-cfg-timer-enabled');
      const bsEndTimeInput = document.getElementById('bs-cfg-endtime');
      const bsEndDatePart = document.getElementById('bs-cfg-enddate');
      const bsEndTimePart = document.getElementById('bs-cfg-endtime-part');
      const bsEndTimeGroup = document.getElementById('bs-endtime-group');
      const bsSearchInput = document.getElementById('bs-search-product');

      const prevTitle = document.getElementById('preview-bs-title');
      const prevSubtitle = document.getElementById('preview-bs-subtitle');
      const prevBadge = document.getElementById('preview-bs-badge');
      const prevTimer = document.getElementById('preview-bs-timer');
      const prevHours = document.getElementById('preview-bs-hours');
      const prevMinutes = document.getElementById('preview-bs-minutes');
      const prevSeconds = document.getElementById('preview-bs-seconds');
      const prevProductsEl = document.getElementById('preview-bs-products');
      const prevCount = document.getElementById('preview-selected-count');
      const filterSelectedCount = document.getElementById('bs-filter-selected-count');
      let bsActiveFilter = 'all';

      function syncHiddenEndTimeFromParts() {
        if (!bsEndTimeInput) return;
        const d = bsEndDatePart?.value || '';
        const t = bsEndTimePart?.value || '23:59';
        bsEndTimeInput.value = d ? `${d}T${t}` : '';
        updatePreviewCountdown();
      }

      function updatePreviewCountdown() {
        if (!prevHours || !prevMinutes || !prevSeconds) return;
        const raw = bsEndTimeInput?.value || '';
        if (!raw) {
          prevHours.textContent = '00';
          prevMinutes.textContent = '00';
          prevSeconds.textContent = '00';
          return;
        }
        const targetMs = new Date(raw.replace('T', ' ').replace(/-/g, '/') + ':00').getTime();
        let diff = Number.isFinite(targetMs) ? Math.max(0, Math.floor((targetMs - Date.now()) / 1000)) : 0;
        const hrs = Math.floor(diff / 3600);
        diff %= 3600;
        const mins = Math.floor(diff / 60);
        const secs = diff % 60;
        prevHours.textContent = String(hrs).padStart(2, '0');
        prevMinutes.textContent = String(mins).padStart(2, '0');
        prevSeconds.textContent = String(secs).padStart(2, '0');
      }

      function syncTimerVisibility() {
        const isEnabled = Boolean(bsTimerEnabled?.checked);
        if (bsEndTimeGroup) bsEndTimeGroup.style.display = isEnabled ? 'block' : 'none';
        if (prevTimer) prevTimer.style.display = isEnabled ? 'inline-flex' : 'none';
      }

      function renderPreviewProducts() {
        const checkedBoxes = Array.from(document.querySelectorAll('.bs-prod-checkbox:checked'));
        if (prevCount) prevCount.textContent = checkedBoxes.length;
        if (filterSelectedCount) filterSelectedCount.textContent = checkedBoxes.length;
        if (!prevProductsEl) return;

        if (checkedBoxes.length === 0) {
          prevProductsEl.innerHTML = '<div style="grid-column:1 / -1; background:rgba(255,255,255,0.16); border:1px dashed rgba(255,255,255,0.4); border-radius:10px; padding:14px; text-align:center; font-size:12px; color:#fff;">Chưa chọn món nào — hãy tick chọn món ăn ở mục 3 bên dưới.</div>';
          return;
        }

        const previewItems = checkedBoxes.slice(0, 4).map((cb) => {
          const card = cb.closest('.bs-prod-card');
          const title = card?.dataset.title || '';
          const price = card?.dataset.price || '';
          const img = card?.dataset.img || '';
          return `
            <div class="bs-preview-mini-card">
              <img src="${escapeHtml(img)}" alt="${escapeHtml(title)}">
              <div class="bs-preview-mini-info">
                <strong class="bs-preview-mini-name">${escapeHtml(title)}</strong>
                <span class="bs-preview-mini-price">${escapeHtml(price)}</span>
              </div>
            </div>
          `;
        }).join('');

        const extraNote = checkedBoxes.length > 4
          ? `<div style="grid-column:1 / -1; text-align:right; font-size:11px; color:rgba(255,255,255,0.92); font-weight:600;">+ ${checkedBoxes.length - 4} món khác sẽ hiển thị cùng trên Trang Chủ</div>`
          : '';
        prevProductsEl.innerHTML = previewItems + extraNote;
      }

      function applyProductFilter() {
        const term = (bsSearchInput?.value || '').trim().toLowerCase();
        document.querySelectorAll('.bs-prod-card').forEach(card => {
          const name = card.dataset.name || '';
          const cb = card.querySelector('.bs-prod-checkbox');
          const matchesTerm = !term || name.includes(term);
          const matchesTab = bsActiveFilter !== 'selected' || Boolean(cb?.checked);
          card.style.display = (matchesTerm && matchesTab) ? 'flex' : 'none';
        });
      }

      // Bind input to live preview
      bsTitleInput?.addEventListener('input', () => {
        if (prevTitle) prevTitle.textContent = bsTitleInput.value.trim() || 'Món Ăn Bán Chạy Nhất';
      });
      bsSubtitleInput?.addEventListener('input', () => {
        if (prevSubtitle) prevSubtitle.textContent = bsSubtitleInput.value.trim() || '';
      });
      bsBadgeInput?.addEventListener('input', () => {
        if (!prevBadge) return;
        const val = bsBadgeInput.value.trim();
        prevBadge.textContent = val;
        prevBadge.style.display = val ? 'inline-flex' : 'none';
      });
      bsTimerEnabled?.addEventListener('change', syncTimerVisibility);
      bsEndDatePart?.addEventListener('change', () => {
        document.querySelectorAll('.bs-preset-btn').forEach(b => b.classList.remove('is-active'));
        syncHiddenEndTimeFromParts();
      });
      bsEndTimePart?.addEventListener('change', () => {
        document.querySelectorAll('.bs-preset-btn').forEach(b => b.classList.remove('is-active'));
        syncHiddenEndTimeFromParts();
      });

      // Preset buttons (+6h, +12h, etc.)
      document.querySelectorAll('.bs-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const hours = parseInt(btn.dataset.hours || '24');
          const target = new Date(Date.now() + hours * 3600 * 1000);
          const yyyy = target.getFullYear();
          const mm = String(target.getMonth() + 1).padStart(2, '0');
          const dd = String(target.getDate()).padStart(2, '0');
          const hh = String(target.getHours()).padStart(2, '0');
          const mi = String(target.getMinutes()).padStart(2, '0');
          if (bsEndDatePart) bsEndDatePart.value = `${yyyy}-${mm}-${dd}`;
          if (bsEndTimePart) bsEndTimePart.value = `${hh}:${mi}`;
          if (bsEndTimeInput) bsEndTimeInput.value = `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
          document.querySelectorAll('.bs-preset-btn').forEach(b => b.classList.remove('is-active'));
          btn.classList.add('is-active');
          updatePreviewCountdown();
          showToast(`Đã chỉnh thời gian kết thúc: +${hours} tiếng`, false);
        });
      });

      // Product selection checkboxes
      document.querySelectorAll('.bs-prod-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
          const card = cb.closest('.bs-prod-card');
          if (card) card.classList.toggle('is-selected', cb.checked);
          renderPreviewProducts();
          applyProductFilter();
        });
      });

      // Filter tabs (Tất cả / Đã chọn)
      document.querySelectorAll('.bs-filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
          bsActiveFilter = tab.dataset.bsFilter || 'all';
          document.querySelectorAll('.bs-filter-tab').forEach(t => t.classList.toggle('is-active', t === tab));
          applyProductFilter();
        });
      });

      // "Chọn nhanh 4 món đầu"
      document.getElementById('btn-select-top4')?.addEventListener('click', () => {
        const allBoxes = Array.from(document.querySelectorAll('.bs-prod-checkbox'));
        allBoxes.forEach((cb, idx) => {
          cb.checked = (idx < 4);
          cb.closest('.bs-prod-card')?.classList.toggle('is-selected', cb.checked);
        });
        renderPreviewProducts();
        applyProductFilter();
        showToast('Đã chọn nhanh 4 món đầu tiên làm Best Seller!');
      });

      // "Bỏ chọn hết"
      document.getElementById('btn-clear-bestseller')?.addEventListener('click', () => {
        document.querySelectorAll('.bs-prod-checkbox').forEach(cb => {
          cb.checked = false;
          cb.closest('.bs-prod-card')?.classList.remove('is-selected');
        });
        renderPreviewProducts();
        applyProductFilter();
      });

      // Search in product selection grid
      bsSearchInput?.addEventListener('input', applyProductFilter);

      // Initialize preview state & live ticker
      syncTimerVisibility();
      renderPreviewProducts();
      updatePreviewCountdown();
      setInterval(updatePreviewCountdown, 1000);

      // Save Best Seller configuration
      document.getElementById('btn-save-bestseller')?.addEventListener('click', async () => {
        const saveBtn = document.getElementById('btn-save-bestseller');
        const title = bsTitleInput?.value.trim() || 'Món Ăn Bán Chạy Nhất (Best Sellers)';
        const subtitle = bsSubtitleInput?.value.trim() || '';
        const badge = bsBadgeInput?.value.trim() || '';
        const timerEnabled = bsTimerEnabled?.checked ? 1 : 0;
        const rawEnd = bsEndTimeInput?.value || '';
        const endTime = rawEnd ? rawEnd.replace('T', ' ') + ':00' : '';

        const selectedIds = Array.from(document.querySelectorAll('.bs-prod-checkbox:checked'))
          .map(cb => parseInt(cb.value))
          .filter(id => id > 0);

        if (selectedIds.length === 0) {
          showToast('Vui lòng chọn ít nhất 1 món ăn làm Best Seller.', true);
          return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span>⏳</span> Đang lưu...';

        try {
          const payload = {
            records: {
              title,
              subtitle,
              badge,
              timer_enabled: timerEnabled,
              end_time: endTime,
              product_ids: selectedIds
            }
          };

          const res = await fetch('/api/v1/realtime/content/bestseller', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + (sessionStorage.getItem('fastfood_auth_token') || serverToken)
            },
            body: JSON.stringify(payload)
          });

          const result = await res.json();
          if (!res.ok || !result.success) {
            throw new Error(result.message || 'Không thể lưu cấu hình.');
          }

          showToast('✅ Đã lưu cài đặt Best Seller & Giờ vàng thành công!');

          // Phát sự kiện realtime đồng bộ sang trang chủ các tab khác
          try {
            const channel = new BroadcastChannel('fastfood_realtime');
            channel.postMessage({ type: 'content:updated', collection: 'bestseller' });
          } catch(e) {}
        } catch (err) {
          showToast('Lỗi: ' + err.message, true);
        } finally {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<span>💾</span> Lưu Cài Đặt Best Seller';
        }
      });

      // === CÀI ĐẶT THÔNG TIN CHÂN TRANG (FOOTER SETTINGS) LOGIC ===
      function updateFooterLivePreview() {
        const desc = document.getElementById('ft-cfg-brand-desc')?.value || '';
        const hotline = document.getElementById('ft-cfg-hotline')?.value || '';
        const hours = document.getElementById('ft-cfg-working-hours')?.value || '';
        const addr = document.getElementById('ft-cfg-address')?.value || '';

        const prevDesc = document.getElementById('prev-ft-desc');
        const prevHotline = document.getElementById('prev-ft-hotline');
        const prevHours = document.getElementById('prev-ft-hours');
        const prevAddr = document.getElementById('prev-ft-addr');

        if (prevDesc) prevDesc.textContent = (desc.length > 70 ? desc.substring(0, 70) + '...' : desc);
        if (prevHotline) prevHotline.textContent = hotline;
        if (prevHours) prevHours.textContent = hours;
        if (prevAddr) prevAddr.textContent = (addr.length > 30 ? addr.substring(0, 30) + '...' : addr);
      }

      ['ft-cfg-brand-desc', 'ft-cfg-hotline', 'ft-cfg-working-hours', 'ft-cfg-address'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', updateFooterLivePreview);
      });

      document.getElementById('btn-save-footer')?.addEventListener('click', async () => {
        const saveBtn = document.getElementById('btn-save-footer');
        const brandDesc = document.getElementById('ft-cfg-brand-desc')?.value.trim() || '';
        const hotline = document.getElementById('ft-cfg-hotline')?.value.trim() || '';
        const email = document.getElementById('ft-cfg-email')?.value.trim() || '';
        const workingHours = document.getElementById('ft-cfg-working-hours')?.value.trim() || '';
        const address = document.getElementById('ft-cfg-address')?.value.trim() || '';
        const mapUrl = document.getElementById('ft-cfg-map-url')?.value.trim() || '';
        const copyright = document.getElementById('ft-cfg-copyright')?.value.trim() || '';

        // Parse explore links
        const rawExplore = document.getElementById('ft-cfg-explore-links')?.value || '';
        const exploreLinks = rawExplore.split('\n').map((l) => l.trim()).filter(Boolean).map((line) => {
          const parts = line.split('|');
          return { label: parts[0]?.trim() || '', url: parts[1]?.trim() || '#' };
        });

        // Parse policy links
        const rawPolicy = document.getElementById('ft-cfg-policy-links')?.value || '';
        const policyLinks = rawPolicy.split('\n').map((l) => l.trim()).filter(Boolean).map((line) => {
          const parts = line.split('|');
          return { label: parts[0]?.trim() || '', url: parts[1]?.trim() || '#' };
        });

        const payload = {
          records: {
            brand_desc: brandDesc,
            hotline: hotline,
            email: email,
            working_hours: workingHours,
            address: address,
            map_url: mapUrl,
            copyright: copyright,
            explore_links: exploreLinks,
            policy_links: policyLinks
          }
        };

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span>⏳</span> Đang lưu...';

        try {
          const res = await fetch('/api/v1/realtime/content/footer_settings', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + (sessionStorage.getItem('fastfood_auth_token') || serverToken)
            },
            body: JSON.stringify(payload)
          });

          const result = await res.json();
          if (!res.ok || result.success === false) {
            throw new Error(result.message || 'Không thể lưu cài đặt chân trang.');
          }

          showToast('✅ Đã lưu cài đặt thông tin Chân Trang (Footer) thành công!');

          // Đồng bộ BroadcastChannel nếu có
          try {
            const channel = new BroadcastChannel('fastfood_realtime');
            channel.postMessage({ type: 'content:updated', collection: 'footer_settings' });
          } catch(e) {}
        } catch (err) {
          showToast('Lỗi: ' + err.message, true);
        } finally {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<span>💾</span> Lưu Cài Đặt Chân Trang';
        }
      });

      // === QUẢN LÝ BANNER & HERO SLIDER LOGIC ===
      let adminBanners = [];
      let adminBannerInterval = 1800;

      async function loadAdminBanners() {
        try {
          const res = await fetch('api.php?action=get_banners');
          const result = await res.json();
          if (result.success && result.data) {
            adminBanners = Array.isArray(result.data.banners) ? result.data.banners : [];
            adminBannerInterval = result.data.interval || 1800;
            const intervalSel = document.getElementById('banner-slide-interval');
            if (intervalSel) intervalSel.value = String(adminBannerInterval);
            renderAdminBanners();
          }
        } catch (err) {
          console.error('Lỗi khi tải banners:', err);
        }
      }

      function renderAdminBanners() {
        const container = document.getElementById('admin-banners-grid');
        if (!container) return;

        if (adminBanners.length === 0) {
          container.innerHTML = '<div style="grid-column: 1 / -1; text-align:center; padding:3rem; background:#fff; border-radius:12px; color:#64748b;">Chưa có banner nào. Bấm "+ Thêm Banner Mới" để tạo slide đầu tiên.</div>';
          return;
        }

        // Sắp xếp theo thứ tự hiển thị
        adminBanners.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));

        container.innerHTML = adminBanners.map((b, idx) => {
          const isActive = b.active !== false;
          return `
            <div class="bs-panel-card" style="background:#fff; border:1px solid ${isActive ? '#e2e8f0' : '#cbd5e1'}; border-radius:12px; overflow:hidden; opacity:${isActive ? '1' : '0.65'}; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
              <div>
                <div style="position:relative; height:160px; overflow:hidden; background:#0f172a;">
                  <img src="${escapeHtml(b.image || '')}" alt="${escapeHtml(b.title || '')}" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&auto=format&fit=crop&q=80';">
                  <div style="position:absolute; top:8px; left:8px; background:rgba(0,0,0,0.75); color:#fff; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;">
                    #${b.order || (idx + 1)}
                  </div>
                  <div style="position:absolute; top:8px; right:8px;">
                    <span style="background:${isActive ? '#10b981' : '#64748b'}; color:#fff; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;">
                      ${isActive ? '● Đang hiển thị' : '○ Tắt'}
                    </span>
                  </div>
                  ${b.badge ? `
                    <div style="position:absolute; bottom:8px; left:8px; background:#ea580c; color:#fff; font-size:11px; font-weight:800; padding:2px 8px; border-radius:4px;">
                      ${escapeHtml(b.badge)}
                    </div>
                  ` : ''}
                </div>
                <div style="padding:1rem;">
                  <h4 style="font-weight:800; font-size:1.05rem; color:#0f172a; margin-bottom:0.25rem;">${escapeHtml(b.title || 'Chưa đặt tiêu đề')}</h4>
                  <p style="font-size:0.85rem; color:#64748b; margin-bottom:0.75rem; line-height:1.4;">${escapeHtml(b.subtitle || 'Không có mô tả phụ')}</p>
                  <div style="font-size:0.8rem; color:#94a3b8; display:flex; align-items:center; gap:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <span>🔗</span> <span>${escapeHtml(b.link || 'foods.php')}</span>
                  </div>
                </div>
              </div>
              <div style="padding:0.75rem 1rem; background:#f8fafc; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="secondary-button" onclick="window.toggleAdminBannerStatus('${b.id}')" style="padding:5px 12px; font-size:12px; font-weight:700;">
                  ${isActive ? 'Tắt hiển thị' : 'Bật hiển thị'}
                </button>
                <div style="display:flex; gap:6px;">
                  <button type="button" class="secondary-button" onclick="window.openEditAdminBanner('${b.id}')" style="padding:5px 12px; font-size:12px; font-weight:700; background:#eff6ff; color:#2563eb; border-color:#bfdbfe;">
                    ✏️ Sửa
                  </button>
                  <button type="button" class="secondary-button" onclick="window.deleteAdminBanner('${b.id}')" style="padding:5px 12px; font-size:12px; font-weight:700; background:#fef2f2; color:#ef4444; border-color:#fecaca;">
                    🗑️ Xóa
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');
      }

      window.openEditAdminBanner = function(id) {
        const banner = adminBanners.find(b => b.id === id);
        if (!banner) return;
        document.getElementById('banner-dialog-title').textContent = 'Chỉnh Sửa Banner';
        document.getElementById('banner-edit-id').value = banner.id;
        document.getElementById('banner-title').value = banner.title || '';
        document.getElementById('banner-subtitle').value = banner.subtitle || '';
        document.getElementById('banner-badge').value = banner.badge || '';
        document.getElementById('banner-order').value = banner.order || 1;
        document.getElementById('banner-image').value = banner.image || '';
        document.getElementById('banner-link').value = banner.link || 'foods.php';
        document.getElementById('banner-active').checked = banner.active !== false;

        const previewImg = document.getElementById('banner-image-preview');
        if (banner.image) {
          previewImg.src = banner.image;
          previewImg.style.display = 'block';
        } else {
          previewImg.style.display = 'none';
        }

        document.getElementById('banner-dialog').showModal();
      };

      window.deleteAdminBanner = function(id) {
        if (!confirm('Bạn có chắc muốn xóa banner này khỏi slider không?')) return;
        adminBanners = adminBanners.filter(b => b.id !== id);
        renderAdminBanners();
        showToast('Đã xóa banner khỏi danh sách tạm. Bấm "Lưu Tất Cả Thay Đổi" để cập nhật!');
      };

      window.toggleAdminBannerStatus = function(id) {
        const banner = adminBanners.find(b => b.id === id);
        if (!banner) return;
        banner.active = banner.active === false ? true : false;
        renderAdminBanners();
      };

      document.getElementById('btn-add-banner')?.addEventListener('click', () => {
        document.getElementById('banner-dialog-title').textContent = 'Thêm Banner Mới';
        document.getElementById('banner-edit-id').value = '';
        document.getElementById('banner-form').reset();
        document.getElementById('banner-order').value = adminBanners.length + 1;
        document.getElementById('banner-image-preview').style.display = 'none';
        document.getElementById('banner-dialog').showModal();
      });

      document.getElementById('banner-dialog-close')?.addEventListener('click', () => {
        document.getElementById('banner-dialog').close();
      });
      document.getElementById('banner-dialog-cancel')?.addEventListener('click', () => {
        document.getElementById('banner-dialog').close();
      });

      document.getElementById('banner-image')?.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        const preview = document.getElementById('banner-image-preview');
        if (val) {
          preview.src = val;
          preview.style.display = 'block';
        } else {
          preview.style.display = 'none';
        }
      });

      document.getElementById('banner-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const editId = document.getElementById('banner-edit-id').value.trim();
        const title = document.getElementById('banner-title').value.trim();
        const subtitle = document.getElementById('banner-subtitle').value.trim();
        const badge = document.getElementById('banner-badge').value.trim();
        const order = parseInt(document.getElementById('banner-order').value, 10) || 1;
        const image = document.getElementById('banner-image').value.trim();
        const link = document.getElementById('banner-link').value.trim() || 'foods.php';
        const active = document.getElementById('banner-active').checked;

        if (!title || !image) {
          alert('Vui lòng nhập tiêu đề và đường dẫn hình ảnh cho banner.');
          return;
        }

        if (editId) {
          const banner = adminBanners.find(b => b.id === editId);
          if (banner) {
            banner.title = title;
            banner.subtitle = subtitle;
            banner.badge = badge;
            banner.order = order;
            banner.image = image;
            banner.link = link;
            banner.active = active;
          }
        } else {
          adminBanners.push({
            id: 'banner-' + Date.now(),
            title,
            subtitle,
            badge,
            order,
            image,
            link,
            active
          });
        }

        renderAdminBanners();
        document.getElementById('banner-dialog').close();
        showToast('Đã cập nhật banner! Vui lòng bấm "Lưu Tất Cả Thay Đổi" để áp dụng lên trang chủ.');
      });

      document.getElementById('btn-save-banners')?.addEventListener('click', async () => {
        const saveBtn = document.getElementById('btn-save-banners');
        const interval = parseInt(document.getElementById('banner-slide-interval')?.value, 10) || 1800;

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span>⏳</span> Đang lưu...';

        try {
          const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'save_banners',
              interval: interval,
              banners: adminBanners
            })
          });
          const result = await res.json();
          if (result.success) {
            showToast('✅ Đã lưu cấu hình Banner & Tốc độ chuyển ảnh 1-2s thành công!');
            try {
              new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'banners' });
            } catch (e) {}
          } else {
            showToast(result.message || 'Lỗi khi lưu banners.', true);
          }
        } catch (err) {
          showToast('Lỗi kết nối: ' + err.message, true);
        } finally {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<span>💾</span> Lưu Tất Cả Thay Đổi';
        }
      });

      // === Milestone 4: Membership Management & Dynamic Configuration Logic ===
      const membershipSection = document.getElementById('membership-section');
      let membershipLoaded = false;
      let membershipPage = 1;
      let membershipSearchTerm = '';
      let membershipTierFilter = '';
      let membershipMembers = [];
      let loyaltyConfigLoaded = false;

      function getTierBadgeHtml(tierCode) {
        const code = String(tierCode || 'BRONZE').toUpperCase();
        const map = {
          BRONZE: { label: 'Đồng', icon: '🥉', cls: 'tier-badge-bronze' },
          SILVER: { label: 'Bạc', icon: '🥈', cls: 'tier-badge-silver' },
          GOLD: { label: 'Vàng', icon: '🥇', cls: 'tier-badge-gold' },
          DIAMOND: { label: 'Kim Cương', icon: '💎', cls: 'tier-badge-diamond' }
        };
        const t = map[code] || map.BRONZE;
        return `<span class="tier-badge ${t.cls}">${t.icon} ${t.label}</span>`;
      }

      window.loadMembershipData = async function(page = 1) {
        membershipPage = page;
        const tbody = document.getElementById('membership-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Đang tải dữ liệu thành viên...</td></tr>';

        try {
          const params = new URLSearchParams({
            action: 'get_loyalty_members',
            page: String(membershipPage),
            tier: membershipTierFilter,
            search: membershipSearchTerm
          });
          const res = await fetch(`api.php?${params.toString()}`);
          const result = await res.json();

          if (result.success && result.data) {
            membershipMembers = result.data.members || [];
            const pagination = result.data.pagination || { page: 1, limit: 10, total: 0, totalPages: 1 };
            const summary = result.data.summary || { totalMembers: 0, totalPoints: 0, vipCount: 0 };

            // Update header summary pills
            const totalEl = document.getElementById('mem-stat-total');
            const ptsEl = document.getElementById('mem-stat-points');
            const vipEl = document.getElementById('mem-stat-vip');
            if (totalEl) totalEl.textContent = new Intl.NumberFormat('vi-VN').format(summary.totalMembers || 0);
            if (ptsEl) ptsEl.textContent = `${new Intl.NumberFormat('vi-VN').format(summary.totalPoints || 0)} 🪙`;
            if (vipEl) vipEl.textContent = new Intl.NumberFormat('vi-VN').format(summary.vipCount || 0);

            renderMembershipTable(membershipMembers);
            renderMembershipPagination(pagination);
            membershipLoaded = true;
          } else {
            tbody.innerHTML = `<tr><td colspan="7" class="empty-state">${escapeHtml(result.message || 'Không thể tải danh sách thành viên.')}</td></tr>`;
          }
        } catch (err) {
          tbody.innerHTML = `<tr><td colspan="7" class="empty-state">Lỗi kết nối: ${escapeHtml(err.message)}</td></tr>`;
        }
      };

      function renderMembershipTable(members) {
        const tbody = document.getElementById('membership-table-body');
        if (!tbody) return;

        if (!members.length) {
          tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Không tìm thấy thành viên nào phù hợp với bộ lọc.</td></tr>';
          return;
        }

        tbody.innerHTML = members.map((m) => {
          const initial = (m.full_name || 'U').trim().charAt(0).toUpperCase();
          return `
            <tr>
              <td style="font-weight:700; color:#64748b;">#${escapeHtml(m.id)}</td>
              <td>
                <div class="mem-cell-user">
                  <div class="mem-avatar">${escapeHtml(initial)}</div>
                  <div class="mem-details">
                    <strong>${escapeHtml(m.full_name || 'Chưa đặt tên')}</strong>
                    <small>${escapeHtml(m.phone || 'Chưa có SĐT')} • ${escapeHtml(m.email || '')}</small>
                  </div>
                </div>
              </td>
              <td>${getTierBadgeHtml(m.member_tier)}</td>
              <td><strong style="color:#ea580c; font-size:12.5px;">${new Intl.NumberFormat('vi-VN').format(m.points_balance || 0)} 🪙</strong></td>
              <td><strong>${money(m.total_spent || 0)}</strong></td>
              <td style="color:#64748b; font-size:11px;">${m.created_at ? formatVietnamDateTime(m.created_at) : '—'}</td>
              <td style="text-align: right;">
                <div class="row-actions" style="justify-content: flex-end;">
                  ${isAdminUser ? `
                    <button type="button" class="action-button edit-tier" onclick="window.openEditMemberLoyaltyModal(${m.id})" title="Sửa thông tin &amp; Thăng hạng thành viên">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 5 5M4 20l4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11z"/></svg>Sửa
                    </button>
                  ` : ''}
                  <button type="button" class="action-button" onclick="window.openPointsHistoryModal(${m.id})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>Lịch sử điểm
                  </button>
                </div>
              </td>
            </tr>
          `;
        }).join('');
      }

      function renderMembershipPagination(pagination) {
        const resCount = document.getElementById('member-result-count');
        const container = document.getElementById('member-pagination');
        if (!resCount || !container) return;

        const total = Number(pagination.total) || 0;
        const page = Number(pagination.page) || 1;
        const limit = Number(pagination.limit) || 10;
        const totalPages = Number(pagination.totalPages || pagination.total_pages) || 1;

        const from = total > 0 ? (page - 1) * limit + 1 : 0;
        const to = Math.min(page * limit, total);
        resCount.textContent = `Hiển thị ${from}–${to} trong tổng số ${total} thành viên`;

        const buttons = [
          `<button class="page-button" type="button" ${page <= 1 ? 'disabled' : ''} onclick="window.loadMembershipData(${page - 1})">‹</button>`
        ];

        for (let p = 1; p <= totalPages; p++) {
          if (totalPages > 5 && p > 2 && p < totalPages - 1 && Math.abs(p - page) > 1) {
            if (p === 3 || p === totalPages - 2) buttons.push('<span class="pagination-ellipsis">…</span>');
            continue;
          }
          buttons.push(`<button class="page-button${p === page ? ' current' : ''}" type="button" onclick="window.loadMembershipData(${p})">${p}</button>`);
        }

        buttons.push(`<button class="page-button" type="button" ${page >= totalPages ? 'disabled' : ''} onclick="window.loadMembershipData(${page + 1})">›</button>`);
        container.innerHTML = buttons.join('');
      }

      window.openPointsHistoryModal = async function(userId) {
        const dialog = document.getElementById('points-history-dialog');
        const tbody = document.getElementById('points-history-table-body');
        if (!dialog || !tbody) return;

        dialog.showModal();
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Đang tải lịch sử giao dịch...</td></tr>';

        try {
          const res = await fetch(`api.php?action=get_member_loyalty_history&user_id=${userId}`);
          const result = await res.json();

          if (result.success && result.data) {
            const u = result.data.user || {};
            const txs = result.data.history || result.data.transactions || [];

            document.getElementById('points-dialog-title').innerHTML = `<span>📜</span> Lịch Sử Điểm Thưởng - ${escapeHtml(u.name || u.full_name || 'Thành viên')}`;
            document.getElementById('pts-avatar').textContent = (u.name || u.full_name || 'U').trim().charAt(0).toUpperCase();
            document.getElementById('pts-name').textContent = u.name || u.full_name || 'Chưa đặt tên';
            document.getElementById('pts-email').textContent = `${u.phone || ''} • ${u.email || ''}`;
            const tierBadgeEl = document.getElementById('pts-tier-badge');
            if (tierBadgeEl) tierBadgeEl.outerHTML = `<span id="pts-tier-badge">${getTierBadgeHtml(u.tier || u.member_tier)}</span>`;
            document.getElementById('pts-balance').textContent = `${new Intl.NumberFormat('vi-VN').format(u.points || u.points_balance || 0)} 🪙`;
            document.getElementById('pts-spent').textContent = money(u.total_spent || 0);

            if (!txs.length) {
              tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Thành viên chưa có giao dịch điểm nào.</td></tr>';
              return;
            }

            const txTypeMap = {
              EARN: { label: 'Tích điểm đơn', cls: 'tx-type-earn' },
              REDEEM_CHECKOUT: { label: 'Giảm giá đơn', cls: 'tx-type-redeem' },
              REDEEM_VOUCHER: { label: 'Đổi voucher', cls: 'tx-type-voucher' },
              REFUND_RESTORE: { label: 'Hoàn điểm hủy', cls: 'tx-type-restore' },
              REFUND_REVOKE: { label: 'Thu hồi điểm', cls: 'tx-type-revoke' },
              ADMIN_ADJUST: { label: 'Điều chỉnh', cls: 'tx-type-adjust' }
            };

            tbody.innerHTML = txs.map((tx) => {
              const typeInfo = txTypeMap[tx.transaction_type] || { label: tx.transaction_type, cls: 'tx-type-adjust' };
              const pts = Number(tx.points) || 0;
              const ptsDisplay = pts > 0
                ? `<span class="pts-positive">+${pts}</span>`
                : (pts < 0 ? `<span class="pts-negative">${pts}</span>` : `<span style="color:#64748b; font-weight:700;">0</span>`);

              return `
                <tr>
                  <td style="color:#64748b;">${tx.created_at ? formatVietnamDateTime(tx.created_at) : '—'}</td>
                  <td><span class="tx-type-tag ${typeInfo.cls}">${typeInfo.label}</span></td>
                  <td>${ptsDisplay}</td>
                  <td>${escapeHtml(tx.description || (tx.order_code ? `Đơn hàng #${tx.order_code}` : 'Giao dịch điểm'))}</td>
                  <td style="text-align: right; font-weight:700;">${new Intl.NumberFormat('vi-VN').format(tx.balance_after)} 🪙</td>
                </tr>
              `;
            }).join('');
          } else {
            tbody.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(result.message || 'Lỗi khi tải lịch sử điểm.')}</td></tr>`;
          }
        } catch (err) {
          tbody.innerHTML = `<tr><td colspan="5" class="empty-state">Lỗi kết nối: ${escapeHtml(err.message)}</td></tr>`;
        }
      };

      async function loadLoyaltyConfig() {
        try {
          const res = await fetch('api.php?action=get_loyalty_config');
          const result = await res.json();
          if (result.success && result.data) {
            const tiers = result.data.tiers || {};
            const settings = result.data.settings || {};

            // Populate thresholds
            if (tiers.SILVER && document.getElementById('cfg-silver-min-spent')) {
              document.getElementById('cfg-silver-min-spent').value = tiers.SILVER.min_spent || 1000000;
            }
            if (tiers.GOLD && document.getElementById('cfg-gold-min-spent')) {
              document.getElementById('cfg-gold-min-spent').value = tiers.GOLD.min_spent || 3000000;
            }
            if (tiers.DIAMOND && document.getElementById('cfg-diamond-min-spent')) {
              document.getElementById('cfg-diamond-min-spent').value = tiers.DIAMOND.min_spent || 5000000;
            }

            // Populate ratios
            if (tiers.BRONZE && document.getElementById('cfg-bronze-ratio')) {
              document.getElementById('cfg-bronze-ratio').value = tiers.BRONZE.point_ratio ?? tiers.BRONZE.points_ratio ?? 1.0;
            }
            if (tiers.SILVER && document.getElementById('cfg-silver-ratio')) {
              document.getElementById('cfg-silver-ratio').value = tiers.SILVER.point_ratio ?? tiers.SILVER.points_ratio ?? 1.5;
            }
            if (tiers.GOLD && document.getElementById('cfg-gold-ratio')) {
              document.getElementById('cfg-gold-ratio').value = tiers.GOLD.point_ratio ?? tiers.GOLD.points_ratio ?? 2.0;
            }
            if (tiers.DIAMOND && document.getElementById('cfg-diamond-ratio')) {
              document.getElementById('cfg-diamond-ratio').value = tiers.DIAMOND.point_ratio ?? tiers.DIAMOND.points_ratio ?? 3.0;
            }

            // Populate settings
            if (document.getElementById('cfg-point-to-vnd')) {
              document.getElementById('cfg-point-to-vnd').value = settings.point_to_vnd_rate || 100;
            }
            if (document.getElementById('cfg-spending-step')) {
              document.getElementById('cfg-spending-step').value = settings.spending_step_vnd || 10000;
            }
            if (document.getElementById('cfg-min-redeem-points')) {
              document.getElementById('cfg-min-redeem-points').value = settings.min_redeem_points || 100;
            }

            // RBAC UI Enforcement
            const noticeContainer = document.getElementById('loyalty-role-notice-container');
            const saveBtn = document.getElementById('btn-save-loyalty-config');
            if (!isAdminUser) {
              // Staff view-only mode
              document.querySelectorAll('.loyalty-cfg-input').forEach((input) => input.disabled = true);
              if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.5';
                saveBtn.style.cursor = 'not-allowed';
                saveBtn.title = 'Chỉ Quản trị viên mới có quyền cập nhật cấu hình';
              }
              if (noticeContainer) {
                noticeContainer.innerHTML = `
                  <div class="loyalty-role-notice">
                    <span style="font-size:20px;">🛡️</span>
                    <div>
                      <strong>Chế độ Xem (Nhân viên hỗ trợ - Staff)</strong>
                      <p>Bạn có quyền xem thông tin cấp bậc và tỷ lệ tích điểm. Chỉ Quản Trị Viên (Admin) mới có quyền thay đổi cấu hình hệ thống.</p>
                    </div>
                  </div>
                `;
              }
            }
            loyaltyConfigLoaded = true;
          }
        } catch (err) {
          console.error('Lỗi khi tải cấu hình loyalty:', err);
        }
      }

      async function saveLoyaltyConfig() {
        if (!isAdminUser) {
          showToast('Từ chối quyền: Chỉ Quản trị viên mới có thể cập nhật cấu hình.', true);
          return;
        }

        const saveBtn = document.getElementById('btn-save-loyalty-config');
        const alertBanner = document.getElementById('loyalty-config-alert');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span>⏳</span> Đang lưu...';

        const payload = {
          action: 'save_loyalty_config',
          thresholds: {
            SILVER: parseFloat(document.getElementById('cfg-silver-min-spent').value) || 1000000,
            GOLD: parseFloat(document.getElementById('cfg-gold-min-spent').value) || 3000000,
            DIAMOND: parseFloat(document.getElementById('cfg-diamond-min-spent').value) || 5000000
          },
          ratios: {
            BRONZE: parseFloat(document.getElementById('cfg-bronze-ratio').value) || 1.0,
            SILVER: parseFloat(document.getElementById('cfg-silver-ratio').value) || 1.5,
            GOLD: parseFloat(document.getElementById('cfg-gold-ratio').value) || 2.0,
            DIAMOND: parseFloat(document.getElementById('cfg-diamond-ratio').value) || 3.0
          },
          settings: {
            point_to_vnd_rate: parseFloat(document.getElementById('cfg-point-to-vnd').value) || 100,
            spending_step_vnd: parseFloat(document.getElementById('cfg-spending-step').value) || 10000,
            min_redeem_points: parseInt(document.getElementById('cfg-min-redeem-points').value, 10) || 100
          }
        };

        try {
          const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const result = await res.json();

          if (result.success) {
            showToast('✅ Đã lưu cấu hình Hạng & Điểm thưởng thành công!');
            try {
              new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'loyalty' });
            } catch (e) {}
            if (alertBanner) {
              alertBanner.className = 'loyalty-alert-banner success';
              alertBanner.innerHTML = '<span>✅</span> <strong>Thành công!</strong> Cấu hình đã được lưu vào hệ thống và áp dụng ngay lập tức.';
              alertBanner.hidden = false;
            }
          } else {
            showToast(result.message || 'Lỗi khi lưu cấu hình.', true);
            if (alertBanner) {
              alertBanner.className = 'loyalty-alert-banner error';
              alertBanner.innerHTML = `<span>⚠️</span> <strong>Lỗi:</strong> ${escapeHtml(result.message || 'Không thể lưu.')}`;
              alertBanner.hidden = false;
            }
          }
        } catch (err) {
          showToast('Lỗi kết nối: ' + err.message, true);
        } finally {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<span>💾</span> Lưu Cấu Hình';
        }
      }

      // Membership Tab switching
      document.getElementById('tab-btn-members')?.addEventListener('click', () => {
        document.getElementById('tab-btn-members').classList.add('active');
        document.getElementById('tab-btn-config').classList.remove('active');
        document.getElementById('membership-tab-ledger').hidden = false;
        document.getElementById('membership-tab-config').hidden = true;
      });
      document.getElementById('tab-btn-config')?.addEventListener('click', () => {
        document.getElementById('tab-btn-config').classList.add('active');
        document.getElementById('tab-btn-members').classList.remove('active');
        document.getElementById('membership-tab-config').hidden = false;
        document.getElementById('membership-tab-ledger').hidden = true;
        if (!loyaltyConfigLoaded) loadLoyaltyConfig();
      });

      // Member Search & Tier Filter
      let memberSearchTimeout = null;
      document.getElementById('member-search-input')?.addEventListener('input', (e) => {
        clearTimeout(memberSearchTimeout);
        memberSearchTimeout = setTimeout(() => {
          membershipSearchTerm = e.target.value.trim().toLocaleLowerCase('vi');
          loadMembershipData(1);
        }, 300);
      });
      document.getElementById('member-tier-filter')?.addEventListener('change', (e) => {
        membershipTierFilter = e.target.value;
        loadMembershipData(1);
      });
      document.getElementById('btn-refresh-members')?.addEventListener('click', () => {
        loadMembershipData(1);
      });

      // Modal Dialog close events
      const ptsDialog = document.getElementById('points-history-dialog');
      document.getElementById('btn-close-points-dialog')?.addEventListener('click', () => ptsDialog?.close());
      document.getElementById('btn-cancel-points-dialog')?.addEventListener('click', () => ptsDialog?.close());
      ptsDialog?.addEventListener('click', (e) => { if (e.target === ptsDialog) ptsDialog.close(); });

      // Member Edit / Tier Promotion Modal Logic
      const editMemDialog = document.getElementById('member-loyalty-edit-dialog');
      const editMemForm = document.getElementById('form-edit-member-loyalty');
      let currentEditingMember = null;

      function selectEditMemberTier(tierCode) {
        const code = String(tierCode || 'BRONZE').toUpperCase();
        const hiddenInput = document.getElementById('edit-mem-tier-value');
        if (hiddenInput) hiddenInput.value = code;

        const cards = document.querySelectorAll('#edit-mem-tier-grid .tier-select-card');
        let matchedMinSpent = 0;
        cards.forEach((btn) => {
          const isMatch = (btn.dataset.tier || '').toUpperCase() === code;
          btn.classList.toggle('is-selected', isMatch);
          if (isMatch) {
            matchedMinSpent = Number(btn.dataset.minSpent || 0);
          }
        });

        const syncChk = document.getElementById('edit-mem-sync-spent');
        const spentInput = document.getElementById('edit-mem-spent');
        const hintEl = document.getElementById('edit-mem-spent-hint');
        if (hintEl) {
          hintEl.textContent = `Mốc chuẩn của hạng ${code}: ${money(matchedMinSpent)}`;
        }
        if (syncChk?.checked && spentInput) {
          const baseSpent = Number(currentEditingMember?.total_spent || 0);
          spentInput.value = String(Math.max(baseSpent, matchedMinSpent));
        }
      }

      document.querySelectorAll('#edit-mem-tier-grid .tier-select-card').forEach((btn) => {
        btn.addEventListener('click', () => {
          selectEditMemberTier(btn.dataset.tier);
        });
      });

      document.getElementById('edit-mem-sync-spent')?.addEventListener('change', (e) => {
        const tierCode = document.getElementById('edit-mem-tier-value')?.value || 'BRONZE';
        const activeCard = document.querySelector(`#edit-mem-tier-grid .tier-select-card[data-tier="${tierCode}"]`);
        const minSpent = Number(activeCard?.dataset.minSpent || 0);
        const spentInput = document.getElementById('edit-mem-spent');
        if (spentInput) {
          const baseSpent = Number(currentEditingMember?.total_spent || 0);
          if (e.target.checked) {
            spentInput.value = String(Math.max(baseSpent, minSpent));
          } else {
            spentInput.value = String(Math.round(baseSpent));
          }
        }
      });

      document.querySelectorAll('#member-loyalty-edit-dialog .quick-pts-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const add = Number(btn.dataset.addPts || 0);
          const ptsInput = document.getElementById('edit-mem-points');
          if (ptsInput) {
            ptsInput.value = String(Math.max(0, (Number(ptsInput.value) || 0) + add));
          }
        });
      });

      window.openEditMemberLoyaltyModal = async function(userId) {
        if (!editMemDialog) return;
        const member = membershipMembers.find((m) => Number(m.id) === Number(userId));
        if (!member) {
          showToast('Không tìm thấy dữ liệu thành viên.', true);
          return;
        }
        currentEditingMember = member;

        // Cập nhật mốc min_spent từ cấu hình hiện tại (nếu có)
        try {
          const cfgRes = await fetch('api.php?action=get_loyalty_config');
          const cfgJson = await cfgRes.json();
          if (cfgJson.success && cfgJson.data?.tiers) {
            const tiers = cfgJson.data.tiers;
            document.querySelectorAll('#edit-mem-tier-grid .tier-select-card').forEach((btn) => {
              const tc = (btn.dataset.tier || '').toUpperCase();
              if (tiers[tc]) {
                const ms = Number(tiers[tc].min_spent || 0);
                const ratio = Number(tiers[tc].point_ratio || 1);
                btn.dataset.minSpent = String(ms);
                const metaEl = btn.querySelector('.tier-select-meta');
                if (metaEl) {
                  metaEl.textContent = `x${ratio} điểm • ${ms >= 1000000 ? (ms / 1000000) + 'Tr' : money(ms)}`;
                }
              }
            });
          }
        } catch (e) {}

        document.getElementById('edit-mem-user-id').value = String(member.id);
        document.getElementById('edit-mem-avatar').textContent = (member.full_name || 'U').trim().charAt(0).toUpperCase();
        document.getElementById('edit-mem-name').textContent = member.full_name || 'Chưa đặt tên';
        document.getElementById('edit-mem-email').textContent = `${member.phone || 'Chưa có SĐT'} • ${member.email || ''}`;
        const curBadge = document.getElementById('edit-mem-current-tier-badge');
        if (curBadge) curBadge.outerHTML = `<span id="edit-mem-current-tier-badge">${getTierBadgeHtml(member.member_tier)}</span>`;

        document.getElementById('edit-mem-sync-spent').checked = false;
        document.getElementById('edit-mem-points').value = String(member.points_balance || 0);
        document.getElementById('edit-mem-spent').value = String(Math.round(Number(member.total_spent || 0)));
        document.getElementById('edit-mem-reason').value = '';

        selectEditMemberTier(member.member_tier || 'BRONZE');
        editMemDialog.showModal();
      };

      document.getElementById('btn-close-edit-member-dialog')?.addEventListener('click', () => editMemDialog?.close());
      document.getElementById('btn-cancel-edit-member-dialog')?.addEventListener('click', () => editMemDialog?.close());
      editMemDialog?.addEventListener('click', (e) => { if (e.target === editMemDialog) editMemDialog.close(); });

      editMemForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const saveBtn = document.getElementById('btn-save-edit-member');
        const userId = Number(document.getElementById('edit-mem-user-id')?.value || 0);
        const memberTier = document.getElementById('edit-mem-tier-value')?.value || 'BRONZE';
        const pointsBalance = Number(document.getElementById('edit-mem-points')?.value || 0);
        const totalSpent = Number(document.getElementById('edit-mem-spent')?.value || 0);
        const syncMinSpent = !!document.getElementById('edit-mem-sync-spent')?.checked;
        const reason = document.getElementById('edit-mem-reason')?.value.trim() || '';

        if (!userId) return;
        if (saveBtn) {
          saveBtn.disabled = true;
          saveBtn.innerHTML = '<span>⏳</span> Đang lưu...';
        }

        try {
          const res = await fetch('api.php?action=update_member_loyalty', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update_member_loyalty',
              user_id: userId,
              member_tier: memberTier,
              points_balance: pointsBalance,
              total_spent: totalSpent,
              sync_min_spent: syncMinSpent,
              reason
            })
          });
          const result = await res.json();

          if (result.success) {
            showToast(`✅ ${result.message || 'Cập nhật hạng thành viên thành công!'}`);
            editMemDialog?.close();
            await window.loadMembershipData(membershipPage);
            try {
              new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'loyalty', userId });
            } catch (err) {}
          } else {
            showToast(result.message || 'Không thể cập nhật thông tin thành viên.', true);
          }
        } catch (err) {
          showToast('Lỗi kết nối: ' + err.message, true);
        } finally {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<span>💾</span> Lưu Thay Đổi';
          }
        }
      });

      // Save loyalty config button
      document.getElementById('btn-save-loyalty-config')?.addEventListener('click', saveLoyaltyConfig);

      // === Voucher Management (Voucher Shop & Sinh Nhật) Logic ===
      let vouchersLoaded = false;
      let adminVouchersList = [];
      const vcDialog = document.getElementById('voucher-admin-dialog');

      window.loadAdminVouchers = async function() {
        const tbody = document.getElementById('vouchers-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="11" class="empty-state">Đang tải danh sách voucher...</td></tr>';

        const searchVal = document.getElementById('vc-search-input')?.value.trim() || '';
        const typeVal = document.getElementById('vc-filter-type')?.value || '';
        const tierVal = document.getElementById('vc-filter-tier')?.value || '';
        const statusVal = document.getElementById('vc-filter-status')?.value || '';

        // Cập nhật trạng thái active & nút reset
        const tSelect = document.getElementById('vc-filter-type');
        const tierSelect = document.getElementById('vc-filter-tier');
        const stSelect = document.getElementById('vc-filter-status');
        const resetBtn = document.getElementById('vc-btn-reset-filters');
        const clearBtn = document.getElementById('vc-search-clear');

        if (clearBtn) clearBtn.style.display = searchVal ? 'flex' : 'none';
        if (tSelect) tSelect.classList.toggle('is-active-filter', Boolean(typeVal));
        if (tierSelect) tierSelect.classList.toggle('is-active-filter', Boolean(tierVal));
        if (stSelect) stSelect.classList.toggle('is-active-filter', Boolean(statusVal));

        const hasActiveFilter = Boolean(searchVal || typeVal || tierVal || statusVal);
        if (resetBtn) resetBtn.style.display = hasActiveFilter ? 'inline-flex' : 'none';

        try {
          const params = new URLSearchParams({
            action: 'get_admin_vouchers',
            search: searchVal,
            voucher_type: typeVal,
            min_member_tier: tierVal,
            is_active: statusVal
          });
          const res = await fetch(`api.php?${params.toString()}`);
          const result = await res.json();

          if (result.success && result.data) {
            adminVouchersList = result.data.vouchers || [];
            const stats = result.data.stats || {};

            // Cập nhật stats cards
            const tEl = document.getElementById('vc-stat-total');
            const aEl = document.getElementById('vc-stat-active');
            const aSubEl = document.getElementById('vc-stat-active-sub');
            const uEl = document.getElementById('vc-stat-usages');
            const dEl = document.getElementById('vc-stat-discount');
            const bEl = document.getElementById('vc-stat-birthday');

            if (tEl) tEl.textContent = stats.total_vouchers || 0;
            if (aEl) aEl.textContent = stats.active_vouchers || 0;
            if (aSubEl) aSubEl.textContent = stats.active_vouchers || 0;
            if (uEl) uEl.textContent = stats.total_usages || 0;
            if (dEl) dEl.textContent = money(stats.total_discount || 0);
            if (bEl) bEl.textContent = `${stats.birthday_gifted || 0} 🎂`;

            renderAdminVouchersTable(adminVouchersList);
            vouchersLoaded = true;
          } else {
            tbody.innerHTML = `<tr><td colspan="11" class="empty-state">${escapeHtml(result.message || 'Không thể tải voucher.')}</td></tr>`;
          }
        } catch (err) {
          tbody.innerHTML = `<tr><td colspan="11" class="empty-state">Lỗi kết nối: ${escapeHtml(err.message)}</td></tr>`;
        }
      };

      window.copyVoucherCodeAdmin = function(code) {
        if (!code) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(code).then(() => {
            showToast('Đã sao chép mã: ' + code);
          }).catch(() => {
            showToast('Mã voucher: ' + code);
          });
        } else {
          showToast('Mã voucher: ' + code);
        }
      };

      function renderAdminVouchersTable(vouchers) {
        const tbody = document.getElementById('vouchers-table-body');
        if (!tbody) return;

        if (!vouchers.length) {
          tbody.innerHTML = '<tr><td colspan="11" class="empty-state">Không tìm thấy voucher nào phù hợp với bộ lọc.</td></tr>';
          return;
        }

        tbody.innerHTML = vouchers.map(v => {
          const typeBadge = {
            percentage: '<span class="badge" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; font-weight:800; font-size:11px;">🔥 Giảm %</span>',
            fixed: '<span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:800; font-size:11px;">💰 Giảm tiền</span>',
            free_shipping: '<span class="badge" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; font-weight:800; font-size:11px;">🚚 Freeship</span>',
            bogo: '<span class="badge" style="background:#fce7f3; color:#be185d; border:1px solid #fbcfe8; font-weight:800; font-size:11px;">🍔 Mua 1 Tặng 1</span>'
          }[v.voucher_type] || '<span class="badge">Ưu đãi</span>';

          const discText = v.voucher_type === 'percentage'
            ? `<strong style="color:#ea580c;">${parseFloat(v.discount_value)}%</strong>`
            : (v.voucher_type === 'free_shipping' ? 'Freeship (' + money(v.discount_value) + ')' : money(v.discount_value));

          const tierConfig = {
            DIAMOND: { color: '#0284c7', bg: '#e0f2fe', border: '#bae6fd', label: '💎 Diamond' },
            GOLD:    { color: '#b45309', bg: '#fef3c7', border: '#fde68a', label: '👑 Gold' },
            SILVER:  { color: '#475569', bg: '#f1f5f9', border: '#cbd5e1', label: '⭐ Silver' },
            BRONZE:  { color: '#9a3412', bg: '#fff7ed', border: '#ffedd5', label: '🥉 Bronze' },
          };
          const tInfo = tierConfig[v.min_member_tier];
          const reqTier = tInfo
            ? `<span style="background:${tInfo.bg}; color:${tInfo.color}; border:1px solid ${tInfo.border}; font-weight:800; font-size:11px; padding:2px 7px; border-radius:6px;">${tInfo.label}+</span>`
            : '<span style="color:#64748b; font-size:11.5px; background:#f8fafc; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0;">Tất cả</span>';

          const statusBadge = parseInt(v.is_active, 10) === 1
            ? '<span class="status-badge status-active"><span class="status-pulse-dot dot-active"></span>Hoạt động</span>'
            : '<span class="status-badge status-cancelled"><span class="status-pulse-dot dot-locked"></span>Đã khóa</span>';

          const used = parseInt(v.actual_usages || v.usage_count || 0, 10);
          const limit = parseInt(v.usage_limit, 10);
          const pct = limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : null;
          const usageDisplay = limit > 0 ? `
            <div style="min-width:85px;">
              <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:750; margin-bottom:2px;">
                <span>${used}/${limit}</span>
                <span style="color:${pct >= 100 ? '#ef4444' : '#64748b'}; font-weight:800;">${pct}%</span>
              </div>
              <div style="background:#e2e8f0; height:5px; border-radius:99px; overflow:hidden;">
                <div style="background:${pct >= 100 ? '#ef4444' : (pct >= 80 ? '#f59e0b' : '#16a34a')}; width:${pct}%; height:100%;"></div>
              </div>
            </div>
          ` : `<span style="font-weight:750; color:#1e293b;">${used} / ∞</span>`;

          const endDateText = v.end_date ? formatVietnamDateTime(v.end_date) : '<span style="color:#64748b;">Vô hạn</span>';

          return `
            <tr>
              <td>
                <span class="vc-copy-pill" onclick="window.copyVoucherCodeAdmin('${escapeHtml(v.code)}')" title="Click để sao chép mã">
                  <span>📋</span> ${escapeHtml(v.code)}
                </span>
                ${parseInt(v.is_birthday_voucher, 10) === 1 ? '<span style="display:block; font-size:10px; color:#b45309; margin-top:2px;">🎂 Voucher Sinh Nhật</span>' : ''}
              </td>
              <td>
                <strong style="display:block; font-size:12.5px; color:#1e293b;">${escapeHtml(v.title)}</strong>
                <small style="color:#64748b; font-size:11px;">${escapeHtml(v.description || '')}</small>
              </td>
              <td>${typeBadge}</td>
              <td>${discText}</td>
              <td>${money(v.min_order_value || 0)}</td>
              <td>${v.max_discount_amount ? money(v.max_discount_amount) : '<span style="color:#94a3b8;">—</span>'}</td>
              <td>${usageDisplay}</td>
              <td>${reqTier}</td>
              <td style="font-size:11.5px; color:#64748b;">${endDateText}</td>
              <td>${statusBadge}</td>
              <td style="text-align:right;">
                <div class="row-actions" style="justify-content:flex-end;">
                  <button type="button" class="action-button" onclick="window.openEditVoucherModal(${v.id})" title="Chỉnh sửa voucher">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 5 5M4 20l4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11z"/></svg>Sửa
                  </button>
                  <button type="button" class="action-button" onclick="window.toggleVoucherStatus(${v.id})" title="${parseInt(v.is_active, 10) === 1 ? 'Khóa voucher' : 'Kích hoạt voucher'}">
                    ${parseInt(v.is_active, 10) === 1 ? '🔒 Khóa' : '🔓 Mở'}
                  </button>
                  <button type="button" class="action-button delete" onclick="window.deleteVoucher(${v.id})" title="Xóa voucher">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          `;
        }).join('');
      }

      window.selectVoucherTypeModal = function(type) {
        const select = document.getElementById('v-field-type');
        if (select) {
          select.value = type;
        }
        document.querySelectorAll('#vc-modal-type-selector .vc-type-card').forEach(card => {
          card.classList.toggle('active', card.getAttribute('data-type') === type);
        });
        updateVoucherFormTypeLabels();
        updateLiveVoucherPreview();
      };

      window.updateLiveVoucherPreview = function() {
        const type = document.getElementById('v-field-type')?.value || 'percentage';
        const discVal = parseFloat(document.getElementById('v-field-disc-val')?.value) || 0;
        const code = (document.getElementById('v-field-code')?.value || 'FASTCODE').trim().toUpperCase();
        const title = (document.getElementById('v-field-title')?.value || 'Tiêu đề voucher').trim();
        const minOrder = parseFloat(document.getElementById('v-field-min-order')?.value) || 0;
        const maxDisc = parseFloat(document.getElementById('v-field-max-disc')?.value) || 0;
        const tier = document.getElementById('v-field-tier')?.value || 'ALL';

        const iconEl = document.getElementById('vc-prev-icon');
        const discEl = document.getElementById('vc-prev-discount');
        const subEl = document.getElementById('vc-prev-stub-label');
        const titleEl = document.getElementById('vc-prev-title');
        const codeEl = document.getElementById('vc-prev-code');
        const condEl = document.getElementById('vc-prev-cond');
        const tierEl = document.getElementById('vc-prev-tier');
        const stubEl = document.getElementById('vc-prev-stub');

        if (codeEl) codeEl.textContent = code;
        if (titleEl) titleEl.textContent = title;
        if (tierEl) {
          tierEl.textContent = tier === 'ALL' ? '👑 Tất cả thành viên' : `👑 Từ Hạng ${tier}+`;
        }

        let condText = minOrder > 0 ? `Đơn tối thiểu ${money(minOrder)}` : 'Áp dụng mọi đơn hàng';
        if (type === 'percentage' && maxDisc > 0) {
          condText += ` (Tối đa ${money(maxDisc)})`;
        }
        if (condEl) condEl.textContent = condText;

        if (type === 'percentage') {
          if (iconEl) iconEl.textContent = '🔥';
          if (discEl) discEl.textContent = `${discVal}%`;
          if (subEl) subEl.textContent = 'GIẢM GIÁ';
          if (stubEl) stubEl.style.background = 'linear-gradient(135deg, #f97316, #ea580c)';
        } else if (type === 'fixed') {
          if (iconEl) iconEl.textContent = '💰';
          if (discEl) discEl.textContent = discVal >= 1000 ? `${Math.round(discVal / 1000)}k` : `${discVal}đ`;
          if (subEl) subEl.textContent = 'TIỀN MẶT';
          if (stubEl) stubEl.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        } else if (type === 'free_shipping') {
          if (iconEl) iconEl.textContent = '🚚';
          if (discEl) discEl.textContent = '0đ';
          if (subEl) subEl.textContent = 'FREESHIP';
          if (stubEl) stubEl.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        } else if (type === 'bogo') {
          if (iconEl) iconEl.textContent = '🍔';
          if (discEl) discEl.textContent = '1+1';
          if (subEl) subEl.textContent = 'TẶNG MÓN';
          if (stubEl) stubEl.style.background = 'linear-gradient(135deg, #ec4899, #db2777)';
        }
      };

      window.openCreateVoucherModal = function() {
        document.getElementById('voucher-dialog-title').textContent = 'Tạo Voucher Mới';
        document.getElementById('voucher-admin-form').reset();
        document.getElementById('v-field-id').value = '';
        generateRandomVoucherCode();
        selectVoucherTypeModal('percentage');
        
        const nowStr = new Date().toISOString().slice(0, 16);
        document.getElementById('v-field-start').value = nowStr;

        updateLiveVoucherPreview();
        vcDialog?.showModal();
      };

      window.openEditVoucherModal = function(id) {
        const v = adminVouchersList.find(item => parseInt(item.id, 10) === parseInt(id, 10));
        if (!v) return;

        document.getElementById('voucher-dialog-title').textContent = `Chỉnh Sửa Voucher #${v.id} (${v.code})`;
        document.getElementById('v-field-id').value = v.id;
        document.getElementById('v-field-code').value = v.code;
        document.getElementById('v-field-title').value = v.title || '';
        document.getElementById('v-field-desc').value = v.description || '';
        document.getElementById('v-field-disc-val').value = v.discount_value || 0;
        document.getElementById('v-field-min-order').value = v.min_order_value || 0;
        document.getElementById('v-field-max-disc').value = v.max_discount_amount || '';
        document.getElementById('v-field-usage-limit').value = v.usage_limit || 0;
        document.getElementById('v-field-user-limit').value = v.user_limit || 1;
        document.getElementById('v-field-tier').value = v.min_member_tier || 'ALL';
        document.getElementById('v-field-public').checked = parseInt(v.is_public, 10) === 1;
        document.getElementById('v-field-active').checked = parseInt(v.is_active, 10) === 1;

        if (v.start_date) {
          document.getElementById('v-field-start').value = v.start_date.replace(' ', 'T').slice(0, 16);
        }
        if (v.end_date) {
          document.getElementById('v-field-end').value = v.end_date.replace(' ', 'T').slice(0, 16);
        } else {
          document.getElementById('v-field-end').value = '';
        }

        selectVoucherTypeModal(v.voucher_type || 'percentage');
        updateLiveVoucherPreview();
        vcDialog?.showModal();
      };

      window.closeVoucherAdminModal = function() {
        vcDialog?.close();
      };

      window.generateRandomVoucherCode = function() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = 'FAST';
        for (let i = 0; i < 4; i++) {
          code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('v-field-code').value = code;
        updateLiveVoucherPreview();
      };

      window.updateVoucherFormTypeLabels = function() {
        const type = document.getElementById('v-field-type')?.value;
        const lbl = document.getElementById('lbl-discount-val');
        const input = document.getElementById('v-field-disc-val');
        if (!lbl || !input) return;

        if (type === 'percentage') {
          lbl.textContent = 'Mức giảm (%) *';
          input.placeholder = 'VD: 15 (nghĩa là giảm 15%)';
        } else if (type === 'free_shipping') {
          lbl.textContent = 'Mức giảm phí ship tối đa (VNĐ) *';
          input.placeholder = 'VD: 18000';
          if (!input.value || input.value === '0') input.value = '18000';
        } else if (type === 'bogo') {
          lbl.textContent = 'Giá trị món tặng quy đổi (VNĐ) *';
          input.placeholder = 'VD: 35000';
        } else {
          lbl.textContent = 'Số tiền giảm cố định (VNĐ) *';
          input.placeholder = 'VD: 20000';
        }
      };

      window.saveVoucherAdminForm = async function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('btn-save-voucher-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang lưu...';

        const voucherData = {
          id: document.getElementById('v-field-id').value || null,
          code: document.getElementById('v-field-code').value.trim().toUpperCase(),
          title: document.getElementById('v-field-title').value.trim(),
          description: document.getElementById('v-field-desc').value.trim(),
          voucher_type: document.getElementById('v-field-type').value,
          discount_value: parseFloat(document.getElementById('v-field-disc-val').value) || 0,
          min_order_value: parseFloat(document.getElementById('v-field-min-order').value) || 0,
          max_discount_amount: document.getElementById('v-field-max-disc').value ? parseFloat(document.getElementById('v-field-max-disc').value) : null,
          usage_limit: parseInt(document.getElementById('v-field-usage-limit').value, 10) || 0,
          user_limit: parseInt(document.getElementById('v-field-user-limit').value, 10) || 1,
          min_member_tier: document.getElementById('v-field-tier').value,
          is_public: document.getElementById('v-field-public').checked ? 1 : 0,
          is_active: document.getElementById('v-field-active').checked ? 1 : 0,
          start_date: document.getElementById('v-field-start').value || null,
          end_date: document.getElementById('v-field-end').value || null,
        };

        try {
          const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_admin_voucher', voucher: voucherData })
          });
          const result = await res.json();

          if (result.success) {
            closeVoucherAdminModal();
            showToast(result.message || 'Lưu voucher thành công!');
            loadAdminVouchers();
            try { new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'vouchers' }); } catch (e) {}
          } else {
            alert(result.message || 'Lưu voucher thất bại.');
          }
        } catch (err) {
          alert('Lỗi kết nối máy chủ: ' + err.message);
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = '💾 Lưu Voucher';
        }
      };

      window.toggleVoucherStatus = async function(id) {
        try {
          const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_voucher_status', id: id })
          });
          const result = await res.json();
          if (result.success) {
            showToast(result.message);
            loadAdminVouchers();
            try { new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'vouchers' }); } catch (e) {}
          } else {
            alert(result.message || 'Không thể đổi trạng thái.');
          }
        } catch (err) {
          alert('Lỗi kết nối: ' + err.message);
        }
      };

      window.deleteVoucher = async function(id) {
        if (!confirm('Bạn có chắc chắn muốn xóa voucher này? Nếu voucher đã phát sinh đơn hàng, hệ thống sẽ tự động chuyển sang trạng thái KHÓA để bảo toàn lịch sử hóa đơn.')) return;

        try {
          const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_admin_voucher', id: id })
          });
          const result = await res.json();
          if (result.success) {
            showToast(result.message);
            loadAdminVouchers();
            try { new BroadcastChannel('fastfood_realtime').postMessage({ type: 'content:updated', collection: 'vouchers' }); } catch (e) {}
          } else {
            alert(result.message || 'Không thể xóa voucher.');
          }
        } catch (err) {
          alert('Lỗi kết nối: ' + err.message);
        }
      };

      window.triggerBirthdayCronAdmin = async function() {
        const btn = document.getElementById('btn-trigger-bday-cron');
        if (btn) btn.disabled = true;

        try {
          const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'trigger_birthday_cron' })
          });
          const result = await res.json();
          if (result.success) {
            alert(result.message);
            loadAdminVouchers();
          } else {
            alert(result.message || 'Lỗi khi quét sinh nhật.');
          }
        } catch (err) {
          alert('Lỗi kết nối: ' + err.message);
        } finally {
          if (btn) btn.disabled = false;
        }
      };

      // Voucher Toolbar & Live Preview Event Bindings
      document.getElementById('btn-open-create-voucher')?.addEventListener('click', openCreateVoucherModal);
      document.getElementById('btn-trigger-bday-cron')?.addEventListener('click', triggerBirthdayCronAdmin);

      let vcSearchTimeout = null;
      document.getElementById('vc-search-input')?.addEventListener('input', (e) => {
        clearTimeout(vcSearchTimeout);
        const clearBtn = document.getElementById('vc-search-clear');
        if (clearBtn) clearBtn.style.display = e.target.value.trim() ? 'flex' : 'none';
        vcSearchTimeout = setTimeout(loadAdminVouchers, 300);
      });
      document.getElementById('vc-search-clear')?.addEventListener('click', () => {
        const sInput = document.getElementById('vc-search-input');
        if (sInput) sInput.value = '';
        document.getElementById('vc-search-clear')?.style.setProperty('display', 'none');
        loadAdminVouchers();
      });
      document.getElementById('vc-filter-type')?.addEventListener('change', loadAdminVouchers);
      document.getElementById('vc-filter-tier')?.addEventListener('change', loadAdminVouchers);
      document.getElementById('vc-filter-status')?.addEventListener('change', loadAdminVouchers);

      document.getElementById('vc-btn-reset-filters')?.addEventListener('click', () => {
        const sInput = document.getElementById('vc-search-input');
        const tSelect = document.getElementById('vc-filter-type');
        const tierSelect = document.getElementById('vc-filter-tier');
        const stSelect = document.getElementById('vc-filter-status');
        if (sInput) sInput.value = '';
        if (tSelect) tSelect.value = '';
        if (tierSelect) tierSelect.value = '';
        if (stSelect) stSelect.value = '';
        document.getElementById('vc-search-clear')?.style.setProperty('display', 'none');
        document.getElementById('vc-btn-reset-filters')?.style.setProperty('display', 'none');
        [tSelect, tierSelect, stSelect].forEach(el => el?.classList.remove('is-active-filter'));
        loadAdminVouchers();
      });

      ['v-field-code', 'v-field-title', 'v-field-disc-val', 'v-field-min-order', 'v-field-max-disc', 'v-field-tier'].forEach(fId => {
        const el = document.getElementById(fId);
        el?.addEventListener('input', updateLiveVoucherPreview);
        el?.addEventListener('change', updateLiveVoucherPreview);
      });

      document.getElementById('today-label').textContent = new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).format(new Date());
      renderAll();
      loadOrderReviews({ silent: true });
      loadAdminNotifications();
      dashboardReady = true;
      pendingOrderEvents.forEach((event) => {
        if (event.type === 'new') handleNewOrder(event.order);
        else handleOrderUpdate(event.update);
      });
      pendingPaymentEvents.forEach(handlePaymentUpdate);
    })();
  </script>

  <!-- Admin Chat Lightbox Modal for Image Zoom -->
  <div class="admin-chat-lightbox-modal" id="admin-chat-lightbox-modal" hidden role="dialog" aria-modal="true" aria-label="Xem ảnh phóng to">
    <div class="admin-lightbox-backdrop" id="admin-lightbox-backdrop"></div>
    <div class="admin-lightbox-container">
      <img id="admin-lightbox-image" src="" alt="Ảnh phóng to" />
      <button type="button" class="admin-lightbox-close" id="admin-lightbox-close" aria-label="Đóng (Esc)">✕</button>
    </div>
  </div>
</body>

</html>