/**
 * r3-responsive-features.test.js - Tier 1: Category-Partition Feature Coverage for Requirement R3
 * Tests: R3-T1-20 through R3-T1-25
 *
 * Verifies chat image contain CSS, food card aspect ratio, single food card thumbnail aspect ratio,
 * lightbox modal markup, lightbox contain & viewport bounds, and asset mirror SHA256 parity.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const { querySelector, hasElement } = require('../harness/dom-parser');
const { getProperty, hasProperty, extractStylesFromHtml } = require('../harness/css-inspector');

function getSha256(filePath) {
  const content = fs.readFileSync(filePath);
  return crypto.createHash('sha256').update(content).digest('hex');
}

describe('Tier 1: R3 Multi-Device Responsive & Image Aspect Ratio Feature Coverage', () => {

  it('R3-T1-20: Chat Image Containment CSS', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // Verify .ff-msg-image in chatbot.css specifies object-fit: contain
    const chatbotImgFit = getProperty(chatbotCss, '.ff-msg-image', 'object-fit');
    // Verify admin chat image thumbnail specifies object-fit: contain
    const adminImgFit = getProperty(adminCss, '.admin-chat-image-thumb', 'object-fit') ||
                        getProperty(adminCss, '.admin-chat-image-wrap img', 'object-fit');

    assert.equal(
      chatbotImgFit,
      'contain',
      `Chatbot image .ff-msg-image must use object-fit: contain to avoid cropping portrait/tall photos, got: ${chatbotImgFit}`
    );

    assert.equal(
      adminImgFit,
      'contain',
      `Admin chat image .admin-chat-image-thumb must use object-fit: contain, got: ${adminImgFit}`
    );
  });

  it('R3-T1-21: Food Card Grid Aspect Ratio', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const aspect = getProperty(chatbotCss, '.ff-card-thumb-wrap', 'aspect-ratio');
    assert.ok(aspect, '.ff-card-thumb-wrap must define aspect-ratio');

    const cleanAspect = aspect.replace(/\s+/g, '');
    assert.ok(
      cleanAspect === '4/3' || cleanAspect === '16/10' || cleanAspect === '4/3!important',
      `Food card grid thumbnail must maintain 4/3 aspect ratio, got: ${aspect}`
    );
  });

  it('R3-T1-22: Single Food Card Thumbnail Aspect Ratio', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    const chatbotSingleAspect = getProperty(chatbotCss, '.ff-single-card-thumb-wrap', 'aspect-ratio');
    const adminSingleAspect = getProperty(adminCss, '.admin-product-card-thumb', 'aspect-ratio') ||
                              getProperty(adminCss, '.admin-card-thumb-wrap', 'aspect-ratio');

    assert.ok(
      chatbotSingleAspect && chatbotSingleAspect.replace(/\s+/g, '').startsWith('1/1'),
      `Single card thumbnail .ff-single-card-thumb-wrap must define aspect-ratio: 1 / 1, got: ${chatbotSingleAspect}`
    );

    assert.ok(
      adminSingleAspect && adminSingleAspect.replace(/\s+/g, '').startsWith('1/1'),
      `Admin food card thumbnail .admin-product-card-thumb must define aspect-ratio: 1 / 1, got: ${adminSingleAspect}`
    );
  });

  it('R3-T1-23: Lightbox Modal Markup Existence', () => {
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const chatbotHtml = fs.readFileSync('includes/chatbot.php', 'utf8');

    const hasAdminLightbox = adminHtml.includes('id="admin-chat-lightbox-modal"') || hasElement(adminHtml, '#admin-chat-lightbox-modal');
    const hasCustomerLightbox = chatbotHtml.includes('id="ff-chat-lightbox"') || hasElement(chatbotHtml, '#ff-chat-lightbox');

    assert.ok(hasAdminLightbox, 'Admin index.php must include #admin-chat-lightbox-modal markup');
    assert.ok(hasCustomerLightbox, 'Customer chatbot.php must include #ff-chat-lightbox markup');
  });

  it('R3-T1-24: Lightbox Contain & Viewport Bounds', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // Verify .ff-lightbox-img has object-fit: contain and viewport max bounds
    const chatbotObjFit = getProperty(chatbotCss, '.ff-lightbox-img', 'object-fit');
    assert.equal(chatbotObjFit, 'contain', 'Customer lightbox image must use object-fit: contain');

    const chatbotMaxW = getProperty(chatbotCss, '.ff-lightbox-img', 'max-width') ||
                        getProperty(chatbotCss, '.ff-lightbox-modal-content', 'max-width');
    const chatbotMaxH = getProperty(chatbotCss, '.ff-lightbox-img', 'max-height') ||
                        getProperty(chatbotCss, '.ff-lightbox-modal-content', 'max-height');

    assert.ok(chatbotMaxW && chatbotMaxW.includes('vw'), `Lightbox max-width should be bounded by viewport width (vw), got: ${chatbotMaxW}`);
    assert.ok(chatbotMaxH && chatbotMaxH.includes('vh'), `Lightbox max-height should be bounded by viewport height (vh), got: ${chatbotMaxH}`);

    // Verify admin lightbox
    const adminObjFit = getProperty(adminCss, '.admin-lightbox-img', 'object-fit');
    assert.equal(adminObjFit, 'contain', 'Admin lightbox image must use object-fit: contain');
  });

  it('R3-T1-25: Asset Mirror Parity', () => {
    const assetPairs = [
      ['css/chatbot.css', 'frontend/css/chatbot.css'],
      ['js/chatbot.js', 'frontend/js/chatbot.js'],
      ['js/admin-chat.js', 'frontend/js/admin-chat.js']
    ];

    for (const [rootAsset, mirrorAsset] of assetPairs) {
      assert.ok(fs.existsSync(rootAsset), `Root asset ${rootAsset} must exist`);
      assert.ok(fs.existsSync(mirrorAsset), `Mirror asset ${mirrorAsset} must exist`);

      const rootHash = getSha256(rootAsset);
      const mirrorHash = getSha256(mirrorAsset);

      assert.equal(
        rootHash,
        mirrorHash,
        `Asset mirror desynchronization detected for ${rootAsset} vs ${mirrorAsset}! Hashes: ${rootHash} != ${mirrorHash}`
      );
    }
  });
});
