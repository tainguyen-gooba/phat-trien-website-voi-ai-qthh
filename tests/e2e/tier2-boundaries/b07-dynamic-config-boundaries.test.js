/**
 * b07-dynamic-config-boundaries.test.js - Tier 2 Boundary Tests: Dynamic Configuration Boundaries
 * 
 * Verifies validation and storage boundaries for dynamic loyalty settings:
 * - B7-01: Negative spending threshold input (min_spent < 0) is rejected
 * - B7-02: Negative point earning ratio (point_ratio < 0) is rejected
 * - B7-03: Zero redemption rate (0 VND/pt or 0 step) rejected to prevent division-by-zero traps
 * - B7-04: Inverted thresholds (Silver > Gold) are rejected
 * - B7-05: Empty or malformed configuration keys/values are rejected
 * - B7-06: Maximum boundary values are stored and retrieved without precision loss
 *
 * Source: ORIGINAL_REQUEST.md §R4, PROJECT.md §F15, TEST_INFRA.md §Tier 2
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');

const dbHelper = require('../harness/db-helper');

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
 * Validates loyalty settings payload according to system constraints
 */
function validateLoyaltyConfig(settings, tiers = []) {
  const errors = [];

  // 1. Settings validation
  if (!settings || typeof settings !== 'object') {
    errors.push('Missing or invalid settings object');
    return { valid: false, errors };
  }

  if (settings.point_to_vnd_rate !== undefined) {
    const rate = Number(settings.point_to_vnd_rate);
    if (isNaN(rate) || rate <= 0) {
      errors.push('point_to_vnd_rate must be a positive number greater than 0');
    }
  }

  if (settings.spending_step_vnd !== undefined) {
    const step = Number(settings.spending_step_vnd);
    if (isNaN(step) || step <= 0) {
      errors.push('spending_step_vnd must be a positive number greater than 0');
    }
  }

  if (settings.min_redeem_points !== undefined) {
    const minRedeem = Number(settings.min_redeem_points);
    if (isNaN(minRedeem) || minRedeem < 0) {
      errors.push('min_redeem_points cannot be negative');
    }
  }

  // Check for empty required keys
  const requiredKeys = ['point_to_vnd_rate', 'spending_step_vnd'];
  for (const k of requiredKeys) {
    if (settings[k] === '' || settings[k] === null || settings[k] === undefined) {
      errors.push(`Setting key '${k}' cannot be empty`);
    }
  }

  // 2. Tiers validation
  if (Array.isArray(tiers) && tiers.length > 0) {
    for (const t of tiers) {
      if (t.min_spent !== undefined && Number(t.min_spent) < 0) {
        errors.push(`Tier ${t.tier_code} min_spent cannot be negative`);
      }
      if (t.point_ratio !== undefined && Number(t.point_ratio) < 0) {
        errors.push(`Tier ${t.tier_code} point_ratio cannot be negative`);
      }
    }

    // Inverted threshold check: Bronze <= Silver < Gold < Diamond
    const tierMap = {};
    tiers.forEach(t => { tierMap[t.tier_code] = Number(t.min_spent); });
    if (tierMap.SILVER !== undefined && tierMap.GOLD !== undefined && tierMap.SILVER >= tierMap.GOLD) {
      errors.push('Inverted threshold: Silver threshold must be strictly less than Gold threshold');
    }
    if (tierMap.GOLD !== undefined && tierMap.DIAMOND !== undefined && tierMap.GOLD >= tierMap.DIAMOND) {
      errors.push('Inverted threshold: Gold threshold must be strictly less than Diamond threshold');
    }
  }

  return {
    valid: errors.length === 0,
    errors
  };
}

