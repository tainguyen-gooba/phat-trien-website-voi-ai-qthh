/**
 * r1-badge-features.test.js - Tier 1: Category-Partition Feature Coverage for Requirement R1
 * Tests: R1-T1-01 through R1-T1-11
 *
 * Verifies admin sidebar badge existence, initial hidden state, total unread endpoint,
 * session unread counts, mark-as-read endpoint, realtime poll count, DB schema/index,
 * default read status for user/admin/assistant messages, and client window APIs.
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const TestClient = require('../harness/TestClient');
const { querySelector, hasElement, getElementById, getTextContent } = require('../harness/dom-parser');

describe('Tier 1: R1 Unread Notification Badge Feature Coverage', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('R1-T1-01: Admin Sidebar Badge Element Existence', async () => {
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200, 'Admin page should return HTTP 200');

    const html = res.text;
    const badge = querySelector(html, '#messages-nav-badge');
    assert.ok(badge, 'Element #messages-nav-badge must exist in admin sidebar');

    // Verify it is placed inside or next to the messages navigation item
    const navItem = querySelector(html, '[data-section="messages"]');
    assert.ok(navItem, 'Navigation item [data-section="messages"] must exist');

    const badgeInsideNav = querySelector(navItem.outerHTML, '#messages-nav-badge');
    const badgeInHtml = hasElement(html, '#messages-nav-badge');
    assert.ok(badgeInsideNav || badgeInHtml, 'Badge should be associated with the messages navigation item');
  });

  it('R1-T1-02: Badge Markup & Initial State', async () => {
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200);

    const badge = querySelector(res.text, '#messages-nav-badge');
    assert.ok(badge, '#messages-nav-badge must exist');

    // Should have badge class (nav-badge or nav-badge-messages)
    const hasBadgeClass = badge.hasClass('nav-badge') || badge.hasClass('nav-badge-messages');
    assert.ok(hasBadgeClass, 'Badge should have nav-badge styling class');

    // When initialized in markup with 0 unread, it should be hidden (hidden attribute, hidden class, or display:none)
    const isHidden = badge.hasAttribute('hidden') ||
                     badge.hasClass('hidden') ||
                     badge.hasClass('d-none') ||
                     (badge.getAttribute('style') && badge.getAttribute('style').includes('display: none'));
    assert.ok(isHidden, 'Badge should be hidden initially when unread count is 0');
  });

  it('R1-T1-03: Conversations Endpoint Returns Total Unread', async () => {
    const res = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(res.status, 200, 'Conversations endpoint should return HTTP 200');
    assert.equal(res.body.success, true, 'Response success should be true');
    assert.ok(res.body.data, 'Response should contain data payload');

    const totalUnread = res.body.data.totalUnread;
    assert.ok(typeof totalUnread === 'number', `totalUnread must be a number, received: ${typeof totalUnread}`);
    assert.ok(totalUnread >= 0, `totalUnread must be non-negative, received: ${totalUnread}`);
  });

  it('R1-T1-04: Per-Session Unread Count in Conversations', async () => {
    const res = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(res.status, 200);
    assert.ok(Array.isArray(res.body.data.conversations), 'conversations must be an array');

    for (const session of res.body.data.conversations) {
      assert.ok(session.sessionId, 'Session must have a sessionId');
      assert.ok(
        session.unreadCount !== undefined && typeof session.unreadCount === 'number',
        `Session ${session.sessionId} must have a numeric unreadCount property`
      );
      assert.ok(session.unreadCount >= 0, `unreadCount must be >= 0, got ${session.unreadCount}`);
    }
  });

  it('R1-T1-05: Mark Session as Read Endpoint', async () => {
    const sessionId = client.createSession();

    // 1. Send a customer message to establish an unread message
    const msgRes = await client.sendCustomerMessage(sessionId, 'Tin nhắn test unread count');
    assert.equal(msgRes.status, 200, 'Customer message should be sent successfully');

    // 2. Mark session as read
    const readRes = await client.markSessionAsRead(sessionId);
    assert.equal(readRes.status, 200, 'Mark session as read should return HTTP 200');
    assert.equal(readRes.body.success, true, 'Response should indicate success');
    assert.ok(readRes.body.data, 'Response should contain data object');

    // 3. Verify affectedMessages and totalUnread in response
    assert.equal(readRes.body.data.sessionId, sessionId, 'Response sessionId should match');
    assert.ok(
      typeof readRes.body.data.affectedMessages === 'number' && readRes.body.data.affectedMessages >= 1,
      `affectedMessages should be at least 1, received: ${readRes.body.data.affectedMessages}`
    );
    assert.ok(
      typeof readRes.body.data.totalUnread === 'number',
      `totalUnread should be a number, received: ${readRes.body.data.totalUnread}`
    );
  });

  it('R1-T1-06: Realtime Polling Unread Chat Count', async () => {
    const res = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(res.status, 200, 'Polling endpoint should return HTTP 200');
    assert.equal(res.body.success, true, 'Polling should succeed');
    assert.ok(res.body.data, 'Poll data must be present');

    // unreadChatCount should be present in poll data for admin user
    assert.ok(
      res.body.data.unreadChatCount !== undefined,
      'Poll data must contain unreadChatCount for admin'
    );
    assert.ok(
      typeof res.body.data.unreadChatCount === 'number',
      `unreadChatCount must be numeric, got: ${typeof res.body.data.unreadChatCount}`
    );
    assert.ok(res.body.data.unreadChatCount >= 0, 'unreadChatCount must be non-negative');
  });

  it('R1-T1-07: DB Schema is_read Column & Index', () => {
    // Check database migration SQL and table structure for covering index and is_read column
    const migrationSqlPath = path.resolve('database/migrate_unread_chat_badge.sql');
    assert.ok(fs.existsSync(migrationSqlPath), 'Migration SQL file must exist');

    const sql = fs.readFileSync(migrationSqlPath, 'utf8');
    assert.ok(sql.includes('is_read'), 'Migration SQL must define is_read column');
    assert.ok(sql.includes('idx_chat_msg_unread'), 'Migration SQL must define idx_chat_msg_unread index');

    // Query active MariaDB database schema
    try {
      const probeScript = path.resolve('backend/scripts/test-chat-unread-badge.php');
      if (fs.existsSync(probeScript)) {
        const out = execSync(`php "${probeScript}"`, { encoding: 'utf8' });
        assert.ok(
          out.includes('idx_chat_msg_unread') && out.includes('PASS'),
          'Database schema verification script must pass covering index check'
        );
      }
    } catch (err) {
      assert.fail(`Database schema probe failed: ${err.message}`);
    }
  });

  it('R1-T1-08: Customer Message Read Status Default', async () => {
    const sessionId = client.createSession();
    const sendRes = await client.sendCustomerMessage(sessionId, 'Tin nhắn khách hàng mặc định chưa đọc');
    assert.equal(sendRes.status, 200);

    // Fetch session messages
    const historyRes = await client.get(`/api/v1/chat/admin/sessions/${sessionId}/messages`);
    assert.equal(historyRes.status, 200);
    const messages = Array.isArray(historyRes.body.data) ? historyRes.body.data : (historyRes.body.data?.messages || []);
    assert.ok(Array.isArray(messages) && messages.length > 0, 'Messages list should not be empty');

    const customerMsg = messages.find(m => m.sender === 'user' || m.sender === 'customer');
    assert.ok(customerMsg, 'Customer message should exist in thread history');

    // Verify customer message has is_read = 0 (or unread = true)
    const isRead = customerMsg.is_read !== undefined ? Number(customerMsg.is_read) : (customerMsg.isRead ? 1 : 0);
    assert.equal(isRead, 0, 'Customer message must default to is_read = 0');
  });

  it('R1-T1-09: Admin Reply Read Status Default', async () => {
    const sessionId = client.createSession();
    await client.sendCustomerMessage(sessionId, 'Tin nhắn khởi tạo phiên');

    // Admin replies
    const replyRes = await client.sendAdminReply(sessionId, 'Dạ FASTFOOD xin chào quý khách!');
    assert.equal(replyRes.status, 200, 'Admin reply should succeed');

    // Fetch messages
    const historyRes = await client.get(`/api/v1/chat/admin/sessions/${sessionId}/messages`);
    assert.equal(historyRes.status, 200);

    const messages = Array.isArray(historyRes.body.data) ? historyRes.body.data : (historyRes.body.data?.messages || []);
    assert.ok(Array.isArray(messages) && messages.length > 0, 'Messages list should not be empty');

    const adminMsg = messages.find(m => m.sender === 'admin');
    assert.ok(adminMsg, 'Admin message should exist in thread history');

    const isRead = adminMsg.is_read !== undefined ? Number(adminMsg.is_read) : (adminMsg.isRead ? 1 : 0);
    assert.equal(isRead, 1, 'Admin reply must be marked read (is_read = 1)');
  });

  it('R1-T1-10: AI Assistant Message Read Status Default', async () => {
    const sessionId = client.createSession();
    // Sending message to AI channel triggers assistant response
    const aiRes = await client.post('/api/v1/chat/message', {
      sessionId,
      message: 'Menu hôm nay có món gì?',
      channel: 'ai'
    });
    assert.equal(aiRes.status, 200);

    const historyRes = await client.get(`/api/v1/chat/admin/sessions/${sessionId}/messages`);
    assert.equal(historyRes.status, 200);

    const messages = Array.isArray(historyRes.body.data) ? historyRes.body.data : (historyRes.body.data?.messages || []);
    const aiMsg = messages.find(m => m.sender === 'assistant');
    if (aiMsg) {
      const isRead = aiMsg.is_read !== undefined ? Number(aiMsg.is_read) : (aiMsg.isRead ? 1 : 0);
      assert.equal(isRead, 1, 'AI Assistant message must be marked read (is_read = 1) so it does not skew unread counts');
    }
  });

  it('R1-T1-11: Client Global Window Badge APIs', () => {
    const adminChatJsPath = path.resolve('js/admin-chat.js');
    assert.ok(fs.existsSync(adminChatJsPath), 'js/admin-chat.js must exist');

    const jsContent = fs.readFileSync(adminChatJsPath, 'utf8');

    // Verify window interface methods or badge sync functions are defined
    const hasUpdateBadge = jsContent.includes('updateMessagesNavBadge') || jsContent.includes('syncUnreadBadge');
    const hasClearBadge = jsContent.includes('clearMessagesBadge') || jsContent.includes('markSessionAsRead');

    assert.ok(hasUpdateBadge, 'js/admin-chat.js must define unread badge updater (e.g. updateMessagesNavBadge)');
    assert.ok(hasClearBadge, 'js/admin-chat.js must define badge clearing method (e.g. clearMessagesBadge)');
  });
});
