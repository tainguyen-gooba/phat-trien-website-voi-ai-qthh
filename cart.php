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

// Xử lý giảm giá từ Coupon / Voucher
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$discount = 0;
if ($appliedCoupon && $subtotal > 0) {
    if (($appliedCoupon['discount_type'] ?? '') === 'percentage') {
        $discount = ($subtotal * (float)$appliedCoupon['discount_value']) / 100.0;
        if (!empty($appliedCoupon['max_discount_amount']) && (float)$appliedCoupon['max_discount_amount'] > 0) {
            $discount = min($discount, (float)$appliedCoupon['max_discount_amount']);
        }
    } else {
        $discount = min($subtotal, (float)($appliedCoupon['discount_value'] ?? 0));
    }
}
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

<main class="container" id="cart-page-content">
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
          <h3 id="cart-count-title" style="font-size:1.15rem; font-weight:800; color:var(--text-main);">Danh Sách Món Đã Chọn (<?= count($cart) ?> món)</h3>
          <button type="button" class="btn btn-outline btn-sm text-danger" onclick="clearCart()">
            Xóa Toàn Bộ
          </button>
        </div>

        <div id="cart-items-container">
          <?php foreach ($cart as $id => $item): 
            $itemTotal = (float)$item['price'] * (int)$item['qty'];
          ?>
            <div class="cart-item-row" id="cart-item-row-<?= $id ?>" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 0; border-bottom:1px solid var(--border-color); gap:1rem;">
              <div style="display:flex; align-items:center; gap:1rem; flex:1;">
                <img src="<?= escapeHtml($item['image'] ?: getAssetUrl('assets/images/logo.svg')) ?>" 
                     alt="<?= escapeHtml($item['name']) ?>" style="width:72px; height:72px; border-radius:8px; object-fit:cover;">
                <div>
                  <h4 style="font-size:1rem; font-weight:700; margin:0 0 0.35rem;"><?= escapeHtml($item['name']) ?></h4>
                  <?php if (!empty($item['toppings']) && is_array($item['toppings'])): ?>
                    <div style="font-size:0.8rem; color:#ea580c; margin-bottom:0.35rem; font-weight:600;">
                      ✨ <?= implode(', ', array_map(function($t) { return escapeHtml($t['name']) . ' (+' . formatVND($t['price']) . ')'; }, $item['toppings'])) ?>
                    </div>
                  <?php endif; ?>
                  <span style="color:var(--primary-color); font-weight:700;"><?= formatVND($item['price']) ?></span>
                </div>
              </div>

              <!-- Quantity Control -->
              <div class="quantity-control" style="margin:0 1rem;">
                <button type="button" class="qty-btn" onclick="changeItemQty('<?= $id ?>', -1)" title="Giảm số lượng">-</button>
                <input type="text" id="qty-input-<?= $id ?>" class="qty-input" value="<?= $item['qty'] ?>" readonly>
                <button type="button" class="qty-btn" onclick="changeItemQty('<?= $id ?>', 1)" title="Tăng số lượng">+</button>
              </div>

              <!-- Item Total Price -->
              <strong id="item-total-<?= $id ?>" style="min-width:90px; text-align:right; font-size:1.05rem;"><?= formatVND($itemTotal) ?></strong>

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
          <span id="cart-subtotal" style="font-weight:700;"><?= formatVND($subtotal) ?></span>
        </div>

        <div class="summary-row">
          <span>Phí vận chuyển:</span>
          <span id="cart-shipping-fee" style="font-weight:700;"><?= $shippingFee > 0 ? formatVND($shippingFee) : '<span style="color:var(--success);">Miễn phí</span>' ?></span>
        </div>

        <div class="summary-row" style="color:var(--success);">
          <span>Giảm giá khuyến mãi:</span>
          <span id="cart-discount-amount" style="font-weight:700;">-<?= formatVND($discount) ?></span>
        </div>

        <!-- Mã Giảm Giá / Voucher Box -->
        <div id="cart-voucher-box" style="margin: 1rem 0; background: #fff7ed; border: 1.5px dashed #fb923c; border-radius: 12px; padding: 0.9rem;">
          <?php if ($appliedCoupon): ?>
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="background:#ea580c; color:#fff; font-weight:800; font-size:0.75rem; padding:0.2rem 0.55rem; border-radius:4px; text-transform:uppercase;">
                  🎟️ <?= escapeHtml($appliedCoupon['code']) ?>
                </span>
                <div style="font-weight:700; color:#ea580c; font-size:0.85rem; margin-top:0.35rem;">
                  -<?= formatVND($discount) ?> (<?= escapeHtml($appliedCoupon['title']) ?>)
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline text-danger" onclick="removeCoupon()" style="padding:0.25rem 0.6rem; font-size:0.75rem;">
                Gỡ bỏ
              </button>
            </div>
          <?php else: ?>
            <label style="display:block; font-size:0.8rem; font-weight:700; color:#9a3412; margin-bottom:0.4rem;">
              🎟️ Bạn có mã ưu đãi? (Ví dụ: NEW50)
            </label>
            <div style="display:flex; gap:0.5rem;">
              <input type="text" id="cart-coupon-input" class="form-control form-control-sm" placeholder="Nhập mã (NEW50...)" style="text-transform:uppercase; font-weight:700; font-size:0.85rem;">
              <button type="button" class="btn btn-sm btn-primary" onclick="applyCoupon()" style="white-space:nowrap; padding:0.4rem 0.85rem; font-size:0.85rem;">
                Áp dụng
              </button>
            </div>
          <?php endif; ?>
        </div>

        <div class="summary-row total-row" style="border-top:1px solid var(--border-color); padding-top:1rem; margin-top:0.5rem;">
          <span style="font-size:1.1rem; font-weight:800;">Tổng thanh toán:</span>
          <span id="cart-total-amount" style="color:var(--primary-color); font-size:1.4rem; font-weight:800;"><?= formatVND($total) ?></span>
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
function formatVND(amount) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
}

