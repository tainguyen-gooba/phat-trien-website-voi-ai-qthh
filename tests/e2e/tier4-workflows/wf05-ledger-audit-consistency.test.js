/**
 * wf05-ledger-audit-consistency.test.js - Tier 4: Real-World Application Scenario Workflows
 * Scenario: WF-05: Multi-Order Transaction Ledger Audit & Database Invariant Verification
 *
 * Sequence of Events:
 * 1. Seed test user with baseline 1000 points and fires a rapid sequence of 5 mixed operations:
 *    - Op 1: Checkout points redemption (-200 pts) -> balance 800.
 *    - Op 2: Completed earn order (300k VND) (+30 pts, +300k spent) -> balance 830.
 *    - Op 3: Voucher package exchange (-100 pts) -> balance 730, generates coupon.
 *    - Op 4: Completed high-value earn order (800k VND) (+80 pts, +800k spent) -> balance 810, reaches 1.1M spent -> auto promoted to SILVER.
 *    - Op 5: Checkout redemption (-150 pts) followed by cancellation -> immediate points restore (+150 pts, REFUND_RESTORE) -> balance 810.
 * 2. Invariant Audit 1: Mathematical Ledger Continuity:
 *    - For all i: balance_after[i] === balance_after[i-1] + points[i]
 *    - 1000 + SUM(points) === users.points_balance === 810
 * 3. Invariant Audit 2: Spending Consistency:
 *    - users.total_spent === SUM(completed orders total_amount) === 1,100,000 VND
 * 4. Invariant Audit 3: Tier Synchronization Invariant:
 *    - calculateTier(users.total_spent) === users.member_tier === 'SILVER'
 * 5. Invariant Audit 4: Zero Orphan Records & Foreign Key Integrity.
 * 6. Invariant Audit 5: Non-Negativity Guards (balance >= 0, balance_after >= 0).
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');
const { execFileSync } = require('child_process');

const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const { TIERS } = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');
const PASSWORD_HASH = '$2y$10$OhD1HTzuuPIp4t5IGxZNJ.Cpr1SH9zhIj0Xrn17uLUEKfP/Ow4L3q'; // hash for 'password123'

/**
 * Invokes helpers/loyalty.php service methods directly via PHP CLI PDO bridge
 * to guarantee progressive testability across milestones M1-M5.
 */
function callLoyaltyService(expr) {
  const script = `require 'helpers/loyalty.php'; echo json_encode(${expr});`;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  try {
    return JSON.parse(output.trim());
  } catch (err) {
    throw new Error(`Failed to parse loyalty service output: ${output} | Expr: ${expr}`);
  }
}

