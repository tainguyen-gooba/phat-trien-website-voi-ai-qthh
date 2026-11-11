/**
 * FASTFOOD REUSABLE UI COMPONENTS CONTROLLER
 * Render Header, Footer, Toast, Modal và Tiện ích dùng chung cho toàn bộ các trang
 */

const UIComponents = {
  // Format tiền tệ VNĐ (ví dụ: 69.000 đ - dùng non-breaking space để không bị rớt dòng chữ đ)
  formatVND(amount) {
    if (isNaN(amount) || amount === null) return '0\u00A0đ';
    return new Intl.NumberFormat('vi-VN').format(amount) + '\u00A0đ';
  },

  formatDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value || '');
    return new Intl.DateTimeFormat('vi-VN', {
      timeZone: 'Asia/Ho_Chi_Minh',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    }).format(date);
  },

  createOrderCode() {
    const randomBytes = new Uint32Array(1);
    window.crypto.getRandomValues(randomBytes);
    return `FF${String(randomBytes[0] % 10000).padStart(4, '0')}`;
  },

  escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
  },

  validateCustomerDetails({ fullName, phone, address } = {}) {
    const name = String(fullName || '').trim().replace(/\s+/g, ' ');
    const normalizedPhone = String(phone || '').trim().replace(/[\s().-]/g, '').replace(/^\+?84/, '0');
    const normalizedAddress = String(address || '').trim().replace(/\s+/g, ' ');
    const nameIsValid = name.length >= 5
      && /^[\p{L}]+(?:[ '-][\p{L}]+)+$/u.test(name)
      && name.length <= 150;
    const phoneIsValid = /^0[35789]\d{8}$/.test(normalizedPhone);
    const addressWords = normalizedAddress.match(/[\p{L}]{2,}/gu) || [];
    const addressIsValid = normalizedAddress.length >= 12
      && normalizedAddress.length <= 255
      && /^[\p{L}\p{N}\s,.'/#-]+$/u.test(normalizedAddress)
      && addressWords.length >= (/\d/.test(normalizedAddress) ? 2 : 3);

    return {
      valid: nameIsValid && phoneIsValid && addressIsValid,
      fullName: name,
      phone: normalizedPhone,
      address: normalizedAddress,
      errors: {
        fullName: nameIsValid ? '' : 'Vui lòng nhập họ tên thật (ít nhất 5 ký tự), gồm họ và tên bằng chữ.',
        phone: phoneIsValid ? '' : 'Số điện thoại phải là số di động Việt Nam hợp lệ gồm 10 chữ số.',
        address: addressIsValid ? '' : 'Vui lòng nhập địa chỉ chi tiết (số nhà/đường và khu vực).',
      },
    };
  },

  // Hiển thị Toast thông báonpm
  showToast(message, type = 'info', duration = 4500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icons = {
      success: '✅',
      error: '❌',
      warning: '⚠️',
      info: '🍔',
    };

    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = icons[type] || 'ℹ️';

    const text = document.createElement('span');
    text.className = 'toast-message';
    text.textContent = message;

    const closeButton = document.createElement('button');
    closeButton.className = 'toast-close';
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Đóng thông báo');
    closeButton.textContent = '✕';
    closeButton.addEventListener('click', () => toast.remove());

    const content = document.createElement('div');
    content.className = 'toast-content';
    content.append(icon, text);
    toast.append(content, closeButton);

    container.appendChild(toast);

    setTimeout(() => {
      if (!toast.isConnected) return;
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-10px)';
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
    const isAdmin = Boolean((user && user.isLoggedIn && user.role === 'admin') || activePage === 'admin' || window.location.pathname.includes('/admin/'));

    headerContainer.className = 'site-header';
    headerContainer.innerHTML = `
      <div class="container navbar">
        <!-- Brand Logo -->
        <a href="./home.html" class="brand-logo">
          <img src="../assets/images/logo.svg" alt="FASTFOOD Buôn Ma Thuột" class="brand-logo-img">
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
          <!-- Cart Link (Ẩn đối với Quản trị viên Admin) -->
          ${!isAdmin ? `
          <a href="./cart.html" class="cart-btn-link" title="Xem giỏ hàng">
            🛒
            <span id="nav-cart-badge" class="cart-counter" style="${totalItems > 0 ? '' : 'display:none;'}">${totalItems}</span>
          </a>
          ` : ''}

          <!-- Favorite / Yêu thích Link -->
          ${!isAdmin ? `
          <a href="./favorites.html" class="favorite-nav-btn" title="Yêu thích của bạn">
            ♡
            <span id="nav-favorite-badge" class="cart-counter" style="display:none;">0</span>
          </a>
          ` : ''}

          <!-- User Session / Login -->
          ${user && user.isLoggedIn ? `
            <a href="./profile.html" class="user-menu-pill" title="Hồ sơ tài khoản">
              <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80" alt="Avatar" class="user-avatar-mini" style="object-fit:cover;">
              <span>${user.fullName ? user.fullName.split(' ').slice(-1)[0] : 'Tài Khoản'}</span>
            </a>
            ${user.role === 'admin' ? '<a href="./admin/dashboard.html" class="btn btn-primary btn-sm">Quản trị</a>' : ''}
          ` : `
            <a href="./login.html" class="btn btn-outline btn-sm">Đăng Nhập</a>
            <a href="./register.html" class="btn btn-primary btn-sm">Đăng Ký</a>
          `}

          <!-- Mobile Hamburger -->
          <button class="mobile-nav-toggle" id="mobile-menu-btn" onclick="UIComponents.toggleMobileMenu()">☰</button>
        </div>
      </div>
    `;
    this.initRealtime(user);

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

    // Update favorite nav badge
    this.updateFavoriteBadge = function () {
      if (typeof window.saveFavoritesToStorage === 'function') {
        window.saveFavoritesToStorage();
      }

      const likedProducts = (window.FASTFOOD_DATA && window.FASTFOOD_DATA.products) || [];
      const likedRestaurants = (window.FASTFOOD_DATA && window.FASTFOOD_DATA.restaurants) || [];
      const totalLiked = likedProducts.filter(p => p.isLiked).length + likedRestaurants.filter(r => r.isLiked).length;

      const badge = document.getElementById('nav-favorite-badge');
      if (badge) {
        badge.textContent = totalLiked;
        badge.style.display = totalLiked > 0 ? 'flex' : 'none';
      }

      const favBtn = document.querySelector('.favorite-nav-btn');
      if (favBtn) {
        if (totalLiked > 0) {
          favBtn.classList.add('is-liked');
          favBtn.textContent = '♥';
        } else {
          favBtn.classList.remove('is-liked');
          favBtn.textContent = '♡';
        }
      }
    };

    // Khởi tạo badge số lượng yêu thích ngay khi nạp header
    this.updateFavoriteBadge();

    // Lắng nghe thay đổi storage từ các tab khác để đồng bộ real-time
    window.addEventListener('storage', (e) => {
      if (e.key === 'fastfood-favorites-v1') {
        if (typeof window.loadFavoritesFromStorage === 'function') {
          window.loadFavoritesFromStorage();
        }
        this.updateFavoriteBadge();
      }
    });
  },

  initRealtime(user) {
    if (window.initFastfoodRealtime) {
      window.initFastfoodRealtime(user);
      return;
    }

    if (window.fastfoodRealtimeScriptLoading) return;
    window.fastfoodRealtimeScriptLoading = true;
    const script = document.createElement('script');
    script.src = '/js/realtime.js';
    script.onload = () => window.initFastfoodRealtime?.(user);
    script.onerror = () => {
      window.fastfoodRealtimeScriptLoading = false;
      console.error('Không thể tải mô-đun realtime.');
    };
    document.body.appendChild(script);
  },

  requireLogin() {
    const user = window.StorageManager?.getUser();
    if (user?.isLoggedIn) return true;

    const next = `${window.location.pathname}${window.location.search}`;
    window.location.replace(`/pages/login.html?next=${encodeURIComponent(next)}`);
    return false;
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
            <a href="./home.html" class="footer-brand-logo" aria-label="FASTFOOD Buôn Ma Thuột - Trang chủ">
              <img src="../assets/images/logo-footer.svg" alt="FASTFOOD Buôn Ma Thuột" width="360" height="64">
            </a>
            <p class="footer-brand-desc">
              FASTFOOD Buôn Ma Thuột - Đặt món ngon địa phương, giao tận nơi trong thành phố.
            </p>
            <div class="footer-quick-links" aria-label="Liên hệ nhanh">
              <a href="tel:19008888" aria-label="Gọi hotline 1900 8888" title="Gọi hotline">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5 9 3l2 4.7-2 1.7a15 15 0 0 0 5.6 5.6l1.7-2 4.7 2-.5 2.4a3 3 0 0 1-3.3 2.4A17.5 17.5 0 0 1 4.2 6.8a3 3 0 0 1 2.4-3.3Z"/></svg>
              </a>
              <a href="mailto:hotro@fastfood.vn" aria-label="Gửi email hỗ trợ" title="Gửi email">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
              </a>
              <a href="https://www.google.com/maps/search/?api=1&query=Buon+Ma+Thuot%2C+Dak+Lak" target="_blank" rel="noopener noreferrer" aria-label="Xem Buôn Ma Thuột trên bản đồ" title="Xem bản đồ">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
              </a>
              <a href="#support-contact" aria-label="Thông tin hỗ trợ" title="Thông tin hỗ trợ">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"/><path d="M4 12h3v7H6a2 2 0 0 1-2-2zm16 0h-3v7h1a2 2 0 0 0 2-2zM9 21h4"/></svg>
              </a>
            </div>
          </div>

          <!-- Cột 2: Khám phá -->
          <div>
            <h4 class="footer-col-title">Khám Phá</h4>
            <div class="footer-links">
              <a href="./home.html" class="footer-link">Trang chủ FASTFOOD</a>
              <a href="./restaurants.html" class="footer-link">Danh sách quán ăn</a>
              <a href="./home.html#best-sellers" class="footer-link">Món ăn bán chạy</a>
              <a href="./home.html#flash-deals" class="footer-link">Flash Deals giảm giá</a>
              <a href="./cart.html" class="footer-link">Giỏ hàng của bạn</a>
            </div>
          </div>

          <!-- Cột 3: Về chúng tôi -->
          <div>
            <h4 class="footer-col-title">Chính Sách & Hỗ Trợ</h4>
            <div class="footer-links">
              <a href="./policies.html#terms" class="footer-link">Điều khoản dịch vụ</a>
              <a href="./policies.html#privacy" class="footer-link">Chính sách bảo mật</a>
              <a href="./policies.html#food-safety" class="footer-link">Quy chuẩn vệ sinh an toàn</a>
              <a href="./order-tracking.html" class="footer-link">Theo dõi đơn hàng</a>
              <a href="./restaurant/dashboard.html" class="footer-link">Đăng ký làm đối tác quán</a>
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
              <span>Buôn Ma Thuột, Đắk Lắk</span>
            </div>
            <div style="background:#24262A; padding:0.75rem 1rem; border-radius:var(--radius-md); margin-top:1rem;">
              <small style="color:#FFA24A; font-weight:700; display:block;">GIAO ĐỒ ĂN NHANH</small>
              <span style="font-size:0.8rem; color:#A0A5AA;">Cam kết giao trễ quá 30 phút tặng voucher 50K</span>
            </div>
          </div>
        </div>

        <div class="footer-bottom" id="support-contact">
          <div>&copy; 2026 FASTFOOD Việt Nam. Tất cả quyền được bảo lưu. Thiết kế chuẩn Modern Food Delivery.</div>
          <div class="footer-bottom-links">
            <a href="./policies.html#privacy">Chính Sách Riêng Tư</a>
            <a href="./policies.html#terms">Quy Chế Hoạt Động</a>
            <a href="./policies.html#complaints">Giải Quyết Khiếu Nại</a>
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

    const toppings = Array.isArray(product.toppings) ? product.toppings : [];

    overlay.innerHTML = `
      <div class="modal-card">
        <!-- Hero Image Header with Gradient & Close Button -->
        <div class="modal-hero-wrap">
          <img src="${product.image}" alt="${this.escapeHtml(product.name)}" class="modal-hero-img" onerror="this.onerror=null; this.style.visibility='hidden';">
          <div class="modal-hero-overlay"></div>
          <button class="modal-close-btn" type="button" title="Đóng cửa sổ" onclick="UIComponents.closeFoodModal()">✕</button>
        </div>

        <!-- Scrollable Modal Body -->
        <div class="modal-body-scroll">
          <div class="modal-restaurant-badge">
            <span>🍔</span>
            <span>${this.escapeHtml(product.restaurantName || 'FASTFOOD')}</span>
          </div>

          <h3 class="modal-food-title">${this.escapeHtml(product.name)}</h3>
          <p class="modal-food-desc">${this.escapeHtml(product.description || 'Món ăn chuẩn vị thơm ngon, chế biến nóng hổi ngay khi nhận đơn.')}</p>
          
          <div class="modal-price-row">
            <span class="modal-current-price" id="modal-base-price-display">${UIComponents.formatVND(product.price)}</span>
            ${product.oldPrice ? `<span class="modal-old-price">${UIComponents.formatVND(product.oldPrice)}</span>` : ''}
          </div>

          <!-- Toppings Selection Area -->
          ${toppings.length ? `
            <div class="modal-toppings-card">
              <div class="modal-toppings-title">
                <span>✨ Tùy chọn thêm (Toppings):</span>
                <small>${toppings.length} tùy chọn</small>
              </div>
              <div class="modal-toppings-list">
                ${toppings.map((t, idx) => `
                  <label class="modal-topping-item" id="topping-item-${idx}">
                    <span class="modal-topping-left">
                      <input type="checkbox" class="modal-topping-cb" data-name="${this.escapeHtml(t.name)}" data-price="${t.price}" onchange="UIComponents.onToppingToggle(this, 'topping-item-${idx}', '${product.id}')">
                      <span>${this.escapeHtml(t.name)}</span>
                    </span>
                    <span class="modal-topping-price">+${UIComponents.formatVND(t.price)}</span>
                  </label>
                `).join('')}
              </div>
            </div>
          ` : ''}
        </div>

        <!-- Sticky Bottom Actions Toolbar -->
        <div class="modal-footer-toolbar">
          <div class="modal-quantity-control quantity-control">
            <button class="modal-qty-btn qty-btn" type="button" title="Giảm số lượng" onclick="UIComponents.adjustModalQty(-1, '${product.id}')">−</button>
            <input type="text" id="modal-qty-input" class="modal-qty-input qty-input" value="1" readonly>
            <button class="modal-qty-btn qty-btn" type="button" title="Tăng số lượng" onclick="UIComponents.adjustModalQty(1, '${product.id}')">+</button>
          </div>

          <button class="modal-add-btn" type="button" id="modal-submit-add-btn" onclick="UIComponents.confirmAddToCart('${product.id}')">
            <span>Thêm Vào Giỏ Hàng</span>
            <span id="modal-btn-total-price" class="modal-btn-price-badge">${UIComponents.formatVND(product.price)}</span>
          </button>
        </div>
      </div>
    `;

    overlay.classList.add('active');
  },

  onToppingToggle(checkbox, itemId, productId) {
    const itemEl = document.getElementById(itemId);
    if (itemEl) {
      itemEl.classList.toggle('checked', checkbox.checked);
    }
    this.updateModalCalculatedTotal(productId);
  },

  adjustModalQty(delta, productId) {
    const input = document.getElementById('modal-qty-input');
    if (!input) return;
    let qty = parseInt(input.value) || 1;
    qty = Math.max(1, qty + delta);
    input.value = qty;
    this.updateModalCalculatedTotal(productId);
  },

  updateModalCalculatedTotal(productId) {
    const product = window.FASTFOOD_DATA.products.find(p => p.id === productId);
    if (!product) return;

    const input = document.getElementById('modal-qty-input');
    const qty = input ? parseInt(input.value) || 1 : 1;

    let toppingsTotal = 0;
    document.querySelectorAll('.modal-topping-cb:checked').forEach(cb => {
      toppingsTotal += (parseInt(cb.dataset.price) || 0);
    });

    const total = (product.price + toppingsTotal) * qty;
    const btnPriceEl = document.getElementById('modal-btn-total-price');
    if (btnPriceEl) {
      btnPriceEl.innerText = UIComponents.formatVND(total);
    }
  },

  confirmAddToCart(productId) {
    const user = window.StorageManager ? window.StorageManager.getUser() : null;
    if (user && user.isLoggedIn && user.role === 'admin') {
      this.showToast('Tài khoản Quản trị viên (Admin) chỉ dùng để quản lý hệ thống, không thể mua hàng!', 'warning', 3500);
      this.closeFoodModal();
      return;
    }

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

  // Tự động khởi tạo Trợ lý FASTFOOD AI Chatbot nổi trên toàn hệ thống
  initChatbot() {
    if (document.querySelector('script[src*="chatbot.js"]') || window.FASTFOODChatbot) return;
    const script = document.createElement('script');
    script.src = '/js/chatbot.js';
    script.defer = true;
    document.body.appendChild(script);
  },
};

// Tự động nạp Trợ lý AI khi trang sẵn sàng
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => UIComponents.initChatbot());
} else {
  UIComponents.initChatbot();
}

window.UIComponents = UIComponents;
