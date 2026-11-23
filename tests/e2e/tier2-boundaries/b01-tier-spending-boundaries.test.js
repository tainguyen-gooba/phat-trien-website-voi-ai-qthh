/**
 * b01-tier-spending-boundaries.test.js - Tier 2 Boundary Tests: Member Tier Spending Thresholds
 * 
 * Verifies exact boundary values for membership tier upgrades:
 * - 0 VNĐ -> BRONZE (default starting point)
 * - 999,999 VNĐ (1 VNĐ below Silver threshold) -> BRONZE
 * - 1,000,000 VNĐ (exact Silver threshold) -> SILVER
 * - 2,999,999 VNĐ (1 VNĐ below Gold threshold) -> SILVER
 * - 3,000,000 VNĐ (exact Gold threshold) -> GOLD
 * - 4,999,999 VNĐ (1 VNĐ below Diamond threshold) -> GOLD
 * - 5,000,000 VNĐ (exact Diamond threshold) -> DIAMOND
 *
 * Source: ORIGINAL_REQUEST.md §R2, PROJECT.md §F2, TEST_INFRA.md §Tier 2
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
  calculateExpectedTier
} = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

/**
 * Executes a PHP snippet against helpers/loyalty.php via base64 eval
 */
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

describe('Tier 2 Boundaries: b01-tier-spending-boundaries (40 total, 7 in this suite)', () => {
  let testUser;
  let testOrderCode;

  function generateUniquePhone() {
    while (true) {
      const phone = '03' + Math.floor(10000000 + Math.random() * 90000000);
      const existing = dbHelper.query('SELECT id FROM users WHERE phone = ? LIMIT 1', [phone]);
      if (!existing || existing.length === 0) return phone;
    }
  }

  before(() => {
    dbHelper.cleanupTestData();
    const email = generateTestEmail();
    const phone = generateUniquePhone();
    const passwordHash = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
       VALUES (2, 'Tier Boundary Test User', ?, ?, ?, 'BRONZE', 0, 0.00, 'active', NOW())`,
      [email, phone, passwordHash]
    );
    const rows = dbHelper.query('SELECT id, email, member_tier, total_spent FROM users WHERE email = ? LIMIT 1', [email]);
    testUser = rows[0];

    testOrderCode = generateTestOrderCode();
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, placed_at, created_at)
       VALUES (?, ?, 1, 0.00, 0.00, 0.00, 0.00, 'cod', 'completed', 'completed', NOW(), NOW())`,
      [testOrderCode, testUser.id]
    );
  });

  after(() => {
    dbHelper.cleanupTestData();
  });

  /**
   * Helper to set spending in DB via completed order and sync tier
   */
  function applySpendingAndSync(amount) {
    // Ensure test user exists in DB
    const users = dbHelper.query('SELECT id FROM users WHERE id = ?', [testUser.id]);
    if (!users || users.length === 0) {
      const phone = '03' + Math.floor(10000000 + Math.random() * 90000000);
      dbHelper.query(
        `INSERT INTO users (id, role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
         VALUES (?, 2, 'Tier Boundary Test User', ?, ?, '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe', 'BRONZE', 0, 0.00, 'active', NOW())
         ON DUPLICATE KEY UPDATE status = 'active'`,
        [testUser.id, testUser.email, phone]
      );
    }

    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, placed_at, created_at)
       VALUES (?, ?, 1, ?, 0.00, 0.00, ?, 'cod', 'completed', 'completed', NOW(), NOW())
       ON DUPLICATE KEY UPDATE total_amount = VALUES(total_amount), subtotal = VALUES(subtotal), order_status = 'completed'`,
      [testOrderCode, testUser.id, amount, amount]
    );
    const phpRes = callLoyaltyPhp(`echo json_encode(syncUserTier(${testUser.id}));`);
    return JSON.parse(phpRes);
  }

  it('B1-01: Exact 0 VNĐ spending classifies as BRONZE tier', () => {
    const spending = 0;
    // 1. Authoritative fixtures formula
    assert.equal(calculateExpectedTier(spending), TIERS.BRONZE);

    // 2. Direct core calculation
    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'BRONZE', `Expected 0 VNĐ to evaluate to BRONZE, got: ${calcTier}`);

    // 3. Database synchronization check
    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'BRONZE');
    assert.equal(syncResult.total_spent, 0);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'BRONZE');
    assert.equal(userInDb.total_spent, 0);
  });

  it('B1-02: 999,999 VNĐ spending (1 VNĐ below Silver threshold) remains BRONZE tier', () => {
    const spending = 999999;
    assert.equal(calculateExpectedTier(spending), TIERS.BRONZE);

    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'BRONZE', `999,999 VNĐ must strictly remain BRONZE, got: ${calcTier}`);

    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'BRONZE');
    assert.equal(syncResult.total_spent, 999999);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'BRONZE');
    assert.equal(userInDb.total_spent, 999999);
  });

  it('B1-03: Exact 1,000,000 VNĐ spending reaches SILVER tier threshold', () => {
    const spending = 1000000;
    assert.equal(calculateExpectedTier(spending), TIERS.SILVER);

    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'SILVER', `1,000,000 VNĐ must promote customer to SILVER, got: ${calcTier}`);

    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'SILVER');
    assert.equal(syncResult.total_spent, 1000000);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'SILVER');
    assert.equal(userInDb.total_spent, 1000000);
  });

  it('B1-04: 2,999,999 VNĐ spending (1 VNĐ below Gold threshold) remains SILVER tier', () => {
    const spending = 2999999;
    assert.equal(calculateExpectedTier(spending), TIERS.SILVER);

    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'SILVER', `2,999,999 VNĐ must strictly remain SILVER, got: ${calcTier}`);

    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'SILVER');
    assert.equal(syncResult.total_spent, 2999999);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'SILVER');
    assert.equal(userInDb.total_spent, 2999999);
  });

  it('B1-05: Exact 3,000,000 VNĐ spending reaches GOLD tier threshold', () => {
    const spending = 3000000;
    assert.equal(calculateExpectedTier(spending), TIERS.GOLD);

    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'GOLD', `3,000,000 VNĐ must promote customer to GOLD, got: ${calcTier}`);

    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'GOLD');
    assert.equal(syncResult.total_spent, 3000000);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'GOLD');
    assert.equal(userInDb.total_spent, 3000000);
  });

  it('B1-06: 4,999,999 VNĐ spending (1 VNĐ below Diamond threshold) remains GOLD tier', () => {
    const spending = 4999999;
    assert.equal(calculateExpectedTier(spending), TIERS.GOLD);

    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'GOLD', `4,999,999 VNĐ must strictly remain GOLD, got: ${calcTier}`);

    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'GOLD');
    assert.equal(syncResult.total_spent, 4999999);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'GOLD');
    assert.equal(userInDb.total_spent, 4999999);
  });

  it('B1-07: Exact 5,000,000 VNĐ spending reaches DIAMOND tier threshold', () => {
    const spending = 5000000;
    assert.equal(calculateExpectedTier(spending), TIERS.DIAMOND);

    const calcTier = JSON.parse(callLoyaltyPhp(`echo json_encode(calculateTier(${spending}));`));
    assert.equal(calcTier, 'DIAMOND', `5,000,000 VNĐ must promote customer to DIAMOND, got: ${calcTier}`);

    const syncResult = applySpendingAndSync(spending);
    assert.equal(syncResult.tier, 'DIAMOND');
    assert.equal(syncResult.total_spent, 5000000);

    const userInDb = dbHelper.getUserLoyalty(testUser.id);
    assert.equal(userInDb.member_tier, 'DIAMOND');
    assert.equal(userInDb.total_spent, 5000000);
  });
});
