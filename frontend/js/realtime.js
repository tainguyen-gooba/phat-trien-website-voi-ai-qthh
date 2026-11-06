const LIVE_CONTENT_KEY = 'fastfood-live-content-v1';
const LIVE_CONTENT_COLLECTIONS = new Set(['categories', 'products']);

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

function appendChatMessage(container, message, currentUser) {
  if (!container || !message) return;
  if (message.id && [...container.querySelectorAll('[data-message-id]')]
    .some((element) => element.dataset.messageId === String(message.id))) return;
  const row = document.createElement('div');
  row.className = `live-chat-message${String(message.senderId) === String(currentUser?.id) ? ' mine' : ''}`;
  if (message.id) row.dataset.messageId = String(message.id);
  row.textContent = message.message;
  container.appendChild(row);
  container.scrollTop = container.scrollHeight;
}

function mountLiveChat(user, socket) {
  if (!user || document.getElementById('live-chat-launcher')) return;

  const isAdmin = user.role === 'admin';
  const launcher = document.createElement('button');
  launcher.id = 'live-chat-launcher';
  launcher.type = 'button';
  launcher.textContent = '💬 Hỗ trợ trực tiếp';

  const panel = document.createElement('section');
  panel.id = 'live-chat-panel';
  panel.innerHTML = `
    <header class="live-chat-header">
      <strong>${isAdmin ? 'Hộp thư hỗ trợ' : 'FASTFOOD hỗ trợ'}</strong>
      <button type="button" aria-label="Đóng chat">×</button>
    </header>
    ${isAdmin ? '<label class="live-chat-customer">Mã khách hàng <input id="live-chat-customer-id" inputmode="numeric" placeholder="Chọn từ tin nhắn đến"></label>' : ''}
    <div class="live-chat-messages" aria-live="polite"></div>
    <form class="live-chat-form">
      <input name="message" maxlength="1000" placeholder="Nhập tin nhắn..." required>
      <button type="submit">Gửi</button>
    </form>
  `;
  document.body.append(launcher, panel);

  const messageList = panel.querySelector('.live-chat-messages');
  const targetInput = panel.querySelector('#live-chat-customer-id');
  let historyCustomerId = '';

  function loadHistory(customerId) {
    if (!customerId || historyCustomerId === customerId) return;
    historyCustomerId = customerId;
    messageList.replaceChildren();
    const token = localStorage.getItem('fastfood_auth_token') || sessionStorage.getItem('fastfood_auth_token');
    fetch(`/api/v1/realtime/chat/${encodeURIComponent(customerId)}`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Không thể tải lịch sử chat.');
        result.data.forEach((message) => appendChatMessage(messageList, message, user));
      })
      .catch((error) => {
        window.UIComponents?.showToast(error.message, 'error');
        historyCustomerId = '';
      });
  }

  launcher.addEventListener('click', () => {
    panel.classList.toggle('open');
    if (!panel.classList.contains('open')) return;
    const customerId = isAdmin ? targetInput.value.trim() : String(user.id);
    loadHistory(customerId);
    panel.querySelector('input[name="message"]').focus();
  });
  panel.querySelector('.live-chat-header button').addEventListener('click', () => panel.classList.remove('open'));
  targetInput?.addEventListener('change', () => {
    historyCustomerId = '';
    loadHistory(targetInput.value.trim());
  });

  panel.querySelector('.live-chat-form').addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const input = form.elements.message;
    const message = input.value.trim();
    if (!message) return;
    if (!socket?.connected) {
      window.UIComponents?.showToast('Kết nối chat chưa sẵn sàng. Vui lòng thử lại.', 'warning');
      return;
    }

    socket.emit('chat:send', {
      message,
      ...(isAdmin ? { customerId: targetInput.value.trim() } : {}),
    }, (result) => {
      if (!result?.success) {
        window.UIComponents?.showToast(result?.message || 'Không thể gửi tin nhắn.', 'error');
        return;
      }
      input.value = '';
    });
  });

  socket?.on('chat:new', (message) => {
    if (isAdmin) {
      const selectedCustomerId = targetInput.value.trim();
      if (!selectedCustomerId && !panel.classList.contains('open')) {
        targetInput.value = message.customerId;
        historyCustomerId = '';
      }
      if (panel.classList.contains('open')
        && (!selectedCustomerId || selectedCustomerId === String(message.customerId))) {
        if (!selectedCustomerId) {
          targetInput.value = message.customerId;
          historyCustomerId = '';
          loadHistory(message.customerId);
        }
        appendChatMessage(messageList, message, user);
      } else {
        window.UIComponents?.showToast(`Tin nhắn hỗ trợ mới từ khách #${message.customerId}.`, 'info');
      }
    } else if (panel.classList.contains('open')) {
      appendChatMessage(messageList, message, user);
    } else {
      window.UIComponents?.showToast('Bạn có tin nhắn hỗ trợ mới.', 'info');
    }
  });

  const style = document.createElement('style');
  style.textContent = `
    #live-chat-launcher{position:fixed;right:22px;bottom:22px;z-index:1100;padding:12px 18px;border:0;border-radius:28px;background:#f28a24;color:#fff;font:600 14px Arial,sans-serif;box-shadow:0 6px 24px #0003;cursor:pointer}
    #live-chat-panel{display:none;position:fixed;right:22px;bottom:78px;z-index:1101;width:min(360px,calc(100vw - 32px));height:430px;max-height:70vh;background:#fff;border:1px solid #eee;border-radius:16px;box-shadow:0 12px 40px #0003;overflow:hidden}
    #live-chat-panel.open{display:flex;flex-direction:column}
    .live-chat-header{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:#f28a24;color:#fff}
    .live-chat-header button{font-size:22px;color:#fff;cursor:pointer}
    .live-chat-customer{padding:10px 12px;font-size:12px}
    .live-chat-customer input{width:100%;margin-top:5px;padding:8px;border:1px solid #ddd;border-radius:8px}
    .live-chat-messages{flex:1;overflow:auto;padding:12px;background:#fafafa}
    .live-chat-message{width:fit-content;max-width:85%;margin:0 0 8px;padding:9px 12px;border-radius:12px;background:#eee;overflow-wrap:anywhere;white-space:pre-wrap}
    .live-chat-message.mine{margin-left:auto;background:#fff0df}
    .live-chat-form{display:flex;gap:8px;padding:10px;border-top:1px solid #eee}
    .live-chat-form input{flex:1;min-width:0;padding:10px;border:1px solid #ddd;border-radius:8px}
    .live-chat-form button{padding:0 14px;border-radius:8px;background:#f28a24;color:#fff;font-weight:700}
  `;
  document.head.appendChild(style);
}

