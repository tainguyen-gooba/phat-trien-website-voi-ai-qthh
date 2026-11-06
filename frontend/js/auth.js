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

  const nextPath = new URLSearchParams(window.location.search).get('next');
  const returnPath = nextPath && nextPath.startsWith('/pages/') && !nextPath.startsWith('//')
    ? nextPath
    : '/pages/home.html';
  const registerLink = document.getElementById('login-register-link');
  const loginLink = document.getElementById('register-login-link');
  if (registerLink && returnPath !== '/pages/home.html') {
    registerLink.href = `./register.html?next=${encodeURIComponent(returnPath)}`;
  }
  if (loginLink && returnPath !== '/pages/home.html') {
    loginLink.href = `./login.html?next=${encodeURIComponent(returnPath)}`;
  }

  function saveSession(token, user, rememberLogin = true) {
    const tokenStorage = rememberLogin ? localStorage : sessionStorage;
    const otherStorage = rememberLogin ? sessionStorage : localStorage;
    tokenStorage.setItem('fastfood_auth_token', token);
    otherStorage.removeItem('fastfood_auth_token');
    StorageManager.setUser({ ...user, isLoggedIn: true });
  }

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
          throw new Error(result.message || 'Không thể đăng nhập. Vui lòng thử lại.');
        }

        const { token, user } = result.data;
        const rememberLogin = loginForm.querySelector('input[type="checkbox"]')?.checked;
        saveSession(token, user, rememberLogin);

        UIComponents.showToast('Đăng nhập thành công! Đang chuyển trang...', 'success');
        window.setTimeout(() => {
          window.location.href = returnPath;
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
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fullName = document.getElementById('reg-fullname')?.value.trim();
      const email = document.getElementById('reg-email')?.value.trim().toLowerCase();
      const phone = document.getElementById('reg-phone')?.value.trim();
      const password = document.getElementById('reg-password')?.value;
      const submitButton = registerForm.querySelector('button[type="submit"]');

      if (!fullName || !email || !phone || !password) {
        UIComponents.showToast('Vui lòng điền đầy đủ các thông tin đăng ký!', 'warning');
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Đang tạo tài khoản...';
      try {
        const apiUrl = window.location.port === '5000'
          ? `${window.location.origin}/api/v1`
          : 'http://localhost:5000/api/v1';
        const response = await fetch(`${apiUrl}/auth/register`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ fullName, email, phone, password }),
        });
        const result = await response.json();

        if (!response.ok || !result.success || !result.data?.token || !result.data?.user) {
          throw new Error(result.message || 'Không thể tạo tài khoản. Vui lòng thử lại.');
        }

        saveSession(result.data.token, result.data.user);
        UIComponents.showToast('Đăng ký thành công! Đang chuyển trang...', 'success');
        window.setTimeout(() => {
          window.location.href = returnPath;
        }, 500);
      } catch (error) {
        UIComponents.showToast(error.message || 'Không thể kết nối máy chủ đăng ký.', 'error');
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Tạo Tài Khoản Ngay';
      }
    });
  }
});