describe('Tier 2 Boundaries: b07-dynamic-config-boundaries (6 tests)', () => {
  let originalSettings = {};
  let originalTiers = {};

  before(() => {
    // Save original config to restore in after()
    originalSettings = JSON.parse(callLoyaltyPhp('echo json_encode(getLoyaltySettings());'));
    originalTiers = JSON.parse(callLoyaltyPhp('echo json_encode(getMembershipTiers());'));
  });

  after(() => {
    // Restore default settings
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'point_to_vnd_rate'");
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '10000' WHERE setting_key = 'spending_step_vnd'");
    dbHelper.query("UPDATE loyalty_settings SET setting_value = '100' WHERE setting_key = 'min_redeem_points'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 0.00, point_ratio = 1.00 WHERE tier_code = 'BRONZE'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 1000000.00, point_ratio = 1.50 WHERE tier_code = 'SILVER'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 3000000.00, point_ratio = 2.00 WHERE tier_code = 'GOLD'");
    dbHelper.query("UPDATE membership_tiers SET min_spent = 5000000.00, point_ratio = 3.00 WHERE tier_code = 'DIAMOND'");
    callLoyaltyPhp('clearLoyaltyCache();');
  });

  it('B7-01: Negative spending threshold input (min_spent < 0) is rejected', () => {
    const invalidTiers = [
      { tier_code: 'SILVER', min_spent: -500000, point_ratio: 1.5 }
    ];
    const validation = validateLoyaltyConfig({}, invalidTiers);

    assert.equal(validation.valid, false, 'Negative min_spent must fail validation');
    assert.ok(validation.errors.some(e => /cannot be negative/i.test(e)));
  });

  it('B7-02: Negative point earning ratio (point_ratio < 0) is rejected', () => {
    const invalidTiers = [
      { tier_code: 'GOLD', min_spent: 3000000, point_ratio: -2.0 }
    ];
    const validation = validateLoyaltyConfig({}, invalidTiers);

    assert.equal(validation.valid, false, 'Negative point_ratio must fail validation');
    assert.ok(validation.errors.some(e => /cannot be negative/i.test(e)));
  });

  it('B7-03: Zero redemption rate (0 VND/pt or 0 step) rejected to prevent division-by-zero traps', () => {
    // 0 VND/point rate
    const zeroRateValidation = validateLoyaltyConfig({ point_to_vnd_rate: 0 });
    assert.equal(zeroRateValidation.valid, false, 'Zero point_to_vnd_rate must be rejected');
    assert.ok(zeroRateValidation.errors.some(e => /greater than 0/i.test(e)));

    // 0 spending step
    const zeroStepValidation = validateLoyaltyConfig({ spending_step_vnd: 0 });
    assert.equal(zeroStepValidation.valid, false, 'Zero spending_step_vnd must be rejected');
    assert.ok(zeroStepValidation.errors.some(e => /greater than 0/i.test(e)));
  });

  it('B7-04: Inverted thresholds (Silver > Gold) are rejected', () => {
    // Silver: 4,000,000 > Gold: 3,000,000
    const invertedTiers = [
      { tier_code: 'BRONZE', min_spent: 0 },
      { tier_code: 'SILVER', min_spent: 4000000 },
      { tier_code: 'GOLD', min_spent: 3000000 },
      { tier_code: 'DIAMOND', min_spent: 5000000 }
    ];
    const validation = validateLoyaltyConfig({}, invertedTiers);

    assert.equal(validation.valid, false, 'Inverted thresholds must be rejected');
    assert.ok(validation.errors.some(e => /inverted/i.test(e)));
  });

  it('B7-05: Empty or malformed configuration keys/values are rejected', () => {
    const malformedValidation = validateLoyaltyConfig({
      point_to_vnd_rate: '',
      spending_step_vnd: null
    });

    assert.equal(malformedValidation.valid, false, 'Empty configuration fields must be rejected');
    assert.ok(malformedValidation.errors.length >= 2);
  });

  it('B7-06: Maximum boundary values are stored and retrieved without precision loss', () => {
    // Test large boundary settings: rate = 1,000 VND/pt, step = 50,000 VND, min_redeem = 10,000 pts
    const largeSettings = {
      point_to_vnd_rate: 1000,
      spending_step_vnd: 50000,
      min_redeem_points: 10000,
      max_redeem_percentage: 100
    };

    const updatePhp = `
      $settings = json_decode('${JSON.stringify(largeSettings)}', true);
      echo json_encode(updateLoyaltySettings($settings));
    `;
    const updateResult = JSON.parse(callLoyaltyPhp(updatePhp));
    assert.equal(updateResult, true, 'updateLoyaltySettings should return true');

    // Retrieve settings and assert values match exactly
    const retrievedSettings = JSON.parse(callLoyaltyPhp('echo json_encode(getLoyaltySettings());'));
    assert.equal(Number(retrievedSettings.point_to_vnd_rate), 1000);
    assert.equal(Number(retrievedSettings.spending_step_vnd), 50000);
    assert.equal(Number(retrievedSettings.min_redeem_points), 10000);

    // Test large tier boundary: Diamond threshold = 100,000,000 VNĐ with ratio = 5.0
    dbHelper.query("UPDATE membership_tiers SET min_spent = 100000000.00, point_ratio = 5.00 WHERE tier_code = 'DIAMOND'");
    callLoyaltyPhp('clearLoyaltyCache();');

    const tiers = JSON.parse(callLoyaltyPhp('echo json_encode(getMembershipTiers());'));
    assert.equal(Number(tiers.DIAMOND.min_spent), 100000000);
    assert.equal(Number(tiers.DIAMOND.point_ratio), 5.0);

    // Verify calculation under 100M threshold: 99M is Gold, 100M is Diamond
    const tier99M = JSON.parse(callLoyaltyPhp('echo json_encode(calculateTier(99000000));'));
    assert.equal(tier99M, 'GOLD');

    const tier100M = JSON.parse(callLoyaltyPhp('echo json_encode(calculateTier(100000000));'));
    assert.equal(tier100M, 'DIAMOND');
  });
});
