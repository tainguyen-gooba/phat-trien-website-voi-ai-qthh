/**
 * FASTFOOD CART CONTROLLER & CART PAGE RENDERER
 */

const CartController = {
  // Thêm món vào giỏ
  addItem(product, qty = 1, selectedToppings = []) {
    const user = window.StorageManager ? window.StorageManager.getUser() : null;
    if (user && user.isLoggedIn && user.role === 'admin') {
      window.UIComponents?.showToast('Tài khoản Quản trị viên (Admin) chỉ dùng để quản lý hệ thống, không thể mua hàng!', 'warning', 3500);
      return;
    }

    let cart = StorageManager.getCart();
    
    // Tính phụ phí topping
    const toppingTotal = selectedToppings.reduce((sum, t) => sum + (t.price || 0), 0);
    const itemPrice = (product.price || 0) + toppingTotal;

    // Tạo key nhận diện duy nhất
    const toppingKey = selectedToppings.map(t => t.name).sort().join('|');
    const uniqueId = `${product.id}_${toppingKey}`;

    const existingIndex = cart.findIndex(item => item.uniqueId === uniqueId);

    if (existingIndex > -1) {
      cart[existingIndex].qty += qty;
    } else {
      cart.push({
        uniqueId,
        productId: product.id,
        name: product.name,
        price: itemPrice,
        basePrice: product.price,
        image: product.image,
        restaurantId: product.restaurantId,
        restaurantName: product.restaurantName,
        selectedToppings,
        qty: qty,
      });
    }

    StorageManager.setCart(cart);
    window.UIComponents?.showToast(`Đã thêm "${product.name}" vào giỏ hàng!`, 'success');
  },

  // Cập nhật số lượng món
  updateQty(uniqueId, change) {
    let cart = StorageManager.getCart();
    const index = cart.findIndex(item => item.uniqueId === uniqueId);

    if (index > -1) {
      cart[index].qty += change;
      if (cart[index].qty <= 0) {
        cart.splice(index, 1);
      }
      StorageManager.setCart(cart);
      this.renderCartPage();
    }
  },

  // Xóa món khỏi giỏ
  removeItem(uniqueId) {
    let cart = StorageManager.getCart();
    cart = cart.filter(item => item.uniqueId !== uniqueId);
    StorageManager.setCart(cart);
    window.UIComponents?.showToast('Đã xóa món khỏi giỏ hàng', 'info');
    this.renderCartPage();
  },

  // Xóa trắng giỏ hàng
  clearCart() {
    StorageManager.setCart([]);
    this.renderCartPage();
  },

  // Tính toán tổng tiền
  getBillSummary(couponDiscount = 0) {
    const cart = StorageManager.getCart();
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const shippingFee = subtotal > 0 ? (subtotal >= 100000 ? 0 : 18000) : 0;
    const discount = Math.min(couponDiscount, subtotal);
    const total = Math.max(0, subtotal + shippingFee - discount);

    return {
      totalItems: cart.reduce((sum, item) => sum + item.qty, 0),
      subtotal,
      shippingFee,
      discount,
      total,
    };
  },

  // Render toàn bộ giao diện Cart Page
  renderCartPage() {
    const container = document.getElementById('cart-items-container');
    const emptyState = document.getElementById('cart-empty-state');
    const layout = document.getElementById('cart-active-layout');

    if (!container) return;

    const cart = StorageManager.getCart();
    const appliedCoupon = StorageManager.get(StorageManager.KEYS.COUPON, null);
    const discountAmount = appliedCoupon ? appliedCoupon.discount : 0;
    const summary = this.getBillSummary(discountAmount);

    if (cart.length === 0) {
      if (layout) layout.style.display = 'none';
      if (emptyState) emptyState.style.display = 'block';
      return;
    }

    if (emptyState) emptyState.style.display = 'none';
    if (layout) layout.style.display = 'grid';

    // Render items list
    container.innerHTML = cart.map(item => `
      <div class="cart-item-row">
        <img src="${item.image}" alt="${UIComponents.escapeHtml(item.name)}" class="cart-item-thumb">
        <div class="cart-item-info">
          <div style="font-size:0.75rem; color:var(--primary-color); font-weight:700;">${UIComponents.escapeHtml(item.restaurantName)}</div>
          <h4 class="cart-item-title">${UIComponents.escapeHtml(item.name)}</h4>
          ${item.selectedToppings && item.selectedToppings.length ? `
            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.35rem;">
              + ${item.selectedToppings.map(t => UIComponents.escapeHtml(t.name)).join(', ')}
            </div>
          ` : ''}
          <div class="cart-item-price">${UIComponents.formatVND(item.price)}</div>
        </div>

        <!-- Quantity Control -->
        <div class="quantity-control" style="margin: 0 1rem;">
          <button class="qty-btn" onclick="CartController.updateQty('${item.uniqueId}', -1)">-</button>
          <input type="text" class="qty-input" value="${item.qty}" readonly>
          <button class="qty-btn" onclick="CartController.updateQty('${item.uniqueId}', 1)">+</button>
        </div>

        <!-- Subtotal of item -->
        <div style="font-weight:800; color:var(--text-main); min-width:85px; text-align:right;">
          ${UIComponents.formatVND(item.price * item.qty)}
        </div>

        <!-- Remove Button -->
        <button style="color:var(--danger); font-size:1.1rem; padding:0.4rem; cursor:pointer;" title="Xóa món" onclick="CartController.removeItem('${item.uniqueId}')">
          🗑️
        </button>
      </div>
    `).join('');

    // Update Summary Box
    const subtotalEl = document.getElementById('cart-subtotal');
    const shipEl = document.getElementById('cart-shipping');
    const discountEl = document.getElementById('cart-discount');
    const totalEl = document.getElementById('cart-total');

    if (subtotalEl) subtotalEl.innerText = UIComponents.formatVND(summary.subtotal);
    if (shipEl) shipEl.innerText = summary.shippingFee === 0 ? 'Miễn phí' : UIComponents.formatVND(summary.shippingFee);
    if (discountEl) discountEl.innerText = `-${UIComponents.formatVND(summary.discount)}`;
    if (totalEl) totalEl.innerText = UIComponents.formatVND(summary.total);
  },
};

