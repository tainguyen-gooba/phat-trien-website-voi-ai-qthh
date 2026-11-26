/**
 * E2E Test: Welcome Voucher 50.000đ Pop-up Flow After Registration
 * Verifies:
 * 1. Registering a new account opens #welcome-voucher-modal on index.php without pre-granting a voucher
 * 2. If user skips/dismisses without submitting info -> NO 50k voucher granted & manual NEW50K code blocked
 * 3. Submitting invalid CCCD/address returns HTTP 400
 * 4. Submitting valid info grants unique NEW50K-XXXXXX (50.000đ), saves to coupons + vouchers + user_vouchers + claims
 * 5. Granted 50k voucher appears in Voucher Wallet & Cart/Checkout Voucher Picker and applies 50,000đ discount
 * 6. Duplicate CCCD across different new account is rejected
 */

const { describe, it, after } = require('node:test');
const assert = require('node:assert/strict');
const dbHelper = require('./harness/db-helper');

const BASE_URL = (process.env.TEST_BASE_URL || 'http://127.0.0.1:5000').replace(/\/+$/, '');

function extractSessCookie(res, existingCookie = '') {
  const setCookie = res.headers.get('set-cookie') || '';
  const match = setCookie.match(/FASTFOOD_SESSID=([^;]+)/i);
  return match ? `FASTFOOD_SESSID=${match[1]}` : existingCookie;
}

