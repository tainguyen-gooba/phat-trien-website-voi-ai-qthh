<?php
/**
 * FASTFOOD - Món ăn & Quán ăn yêu thích (Favorites)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Yêu Thích Của Bạn - FASTFOOD';
$activeNav = 'favorites';
$extraCss  = ['css/pages.css'];

$db = getDB();
$favProducts = $db->query("SELECT p.*, c.name AS category_name, r.name AS restaurant_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                           ORDER BY p.rating_avg DESC LIMIT 6")->fetchAll();

$favRestaurants = $db->query("SELECT * FROM restaurants ORDER BY rating_avg DESC LIMIT 4")->fetchAll();

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Yêu thích</span>
    </div>
    <h1 class="page-title">Món Ăn & Quán Ăn Yêu Thích</h1>
    <p class="restaurants-page-intro" style="color:var(--text-muted); margin-top:0.35rem;">
      Các món ăn và quán ăn nổi bật bạn quan tâm trên hệ thống FASTFOOD.
    </p>
  </div>
</div>

<main class="container" style="margin-top: 2rem; margin-bottom: 5rem;">
  <section class="favorites-section" aria-label="Danh sách yêu thích">
    <div class="favorites-content" id="favorites-content">
      <div class="favorites-tabs" style="display:flex; gap:1rem; margin-bottom:1.5rem; border-bottom:2px solid #f1f5f9; padding-bottom:0.75rem;">
        <button type="button" class="fav-tab active btn btn-primary btn-sm" onclick="switchFavTab('products', this)">Món Ăn Yêu Thích (<?= count($favProducts) ?>)</button>
        <button type="button" class="fav-tab btn btn-outline btn-sm" onclick="switchFavTab('restaurants', this)">Quán Ăn Yêu Thích (<?= count($favRestaurants) ?>)</button>
      </div>

      <!-- Favorite Products Tab -->
      <div class="fav-tab-panel active" id="fav-products">
        <div class="grid grid-cols-3" id="favorite-products-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1.25rem;">
          <?php foreach ($favProducts as $prod): ?>
            <div class="card product-card">
              <a href="<?= getUrl('food-detail.php?id=' . $prod['id']) ?>" class="product-thumb-link">
                <img src="<?= escapeHtml($prod['image_url'] ?: getAssetUrl('images/placeholder-food.jpg')) ?>" alt="<?= escapeHtml($prod['name']) ?>" class="product-thumb">
              </a>
              <div class="product-body" style="padding:1rem;">
                <span class="product-category" style="font-size:0.75rem; color:var(--text-muted); font-weight:700;"><?= escapeHtml($prod['category_name']) ?></span>
                <h3 class="product-title" style="margin:0.25rem 0 0.5rem; font-size:1.05rem;">
                  <a href="<?= getUrl('food-detail.php?id=' . $prod['id']) ?>"><?= escapeHtml($prod['name']) ?></a>
                </h3>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.75rem;">
                  <span class="product-price" style="font-size:1.15rem; font-weight:800; color:var(--primary);"><?= formatVND($prod['discount_price'] ?: $prod['base_price']) ?></span>
                  <a href="<?= getUrl('food-detail.php?id=' . $prod['id']) ?>" class="btn btn-primary btn-sm">Xem Chi Tiết</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Favorite Restaurants Tab -->
      <div class="fav-tab-panel" id="fav-restaurants" style="display:none;">
        <div class="grid grid-cols-2" id="favorite-restaurants-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:1.25rem;">
          <?php foreach ($favRestaurants as $res): ?>
            <div class="card" style="padding:1.25rem; display:flex; gap:1rem; align-items:center; background:#fff; border-radius:12px; border:1px solid #f1f5f9;">
              <div style="font-size:2.5rem; background:#fff7ed; width:60px; height:60px; border-radius:12px; display:grid; place-items:center;">🏪</div>
              <div style="flex:1;">
                <h3 style="font-size:1.05rem; margin:0 0 0.25rem; font-weight:700;"><?= escapeHtml($res['name']) ?></h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 0.5rem;">📍 <?= escapeHtml($res['address']) ?></p>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                  <span style="color:#f59e0b; font-weight:700; font-size:0.85rem;">⭐ <?= number_format($res['rating_avg'], 1) ?></span>
                  <span style="color:#10b981; font-weight:600; font-size:0.8rem; background:#ecfdf5; padding:2px 8px; border-radius:999px;">Đang mở</span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
function switchFavTab(tab, btn) {
  document.querySelectorAll('.fav-tab').forEach(b => {
    b.classList.remove('active', 'btn-primary');
    b.classList.add('btn-outline');
  });
  btn.classList.add('active', 'btn-primary');
  btn.classList.remove('btn-outline');
  document.getElementById('fav-products').style.display = (tab === 'products') ? 'block' : 'none';
  document.getElementById('fav-restaurants').style.display = (tab === 'restaurants') ? 'block' : 'none';
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

