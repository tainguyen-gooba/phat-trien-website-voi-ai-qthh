/**
 * b02-points-calculation-edges.test.js - Tier 2 Boundary Tests: Points Calculation Edges
 * 
 * Verifies edge cases and precision rules for points calculation:
 * - B2-01: Sub-step order (9,999 VNĐ) awards 0 points across all tiers
 * - B2-02: Fractional point flooring: 25,000 VNĐ at Bronze awards exactly 2 points
 * - B2-03: 10,000 VNĐ at Silver (1.5 ratio) floors 1.5 to 1 point
 * - B2-04: Zero-value order (0 VNĐ) awards 0 points
 * - B2-05: High-value order (50,000,000 VNĐ) calculates accurately without overflow
 * - B2-06: Ratio precision: boundary amounts evaluate deterministically
 *
 * Source: ORIGINAL_REQUEST.md §R2, §R3, PROJECT.md §F2, §F5, TEST_INFRA.md §Tier 2
 */

const { describe, it, before, after, beforeEach } = require('node:test');
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

describe('Tier 2 Boundaries: b02-points-calculation-edges (6 tests)', () => {
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

  /**
   * Helper to create a fresh test customer in DB
   */
  function createTestCustomer(tier = 'BRONZE') {
    const email = 'e2e_pt_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7) + '@fastfood.test';
    const phone = generateUniquePhone();
    const passwordHash = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
       VALUES (2, 'Points Edge User', ?, ?, ?, ?, 0, 0.00, 'active', NOW())`,
      [email, phone, passwordHash, tier]
    );
    const rows = dbHelper.query('SELECT id, email, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [email]);
    assert.ok(rows && rows.length > 0, 'User should be created');
    return rows[0];
  }

  /**
   * Helper to create completed order and run processOrderEarnPoints
   */
  function createAndAwardOrder(user, amount, tier = 'BRONZE') {
    dbHelper.setUserLoyalty(user.id, { member_tier: tier, points_balance: 0 });
    const orderCode = 'E2E_ORD_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, points_earned, placed_at, created_at)
       VALUES (?, ?, 1, ?, 0.00, 0.00, ?, 'cod', 'completed', 'completed', 0, NOW(), NOW())`,
      [orderCode, user.id, amount, amount]
    );
    const rows = dbHelper.query('SELECT id, order_code FROM orders WHERE order_code = ? LIMIT 1', [orderCode]);
    assert.ok(rows && rows.length > 0, `Order ${orderCode} should be created in DB`);
    const orderRow = rows[0];
    const phpRes = callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${orderRow.id}));`);
    return {
      orderId: orderRow.id,
      orderCode,
      result: JSON.parse(phpRes)
    };
  }

  it('B2-01: Sub-step order (9,999 VNĐ) awards 0 points across all tiers', () => {
    const amount = 9999;
    // Expected points for sub-step amount is strictly 0
    assert.equal(calculateExpectedPoints(amount, TIERS.BRONZE), 0);
    assert.equal(calculateExpectedPoints(amount, TIERS.SILVER), 0);
    assert.equal(calculateExpectedPoints(amount, TIERS.GOLD), 0);
    assert.equal(calculateExpectedPoints(amount, TIERS.DIAMOND), 0);

    const bronzePts = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'BRONZE'));`));
    const diamondPts = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'DIAMOND'));`));
    assert.equal(bronzePts, 0, 'Bronze 9,999 VNĐ must award 0 points');
    assert.equal(diamondPts, 0, 'Diamond 9,999 VNĐ must award 0 points');

    // DB order test
    const user = createTestCustomer('BRONZE');
    const { orderCode, result } = createAndAwardOrder(user, amount, 'BRONZE');
    assert.equal(result.points_earned, 0, 'Order completion for 9,999 VNĐ must award 0 points');
    const orderInDb = dbHelper.getOrderLoyalty(orderCode);
    assert.equal(orderInDb.points_earned, 0);
  });

  it('B2-02: Fractional point flooring: 25,000 VNĐ at Bronze awards exactly 2 points', () => {
    const amount = 25000;
    // 25,000 / 10,000 = 2 steps. 2 * 1.0 = 2 points.
    assert.equal(calculateExpectedPoints(amount, TIERS.BRONZE), 2);

    const pts = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'BRONZE'));`));
    assert.equal(pts, 2, `25,000 VNĐ at Bronze must yield exactly 2 points, got: ${pts}`);

    const user = createTestCustomer('BRONZE');
    const { orderCode, result } = createAndAwardOrder(user, amount, 'BRONZE');
    assert.equal(result.points_earned, 2);
    const orderInDb = dbHelper.getOrderLoyalty(orderCode);
    assert.equal(orderInDb.points_earned, 2);
  });

  it('B2-03: 10,000 VNĐ at Silver (1.5 ratio) floors 1.5 to 1 point', () => {
    const amount = 10000;
    // 10,000 / 10,000 = 1 step. 1 * 1.5 = 1.5 -> floored to 1 point.
    assert.equal(calculateExpectedPoints(amount, TIERS.SILVER), 1);

    const pts = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'SILVER'));`));
    assert.equal(pts, 1, `10,000 VNĐ at Silver (1.5 ratio) must floor to 1 point, got: ${pts}`);

    const user = createTestCustomer('SILVER');
    const { orderCode, result } = createAndAwardOrder(user, amount, 'SILVER');
    assert.equal(result.points_earned, 1);
    const orderInDb = dbHelper.getOrderLoyalty(orderCode);
    assert.equal(orderInDb.points_earned, 1);
  });

  it('B2-04: Zero-value order (0 VNĐ) awards 0 points', () => {
    const amount = 0;
    assert.equal(calculateExpectedPoints(amount, TIERS.BRONZE), 0);
    assert.equal(calculateExpectedPoints(amount, TIERS.DIAMOND), 0);

    const ptsBronze = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'BRONZE'));`));
    const ptsDiamond = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'DIAMOND'));`));
    assert.equal(ptsBronze, 0);
    assert.equal(ptsDiamond, 0);

    const user = createTestCustomer('DIAMOND');
    const { orderCode, result } = createAndAwardOrder(user, amount, 'DIAMOND');
    assert.equal(result.points_earned, 0);
    const orderInDb = dbHelper.getOrderLoyalty(orderCode);
    assert.equal(orderInDb.points_earned, 0);
  });

  it('B2-05: High-value order (50,000,000 VNĐ) calculates accurately without overflow', () => {
    const amount = 50000000;
    // 50,000,000 / 10,000 = 5,000 steps.
    // At Diamond (3.0): 5,000 * 3 = 15,000 points.
    assert.equal(calculateExpectedPoints(amount, TIERS.DIAMOND), 15000);

    const ptsDiamond = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(${amount}, 'DIAMOND'));`));
    assert.equal(ptsDiamond, 15000, `50,000,000 VNĐ at Diamond must yield 15,000 points, got: ${ptsDiamond}`);

    const user = createTestCustomer('DIAMOND');
    const { orderCode, result } = createAndAwardOrder(user, amount, 'DIAMOND');
    assert.equal(result.points_earned, 15000);
    const userInDb = dbHelper.getUserLoyalty(user.id);
    assert.equal(userInDb.points_balance, 15000);
  });

  it('B2-06: Ratio precision: boundary amounts evaluate deterministically', () => {
    // 19,999 VNĐ at Silver: 1 step * 1.5 = 1.5 -> floor to 1 pt
    const pts19k = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(19999, 'SILVER'));`));
    assert.equal(pts19k, 1);

    // 20,000 VNĐ at Silver: 2 steps * 1.5 = 3.0 -> 3 pts
    const pts20k = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(20000, 'SILVER'));`));
    assert.equal(pts20k, 3);

    // 35,000 VNĐ at Silver: 3 steps * 1.5 = 4.5 -> floor to 4 pts
    const pts35k = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(35000, 'SILVER'));`));
    assert.equal(pts35k, 4);

    // 35,000 VNĐ at Gold (2.0): 3 steps * 2.0 = 6 pts
    const pts35kGold = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateEarnPoints(35000, 'GOLD'));`));
    assert.equal(pts35kGold, 6);
  });
});
