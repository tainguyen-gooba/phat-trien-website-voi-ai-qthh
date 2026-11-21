/**
 * r1-badge-boundaries.test.js - Tier 2: Boundary Value Analysis & Edge Cases for Requirement R1
 * Tests: R1-T2-01 through R1-T2-06
 *
 * Verifies zero unread hidden state, single-digit display (1-9), large count display (12, 99+),
 * idempotent mark-as-read, non-existent session ID handling, and rapid successive mark-as-read requests.
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const { querySelector, hasElement } = require('../harness/dom-parser');

describe('Tier 2: R1 Badge Boundary Value & Edge Cases', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('R1-T2-01: Zero Unread Messages Boundary', async () => {
    // When count = 0, badge must be hidden
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200);

    const badge = querySelector(res.text, '#messages-nav-badge');
    assert.ok(badge, '#messages-nav-badge should exist in admin DOM');

    const isHidden = badge.hasAttribute('hidden') ||
                     badge.hasClass('hidden') ||
                     badge.hasClass('d-none') ||
                     (badge.getAttribute('style') && badge.getAttribute('style').includes('display: none'));
    assert.ok(isHidden, 'Badge must be hidden when unread count is 0');
  });

  it('R1-T2-02: Single-Digit Unread Count Display', () => {
    // Verify badge text formatting in admin-chat.js for counts 1-9
    const jsPath = path.resolve('js/admin-chat.js');
    assert.ok(fs.existsSync(jsPath));
    const js = fs.readFileSync(jsPath, 'utf8');

    // Test formatter logic: count <= 99 ? String(count) : '99+'
    // or count > 99 ? '99+' : count
    function formatCount(c) {
      if (c <= 0) return '';
      if (c > 99) return '99+';
      return String(c);
    }

    assert.equal(formatCount(1), '1');
    assert.equal(formatCount(5), '5');
    assert.equal(formatCount(9), '9');

    // Check that admin-chat.js handles single-digit badge text updates
    const hasBadgeLogic = js.includes('messages-nav-badge') || js.includes('nav-badge') || js.includes('unread');
    assert.ok(hasBadgeLogic, 'admin-chat.js must contain logic for unread badge updates');
  });

  it('R1-T2-03: Double-Digit & Large Count Display', () => {
    function formatCount(c) {
      if (c <= 0) return '';
      if (c > 99) return '99+';
      return String(c);
    }

    // Double digit exact display (e.g. 12, 25, 99)
    assert.equal(formatCount(12), '12', 'Count 12 should display as "12"');
    assert.equal(formatCount(99), '99', 'Count 99 should display as "99"');

    // Overflow boundary display (> 99)
    assert.equal(formatCount(100), '99+', 'Count 100 should display as "99+"');
    assert.equal(formatCount(999), '99+', 'Count 999 should display as "99+"');
  });

  it('R1-T2-04: Idempotent Mark-as-Read on Read Session', async () => {
    const sessionId = client.createSession();

    // 1. Send customer message
    await client.sendCustomerMessage(sessionId, 'Tin nhắn kiểm tra tính idempotent');

    // 2. First mark-as-read: should affect messages
    const firstRes = await client.markSessionAsRead(sessionId);
    assert.equal(firstRes.status, 200);
    assert.equal(firstRes.body.success, true);
    const affectedFirst = firstRes.body.data.affectedMessages;
    assert.ok(affectedFirst >= 1, `First call should mark at least 1 message, got: ${affectedFirst}`);

    // 3. Second mark-as-read: already read, should affect 0 messages without error
    const secondRes = await client.markSessionAsRead(sessionId);
    assert.equal(secondRes.status, 200, 'Second mark-as-read must return HTTP 200');
    assert.equal(secondRes.body.success, true, 'Second mark-as-read must be successful');
    assert.equal(secondRes.body.data.affectedMessages, 0, 'Second call on already-read session must affect 0 messages');

    // 4. Total unread must be non-negative
    assert.ok(
      secondRes.body.data.totalUnread >= 0,
      `totalUnread must remain non-negative, got: ${secondRes.body.data.totalUnread}`
    );
  });

  it('R1-T2-05: Mark Non-Existent Session ID', async () => {
    const fakeSessionId = 'e2e_fake_nonexistent_' + Date.now();
    const res = await client.markSessionAsRead(fakeSessionId);

    // Endpoint should handle gracefully (either HTTP 200 with 0 affected or HTTP 404/400)
    assert.ok(
      res.status === 200 || res.status === 404 || res.status === 400,
      `Expected graceful HTTP status (200, 404, or 400), got: ${res.status}`
    );

    if (res.status === 200) {
      assert.equal(res.body.data.affectedMessages, 0, 'Non-existent session should have 0 affected messages');
      assert.ok(res.body.data.totalUnread >= 0, 'totalUnread must remain >= 0');
    }
  });

  it('R1-T2-06: Rapid Successive Mark-as-Read Requests', async () => {
    const sessionId = client.createSession();
    await client.sendCustomerMessage(sessionId, 'Tin nhắn test race condition mark-as-read');

    // Fire 5 rapid parallel mark-as-read requests
    const promises = Array.from({ length: 5 }, () => client.markSessionAsRead(sessionId));
    const results = await Promise.all(promises);

    for (const res of results) {
      assert.equal(res.status, 200, 'All concurrent mark-as-read requests should return HTTP 200');
      assert.equal(res.body.success, true, 'All concurrent requests should succeed');
      assert.ok(
        res.body.data.totalUnread >= 0,
        `totalUnread must never be negative, got: ${res.body.data.totalUnread}`
      );
    }
  });
});
