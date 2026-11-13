const jwt = require('jsonwebtoken');
const { Server } = require('socket.io');
const config = require('./config/app.config');
const { pool } = require('./config/db.config');
const chatController = require('./controllers/chatController');
const logger = require('./utils/logger');

const CONTENT_COLLECTIONS = new Set(['categories', 'products', 'restaurants']);
const ORDER_STATUSES = {
  'Chờ xác nhận': 'pending',
  'Đang chuẩn bị': 'preparing',
  'Đang giao': 'delivering',
  'Đã giao': 'delivered',
  'Đã hủy': 'cancelled',
  pending: 'pending',
  preparing: 'preparing',
  delivering: 'delivering',
  delivered: 'delivered',
  cancelled: 'cancelled',
  placed: 'pending',
  confirmed: 'preparing',
  completed: 'delivered',
};

const DISPLAY_STATUS_MAP = {
  pending: 'Chờ xác nhận',
  preparing: 'Đang chuẩn bị',
  delivering: 'Đang giao',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
};

function buildChatAssistantReply(message) {
  const normalized = message.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').toLowerCase();

  if (/\b(chao|xin chao|hello|hi|alo)\b/.test(normalized)) {
    return 'Chào bạn! Mình là trợ lý FASTFOOD. Bạn cần hỗ trợ về thực đơn, đặt hàng, giao hàng hay khuyến mãi?';
  }
  if (/(don hang|order|theo doi|trang thai|ma don)/.test(normalized)) {
    return 'Bạn có thể xem trạng thái đơn trong mục “Đơn hàng” sau khi đăng nhập. Nếu cần hỗ trợ thêm, hãy gửi mã đơn để nhân viên kiểm tra nhé.';
  }
  if (/(giao hang|phi ship|van chuyen|thoi gian giao)/.test(normalized)) {
    return 'Phí và thời gian giao hàng phụ thuộc vào địa chỉ nhận và quán bạn chọn. Bạn hãy nhập địa chỉ khi đặt món để xem thông tin chính xác nhé.';
  }
  if (/(mon an|thuc don|menu|gia|san pham|mon nao)/.test(normalized)) {
    return 'Bạn có thể xem thực đơn và giá món tại mục “Món ăn”. Hãy cho mình biết bạn đang tìm món hoặc quán nào để mình hướng dẫn cụ thể hơn nhé.';
  }
  if (/(khuyen mai|ma giam|voucher|giam gia)/.test(normalized)) {
    return 'Các mã giảm giá hiện có sẽ được hiển thị ở bước thanh toán. Bạn nhớ kiểm tra điều kiện áp dụng và thời hạn của mã nhé.';
  }
  if (/(nhan vien|admin|nguoi that|gap nguoi)/.test(normalized)) {
    return 'Mình đã ghi nhận yêu cầu. Nhân viên FASTFOOD sẽ tiếp tục hỗ trợ bạn trong cuộc trò chuyện này.';
  }
  return 'Mình đã nhận được tin nhắn. Bạn có thể cho mình biết thêm bạn cần hỗ trợ về món ăn, đơn hàng hay giao hàng không?';
}

