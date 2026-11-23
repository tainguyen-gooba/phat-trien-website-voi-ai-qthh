/**
 * f04-checkout-redemption.test.js - Tier 1: F4 Checkout Points Direct Redemption
 *
 * Tests:
 * 1. Checkout accepts points_to_use parameter
 * 2. Conversion rate 100 points = 10,000 VNĐ discount
 * 3. User points_balance is deducted atomically by points_to_use
 * 4. Created order record populates points_used and points_discount columns
 * 5. point_transactions ledger records REDEEM_CHECKOUT with negative points and balance_after
 */

const { describe, it, before, after, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

function runLoyaltyPhp(code) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    ${code}
  `;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return JSON.parse(output.trim());
}

describe('Tier 1: F4 - Checkout Points Direct Redemption', () => {
  let client;
  let testUserId;
  let testEmail;
  let testOrderCode;
  let testOrderId;

  before(async () => {
    client = new TestClient();
  });

  beforeEach(async () => {
    testEmail = fixtures.generateTestEmail('e2e_f04');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'E2E F04 Customer', ?, '0904000001', 'hash', 'active', 'SILVER', 500, 1500000, NOW())
    `, [testEmail]);
    const u = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    testUserId = u.id;

    testOrderCode = 'E2E_C' + Date.now().toString().slice(-6) + Math.floor(1000 + Math.random() * 9000);
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'E2E F04 Customer', '0904000001', '123 Street', 200000, 0, 200000, 'cod', 'pending', 'placed', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;
  });

  afterEach(async () => {
    if (testUserId) {
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM orders WHERE customer_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
      testUserId = null;
      testOrderId = null;
    }
  });

  it('F4-01: Accepts points_to_use parameter and validates minimum points threshold', async () => {
    // Under minimum threshold (e.g. 50 points when min is 100) should be rejected
    const underMin = runLoyaltyPhp(`
      $res = redeemPointsAtCheckout(${testUserId}, ${testOrderId}, 50, '${testOrderCode}');
      echo json_encode($res);
    `);
    assert.equal(underMin.success, false, 'Redeeming under min_redeem_points (100) must fail');
    assert.ok(underMin.error.includes('100'), 'Error message should indicate min 100 points required');

    // Valid points >= 100 should be accepted
    const valid = runLoyaltyPhp(`
      $res = redeemPointsAtCheckout(${testUserId}, ${testOrderId}, 100, '${testOrderCode}');
      echo json_encode($res);
    `);
    assert.equal(valid.success, true, 'Redeeming 100 points should succeed');
    assert.equal(valid.points_used, 100);
  });

  it('F4-02: Applies exact conversion rate of 100 points = 10,000 VNĐ discount', async () => {
    // 200 points should yield 20,000 VNĐ discount
    const res = runLoyaltyPhp(`
      $res = redeemPointsAtCheckout(${testUserId}, ${testOrderId}, 200, '${testOrderCode}');
      echo json_encode($res);
    `);
    assert.ok(res.success, 'Redemption should succeed');
    assert.equal(res.points_used, 200, 'Points used must be 200');
    assert.equal(res.discount_amount, 20000, 'Discount must be 20,000 VNĐ (100 pts = 10,000 VNĐ)');

    const expectedDiscount = fixtures.calculateExpectedDiscount(200);
    assert.equal(res.discount_amount, expectedDiscount, 'Matches fixture expected discount calculation');
  });

  it('F4-03: Deducts redeemed points atomically from users.points_balance', async () => {
    const initialUser = dbHelper.getUserLoyalty(testUserId);
    assert.equal(initialUser.points_balance, 500, 'Initial points balance should be 500');

    // Redeem 150 points
    const res = runLoyaltyPhp(`
      $res = redeemPointsAtCheckout(${testUserId}, ${testOrderId}, 150, '${testOrderCode}');
      echo json_encode($res);
    `);
    assert.ok(res.success);

    // Verify DB
    const updatedUser = dbHelper.getUserLoyalty(testUserId);
    assert.equal(updatedUser.points_balance, 350, 'users.points_balance must be decremented from 500 to 350');
  });

  it('F4-04: Populates orders.points_used and orders.points_discount columns in database', async () => {
    // Redeem 300 points (30,000 VNĐ discount)
    runLoyaltyPhp(`
      $res = redeemPointsAtCheckout(${testUserId}, ${testOrderId}, 300, '${testOrderCode}');
      echo json_encode($res);
    `);

    const orderLoyalty = dbHelper.getOrderLoyalty(testOrderCode);
    assert.ok(orderLoyalty, 'Order record must exist');
    assert.equal(orderLoyalty.points_used, 300, 'orders.points_used must be 300');
    assert.equal(orderLoyalty.points_discount, 30000, 'orders.points_discount must be 30,000.00');
  });

  it('F4-05: Appends atomic REDEEM_CHECKOUT transaction with negative points and correct balance_after', async () => {
    // Redeem 100 points
    runLoyaltyPhp(`
      $res = redeemPointsAtCheckout(${testUserId}, ${testOrderId}, 100, '${testOrderCode}');
      echo json_encode($res);
    `);

    const txs = dbHelper.getTransactions(testUserId);
    assert.equal(txs.length, 1, 'Should create exactly 1 transaction in point_transactions');

    const tx = txs[0];
    assert.equal(tx.transaction_type, fixtures.TRANSACTION_TYPES.REDEEM_CHECKOUT);
    assert.equal(Number(tx.points), -100, 'Transaction points must be negative (-100)');
    assert.equal(Number(tx.balance_after), 400, 'balance_after must be 400 (500 - 100)');
    assert.equal(Number(tx.order_id), testOrderId, 'Transaction order_id must match testOrderId');
    assert.ok(tx.description && tx.description.includes(testOrderCode), 'Description must reference the order code');
  });

});
