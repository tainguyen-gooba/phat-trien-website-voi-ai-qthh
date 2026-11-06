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
    return this.get(this.KEYS.USER, {
      id: 'usr-1',
      fullName: 'Trần Văn FastFood',
      email: 'customer@fastfood.vn',
      phone: '0903000003',
      address: '72 Lê Thánh Tôn, Bến Nghé, Quận 1, TP. HCM',
      isLoggedIn: true,
    });
  },

  setUser(user) {
    this.set(this.KEYS.USER, user);
    window.dispatchEvent(new CustomEvent('userUpdated', { detail: user }));
  },

  getOrders() {
    return this.get(this.KEYS.ORDERS, [
      {
        id: 'ORD-98213',
        createdAt: '2026-11-04 18:45',
        restaurantName: 'Burger King Station',
        items: [
          { name: 'Burger Bò Nướng Phô Mai', qty: 2, price: 69000 },
          { name: 'Khoai Tây Chiên Lắc Phô Mai', qty: 1, price: 35000 },
        ],
        total: 173000,
        status: 'delivered',
        statusText: 'Đã giao thành công',
      },
    ]);
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

