/**
 * FASTFOOD AUTHENTICATION CONTROLLER (Login / Register)
 */

document.addEventListener('DOMContentLoaded', () => {
  if (window.location.protocol === 'file:') {
    window.location.replace('http://localhost:5000/pages/login.html');
    return;
  }

  UIComponents.renderHeader('auth');
  UIComponents.renderFooter();

  // Login Form
  const loginForm = document.getElementById('login-page-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('login-email')?.value.trim().toLowerCase();
      const password = document.getElementById('login-password')?.value;
      const submitButton = loginForm.querySelector('button[type="submit"]');

      if (!email || !password) {
        UIComponents.showToast('Vui lòng nhập đầy đủ email và mật khẩu!', 'warning');
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Đang xác thực...';

      try {
        const apiUrl = window.location.port === '5000'
          ? `${window.location.origin}/api/v1`
          : 'http://localhost:5000/api/v1';
        const response = await fetch(`${apiUrl}/auth/login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ email, password }),
        });
        const result = await response.json();

        if (!response.ok || !result.success || !result.data?.token || !result.data?.user) {
          if (response.status === 503 && result.message?.includes('MySQL')) {
            throw new Error('MySQL chưa chạy. Hãy bật MySQL rồi thử đăng nhập lại.');
          }
          throw new Error(result.message || 'Không thể đăng nhập. Vui lòng thử lại.');
        }

        const { token, user } = result.data;
        const rememberLogin = loginForm.querySelector('input[type="checkbox"]')?.checked;
        const tokenStorage = rememberLogin ? localStorage : sessionStorage;
        const otherStorage = rememberLogin ? sessionStorage : localStorage;
        tokenStorage.setItem('fastfood_auth_token', token);
        otherStorage.removeItem('fastfood_auth_token');
        StorageManager.setUser({ ...user, isLoggedIn: true });

        UIComponents.showToast('Đăng nhập thành công! Đang chuyển trang...', 'success');
        const destination = user.role === 'admin' ? './admin/dashboard.html' : './home.html';
        window.setTimeout(() => {
          window.location.href = destination;
        }, 500);
      } catch (error) {
        UIComponents.showToast(error.message || 'Không thể kết nối máy chủ đăng nhập.', 'error');
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Đăng Nhập';
      }
    });
  }

  // Register Form
  const registerForm = document.getElementById('register-page-form');
  if (registerForm) {
    registerForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const fullName = document.getElementById('reg-fullname')?.value.trim();
      const email = document.getElementById('reg-email')?.value.trim();
      const phone = document.getElementById('reg-phone')?.value.trim();
      const password = document.getElementById('reg-password')?.value;

      if (!fullName || !email || !phone || !password) {
        UIComponents.showToast('Vui lòng điền đầy đủ các thông tin đăng ký!', 'warning');
        return;
      }

      const user = {
        id: `usr-${Date.now()}`,
        fullName,
        email,
        phone,
        address: 'Chưa cập nhật địa chỉ',
        isLoggedIn: true,
      };

      StorageManager.setUser(user);
      UIComponents.showToast('Đăng ký tài khoản thành công!', 'success');

      setTimeout(() => {
        window.location.href = './home.html';
      }, 700);
    });
  }
});

// Demo login credentials quick helper
function fillDemoAccount(role) {
  const emailInput = document.getElementById('login-email');
  const passInput = document.getElementById('login-password');

  if (role === 'admin') {
    if (emailInput) emailInput.value = 'admin@fastfood.vn';
  } else if (role === 'owner') {
    if (emailInput) emailInput.value = 'owner@fastfood.vn';
  } else {
    if (emailInput) emailInput.value = 'customer@fastfood.vn';
  }

  if (passInput) passInput.value = 'Admin@123456';
  UIComponents.showToast(`Đã điền tài khoản mẫu: ${role.toUpperCase()}`, 'info');
}

window.fillDemoAccount = fillDemoAccount;
