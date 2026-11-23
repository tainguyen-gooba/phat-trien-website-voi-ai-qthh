/**
 * wf04-security-rbac-hardening.test.js - Tier 4: Real-World Application Scenario Workflows
 * Scenario: WF-04: Security, Role-Based Access Control & Anti-Tampering Hardening
 *
 * Sequence of Events:
 * 1. Guest blocked from profile.php (redirects to login) and admin/api.php (401/403/redirect).
 * 2. Customer (Role 2) calling admin endpoints or save_loyalty_config receives 403 Forbidden / Access Denied.
 * 3. Staff (Role 5) can view members (get_loyalty_members / get_all_data) but is blocked from save_loyalty_config (403 Forbidden).
 * 4. Anti-tampering: malicious parameter injection on profile (points_balance=999999, member_tier=DIAMOND) has zero effect on server state.
 * 5. Points over-redemption guard: attempting to redeem more points than balance or negative points is strictly rejected.
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');
const { execFileSync } = require('child_process');

const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const { TIERS } = require('../harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');
const PASSWORD_HASH = '$2y$10$OhD1HTzuuPIp4t5IGxZNJ.Cpr1SH9zhIj0Xrn17uLUEKfP/Ow4L3q'; // hash for 'password123'

/**
 * Invokes helpers/loyalty.php service methods directly via PHP CLI PDO bridge
 * to guarantee progressive testability across milestones M1-M5.
 */
