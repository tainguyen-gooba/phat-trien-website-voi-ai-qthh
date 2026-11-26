/**
 * E2E Test: Tier-Upgrade Exclusive Voucher (Chỉ sử dụng 1 lần duy nhất)
 * Verifies:
 * 1. Promoting a member from BRONZE -> GOLD via Admin API automatically gifts 1 unique UP-GOLD-... voucher (usage_limit=1, user_limit=1)
 * 2. Re-saving GOLD or downgrading & re-promoting to GOLD never creates a duplicate UP-GOLD voucher
 * 3. Organic spend promotion (BRONZE -> SILVER via processOrderEarnPoints) automatically gifts 1 unique UP-SILVER-... voucher
 * 4. Gifted tier-upgrade voucher appears in customer's Wallet (vouchers.php & sync_state) with "Quà Thăng Hạng" badge
 * 5. Using the tier-upgrade voucher at checkout works ONCE, then hides code (xxxx), decrements wallet_count, and strictly blocks 2nd use
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('child_process');
const path = require('path');
const TestClient = require('./harness/TestClient');
const dbHelper = require('./harness/db-helper');
const fixtures = require('./harness/fixtures/loyalty-fixtures');

const PROJECT_ROOT = path.resolve(__dirname, '../..');

describe('Tier-Upgrade Exclusive 1-Use Voucher', () => {
  const createdUserIds = [];
  const createdVoucherIds = [];
  let pwdHash;

  before(() => {
    const sample = dbHelper.query("SELECT password_hash FROM users WHERE email = 'customer@fastfood.vn' LIMIT 1");
    pwdHash = sample[0].password_hash;
  });

  after(() => {
    for (const uId of createdUserIds) {
      const uOrders = dbHelper.query('SELECT id, order_code FROM orders WHERE customer_id = ?', [uId]);
      for (const o of uOrders) {
        dbHelper.query('DELETE FROM realtime_orders WHERE order_code = ?', [o.order_code]);
        dbHelper.query('DELETE FROM order_items WHERE order_id = ?', [o.id]);
        dbHelper.query('DELETE FROM orders WHERE id = ?', [o.id]);
      }
      const gifted = dbHelper.query('SELECT voucher_id FROM user_vouchers WHERE user_id = ?', [uId]);
      for (const g of gifted) {
        createdVoucherIds.push(Number(g.voucher_id));
      }
      dbHelper.query('DELETE FROM voucher_usages WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM user_vouchers WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM coupons WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [uId]);
    }
    for (const vid of [...new Set(createdVoucherIds)]) {
      dbHelper.query('DELETE FROM vouchers WHERE id = ?', [vid]);
    }
  });

  it('1. Admin promoting BRONZE -> GOLD gifts exactly 1 UP-GOLD voucher, visible in Wallet, and strictly 1-use', async () => {
    const email = fixtures.generateTestEmail('upg_admin');
    const phone = '09' + String(Math.floor(10000000 + Math.random() * 89999999));
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
       VALUES (2, 'Upgrade Voucher Tester', ?, ?, ?, 'active', 'BRONZE', 0, 0, NOW())`,
      [email, phone, pwdHash]
    );
    const uRow = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [email]);
    const userId = Number(uRow[0].id);
    createdUserIds.push(userId);

    const admin = new TestClient('http://127.0.0.1:5000');
    await admin.loginAsAdmin();

    // Promote BRONZE -> GOLD
    const promoRes = await admin.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: userId,
      member_tier: 'GOLD',
      reason: 'Thăng hạng test'
    }, {}, 'admin');
    assert.equal(promoRes.status, 200);
    assert.equal(promoRes.body?.success, true, promoRes.text);

    const gift = promoRes.body?.data?.tier_upgrade_voucher;
    assert.ok(gift && gift.code.startsWith(`UP-GOLD-${userId}-`), `Expected UP-GOLD voucher, got: ${JSON.stringify(gift)}`);
    assert.equal(Number(gift.usage_limit), 1);
    assert.equal(Number(gift.user_limit), 1);
    createdVoucherIds.push(Number(gift.voucher_id));

    // Re-saving GOLD or downgrading -> re-promoting to GOLD MUST NOT duplicate UP-GOLD
    const repeatRes = await admin.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: userId,
      member_tier: 'GOLD'
    }, {}, 'admin');
    assert.equal(repeatRes.body?.data?.tier_upgrade_voucher || null, null, 'Must not re-gift UP-GOLD on same tier');

    await admin.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: userId,
      member_tier: 'SILVER'
    }, {}, 'admin');
    const rePromoRes = await admin.post('/admin/api.php?action=update_member_loyalty', {
      action: 'update_member_loyalty',
      user_id: userId,
      member_tier: 'GOLD'
    }, {}, 'admin');
    assert.equal(rePromoRes.body?.data?.tier_upgrade_voucher || null, null, 'Must not re-gift UP-GOLD even after downgrade & re-promote');

    // Customer checks Wallet (vouchers.php & sync_state) -> sees Quà Thăng Hạng
    const cust = new TestClient('http://127.0.0.1:5000');
    await cust.loginAsCustomer(email, '123456');

    const syncRes = await cust.get('/api/vouchers.php?action=sync_state');
    assert.equal(Number(syncRes.body.data.wallet_count), 1);
    const wItem = syncRes.body.data.wallet_vouchers.find(v => v.code === gift.code);
    assert.ok(wItem, 'Tier upgrade voucher must be in wallet_vouchers');
    assert.equal(wItem.is_tier_upgrade, true);

    const htmlRes = await cust.get('/vouchers.php');
    assert.ok(htmlRes.text.includes('Quà Thăng Hạng'), 'vouchers.php Wallet must show Quà Thăng Hạng badge');
    assert.ok(htmlRes.text.includes(gift.code), 'Unused upgrade voucher code visible in Wallet');

    // Customer uses the UP-GOLD voucher ONCE at checkout (order subtotal >= 120,000đ)
    const [prod] = dbHelper.query('SELECT id FROM products WHERE is_available = 1 AND base_price >= 60000 LIMIT 1');
    await cust.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    const app1 = await cust.post('/api/vouchers.php', { action: 'apply', code: gift.code });
    assert.equal(app1.body?.success, true, `Apply upgrade voucher failed: ${app1.text}`);

    const chk1 = await fetch('http://127.0.0.1:5000/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Cookie': cust.customerCookie },
      body: new URLSearchParams({
        customer_name: 'Upgrade Tester',
        customer_phone: '0912345678',
        customer_address: '123 Le Duan, BMT',
        paymentMethod: 'cod'
      }).toString(),
      redirect: 'manual'
    });
    assert.equal(chk1.status, 302, '1st use of upgrade voucher must succeed');

    // Verify 2nd use is STRICTLY blocked & code is hidden with xxxx in wallet
    const syncAfter = await cust.get('/api/vouchers.php?action=sync_state');
    assert.equal(Number(syncAfter.body.data.wallet_count), 0, 'wallet_count must drop 1 -> 0 after 1st use');
    const usedItem = syncAfter.body.data.wallet_vouchers.find(v => Number(v.id) === Number(gift.voucher_id));
    assert.equal(usedItem?.is_used, true);
    assert.equal(usedItem?.is_code_hidden, true);
    assert.ok(!String(usedItem?.code || '').includes(gift.code), 'Real UP-GOLD code must be hidden after 1st use');

    await cust.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    const app2 = await cust.post('/api/vouchers.php', { action: 'apply', code: gift.code });
    assert.equal(app2.body?.success, false, '2nd attempt to apply upgrade voucher must fail');
    const cartApp2 = await cust.post('/api/cart.php', { action: 'apply_coupon', code: gift.code });
    assert.equal(cartApp2.body?.success, false, '2nd attempt via cart.php must fail');
  });

  it('2. Organic spend order upgrade (BRONZE -> SILVER) via processOrderEarnPoints auto-gifts 1 UP-SILVER voucher', async () => {
    const email = fixtures.generateTestEmail('upg_order');
    const phone = '09' + String(Math.floor(10000000 + Math.random() * 89999999));
    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
       VALUES (2, 'Organic Silver Tester', ?, ?, ?, 'active', 'BRONZE', 0, 800000, NOW())`,
      [email, phone, pwdHash]
    );
    const uRow = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [email]);
    const userId = Number(uRow[0].id);
    createdUserIds.push(userId);

    const ordCode = 'UPORD-' + Date.now();
    dbHelper.query(
      `INSERT INTO orders (order_code, customer_id, restaurant_id, delivery_address, recipient_name, recipient_phone, subtotal, shipping_fee, total_amount, order_status)
       VALUES (?, ?, 1, 'Test Addr', 'Organic Tester', '0912345678', 300000, 0, 300000, 'completed')`,
      [ordCode, userId]
    );
    const ordRows = dbHelper.query('SELECT id FROM orders WHERE order_code = ? LIMIT 1', [ordCode]);
    const orderId = Number(ordRows[0].id);

    const out = execFileSync('php', [
      '-r',
      `require 'helpers/loyalty.php'; echo json_encode(processOrderEarnPoints(${orderId}));`
    ], { cwd: PROJECT_ROOT, encoding: 'utf8', windowsHide: true }).trim();

    const parsed = JSON.parse(out);
    assert.equal(parsed.success, true);
    assert.equal(parsed.new_tier, 'SILVER');
    assert.ok(parsed.tier_upgrade_voucher?.code?.startsWith(`UP-SILVER-${userId}-`), `Expected UP-SILVER voucher: ${out}`);
    createdVoucherIds.push(Number(parsed.tier_upgrade_voucher.voucher_id));
  });
});