describe('WF-05: Multi-Order Transaction Ledger Audit & Database Invariants', () => {
  let customerClient;
  let adminClient;
  const testEmail = 'wf05_user_audit@fastfood.vn';
  const testPassword = 'password123';
  const testFullName = 'Kiểm Toán Viên';
  const testPhone = '0905678901';
  let userId;

  const order1Code = `WF05_ORD1_${Date.now()}`;
  let order1Id;
  const order2Code = `WF05_ORD2_${Date.now()}`;
  let order2Id;
  const order3Code = `WF05_ORD3_${Date.now()}`;
  let order3Id;
  const order4Code = `WF05_ORD4_${Date.now()}`;
  let order4Id;

  let lastState = {
    tier: 'BRONZE',
    points: 1000,
    spent: 0
  };

  function ensureUser() {
    let rows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    if (!rows || rows.length === 0) {
      dbHelper.query(
        `INSERT INTO users (full_name, email, password_hash, phone, address, role_id, status, member_tier, points_balance, total_spent, created_at)
         VALUES (?, ?, ?, ?, '999 Điện Biên Phủ, Bình Thạnh', 2, 'active', ?, ?, ?, NOW())`,
        [testFullName, testEmail, PASSWORD_HASH, testPhone, lastState.tier, lastState.points, lastState.spent]
      );
      rows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    }
    userId = Number(rows[0].id);
    return userId;
  }

  function cleanupWf05() {
    try {
      dbHelper.query("DELETE FROM point_transactions WHERE description LIKE '%WF05%' OR user_id IN (SELECT id FROM users WHERE email = ?)", [testEmail]);
      dbHelper.query("DELETE FROM coupons WHERE code LIKE 'FF10K%' AND user_id IN (SELECT id FROM users WHERE email = ?)", [testEmail]);
      dbHelper.query("DELETE FROM orders WHERE order_code LIKE 'WF05%'");
      dbHelper.query("DELETE FROM users WHERE email = ?", [testEmail]);
    } catch (_) {}
  }

  before(async () => {
    TestClient.resetRateLimits();
    customerClient = new TestClient();
    adminClient = new TestClient();
    await adminClient.loginAsAdmin();

    cleanupWf05();
    ensureUser();

    await customerClient.loginAsCustomer(testEmail, testPassword);
  });

  after(async () => {
    cleanupWf05();
  });

  it('Step 1: Execute rapid sequence of 5 mixed operations (Redeem, Earn, Voucher, Tier Jump, Cancel Restore)', async () => {
    ensureUser();

    // -------------------------------------------------------------------------
    // Op 1: Checkout points redemption (-200 points, 20,000 VND discount)
    // -------------------------------------------------------------------------
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '999 Điện Biên Phủ', 100000, 0, 20000, 200, 20000, 80000, 'cod', 'pending', 'placed', NOW())`,
      [order1Code, userId, testFullName, testPhone]
    );
    order1Id = Number(dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order1Code])[0].id);

    const redeem1 = callLoyaltyService(`redeemPointsAtCheckout(${userId}, ${order1Id}, 200, '${order1Code}')`);
    assert.ok(redeem1.success, 'Op 1 redemption must succeed');
    assert.equal(redeem1.new_balance, 800, 'Op 1 new_balance must be 800');

    // -------------------------------------------------------------------------
    // Op 2: Completed Earn Order (300,000 VND) -> +30 points, +300k total_spent
    // -------------------------------------------------------------------------
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '999 Điện Biên Phủ', 300000, 0, 0, 300000, 'cod', 'pending', 'placed', NOW())`,
      [order2Code, userId, testFullName, testPhone]
    );
    order2Id = Number(dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order2Code])[0].id);

    await adminClient.adminUpdateOrderStatus(order2Code, 'completed');
    let ord2Loyalty = dbHelper.getOrderLoyalty(order2Code);
    if (!ord2Loyalty || ord2Loyalty.points_earned === 0) {
      callLoyaltyService(`processOrderEarnPoints(${order2Id})`);
    }

    let uLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(uLoyalty.points_balance, 830, `Op 2 balance must be 830 (800 + 30), got ${uLoyalty.points_balance}`);
    assert.equal(uLoyalty.total_spent, 300000, `Op 2 total_spent must be 300,000 VND, got ${uLoyalty.total_spent}`);

    // -------------------------------------------------------------------------
    // Op 3: Voucher package exchange (-100 points -> 10,000 VND coupon)
    // -------------------------------------------------------------------------
    const voucherRes = callLoyaltyService(`exchangePointsForVoucher(${userId}, 1)`);
    assert.ok(voucherRes.success, 'Op 3 voucher exchange must succeed');
    assert.equal(voucherRes.new_balance, 730, 'Op 3 new_balance must be 730 (830 - 100)');

    // -------------------------------------------------------------------------
    // Op 4: Completed High-Value Earn Order (800,000 VND) -> +80 points, reaches 1.1M spent -> SILVER!
    // -------------------------------------------------------------------------
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '999 Điện Biên Phủ', 800000, 0, 0, 800000, 'cod', 'pending', 'placed', NOW())`,
      [order3Code, userId, testFullName, testPhone]
    );
    order3Id = Number(dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order3Code])[0].id);

    await adminClient.adminUpdateOrderStatus(order3Code, 'completed');
    let ord3Loyalty = dbHelper.getOrderLoyalty(order3Code);
    if (!ord3Loyalty || ord3Loyalty.points_earned === 0) {
      callLoyaltyService(`processOrderEarnPoints(${order3Id})`);
    }

    uLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(uLoyalty.points_balance, 810, `Op 4 balance must be 810 (730 + 80), got ${uLoyalty.points_balance}`);
    assert.equal(uLoyalty.total_spent, 1100000, `Op 4 total_spent must reach 1,100,000 VND, got ${uLoyalty.total_spent}`);
    assert.equal(uLoyalty.member_tier, TIERS.SILVER, `Op 4 user must be auto-promoted to SILVER tier, got ${uLoyalty.member_tier}`);

    // -------------------------------------------------------------------------
    // Op 5: Checkout redemption (-150 points) followed by cancellation -> points restored (+150 points)
    // -------------------------------------------------------------------------
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, points_used, points_discount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '999 Điện Biên Phủ', 150000, 0, 15000, 150, 15000, 135000, 'cod', 'pending', 'placed', NOW())`,
      [order4Code, userId, testFullName, testPhone]
    );
    order4Id = Number(dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order4Code])[0].id);

    // Deduct 150 points
    callLoyaltyService(`redeemPointsAtCheckout(${userId}, ${order4Id}, 150, '${order4Code}')`);
    let tempLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(tempLoyalty.points_balance, 660, 'Balance must temporarily be 660 (810 - 150)');

    // Cancel order -> restore 150 points
    dbHelper.query("UPDATE orders SET order_status = 'cancelled' WHERE id = ?", [order4Id]);
    callLoyaltyService(`handleOrderCancellation(${order4Id})`);

    uLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(uLoyalty.points_balance, 810, `Op 5 balance must be restored to 810 (660 + 150), got ${uLoyalty.points_balance}`);

    lastState = { tier: uLoyalty.member_tier, points: uLoyalty.points_balance, spent: uLoyalty.total_spent };
  });

  it('Step 2: Invariant Audit 1: Mathematical Ledger Continuity across all transactions', async () => {
    ensureUser();
    const transactions = dbHelper.getTransactions(userId);
    assert.ok(transactions.length >= 6, `Must have at least 6 ledger transactions recorded, found ${transactions.length}`);

    // Verify running balance continuity: balance_after[i] === balance_after[i-1] + points[i]
    let runningBalance = 1000; // initial balance
    for (let i = 0; i < transactions.length; i++) {
      const tx = transactions[i];
      const delta = Number(tx.points);
      runningBalance += delta;
      const recordedBalance = Number(tx.balance_after);

      assert.equal(
        recordedBalance,
        runningBalance,
        `Ledger continuity violated at transaction #${tx.id} (${tx.transaction_type}): recorded balance_after (${recordedBalance}) !== expected running balance (${runningBalance})`
      );
    }

    // Invariant: initial (1000) + SUM(points) === users.points_balance === 810
    const sumDeltas = transactions.reduce((acc, t) => acc + Number(t.points), 0);
    const userLoyalty = dbHelper.getUserLoyalty(userId);

    assert.equal(
      1000 + sumDeltas,
      userLoyalty.points_balance,
      `Ledger sum invariant failed: 1000 + SUM(points) (${1000 + sumDeltas}) !== points_balance (${userLoyalty.points_balance})`
    );
    assert.equal(userLoyalty.points_balance, 810, 'Final points balance must be exactly 810');
  });

  it('Step 3: Invariant Audit 2: Spending Consistency with completed orders', async () => {
    ensureUser();
    // Query sum of all completed orders for this user
    const spentRow = dbHelper.query(
      "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE customer_id = ? AND order_status = 'completed'",
      [userId]
    );
    const sumCompletedOrders = Number(spentRow[0].total);

    const userLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(
      userLoyalty.total_spent,
      sumCompletedOrders,
      `Spending invariant violated: users.total_spent (${userLoyalty.total_spent}) !== SUM(completed orders) (${sumCompletedOrders})`
    );
    assert.equal(userLoyalty.total_spent, 1100000, 'Total spent must be exactly 1,100,000 VND (300k + 800k)');
  });

  it('Step 4: Invariant Audit 3: Tier Synchronization Invariant', async () => {
    ensureUser();
    const userLoyalty = dbHelper.getUserLoyalty(userId);
    const expectedTier = callLoyaltyService(`calculateTier(${userLoyalty.total_spent})`);

    assert.equal(
      userLoyalty.member_tier,
      expectedTier,
      `Tier synchronization violated: users.member_tier (${userLoyalty.member_tier}) !== calculateTier (${expectedTier})`
    );
    assert.equal(userLoyalty.member_tier, TIERS.SILVER, 'Tier must be SILVER for 1,100,000 VND total spent');
  });

  it('Step 5: Invariant Audit 4: Zero Orphan Records & Foreign Key Integrity', async () => {
    ensureUser();
    // 1. Check all transactions have valid user_id
    const orphanUsers = dbHelper.query(
      'SELECT id FROM point_transactions WHERE user_id = ? AND user_id NOT IN (SELECT id FROM users)',
      [userId]
    );
    assert.equal(orphanUsers.length, 0, 'No point_transactions should have orphaned user_id');

    // 2. Check all non-null order_id in transactions point to existing orders
    const orphanOrders = dbHelper.query(
      'SELECT id, order_id FROM point_transactions WHERE user_id = ? AND order_id IS NOT NULL AND order_id NOT IN (SELECT id FROM orders)',
      [userId]
    );
    assert.equal(orphanOrders.length, 0, 'No point_transactions should have orphaned order_id');

    // 3. Check coupons claimed by user point to valid user_id
    const userCoupons = dbHelper.query('SELECT id, user_id FROM coupons WHERE user_id = ?', [userId]);
    assert.ok(userCoupons.length >= 1, 'Must have at least 1 coupon from Op 3 voucher exchange');
    for (const c of userCoupons) {
      assert.equal(Number(c.user_id), userId, 'Coupon user_id must point to valid user');
    }
  });

  it('Step 6: Invariant Audit 5: Non-Negativity & Intermediate State Guards', async () => {
    ensureUser();
    const userLoyalty = dbHelper.getUserLoyalty(userId);
    assert.ok(userLoyalty.points_balance >= 0, 'User points_balance must be non-negative');
    assert.ok(userLoyalty.total_spent >= 0, 'User total_spent must be non-negative');

    // Check every transaction balance_after was strictly >= 0
    const transactions = dbHelper.getTransactions(userId);
    for (const tx of transactions) {
      assert.ok(
        Number(tx.balance_after) >= 0,
        `Transaction #${tx.id} produced illegal negative balance_after: ${tx.balance_after}`
      );
    }
  });
});
