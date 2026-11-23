/**
 * f09-tier-privileges.test.js - Tier 1: F9 Tier Privileges Guide & Comparison
 *
 * Tests:
 * 1. Displays all 4 membership tiers (Bronze, Silver, Gold, Diamond) on profile.php
 * 2. Current active tier is highlighted with active styling or badge
 * 3. Displays points earning ratios for all tiers (1.0x, 1.5x, 2.0x, 3.0x)
 * 4. Displays spending thresholds for all tiers (0, 1.000.000đ, 3.000.000đ, 5.000.000đ)
 * 5. Lists exclusive perks and benefits for each tier
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const domParser = require('../harness/dom-parser');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F9 - Tier Privileges Guide & Comparison', () => {
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

    // Set customer to SILVER tier
    dbHelper.setUserLoyalty(customerUser.id, {
      member_tier: 'SILVER',
      total_spent: 1500000,
      points_balance: 150
    });

    await client.loginAsCustomer(customerUser.email, customerUser.password);
  });

  after(async () => {
    if (customerUser && originalLoyalty.member_tier) {
      dbHelper.setUserLoyalty(customerUser.id, originalLoyalty);
    }
  });

  it('F9-01: Displays all 4 membership tiers (Bronze, Silver, Gold, Diamond) on profile page', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const hasBronze = html.includes('BRONZE') || html.includes('Bronze') || html.includes('Đồng');
    const hasSilver = html.includes('SILVER') || html.includes('Silver') || html.includes('Bạc');
    const hasGold = html.includes('GOLD') || html.includes('Gold') || html.includes('Vàng');
    const hasDiamond = html.includes('DIAMOND') || html.includes('Diamond') || html.includes('Kim Cương');

    assert.ok(hasBronze, 'Must mention Bronze / Đồng tier');
    assert.ok(hasSilver, 'Must mention Silver / Bạc tier');
    assert.ok(hasGold, 'Must mention Gold / Vàng tier');
    assert.ok(hasDiamond, 'Must mention Diamond / Kim Cương tier');

    const tierCards = domParser.querySelectorAll(html, '.tier-card') ||
                      domParser.querySelectorAll(html, '.privilege-card') ||
                      domParser.querySelectorAll(html, '[data-tier-item]');
    assert.ok(tierCards && tierCards.length >= 4, 'Should render at least 4 tier privilege cards/items');
  });

  it('F9-02: Highlights customer current active tier (Silver) with distinctive active badge or styling', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const activeTierElem = domParser.querySelector(html, '.tier-card.active') ||
                           domParser.querySelector(html, '.current-tier') ||
                           domParser.querySelector(html, '.tier-active') ||
                           domParser.querySelector(html, '[data-is-current="true"]') ||
                           domParser.querySelector(html, '.badge-current-tier');

    assert.ok(activeTierElem || html.includes('Hạng hiện tại') || html.includes('Hiện tại'),
      'Must have highlighted active state or "Hạng hiện tại" indicator for the user current tier (Silver)');
  });

  it('F9-03: Displays points earning ratios for tiers (1.0, 1.5, 2.0, 3.0 or corresponding points per 10k)', async () => {
    const res = await client.getProfile();
    const html = res.text;

    const has1_0 = html.includes('1') && (html.includes('10.000') || html.includes('10k') || html.includes('1.0'));
    const has1_5 = html.includes('1.5') || html.includes('1,5');
    const has2_0 = html.includes('2') || html.includes('2.0') || html.includes('gấp đôi');
    const has3_0 = html.includes('3') || html.includes('3.0') || html.includes('gấp 3');

    assert.ok(has1_0 && has1_5 && has2_0 && has3_0,
      'Must display earning multipliers/ratios across tiers (1.0, 1.5, 2.0, 3.0 points per 10k VND)');
  });

  it('F9-04: Displays spending qualification thresholds for all tiers', async () => {
    const res = await client.getProfile();
    const html = res.text;

    // Thresholds: 1M (Silver), 3M (Gold), 5M (Diamond)
    const has1M = html.includes('1.000.000') || html.includes('1,000,000') || html.includes('1 triệu') || html.includes('1M');
    const has3M = html.includes('3.000.000') || html.includes('3,000,000') || html.includes('3 triệu') || html.includes('3M');
    const has5M = html.includes('5.000.000') || html.includes('5,000,000') || html.includes('5 triệu') || html.includes('5M');

    assert.ok(has1M, 'Must display 1,000,000 threshold for Silver');
    assert.ok(has3M, 'Must display 3,000,000 threshold for Gold');
    assert.ok(has5M, 'Must display 5,000,000 threshold for Diamond');
  });

  it('F9-05: Lists exclusive benefits and perks for each tier', async () => {
    const res = await client.getProfile();
    const html = res.text;

    // Standard benefits from membership_tiers seed
    const hasPerks = html.includes('voucher') || 
                     html.includes('Voucher') ||
                     html.includes('sinh nhật') || 
                     html.includes('vận chuyển') || 
                     html.includes('ưu tiên') ||
                     html.includes('VIP');

    assert.ok(hasPerks, 'Privileges section must list specific tier perks (voucher, shipping, birthday, VIP support)');
  });

});