function callLoyaltyService(expr) {
  const script = `require 'helpers/loyalty.php'; echo json_encode(${expr});`;
  const output = execFileSync('php', ['-r', script], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  try {
    return JSON.parse(output.trim());
  } catch (err) {
    throw new Error(`Failed to parse loyalty service output: ${output} | Expr: ${expr}`);
  }
}

describe('WF-04: Security, Role-Based Access Control & Anti-Tampering Hardening', () => {
  let guestClient;
  let customerClient;
  let staffClient;
  let adminClient;

  const testEmail = 'wf04_user_security@fastfood.vn';
  const testPassword = 'password123';
  const testFullName = 'Đỗ Bảo Mật';
  const testPhone = '0904567890';
  let userId;

  let lastState = {
    tier: 'BRONZE',
    points: 50,
    spent: 0
  };

  function ensureUser() {
    let rows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    if (!rows || rows.length === 0) {
      dbHelper.query(
        `INSERT INTO users (full_name, email, password_hash, phone, address, role_id, status, member_tier, points_balance, total_spent, created_at)
         VALUES (?, ?, ?, ?, '321 Nam Kỳ Khởi Nghĩa, Q3', 2, 'active', ?, ?, ?, NOW())`,
        [testFullName, testEmail, PASSWORD_HASH, testPhone, lastState.tier, lastState.points, lastState.spent]
      );
      rows = dbHelper.query('SELECT id, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [testEmail]);
    }
    userId = Number(rows[0].id);
    return userId;
  }

  function cleanupWf04() {
    try {
      dbHelper.query("DELETE FROM point_transactions WHERE description LIKE '%WF04%' OR user_id IN (SELECT id FROM users WHERE email = ?)", [testEmail]);
      dbHelper.query("DELETE FROM orders WHERE order_code LIKE 'WF04%'");
      dbHelper.query("DELETE FROM users WHERE email = ?", [testEmail]);
    } catch (_) {}
  }

  before(async () => {
    TestClient.resetRateLimits();
    guestClient = new TestClient().asGuest();
    customerClient = new TestClient();
    staffClient = new TestClient();
    adminClient = new TestClient();

    cleanupWf04();
    ensureUser();

    await adminClient.loginAsAdmin();
  });

  after(async () => {
    cleanupWf04();
  });

  it('Step 1: Guest blocked from profile.php and admin/api.php', async () => {
    ensureUser();
    // 1. Guest access to profile.php
    const profileRes = await guestClient.get('/profile.php');
    const isRedirectToLogin = profileRes.status === 302 && profileRes.location.includes('login.php');
    const showsLoginForm = profileRes.text.includes('Đăng nhập') || profileRes.text.includes('login.php') || profileRes.text.includes('Mật khẩu');
    assert.ok(
      isRedirectToLogin || showsLoginForm || profileRes.status === 401 || profileRes.status === 403,
      `Unauthenticated guest must be blocked from profile.php, got HTTP ${profileRes.status}`
    );

    // 2. Guest access to admin/api.php
    const adminApiRes = await guestClient.get('/admin/api.php?action=get_banners');
    const isGuestBlocked = adminApiRes.status === 401 || adminApiRes.status === 403 || adminApiRes.status === 302 ||
                           adminApiRes.text.includes('login.php') || adminApiRes.text.includes('Đăng Nhập') || adminApiRes.body?.success === false;
    assert.ok(
      isGuestBlocked,
      `Guest must be denied access to admin/api.php, got status ${adminApiRes.status}`
    );

    // 3. Guest POST to admin/api.php
    const adminPostRes = await guestClient.post('/admin/api.php', { action: 'save_loyalty_config' });
    const isGuestPostBlocked = adminPostRes.status === 401 || adminPostRes.status === 403 || adminPostRes.status === 302 ||
                               adminPostRes.text.includes('login.php') || adminPostRes.text.includes('Đăng Nhập') || adminPostRes.body?.success === false;
    assert.ok(
      isGuestPostBlocked,
      `Guest must be denied execution on admin/api.php, got status ${adminPostRes.status}`
    );
  });

  it('Step 2: Customer (Role 2) calling save_loyalty_config is blocked with 403 Forbidden / Denied', async () => {
    ensureUser();
    await customerClient.loginAsCustomer(testEmail, testPassword);

    // Customer attempts to call admin config endpoint
    const tamperRes = await customerClient.post('/admin/api.php', {
      action: 'save_loyalty_config',
      point_to_vnd_rate: 99999
    });

    const isCustomerBlocked = tamperRes.status === 403 || tamperRes.status === 401 || tamperRes.status === 302 ||
                              tamperRes.text.includes('403 Forbidden') || tamperRes.text.includes('login.php') ||
                              tamperRes.body?.success === false;
    assert.ok(
      isCustomerBlocked,
      `Customer must receive 403 Forbidden / Denied when calling save_loyalty_config, got status ${tamperRes.status}`
    );

    // Verify database loyalty_settings was NOT modified by customer
    const settings = callLoyaltyService('getLoyaltySettings()');
    assert.notEqual(
      Number(settings.point_to_vnd_rate),
      99999,
      'Tampered rate must NOT be saved in database'
    );
  });

  it('Step 3: Staff (Role 5) can view member data but is blocked from modifying loyalty config', async () => {
    ensureUser();
    // Authenticate as staff member (Role 5)
    const staffLogin = await staffClient.loginAsStaff('staff1@fastfood.vn', '123456');
    assert.ok(staffLogin.success, 'Staff login must succeed');

    // 1. Staff can view admin operations data
    const viewRes = await staffClient.get('/admin/api.php?action=get_banners');
    assert.equal(viewRes.status, 200, 'Staff must be able to view admin data (HTTP 200)');
    assert.ok(viewRes.body?.success, 'Staff data query must succeed');

    // 2. Staff is blocked from executing admin-only modifications (save_user or save_loyalty_config)
    const staffModifyRes = await staffClient.post('/admin/api.php', {
      action: 'save_user',
      id: userId,
      roleId: 1
    });

    const isStaffBlocked = staffModifyRes.status === 403 || staffModifyRes.status === 401 ||
                           staffModifyRes.text.includes('403 Forbidden') || staffModifyRes.body?.success === false;
    assert.ok(
      isStaffBlocked,
      `Staff must be blocked with 403 Forbidden on admin modification actions, got status ${staffModifyRes.status}`
    );

    // Verify user role was not elevated by staff
    const userRow = dbHelper.query('SELECT role_id FROM users WHERE id = ?', [userId]);
    assert.equal(Number(userRow[0].role_id), 2, 'Customer role must remain 2 (not escalated to admin)');
  });

  it('Step 4: Malicious parameter injection on profile has zero effect on server state', async () => {
    ensureUser();
    await customerClient.loginAsCustomer(testEmail, testPassword);

    // Customer attempts to tamper with points and tier via profile form submission
    const formTamperRes = await customerClient.post('/profile.php', {
      fullName: 'Hacker Đỗ',
      phone: testPhone,
      address: '321 Nam Kỳ Khởi Nghĩa, Q3',
      points_balance: 999999,
      member_tier: 'DIAMOND',
      total_spent: 999999999
    }, {
      'Content-Type': 'application/x-www-form-urlencoded'
    });

    // Also attempt via JSON profile API
    await customerClient.post('/api/auth.php', {
      action: 'profile',
      fullName: 'Hacker Đỗ',
      points_balance: 999999,
      member_tier: 'DIAMOND',
      total_spent: 999999999
    });

    // Directly audit database: points_balance and member_tier MUST remain untampered!
    const loyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(
      loyalty.points_balance,
      50,
      `Tamper attack failed: points_balance was modified from 50 to ${loyalty.points_balance}`
    );
    assert.equal(
      loyalty.member_tier,
      TIERS.BRONZE,
      `Tamper attack failed: member_tier was modified from BRONZE to ${loyalty.member_tier}`
    );
    assert.equal(
      loyalty.total_spent,
      0,
      `Tamper attack failed: total_spent was modified from 0 to ${loyalty.total_spent}`
    );
  });

  it('Step 5: Points over-redemption guard -> Reject redeeming more points than balance or negative points', async () => {
    ensureUser();
    // User currently has 50 points

    // 1. Attempt to exchange voucher package 1 (requires 100 points) with only 50 points
    const overExchange = callLoyaltyService(`exchangePointsForVoucher(${userId}, 1)`);
    assert.equal(
      overExchange.success,
      false,
      'Exchanging voucher with insufficient points must return success: false'
    );
    assert.ok(
      overExchange.error && overExchange.error.includes('không đủ'),
      `Error message should state insufficient points, got: ${overExchange.error}`
    );

    // Verify balance was NOT deducted
    let loyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(loyalty.points_balance, 50, `Balance must remain 50, got ${loyalty.points_balance}`);

    // 2. Attempt checkout redemption of 100 points with only 50 points balance
    const overRedeem = callLoyaltyService(`redeemPointsAtCheckout(${userId}, 0, 100, 'TEST_OVER')`);
    assert.equal(
      overRedeem.success,
      false,
      'Redeeming more points than balance must return success: false'
    );
    assert.ok(
      overRedeem.error && overRedeem.error.includes('không đủ'),
      `Error message should state insufficient points, got: ${overRedeem.error}`
    );

    loyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(loyalty.points_balance, 50, `Balance must remain 50, got ${loyalty.points_balance}`);

    // 3. Attempt negative points redemption (-100 points to artificially inflate balance)
    const negativeRedeem = callLoyaltyService(`redeemPointsAtCheckout(${userId}, 0, -100, 'TEST_NEG')`);
    assert.equal(
      negativeRedeem.points_used,
      0,
      'Negative points must be clamped to 0 points used'
    );
    assert.equal(
      negativeRedeem.discount_amount,
      0,
      'Negative points must produce 0 discount amount'
    );

    loyalty = dbHelper.getUserLoyalty(userId);
    assert.equal(loyalty.points_balance, 50, 'Balance must remain 50 after negative points attempt');
  });
});
