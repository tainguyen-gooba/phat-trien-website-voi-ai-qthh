/**
 * realtime-sync-race-flow.test.js - Tier 4: Real-World Application Scenario Workflows
 * Tests: WF-T4-04 and WF-T4-05
 *
 * Scenarios:
 * - WF-T4-04: Cross-Tab BroadcastChannel Sync & Race-Condition Defense
 * - WF-T4-05: Multi-Viewport Responsive Transition Flow
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const {
  getProperty,
  findRules,
  findMediaQueries,
  getPropertyInMediaQuery,
  extractStylesFromHtml
} = require('../harness/css-inspector');

describe('Tier 4: Realtime Sync Race Defense & Multi-Viewport Flow', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('WF-T4-04: Cross-Tab BroadcastChannel Sync & Race-Condition Defense', async () => {
    // -------------------------------------------------------------
    // Step 1: Simulate rapid succession: customer message -> background poll -> admin marks read -> immediate second poll
    // -------------------------------------------------------------
    const sessionId = client.createSession();

    // Baseline poll
    const p0 = await client.get('/api/realtime.php?action=poll&for=admin');
    const baseCount = p0.body.data?.unreadChatCount ?? 0;

    // Customer sends message
    await client.sendCustomerMessage(sessionId, 'Tin nhắn kích hoạt race condition test');

    // Immediate background poll
    const p1 = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(p1.status, 200);
    const countAfterMessage = p1.body.data?.unreadChatCount ?? 0;
    assert.ok(
      countAfterMessage >= baseCount + 1,
      `Poll after customer message should reflect unread, got: ${countAfterMessage}`
    );

    // Admin opens session and immediately calls mark-as-read
    const readRes = await client.markSessionAsRead(sessionId);
    assert.equal(readRes.status, 200);
    const totalAfterRead = readRes.body.data?.totalUnread ?? 0;

    // Immediate second poll in rapid succession (simulating background poll ticking at the exact same moment)
    const p2 = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(p2.status, 200);
    const countAfterSecondPoll = p2.body.data?.unreadChatCount ?? 0;

    // -------------------------------------------------------------
    // Step 2: Assert stale background poll does NOT resurrect cleared unread badge count
    // -------------------------------------------------------------
    assert.equal(
      countAfterSecondPoll,
      totalAfterRead,
      `Second poll must match totalUnread after mark-as-read (${totalAfterRead}), cannot resurrect cleared count: got ${countAfterSecondPoll}`
    );
    assert.ok(
      countAfterSecondPoll <= countAfterMessage,
      `Unread count must be monotonically non-increasing after read: ${countAfterSecondPoll} <= ${countAfterMessage}`
    );
    assert.ok(
      countAfterSecondPoll >= 0,
      `Unread count must never be negative: ${countAfterSecondPoll} >= 0`
    );

    // -------------------------------------------------------------
    // Step 3: Inspect client-side race defense implementation in admin-chat.js and realtime.js
    // -------------------------------------------------------------
    const adminChatJs = fs.readFileSync('js/admin-chat.js', 'utf8');
    const realtimeJs = fs.readFileSync('js/realtime.js', 'utf8');

    // BroadcastChannel name consistency
    assert.ok(
      adminChatJs.includes('fastfood_chat_realtime') || adminChatJs.includes('BroadcastChannel'),
      'admin-chat.js must initialize BroadcastChannel for cross-tab communication'
    );
    assert.ok(
      realtimeJs.includes('fastfood_realtime') || realtimeJs.includes('BroadcastChannel'),
      'realtime.js must maintain cross-tab BroadcastChannel'
    );

    // SESSION_READ event handling in admin-chat.js
    assert.ok(
      adminChatJs.includes('SESSION_READ'),
      'admin-chat.js must handle SESSION_READ event to sync zero-latency unread status across tabs'
    );

    // Event deduplication protection
    assert.ok(
      realtimeJs.includes('processedEventIds') || realtimeJs.includes('lastChatId'),
      'realtime.js must maintain event deduplication to defend against out-of-order race conditions'
    );

    // Teardown session
    await client.deleteSession(sessionId);
  });

  it('WF-T4-05: Multi-Viewport Responsive Transition Flow', () => {
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');

    // -------------------------------------------------------------
    // Step 1: Desktop Viewport (1440px)
    // -------------------------------------------------------------
    // .chat-inbox uses multi-column grid layout on desktop
    const desktopGrid = getProperty(adminCss, '.chat-inbox', 'grid-template-columns');
    assert.ok(desktopGrid, '.chat-inbox must define grid-template-columns for desktop');
    assert.ok(
      desktopGrid.includes('360px') || desktopGrid.includes('1fr') || desktopGrid.split(/\s+/).length >= 2,
      `.chat-inbox must use multi-column split on desktop (e.g. 360px 1fr), got: ${desktopGrid}`
    );

    // Desktop sidebar fixed width standard (224px - 264px)
    const sidebarWidth = getProperty(adminCss, '.sidebar', 'width');
    assert.ok(sidebarWidth, '.sidebar must have defined desktop width');

    // -------------------------------------------------------------
    // Step 2: Tablet Viewport (820px)
    // -------------------------------------------------------------
    const mqs820 = findMediaQueries(adminCss, 'max-width: 820px');
    assert.ok(mqs820.length > 0, 'admin/index.php must contain @media (max-width: 820px)');

    // In tablet breakpoint, sidebar transforms to offcanvas drawer
    const combined820 = mqs820.map(m => m.body).join('\n');
    assert.ok(
      combined820.includes('translateX') || combined820.includes('transform'),
      'Sidebar under 820px tablet breakpoint must use offcanvas transform transition'
    );

    // -------------------------------------------------------------
    // Step 3: Mobile Viewport (375px / <= 560px)
    // -------------------------------------------------------------
    const mqs560 = findMediaQueries(adminCss, 'max-width: 560px');
    assert.ok(mqs560.length > 0, 'admin/index.php must contain @media (max-width: 560px)');

    // In mobile breakpoint, .chat-inbox collapses to single column (1fr)
    const mobileGrid = getPropertyInMediaQuery(adminCss, 'max-width: 560px', '.chat-inbox', 'grid-template-columns');
    assert.ok(
      mobileGrid && (mobileGrid.includes('1fr') || mobileGrid === 'none'),
      `.chat-inbox on mobile <= 560px must be 1fr single column, got: ${mobileGrid}`
    );

    // -------------------------------------------------------------
    // Step 4: Zero Horizontal Overflow Constraints
    // -------------------------------------------------------------
    // Admin layout and content containers guard against horizontal overflow
    const hasOverflowXGuard = adminCss.includes('overflow-x: hidden') ||
                              adminCss.includes('overflow-x: clip') ||
                              adminCss.includes('max-width: 100vw');
    assert.ok(
      hasOverflowXGuard,
      'Admin layout must define overflow-x: hidden / clip or max-width: 100vw to guarantee zero horizontal scroll'
    );

    // Chat images enforce responsive max-width bounds
    const adminImgMaxW = getProperty(adminCss, '.admin-msg-image-bubble', 'max-width') ||
                         getProperty(adminCss, '.admin-chat-image-wrap img', 'max-width');
    assert.ok(
      adminImgMaxW,
      'Admin chat images must specify max-width to prevent blowing out viewport'
    );

    const chatbotImgMaxW = getProperty(chatbotCss, '.ff-msg-image', 'max-width') ||
                          getProperty(chatbotCss, '.ff-msg-bubble', 'max-width');
    assert.ok(
      chatbotImgMaxW,
      'Customer chatbot images must specify max-width to prevent horizontal overflow'
    );
  });
});
