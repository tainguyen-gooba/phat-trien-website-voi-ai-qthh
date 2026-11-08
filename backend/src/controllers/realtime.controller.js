const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');

class RealtimeController extends BaseController {
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
    const validMoney = (value) => Number.isSafeInteger(value) && value >= 0;
    const itemSubtotal = Array.isArray(items)
      ? items.reduce((sum, item) => sum + (Number(item?.price) * Number(item?.qty)), 0)
      : NaN;
    if (!/^FF-\d{8}-\d{4}$/.test(orderCode)
      || typeof order?.recipient?.name !== 'string' || !order.recipient.name.trim() || order.recipient.name.length > 150
      || typeof order?.recipient?.phone !== 'string' || !order.recipient.phone.trim() || order.recipient.phone.length > 20
      || typeof order?.recipient?.address !== 'string' || !order.recipient.address.trim() || order.recipient.address.length > 255
      || (order.recipient.notes && (typeof order.recipient.notes !== 'string' || order.recipient.notes.length > 255))
      || !Array.isArray(items) || items.length < 1 || items.length > 50
      || items.some((item) => !item || typeof item.name !== 'string' || !item.name.trim() || item.name.length > 200
        || !Number.isSafeInteger(item.qty) || item.qty < 1 || item.qty > 100
        || !validMoney(item.price))
      || !validMoney(order.subtotal) || !validMoney(order.shippingFee)
      || !validMoney(order.discount) || !validMoney(order.total)
      || !Number.isSafeInteger(itemSubtotal) || order.subtotal !== itemSubtotal
      || order.total !== order.subtotal + order.shippingFee - order.discount) {
      return this.sendError(res, 'Thông tin đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }

    try {
      const orderData = {
        ...order,
        id: orderCode,
        status: 'pending',
        statusStep: 1,
        createdAt: new Date().toISOString(),
      };
      await pool.execute(
        'INSERT INTO realtime_orders (order_code, user_id, order_data, status) VALUES (?, ?, ?, ?)',
        [orderCode, req.user.id, JSON.stringify(orderData), 'pending']
      );
      req.app.get('io')?.to('admins').emit('order:new', {
        order: { ...orderData, statusText: 'Chờ xác nhận' },
        customerId: String(req.user.id),
      });
      return this.sendSuccess(res, 'Đặt hàng thành công.', orderData, HTTP_STATUS.CREATED);
    } catch (error) {
      if (error.code === 'ER_DUP_ENTRY') {
        return this.sendError(res, 'Mã đơn hàng đã tồn tại. Vui lòng đặt lại đơn.', HTTP_STATUS.CONFLICT);
      }
      return next(error);
    }
  }

  async getOrders(req, res, next) {
    try {
      const query = req.user.role === 'admin'
        ? 'SELECT order_code, order_data, status FROM realtime_orders ORDER BY id DESC LIMIT 200'
        : 'SELECT order_code, order_data, status FROM realtime_orders WHERE user_id = ? ORDER BY id DESC LIMIT 100';
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
