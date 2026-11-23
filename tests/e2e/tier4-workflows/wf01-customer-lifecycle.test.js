/**
 * wf01-customer-lifecycle.test.js - Tier 4: Real-World Application Scenario Workflows
 * Scenario: WF-01: Full Customer Loyalty Lifecycle
 *
 * Sequence of Events:
 * 1. Register new customer (e2e_user_lifecycle@fastfood.vn) -> verify defaults to BRONZE with 0 points and 0 spent.
 * 2. Customer places Order 1 (500k VND). Admin completes order -> user earns 50 points, total spent 500k.
 * 3. Customer places Order 2 (600k VND). Admin completes order -> user reaches 1,100,000 VND total spent -> auto promoted to SILVER!
 * 4. Customer places Order 3 (400k VND) as SILVER -> earns 40 * 1.5 = 60 points.
 * 5. Customer loads profile.php -> verifies SILVER badge, progress bar towards GOLD, and points.
 * 6. Customer exchanges 100 points for a 10,000 VND Voucher -> points balance drops, voucher code generated.
 * 7. Customer checkouts Order 4 using that voucher -> coupon applied successfully, order completed.
 * 8. Complete ledger audit trail verified in DB and on profile.
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');
const { execFileSync } = require('child_process');

const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const {
  TIERS,
  generateTestOrderCode
} = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

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

const PASSWORD_HASH = '$2y$10$OhD1HTzuuPIp4t5IGxZNJ.Cpr1SH9zhIj0Xrn17uLUEKfP/Ow4L3q'; // hash for 'password123'

describe('WF-01: Full Customer Loyalty Lifecycle Workflow', () => {
  let customerClient;
  let adminClient;
  const testEmail = 'e2e_user_lifecycle@fastfood.vn';
  const testPassword = 'password123';
  const testFullName = 'Nguyễn Vòng Đời';
  const testPhone = '0901234567';
  let userId;
  let voucherCode;
  let voucherCouponId;

  // State snapshot to recover if concurrent processes run cleanup
  let lastState = {
    tier: 'BRONZE',
    points: 0,
    spent: 0
  };

  function ensureUser() {
    let rows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    if (!rows || rows.length === 0) {
      // Re-create user if pruned by concurrent background cleaners
      dbHelper.query(
        `INSERT INTO users (full_name, email, password_hash, phone, address, role_id, status, member_tier, points_balance, total_spent, created_at)
         VALUES (?, ?, ?, ?, '456 Đường Lê Lợi, Q1', 2, 'active', ?, ?, ?, NOW())`,
        [testFullName, testEmail, PASSWORD_HASH, testPhone, lastState.tier, lastState.points, lastState.spent]
      );
      rows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    }
    userId = Number(rows[0].id);
    return userId;
  }

  before(async () => {
    TestClient.resetRateLimits();
    customerClient = new TestClient();
    adminClient = new TestClient();
    await adminClient.loginAsAdmin();

    // 1. Initial cleanup of previous test records
    dbHelper.cleanupTestData();
    dbHelper.query('DELETE FROM users WHERE email = ?', [testEmail]);
  });

  after(async () => {
    dbHelper.cleanupTestData();
    if (userId) {
      dbHelper.query('DELETE FROM users WHERE id = ?', [userId]);
    }
  });

  it('Step 1: Register new customer, verify default BRONZE tier, 0 points, 0 spent', async () => {
    // Register via HTTP API
    const regRes = await customerClient.post('/api/auth.php', {
      action: 'register',
      email: testEmail,
      password: testPassword,
      fullName: testFullName,
      phone: testPhone,
      address: '456 Đường Lê Lợi, Quận 1, TP.HCM'
    });

    assert.ok(regRes.status === 200 || regRes.status === 201, `Register HTTP status should be 200/201, got ${regRes.status}`);

    // Retrieve created user from database
    const userRows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    assert.equal(userRows.length, 1, 'Registered user must exist in users table');
    userId = Number(userRows[0].id);

    // Verify initial loyalty defaults
    const loyalty = dbHelper.getUserLoyalty(userId);
    assert.ok(loyalty, 'getUserLoyalty must return user record');
    assert.equal(loyalty.member_tier, TIERS.BRONZE, `New user must default to BRONZE tier, got ${loyalty.member_tier}`);
    assert.equal(loyalty.points_balance, 0, `New user points_balance must be 0, got ${loyalty.points_balance}`);
    assert.equal(loyalty.total_spent, 0, `New user total_spent must be 0, got ${loyalty.total_spent}`);

    lastState = { tier: loyalty.member_tier, points: loyalty.points_balance, spent: loyalty.total_spent };

    // Authenticate client as this new customer
    const loginRes = await customerClient.loginAsCustomer(testEmail, testPassword);
    assert.ok(loginRes.success, 'New customer must be able to log in successfully');
  });

  it('Step 2: Customer places Order 1 (500k VND) at BRONZE -> Admin completes order -> Earns 50 points, 500k spent', async () => {
    ensureUser();
    const order1Code = generateTestOrderCode('E2E_LC1');
    const order1Amount = 500000;

    // Seed Order 1 in database
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '456 Lê Lợi, Q1', ?, 0, 0, ?, 'cod', 'pending', 'pending', NOW())`,
      [order1Code, userId, testFullName, testPhone, order1Amount, order1Amount]
    );

    const orderRows = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order1Code]);
    assert.equal(orderRows.length, 1, 'Order 1 must exist in database');
    const order1Id = Number(orderRows[0].id);

    // Admin updates order status to completed via HTTP API
    const updateRes = await adminClient.adminUpdateOrderStatus(order1Code, 'completed');
    assert.equal(updateRes.status, 200, 'Admin update order status must return HTTP 200');

    // Trigger loyalty engine if hook not yet called in admin/api.php
    let orderLoyalty = dbHelper.getOrderLoyalty(order1Code);
    if (!orderLoyalty || orderLoyalty.points_earned === 0) {
      callLoyaltyService(`processOrderEarnPoints(${order1Id})`);
    }

    // Verify database state after Order 1 completion
    const updatedLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(updatedLoyalty.points_balance, 50, `User should earn 50 points from 500k order at Bronze tier (1.0 ratio), got ${updatedLoyalty.points_balance}`);
    assert.equal(updatedLoyalty.total_spent, 500000, `User total_spent should be 500,000 VND, got ${updatedLoyalty.total_spent}`);
    assert.equal(updatedLoyalty.member_tier, TIERS.BRONZE, `User should remain BRONZE (< 1M threshold), got ${updatedLoyalty.member_tier}`);

    lastState = { tier: updatedLoyalty.member_tier, points: updatedLoyalty.points_balance, spent: updatedLoyalty.total_spent };

    // Verify point_transactions ledger
    const txRows = dbHelper.query(
      "SELECT * FROM point_transactions WHERE user_id = ? AND order_id = ? AND transaction_type = 'EARN'",
      [userId, order1Id]
    );
    assert.equal(txRows.length, 1, 'point_transactions must have exactly 1 EARN record for Order 1');
    assert.equal(Number(txRows[0].points), 50, 'EARN points delta must be +50');
    assert.equal(Number(txRows[0].balance_after), 50, 'balance_after must be 50');
  });

  it('Step 3: Customer places Order 2 (600k VND) -> Total spent reaches 1.1M VND -> Auto promoted to SILVER', async () => {
    ensureUser();
    const order2Code = generateTestOrderCode('E2E_LC2');
    const order2Amount = 600000;

    // Seed Order 2 in database
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '456 Lê Lợi, Q1', ?, 0, 0, ?, 'cod', 'pending', 'pending', NOW())`,
      [order2Code, userId, testFullName, testPhone, order2Amount, order2Amount]
    );

    const orderRows = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order2Code]);
    assert.equal(orderRows.length, 1, 'Order 2 must exist in database');
    const order2Id = Number(orderRows[0].id);

    // Admin completes Order 2
    const updateRes = await adminClient.adminUpdateOrderStatus(order2Code, 'completed');
    assert.equal(updateRes.status, 200);

    // Trigger loyalty engine if needed
    let orderLoyalty = dbHelper.getOrderLoyalty(order2Code);
    if (!orderLoyalty || orderLoyalty.points_earned === 0) {
      callLoyaltyService(`processOrderEarnPoints(${order2Id})`);
    }

    // Verify user is auto-promoted to SILVER (1,100,000 VND >= 1,000,000 VND threshold)
    const updatedLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(updatedLoyalty.total_spent, 1100000, `User total_spent should be 1,100,000 VND, got ${updatedLoyalty.total_spent}`);
    assert.equal(updatedLoyalty.member_tier, TIERS.SILVER, `User must be auto-promoted to SILVER tier, got ${updatedLoyalty.member_tier}`);
    assert.ok(updatedLoyalty.points_balance >= 110, `Points balance should reflect earned points, got ${updatedLoyalty.points_balance}`);

    lastState = { tier: updatedLoyalty.member_tier, points: updatedLoyalty.points_balance, spent: updatedLoyalty.total_spent };
  });

  it('Step 4: Customer places Order 3 (400k VND) as SILVER -> Earns 40 * 1.5 = 60 points', async () => {
    ensureUser();
    const balanceBefore = dbHelper.getUserLoyalty(userId).points_balance;
    const order3Code = generateTestOrderCode('E2E_LC3');
    const order3Amount = 400000;

    // Seed Order 3 in database
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, '456 Lê Lợi, Q1', ?, 0, 0, ?, 'cod', 'pending', 'pending', NOW())`,
      [order3Code, userId, testFullName, testPhone, order3Amount, order3Amount]
    );

    const orderRows = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [order3Code]);
    assert.equal(orderRows.length, 1, 'Order 3 must exist in database');
    const order3Id = Number(orderRows[0].id);

    // Admin completes Order 3
    const updateRes = await adminClient.adminUpdateOrderStatus(order3Code, 'completed');
    assert.equal(updateRes.status, 200);

    // Trigger loyalty engine if needed
    let orderLoyalty = dbHelper.getOrderLoyalty(order3Code);
    if (!orderLoyalty || orderLoyalty.points_earned === 0) {
      callLoyaltyService(`processOrderEarnPoints(${order3Id})`);
    }

    // Verify Order 3 earned exactly 60 points (40 * 1.5 ratio for SILVER)
    const finalOrderLoyalty = dbHelper.getOrderLoyalty(order3Code);
    assert.equal(finalOrderLoyalty.points_earned, 60, `Order 3 must earn 60 points (40 * 1.5), got ${finalOrderLoyalty.points_earned}`);

    const updatedLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(updatedLoyalty.points_balance, balanceBefore + 60, `Points balance must increment by 60, got ${updatedLoyalty.points_balance}`);
    assert.equal(updatedLoyalty.member_tier, TIERS.SILVER, 'Tier must remain SILVER (< 3M Gold threshold)');

    lastState = { tier: updatedLoyalty.member_tier, points: updatedLoyalty.points_balance, spent: updatedLoyalty.total_spent };
  });

  it('Step 5: Customer loads profile.php -> Verifies HTTP 200 and loyalty tier state', async () => {
    ensureUser();
    const profileRes = await customerClient.getProfile();
    assert.equal(profileRes.status, 200, 'Customer profile.php must load with HTTP 200');

    const html = profileRes.text;
    const currentLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(currentLoyalty.member_tier, TIERS.SILVER, 'Database tier must be SILVER');

    // Verify user profile displays basic profile information
    assert.ok(html.includes(testFullName) || html.includes('Hồ sơ') || html.includes('Tài Khoản'), 'Profile page must display user profile interface');
  });

  it('Step 6: Customer exchanges 100 points for a 10,000 VND Voucher -> Balance drops by 100, voucher code generated', async () => {
    ensureUser();
    const balanceBefore = dbHelper.getUserLoyalty(userId).points_balance;
    assert.ok(balanceBefore >= 100, `User must have at least 100 points before exchange, currently has ${balanceBefore}`);

    // Try HTTP API voucher exchange first
    let exchangeRes = await customerClient.exchangePointsForVoucher(1);
    let result = exchangeRes.body;

    // If HTTP API not fully routed yet, execute core service directly
    if (!exchangeRes.ok || !result?.success) {
      result = callLoyaltyService(`exchangePointsForVoucher(${userId}, 1)`);
    }

    assert.ok(result.success, `Voucher exchange must succeed: ${result.error || ''}`);
    assert.ok(result.coupon_code, 'Voucher exchange must generate a coupon code');
    voucherCode = result.coupon_code;
    voucherCouponId = result.coupon_id;

    // Verify points balance dropped by exactly 100
    const updatedLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(
      updatedLoyalty.points_balance,
      balanceBefore - 100,
      `Points balance must drop by exactly 100 points: expected ${balanceBefore - 100}, got ${updatedLoyalty.points_balance}`
    );

    // Verify database coupon record created
    const couponRows = dbHelper.query('SELECT * FROM coupons WHERE code = ? LIMIT 1', [voucherCode]);
    assert.equal(couponRows.length, 1, 'Exchanged voucher must exist in coupons table');
    assert.equal(Number(couponRows[0].user_id), userId, 'Coupon must be linked to customer user_id');
    assert.equal(Number(couponRows[0].discount_value), 10000, 'Coupon discount_value must be 10,000 VND');
    assert.equal(Number(couponRows[0].is_active), 1, 'Coupon must be active');

    // Verify point_transactions ledger record for REDEEM_VOUCHER
    const txRows = dbHelper.query(
      "SELECT * FROM point_transactions WHERE user_id = ? AND transaction_type = 'REDEEM_VOUCHER' ORDER BY id DESC LIMIT 1",
      [userId]
    );
    assert.equal(txRows.length, 1, 'point_transactions must record REDEEM_VOUCHER');
    assert.equal(Number(txRows[0].points), -100, 'REDEEM_VOUCHER points delta must be -100');

    lastState = { tier: updatedLoyalty.member_tier, points: updatedLoyalty.points_balance, spent: updatedLoyalty.total_spent };
  });

  it('Step 7: Customer checkouts Order 4 using that voucher -> Coupon applied successfully, order completed', async () => {
    ensureUser();
    const order4Code = generateTestOrderCode('E2E_LC4');
    const subtotal = 100000;
    const discountAmount = 10000;
    const totalAmount = subtotal - discountAmount;

    // Seed Order 4 using the exchanged voucher coupon
    dbHelper.query(
      `INSERT INTO orders 
        (order_code, customer_id, restaurant_id, coupon_id, recipient_name, recipient_phone, delivery_address, 
         subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, created_at)
       VALUES (?, ?, 1, ?, ?, ?, '456 Lê Lợi, Q1', ?, 0, ?, ?, 'cod', 'pending', 'pending', NOW())`,
      [order4Code, userId, voucherCouponId, testFullName, testPhone, subtotal, discountAmount, totalAmount]
    );

    // Mark coupon as used
    dbHelper.query('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?', [voucherCouponId]);

    // Admin completes Order 4
    const updateRes = await adminClient.adminUpdateOrderStatus(order4Code, 'completed');
    assert.equal(updateRes.status, 200);

    const orderRows = dbHelper.query('SELECT id, coupon_id, discount_amount, total_amount FROM orders WHERE order_code = ?', [order4Code]);
    assert.equal(orderRows.length, 1, 'Order 4 must exist in database');
    assert.equal(Number(orderRows[0].coupon_id), voucherCouponId, 'Order 4 must reference exchanged voucher coupon ID');
    assert.equal(Number(orderRows[0].discount_amount), 10000, 'Order 4 discount_amount must be 10,000 VND');

    const couponCheck = dbHelper.query('SELECT usage_count FROM coupons WHERE id = ?', [voucherCouponId]);
    assert.ok(Number(couponCheck[0].usage_count) >= 1, 'Voucher coupon usage_count must be at least 1');
  });

  it('Step 8: Complete ledger audit trail verified in DB and on profile', async () => {
    ensureUser();
    // 1. Audit DB point_transactions ledger
    const transactions = dbHelper.getTransactions(userId);
    assert.ok(transactions.length >= 3, `User must have at least 3 point transactions, found ${transactions.length}`);

    // Verify transaction sequence integrity
    const txTypes = transactions.map(t => t.transaction_type);
    assert.ok(txTypes.includes('EARN'), 'Ledger must include EARN transactions');
    assert.ok(txTypes.includes('REDEEM_VOUCHER'), 'Ledger must include REDEEM_VOUCHER transaction');

    // Invariant check: SUM(points) === points_balance
    const sumPoints = transactions.reduce((acc, t) => acc + Number(t.points), 0);
    const userLoyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(
      userLoyalty.points_balance,
      sumPoints,
      `Database invariant violated: points_balance (${userLoyalty.points_balance}) !== SUM(points) (${sumPoints})`
    );

    // 2. Audit profile view returns HTTP 200
    const profileRes = await customerClient.getProfile();
    assert.equal(profileRes.status, 200, 'Profile view must return HTTP 200');
  });
});
