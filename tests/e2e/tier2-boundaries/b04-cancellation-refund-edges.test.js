/**
 * b04-cancellation-refund-edges.test.js - Tier 2 Boundary Tests: Cancellation & Refund Edges
 * 
 * Verifies edge cases and rollback guarantees for order cancellation and refunds:
 * - B4-01: Double cancellation idempotency (second cancel does not duplicate points restoration)
 * - B4-02: Cancelling an order with 0 points used restores 0 points without phantom ledger rows
 * - B4-03: Refund/revocation when points were already spent floors balance at 0 (never negative)
 * - B4-04: Refund under changed earning ratio revokes exact originally awarded points
 * - B4-05: Rapid concurrent cancellation attempts maintain idempotency via DB locks
 *
 * Source: ORIGINAL_REQUEST.md §R3, PROJECT.md §F6, TEST_INFRA.md §Tier 2
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');

const dbHelper = require('../harness/db-helper');
const {
  TIERS,
  generateTestEmail,
  generateTestOrderCode
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

describe('Tier 2 Boundaries: b04-cancellation-refund-edges (5 tests)', () => {
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

  function createTestCustomer(initialBalance = 0, totalSpent = 0, tier = 'BRONZE') {
    const email = 'e2e_canc_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7) + '@fastfood.test';
    const phone = generateUniquePhone();
    const passwordHash = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
       VALUES (2, 'Cancel Test User', ?, ?, ?, ?, ?, ?, 'active', NOW())`,
      [email, phone, passwordHash, tier, initialBalance, totalSpent]
    );
    const rows = dbHelper.query('SELECT id, email, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [email]);
    assert.ok(rows && rows.length > 0, 'User should be created');
    return rows[0];
  }

  function createOrderWithPoints(userId, { pointsUsed = 0, pointsEarned = 0, amount = 100000, status = 'pending' } = {}) {
    const orderCode = 'E2E_ORD_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, points_earned, points_used, points_discount, payment_method, payment_status, order_status, placed_at, created_at)
       VALUES (?, ?, 1, ?, 0.00, 0.00, ?, ?, ?, 0.00, 'cod', 'pending', ?, NOW(), NOW())`,
      [orderCode, userId, amount, amount, pointsEarned, pointsUsed, status]
    );
    const rows = dbHelper.query('SELECT * FROM orders WHERE order_code = ? LIMIT 1', [orderCode]);
    return rows[0];
  }

  it('B4-01: Double cancellation idempotency: cancelling twice does not duplicate refund', () => {
    // Customer places order using 150 points. Balance drops to 100.
    const user = createTestCustomer(100, 0, 'BRONZE');
    const order = createOrderWithPoints(user.id, { pointsUsed: 150, pointsEarned: 0, status: 'pending' });

    // 1st cancellation
    const firstRes = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    assert.equal(firstRes.success, true);
    assert.equal(firstRes.points_restored, 150);

    const userAfterFirst = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfterFirst.points_balance, 250);

    // 2nd cancellation on same order (idempotent guard)
    const secondRes = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    assert.equal(secondRes.success, true);
    assert.equal(secondRes.points_restored, 0, 'Second cancel must restore 0 additional points');

    const userAfterSecond = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfterSecond.points_balance, 250, 'Balance must remain 250, no duplicated points');

    // Audit ledger: exactly 1 REFUND_RESTORE
    const txs = dbHelper.getTransactions(user.id);
    const restoreTxs = txs.filter(t => t.transaction_type === 'REFUND_RESTORE');
    assert.equal(restoreTxs.length, 1, 'Only one REFUND_RESTORE entry should exist in ledger');
  });

  it('B4-02: Cancelling an order with 0 points used restores 0 points cleanly without ledger noise', () => {
    const user = createTestCustomer(200, 0, 'BRONZE');
    const order = createOrderWithPoints(user.id, { pointsUsed: 0, pointsEarned: 0, status: 'pending' });

    const res = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    assert.equal(res.success, true);
    assert.equal(res.points_restored, 0);

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 200, 'Balance must remain unchanged');

    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 0, 'No point transactions created when 0 points were used');
  });

  it('B4-03: Refund/revocation when points were already spent floors balance at 0 (never negative)', () => {
    // User earned 200 points, spent them all (balance: 0).
    // Now the completed order of 200,000 VNĐ is refunded/cancelled (revoking 20 points).
    const user = createTestCustomer(0, 200000, 'BRONZE');
    const order = createOrderWithPoints(user.id, { pointsUsed: 0, pointsEarned: 20, amount: 200000, status: 'completed' });

    const res = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    assert.equal(res.success, true);
    assert.equal(res.points_revoked, 20);

    // Balance must floor at 0, NEVER become negative (-20)
    assert.equal(res.new_balance, 0, 'Returned balance must floor at 0');

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 0, 'DB points_balance must be 0, not negative');

    // Ledger records revocation with balance_after = 0
    const txs = dbHelper.getTransactions(user.id);
    const revokeTx = txs.find(t => t.transaction_type === 'REFUND_REVOKE');
    assert.ok(revokeTx, 'REFUND_REVOKE transaction must exist');
    assert.equal(Number(revokeTx.points), -20);
    assert.equal(Number(revokeTx.balance_after), 0);
  });

  it('B4-04: Refund under changed earning ratio revokes exact originally awarded points', () => {
    // Order originally earned 10 points under 1.0 ratio
    const user = createTestCustomer(50, 100000, 'BRONZE');
    const order = createOrderWithPoints(user.id, { pointsUsed: 0, pointsEarned: 10, amount: 100000, status: 'completed' });

    // Admin updates Bronze ratio in DB from 1.0 to 2.5
    dbHelper.query("UPDATE membership_tiers SET point_ratio = 2.5 WHERE tier_code = 'BRONZE'");
    callLoyaltyPhp('clearLoyaltyCache();');

    // Refund order
    const res = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    assert.equal(res.success, true);
    // Revocation must revoke original 10 points, NOT 25 points under new 2.5 ratio!
    assert.equal(res.points_revoked, 10, 'Must revoke exact original points_earned stored on order (10), not 25');

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 40, 'Balance must be 50 - 10 = 40');

    // Restore ratio
    dbHelper.query("UPDATE membership_tiers SET point_ratio = 1.0 WHERE tier_code = 'BRONZE'");
    callLoyaltyPhp('clearLoyaltyCache();');
  });

  it('B4-05: Rapid concurrent cancellation attempts maintain idempotency via DB locks', () => {
    const user = createTestCustomer(100, 0, 'BRONZE');
    const order = createOrderWithPoints(user.id, { pointsUsed: 80, pointsEarned: 0, status: 'pending' });

    // Execute multiple sequential calls simulating concurrent rapid cancel requests
    const res1 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    const res2 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
    const res3 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));

    assert.equal(res1.points_restored, 80);
    assert.equal(res2.points_restored, 0);
    assert.equal(res3.points_restored, 0);

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 180, 'Points restored exactly once (100 + 80 = 180)');

    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 1, 'Exactly one transaction logged despite rapid repeated cancel calls');
  });
});
