/**
 * FASTFOOD LOCAL STORAGE & STATE MANAGER
 */

const StorageManager = {
  KEYS: {
    CART: 'fastfood_cart',
    USER: 'fastfood_user',
    ORDERS: 'fastfood_orders',
    ACTIVE_ORDER: 'fastfood_active_order',
    COUPON: 'fastfood_applied_coupon',
    AVAILABLE_COUPONS: 'fastfood_available_coupons',
  },

  SESSION_KEYS: new Set(['fastfood_user', 'fastfood_cart', 'fastfood_orders', 'fastfood_active_order', 'fastfood_applied_coupon', 'fastfood_available_coupons']),

  getStorage(key) {
    return this.SESSION_KEYS.has(key) ? sessionStorage : localStorage;
  },

  get(key, defaultValue = null) {
    try {
      const data = this.getStorage(key).getItem(key);
      return data ? JSON.parse(data) : defaultValue;
    } catch (e) {
      console.error(`Error reading ${key} from localStorage:`, e);
      return defaultValue;
    }
  },

  set(key, value) {
    try {
      this.getStorage(key).setItem(key, JSON.stringify(value));
    } catch (e) {
      console.error(`Error saving ${key} to localStorage:`, e);
    }
  },

  remove(key) {
    this.getStorage(key).removeItem(key);
  },

  // Helpers
  getCart() {
    return this.get(this.KEYS.CART, []);
  },

  setCart(cart) {
    this.set(this.KEYS.CART, cart);
    // Dispatch custom event for real-time updates across navbar and pages
    window.dispatchEvent(new CustomEvent('cartUpdated', { detail: cart }));
  },

  getUser() {
    const hasToken = Boolean(sessionStorage.getItem('fastfood_auth_token'));
    const user = this.get(this.KEYS.USER);
    return hasToken && user?.isLoggedIn ? user : null;
  },

  setUser(user) {
    this.set(this.KEYS.USER, user);
    window.dispatchEvent(new CustomEvent('userUpdated', { detail: user }));
  },

  clearAuth() {
    sessionStorage.removeItem('fastfood_auth_token');
    for (const key of this.SESSION_KEYS) sessionStorage.removeItem(key);
    localStorage.removeItem('fastfood_auth_token');
    localStorage.removeItem(this.KEYS.USER);
    window.dispatchEvent(new CustomEvent('userUpdated', { detail: null }));
  },

  getOrders() {
    return this.get(this.KEYS.ORDERS, []);
  },

  addOrder(order) {
    const orders = this.getOrders();
    orders.unshift(order);
    this.set(this.KEYS.ORDERS, orders);
    this.set(this.KEYS.ACTIVE_ORDER, order);
  },

  getActiveOrder() {
    return this.get(this.KEYS.ACTIVE_ORDER, null);
  },
};

localStorage.removeItem('fastfood_auth_token');
localStorage.removeItem(StorageManager.KEYS.USER);

window.StorageManager = StorageManager;

// =====================================================================
// FASTFOOD FAVORITES MANAGER (LƯU & ĐỒNG BỘ MÓN ĂN YÊU THÍCH)
// =====================================================================
const FavoritesManager = {
  KEY: 'fastfood_user_favorites',

  getIds() {
    try {
      const data = localStorage.getItem(this.KEY);
      const parsed = data ? JSON.parse(data) : [];
      return Array.isArray(parsed) ? parsed.map(Number).filter(n => n > 0) : [];
    } catch (e) {
      return [];
    }
  },

  has(productId) {
    const ids = this.getIds();
    return ids.includes(Number(productId));
  },

  toggle(productId, btn = null) {
    const idNum = Number(productId);
    if (!idNum) return false;

    let ids = this.getIds();
    const index = ids.indexOf(idNum);
    let isLiked = false;

    if (index > -1) {
      ids.splice(index, 1);
      isLiked = false;
    } else {
      ids.unshift(idNum);
      isLiked = true;
    }

    try {
      localStorage.setItem(this.KEY, JSON.stringify(ids));
    } catch (e) {
      console.error('Lỗi khi lưu yêu thích vào localStorage:', e);
    }

    // Cập nhật trạng thái tất cả các nút trái tim của sản phẩm này trên trang
    document.querySelectorAll(`.favorite-btn[data-id="${idNum}"]`).forEach((b) => {
      b.classList.toggle('is-liked', isLiked);
      b.textContent = isLiked ? '♥' : '♡';
    });
    if (btn) {
      btn.classList.toggle('is-liked', isLiked);
      btn.textContent = isLiked ? '♥' : '♡';
    }

    // Cập nhật số lượng trên thanh menu
    this.updateBadge();

    // Thông báo Toast
    if (window.UIComponents?.showToast) {
      window.UIComponents.showToast(
        isLiked ? 'Đã thêm vào danh sách yêu thích!' : 'Đã xóa khỏi danh sách yêu thích.',
        isLiked ? 'success' : 'info'
      );
    }

    // Bắn sự kiện đồng bộ
    window.dispatchEvent(new CustomEvent('favoritesUpdated', { detail: { ids, productId: idNum, isLiked } }));

    // Gọi API lưu phiên nền
    try {
      fetch('/api/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle', productId: idNum })
      }).catch(() => {});
    } catch (e) {}

    return isLiked;
  },

  updateBadge() {
    const ids = this.getIds();
    const count = ids.length;
    const badge = document.getElementById('nav-favorite-badge');
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  },

  syncButtons() {
    const ids = this.getIds();
    document.querySelectorAll('.favorite-btn').forEach((btn) => {
      let id = btn.dataset.id;
      if (!id) {
        const match = (btn.getAttribute('onclick') || '').match(/toggleFavorite\((\d+)/);
        if (match) {
          id = match[1];
          btn.dataset.id = id;
        }
      }
      if (id && ids.includes(Number(id))) {
        btn.classList.add('is-liked');
        btn.textContent = '♥';
      } else {
        btn.classList.remove('is-liked');
        btn.textContent = '♡';
      }
    });
    this.updateBadge();
  }
};

window.FavoritesManager = FavoritesManager;

// Hàm gọi toàn cục từ onclick của các button món ăn
window.toggleFavorite = function(productId, btn) {
  FavoritesManager.toggle(productId, btn);
};

// Tự động đồng bộ ngay khi trang sẵn sàng
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => FavoritesManager.syncButtons());
} else {
  FavoritesManager.syncButtons();
}
