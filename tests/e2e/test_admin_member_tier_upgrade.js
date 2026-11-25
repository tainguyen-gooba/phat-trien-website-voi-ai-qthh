/**
 * E2E Test: Admin Member Edit & Tier Promotion (Thăng hạng thành viên)
 * Verifies:
 * 1. RBAC: Guest, Customer (role 2), and Staff (role 5) are blocked (403/redirect) from update_member_loyalty
 * 2. Admin (role 1) can promote a BRONZE member to GOLD / DIAMOND and adjust points
 * 3. sync_min_spent option automatically raises total_spent to tier threshold when checked
 * 4. point_transactions ledger logs an ADMIN_ADJUST audit row
 * 5. processOrderEarnPoints() preserves manually upgraded tier (does not downgrade on small order)
 * 6. Admin Console UI renders the Edit button & member-loyalty-edit-dialog modal
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const TestClient = require('./harness/TestClient');
const dbHelper = require('./harness/db-helper');
const fixtures = require('./harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../..');

function runPhpLoyalty(code) {
  const full = `require 'config/database.php'; require 'helpers/loyalty.php'; ${code}`;
  return execFileSync('php', ['-r', full], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  }).trim();
}

describe('Admin Member Edit & Tier Promotion (Thăng hạng thành viên)', () => {
  let client;
  let testUserId;
  let testOrderId;
  const testEmail = fixtures.generateTestEmail('tier_promo');

  before(async () => {
    client = new TestClient();

    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
       VALUES (2, 'Tier Promo Test User', ?, '0988777666', 'hash', 'active', 'BRONZE', 50, 100000, NOW())`,
      [testEmail]
    );
    const rows = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [testEmail]);
    testUserId = Number(rows[0].id);
  });

  after(async () => {
    if (testOrderId) {
      dbHelper.query('DELETE FROM point_transactions WHERE order_id = ?', [testOrderId]);
      dbHelper.query('DELETE FROM orders WHERE id = ?', [testOrderId]);
    }
    if (testUserId) {
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [testUserId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
    }
  });

  it('1. Admin UI includes Edit button and member-loyalty-edit-dialog modal', async () => {
    await client.loginAsAdmin();
    const res = await client.get('/admin/index.php', {}, 'admin');
    assert.equal(res.status, 200);
    assert.ok(res.text.includes('member-loyalty-edit-dialog'), 'Must render edit modal');
    assert.ok(res.text.includes('openEditMemberLoyaltyModal'), 'Must define openEditMemberLoyaltyModal');
    assert.ok(res.text.includes('update_member_loyalty'), 'Must wire update_member_loyalty API action');
  });

  it('2. Guest, Customer (role 2), and Staff (role 5) are blocked from update_member_loyalty', async () => {
    client.asGuest();
    const guestRes = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'DIAMOND'
    });
    assert.ok(
      guestRes.body?.success !== true && (
        guestRes.status === 401 ||
        guestRes.status === 403 ||
        guestRes.status === 302 ||
        guestRes.text.includes('Đăng Nhập') ||
        (guestRes.raw?.url || '').includes('login.php')
      ),
      `Guest must be blocked, got status=${guestRes.status}, url=${guestRes.raw?.url}`
    );

    await client.loginAsCustomer();
    const custRes = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'DIAMOND'
    }, {}, 'customer');
    assert.ok(
      custRes.body?.success !== true && (
        custRes.status === 403 ||
        custRes.status === 401 ||
        custRes.status === 302 ||
        custRes.text.includes('Đăng Nhập') ||
        (custRes.raw?.url || '').includes('login.php')
      ),
      `Customer must be blocked, got status=${custRes.status}, url=${custRes.raw?.url}`
    );

    const dbAfter = dbHelper.getUserLoyalty(testUserId);
    assert.equal(dbAfter.member_tier, 'BRONZE', 'Tier must remain BRONZE after unauthorized attempts');

    await client.loginAsStaff();
    const staffRes = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'DIAMOND'
    }, {}, 'staff');
    assert.equal(staffRes.status, 403, `Staff must receive 403 Forbidden, got ${staffRes.status}`);
  });

  it('3. Admin rejects invalid tier code and negative points', async () => {
    await client.loginAsAdmin();

    const invalidTierRes = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'PLATINUM_FAKE'
    }, {}, 'admin');
    assert.equal(invalidTierRes.status, 400);
    assert.equal(invalidTierRes.body?.success, false);

    const negPtsRes = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'GOLD',
      points_balance: -50
    }, {}, 'admin');
    assert.equal(negPtsRes.status, 400);
    assert.equal(negPtsRes.body?.success, false);
  });

  it('4. Admin promotes BRONZE user to GOLD without overwriting spent and logs ADMIN_ADJUST', async () => {
    await client.loginAsAdmin();
    const res = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'GOLD',
      points_balance: 250,
      total_spent: 100000,
      sync_min_spent: false,
      reason: 'Thăng hạng khách hàng VIP'
    }, {}, 'admin');

    assert.equal(res.status, 200);
    assert.equal(res.body?.success, true);

    const updated = dbHelper.getUserLoyalty(testUserId);
    assert.equal(updated.member_tier, 'GOLD');
    assert.equal(Number(updated.points_balance), 250);
    assert.equal(Number(updated.total_spent), 100000);

    const txs = dbHelper.query(
      "SELECT * FROM point_transactions WHERE user_id = ? AND transaction_type = 'ADMIN_ADJUST' ORDER BY id DESC LIMIT 1",
      [testUserId]
    );
    assert.equal(txs.length, 1);
    assert.equal(Number(txs[0].points), 200);
    assert.equal(Number(txs[0].balance_after), 250);
    assert.ok(txs[0].description.includes('Thăng hạng khách hàng VIP'));
  });

  it('5. Completing a small order preserves Admin-promoted GOLD/DIAMOND tier and earns tier ratio points', async () => {
    await client.loginAsAdmin();
    // Promote to DIAMOND with sync_min_spent = true
    const promoRes = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'DIAMOND',
      points_balance: 300,
      total_spent: 100000,
      sync_min_spent: false,
      reason: 'Đặc cách Kim Cương'
    }, {}, 'admin');
    assert.equal(promoRes.status, 200);

    // Create completed small order (50,000 VND) and process earn points
    const orderCode = 'E2E_PROMO_' + Date.now();
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status, order_status, points_earned, placed_at, created_at)
       VALUES (?, ?, 1, 50000, 0, 0, 50000, 'cod', 'completed', 'completed', 0, NOW(), NOW())`,
      [orderCode, testUserId]
    );
    const ord = dbHelper.query('SELECT id FROM orders WHERE order_code = ? LIMIT 1', [orderCode])[0];
    testOrderId = Number(ord.id);

    const earnJson = JSON.parse(runPhpLoyalty(`echo json_encode(processOrderEarnPoints(${testOrderId}));`));
    assert.equal(earnJson.success, true);
    assert.equal(earnJson.points_earned, 15, '50,000 VND at DIAMOND x3 ratio should earn 15 pts');
    assert.equal(earnJson.new_tier, 'DIAMOND', 'Manually promoted DIAMOND tier must not downgrade on small order');

    const afterUser = dbHelper.getUserLoyalty(testUserId);
    assert.equal(afterUser.member_tier, 'DIAMOND');
    assert.equal(Number(afterUser.points_balance), 315);
  });

  it('6. sync_min_spent raises total_spent to SILVER threshold when enabled', async () => {
    await client.loginAsAdmin();
    const res = await client.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: testUserId,
      member_tier: 'SILVER',
      points_balance: 315,
      total_spent: 100000,
      sync_min_spent: true
    }, {}, 'admin');
    assert.equal(res.status, 200);

    const updated = dbHelper.getUserLoyalty(testUserId);
    assert.equal(updated.member_tier, 'SILVER');
    assert.ok(Number(updated.total_spent) >= 1000000, 'sync_min_spent should raise spent to >= SILVER threshold');
  });
});
