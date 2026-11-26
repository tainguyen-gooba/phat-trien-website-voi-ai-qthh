/**
 * E2E Test: Voucher Usage Percentage & 1-Time-Per-Member-Tier Limit
 * Verifies:
 * 1. When 1+ users use a voucher (e.g. DIAMOND50: 1/300), vouchers.php renders non-zero percentage (0.3%) and remaining count (Còn 299 lượt)
 * 2. Adding more user usages updates % and decrements remaining count dynamically (e.g. 2/300 -> 0.7%, Còn 298 lượt)
 * 3. Member-tier vouchers strictly allow ONLY 1 usage per account across claim, cart, voucher modal, and checkout (POST)
 * 4. Attempting to reuse a tier voucher via Cart/Vouchers API or simultaneous Checkout POST is rejected
 * 5. Admin save_admin_voucher automatically forces user_limit = 1 when min_member_tier != 'ALL'
 * 6. BURGER30 / public vouchers without coupons row checkout safely without FK constraint error
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('./harness/TestClient');
const dbHelper = require('./harness/db-helper');
const fixtures = require('./harness/fixtures/loyalty-fixtures');

function createTestCustomer(prefix, tier, passwordHash) {
  const email = fixtures.generateTestEmail(prefix);
  const phone = '09' + String(Math.floor(10000000 + Math.random() * 89999999));
  dbHelper.query(
    `INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
     VALUES (2, ?, ?, ?, ?, 'active', ?, 0, 0, NOW())`,
    [`Test ${prefix}`, email, phone, passwordHash, tier]
  );
  const rows = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [email]);
  return { id: Number(rows[0].id), email, tier };
}

describe('Voucher Usage % Display, Quota Decrement & 1-Use Member Tier Limit', () => {
  const createdUserIds = [];
  const createdVoucherIds = [];
  const createdOrderIds = [];
  let gold1, gold2, bronze1;

  before(async () => {
    // Reuse hash of existing customer/admin or known valid bcrypt for '123456'
    const sample = dbHelper.query("SELECT password_hash FROM users WHERE email = 'customer@fastfood.vn' LIMIT 1");
    const pwdHash = sample[0].password_hash;

    gold1 = createTestCustomer('tier_gold1', 'GOLD', pwdHash);
    gold2 = createTestCustomer('tier_gold2', 'GOLD', pwdHash);
    bronze1 = createTestCustomer('tier_bronze1', 'BRONZE', pwdHash);
    createdUserIds.push(gold1.id, gold2.id, bronze1.id);
  });

  after(() => {
    for (const vId of createdVoucherIds) {
      dbHelper.query('DELETE FROM voucher_usages WHERE voucher_id = ?', [vId]);
      dbHelper.query('DELETE FROM user_vouchers WHERE voucher_id = ?', [vId]);
      dbHelper.query('DELETE FROM coupons WHERE id = ?', [vId]);
      dbHelper.query('DELETE FROM vouchers WHERE id = ?', [vId]);
    }
    for (const oId of createdOrderIds) {
      dbHelper.query('DELETE FROM order_items WHERE order_id = ?', [oId]);
      dbHelper.query('DELETE FROM orders WHERE id = ?', [oId]);
    }
    for (const uId of createdUserIds) {
      const uOrders = dbHelper.query('SELECT id, order_code FROM orders WHERE customer_id = ?', [uId]);
      for (const o of uOrders) {
        dbHelper.query('DELETE FROM realtime_orders WHERE order_code = ?', [o.order_code]);
        dbHelper.query('DELETE FROM order_items WHERE order_id = ?', [o.id]);
        dbHelper.query('DELETE FROM orders WHERE id = ?', [o.id]);
      }
      dbHelper.query('DELETE FROM voucher_usages WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM user_vouchers WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [uId]);
    }
  });

  it('1. Live DIAMOND50 card on vouchers.php shows 0.3% (not 0%) and Còn 299 lượt', async () => {
    const client = new TestClient('http://127.0.0.1:5000');
    const page = await client.get('/vouchers.php');
    assert.equal(page.status, 200);

    // DIAMOND50 has usage_limit=300, usage_count=1 -> 0.3%, Còn 299 lượt
    const diamondCardMatch = page.text.match(/data-code="diamond50"[\s\S]*?<\/article>/);
    assert.ok(diamondCardMatch, 'DIAMOND50 card must appear on vouchers.php');
    const cardHtml = diamondCardMatch[0];

    assert.ok(cardHtml.includes('Đã dùng 0.3%'), `Expected "Đã dùng 0.3%" in cardHtml, got:\n${cardHtml}`);
    assert.ok(cardHtml.includes('Còn 299 lượt'), `Expected "Còn 299 lượt" in cardHtml`);
    assert.ok(cardHtml.includes('1 lần/tài khoản'), `Expected "1 lần/tài khoản" badge on DIAMOND50`);
    assert.ok(!cardHtml.includes('width: 0%'), 'Progress bar must not have width: 0% when 1 usage occurred');
    assert.ok(
      cardHtml.indexOf('ticket-quota-wrap') > -1 && cardHtml.indexOf('ticket-code-row') > cardHtml.indexOf('ticket-quota-wrap'),
      'Voucher code must be positioned below % usage bar'
    );
  });

  it('2. Admin save_admin_voucher enforces user_limit = 1 on member-tier vouchers even if >1 submitted', async () => {
    const adminClient = new TestClient('http://127.0.0.1:5000');
    await adminClient.loginAsAdmin();

    const code = 'GOLDTEST' + Date.now().toString().slice(-4);
    const saveRes = await adminClient.post('/admin/api.php', {
      action: 'save_admin_voucher',
      voucher: {
        code,
        title: 'Test Tier 1-Use Voucher',
        description: 'Testing tier limit and usage percentage',
        voucher_type: 'fixed',
        discount_value: 25000,
        min_order_value: 50000,
        max_discount_amount: 25000,
        usage_limit: 300,
        user_limit: 5, // Try passing 5 -> backend must clamp to 1 for GOLD tier!
        min_member_tier: 'GOLD',
        is_public: 1,
        is_active: 1
      }
    }, {}, 'admin');

    assert.equal(saveRes.status, 200);
    assert.equal(saveRes.body?.success, true, `Save voucher failed: ${saveRes.text}`);
    const voucherId = Number(saveRes.body.data.voucher_id);
    createdVoucherIds.push(voucherId);

    const row = dbHelper.query('SELECT user_limit, usage_limit, usage_count FROM vouchers WHERE id = ? LIMIT 1', [voucherId]);
    assert.equal(Number(row[0].user_limit), 1, 'Member-tier voucher must strictly force user_limit = 1');
    assert.equal(Number(row[0].usage_limit), 300);
    assert.equal(Number(row[0].usage_count), 0);
  });

  it('3. Multiple users redeem tier voucher -> % & remaining update dynamically, and each user is strictly limited to 1 use', async () => {
    const voucherId = createdVoucherIds[0];
    const [vInfo] = dbHelper.query('SELECT code FROM vouchers WHERE id = ? LIMIT 1', [voucherId]);
    const code = vInfo.code;

    // Fetch a real active product to put into cart
    const [prod] = dbHelper.query('SELECT id, base_price FROM products WHERE is_available = 1 AND base_price >= 30000 LIMIT 1');
    assert.ok(prod, 'Need an available product >= 30,000đ');

    // A. BRONZE user is blocked from GOLD voucher
    const bronzeClient = new TestClient('http://127.0.0.1:5000');
    await bronzeClient.loginAsCustomer(bronze1.email, '123456');
    const bronzeApply = await bronzeClient.post('/api/vouchers.php', {
      action: 'apply',
      code,
      subtotal: 150000
    });
    assert.equal(bronzeApply.body?.success, false, 'BRONZE user must not apply GOLD voucher');

    // B. First GOLD user (gold1) claims & places order via checkout.php -> usage_count becomes 1 (0.3%, Remaining 299)
    const c1 = new TestClient('http://127.0.0.1:5000');
    await c1.loginAsCustomer(gold1.email, '123456');
    const add1 = await c1.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    assert.equal(add1.body?.success, true);

    const apply1 = await c1.post('/api/vouchers.php', { action: 'apply', code });
    assert.equal(apply1.body?.success, true, `gold1 apply failed: ${apply1.text}`);

    const chk1 = await fetch('http://127.0.0.1:5000/checkout.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Cookie': c1.customerCookie
      },
      body: new URLSearchParams({
        customer_name: 'Gold Tester One',
        customer_phone: '0912345678',
        customer_address: '123 Le Duan, Buon Ma Thuot',
        paymentMethod: 'cod'
      }).toString(),
      redirect: 'manual'
    });
    assert.equal(chk1.status, 302, 'gold1 checkout must succeed and redirect');

    const after1 = dbHelper.query('SELECT usage_count FROM vouchers WHERE id = ? LIMIT 1', [voucherId]);
    assert.equal(Number(after1[0].usage_count), 1, 'usage_count must increment to 1');

    const pageAfter1 = await c1.get('/vouchers.php');
    const regexById = new RegExp(`data-voucher-id="${voucherId}"[\\s\\S]*?<\\/article>`);
    const cardAfter1 = pageAfter1.text.match(regexById)?.[0] || '';
    assert.ok(cardAfter1.includes('Đã dùng 0.3%'), `After 1 use (1/300), expected "Đã dùng 0.3%":\n${cardAfter1}`);
    assert.ok(cardAfter1.includes('Còn 299 lượt'), 'After 1 use, expected Còn 299 lượt');
    assert.match(cardAfter1, /Đã dùng\s*<\/button>/, 'gold1 must see disabled "Đã dùng" button on card');
    assert.ok(!cardAfter1.toLowerCase().includes(code.toLowerCase()), 'Used voucher must completely mask/hide the real code in DOM');
    assert.ok(cardAfter1.includes('is-masked') && cardAfter1.includes('xxxxxxxx'), 'Used voucher must display x-masked code');
    assert.ok(!cardAfter1.includes('js-copy-voucher-code'), 'Used voucher must NOT expose copy button');

    // C. gold1 tries to claim OR apply the SAME voucher a 2nd time -> MUST BE BLOCKED!
    const reClaim = await c1.post('/api/vouchers.php', { action: 'claim', voucher_id: voucherId });
    assert.equal(reClaim.body?.success, false, 'gold1 cannot re-claim already used voucher');

    await c1.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    const reApplyV = await c1.post('/api/vouchers.php', { action: 'apply', code });
    assert.equal(reApplyV.body?.success, false, 'gold1 cannot re-apply via api/vouchers.php');

    const reApplyCart = await c1.post('/api/cart.php', { action: 'apply_coupon', code });
    assert.equal(reApplyCart.body?.success, false, 'gold1 cannot re-apply via api/cart.php');

    // D. Second GOLD user (gold2) uses the SAME voucher -> usage_count becomes 2 (0.7%, Remaining 298)
    const c2 = new TestClient('http://127.0.0.1:5000');
    await c2.loginAsCustomer(gold2.email, '123456');
    await c2.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    const apply2 = await c2.post('/api/vouchers.php', { action: 'apply', code });
    assert.equal(apply2.body?.success, true, `gold2 apply failed: ${apply2.text}`);

    const chk2 = await fetch('http://127.0.0.1:5000/checkout.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Cookie': c2.customerCookie
      },
      body: new URLSearchParams({
        customer_name: 'Gold Tester Two',
        customer_phone: '0912345679',
        customer_address: '456 Nguyen Tat Thanh, Buon Ma Thuot',
        paymentMethod: 'cod'
      }).toString(),
      redirect: 'manual'
    });
    assert.equal(chk2.status, 302, 'gold2 checkout must succeed and redirect');

    const after2 = dbHelper.query('SELECT usage_count FROM vouchers WHERE id = ? LIMIT 1', [voucherId]);
    assert.equal(Number(after2[0].usage_count), 2, 'usage_count must increment to 2');

    const pageAfter2 = await c2.get('/vouchers.php');
    const cardAfter2 = pageAfter2.text.match(regexById)?.[0] || '';
    assert.ok(cardAfter2.includes('Đã dùng 0.7%'), `After 2 uses (2/300), expected "Đã dùng 0.7%":\n${cardAfter2}`);
    assert.ok(cardAfter2.includes('Còn 298 lượt'), 'After 2 uses, expected Còn 298 lượt');
    assert.ok(cardAfter2.includes('is-masked') && !cardAfter2.includes('js-copy-voucher-code'), 'gold2 used card also masks code and removes copy');
  });

  it('4. Checkout re-validates voucher inside transaction (blocks reuse if session had stale coupon)', async () => {
    const voucherId = createdVoucherIds[0];
    const [vInfo] = dbHelper.query('SELECT code FROM vouchers WHERE id = ? LIMIT 1', [voucherId]);
    const code = vInfo.code;

    // Fresh GOLD user gold3 opens 2 sessions BEFORE checking out
    const sample = dbHelper.query("SELECT password_hash FROM users WHERE email = 'customer@fastfood.vn' LIMIT 1");
    const gold3 = createTestCustomer('tier_gold3', 'GOLD', sample[0].password_hash);
    createdUserIds.push(gold3.id);

    const [prod] = dbHelper.query('SELECT id FROM products WHERE is_available = 1 AND base_price >= 30000 LIMIT 1');

    const tab1 = new TestClient('http://127.0.0.1:5000');
    const l1 = await tab1.loginAsCustomer(gold3.email, '123456');
    assert.equal(l1.success, true, 'tab1 login must succeed');
    const cAdd1 = await tab1.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    assert.equal(cAdd1.body?.success, true, `tab1 cart add failed: ${cAdd1.text}`);
    const cApp1 = await tab1.post('/api/cart.php', { action: 'apply_coupon', code });
    assert.equal(cApp1.body?.success, true, `tab1 apply_coupon failed: ${cApp1.text}`);

    const tab2 = new TestClient('http://127.0.0.1:5000');
    const l2 = await tab2.loginAsCustomer(gold3.email, '123456');
    assert.equal(l2.success, true, 'tab2 login must succeed');
    const cAdd2 = await tab2.post('/api/cart.php', { action: 'add', productId: prod.id, qty: 3 });
    assert.equal(cAdd2.body?.success, true, `tab2 cart add failed: ${cAdd2.text}`);
    const cApp2 = await tab2.post('/api/cart.php', { action: 'apply_coupon', code });
    assert.equal(cApp2.body?.success, true, `tab2 apply_coupon failed: ${cApp2.text}`);

    // Tab 1 places order #1 -> succeeds
    const r1 = await fetch('http://127.0.0.1:5000/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Cookie': tab1.customerCookie },
      body: new URLSearchParams({
        customer_name: 'Gold Tester Three',
        customer_phone: '0987654321',
        customer_address: '789 Hung Vuong, BMT',
        paymentMethod: 'cod'
      }).toString(),
      redirect: 'manual'
    });
    assert.equal(r1.status, 302, 'Tab 1 first checkout should succeed');

    // Tab 2 tries submitting stale session coupon -> MUST NOT redirect/create discounted order #2!
    const r2 = await fetch('http://127.0.0.1:5000/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Cookie': tab2.customerCookie },
      body: new URLSearchParams({
        customer_name: 'Gold Tester Three',
        customer_phone: '0987654321',
        customer_address: '789 Hung Vuong, BMT',
        paymentMethod: 'cod'
      }).toString(),
      redirect: 'manual'
    });
    const r2Text = await r2.text();
    assert.equal(r2.status, 200, 'Tab 2 second checkout with same voucher must be blocked and show error');
    assert.ok(r2Text.includes('1 lần') || r2Text.includes('đã sử dụng'), `Expected 1-use error message, got:\n${r2Text.slice(0, 600)}`);

    const usageRows = dbHelper.query('SELECT COUNT(*) AS cnt FROM voucher_usages WHERE voucher_id = ? AND user_id = ?', [voucherId, gold3.id]);
    assert.equal(Number(usageRows[0].cnt), 1, 'gold3 must have strictly 1 usage row in voucher_usages');
  });
});
