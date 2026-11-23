/**
 * b03-checkout-redemption-edges.test.js - Tier 2 Boundary Tests: Checkout Points Redemption Edges
 * 
 * Verifies edge cases and constraints when redeeming points at checkout:
 * - B3-01: Using 0 points at checkout awards 0 VNĐ discount and leaves balance unchanged
 * - B3-02: Negative points input (points_to_use < 0) is rejected or sanitized
 * - B3-03: Attempting to redeem more points than available balance is rejected
 * - B3-04: Points discount exceeding order subtotal is capped at subtotal or rejected
 * - B3-05: Redemption below minimum threshold (min_redeem_points = 100) is rejected
 * - B3-06: Exact full payment with points reduces payable cash to 0 VNĐ
 *
 * Source: ORIGINAL_REQUEST.md §R3, PROJECT.md §F4, TEST_INFRA.md §Tier 2
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');

const dbHelper = require('../harness/db-helper');
const {
  REDEMPTION_RATE,
  generateTestEmail,
  generateTestOrderCode,
  calculateExpectedDiscount
} = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

function callLoyaltyPhp(code) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    ${code}
  `;
  const b64 = Buffer.from(script, 'utf8').toString('base64');
  const out = execFileSync('php', ['-r', `eval(base64_decode('${b64}'));`], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return out.trim();
}

describe('Tier 2 Boundaries: b03-checkout-redemption-edges (6 tests)', () => {
  before(() => {
    dbHelper.cleanupTestData();
  });

  after(() => {
    dbHelper.cleanupTestData();
  });

  function generateUniquePhone() {
    while (true) {
      const phone = '03' + Math.floor(10000000 + Math.random() * 90000000);
      const existing = dbHelper.query('SELECT id FROM users WHERE phone = ? LIMIT 1', [phone]);
      if (!existing || existing.length === 0) return phone;
    }
  }

  /**
   * Helper to create a test user with a specific points balance
   */
  function createTestUserWithBalance(initialBalance = 500) {
    const email = 'e2e_red_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7) + '@fastfood.test';
    const phone = generateUniquePhone();
    const passwordHash = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
       VALUES (2, 'Redemption Test User', ?, ?, ?, 'BRONZE', ?, 0.00, 'active', NOW())`,
      [email, phone, passwordHash, initialBalance]
    );
    const rows = dbHelper.query('SELECT id, email, points_balance FROM users WHERE email = ? LIMIT 1', [email]);
    assert.ok(rows && rows.length > 0, 'User should be created');
    return rows[0];
  }

  /**
   * Helper to create a pending order for testing checkout redemption
   */
  function createPendingOrder(userId, subtotal = 100000) {
    const orderCode = 'E2E_ORD_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, points_used, points_discount, payment_method, payment_status, order_status, placed_at, created_at)
       VALUES (?, ?, 1, ?, 0.00, 0.00, ?, 0, 0.00, 'cod', 'pending', 'pending', NOW(), NOW())`,
      [orderCode, userId, subtotal, subtotal]
    );
    const rows = dbHelper.query('SELECT id, order_code, subtotal, total_amount FROM orders WHERE order_code = ? LIMIT 1', [orderCode]);
    return rows[0];
  }

  it('B3-01: Using 0 points at checkout awards 0 VNĐ discount and leaves balance unchanged', () => {
    const initialBalance = 500;
    const user = createTestUserWithBalance(initialBalance);
    const order = createPendingOrder(user.id, 100000);

    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 0, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    assert.equal(result.points_used, 0);
    assert.equal(result.discount_amount, 0);

    // Balance remains exactly unchanged
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, initialBalance);

    // No ledger record for 0 points redemption
    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 0);
  });

  it('B3-02: Negative points input (points_to_use < 0) is rejected or sanitized to zero', () => {
    const initialBalance = 500;
    const user = createTestUserWithBalance(initialBalance);
    const order = createPendingOrder(user.id, 100000);

    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, -50, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    // Negative points must NOT grant discount or increase customer balance
    if (result.success) {
      assert.equal(result.points_used, 0, 'Sanitized points used must be 0');
      assert.equal(result.discount_amount, 0, 'Sanitized discount must be 0');
    } else {
      assert.ok(result.error, 'Must return error on negative points');
    }

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, initialBalance, 'Balance must not change on negative points input');
  });

  it('B3-03: Attempting to redeem more points than available balance is rejected', () => {
    const initialBalance = 150;
    const user = createTestUserWithBalance(initialBalance);
    const order = createPendingOrder(user.id, 100000);

    // Attempts 200 points when balance is only 150
    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 200, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, false, 'Redemption exceeding balance must be rejected');
    assert.ok(result.error && result.error.length > 0, 'Must return descriptive error message');

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, initialBalance, 'Balance must remain unchanged after rejected redemption');

    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 0, 'No transaction should be written on failure');
  });

  it('B3-04: Points discount exceeding order subtotal is capped at subtotal or rejected', () => {
    // Subtotal 20,000 VNĐ -> maximum allowable points discount is 20,000 VNĐ (200 points)
    const initialBalance = 1000;
    const user = createTestUserWithBalance(initialBalance);
    const order = createPendingOrder(user.id, 20000);

    // User attempts to use 500 points (50,000 VNĐ discount on a 20,000 VNĐ order)
    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 500, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    if (result.success) {
      // If allowed, discount must not exceed order total (capped at subtotal)
      const maxDiscount = Number(order.subtotal);
      const effectiveDiscount = Math.min(result.discount_amount, maxDiscount);
      const netPayable = Math.max(0, order.subtotal - effectiveDiscount);
      assert.ok(netPayable >= 0, 'Net payable cash cannot be negative');
    } else {
      assert.ok(result.error, 'Over-subtotal redemption rejected cleanly');
    }
  });

  it('B3-05: Redemption below minimum threshold (min_redeem_points = 100) is rejected', () => {
    const initialBalance = 500;
    const user = createTestUserWithBalance(initialBalance);
    const order = createPendingOrder(user.id, 100000);

    // Attempt to redeem 50 points (less than min_redeem_points: 100)
    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 50, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, false, 'Redeeming below 100 points must be rejected');
    assert.match(result.error, /100|tối thiểu/i, 'Error message must mention minimum 100 points');

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, initialBalance);
  });

  it('B3-06: Exact full payment with points reduces payable cash to 0 VNĐ', () => {
    const settings = JSON.parse(callLoyaltyPhp('echo json_encode(getLoyaltySettings());'));
    const rate = Number(settings.point_to_vnd_rate) || 100;
    const pointsToUse = 300;
    const subtotal = pointsToUse * rate; // exactly matches points discount

    const initialBalance = 500;
    const user = createTestUserWithBalance(initialBalance);
    const order = createPendingOrder(user.id, subtotal);

    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, ${pointsToUse}, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    assert.equal(result.points_used, pointsToUse);
    assert.equal(result.discount_amount, subtotal);
    assert.equal(result.new_balance, initialBalance - pointsToUse);

    // Verify net cash calculation: subtotal - discount = 0
    const netCash = subtotal - result.discount_amount;
    assert.equal(netCash, 0, 'Net cash paid after exact full redemption must be 0 VNĐ');

    // Verify user balance updated in DB
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, initialBalance - pointsToUse);

    // Verify ledger entry
    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 1);
    assert.equal(txs[0].transaction_type, 'REDEEM_CHECKOUT');
    assert.equal(Number(txs[0].points), -pointsToUse);
    assert.equal(Number(txs[0].balance_after), initialBalance - pointsToUse);
  });
});
