/**
 * f01-schema-migration.test.js - Tier 1: F1 DB Migration & Schema Extension
 *
 * Tests:
 * 1. Verify `membership_tiers` table structure, columns, and 4 default tier seeds (BRONZE, SILVER, GOLD, DIAMOND)
 * 2. Verify `point_transactions` ledger table schema, transaction types enum, and foreign key relations
 * 3. Verify `loyalty_settings` key-value table schema and initial standard configurations
 * 4. Verify `users` table extended loyalty columns (member_tier, points_balance, total_spent) and indices
 * 5. Verify `orders` table extended loyalty columns (points_earned, points_used, points_discount) and index
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const dbHelper = require('../harness/db-helper');

describe('Tier 1: F1 - Database Schema Migration & Extension', () => {

  it('F1-01: membership_tiers table exists with required columns and 4 default tier definitions', async () => {
    // 1. Inspect table columns
    const cols = dbHelper.query('DESCRIBE membership_tiers');
    assert.ok(Array.isArray(cols) && cols.length > 0, 'membership_tiers table must exist');
    const colNames = cols.map(c => c.Field.toLowerCase());

    const expectedCols = ['id', 'tier_code', 'tier_name', 'min_spent', 'point_ratio', 'badge_icon', 'badge_color', 'benefits_json'];
    for (const ec of expectedCols) {
      assert.ok(colNames.includes(ec), `membership_tiers must contain column '${ec}'`);
    }

    // 2. Query seeded tiers
    const tiers = dbHelper.query('SELECT tier_code, min_spent, point_ratio FROM membership_tiers ORDER BY min_spent ASC');
    assert.equal(tiers.length, 4, 'Must contain exactly 4 tier rows (BRONZE, SILVER, GOLD, DIAMOND)');

    const tierMap = {};
    for (const t of tiers) {
      tierMap[t.tier_code.toUpperCase()] = {
        min_spent: Number(t.min_spent),
        point_ratio: Number(t.point_ratio)
      };
    }

    assert.ok(tierMap.BRONZE, 'BRONZE tier must be present');
    assert.equal(tierMap.BRONZE.min_spent, 0, 'BRONZE threshold must be 0 VND');
    assert.equal(tierMap.BRONZE.point_ratio, 1.0, 'BRONZE ratio must be 1.0');

    assert.ok(tierMap.SILVER, 'SILVER tier must be present');
    assert.equal(tierMap.SILVER.min_spent, 1000000, 'SILVER threshold must be 1,000,000 VND');
    assert.equal(tierMap.SILVER.point_ratio, 1.5, 'SILVER ratio must be 1.5');

    assert.ok(tierMap.GOLD, 'GOLD tier must be present');
    assert.equal(tierMap.GOLD.min_spent, 3000000, 'GOLD threshold must be 3,000,000 VND');
    assert.equal(tierMap.GOLD.point_ratio, 2.0, 'GOLD ratio must be 2.0');

    assert.ok(tierMap.DIAMOND, 'DIAMOND tier must be present');
    assert.equal(tierMap.DIAMOND.min_spent, 5000000, 'DIAMOND threshold must be 5,000,000 VND');
    assert.equal(tierMap.DIAMOND.point_ratio, 3.0, 'DIAMOND ratio must be 3.0');
  });

  it('F1-02: point_transactions table exists with append-only ledger columns and ENUM transaction types', async () => {
    const cols = dbHelper.query('DESCRIBE point_transactions');
    assert.ok(Array.isArray(cols) && cols.length > 0, 'point_transactions table must exist');
    const colNames = cols.map(c => c.Field.toLowerCase());

    const expectedCols = ['id', 'user_id', 'order_id', 'points', 'balance_after', 'transaction_type', 'description', 'created_at'];
    for (const ec of expectedCols) {
      assert.ok(colNames.includes(ec), `point_transactions must contain column '${ec}'`);
    }

    // Verify transaction_type enum definition
    const typeCol = cols.find(c => c.Field.toLowerCase() === 'transaction_type');
    assert.ok(typeCol, 'transaction_type column must exist');
    const colType = typeCol.Type.toUpperCase();
    const requiredTypes = ['EARN', 'REDEEM_CHECKOUT', 'REDEEM_VOUCHER', 'REFUND_REVOKE', 'REFUND_RESTORE', 'ADMIN_ADJUST'];
    for (const rt of requiredTypes) {
      assert.ok(colType.includes(rt), `transaction_type ENUM must include '${rt}'`);
    }

    // Verify primary key & indexes
    const indices = dbHelper.query('SHOW INDEX FROM point_transactions');
    const indexNames = indices.map(idx => idx.Key_name);
    assert.ok(indexNames.includes('PRIMARY'), 'point_transactions must have a PRIMARY KEY');
    assert.ok(indexNames.some(name => name.toLowerCase().includes('user')), 'point_transactions must index user_id');
  });

  it('F1-03: loyalty_settings table exists with key-value schema and required default settings', async () => {
    const cols = dbHelper.query('DESCRIBE loyalty_settings');
    assert.ok(Array.isArray(cols) && cols.length > 0, 'loyalty_settings table must exist');
    const colNames = cols.map(c => c.Field.toLowerCase());

    assert.ok(colNames.includes('setting_key'), 'loyalty_settings must have setting_key');
    assert.ok(colNames.includes('setting_value'), 'loyalty_settings must have setting_value');

    // Query setting records
    const rows = dbHelper.query('SELECT setting_key, setting_value FROM loyalty_settings');
    const settingsMap = {};
    for (const r of rows) {
      settingsMap[r.setting_key] = r.setting_value;
    }

    assert.ok(settingsMap['point_to_vnd_rate'] !== undefined, 'Setting point_to_vnd_rate must exist');
    assert.equal(Number(settingsMap['point_to_vnd_rate']), 100, 'point_to_vnd_rate default must be 100');

    assert.ok(settingsMap['spending_step_vnd'] !== undefined, 'Setting spending_step_vnd must exist');
    assert.equal(Number(settingsMap['spending_step_vnd']), 10000, 'spending_step_vnd default must be 10,000');

    assert.ok(settingsMap['min_redeem_points'] !== undefined, 'Setting min_redeem_points must exist');
    assert.equal(Number(settingsMap['min_redeem_points']), 100, 'min_redeem_points default must be 100');

    assert.ok(settingsMap['max_redeem_percentage'] !== undefined, 'Setting max_redeem_percentage must exist');
    assert.equal(Number(settingsMap['max_redeem_percentage']), 100, 'max_redeem_percentage default must be 100');
  });

  it('F1-04: users table extended with member_tier, points_balance, total_spent and relevant indexes', async () => {
    const cols = dbHelper.query('DESCRIBE users');
    const colMap = {};
    for (const c of cols) {
      colMap[c.Field.toLowerCase()] = c;
    }

    assert.ok(colMap['member_tier'], "users must contain 'member_tier' column");
    assert.ok(colMap['member_tier'].Default === 'BRONZE' || colMap['member_tier'].Type.includes('varchar'), 'member_tier default should be BRONZE');

    assert.ok(colMap['points_balance'], "users must contain 'points_balance' column");
    assert.equal(Number(colMap['points_balance'].Default || 0), 0, 'points_balance default should be 0');

    assert.ok(colMap['total_spent'], "users must contain 'total_spent' column");
    assert.equal(Number(colMap['total_spent'].Default || 0), 0, 'total_spent default should be 0.00');

    // Verify indexes on users
    const indices = dbHelper.query('SHOW INDEX FROM users');
    const indexedCols = indices.map(idx => idx.Column_name.toLowerCase());
    assert.ok(indexedCols.includes('member_tier'), 'users table should index member_tier');
    assert.ok(indexedCols.includes('total_spent'), 'users table should index total_spent');
  });

  it('F1-05: orders table extended with points_earned, points_used, points_discount columns and index', async () => {
    const cols = dbHelper.query('DESCRIBE orders');
    const colMap = {};
    for (const c of cols) {
      colMap[c.Field.toLowerCase()] = c;
    }

    assert.ok(colMap['points_earned'], "orders must contain 'points_earned' column");
    assert.equal(Number(colMap['points_earned'].Default || 0), 0, 'points_earned default should be 0');

    assert.ok(colMap['points_used'], "orders must contain 'points_used' column");
    assert.equal(Number(colMap['points_used'].Default || 0), 0, 'points_used default should be 0');

    assert.ok(colMap['points_discount'], "orders must contain 'points_discount' column");
    assert.equal(Number(colMap['points_discount'].Default || 0), 0, 'points_discount default should be 0.00');

    // Verify index on points_used
    const indices = dbHelper.query('SHOW INDEX FROM orders');
    const indexedCols = indices.map(idx => idx.Column_name.toLowerCase());
    assert.ok(indexedCols.includes('points_used'), 'orders table should index points_used');
  });

});
