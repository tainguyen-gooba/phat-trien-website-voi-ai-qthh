<?php
/**
 * FASTFOOD - Reusable Voucher Picker Modal Component
 * Sử dụng tại cart.php, checkout.php và footer.php
 * Phong cách Modern Orange - White Luxury Ticket Design
 */
?>
<style id="voucher-picker-modal-styles">
/* ==========================================================================
   VOUCHER PICKER MODAL (Modern Orange - White Luxury Ticket UI)
   ========================================================================== */
.voucher-modal-overlay {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.68);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem 1rem;
  z-index: 99999;
  box-sizing: border-box;
  animation: vModalFadeIn 0.24s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes vModalFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.voucher-modal-card {
  background: #ffffff;
  width: 100%;
  max-width: 660px;
  max-height: 90vh;
  border-radius: 24px;
  box-shadow: 0 25px 65px -12px rgba(15, 23, 42, 0.4), 0 0 0 1px rgba(251, 146, 60, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-sizing: border-box;
  animation: vModalZoomIn 0.28s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}

@keyframes vModalZoomIn {
  from { opacity: 0; transform: scale(0.93) translateY(14px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Modal Header */
.voucher-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.6rem;
  background: linear-gradient(135deg, #fff7ed 0%, #ffffff 88%);
  border-bottom: 1.5px solid #fed7aa;
}

.voucher-modal-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 850;
  color: #9a3412;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  letter-spacing: -0.2px;
}

.voucher-modal-title span {
  font-size: 1.45rem;
}

.voucher-modal-subtitle {
  margin: 0.25rem 0 0 0;
  font-size: 0.78rem;
  color: #64748b;
  font-weight: 500;
}

.voucher-modal-close {
  background: #f1f5f9;
  border: none;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  color: #64748b;
  font-size: 1.2rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  padding: 0;
  flex-shrink: 0;
}

.voucher-modal-close:hover {
  background: #fee2e2;
  color: #dc2626;
  transform: rotate(90deg) scale(1.08);
}

/* Modal Body */
.voucher-modal-body {
  padding: 1.35rem 1.6rem 1rem;
  overflow-y: auto;
  flex: 1;
  box-sizing: border-box;
}

.voucher-modal-body::-webkit-scrollbar {
  width: 6px;
}
.voucher-modal-body::-webkit-scrollbar-track {
  background: #f8fafc;
}
.voucher-modal-body::-webkit-scrollbar-thumb {
  background: #fdba74;
  border-radius: 6px;
}
.voucher-modal-body::-webkit-scrollbar-thumb:hover {
  background: #ea580c;
}

/* Manual Input Section */
.voucher-modal-manual-input {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  position: relative;
}

.voucher-modal-input-wrap {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.voucher-modal-input-icon {
  position: absolute;
  left: 1rem;
  font-size: 1.15rem;
  pointer-events: none;
  opacity: 0.9;
}

.voucher-modal-input-wrap input {
  width: 100%;
  padding: 0.82rem 1rem 0.82rem 2.85rem;
  border: 1.5px solid #fed7aa;
  border-radius: 14px;
  font-size: 0.9rem;
  font-weight: 750;
  color: #0f172a;
  background: #fffdfa;
  text-transform: uppercase;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.voucher-modal-input-wrap input::placeholder {
  text-transform: none;
  font-weight: 450;
  color: #94a3b8;
}

.voucher-modal-input-wrap input:focus {
  outline: none;
  border-color: #ea580c;
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(234, 88, 12, 0.14);
}

.voucher-modal-manual-input button {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  color: #ffffff;
  font-weight: 850;
  font-size: 0.9rem;
  padding: 0.82rem 1.5rem;
  border-radius: 14px;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 4px 14px rgba(234, 88, 12, 0.32);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.voucher-modal-manual-input button:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(234, 88, 12, 0.42);
}

.voucher-modal-manual-input button:active {
  transform: translateY(0);
}

/* Subheader Row */
.voucher-modal-subheader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.15rem;
  padding-bottom: 0.5rem;
}

.voucher-modal-subheading {
  font-size: 0.88rem;
  font-weight: 850;
  color: #334155;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.voucher-modal-link-more {
  font-size: 0.8rem;
  color: #ea580c;
  font-weight: 750;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  transition: all 0.2s ease;
}

.voucher-modal-link-more:hover {
  background: #ea580c;
  color: #ffffff;
  border-color: #ea580c;
  box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);
}

/* Voucher Cards List */
.voucher-modal-list {
  margin-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.95rem;
  max-height: 480px;
  overflow-y: auto;
  padding: 4px 6px 1.75rem 2px;
}

.voucher-modal-list::-webkit-scrollbar {
  width: 6px;
}
.voucher-modal-list::-webkit-scrollbar-track {
  background: #f8fafc;
  border-radius: 6px;
}
.voucher-modal-list::-webkit-scrollbar-thumb {
  background: #fdba74;
  border-radius: 6px;
}
.voucher-modal-list::-webkit-scrollbar-thumb:hover {
  background: #ea580c;
}

/* ==========================================================================
   TICKET CARD (Luxury Ticket with Cutout Notches & Clear Hierarchy)
   ========================================================================== */
.voucher-picker-item {
  display: flex;
  align-items: stretch;
  background: #ffffff;
  border: 1.5px solid #fed7aa;
  border-radius: 16px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
  position: relative;
  transition: all 0.22s ease;
  min-height: 98px;
  overflow: hidden;
}

.voucher-picker-item:hover {
  border-color: #fb923c;
  box-shadow: 0 8px 22px rgba(234, 88, 12, 0.12);
  transform: translateY(-1.5px);
}

/* Left side stub (Cuống vé) */
.voucher-picker-tag {
  width: 122px;
  min-width: 122px;
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  border-right: 1.5px dashed #fdba74;
  color: #ea580c;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0.95rem 0.5rem;
  text-align: center;
  box-sizing: border-box;
  position: relative;
  flex-shrink: 0;
}

/* Ticket notch cutouts (Vết khuyết tròn vé xé) */
.voucher-picker-tag::before,
.voucher-picker-tag::after {
  content: '';
  position: absolute;
  right: -8px;
  width: 15px;
  height: 15px;
  background: #ffffff;
  border: 1.5px solid #fed7aa;
  border-radius: 50%;
  z-index: 3;
}
.voucher-picker-tag::before {
  top: -8.5px;
  border-top-color: transparent;
  border-left-color: transparent;
}
.voucher-picker-tag::after {
  bottom: -8.5px;
  border-bottom-color: transparent;
  border-left-color: transparent;
}

.voucher-tag-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  margin-bottom: 0.3rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.voucher-tag-label {
  font-size: 0.68rem;
  font-weight: 850;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  opacity: 0.85;
  margin-bottom: 0.15rem;
  line-height: 1;
}

.voucher-tag-val {
  font-size: 1rem;
  font-weight: 950;
  line-height: 1.15;
  letter-spacing: -0.3px;
  word-break: break-word;
}

/* Voucher Type Color Variants */
.voucher-picker-item.type-percentage .voucher-picker-tag {
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  color: #ea580c;
  border-right-color: #fdba74;
}
.voucher-picker-item.type-fixed .voucher-picker-tag {
  background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%);
  color: #b45309;
  border-right-color: #fde68a;
}
.voucher-picker-item.type-free_shipping .voucher-picker-tag {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  color: #0369a1;
  border-right-color: #bae6fd;
}
.voucher-picker-item.type-bogo .voucher-picker-tag {
  background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
  color: #7e22ce;
  border-right-color: #e9d5ff;
}

/* Center Details (Never clips top or bottom!) */
.voucher-picker-info {
  flex: 1;
  padding: 0.85rem 1.15rem;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  gap: 0.3rem;
  min-width: 0;
  box-sizing: border-box;
}

.voucher-picker-top-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.voucher-picker-code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.76rem;
  font-weight: 850;
  background: #fff7ed;
  color: #ea580c;
  border: 1px solid #fed7aa;
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.voucher-picker-tier {
  font-size: 0.72rem;
  font-weight: 800;
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
}

.voucher-picker-title {
  margin: 0;
  font-size: 0.93rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.35;
}

.voucher-picker-condition {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem;
  line-height: 1.35;
}

.voucher-picker-dot {
  color: #cbd5e1;
  font-weight: bold;
}

.voucher-picker-warning {
  font-size: 0.74rem;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 7px;
  padding: 0.3rem 0.65rem;
  font-weight: 650;
  line-height: 1.35;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-top: 0.15rem;
}

/* Right side action */
.voucher-picker-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0.85rem 1.15rem;
  min-width: 115px;
  box-sizing: border-box;
  flex-shrink: 0;
}

/* Action Buttons */
.btn-picker-apply {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  color: #ffffff;
  border: none;
  border-radius: 20px;
  padding: 0.6rem 1.25rem;
  font-size: 0.84rem;
  font-weight: 850;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.btn-picker-apply:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 18px rgba(234, 88, 12, 0.42);
}

.btn-picker-apply:active {
  transform: translateY(0);
}

.btn-picker-applied {
  background: #dcfce7;
  color: #15803d;
  border: 1.5px solid #86efac;
  border-radius: 20px;
  padding: 0.6rem 1.15rem;
  font-size: 0.82rem;
  font-weight: 850;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
  width: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-picker-applied:hover {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fca5a5;
}

.btn-picker-disabled {
  background: #f8fafc;
  color: #94a3b8;
  border: 1.5px solid #e2e8f0;
  border-radius: 20px;
  font-size: 0.76rem;
  font-weight: 750;
  padding: 0.55rem 0.95rem;
  cursor: not-allowed;
  white-space: nowrap;
  width: 100%;
  text-align: center;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Selected Active State */
.voucher-picker-item.is-selected {
  border: 2px solid #ea580c;
  background: #fffaf5;
  box-shadow: 0 6px 20px rgba(234, 88, 12, 0.16);
}

.voucher-picker-item.is-selected::after {
  content: '✓ ĐANG CHỌN';
  position: absolute;
  top: 0;
  right: 0;
  background: #16a34a;
  color: #ffffff;
  font-size: 0.62rem;
  font-weight: 850;
  padding: 2px 9px;
  border-bottom-left-radius: 8px;
  letter-spacing: 0.5px;
  z-index: 4;
}

/* Ineligible State */
.voucher-picker-item.is-disabled {
  background: #fafafa;
  border-color: #e2e8f0;
  opacity: 0.88;
}

.voucher-picker-item.is-disabled:hover {
  transform: none;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
  border-color: #e2e8f0;
}

.voucher-picker-item.is-disabled .voucher-picker-tag {
  background: #f1f5f9 !important;
  color: #94a3b8 !important;
  border-right-color: #cbd5e1 !important;
}

.voucher-picker-item.is-disabled .voucher-picker-tag::before,
.voucher-picker-item.is-disabled .voucher-picker-tag::after {
  border-color: #e2e8f0 !important;
}

/* Empty State */
.voucher-modal-empty-state {
  text-align: center;
  padding: 3rem 1.5rem;
  color: #64748b;
  background: #fff7ed;
  border: 1.5px dashed #fed7aa;
  border-radius: 18px;
}

.voucher-empty-icon {
  font-size: 2.6rem;
  margin-bottom: 0.6rem;
}

.voucher-empty-title {
  margin: 0 0 0.4rem 0;
  color: #9a3412;
  font-size: 1.05rem;
  font-weight: 850;
}

.voucher-empty-desc {
  font-size: 0.82rem;
  margin: 0 0 1.25rem 0;
  color: #64748b;
  line-height: 1.45;
}

.voucher-empty-btn {
  display: inline-block;
  padding: 0.55rem 1.25rem;
  font-size: 0.85rem;
  border-radius: 12px;
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  color: #fff;
  text-decoration: none;
  font-weight: 850;
  box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
  transition: all 0.2s ease;
}

.voucher-empty-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(234, 88, 12, 0.4);
}

/* Responsive */
@media (max-width: 600px) {
  .voucher-modal-card {
    max-height: 94vh;
    border-radius: 18px;
    margin: 0.5rem;
  }
  .voucher-modal-header {
    padding: 1rem 1.2rem;
  }
  .voucher-modal-body {
    padding: 1rem 1.2rem;
  }
  .voucher-picker-item {
    flex-direction: column;
    min-height: auto;
  }
  .voucher-picker-tag {
    width: 100%;
    min-width: 100%;
    border-right: none;
    border-bottom: 1.5px dashed #fed7aa;
    padding: 0.65rem 1rem;
    flex-direction: row;
    justify-content: flex-start;
    gap: 0.75rem;
  }
  .voucher-picker-tag::before,
  .voucher-picker-tag::after {
    display: none;
  }
  .voucher-tag-icon-wrap {
    margin-bottom: 0;
  }
  .voucher-picker-info {
    padding: 0.85rem 1rem 0.5rem;
  }
  .voucher-picker-action {
    padding: 0.5rem 1rem 0.85rem;
    width: 100%;
    align-items: stretch;
  }
  .voucher-modal-manual-input {
    flex-direction: column;
    gap: 0.5rem;
  }
  .voucher-modal-manual-input button {
    width: 100%;
  }
}
</style>

<!-- Voucher Picker Modal Component -->
<div id="voucher-picker-modal" class="voucher-modal-overlay" style="display:none;" onclick="VoucherModal.handleBackdrop(event)">
  <div class="voucher-modal-card" role="dialog" aria-modal="true" aria-labelledby="voucher-modal-title">
    
    <!-- Modal Header -->
    <div class="voucher-modal-header">
      <div>
        <h3 id="voucher-modal-title" class="voucher-modal-title">
          <span>🎟️</span> Chọn Voucher Giảm Giá
        </h3>
        <p class="voucher-modal-subtitle">Chọn 1 voucher tối ưu hoặc nhập mã ưu đãi của bạn</p>
      </div>
      <button type="button" class="voucher-modal-close" onclick="VoucherModal.close()" aria-label="Đóng popup">✕</button>
    </div>

    <!-- Modal Body -->
    <div class="voucher-modal-body">
      <!-- Nhập mã giảm giá thủ công -->
      <div class="voucher-modal-manual-input">
        <div class="voucher-modal-input-wrap">
          <span class="voucher-modal-input-icon">🎟️</span>
          <input type="text" id="voucher-manual-code-input" placeholder="Nhập mã ưu đãi (VD: FASTFOOD20...)" maxlength="30" onkeydown="if(event.key==='Enter'){event.preventDefault();VoucherModal.applyManual();}">
        </div>
        <button type="button" id="btn-apply-manual-code" onclick="VoucherModal.applyManual()">Áp Dụng</button>
      </div>

      <!-- Tiêu đề danh sách voucher -->
      <div class="voucher-modal-subheader">
        <span class="voucher-modal-subheading">
          <span>✨</span> Voucher Khả Dụng Trong Ví &amp; Sàn
        </span>
        <a href="<?= getUrl('vouchers.php') ?>" class="voucher-modal-link-more" onclick="VoucherModal.close()">
          🎁 Săn thêm voucher →
        </a>
      </div>

      <!-- Container chứa danh sách thẻ voucher -->
      <div id="voucher-picker-list-container" class="voucher-modal-list">
        <div style="text-align:center; padding:2.5rem 1rem; color:#94a3b8;">
          <div style="font-size:1.8rem; margin-bottom:0.5rem;">⏳</div>
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
      <div style="text-align:center; padding:2.5rem 1rem; color:#94a3b8;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;">⏳</div>
        Đang tải danh sách voucher phù hợp...
      </div>
    `;

    try {
      const url = window.getApiUrl ? window.getApiUrl(`api/vouchers.php?action=get_eligible&subtotal=${encodeURIComponent(activeOptions.subtotal)}`) : `api/vouchers.php?action=get_eligible&subtotal=${encodeURIComponent(activeOptions.subtotal)}`;
      const res = await fetch(url);
      const data = await res.json();

      if (!data.success || !data.data || !Array.isArray(data.data.vouchers) || data.data.vouchers.length === 0) {
        container.innerHTML = `
          <div class="voucher-modal-empty-state">
            <div class="voucher-empty-icon">🎟️</div>
            <h4 class="voucher-empty-title">Chưa có voucher phù hợp với giỏ hàng</h4>
            <p class="voucher-empty-desc">Bạn có thể ghé sàn khuyến mãi để thu thập thêm mã hoặc nhập mã ưu đãi thủ công ở trên.</p>
            <a href="<?= getUrl('vouchers.php') ?>" class="voucher-empty-btn" onclick="VoucherModal.close()">
              🎁 Khám Phá Sàn Voucher Ngay
            </a>
          </div>
        `;
        return;
      }

      renderList(data.data.vouchers, container);
    } catch (err) {
      console.error('Lỗi tải danh sách voucher:', err);
      container.innerHTML = `
        <div style="text-align:center; padding:2rem 1rem; color:#dc2626; background:#fef2f2; border:1px solid #fee2e2; border-radius:12px;">
          ⚠️ Không thể kết nối để lấy danh sách voucher. Vui lòng thử lại!
        </div>
      `;
    }
  }

  function formatTag(v) {
    const vType = v.voucher_type || 'percentage';
    let icon = '⚡';
    let label = 'GIẢM';
    let val = escapeHtml(v.discount_badge || '');

    if (vType === 'fixed') {
      icon = '💰';
      label = 'GIẢM';
      val = Number(v.discount_value || 0).toLocaleString('vi-VN') + 'đ';
    } else if (vType === 'percentage') {
      icon = '⚡';
      label = 'GIẢM';
      val = (v.discount_value || 0) + '%';
    } else if (vType === 'free_shipping') {
      icon = '🚚';
      label = 'MIỄN PHÍ';
      val = 'FREESHIP';
    } else if (vType === 'bogo') {
      icon = '🎁';
      label = 'QUÀ TẶNG';
      val = 'MUA 1 TẶNG 1';
    }

    return { icon, label, val };
  }

  function renderList(vouchers, container) {
    let html = '';
    vouchers.forEach(v => {
      const isEligible = !!v.is_eligible;
      const isApplied  = !!v.is_applied;
      const vType      = v.voucher_type || 'percentage';
      const cardClass  = 'voucher-picker-item type-' + vType + (isApplied ? ' is-selected' : (!isEligible ? ' is-disabled' : ''));

      let actionBtn = '';
      if (isApplied) {
        actionBtn = `
          <button type="button" class="btn-picker-applied" onclick="VoucherModal.removeVoucher()" title="Đang áp dụng voucher này. Bấm để hủy dùng">
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
          <button type="button" class="btn-picker-disabled" disabled title="${escapeHtml(v.ineligibility_reason || 'Chưa đủ điều kiện')}">
            Chưa đạt
          </button>
        `;
      }

      let condText = '';
      if (v.min_order > 0) {
        condText = `Đơn tối thiểu ${Number(v.min_order).toLocaleString('vi-VN')}đ`;
      } else {
        condText = 'Áp dụng mọi đơn hàng';
      }

      let warningHtml = '';
      if (!isEligible && v.ineligibility_reason) {
        warningHtml = `<div class="voucher-picker-warning">⚠️ ${escapeHtml(v.ineligibility_reason)}</div>`;
      }

      const tagInfo = formatTag(v);

      html += `
        <div class="${cardClass}">
          <!-- Left side ticket stub -->
          <div class="voucher-picker-tag">
            <div class="voucher-tag-icon-wrap">${tagInfo.icon}</div>
            <div class="voucher-tag-label">${tagInfo.label}</div>
            <div class="voucher-tag-val">${tagInfo.val}</div>
          </div>

          <!-- Middle details -->
          <div class="voucher-picker-info">
            <div class="voucher-picker-top-row">
              <span class="voucher-picker-code" style="${v.is_used ? 'user-select:none; color:#94a3b8; background:#f1f5f9; border-color:#cbd5e1;' : ''}">${v.is_used ? '🔒 ' + 'x'.repeat(Math.max(8, String(v.code || '').length)) : '🎟️ ' + escapeHtml(v.code)}</span>
              ${v.min_member_tier && v.min_member_tier !== 'ALL' ? `<span class="voucher-picker-tier">👑 Hạng ${escapeHtml(v.min_member_tier)}+</span>` : ''}
              ${v.applicable_scope === 'specific' && v.applicable_label ? `<span style="font-size:0.7rem; font-weight:750; color:#1d4ed8; background:#eff6ff; border:1px solid #bfdbfe; padding:0.15rem 0.45rem; border-radius:6px;">🍽️ ${escapeHtml(v.applicable_label)}</span>` : ''}
              ${Number(v.user_limit || 1) === 1 ? `<span style="font-size:0.7rem; font-weight:750; color:#9a3412; background:#fff7ed; border:1px solid #fed7aa; padding:0.15rem 0.45rem; border-radius:6px;">🔒 1 lần/tài khoản</span>` : ''}
              ${v.max_discount ? `<span style="font-size:0.7rem; font-weight:700; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; padding:0.15rem 0.45rem; border-radius:6px;">Tối đa ${Number(v.max_discount).toLocaleString('vi-VN')}đ</span>` : ''}
            </div>
            
            <h4 class="voucher-picker-title">${escapeHtml(v.title)}</h4>

            <div class="voucher-picker-condition">
              <span>${condText}</span>
              <span class="voucher-picker-dot">•</span>
              <span>HSD: ${escapeHtml(v.end_date)}</span>
            </div>

            ${warningHtml}
          </div>

          <!-- Right side action button -->
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
      } else {
        alert(data.message || 'Không thể gỡ voucher.');
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
