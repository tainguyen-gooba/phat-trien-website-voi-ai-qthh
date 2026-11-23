/**
 * loyalty-fixtures.js - Standard test constants, fixtures, and account definitions
 * for FASTFOOD Membership Tiers & Loyalty Reward System E2E testing.
 */

const TIERS = {
  BRONZE: 'BRONZE',
  SILVER: 'SILVER',
  GOLD: 'GOLD',
  DIAMOND: 'DIAMOND'
};

const TIER_THRESHOLDS = {
  BRONZE: 0,
  SILVER: 1000000,   // 1,000,000 VNĐ
  GOLD: 3000000,     // 3,000,000 VNĐ
  DIAMOND: 5000000   // 5,000,000 VNĐ
};

const TIER_RATIOS = {
  BRONZE: 1.0,  // 1.0 pt per 10k VNĐ
  SILVER: 1.5,  // 1.5 pts per 10k VNĐ
  GOLD: 2.0,    // 2.0 pts per 10k VNĐ
  DIAMOND: 3.0  // 3.0 pts per 10k VNĐ
};

const SPENDING_STEP = 10000; // 10,000 VNĐ per earning unit

const REDEMPTION_RATE = {
  POINTS_UNIT: 100,        // 100 points
  DISCOUNT_UNIT: 10000,    // = 10,000 VNĐ
  VND_PER_POINT: 100       // 1 point = 100 VNĐ discount
};

const VOUCHER_PACKAGES = [
  {
    id: 1,
    packageId: 1,
    title: 'Voucher Giảm 10.000đ',
    points_required: 100,
    discount_amount: 10000,
    min_order_amount: 0
  },
  {
    id: 2,
    packageId: 2,
    title: 'Voucher Giảm 20.000đ',
    points_required: 200,
    discount_amount: 20000,
    min_order_amount: 0
  },
  {
    id: 3,
    packageId: 3,
    title: 'Voucher Giảm 50.000đ',
    points_required: 500,
    discount_amount: 50000,
    min_order_amount: 0
  }
];

const TRANSACTION_TYPES = {
  EARN: 'EARN',
  REDEEM_CHECKOUT: 'REDEEM_CHECKOUT',
  REDEEM_VOUCHER: 'REDEEM_VOUCHER',
  REFUND_REVOKE: 'REFUND_REVOKE',
  REFUND_RESTORE: 'REFUND_RESTORE',
  ADMIN_ADJUST: 'ADMIN_ADJUST'
};

const ACCOUNTS = {
  admin: {
    id: 1,
    email: 'admin@fastfood.vn',
    password: '123456',
    role_id: 1,
    role: 'admin',
    name: 'Quản Trị Viên'
  },
  staff: {
    id: 14,
    email: 'staff1@fastfood.vn',
    password: '123456',
    role_id: 5,
    role: 'staff',
    name: 'Nhân Viên 1'
  },
  customer: {
    id: 3,
    email: 'customer@fastfood.vn',
    password: '123456',
    role_id: 2,
    role: 'customer',
    name: 'Trần Khách Hàng'
  }
};

/**
 * Calculates expected member tier based on total spent amount.
 *
 * @param {number} totalSpent
 * @returns {string} Tier code
 */
function calculateExpectedTier(totalSpent) {
  const spent = Math.max(0, Number(totalSpent) || 0);
  if (spent >= TIER_THRESHOLDS.DIAMOND) return TIERS.DIAMOND;
  if (spent >= TIER_THRESHOLDS.GOLD) return TIERS.GOLD;
  if (spent >= TIER_THRESHOLDS.SILVER) return TIERS.SILVER;
  return TIERS.BRONZE;
}

/**
 * Calculates expected points earned from an order amount given tier.
 * Formula: floor(orderAmount / 10000) * ratio (floored to integer)
 *
 * @param {number} orderAmount
 * @param {string} tierCode
 * @returns {number} Points earned
 */
function calculateExpectedPoints(orderAmount, tierCode = TIERS.BRONZE) {
  const amount = Math.max(0, Number(orderAmount) || 0);
  const ratio = TIER_RATIOS[tierCode] !== undefined ? TIER_RATIOS[tierCode] : 1.0;
  const units = Math.floor(amount / SPENDING_STEP);
  return Math.floor(units * ratio);
}

/**
 * Calculates discount amount in VND for given points used.
 * Formula: (points / 100) * 10,000 = points * 100
 *
 * @param {number} points
 * @returns {number} VND discount
 */
function calculateExpectedDiscount(points) {
  const pts = Math.max(0, Math.floor(Number(points) || 0));
  return pts * REDEMPTION_RATE.VND_PER_POINT;
}

/**
 * Generates an isolated test order code with 'E2E' prefix for easy cleanup.
 *
 * @param {string} [prefix='E2E']
 * @returns {string}
 */
function generateTestOrderCode(prefix = 'E2E') {
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `${prefix}${Date.now().toString().slice(-4)}${rand}`;
}

/**
 * Generates an isolated test customer email with 'e2e_' prefix.
 *
 * @param {string} [prefix='e2e']
 * @returns {string}
 */
function generateTestEmail(prefix = 'e2e') {
  const rand = Math.random().toString(36).slice(2, 8);
  return `${prefix}_${Date.now()}_${rand}@fastfood.test`;
}

module.exports = {
  TIERS,
  TIER_THRESHOLDS,
  TIER_RATIOS,
  SPENDING_STEP,
  REDEMPTION_RATE,
  VOUCHER_PACKAGES,
  TRANSACTION_TYPES,
  ACCOUNTS,
  calculateExpectedTier,
  calculateExpectedPoints,
  calculateExpectedDiscount,
  generateTestOrderCode,
  generateTestEmail
};
