<?php
/**
 * FASTFOOD - Universal Header Template
 */
require_once dirname(__DIR__) . '/config/config.php';

$pageTitle = $pageTitle ?? 'FASTFOOD - Đặt Đồ Ăn Trực Tuyến Nhanh Chóng & Chuẩn Vị';
$activeNav = $activeNav ?? 'home';
$currentUser = currentUser();
$isManager = isAdminOrStaff();

// Đếm số lượng món trong giỏ hàng (Session)
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? 1);
    }
}
$favCount = 0;
if (!empty($_SESSION['user_favorites']) && is_array($_SESSION['user_favorites'])) {
    $favCount = count($_SESSION['user_favorites']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= escapeHtml($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= getAssetUrl('assets/images/logo.svg') ?>">

  <!-- Google Fonts - Chuẩn tiếng Việt không lỗi phông -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- CSS Design System -->
  <link rel="stylesheet" href="<?= getAssetUrl('css/variables.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/reset.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/base.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/components.css') ?>?v=<?= @filemtime(__DIR__ . '/../css/components.css') ?: '20261126' ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/responsive.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/chatbot.css') ?>?v=<?= @filemtime(__DIR__ . '/../css/chatbot.css') ?: '20261126' ?>">
  <?php if (!empty($extraCss)): ?>
    <?php foreach ((array)$extraCss as $css): ?>
      <link rel="stylesheet" href="<?= getAssetUrl($css) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>

  <?php
  $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
  $isAuthScreen = in_array($currentScript, ['login.php', 'register.php']) || !empty($hideHeader);
  ?>

  <?php if (!$isAuthScreen): ?>
  <!-- Universal Header -->
  <header class="site-header" id="site-header">
    <div class="container navbar">
      <!-- Brand Logo -->
      <a href="<?= getUrl('index.php') ?>" class="brand-logo">
        <img src="<?= getAssetUrl('assets/images/logo.svg') ?>" alt="FASTFOOD Buôn Ma Thuột" class="brand-logo-img">
      </a>

      <!-- Header Search Bar -->
      <form action="<?= getUrl('foods.php') ?>" method="GET" class="header-search-wrap">
        <svg class="header-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#888888" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="search" id="header-search-input" class="header-search-input" 
               placeholder="Tìm món ăn, trà sữa, pizza..." value="<?= escapeHtml($_GET['search'] ?? '') ?>">
      </form>

      <!-- Navigation Menu Links -->
      <nav class="nav-menu" id="main-nav-menu">
        <a href="<?= getUrl('index.php') ?>" class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">Trang Chủ</a>
        <a href="<?= getUrl('foods.php') ?>" class="nav-link <?= $activeNav === 'foods' ? 'active' : '' ?>">Món Ăn</a>
        <a href="<?= getUrl('vouchers.php') ?>" class="nav-link <?= $activeNav === 'vouchers' ? 'active' : '' ?>">Khuyến Mãi</a>
        <a href="<?= getUrl('order-history.php') ?>" class="nav-link <?= $activeNav === 'orders' ? 'active' : '' ?>">Đơn Hàng</a>
      </nav>

      <!-- Right Actions Controls -->
      <div class="header-actions">
        <?php if (!$isManager): ?>
          <!-- Cart Link -->
          <a href="<?= getUrl('cart.php') ?>" class="cart-btn-link" title="Xem giỏ hàng">
            🛒
            <span id="nav-cart-badge" class="cart-counter" style="<?= $cartCount > 0 ? '' : 'display:none;' ?>"><?= $cartCount ?></span>
          </a>

          <!-- Favorite Link -->
          <a href="<?= getUrl('favorites.php') ?>" class="favorite-nav-btn" title="Yêu thích của bạn">
            ♡
            <span id="nav-favorite-badge" class="cart-counter" style="<?= $favCount > 0 ? '' : 'display:none;' ?>"><?= $favCount ?></span>
          </a>
        <?php endif; ?>

        <!-- User Session / Login -->
        <?php if ($currentUser): ?>
          <div class="user-dropdown-wrap" id="user-dropdown-wrap">
            <button type="button" class="user-menu-pill" id="user-menu-trigger" aria-expanded="false" aria-haspopup="true" onclick="toggleUserDropdown(event)" title="Tài khoản: <?= escapeHtml($currentUser['full_name']) ?>">
              <img src="<?= escapeHtml(getAvatarUrl($currentUser['avatar_url'] ?? $currentUser['avatar'] ?? '', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80')) ?>" alt="Avatar" class="user-avatar-mini" style="object-fit:cover;">
              <span><?= escapeHtml(explode(' ', $currentUser['full_name'])[count(explode(' ', $currentUser['full_name'])) - 1] ?? 'Tài Khoản') ?></span>
              <svg class="dropdown-arrow-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>

            <div class="user-dropdown-menu" id="user-dropdown-menu">
              <!-- Header info in dropdown -->
              <div class="user-dropdown-header">
                <img src="<?= escapeHtml(getAvatarUrl($currentUser['avatar_url'] ?? $currentUser['avatar'] ?? '', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80')) ?>" alt="Avatar" class="user-dropdown-avatar">
                <div class="user-dropdown-info">
                  <strong class="user-dropdown-name"><?= escapeHtml($currentUser['full_name']) ?></strong>
                  <span class="user-dropdown-email"><?= escapeHtml($currentUser['email']) ?></span>
                  <span class="user-dropdown-role-badge <?= $isManager ? 'role-badge-admin' : 'role-badge-user' ?>">
                    <?= $isManager ? (isAdmin() ? '⭐ Quản Trị Viên' : '🛡️ Quản Lý Hệ Thống') : '👤 Khách Hàng' ?>
                  </span>
                </div>
              </div>

              <div class="user-dropdown-divider"></div>

              <ul class="user-dropdown-list">
                <?php if ($isManager): ?>
                  <!-- Dành cho Admin / Quản trị viên -->
                  <li>
                    <a href="<?= getUrl('admin/index.php') ?>" class="user-dropdown-item item-highlight">
                      <div class="dropdown-item-text">
                        <strong>Dashboard</strong>
                        <small>Bảng điều khiển &amp; doanh số</small>
                      </div>
                    </a>
                  </li>
                <?php else: ?>
                  <!-- Dành cho Người dùng / Khách hàng (bỏ trang quản trị) -->
                  <li>
                    <a href="<?= getUrl('profile.php') ?>" class="user-dropdown-item">
                      <div class="dropdown-item-text">
                        <strong>Hồ sơ tài khoản</strong>
                        <small>Xem và sửa thông tin cá nhân</small>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="<?= getUrl('vouchers.php#tab-wallet') ?>" class="user-dropdown-item">
                      <div class="dropdown-item-text">
                        <strong>Ví Voucher</strong>
                        <small>Kho voucher &amp; ưu đãi của bạn</small>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="<?= getUrl('order-history.php') ?>" class="user-dropdown-item">
                      <div class="dropdown-item-text">
                        <strong>Lịch sử đơn hàng</strong>
                        <small>Theo dõi tình trạng đơn đặt món</small>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="<?= getUrl('favorites.php') ?>" class="user-dropdown-item">
                      <div class="dropdown-item-text">
                        <strong>Món ăn yêu thích</strong>
                        <small>Danh sách các món đã lưu</small>
                      </div>
                    </a>
                  </li>
                <?php endif; ?>
              </ul>

              <div class="user-dropdown-divider"></div>

              <div class="user-dropdown-footer">
                <a href="<?= getUrl('logout.php') ?>" class="user-dropdown-logout-btn">
                  <strong>Đăng Xuất</strong>
                </a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <a href="<?= getUrl('login.php') ?>" class="btn btn-outline btn-sm">Đăng Nhập</a>
          <a href="<?= getUrl('register.php') ?>" class="btn btn-primary btn-sm">Đăng Ký</a>
        <?php endif; ?>

        <!-- Mobile Hamburger Toggle -->
        <button type="button" class="mobile-nav-toggle" id="mobile-menu-btn" onclick="UIComponents.toggleMobileMenu()">☰</button>
      </div>
    </div>
  </header>
  <?php endif; ?>
  <script>
    window.BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.getApiUrl = function(path) {
      const base = (window.BASE_URL || '').replace(/\/+$/, '');
      const clean = String(path || '').replace(/^\/+/, '');
      return base ? `${base}/${clean}` : `/${clean}`;
    };
    window.FASTFOOD_INITIAL_FAVORITES = <?= json_encode($_SESSION['user_favorites'] ?? []) ?>;
    window.FASTFOOD_INITIAL_CART_COUNT = <?= (int)$cartCount ?>;

    // Phân lập giỏ hàng theo tài khoản người dùng
    if (typeof sessionStorage !== 'undefined') {
      const curUser = <?= json_encode($currentUser ? (int)$currentUser['id'] : 0) ?>;
      const lastUser = sessionStorage.getItem('fastfood_cart_user');
      if (lastUser !== null && String(lastUser) !== String(curUser)) {
        sessionStorage.removeItem('fastfood_cart');
      }
      sessionStorage.setItem('fastfood_cart_user', String(curUser));
    }
    // Dọn dẹp key cũ không phân quyền
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem('fastfood_user_favorites');
    }

    window.FASTFOOD_USER = <?= json_encode($currentUser ? [
        'id'        => (int)$currentUser['id'],
        'full_name' => $currentUser['full_name'],
        'name'      => $currentUser['full_name'],
        'email'     => $currentUser['email'] ?? '',
        'phone'     => $currentUser['phone'] ?? '',
        'role'      => $currentUser['role'] ?? 'customer',
        'role_id'   => (int)($currentUser['role_id'] ?? 4),
        'avatar'    => $currentUser['avatar_url'] ?? ''
    ] : null, JSON_UNESCAPED_UNICODE) ?>;

    function toggleUserDropdown(e) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      const wrap = document.getElementById('user-dropdown-wrap');
      if (!wrap) return;
      const isOpen = wrap.classList.toggle('open');
      const trigger = document.getElementById('user-menu-trigger');
      if (trigger) trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    document.addEventListener('click', function(e) {
      const wrap = document.getElementById('user-dropdown-wrap');
      if (!wrap) return;
      const menu = document.getElementById('user-dropdown-menu');
      if (menu && menu.contains(e.target) && !e.target.closest('a')) {
        return;
      }
      if (!wrap.contains(e.target) || e.target.closest('a')) {
        wrap.classList.remove('open');
        const trigger = document.getElementById('user-menu-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const wrap = document.getElementById('user-dropdown-wrap');
        if (wrap) {
          wrap.classList.remove('open');
          const trigger = document.getElementById('user-menu-trigger');
          if (trigger) trigger.setAttribute('aria-expanded', 'false');
        }
      }
    });
  </script>

