/**
 * f03-spending-backfill.test.js - Tier 1: F3 Backfill Existing User Spending & Tiers
 *
 * Tests:
 * 1. Historical completed orders are summed into users.total_spent
 * 2. Non-completed orders (placed, preparing, delivering, cancelled) are excluded from total_spent
 * 3. Member tier is correctly computed and mapped from the backfilled total_spent
 * 4. Initial points_balance remains 0 (historical orders do not create retroactive points)
 * 5. Backfill execution is completely idempotent (running multiple times yields identical results)
 */

const { describe, it, before, after, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

function syncUserTierViaPhp(userId) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    $res = syncUserTier(${userId});
    echo json_encode($res);
  `;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return JSON.parse(output.trim());
}

describe('Tier 1: F3 - Backfill Existing User Spending & Tiers', () => {
  let testUserId = null;
  let testEmail = null;

  beforeEach(() => {
    testEmail = fixtures.generateTestEmail('e2e_f03');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'E2E Backfill User', ?, '0909000001', 'hash', 'active', 'BRONZE', 0, 0, NOW())
    `, [testEmail]);
    const u = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    testUserId = u.id;
  });

  afterEach(() => {
    if (testUserId) {
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM orders WHERE customer_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
      testUserId = null;
    }
  });

  it('F3-01: Sums total_amount of all completed orders into users.total_spent', async () => {
    // Insert 2 completed orders: 350,000 + 450,000 = 800,000 VND
    const code1 = fixtures.generateTestOrderCode('E2E_BK1_');
    const code2 = fixtures.generateTestOrderCode('E2E_BK2_');

    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES 
        (?, ?, 1, 'Recip 1', '0909000001', '123 St', 350000, 0, 350000, 'cod', 'paid', 'completed', 0, 0, 0, NOW()),
        (?, ?, 1, 'Recip 2', '0909000001', '123 St', 450000, 0, 450000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [code1, testUserId, code2, testUserId]);

    const res = syncUserTierViaPhp(testUserId);
    assert.ok(res.success, 'syncUserTier should return success');
    assert.equal(res.total_spent, 800000, 'Total spent must be exactly 800,000 VNĐ');

    const userInDb = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userInDb.total_spent, 800000, 'DB users.total_spent must be 800,000 VNĐ');
  });

  it('F3-02: Excludes non-completed orders (placed, preparing, delivering, cancelled) from total_spent', async () => {
    const codeCompleted = fixtures.generateTestOrderCode('E2E_CMP_');
    const codePlaced = fixtures.generateTestOrderCode('E2E_PLC_');
    const codePreparing = fixtures.generateTestOrderCode('E2E_PRP_');
    const codeDelivering = fixtures.generateTestOrderCode('E2E_DLV_');
    const codeCancelled = fixtures.generateTestOrderCode('E2E_CNC_');

    // Only codeCompleted (500,000 VND) should count. Others sum to 1,500,000 VND.
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES 
        (?, ?, 1, 'Recip', '0909', '123 St', 500000, 0, 500000, 'cod', 'paid', 'completed', 0, 0, 0, NOW()),
        (?, ?, 1, 'Recip', '0909', '123 St', 300000, 0, 300000, 'cod', 'pending', 'placed', 0, 0, 0, NOW()),
        (?, ?, 1, 'Recip', '0909', '123 St', 400000, 0, 400000, 'cod', 'pending', 'preparing', 0, 0, 0, NOW()),
        (?, ?, 1, 'Recip', '0909', '123 St', 500000, 0, 500000, 'cod', 'pending', 'delivering', 0, 0, 0, NOW()),
        (?, ?, 1, 'Recip', '0909', '123 St', 300000, 0, 300000, 'cod', 'failed', 'cancelled', 0, 0, 0, NOW())
    `, [
      codeCompleted, testUserId,
      codePlaced, testUserId,
      codePreparing, testUserId,
      codeDelivering, testUserId,
      codeCancelled, testUserId
    ]);

    const res = syncUserTierViaPhp(testUserId);
    assert.equal(res.total_spent, 500000, 'Total spent must only include the completed order (500,000 VND)');

    const userInDb = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userInDb.total_spent, 500000, 'DB users.total_spent must ignore non-completed orders');
  });

  it('F3-03: Correctly computes and updates member_tier from backfilled total_spent', async () => {
    // Seed orders totaling 3,200,000 VNĐ -> qualifies for GOLD tier (>= 3,000,000 VNĐ)
    const code1 = fixtures.generateTestOrderCode('E2E_GD1_');
    const code2 = fixtures.generateTestOrderCode('E2E_GD2_');

    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES 
        (?, ?, 1, 'Recip', '0909', '123 St', 2000000, 0, 2000000, 'cod', 'paid', 'completed', 0, 0, 0, NOW()),
        (?, ?, 1, 'Recip', '0909', '123 St', 1200000, 0, 1200000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [code1, testUserId, code2, testUserId]);

    const res = syncUserTierViaPhp(testUserId);
    assert.equal(res.tier, fixtures.TIERS.GOLD, '3,200,000 VNĐ in completed orders must result in GOLD tier');
    assert.equal(res.upgraded, true, 'User should be marked as upgraded from BRONZE to GOLD');

    const userInDb = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userInDb.member_tier, fixtures.TIERS.GOLD, 'users.member_tier in DB must be GOLD');
  });

  it('F3-04: Historical backfill preserves points_balance at 0 without retroactive awarding', async () => {
    const code = fixtures.generateTestOrderCode('E2E_PTS_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'Recip', '0909', '123 St', 1500000, 0, 1500000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [code, testUserId]);

    // Backfill sync
    syncUserTierViaPhp(testUserId);

    const userInDb = dbHelper.getUserLoyalty(testUserId);
    assert.equal(userInDb.points_balance, 0, 'points_balance must remain 0 after historical backfill');

    // Ledger transactions should NOT be retroactively created
    const txs = dbHelper.getTransactions(testUserId);
    assert.equal(txs.length, 0, 'No point transactions should be back-created for pre-loyalty orders');
  });

  it('F3-05: Backfill operation is strictly idempotent across repeated executions', async () => {
    const code = fixtures.generateTestOrderCode('E2E_IDM_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'Recip', '0909', '123 St', 5500000, 0, 5500000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [code, testUserId]);

    // Run 1
    const run1 = syncUserTierViaPhp(testUserId);
    assert.equal(run1.total_spent, 5500000);
    assert.equal(run1.tier, fixtures.TIERS.DIAMOND);
    assert.equal(run1.upgraded, true);

    // Run 2 (immediately after)
    const run2 = syncUserTierViaPhp(testUserId);
    assert.equal(run2.total_spent, 5500000, 'Second run must not double count spending');
    assert.equal(run2.tier, fixtures.TIERS.DIAMOND, 'Second run tier must remain DIAMOND');
    assert.equal(run2.upgraded, false, 'Second run upgraded flag must be false');

    // Run 3
    const run3 = syncUserTierViaPhp(testUserId);
    assert.equal(run3.total_spent, 5500000, 'Third run must produce identical total_spent');
    assert.equal(run3.tier, fixtures.TIERS.DIAMOND, 'Third run must produce identical tier');

    const finalUser = dbHelper.getUserLoyalty(testUserId);
    assert.equal(finalUser.total_spent, 5500000);
    assert.equal(finalUser.member_tier, fixtures.TIERS.DIAMOND);
  });

});
