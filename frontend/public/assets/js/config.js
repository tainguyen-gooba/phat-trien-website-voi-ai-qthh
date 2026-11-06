/**
 * FASTFOOD Frontend Configuration
 */
const CONFIG = {
  API_BASE_URL: window.location.port === '5000'
    ? `${window.location.origin}/api/v1`
    : 'http://localhost:5000/api/v1',
  
  STORAGE_KEYS: {
    AUTH_TOKEN: 'fastfood_auth_token',
    USER_INFO: 'fastfood_user_info',
    CART: 'fastfood_cart_items',
  },
  
  DEFAULT_PAGE_SIZE: 12,
};

window.CONFIG = CONFIG;

