/**
 * product-picker-flow.test.js - Tier 4: Real-World Application Scenario Workflows
 * Test: WF-T4-03
 *
 * Scenario:
 * - WF-T4-03: Admin 1-Click Product Card Recommendation Flow
 *   1. Admin queries products via /api/v1/chat/admin/products?search=...
 *   2. Admin sends product card payload (message_type = 'product_card')
 *   3. Customer chatbot receives product card with 1:1 image, title, price, and status
 *   4. Teardown session
 */

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const TestClient = require('../harness/TestClient');
const { getProperty, extractStylesFromHtml } = require('../harness/css-inspector');

describe('Tier 4: Product Picker Recommendation Workflow', () => {
  let client;

  before(async () => {
    client = new TestClient();
    await client.ensureAdminAuth();
  });

  after(async () => {
    await client.cleanupAllSessions();
  });

  it('WF-T4-03: Admin 1-Click Product Card Recommendation Flow', async () => {
    // -------------------------------------------------------------
    // Step 1: Admin queries products via /api/v1/chat/admin/products
    // -------------------------------------------------------------
    const prodRes = await client.get('/api/v1/chat/admin/products?search=burger&keyword=burger');
    assert.equal(prodRes.status, 200, 'Products catalog query should return HTTP 200');
    assert.equal(prodRes.body.success, true, 'Products query should indicate success');
    assert.ok(prodRes.body.data, 'Response should contain data payload');

    const products = prodRes.body.data.products;
    assert.ok(Array.isArray(products) && products.length > 0, 'Products array must not be empty');

    const targetProduct = products[0];
    assert.ok(targetProduct.id, 'Product must have an id');
    assert.ok(targetProduct.name, 'Product must have a name');
    assert.ok(typeof targetProduct.price === 'number', 'Product must have a numeric price');
    assert.ok(targetProduct.image, 'Product must have an image URL');
    assert.ok(targetProduct.status, 'Product must have a status string');

    // -------------------------------------------------------------
    // Step 2: Admin sends product card payload (message_type = 'product_card')
    // -------------------------------------------------------------
    const sessionId = client.createSession();
    // Customer initiates question
    await client.sendCustomerMessage(sessionId, 'Quán gợi ý cho mình món nào ngon bán chạy nhất đi ạ');

    // Admin sends 1-click product card reply
    const replyRes = await client.post(`/api/v1/chat/admin/sessions/${sessionId}/reply`, {
      messageType: 'product_card',
      productId: targetProduct.id,
      message: `FASTFOOD xin giới thiệu món ngon: ${targetProduct.name}`
    });

    assert.equal(replyRes.status, 200, 'Admin product card reply must succeed with HTTP 200');
    assert.equal(replyRes.body.success, true);
    assert.ok(replyRes.body.data?.message, 'Reply response should include message object');

    const sentMessage = replyRes.body.data.message;
    assert.equal(sentMessage.sender, 'admin', 'Sender must be admin');
    assert.equal(sentMessage.messageType, 'product_card', 'messageType must be product_card');
    assert.ok(sentMessage.metadata?.product, 'Message metadata must contain product details');

    // -------------------------------------------------------------
    // Step 3: Customer chatbot receives product card with 1:1 image, title, price, and status
    // -------------------------------------------------------------
    const customerHistRes = await client.get(`/api/v1/chat/history?sessionId=${sessionId}`);
    assert.equal(customerHistRes.status, 200, 'Customer history retrieval should succeed');
    assert.equal(customerHistRes.body.success, true);

    const histMessages = customerHistRes.body.data?.messages || [];
    assert.ok(histMessages.length >= 2, 'History must contain customer message and admin product card');

    const receivedCard = histMessages.find(m => m.messageType === 'product_card');
    assert.ok(receivedCard, 'Product card message must be present in customer chatbot history');

    const productPayload = receivedCard.metadata?.product;
    assert.ok(productPayload, 'Product payload must exist in received message metadata');
    assert.equal(productPayload.id, targetProduct.id, 'Product ID must match');
    assert.equal(productPayload.name, targetProduct.name, 'Product name must match');
    assert.equal(productPayload.price, targetProduct.price, 'Product price must match');
    assert.ok(productPayload.image, 'Product must have an image');
    assert.ok(productPayload.statusText || productPayload.status, 'Product must have status indication');

    // Verify 1:1 image thumbnail styling in customer chatbot and admin styles
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const singleCardAspect = getProperty(chatbotCss, '.ff-single-card-thumb-wrap', 'aspect-ratio');
    assert.ok(
      singleCardAspect && singleCardAspect.replace(/\s+/g, '').startsWith('1/1'),
      `.ff-single-card-thumb-wrap must define aspect-ratio: 1 / 1, got: ${singleCardAspect}`
    );

    // -------------------------------------------------------------
    // Step 4: Teardown session
    // -------------------------------------------------------------
    const delRes = await client.deleteSession(sessionId);
    assert.equal(delRes.status, 200, 'Session teardown should succeed');
  });
});
