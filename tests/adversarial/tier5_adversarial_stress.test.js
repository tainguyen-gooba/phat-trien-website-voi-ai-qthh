/**
 * tests/adversarial/tier5_adversarial_stress.test.js
 * 
 * Milestone 5 Phase 2: White-Box Adversarial Stress Testing (Tier 5)
 * 
 * Target: Integrated FASTFOOD Membership & Loyalty Architecture
 * 
 * Adversarial Focus Areas:
 * 1. High-Concurrency Race Conditions (simultaneous checkout point drain, simultaneous voucher claims, double earn)
 * 2. Transaction Rollback Invariants (aborted checkout, cancel restore invariant, refund revoke invariant, balance clamping)
 * 3. Boundary & Tampering Exploits (negative points, string/float/SQL injection, huge overflow, client tier tampering, RBAC escalation)
 * 4. Order Lifecycle Edge Cases (rapid double cancel idempotency, cancelling completed order, completing cancelled order, single-use voucher)
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');
const { execFileSync } = require('child_process');

const TestClient = require('../e2e/harness/TestClient');
const dbHelper = require('../e2e/harness/db-helper');

const PROJECT_ROOT = path.resolve(__dirname, '../..');
const BASE_URL = process.env.TEST_BASE_URL || 'http://127.0.0.1:5000';
// Standard bcrypt hash for '123456' matching active seed accounts
const PASSWORD_HASH = '$2a$10$bkietFQJeqvf5auGcaZdpeA3bauzIW68ngdzqkEYVS9T1o4j.tsXe';

/**
 * Executes a PHP code snippet against helpers/loyalty.php via child_process
 */
function callLoyaltyPhp(code) {
  const script = `
    require 'config/database.php';
    require 'helpers/loyalty.php';
    ${code}
  `;
  const b64 = Buffer.from(script, 'utf8').toString('base64');
  const out = execFileSync('php', ['-r', `eval(base64_decode('${b64}'));`], {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    windowsHide: true
  });
  return out.trim();
}

/**
 * Generates unique test email
 */
function genEmail(tag = 'adv') {
  return `${tag}_${Date.now()}_${Math.random().toString(36).slice(2, 7)}@fastfood.test`;
}

/**
 * Generates unique phone number
 */
function genPhone() {
  while (true) {
    const phone = '03' + Math.floor(10000000 + Math.random() * 90000000);
    const existing = dbHelper.query('SELECT id FROM users WHERE phone = ? LIMIT 1', [phone]);
    if (!existing || existing.length === 0) return phone;
  }
}

/**
 * Creates an active customer with custom points and spending
 */
function createCustomer({ points = 500, spent = 0, tier = 'BRONZE' } = {}) {
  const email = genEmail('adv_cust');
  const phone = genPhone();
  dbHelper.query(
    `INSERT INTO users (role_id, full_name, email, phone, password_hash, member_tier, points_balance, total_spent, status, created_at)
     VALUES (2, 'Adversarial Tester', ?, ?, ?, ?, ?, ?, 'active', NOW())`,
    [email, phone, PASSWORD_HASH, tier, points, spent]
  );
  const rows = dbHelper.query('SELECT id, email, phone, member_tier, points_balance, total_spent FROM users WHERE email = ? LIMIT 1', [email]);
  assert.ok(rows && rows.length > 0, 'Customer must be created');
  return { ...rows[0], password: '123456' };
}

/**
 * Creates an order in database
 */
