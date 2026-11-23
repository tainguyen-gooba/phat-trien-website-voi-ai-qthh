/**
 * f06-cancellation-refund.test.js - Tier 1: F6 Order Cancellation & Refund Points Rollback
 *
 * Tests:
 * 1. Order cancellation restores redeemed points back to user's points_balance
 * 2. Appends REFUND_RESTORE transaction in point_transactions with positive points
 * 3. Refunding a completed order revokes earned points from user's balance
 * 4. Deducts total_spent and recalculates tier upon completed order cancellation
 * 5. Cancellation idempotency: multiple cancellation requests do not duplicate point actions
 */

const { describe, it, before, after, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

function cancelOrderViaPhp(orderId) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    $res = handleOrderCancellation(${orderId});
    echo json_encode($res);
  `;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return JSON.parse(output.trim());
}

describe('Tier 1: F6 - Order Cancellation & Refund Points Rollback', () => {
  let testUserId;
  let testEmail;
  let testOrderCode;
  let testOrderId;

  beforeEach(async () => {
    testEmail = fixtures.generateTestEmail('e2e_f06');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'E2E F06 Customer', ?, '0906000001', 'hash', 'active', 'BRONZE', 100, 0, NOW())
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

  it('F6-01: Order cancellation restores redeemed points back to user points_balance', async () => {
    // User redeemed 100 points on order (order_status: 'placed')
    testOrderCode = fixtures.generateTestOrderCode('E2E_RST_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F06 Recip', '0906', '123 St', 150000, 0, 140000, 'cod', 'pending', 'placed', 0, 100, 10000, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    // Simulate redemption deduction prior to cancellation: balance was 100, now 0
    dbHelper.setUserLoyalty(testUserId, { points_balance: 0 });

    const res = cancelOrderViaPhp(testOrderId);
    assert.ok(res.success);
    assert.equal(res.points_restored, 100, 'Must restore 100 points');
    assert.equal(res.new_balance, 100, 'New balance must be 100');

    const user = dbHelper.getUserLoyalty(testUserId);
    assert.equal(user.points_balance, 100, 'DB users.points_balance must be restored to 100');
  });

  it('F6-02: Appends REFUND_RESTORE transaction in point_transactions with positive points', async () => {
    testOrderCode = fixtures.generateTestOrderCode('E2E_RFT_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F06 Recip', '0906', '123 St', 150000, 0, 130000, 'cod', 'pending', 'placed', 0, 200, 20000, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    cancelOrderViaPhp(testOrderId);

    const txs = dbHelper.getTransactions(testUserId);
    assert.equal(txs.length, 1);
    assert.equal(txs[0].transaction_type, fixtures.TRANSACTION_TYPES.REFUND_RESTORE);
    assert.equal(Number(txs[0].points), 200, 'Transaction points must be positive (+200)');
    assert.equal(Number(txs[0].order_id), testOrderId);
    assert.ok(txs[0].description && txs[0].description.includes(testOrderCode));
  });

  it('F6-03: Refunding a completed order revokes earned points from users balance', async () => {
    // Completed order that had earned 50 points, bringing balance to 150
    dbHelper.setUserLoyalty(testUserId, { points_balance: 150, total_spent: 500000 });

    testOrderCode = fixtures.generateTestOrderCode('E2E_RVK_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F06 Recip', '0906', '123 St', 500000, 0, 500000, 'cod', 'paid', 'completed', 50, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    const res = cancelOrderViaPhp(testOrderId);
    assert.ok(res.success);
    assert.equal(res.points_revoked, 50, 'Must revoke 50 earned points');
    assert.equal(res.new_balance, 100, 'Balance must drop from 150 to 100');

    const user = dbHelper.getUserLoyalty(testUserId);
    assert.equal(user.points_balance, 100);

    const txs = dbHelper.getTransactions(testUserId);
    const revokeTx = txs.find(t => t.transaction_type === fixtures.TRANSACTION_TYPES.REFUND_REVOKE);
    assert.ok(revokeTx, 'Must record REFUND_REVOKE in transactions');
    assert.equal(Number(revokeTx.points), -50, 'Revoke transaction must be negative (-50)');
  });

  it('F6-04: Deducts total_spent and downgrades tier upon completed order refund', async () => {
    // User is SILVER with 1,100,000 VND total_spent. Cancelling a 200,000 VND order drops spent to 900,000 VND (BRONZE)
    dbHelper.setUserLoyalty(testUserId, { member_tier: 'SILVER', total_spent: 1100000, points_balance: 120 });

    testOrderCode = fixtures.generateTestOrderCode('E2E_DWN_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F06 Recip', '0906', '123 St', 200000, 0, 200000, 'cod', 'paid', 'completed', 30, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    const res = cancelOrderViaPhp(testOrderId);
    assert.ok(res.success);
    assert.equal(res.new_spent, 900000, 'New spent must be 900,000 VND');
    assert.equal(res.new_tier, fixtures.TIERS.BRONZE, 'Tier must downgrade to BRONZE');

    const user = dbHelper.getUserLoyalty(testUserId);
    assert.equal(user.total_spent, 900000);
    assert.equal(user.member_tier, fixtures.TIERS.BRONZE);
  });

  it('F6-05: Cancellation idempotency: multiple cancellation requests do not duplicate point actions', async () => {
    // Order used 100 points
    dbHelper.setUserLoyalty(testUserId, { points_balance: 50 });

    testOrderCode = fixtures.generateTestOrderCode('E2E_IDP_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'F06 Recip', '0906', '123 St', 100000, 0, 90000, 'cod', 'pending', 'placed', 0, 100, 10000, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    // Cancellation 1
    const res1 = cancelOrderViaPhp(testOrderId);
    assert.equal(res1.points_restored, 100);
    assert.equal(res1.new_balance, 150);

    // Cancellation 2 (immediate duplicate)
    const res2 = cancelOrderViaPhp(testOrderId);
    assert.equal(res2.points_restored, 0, 'Second cancel must restore 0 additional points');
    assert.equal(res2.new_balance, 150, 'Balance must remain 150');

    // Verify DB ledger has only 1 REFUND_RESTORE record
    const txs = dbHelper.getTransactions(testUserId);
    assert.equal(txs.length, 1, 'Only one transaction record should exist');
  });

});
