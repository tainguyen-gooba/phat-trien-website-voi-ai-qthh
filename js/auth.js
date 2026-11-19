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
 * Khởi tạo Welcome Voucher Modal
 */
function initWelcomeModal() {
  const modal = document.getElementById('welcome-voucher-modal');
  if (!modal) return;

  // Tự động mở modal
  setTimeout(() => {
    modal.classList.add('active');
    triggerConfetti();
  }, 400);

  // Nút đóng
  const closeBtns = modal.querySelectorAll('.js-close-welcome-modal');
  closeBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      closeWelcomeModal();
    });
  });

  // Bấm phím Escape để đóng
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeWelcomeModal();
    }
  });

  // Nút sao chép mã voucher
  const copyBtn = document.getElementById('welcome-copy-code-btn');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      const code = this.getAttribute('data-code') || 'NEW50';
      navigator.clipboard.writeText(code).then(() => {
        const origText = this.innerHTML;
        this.innerHTML = `✓ Đã chép`;
        this.style.background = '#10B981';
        setTimeout(() => {
          this.innerHTML = origText;
          this.style.background = '';
        }, 2500);
      }).catch(() => {
        // Fallback
        const temp = document.createElement('input');
        temp.value = code;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        this.innerHTML = `✓ Đã chép`;
      });
    });
  }
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
