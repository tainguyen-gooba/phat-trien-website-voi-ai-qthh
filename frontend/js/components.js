/**
 * FASTFOOD REUSABLE UI COMPONENTS CONTROLLER
 * Render Header, Footer, Toast, Modal và Tiện ích dùng chung cho toàn bộ các trang
 */

const UIComponents = {
  // Format tiền tệ VNĐ (ví dụ: 69.000 đ)
  formatVND(amount) {
    if (isNaN(amount) || amount === null) return '0 đ';
    return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
  },

  // Hiển thị Toast thông báo
  showToast(message, type = 'info', duration = 3000) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
      success: '✅',
      error: '❌',
      warning: '⚠️',
      info: '🍔',
    };

    toast.innerHTML = `
      <div style="display:flex; align-items:center; gap:0.6rem;">
        <span style="font-size:1.1rem;">${icons[type] || 'ℹ️'}</span>
        <span style="font-size:0.9rem; font-weight:600; color:var(--text-main);">${message}</span>
      </div>
      <button style="color:var(--text-muted); cursor:pointer; font-weight:bold; font-size:1rem; padding:0 4px;" onclick="this.closest('.toast').remove()">✕</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(15px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  // Render Header chung cho các trang
  renderHeader(activePage = 'home') {
    const headerContainer = document.getElementById('site-header');
    if (!headerContainer) return;

    const cart = window.StorageManager ? window.StorageManager.getCart() : [];
    const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
    const user = window.StorageManager ? window.StorageManager.getUser() : null;

    headerContainer.className = 'site-header';
    headerContainer.innerHTML = `
      <div class="container navbar">
        <!-- Brand Logo -->
        <a href="./home.html" class="brand-logo">
          <img src="../assets/images/logo.svg" alt="FASTFOOD Logo" class="brand-logo-img">
        </a>

        <!-- Header Search Bar -->
        <div class="header-search-wrap">
          <svg class="header-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#888888" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="header-search-input" class="header-search-input" placeholder="Tìm món ăn, trà sữa, pizza...">
        </div>

        <!-- Navigation Menu Links -->
        <nav class="nav-menu">
          <a href="./home.html" class="nav-link ${activePage === 'home' ? 'active' : ''}">Trang Chủ</a>
          <a href="./restaurants.html" class="nav-link ${activePage === 'restaurants' ? 'active' : ''}">Nhà Hàng</a>
          <a href="./foods.html" class="nav-link ${activePage === 'foods' ? 'active' : ''}">Món Ăn</a>
          <a href="./order-history.html" class="nav-link ${activePage === 'orders' ? 'active' : ''}">Đơn Hàng</a>
        </nav>

        <!-- Right Actions Controls -->
        <div class="header-actions">
          <!-- Cart Link -->
          <a href="./cart.html" class="cart-btn-link" title="Xem giỏ hàng">
            🛒
            <span id="nav-cart-badge" class="cart-counter" style="${totalItems > 0 ? '' : 'display:none;'}">${totalItems}</span>
          </a>

          <!-- User Session / Login -->
          ${user && user.isLoggedIn ? `
            <a href="./profile.html" class="user-menu-pill" title="Hồ sơ tài khoản">
              <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80" alt="Avatar" class="user-avatar-mini" style="object-fit:cover;">
              <span>${user.fullName ? user.fullName.split(' ').slice(-1)[0] : 'Tài Khoản'}</span>
            </a>
          ` : `
            <a href="./login.html" class="btn btn-outline btn-sm">Đăng Nhập</a>
            <a href="./register.html" class="btn btn-primary btn-sm">Đăng Ký</a>
          `}

          <!-- Mobile Hamburger -->
          <button class="mobile-nav-toggle" id="mobile-menu-btn" onclick="UIComponents.toggleMobileMenu()">☰</button>
        </div>
      </div>
    `;

    // Search input enter event
    const searchInput = document.getElementById('header-search-input');
    if (searchInput) {
      searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          const val = searchInput.value.trim();
          if (val) {
            window.location.href = `./foods.html?search=${encodeURIComponent(val)}`;
          }
        }
      });
    }

    // Subscribe to cart updates
    window.addEventListener('cartUpdated', (e) => {
      const badge = document.getElementById('nav-cart-badge');
      if (badge) {
        const count = e.detail.reduce((sum, item) => sum + item.qty, 0);
        badge.innerText = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
      }
    });
  },

  // Toggle mobile navigation
  toggleMobileMenu() {
    const nav = document.querySelector('.nav-menu');
    if (nav) {
      nav.style.display = nav.style.display === 'flex' ? 'none' : 'flex';
      nav.style.flexDirection = 'column';
      nav.style.position = 'absolute';
      nav.style.top = 'var(--header-height)';
      nav.style.left = '0';
      nav.style.right = '0';
      nav.style.background = '#FFFFFF';
      nav.style.padding = '1.5rem';
      nav.style.borderBottom = '2px solid var(--primary-color)';
      nav.style.boxShadow = 'var(--shadow-lg)';
    }
  },

  // Render Footer chung cho các trang
  renderFooter() {
    const footerContainer = document.getElementById('site-footer');
    if (!footerContainer) return;

    footerContainer.className = 'site-footer';
    footerContainer.innerHTML = `
      <div class="container">
        <div class="footer-grid">
          <!-- Cột 1: Brand -->
          <div>
            <a href="./home.html" class="brand-logo" style="color:#FFFFFF;">
              <img src="../assets/images/logo.svg" alt="FASTFOOD Logo" style="height:44px; filter:brightness(0) invert(1);">
            </a>
            <p class="footer-brand-desc">
              FASTFOOD - Nền tảng đặt món ăn trực tuyến thế hệ mới. Giao hàng thần tốc trong 20 phút, bảo đảm chất lượng món ăn nóng hổi chuẩn vị.
            </p>
            <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
              <span style="font-size:1.4rem; cursor:pointer;">📱</span>
              <span style="font-size:1.4rem; cursor:pointer;">📘</span>
              <span style="font-size:1.4rem; cursor:pointer;">📸</span>
              <span style="font-size:1.4rem; cursor:pointer;">🎥</span>
            </div>
          </div>

          <!-- Cột 2: Khám phá -->
          <div>
            <h4 class="footer-col-title">Khám Phá</h4>
            <div class="footer-links">
              <a href="./home.html" class="footer-link">Trang chủ FASTFOOD</a>
              <a href="./restaurants.html" class="footer-link">Danh sách quán ăn</a>
              <a href="./foods.html" class="footer-link">Món ăn bán chạy</a>
              <a href="./foods.html?filter=flash" class="footer-link">Flash Deals giảm giá</a>
              <a href="./cart.html" class="footer-link">Giỏ hàng của bạn</a>
            </div>
          </div>

          <!-- Cột 3: Về chúng tôi -->
          <div>
            <h4 class="footer-col-title">Chính Sách & Hỗ Trợ</h4>
            <div class="footer-links">
              <a href="#" class="footer-link">Điều khoản dịch vụ</a>
              <a href="#" class="footer-link">Chính sách bảo mật</a>
              <a href="#" class="footer-link">Quy chuẩn vệ sinh an toàn</a>
              <a href="./order-tracking.html" class="footer-link">Theo dõi đơn hàng</a>
              <a href="#" class="footer-link">Đăng ký làm đối tác quán</a>
            </div>
          </div>

          <!-- Cột 4: Liên hệ -->
          <div>
            <h4 class="footer-col-title">Tổng Đài Hỗ Trợ</h4>
            <div class="footer-contact-item">
              <span>📞</span>
              <strong>Hotline: 1900 8888 (24/7)</strong>
            </div>
            <div class="footer-contact-item">
              <span>📧</span>
              <span>hotro@fastfood.vn</span>
            </div>
            <div class="footer-contact-item">
              <span>📍</span>
              <span>Tòa nhà FASTFOOD Center, Quận 1, TP. HCM</span>
            </div>
            <div style="background:#24262A; padding:0.75rem 1rem; border-radius:var(--radius-md); margin-top:1rem;">
              <small style="color:#FFA24A; font-weight:700; display:block;">GIAO ĐỒ ĂN NHANH</small>
              <span style="font-size:0.8rem; color:#A0A5AA;">Cam kết giao trễ quá 30 phút tặng voucher 50K</span>
            </div>
          </div>
        </div>

        <div class="footer-bottom">
          <div>&copy; 2026 FASTFOOD Việt Nam. Tất cả quyền được bảo lưu. Thiết kế chuẩn Modern Food Delivery.</div>
          <div style="display:flex; gap:1.25rem;">
            <span>Chính Sách Riêng Tư</span>
            <span>Quy Chế Hoạt Động</span>
            <span>Giải Quyết Khiếu Nại</span>
          </div>
        </div>
      </div>
    `;
  },

  // Modal xem nhanh & tùy biến món ăn (Quick View & Toppings)
  openFoodModal(productId) {
    const product = window.FASTFOOD_DATA.products.find(p => p.id === productId);
    if (!product) return;

    let overlay = document.getElementById('food-modal-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'food-modal-overlay';
      overlay.className = 'modal-overlay';
      document.body.appendChild(overlay);
    }

    overlay.innerHTML = `
      <div class="modal-card">
        <div style="position:relative; height:240px; background:#F4F4F6;">
          <img src="${product.image}" alt="${product.name}" style="width:100%; height:100%; object-fit:cover;">
          <button style="position:absolute; top:15px; right:15px; background:rgba(0,0,0,0.6); color:#fff; width:34px; height:34px; border-radius:50%; font-size:1rem; cursor:pointer;" onclick="UIComponents.closeFoodModal()">✕</button>
        </div>

        <div style="padding:1.5rem;">
          <div style="font-size:0.8rem; color:var(--primary-color); font-weight:700; text-transform:uppercase;">${product.restaurantName}</div>
          <h3 style="font-size:1.35rem; font-weight:800; color:var(--text-main); margin:0.35rem 0 0.5rem;">${product.name}</h3>
          <p style="font-size:0.875rem; color:var(--text-secondary); line-height:1.5;">${product.description}</p>
          
          <div style="display:flex; align-items:baseline; gap:0.75rem; margin:1rem 0;">
            <span style="font-size:1.6rem; font-weight:900; color:var(--primary-color);">${UIComponents.formatVND(product.price)}</span>
            ${product.oldPrice ? `<span style="font-size:0.95rem; color:var(--text-muted); text-decoration:line-through;">${UIComponents.formatVND(product.oldPrice)}</span>` : ''}
          </div>

          <!-- Topping Checkbox Options -->
          ${product.toppings && product.toppings.length ? `
            <div style="background:var(--bg-surface); padding:1rem; border-radius:var(--radius-md); margin-bottom:1.25rem;">
              <div style="font-weight:700; font-size:0.85rem; margin-bottom:0.6rem; color:var(--text-main);">Tùy chọn thêm (Toppings):</div>
              ${product.toppings.map((t, idx) => `
                <label style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0; font-size:0.85rem; cursor:pointer;">
                  <span style="display:flex; align-items:center; gap:0.5rem;">
                    <input type="checkbox" class="modal-topping-cb" data-name="${t.name}" data-price="${t.price}" style="accent-color:var(--primary-color);">
                    <span>${t.name}</span>
                  </span>
                  <span style="font-weight:700; color:var(--primary-color);">+${UIComponents.formatVND(t.price)}</span>
                </label>
              `).join('')}
            </div>
          ` : ''}

          <!-- Quantity and Add Button -->
          <div style="display:flex; align-items:center; gap:1rem;">
            <div class="quantity-control">
              <button class="qty-btn" onclick="UIComponents.adjustModalQty(-1)">-</button>
              <input type="text" id="modal-qty-input" class="qty-input" value="1" readonly>
              <button class="qty-btn" onclick="UIComponents.adjustModalQty(1)">+</button>
            </div>

            <button class="btn btn-primary btn-lg" style="flex:1;" onclick="UIComponents.confirmAddToCart('${product.id}')">
              Thêm Vào Giỏ Hàng
            </button>
          </div>
        </div>
      </div>
    `;

    overlay.classList.add('active');
  },

  adjustModalQty(delta) {
    const input = document.getElementById('modal-qty-input');
    if (!input) return;
    let qty = parseInt(input.value) || 1;
    qty = Math.max(1, qty + delta);
    input.value = qty;
  },

  confirmAddToCart(productId) {
    const product = window.FASTFOOD_DATA.products.find(p => p.id === productId);
    if (!product) return;

    const qtyInput = document.getElementById('modal-qty-input');
    const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    // Lấy toppings đã chọn
    const selectedToppings = [];
    document.querySelectorAll('.modal-topping-cb:checked').forEach(cb => {
      selectedToppings.push({
        name: cb.dataset.name,
        price: parseInt(cb.dataset.price) || 0,
      });
    });

    window.CartController.addItem(product, qty, selectedToppings);
    this.closeFoodModal();
  },

  closeFoodModal() {
    const overlay = document.getElementById('food-modal-overlay');
    if (overlay) overlay.classList.remove('active');
  },
};

window.UIComponents = UIComponents;

