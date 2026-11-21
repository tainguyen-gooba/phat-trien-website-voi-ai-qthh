/**
 * customer-admin-chat-flow.test.js - Tier 4: Real-World Application Scenario Workflows
 * Tests: WF-T4-01 and WF-T4-02
 *
 * Scenarios:
 * - WF-T4-01: Customer-to-Admin Unread Notification & Clearing Flow
 * - WF-T4-02: Customer Image Upload to Admin Lightbox Zoom Flow
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const { querySelector, hasElement } = require('../harness/dom-parser');
const { getProperty, extractStylesFromHtml } = require('../harness/css-inspector');
const { getValidJpgBuffer } = require('../harness/fixtures');

describe('Tier 4: Customer-Admin Chat Real-World Scenarios', () => {
  let client;

  before(async () => {
    TestClient.resetRateLimits();
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('WF-T4-01: Customer-to-Admin Unread Notification & Clearing Flow', async () => {
    // -------------------------------------------------------------
    // Step 1: Record baseline unread counts
    // -------------------------------------------------------------
    const baselinePollRes = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(baselinePollRes.status, 200, 'Baseline poll should return HTTP 200');
    const baselineUnreadCount = baselinePollRes.body.data?.unreadChatCount ?? 0;

    const baselineConvRes = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(baselineConvRes.status, 200, 'Baseline conversations should return HTTP 200');
    const baselineTotalUnread = baselineConvRes.body.data?.totalUnread ?? 0;

    // -------------------------------------------------------------
    // Step 2: Customer initiates session and sends message
    // -------------------------------------------------------------
    const sessionId = client.createSession();
    const customerMsg = 'Chào FASTFOOD, mình muốn hỏi về combo trưa cho 4 người';
    const sendRes = await client.sendCustomerMessage(sessionId, customerMsg, {
      customerName: 'Workflow Test Customer'
    });
    assert.equal(sendRes.status, 200, 'Customer message must be accepted');
    assert.equal(sendRes.body.success, true);

    // -------------------------------------------------------------
    // Step 3: Sidebar unread count increments dynamically in poll & conversations API
    // -------------------------------------------------------------
    const pollAfterRes = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(pollAfterRes.status, 200);
    const updatedPollCount = pollAfterRes.body.data?.unreadChatCount;
    assert.equal(
      updatedPollCount,
      baselineUnreadCount + 1,
      `Polling unreadChatCount should increment by 1: expected ${baselineUnreadCount + 1}, got ${updatedPollCount}`
    );

    const convAfterRes = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(convAfterRes.status, 200);
    const sessionInList = convAfterRes.body.data.conversations.find(c => c.sessionId === sessionId);
    assert.ok(sessionInList, 'Created session must appear in conversations list');
    assert.equal(
      sessionInList.unreadCount,
      1,
      `Session unreadCount should be 1, got ${sessionInList.unreadCount}`
    );
    assert.equal(
      convAfterRes.body.data.totalUnread,
      baselineTotalUnread + 1,
      `totalUnread should increment by 1: expected ${baselineTotalUnread + 1}, got ${convAfterRes.body.data.totalUnread}`
    );

    // -------------------------------------------------------------
    // Step 4: Admin opens session and calls mark-as-read
    // -------------------------------------------------------------
    const readRes = await client.markSessionAsRead(sessionId);
    assert.equal(readRes.status, 200, 'Mark-as-read endpoint should return HTTP 200');
    assert.equal(readRes.body.success, true);
    assert.equal(readRes.body.data.sessionId, sessionId);
    assert.ok(
      readRes.body.data.affectedMessages >= 1,
      `affectedMessages should be at least 1, got: ${readRes.body.data.affectedMessages}`
    );

    // -------------------------------------------------------------
    // Step 5: Badge count decrements / clears to 0
    // -------------------------------------------------------------
    const convClearedRes = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(convClearedRes.status, 200);
    const sessionCleared = convClearedRes.body.data.conversations.find(c => c.sessionId === sessionId);
    assert.ok(sessionCleared, 'Session should remain in conversations list');
    assert.equal(
      sessionCleared.unreadCount,
      0,
      `Session unreadCount must clear to 0 after mark-as-read, got ${sessionCleared.unreadCount}`
    );
    assert.equal(
      convClearedRes.body.data.totalUnread,
      baselineTotalUnread,
      `totalUnread should decrement back to baseline ${baselineTotalUnread}, got ${convClearedRes.body.data.totalUnread}`
    );

    // Also verify realtime poll reflects the cleared badge count
    const pollClearedRes = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(pollClearedRes.status, 200);
    assert.equal(
      pollClearedRes.body.data?.unreadChatCount,
      baselineUnreadCount,
      `Poll unreadChatCount should decrement back to baseline ${baselineUnreadCount}, got ${pollClearedRes.body.data?.unreadChatCount}`
    );

    // -------------------------------------------------------------
    // Step 6: Admin sends reply
    // -------------------------------------------------------------
    const replyText = 'Dạ FASTFOOD chào bạn! Quán có Combo Trưa 4 người ưu đãi 299k gồm 4 burger + 4 khoai + 4 nước ngọt ạ.';
    const replyRes = await client.sendAdminReply(sessionId, replyText);
    assert.equal(replyRes.status, 200, 'Admin reply should succeed');
    assert.equal(replyRes.body.success, true);
    assert.equal(replyRes.body.data.message.sender, 'admin');

    // -------------------------------------------------------------
    // Step 7: Session cleaned up via HTTP DELETE
    // -------------------------------------------------------------
    const deleteRes = await client.deleteSession(sessionId);
    assert.equal(deleteRes.status, 200, 'Session deletion should return HTTP 200');

    // Verify session is removed from active conversations
    const convFinalRes = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(convFinalRes.status, 200);
    const sessionDeleted = convFinalRes.body.data.conversations.find(c => c.sessionId === sessionId);
    assert.equal(sessionDeleted, undefined, 'Deleted session must no longer appear in conversations list');
  });

  it('WF-T4-02: Customer Image Upload to Admin Lightbox Zoom Flow', async () => {
    // -------------------------------------------------------------
    // Step 1: Customer uploads food photo via multipart API
    // -------------------------------------------------------------
    const validJpg = getValidJpgBuffer();
    const uploadRes = await client.uploadChatImage(validJpg, 'customer_dish_query.jpg', 'image/jpeg');
    assert.equal(uploadRes.status, 200, 'Multipart image upload must succeed with HTTP 200');
    assert.equal(uploadRes.body.success, true, 'Upload response must be success');
    assert.ok(uploadRes.body.data?.url, 'Uploaded file URL must be returned');
    assert.ok(
      uploadRes.body.data.url.startsWith('/uploads/chat/'),
      `Image URL must point to /uploads/chat/, got: ${uploadRes.body.data.url}`
    );
    const imageUrl = uploadRes.body.data.url;

    // -------------------------------------------------------------
    // Step 2: Image message saved in thread
    // -------------------------------------------------------------
    const sessionId = client.createSession();
    const sendRes = await client.sendCustomerMessage(
      sessionId,
      'Cho mình hỏi quán có phục vụ món này không?',
      {
        messageType: 'image',
        imageUrl: imageUrl,
        metadata: {
          type: 'image',
          imageUrl: imageUrl,
          width: uploadRes.body.data.width || 800,
          height: uploadRes.body.data.height || 600
        }
      }
    );
    assert.equal(sendRes.status, 200, 'Message with image must be saved');

    // -------------------------------------------------------------
    // Step 3: Admin fetches thread and receives message with image payload
    // -------------------------------------------------------------
    const threadRes = await client.get(`/api/v1/chat/admin/sessions/${sessionId}/messages`);
    assert.equal(threadRes.status, 200, 'Admin messages retrieval must succeed');
    const messages = Array.isArray(threadRes.body.data)
      ? threadRes.body.data
      : (threadRes.body.data?.messages || []);

    const imageMsg = messages.find(m => m.messageType === 'image' || (m.metadata && m.metadata.imageUrl));
    assert.ok(imageMsg, 'Image message must exist in the admin thread history');
    const receivedUrl = imageMsg.metadata?.imageUrl || imageMsg.imageUrl;
    assert.equal(receivedUrl, imageUrl, 'Thread message image URL must match customer uploaded URL');

    // -------------------------------------------------------------
    // Step 4: Admin HTML / CSS verifies object-fit: contain thumbnail display
    // -------------------------------------------------------------
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // Verify .admin-chat-image-thumb or .admin-msg-image-bubble specifies object-fit: contain
    const thumbFit = getProperty(adminCss, '.admin-chat-image-thumb', 'object-fit') ||
                     getProperty(adminCss, '.admin-chat-image-wrap img', 'object-fit') ||
                     getProperty(adminCss, '.admin-msg-image-bubble img', 'object-fit');
    assert.equal(
      thumbFit,
      'contain',
      `Admin chat image thumbnail must use object-fit: contain to avoid cropping food photos, got: ${thumbFit}`
    );

    // -------------------------------------------------------------
    // Step 5: Lightbox modal open trigger & contain bounds verified
    // -------------------------------------------------------------
    assert.ok(
      adminHtml.includes('id="admin-chat-lightbox-modal"') || hasElement(adminHtml, '#admin-chat-lightbox-modal'),
      'Admin layout must contain #admin-chat-lightbox-modal markup'
    );

    // Lightbox image element contains object-fit: contain and viewport max bounds
    const lightboxImgFit = getProperty(adminCss, '.admin-lightbox-img', 'object-fit') ||
                           getProperty(adminCss, '.admin-lightbox-container img', 'object-fit');
    assert.equal(
      lightboxImgFit,
      'contain',
      `Admin lightbox modal image must use object-fit: contain, got: ${lightboxImgFit}`
    );

    const lightboxMaxW = getProperty(adminCss, '.admin-lightbox-img', 'max-width') ||
                         getProperty(adminCss, '.admin-lightbox-container', 'max-width') ||
                         getProperty(adminCss, '.admin-lightbox-container img', 'max-width');
    const lightboxMaxH = getProperty(adminCss, '.admin-lightbox-img', 'max-height') ||
                         getProperty(adminCss, '.admin-lightbox-container', 'max-height') ||
                         getProperty(adminCss, '.admin-lightbox-container img', 'max-height');

    assert.ok(lightboxMaxW, 'Lightbox must specify max-width');
    assert.ok(lightboxMaxH, 'Lightbox must specify max-height');

    // -------------------------------------------------------------
    // Step 6: Teardown session
    // -------------------------------------------------------------
    await client.deleteSession(sessionId);
  });
});