describe('Post-Registration Welcome Voucher 50K Pop-up & Enforcement', () => {
  const createdUserIds = [];
  const createdCouponIds = [];
  const ts = Date.now().toString().slice(-6);
  const rand = Math.floor(Math.random() * 90 + 10);

  after(async () => {
    for (const cid of createdCouponIds) {
      dbHelper.query('DELETE FROM user_vouchers WHERE voucher_id = ?', [cid]);
      dbHelper.query('DELETE FROM vouchers WHERE id = ?', [cid]);
      dbHelper.query('DELETE FROM coupons WHERE id = ?', [cid]);
    }
    for (const uid of createdUserIds) {
      dbHelper.query('DELETE FROM customer_voucher_claims WHERE user_id = ?', [uid]);
      dbHelper.query('DELETE FROM notifications WHERE user_id = ?', [uid]);
      dbHelper.query('DELETE FROM user_vouchers WHERE user_id = ?', [uid]);
      dbHelper.query('DELETE FROM coupons WHERE user_id = ?', [uid]);
      dbHelper.query('DELETE FROM users WHERE id = ?', [uid]);
    }
  });

  async function registerUser(fullName, email, phone) {
    const form = new URLSearchParams({
      fullName,
      email,
      phone,
      password: 'Password123!',
      passwordConfirm: 'Password123!'
    });
    const res = await fetch(`${BASE_URL}/register.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: form.toString(),
      redirect: 'manual'
    });
    const cookie = extractSessCookie(res);
    const rows = dbHelper.query('SELECT id FROM users WHERE email = ? LIMIT 1', [email]);
    assert.ok(rows.length === 1, `User ${email} must be created`);
    const userId = Number(rows[0].id);
    createdUserIds.push(userId);
    return { res, cookie, userId };
  }

  it('1. New registration triggers #welcome-voucher-modal pop-up without auto-granting voucher', async () => {
    const emailA = `welcome_a_${ts}${rand}@test.local`;
    const phoneA = `091${ts}${rand}`.slice(0, 10);
    const { res, cookie, userId } = await registerUser('Khách Mới A', emailA, phoneA);

    assert.equal(res.status, 302, 'register.php should redirect on success');
    assert.ok(cookie, 'Registration should set FASTFOOD_SESSID cookie');

    // Verify NO voucher issued yet
    const claims = dbHelper.query('SELECT id FROM customer_voucher_claims WHERE user_id = ?', [userId]);
    const uv = dbHelper.query('SELECT id FROM user_vouchers WHERE user_id = ?', [userId]);
    assert.equal(claims.length, 0, 'Must NOT have claim before submitting modal info');
    assert.equal(uv.length, 0, 'Must NOT have voucher in wallet before submitting modal info');

    // Load index.php -> pop-up must render in HTML
    const homeRes = await fetch(`${BASE_URL}/index.php`, {
      headers: { Cookie: cookie }
    });
    const html = await homeRes.text();
    assert.equal(homeRes.status, 200);
    assert.ok(html.includes('id="welcome-voucher-modal"'), 'Pop-up #welcome-voucher-modal must be present on page');
    assert.ok(html.includes('id="welcome-voucher-claim-form"'), 'Pop-up must include customer info form');
    assert.ok(html.includes('id="voucher-dob"'), 'Pop-up must include date_of_birth field');
    assert.ok(!html.includes('id="voucher-cccd"'), 'CCCD field must be replaced by date_of_birth');
    assert.ok(html.includes('id="voucher-address"'), 'Pop-up must include Address field');
  });

  it('2. Skipping pop-up without entering info grants ZERO 50k voucher and blocks manual NEW50K', async () => {
    const emailB = `welcome_skip_${ts}${rand}@test.local`;
    const phoneB = `092${ts}${rand}`.slice(0, 10);
    const { cookie, userId } = await registerUser('Khách Bỏ Qua', emailB, phoneB);

    // Confirm pop-up shows initially
    const home1 = await (await fetch(`${BASE_URL}/index.php`, { headers: { Cookie: cookie } })).text();
    assert.ok(home1.includes('id="welcome-voucher-modal"'), 'Pop-up should show before skipping');

    // User dismisses / skips pop-up
    const skipRes = await fetch(`${BASE_URL}/api/claim-voucher.php?action=skip_welcome`, {
      method: 'POST',
      headers: { Cookie: cookie, 'X-Requested-With': 'XMLHttpRequest' }
    });
    const skipJson = await skipRes.json();
    assert.equal(skipJson.success, true);

    // Pop-up no longer renders on reload
    const home2 = await (await fetch(`${BASE_URL}/index.php`, { headers: { Cookie: cookie } })).text();
    assert.ok(!home2.includes('id="welcome-voucher-modal"'), 'Pop-up must not render after skip');

    // Verify NO voucher exists in wallet or coupons for User B
    const uv = dbHelper.query('SELECT id FROM user_vouchers WHERE user_id = ?', [userId]);
    const cp = dbHelper.query('SELECT id FROM coupons WHERE user_id = ?', [userId]);
    assert.equal(uv.length, 0);
    assert.equal(cp.length, 0);

    // Verify User B cannot bypass by typing NEW50K in cart or voucher API
    await fetch(`${BASE_URL}/api/cart.php`, {
      method: 'POST',
      headers: { Cookie: cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add', productId: 1, qty: 2 })
    });
    const applyCartRes = await fetch(`${BASE_URL}/api/cart.php`, {
      method: 'POST',
      headers: { Cookie: cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'apply_coupon', code: 'NEW50K' })
    });
    const applyCartJson = await applyCartRes.json();
    assert.equal(applyCartJson.success, false, 'Must reject manual NEW50K when info was not submitted');

    const applyVcRes = await fetch(`${BASE_URL}/api/vouchers.php`, {
      method: 'POST',
      headers: { Cookie: cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'apply', code: 'NEW50K', subtotal: 120000 })
    });
    const applyVcJson = await applyVcRes.json();
    assert.equal(applyVcJson.success, false, 'Voucher API must also reject NEW50K without claim');
  });

  it('3. Submitting valid date_of_birth grants 50k voucher, saves DOB, adds to wallet & cart, and blocks duplicate phone', async () => {
    const emailC = `welcome_ok_${ts}${rand}@test.local`;
    const phoneC = `093${ts}${rand}`.slice(0, 10);
    const dobC = '2000-05-15';
    const { cookie, userId } = await registerUser('Khách Nhập Đủ', emailC, phoneC);

    // Future date_of_birth must be rejected (HTTP 400)
    const futureRes = await fetch(`${BASE_URL}/api/claim-voucher.php`, {
      method: 'POST',
      headers: { Cookie: cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fullName: 'Khách Nhập Đủ',
        phone: phoneC,
        date_of_birth: '2099-01-01',
        address: '16 Hoàng Diệu, Buôn Ma Thuột'
      })
    });
    assert.equal(futureRes.status, 400);

    // Invalid calendar date must be rejected (HTTP 400)
    const badCalRes = await fetch(`${BASE_URL}/api/claim-voucher.php`, {
      method: 'POST',
      headers: { Cookie: cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fullName: 'Khách Nhập Đủ',
        phone: phoneC,
        date_of_birth: '2000-02-30',
        address: '16 Hoàng Diệu, Buôn Ma Thuột'
      })
    });
    assert.equal(badCalRes.status, 400);

    // Valid submit
    const claimRes = await fetch(`${BASE_URL}/api/claim-voucher.php`, {
      method: 'POST',
      headers: { Cookie: cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fullName: 'Khách Nhập Đủ',
        phone: phoneC,
        date_of_birth: dobC,
        address: '16 Hoàng Diệu, Buôn Ma Thuột'
      })
    });
    const claimJson = await claimRes.json();
    assert.equal(claimRes.status, 200);
    assert.equal(claimJson.success, true);

    // Verify date_of_birth saved in users table
    const userRows = dbHelper.query('SELECT date_of_birth FROM users WHERE id = ?', [userId]);
    assert.equal(userRows[0].date_of_birth, dobC, 'users.date_of_birth must be saved');

    const grantedVoucher = claimJson.data?.voucher;
    assert.ok(grantedVoucher?.code?.startsWith('NEW50K-'));
    assert.equal(Number(grantedVoucher.discount_value), 50000);
    createdCouponIds.push(Number(grantedVoucher.id));

    // Must appear in Wallet (vouchers.php)
    const walletRes = await fetch(`${BASE_URL}/api/vouchers.php?action=get_wallet`, {
      headers: { Cookie: cookie }
    });
    const walletJson = await walletRes.json();
    assert.equal(walletJson.success, true);
    const inWallet = walletJson.data.vouchers.find(v => v.code === grantedVoucher.code);
    assert.ok(inWallet, 'Claimed 50k voucher must appear in Voucher của tôi wallet');
    assert.equal(Number(inWallet.discount_value), 50000);

    // Must appear as eligible & pre-applied in Cart/Checkout Picker Modal
    const eligRes = await fetch(`${BASE_URL}/api/vouchers.php?action=get_eligible&subtotal=120000`, {
      headers: { Cookie: cookie }
    });
    const eligJson = await eligRes.json();
    const inPicker = eligJson.data.vouchers.find(v => v.code === grantedVoucher.code);
    assert.ok(inPicker, 'Claimed 50k voucher must appear in Cart/Checkout picker');
    assert.equal(inPicker.is_eligible, true);
    assert.equal(inPicker.is_applied, true);
    assert.equal(Number(inPicker.estimated_discount), 50000);

    // Different user sharing same birthday WITH different phone must succeed!
    const emailSameDob = `welcome_samedob_${ts}${rand}@test.local`;
    const phoneSameDob = `097${ts}${rand}`.slice(0, 10);
    const userSameDob = await registerUser('Khách Cùng Sinh Nhật', emailSameDob, phoneSameDob);
    const sameDobRes = await fetch(`${BASE_URL}/api/claim-voucher.php`, {
      method: 'POST',
      headers: { Cookie: userSameDob.cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fullName: 'Khách Cùng Sinh Nhật',
        phone: phoneSameDob,
        date_of_birth: dobC,
        address: '88 Phan Chu Trinh'
      })
    });
    const sameDobJson = await sameDobRes.json();
    assert.equal(sameDobRes.status, 200);
    assert.equal(sameDobJson.success, true);
    if (sameDobJson.data?.voucher?.id) {
      createdCouponIds.push(Number(sameDobJson.data.voucher.id));
    }

    // Duplicate phone on another new account must fail
    const emailD = `welcome_dup_${ts}${rand}@test.local`;
    const phoneD = `094${ts}${rand}`.slice(0, 10);
    const userD = await registerUser('Khách Trùng SĐT', emailD, phoneD);
    const dupRes = await fetch(`${BASE_URL}/api/claim-voucher.php`, {
      method: 'POST',
      headers: { Cookie: userD.cookie, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fullName: 'Khách Trùng SĐT',
        phone: phoneC,
        date_of_birth: '1999-12-20',
        address: '99 Nguyễn Tất Thành'
      })
    });
    const dupJson = await dupRes.json();
    assert.equal(dupRes.status, 400);
    assert.equal(dupJson.success, false);
  });
});
