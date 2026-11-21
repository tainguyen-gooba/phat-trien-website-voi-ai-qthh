/**
 * r2-chat-ui-features.test.js - Tier 1: Category-Partition Feature Coverage for Requirement R2
 * Tests: R2-T1-12 through R2-T1-19
 *
 * Verifies admin input image attachment button, file input accept types, upload preview strip,
 * product catalog picker trigger, image upload multipart endpoint, message bubble differentiation,
 * customer chatbot header & tabs, and customer chatbot attachment.
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const { querySelector, hasElement, getElementById, getTextContent } = require('../harness/dom-parser');
const { getValidJpgBuffer } = require('../harness/fixtures');

describe('Tier 1: R2 Chat UI/UX Redesign Feature Coverage', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('R2-T1-12: Admin Input Image Attachment Trigger', async () => {
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200);

    const html = res.text;
    const form = querySelector(html, '#admin-chat-form');
    assert.ok(form, '#admin-chat-form must exist in admin chat interface');

    const attachBtn = querySelector(html, '#admin-chat-attach-btn');
    assert.ok(attachBtn, '#admin-chat-attach-btn must exist to trigger image attachment');
  });

  it('R2-T1-13: Admin Input File Input Specs', async () => {
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200);

    const html = res.text;
    const fileInput = querySelector(html, '#admin-chat-file-input');
    assert.ok(fileInput, '#admin-chat-file-input must exist for selecting image files');

    assert.equal(fileInput.getAttribute('type'), 'file', 'File input type must be "file"');

    const accept = fileInput.getAttribute('accept');
    assert.ok(accept, 'File input must declare accept attribute');
    assert.ok(
      accept.includes('image/jpeg') || accept.includes('.jpg'),
      'File input accept attribute must include JPEG'
    );
    assert.ok(
      accept.includes('image/png') || accept.includes('.png'),
      'File input accept attribute must include PNG'
    );
  });

  it('R2-T1-14: Admin Upload Preview Strip Markup', async () => {
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200);

    const html = res.text;
    const previewStrip = querySelector(html, '#admin-chat-upload-preview');
    assert.ok(previewStrip, '#admin-chat-upload-preview strip must exist for staging image uploads');

    // Check thumbnail preview element and cancel/remove button
    const hasThumb = hasElement(html, '#admin-preview-thumb') || hasElement(html, '.admin-preview-thumb');
    const hasRemoveBtn = hasElement(html, '#admin-btn-remove-preview') || hasElement(html, '.admin-btn-remove-preview');

    assert.ok(hasThumb, 'Preview strip must contain thumbnail preview element');
    assert.ok(hasRemoveBtn, 'Preview strip must contain cancel/remove attachment button');
  });

  it('R2-T1-15: Product Catalog Picker Trigger', async () => {
    const res = await client.get('/admin/index.php');
    assert.equal(res.status, 200);

    const html = res.text;
    const pickerBtn = querySelector(html, '#btn-open-product-picker');
    assert.ok(pickerBtn, '#btn-open-product-picker button must exist in #admin-chat-form');

    const title = pickerBtn.getAttribute('title') || '';
    const text = pickerBtn.textContent || '';
    assert.ok(
      title.toLowerCase().includes('món') || text.toLowerCase().includes('món') || title.toLowerCase().includes('kho') || text.toLowerCase().includes('kho'),
      'Product picker button should indicate food catalog / Kho Món Ăn functionality'
    );
  });

  it('R2-T1-16: Image Upload Multipart API', async () => {
    const validJpg = getValidJpgBuffer();
    const res = await client.uploadChatImage(validJpg, 'e2e_test_valid.jpg', 'image/jpeg');

    assert.equal(res.status, 200, 'Image upload endpoint should return HTTP 200');
    assert.equal(res.body.success, true, 'Image upload should indicate success');
    assert.ok(res.body.data, 'Upload response must include data payload');

    const { url, width, height } = res.body.data;
    assert.ok(url && url.startsWith('/uploads/chat/'), `Returned URL must point to /uploads/chat/, got: ${url}`);
    assert.ok(typeof width === 'number' && width > 0, `Returned image width must be > 0, got: ${width}`);
    assert.ok(typeof height === 'number' && height > 0, `Returned image height must be > 0, got: ${height}`);
  });

  it('R2-T1-17: Bubble Differentiation (Customer/Admin/AI)', () => {
    // Check chatbot.css or admin styles for distinct bubble classes
    const cssPath = path.resolve('css/chatbot.css');
    assert.ok(fs.existsSync(cssPath), 'css/chatbot.css must exist');
    const css = fs.readFileSync(cssPath, 'utf8');

    // Customer / User bubble styling
    const hasUserMsg = css.includes('.ff-msg-user') || css.includes('.chat-msg-user') || css.includes('.message-user');
    // Admin bubble styling
    const hasAdminMsg = css.includes('.ff-msg-admin') || css.includes('.chat-msg-admin') || css.includes('.message-admin');
    // Assistant / Bot bubble styling
    const hasAiMsg = css.includes('.ff-msg-ai') || css.includes('.ff-msg-assistant') || css.includes('.chat-msg-ai');

    assert.ok(hasUserMsg, 'CSS must define styling for customer messages');
    assert.ok(hasAdminMsg, 'CSS must define distinct styling for admin messages');
    assert.ok(hasAiMsg, 'CSS must define distinct styling for AI assistant messages');
  });

  it('R2-T1-18: Customer Chatbot Widget Header & Tabs', async () => {
    // Verify customer chatbot widget markup
    const chatbotPhpPath = path.resolve('includes/chatbot.php');
    assert.ok(fs.existsSync(chatbotPhpPath), 'includes/chatbot.php must exist');

    const html = fs.readFileSync(chatbotPhpPath, 'utf8');

    assert.ok(hasElement(html, '#ff-chatbot-wrapper') || html.includes('ff-chatbot-wrapper'), 'Chatbot wrapper must exist');
    assert.ok(hasElement(html, '#ff-tab-ai') || html.includes('id="ff-tab-ai"'), 'Tab AI button must exist');
    assert.ok(hasElement(html, '#ff-tab-admin') || html.includes('id="ff-tab-admin"'), 'Tab Admin button must exist');
    assert.ok(hasElement(html, '#ff-tab-admin-dot') || html.includes('ff-tab-admin-dot'), 'Admin tab notification dot must exist');
  });

  it('R2-T1-19: Customer Chatbot Input Bar & Attachment', () => {
    const chatbotPhpPath = path.resolve('includes/chatbot.php');
    assert.ok(fs.existsSync(chatbotPhpPath), 'includes/chatbot.php must exist');

    const html = fs.readFileSync(chatbotPhpPath, 'utf8');

    assert.ok(html.includes('id="ff-chat-attach-btn"') || hasElement(html, '#ff-chat-attach-btn'), 'Customer chatbot must contain #ff-chat-attach-btn');
    assert.ok(html.includes('id="ff-chat-upload-preview"') || hasElement(html, '#ff-chat-upload-preview'), 'Customer chatbot must contain #ff-chat-upload-preview');
    assert.ok(html.includes('id="ff-chat-file-input"') || hasElement(html, '#ff-chat-file-input'), 'Customer chatbot must contain #ff-chat-file-input');
  });
});
