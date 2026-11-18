/**
 * FASTFOOD Native Realtime Socket Bridge (Socket.IO Mock API)
 * Thay thế Socket.IO Node.js bằng Native BroadcastChannel + HTTP Poller
 */
(function(window) {
  function createRealtimeSocket() {
    if (window.FASTFOOD_SOCKET) return window.FASTFOOD_SOCKET;

    const channel = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('fastfood_realtime') : null;
    const listeners = {};

    const socket = {
      connected: true,
      id: 'native-' + Math.random().toString(36).substring(2, 9),
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
        // 1. Gửi qua BroadcastChannel tới các tab khác
        if (channel) {
          channel.postMessage({ type: event, data: data, timestamp: Date.now() });
        }
        // 2. Kích hoạt CustomEvent trong cùng trang
        window.dispatchEvent(new CustomEvent('fastfood:' + event.replace(/:/g, '-'), { detail: data }));

        if (typeof callback === 'function') {
          setTimeout(() => callback(null, { success: true }), 10);
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

    if (channel) {
      channel.onmessage = function(e) {
        const payload = e.data;
        if (!payload || !payload.type) return;

        const eventName = payload.type;
        const eventData = payload.data !== undefined ? payload.data : payload;

        if (listeners[eventName]) {
          listeners[eventName].forEach(cb => {
            try { cb(eventData); } catch (err) { console.error('[Realtime Listener Error]', err); }
          });
        }
        window.dispatchEvent(new CustomEvent('fastfood:' + eventName.replace(/:/g, '-'), { detail: eventData }));
      };
    }

    window.FASTFOOD_SOCKET = socket;
    return socket;
  }

  window.io = function(origin, opts) {
    const s = createRealtimeSocket();
    setTimeout(() => {
      if (s.connected && listeners['connect']) {
        listeners['connect'].forEach(cb => cb());
      }
    }, 20);
    return s;
  };
})(window);
