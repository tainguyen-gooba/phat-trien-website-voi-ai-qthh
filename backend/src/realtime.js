const jwt = require('jsonwebtoken');
const { Server } = require('socket.io');
const config = require('./config/app.config');
const { pool } = require('./config/db.config');
const logger = require('./utils/logger');

const CONTENT_COLLECTIONS = new Set(['categories', 'products', 'restaurants']);
const ORDER_STATUSES = {
  'Chờ xác nhận': 'pending',
  'Đang chuẩn bị': 'preparing',
  'Đang giao': 'delivering',
  'Đã giao': 'delivered',
  'Đã hủy': 'cancelled',
};

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

  io.on('connection', (socket) => {
    const user = socket.data.user;
    socket.join('public');
    if (user) {
      socket.join(`user:${user.id}`);
      if (user.role === 'admin') socket.join('admins');
    }

    socket.on('admin:content:publish', async (payload, acknowledge = () => {}) => {
      if (user?.role !== 'admin') {
        acknowledge({ success: false, message: 'Chỉ admin mới được cập nhật nội dung.' });
        return;
      }

      const { collection, records } = payload || {};
      const invalidRecord = (record) => !record || typeof record !== 'object' || Array.isArray(record)
        || !/^[a-zA-Z0-9_-]{1,80}$/.test(String(record.id || ''))
        || Object.values(record).some((value) => (
          typeof value === 'string' && value.length > 500
        ) || (typeof value === 'number' && !Number.isFinite(value))
          || (value !== null && !['string', 'number', 'boolean'].includes(typeof value)));
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

    socket.on('chat:send', async (payload, acknowledge = () => {}) => {
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

        const [result] = await pool.execute(
          `INSERT INTO chat_messages (customer_id, sender_id, sender_role, message)
           VALUES (?, ?, ?, ?)`,
          [customerId, user.id, user.role, message]
        );
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

        acknowledge({ success: true, message: chatMessage });
      } catch (error) {
        logger.error('Không thể gửi tin nhắn realtime.', { message: error.message });
        acknowledge({ success: false, message: 'Không thể gửi tin nhắn.' });
      }
    });

    socket.on('admin:order:status', async (payload, acknowledge = () => {}) => {
      if (user?.role !== 'admin') {
        acknowledge({ success: false, message: 'Chỉ admin mới được cập nhật đơn hàng.' });
        return;
      }

      const orderCode = typeof payload?.orderNo === 'string'
        ? payload.orderNo.replace(/^#/, '').trim()
        : '';
      const status = ORDER_STATUSES[payload?.status];
      if (!orderCode || !status) {
        acknowledge({ success: false, message: 'Mã đơn hoặc trạng thái không hợp lệ.' });
        return;
      }

      try {
        const [orders] = await pool.execute(
          'SELECT id, user_id FROM orders WHERE order_code = ? LIMIT 1',
          [orderCode]
        );
        const [realtimeOrders] = await pool.execute(
          'SELECT id, user_id FROM realtime_orders WHERE order_code = ? LIMIT 1',
          [orderCode]
        );
        const order = orders[0] || realtimeOrders[0];
        if (!order) {
          acknowledge({ success: false, message: 'Không tìm thấy đơn hàng cần cập nhật.' });
          return;
        }

        if (orders[0]) {
          await pool.execute(
            `UPDATE orders SET status = ?, delivered_time = IF(? = 'delivered', CURRENT_TIMESTAMP, delivered_time)
             WHERE id = ?`,
            [status, status, orders[0].id]
          );
        }
        if (realtimeOrders[0]) {
          await pool.execute(
            'UPDATE realtime_orders SET status = ? WHERE id = ?',
            [status, realtimeOrders[0].id]
          );
        }
        if (order.user_id) {
          const [notification] = await pool.execute(
            `INSERT INTO notifications (user_id, title, content, type, metadata)
             VALUES (?, 'Cập nhật đơn hàng', ?, 'order', ?)`,
            [order.user_id, `Đơn ${orderCode}: ${payload.status}`, JSON.stringify({ orderCode, status })]
          );
          io.to(`user:${order.user_id}`).emit('notification:new', {
            id: notification.insertId,
            title: 'Cập nhật đơn hàng',
            content: `Đơn ${orderCode}: ${payload.status}`,
            type: 'order',
            orderCode,
            status,
          });
        }

        const update = { orderCode, status, displayStatus: payload.status };
        io.to('admins').emit('order:updated', update);
        io.to(`user:${order.user_id}`).emit('order:updated', update);
        acknowledge({ success: true, updated: true });
      } catch (error) {
        logger.error('Không thể cập nhật trạng thái đơn hàng realtime.', { message: error.message });
        acknowledge({ success: false, message: 'Không thể cập nhật trạng thái đơn hàng.' });
      }
    });
  });

  return io;
}

module.exports = { createRealtimeServer };