function createRealtimeServer(httpServer) {
  const io = new Server(httpServer, {
    cors: {
      origin: config.cors.origin === '*' ? true : config.cors.origin.split(','),
      credentials: true,
    },
  });

  io.use((socket, next) => {
    const token = socket.handshake.auth?.token;
    if (!token) {
      socket.data.user = null;
      return next();
    }

    try {
      socket.data.user = jwt.verify(token, config.jwt.secret);
      return next();
    } catch (error) {
      return next(new Error('Phiên đăng nhập realtime không hợp lệ.'));
    }
  });

  chatController.setIo(io);

  io.on('connection', (socket) => {
    const user = socket.data.user;
    socket.join('public');
    if (user) {
      socket.join(`user:${user.id}`);
      if (user.role === 'admin') {
        socket.join('admins');
        io.emit('support:status', chatController.getGlobalSupportState(io));
      }
    }

    socket.on('support:join', (payload) => {
      const sessionId = typeof payload?.sessionId === 'string' ? payload.sessionId.trim() : '';
      if (sessionId && /^[a-zA-Z0-9_-]{4,80}$/.test(sessionId)) {
        socket.join(`support:${sessionId}`);
        socket.emit('support:status', chatController.getGlobalSupportState(io));
      }
    });

    socket.on('disconnect', () => {
      if (user?.role === 'admin') {
        setTimeout(() => {
          io.emit('support:status', chatController.getGlobalSupportState(io));
        }, 100);
      }
    });

    socket.on('admin:content:publish', async (payload, acknowledge = () => { }) => {
      if (user?.role !== 'admin') {
        acknowledge({ success: false, message: 'Chỉ admin mới được cập nhật nội dung.' });
        return;
      }

      const { collection, records } = payload || {};
      const isValidValue = (val) => {
        if (val === null || val === undefined) return true;
        const t = typeof val;
        if (t === 'string') return val.length <= 5000;
        if (t === 'number') return Number.isFinite(val);
        if (t === 'boolean') return true;
        if (Array.isArray(val)) {
          return val.length <= 100 && val.every((item) => (
            item && typeof item === 'object'
              ? Object.values(item).every(isValidValue)
              : isValidValue(item)
          ));
        }
        if (t === 'object') {
          return Object.values(val).every(isValidValue);
        }
        return false;
      };
      const invalidRecord = (record) => !record || typeof record !== 'object' || Array.isArray(record)
        || !/^[a-zA-Z0-9_-]{1,80}$/.test(String(record.id || ''))
        || !Object.values(record).every(isValidValue);
      if (!CONTENT_COLLECTIONS.has(collection) || !Array.isArray(records)
        || records.length > 500 || records.some(invalidRecord)) {
        acknowledge({ success: false, message: 'Dữ liệu cập nhật không hợp lệ.' });
        return;
      }

      try {
        const [result] = await pool.execute(
          `INSERT INTO website_content (collection_name, content)
           VALUES (?, ?)
           ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = CURRENT_TIMESTAMP`,
          [collection, JSON.stringify(records)]
        );
        const update = { collection, records, updatedAt: new Date().toISOString() };
        io.emit('content:updated', update);
        acknowledge({ success: true, updateId: result.insertId || null });
      } catch (error) {
        logger.error('Không thể phát hành cập nhật nội dung realtime.', { message: error.message });
        acknowledge({ success: false, message: 'Không thể lưu thay đổi vào MySQL.' });
      }
    });

    socket.on('chat:send', async (payload, acknowledge = () => { }) => {
      if (!user || !['admin', 'customer'].includes(user.role)) {
        acknowledge({ success: false, message: 'Đăng nhập để sử dụng chat hỗ trợ.' });
        return;
      }

      const message = typeof payload?.message === 'string' ? payload.message.trim() : '';
      const customerId = user.role === 'admin' ? Number(payload?.customerId) : Number(user.id);
      if (!message || message.length > 1000 || !Number.isSafeInteger(customerId) || customerId < 1) {
        acknowledge({ success: false, message: 'Nội dung tin nhắn không hợp lệ.' });
        return;
      }

      let connection;
      try {
        if (user.role === 'admin') {
          const [customers] = await pool.execute(
            `SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND r.name = 'customer' LIMIT 1`,
            [customerId]
          );
          if (!customers.length) {
            acknowledge({ success: false, message: 'Không tìm thấy tài khoản khách hàng.' });
            return;
          }
        }

        connection = await pool.getConnection();
        await connection.beginTransaction();
        await connection.execute(
          `INSERT IGNORE INTO chat_automation (customer_id) VALUES (?)`,
          [customerId]
        );
        if (user.role === 'admin') {
          await connection.execute(
            `UPDATE chat_automation
             SET paused_until = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)
             WHERE customer_id = ?`,
            [customerId]
          );
        }
        const [result] = await connection.execute(
          `INSERT INTO chat_messages (customer_id, sender_id, sender_role, message)
           VALUES (?, ?, ?, ?)`,
          [customerId, user.id, user.role, message]
        );
        await connection.commit();
        connection.release();
        connection = null;

        const chatMessage = {
          id: result.insertId,
          customerId: String(customerId),
          senderId: String(user.id),
          senderRole: user.role,
          message,
          createdAt: new Date().toISOString(),
        };
        io.to(`user:${customerId}`).emit('chat:new', chatMessage);
        io.to('admins').emit('chat:new', chatMessage);
        if (user.role === 'customer') {
          io.to('admins').emit('notification:new', {
            title: 'Tin nhắn hỗ trợ mới',
            content: message,
            type: 'chat',
            customerId: String(customerId),
          });
        }

        const recipientId = user.role === 'admin' ? customerId : null;
        if (recipientId) {
          await pool.execute(
            `INSERT INTO notifications (user_id, title, content, type, metadata)
             VALUES (?, 'Tin nhắn hỗ trợ mới', ?, 'system', ?)`,
            [recipientId, message, JSON.stringify({ type: 'chat' })]
          );
          io.to(`user:${recipientId}`).emit('notification:new', {
            title: 'Tin nhắn hỗ trợ mới',
            content: message,
            type: 'chat',
          });
        }

        if (user.role === 'customer') {
          const scheduleAssistantReply = () => {
            setTimeout(async () => {
              let assistantConnection;
              try {
                assistantConnection = await pool.getConnection();
                await assistantConnection.beginTransaction();
                const [automationRows] = await assistantConnection.execute(
                  `SELECT paused_until AS pausedUntil
                   FROM chat_automation
                   WHERE customer_id = ?
                   FOR UPDATE`,
                  [customerId]
                );
                const pausedUntil = automationRows[0]?.pausedUntil;
                if (pausedUntil && new Date(pausedUntil).getTime() > Date.now()) {
                  await assistantConnection.commit();
                  return;
                }

                const reply = buildChatAssistantReply(message);
                const [assistantResult] = await assistantConnection.execute(
                  `INSERT INTO chat_messages (customer_id, sender_id, sender_role, message)
                   VALUES (?, NULL, 'ai', ?)`,
                  [customerId, reply]
                );
                await assistantConnection.commit();
                io.to(`user:${customerId}`).emit('chat:new', {
                  id: assistantResult.insertId,
                  customerId: String(customerId),
                  senderId: null,
                  senderRole: 'ai',
                  message: reply,
                  createdAt: new Date().toISOString(),
                });
                io.to('admins').emit('chat:new', {
                  id: assistantResult.insertId,
                  customerId: String(customerId),
                  senderId: null,
                  senderRole: 'ai',
                  message: reply,
                  createdAt: new Date().toISOString(),
                });
              } catch (error) {
                if (assistantConnection) await assistantConnection.rollback();
                logger.error('Không thể tạo phản hồi tự động cho chat.', { message: error.message });
              } finally {
                if (assistantConnection) assistantConnection.release();
              }
            }, 700);
          };
          scheduleAssistantReply();
        }

        acknowledge({ success: true, message: chatMessage });
      } catch (error) {
        if (connection) {
          await connection.rollback();
          connection.release();
        }
        logger.error('Không thể gửi tin nhắn realtime.', { message: error.message });
        acknowledge({ success: false, message: 'Không thể gửi tin nhắn.' });
      }
    });

    socket.on('admin:order:status', async (payload, acknowledge = () => { }) => {
      if (user?.role !== 'admin') {
        acknowledge({ success: false, message: 'Chỉ admin mới được cập nhật đơn hàng.' });
        return;
      }

      const orderCode = String(payload?.orderNo || payload?.id || payload?.orderCode || '').replace(/^#/, '').trim();
      const rawInput = typeof payload?.status === 'string' ? payload.status.trim() : '';
      const status = ORDER_STATUSES[rawInput];
      const displayStatus = DISPLAY_STATUS_MAP[status] || rawInput;

      if (!orderCode || !status) {
        acknowledge({ success: false, message: 'Mã đơn hoặc trạng thái không hợp lệ.' });
        return;
      }

      try {
        const [realtimeOrders] = await pool.execute(
          'SELECT id, user_id, status, order_data FROM realtime_orders WHERE order_code = ? LIMIT 1',
          [orderCode]
        );
        const [orders] = await pool.execute(
          'SELECT id, customer_id, order_status FROM orders WHERE order_code = ? LIMIT 1',
          [orderCode]
        );
        const order = realtimeOrders[0] || orders[0];
        if (!order) {
          acknowledge({ success: false, message: 'Không tìm thấy đơn hàng cần cập nhật.' });
          return;
        }

        const currentStatus = realtimeOrders[0]?.status || orders[0]?.order_status;
        if (currentStatus === 'cancelled' && status !== 'cancelled') {
          acknowledge({ success: false, message: 'Đơn hàng đã hủy không thể khôi phục trạng thái.' });
          return;
        }

        if (realtimeOrders[0]) {
          let updatedOrderData = realtimeOrders[0].order_data;
          try {
            const parsed = typeof updatedOrderData === 'string' ? JSON.parse(updatedOrderData) : (updatedOrderData || {});
            parsed.status = status;
            parsed.statusText = displayStatus;
            updatedOrderData = JSON.stringify(parsed);
          } catch (_) { }
          await pool.execute(
            'UPDATE realtime_orders SET status = ?, order_data = ? WHERE id = ?',
            [status, updatedOrderData, realtimeOrders[0].id]
          );
        }

        if (orders[0]) {
          const legacyStatusMap = {
            pending: 'placed',
            preparing: 'preparing',
            delivering: 'delivering',
            delivered: 'completed',
            cancelled: 'cancelled',
          };
          const legacyStatus = legacyStatusMap[status] || status;
          await pool.execute(
            `UPDATE orders 
             SET order_status = ?,
                 completed_at = IF(? = 'completed', COALESCE(completed_at, CURRENT_TIMESTAMP), completed_at),
                 cancelled_at = IF(? = 'cancelled', COALESCE(cancelled_at, CURRENT_TIMESTAMP), cancelled_at)
             WHERE id = ?`,
            [legacyStatus, legacyStatus, legacyStatus, orders[0].id]
          );
        }

        const targetUserId = realtimeOrders[0]?.user_id || orders[0]?.customer_id;
        if (targetUserId) {
          const [notification] = await pool.execute(
            `INSERT INTO notifications (user_id, title, content, type, metadata)
             VALUES (?, 'Cập nhật đơn hàng', ?, 'order', ?)`,
            [targetUserId, `Đơn hàng #${orderCode}: ${displayStatus}`, JSON.stringify({ orderCode, status, displayStatus })]
          );
          io.to(`user:${targetUserId}`).emit('notification:new', {
            id: notification.insertId,
            title: 'Cập nhật đơn hàng',
            content: `Đơn hàng #${orderCode}: ${displayStatus}`,
            type: 'order',
            orderCode,
            status,
          });
          io.to(`user:${targetUserId}`).emit('order:updated', { orderCode, status, displayStatus });
        }

        const update = { orderCode, status, displayStatus };
        io.to('admins').emit('order:updated', update);
        acknowledge({ success: true, updated: true });
      } catch (error) {
        logger.error('Không thể cập nhật trạng thái đơn hàng realtime.', { message: error.message });
        acknowledge({ success: false, message: 'Không thể cập nhật trạng thái đơn hàng: ' + error.message });
      }
    });
  });

  return io;
}

module.exports = { createRealtimeServer };
