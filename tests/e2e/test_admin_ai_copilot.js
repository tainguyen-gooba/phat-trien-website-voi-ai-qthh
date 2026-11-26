/**
 * E2E Test: Admin AI Co-pilot & Markdown Rendering Fix
 * Verifies:
 * 1. admin/index.php routes Admin AI Assistant to api.php?action=admin_ai_copilot (not ../api/ai-chat.php)
 * 2. RBAC: Guest and Customer (role 2) are blocked; Admin (role 1) & Staff (role 5) are authorized
 * 3. All Admin quick-prompt chips return real operations/analytics/SOP responses (no customer "Đặt món" FAQ hijack)
 * 4. Admin Co-pilot & api/ai-chat.php return clean Markdown (no raw unescaped <a>, <strong>, <br> HTML tags)
 * 5. Both PHP dev server (127.0.0.1:5000) and Apache (/phat-trien-website-voi-ai-qthh) work identically
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const TestClient = require('./harness/TestClient');

const BASE_URLS = [
  'http://127.0.0.1:5000',
  'http://localhost/phat-trien-website-voi-ai-qthh'
];

const RAW_HTML_TAG_REGEX = /<\s*\/?\s*(?:a|strong|br|div|span|script|img|p)\b[^>]*>/i;

for (const baseUrl of BASE_URLS) {
  describe(`Admin AI Co-pilot E2E (${baseUrl})`, () => {
    it('1. Admin dashboard HTML wires admin_ai_copilot and Markdown formatter (not ../api/ai-chat.php)', async () => {
      const client = new TestClient(baseUrl);
      const loginRes = await client.loginAsAdmin();
      assert.ok(loginRes.success, `Admin login failed on ${baseUrl}`);

      const res = await client.get('/admin/index.php', {}, 'admin');
      assert.equal(res.status, 200);
      assert.ok(
        res.text.includes('api.php?action=admin_ai_copilot'),
        'Admin AI Co-pilot form must submit to api.php?action=admin_ai_copilot'
      );
      assert.ok(
        !res.text.includes('../api/ai-chat.php'),
        'Admin UI must no longer call customer FAQ endpoint ../api/ai-chat.php'
      );
      assert.ok(
        res.text.includes('function formatAiMarkdown'),
        'Admin UI must define formatAiMarkdown()'
      );
      assert.ok(
        res.text.includes('ai-prompt-suggestions'),
        'Admin UI must render follow-up suggestion chips cleanly'
      );
    });

    it('2. Guest and Customer are blocked from admin_ai_copilot; Staff & Admin are permitted', async () => {
      const client = new TestClient(baseUrl);

      // Guest
      client.asGuest();
      const guestRes = await client.post('/admin/api.php?action=admin_ai_copilot', {
        action: 'admin_ai_copilot',
        prompt: 'Phân tích tình hình doanh thu và các giờ cao điểm đặt món'
      });
      assert.ok(
        guestRes.body?.success !== true,
        `Guest must be blocked from admin_ai_copilot, got: ${JSON.stringify(guestRes.body)}`
      );

      // Customer
      await client.loginAsCustomer();
      const custRes = await client.post('/admin/api.php?action=admin_ai_copilot', {
        action: 'admin_ai_copilot',
        prompt: 'Phân tích tình hình doanh thu và các giờ cao điểm đặt món'
      });
      assert.ok(
        custRes.body?.success !== true,
        `Customer must be blocked from admin_ai_copilot, got: ${JSON.stringify(custRes.body)}`
      );

      // Staff
      const staffLogin = await client.loginAsStaff();
      assert.ok(staffLogin.success, `Staff login failed on ${baseUrl}`);
      const staffRes = await client.post('/admin/api.php?action=admin_ai_copilot', {
        action: 'admin_ai_copilot',
        prompt: 'Phân tích tình hình doanh thu và các giờ cao điểm đặt món'
      }, {}, 'admin');
      assert.equal(staffRes.status, 200);
      assert.equal(staffRes.body?.success, true, `Staff should access admin_ai_copilot: ${staffRes.text}`);
    });

    it('3. All Admin UI prompt chips return real Admin Co-pilot analysis & clean Markdown without raw HTML', async () => {
      const client = new TestClient(baseUrl);
      await client.loginAsAdmin();

      const page = await client.get('/admin/index.php', {}, 'admin');
      assert.equal(page.status, 200);

      const chipPrompts = [...page.text.matchAll(/data-prompt="([^"]+)"/g)].map(m => m[1]);
      assert.ok(chipPrompts.length >= 4, `Expected >= 4 quick prompt chips on admin/index.php, got ${chipPrompts.length}`);

      for (const prompt of chipPrompts) {
        const res = await client.post('/admin/api.php?action=admin_ai_copilot', {
          action: 'admin_ai_copilot',
          prompt
        }, {}, 'admin');

        assert.equal(res.status, 200, `Prompt failed (${prompt}): status=${res.status}`);
        assert.equal(res.body?.success, true, `Prompt unsuccessful (${prompt}): ${res.text}`);

        const reply = res.body?.data?.reply || '';
        assert.ok(reply.length > 40, `Reply too short for "${prompt}": ${reply}`);
        assert.ok(
          !reply.includes('Dạ bạn có thể bấm vào mục'),
          `Customer ordering FAQ must NEVER hijack Admin prompt "${prompt}". Got: ${reply}`
        );
        assert.ok(
          !RAW_HTML_TAG_REGEX.test(reply),
          `Admin reply for "${prompt}" must NOT contain raw HTML tags. Got: ${reply}`
        );
      }

      // Specific check for the screenshot bug prompt: Revenue & Peak Hours
      const revRes = await client.post('/admin/api.php?action=admin_ai_copilot', {
        action: 'admin_ai_copilot',
        prompt: 'Phân tích tình hình doanh thu và các giờ cao điểm đặt món'
      }, {}, 'admin');
      const revReply = revRes.body?.data?.reply || '';
      assert.match(revReply, /doanh thu|Doanh Thu/i, `Revenue reply missing revenue stats: ${revReply}`);
      assert.match(revReply, /cao điểm|đơn/i, `Revenue reply missing peak-hour/order analysis: ${revReply}`);

      // Specific check for 1-star complaint SOP prompt
      const sopRes = await client.post('/admin/api.php?action=admin_ai_copilot', {
        action: 'admin_ai_copilot',
        prompt: 'Hướng dẫn quy trình chuẩn xử lý khi khách hàng đánh giá 1 sao vì đồ ăn giao trễ'
      }, {}, 'admin');
      const sopReply = sopRes.body?.data?.reply || '';
      assert.match(sopReply, /1 sao|khiếu nại|xin lỗi|quy trình/i, `SOP reply missing handling steps: ${sopReply}`);
    });

    it('4. Customer FAQ endpoint api/ai-chat.php outputs clean Markdown with zero raw HTML tags', async () => {
      const client = new TestClient(baseUrl);
      const testQuestions = [
        'đặt món',
        'phí ship giao hàng',
        'voucher khuyến mãi',
        'mở cửa mấy giờ',
        'xin chào'
      ];

      for (const message of testQuestions) {
        const res = await client.post('/api/ai-chat.php', { message });
        assert.equal(res.status, 200, `api/ai-chat.php failed for "${message}"`);
        assert.equal(res.body?.success, true);

        const reply = res.body?.data?.reply || res.body?.reply || '';
        assert.ok(reply.length > 10, `Empty reply for "${message}" (body=${res.text})`);
        assert.ok(
          !RAW_HTML_TAG_REGEX.test(reply),
          `api/ai-chat.php reply for "${message}" must use Markdown, not raw HTML. Got: ${reply}`
        );
      }
    });
  });
}
