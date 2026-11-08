/**
 * FASTFOOD PROFILE & ORDER HISTORY CONTROLLER
 */

document.addEventListener('DOMContentLoaded', () => {
  if (!UIComponents.requireLogin()) return;

  UIComponents.renderHeader('orders');
  UIComponents.renderFooter();

  // 1. Render Order History
  const historyContainer = document.getElementById('order-history-list');
  if (historyContainer) {
    const cachedOrders = StorageManager.getOrders();
    let orders = [];
    let activeTab = 'active';
    const apiUrl = window.location.port === '5000'
      ? `${window.location.origin}/api/v1`
      : 'http://localhost:5000/api/v1';
    const authToken = sessionStorage.getItem('fastfood_auth_token');
    const escapeHtml = UIComponents.escapeHtml;
    const normalizeStatus = (status) => String(status || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/đ/g, 'd');
    const getOrderGroup = (order) => {
      const statuses = [order.statusText, order.status].map(normalizeStatus);
      if (statuses.some(status => status.includes('da giao') || status.includes('giao thanh cong')
        || status.includes('delivered') || status.includes('completed'))) return 'delivered';
      if (statuses.some(status => status.includes('huy') || status.includes('cancel'))) return 'cancelled';
      return 'active';
    };
    const getStatusText = (order) => ({
      pending: 'Chờ xác nhận',
      preparing: 'Đang chuẩn bị',
      delivering: 'Đang giao',
      delivered: 'Đã giao',
      cancelled: 'Đã hủy',
    })[normalizeStatus(order.status)] || order.statusText || order.status || 'Đang xử lý';
    const renderHistory = () => {
      const counts = {
        active: orders.filter(order => getOrderGroup(order) === 'active').length,
        delivered: orders.filter(order => getOrderGroup(order) === 'delivered').length,
        cancelled: orders.filter(order => getOrderGroup(order) === 'cancelled').length,
      };
      const visibleOrders = orders.filter(order => getOrderGroup(order) === activeTab);
      const tabs = [
        ['active', 'Đang đặt'],
        ['delivered', 'Đã giao thành công'],
        ['cancelled', 'Đã hủy'],
      ].filter(([key]) => key !== 'cancelled' || counts.cancelled > 0);

      historyContainer.innerHTML = `
        <div class="order-history-tabs" role="tablist" aria-label="Lọc đơn hàng">
          ${tabs.map(([key, label]) => `
            <button type="button" class="order-history-tab" role="tab"
              aria-selected="${activeTab === key}" data-order-tab="${key}">
              ${label} <span>(${counts[key]})</span>
            </button>
          `).join('')}
        </div>
        ${visibleOrders.length ? visibleOrders.map(order => {
          const group = getOrderGroup(order);
          const status = getStatusText(order);
          const savedRating = order.rating;
          const orderId = escapeHtml(order.id);
          const items = Array.isArray(order.items) ? order.items : [];
          const ratingSection = group !== 'delivered' ? '' : savedRating?.submittedAt
            ? `
              <div class="order-rating">
                <h5>⭐ Đánh giá của bạn</h5>
                <p class="rating-summary">Quán: <strong>${'★'.repeat(savedRating.restaurant || 0)}</strong>
                  · ${items.map((item, index) => `${escapeHtml(item.name)}: <strong>${'★'.repeat(savedRating.items?.[index] || 0)}</strong>`).join(' · ')}</p>
              </div>
            `
            : `
              <form class="order-rating" data-rating-form="${orderId}">
                <h5>⭐ Đơn đã giao! Hãy đánh giá món ăn và quán nhé</h5>
                <div class="rating-row">
                  <span class="rating-label">Quán ${escapeHtml(order.restaurantName)}</span>
                  ${renderRatingStars(order.id, 'restaurant', 0)}
                </div>
                ${items.map((item, index) => `
                  <div class="rating-row">
                    <span class="rating-label">${escapeHtml(item.name)}</span>
                    ${renderRatingStars(order.id, 'item', index)}
                  </div>
                `).join('')}
                <button type="submit" class="btn btn-primary btn-sm">Gửi đánh giá</button>
              </form>
            `;

          return `
            <article class="card" style="margin-bottom:1.5rem;">
              <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1rem;">
                <div>
                  <span class="badge badge-secondary" style="margin-bottom:0.25rem;">Mã Đơn: ${orderId}</span>
                  <h4 style="font-size:1.15rem; font-weight:700; color:var(--text-main);">${escapeHtml(order.restaurantName)}</h4>
                  <small style="color:var(--text-muted);">Đặt lúc: ${escapeHtml(UIComponents.formatDateTime(order.createdAt))}</small>
                </div>
                <div style="text-align:right;">
                  <span class="order-status-badge ${group === 'delivered' ? 'is-delivered' : ''} ${group === 'cancelled' ? 'is-cancelled' : ''}">
                    ${group === 'delivered' ? '✓' : group === 'cancelled' ? '✕' : '●'} ${escapeHtml(status)}
                  </span>
                  <div style="font-size:1.2rem; font-weight:800; color:var(--primary-color); margin-top:0.35rem;">
                    ${UIComponents.formatVND(order.total)}
                  </div>
                </div>
              </div>
              <div style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:1.25rem;">
                ${items.map(item => `
                  <div style="display:flex; justify-content:space-between; padding:0.25rem 0;">
                    <span>${escapeHtml(item.qty)}x ${escapeHtml(item.name)}</span>
                    <span>${UIComponents.formatVND(item.price * item.qty)}</span>
                  </div>
                `).join('')}
              </div>
              ${ratingSection}
              <div style="display:flex; justify-content:flex-end; flex-wrap:wrap; gap:0.75rem; border-top:1px dashed var(--border-color); padding-top:1rem; margin-top:1rem;">
                ${group !== 'cancelled' ? `
                  <a href="./order-tracking.html?id=${encodeURIComponent(order.id)}" class="btn btn-outline btn-sm">
                    🚚 Theo Dõi Đơn Hàng
                  </a>
                  <button type="button" class="btn btn-primary btn-sm" data-reorder-id="${orderId}">
                    🔁 Đặt Lại Đơn Này
                  </button>
                ` : ''}
              </div>
            </article>
          `;
        }).join('') : `
          <div class="state-box">
            <h3 class="state-title">${activeTab === 'delivered' ? 'Chưa có đơn hàng đã giao' : activeTab === 'cancelled' ? 'Không có đơn hàng đã hủy' : 'Không có đơn hàng đang đặt'}</h3>
            <p class="state-desc">${activeTab === 'active' ? 'Các đơn hàng bạn đang đặt sẽ hiển thị tại đây.' : 'Đơn hàng sẽ được hiển thị tại đây khi có cập nhật.'}</p>
            ${activeTab === 'active' ? '<a href="./home.html" class="btn btn-primary">Khám Phá Món Ngon</a>' : ''}
          </div>
        `}
      `;
    };

    const renderRatingStars = (orderId, target, itemIndex) => `
      <div class="rating-stars" role="group" aria-label="Chọn số sao"
        data-rating-order="${escapeHtml(orderId)}" data-rating-target="${target}"
        ${target === 'item' ? `data-item-index="${itemIndex}"` : ''}>
        ${[1, 2, 3, 4, 5].map(value => `
          <button type="button" class="rating-star" aria-label="${value} sao" aria-pressed="false"
            data-rating-value="${value}">★</button>
        `).join('')}
      </div>
    `;

    historyContainer.innerHTML = `
      <div class="state-box">
        <div class="spinner"></div>
        <p>Đang tải đơn hàng từ máy chủ...</p>
      </div>
    `;
    historyContainer.addEventListener('click', (event) => {
      if (event.target.closest('[data-retry-orders]')) {
        syncOrders();
        return;
      }

      const tab = event.target.closest('[data-order-tab]');
      if (tab) {
        activeTab = tab.dataset.orderTab;
        renderHistory();
        return;
      }

      const reorderButton = event.target.closest('[data-reorder-id]');
      if (reorderButton) {
        reOrder(reorderButton.dataset.reorderId);
        return;
      }

      const star = event.target.closest('[data-rating-value]');
      if (!star) return;
      const value = Number(star.dataset.ratingValue);
      const group = star.closest('.rating-stars');
      group.querySelectorAll('.rating-star').forEach(button => {
        const selected = Number(button.dataset.ratingValue) <= value;
        button.classList.toggle('is-selected', selected);
        button.setAttribute('aria-pressed', String(Number(button.dataset.ratingValue) === value));
      });
    });

    historyContainer.addEventListener('submit', (event) => {
      const form = event.target.closest('[data-rating-form]');
      if (!form) return;
      event.preventDefault();

      const restaurantRating = form.querySelector('.rating-stars[data-rating-target="restaurant"] .rating-star[aria-pressed="true"]');
      const itemRatings = [...form.querySelectorAll('.rating-stars[data-rating-target="item"]')].map(group =>
        group.querySelector('.rating-star[aria-pressed="true"]')
      );
      if (!restaurantRating || itemRatings.some(rating => !rating)) {
        UIComponents.showToast('Vui lòng chấm sao cho quán và tất cả món ăn trước khi gửi.', 'warning');
        return;
      }

      const order = orders.find(item => String(item.id) === form.dataset.ratingForm);
      if (!order) {
        UIComponents.showToast('Không tìm thấy đơn hàng để lưu đánh giá.', 'error');
        return;
      }
      order.rating = {
        restaurant: Number(restaurantRating.dataset.ratingValue),
        items: itemRatings.map(rating => Number(rating.dataset.ratingValue)),
        submittedAt: new Date().toISOString(),
      };
      StorageManager.set(StorageManager.KEYS.ORDERS, orders);
      renderHistory();
      UIComponents.showToast('Cảm ơn bạn đã đánh giá món ăn và quán!', 'success');
    });

    async function syncOrders() {
      try {
        const response = await fetch(`${apiUrl}/realtime/orders`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${authToken}` },
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Array.isArray(result.data)) {
          throw new Error(result.message || 'Không thể đồng bộ đơn hàng từ máy chủ.');
        }

        const cachedOrdersById = new Map(cachedOrders.map(order => [String(order.id), order]));
        orders = result.data.map(order => ({
          ...order,
          rating: cachedOrdersById.get(String(order.id))?.rating || order.rating,
        }));
        StorageManager.set(StorageManager.KEYS.ORDERS, orders);
        const activeOrder = StorageManager.getActiveOrder();
        const currentActiveOrder = orders.find(order => String(order.id) === String(activeOrder?.id));
        if (currentActiveOrder) {
          StorageManager.set(StorageManager.KEYS.ACTIVE_ORDER, currentActiveOrder);
        } else if (activeOrder) {
          StorageManager.remove(StorageManager.KEYS.ACTIVE_ORDER);
        }
        renderHistory();
      } catch (error) {
        historyContainer.innerHTML = `
          <div class="state-box">
            <h3 class="state-title">Không thể tải đơn hàng</h3>
            <p class="state-desc">Vui lòng kiểm tra kết nối và thử lại.</p>
            <button type="button" class="btn btn-primary" data-retry-orders>Thử lại</button>
          </div>
        `;
        UIComponents.showToast(error.message || 'Không thể đồng bộ đơn hàng.', 'error');
      }
    }

    async function cancelOrder(orderId, button) {
      const order = orders.find(item => String(item.id) === String(orderId));
      if (!order || !canCancelOrder(order)) {
        UIComponents.showToast('Đơn này không còn đủ điều kiện để hủy.', 'warning');
        return;
      }
      if (!window.confirm('Bạn chắc chắn muốn hủy đơn hàng này? Mỗi lần hủy, phí giao hàng cho các đơn tiếp theo sẽ tăng thêm 3%.')) {
        return;
      }

      button.disabled = true;
      try {
        const response = await fetch(`${apiUrl}/realtime/orders/${encodeURIComponent(orderId)}/cancel`, {
          method: 'PATCH',
          headers: { Accept: 'application/json', Authorization: `Bearer ${authToken}` },
        });
        const result = await response.json();
        if (!response.ok || !result.success || !result.data) {
          throw new Error(result.message || 'Không thể hủy đơn hàng.');
        }

        orders = orders.map(item => String(item.id) === String(orderId)
          ? { ...item, ...result.data }
          : item);
        StorageManager.set(StorageManager.KEYS.ORDERS, orders);
        activeTab = 'cancelled';
        renderHistory();
        UIComponents.showToast('Đã hủy đơn. Phí giao hàng cho các đơn tiếp theo sẽ tăng 3%.', 'success');
      } catch (error) {
        button.disabled = false;
        UIComponents.showToast(error.message || 'Không thể hủy đơn hàng.', 'error');
      }
    }

    window.addEventListener('fastfood:order-updated', (event) => {
      const update = event.detail;
      if (!update?.orderCode) return;
      const index = orders.findIndex(order => String(order.id).replace(/^#/, '') === String(update.orderCode));
      if (index < 0) {
        syncOrders();
        return;
      }

      orders[index] = {
        ...orders[index],
        status: update.status || update.displayStatus,
        statusText: update.displayStatus || getStatusText(orders[index]),
        statusStep: update.statusStep || orders[index].statusStep,
      };
      StorageManager.set(StorageManager.KEYS.ORDERS, orders);
      renderHistory();
    });

    if (authToken) syncOrders();
  }

  // 2. Profile Form Management
  const profileForm = document.getElementById('profile-edit-form');
  const user = StorageManager.getUser();

  if (profileForm && user) {
    const nameInput = document.getElementById('prof-name');
    const emailInput = document.getElementById('prof-email');
    const phoneInput = document.getElementById('prof-phone');
    const addressInput = document.getElementById('prof-address');
    const displayName = document.getElementById('profile-display-name');

    if (nameInput) nameInput.value = user.fullName || '';
    if (emailInput) emailInput.value = user.email || '';
    if (phoneInput) phoneInput.value = user.phone || '';
    if (addressInput) addressInput.value = user.address || '';
    if (displayName) displayName.textContent = user.fullName || 'Tài khoản';

    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fullName = nameInput.value.trim();
      const phone = phoneInput.value.trim();
      const address = addressInput.value.trim();
      const customerDetails = UIComponents.validateCustomerDetails({ fullName, phone, address });
      if (!customerDetails.valid) {
        UIComponents.showToast(Object.values(customerDetails.errors).filter(Boolean).join(' '), 'warning');
        return;
      }
      const submitButton = profileForm.querySelector('button[type="submit"]');

      submitButton.disabled = true;
      try {
        const apiUrl = window.location.port === '5000'
          ? `${window.location.origin}/api/v1`
          : 'http://localhost:5000/api/v1';
        const token = sessionStorage.getItem('fastfood_auth_token');
        const response = await fetch(`${apiUrl}/auth/me`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify(customerDetails),
        });
        const result = await response.json();

        if (!response.ok || !result.success || !result.data?.user) {
          throw new Error(result.message || 'Không thể cập nhật thông tin hồ sơ.');
        }

        Object.assign(user, result.data.user);
        StorageManager.setUser(user);
        if (displayName) displayName.textContent = user.fullName;
        UIComponents.showToast('Cập nhật thông tin hồ sơ thành công!', 'success');
      } catch (error) {
        UIComponents.showToast(error.message || 'Không thể kết nối máy chủ.', 'error');
      } finally {
        submitButton.disabled = false;
      }
    });
  }
});

function reOrder(orderId) {
  const order = StorageManager.getOrders().find(o => o.id === orderId);
  if (!order) return;

  // Thêm lại từng món vào giỏ
  order.items.forEach(item => {
    const prod = FASTFOOD_DATA.products.find(p => p.name === item.name) || {
      id: 'prod-custom',
      name: item.name,
      price: item.price,
      restaurantName: order.restaurantName,
      image: 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80',
    };
    CartController.addItem(prod, item.qty, item.toppings || []);
  });

  UIComponents.showToast('Đã nạp lại món ăn vào giỏ hàng!', 'success');
  setTimeout(() => {
    window.location.href = './cart.html';
  }, 600);
}
