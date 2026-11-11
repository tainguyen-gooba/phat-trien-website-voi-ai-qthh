const LIVE_CONTENT_KEY = 'fastfood-live-content-v1';
const LIVE_CONTENT_COLLECTIONS = new Set(['categories', 'products', 'restaurants']);

function mergeLiveContent(update) {
  if (!update || !LIVE_CONTENT_COLLECTIONS.has(update.collection) || !Array.isArray(update.records)) return false;

  let current = {};
  try {
    current = JSON.parse(localStorage.getItem(LIVE_CONTENT_KEY) || '{}');
  } catch (error) {
    console.error('Không thể đọc nội dung realtime đã lưu:', error);
  }

  const next = { ...current, [update.collection]: update.records };
  if (JSON.stringify(current) === JSON.stringify(next)) return false;
  localStorage.setItem(LIVE_CONTENT_KEY, JSON.stringify(next));
  return true;
}

function getRealtimeOrigin() {
  if (window.location.port === '5000') return window.location.origin;
  if (window.location.protocol === 'file:') return 'http://localhost:5000';
  return 'http://localhost:5000';
}

window.initFastfoodRealtime = function initFastfoodRealtime(user = null) {
  if (window.fastfoodRealtimeReady) return window.fastfoodRealtimeReady;

  const backendOrigin = getRealtimeOrigin();

  window.fastfoodRealtimeReady = new Promise((resolve) => {
    const start = () => {
      const currentUser = user || window.StorageManager?.getUser();
      const token = sessionStorage.getItem('fastfood_auth_token');
      const socket = window.io(backendOrigin, {
        auth: { token },
        reconnection: true,
        reconnectionAttempts: 10,
      });
      window.FASTFOOD_SOCKET = socket;

      socket.on('connect', () => {
        window.dispatchEvent(new CustomEvent('fastfood:socket-connected', { detail: { socket } }));
        fetch(`${backendOrigin}/api/v1/realtime/content`, { headers: { Accept: 'application/json' } })
          .then(async (response) => {
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Không thể tải nội dung realtime.');
            let changed = false;
            Object.entries(result.data).forEach(([collection, records]) => {
              changed = mergeLiveContent({ collection, records }) || changed;
            });
            if (changed) window.location.reload();
          })
          .catch((error) => console.error('[Realtime content load failed]', error.message));
      });

      socket.on('content:updated', (update) => {
        if (!mergeLiveContent(update)) return;
        if (!window.location.pathname.endsWith('/pages/admin/dashboard.html')) window.location.reload();
        else window.UIComponents?.showToast('Nội dung website đã được đồng bộ.', 'success');
      });
      socket.on('notification:new', (notification) => {
        window.UIComponents?.showToast(notification.content || notification.title, 'info');
      });
      socket.on('support:new_message', (payload) => {
        window.dispatchEvent(new CustomEvent('fastfood:support-message', { detail: payload }));
      });
      socket.on('support:status', (payload) => {
        window.dispatchEvent(new CustomEvent('fastfood:support-status', { detail: payload }));
      });
      socket.on('order:updated', (update) => {
        window.dispatchEvent(new CustomEvent('fastfood:order-updated', { detail: update }));
      });
      socket.on('payment:updated', (update) => {
        window.dispatchEvent(new CustomEvent('fastfood:payment-updated', { detail: update }));
      });
      socket.on('realtime:error', (error) => {
        window.UIComponents?.showToast(error.message || 'Đã xảy ra lỗi realtime.', 'error');
      });
      socket.on('connect_error', (error) => {
        console.error('[Realtime connection failed]', error.message);
      });

      resolve(socket);
    };

    if (window.io) {
      start();
      return;
    }

    const script = document.createElement('script');
    script.src = `${backendOrigin}/socket.io/socket.io.js`;
    script.onload = start;
    script.onerror = () => {
      window.fastfoodRealtimeReady = null;
      console.warn('Không thể tải Socket.IO client, hệ thống dùng đồng bộ HTTP.');
      resolve(null);
    };
    document.head.appendChild(script);
  });

  return window.fastfoodRealtimeReady;
};
