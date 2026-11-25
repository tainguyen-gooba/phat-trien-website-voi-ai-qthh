/**
 * FASTFOOD - Authentication & Welcome Voucher Script
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Toggle Show / Hide Password
  initPasswordToggles();

  // 2. Password Strength Meter (cho form Register)
  initPasswordStrength();

  // 3. Demo Account Autofill (cho form Login)
  initDemoAccountFill();

  // 4. Welcome Modal & Confetti
  initWelcomeModal();
});

/**
 * Bật / tắt ẩn hiện mật khẩu
 */
function initPasswordToggles() {
  const toggleBtns = document.querySelectorAll('.auth-toggle-pass');
  toggleBtns.forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';

      // Đổi icon
      this.innerHTML = isPass
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
      this.setAttribute('title', isPass ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
    });
  });
}

/**
 * Kiểm tra độ mạnh mật khẩu khi đăng ký
 */
function initPasswordStrength() {
  const passInput = document.getElementById('reg-pass');
  const bars = document.querySelectorAll('.auth-meter-bar');
  const textElem = document.getElementById('pass-strength-text');
  if (!passInput || bars.length === 0 || !textElem) return;

  passInput.addEventListener('input', function () {
    const val = this.value;
    let score = 0;

    if (val.length >= 6) score++;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score++;

    // Reset màu
    bars.forEach(b => {
      b.style.background = '#E2E8F0';
    });

    if (!val) {
      textElem.textContent = 'Độ mạnh: Chưa nhập';
      textElem.style.color = '#64748B';
      return;
    }

    const colors = ['#EF4444', '#F59E0B', '#3B82F6', '#10B981'];
    const labels = ['Quá yếu', 'Trung bình', 'Khá tốt', 'Rất mạnh'];

    const activeColor = colors[score - 1] || '#EF4444';
    const activeLabel = labels[score - 1] || 'Quá yếu';

    for (let i = 0; i < score; i++) {
      if (bars[i]) bars[i].style.background = activeColor;
    }

    textElem.textContent = 'Độ mạnh: ' + activeLabel;
    textElem.style.color = activeColor;
  });
}

/**
 * Điền nhanh tài khoản demo
 */
function initDemoAccountFill() {
  const chips = document.querySelectorAll('[data-demo-email]');
  chips.forEach(chip => {
    chip.addEventListener('click', function () {
      const email = this.getAttribute('data-demo-email');
      const pass = this.getAttribute('data-demo-pass') || '123456';

      const emailInput = document.getElementById('login-email');
      const passInput = document.getElementById('login-password');

      if (emailInput) {
        emailInput.value = email;
        emailInput.dispatchEvent(new Event('input'));
      }
      if (passInput) {
        passInput.value = pass;
        passInput.dispatchEvent(new Event('input'));
      }

      // Tạo hiệu ứng highlight
      if (emailInput) {
        emailInput.focus();
        emailInput.style.borderColor = '#10B981';
        setTimeout(() => { emailInput.style.borderColor = ''; }, 1000);
      }
    });
  });
}

/**
 * Khởi tạo Welcome Voucher Modal & Anti-Duplication Claim Form
 */
