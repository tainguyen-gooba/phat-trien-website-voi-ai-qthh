/**
 * f10-voucher-rewards.test.js - Tier 1: F10 Rewards Center - Points to Voucher Exchange
 *
 * Tests:
 * 1. Provides 3 standard voucher packages (10k for 100pts, 20k for 200pts, 50k for 500pts)
 * 2. Deducts points and generates a new coupon record in coupons table
 * 3. Newly created coupon is strictly bound to customer user_id
 * 4. Appends atomic REDEEM_VOUCHER transaction with negative points in point_transactions
 * 5. Exchanged voucher appears in customer active vouchers listing
 */

const { describe, it, before, after, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

function exchangeVoucherViaPhp(userId, packageId) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    $res = exchangePointsForVoucher(${userId}, ${packageId});
    echo json_encode($res);
  `;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return JSON.parse(output.trim());
}

describe('Tier 1: F10 - Rewards Center - Points to Voucher Exchange', () => {
  let testUserId;
  let testEmail;

  beforeEach(async () => {
    testEmail = fixtures.generateTestEmail('e2e_f10');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'E2E F10 Customer', ?, '0910000001', 'hash', 'active', 'SILVER', 600, 1500000, NOW())
    `, [testEmail]);
    const u = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    testUserId = u.id;
  });

  afterEach(async () => {
    if (testUserId) {
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM coupons WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
      testUserId = null;
    }
  });

  it('F10-01: Provides standard voucher packages for 10k, 20k, and 50k discounts', async () => {
    const script = `
      require 'helpers/loyalty.php';
      echo json_encode(getVoucherPackages());
    `;
    const output = execFileSync('php', ['-r', script], {
      cwd: PROJECT_ROOT,
      encoding: 'utf8',
      windowsHide: true
    });
    const packages = JSON.parse(output.trim());

    assert.ok(packages[1], 'Package 1 (10k) must exist');
    assert.equal(packages[1].points_cost, 100, 'Package 1 requires 100 points');
    assert.equal(packages[1].discount_amount, 10000, 'Package 1 gives 10,000 VNĐ discount');

    assert.ok(packages[2], 'Package 2 (20k) must exist');
    assert.equal(packages[2].points_cost, 200, 'Package 2 requires 200 points');
    assert.equal(packages[2].discount_amount, 20000, 'Package 2 gives 20,000 VNĐ discount');

    assert.ok(packages[3], 'Package 3 (50k) must exist');
    assert.equal(packages[3].points_cost, 500, 'Package 3 requires 500 points');
    assert.equal(packages[3].discount_amount, 50000, 'Package 3 gives 50,000 VNĐ discount');
  });

  it('F10-02: Exchanging points deducts points balance and generates new coupon record', async () => {
    const userBefore = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userBefore.points_balance, 600);

    // Exchange Package 1 (100 points -> 10,000 VNĐ voucher)
    const res = exchangeVoucherViaPhp(testUserId, 1);
    assert.ok(res.success, 'Exchange should succeed');
    assert.ok(res.coupon_code, 'Must return generated coupon code');
    assert.equal(res.points_deducted, 100, 'Must deduct 100 points');
    assert.equal(res.new_balance, 500, 'New balance must be 500');

    // Verify DB user balance
    const userAfter = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userAfter.points_balance, 500, 'DB points_balance must be 500');

    // Verify DB coupon record
    const coupons = dbHelper.getCoupons(testUserId);
    assert.equal(coupons.length, 1, 'Exactly 1 coupon should be created in coupons table');
    assert.equal(coupons[0].code, res.coupon_code);
    assert.equal(Number(coupons[0].discount_value), 10000);
    assert.equal(Number(coupons[0].is_active), 1);
  });

  it('F10-03: Created coupon is strictly bound to customer user_id', async () => {
    const res = exchangeVoucherViaPhp(testUserId, 2); // 20k voucher
    assert.ok(res.success);

    const couponInDb = dbHelper.query('SELECT * FROM coupons WHERE code = ?', [res.coupon_code])[0];
    assert.ok(couponInDb, 'Coupon must exist in database');
    assert.equal(Number(couponInDb.user_id), testUserId, 'Coupon user_id must match the customer ID');
  });

  it('F10-04: Appends atomic REDEEM_VOUCHER transaction with negative points in point_transactions', async () => {
    const res = exchangeVoucherViaPhp(testUserId, 3); // 500 points for 50k voucher
    assert.ok(res.success);

    const txs = dbHelper.getTransactions(testUserId);
    assert.equal(txs.length, 1);

    const tx = txs[0];
    assert.equal(tx.transaction_type, fixtures.TRANSACTION_TYPES.REDEEM_VOUCHER);
    assert.equal(Number(tx.points), -500, 'Points delta must be negative (-500)');
    assert.equal(Number(tx.balance_after), 100, 'balance_after must be 100 (600 - 500)');
    assert.ok(tx.description && tx.description.includes(res.coupon_code), 'Description must reference coupon code');
  });

  it('F10-05: Exchanged active vouchers are queryable and linked to customer profile', async () => {
    // Exchange 2 vouchers
    exchangeVoucherViaPhp(testUserId, 1);
    exchangeVoucherViaPhp(testUserId, 2);

    const activeCoupons = dbHelper.query(`
      SELECT code, discount_value, min_order_value, is_active 
      FROM coupons 
      WHERE user_id = ? AND is_active = 1 AND end_date > NOW()
    `, [testUserId]);

    assert.equal(activeCoupons.length, 2, 'Customer must have 2 active valid coupons in DB');
    const values = activeCoupons.map(c => Number(c.discount_value)).sort((a, b) => a - b);
    assert.deepEqual(values, [10000, 20000], 'Coupons must have values 10,000 and 20,000');
  });

});
