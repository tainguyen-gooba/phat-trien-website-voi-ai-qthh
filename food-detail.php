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

// Lấy danh sách Toppings từ website_content
$toppings = [];
$wcRow = $db->query("SELECT content FROM website_content WHERE collection_name = 'products'")->fetch();
if ($wcRow && !empty($wcRow['content'])) {
    $wcProds = json_decode($wcRow['content'], true);
    if (is_array($wcProds)) {
        foreach ($wcProds as $item) {
            $itemNum = (int)preg_replace('/\D/', '', $item['id'] ?? '');
            if ($itemNum === (int)$product['id'] || (!empty($item['name']) && trim($item['name']) === trim($product['name']))) {
                if (!empty($item['toppings']) && is_array($item['toppings'])) {
                    $toppings = $item['toppings'];
                }
                break;
            }
        }
    }
}
if (empty($toppings)) {
    $catName = strtolower($product['category_name'] ?? '');
    if (strpos($catName, 'burger') !== false || strpos($catName, 'gà rán') !== false) {
        $toppings = [
            ['name' => 'Thêm phô mai lát Cheddar', 'price' => 10000],
            ['name' => 'Thịt ba chỉ xông khói (Bacon)', 'price' => 15000],
            ['name' => 'Trứng ốp la lòng đào', 'price' => 8000],
        ];
    } elseif (strpos($catName, 'trà sữa') !== false || strpos($catName, 'uống') !== false) {
        $toppings = [
            ['name' => 'Trân châu đường đen dẻo', 'price' => 6000],
            ['name' => 'Thạch nha đam thanh mát', 'price' => 5000],
            ['name' => 'Kem cheese béo mặn thơm ngậy', 'price' => 10000],
            ['name' => 'Pudding trứng béo mịn', 'price' => 7000],
        ];
    } else {
        $toppings = [
            ['name' => 'Thêm trứng ốp la lòng đào', 'price' => 8000],
            ['name' => 'Thêm chả lụa / chả trứng', 'price' => 10000],
            ['name' => 'Canh rong biển súp ấm', 'price' => 12000],
        ];
    }
}

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
          <span class="badge badge-primary">FASTFOOD Chính Hãng</span>
          <span style="color:var(--accent-gold); font-weight:700; font-size:0.9rem;">
            ★ <?= number_format((float)($product['rating_avg'] ?: 4.9), 1) ?> (<?= (int)($product['rating_count'] ?: 28) ?> đánh giá)
          </span>
          <span style="color:var(--text-muted); font-size:0.85rem;">• ⏱️ <?= (int)($product['prep_time_minutes'] ?: 15) ?> phút</span>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.65rem;">
          🛵 Giao hàng tận nơi siêu tốc trong 20 phút tại Buôn Ma Thuột
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

        <!-- Toppings Selection Area -->
        <?php if (!empty($toppings)): ?>
          <div class="modal-toppings-card" style="margin-bottom:1.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem;">
            <div class="modal-toppings-title" style="font-weight:750; font-size:0.95rem; color:#1e293b; margin-bottom:0.75rem; display:flex; justify-content:space-between; align-items:center;">
              <span>✨ Tùy chọn thêm (Toppings):</span>
              <small style="color:#64748b; font-size:0.8rem;"><?= count($toppings) ?> tùy chọn</small>
            </div>
            <div class="modal-toppings-list">
              <?php foreach ($toppings as $idx => $t): ?>
                <label class="modal-topping-item" id="detail-topping-item-<?= $idx ?>" style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0.85rem; margin-bottom:0.5rem; border-radius:8px; background:#fff; border:1px solid #e2e8f0; cursor:pointer;">
                  <span class="modal-topping-left" style="display:flex; align-items:center; gap:0.65rem; font-size:0.9rem; font-weight:600; color:#334155;">
                    <input type="checkbox" class="detail-topping-cb" data-name="<?= escapeHtml($t['name']) ?>" data-price="<?= (float)$t['price'] ?>" onchange="onDetailToppingToggle(this, 'detail-topping-item-<?= $idx ?>')">
                    <span><?= escapeHtml($t['name']) ?></span>
                  </span>
                  <span class="modal-topping-price" style="font-weight:750; color:#ea580c; font-size:0.9rem;">+<?= formatVND($t['price']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Thao tác mua hàng -->
        <div style="display:flex; align-items:center; gap:1.25rem; margin-top:1.5rem; flex-wrap:wrap;">
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
      <?php foreach ($relatedFoods as $rf): ?>
        <?= renderProductCard($rf) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<script>
let qty = 1;
const basePrice = <?= (float)$currentPrice ?>;
const productId = <?= (int)$product['id'] ?>;

function onDetailToppingToggle(checkbox, itemId) {
  const itemEl = document.getElementById(itemId);
  if (itemEl) {
    itemEl.style.borderColor = checkbox.checked ? '#f97316' : '#e2e8f0';
    itemEl.style.background = checkbox.checked ? '#fff7ed' : '#fff';
  }
  calculateDetailTotal();
}

function updateQty(delta) {
  qty = Math.max(1, qty + delta);
  document.getElementById('detail-qty-input').value = qty;
  calculateDetailTotal();
}

function calculateDetailTotal() {
  let toppingsTotal = 0;
  document.querySelectorAll('.detail-topping-cb:checked').forEach(cb => {
    toppingsTotal += (parseFloat(cb.dataset.price) || 0);
  });
  const total = (basePrice + toppingsTotal) * qty;
  const display = document.getElementById('detail-total-price');
  if (display) {
    display.textContent = total.toLocaleString('vi-VN') + ' đ';
  }
}

async function handleAddToCart(isBuyNow = false) {
  try {
    const selectedToppings = [];
    document.querySelectorAll('.detail-topping-cb:checked').forEach(cb => {
      selectedToppings.push({
        name: cb.dataset.name,
        price: parseFloat(cb.dataset.price) || 0
      });
    });

    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add', productId, qty, toppings: selectedToppings })
    });
    const result = await res.json();
    if (result.success) {
      if (isBuyNow) {
        window.location.href = '<?= getUrl('cart.php') ?>';
      } else {
        const topText = selectedToppings.length ? ` (kèm ${selectedToppings.length} topping)` : '';
        if (window.UIComponents?.showToast) {
          window.UIComponents.showToast(`Đã thêm ${qty} phần món ăn${topText} vào giỏ hàng!`, 'success');
        } else {
          alert(`Đã thêm ${qty} phần món ăn vào giỏ hàng!`);
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
    } else {
      alert(result.message || 'Không thể thêm vào giỏ.');
    }
  } catch (err) {
    console.error(err);
  }
}
function toggleFavorite(productId, btn) {
  const isLiked = btn.classList.toggle('is-liked');
  btn.textContent = isLiked ? '♥' : '♡';
  if (window.UIComponents?.showToast) {
    window.UIComponents.showToast(isLiked ? 'Đã thêm vào danh sách yêu thích!' : 'Đã xóa khỏi danh sách yêu thích.', 'info');
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

