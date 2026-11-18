/**
 * FASTFOOD - Unified Realtime Engine (PHP Native & Cross-Tab Broadcast)
 * Đồng bộ Realtime Đơn Hàng & Tin Nhắn giữa các Tab bằng BroadcastChannel và Polling
 */
(function(window) {
  'use strict';

  // 1. Âm thanh chuông báo đơn mới (Ding-Dong Chime bằng Web Audio API)
  window.playOrderNotificationSound = function() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const now = ctx.currentTime;

      // Note 1: E5 (659.25Hz)
      const osc1 = ctx.createOscillator();
      const gain1 = ctx.createGain();
      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(659.25, now);
      gain1.gain.setValueAtTime(0.3, now);
      gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
      osc1.connect(gain1);
      gain1.connect(ctx.destination);
      osc1.start(now);
      osc1.stop(now + 0.45);

      // Note 2: B5 (987.77Hz)
      const osc2 = ctx.createOscillator();
      const gain2 = ctx.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(987.77, now + 0.12);
      gain2.gain.setValueAtTime(0.35, now + 0.12);
      gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
      osc2.connect(gain2);
      gain2.connect(ctx.destination);
      osc2.start(now + 0.12);
      osc2.stop(now + 0.75);
    } catch (e) {
      console.log('[Realtime] Âm thanh chờ tương tác người dùng:', e);
    }
  };

  // 2. Kênh truyền tin trực tiếp giữa các tab (0ms Latency)
  const channel = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('fastfood_realtime') : null;
  const listeners = {};
  const processedEventIds = new Set();

  function triggerLocalListeners(eventName, payload) {
    if (listeners[eventName] && Array.isArray(listeners[eventName])) {
      listeners[eventName].forEach(cb => {
        try { cb(payload); } catch (err) { console.error('[Realtime Listener Error]', err); }
      });
    }
    const eventSlug = eventName.replace(/:/g, '-');
    window.dispatchEvent(new CustomEvent('fastfood:' + eventSlug, { detail: payload }));
  }

  // 3. Socket Object Interface (Tương thích 100% với Socket.IO của Admin & Chatbot)
  const socket = {
    connected: true,
    id: 'ff-native-' + Math.random().toString(36).substring(2, 9),

    on: function(event, callback) {
      if (!listeners[event]) listeners[event] = [];
      listeners[event].push(callback);
      return socket;
    },

    off: function(event, callback) {
      if (!listeners[event]) return socket;
      if (!callback) {
        delete listeners[event];
      } else {
        listeners[event] = listeners[event].filter(cb => cb !== callback);
      }
      return socket;
    },

    emit: function(event, data, callback) {
      const eventId = 'evt-' + Date.now() + '-' + Math.random().toString(36).substring(2, 6);
      processedEventIds.add(eventId);

      // A. Bắn qua BroadcastChannel cho các tab khác
      if (channel) {
        channel.postMessage({
          id: eventId,
          type: event,
          data: data,
          timestamp: Date.now()
        });
      }

      // B. Kích hoạt tại tab hiện tại
      triggerLocalListeners(event, data);

      // C. Xử lý backend HTTP nếu là tác vụ cập nhật đơn hoặc gửi chat
      if (event === 'admin:order:status' && data) {
        const orderNo = String(data.orderNo || data.id || '').replace(/^#/, '').trim();
        const status = data.status;
        if (orderNo && status) {
          fetch(`/api/v1/realtime/orders/${encodeURIComponent(orderNo)}/status`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + (sessionStorage.getItem('fastfood_auth_token') || '')
            },
            body: JSON.stringify({ status: status })
          }).catch(err => console.warn('[Realtime Status Sync Error]', err));

          // Phát sự kiện order:updated cho khách hàng ở tab khác
          const updatePayload = {
            id: orderNo,
            orderCode: orderNo,
            orderNo: '#' + orderNo,
            status: status,
            statusText: status
          };
          if (channel) {
            channel.postMessage({ type: 'order:updated', data: updatePayload });
          }
          triggerLocalListeners('order:updated', updatePayload);
        }
      }

      if (typeof callback === 'function') {
        setTimeout(() => callback(null, { success: true }), 15);
      }
      return socket;
    },

    timeout: function(ms) {
      return {
        emit: function(event, data, callback) {
          return socket.emit(event, data, callback);
        }
      };
    }
  };

  // 4. Lắng nghe tin nhắn từ BroadcastChannel (Cross-tab Realtime)
  if (channel) {
    channel.onmessage = function(event) {
      const payload = event.data;
      if (!payload || !payload.type) return;

      if (payload.id && processedEventIds.has(payload.id)) {
        return; // Bỏ qua nếu là sự kiện chính tab này vừa phát
      }
      if (payload.id) processedEventIds.add(payload.id);

      const eventName = payload.type;
      const eventData = payload.data !== undefined ? payload.data : payload;

      // Nếu có đơn mới và đang ở trang Admin -> phát chuông báo
      if (eventName === 'order:new') {
        const isAdmin = window.location.pathname.includes('/admin') || window.location.pathname.includes('dashboard');
        if (isAdmin) {
          window.playOrderNotificationSound();
        }
      }

      // Nếu nội dung website/bestseller được cập nhật từ Admin -> tự động đồng bộ trang chủ
      if (eventName === 'content:updated') {
        const isAdmin = window.location.pathname.includes('/admin') || window.location.pathname.includes('dashboard');
        if (!isAdmin && (window.location.pathname.endsWith('index.php') || window.location.pathname === '/' || window.location.pathname === '')) {
          setTimeout(() => window.location.reload(), 300);
        }
      }

      triggerLocalListeners(eventName, eventData);
    };
  }

  // 5. Intelligent Background Polling (Dự phòng đồng bộ với Database MySQL)
  let lastOrderId = 0;
  let lastChatId = 0;
  let isPolling = false;

  async function pollServer() {
    if (isPolling) return;
    isPolling = true;

    try {
      const isAdmin = window.location.pathname.includes('/admin');
      const trackedOrder = window.TRACKED_ORDER_CODE || '';
      const params = new URLSearchParams({
        action: 'poll',
        lastOrderId: String(lastOrderId),
        lastChatId: String(lastChatId),
        orderCode: trackedOrder
      });

      const res = await fetch(`/api/realtime.php?${params.toString()}`, {
        headers: { Accept: 'application/json' }
      });
      if (!res.ok) throw new Error('Poll failed');
      const result = await res.json();

      if (result.success && result.data) {
        const d = result.data;

        // Cập nhật mốc ban đầu nếu là lần đầu tiên poll
        if (lastOrderId === 0 && d.maxOrderId) {
          lastOrderId = d.maxOrderId;
        } else if (d.maxOrderId) {
          lastOrderId = Math.max(lastOrderId, d.maxOrderId);
        }

        if (lastChatId === 0 && d.maxChatId) {
          lastChatId = d.maxChatId;
        } else if (d.maxChatId) {
          lastChatId = Math.max(lastChatId, d.maxChatId);
        }

        // A. Có đơn hàng mới cho Admin
        if (Array.isArray(d.newOrders) && d.newOrders.length > 0) {
          d.newOrders.forEach(order => {
            const orderPayload = { order: order };
            triggerLocalListeners('order:new', orderPayload);
            if (isAdmin) window.playOrderNotificationSound();
          });
        }

        // B. Có tin nhắn mới
        if (Array.isArray(d.newMessages) && d.newMessages.length > 0) {
          d.newMessages.forEach(msg => {
            const chatPayload = {
              sessionId: 'cust-' + msg.customer_id,
              customerId: msg.customer_id,
              message: {
                id: msg.id,
                sender: msg.sender_role,
                senderRole: msg.sender_role,
                message: msg.message,
                time: (msg.created_at || '').substring(11, 16)
              }
            };
            triggerLocalListeners('support:new_message', chatPayload);
          });
        }

        // C. Trạng thái đơn hàng đang theo dõi (trang order-tracking.php)
        if (d.trackedOrder) {
          triggerLocalListeners('order:updated', d.trackedOrder);
        }
      }
    } catch (err) {
      // Im lặng để không làm phiền console khi mất mạng tạm thời
    } finally {
      isPolling = false;
    }
  }

  // Khởi chạy vòng lặp Polling mỗi 2.5s
  setInterval(pollServer, 2500);

  // 6. Global Exports
  window.FASTFOOD_SOCKET = socket;
  window.initFastfoodRealtime = function(user = null) {
    if (window.fastfoodRealtimeReady) return window.fastfoodRealtimeReady;

    window.fastfoodRealtimeReady = new Promise(resolve => {
      setTimeout(() => {
        triggerLocalListeners('connect', {});
        window.dispatchEvent(new CustomEvent('fastfood:socket-connected', { detail: { socket } }));
        resolve(socket);
      }, 30);
    });
    return window.fastfoodRealtimeReady;
  };

  // Khởi động sẵn
  window.initFastfoodRealtime();

})(window);
