<?php
/**
 * FASTFOOD - Danh sách nhà hàng đối tác (Restaurants)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Nhà Hàng & Quán Ăn Đối Tác - FASTFOOD';
$activeNav = 'restaurants';
$extraCss  = ['css/pages.css'];

$db = getDB();
$restaurants = $db->query("SELECT * FROM restaurants WHERE is_active = 1 ORDER BY rating_avg DESC, id ASC")->fetchAll();

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Nhà hàng</span>
    </div>
    <h1 class="page-title">Nhà Hàng & Quán Ăn Đối Tác</h1>
  </div>
</div>

<main class="container" style="margin-top: 2.5rem; margin-bottom: 5rem;">
  <div class="grid grid-cols-2">
    <?php foreach ($restaurants as $r): ?>
      <div class="restaurant-card" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
        <img src="<?= escapeHtml($r['banner_url'] ?: 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&auto=format&fit=crop&q=80') ?>" 
             alt="<?= escapeHtml($r['name']) ?>" style="width:100%; height:200px; object-fit:cover;">
        <div class="restaurant-card-body" style="padding:1.5rem; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">
              <h3 style="font-size:1.25rem; font-weight:800; margin:0;"><?= escapeHtml($r['name']) ?></h3>
              <span style="color:var(--accent-gold); font-weight:800; font-size:0.95rem;">★ <?= number_format((float)$r['rating_avg'], 1) ?></span>
            </div>
            <p style="color:var(--text-muted); font-size:0.88rem; margin:0 0 0.5rem;">📍 <?= escapeHtml($r['address']) ?></p>
            <p style="color:var(--text-secondary); font-size:0.85rem; margin:0 0 1rem;">
              ⏰ Mở cửa: <?= escapeHtml($r['opening_time'] ?: '07:00') ?> - <?= escapeHtml($r['closing_time'] ?: '22:30') ?>
            </p>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:1rem;">
            <span style="font-size:0.85rem; color:var(--text-muted);">Hotline: <strong><?= escapeHtml($r['phone'] ?: '1900 8888') ?></strong></span>
            <a href="<?= getUrl('foods.php?restaurant=' . $r['id']) ?>" class="btn btn-primary btn-sm">Xem thực đơn quán</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

