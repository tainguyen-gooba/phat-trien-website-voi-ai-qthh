/**
 * f02-loyalty-engine.test.js - Tier 1: F2 Core Loyalty Engine & Tier Calculation
 *
 * Tests:
 * 1. Default BRONZE tier assigned for total_spent < 1,000,000 VNĐ (0, 500k, 999k)
 * 2. Automatic SILVER tier classification at >= 1,000,000 VNĐ and < 3,000,000 VNĐ
 * 3. Automatic GOLD tier classification at >= 3,000,000 VNĐ and < 5,000,000 VNĐ
 * 4. Automatic DIAMOND tier classification at >= 5,000,000 VNĐ (5M, 10M)
 * 5. Points earning multipliers (Bronze 1.0, Silver 1.5, Gold 2.0, Diamond 3.0) with flooring rule
 * 6. Atomic transaction safety & ledger integrity in points awarding
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

/**
 * Executes a PHP snippet that requires helpers/loyalty.php and returns parsed JSON.
 */
function runLoyaltyPhp(code) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    ${code}
  `;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return JSON.parse(output.trim());
}

describe('Tier 1: F2 - Core Loyalty Engine & Tier Calculation', () => {

  it('F2-01: Default BRONZE tier is assigned for total_spent < 1,000,000 VNĐ', async () => {
    const res = runLoyaltyPhp(`
      $results = [
        'spent_0'      => calculateTier(0),
        'spent_250k'   => calculateTier(250000),
        'spent_500k'   => calculateTier(500000),
        'spent_999999' => calculateTier(999999.99),
        'negative'     => calculateTier(-10000)
      ];
      echo json_encode($results);
    `);

    assert.equal(res.spent_0, fixtures.TIERS.BRONZE, '0 VNĐ spent should be BRONZE');
    assert.equal(res.spent_250k, fixtures.TIERS.BRONZE, '250k VNĐ spent should be BRONZE');
    assert.equal(res.spent_500k, fixtures.TIERS.BRONZE, '500k VNĐ spent should be BRONZE');
    assert.equal(res.spent_999999, fixtures.TIERS.BRONZE, '999,999.99 VNĐ spent should still be BRONZE');
    assert.equal(res.negative, fixtures.TIERS.BRONZE, 'Negative spent should default to BRONZE');
  });

  it('F2-02: Automatic SILVER tier classification at >= 1,000,000 VNĐ and < 3,000,000 VNĐ', async () => {
    const res = runLoyaltyPhp(`
      $results = [
        'spent_1M'    => calculateTier(1000000),
        'spent_1_5M'  => calculateTier(1500000),
        'spent_2_99M' => calculateTier(2999999.99)
      ];
      echo json_encode($results);
    `);

    assert.equal(res.spent_1M, fixtures.TIERS.SILVER, '1,000,000 VNĐ spent must qualify for SILVER');
    assert.equal(res.spent_1_5M, fixtures.TIERS.SILVER, '1,500,000 VNĐ spent must qualify for SILVER');
    assert.equal(res.spent_2_99M, fixtures.TIERS.SILVER, '2,999,999.99 VNĐ spent must qualify for SILVER');
  });

  it('F2-03: Automatic GOLD tier classification at >= 3,000,000 VNĐ and < 5,000,000 VNĐ', async () => {
    const res = runLoyaltyPhp(`
      $results = [
        'spent_3M'    => calculateTier(3000000),
        'spent_4M'    => calculateTier(4000000),
        'spent_4_99M' => calculateTier(4999999.99)
      ];
      echo json_encode($results);
    `);

    assert.equal(res.spent_3M, fixtures.TIERS.GOLD, '3,000,000 VNĐ spent must qualify for GOLD');
    assert.equal(res.spent_4M, fixtures.TIERS.GOLD, '4,000,000 VNĐ spent must qualify for GOLD');
    assert.equal(res.spent_4_99M, fixtures.TIERS.GOLD, '4,999,999.99 VNĐ spent must qualify for GOLD');
  });

  it('F2-04: Automatic DIAMOND tier classification at >= 5,000,000 VNĐ', async () => {
    const res = runLoyaltyPhp(`
      $results = [
        'spent_5M'   => calculateTier(5000000),
        'spent_7_5M' => calculateTier(7500000),
        'spent_50M'  => calculateTier(50000000)
      ];
      echo json_encode($results);
    `);

    assert.equal(res.spent_5M, fixtures.TIERS.DIAMOND, '5,000,000 VNĐ spent must qualify for DIAMOND');
    assert.equal(res.spent_7_5M, fixtures.TIERS.DIAMOND, '7,500,000 VNĐ spent must qualify for DIAMOND');
    assert.equal(res.spent_50M, fixtures.TIERS.DIAMOND, '50,000,000 VNĐ spent must qualify for DIAMOND');
  });

  it('F2-05: Points earning formula respects tier multipliers (1.0, 1.5, 2.0, 3.0) and floored spending step', async () => {
    const res = runLoyaltyPhp(`
      $results = [
        // 100,000 VND order (10 steps)
        'bronze_100k'  => calculateEarnPoints(100000, 'BRONZE'),
        'silver_100k'  => calculateEarnPoints(100000, 'SILVER'),
        'gold_100k'    => calculateEarnPoints(100000, 'GOLD'),
        'diamond_100k' => calculateEarnPoints(100000, 'DIAMOND'),

        // 95,000 VND order (9 steps)
        'bronze_95k'   => calculateEarnPoints(95000, 'BRONZE'),
        'silver_95k'   => calculateEarnPoints(95000, 'SILVER'),
        'gold_95k'     => calculateEarnPoints(95000, 'GOLD'),
        'diamond_95k'  => calculateEarnPoints(95000, 'DIAMOND'),

        // Sub-10,000 VND order (0 steps)
        'sub10k'       => calculateEarnPoints(9999, 'DIAMOND'),
        'zero'         => calculateEarnPoints(0, 'GOLD')
      ];
      echo json_encode($results);
    `);

    // 100,000 VND = 10 units of 10,000 VND
    assert.equal(res.bronze_100k, 10, 'Bronze earning ratio 1.0 -> 10 pts for 100k');
    assert.equal(res.silver_100k, 15, 'Silver earning ratio 1.5 -> 15 pts for 100k');
    assert.equal(res.gold_100k, 20, 'Gold earning ratio 2.0 -> 20 pts for 100k');
    assert.equal(res.diamond_100k, 30, 'Diamond earning ratio 3.0 -> 30 pts for 100k');

    // 95,000 VND = 9 units (remainder 5,000 floored out)
    assert.equal(res.bronze_95k, 9, 'Bronze 95k -> floor(9 * 1.0) = 9 pts');
    assert.equal(res.silver_95k, 13, 'Silver 95k -> floor(9 * 1.5) = 13 pts (13.5 floored)');
    assert.equal(res.gold_95k, 18, 'Gold 95k -> floor(9 * 2.0) = 18 pts');
    assert.equal(res.diamond_95k, 27, 'Diamond 95k -> floor(9 * 3.0) = 27 pts');

    // Zero & sub-10k orders earn 0
    assert.equal(res.sub10k, 0, 'Sub-10,000 VND order must earn 0 pts');
    assert.equal(res.zero, 0, '0 VND order must earn 0 pts');
  });

  it('F2-06: Atomic transaction safety guarantees ledger and balance consistency in points awarding', async () => {
    // 1. Create isolated test user & completed order
    const testEmail = fixtures.generateTestEmail('e2e_f02');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'E2E F02 User', ?, '0901234567', 'hash', 'active', 'BRONZE', 0, 0, NOW())
    `, [testEmail]);
    const userRow = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    const testUserId = userRow.id;

    const testOrderCode = fixtures.generateTestOrderCode('E2E_F02_');
    dbHelper.query(`
      INSERT INTO orders (order_code, customer_id, restaurant_id, recipient_name, recipient_phone, delivery_address, subtotal, shipping_fee, total_amount, payment_method, payment_status, order_status, points_earned, points_used, points_discount, created_at)
      VALUES (?, ?, 1, 'E2E F02 User', '0901234567', '123 Test St', 200000, 0, 200000, 'cod', 'paid', 'completed', 0, 0, 0, NOW())
    `, [testOrderCode, testUserId]);
    const orderRow = dbHelper.query('SELECT id FROM orders WHERE order_code = ?', [testOrderCode])[0];
    const testOrderId = orderRow.id;

    try {
      // 2. Execute processOrderEarnPoints
      const awardRes = runLoyaltyPhp(`
        $res = processOrderEarnPoints(${testOrderId});
        echo json_encode($res);
      `);

      assert.ok(awardRes.success, 'processOrderEarnPoints should succeed');
      assert.equal(awardRes.points_earned, 20, '200,000 VND on Bronze tier should earn 20 pts');

      // 3. Verify user in DB updated atomically
      const updatedUser = dbHelper.getUserLoyalty(testUserId);
      assert.equal(updatedUser.points_balance, 20, 'User points_balance must be updated to 20');
      assert.equal(updatedUser.total_spent, 200000, 'User total_spent must be updated to 200,000');

      // 4. Verify point_transactions ledger record created
      const txs = dbHelper.getTransactions(testUserId);
      assert.equal(txs.length, 1, 'Exactly one transaction should be logged');
      assert.equal(txs[0].transaction_type, fixtures.TRANSACTION_TYPES.EARN);
      assert.equal(Number(txs[0].points), 20);
      assert.equal(Number(txs[0].balance_after), 20);

      // 5. Test idempotency: Calling processOrderEarnPoints again must NOT double-award
      const secondCallRes = runLoyaltyPhp(`
        $res = processOrderEarnPoints(${testOrderId});
        echo json_encode($res);
      `);
      assert.equal(secondCallRes.already_awarded, true, 'Second call must be flagged as already_awarded');

      const userAfterSecond = dbHelper.getUserLoyalty(testUserId);
      assert.equal(userAfterSecond.points_balance, 20, 'Points balance must remain unchanged on duplicate call');
      const txsAfterSecond = dbHelper.getTransactions(testUserId);
      assert.equal(txsAfterSecond.length, 1, 'Transaction count must remain 1 (no duplicate log)');

    } finally {
      // Cleanup
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM orders WHERE id = ?', [testOrderId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
    }
  });

});
