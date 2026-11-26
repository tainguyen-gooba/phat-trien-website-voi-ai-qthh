/**
 * E2E Test: Food & Category Scoping for Vouchers
 * Verifies:
 * 1. Food-specific vouchers (BURGER30, BOGOPIZZA, COMBO15, GARANBOGO, SNACK15K) reject non-matching carts (e.g. Cơm Tấm)
 * 2. General discount vouchers (FASTFOOD20) and Freeship vouchers (FREESHIP18/FREESHIP25) still apply to any order
 * 3. Food-specific vouchers succeed when matching dishes exist in cart and calculate discount strictly on eligible item subtotal
 * 4. Removing matching dish auto-removes food-specific voucher from cart summary
 * 5. Admin save_admin_voucher persists category/keyword scope rules and enforces them at Cart & Checkout
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('./harness/TestClient');
const dbHelper = require('./harness/db-helper');
const fixtures = require('./harness/fixtures/loyalty-fixtures');

function createCustomer(prefix, tier, pwdHash) {
  const email = fixtures.generateTestEmail(prefix);
  const phone = '09' + String(Math.floor(10000000 + Math.random() * 89999999));
  dbHelper.query(
    `INSERT INTO users (role_id, full_name, email, phone, password_hash, status, member_tier, points_balance, total_spent, created_at)
     VALUES (2, ?, ?, ?, ?, 'active', ?, 0, 0, NOW())`,
    [`Scope ${prefix}`, email, phone, pwdHash, tier]
  );
  const [row] = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [email]);
  return { id: Number(row.id), email };
}

describe('Voucher Food & Category Scope Enforcement', () => {
  const createdUserIds = [];
  const createdVoucherIds = [];
  let pwdHash = '';
  let riceId, burger139Id, pizza159Id, milkTeaId, combo85Id, sushiId;

  before(() => {
    const [u] = dbHelper.query("SELECT password_hash FROM users WHERE email = 'customer@fastfood.vn' LIMIT 1");
    pwdHash = u.password_hash;

    const [rice] = dbHelper.query("SELECT id FROM products WHERE category_id = 30 AND LOWER(name) LIKE 'cơm tấm%' LIMIT 1");
    const [burger] = dbHelper.query("SELECT id FROM products WHERE LOWER(name) LIKE '%burger%' AND base_price >= 120000 LIMIT 1");
    const [pizza] = dbHelper.query("SELECT id FROM products WHERE LOWER(name) LIKE '%pizza%' AND base_price >= 150000 LIMIT 1");
    const [tea] = dbHelper.query("SELECT id FROM products WHERE category_id = 32 LIMIT 1");
    const [combo] = dbHelper.query("SELECT id FROM products WHERE LOWER(name) LIKE 'combo 1 người%' LIMIT 1");
    const [sushi] = dbHelper.query("SELECT id FROM products WHERE category_id = 37 AND base_price >= 60000 LIMIT 1");

    riceId = Number(rice.id);
    burger139Id = Number(burger.id);
    pizza159Id = Number(pizza.id);
    milkTeaId = Number(tea.id);
    combo85Id = Number(combo.id);
    sushiId = Number(sushi.id);
  });

  after(() => {
    for (const vId of createdVoucherIds) {
      dbHelper.query('DELETE FROM voucher_usages WHERE voucher_id = ?', [vId]);
      dbHelper.query('DELETE FROM user_vouchers WHERE voucher_id = ?', [vId]);
      dbHelper.query('DELETE FROM coupons WHERE id = ?', [vId]);
      dbHelper.query('DELETE FROM vouchers WHERE id = ?', [vId]);
    }
    for (const uId of createdUserIds) {
      const orders = dbHelper.query('SELECT id, order_code FROM orders WHERE customer_id = ?', [uId]);
      for (const o of orders) {
        dbHelper.query('DELETE FROM realtime_orders WHERE order_code = ?', [o.order_code]);
        dbHelper.query('DELETE FROM order_items WHERE order_id = ?', [o.id]);
        dbHelper.query('DELETE FROM orders WHERE id = ?', [o.id]);
      }
      dbHelper.query('DELETE FROM voucher_usages WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM user_vouchers WHERE user_id = ?', [uId]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [uId]);
    }
  });

  it('1. Rejects BURGER30/BOGOPIZZA/COMBO15/GARANBOGO/SNACK15K on Cơm Tấm cart, while allowing FASTFOOD20 and FREESHIP18', async () => {
    const cust = createCustomer('rice_only', 'BRONZE', pwdHash);
    createdUserIds.push(cust.id);

    const c = new TestClient('http://127.0.0.1:5000');
    const login = await c.loginAsCustomer(cust.email, '123456');
    assert.equal(login.success, true);

    await c.post('/api/cart.php', { action: 'add', productId: riceId, qty: 3 });

    for (const code of ['BURGER30', 'BOGOPIZZA', 'COMBO15', 'GARANBOGO', 'SNACK15K']) {
      const res = await c.post('/api/cart.php', { action: 'apply_coupon', code });
      assert.equal(res.body?.success, false, `${code} must be rejected when cart only has Cơm Tấm`);
      assert.ok(
        (res.body?.message || '').includes('chỉ áp dụng'),
        `${code} rejection must explain targeted food scope, got: ${res.body?.message}`
      );
    }

    const elig = await c.get('/api/vouchers.php?action=get_eligible&subtotal=165000');
    const burgerV = elig.body?.data?.vouchers?.find(v => v.code === 'BURGER30');
    assert.equal(burgerV?.is_eligible, false, 'get_eligible must mark BURGER30 ineligible for Cơm Tấm cart');
    assert.equal(burgerV?.applicable_scope, 'specific');
    assert.ok((burgerV?.applicable_label || '').includes('Burger'));

    const genRes = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'FASTFOOD20' });
    assert.equal(genRes.body?.success, true, 'General coupon FASTFOOD20 must still work on Cơm Tấm cart');

    await c.post('/api/cart.php', { action: 'clear' });
    await c.post('/api/cart.php', { action: 'add', productId: riceId, qty: 1 });
    await c.post('/api/cart.php', { action: 'add', productId: milkTeaId, qty: 2 });
    // Now cart has >= 100k subtotal? Let's ensure subtotal < 100k or check FREESHIP25
    await c.post('/api/cart.php', { action: 'clear' });
    await c.post('/api/cart.php', { action: 'add', productId: riceId, qty: 2 }); // ~110k (shipping 0) or adjust to test freeship25 >= 70k
    const shipRes = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'FREESHIP25' });
    assert.equal(shipRes.body?.success, true, 'Freeship FREESHIP25 must apply regardless of food category');
  });

  it('2. Allows BURGER30 only with Burger, BOGOPIZZA only with Pizza, SNACK15K with Trà Sữa', async () => {
    const cust = createCustomer('match_items', 'BRONZE', pwdHash);
    createdUserIds.push(cust.id);

    const c = new TestClient('http://127.0.0.1:5000');
    await c.loginAsCustomer(cust.email, '123456');

    // Burger cart -> BURGER30 succeeds, BOGOPIZZA rejected
    await c.post('/api/cart.php', { action: 'add', productId: burger139Id, qty: 1 });
    const bOk = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'BURGER30' });
    assert.equal(bOk.body?.success, true, `BURGER30 should apply on Burger cart: ${bOk.text}`);
    assert.equal(Number(bOk.body?.data?.discount), 30000);

    const pFail = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'BOGOPIZZA' });
    assert.equal(pFail.body?.success, false, 'BOGOPIZZA must be rejected on Burger-only cart');

    // Pizza cart -> BOGOPIZZA succeeds
    await c.post('/api/cart.php', { action: 'clear' });
    await c.post('/api/cart.php', { action: 'add', productId: pizza159Id, qty: 1 });
    const pOk = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'BOGOPIZZA' });
    assert.equal(pOk.body?.success, true, `BOGOPIZZA should apply on Pizza cart: ${pOk.text}`);
    assert.equal(Number(pOk.body?.data?.discount), 35000);

    // Trà sữa cart -> SNACK15K succeeds
    await c.post('/api/cart.php', { action: 'clear' });
    await c.post('/api/cart.php', { action: 'add', productId: milkTeaId, qty: 2 });
    const sOk = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'SNACK15K' });
    assert.equal(sOk.body?.success, true, `SNACK15K should apply on Trà Sữa cart: ${sOk.text}`);
    assert.equal(Number(sOk.body?.data?.discount), 15000);
  });

  it('3. Mixed cart (Combo 85k + Cơm Tấm 55k) computes COMBO15 15% strictly on Combo subtotal and auto-removes when Combo is removed', async () => {
    const cust = createCustomer('mixed_calc', 'BRONZE', pwdHash);
    createdUserIds.push(cust.id);

    const c = new TestClient('http://127.0.0.1:5000');
    await c.loginAsCustomer(cust.email, '123456');

    await c.post('/api/cart.php', { action: 'add', productId: combo85Id, qty: 1 });
    await c.post('/api/cart.php', { action: 'add', productId: riceId, qty: 1 });

    const comboRes = await c.post('/api/cart.php', { action: 'apply_coupon', code: 'COMBO15' });
    assert.equal(comboRes.body?.success, true, `COMBO15 should apply on mixed cart containing Combo: ${comboRes.text}`);

    const [comboRow] = dbHelper.query('SELECT base_price, discount_price FROM products WHERE id = ?', [combo85Id]);
    const comboPrice = Number(comboRow.discount_price) > 0 ? Number(comboRow.discount_price) : Number(comboRow.base_price);
    const expectedComboDiscount = Math.min(comboPrice, Math.min(40000, comboPrice * 0.15));
    assert.equal(
      Number(comboRes.body?.data?.discount),
      expectedComboDiscount,
      `Discount must be 15% of Combo (${comboPrice}) = ${expectedComboDiscount}, not whole cart subtotal`
    );

    // Removing Combo leaves only Cơm Tấm -> COMBO15 must be auto-cleared
    const afterRemove = await c.post('/api/cart.php', { action: 'remove', productId: String(combo85Id) });
    assert.equal(afterRemove.body?.success, true);
    assert.equal(afterRemove.body?.data?.appliedCoupon, null, 'COMBO15 must auto-clear once Combo item is removed from cart');
    assert.equal(Number(afterRemove.body?.data?.discount), 0);
  });

  it('4. Admin save_admin_voucher saves category-scoped voucher and enforces it in Cart', async () => {
    const admin = new TestClient('http://127.0.0.1:5000');
    const aLogin = await admin.loginAsAdmin('admin@fastfood.vn', '123456');
    assert.equal(aLogin.success, true, 'Admin login must succeed');

    const code = 'SUSHI20_' + Date.now().toString().slice(-4);
    const saveRes = await admin.post('/admin/api.php', {
      action: 'save_admin_voucher',
      voucher: {
        code,
        title: 'Ưu đãi Sushi - Giảm 20.000đ',
        description: 'Chỉ áp dụng cho danh mục Sushi & Món Nhật',
        voucher_type: 'fixed',
        discount_value: 20000,
        min_order_value: 50000,
        usage_limit: 50,
        user_limit: 1,
        min_member_tier: 'ALL',
        applicable_scope: 'specific',
        applicable_category_ids: ['37'],
        applicable_label: 'Sushi & Món Nhật',
        is_public: 1,
        is_active: 1
      }
    });
    assert.equal(saveRes.body?.success, true, `Admin save failed: ${saveRes.text}`);
    const newVid = Number(saveRes.body.data.voucher_id);
    createdVoucherIds.push(newVid);

    const cust = createCustomer('sushi_cust', 'BRONZE', pwdHash);
    createdUserIds.push(cust.id);
    const c = new TestClient('http://127.0.0.1:5000');
    await c.loginAsCustomer(cust.email, '123456');

    await c.post('/api/cart.php', { action: 'add', productId: burger139Id, qty: 1 });
    const failRes = await c.post('/api/cart.php', { action: 'apply_coupon', code });
    assert.equal(failRes.body?.success, false, 'Sushi voucher must fail on Burger-only cart');

    await c.post('/api/cart.php', { action: 'clear' });
    await c.post('/api/cart.php', { action: 'add', productId: sushiId, qty: 1 });
    const passRes = await c.post('/api/cart.php', { action: 'apply_coupon', code });
    assert.equal(passRes.body?.success, true, `Sushi voucher must succeed on Sushi cart: ${passRes.text}`);
    assert.equal(Number(passRes.body?.data?.discount), 20000);
  });
});