window.CartController = CartController;

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('cart-items-container')) {
    const user = window.StorageManager ? window.StorageManager.getUser() : null;
    if (user && user.isLoggedIn && user.role === 'admin') {
      window.UIComponents?.showToast('Tài khoản Quản trị viên chỉ dùng để quản lý hệ thống, không có giỏ hàng hoặc mua hàng!', 'warning', 4000);
      window.location.replace('./admin/dashboard.html');
      return;
    }

    UIComponents.renderHeader('cart');
    UIComponents.renderFooter();
    CartController.renderCartPage();

    // Coupon apply button
    const applyCouponBtn = document.getElementById('btn-apply-coupon');
    const couponInput = document.getElementById('cart-coupon-input');

    if (applyCouponBtn && couponInput) {
      applyCouponBtn.addEventListener('click', async () => {
        const code = couponInput.value.trim().toUpperCase();
        const availableCoupons = StorageManager.get(StorageManager.KEYS.AVAILABLE_COUPONS, []);
        const found = FASTFOOD_DATA.coupons.find(c => c.code === code)
          || availableCoupons.find((coupon) => coupon.code === code);

        if (found) {
          const subtotal = CartController.getBillSummary().subtotal;
          if (subtotal < (found.minOrder || 0)) {
            UIComponents.showToast(
              `Mã này áp dụng cho đơn hàng từ ${UIComponents.formatVND(found.minOrder)}.`,
              'warning'
            );
            return;
          }
          StorageManager.set(StorageManager.KEYS.COUPON, found);
          UIComponents.showToast(`Áp dụng mã "${found.code}" thành công (-${UIComponents.formatVND(found.discount)})!`, 'success');
          CartController.renderCartPage();
        } else {
          UIComponents.showToast('Mã giảm giá không hợp lệ hoặc đã hết hạn!', 'warning');
        }
      });
    }
  }
});
