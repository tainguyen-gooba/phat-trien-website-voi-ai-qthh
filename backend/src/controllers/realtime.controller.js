const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');
const { normalizeCustomerProfile } = require('../utils/customer-profile.validation');
const aiChatService = require('../services/ai-chat.service');

const isValidOrderCode = (orderCode) => {
  const clean = typeof orderCode === 'string' ? orderCode.replace(/^#/, '').trim() : '';
  return /^FF\d{4}$/.test(clean)
    || /^FF-[A-Z0-9]{6,8}$/.test(clean)
    || /^FF-\d{8}-\d{4}$/.test(clean);
};
const STATIC_COUPONS = {
  FASTFOOD20: { discount: 20000, minOrder: 60000 },
  FREESHIP: { discount: 18000, minOrder: 100000 },
  GIAM50K: { discount: 50000, minOrder: 200000 },
};

function invalidOrderPricing(message) {
  const error = new Error(message);
  error.code = 'INVALID_ORDER_PRICING';
  return error;
}

class RealtimeController extends BaseController {
  async calculateTrustedItems(connection, items) {
    const [contentRows] = await connection.execute(
      'SELECT content FROM website_content WHERE collection_name = ? LIMIT 1',
      ['products']
    );
    const publishedProducts = contentRows[0]
      ? (typeof contentRows[0].content === 'string' ? JSON.parse(contentRows[0].content) : contentRows[0].content)
      : [];
    const [databaseProducts] = await connection.execute(
      `SELECT id, name, base_price AS basePrice, is_available AS isAvailable
       FROM products WHERE is_available = 1`
    );
    const normalizeProductId = (id) => String(id || '')
      .replace(/^live-product-/, '')
      .replace(/^(prod|prd)-/i, '');

    let subtotal = 0;
    const trustedItems = items.map((item) => {
      const productId = String(item.productId || '');
      const normalizedId = normalizeProductId(productId);
      const publishedProduct = publishedProducts.find((product) => (
        normalizeProductId(product.id) === normalizedId
      ));
      const databaseProduct = publishedProduct ? null : databaseProducts.find(
        (product) => String(product.id) === normalizedId
      );
      const product = publishedProduct || databaseProduct;
      const productStatus = String(product?.status || '').toLocaleLowerCase('vi');
      if (!product || /tạm hết|ẩn|hidden|inactive|unavailable/.test(productStatus)) {
        throw invalidOrderPricing(`Không thể xác minh giá hoặc tình trạng món ${item.name || ''}.`);
      }

      const basePrice = Number(publishedProduct ? product.price : product.basePrice);
      const toppings = Array.isArray(item.selectedToppings)
        ? item.selectedToppings
        : Array.isArray(item.toppings) ? item.toppings : [];
      const availableToppings = Array.isArray(product.toppings) ? product.toppings : [];
      if (!Number.isSafeInteger(basePrice) || basePrice < 0
        || !Number.isSafeInteger(item.basePrice) || item.basePrice !== basePrice) {
        throw invalidOrderPricing(`Giá món ${product.name} đã thay đổi. Vui lòng tải lại giỏ hàng.`);
      }

      let toppingTotal = 0;
      const trustedToppings = toppings.map((topping) => {
        const validTopping = availableToppings.find((option) => (
          option.name === topping?.name && Number(option.price) === Number(topping?.price)
        ));
        if (!validTopping || !Number.isSafeInteger(Number(validTopping.price))
          || Number(validTopping.price) < 0) {
          throw invalidOrderPricing(`Không thể xác minh lựa chọn thêm cho món ${product.name}.`);
        }
        toppingTotal += Number(validTopping.price);
        return { name: validTopping.name, price: Number(validTopping.price) };
      });

      const price = basePrice + toppingTotal;
      if (!Number.isSafeInteger(price) || item.price !== price) {
        throw invalidOrderPricing(`Giá giỏ hàng của món ${product.name} không còn khớp.`);
      }
      subtotal += price * item.qty;
      if (!Number.isSafeInteger(subtotal)) {
        throw invalidOrderPricing('Tổng tiền đơn hàng vượt quá giới hạn hợp lệ.');
      }
      return {
        ...item,
        productId: String(product.id),
        name: product.name,
        basePrice,
        price,
        selectedToppings: trustedToppings,
        toppings: trustedToppings,
      };
    });

    return { subtotal, items: trustedItems };
  }

  async validateDiscount(connection, couponCode, userId, subtotal, requestedDiscount) {
    const code = String(couponCode || '').trim().toUpperCase();
    if (!code) {
      if (requestedDiscount !== 0) {
        throw invalidOrderPricing('Mã giảm giá không hợp lệ. Hãy áp dụng lại mã trước khi đặt hàng.');
      }
      return { discount: 0, couponId: null };
    }

    const [rows] = await connection.execute(
      `SELECT id, discount_type AS discountType, discount_value AS discountValue,
              min_order_value AS minOrderValue, max_discount_amount AS maxDiscountAmount,
              is_active AS isActive, start_date AS startDate, end_date AS endDate,
              usage_limit AS usageLimit, usage_count AS usageCount
       FROM coupons
       WHERE code = ? AND (user_id IS NULL OR user_id = ?)
       LIMIT 1 FOR UPDATE`,
      [code, userId]
    );
    const coupon = rows[0];
    const staticCoupon = STATIC_COUPONS[code];
    if (coupon && (Number(coupon.isActive) !== 1
      || new Date(coupon.startDate).getTime() > Date.now()
      || new Date(coupon.endDate).getTime() < Date.now()
      || (coupon.usageLimit !== null && Number(coupon.usageCount) >= Number(coupon.usageLimit)))) {
      throw invalidOrderPricing('Mã giảm giá không hợp lệ hoặc đã hết hạn.');
    }
    if (!coupon && !staticCoupon) {
      throw invalidOrderPricing('Mã giảm giá không hợp lệ hoặc đã hết hạn.');
    }

    const minimumOrder = Number(coupon?.minOrderValue ?? staticCoupon?.minOrder ?? 0);
    if (subtotal < minimumOrder) {
      throw invalidOrderPricing('Đơn hàng chưa đạt giá trị tối thiểu của mã giảm giá.');
    }
    let discount = staticCoupon?.discount;
    if (coupon) {
      discount = coupon.discountType === 'percentage'
        ? Math.floor(subtotal * Number(coupon.discountValue) / 100)
        : Math.floor(Number(coupon.discountValue));
      if (coupon.maxDiscountAmount !== null && coupon.maxDiscountAmount !== undefined) {
        discount = Math.min(discount, Math.floor(Number(coupon.maxDiscountAmount)));
      }
    }
    discount = Math.min(Math.max(0, discount), subtotal);
    if (requestedDiscount !== discount) {
      throw invalidOrderPricing('Giá trị giảm giá không khớp với mã ưu đãi.');
    }
    return { discount, couponId: coupon?.id || null };
  }

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
    const paymentMethod = String(data.paymentMethod || row.paymentMethod || '').toLowerCase();
    const expiresAt = row.paymentExpiresAt || data.paymentExpiresAt || null;
    const retryUntil = row.paymentRetryUntil || data.paymentRetryUntil || null;
    let paymentStatus = paymentMethod === 'cod'
      ? String(data.paymentStatus || 'pending').toLowerCase()
      : String(row.paymentStatus || data.paymentStatus || 'pending').toLowerCase();
    const expiresAtTime = expiresAt ? new Date(expiresAt).getTime() : null;
    if (paymentStatus === 'pending' && expiresAtTime && expiresAtTime <= Date.now()) {
      paymentStatus = 'failed';
    }
    const status = row.status === 'awaiting_payment' && paymentStatus === 'failed'
      ? 'payment_failed'
      : row.status;
    const retryWindowOpen = !retryUntil || new Date(retryUntil).getTime() > Date.now();
    const canRetryPayment = paymentMethod !== 'cod' && paymentStatus !== 'completed'
      && ['awaiting_payment', 'payment_failed'].includes(status) && retryWindowOpen;
    return {
      ...data,
      id: row.order_code,
      paymentMethod: paymentMethod || data.paymentMethod,
      paymentStatus,
      paymentExpiresAt: expiresAt,
      paymentRetryUntil: retryUntil,
      canRetryPayment,
      canSwitchToCod: paymentMethod !== 'cod' && paymentStatus !== 'completed'
        && ['awaiting_payment', 'payment_failed'].includes(status) && retryWindowOpen,
      status,
      statusStep: ({
        awaiting_payment: 1,
        payment_failed: 1,
        pending: 1,
        preparing: 3,
        delivering: 4,
        delivered: 5,
        cancelled: 1,
      })[status],
      statusText: ({
        awaiting_payment: 'Chờ thanh toán',
        payment_failed: 'Thanh toán không thành công',
        pending: 'Chờ xác nhận',
        preparing: 'Đang chuẩn bị',
        delivering: 'Đang giao',
        delivered: 'Đã giao',
        cancelled: 'Đã hủy',
      })[status],
    };
  }

  async createOrder(req, res, next) {
    if (['admin', 'staff'].includes(req.user?.role)) {
      return this.sendError(res, 'Tài khoản Quản trị / Nhân viên chỉ dùng để quản lý hệ thống, không có quyền đặt hàng hoặc thanh toán.', HTTP_STATUS.FORBIDDEN);
    }

    const order = req.body?.order;
    const orderCode = typeof order?.id === 'string' ? order.id : '';
    const requestedPaymentMethod = String(order?.paymentMethod || '').toLowerCase();
    const paymentMethod = requestedPaymentMethod === 'vietqr' ? 'banking' : requestedPaymentMethod;
    const items = order?.items;
    const recipient = normalizeCustomerProfile(order?.recipient);
    const validOrderCode = isValidOrderCode(orderCode);
    const validMoney = (value) => Number.isSafeInteger(value) && value >= 0;
    const itemSubtotal = Array.isArray(items)
      ? items.reduce((sum, item) => sum + (Number(item?.price) * Number(item?.qty)), 0)
      : NaN;
    if (!validOrderCode || !recipient.valid
      || !['cod', 'banking', 'momo'].includes(paymentMethod)
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
      let orderItems = items;
      let orderSubtotal = order.subtotal;
      let discountAmount = order.discount;
      let couponId = null;
      if (paymentMethod !== 'cod') {
        if (items.some((item) => typeof item.productId !== 'string' || !Number.isSafeInteger(item.basePrice))) {
          throw invalidOrderPricing('Không thể xác minh giá món hàng. Vui lòng tải lại giỏ hàng.');
        }
        const trusted = await this.calculateTrustedItems(connection, items);
        if (trusted.subtotal !== order.subtotal) {
          throw invalidOrderPricing('Tổng tiền giỏ hàng đã thay đổi. Vui lòng tải lại trước khi thanh toán.');
        }
        const discount = await this.validateDiscount(
          connection,
          order.couponCode,
          req.user.id,
          trusted.subtotal,
          order.discount
        );
        orderItems = trusted.items;
        orderSubtotal = trusted.subtotal;
        discountAmount = discount.discount;
        couponId = discount.couponId;
      }
      const [cancellations] = await connection.execute(
        `SELECT COUNT(*) AS cancellationCount
         FROM realtime_orders
         WHERE user_id = ? AND status = 'cancelled'
           AND JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelledBy')) = 'customer'`,
        [req.user.id]
      );
      const cancellationCount = Number(cancellations[0]?.cancellationCount) || 0;
      const baseShippingFee = orderSubtotal > 0 && orderSubtotal < 100000 ? 18000 : 0;
      const shippingFee = Math.round(baseShippingFee * (1 + cancellationCount * 0.03));
      const orderData = {
        ...order,
        id: orderCode,
        items: orderItems,
        paymentMethod: paymentMethod.toUpperCase(),
        paymentStatus: 'pending',
        recipient: {
          ...order.recipient,
          name: recipient.fullName,
          phone: recipient.phone,
          address: recipient.address,
        },
        shippingFee,
        subtotal: orderSubtotal,
        discount: discountAmount,
        total: orderSubtotal + shippingFee - discountAmount,
        status: paymentMethod === 'cod' ? 'pending' : 'awaiting_payment',
        statusText: paymentMethod === 'cod' ? 'Chờ xác nhận' : 'Chờ thanh toán',
        cancellationCount,
        shippingIncreasePercent: cancellationCount * 3,
        statusStep: 1,
        createdAt: new Date().toISOString(),
      };
      await connection.execute(
        'INSERT INTO realtime_orders (order_code, user_id, order_data, status) VALUES (?, ?, ?, ?)',
        [orderCode, req.user.id, JSON.stringify(orderData), orderData.status]
      );
      if (couponId) {
        await connection.execute('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?', [couponId]);
      }

      const customerName = recipient.fullName || 'Khách hàng';
      const itemsSummary = Array.isArray(orderItems)
        ? orderItems.map((item) => `${item.qty}x ${item.name}`).slice(0, 3).join(', ') + (orderItems.length > 3 ? '...' : '')
        : '';
      const notifContent = `Khách hàng ${customerName} vừa đặt đơn #${orderCode} (${Number(orderData.total).toLocaleString('vi-VN')}đ) - ${itemsSummary}`;

      const [notifResult] = await connection.execute(
        `INSERT INTO notifications (user_id, title, content, type, metadata)
         SELECT u.id, 'Đơn hàng mới', ?, 'order', ?
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'admin'`,
        [
          notifContent,
          JSON.stringify({
            orderCode,
            customerName,
            phone: recipient.phone,
            total: orderData.total,
            itemsCount: orderItems.length,
            status: 'pending',
            createdAt: orderData.createdAt
          })
        ]
      );

      await connection.commit();
      transactionStarted = false;

      const notifPayload = {
        id: notifResult.insertId || Date.now(),
        title: 'Khách hàng mới đặt đơn! 🛍️',
        content: notifContent,
        type: 'order',
        order: { ...orderData, statusText: 'Chờ xác nhận' },
        orderCode,
        customerName,
        total: orderData.total,
        createdAt: orderData.createdAt,
        isRead: false
      };

      req.app.get('io')?.to('admins').emit('order:new', {
        order: orderData,
        customerId: String(req.user.id),
      });
      req.app.get('io')?.to('admins').emit('notification:new', notifPayload);

      return this.sendSuccess(res, 'Đặt hàng thành công.', orderData, HTTP_STATUS.CREATED);
    } catch (error) {
      if (connection && transactionStarted) await connection.rollback();
      if (error.code === 'INVALID_ORDER_PRICING') {
        return this.sendError(res, error.message, HTTP_STATUS.BAD_REQUEST);
      }
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
      if (!['awaiting_payment', 'payment_failed', 'pending', 'preparing'].includes(order.status)) {
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
         WHERE id = ? AND status IN ('awaiting_payment', 'payment_failed', 'pending', 'preparing')`,
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

  async submitOrderReview(req, res, next) {
    const rawOrderCode = typeof req.params.orderCode === 'string' ? req.params.orderCode.trim() : '';
    const orderCode = rawOrderCode.replace(/^#/, '');
    const target = req.params.target;
    const rating = Number(req.body?.rating);
    const comment = typeof req.body?.comment === 'string' ? req.body.comment.trim() : '';
    if (!isValidOrderCode(orderCode) || !['driver', 'restaurant'].includes(target)
      || !Number.isInteger(rating) || rating < 1 || rating > 5
      || comment.length > 1000) {
      return this.sendError(res, 'Thông tin đánh giá không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }

    let connection;
    let transactionStarted = false;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();
      transactionStarted = true;
      const [rows] = await connection.execute(
        `SELECT order_code, order_data, status
         FROM realtime_orders
         WHERE (order_code = ? OR order_code = ?) AND user_id = ?
         LIMIT 1
         FOR UPDATE`,
        [orderCode, `#${orderCode}`, req.user.id]
      );
      const row = rows[0];
      if (!row) {
        await connection.rollback();
        transactionStarted = false;
        return this.sendError(res, 'Không tìm thấy đơn hàng của bạn.', HTTP_STATUS.NOT_FOUND);
      }
      if (row.status !== 'delivered') {
        await connection.rollback();
        transactionStarted = false;
        return this.sendError(res, 'Chỉ có thể đánh giá đơn hàng đã giao thành công.', HTTP_STATUS.CONFLICT);
      }

      const orderData = typeof row.order_data === 'string' ? JSON.parse(row.order_data) : row.order_data;
      const reviewKey = `${target}Review`;
      if (orderData[reviewKey]) {
        await connection.rollback();
        transactionStarted = false;
        return this.sendError(res, 'Bạn đã gửi đánh giá này rồi.', HTTP_STATUS.CONFLICT);
      }
      orderData[reviewKey] = {
        rating,
        comment,
        submittedAt: new Date().toISOString(),
      };
      await connection.execute(
        'UPDATE realtime_orders SET order_data = ? WHERE (order_code = ? OR order_code = ?) AND user_id = ?',
        [JSON.stringify(orderData), orderCode, `#${orderCode}`, req.user.id]
      );
      const cleanOrderCode = String(row.order_code).replace(/^#/, '');
      const review = {
        orderCode: cleanOrderCode,
        customerId: String(req.user.id),
        customerName: orderData.recipient?.name || req.user.fullName || 'Khách hàng',
        restaurantName: orderData.restaurantName || 'FASTFOOD Partner',
        target,
        rating,
        comment,
        submittedAt: orderData[reviewKey].submittedAt,
      };
      const title = target === 'driver' ? 'Đánh giá tài xế mới' : 'Đánh giá quán ăn mới';
      const content = `${review.customerName} đánh giá ${target === 'driver' ? 'tài xế' : review.restaurantName}: ${rating}/5 sao${comment ? ` — ${comment}` : ''}`;
      const [notificationResult] = await connection.execute(
        `INSERT INTO notifications (user_id, title, content, type, metadata)
         SELECT u.id, ?, ?, 'system', ?
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'admin'`,
        [title, content, JSON.stringify({ type: 'review', orderCode: cleanOrderCode, target, rating })]
      );
      await connection.commit();
      transactionStarted = false;
      const io = req.app.get('io');
      io?.to('admins').emit('notification:new', {
        id: notificationResult.insertId || null,
        title,
        content,
        type: 'review',
        orderCode: cleanOrderCode,
        target,
        rating,
      });
      io?.to('admins').emit('review:new', review);
      return this.sendSuccess(res, 'Đã lưu đánh giá thành công.', {
        ...orderData,
        id: row.order_code,
        status: row.status,
        statusText: 'Đã giao',
        statusStep: 5,
      }, HTTP_STATUS.OK);
    } catch (error) {
      if (connection && transactionStarted) await connection.rollback();
      return next(error);
    } finally {
      connection?.release();
    }
  }

  async getOrderReviews(req, res, next) {
    try {
      const [rows] = await pool.execute(
        `SELECT o.order_code AS orderCode, o.order_data AS orderData, o.created_at AS createdAt,
                u.id AS customerId, u.full_name AS customerName
         FROM realtime_orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE (o.status = 'delivered' OR JSON_CONTAINS_PATH(o.order_data, 'one', '$.driverReview') OR JSON_CONTAINS_PATH(o.order_data, 'one', '$.restaurantReview'))
           AND (
             JSON_CONTAINS_PATH(o.order_data, 'one', '$.driverReview')
             OR JSON_CONTAINS_PATH(o.order_data, 'one', '$.restaurantReview')
           )
         ORDER BY o.updated_at DESC, o.id DESC
         LIMIT 200`
      );
      const reviews = rows.flatMap((row) => {
        const orderData = typeof row.orderData === 'string' ? JSON.parse(row.orderData) : row.orderData;
        const cleanOrderCode = String(row.orderCode).replace(/^#/, '');
        return ['driver', 'restaurant'].flatMap((target) => {
          const review = orderData[`${target}Review`];
          return review ? [{
            orderCode: cleanOrderCode,
            customerId: String(row.customerId || orderData.userId || ''),
            customerName: row.customerName || orderData.recipient?.name || 'Khách hàng',
            restaurantName: orderData.restaurantName || 'FASTFOOD Partner',
            target,
            rating: Number(review.rating),
            comment: review.comment || '',
            submittedAt: review.submittedAt || row.createdAt,
          }] : [];
        });
      });
      return this.sendSuccess(res, 'Lấy đánh giá khách hàng thành công.', reviews, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  async getOrders(req, res, next) {
    try {
<<<<<<< HEAD
      const isAdminOrStaff = ['admin', 'staff'].includes(req.user.role);
      const query = isAdminOrStaff
        ? 'SELECT order_code, order_data, status FROM realtime_orders ORDER BY id DESC'
        : 'SELECT order_code, order_data, status FROM realtime_orders WHERE user_id = ? ORDER BY id DESC';
      const [rows] = isAdminOrStaff
=======
      const query = req.user.role === 'admin'
        ? `SELECT o.order_code, o.order_data, o.status,
                  p.payment_method AS paymentMethod, p.status AS paymentStatus,
                  p.expires_at AS paymentExpiresAt, p.retry_until AS paymentRetryUntil
           FROM realtime_orders o
           LEFT JOIN realtime_payments p ON p.order_code = o.order_code
           ORDER BY o.id DESC`
        : `SELECT o.order_code, o.order_data, o.status,
                  p.payment_method AS paymentMethod, p.status AS paymentStatus,
                  p.expires_at AS paymentExpiresAt, p.retry_until AS paymentRetryUntil
           FROM realtime_orders o
           LEFT JOIN realtime_payments p ON p.order_code = o.order_code
           WHERE o.user_id = ? ORDER BY o.id DESC`;
      const [rows] = req.user.role === 'admin'
>>>>>>> c13eb7988e43f1e98bacdcb49b7f0221c0b7ad48
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
      if (!['admin', 'staff'].includes(req.user.role) && String(req.user.id) !== String(customerId)) {
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

  async getChatConversations(req, res, next) {
    try {
      const [rows] = await pool.execute(
        `SELECT c.customer_id AS customerId, u.full_name AS customerName, u.email,
                c.message AS lastMessage, c.sender_role AS lastSenderRole,
                c.created_at AS lastMessageAt, a.paused_until AS aiPausedUntil
         FROM chat_messages c
         INNER JOIN (
           SELECT customer_id, MAX(id) AS last_message_id
           FROM chat_messages
           GROUP BY customer_id
         ) latest ON latest.last_message_id = c.id
         INNER JOIN users u ON u.id = c.customer_id
         INNER JOIN roles r ON r.id = u.role_id AND r.name = 'customer'
         LEFT JOIN chat_automation a ON a.customer_id = c.customer_id
         ORDER BY c.id DESC`
      );
      return this.sendSuccess(res, 'Lấy danh sách hội thoại thành công.', rows, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * AI Gợi ý câu trả lời thông minh cho Admin khi hỗ trợ khách hàng
   */
  async getAISuggestion(req, res, next) {
    try {
      const { customerMessage, customerName } = req.body || {};
      if (!customerMessage) {
        return this.sendError(res, 'Vui lòng cung cấp nội dung tin nhắn của khách.', HTTP_STATUS.BAD_REQUEST);
      }
      const data = aiChatService.generateReplySuggestion({ customerMessage, customerName });
      data.suggestedReply = data.suggestion;
      return this.sendSuccess(res, 'Gợi ý phản hồi AI thành công.', data, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Trợ lý Chatbot AI tư vấn nghiệp vụ & vận hành dành riêng cho Admin
   */
  async consultAICopilot(req, res, next) {
    try {
      const { prompt } = req.body || {};
      if (!prompt || typeof prompt !== 'string' || !prompt.trim()) {
        return this.sendError(res, 'Vui lòng nhập câu hỏi cho Trợ lý AI.', HTTP_STATUS.BAD_REQUEST);
      }
      const reply = aiChatService.consultAdminAssistant({ prompt });
      return this.sendSuccess(res, 'Phản hồi từ Trợ lý AI Admin.', { reply }, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Bật / Tắt chế độ AI Bot tự động trả lời cho từng khách hàng
   */
  async toggleChatAutomation(req, res, next) {
    try {
      const customerId = Number(req.params.customerId);
      const { enabled, pauseMinutes = 30 } = req.body || {};

      if (!Number.isSafeInteger(customerId) || customerId < 1) {
        return this.sendError(res, 'Mã khách hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }

      await pool.execute(
        `INSERT IGNORE INTO chat_automation (customer_id) VALUES (?)`,
        [customerId]
      );

      if (enabled) {
        // Bật lại AI: xóa thời gian tạm dừng (paused_until = NULL)
        await pool.execute(
          `UPDATE chat_automation SET paused_until = NULL WHERE customer_id = ?`,
          [customerId]
        );
      } else {
        // Tạm dừng AI theo số phút
        const minutes = Math.max(5, Math.min(1440, Number(pauseMinutes) || 30));
        await pool.execute(
          `UPDATE chat_automation
           SET paused_until = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? MINUTE)
           WHERE customer_id = ?`,
          [minutes, customerId]
        );
      }

      return this.sendSuccess(
        res,
        enabled ? 'Đã bật lại Trợ lý AI tự động.' : `Đã tạm dừng Trợ lý AI trong ${pauseMinutes} phút.`,
        { enabled: Boolean(enabled) },
        HTTP_STATUS.OK
      );
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Lấy danh sách thông báo hệ thống của Quản trị viên
   */
  async getNotifications(req, res, next) {
    try {
      const [rows] = await pool.execute(
        `SELECT id, title, content, type, is_read AS isRead, metadata, created_at AS createdAt
         FROM notifications
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 40`,
        [req.user.id]
      );
      const notifications = rows.map((row) => ({
        ...row,
        metadata: typeof row.metadata === 'string' ? JSON.parse(row.metadata) : row.metadata,
        isRead: Boolean(row.isRead),
      }));
      return this.sendSuccess(res, 'Lấy danh sách thông báo thành công.', notifications, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Đánh dấu 1 thông báo là đã đọc
   */
  async markNotificationRead(req, res, next) {
    try {
      const notifId = Number(req.params.id);
      if (!Number.isSafeInteger(notifId) || notifId < 1) {
        return this.sendError(res, 'Mã thông báo không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }
      await pool.execute(
        'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
        [notifId, req.user.id]
      );
      return this.sendSuccess(res, 'Đã đánh dấu thông báo là đã đọc.', null, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Đánh dấu tất cả thông báo là đã đọc
   */
  async markAllNotificationsRead(req, res, next) {
    try {
      await pool.execute(
        'UPDATE notifications SET is_read = 1 WHERE user_id = ?',
        [req.user.id]
      );
      return this.sendSuccess(res, 'Đã đánh dấu tất cả thông báo là đã đọc.', null, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Cập nhật trạng thái đơn hàng (REST API endpoint cho Admin)
   */
  async updateOrderStatus(req, res, next) {
    const cleanOrderCode = typeof req.params.orderCode === 'string'
      ? req.params.orderCode.replace(/^#/, '').trim()
      : '';
    const ORDER_STATUS_MAP = {
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
    const rawStatus = typeof req.body?.status === 'string' ? req.body.status.trim() : '';
    const status = ORDER_STATUS_MAP[rawStatus];
    const displayStatus = DISPLAY_STATUS_MAP[status] || rawStatus;

    if (!cleanOrderCode || !status) {
      return this.sendError(res, 'Mã đơn hoặc trạng thái không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }

    try {
      const [realtimeOrders] = await pool.execute(
        'SELECT id, user_id, status, order_data FROM realtime_orders WHERE order_code = ? LIMIT 1',
        [cleanOrderCode]
      );
      const [orders] = await pool.execute(
        'SELECT id, customer_id, order_status FROM orders WHERE order_code = ? LIMIT 1',
        [cleanOrderCode]
      );
      const order = realtimeOrders[0] || orders[0];
      if (!order) {
        return this.sendError(res, 'Không tìm thấy đơn hàng cần cập nhật.', HTTP_STATUS.NOT_FOUND);
      }

      const currentStatus = realtimeOrders[0]?.status || orders[0]?.order_status;
      if (currentStatus === 'cancelled' && status !== 'cancelled') {
        return this.sendError(res, 'Đơn hàng đã hủy không thể khôi phục trạng thái.', HTTP_STATUS.BAD_REQUEST);
      }

      if (realtimeOrders[0]) {
        let updatedOrderData = realtimeOrders[0].order_data;
        try {
          const parsed = typeof updatedOrderData === 'string' ? JSON.parse(updatedOrderData) : (updatedOrderData || {});
          parsed.status = status;
          parsed.statusText = displayStatus;
          updatedOrderData = JSON.stringify(parsed);
        } catch (_) {}
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

      const io = req.app.get('io');
      const targetUserId = realtimeOrders[0]?.user_id || orders[0]?.customer_id;
      if (targetUserId) {
        const [notification] = await pool.execute(
          `INSERT INTO notifications (user_id, title, content, type, metadata)
           VALUES (?, 'Cập nhật đơn hàng', ?, 'order', ?)`,
          [targetUserId, `Đơn hàng #${cleanOrderCode}: ${displayStatus}`, JSON.stringify({ orderCode: cleanOrderCode, status, displayStatus })]
        );
        io?.to(`user:${targetUserId}`).emit('notification:new', {
          id: notification.insertId,
          title: 'Cập nhật đơn hàng',
          content: `Đơn hàng #${cleanOrderCode}: ${displayStatus}`,
          type: 'order',
          orderCode: cleanOrderCode,
          status,
        });
        io?.to(`user:${targetUserId}`).emit('order:updated', { orderCode: cleanOrderCode, status, displayStatus });
      }

      const update = { orderCode: cleanOrderCode, status, displayStatus };
      io?.to('admins').emit('order:updated', update);

      return this.sendSuccess(res, 'Cập nhật trạng thái đơn hàng thành công.', update, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  /**
   * Cập nhật nội dung website (REST API fallback)
   */
  async publishWebsiteContent(req, res, next) {
    const collection = String(req.params.collection || '').trim();
    const records = req.body?.records;
    if (!['categories', 'products', 'restaurants'].includes(collection) || !Array.isArray(records)) {
      return this.sendError(res, 'Dữ liệu nội dung không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }
    try {
      await pool.execute(
        `INSERT INTO website_content (collection_name, content)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = CURRENT_TIMESTAMP`,
        [collection, JSON.stringify(records)]
      );
      const io = req.app.get('io');
      io?.emit('content:updated', { collection, records, updatedAt: new Date().toISOString() });
      return this.sendSuccess(res, 'Cập nhật nội dung website thành công.', { collection }, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }
}

module.exports = new RealtimeController();
