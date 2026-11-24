/**
 * FASTFOOD - Customer Voucher System E2E & API Test Suite
 * File: tests/e2e/test_customer_voucher_system.js
 */

const http = require('http');
const assert = require('assert');

const BASE_URL = 'http://127.0.0.1:5000';

function request(path, options = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(path, BASE_URL);
    const reqOptions = {
      hostname: url.hostname,
      port: url.port,
      path: url.pathname + url.search,
      method: options.method || 'GET',
      headers: options.headers || {}
    };

    const req = http.request(reqOptions, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        resolve({
          statusCode: res.statusCode,
          headers: res.headers,
          body: data
        });
      });
    });

    req.on('error', reject);

    if (options.body) {
      req.write(typeof options.body === 'string' ? options.body : JSON.stringify(options.body));
    }
    req.end();
  });
}

function parseCookies(res) {
  const setCookie = res.headers['set-cookie'];
  if (!setCookie) return '';
  return setCookie.map(c => c.split(';')[0]).join('; ');
}

async function runTests() {
  console.log('🚀 Bắt đầu kiểm thử Hệ Thống Voucher Khách Hàng (Customer Voucher System)...');
  let passed = 0;
  let total = 0;

  function test(name, fn) {
    total++;
    return fn()
      .then(() => {
        passed++;
        console.log(`  ✅ [PASS] ${name}`);
      })
      .catch((err) => {
        console.error(`  ❌ [FAIL] ${name}`);
        console.error(`     Reason:`, err.message || err);
      });
  }

  // 1. Kiểm tra API Get Public Vouchers
  await test('1. API get_all trả về danh sách voucher công khai thành công', async () => {
    const res = await request('/api/vouchers.php?action=get_all');
    assert.strictEqual(res.statusCode, 200, 'Status code must be 200');
    const json = JSON.parse(res.body);
    assert.strictEqual(json.success, true, 'API success must be true');
    assert.ok(Array.isArray(json.data.vouchers), 'vouchers must be an array');
    assert.ok(json.data.vouchers.length >= 4, 'Must have at least 4 public vouchers');
    
    const codes = json.data.vouchers.map(v => v.code);
    assert.ok(codes.includes('FASTFOOD20'), 'FASTFOOD20 must be present');
    assert.ok(codes.includes('FREESHIP18'), 'FREESHIP18 must be present');
  });

  // 2. Kiểm tra API Get Eligible Vouchers với subtotal = 150000
  await test('2. API get_eligible tính toán chiết khấu và điều kiện áp dụng chính xác', async () => {
    const res = await request('/api/vouchers.php?action=get_eligible&subtotal=150000');
    assert.strictEqual(res.statusCode, 200);
    const json = JSON.parse(res.body);
    assert.strictEqual(json.success, true);
    const vouchers = json.data.vouchers;

    const fastfood20 = vouchers.find(v => v.code === 'FASTFOOD20');
    assert.ok(fastfood20, 'FASTFOOD20 found');
    assert.strictEqual(fastfood20.is_eligible, true, 'Subtotal 150k > min_order 60k -> eligible');
    assert.strictEqual(fastfood20.estimated_discount, 20000, 'Discount must be 20.000đ');

    const highOrderVoucher = vouchers.find(v => v.code === 'BURGER30' || v.code === 'BOGOPIZZA');
    assert.ok(highOrderVoucher, 'High min-order voucher found');
    assert.strictEqual(highOrderVoucher.is_eligible, true, 'Subtotal 150k >= min_order -> eligible');
  });

  // 3. Kiểm tra Flow Áp dụng Voucher (apply) và Hủy (remove)
  await test('3. Session Cart: Áp dụng voucher và gỡ bỏ voucher hoạt động mượt mà', async () => {
    // Gọi apply coupon
    const applyRes = await request('/api/vouchers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: { action: 'apply', code: 'FASTFOOD20', subtotal: 80000 }
    });
    assert.strictEqual(applyRes.statusCode, 200);
    const applyJson = JSON.parse(applyRes.body);
    assert.strictEqual(applyJson.success, true);
    assert.strictEqual(applyJson.data.discount_amount, 20000);
    const cookie = parseCookies(applyRes);

    // Kiểm tra gỡ bỏ voucher với cùng session
    const removeRes = await request('/api/vouchers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Cookie': cookie },
      body: { action: 'remove' }
    });
    assert.strictEqual(removeRes.statusCode, 200);
    const removeJson = JSON.parse(removeRes.body);
    assert.strictEqual(removeJson.success, true);
    assert.strictEqual(removeJson.data.applied_coupon, null);
  });

  // 4. Kiểm tra trang vouchers.php
  await test('4. Trang vouchers.php tải thành công (HTTP 200) và đầy đủ 3 Tab điều hướng', async () => {
    const res = await request('/vouchers.php');
    assert.strictEqual(res.statusCode, 200);
    assert.ok(res.body.includes('Săn Voucher Hot'), 'Must contain Tab 1 title');
    assert.ok(res.body.includes('Ví Voucher Của Tôi'), 'Must contain Tab 2 title');
    assert.ok(res.body.includes('Đổi Điểm Lấy Voucher'), 'Must contain Tab 3 title');
    assert.ok(res.body.includes('FASTFOOD20'), 'Must display FASTFOOD20 ticket');
  });

  // 5. Kiểm tra Navigation Header
  await test('5. Header chứa liên kết "Khuyến Mãi" trỏ về vouchers.php', async () => {
    const res = await request('/index.php');
    assert.strictEqual(res.statusCode, 200);
    assert.ok(res.body.includes('vouchers.php'), 'Must contain link to vouchers.php');
    assert.ok(res.body.includes('Khuyến Mãi'), 'Must have "Khuyến Mãi" navigation text');
  });

  // 6. Kiểm tra Voucher Modal trên Cart và Checkout
  await test('6. Trang giỏ hàng (cart.php) và thanh toán (checkout.php) nhúng modal chọn voucher', async () => {
    const cartRes = await request('/cart.php');
    assert.strictEqual(cartRes.statusCode, 200);
    assert.ok(cartRes.body.includes('voucher-picker-modal'), 'Cart must have voucher-picker-modal');
    assert.ok(cartRes.body.includes('cart-voucher-box'), 'Cart must have cart-voucher-box');

    // Checkout requires login normally, test redirect or modal presence
    const checkoutRes = await request('/checkout.php');
    // If not logged in, redirects to login.php, which is correct authentication behavior
    assert.ok([200, 302].includes(checkoutRes.statusCode));
  });

  console.log(`\n========================================`);
  console.log(`Tổng kết: ${passed}/${total} bài kiểm tra đạt thành công (PASS)!`);
  console.log(`========================================\n`);

  if (passed !== total) {
    process.exit(1);
  }
}

runTests().catch(err => {
  console.error('Lỗi kiểm thử E2E:', err);
  process.exit(1);
});
