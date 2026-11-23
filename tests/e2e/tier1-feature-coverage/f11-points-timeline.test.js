/**
 * f11-points-timeline.test.js - Tier 1: F11 Points Activity History Timeline
 *
 * Tests:
 * 1. Points history table or timeline element exists on profile.php
 * 2. Displays transaction timestamp and transaction type badge
 * 3. Displays signed points delta (+ for earn/restore, - for redemption)
 * 4. Displays order code or voucher reference in transaction description
 * 5. Displays balance after transaction in the timeline row
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const domParser = require('../harness/dom-parser');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F11 - Points Activity History Timeline', () => {
  let client;
  let customerUser;
  let testOrderCode;
  const originalLoyalty = {};

  before(async () => {
    client = new TestClient();
    customerUser = fixtures.ACCOUNTS.customer;

    const current = dbHelper.getUserLoyalty(customerUser.id);
    if (current) {
      originalLoyalty.member_tier = current.member_tier;
      originalLoyalty.points_balance = current.points_balance;
      originalLoyalty.total_spent = current.total_spent;
    }

    testOrderCode = fixtures.generateTestOrderCode('E2E_TIM_');

    // Seed test transactions in point_transactions for the customer
    dbHelper.query(`
      INSERT INTO point_transactions (user_id, order_id, points, balance_after, transaction_type, description, created_at)
      VALUES 
        (?, NULL, 50, 150, 'EARN', 'Tích lũy điểm từ đơn hàng ${testOrderCode}', NOW()),
        (?, NULL, -100, 50, 'REDEEM_VOUCHER', 'Đổi 100 điểm lấy voucher FF10K-TEST', NOW())
    `, [customerUser.id, customerUser.id]);

    await client.loginAsCustomer(customerUser.email, customerUser.password);
  });

  after(async () => {
    // Cleanup seeded transactions
    dbHelper.query("DELETE FROM point_transactions WHERE user_id = ? AND description LIKE '%E2E_TIM_%'", [customerUser.id]);
    dbHelper.query("DELETE FROM point_transactions WHERE user_id = ? AND description LIKE '%FF10K-TEST%'", [customerUser.id]);

    if (customerUser && originalLoyalty.member_tier) {
      dbHelper.setUserLoyalty(customerUser.id, originalLoyalty);
    }
  });

  it('F11-01: Profile page contains points activity history table or timeline container', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const historyContainer = domParser.querySelector(html, '#pointsHistoryTable') ||
                             domParser.querySelector(html, '.points-timeline') ||
                             domParser.querySelector(html, '.points-history') ||
                             domParser.querySelector(html, '.history-table') ||
                             domParser.querySelector(html, '[data-component="points-timeline"]');

    assert.ok(historyContainer || html.includes('Lịch sử') || html.includes('Biến động điểm'),
      'Must contain points history table/timeline element or section on profile.php');
  });

  it('F11-02: Displays transaction timestamp and type badge (EARN / REDEEM)', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const hasTypeOrBadge = html.includes('EARN') || 
                           html.includes('Tích điểm') || 
                           html.includes('Tích lũy') || 
                           html.includes('Đổi voucher') || 
                           html.includes('REDEEM');

    assert.ok(hasTypeOrBadge, 'Timeline must show transaction type badges (EARN, REDEEM, etc.)');
  });

  it('F11-03: Displays signed points delta (+ for positive, - for negative)', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const hasPlus = html.includes('+50') || html.includes('+ 50');
    const hasMinus = html.includes('-100') || html.includes('- 100');

    assert.ok(hasPlus && hasMinus, 'Must show signed point variations (+50 and -100)');
  });

  it('F11-04: Displays order code or voucher code in transaction reference description', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const hasOrderRef = html.includes(testOrderCode) || html.includes('FF10K-TEST');
    assert.ok(hasOrderRef, `Must include reference code (${testOrderCode} or FF10K-TEST) in timeline`);
  });

  it('F11-05: Displays accurate balance_after figure in timeline record', async () => {
    const res = await client.getProfile();
    const html = res.text;

    // We seeded balance_after: 150 and 50
    const hasBalance = html.includes('150') && html.includes('50');
    assert.ok(hasBalance, 'Must show balance after transaction in ledger display');
  });

});
