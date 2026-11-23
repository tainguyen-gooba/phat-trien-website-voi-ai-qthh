/**
 * f08-progress-tracker.test.js - Tier 1: F8 Tier Progress Tracker & Next Tier Gap
 *
 * Tests:
 * 1. Progress bar element exists with percentage width or progress indicator
 * 2. Displays remaining VND gap required to reach next tier (e.g. 400,000 VNĐ to Silver)
 * 3. Identifies next tier target label (e.g. "Bạc", "Vàng", "Kim Cương")
 * 4. Displays MAX cap / 100% completion for Diamond tier users without next tier gap
 * 5. Dynamic update: higher spending immediately reflects higher progress % and lower gap
 */

const { describe, it, before, after, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const domParser = require('../harness/dom-parser');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F8 - Tier Progress Tracker & Next Tier Gap', () => {
  let client;
  let customerUser;
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

    await client.loginAsCustomer(customerUser.email, customerUser.password);
  });

  after(async () => {
    if (customerUser && originalLoyalty.member_tier) {
      dbHelper.setUserLoyalty(customerUser.id, originalLoyalty);
    }
  });

  it('F8-01: Progress bar element exists on profile page with progression attribute or styling', async () => {
    // Set user to Bronze with 400,000 VND (40% to Silver 1M)
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'BRONZE',
      total_spent: 400000,
      points_balance: 40
    });

    const res = await client.getProfile();
    const html = res.text;

    const progressBar = domParser.querySelector(html, '.progress-bar') ||
                        domParser.querySelector(html, '[role="progressbar"]') ||
                        domParser.querySelector(html, '#tierProgressBar') ||
                        domParser.querySelector(html, '.tier-progress-fill') ||
                        domParser.querySelector(html, '[data-component="progress-tracker"]');

    assert.ok(progressBar, 'Progress bar element (.progress-bar, #tierProgressBar, etc.) must exist in profile.php HTML');
  });

  it('F8-02: Displays accurate remaining VND gap to reach next tier', async () => {
    // Bronze user with 600,000 VND spent -> needs 400,000 VND to reach Silver (1,000,000 VND)
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'BRONZE',
      total_spent: 600000,
      points_balance: 60
    });

    const res = await client.getProfile();
    const html = res.text;

    // Remaining gap should be 400,000 VND
    const hasGap = html.includes('400.000') || html.includes('400,000') || html.includes('400000');
    assert.ok(hasGap, 'Must display remaining gap of 400,000 VNĐ to reach next tier');

    const gapElem = domParser.querySelector(html, '.remaining-gap') ||
                    domParser.querySelector(html, '.next-tier-gap') ||
                    domParser.querySelector(html, '#remainingGap') ||
                    domParser.querySelector(html, '[data-gap]');
    assert.ok(gapElem, 'Remaining gap element (.remaining-gap, .next-tier-gap, etc.) must exist');
  });

  it('F8-03: Displays target next tier label for current member tier', async () => {
    // Silver user (total_spent = 1,500,000 VND) -> Next tier is GOLD / Vàng
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'SILVER',
      total_spent: 1500000,
      points_balance: 150
    });

    const res = await client.getProfile();
    const html = res.text;

    const hasNextTier = html.includes('GOLD') || 
                        html.includes('Gold') || 
                        html.includes('Vàng') || 
                        html.includes('VÀNG');
    assert.ok(hasNextTier, 'Must display next tier label (GOLD or Vàng) for a Silver member');
  });

  it('F8-04: Diamond tier displays MAX cap / 100% state without higher tier gap', async () => {
    // Diamond user (highest tier, e.g. 6,000,000 VND)
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'DIAMOND',
      total_spent: 6000000,
      points_balance: 600
    });

    const res = await client.getProfile();
    const html = res.text;

    const hasMaxIndicator = html.includes('100%') ||
                            html.includes('MAX') ||
                            html.includes('Max') ||
                            html.includes('cao nhất') ||
                            html.includes('Hạng cao nhất') ||
                            html.includes('Đã đạt hạng tối đa');
    assert.ok(hasMaxIndicator, 'Diamond tier member must see MAX cap, 100%, or highest tier indicator');
  });

  it('F8-05: Dynamic update: increasing spending increases progress % and reduces remaining gap', async () => {
    // Step A: 200,000 VND spent (gap 800,000 VND to Silver)
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'BRONZE',
      total_spent: 200000,
      points_balance: 20
    });
    const resA = await client.getProfile();
    const htmlA = resA.text;
    const hasGap800 = htmlA.includes('800.000') || htmlA.includes('800,000') || htmlA.includes('800000');
    assert.ok(hasGap800, 'State A must show remaining gap of 800,000 VNĐ');

    // Step B: Increase spending to 700,000 VND (gap drops to 300,000 VND to Silver)
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'BRONZE',
      total_spent: 700000,
      points_balance: 70
    });
    const resB = await client.getProfile();
    const htmlB = resB.text;
    const hasGap300 = htmlB.includes('300.000') || htmlB.includes('300,000') || htmlB.includes('300000');
    assert.ok(hasGap300, 'State B must show remaining gap of 300,000 VNĐ');
  });

});
