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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= escapeHtml($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= getAssetUrl('assets/images/logo.svg') ?>">

  <!-- CSS Design System -->
  <link rel="stylesheet" href="<?= getAssetUrl('css/variables.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/reset.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/base.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/components.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/responsive.css') ?>">
  <link rel="stylesheet" href="<?= getAssetUrl('css/chatbot.css') ?>">
  <?php if (!empty($extraCss)): ?>
    <?php foreach ((array)$extraCss as $css): ?>
      <link rel="stylesheet" href="<?= getAssetUrl($css) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>

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
        <a href="<?= getUrl('restaurants.php') ?>" class="nav-link <?= $activeNav === 'restaurants' ? 'active' : '' ?>">Nhà Hàng</a>
        <a href="<?= getUrl('foods.php') ?>" class="nav-link <?= $activeNav === 'foods' ? 'active' : '' ?>">Món Ăn</a>
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
            <span id="nav-favorite-badge" class="cart-counter" style="display:none;">0</span>
          </a>
        <?php endif; ?>

        <!-- User Session / Login -->
        <?php if ($currentUser): ?>
          <div class="user-dropdown-wrap" id="user-dropdown-wrap">
            <button type="button" class="user-menu-pill" id="user-menu-trigger" aria-expanded="false" aria-haspopup="true" onclick="toggleUserDropdown(event)" title="Tài khoản: <?= escapeHtml($currentUser['full_name']) ?>">
              <img src="<?= escapeHtml($currentUser['avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80') ?>" alt="Avatar" class="user-avatar-mini" style="object-fit:cover;">
              <span><?= escapeHtml(explode(' ', $currentUser['full_name'])[count(explode(' ', $currentUser['full_name'])) - 1] ?? 'Tài Khoản') ?></span>
              <svg class="dropdown-arrow-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>

            <div class="user-dropdown-menu" id="user-dropdown-menu">
              <!-- Header info in dropdown -->
              <div class="user-dropdown-header">
                <img src="<?= escapeHtml($currentUser['avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80') ?>" alt="Avatar" class="user-dropdown-avatar">
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
                  <li>
                    <a href="<?= getUrl('admin/index.php') ?>" class="user-dropdown-item item-highlight">
                      <span class="dropdown-item-icon">⚡</span>
                      <div class="dropdown-item-text">
                        <strong>Trang Quản Trị</strong>
                        <small>Đơn hàng, doanh số & món ăn</small>
                      </div>
                    </a>
                  </li>
                <?php endif; ?>

                <li>
                  <a href="<?= getUrl('profile.php') ?>" class="user-dropdown-item">
                    <span class="dropdown-item-icon">👤</span>
                    <div class="dropdown-item-text">
                      <strong>Hồ sơ tài khoản</strong>
                      <small>Xem và sửa thông tin cá nhân</small>
                    </div>
                  </a>
                </li>

                <li>
                  <a href="<?= getUrl('order-history.php') ?>" class="user-dropdown-item">
                    <span class="dropdown-item-icon">📦</span>
                    <div class="dropdown-item-text">
                      <strong>Lịch sử đơn hàng</strong>
                      <small>Theo dõi tình trạng đơn đặt món</small>
                    </div>
                  </a>
                </li>

                <li>
                  <a href="<?= getUrl('favorites.php') ?>" class="user-dropdown-item">
                    <span class="dropdown-item-icon">💖</span>
                    <div class="dropdown-item-text">
                      <strong>Món ăn yêu thích</strong>
                      <small>Danh sách các món đã lưu</small>
                    </div>
                  </a>
                </li>
              </ul>

              <div class="user-dropdown-divider"></div>

              <div class="user-dropdown-footer">
                <a href="<?= getUrl('logout.php') ?>" class="user-dropdown-logout-btn">
                  <span class="dropdown-item-icon">🚪</span>
                  <strong>Đăng Xuất</strong>
                </a>
              </div>
            </div>
          </div>

          <?php if ($isManager): ?>
            <a href="<?= getUrl('admin/index.php') ?>" class="btn btn-primary btn-sm"><?= isAdmin() ? 'Quản trị' : 'Quản lý' ?></a>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?= getUrl('login.php') ?>" class="btn btn-outline btn-sm">Đăng Nhập</a>
          <a href="<?= getUrl('register.php') ?>" class="btn btn-primary btn-sm">Đăng Ký</a>
        <?php endif; ?>

        <!-- Mobile Hamburger Toggle -->
        <button type="button" class="mobile-nav-toggle" id="mobile-menu-btn" onclick="UIComponents.toggleMobileMenu()">☰</button>
      </div>
    </div>
  </header>
  <script>
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
      if (wrap && !wrap.contains(e.target)) {
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

