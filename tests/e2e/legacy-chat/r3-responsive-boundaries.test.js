/**
 * r3-responsive-boundaries.test.js - Tier 2: Boundary Value Analysis & Edge Cases for Requirement R3
 * Tests: R3-T2-11 through R3-T2-14
 *
 * Verifies extreme tall 9:16 portrait containment, extreme wide 21:9 panorama overflow defense,
 * sidebar 224px badge truncation guard, and mobile lightbox fixed close button positioning.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const { querySelector, hasElement } = require('../harness/dom-parser');
const { getProperty, getPropertyInMediaQuery, extractStylesFromHtml, findMediaQueries } = require('../harness/css-inspector');

describe('Tier 2: R3 Responsive Boundary Value & Edge Cases', () => {

  it('R3-T2-11: Extreme Tall Aspect Ratio (9:16 Portrait Receipt)', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // Verify .ff-msg-image enforces object-fit: contain AND max-height
    const chatbotFit = getProperty(chatbotCss, '.ff-msg-image', 'object-fit');
    const chatbotMaxH = getProperty(chatbotCss, '.ff-msg-image', 'max-height');

    assert.equal(chatbotFit, 'contain', 'Extreme tall 9:16 images must use object-fit: contain to avoid vertical text clipping');
    assert.ok(chatbotMaxH, '.ff-msg-image must specify max-height boundary for tall image defense');

    // Verify admin chat image thumbnail
    const adminFit = getProperty(adminCss, '.admin-chat-image-thumb', 'object-fit') ||
                     getProperty(adminCss, '.admin-chat-image-wrap img', 'object-fit');
    assert.equal(adminFit, 'contain', 'Admin .admin-chat-image-thumb must use object-fit: contain');
  });

  it('R3-T2-12: Extreme Wide Aspect Ratio (21:9 Panorama)', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');

    // Image bubble container should have max-width: 100% and overflow defense
    const imgMaxW = getProperty(chatbotCss, '.ff-msg-image', 'max-width') ||
                    getProperty(chatbotCss, '.ff-msg-image', 'width');
    assert.ok(
      imgMaxW && (imgMaxW.includes('100%') || imgMaxW.includes('auto')),
      `21:9 wide image must constrain max-width or width to container, got: ${imgMaxW}`
    );

    // Bubble container defense
    const bubbleMaxW = getProperty(chatbotCss, '.ff-msg-bubble', 'max-width') ||
                       getProperty(chatbotCss, '.ff-chatbot-messages', 'overflow-x') ||
                       getProperty(chatbotCss, '.ff-chatbot-body', 'overflow-x');
    assert.ok(bubbleMaxW, 'Chat container must provide width/overflow bounds against ultra-wide elements');
  });

  it('R3-T2-13: Sidebar Nav Truncation Guard', () => {
    const adminHtml = fs.readFileSync('admin/index.php', 'utf8');
    const adminCss = extractStylesFromHtml(adminHtml);

    // Admin sidebar width is bounded (224px - 250px).
    // The nav-item or badge must enforce truncation guard or flex-shrink: 0 on badge
    // so that "Tin nhắn khách hàng" + "99+" badge never wraps onto 2 lines or overflows sidebar.
    const badgeShrink = getProperty(adminCss, '#messages-nav-badge', 'flex-shrink') ||
                        getProperty(adminCss, '.nav-badge', 'flex-shrink') ||
                        getProperty(adminCss, '.nav-badge-messages', 'flex-shrink');

    const navItemOverflow = getProperty(adminCss, '.nav-item', 'overflow') ||
                            getProperty(adminCss, '.sidebar-nav', 'overflow') ||
                            getProperty(adminCss, '.nav-item span', 'overflow');

    const hasTruncationGuard = (badgeShrink === '0') ||
                               (navItemOverflow && (navItemOverflow.includes('hidden') || navItemOverflow.includes('ellipsis'))) ||
                               adminCss.includes('text-overflow: ellipsis');

    assert.ok(
      hasTruncationGuard,
      'Admin sidebar nav item or badge must define truncation defense (flex-shrink: 0, text-overflow: ellipsis, or overflow: hidden)'
    );
  });

  it('R3-T2-14: Mobile Lightbox Close Button Position', () => {
    const chatbotCss = fs.readFileSync('css/chatbot.css', 'utf8');

    // On mobile devices (<= 640px or <= 480px), close button must be fixed inside viewport bounds
    // to prevent being positioned off-screen (top: -16px; right: -16px;)
    const mobileClosePos = getPropertyInMediaQuery(chatbotCss, 'max-width', '.ff-lightbox-close', 'position');
    const rootClosePos = getProperty(chatbotCss, '.ff-lightbox-close', 'position');

    const isFixedOnMobile = mobileClosePos === 'fixed' || rootClosePos === 'fixed';
    assert.ok(
      isFixedOnMobile,
      `Mobile lightbox close button .ff-lightbox-close must use position: fixed (found mobile: "${mobileClosePos}", root: "${rootClosePos}")`
    );
  });
});
