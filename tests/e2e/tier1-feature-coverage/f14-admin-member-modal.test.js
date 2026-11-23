/**
 * f14-admin-member-modal.test.js - Tier 1: F14 Admin Member Points History Modal
 *
 * Tests:
 * 1. Admin can invoke get_member_loyalty_history endpoint with user_id
 * 2. Loads full transaction list for target member
 * 3. Audit items include transaction_type, points delta, balance_after, and timestamp
 * 4. Order-linked transactions include order_id or order_code references
 * 5. Handles users with zero transactions gracefully (empty array, no SQL error)
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F14 - Admin Member Points History Modal', () => {
  let client;
  let testUserId;
  let testZeroUserId;
  let testOrderCode;
  let testOrderId;
  const testEmail = fixtures.generateTestEmail('e2e_f14');
  const testZeroEmail = fixtures.generateTestEmail('e2e_f14_zero');

  before(async () => {
    client = new TestClient();

    // 1. User with transactions
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'Audit Test User', ?, '0914000001', 'hash', 'active', 'SILVER', 180, 1200000, NOW())
    `, [testEmail]);
    const u1 = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    testUserId = u1.id;

    testOrderCode = fixtures.generateTestOrderCode('E2E_AUD_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'Audit Recip', '0914', '123 St', 200000, 0, 200000, 'cod', 'paid', 'completed', 20, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const o = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    testOrderId = o.id;

    // Seed transaction records
    dbHelper.query(`
      INSERT INTO point_transactions (user_id, order_id, points, balance_after, transaction_type, description, created_at)
      VALUES 
        (?, ?, 20, 120, 'EARN', 'Tích điểm đơn ${testOrderCode}', NOW()),
        (?, NULL, 60, 180, 'ADMIN_ADJUST', 'Admin tặng điểm thưởng', NOW())
    `, [testUserId, testOrderId, testUserId]);

    // 2. User with zero transactions
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'Zero Tx User', ?, '0914000002', 'hash', 'active', 'BRONZE', 0, 0, NOW())
    `, [testZeroEmail]);
    const u2 = dbHelper.query('SELECT id FROM users WHERE email = ?', [testZeroEmail])[0];
    testZeroUserId = u2.id;

    await client.loginAsAdmin();
  });

  after(async () => {
    if (testUserId) {
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM orders WHERE customer_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
    }
    if (testZeroUserId) {
      dbHelper.query('DELETE FROM users WHERE id = ?', [testZeroUserId]);
    }
  });

  it('F14-01: Admin can invoke get_member_loyalty_history with user_id', async () => {
    const res = await client.adminGetMemberHistory(testUserId);
    assert.equal(res.status, 200, 'Endpoint should return HTTP 200');
    assert.ok(res.body, 'Response body must exist');
  });

  it('F14-02: Returns full transaction list for target member', async () => {
    const res = await client.adminGetMemberHistory(testUserId);
    const history = res.data?.history || res.body?.data || res.body?.history || [];
    assert.ok(Array.isArray(history), 'History must be an array');
    assert.equal(history.length, 2, 'Should return exactly 2 seeded transactions');
  });

  it('F14-03: Audit items include transaction_type, points delta, balance_after, and created_at', async () => {
    const res = await client.adminGetMemberHistory(testUserId);
    const history = res.data?.history || res.body?.data || res.body?.history || [];
    const item = history[0];

    assert.ok(item.transaction_type !== undefined, 'Item must have transaction_type');
    assert.ok(item.points !== undefined, 'Item must have points');
    assert.ok(item.balance_after !== undefined, 'Item must have balance_after');
    assert.ok(item.created_at !== undefined, 'Item must have created_at timestamp');
  });

  it('F14-04: Order-linked transactions include order_id or order_code references', async () => {
    const res = await client.adminGetMemberHistory(testUserId);
    const history = res.data?.history || res.body?.data || res.body?.history || [];
    const orderTx = history.find(t => t.transaction_type === 'EARN');

    assert.ok(orderTx, 'Must find EARN transaction');
    assert.ok(orderTx.order_id || orderTx.order_code || (orderTx.description && orderTx.description.includes(testOrderCode)),
      'Transaction must reference order_id, order_code, or description with order code');
  });

  it('F14-05: Requesting history for user with zero transactions returns empty array cleanly', async () => {
    const res = await client.adminGetMemberHistory(testZeroUserId);
    assert.equal(res.status, 200);

    const history = res.data?.history || res.body?.data || res.body?.history || [];
    assert.ok(Array.isArray(history), 'History for new user must be an array');
    assert.equal(history.length, 0, 'New user history must be empty array (length 0)');
  });

});
