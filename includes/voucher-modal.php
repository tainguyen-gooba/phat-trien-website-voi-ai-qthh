<?php
/**
 * FASTFOOD - Reusable Voucher Picker Modal Component
 * Sử dụng tại cart.php và checkout.php
 */
?>
<!-- Voucher Picker Modal -->
<div id="voucher-picker-modal" class="voucher-modal-overlay" style="display:none;" onclick="VoucherModal.handleBackdrop(event)">
  <div class="voucher-modal-card" role="dialog" aria-modal="true" aria-labelledby="voucher-modal-title">
    <div class="voucher-modal-header">
      <h3 id="voucher-modal-title" class="voucher-modal-title">
        <span>🎟️</span> Chọn Voucher Giảm Giá
      </h3>
      <button type="button" class="voucher-modal-close" onclick="VoucherModal.close()" aria-label="Đóng popup">✕</button>
    </div>

    <div class="voucher-modal-body">
      <!-- Nhập mã giảm giá thủ công -->
      <div class="voucher-modal-manual-input">
        <input type="text" id="voucher-manual-code-input" placeholder="Nhập mã ưu đãi hoặc mã riêng..." maxlength="30" onkeydown="if(event.key==='Enter'){event.preventDefault();VoucherModal.applyManual();}">
        <button type="button" id="btn-apply-manual-code" onclick="VoucherModal.applyManual()">Áp Dụng</button>
      </div>

      <!-- Danh sách voucher khả dụng -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.25rem;">
        <span style="font-size:0.85rem; font-weight:800; color:#475569;">Voucher Khả Dụng Trong Ví & Sàn</span>
        <a href="<?= getUrl('vouchers.php') ?>" target="_blank" style="font-size:0.75rem; color:#ea580c; font-weight:750; text-decoration:none;">
          Săn thêm voucher ↗
        </a>
      </div>

      <div id="voucher-picker-list-container" class="voucher-modal-list">
        <div style="text-align:center; padding:2rem 1rem; color:#94a3b8;">
          <div style="font-size:1.5rem; margin-bottom:0.5rem;">⏳</div>
          Đang kiểm tra các voucher hợp lệ cho đơn hàng...
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.VoucherModal = (function() {
  let activeOptions = {
    subtotal: 0,
    currentCode: '',
    onApplied: null,
    onRemoved: null
  };

  function open(opts) {
    activeOptions = Object.assign({
      subtotal: 0,
      currentCode: '',
      onApplied: null,
      onRemoved: null
    }, opts || {});

    const modal = document.getElementById('voucher-picker-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const input = document.getElementById('voucher-manual-code-input');
    if (input) {
      input.value = '';
      setTimeout(() => input.focus(), 100);
    }

    loadEligibleVouchers();
  }

  function close() {
    const modal = document.getElementById('voucher-picker-modal');
    if (!modal) return;
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  function handleBackdrop(e) {
    if (e.target && e.target.id === 'voucher-picker-modal') {
      close();
    }
  }

  async function loadEligibleVouchers() {
    const container = document.getElementById('voucher-picker-list-container');
    if (!container) return;
    container.innerHTML = `
      <div style="text-align:center; padding:2rem 1rem; color:#94a3b8;">
        <div style="font-size:1.5rem; margin-bottom:0.5rem;">⏳</div>
        Đang tải danh sách voucher phù hợp...
      </div>
    `;

    try {
      const url = window.getApiUrl ? window.getApiUrl(`api/vouchers.php?action=get_eligible&subtotal=${encodeURIComponent(activeOptions.subtotal)}`) : `api/vouchers.php?action=get_eligible&subtotal=${encodeURIComponent(activeOptions.subtotal)}`;
      const res = await fetch(url);
      const data = await res.json();

      if (!data.success || !data.data || !Array.isArray(data.data.vouchers) || data.data.vouchers.length === 0) {
        container.innerHTML = `
          <div style="text-align:center; padding:2.5rem 1rem; color:#64748b; background:#f8fafc; border-radius:12px;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">🎟️</div>
            <strong style="display:block; margin-bottom:0.25rem;">Chưa có voucher phù hợp với giỏ hàng</strong>
            <p style="font-size:0.8rem; margin:0 0 1rem 0; color:#94a3b8;">Bạn có thể ghé sàn săn voucher để lưu thêm mã hoặc nhập mã giảm giá trực tiếp ở trên.</p>
            <a href="${window.BASE_URL || ''}/vouchers.php" class="btn btn-sm btn-primary" style="display:inline-block; padding:0.4rem 0.9rem; font-size:0.8rem;">
              🎁 Đi Săn Voucher Ngay
            </a>
          </div>
        `;
        return;
      }

      renderList(data.data.vouchers, container);
    } catch (err) {
      console.error('Lỗi tải danh sách voucher:', err);
      container.innerHTML = `
        <div style="text-align:center; padding:2rem 1rem; color:#dc2626;">
          ⚠️ Không thể kết nối để lấy danh sách voucher. Vui lòng thử lại!
        </div>
      `;
    }
  }

  function renderList(vouchers, container) {
    let html = '';
    vouchers.forEach(v => {
      const isEligible = !!v.is_eligible;
      const isApplied  = !!v.is_applied;
      const cardClass = 'voucher-picker-item' + (isApplied ? ' is-selected' : (!isEligible ? ' is-disabled' : ''));

      let actionBtn = '';
      if (isApplied) {
        actionBtn = `
          <button type="button" class="btn-picker-applied" onclick="VoucherModal.removeVoucher()" title="Đang áp dụng. Bấm để gỡ">
            ✓ Đang dùng
          </button>
        `;
      } else if (isEligible) {
        actionBtn = `
          <button type="button" class="btn-picker-apply" onclick="VoucherModal.applyVoucher('${escapeHtml(v.code)}')">
            Áp Dụng
          </button>
        `;
      } else {
        actionBtn = `
          <button type="button" disabled style="padding:0.45rem 0.85rem; border-radius:8px; font-size:0.75rem; font-weight:750; background:#e2e8f0; color:#94a3b8; border:none; cursor:not-allowed;">
            Chưa đủ điều kiện
          </button>
        `;
      }

      let subNote = '';
      if (!isEligible && v.ineligibility_reason) {
        subNote = `<div style="font-size:0.75rem; color:#dc2626; font-weight:600; margin-top:0.25rem;">⚠️ ${escapeHtml(v.ineligibility_reason)}</div>`;
      } else if (v.min_order > 0) {
        subNote = `<div class="voucher-picker-sub">Đơn tối thiểu ${Number(v.min_order).toLocaleString('vi-VN')}đ • HSD: ${escapeHtml(v.end_date)}</div>`;
      } else {
        subNote = `<div class="voucher-picker-sub">Áp dụng cho mọi đơn hàng • HSD: ${escapeHtml(v.end_date)}</div>`;
      }

      html += `
        <div class="${cardClass}">
          <div class="voucher-picker-tag">
            ${escapeHtml(v.discount_badge)}
          </div>
          <div class="voucher-picker-info">
            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
              <span style="font-size:0.78rem; font-weight:800; background:#fff7ed; color:#ea580c; padding:0.1rem 0.4rem; border-radius:4px; text-transform:uppercase;">
                ${escapeHtml(v.code)}
              </span>
              ${v.min_member_tier && v.min_member_tier !== 'ALL' ? `<span style="font-size:0.7rem; font-weight:800; background:#fef3c7; color:#b45309; padding:0.1rem 0.4rem; border-radius:4px;">👑 Hạng ${escapeHtml(v.min_member_tier)}+</span>` : ''}
              <strong class="voucher-picker-title">${escapeHtml(v.title)}</strong>
            </div>
            ${subNote}
          </div>
          <div class="voucher-picker-action">
            ${actionBtn}
          </div>
        </div>
      `;
    });

    container.innerHTML = html;
  }

  async function applyVoucher(code) {
    if (!code) return;
    try {
      const applyBtn = event && event.target ? event.target : null;
      if (applyBtn && applyBtn.tagName === 'BUTTON') {
        applyBtn.disabled = true;
        applyBtn.textContent = 'Đang áp...';
      }

      const url = window.getApiUrl ? window.getApiUrl('api/vouchers.php') : 'api/vouchers.php';
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'apply',
          code: code,
          subtotal: activeOptions.subtotal
        })
      });
      const data = await res.json();

      if (data.success) {
        close();
        if (typeof activeOptions.onApplied === 'function') {
          activeOptions.onApplied(data.data, data.message);
        } else {
          window.location.reload();
        }
      } else {
        alert(data.message || 'Không thể áp dụng voucher này.');
        if (applyBtn && applyBtn.tagName === 'BUTTON') {
          applyBtn.disabled = false;
          applyBtn.textContent = 'Áp Dụng';
        }
      }
    } catch (err) {
      console.error(err);
      alert('Lỗi kết nối khi áp dụng voucher.');
    }
  }

  async function applyManual() {
    const input = document.getElementById('voucher-manual-code-input');
    if (!input) return;
    const code = input.value.trim();
    if (!code) {
      alert('Vui lòng nhập mã giảm giá.');
      input.focus();
      return;
    }
    await applyVoucher(code);
  }

  async function removeVoucher() {
    try {
      const url = window.getApiUrl ? window.getApiUrl('api/vouchers.php') : 'api/vouchers.php';
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove' })
      });
      const data = await res.json();

      if (data.success) {
        close();
        if (typeof activeOptions.onRemoved === 'function') {
          activeOptions.onRemoved(data.data, data.message);
        } else {
          window.location.reload();
        }
      }
    } catch (err) {
      console.error(err);
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

  // Phím Escape để đóng modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('voucher-picker-modal');
      if (modal && modal.style.display !== 'none') {
        close();
      }
    }
  });

  return {
    open,
    close,
    handleBackdrop,
    applyVoucher,
    applyManual,
    removeVoucher
  };
})();
</script>
