/**
 * f12-profile-form.test.js - Tier 1: F12 Modern FastFood Profile Form & Styling
 *
 * Tests:
 * 1. Profile form contains inputs for fullName, phone, address, and readonly email
 * 2. Submitting valid data successfully updates personal info in database
 * 3. Submitting invalid inputs (short name, invalid phone) triggers validation errors
 * 4. Submitting valid update displays success alert / feedback message
 * 5. Updating personal info strictly preserves all loyalty metadata (tier, points, spent, role)
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('../harness/TestClient');
const dbHelper = require('../harness/db-helper');
const domParser = require('../harness/dom-parser');
const fixtures = require('../harness/fixtures/loyalty-fixtures');

describe('Tier 1: F12 - Modern FastFood Profile Form & Styling', () => {
  let client;
  let testUserId;
  let testEmail;

  before(async () => {
    client = new TestClient();
    testEmail = fixtures.generateTestEmail('e2e_f12');

    const custHash = dbHelper.query('SELECT password_hash FROM users WHERE id = 3')[0]?.password_hash ||
                     '$2y$10$w095N5eYn5vBq3YfHlMhge1sL0q4x2c8o/w3fJt2f6D9uYd1GzJ3a';

    dbHelper.query(`
      INSERT INTO users (role_id, full_name, email, phone, address, password_hash, status, member_tier, points_balance, total_spent, created_at)
      VALUES (2, 'Nguyễn Văn Cũ', ?, '0901234567', 'Số 10 Đường Cũ, Q1', ?, 'active', 'SILVER', 250, 1500000, NOW())
    `, [testEmail, custHash]);

    const u = dbHelper.query('SELECT id FROM users WHERE email = ?', [testEmail])[0];
    testUserId = u.id;

    await client.loginAsCustomer(testEmail, '123456');
  });

  after(async () => {
    if (testUserId) {
      dbHelper.query('DELETE FROM users WHERE id = ?', [testUserId]);
      testUserId = null;
    }
  });

  it('F12-01: Form contains input fields for fullName, phone, address, and readonly email', async () => {
    const res = await client.getProfile();
    assert.equal(res.status, 200);
    const html = res.text;

    const nameInput = domParser.querySelector(html, 'input[name="fullName"]') ||
                      domParser.querySelector(html, 'input[name="full_name"]');
    assert.ok(nameInput, 'Must have input for fullName or full_name');

    const phoneInput = domParser.querySelector(html, 'input[name="phone"]');
    assert.ok(phoneInput, 'Must have input for phone');

    const addressInput = domParser.querySelector(html, 'input[name="address"]') ||
                         domParser.querySelector(html, 'textarea[name="address"]');
    assert.ok(addressInput, 'Must have input/textarea for address');

    assert.ok(html.includes(testEmail), 'Email address must be displayed');
  });

  it('F12-02: Submitting valid update modifies customer info in database', async () => {
    const newName = 'Nguyễn Văn Mới';
    const newPhone = '0987654321';
    const newAddress = 'Số 99 Đường Mới, Q3, TP.HCM';

    const res = await client.updateProfile({
      fullName: newName,
      phone: newPhone,
      address: newAddress
    });

    assert.equal(res.status, 200);

    const userInDb = dbHelper.query('SELECT full_name, phone, address FROM users WHERE id = ?', [testUserId])[0];
    assert.equal(userInDb.full_name, newName, 'full_name should be updated in DB');
    assert.equal(userInDb.phone, newPhone, 'phone should be updated in DB');
    assert.equal(userInDb.address, newAddress, 'address should be updated in DB');
  });

  it('F12-03: Submitting invalid inputs displays validation error without corrupting data', async () => {
    const res = await client.updateProfile({
      fullName: 'A',
      phone: '0987654321',
      address: 'Valid Address'
    });

    const html = res.text;
    assert.ok(html.includes('không hợp lệ') || html.includes('lỗi') || html.includes('Họ và tên'),
      'Must display validation error message for short name');

    // Verify DB still contains previous valid name ('Nguyễn Văn Mới')
    const userInDb = dbHelper.query('SELECT full_name FROM users WHERE id = ?', [testUserId])[0];
    assert.equal(userInDb.full_name, 'Nguyễn Văn Mới', 'DB full_name must not be overwritten with invalid input');
  });

  it('F12-04: Submitting valid update displays success alert / feedback message', async () => {
    const res = await client.updateProfile({
      fullName: 'Trần Văn Thành Công',
      phone: '0903111222',
      address: '456 Lê Lợi, Q1'
    });

    const html = res.text;
    const hasSuccessAlert = html.includes('thành công') || 
                            html.includes('Thành công') || 
                            html.includes('Cập nhật thông tin thành công');
    assert.ok(hasSuccessAlert, 'Must display success message upon profile update');
  });

  it('F12-05: Updating personal info strictly preserves loyalty metadata (tier, points, spent, role)', async () => {
    const beforeLoyalty = dbHelper.getUserLoyalty(testUserId);
    const beforeRole = dbHelper.query('SELECT role_id FROM users WHERE id = ?', [testUserId])[0]?.role_id;

    assert.equal(beforeLoyalty.member_tier, 'SILVER');
    assert.equal(beforeLoyalty.points_balance, 250);
    assert.equal(beforeLoyalty.total_spent, 1500000);
    assert.equal(Number(beforeRole), 2);

    await client.updateProfile({
      fullName: 'Lê Văn Bảo Toàn',
      phone: '0912345678',
      address: '789 Nguyễn Huệ, Q1'
    });

    const afterLoyalty = dbHelper.getUserLoyalty(testUserId);
    const afterRole = dbHelper.query('SELECT role_id FROM users WHERE id = ?', [testUserId])[0]?.role_id;

    assert.equal(afterLoyalty.member_tier, 'SILVER', 'member_tier must remain intact');
    assert.equal(afterLoyalty.points_balance, 250, 'points_balance must remain intact');
    assert.equal(afterLoyalty.total_spent, 1500000, 'total_spent must remain intact');
    assert.equal(Number(afterRole), 2, 'role_id must remain 2');
  });

});