window.initFastfoodRealtime = function initFastfoodRealtime(user = null) {
  if (window.fastfoodRealtimeReady) return window.fastfoodRealtimeReady;

  window.fastfoodRealtimeReady = new Promise((resolve) => {
    const start = () => {
      const currentUser = user || window.StorageManager?.getUser();
      const token = localStorage.getItem('fastfood_auth_token') || sessionStorage.getItem('fastfood_auth_token');
      const socket = window.io({
        auth: { token },
        reconnection: true,
        reconnectionAttempts: 10,
      });
      window.FASTFOOD_SOCKET = socket;

      socket.on('connect', () => {
        fetch('/api/v1/realtime/content', { headers: { Accept: 'application/json' } })
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
        if (currentUser?.role !== 'admin') window.location.reload();
        else window.UIComponents?.showToast('Nội dung website đã được đồng bộ.', 'success');
      });
      socket.on('notification:new', (notification) => {
        window.UIComponents?.showToast(notification.content || notification.title, 'info');
      });
      socket.on('order:updated', (update) => {
        window.dispatchEvent(new CustomEvent('fastfood:order-updated', { detail: update }));
      });
      socket.on('realtime:error', (error) => {
        window.UIComponents?.showToast(error.message || 'Đã xảy ra lỗi realtime.', 'error');
      });
      socket.on('connect_error', (error) => {
        console.error('[Realtime connection failed]', error.message);
      });

      mountLiveChat(currentUser, socket);
      resolve(socket);
    };

    if (window.io) {
      start();
      return;
    }

    const script = document.createElement('script');
    script.src = '/socket.io/socket.io.js';
    script.onload = start;
    script.onerror = () => {
      window.fastfoodRealtimeReady = null;
      console.error('Không thể tải Socket.IO client.');
      resolve(null);
    };
    document.head.appendChild(script);
  });

  return window.fastfoodRealtimeReady;
};
