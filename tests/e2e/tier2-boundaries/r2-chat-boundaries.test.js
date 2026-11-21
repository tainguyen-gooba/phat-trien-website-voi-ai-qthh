/**
 * r2-chat-boundaries.test.js - Tier 2: Boundary Value Analysis & Edge Cases for Requirement R2
 * Tests: R2-T2-07 through R2-T2-10
 *
 * Verifies oversized image upload (>5MB rejection), disallowed MIME type rejection,
 * maxlength text boundary (1000 chars), and XSS script tag sanitization/escaping in bubbles.
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const { getOversizedBuffer, getDisallowedBuffer } = require('../harness/fixtures');

describe('Tier 2: R2 Chat Boundary Value & Edge Cases', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('R2-T2-07: Oversized Image Upload (> 5MB)', async () => {
    // 6MB buffer exceeds the 5MB upload limit
    const oversizedBuffer = getOversizedBuffer(6 * 1024 * 1024);
    const res = await client.uploadChatImage(oversizedBuffer, 'oversized_6mb.jpg', 'image/jpeg');

    assert.equal(res.status, 400, 'Uploading image > 5MB must be rejected with HTTP 400 Bad Request');
    assert.equal(res.body.success, false, 'Response success must be false');
    assert.ok(
      res.body.message && (res.body.message.includes('5MB') || res.body.message.toLowerCase().includes('dung lượng')),
      `Error message should explain size limit violation, got: "${res.body.message}"`
    );
  });

  it('R2-T2-08: Disallowed MIME Type Upload', async () => {
    const disallowedBuffer = getDisallowedBuffer();

    // 1. Upload PHP script disguised as image or with .php extension
    const res = await client.uploadChatImage(disallowedBuffer, 'exploit.php', 'application/x-php');
    assert.equal(res.status, 400, 'Uploading disallowed PHP file must be rejected with HTTP 400');
    assert.equal(res.body.success, false);

    // 2. Upload plain text file
    const txtRes = await client.uploadChatImage(disallowedBuffer, 'payload.txt', 'text/plain');
    assert.equal(txtRes.status, 400, 'Uploading text/plain file must be rejected with HTTP 400');
    assert.equal(txtRes.body.success, false);
  });

  it('R2-T2-09: Maxlength Text Boundary (1000 Chars)', async () => {
    const sessionId = client.createSession();

    // 1. Boundary: exactly 1000 characters
    const text1000 = 'A'.repeat(1000);
    const res1000 = await client.sendCustomerMessage(sessionId, text1000);
    assert.equal(res1000.status, 200, '1000-character message must be accepted (HTTP 200)');
    assert.equal(res1000.body.success, true);

    // 2. Exceeding boundary: 1500 characters - must not cause 500 error
    const text1500 = 'B'.repeat(1500);
    const res1500 = await client.sendCustomerMessage(sessionId, text1500);
    assert.ok(
      res1500.status === 200 || res1500.status === 400,
      `Exceeding text length should be gracefully handled with HTTP 200 or 400, got: ${res1500.status}`
    );
  });

  it('R2-T2-10: XSS Script Tag Defense in Bubbles', async () => {
    const sessionId = client.createSession();
    const maliciousPayload = '<script>alert("XSS-ATTACK")</script><img src=x onerror=alert(1)>';

    const sendRes = await client.sendCustomerMessage(sessionId, maliciousPayload);
    assert.equal(sendRes.status, 200);

    // Retrieve thread messages
    const historyRes = await client.get(`/api/v1/chat/admin/sessions/${sessionId}/messages`);
    assert.equal(historyRes.status, 200);

    const messages = Array.isArray(historyRes.body.data) ? historyRes.body.data : (historyRes.body.data?.messages || []);
    const lastMsg = messages[messages.length - 1];
    assert.ok(lastMsg, 'Message should be stored');

    // Verify raw unescaped <script> tag is neutralized in message content or rendered output
    const msgContent = lastMsg.message || '';
    // Either the script tag was stripped, or converted to HTML entities &lt;script&gt;
    const isSafe = !msgContent.includes('<script>') || msgContent.includes('&lt;script&gt;');
    assert.ok(isSafe, `Message content must sanitize or escape <script> tags, got: "${msgContent}"`);
  });
});
