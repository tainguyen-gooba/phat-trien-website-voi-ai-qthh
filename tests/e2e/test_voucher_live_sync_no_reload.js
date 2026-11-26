/**
 * E2E Test: Live No-Reload Voucher Sync, Wallet Addition & Usage Count Updates
 * Verifies:
 * 1. Claiming a voucher immediately increments wallet_count and populates wallet_vouchers in API response
 * 2. Exchanging loyalty points saves into BOTH coupons and vouchers/user_vouchers, increments wallet_count, and sync_state returns it
 * 3. Using a voucher at checkout decrements tab-hunt-badge (hunt_available_count) and tab-wallet-badge (wallet_count) immediately on page/sync_state
 * 4. vouchers.php & js/realtime.js implement live DOM state sync (syncVouchersLiveState) without calling window.location.reload()
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('./harness/TestClient');
const dbHelper = require('./harness/db-helper');
const fixtures = require('./harness/fixtures/loyalty-fixtures');

describe('Live No-Reload Voucher Counts & Wallet Sync', () => {
  let userId;
  let userEmail;
  let publicVoucherId;
  let publicVoucherCode;
  const createdVoucherIds = [];

  before(async () => {
    const sample = dbHelper.query("SELECT password_hash FROM users WHERE email = 'customer@fastfood.vn' LIMIT 1");
    const pwdHash = sample[0].password_hash;
    userEmail = fixtures.generateTestEmail('live_sync');
    const phone = '09' + String(Math.floor(10000000 + Math.random() * 89999999));

    dbHelper.query(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
       VALUES (2, 'Live Sync Tester', ?, ?, ?, 'active', 'GOLD', 250, 0, NOW())`,
      [userEmail, phone, pwdHash]
    );
    const rows = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [userEmail]);
    userId = Number(rows[0].id);

    // Create an isolated public voucher for this test so we don't mutate seeded voucher counts
    publicVoucherCode = 'SYNCTEST' + Date.now().toString().slice(-4);
    dbHelper.query(
      `INSERT INTO vouchers (code, title, description, voucher_type, discount_value, min_order_value, max_discount_amount, usage_limit, usage_count, user_limit, min_member_tier, is_public, is_active, start_date, end_date)
       VALUES (?, 'Sync Test Voucher 25K', 'Test live voucher sync', 'fixed', 25000, 50000, 25000, 100, 0, 1, 'ALL', 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))`,
      [publicVoucherCode]
    );
    const vRows = dbHelper.query('SELECT id FROM vouchers WHERE code = ? LIMIT 1', [publicVoucherCode]);
    publicVoucherId = Number(vRows[0].id);
    createdVoucherIds.push(publicVoucherId);
  });

  after(() => {
    const uOrders = dbHelper.query('SELECT id, order_code FROM orders WHERE customer_id = ?', [userId]);
    for (const o of uOrders) {
      dbHelper.query('DELETE FROM realtime_orders WHERE order_code = ?', [o.order_code]);
      dbHelper.query('DELETE FROM order_items WHERE order_id = ?', [o.id]);
      dbHelper.query('DELETE FROM orders WHERE id = ?', [o.id]);
    }
    const redeemedVouchers = dbHelper.query('SELECT voucher_id FROM user_vouchers WHERE user_id = ?', [userId]);
    for (const r of redeemedVouchers) {
      createdVoucherIds.push(Number(r.voucher_id));
    }
    dbHelper.query('DELETE FROM point_transactions WHERE user_id = ?', [userId]);
    dbHelper.query('DELETE FROM voucher_usages WHERE user_id = ?', [userId]);
    dbHelper.query('DELETE FROM user_vouchers WHERE user_id = ?', [userId]);
    dbHelper.query('DELETE FROM coupons WHERE user_id = ?', [userId]);
    for (const vid of [...new Set(createdVoucherIds)]) {
      dbHelper.query('DELETE FROM vouchers WHERE id = ?', [vid]);
    }
    dbHelper.query('DELETE FROM users WHERE id = ?', [userId]);
  });

  it('1. vouchers.php implements live DOM sync without window.location.reload()', async () => {
    const client = new TestClient('http://127.0.0.1:5000');
    await client.loginAsCustomer(userEmail, '123456');

    const page = await client.get('/vouchers.php');
    assert.equal(page.status, 200);
    assert.ok(page.text.includes('id="tab-hunt-badge"'), 'Must expose #tab-hunt-badge');
    assert.ok(page.text.includes('window.syncVouchersLiveState'), 'Must expose window.syncVouchersLiveState');
    assert.ok(page.text.includes('applyVoucherStateSnapshot'), 'Must implement applyVoucherStateSnapshot');
    const vouchersScriptStart = page.text.indexOf('window.syncVouchersLiveState');
    const vouchersScriptEnd = page.text.indexOf('</script>', vouchersScriptStart);
    const vouchersLiveScript = page.text.slice(vouchersScriptStart, vouchersScriptEnd);
    assert.ok(!vouchersLiveScript.includes('window.location.reload()'), 'vouchers.php live sync script must NOT call window.location.reload()');

    const rtJs = await client.get('/js/realtime.js');
    assert.ok(rtJs.text.includes('window.syncVouchersLiveState'), 'js/realtime.js must call syncVouchersLiveState without reload');
  });

  it('2. Claiming voucher immediately increases wallet_count & returns wallet_vouchers; Exchanging points adds voucher to wallet cleanly', async () => {
    const client = new TestClient('http://127.0.0.1:5000');
    await client.loginAsCustomer(userEmail, '123456');

    const initState = await client.get('/api/vouchers.php?action=sync_state');
    assert.equal(initState.status, 200);
    const baseHunt = Number(initState.body.data.hunt_available_count);
    assert.equal(Number(initState.body.data.wallet_count), 0);

    // A. Claim public voucher -> wallet_count becomes 1, wallet_vouchers contains it immediately
    const claimRes = await client.post('/api/vouchers.php', {
      action: 'claim',
      voucher_id: publicVoucherId
    });
    assert.equal(claimRes.body?.success, true, `Claim failed: ${claimRes.text}`);
    assert.equal(Number(claimRes.body.data.wallet_count), 1);
    assert.ok(
      claimRes.body.data.wallet_vouchers.some(v => v.code === publicVoucherCode),
      'Claimed voucher must appear immediately in wallet_vouchers'
    );

    // B. Redeem 100 points in Tab 3 -> deducts points to 150, adds voucher into user_vouchers & wallet_count becomes 2
    const exchRes = await client.post('/api/loyalty.php', {
      action: 'exchange_voucher',
      package_id: 1
    });
    assert.equal(exchRes.status, 200);
    assert.equal(exchRes.body?.success, true, `Exchange failed: ${exchRes.text}`);
    assert.equal(Number(exchRes.body.data.new_balance), 150);
    assert.equal(Number(exchRes.body.data.wallet_count), 2);
    const redeemedCode = exchRes.body.data.coupon_code;
    assert.ok(redeemedCode, 'Must return redeemed voucher code');

    // C. Verify sync_state reflects 150 pts, wallet_count = 2, and BOTH vouchers inside wallet_vouchers
    const afterExch = await client.get('/api/vouchers.php?action=sync_state');
    assert.equal(Number(afterExch.body.data.user_points), 150);
    assert.equal(Number(afterExch.body.data.wallet_count), 2);
    const walletCodes = afterExch.body.data.wallet_vouchers.map(v => v.code);
    assert.ok(walletCodes.includes(publicVoucherCode));
    assert.ok(walletCodes.includes(redeemedCode), `Expected ${redeemedCode} inside wallet_vouchers: ${walletCodes}`);

    // D. Use the claimed public voucher at checkout -> BOTH wallet_count (2 -> 1) and hunt_available_count (baseHunt -> baseHunt - 1) decrease!
    const [prod] = dbHelper.query('SELECT id FROM products WHERE is_available = 1 AND base_price >= 30000 LIMIT 1');
    await client.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    const appRes = await client.post('/api/vouchers.php', { action: 'apply', code: publicVoucherCode });
    assert.equal(appRes.body?.success, true);

    const chk = await fetch('http://127.0.0.1:5000/checkout.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Cookie': client.customerCookie
      },
      body: new URLSearchParams({
        customer_name: 'Live Sync Tester',
        customer_phone: '0912345678',
        customer_address: '123 Le Duan, BMT',
        paymentMethod: 'cod'
      }).toString(),
      redirect: 'manual'
    });
    assert.equal(chk.status, 302);

    const afterUse = await client.get('/api/vouchers.php?action=sync_state');
    assert.equal(Number(afterUse.body.data.wallet_count), 1, 'Using 1 voucher must decrement wallet_count 2 -> 1');

    const myPubUsed = afterUse.body.data.public_vouchers.find(v => Number(v.id) === publicVoucherId);
    assert.equal(myPubUsed?.is_used, true, 'Used public voucher must become is_used = true');
    const expectedHuntCount = afterUse.body.data.public_vouchers.filter(v => !v.is_used && v.is_available).length;
    assert.equal(
      Number(afterUse.body.data.hunt_available_count),
      expectedHuntCount,
      'hunt_available_count must exclude used public vouchers'
    );

    const usedWalletItem = afterUse.body.data.wallet_vouchers.find(v => Number(v.id) === publicVoucherId);
    assert.ok(usedWalletItem?.is_used, 'Used voucher must be flagged is_used = true');
    assert.equal(usedWalletItem?.is_code_hidden, true, 'Used voucher code must be hidden');

    // E. Live vouchers.php SSR page also matches decreased badge counts cleanly
    const finalHtml = await client.get('/vouchers.php');
    assert.ok(finalHtml.text.includes(`id="tab-hunt-badge">${expectedHuntCount}</span>`));
    assert.ok(finalHtml.text.includes(`id="tab-wallet-badge">1</span>`));
  });
});
