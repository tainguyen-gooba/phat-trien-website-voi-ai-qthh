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

  get(key, defaultValue = null) {
    try {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : defaultValue;
    } catch (e) {
      console.error(`Error reading ${key} from localStorage:`, e);
      return defaultValue;
    }
  },

  set(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
      console.error(`Error saving ${key} to localStorage:`, e);
    }
  },

  remove(key) {
    localStorage.removeItem(key);
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
    const hasToken = Boolean(
      localStorage.getItem('fastfood_auth_token')
      || sessionStorage.getItem('fastfood_auth_token')
    );
    const user = this.get(this.KEYS.USER);
    return hasToken && user?.isLoggedIn ? user : null;
  },

  setUser(user) {
    this.set(this.KEYS.USER, user);
    window.dispatchEvent(new CustomEvent('userUpdated', { detail: user }));
  },

  clearAuth() {
    localStorage.removeItem('fastfood_auth_token');
    sessionStorage.removeItem('fastfood_auth_token');
    this.remove(this.KEYS.USER);
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

window.StorageManager = StorageManager;