function initWelcomeModal() {
  const modal = document.getElementById('welcome-voucher-modal');
  if (!modal) return;
  if (modal.dataset.initialized === '1') return;
  modal.dataset.initialized = '1';

  const formView = document.getElementById('voucher-form-view');
  const successView = document.getElementById('voucher-success-view');
  const claimForm = document.getElementById('welcome-voucher-claim-form');
  const errorAlert = document.getElementById('voucher-error-alert');
  const errorMsg = document.getElementById('voucher-error-msg');
  const submitBtn = document.getElementById('voucher-submit-btn');
  const submitText = document.getElementById('voucher-submit-text');
  const submitSpinner = document.getElementById('voucher-submit-spinner');

  // Mở modal mượt mà sau khi trang đã render
  setTimeout(() => {
    modal.classList.add('active');
    // Nếu chỉ là popup chúc mừng cũ (không có form định danh) thì bắn pháo hoa ngay
    if (!claimForm) {
      triggerConfetti();
    }
  }, 350);

  // Xử lý gửi Form Định Danh & Nhận Voucher 50.000đ
  if (claimForm) {
    claimForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      // Reset alert
      if (errorAlert) errorAlert.style.display = 'none';
      const inputs = claimForm.querySelectorAll('.welcome-form-input');
      inputs.forEach(i => i.classList.remove('is-invalid'));

      const fullName = (document.getElementById('voucher-fullname')?.value || '').trim();
      const phone = (document.getElementById('voucher-phone')?.value || '').trim();
      const dob = (document.getElementById('voucher-dob')?.value || '').trim();
      const address = (document.getElementById('voucher-address')?.value || '').trim();

      // Validation phía Client
      if (fullName.length < 2) {
        showVoucherError('Vui lòng nhập họ và tên hợp lệ (tối thiểu 2 ký tự).', 'voucher-fullname');
        return;
      }

      const cleanPhone = phone.replace(/[^0-9]/g, '');
      if (cleanPhone.length < 9 || cleanPhone.length > 11) {
        showVoucherError('Vui lòng nhập số điện thoại hợp lệ (9 - 11 chữ số).', 'voucher-phone');
        return;
      }

      if (!dob || !/^\d{4}-\d{2}-\d{2}$/.test(dob)) {
        showVoucherError('Vui lòng chọn ngày sinh hợp lệ.', 'voucher-dob');
        return;
      }
      const dobDate = new Date(dob + 'T00:00:00');
      const todayEnd = new Date();
      todayEnd.setHours(23, 59, 59, 999);
      if (isNaN(dobDate.getTime()) || dobDate > todayEnd || dob < '1920-01-01') {
        showVoucherError('Ngày sinh không hợp lệ (không được chọn ngày ở tương lai).', 'voucher-dob');
        return;
      }

      if (address.length < 5) {
        showVoucherError('Vui lòng nhập địa chỉ giao hàng chi tiết (tối thiểu 5 ký tự).', 'voucher-address');
        return;
      }

      // Trạng thái Loading
      if (submitBtn) submitBtn.disabled = true;
      if (submitText) submitText.style.display = 'none';
      if (submitSpinner) submitSpinner.style.display = 'inline';

      try {
        const claimUrl = window.getApiUrl ? window.getApiUrl('api/claim-voucher.php') : 'api/claim-voucher.php';
        const response = await fetch(claimUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            fullName: fullName,
            phone: cleanPhone,
            date_of_birth: dob,
            address: address
          })
        });

        const result = await response.json().catch(() => null);

        if (response.ok && result && result.success) {
          // Thành công: chuyển sang View chúc mừng & bắn Confetti
          if (formView) formView.style.display = 'none';
          if (successView) {
            successView.style.display = 'block';
            successView.style.animation = 'fadeIn 0.4s ease';
          }

          const voucherCode = result.data?.voucher?.code || 'NEW50K';
          const codeDisplay = document.getElementById('voucher-granted-code');
          if (codeDisplay) codeDisplay.textContent = voucherCode;

          const copyBtn = document.getElementById('voucher-copy-btn');
          if (copyBtn) copyBtn.setAttribute('data-code', voucherCode);

          triggerConfetti();
        } else {
          // Lỗi từ chối do trùng lặp hoặc vi phạm quy tắc
          const message = result?.message || 'Không thể cấp voucher. Vui lòng kiểm tra lại thông tin.';
          let highlightField = null;
          if (message.includes('Ngày sinh') || message.includes('Năm sinh') || message.includes('sinh')) {
            highlightField = 'voucher-dob';
          } else if (message.includes('điện thoại') || message.includes('SĐT')) {
            highlightField = 'voucher-phone';
          } else if (message.includes('địa chỉ')) {
            highlightField = 'voucher-address';
          }
          showVoucherError(message, highlightField);
        }
      } catch (err) {
        showVoucherError('Có lỗi kết nối đến máy chủ. Vui lòng thử lại sau.');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (submitText) submitText.style.display = 'inline';
        if (submitSpinner) submitSpinner.style.display = 'none';
      }
    });
  }

  function showVoucherError(msg, fieldId) {
    if (errorAlert && errorMsg) {
      errorMsg.textContent = msg;
      errorAlert.style.display = 'flex';
      errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
      alert(msg);
    }
    if (fieldId) {
      const input = document.getElementById(fieldId);
      if (input) {
        input.classList.add('is-invalid');
        input.focus();
      }
    }
  }

  // Xử lý nút "Bỏ qua / Để sau" & Nút đóng "✕" (Không làm gián đoạn tài khoản)
  const skipBtns = modal.querySelectorAll('.js-skip-voucher-modal');
  skipBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      // Gửi yêu cầu giải phóng cờ session ở background
      const skipUrl = window.getApiUrl ? window.getApiUrl('api/claim-voucher.php?action=skip_welcome') : 'api/claim-voucher.php?action=skip_welcome';
      fetch(skipUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).catch(() => {});

      closeWelcomeModal();
    });
  });

  // Nút đóng ở view chúc mừng
  const closeBtns = modal.querySelectorAll('.js-close-voucher-modal, .js-close-welcome-modal');
  closeBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      closeWelcomeModal();
    });
  });

  // Bấm phím Escape để đóng/bỏ qua
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      const skipUrl = window.getApiUrl ? window.getApiUrl('api/claim-voucher.php?action=skip_welcome') : 'api/claim-voucher.php?action=skip_welcome';
      fetch(skipUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).catch(() => {});
      closeWelcomeModal();
    }
  });

  // Xử lý nút sao chép mã voucher (cả ở view mới và view cũ)
  setupCopyButtons();
}

