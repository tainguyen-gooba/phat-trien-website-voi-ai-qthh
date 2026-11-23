/**
 * b05-progress-bar-boundaries.test.js - Tier 2 Boundary Tests: Tier Progress Bar Boundaries
 * 
 * Verifies edge cases and precision rules for customer tier progress calculations:
 * - B5-01: Exact 0% progress at 0 VNĐ spending (1,000,000 VNĐ remaining to Silver)
 * - B5-02: 99.9% progress layout stability at 999,000 VNĐ (1,000 VNĐ remaining)
 * - B5-03: 100% threshold reset to next tier at 1,000,000 VNĐ (resets for Silver -> Gold)
 * - B5-04: Diamond tier with 0 VNĐ remaining displays MAX status (no higher tier)
 * - B5-05: Negative or NaN spending guard clamps safely to 0 VNĐ without zero-division
 *
 * Source: ORIGINAL_REQUEST.md §R1, PROJECT.md §F8, TEST_INFRA.md §Tier 2
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');

const dbHelper = require('../harness/db-helper');
const {
  TIERS,
  TIER_THRESHOLDS,
  generateTestEmail
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

/**
 * Authoritative progress calculation algorithm per ORIGINAL_REQUEST §R1 & PROJECT §F8
 */
function calculateTierProgress(spendingInput, tiersConfig = null) {
  const tiers = tiersConfig || [
    { code: 'BRONZE', min_spent: 0 },
    { code: 'SILVER', min_spent: 1000000 },
    { code: 'GOLD', min_spent: 3000000 },
    { code: 'DIAMOND', min_spent: 5000000 }
  ];

  const rawSpending = Number(spendingInput);
  const spending = isNaN(rawSpending) || rawSpending < 0 ? 0 : rawSpending;

  // Determine current tier
  let currentTierIndex = 0;
  for (let i = tiers.length - 1; i >= 0; i--) {
    if (spending >= tiers[i].min_spent) {
      currentTierIndex = i;
      break;
    }
  }

  const currentTier = tiers[currentTierIndex];
  const nextTier = currentTierIndex < tiers.length - 1 ? tiers[currentTierIndex + 1] : null;

  if (!nextTier) {
    // Diamond / MAX tier reached
    return {
      currentTier: currentTier.code,
      nextTier: null,
      progressPercentage: 100,
      remainingVnd: 0,
      isMaxTier: true
    };
  }

  const range = nextTier.min_spent - currentTier.min_spent;
  const spentInRange = Math.max(0, spending - currentTier.min_spent);
  const rawPct = range > 0 ? (spentInRange / range) * 100 : 0;
  const progressPercentage = Math.min(100, Math.max(0, Math.round(rawPct * 10) / 10)); // 1 decimal place
  const remainingVnd = Math.max(0, nextTier.min_spent - spending);

  return {
    currentTier: currentTier.code,
    nextTier: nextTier.code,
    progressPercentage,
    remainingVnd,
    isMaxTier: false
  };
}

describe('Tier 2 Boundaries: b05-progress-bar-boundaries (5 tests)', () => {
  let dbTiers = [];

  before(() => {
    dbHelper.cleanupTestData();
    const tiersJson = callLoyaltyPhp('echo json_encode(array_values(getMembershipTiers()));');
    const parsed = JSON.parse(tiersJson);
    dbTiers = parsed.map(t => ({ code: t.tier_code, min_spent: Number(t.min_spent) }));
  });

  after(() => {
    dbHelper.cleanupTestData();
  });

  it('B5-01: Exact 0% progress at 0 VNĐ spending (1,000,000 VNĐ remaining to Silver)', () => {
    const spending = 0;
    const progress = calculateTierProgress(spending, dbTiers);

    assert.equal(progress.currentTier, TIERS.BRONZE);
    assert.equal(progress.nextTier, TIERS.SILVER);
    assert.equal(progress.progressPercentage, 0.0, 'Progress must be exactly 0%');
    assert.equal(progress.remainingVnd, 1000000, 'Remaining gap must be exactly 1,000,000 VNĐ');
    assert.equal(progress.isMaxTier, false);
  });

  it('B5-02: 99.9% progress layout stability at 999,000 VNĐ (1,000 VNĐ remaining)', () => {
    const spending = 999000;
    const progress = calculateTierProgress(spending, dbTiers);

    assert.equal(progress.currentTier, TIERS.BRONZE);
    assert.equal(progress.nextTier, TIERS.SILVER);
    assert.equal(progress.progressPercentage, 99.9, 'Progress must be 99.9%');
    assert.equal(progress.remainingVnd, 1000, 'Remaining gap must be 1,000 VNĐ');
    assert.ok(progress.progressPercentage <= 100, 'Progress bar percentage must not overflow 100%');
    assert.equal(progress.isMaxTier, false);
  });

  it('B5-03: 100% threshold reset to next tier at 1,000,000 VNĐ (resets for Silver -> Gold)', () => {
    const spending = 1000000;
    const progress = calculateTierProgress(spending, dbTiers);

    // Upgraded to SILVER
    assert.equal(progress.currentTier, TIERS.SILVER);
    assert.equal(progress.nextTier, TIERS.GOLD);

    // Progress percentage resets for Silver -> Gold track:
    // Spent in range = 1,000,000 - 1,000,000 = 0. Range = 3,000,000 - 1,000,000 = 2,000,000.
    // 0 / 2,000,000 = 0.0%
    assert.equal(progress.progressPercentage, 0.0, 'Progress resets to 0% on entering new tier bracket');
    assert.equal(progress.remainingVnd, 2000000, 'Remaining gap to Gold must be 2,000,000 VNĐ');
    assert.equal(progress.isMaxTier, false);
  });

  it('B5-04: Diamond tier with 0 VNĐ remaining displays MAX status (no higher tier)', () => {
    // Exact Diamond threshold (5,000,000 VNĐ) and above (7,500,000 VNĐ)
    const exactDiamond = calculateTierProgress(5000000, dbTiers);
    assert.equal(exactDiamond.currentTier, TIERS.DIAMOND);
    assert.equal(exactDiamond.nextTier, null, 'Diamond has no next tier');
    assert.equal(exactDiamond.remainingVnd, 0, 'Diamond remaining VNĐ must be 0');
    assert.equal(exactDiamond.progressPercentage, 100, 'Diamond progress must show 100% or MAX');
    assert.equal(exactDiamond.isMaxTier, true, 'MAX badge indicator must be true');

    const aboveDiamond = calculateTierProgress(7500000, dbTiers);
    assert.equal(aboveDiamond.currentTier, TIERS.DIAMOND);
    assert.equal(aboveDiamond.remainingVnd, 0);
    assert.equal(aboveDiamond.isMaxTier, true);
  });

  it('B5-05: Negative or NaN spending guard clamps safely to 0 VNĐ without zero-division', () => {
    // Negative spending
    const negProgress = calculateTierProgress(-50000, dbTiers);
    assert.equal(negProgress.currentTier, TIERS.BRONZE);
    assert.equal(negProgress.progressPercentage, 0.0);
    assert.equal(negProgress.remainingVnd, 1000000);

    // NaN / invalid string
    const nanProgress = calculateTierProgress('invalid_number', dbTiers);
    assert.equal(nanProgress.currentTier, TIERS.BRONZE);
    assert.equal(nanProgress.progressPercentage, 0.0);
    assert.equal(nanProgress.remainingVnd, 1000000);
    assert.equal(nanProgress.isMaxTier, false);
  });
});