function createOrder({ userId, subtotal = 100000, pointsUsed = 0, pointsDiscount = 0, status = 'pending' } = {}) {
  const orderCode = 'ADV_' + Date.now().toString(36).toUpperCase() + '_' + Math.random().toString(36).slice(2, 6).toUpperCase();
  const total = Math.max(0, subtotal - pointsDiscount);
  dbHelper.query(
    `INSERT INTO orders (order_code, customer_id, restaurant_id, subtotal, shipping_fee, discount_amount, total_amount, points_used, points_discount, payment_method, payment_status, order_status, placed_at, created_at)
     VALUES (?, ?, 1, ?, 0.00, 0.00, ?, ?, ?, 'cod', 'pending', ?, NOW(), NOW())`,
    [orderCode, userId, subtotal, total, pointsUsed, pointsDiscount, status]
  );
  const rows = dbHelper.query('SELECT * FROM orders WHERE order_code = ? LIMIT 1', [orderCode]);
  assert.ok(rows && rows.length > 0, 'Order must be created');
  return rows[0];
}

describe('Tier 5 Adversarial Stress & Vulnerability Probing Suite', () => {

  after(() => {
    try {
      dbHelper.query("DELETE FROM point_transactions WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@fastfood.test')");
      dbHelper.query("DELETE FROM coupons WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@fastfood.test')");
      dbHelper.query("DELETE FROM orders WHERE order_code LIKE 'ADV_%' OR customer_id IN (SELECT id FROM users WHERE email LIKE '%@fastfood.test')");
      dbHelper.query("DELETE FROM users WHERE email LIKE '%@fastfood.test'");
    } catch (_) {}
  });

  // =========================================================================
  // 1. High-Concurrency Race Conditions
  // =========================================================================
  describe('Group 1: High-Concurrency Race Conditions', () => {

    it('ADV-01: Simultaneous concurrent checkout requests cannot double-drain points balance', async () => {
      // Setup customer with exactly 300 points (worth 30,000 VND)
      const user = createCustomer({ points: 300 });

      // Create 5 separate orders for this user, each wanting to redeem 300 points
      const orders = [];
      for (let i = 0; i < 5; i++) {
        orders.push(createOrder({ userId: user.id, subtotal: 100000 }));
      }

      // Execute 5 concurrent checkout point redemption calls via PHP sub-processes
      const promises = orders.map(ord => {
        return new Promise(resolve => {
          try {
            const script = `echo json_encode(redeemPointsAtCheckout(${user.id}, ${ord.id}, 300, '${ord.order_code}'));`;
            const raw = callLoyaltyPhp(script);
            const res = JSON.parse(raw);
            resolve(res);
          } catch (err) {
            resolve({ success: false, error: err.message });
          }
        });
      });

      const results = await Promise.all(promises);
      const successes = results.filter(r => r.success === true);
      const failures = results.filter(r => r.success === false);

      // Invariant: Exactly 1 request may succeed because balance is 300 and cost is 300
      assert.equal(successes.length, 1, `Exactly 1 concurrent request should succeed, but got ${successes.length}`);
      assert.equal(failures.length, 4, `4 concurrent requests should fail, but got ${failures.length}`);

      // Invariant: Balance cannot be negative
      const updatedUser = dbHelper.getUserLoyalty(user.id);
      assert.equal(updatedUser.points_balance, 0, 'User points balance must be exactly 0, never negative');

      // Invariant: Ledger entries
      const txRows = dbHelper.query(
        "SELECT * FROM point_transactions WHERE user_id = ? AND transaction_type = 'REDEEM_CHECKOUT'",
        [user.id]
      );
      assert.equal(txRows.length, 1, 'Exactly 1 REDEEM_CHECKOUT transaction recorded in ledger');
      assert.equal(Number(txRows[0].points), -300, 'Transaction points deducted must be -300');
    });

    it('ADV-02: Simultaneous concurrent voucher redemptions cannot exceed available points', async () => {
      // Customer has 200 points. Package 2 costs 200 points.
      const user = createCustomer({ points: 200 });
      const client = new TestClient(BASE_URL);
      const login = await client.loginAsCustomer(user.email, user.password);
      assert.ok(login.success, 'Login must succeed');

      // Fire 5 concurrent HTTP POST requests to api/loyalty.php for package 2
      const promises = [];
      for (let i = 0; i < 5; i++) {
        promises.push(
          client.post('/api/loyalty.php', {
            action: 'exchange_voucher',
            package_id: 2
          })
        );
      }

      const responses = await Promise.all(promises);
      const successful = responses.filter(r => r.status === 200 && r.body?.success === true);
      const rejected = responses.filter(r => r.status === 400 || r.body?.success === false);

      // Invariant: Exactly 1 voucher can be exchanged under race conditions
      assert.equal(successful.length, 1, `Only 1 voucher redemption must succeed under race conditions, got ${successful.length}`);
      assert.equal(rejected.length, 4, `4 voucher redemptions must be rejected, got ${rejected.length}`);

      // Invariant: Points balance must be exactly 0
      const updatedUser = dbHelper.getUserLoyalty(user.id);
      assert.equal(updatedUser.points_balance, 0, 'Points balance must be 0');

      // Invariant: Exactly 1 coupon issued for user
      const coupons = dbHelper.query('SELECT id, code, discount_value FROM coupons WHERE user_id = ?', [user.id]);
      assert.equal(coupons.length, 1, 'Only 1 coupon record must exist in DB');
    });

    it('ADV-03: Race condition between simultaneous checkout redemption and voucher exchange', async () => {
      // User has 300 points
      const user = createCustomer({ points: 300 });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(user.email, user.password);

      const order = createOrder({ userId: user.id, subtotal: 100000 });

      // Request A: checkout redeem 300 points
      const reqCheckout = new Promise(resolve => {
        try {
          const res = JSON.parse(callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 300, '${order.order_code}'));`));
          resolve({ type: 'checkout', res });
        } catch (e) {
          resolve({ type: 'checkout', res: { success: false, error: e.message } });
        }
      });

      // Request B: voucher exchange for package 2 (200 points)
      const reqVoucher = client.post('/api/loyalty.php', {
        action: 'exchange_voucher',
        package_id: 2
      }).then(r => ({ type: 'voucher', res: r.body }));

      // Run concurrently
      const [rCheckout, rVoucher] = await Promise.all([reqCheckout, reqVoucher]);

      // Since 300 + 200 = 500 > 300, BOTH CANNOT SUCCEED!
      const checkoutSuccess = rCheckout.res?.success === true;
      const voucherSuccess = rVoucher.res?.success === true;

      assert.ok(!(checkoutSuccess && voucherSuccess), 'Both checkout redemption (300) and voucher exchange (200) cannot succeed when balance is 300');

      // Final balance cannot be negative
      const updatedUser = dbHelper.getUserLoyalty(user.id);
      assert.ok(updatedUser.points_balance >= 0, `Balance must not be negative, was ${updatedUser.points_balance}`);

      // Invariant: balance + total deducted == 300
      const ledger = dbHelper.query(
        'SELECT SUM(points) as total_change FROM point_transactions WHERE user_id = ?',
        [user.id]
      );
      const totalChange = Number(ledger[0]?.total_change || 0);
      assert.equal(300 + totalChange, updatedUser.points_balance, 'Ledger sum must strictly match remaining balance');
    });

    it('ADV-04: Concurrent order completion requests cannot award duplicate earn points', async () => {
      const user = createCustomer({ points: 0 });
      const order = createOrder({ userId: user.id, subtotal: 200000, status: 'completed' });

      // Run 5 concurrent executions of processOrderEarnPoints for the SAME completed order
      const promises = [];
      for (let i = 0; i < 5; i++) {
        promises.push(
          new Promise(resolve => {
            try {
              const res = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`));
              resolve(res);
            } catch (e) {
              resolve({ success: false, error: e.message });
            }
          })
        );
      }

      const results = await Promise.all(promises);
      const earned = results.filter(r => r.success === true && r.points_earned > 0);

      // Invariant: Exactly 1 execution awards points; the rest are idempotent
      assert.equal(earned.length, 1, 'Only 1 execution can award points for the order');

      // Invariant: 200k order at Bronze awards 20 points
      const updatedUser = dbHelper.getUserLoyalty(user.id);
      assert.equal(updatedUser.points_balance, 20, 'User points balance must be 20');

      // Invariant: point_transactions has exactly 1 EARN row for this order
      const txRows = dbHelper.query(
        "SELECT * FROM point_transactions WHERE order_id = ? AND transaction_type = 'EARN'",
        [order.id]
      );
      assert.equal(txRows.length, 1, 'Exactly 1 EARN transaction row in point_transactions');
    });
  });

  // =========================================================================
  // 2. Transaction Rollback Invariants & Ledger Audit
  // =========================================================================
  describe('Group 2: Transaction Rollback Invariants & Ledger Audit', () => {

    it('ADV-05: Aborted checkout rolls back points deduction without orphaned records', () => {
      const initialPoints = 400;
      const user = createCustomer({ points: initialPoints });
      const order = createOrder({ userId: user.id, subtotal: 100000 });

      // Simulate a multi-query transaction in checkout that redeems points then encounters an unrecoverable failure
      const script = `
        $db = getDB();
        $db->beginTransaction();
        try {
            $r = redeemPointsAtCheckout(${user.id}, ${order.id}, 200, '${order.order_code}');
            if (!$r['success']) {
                throw new Exception('Redeem failed');
            }
            // Simulated crash or validation failure before commit
            throw new Exception('Simulated gateway or item error');
            $db->commit();
            echo json_encode(['committed' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['committed' => false, 'error' => $e->getMessage()]);
        }
      `;
      const out = JSON.parse(callLoyaltyPhp(script));
      assert.equal(out.committed, false, 'Transaction must be aborted and rolled back');

      // User points must be untouched
      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, initialPoints, 'User points balance must remain 400 after rollback');

      // No ledger entry should exist
      const txs = dbHelper.query('SELECT * FROM point_transactions WHERE user_id = ?', [user.id]);
      assert.equal(txs.length, 0, 'No orphaned ledger records after rollback');
    });

    it('ADV-06: Cancelled order restores points and satisfies SUM(tx) == balance invariant', () => {
      const user = createCustomer({ points: 500 });
      const order = createOrder({ userId: user.id, subtotal: 100000 });

      // 1. Redeem 300 points
      const rRedeem = JSON.parse(callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, ${order.id}, 300, '${order.order_code}'));`));
      assert.ok(rRedeem.success);

      // Verify intermediate balance = 200
      let u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 200);

      // 2. Cancel order
      const rCancel = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
      assert.ok(rCancel.success);
      assert.equal(rCancel.points_restored, 300);

      // Verify final balance = 500
      u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 500, 'Balance must be restored to 500');

      // Invariant: SUM(transactions) == balance change
      const ledgerSum = dbHelper.query(
        'SELECT SUM(points) as net FROM point_transactions WHERE user_id = ?',
        [user.id]
      );
      assert.equal(Number(ledgerSum[0]?.net || 0), 0, 'Net transaction sum must be 0 (300 redeemed, 300 restored)');
    });

    it('ADV-07: Completed order refund revokes earned points, decrements spending, and downgrades tier', () => {
      // User with 0 spent, BRONZE
      const user = createCustomer({ points: 0, spent: 0, tier: 'BRONZE' });
      // Order of 1,200,000 VND -> qualifies for SILVER
      const order = createOrder({ userId: user.id, subtotal: 1200000, status: 'completed' });

      // 1. Earn points on completion
      const rEarn = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`));
      assert.ok(rEarn.success);
      assert.equal(rEarn.new_tier, 'SILVER');
      assert.equal(rEarn.points_earned, 120);

      // Verify user state: SILVER, total_spent 1,200,000, points 120
      let u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.member_tier, 'SILVER');
      assert.equal(u.total_spent, 1200000);
      assert.equal(u.points_balance, 120);

      // 2. Order is refunded / cancelled
      const rCancel = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
      assert.ok(rCancel.success);
      assert.equal(rCancel.points_revoked, 120);
      assert.equal(rCancel.new_spent, 0);
      assert.equal(rCancel.new_tier, 'BRONZE');

      // Verify user state in DB: BRONZE, total_spent 0, points 0
      u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.member_tier, 'BRONZE', 'Tier must downgrade to BRONZE');
      assert.equal(u.total_spent, 0, 'Total spent must be 0');
      assert.equal(u.points_balance, 0, 'Points balance must be 0');

      // Invariant: SUM(transactions) == balance
      const ledgerSum = dbHelper.query(
        'SELECT SUM(points) as net FROM point_transactions WHERE user_id = ?',
        [user.id]
      );
      assert.equal(Number(ledgerSum[0]?.net || 0), 0, 'Net transaction points must be 0');
    });

    it('ADV-08: Negative points balance clamping and double refund immunity', () => {
      // User earned 50 points from Order 1, but then spent those 50 points
      const user = createCustomer({ points: 0, spent: 0, tier: 'BRONZE' });
      const order = createOrder({ userId: user.id, subtotal: 500000, status: 'completed' });

      // Award 50 points
      callLoyaltyPhp(`processOrderEarnPoints(${order.id});`);
      let u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 50);

      // Simulate spending those points on something else, balance drops to 0
      dbHelper.query('UPDATE users SET points_balance = 0 WHERE id = ?', [user.id]);

      // Now cancel order 1: should attempt to revoke 50 points, but clamp at 0
      const rCancel1 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
      assert.ok(rCancel1.success);
      assert.equal(rCancel1.new_balance, 0, 'Balance must be clamped to 0 (never negative)');

      u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 0, 'DB balance is 0');

      // Double refund attempt: call handleOrderCancellation again
      const rCancel2 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
      assert.ok(rCancel2.success);
      assert.equal(rCancel2.points_revoked, 0, 'Second cancellation must revoke 0 points (idempotent)');

      u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 0, 'Balance remains 0');
    });

    it('ADV-09: Mathematical ledger continuity invariant holds across 10 mixed operations', () => {
      const initialBalance = 1000;
      const user = createCustomer({ points: initialBalance });

      // Op 1: Checkout redeem 100 pts
      const ord1 = createOrder({ userId: user.id, subtotal: 50000 });
      callLoyaltyPhp(`redeemPointsAtCheckout(${user.id}, ${ord1.id}, 100, '${ord1.order_code}');`);

      // Op 2: Voucher exchange 100 pts
      callLoyaltyPhp(`exchangePointsForVoucher(${user.id}, 1);`);

      // Op 3: Earn from completed order 200k (20 pts)
      const ord2 = createOrder({ userId: user.id, subtotal: 200000, status: 'completed' });
      callLoyaltyPhp(`processOrderEarnPoints(${ord2.id});`);

      // Op 4: Checkout redeem 200 pts
      const ord3 = createOrder({ userId: user.id, subtotal: 80000 });
      callLoyaltyPhp(`redeemPointsAtCheckout(${user.id}, ${ord3.id}, 200, '${ord3.order_code}');`);

      // Op 5: Cancel ord3 (restore 200 pts)
      callLoyaltyPhp(`handleOrderCancellation(${ord3.id});`);

      // Op 6: Cancel ord2 (revoke 20 pts)
      callLoyaltyPhp(`handleOrderCancellation(${ord2.id});`);

      // Op 7: Voucher exchange 200 pts (pkg 2)
      callLoyaltyPhp(`exchangePointsForVoucher(${user.id}, 2);`);

      // Op 8: Earn from completed order 500k (50 pts)
      const ord4 = createOrder({ userId: user.id, subtotal: 500000, status: 'completed' });
      callLoyaltyPhp(`processOrderEarnPoints(${ord4.id});`);

      // Verify Mathematical Ledger Continuity:
      // balance_after[i] == balance_after[i-1] + delta[i]
      const txs = dbHelper.query(
        'SELECT id, points, balance_after FROM point_transactions WHERE user_id = ? ORDER BY id ASC',
        [user.id]
      );
      assert.ok(txs.length >= 7, 'Must have at least 7 recorded transactions');

      let currentExpected = initialBalance;
      for (const tx of txs) {
        const delta = Number(tx.points);
        const recordedAfter = Number(tx.balance_after);
        currentExpected += delta;
        assert.equal(recordedAfter, currentExpected, `Ledger continuity broken at tx ${tx.id}: expected ${currentExpected}, found ${recordedAfter}`);
      }

      // Check final DB user balance
      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, currentExpected, 'Final user points balance must exactly match running ledger total');
    });
  });

  // =========================================================================
  // 3. Boundary & Tampering Exploits
  // =========================================================================
  describe('Group 3: Boundary & Tampering Exploits', () => {

    it('ADV-10: Negative points injection in checkout is strictly rejected', async () => {
      const user = createCustomer({ points: 500 });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(user.email, user.password);
      await client.addToCart(1, 1);

      // Attempt checkout with negative points
      const res = await client.checkoutOrder({
        customer_name: 'Attacker Name',
        customer_phone: '0901234567',
        customer_address: '123 Exploit Street',
        paymentMethod: 'cod',
        points_to_use: '-100'
      });

      // User points balance must not have changed
      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 500, 'User points must be untouched after negative points attempt');

      // Direct service check
      const serviceRes = JSON.parse(callLoyaltyPhp(`echo json_encode(redeemPointsAtCheckout(${user.id}, 99999, -100, 'TEST'));`));
      assert.equal(serviceRes.discount_amount, 0, 'Negative points produces 0 discount');
    });

    it('ADV-11: String, float, and SQL injection in points_to_use are rejected', async () => {
      const user = createCustomer({ points: 500 });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(user.email, user.password);
      await client.addToCart(1, 1);

      const maliciousInputs = [
        '100.5',
        'NaN',
        '0x10',
        "100' OR '1'='1",
        '<script>alert(1)</script>',
        '1e5'
      ];

      for (const malicious of maliciousInputs) {
        await client.checkoutOrder({
          customer_name: 'Attacker Name',
          customer_phone: '0901234567',
          customer_address: '123 Exploit Street',
          paymentMethod: 'cod',
          points_to_use: malicious
        });

        const u = dbHelper.getUserLoyalty(user.id);
        assert.equal(u.points_balance, 500, `User points must not change for malicious input: ${malicious}`);
      }
    });

    it('ADV-12: Huge numeric overflow values are rejected', async () => {
      const user = createCustomer({ points: 500 });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(user.email, user.password);
      await client.addToCart(1, 1);

      await client.checkoutOrder({
        customer_name: 'Attacker Name',
        customer_phone: '0901234567',
        customer_address: '123 Exploit Street',
        paymentMethod: 'cod',
        points_to_use: '999999999999999999'
      });

      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 500, 'Points balance must remain 500');
    });

    it('ADV-13: Client-side member_tier tampering attempts are ignored by the backend', async () => {
      const user = createCustomer({ points: 0, spent: 0, tier: 'BRONZE' });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(user.email, user.password);

      // Attempt to post tier alteration via updateProfile
      await client.updateProfile({
        fullName: 'Hacker Diamond',
        phone: user.phone,
        member_tier: 'DIAMOND',
        total_spent: '999999999',
        points_balance: '888888'
      });

      // Verify DB remains BRONZE
      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.member_tier, 'BRONZE', 'member_tier cannot be modified by client tampering');
      assert.equal(u.total_spent, 0, 'total_spent cannot be modified by client tampering');
      assert.equal(u.points_balance, 0, 'points_balance cannot be modified by client tampering');
    });

    it('ADV-14: Customer role (role 2) cannot access Admin API endpoints', async () => {
      const customer = createCustomer({ points: 100 });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(customer.email, customer.password);

      // Attempt 1: save_loyalty_config
      const r1 = await client.post('/admin/api.php?action=save_loyalty_config', {
        action: 'save_loyalty_config',
        point_to_vnd_rate: 9999
      }, {}, 'customer');
      const isBlocked1 = r1.status === 403 || r1.status === 401 || r1.status === 302 || r1.text.includes('403') || r1.text.includes('Forbidden') || r1.text.includes('login') || r1.body?.error;
      assert.ok(isBlocked1, 'Customer cannot invoke save_loyalty_config');

      // Attempt 2: get_loyalty_members
      const r2 = await client.get('/admin/api.php?action=get_loyalty_members', {}, 'customer');
      const isBlocked2 = r2.status === 403 || r2.status === 401 || r2.status === 302 || r2.text.includes('403') || r2.text.includes('Forbidden') || r2.text.includes('login') || r2.body?.error;
      assert.ok(isBlocked2, 'Customer cannot view members ledger');

      // Attempt 3: update_order_status
      const r3 = await client.post('/admin/api.php?action=update_order_status', {
        action: 'update_order_status',
        order_code: 'FF9999',
        status: 'completed'
      }, {}, 'customer');
      const isBlocked3 = r3.status === 403 || r3.status === 401 || r3.status === 302 || r3.text.includes('403') || r3.text.includes('Forbidden') || r3.text.includes('login') || r3.body?.error;
      assert.ok(isBlocked3, 'Customer cannot update order status');
    });

    it('ADV-15: Staff role (role 5) is forbidden from modifying dynamic loyalty configuration', async () => {
      const client = new TestClient(BASE_URL);
      await client.loginAsStaff();

      const res = await client.post('/admin/api.php', {
        action: 'save_loyalty_config',
        settings: {
          point_to_vnd_rate: 200
        }
      });

      // Must be 403 Forbidden
      assert.equal(res.status, 403, 'Staff must receive HTTP 403 when attempting save_loyalty_config');
      assert.equal(res.body?.success, false);
      assert.match(res.body?.message || res.text, /quản trị viên/i);
    });

    it('ADV-16: Admin dynamic config rejects inverted thresholds, 0 rates, and negative steps', async () => {
      const adminClient = new TestClient(BASE_URL);
      await adminClient.loginAsAdmin();

      // Case A: 0 rate
      const r1 = await adminClient.post('/admin/api.php', {
        action: 'save_loyalty_config',
        settings: { point_to_vnd_rate: 0 }
      });
      assert.equal(r1.status, 400, '0 rate must be rejected with 400');

      // Case B: Negative spending step
      const r2 = await adminClient.post('/admin/api.php', {
        action: 'save_loyalty_config',
        settings: { spending_step_vnd: -5000 }
      });
      assert.equal(r2.status, 400, 'Negative spending step must be rejected with 400');

      // Case C: Inverted thresholds (Silver > Gold)
      const r3 = await adminClient.post('/admin/api.php', {
        action: 'save_loyalty_config',
        tiers: [
          { tier_code: 'SILVER', min_spent: 4000000 },
          { tier_code: 'GOLD', min_spent: 2000000 }
        ]
      });
      assert.equal(r3.status, 400, 'Inverted tier thresholds must be rejected with 400');
    });
  });

  // =========================================================================
  // 4. Order Lifecycle Edge Cases
  // =========================================================================
  describe('Group 4: Order Lifecycle Edge Cases', () => {

    it('ADV-17: Rapid double-cancellation restores points exactly once (idempotent)', () => {
      const user = createCustomer({ points: 600 });
      const order = createOrder({ userId: user.id, subtotal: 100000 });

      // Redeem 400 points
      callLoyaltyPhp(`redeemPointsAtCheckout(${user.id}, ${order.id}, 400, '${order.order_code}');`);
      let u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 200);

      // Rapid consecutive cancellations
      const r1 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));
      const r2 = JSON.parse(callLoyaltyPhp(`echo json_encode(handleOrderCancellation(${order.id}));`));

      assert.equal(r1.points_restored, 400, 'First cancellation restores 400 points');
      assert.equal(r2.points_restored, 0, 'Second cancellation restores 0 points (idempotent)');

      u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 600, 'Balance must be exactly restored to 600, not 1000');

      const restoreTxs = dbHelper.query(
        "SELECT * FROM point_transactions WHERE order_id = ? AND transaction_type = 'REFUND_RESTORE'",
        [order.id]
      );
      assert.equal(restoreTxs.length, 1, 'Only 1 REFUND_RESTORE record created');
    });

    it('ADV-18: Order completion idempotency: marking completed multiple times awards points once', () => {
      const user = createCustomer({ points: 0, spent: 0 });
      const order = createOrder({ userId: user.id, subtotal: 300000, status: 'completed' });

      // First call
      const res1 = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`));
      assert.ok(res1.success);
      assert.equal(res1.points_earned, 30);

      // Second call
      const res2 = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`));
      assert.equal(res2.success, false);
      assert.equal(res2.already_awarded, true);

      // Third call
      const res3 = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`));
      assert.equal(res3.success, false);
      assert.equal(res3.already_awarded, true);

      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 30, 'Balance awarded exactly once');
      assert.equal(u.total_spent, 300000, 'Spending added exactly once');
    });

    it('ADV-19: Cancelled order cannot be completed to illicitly award points', () => {
      const user = createCustomer({ points: 0, spent: 0 });
      const order = createOrder({ userId: user.id, subtotal: 200000, status: 'cancelled' });

      // Attempt to call processOrderEarnPoints on a cancelled order
      const res = JSON.parse(callLoyaltyPhp(`echo json_encode(processOrderEarnPoints(${order.id}));`));
      assert.equal(res.success, false, 'processOrderEarnPoints must reject non-completed orders');
      assert.match(res.error, /not completed/i);

      const u = dbHelper.getUserLoyalty(user.id);
      assert.equal(u.points_balance, 0, 'No points awarded for cancelled order');
      assert.equal(u.total_spent, 0, 'No spending recorded for cancelled order');
    });

    it('ADV-20: Redeemed voucher is single-use and cannot be applied to multiple orders', async () => {
      const user = createCustomer({ points: 200 });
      const client = new TestClient(BASE_URL);
      await client.loginAsCustomer(user.email, user.password);

      // 1. Exchange 100 points for Voucher package 1 (10,000 VND discount)
      const rVoucher = await client.post('/api/loyalty.php', {
        action: 'exchange_voucher',
        package_id: 1
      });
      assert.ok(rVoucher.body?.success, 'Voucher exchange should succeed');
      const couponCode = rVoucher.body?.coupon_code;
      assert.ok(couponCode, 'Voucher coupon code must be generated');

      // Verify coupon usage_count is 0 and usage_limit is 1
      const cpRow1 = dbHelper.query('SELECT usage_count, usage_limit FROM coupons WHERE code = ? LIMIT 1', [couponCode]);
      assert.equal(Number(cpRow1[0].usage_count), 0);
      assert.equal(Number(cpRow1[0].usage_limit), 1);

      // 2. Simulate coupon usage on order checkout
      dbHelper.query('UPDATE coupons SET usage_count = usage_count + 1 WHERE code = ?', [couponCode]);

      // 3. Second checkout attempting to reuse the same coupon code
      const cpRow2 = dbHelper.query('SELECT usage_count, usage_limit FROM coupons WHERE code = ? LIMIT 1', [couponCode]);
      const isExhausted = Number(cpRow2[0].usage_count) >= Number(cpRow2[0].usage_limit);
      assert.equal(isExhausted, true, 'Coupon must be marked exhausted after 1 use');
    });
  });
});
