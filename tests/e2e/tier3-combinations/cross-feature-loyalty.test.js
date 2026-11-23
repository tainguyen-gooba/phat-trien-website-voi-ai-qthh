/**
 * cross-feature-loyalty.test.js - Tier 3: Cross-Feature Pairwise Interactions
 * Tests: T3-01 through T3-10
 *
 * Verifies pairwise combinatorial interactions across loyalty features:
 * - T3-01: F4 + F5 (Points earned calculated on net cash paid after points discount)
 * - T3-02: F5 + F2 (Single 3.5M order jumps customer from Bronze directly to Gold immediately)
 * - T3-03: F4 + F10 (Voucher applied at checkout combined with direct points discount)
 * - T3-04: F5 + F6 + F11 (EARN + REVOKE + RESTORE reconstructed in ledger timeline)
 * - T3-05: F15 + F5 (Admin alters Gold earning ratio to 2.5 -> immediately awards 2.5x points)
 * - T3-06: F15 + F4 (Admin alters redemption rate to 100 pts = 15k VND -> immediate checkout effect)
 * - T3-07: F10 + F11 (Exchanging voucher writes REDEEM_VOUCHER and syncs profile balance)
 * - T3-08: F5 + F7/F8 (Completed order updates VIP card stats and adjusts progress bar)
 * - T3-09: F5 + F13/F14 (Completed order appears in Admin Members ledger and History modal)
 * - T3-10: F6 + F2 (Refund reduces total_spent and adjusts tier consistently)
 *
 * Source: PROJECT.md §Feature Inventory & Milestones, TEST_INFRA.md §Tier 3
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');

const dbHelper = require('../harness/db-helper');
const {
  TIERS,
  generateTestEmail,
  generateTestOrderCode,
  calculateExpectedPoints
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

describe('Tier 3 Combinations: Cross-Feature Loyalty Interactions (10 tests)', () => {
  before(() => {
    dbHelper.cleanupTestData();
  });

  after(() => {
    // Reset configuration back to standard defaults
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'point_to_vnd_rate'");
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '10000' WHERE setting_key = 'spending_step_vnd'");
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'min_redeem_points'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 0.00, point_ratio = 1.00 WHERE tier_code = 'BRONZE'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 1000000.00, point_ratio = 1.50 WHERE tier_code = 'SILVER'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 3000000.00, point_ratio = 2.00 WHERE tier_code = 'GOLD'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 5000000.00, point_ratio = 3.00 WHERE tier_code = 'DIAMOND'");
    callLoyaltyPhp('clearLoyaltyCache();');
    dbHelper.cleanupTestData();
  });

  function generateUniquePhone() {
    while (true) {
      const phone = '03' + Math.floor(10000000 + Math.random() * 90000000);
      const existing = dbHelper.query('SELECT id FROM users WHERE phone = ? LIMIT 1', [phone]);
      if (!existing || existing.length === 0) return phone;
    }
  }

  function createTestCustomer({ tier = 'BRONZE', points = 0, spent = 0 } = {}) {
    const email = 'e2e_t3_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7) + '@fastfood.test';
    const phone = generateUniquePhone();
    const passwordHash = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
       VALUES (2, 'Tier 3 Cross-Feature User', ?, ?, ?, ?, ?, ?, 'active', NOW())`,
      [email, phone, passwordHash, tier, points, spent]
    );
    const rows = dbHelper.query('SELECT id, email, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [email]);
    assert.ok(rows && rows.length > 0, 'Customer should be created in DB');
    return rows[0];
  }

  function createOrder(userId, {
    subtotal = 100000,
    total = null,
    discount = 0,
    pointsUsed = 0,
    pointsDiscount = 0,
    pointsEarned = 0,
    status = 'completed'
  } = {}) {
    const orderCode = 'E2E_T3_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
    const finalTotal = total !== null ? total : (subtotal - discount - pointsDiscount);
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, points_earned, points_used, points_discount, payment_method, payment_status, order_status, placed_at, created_at)
       VALUES (?, ?, 1, ?, 0.00, ?, ?, ?, ?, ?, 'cod', 'completed', ?, NOW(), NOW())`,
      [orderCode, userId, subtotal, discount, finalTotal, pointsEarned, pointsUsed, pointsDiscount, status]
    );
    const rows = dbHelper.query('SELECT * FROM orders WHERE order_code = ? LIMIT 1', [orderCode]);
    return rows[0];
  }

  it('T3-01: F4 + F5: Points earned calculated on net cash paid after points discount', () => {
    // Subtotal: 200,000 VNĐ. Customer used 500 points (50,000 VNĐ discount).
    // Net cash paid: 150,000 VNĐ.
    const user = createTestCustomer({ tier: 'BRONZE', points: 500, spent: 0 });
    const order = createOrder(user.id, {
      subtotal: 200000,
      total: 150000,
      pointsUsed: 500,
      pointsDiscount: 50000,
      pointsEarned: 0,
      status: 'completed'
    });

    // Complete order and award points
    const phpRes = callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    // Formula at Bronze (ratio 1.0): floor(150,000 / 10,000) * 1.0 = 15 points (NOT 20 points!)
    assert.equal(result.points_earned, 15, 'Points must be calculated on net total_amount (150k -> 15 pts), not subtotal (200k)');

    // Invariant: total_spent increases by net cash 150,000 VNĐ
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.total_spent, 150000);
  });

  it('T3-02: F5 + F2: Single 3.5M order jumps customer from Bronze directly to Gold immediately', () => {
    // Bronze member (0 VNĐ spending)
    const user = createTestCustomer({ tier: 'BRONZE', points: 0, spent: 0 });

    // Customer places and completes a single 3,500,000 VNĐ order
    const order = createOrder(user.id, {
      subtotal: 3500000,
      total: 3500000,
      pointsUsed: 0,
      pointsEarned: 0,
      status: 'completed'
    });

    const phpRes = callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    assert.equal(result.new_tier, 'GOLD', 'Customer must jump directly from Bronze to GOLD');
    assert.equal(result.previous_tier, 'BRONZE');
    assert.equal(result.total_spent, 3500000);

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.member_tier, 'GOLD', 'Database member_tier must be updated to GOLD');
  });

  it('T3-03: F4 + F10: Voucher applied at checkout combined with direct points discount', () => {
    // Customer has 400 points
    const user = createTestCustomer({ tier: 'BRONZE', points: 400, spent: 0 });

    // Step 1: Exchange 100 points for a 10k voucher
    const vchRes = JSON.parse(callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, 1));`));
    assert.equal(vchRes.success, true);
    assert.equal(vchRes.new_balance, 300);

    // Step 2: At checkout, order subtotal is 100,000 VNĐ.
    // Apply 10k voucher (discount = 10,000) AND redeem 200 points (discount = 20,000)
    const order = createOrder(user.id, {
      subtotal: 100000,
      discount: 10000,       // voucher discount
      pointsUsed: 200,       // direct points used
      pointsDiscount: 20000, // points discount
      total: 70000,          // 100k - 10k - 20k = 70k
      status: 'pending'
    });

    // Redeem points for the order
    const redRes = JSON.parse(callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 200, '${order.order_code}'));`));
    assert.equal(redRes.success, true);
    assert.equal(redRes.new_balance, 100);

    // Verify user balance in DB: 400 - 100 (voucher) - 200 (checkout) = 100 points
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 100);

    // Verify order in DB
    const orderAfter = dbHelper.getOrderLoyalty(order.order_code);
    assert.equal(orderAfter.points_used, 200);
    assert.equal(orderAfter.points_discount, 20000);
  });

  it('T3-04: F5 + F6 + F11: Completed order EARN + refunded REVOKE + cancelled RESTORE accurately reconstructed in ledger timeline', () => {
    const user = createTestCustomer({ tier: 'BRONZE', points: 100, spent: 0 });

    // 1. Order 1 completes: EARN +20 points
    const order1 = createOrder(user.id, { subtotal: 200000, total: 200000, pointsUsed: 0, status: 'completed' });
    const earnRes = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order1.id}));`));
    assert.equal(earnRes.success, true);
    assert.equal(earnRes.points_earned, 20);

    // 2. Order 2 placed with REDEEM_CHECKOUT: -100 points (meets min_redeem_points = 100)
    const order2 = createOrder(user.id, { subtotal: 120000, total: 110000, pointsUsed: 100, pointsDiscount: 10000, status: 'pending' });
    const redRes = JSON.parse(callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order2.id}, 100, '${order2.order_code}'));`));
    assert.equal(redRes.success, true);

    // 3. Order 2 cancelled: REFUND_RESTORE: +100 points
    const cancelRes = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order2.id}));`));
    assert.equal(cancelRes.success, true);
    assert.equal(cancelRes.points_restored, 100);

    // 4. Order 1 refunded: REFUND_REVOKE: -20 points
    const refundRes = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order1.id}));`));
    assert.equal(refundRes.success, true);
    assert.equal(refundRes.points_revoked, 20);

    // Verify complete ledger timeline: EARN -> REDEEM_CHECKOUT -> REFUND_RESTORE -> REFUND_REVOKE
    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 4, 'Ledger must contain exactly 4 transactions');

    assert.equal(txs[0].transaction_type, 'EARN');
    assert.equal(Number(txs[0].points), 20);
    assert.equal(Number(txs[0].balance_after), 120);

    assert.equal(txs[1].transaction_type, 'REDEEM_CHECKOUT');
    assert.equal(Number(txs[1].points), -100);
    assert.equal(Number(txs[1].balance_after), 20);

    assert.equal(txs[2].transaction_type, 'REFUND_RESTORE');
    assert.equal(Number(txs[2].points), 100);
    assert.equal(Number(txs[2].balance_after), 120);

    assert.equal(txs[3].transaction_type, 'REFUND_REVOKE');
    assert.equal(Number(txs[3].points), -20);
    assert.equal(Number(txs[3].balance_after), 100);

    // User current balance matches initial 100
    const finalUser = dbHelper.getUserLoyalty(user.id);
    assert.equal(finalUser.points_balance, 100);
  });

  it('T3-05: F15 + F5: Admin alters Gold earning ratio to 2.5 -> immediately awards 2.5x points on next completed order', () => {
    // 1. Admin alters Gold earning ratio to 2.5
    dbHelper.query("UPDATE membership_tiers SET point_ratio = 2.5 WHERE tier_code = 'GOLD'");
    callLoyaltyPhp('clearLoyaltyCache();');

    // 2. Gold member completes an order of 100,000 VNĐ
    const user = createTestCustomer({ tier: 'GOLD', points: 0, spent: 3000000 });
    const order = createOrder(user.id, { subtotal: 100000, total: 100000, pointsUsed: 0, status: 'completed' });

    const phpRes = callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    // 100,000 / 10,000 = 10 steps. 10 * 2.5 = 25 points (instead of standard 20 points)
    assert.equal(result.points_earned, 25, 'Immediate award of 2.5x points on newly configured ratio');

    // Revert ratio
    dbHelper.query("UPDATE membership_tiers SET point_ratio = 2.0 WHERE tier_code = 'GOLD'");
    callLoyaltyPhp('clearLoyaltyCache();');
  });

  it('T3-06: F15 + F4: Admin alters redemption rate to 100 pts = 15,000 VND -> next checkout calculates 15k/100pts discount', () => {
    // 1. Admin alters point_to_vnd_rate to 150 (1 point = 150 VNĐ, so 100 points = 15,000 VNĐ)
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '150' WHERE setting_key = 'point_to_vnd_rate'");
    callLoyaltyPhp('clearLoyaltyCache();');

    // 2. Customer redeems 200 points at checkout
    const user = createTestCustomer({ tier: 'BRONZE', points: 500, spent: 0 });
    const order = createOrder(user.id, { subtotal: 100000, total: 70000, pointsUsed: 200, pointsDiscount: 30000, status: 'pending' });

    const phpRes = callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 200, '${order.order_code}'));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    // 200 points * 150 VNĐ/pt = 30,000 VNĐ discount (instead of 20,000 VNĐ)
    assert.equal(result.discount_amount, 30000, 'Redemption rate change must apply immediately to checkout discount');
    assert.equal(result.points_used, 200);

    // Revert setting
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'point_to_vnd_rate'");
    callLoyaltyPhp('clearLoyaltyCache();');
  });

  it('T3-07: F10 + F11: Exchanging voucher writes REDEEM_VOUCHER and updates profile balance without discrepancy', () => {
    const user = createTestCustomer({ tier: 'BRONZE', points: 500, spent: 0 });

    // Redeem 200 points for voucher package 2
    const phpRes = callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, 2));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    assert.equal(result.points_deducted, 200);
    assert.equal(result.new_balance, 300);

    // Audit ledger
    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 1);
    assert.equal(txs[0].transaction_type, 'REDEEM_VOUCHER');
    assert.equal(Number(txs[0].points), -200);
    assert.equal(Number(txs[0].balance_after), 300);

    // User profile balance in DB must equal ledger balance_after with zero discrepancy
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, Number(txs[0].balance_after));
  });

  it('T3-08: F5 + F7/F8: Completed order updates VIP card stats and adjusts progress bar on profile refresh', () => {
    // User starts at 400k spending (Bronze)
    const user = createTestCustomer({ tier: 'BRONZE', points: 40, spent: 400000 });

    // Completes an 800k order -> total spent becomes 1,200,000 VNĐ (SILVER)
    const order = createOrder(user.id, { subtotal: 800000, total: 800000, pointsUsed: 0, status: 'completed' });
    const phpRes = callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    assert.equal(result.new_tier, 'SILVER', 'Must promote to SILVER');
    assert.equal(result.total_spent, 1200000);

    // Check user data loaded for profile
    const userProfile = dbHelper.getUserLoyalty(user.id);
    assert.equal(userProfile.member_tier, 'SILVER');
    assert.equal(userProfile.total_spent, 1200000);

    // Verify progress to next tier (GOLD at 3M):
    // Next gap = 3,000,000 - 1,200,000 = 1,800,000 VNĐ
    const nextGap = 3000000 - userProfile.total_spent;
    assert.equal(nextGap, 1800000, 'Remaining gap to Gold must be 1,800,000 VNĐ');
  });

  it('T3-09: F5 + F13/F14: Completed order appears in Admin Members ledger and Member Points History modal', () => {
    const user = createTestCustomer({ tier: 'BRONZE', points: 0, spent: 0 });
    const order = createOrder(user.id, { subtotal: 100000, total: 100000, pointsUsed: 0, status: 'completed' });

    callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`);

    // Verify admin member history retrieval
    const historyJson = callLoyaltyPhp(`echo json_encode(getUserPointHistory(${user.id}));`);
    const history = JSON.parse(historyJson);

    assert.ok(Array.isArray(history), 'History must be an array');
    assert.equal(history.length, 1);
    assert.equal(history[0].transaction_type, 'EARN');
    assert.equal(Number(history[0].points), 10);
    assert.equal(history[0].order_code, order.order_code);

    // Verify admin members list reflects new points and total spent
    const memberRows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE id = ?', [user.id]);
    assert.equal(memberRows.length, 1);
    assert.equal(Number(memberRows[0].points_balance), 10);
    assert.equal(Number(memberRows[0].total_spent), 100000);
  });

  it('T3-10: F6 + F2: Refund reduces total_spent and adjusts tier consistently per specification', () => {
    // User had total spent of 1,200,000 VNĐ (Silver) from two orders: 800k and 400k
    const user = createTestCustomer({ tier: 'SILVER', points: 150, spent: 1200000 });

    const orderToRefund = createOrder(user.id, {
      subtotal: 400000,
      total: 400000,
      pointsEarned: 60, // earned 60 pts
      pointsUsed: 0,
      status: 'completed'
    });

    // Refund 400k order
    const cancelRes = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${orderToRefund.id}));`));
    assert.equal(cancelRes.success, true);
    assert.equal(cancelRes.points_revoked, 60);

    // Total spent reduced from 1,200,000 to 800,000 VNĐ
    assert.equal(cancelRes.new_spent, 800000);

    // Because 800,000 VNĐ < 1,000,000 VNĐ (Silver threshold), tier is consistently downgraded to BRONZE
    assert.equal(cancelRes.new_tier, 'BRONZE', 'Tier must downgrade to BRONZE when total_spent falls below 1,000,000 VNĐ');

    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.member_tier, 'BRONZE');
    assert.equal(userAfter.total_spent, 800000);
    assert.equal(userAfter.points_balance, 90); // 150 - 60 = 90
  });
});
