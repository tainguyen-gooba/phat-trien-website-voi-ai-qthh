/**
 * FASTFOOD ORDER LIVE TRACKING CONTROLLER
 */

document.addEventListener('DOMContentLoaded', async () => {
  if (!UIComponents.requireLogin()) return;

  UIComponents.renderHeader('orders');
  UIComponents.renderFooter();

  const urlParams = new URLSearchParams(window.location.search);
  const orderId = urlParams.get('id');
  const container = document.getElementById('tracking-content-container');
  if (!container) return;
  container.innerHTML = `
    <div class="state-box">
      <div class="spinner"></div>
      <p>Đang tải đơn hàng...</p>
    </div>
  `;

  const apiUrl = window.location.port === '5000'
    ? `${window.location.origin}/api/v1`
    : 'http://localhost:5000/api/v1';
  let order;
  try {
    const response = await fetch(`${apiUrl}/realtime/orders`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${sessionStorage.getItem('fastfood_auth_token') || ''}`,
      },
    });
    const result = await response.json();
    if (!response.ok || !result.success || !Array.isArray(result.data)) {
      throw new Error(result.message || 'Không thể tải đơn hàng.');
    }

    const cachedOrders = new Map(StorageManager.getOrders().map(item => [String(item.id), item]));
    const orders = result.data.map(item => ({
      ...item,
      rating: cachedOrders.get(String(item.id))?.rating || item.rating,
    }));
    StorageManager.set(StorageManager.KEYS.ORDERS, orders);
    const cachedActiveOrder = StorageManager.getActiveOrder();
    const requestedOrderId = orderId || cachedActiveOrder?.id;
    order = orders.find(item => String(item.id) === String(requestedOrderId)) || (orderId ? null : orders[0]);
    if (!order) {
      if (cachedActiveOrder) StorageManager.remove(StorageManager.KEYS.ACTIVE_ORDER);
      container.innerHTML = `
        <div class="state-box">
          <h3 class="state-title">Không tìm thấy đơn hàng</h3>
          <p class="state-desc">Đơn hàng không có trên máy chủ hoặc không thuộc tài khoản này.</p>
          <a href="./order-history.html" class="btn btn-primary">Xem đơn hàng của tôi</a>
        </div>
      `;
      return;
    }
    StorageManager.set(StorageManager.KEYS.ACTIVE_ORDER, order);
  } catch (error) {
    container.innerHTML = `
      <div class="state-box">
        <h3 class="state-title">Không thể tải đơn hàng</h3>
        <p class="state-desc">Vui lòng kiểm tra kết nối rồi tải lại trang.</p>
      </div>
    `;
    UIComponents.showToast(error.message || 'Không thể tải đơn hàng.', 'error');
    return;
  }

  const currentStep = order.statusStep || 3;
  const escapeHtml = UIComponents.escapeHtml;
  const statusHeading = order.statusText || 'Đang giao đến bạn';
  const normalizedStatuses = [order.status, order.statusText]
    .map(status => String(status || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/đ/g, 'd'));
  const canCancelOrder = normalizedStatuses.some(status => (
    status === 'pending' || status === 'preparing'
    || status.includes('cho xac nhan') || status.includes('dang chuan bi')
  ));

  container.innerHTML = `
    <div class="tracking-card">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; border-bottom:1px solid var(--border-color); padding-bottom:1.25rem;">
        <div>
          <span class="badge badge-secondary" style="margin-bottom:0.5rem;">Mã Đơn: ${escapeHtml(order.id)}</span>
          <h2 style="font-size:1.6rem; font-weight:800; color:var(--text-main);">
            ${escapeHtml(statusHeading)}${order.statusText ? '' : ` (${escapeHtml(order.estimatedTime || '20 - 25 phút')})`}
          </h2>
          <p style="color:var(--text-secondary); font-size:0.9rem;">
            Quán ăn: <strong>${escapeHtml(order.restaurantName)}</strong> • Thời gian đặt: ${escapeHtml(UIComponents.formatDateTime(order.createdAt))}
          </p>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:0.75rem;">
          ${canCancelOrder ? `
            <button type="button" class="btn btn-outline btn-sm" id="cancel-tracked-order">
              ✕ Hủy đơn
            </button>
          ` : ''}
          <button class="btn btn-outline btn-sm" onclick="window.print()">
            🖨️ In Hóa Đơn
          </button>
        </div>
      </div>

      <!-- Timeline Stepper 5 bước -->
      <div class="timeline-stepper">
        <div class="timeline-progress-bar" style="width: ${(currentStep - 1) * 25}%;"></div>

        <!-- Bước 1 -->
        <div class="timeline-step ${currentStep >= 1 ? (currentStep > 1 ? 'completed' : 'active') : ''}">
          <div class="step-node">${currentStep > 1 ? '✓' : '1'}</div>
          <div class="step-title">Đã Đặt Đơn</div>
          <div class="step-time">Hoàn tất</div>
        </div>

        <!-- Bước 2 -->
        <div class="timeline-step ${currentStep >= 2 ? (currentStep > 2 ? 'completed' : 'active') : ''}">
          <div class="step-node">${currentStep > 2 ? '✓' : '2'}</div>
          <div class="step-title">Quán Nhận Đơn</div>
          <div class="step-time">Đã xác nhận</div>
        </div>

        <!-- Bước 3 -->
        <div class="timeline-step ${currentStep >= 3 ? (currentStep > 3 ? 'completed' : 'active') : ''}">
          <div class="step-node">${currentStep > 3 ? '✓' : '3'}</div>
          <div class="step-title">Đang Nấu Món</div>
          <div class="step-time">Nóng hổi</div>
        </div>

        <!-- Bước 4 -->
        <div class="timeline-step ${currentStep >= 4 ? (currentStep > 4 ? 'completed' : 'active') : ''}">
          <div class="step-node">${currentStep > 4 ? '✓' : '4'}</div>
          <div class="step-title">Tài Xế Đang Giao</div>
          <div class="step-time">Đang di chuyển</div>
        </div>

        <!-- Bước 5 -->
        <div class="timeline-step ${currentStep >= 5 ? 'completed' : ''}">
          <div class="step-node">${currentStep >= 5 ? '✓' : '5'}</div>
          <div class="step-title">Đã Nhận Món</div>
          <div class="step-time">Tận tay bạn</div>
        </div>
      </div>

      <!-- Shipper Box -->
      <div class="shipper-card">
        <div style="display:flex; align-items:center; gap:1rem;">
          <img src="https://images.unsplash.com/photo-1526367790999-0150786686a2?w=160&auto=format&fit=crop&q=80" alt="Shipper" style="width:56px; height:56px; border-radius:50%; object-fit:cover; border:2.5px solid var(--primary-color); box-shadow:var(--shadow-sm); flex-shrink:0;">
          <div>
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Tài xế FASTFOOD Express</div>
            <strong style="font-size:1.05rem; color:var(--text-main);">${escapeHtml(order.shipper?.name || 'Lê Văn Tài Xế')}</strong>
            <div style="font-size:0.85rem; color:var(--text-secondary);">${escapeHtml(order.shipper?.vehicle || 'Yamaha Grande • 59-X3 789.12')}</div>
          </div>
        </div>

        <div style="display:flex; gap:0.75rem;">
          <a href="tel:${escapeHtml(order.shipper?.phone || '0901234567')}" class="btn btn-primary btn-sm">
            📞 Gọi Tài Xế
          </a>
          <button class="btn btn-outline btn-sm" onclick="UIComponents.showToast('Đang kết nối tin nhắn với tài xế...', 'info')">
            💬 Nhắn Tin
          </button>
        </div>
      </div>

      <!-- Order Details Summary Grid -->
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1.5rem; margin-top:2rem;">
        <div style="background:var(--bg-surface); padding:1.25rem; border-radius:var(--radius-md);">
          <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.6rem;">📍 Địa Chỉ Giao Hàng</h4>
          <p style="font-size:0.875rem; color:var(--text-secondary); line-height:1.6;">
            <strong>${escapeHtml(order.recipient?.name || 'Khách hàng')}</strong> (${escapeHtml(order.recipient?.phone || '0903000003')})<br>
            ${escapeHtml(order.recipient?.address || '72 Lê Thánh Tông, Buôn Ma Thuột, Đắk Lắk')}<br>
            ${order.recipient?.notes ? `<small style="color:var(--primary-color);">Ghi chú: ${escapeHtml(order.recipient.notes)}</small>` : ''}
          </p>
        </div>

        <div style="background:var(--bg-surface); padding:1.25rem; border-radius:var(--radius-md);">
          <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.6rem;">🧾 Chi Tiết Món Ăn</h4>
          <div style="font-size:0.875rem; color:var(--text-secondary);">
            ${order.items.map(item => `
              <div style="display:flex; justify-content:space-between; margin-bottom:0.35rem;">
                <span>${item.qty}x ${escapeHtml(item.name)}</span>
                <strong>${UIComponents.formatVND(item.price * item.qty)}</strong>
              </div>
            `).join('')}
            <div style="border-top:1px dashed var(--border-color); margin-top:0.6rem; padding-top:0.6rem; display:flex; justify-content:space-between; font-weight:800; color:var(--primary-color);">
              <span>Tổng thanh toán (${escapeHtml(order.paymentMethod || 'COD')}):</span>
              <span>${UIComponents.formatVND(order.total)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  const cancelButton = document.getElementById('cancel-tracked-order');
  cancelButton?.addEventListener('click', async () => {
    if (!canCancelOrder) {
      UIComponents.showToast('Đơn này không còn đủ điều kiện để hủy.', 'warning');
      return;
    }
    if (!window.confirm('Bạn chắc chắn muốn hủy đơn hàng này? Mỗi lần hủy, phí giao hàng cho các đơn tiếp theo sẽ tăng thêm 3%.')) {
      return;
    }

    cancelButton.disabled = true;
    try {
      const response = await fetch(`${apiUrl}/realtime/orders/${encodeURIComponent(order.id)}/cancel`, {
        method: 'PATCH',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${sessionStorage.getItem('fastfood_auth_token') || ''}`,
        },
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data) {
        throw new Error(result.message || 'Không thể hủy đơn hàng.');
      }

      order = { ...order, ...result.data };
      StorageManager.set(StorageManager.KEYS.ORDERS, allOrders.map(item => item.id === order.id ? order : item));
      StorageManager.set(StorageManager.KEYS.ACTIVE_ORDER, order);
      UIComponents.showToast('Đã hủy đơn. Phí giao hàng cho các đơn tiếp theo sẽ tăng 3%.', 'success');
      window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
      cancelButton.disabled = false;
      UIComponents.showToast(error.message || 'Không thể hủy đơn hàng.', 'error');
    }
  });

  window.addEventListener('fastfood:order-updated', (event) => {
    const update = event.detail;
    if (!update || update.orderCode !== String(order.id).replace(/^#/, '')) return;
    order.status = update.displayStatus;
    order.statusText = update.displayStatus;
    order.statusStep = ({
      'Chờ xác nhận': 1,
      'Đang chuẩn bị': 3,
      'Đang giao': 4,
      'Đã giao': 5,
      'Đã hủy': 1,
    })[update.displayStatus] || order.statusStep;
    const orders = StorageManager.getOrders().map((item) => item.id === order.id ? order : item);
    StorageManager.set(StorageManager.KEYS.ORDERS, orders);
    window.location.reload();
  });
});
