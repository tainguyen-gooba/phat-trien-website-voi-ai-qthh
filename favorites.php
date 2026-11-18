<?php
/**
 * FASTFOOD - Món ăn yêu thích (Favorites)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Món Ăn Yêu Thích - FASTFOOD';
$activeNav = 'favorites';
$extraCss  = ['css/pages.css'];

$db = getDB();
$favProducts = $db->query("SELECT p.*, c.name AS category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           ORDER BY p.rating_avg DESC LIMIT 8")->fetchAll();

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Yêu thích</span>
    </div>
    <h1 class="page-title">Món Ăn Yêu Thích</h1>
    <p class="restaurants-page-intro" style="color:var(--text-muted); margin-top:0.35rem;">
      Các món ăn thơm ngon, hấp dẫn được bạn và thực khách yêu thích nhất tại FASTFOOD.
    </p>
  </div>
</div>

<main class="container" style="margin-top: 2rem; margin-bottom: 5rem;">
  <section class="favorites-section" aria-label="Danh sách món ăn yêu thích">
    <div class="grid grid-cols-4" id="favorite-products-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1.25rem;">
      <?php foreach ($favProducts as $prod): ?>
        <?= renderProductCard($prod) ?>
      <?php endforeach; ?>
    </div>
  </section>
</main>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
