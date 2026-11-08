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
    const orders = StorageManager.getOrders();

    if (orders.length === 0) {
      historyContainer.innerHTML = `
        <div class="state-box">
          <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=160&auto=format&fit=crop&q=80" alt="No orders" style="width:100px; height:100px; border-radius:50%; object-fit:cover; margin:0 auto 1rem; box-shadow:var(--shadow-md);">
          <h3 class="state-title">Chưa có đơn hàng nào</h3>
          <p class="state-desc">Bạn chưa đặt đơn hàng nào trên FASTFOOD. Hãy khám phá ngay các món ngon hôm nay nhé!</p>
          <a href="./home.html" class="btn btn-primary">Đặt Món Ngay</a>
        </div>
      `;
    } else {
      historyContainer.innerHTML = orders.map(order => `
        <div class="card" style="margin-bottom:1.5rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; border-bottom:1px solid var(--border-color); padding-bottom:1rem; margin-bottom:1rem;">
            <div>
              <span class="badge badge-secondary" style="margin-bottom:0.25rem;">Mã Đơn: ${order.id}</span>
              <h4 style="font-size:1.15rem; font-weight:700; color:var(--text-main);">${order.restaurantName}</h4>
              <small style="color:var(--text-muted);">Đặt lúc: ${order.createdAt}</small>
            </div>

            <div style="text-align:right;">
              <span class="badge badge-success">● ${order.statusText || 'Đang xử lý'}</span>
              <div style="font-size:1.2rem; font-weight:800; color:var(--primary-color); margin-top:0.35rem;">
                ${UIComponents.formatVND(order.total)}
              </div>
            </div>
          </div>

          <!-- Món trong đơn -->
          <div style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:1.25rem;">
            ${order.items.map(i => `
              <div style="display:flex; justify-content:space-between; padding:0.25rem 0;">
                <span>${i.qty}x ${i.name}</span>
                <span>${UIComponents.formatVND(i.price * i.qty)}</span>
              </div>
            `).join('')}
          </div>

          <!-- Actions -->
          <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px dashed var(--border-color); padding-top:1rem;">
            <a href="./order-tracking.html?id=${order.id}" class="btn btn-outline btn-sm">
              🚚 Theo Dõi Đơn Hàng
            </a>
            <button class="btn btn-primary btn-sm" onclick="reOrder('${order.id}')">
              🔁 Đặt Lại Đơn Này
            </button>
          </div>
        </div>
      `).join('');
    }
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
      const submitButton = profileForm.querySelector('button[type="submit"]');

      submitButton.disabled = true;
      try {
        const apiUrl = window.location.port === '5000'
          ? `${window.location.origin}/api/v1`
          : 'http://localhost:5000/api/v1';
        const token = localStorage.getItem('fastfood_auth_token')
          || sessionStorage.getItem('fastfood_auth_token');
        const response = await fetch(`${apiUrl}/auth/me`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({ fullName, phone, address }),
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
