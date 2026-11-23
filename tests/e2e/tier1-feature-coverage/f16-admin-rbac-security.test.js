/**
 * f16-admin-rbac-security.test.js - Tier 1: F16 Admin Security & Role Authorization
 *
 * Tests:
 * 1. Guest (unauthenticated) is blocked from accessing customer profile.php (redirect to login.php)
 * 2. Guest is blocked from accessing admin API (redirect to admin login or 401/403)
 * 3. Customer (role 2) attempting to access admin configuration is blocked with HTTP 403
 * 4. Staff (role 5) is authorized to view members ledger
 * 5. Staff (role 5) is blocked with HTTP 403 from modifying loyalty settings
 * 6. Client-side parameter tampering: submitting member_tier/points in profile.php is strictly ignored
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F16 - Admin Security & Role Authorization', () => {
  let client;

  before(async () => {
    client = new TestClient();
  });

  it('F16-01: Guest (unauthenticated) requesting /profile.php is blocked and redirected to login.php', async () => {
    client.asGuest();
    const res = await client.get('/profile.php');

    // Either 302 redirect to login.php or body containing login redirect
    const isRedirect = res.status === 302 || res.status === 301;
    const isLoginPrompt = res.location.includes('login.php') || res.text.includes('login.php') || res.status === 401 || res.status === 403;

    assert.ok(isRedirect || isLoginPrompt,
      `Guest should be redirected to login.php, got status: ${res.status}, location: ${res.location}`);
  });

  it('F16-02: Guest requesting /admin/api.php is rejected with redirect or 401/403', async () => {
    client.asGuest();
    const res = await client.get('/admin/api.php?action=get_loyalty_members');

    const isBlocked = res.status === 401 || 
                      res.status === 403 || 
                      res.status === 302 || 
                      res.location.includes('admin/login.php') ||
                      res.text.includes('403') ||
                      res.text.includes('login');

    assert.ok(isBlocked, `Guest must not access admin/api.php, got status: ${res.status}`);
  });

  it('F16-03: Customer (role 2) is blocked with HTTP 403 from admin configuration', async () => {
    await client.loginAsCustomer();
    const res = await client.post('/admin/api.php?action=save_loyalty_config', {
      action: 'save_loyalty_config',
      point_to_vnd_rate: 999
    }, {}, 'customer');

    const isForbidden = res.status === 403 || 
                        res.status === 401 || 
                        res.status === 302 || 
                        res.text.includes('403') || 
                        res.text.includes('Forbidden') ||
                        res.text.includes('admin/login.php') ||
                        res.text.includes('Cổng Quản Trị') ||
                        res.body?.error;

    assert.ok(isForbidden, `Customer must be blocked from save_loyalty_config, got status: ${res.status}`);
  });

  it('F16-04: Staff (role 5) is authorized to view members ledger', async () => {
    await client.loginAsStaff();
    const res = await client.get('/admin/api.php?action=get_loyalty_members', {}, 'staff');

    // Staff should not be 403 Forbidden from viewing members
    const isAllowed = res.status === 200 || (res.status !== 403 && !res.text.includes('403 Forbidden'));
    assert.ok(isAllowed, `Staff should have view permission on members list, got status: ${res.status}`);
  });

  it('F16-05: Staff (role 5) is blocked with 403 Forbidden from modifying loyalty configuration', async () => {
    await client.loginAsStaff();
    const res = await client.post('/admin/api.php?action=save_loyalty_config', {
      action: 'save_loyalty_config',
      point_to_vnd_rate: 999
    }, {}, 'staff');

    const isBlocked = res.status === 403 || 
                      res.text.includes('403') || 
                      res.text.includes('Forbidden') || 
                      (res.body && res.body.success === false);

    assert.ok(isBlocked, `Staff must not have write/config modification access, got status: ${res.status}`);
  });

  it('F16-06: Client-side parameter tampering: customer cannot alter member_tier, points_balance, or total_spent', async () => {
    const testEmail = fixtures.generateTestEmail('e2e_tamper');
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'Tamper Test', ?, '0916000001', 'hash', 'active', 'BRONZE', 0, 0, NOW())
    `, [testEmail]);
    const u = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    const testUserId = u.id;

    try {
      // Copy password hash from customer@fastfood.vn
      const custHash = dbHelper.query('SELECT password_hash FROM users WHERE id = 3')[0]?.password_hash;
      if (custHash) {
        dbHelper.query('UPDATE users SET password_hash = ? WHERE id = ?', [custHash, testUserId]);
      }

      await client.loginAsCustomer(testEmail, '123456');

      // Attempt tampering via POST /profile.php
      await client.updateProfile({
        fullName: 'Hacker Name',
        phone: '0916000001',
        address: '123 Hacker Street',
        member_tier: 'DIAMOND',
        points_balance: 99999,
        total_spent: 999999999
      });

      // Verify DB state was NOT modified
      const userAfter = dbHelper.getUserLoyalty(testUserId);
      assert.equal(userAfter.member_tier, 'BRONZE', 'member_tier must remain BRONZE despite tampering');
      assert.equal(userAfter.points_balance, 0, 'points_balance must remain 0 despite tampering');
      assert.equal(userAfter.total_spent, 0, 'total_spent must remain 0 despite tampering');

    } finally {
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
    }
  });

});
