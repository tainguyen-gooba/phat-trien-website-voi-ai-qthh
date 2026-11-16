<?php
/**
 * FASTFOOD - Giỏ hàng (Shopping Cart)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Giỏ Hàng Của Bạn - FASTFOOD';
$activeNav = 'cart';
$extraCss  = ['css/pages.css'];

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['qty'];
}
$shippingFee = ($subtotal > 0 && $subtotal < 100000) ? 18000 : 0;
$discount = 0;
$total = max(0, $subtotal + $shippingFee - $discount);

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= getUrl('index.php') ?>">Trang chủ</a> <span>/</span> <span>Giỏ hàng</span>
    </div>
    <h1 class="page-title">Giỏ Hàng Của Bạn</h1>
  </div>
</div>

<main class="container">
  <?php if (empty($cart)): ?>
    <!-- Empty State -->
    <div class="state-box" style="max-width:600px; margin:2rem auto 4rem; text-align:center;">
      <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80" 
           alt="Empty Cart" style="width:110px; height:110px; border-radius:50%; object-fit:cover; margin:0 auto 1.25rem; box-shadow:var(--shadow-md); border:3px solid var(--secondary-color);">
      <h3 class="state-title">Giỏ hàng của bạn đang trống</h3>
      <p class="state-desc" style="color:var(--text-muted); margin:0.5rem 0 1.5rem;">Hãy khám phá thực đơn thơm ngon và ngập tràn ưu đãi của FASTFOOD ngay bây giờ!</p>
      <a href="<?= getUrl('foods.php') ?>" class="btn btn-primary btn-lg">Khám Phá Món Ngon</a>
    </div>
  <?php else: ?>
    <!-- Active Cart Layout -->
    <div class="cart-layout">
      <!-- Items List -->
      <div class="cart-items-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1rem;">
          <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-main);">Danh Sách Món Đã Chọn (<?= count($cart) ?> món)</h3>
          <button type="button" class="btn btn-outline btn-sm text-danger" onclick="clearCart()">
            Xóa Toàn Bộ
          </button>
        </div>

        <div id="cart-items-container">
          <?php foreach ($cart as $id => $item): 
            $itemTotal = (float)$item['price'] * (int)$item['qty'];
          ?>
            <div class="cart-item-row" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 0; border-bottom:1px solid var(--border-color); gap:1rem;">
              <div style="display:flex; align-items:center; gap:1rem; flex:1;">
                <img src="<?= escapeHtml($item['image'] ?: getAssetUrl('assets/images/logo.svg')) ?>" 
                     alt="<?= escapeHtml($item['name']) ?>" style="width:72px; height:72px; border-radius:8px; object-fit:cover;">
                <div>
                  <h4 style="font-size:1rem; font-weight:700; margin:0 0 0.35rem;"><?= escapeHtml($item['name']) ?></h4>
                  <span style="color:var(--primary-color); font-weight:700;"><?= formatVND($item['price']) ?></span>
                </div>
              </div>

              <!-- Quantity Control -->
              <div class="quantity-control" style="margin:0 1rem;">
                <button type="button" class="qty-btn" onclick="updateItemQty('<?= $id ?>', <?= max(1, $item['qty'] - 1) ?>)">-</button>
                <input type="text" class="qty-input" value="<?= $item['qty'] ?>" readonly>
                <button type="button" class="qty-btn" onclick="updateItemQty('<?= $id ?>', <?= $item['qty'] + 1 ?>)">+</button>
              </div>

              <!-- Item Total Price -->
              <strong style="min-width:90px; text-align:right; font-size:1.05rem;"><?= formatVND($itemTotal) ?></strong>

              <!-- Remove button -->
              <button type="button" onclick="removeItem('<?= $id ?>')" style="background:none; border:none; color:#ef4444; font-size:1.2rem; cursor:pointer;" title="Xóa món">🗑️</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Summary Bill Card -->
      <div class="cart-summary-card">
        <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-main); margin-bottom:1.25rem;">
          Tóm Tắt Đơn Hàng
        </h3>

        <div class="summary-row">
          <span>Tạm tính món ăn:</span>
          <span style="font-weight:700;"><?= formatVND($subtotal) ?></span>
        </div>

        <div class="summary-row">
          <span>Phí vận chuyển:</span>
          <span style="font-weight:700;"><?= $shippingFee > 0 ? formatVND($shippingFee) : '<span style="color:var(--success);">Miễn phí</span>' ?></span>
        </div>

        <div class="summary-row" style="color:var(--success);">
          <span>Giảm giá khuyến mãi:</span>
          <span style="font-weight:700;">-<?= formatVND($discount) ?></span>
        </div>

        <div class="summary-row total-row" style="border-top:1px solid var(--border-color); padding-top:1rem; margin-top:0.5rem;">
          <span style="font-size:1.1rem; font-weight:800;">Tổng thanh toán:</span>
          <span style="color:var(--primary-color); font-size:1.4rem; font-weight:800;"><?= formatVND($total) ?></span>
        </div>

        <button type="button" class="btn btn-primary btn-lg" style="width:100%; margin-top:1.5rem;" 
                onclick="window.location.href='<?= getUrl('checkout.php') ?>'">
          Tiến Hành Thanh Toán ➔
        </button>

        <a href="<?= getUrl('foods.php') ?>" style="display:block; text-align:center; margin-top:1rem; font-size:0.85rem; color:var(--text-muted);">
          ← Tiếp tục chọn thêm món
        </a>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
async function updateItemQty(productId, qty) {
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update', productId, qty })
    });
    const result = await res.json();
    if (result.success) {
      location.reload();
    }
  } catch (err) {
    console.error(err);
  }
}

async function removeItem(productId) {
  if (!confirm('Bạn có chắc muốn xóa món này khỏi giỏ hàng?')) return;
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'remove', productId })
    });
    const result = await res.json();
    if (result.success) {
      location.reload();
    }
  } catch (err) {
    console.error(err);
  }
}

async function clearCart() {
  if (!confirm('Bạn có chắc muốn xóa toàn bộ giỏ hàng?')) return;
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'clear' })
    });
    const result = await res.json();
    if (result.success) {
      location.reload();
    }
  } catch (err) {
    console.error(err);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

