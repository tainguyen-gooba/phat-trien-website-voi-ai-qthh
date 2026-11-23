/**
 * f07-profile-vip-card.test.js - Tier 1: F7 Modern FastFood VIP Member Card & Stats
 *
 * Tests:
 * 1. VIP Card DOM container exists on profile.php (.vip-card, #vipCard, .membership-card, or [data-component="vip-card"])
 * 2. Displays current member tier badge and name (BRONZE/SILVER/GOLD/DIAMOND)
 * 3. Displays available points balance accurately
 * 4. Displays total spending formatted with Vietnamese currency notation (VNĐ / đ)
 * 5. Contains mobile-responsive viewport meta tag and container layout
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const domParser = require('../harness/dom-parser');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F7 - Modern FastFood VIP Member Card & Stats', () => {
  let client;
  let customerUser;
  const originalLoyalty = {};

  before(async () => {
    client = new TestClient();
    customerUser = fixtures.ACCOUNTS.customer;

    // Backup original customer loyalty state
    const current = dbHelper.getUserLoyalty(customerUser.id);
    if (current) {
      originalLoyalty.member_tier = current.member_tier;
      originalLoyalty.points_balance = current.points_balance;
      originalLoyalty.total_spent = current.total_spent;
    }

    // Set known test loyalty state for customer: GOLD, 350 pts, 3,500,000 VND
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'GOLD',
      points_balance: 350,
      total_spent: 3500000
    });

    await client.loginAsCustomer(customerUser.email, customerUser.password);
  });

  after(async () => {
    // Restore customer loyalty state
    if (customerUser && originalLoyalty.member_tier) {
      dbHelper.setUserLoyalty(customerUser.id, originalLoyalty);
    }
  });

  it('F7-01: Profile page renders VIP member card container element', async () => {
    const res = await client.getProfile();
    assert.equal(res.status, 200, 'Profile page should return HTTP 200');

    const html = res.text;
    const card = domParser.querySelector(html, '.vip-card') ||
                 domParser.querySelector(html, '#vipCard') ||
                 domParser.querySelector(html, '.membership-card') ||
                 domParser.querySelector(html, '.vip-member-card') ||
                 domParser.querySelector(html, '[data-component="vip-card"]');

    assert.ok(card, 'VIP member card container (.vip-card, #vipCard, or .membership-card) must be present in profile.php HTML');
  });

  it('F7-02: VIP card displays current tier badge and name matching database state', async () => {
    const res = await client.getProfile();
    const html = res.text;

    // Must display GOLD / Vàng tier
    const hasTierName = html.includes('GOLD') || 
                        html.includes('Gold') || 
                        html.includes('Vàng') || 
                        html.includes('VÀNG');
    assert.ok(hasTierName, 'VIP card must display the active tier name (GOLD or Vàng)');

    const tierElem = domParser.querySelector(html, '.tier-badge') ||
                     domParser.querySelector(html, '.vip-tier') ||
                     domParser.querySelector(html, '.badge-tier') ||
                     domParser.querySelector(html, '[data-tier]');
    assert.ok(tierElem, 'Tier badge element (.tier-badge, .vip-tier, etc.) must exist');
  });

  it('F7-03: VIP card displays available points balance accurately', async () => {
    const res = await client.getProfile();
    const html = res.text;

    // Check presence of points balance (350)
    assert.ok(html.includes('350'), 'Profile page must display points balance value (350)');

    const pointsElem = domParser.querySelector(html, '.points-balance') ||
                       domParser.querySelector(html, '#pointsBalance') ||
                       domParser.querySelector(html, '.vip-points') ||
                       domParser.querySelector(html, '[data-points]');
    assert.ok(pointsElem, 'Points balance element (.points-balance, #pointsBalance, or .vip-points) must exist');
  });

  it('F7-04: Displays total spending formatted with Vietnamese currency notation', async () => {
    const res = await client.getProfile();
    const html = res.text;

    // 3,500,000 VND formatted as "3.500.000" or "3,500,000" with "đ" or "VNĐ"
    const hasSpending = html.includes('3.500.000') || 
                        html.includes('3,500,000') || 
                        html.includes('3500000');
    assert.ok(hasSpending, 'VIP card must display total spending figure (3.500.000 or 3,500,000)');

    const hasCurrency = html.includes('đ') || html.includes('VNĐ') || html.includes('VND');
    assert.ok(hasCurrency, 'Must include Vietnamese currency unit (đ or VNĐ)');
  });

  it('F7-05: Contains mobile-responsive viewport meta tag and container layout', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const viewportMeta = domParser.querySelector(html, 'meta[name="viewport"]');
    assert.ok(viewportMeta, 'Must contain meta[name="viewport"] for responsive layout');
    const content = viewportMeta.getAttribute('content') || '';
    assert.ok(content.includes('width=device-width'), 'Viewport must define width=device-width');
  });

});
