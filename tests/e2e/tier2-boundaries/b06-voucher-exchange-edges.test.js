/**
 * b06-voucher-exchange-edges.test.js - Tier 2 Boundary Tests: Rewards Center Voucher Exchange Edges
 * 
 * Verifies edge cases and invariants when exchanging points for vouchers:
 * - B6-01: Balance of 99 points attempting 100-point voucher is rejected
 * - B6-02: Exact 100 points balance succeeds and leaves balance at exactly 0
 * - B6-03: Invalid package ID (e.g. 999 or -1) is rejected cleanly
 * - B6-04: Exchanging 10 consecutive vouchers generates 10 unique collision-free coupon codes
 * - B6-05: Claimed voucher is strictly bound to the claiming user (user_id linkage)
 *
 * Source: ORIGINAL_REQUEST.md §R1, §R3, PROJECT.md §F10, TEST_INFRA.md §Tier 2
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');

const dbHelper = require('../harness/db-helper');
const {
  VOUCHER_PACKAGES,
  generateTestEmail
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

describe('Tier 2 Boundaries: b06-voucher-exchange-edges (5 tests)', () => {
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

  function createTestUser(pointsBalance = 0) {
    const email = 'e2e_vch_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7) + '@fastfood.test';
    const phone = generateUniquePhone();
    const passwordHash = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
       VALUES (2, 'Voucher Test User', ?, ?, ?, 'BRONZE', ?, 0.00, 'active', NOW())`,
      [email, phone, passwordHash, pointsBalance]
    );
    const rows = dbHelper.query('SELECT id, email, points_balance FROM users WHERE email = ? LIMIT 1', [email]);
    assert.ok(rows && rows.length > 0, 'User should be created');
    return rows[0];
  }

  it('B6-01: Balance of 99 points attempting 100-point voucher is rejected with insufficient balance error', () => {
    // User has 99 points. Package 1 requires 100 points.
    const user = createTestUser(99);

    const phpRes = callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, 1));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, false, 'Exchange with 99 points must fail');
    assert.match(result.error, /không đủ/i, 'Error must indicate insufficient points balance');

    // Balance must remain 99
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 99);

    // No coupon should be created
    const coupons = dbHelper.getCoupons(user.id);
    assert.equal(coupons.length, 0);
  });

  it('B6-02: Exact 100 points balance succeeds in exchanging for 100-point voucher and leaves balance at exactly 0', () => {
    const user = createTestUser(100);

    const phpRes = callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, 1));`);
    const result = JSON.parse(phpRes);

    assert.equal(result.success, true);
    assert.equal(result.points_deducted, 100);
    assert.equal(result.new_balance, 0);
    assert.ok(result.coupon_code && result.coupon_code.length > 0);

    // Balance in DB is exactly 0
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 0);

    // Transaction ledger records REDEEM_VOUCHER with points = -100 and balance_after = 0
    const txs = dbHelper.getTransactions(user.id);
    assert.equal(txs.length, 1);
    assert.equal(txs[0].transaction_type, 'REDEEM_VOUCHER');
    assert.equal(Number(txs[0].points), -100);
    assert.equal(Number(txs[0].balance_after), 0);
  });

  it('B6-03: Invalid package ID (e.g. 999 or -1) is rejected cleanly with error', () => {
    const user = createTestUser(500);

    const resInvalidHigh = JSON.parse(callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, 999));`));
    assert.equal(resInvalidHigh.success, false);
    assert.match(resInvalidHigh.error, /không hợp lệ/i);

    const resInvalidNegative = JSON.parse(callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, -1));`));
    assert.equal(resInvalidNegative.success, false);
    assert.match(resInvalidNegative.error, /không hợp lệ/i);

    // Points balance untouched
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 500);
  });

  it('B6-04: Exchanging 10 consecutive vouchers generates 10 unique collision-free coupon codes', () => {
    // 10 vouchers x 100 pts = 1,000 points
    const user = createTestUser(1000);
    const codes = new Set();

    for (let i = 0; i < 10; i++) {
      const phpRes = callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${user.id}, 1));`);
      const result = JSON.parse(phpRes);
      assert.equal(result.success, true, `Voucher exchange #${i + 1} should succeed`);
      assert.ok(result.coupon_code, 'Must return coupon code');
      codes.add(result.coupon_code);
    }

    assert.equal(codes.size, 10, 'All 10 generated coupon codes must be strictly distinct and unique');

    // Balance is 0
    const userAfter = dbHelper.getUserLoyalty(user.id);
    assert.equal(userAfter.points_balance, 0);
  });

  it('B6-05: Claimed voucher is strictly bound to the claiming user (user_id binding)', () => {
    const userA = createTestUser(200);
    const userB = createTestUser(0);

    const phpRes = callLoyaltyPhp(`echo json_encode(exchangePointsForVoucher(${userA.id}, 1));`);
    const result = JSON.parse(phpRes);
    assert.equal(result.success, true);

    const couponCode = result.coupon_code;

    // Verify in database that the coupon has user_id = userA.id
    const couponsA = dbHelper.getCoupons(userA.id);
    assert.equal(couponsA.length, 1);
    assert.equal(couponsA[0].code, couponCode);
    assert.equal(Number(couponsA[0].user_id), userA.id, 'Coupon user_id must be bound to User A');

    // Verify User B does not own this coupon
    const couponsB = dbHelper.getCoupons(userB.id);
    assert.equal(couponsB.length, 0, 'User B must not have any coupons');
  });
});
