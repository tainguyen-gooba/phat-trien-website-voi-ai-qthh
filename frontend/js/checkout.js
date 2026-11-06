/**
 * FASTFOOD CHECKOUT PAGE CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  UIComponents.renderHeader('checkout');
  UIComponents.renderFooter();

  const cart = StorageManager.getCart();
  if (cart.length === 0) {
    window.location.href = './cart.html';
    return;
  }

  const appliedCoupon = StorageManager.get(StorageManager.KEYS.COUPON, null);
  const discountAmount = appliedCoupon ? appliedCoupon.discount : 0;
  const summary = CartController.getBillSummary(discountAmount);

  // Render Mini Order Items List
  const itemsContainer = document.getElementById('checkout-items-summary');
  if (itemsContainer) {
    itemsContainer.innerHTML = cart.map(item => `
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; font-size:0.9rem;">
        <div style="display:flex; align-items:center; gap:0.6rem;">
          <span style="font-weight:700; color:var(--primary-color);">${item.qty}x</span>
          <span style="color:var(--text-main); font-weight:500;">${item.name}</span>
        </div>
        <span style="font-weight:700;">${UIComponents.formatVND(item.price * item.qty)}</span>
      </div>
    `).join('');
  }

  // Update Bill Numbers
  const subtotalEl = document.getElementById('checkout-subtotal');
  const shipEl = document.getElementById('checkout-shipping');
  const discountEl = document.getElementById('checkout-discount');
  const totalEl = document.getElementById('checkout-total');

  if (subtotalEl) subtotalEl.innerText = UIComponents.formatVND(summary.subtotal);
  if (shipEl) shipEl.innerText = summary.shippingFee === 0 ? 'Miễn phí' : UIComponents.formatVND(summary.shippingFee);
  if (discountEl) discountEl.innerText = `-${UIComponents.formatVND(summary.discount)}`;
  if (totalEl) totalEl.innerText = UIComponents.formatVND(summary.total);

  // Payment Method selection
  let selectedPayment = 'cod';
  document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      const radio = card.querySelector('input[type="radio"]');
      if (radio) {
        radio.checked = true;
        selectedPayment = radio.value;
      }
    });
  });

  // Submit Order Form
  const checkoutForm = document.getElementById('checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const name = document.getElementById('customer-name')?.value.trim();
      const phone = document.getElementById('customer-phone')?.value.trim();
      const address = document.getElementById('customer-address')?.value.trim();
      const notes = document.getElementById('customer-notes')?.value.trim();

      if (!name || !phone || !address) {
        UIComponents.showToast('Vui lòng điền đầy đủ tên, số điện thoại và địa chỉ giao hàng!', 'warning');
        return;
      }

      // Tạo mã đơn hàng ngẫu nhiên (VD: FF-20261105-8942)
      const orderCode = `FF-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${Math.floor(1000 + Math.random() * 9000)}`;

      const newOrder = {
        id: orderCode,
        createdAt: new Date().toLocaleString('vi-VN'),
        restaurantName: cart[0]?.restaurantName || 'FASTFOOD Partner',
        recipient: { name, phone, address, notes },
        items: cart.map(i => ({ name: i.name, qty: i.qty, price: i.price, toppings: i.selectedToppings })),
        paymentMethod: selectedPayment.toUpperCase(),
        subtotal: summary.subtotal,
        shippingFee: summary.shippingFee,
        discount: summary.discount,
        total: summary.total,
        status: 'confirmed',
        statusStep: 2, // 1: Đặt đơn, 2: Quán nhận, 3: Đang làm, 4: Đang giao, 5: Đã giao
        estimatedTime: '20 - 25 phút',
        shipper: {
          name: 'Nguyễn Văn Giao Hàng',
          phone: '0909 123 456',
          vehicle: 'Honda Wave • 59-P1 888.88',
        },
      };

      // Lưu đơn hàng và dọn giỏ
      StorageManager.addOrder(newOrder);
      CartController.clearCart();
      StorageManager.remove(StorageManager.KEYS.COUPON);

      UIComponents.showToast('Đặt hàng thành công! Đang chuyển đến trang theo dõi...', 'success');

      setTimeout(() => {
        window.location.href = `./order-tracking.html?id=${orderCode}`;
      }, 1000);
    });
  }
});

