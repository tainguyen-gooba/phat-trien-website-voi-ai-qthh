<?php
/**
 * FASTFOOD - Thực đơn món ăn (Foods Catalog)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Thực Đơn Món Ăn - FASTFOOD';
$activeNav = 'foods';
$extraCss  = ['css/pages.css'];

$db = getDB();

// Lấy danh sách danh mục để hiển thị sidebar
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC, id ASC")->fetchAll();

// Lọc tham số tìm kiếm & danh mục
$selectedCat = trim($_GET['category'] ?? 'all');
$search      = trim($_GET['search'] ?? '');
$sort        = trim($_GET['sort'] ?? 'default');
$restaurantId = (int)($_GET['restaurant'] ?? 0);

$where = ["p.is_available = 1"];
$params = [];

if ($selectedCat && $selectedCat !== 'all') {
    $where[] = "(c.slug = ? OR c.id = ?)";
    $params[] = $selectedCat;
    $params[] = is_numeric($selectedCat) ? (int)$selectedCat : 0;
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($restaurantId > 0) {
    $where[] = "p.restaurant_id = ?";
    $params[] = $restaurantId;
}

$orderBy = "p.id DESC";
if ($sort === 'price-asc') {
    $orderBy = "COALESCE(NULLIF(p.discount_price, 0), p.base_price) ASC";
} elseif ($sort === 'price-desc') {
    $orderBy = "COALESCE(NULLIF(p.discount_price, 0), p.base_price) DESC";
} elseif ($sort === 'popular') {
    $orderBy = "p.is_featured DESC, p.id DESC";
} elseif ($sort === 'name') {
    $orderBy = "p.name ASC";
}

$sql = "SELECT p.*, r.name AS restaurant_name, c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN restaurants r ON p.restaurant_id = r.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$totalCount = count($products);

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Thực đơn</span>
    </div>
    <h1 class="page-title">Thực Đơn Món Ăn FASTFOOD</h1>
  </div>
</div>

<main class="container">
  <div class="layout-with-sidebar">
    
    <!-- Filter Sidebar -->
    <aside class="filter-sidebar">
      <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:1.25rem;">Lọc Món Ăn</h3>

      <!-- Danh mục -->
      <div class="filter-group">
        <h4 class="filter-group-title">Danh Mục</h4>
        <label class="filter-checkbox-item">
          <input type="radio" name="f-cat" class="food-cat-filter" value="all" <?= $selectedCat === 'all' ? 'checked' : '' ?>
                 onchange="location.href='<?= getUrl('foods.php') ?>'">
          <span>Tất cả danh mục</span>
        </label>
        <?php foreach ($categories as $cat): 
          $val = $cat['slug'] ?: (string)$cat['id'];
          $isChecked = ($selectedCat === $val || $selectedCat == $cat['id']);
        ?>
          <label class="filter-checkbox-item">
            <input type="radio" name="f-cat" class="food-cat-filter" value="<?= escapeHtml($val) ?>" <?= $isChecked ? 'checked' : '' ?>
                   onchange="location.href='<?= getUrl('foods.php?category=' . urlencode($val) . ($search ? '&search=' . urlencode($search) : '')) ?>'">
            <span><?= escapeHtml($cat['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div>
      <div class="content-top-toolbar">
        <form action="<?= getUrl('foods.php') ?>" method="GET" style="flex:1; max-width:320px; display:flex; gap:0.5rem;">
          <?php if ($selectedCat && $selectedCat !== 'all'): ?>
            <input type="hidden" name="category" value="<?= escapeHtml($selectedCat) ?>">
          <?php endif; ?>
          <input type="text" name="search" id="food-search-input" class="form-control" 
                 placeholder="Tìm theo tên món hoặc mô tả..." value="<?= escapeHtml($search) ?>">
          <button type="submit" class="btn btn-primary btn-sm" style="padding:0.4rem 0.8rem;">Tìm</button>
        </form>

        <div style="display:flex; align-items:center; gap:1rem;">
          <span id="food-results-count" style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">
            <?= $totalCount ?> món ăn
          </span>
          <select id="food-sort-select" class="form-control" style="width:auto; padding:0.5rem 1rem;" 
                  onchange="location.href='<?= getUrl('foods.php?category=' . urlencode($selectedCat) . ($search ? '&search=' . urlencode($search) : '') . '&sort=') ?>' + this.value">
            <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>Mặc định</option>
            <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : '' ?>>Giá: Thấp đến cao</option>
            <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Giá: Cao đến thấp</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Món nổi bật nhất</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Tên: A-Z</option>
          </select>
        </div>
      </div>

      <!-- Foods Grid -->
      <?php if (empty($products)): ?>
        <div style="text-align:center; padding:4rem 1rem; color:var(--text-muted);">
          <div style="font-size:3rem; margin-bottom:1rem;">🍽️</div>
          <h3>Không tìm thấy món ăn phù hợp</h3>
          <p>Thử tìm với từ khóa khác hoặc quay lại danh mục tất cả.</p>
          <a href="<?= getUrl('foods.php') ?>" class="btn btn-primary" style="margin-top:1rem;">Xem toàn bộ thực đơn</a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-3" id="foods-grid-container">
          <?php foreach ($products as $p): 
            $hasDiscount = !empty($p['discount_price']) && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['base_price'];
            $currentPrice = $hasDiscount ? $p['discount_price'] : $p['base_price'];
          ?>
            <div class="product-card">
              <div class="product-img-wrap">
                <?php if ($hasDiscount): 
                  $discountPct = round((($p['base_price'] - $p['discount_price']) / $p['base_price']) * 100);
                ?>
                  <span class="product-badge-sale">-<?= $discountPct ?>%</span>
                <?php endif; ?>
                <img src="<?= escapeHtml($p['image_url'] ?: getAssetUrl('assets/images/logo.svg')) ?>" 
                     alt="<?= escapeHtml($p['name']) ?>" class="product-img" loading="lazy">
              </div>
              <div class="product-content">
                <span class="product-restaurant"><?= escapeHtml($p['restaurant_name'] ?: 'FASTFOOD Partner') ?></span>
                <h3 class="product-title">
                  <a href="<?= getUrl('food-detail.php?id=' . $p['id']) ?>"><?= escapeHtml($p['name']) ?></a>
                </h3>
                <div class="product-footer">
                  <div class="product-pricing">
                    <span class="product-price-current"><?= formatVND($currentPrice) ?></span>
                    <?php if ($hasDiscount): ?>
                      <span class="product-price-original"><?= formatVND($p['base_price']) ?></span>
                    <?php endif; ?>
                  </div>
                  <button type="button" class="btn-add-cart" onclick="addToCart(<?= $p['id'] ?>)" title="Thêm vào giỏ">＋</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<script>
async function addToCart(productId) {
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add', productId, qty: 1 })
    });
    const result = await res.json();
    if (result.success) {
      if (window.UIComponents?.showToast) {
        window.UIComponents.showToast('Đã thêm món vào giỏ hàng!', 'success');
      } else {
        alert('Đã thêm món vào giỏ hàng!');
      }
      const badge = document.getElementById('nav-cart-badge');
      if (badge) {
        badge.textContent = result.data.totalCount;
        badge.style.display = result.data.totalCount > 0 ? 'flex' : 'none';
      }
    } else {
      alert(result.message || 'Không thể thêm món.');
    }
  } catch (err) {
    console.error(err);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

