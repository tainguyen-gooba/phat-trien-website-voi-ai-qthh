const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');
const { normalizeCustomerProfile } = require('../utils/customer-profile.validation');

const isValidOrderCode = (orderCode) => /^FF\d{4}$/.test(orderCode)
  || /^FF-[A-Z0-9]{6,8}$/.test(orderCode)
  || /^FF-\d{8}-\d{4}$/.test(orderCode);

class RealtimeController extends BaseController {
  async getShippingPolicy(req, res, next) {
    try {
      const [rows] = await pool.execute(
        `SELECT COUNT(*) AS cancellationCount
         FROM realtime_orders
         WHERE user_id = ? AND status = 'cancelled'
           AND JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelledBy')) = 'customer'`,
        [req.user.id]
      );
      const cancellationCount = Number(rows[0]?.cancellationCount) || 0;
      return this.sendSuccess(res, 'Lấy chính sách phí giao hàng thành công.', {
        cancellationCount,
        increasePercent: cancellationCount * 3,
      }, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  orderFromRow(row) {
    const data = typeof row.order_data === 'string' ? JSON.parse(row.order_data) : row.order_data;
    return {
      ...data,
      id: row.order_code,
      status: row.status,
      statusStep: ({
        pending: 1,
        preparing: 3,
        delivering: 4,
        delivered: 5,
        cancelled: 1,
      })[row.status],
      statusText: ({
        pending: 'Chờ xác nhận',
        preparing: 'Đang chuẩn bị',
        delivering: 'Đang giao',
        delivered: 'Đã giao',
        cancelled: 'Đã hủy',
      })[row.status],
    };
  }

  async createOrder(req, res, next) {
    if (req.user?.role === 'admin') {
      return this.sendError(res, 'Tài khoản Quản trị viên (Admin) chỉ dùng để quản lý hệ thống, không có quyền đặt hàng hoặc thanh toán.', HTTP_STATUS.FORBIDDEN);
    }

    const order = req.body?.order;
    const orderCode = typeof order?.id === 'string' ? order.id : '';
    const items = order?.items;
    const recipient = normalizeCustomerProfile(order?.recipient);
    const validOrderCode = isValidOrderCode(orderCode);
    const validMoney = (value) => Number.isSafeInteger(value) && value >= 0;
    const itemSubtotal = Array.isArray(items)
      ? items.reduce((sum, item) => sum + (Number(item?.price) * Number(item?.qty)), 0)
      : NaN;
    if (!validOrderCode || !recipient.valid
      || (order.recipient.notes && (typeof order.recipient.notes !== 'string' || order.recipient.notes.length > 255))
      || !Array.isArray(items) || items.length < 1 || items.length > 50
      || items.some((item) => !item || typeof item.name !== 'string' || !item.name.trim() || item.name.length > 200
        || !Number.isSafeInteger(item.qty) || item.qty < 1 || item.qty > 100
        || !validMoney(item.price))
      || !validMoney(order.subtotal) || !validMoney(order.shippingFee)
      || !validMoney(order.discount) || !validMoney(order.total)
      || !Number.isSafeInteger(itemSubtotal) || order.subtotal !== itemSubtotal
      || order.discount > order.subtotal) {
      return this.sendError(res, 'Thông tin đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }

    let connection;
    let transactionStarted = false;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();
      transactionStarted = true;
      await connection.execute('SELECT id FROM users WHERE id = ? FOR UPDATE', [req.user.id]);
      const [cancellations] = await connection.execute(
        `SELECT COUNT(*) AS cancellationCount
         FROM realtime_orders
         WHERE user_id = ? AND status = 'cancelled'
           AND JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelledBy')) = 'customer'`,
        [req.user.id]
      );
      const cancellationCount = Number(cancellations[0]?.cancellationCount) || 0;
      const baseShippingFee = order.subtotal > 0 && order.subtotal < 100000 ? 18000 : 0;
      const shippingFee = Math.round(baseShippingFee * (1 + cancellationCount * 0.03));
      const orderData = {
        ...order,
        id: orderCode,
        recipient: {
          ...order.recipient,
          name: recipient.fullName,
          phone: recipient.phone,
          address: recipient.address,
        },
        shippingFee,
        total: order.subtotal + shippingFee - order.discount,
        cancellationCount,
        shippingIncreasePercent: cancellationCount * 3,
        status: 'pending',
        statusStep: 1,
        statusText: 'Chờ xác nhận',
        createdAt: new Date().toISOString(),
      };
      await connection.execute(
        'INSERT INTO realtime_orders (order_code, user_id, order_data, status) VALUES (?, ?, ?, ?)',
        [orderCode, req.user.id, JSON.stringify(orderData), 'pending']
      );
      await connection.commit();
      transactionStarted = false;
      req.app.get('io')?.to('admins').emit('order:new', {
        order: { ...orderData, statusText: 'Chờ xác nhận' },
        customerId: String(req.user.id),
      });
      return this.sendSuccess(res, 'Đặt hàng thành công.', orderData, HTTP_STATUS.CREATED);
    } catch (error) {
      if (connection && transactionStarted) await connection.rollback();
      if (error.code === 'ER_DUP_ENTRY') {
        return this.sendError(res, 'Mã đơn hàng đã tồn tại. Vui lòng đặt lại đơn.', HTTP_STATUS.CONFLICT);
      }
      return next(error);
    } finally {
      connection?.release();
    }
  }

  async cancelOrder(req, res, next) {
    const orderCode = typeof req.params.orderCode === 'string' ? req.params.orderCode.trim() : '';
    if (!isValidOrderCode(orderCode)) {
      return this.sendError(res, 'Mã đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }

    let connection;
    let transactionStarted = false;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();
      transactionStarted = true;
      await connection.execute('SELECT id FROM users WHERE id = ? FOR UPDATE', [req.user.id]);
      const [orders] = await connection.execute(
        `SELECT id, order_code, user_id, order_data, status
         FROM realtime_orders
         WHERE order_code = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE`,
        [orderCode, req.user.id]
      );
      const order = orders[0];
      if (!order) {
        await connection.rollback();
        transactionStarted = false;
        return this.sendError(res, 'Không tìm thấy đơn hàng của bạn.', HTTP_STATUS.NOT_FOUND);
      }
      if (!['pending', 'preparing'].includes(order.status)) {
        await connection.rollback();
        transactionStarted = false;
        return this.sendError(res, 'Chỉ có thể hủy đơn khi quán chưa bắt đầu giao hàng.', HTTP_STATUS.CONFLICT);
      }

      const orderData = typeof order.order_data === 'string' ? JSON.parse(order.order_data) : order.order_data;
      const customerName = orderData.recipient?.name || 'Khách hàng';
      await connection.execute(
        `UPDATE realtime_orders
         SET status = 'cancelled',
             order_data = JSON_SET(order_data, '$.cancelledBy', 'customer', '$.cancelledAt', ?)
         WHERE id = ? AND status IN ('pending', 'preparing')`,
        [new Date().toISOString(), order.id]
      );
      await connection.execute(
        `INSERT INTO notifications (user_id, title, content, type, metadata)
         SELECT u.id, 'Khách hàng hủy đơn hàng', ?, 'order', ?
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'admin'`,
        [`${customerName} đã hủy đơn ${orderCode}.`, JSON.stringify({ orderCode, status: 'cancelled' })]
      );
      await connection.commit();
      transactionStarted = false;

      const update = {
        orderCode,
        status: 'cancelled',
        displayStatus: 'Đã hủy',
        statusStep: 1,
      };
      const io = req.app.get('io');
      io?.to('admins').emit('order:updated', update);
      io?.to(`user:${req.user.id}`).emit('order:updated', update);
      io?.to('admins').emit('notification:new', {
        title: 'Khách hàng hủy đơn hàng',
        content: `${customerName} đã hủy đơn ${orderCode}.`,
        type: 'order',
        orderCode,
        customerId: String(req.user.id),
      });

      return this.sendSuccess(res, 'Đã hủy đơn hàng.', {
        ...orderData,
        id: orderCode,
        status: 'cancelled',
        statusText: 'Đã hủy',
        statusStep: 1,
      }, HTTP_STATUS.OK);
    } catch (error) {
      if (connection && transactionStarted) await connection.rollback();
      return next(error);
    } finally {
      connection?.release();
    }
  }

  async getOrders(req, res, next) {
    try {
      const query = req.user.role === 'admin'
        ? 'SELECT order_code, order_data, status FROM realtime_orders ORDER BY id DESC'
        : 'SELECT order_code, order_data, status FROM realtime_orders WHERE user_id = ? ORDER BY id DESC';
      const [rows] = req.user.role === 'admin'
        ? await pool.execute(query)
        : await pool.execute(query, [req.user.id]);
      return this.sendSuccess(
        res,
        'Lấy danh sách đơn hàng thành công.',
        rows.map((row) => this.orderFromRow(row)),
        HTTP_STATUS.OK
      );
    } catch (error) {
      return next(error);
    }
  }

  async getWebsiteContent(req, res, next) {
    try {
      const [rows] = await pool.execute(
        'SELECT collection_name, content, updated_at FROM website_content'
      );
      const content = Object.fromEntries(rows.map((row) => [
        row.collection_name,
        typeof row.content === 'string' ? JSON.parse(row.content) : row.content,
      ]));
      return this.sendSuccess(res, 'Lấy nội dung website thành công', content, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  async getChatHistory(req, res, next) {
    try {
      const customerId = Number(req.params.customerId);
      if (!Number.isSafeInteger(customerId) || customerId < 1) {
        return this.sendError(res, 'Tài khoản chat không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }
      if (req.user.role !== 'admin' && String(req.user.id) !== String(customerId)) {
        return this.sendError(res, 'Bạn không có quyền xem cuộc trò chuyện này.', HTTP_STATUS.FORBIDDEN);
      }

      const [rows] = await pool.execute(
        `SELECT id, customer_id AS customerId, sender_id AS senderId, sender_role AS senderRole,
                message, created_at AS createdAt
         FROM chat_messages
         WHERE customer_id = ?
         ORDER BY id DESC
         LIMIT 100`,
        [customerId]
      );
      return this.sendSuccess(res, 'Lấy tin nhắn thành công', rows.reverse(), HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }
}

module.exports = new RealtimeController();