function updateCartUI(data) {
  if (!data) return;

  // 1. Nếu giỏ hàng trống, cập nhật giao diện rỗng ngay lập tức không reload
  if (!data.items || data.items.length === 0) {
    const pageContent = document.getElementById('cart-page-content');
    if (pageContent) {
      pageContent.innerHTML = `
        <div class="state-box" style="max-width:600px; margin:2rem auto 4rem; text-align:center;">
          <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80" 
               alt="Empty Cart" style="width:110px; height:110px; border-radius:50%; object-fit:cover; margin:0 auto 1.25rem; box-shadow:var(--shadow-md); border:3px solid var(--secondary-color);">
          <h3 class="state-title">Giỏ hàng của bạn đang trống</h3>
          <p class="state-desc" style="color:var(--text-muted); margin:0.5rem 0 1.5rem;">Hãy khám phá thực đơn thơm ngon và ngập tràn ưu đãi của FASTFOOD ngay bây giờ!</p>
          <a href="<?= getUrl('foods.php') ?>" class="btn btn-primary btn-lg">Khám Phá Món Ngon</a>
        </div>
      `;
    }
    const badge = document.getElementById('nav-cart-badge');
    if (badge) {
      badge.textContent = '0';
      badge.style.display = 'none';
    }
    return;
  }

  // 2. Cập nhật số lượng trên badge giỏ hàng thanh điều hướng
  const badge = document.getElementById('nav-cart-badge');
  if (badge) {
    badge.textContent = data.totalCount;
    badge.style.display = data.totalCount > 0 ? 'flex' : 'none';
  }

  // 3. Cập nhật tiêu đề tổng số món
  const countTitle = document.getElementById('cart-count-title');
  if (countTitle) {
    countTitle.textContent = `Danh Sách Món Đã Chọn (${data.items.length} món)`;
  }

  // 4. Cập nhật số lượng và tổng tiền từng dòng món ăn
  data.items.forEach(item => {
    const qtyInput = document.getElementById(`qty-input-${item.cartKey}`);
    if (qtyInput) {
      qtyInput.value = item.qty;
    }
    const itemTotalEl = document.getElementById(`item-total-${item.cartKey}`);
    if (itemTotalEl) {
      itemTotalEl.textContent = formatVND(item.itemTotal);
    }
  });

  // 5. Cập nhật bảng tổng kết thanh toán
  const subtotalEl = document.getElementById('cart-subtotal');
  if (subtotalEl) {
    subtotalEl.textContent = formatVND(data.subtotal);
  }

  const shippingEl = document.getElementById('cart-shipping-fee');
  if (shippingEl) {
    if (data.shippingFee > 0) {
      shippingEl.innerHTML = formatVND(data.shippingFee);
    } else {
      shippingEl.innerHTML = '<span style="color:var(--success);">Miễn phí</span>';
    }
  }

  const discountEl = document.getElementById('cart-discount-amount');
  if (discountEl) {
    discountEl.textContent = '-' + formatVND(data.discount);
  }

  const totalEl = document.getElementById('cart-total-amount');
  if (totalEl) {
    totalEl.textContent = formatVND(data.total);
  }

  // 6. Cập nhật khung mã giảm giá voucher
  const voucherBox = document.getElementById('cart-voucher-box');
  if (voucherBox) {
    if (data.appliedCoupon) {
      voucherBox.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div>
            <span style="background:#ea580c; color:#fff; font-weight:800; font-size:0.75rem; padding:0.2rem 0.55rem; border-radius:4px; text-transform:uppercase;">
              🎟️ ${data.appliedCoupon.code}
            </span>
            <div style="font-weight:700; color:#ea580c; font-size:0.85rem; margin-top:0.35rem;">
              -${formatVND(data.discount)} (${data.appliedCoupon.title})
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline text-danger" onclick="removeCoupon()" style="padding:0.25rem 0.6rem; font-size:0.75rem;">
            Gỡ bỏ
          </button>
        </div>
      `;
    } else {
      voucherBox.innerHTML = `
        <label style="display:block; font-size:0.8rem; font-weight:700; color:#9a3412; margin-bottom:0.4rem;">
          🎟️ Bạn có mã ưu đãi? (Ví dụ: NEW50)
        </label>
        <div style="display:flex; gap:0.5rem;">
          <input type="text" id="cart-coupon-input" class="form-control form-control-sm" placeholder="Nhập mã (NEW50...)" style="text-transform:uppercase; font-weight:700; font-size:0.85rem;">
          <button type="button" class="btn btn-sm btn-primary" onclick="applyCoupon()" style="white-space:nowrap; padding:0.4rem 0.85rem; font-size:0.85rem;">
            Áp dụng
          </button>
        </div>
      `;
    }
  }
}

async function changeItemQty(productId, delta) {
  const input = document.getElementById(`qty-input-${productId}`);
  if (!input) return;
  const currentQty = parseInt(input.value) || 1;
  const newQty = currentQty + delta;
  if (newQty < 1) {
    return; // Giữ tối thiểu 1 phần, muốn xóa nhấn biểu tượng thùng rác
  }

  // Cập nhật ngay trên giao diện để có phản hồi siêu nhanh (optimistic update)
  input.value = newQty;

  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update', productId, qty: newQty })
    });
    const result = await res.json();
    if (result.success && result.data) {
      updateCartUI(result.data);
    } else {
      input.value = currentQty;
      if (typeof UIComponents !== 'undefined' && UIComponents.showToast) {
        UIComponents.showToast(result.message || 'Không thể cập nhật số lượng.', 'error');
      } else {
        alert(result.message || 'Không thể cập nhật số lượng.');
      }
    }
  } catch (err) {
    console.error(err);
    input.value = currentQty;
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
    if (result.success && result.data) {
      const row = document.getElementById(`cart-item-row-${productId}`);
      if (row) {
        row.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        setTimeout(() => {
          row.remove();
          updateCartUI(result.data);
        }, 250);
      } else {
        updateCartUI(result.data);
      }
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
    if (result.success && result.data) {
      updateCartUI(result.data);
    }
  } catch (err) {
    console.error(err);
  }
}

async function applyCoupon() {
  const input = document.getElementById('cart-coupon-input');
  if (!input) return;
  const code = input.value.trim();
  if (!code) {
    alert('Vui lòng nhập mã giảm giá.');
    input.focus();
    return;
  }
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'apply_coupon', code })
    });
    const result = await res.json();
    if (result.success && result.data) {
      updateCartUI(result.data);
      if (typeof UIComponents !== 'undefined' && UIComponents.showToast) {
        UIComponents.showToast(result.message || 'Đã áp dụng mã giảm giá!', 'success');
      } else {
        alert(result.message || 'Đã áp dụng mã giảm giá thành công!');
      }
    } else {
      alert(result.message || 'Không thể áp dụng mã giảm giá này.');
    }
  } catch (err) {
    console.error(err);
    alert('Lỗi kết nối khi áp dụng mã giảm giá.');
  }
}

async function removeCoupon() {
  try {
    const res = await fetch('<?= getUrl('api/cart.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'remove_coupon' })
    });
    const result = await res.json();
    if (result.success && result.data) {
      updateCartUI(result.data);
      if (typeof UIComponents !== 'undefined' && UIComponents.showToast) {
        UIComponents.showToast('Đã hủy áp dụng mã giảm giá.', 'info');
      }
    }
  } catch (err) {
    console.error(err);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>

