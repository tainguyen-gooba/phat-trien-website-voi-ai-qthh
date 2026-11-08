/**
 * FASTFOOD Frontend Utilities
 */
const Utils = {
  /**
   * Định dạng tiền tệ VNĐ (ví dụ: 50.000 đ)
   */
  formatCurrency(amount) {
    if (isNaN(amount) || amount === null) return '0 đ';
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND',
    }).format(amount);
  },

  /**
   * Định dạng ngày giờ thân thiện
   */
  formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(date);
  },

  /**
   * Hiển thị thông báo Toast góc màn hình
   */
  showToast(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <div style="display:flex; align-items:center; gap:0.5rem;">
        <span>${type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️'}</span>
        <span>${message}</span>
      </div>
      <button style="color:var(--text-muted); cursor:pointer; font-weight:bold;" onclick="this.parentElement.remove()">✕</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  /**
   * Helper lưu và lấy từ localStorage
   */
  storage: {
    get(key) {
      try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : null;
      } catch {
        return null;
      }
    },
    set(key, value) {
      try {
        localStorage.setItem(key, JSON.stringify(value));
      } catch (err) {
        console.error('Storage error:', err);
      }
    },
    remove(key) {
      localStorage.removeItem(key);
    },
  },
};

window.Utils = Utils;