function setupCopyButtons() {
  const copyButtons = document.querySelectorAll('#welcome-copy-code-btn, #voucher-copy-btn, #welcome-copy-voucher-btn');
  copyButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const code = this.getAttribute('data-code') || 
                   document.getElementById('voucher-granted-code')?.textContent || 
                   document.getElementById('welcome-voucher-display')?.textContent || 
                   'NEW50K';

      const doFeedback = () => {
        const origHtml = btn.innerHTML;
        btn.innerHTML = `✓ Đã chép`;
        btn.style.background = '#10B981';
        setTimeout(() => {
          btn.innerHTML = origHtml;
          btn.style.background = '';
        }, 2500);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(doFeedback).catch(() => {
          fallbackCopy(code, doFeedback);
        });
      } else {
        fallbackCopy(code, doFeedback);
      }
    });
  });
}

function fallbackCopy(text, callback) {
  const temp = document.createElement('textarea');
  temp.value = text;
  temp.style.position = 'fixed';
  temp.style.left = '-9999px';
  document.body.appendChild(temp);
  temp.focus();
  temp.select();
  try {
    document.execCommand('copy');
    if (callback) callback();
  } catch (e) {}
  document.body.removeChild(temp);
}

function closeWelcomeModal() {
  const modal = document.getElementById('welcome-voucher-modal');
  if (modal) {
    modal.classList.remove('active');
    setTimeout(() => {
      modal.remove();
    }, 400);
  }
}

/**
 * Hiệu ứng bắn pháo hoa Confetti trên canvas
 */
function triggerConfetti() {
  const canvas = document.getElementById('welcome-confetti-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const width = canvas.parentElement.offsetWidth || 480;
  const height = canvas.parentElement.offsetHeight || 550;
  canvas.width = width;
  canvas.height = height;

  const pieces = [];
  const numberOfPieces = 55;
  const colors = ['#F28A24', '#FF5722', '#FFD166', '#06D6A0', '#118AB2', '#FF4742'];

  for (let i = 0; i < numberOfPieces; i++) {
    pieces.push({
      x: width / 2,
      y: height / 3,
      r: Math.random() * 6 + 3,
      vx: (Math.random() - 0.5) * 12,
      vy: (Math.random() - 0.8) * 10,
      color: colors[Math.floor(Math.random() * colors.length)],
      tilt: Math.random() * 10 - 5,
      tiltAngle: 0,
      tiltAngleInc: (Math.random() * 0.08) + 0.04,
      gravity: 0.22,
      alpha: 1,
      decay: Math.random() * 0.008 + 0.005
    });
  }

  let animationFrame;
  function updateConfetti() {
    ctx.clearRect(0, 0, width, height);

    let activePieces = 0;
    pieces.forEach(p => {
      p.x += p.vx;
      p.y += p.vy;
      p.vy += p.gravity;
      p.vx *= 0.98;
      p.tiltAngle += p.tiltAngleInc;
      p.alpha -= p.decay;

      if (p.alpha > 0) {
        activePieces++;
        ctx.save();
        ctx.globalAlpha = Math.max(0, p.alpha);
        ctx.fillStyle = p.color;
        ctx.beginPath();
        ctx.ellipse(p.x, p.y, p.r, p.r * Math.cos(p.tiltAngle), 0, 0, 2 * Math.PI);
        ctx.fill();
        ctx.restore();
      }
    });

    if (activePieces > 0) {
      animationFrame = requestAnimationFrame(updateConfetti);
    } else {
      ctx.clearRect(0, 0, width, height);
      cancelAnimationFrame(animationFrame);
    }
  }

  updateConfetti();
}
