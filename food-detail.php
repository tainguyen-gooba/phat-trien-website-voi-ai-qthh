<?php
/**
 * FASTFOOD - Chi tiết món ăn (Food Detail)
 */
require_once __DIR__ . '/config/config.php';

$db = getDB();
$id = trim($_GET['id'] ?? '');

$product = null;
if ($id) {
    $stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, 
                                 r.rating_avg, r.rating_count, c.name AS category_name
                          FROM products p
                          LEFT JOIN restaurants r ON p.restaurant_id = r.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.id = ? OR p.product_code = ? LIMIT 1");
    $stmt->execute([$id, $id]);
    $product = $stmt->fetch();

    if (!$product && is_numeric($id)) {
        $stmt = $db->prepare("SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, 
                                     r.rating_avg, r.rating_count, c.name AS category_name
                              FROM products p
                              LEFT JOIN restaurants r ON p.restaurant_id = r.id
                              LEFT JOIN categories c ON p.category_id = c.id
                              WHERE p.id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $product = $stmt->fetch();
    }
}

if (!$product) {
    header('Location: ' . getUrl('foods.php'));
    exit;
}

$pageTitle = $product['name'] . ' - FASTFOOD';
$activeNav = 'foods';
$extraCss  = ['css/pages.css'];

$hasDiscount = !empty($product['discount_price']) && (float)$product['discount_price'] > 0 && (float)$product['discount_price'] < (float)$product['base_price'];
$currentPrice = $hasDiscount ? $product['discount_price'] : $product['base_price'];

// Món ăn liên quan
$relatedStmt = $db->prepare("SELECT p.*, r.name AS restaurant_name 
                             FROM products p 
                             LEFT JOIN restaurants r ON p.restaurant_id = r.id 
                             WHERE p.id != ? AND (p.category_id = ? OR p.restaurant_id = ?) AND p.is_available = 1 
                             ORDER BY p.id DESC LIMIT 4");
$relatedStmt->execute([$product['id'], $product['category_id'] ?? 0, $product['restaurant_id'] ?? 0]);
$relatedFoods = $relatedStmt->fetchAll();

$isManager = isAdmin();

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> 
      <a href="<?= getUrl('foods.php') ?>">Món ăn</a> <span>/</span> 
      <span><?= escapeHtml($product['name']) ?></span>
    </div>
    <h1 class="page-title">Thông Tin Món Ăn</h1>
  </div>
</div>

<main class="container">
  <div id="food-detail-container" style="margin-top: 2rem;">
    <div class="food-detail-grid">
      <!-- Ảnh món ăn -->
      <div class="food-gallery-main">
        <img src="<?= escapeHtml($product['image_url'] ?: getAssetUrl('assets/images/logo.svg')) ?>" 
             alt="<?= escapeHtml($product['name']) ?>" class="food-gallery-img">
      </div>

      <!-- Chi tiết thông tin -->
      <div>
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; flex-wrap:wrap;">
          <span class="badge badge-secondary"><?= escapeHtml($product['restaurant_name'] ?: 'FASTFOOD Partner') ?></span>
          <span style="color:var(--accent-gold); font-weight:700; font-size:0.9rem;">
            ★ <?= number_format((float)($product['rating_avg'] ?: 4.9), 1) ?> (<?= (int)($product['rating_count'] ?: 28) ?> đánh giá)
          </span>
          <span style="color:var(--text-muted); font-size:0.85rem;">• ⏱️ <?= (int)($product['prep_time_minutes'] ?: 15) ?> phút</span>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.65rem;">
          📍 <?= escapeHtml($product['restaurant_address'] ?: 'Buôn Ma Thuột, Đắk Lắk') ?>
        </p>

        <h1 style="font-size:2rem; font-weight:800; color:var(--text-main); line-height:1.25;">
          <?= escapeHtml($product['name']) ?>
        </h1>
        
        <div class="detail-price-box">
          <span id="detail-total-price" class="detail-current-price"><?= formatVND($currentPrice) ?></span>
          <?php if ($hasDiscount): ?>
            <span class="detail-old-price"><?= formatVND($product['base_price']) ?></span>
          <?php endif; ?>
        </div>

        <p style="color:var(--text-secondary); line-height:1.7; font-size:0.95rem; margin-bottom:1.5rem;">
          <?= nl2br(escapeHtml($product['description'] ?: 'Món ăn thơm ngon, nóng hổi, chuẩn vị chế biến từ nguyên liệu tươi sạch hàng ngày.')) ?>
        </p>

        <!-- Thao tác mua hàng -->
        <div style="display:flex; align-items:center; gap:1.25rem; margin-top:2rem; flex-wrap:wrap;">
          <?php if ($isManager): ?>
            <div style="flex:1; padding:1rem 1.25rem; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; border-radius:var(--radius-md); font-weight:700; font-size:0.95rem;">
              👑 <span>Tài khoản Quản trị viên (Admin) chỉ dùng để quản trị hệ thống.</span>
            </div>
          <?php else: ?>
            <div class="quantity-control">
              <button type="button" class="qty-btn" id="btn-qty-minus" onclick="updateQty(-1)">-</button>
              <input type="text" id="detail-qty-input" class="qty-input" value="1" readonly>
              <button type="button" class="qty-btn" id="btn-qty-plus" onclick="updateQty(1)">+</button>
            </div>

            <button type="button" class="btn btn-outline btn-lg" onclick="handleAddToCart(false)">
              🛒 Thêm Vào Giỏ Hàng
            </button>

            <button type="button" class="btn btn-primary btn-lg" onclick="handleAddToCart(true)">
              ⚡ Mua Ngay
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Món ăn liên quan -->
  <?php if (!empty($relatedFoods)): ?>
  <section style="margin-top:4rem; margin-bottom:4rem;">
    <h3 style="font-size:1.35rem; font-weight:800; color:var(--text-main); margin-bottom:1.5rem;">
      Món Ăn Liên Quan Được Yêu Thích
    </h3>
    <div class="grid grid-cols-4" id="related-foods-container">
      <?php foreach ($relatedFoods as $rf): 
        $rDiscount = !empty($rf['discount_price']) && (float)$rf['discount_price'] > 0 && (float)$rf['discount_price'] < (float)$rf['base_price'];
        $rPrice = $rDiscount ? $rf['discount_price'] : $rf['base_price'];
      ?>
        <div class="product-card">
          <div class="product-img-wrap">
            <img src="<?= escapeHtml($rf['image_url'] ?: getAssetUrl('assets/images/logo.svg')) ?>" 
                 alt="<?= escapeHtml($rf['name']) ?>" class="product-img" loading="lazy">
          </div>
          <div class="product-content">
            <span class="product-restaurant"><?= escapeHtml($rf['restaurant_name'] ?: 'FASTFOOD Partner') ?></span>
            <h3 class="product-title">
              <a href="<?= getUrl('food-detail.php?id=' . $rf['id']) ?>"><?= escapeHtml($rf['name']) ?></a>
            </h3>
            <div class="product-footer">
              <div class="product-pricing">
                <span class="product-price-current"><?= formatVND($rPrice) ?></span>
              </div>
              <button type="button" class="btn-add-cart" onclick="addToCart(<?= $rf['id'] ?>)">＋</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<script>
let qty = 1;
const productId = <?= (int)$product['id'] ?>;

function updateQty(delta) {
  qty = Math.max(1, qty + delta);
  document.getElementById('detail-qty-input').value = qty;
}

async function handleAddToCart(isBuyNow = false) {
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add', productId, qty })
    });
    const result = await res.json();
    if (result.success) {
      if (isBuyNow) {
        window.location.href = '<?= getUrl('cart.php') ?>';
      } else {
        if (window.UIComponents?.showToast) {
          window.UIComponents.showToast(`Đã thêm ${qty} món vào giỏ hàng!`, 'success');
        } else {
          alert(`Đã thêm ${qty} món vào giỏ hàng!`);
        }
        const badge = document.getElementById('nav-cart-badge');
        if (badge) {
          badge.textContent = result.data.totalCount;
          badge.style.display = result.data.totalCount > 0 ? 'flex' : 'none';
        }
      }
    } else {
      alert(result.message || 'Không thể thêm vào giỏ.');
    }
  } catch (err) {
    console.error(err);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

