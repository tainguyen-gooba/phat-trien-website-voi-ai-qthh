/**
 * f05-order-completion.test.js - Tier 1: F5 Order Completion Auto-Points & Tier Upgrade
 *
 * Tests:
 * 1. Status 'completed' hook triggers points earning, whereas other statuses do not award points
 * 2. Floored points awarded according to customer tier multiplier (e.g. 1.5 for Silver)
 * 3. Atomically increments user points_balance and total_spent in users table
 * 4. Automatically upgrades tier when total_spent reaches the next tier threshold (e.g. Bronze -> Silver)
 * 5. Appends atomic EARN transaction with positive points and balance_after in point_transactions
 */

const { describe, it, before, after, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

function processOrderEarn(orderId) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    $res = processOrderEarnPoints(${orderId});
    echo json_encode($res);
  `;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return JSON.parse(output.trim());
}

describe('Tier 1: F5 - Order Completion Auto-Points & Tier Upgrade', () => {
  let testUserId;
  let testEmail;
  let testOrderCode;
  let testOrderId;

  beforeEach(async () => {
    testEmail = fixtures.generateTestEmail('e2e_f05');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'E2E F05 Customer', ?, '0905000001', 'hash', 'active', 'BRONZE', 10, 0, NOW())
    `, [testEmail]);
    const u = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    testUserId = u.id;
  });

  afterEach(async () => {
    if (testUserId) {
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM orders WHERE customer_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
      testUserId = null;
    }
  });

  it('F5-01: Only status completed triggers points calculation; non-completed status does not award points', async () => {
    testOrderCode = fixtures.generateTestOrderCode('E2E_DEL_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F05 Recip', '0905', '123 St', 200000, 0, 200000, 'cod', 'pending', 'delivering', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    // Call processOrderEarnPoints while order is still 'delivering'
    const notCompletedRes = processOrderEarn(testOrderId);
    assert.equal(notCompletedRes.success, false, 'Should fail to award points when order is not completed');
    assert.ok(notCompletedRes.error && notCompletedRes.error.includes('completed'), 'Error message must specify order not completed');

    const userBefore = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userBefore.points_balance, 10, 'Points balance must remain unchanged');

    // Update order status to 'completed'
    dbHelper.query("UPDATE orders SET order_status = 'completed' WHERE id = ?", [testOrderId]);
    const completedRes = processOrderEarn(testOrderId);
    assert.ok(completedRes.success, 'Points should be awarded once order is completed');
    assert.equal(completedRes.points_earned, 20, '200,000 VND order on BRONZE tier earns 20 points');
  });

  it('F5-02: Floored points calculated accurately using customer current tier ratio', async () => {
    // Set user to SILVER tier (multiplier 1.5)
    dbHelper.setUserLoyalty(testUserId, { member_tier: 'SILVER', total_spent: 1200000 });

    testOrderCode = fixtures.generateTestOrderCode('E2E_SLV_');
    // Order total = 250,000 VND -> 25 steps * 1.5 = 37.5 floored to 37 points
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F05 Recip', '0905', '123 St', 250000, 0, 250000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    const res = processOrderEarn(testOrderId);
    assert.ok(res.success);
    assert.equal(res.points_earned, 37, '250,000 VND on SILVER tier must yield floor(25 * 1.5) = 37 points');

    const expectedPoints = fixtures.calculateExpectedPoints(250000, fixtures.TIERS.SILVER);
    assert.equal(res.points_earned, expectedPoints, 'Matches fixture calculation');
  });

  it('F5-03: Atomically increments user points_balance and total_spent in users table', async () => {
    const userBefore = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userBefore.points_balance, 10);
    assert.equal(userBefore.total_spent, 0);

    testOrderCode = fixtures.generateTestOrderCode('E2E_INC_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F05 Recip', '0905', '123 St', 150000, 0, 150000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    const res = processOrderEarn(testOrderId);
    assert.ok(res.success);
    assert.equal(res.points_earned, 15); // 150k on Bronze = 15 pts

    const userAfter = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userAfter.points_balance, 25, 'points_balance should increment from 10 to 25');
    assert.equal(userAfter.total_spent, 150000, 'total_spent should increment from 0 to 150,000');
  });

  it('F5-04: Automatically upgrades tier when total_spent reaches next threshold (Bronze -> Silver)', async () => {
    // Current spent: 900,000 VND (BRONZE). Order of 150,000 VND brings total to 1,050,000 VND (crosses 1,000,000 VND threshold)
    dbHelper.setUserLoyalty(testUserId, { member_tier: 'BRONZE', total_spent: 900000, points_balance: 50 });

    testOrderCode = fixtures.generateTestOrderCode('E2E_UPG_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F05 Recip', '0905', '123 St', 150000, 0, 150000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    const res = processOrderEarn(testOrderId);
    assert.ok(res.success);
    assert.equal(res.previous_tier, fixtures.TIERS.BRONZE);
    assert.equal(res.new_tier, fixtures.TIERS.SILVER, 'Tier must automatically upgrade to SILVER');
    assert.equal(res.total_spent, 1050000);

    const userAfter = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userAfter.member_tier, fixtures.TIERS.SILVER, 'DB users.member_tier must be updated to SILVER');
  });

  it('F5-05: Appends atomic EARN transaction with positive points and balance_after in point_transactions', async () => {
    testOrderCode = fixtures.generateTestOrderCode('E2E_TXE_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F05 Recip', '0905', '123 St', 300000, 0, 300000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    const res = processOrderEarn(testOrderId);
    assert.ok(res.success);

    const txs = dbHelper.getTransactions(testUserId);
    assert.equal(txs.length, 1, 'Exactly one transaction should be logged');

    const tx = txs[0];
    assert.equal(tx.transaction_type, fixtures.TRANSACTION_TYPES.EARN);
    assert.equal(Number(tx.points), 30, 'Transaction points must be positive (+30)');
    assert.equal(Number(tx.balance_after), 40, 'balance_after must be 40 (10 + 30)');
    assert.equal(Number(tx.order_id), testOrderId);
    assert.ok(tx.description && tx.description.includes(testOrderCode));
  });

});
