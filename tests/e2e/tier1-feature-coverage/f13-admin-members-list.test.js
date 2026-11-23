/**
 * f13-admin-members-list.test.js - Tier 1: F13 Admin Members Ledger
 *
 * Tests:
 * 1. Membership section and get_loyalty_members API endpoint are accessible to Admin
 * 2. Members ledger returns member rows with member_tier, points_balance, total_spent
 * 3. Search filter by keyword (name or email) correctly filters returned members
 * 4. Tier filter parameter (tier=DIAMOND/GOLD/etc.) filters members by specific tier
 * 5. Pagination parameter (page) is supported with correct pagination results
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F13 - Admin Members Ledger', () => {
  let client;
  let testDiamondId;
  let testBronzeId;
  const testDiamondEmail = fixtures.generateTestEmail('e2e_diamond');
  const testBronzeEmail = fixtures.generateTestEmail('e2e_bronze');

  before(async () => {
    client = new TestClient();

    // Seed test customers: 1 DIAMOND, 1 BRONZE
    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES 
        (2, 'Vip Diamond Member', ?, '0913000001', 'hash', 'active', 'DIAMOND', 1500, 6000000, NOW()),
        (2, 'Normal Bronze Member', ?, '0913000002', 'hash', 'active', 'BRONZE', 0, 100000, NOW())
    `, [testDiamondEmail, testBronzeEmail]);

    const u1 = dbHelper.query('SELECT id FROM users WHERE email = ?', [testDiamondEmail])[0];
    const u2 = dbHelper.query('SELECT id FROM users WHERE email = ?', [testBronzeEmail])[0];
    testDiamondId = u1.id;
    testBronzeId = u2.id;

    await client.loginAsAdmin();
  });

  after(async () => {
    if (testDiamondId) dbHelper.query('DELETE FROM users WHERE id = ?', [testDiamondId]);
    if (testBronzeId) dbHelper.query('DELETE FROM users WHERE id = ?', [testBronzeId]);
  });

  it('F13-01: Admin can access membership ledger endpoint (get_loyalty_members)', async () => {
    const res = await client.adminGetMembers();
    assert.equal(res.status, 200, 'get_loyalty_members should return HTTP 200');
    assert.ok(res.body, 'Response body must exist');
  });

  it('F13-02: Members list includes member_tier, points_balance, and total_spent for users', async () => {
    const res = await client.adminGetMembers();
    const members = res.data?.members || res.body?.data || res.body?.members || [];
    assert.ok(Array.isArray(members), 'Members list should be an array');
    assert.ok(members.length > 0, 'Should return at least one member');

    const first = members[0];
    assert.ok(first.member_tier !== undefined, 'Member row must contain member_tier');
    assert.ok(first.points_balance !== undefined, 'Member row must contain points_balance');
    assert.ok(first.total_spent !== undefined, 'Member row must contain total_spent');
  });

  it('F13-03: Search filter returns members matching name or email query keyword', async () => {
    const res = await client.adminGetMembers({ search: testDiamondEmail });
    const members = res.data?.members || res.body?.data || res.body?.members || [];

    assert.ok(Array.isArray(members));
    assert.ok(members.length >= 1, 'Should find the seeded diamond member by email search');
    const found = members.find(m => m.email === testDiamondEmail);
    assert.ok(found, 'Search result must include the target diamond member');
    assert.equal(found.member_tier, 'DIAMOND');
  });

  it('F13-04: Tier filter returns only members belonging to specified tier (DIAMOND)', async () => {
    const res = await client.adminGetMembers({ tier: 'DIAMOND' });
    const members = res.data?.members || res.body?.data || res.body?.members || [];

    assert.ok(Array.isArray(members));
    assert.ok(members.length >= 1, 'Should find at least 1 diamond member');
    for (const m of members) {
      assert.equal(m.member_tier.toUpperCase(), 'DIAMOND', 'All returned members must have DIAMOND tier');
    }
  });

  it('F13-05: Supports pagination parameter (page) and returns paginated records', async () => {
    const resPage1 = await client.adminGetMembers({ page: 1, limit: 1 });
    const membersPage1 = resPage1.data?.members || resPage1.body?.data || resPage1.body?.members || [];

    assert.ok(Array.isArray(membersPage1));
    assert.ok(membersPage1.length <= 1, 'Page 1 with limit 1 should return at most 1 item');
  });

});
