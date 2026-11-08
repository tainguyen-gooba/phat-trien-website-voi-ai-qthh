/**
 * System User Roles Constant Reference
 */
const ROLES = {
  ADMIN: 'admin',
  CUSTOMER: 'customer',
  RESTAURANT_OWNER: 'restaurant_owner',
  SHIPPER: 'shipper',
};

const ORDER_STATUS = {
  PENDING: 'pending',
  CONFIRMED: 'confirmed',
  PREPARING: 'preparing',
  DELIVERING: 'delivering',
  DELIVERED: 'delivered',
  CANCELLED: 'cancelled',
};

const PAYMENT_METHOD = {
  COD: 'cod',
  VNPAY: 'vnpay',
  MOMO: 'momo',
  BANKING: 'banking',
};

const PAYMENT_STATUS = {
  PENDING: 'pending',
  COMPLETED: 'completed',
  FAILED: 'failed',
  REFUNDED: 'refunded',
};

module.exports = {
  ROLES,
  ORDER_STATUS,
  PAYMENT_METHOD,
  PAYMENT_STATUS,
};

