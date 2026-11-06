/**
 * Authentication Module Logic
 * Quản lý logic form đăng nhập, đăng ký và lưu trữ JWT token
 */
document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('email')?.value.trim();
      const password = document.getElementById('password')?.value;

      if (!email || !password) {
        window.Utils?.showToast('Vui lòng điền đầy đủ email và mật khẩu!', 'warning');
        return;
      }

      window.Utils?.showToast('Module Auth API sẽ được kết nối hoàn chỉnh tại Prompt 1!', 'info');
    });
  }

  if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fullName = document.getElementById('fullName')?.value.trim();
      const email = document.getElementById('email')?.value.trim();
      const phone = document.getElementById('phone')?.value.trim();
      const password = document.getElementById('password')?.value;

      if (!fullName || !email || !phone || !password) {
        window.Utils?.showToast('Vui lòng điền đầy đủ thông tin đăng ký!', 'warning');
        return;
      }

      window.Utils?.showToast('Module Auth API sẽ được kết nối hoàn chỉnh tại Prompt 1!', 'info');
    });
  }
});

