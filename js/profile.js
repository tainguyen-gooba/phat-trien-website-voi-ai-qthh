/**
 * FASTFOOD - VIP Profile & Loyalty Hub Interactive Controller
 * File: js/profile.js
 */
(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initClipboardCopy();
    initVoucherExchange();
    initVipCardTilt();
  });

  // =========================================================================
  // 0. Subtle 3D Metallic Tilt & Specular Spotlight for VIP Card
  // =========================================================================
  function initVipCardTilt() {
    const card = document.getElementById('vipCard');
    if (!card || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
      return;
    }

    card.addEventListener('mousemove', function(e) {
      const rect = card.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const px = (x / rect.width) * 100;
      const py = (y / rect.height) * 100;
      const rotateY = ((x / rect.width) - 0.5) * 8;
      const rotateX = (0.5 - (y / rect.height)) * 8;

      card.style.setProperty('--glare-x', px.toFixed(1) + '%');
      card.style.setProperty('--glare-y', py.toFixed(1) + '%');
      card.style.transform = 'perspective(1000px) rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) translateY(-4px)';
    });

    card.addEventListener('mouseleave', function() {
      card.style.removeProperty('transform');
      card.style.setProperty('--glare-x', '78%');
      card.style.setProperty('--glare-y', '20%');
    });
  }

  // =========================================================================
  // 1. Tab Switching Module
  // =========================================================================
  function initTabs() {
    const tabs = document.querySelectorAll('.js-profile-tab');
    const panels = document.querySelectorAll('.js-profile-panel');

    function activateTab(targetId) {
      if (!targetId) return;

      tabs.forEach(function(t) {
        const isMatch = t.getAttribute('data-target') === targetId;
        t.classList.toggle('active', isMatch);
        t.setAttribute('aria-selected', isMatch ? 'true' : 'false');
      });

      panels.forEach(function(p) {
        const isMatch = p.id === targetId;
        p.classList.toggle('active', isMatch);
      });
    }

    tabs.forEach(function(tab) {
      tab.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = tab.getAttribute('data-target');
        activateTab(targetId);
        try {
          history.replaceState(null, '', '#' + targetId);
        } catch (e) {}
      });
    });

    // Check hash on page load
    const hash = window.location.hash.replace('#', '');
    if (hash) {
      const targetId = hash.startsWith('tab-') ? hash : 'tab-' + hash;
      if (document.getElementById(targetId)) {
        activateTab(targetId);
      }
    }
  }

  // =========================================================================
  // 2. 1-Click Clipboard Copy Module
  // =========================================================================
  function attachCopyHandler(button) {
    if (button._hasCopyHandler) return;
    button._hasCopyHandler = true;

    button.addEventListener('click', async function(e) {
      e.preventDefault();
      const code = button.getAttribute('data-code');
      if (!code) return;

      let success = false;
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(code);
          success = true;
        } else {
          const textarea = document.createElement('textarea');
          textarea.value = code;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.focus();
          textarea.select();
          success = document.execCommand('copy');
          document.body.removeChild(textarea);
        }
      } catch (err) {
        console.warn('Clipboard copy error, fallback:', err);
      }

      const textEl = button.querySelector('.copy-btn-text') || button;
      const originalText = textEl.textContent;

      if (success) {
        textEl.textContent = '✓ Đã chép!';
        button.classList.add('copied');

        if (window.UIComponents && typeof window.UIComponents.showToast === 'function') {
          window.UIComponents.showToast('Đã sao chép mã ưu đãi: ' + code, 'success', 2500);
        }

        setTimeout(function() {
          textEl.textContent = originalText;
          button.classList.remove('copied');
        }, 2000);
      } else {
        prompt('Mã voucher của bạn: (Nhấn Ctrl+C để sao chép)', code);
      }
    });
  }

  function initClipboardCopy() {
    document.querySelectorAll('.js-copy-code').forEach(attachCopyHandler);
  }

  // =========================================================================
  // 3. Interactive Voucher Exchange via AJAX
  // =========================================================================
  function initVoucherExchange() {
    const exchangeButtons = document.querySelectorAll('.js-exchange-voucher-btn');

    exchangeButtons.forEach(function(btn) {
      btn.addEventListener('click', async function(e) {
        e.preventDefault();

        const packageId = parseInt(btn.getAttribute('data-package-id'), 10);
        const pointsCost = parseInt(btn.getAttribute('data-points-cost'), 10);
        const packageName = btn.getAttribute('data-package-name') || 'Voucher';

        // Read current points
        const firstBalanceEl = document.querySelector('.js-user-points-balance');
        const currentBalance = firstBalanceEl ? parseInt(firstBalanceEl.getAttribute('data-raw-points') || '0', 10) : 0;

        if (currentBalance < pointsCost) {
          const needed = pointsCost - currentBalance;
          showNotification('Bạn cần tích lũy thêm ' + needed.toLocaleString('vi-VN') + ' điểm để đổi gói quà này!', 'warning');
          return;
        }

        const confirmed = confirm('Bạn có chắc chắn muốn dùng ' + pointsCost.toLocaleString('vi-VN') + ' điểm để đổi "' + packageName + '" không?');
        if (!confirmed) return;

        // Button loading state
        const btnTextEl = btn.querySelector('.btn-text') || btn;
        const originalText = btnTextEl.innerHTML;
        btn.disabled = true;
        btnTextEl.innerHTML = '<span class="profile-spinner"></span> Đang xử lý...';

        try {
          const response = await fetch('api/loyalty.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              action: 'exchange_voucher',
              package_id: packageId
            })
          });

          const data = await response.json();

          if (!response.ok || !data.success) {
            const errorMsg = data.error || data.message || 'Không thể đổi voucher. Vui lòng thử lại sau.';
            showNotification(errorMsg, 'danger');
            btn.disabled = false;
            btnTextEl.innerHTML = originalText;
            return;
          }

          // Success notification
          const successMsg = data.message || 'Đổi voucher thành công! Mã ưu đãi: ' + data.coupon_code;
          showNotification(successMsg, 'success', 5000);

          // Update Points Balance across all page elements
          updateLivePointsBalance(data.new_balance, data.new_balance_vnd);

          // Insert new voucher ticket into "Kho Voucher"
          insertVoucherTicket({
            code: data.coupon_code,
            discount: data.discount_amount,
            title: packageName
          });

          // Update button states
          refreshExchangeButtonStates(data.new_balance);

        } catch (err) {
          console.error('Lỗi kết nối khi đổi voucher:', err);
          showNotification('Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại đường truyền.', 'danger');
        } finally {
          btn.disabled = false;
          btnTextEl.innerHTML = originalText;
          // Re-check affordability for this specific button
          const latestBalanceEl = document.querySelector('.js-user-points-balance');
          const latestBalance = latestBalanceEl ? parseInt(latestBalanceEl.getAttribute('data-raw-points') || '0', 10) : 0;
          if (latestBalance < pointsCost) {
            btn.disabled = true;
            btn.classList.add('btn-disabled');
            const needed = pointsCost - latestBalance;
            btnTextEl.textContent = 'Cần thêm ' + needed.toLocaleString('vi-VN') + ' điểm';
          }
        }
      });
    });
  }

  // =========================================================================
  // 4. Helpers: Live Updates & Notifications
  // =========================================================================
  function updateLivePointsBalance(newBalance, newBalanceVnd) {
    const balanceEls = document.querySelectorAll('.js-user-points-balance');
    balanceEls.forEach(function(el) {
      el.setAttribute('data-raw-points', newBalance);
      el.textContent = newBalance.toLocaleString('vi-VN');
    });

    const vndVal = typeof newBalanceVnd === 'number' ? newBalanceVnd : (newBalance * 100);
    const vndEls = document.querySelectorAll('.js-user-points-vnd');
    vndEls.forEach(function(el) {
      el.textContent = '~ ' + vndVal.toLocaleString('vi-VN') + ' đ';
    });
  }

  function insertVoucherTicket(item) {
    const container = document.getElementById('user-vouchers-container');
    if (!container) return;

    // Remove empty state if present
    const emptyState = container.querySelector('.js-no-vouchers');
    if (emptyState) emptyState.remove();

    const discountDisplay = item.discount.toLocaleString('vi-VN') + 'đ';

    const ticket = document.createElement('div');
    ticket.className = 'voucher-ticket new-item-highlight';
    ticket.innerHTML = `
      <div class="voucher-ticket-left">
        <div class="ticket-discount-tag">${discountDisplay}</div>
        <div class="ticket-discount-sub">GIẢM NGAY</div>
      </div>
      <div class="voucher-ticket-right">
        <div>
          <h4 class="ticket-title">${escapeHtml(item.title)}</h4>
          <div class="ticket-condition">Mã ưu đãi đổi từ điểm thưởng (HSD: 30 ngày)</div>
        </div>
        <div>
          <div class="ticket-code-row">
            <span class="ticket-code-badge">${escapeHtml(item.code)}</span>
            <button type="button" class="ticket-copy-btn js-copy-code" data-code="${escapeHtml(item.code)}" title="Sao chép mã vào clipboard">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
              <span class="copy-btn-text">Sao Chép</span>
            </button>
          </div>
          <div class="ticket-expiry-row">
            <span>HSD: 30 ngày kể từ hôm nay</span>
            <a href="foods.php" style="color: var(--ff-primary); font-weight: 700; text-decoration: none;">Dùng ngay →</a>
          </div>
        </div>
      </div>
    `;

    container.prepend(ticket);

    const copyBtn = ticket.querySelector('.js-copy-code');
    if (copyBtn) attachCopyHandler(copyBtn);

    // Increment voucher counter badges
    const counterEls = document.querySelectorAll('.js-voucher-count');
    counterEls.forEach(function(c) {
      const cur = parseInt(c.textContent || '0', 10);
      c.textContent = cur + 1;
    });
  }

  function refreshExchangeButtonStates(newBalance) {
    const exchangeButtons = document.querySelectorAll('.js-exchange-voucher-btn');
    exchangeButtons.forEach(function(b) {
      const cost = parseInt(b.getAttribute('data-points-cost'), 10);
      const cardParent = b.closest('.voucher-pkg-card');
      const textEl = b.querySelector('.btn-text') || b;

      if (newBalance < cost) {
        b.disabled = true;
        b.classList.add('btn-disabled');
        const needed = cost - newBalance;
        textEl.textContent = 'Cần thêm ' + needed.toLocaleString('vi-VN') + ' điểm';
        if (cardParent) cardParent.classList.add('package-locked');
      } else {
        b.disabled = false;
        b.classList.remove('btn-disabled');
        textEl.textContent = 'Đổi Ngay';
        if (cardParent) cardParent.classList.remove('package-locked');
      }
    });
  }

  function showNotification(msg, type, duration) {
    if (window.UIComponents && typeof window.UIComponents.showToast === 'function') {
      window.UIComponents.showToast(msg, type || 'info', duration || 3500);
    } else {
      alert(msg);
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

})();
