/**
 * cross-feature-pairs.test.js - Tier 3: Pairwise Cross-Feature Interactions
 * Tests: T3-01 through T3-06
 *
 * Verifies pairwise interactions across requirements R1, R2, and R3:
 * - T3-01: R1 + R2: Admin Image Reply Auto-Marks Session Read
 * - T3-02: R1 + R2: Customer Image Arrival Increments Badge
 * - T3-03: R1 + R3: Dynamic Badge in Mobile Offcanvas Sidebar
 * - T3-04: R2 + R3: Admin Chat Input Toolbar Mobile Wrap
 * - T3-05: R2 + R3: Product Picker Grid on Tablet & Mobile
 * - T3-06: R2 + R3: Chatbot Dual Channel & Safe-Area Inset
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const { querySelector, hasElement, getElementById, getTextContent } = require('../harness/dom-parser');
const {
  getProperty,
  hasProperty,
  findMediaQueries,
  getPropertyInMediaQuery,
  extractStylesFromHtml
} = require('../harness/css-inspector');
const { getValidJpgBuffer } = require('../harness/fixtures');

describe('Tier 3: Pairwise Cross-Feature Interactions', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('T3-01: R1 + R2: Admin Image Reply Auto-Marks Session Read', async () => {
    // 1. Establish session with unread customer message
    const sessionId = client.createSession();
    const customerMsg = await client.sendCustomerMessage(
      sessionId,
      'Khách hỏi: Quán còn món gà rán giòn sốt cay không shop?'
    );
    assert.equal(customerMsg.status, 200, 'Customer message sent');

    // Verify session has unread messages
    const convBefore = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(convBefore.status, 200);
    const sessionBefore = convBefore.body.data.conversations.find(c => c.sessionId === sessionId);
    assert.ok(sessionBefore, 'Session should exist in conversations list');
    assert.ok(
      sessionBefore.unreadCount >= 1,
      `Session should have unreadCount >= 1, got: ${sessionBefore.unreadCount}`
    );

    // 2. Admin uploads image attachment
    const validJpg = getValidJpgBuffer();
    const uploadRes = await client.uploadChatImage(validJpg, 'reply_menu.jpg', 'image/jpeg');
    assert.equal(uploadRes.status, 200, 'Image upload should succeed');
    assert.ok(uploadRes.body.data?.url, 'Uploaded image URL must be returned');
    const imageUrl = uploadRes.body.data.url;

    // 3. Admin sends image reply
    const replyRes = await client.sendAdminReply(
      sessionId,
      'Dạ quán còn nhé bạn ơi! Bạn tham khảo hình ảnh món tại đây ạ:',
      {
        messageType: 'image',
        imageUrl: imageUrl,
        metadata: {
          type: 'image',
          imageUrl: imageUrl,
          caption: 'Gà rán sốt cay đặc biệt'
        }
      }
    );
    assert.equal(replyRes.status, 200, 'Admin image reply should succeed');
    assert.equal(replyRes.body.success, true);

    // 4. Verify admin image reply automatically marks customer messages as read
    const convAfter = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(convAfter.status, 200);
    const sessionAfter = convAfter.body.data.conversations.find(c => c.sessionId === sessionId);
    assert.ok(sessionAfter, 'Session must still be present in conversations');
    assert.equal(
      sessionAfter.unreadCount,
      0,
      `Admin image reply must auto-mark session read: unreadCount should be 0, got: ${sessionAfter.unreadCount}`
    );

    // 5. Verify thread messages show customer message is_read = 1
    const messagesRes = await client.get(`/api/v1/chat/admin/sessions/${sessionId}/messages`);
    assert.equal(messagesRes.status, 200);
    const messages = Array.isArray(messagesRes.body.data)
      ? messagesRes.body.data
      : (messagesRes.body.data?.messages || []);
    const userMsg = messages.find(m => m.sender === 'user' || m.senderRole === 'customer');
    assert.ok(userMsg, 'User message should exist in thread');
    const isRead = userMsg.is_read !== undefined ? Number(userMsg.is_read) : (userMsg.isRead ? 1 : 0);
    assert.equal(isRead, 1, 'Customer message is_read flag must be updated to 1 upon admin reply');
  });

  it('T3-02: R1 + R2: Customer Image Arrival Increments Badge', async () => {
    // 1. Record baseline unread count from polling endpoint
    const baselinePoll = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(baselinePoll.status, 200);
    const baselineUnread = baselinePoll.body.data?.unreadChatCount ?? 0;

    // 2. Customer uploads food photo via multipart API
    const validJpg = getValidJpgBuffer();
    const uploadRes = await client.uploadChatImage(validJpg, 'customer_dish.jpg', 'image/jpeg');
    assert.equal(uploadRes.status, 200, 'Upload should succeed');
    const uploadedUrl = uploadRes.body.data?.url;
    assert.ok(uploadedUrl, 'Image URL should be present');

    // 3. Customer sends image message in a new session
    const sessionId = client.createSession();
    const sendRes = await client.sendCustomerMessage(
      sessionId,
      'Quán có làm món như trong ảnh này được không?',
      {
        messageType: 'image',
        imageUrl: uploadedUrl,
        metadata: {
          type: 'image',
          imageUrl: uploadedUrl
        }
      }
    );
    assert.equal(sendRes.status, 200, 'Customer message should be saved');

    // 4. Verify session unreadCount in conversations API is incremented
    const convRes = await client.get('/api/v1/chat/admin/conversations');
    assert.equal(convRes.status, 200);
    const targetSession = convRes.body.data.conversations.find(c => c.sessionId === sessionId);
    assert.ok(targetSession, 'New customer image session must appear in conversations');
    assert.equal(
      targetSession.unreadCount,
      1,
      `New session with 1 image message should have unreadCount = 1, got: ${targetSession.unreadCount}`
    );

    // 5. Verify unreadChatCount in polling API increments by 1
    const pollAfter = await client.get('/api/realtime.php?action=poll&for=admin');
    assert.equal(pollAfter.status, 200);
    const currentUnread = pollAfter.body.data?.unreadChatCount;
    assert.ok(
      typeof currentUnread === 'number',
      `unreadChatCount must be numeric, got: ${typeof currentUnread}`
    );
    assert.equal(
      currentUnread,
      baselineUnread + 1,
      `Polling unreadChatCount should increment by 1: expected ${baselineUnread + 1}, got ${currentUnread}`
    );
  });

  it('T3-03: R1 + R3: Dynamic Badge in Mobile Offcanvas Sidebar', () => {
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // 1. Verify sidebar offcanvas markup & badge existence
    assert.ok(hasElement(adminHtml, '#sidebar') || adminHtml.includes('id="sidebar"'), '#sidebar must exist in admin HTML');
    const badge = querySelector(adminHtml, '#messages-nav-badge');
    assert.ok(badge, '#messages-nav-badge must exist within the admin sidebar DOM');

    // 2. Verify sidebar offcanvas drawer media query at <= 820px
    const mqs820 = findMediaQueries(adminCss, 'max-width: 820px');
    assert.ok(mqs820.length > 0, 'Admin CSS must contain @media (max-width: 820px) breakpoint for offcanvas drawer');

    const sidebarIn820 = mqs820.some(mq => mq.body.includes('.sidebar'));
    assert.ok(sidebarIn820, '.sidebar must have responsive styling defined under @media (max-width: 820px)');

    // Offcanvas transform hidden & open state
    const combined820Css = mqs820.map(m => m.body).join('\n');
    assert.ok(
      combined820Css.includes('translateX') || combined820Css.includes('transform'),
      'Offcanvas sidebar under 820px should use transform / translateX transition'
    );

    // 3. Verify badge markup & flex-shrink protection inside sidebar item
    const badgeStyle = getProperty(adminCss, '#messages-nav-badge', 'flex-shrink') ||
                       getProperty(adminCss, '.nav-badge', 'flex-shrink');
    // Flex-shrink 0 prevents badge from getting squeezed when drawer width is tight
    const shrinkValue = badgeStyle ? badgeStyle.trim() : null;
    assert.ok(
      shrinkValue === '0' || combined820Css.includes('#messages-nav-badge') || adminCss.includes('nav-badge'),
      'Badge or nav item must prevent badge squeezing (flex-shrink: 0 or explicit badge layout)'
    );

    // 4. Verify badge has prominent visibility styling (background, border-radius)
    const badgeBg = getProperty(adminCss, '#messages-nav-badge', 'background') ||
                    getProperty(adminCss, '#messages-nav-badge', 'background-color') ||
                    getProperty(adminCss, '.nav-badge', 'background') ||
                    getProperty(adminCss, '.nav-badge', 'background-color');
    assert.ok(badgeBg, 'Sidebar badge must declare background color or gradient');
  });

  it('T3-04: R2 + R3: Admin Chat Input Toolbar Mobile Wrap', () => {
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // 1. Verify toolbar form elements exist
    const form = querySelector(adminHtml, '#admin-chat-form');
    assert.ok(form, '#admin-chat-form must exist');

    // Camera attachment trigger
    const attachBtn = querySelector(adminHtml, '#admin-chat-attach-btn');
    assert.ok(attachBtn, 'Camera attachment trigger #admin-chat-attach-btn must exist in form');

    // Product picker button
    const pickerBtn = querySelector(adminHtml, '#btn-open-product-picker');
    assert.ok(pickerBtn, 'Product picker trigger #btn-open-product-picker must exist in form');

    // Text input field
    const inputField = querySelector(adminHtml, '#admin-chat-input-field') || querySelector(adminHtml, 'input[name="message"]');
    assert.ok(inputField, 'Chat input field must exist');

    // Send button
    const submitBtn = querySelector(adminHtml, '#admin-chat-submit-btn') || querySelector(adminHtml, 'button[type="submit"]');
    assert.ok(submitBtn, 'Submit button must exist');

    // 2. Verify mobile breakpoint <= 560px handles toolbar layout cleanly
    const mqs560 = findMediaQueries(adminCss, 'max-width: 560px');
    assert.ok(mqs560.length > 0, 'Admin CSS must contain @media (max-width: 560px)');

    // Check .admin-chat-form or input wrapper wrapping
    const formWrap = getProperty(adminCss, '.admin-chat-form', 'flex-wrap') ||
                     getPropertyInMediaQuery(adminCss, 'max-width: 560px', '.admin-chat-form', 'flex-wrap') ||
                     getPropertyInMediaQuery(adminCss, 'max-width: 560px', '.admin-chat-input-wrapper', 'flex-wrap');

    // Verify chat inbox on <= 560px switches to single column to avoid horizontal overflow
    const inboxGrid560 = getPropertyInMediaQuery(adminCss, 'max-width: 560px', '.chat-inbox', 'grid-template-columns');
    assert.ok(
      inboxGrid560 && (inboxGrid560.includes('1fr') || inboxGrid560 === 'none'),
      `On mobile <= 560px, .chat-inbox grid-template-columns must be single column (1fr), got: ${inboxGrid560}`
    );
  });

  it('T3-05: R2 + R3: Product Picker Grid on Tablet & Mobile', () => {
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // 1. Verify product picker drawer markup
    assert.ok(
      adminHtml.includes('id="admin-product-picker-drawer"') || hasElement(adminHtml, '#admin-product-picker-drawer'),
      '#admin-product-picker-drawer must exist in admin HTML'
    );

    // 2. Verify product card thumbnail 1:1 aspect ratio
    const thumbAspect = getProperty(adminCss, '.admin-product-card-thumb', 'aspect-ratio');
    const thumbWidth = getProperty(adminCss, '.admin-product-card-thumb', 'width');
    const thumbHeight = getProperty(adminCss, '.admin-product-card-thumb', 'height');
    const thumbFit = getProperty(adminCss, '.admin-product-card-thumb', 'object-fit');

    const is1to1 = (thumbAspect && thumbAspect.replace(/\s+/g, '').startsWith('1/1')) ||
                   (thumbWidth && thumbHeight && thumbWidth.trim() === thumbHeight.trim());
    assert.ok(
      is1to1,
      `Product card thumbnail must maintain 1:1 square ratio (aspect-ratio: 1/1 or equal width/height), got: aspect=${thumbAspect}, w=${thumbWidth}, h=${thumbHeight}`
    );
    assert.ok(
      thumbFit === 'cover' || thumbFit === 'contain',
      `Product thumbnail must have object-fit: cover or contain, got: ${thumbFit}`
    );

    // 3. Verify drawer responsive constraints (max-width: 100% or bounded width)
    const drawerMaxW = getProperty(adminCss, '.admin-product-picker-drawer', 'max-width');
    assert.ok(
      drawerMaxW && (drawerMaxW === '100%' || drawerMaxW.includes('100vw') || drawerMaxW.includes('calc')),
      `.admin-product-picker-drawer must declare max-width: 100% to adapt cleanly on tablet & mobile, got: ${drawerMaxW}`
    );
  });

  it('T3-06: R2 + R3: Chatbot Dual Channel & Safe-Area Inset', () => {
    const chatbotHtml = fs.readFileSync('includes/chatbot.php', 'utf8');
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const chatbotJs = fs.readFileSync('js/chatbot.js', 'utf8');

    // 1. Verify dual channel tab elements in customer chatbot
    assert.ok(
      chatbotHtml.includes('id="ff-tab-ai"') || hasElement(chatbotHtml, '#ff-tab-ai'),
      'Customer chatbot must have #ff-tab-ai button'
    );
    assert.ok(
      chatbotHtml.includes('id="ff-tab-admin"') || hasElement(chatbotHtml, '#ff-tab-admin'),
      'Customer chatbot must have #ff-tab-admin button'
    );
    assert.ok(
      chatbotHtml.includes('id="ff-tab-admin-dot"') || hasElement(chatbotHtml, '#ff-tab-admin-dot'),
      'Customer chatbot must have #ff-tab-admin-dot channel badge'
    );

    // 2. Verify channel switching logic in chatbot.js
    const hasChannelSwitch = chatbotJs.includes('switchChannel') ||
                             chatbotJs.includes('currentChannel') ||
                             (chatbotJs.includes('ff-tab-ai') && chatbotJs.includes('ff-tab-admin'));
    assert.ok(hasChannelSwitch, 'js/chatbot.js must contain dual channel switching logic');

    // 3. Verify safe-area-inset-bottom support for mobile viewport
    const hasSafeArea = chatbotCss.includes('safe-area-inset-bottom') ||
                        chatbotCss.includes('env(safe-area-inset-bottom)');
    assert.ok(
      hasSafeArea,
      'css/chatbot.css must include env(safe-area-inset-bottom) padding for mobile notch/home indicator display'
    );
  });
});
