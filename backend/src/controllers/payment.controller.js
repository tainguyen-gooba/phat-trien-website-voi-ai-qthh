const crypto = require('crypto');
const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const config = require('../config/app.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');

const ORDER_CODE_PATTERN = /^FF\d{4}$|^FF-[A-Z0-9]{6,8}$|^FF-\d{8}-\d{4}$/;
const PAYMENT_ATTEMPT_MS = 15 * 60 * 1000;
const PAYMENT_RETRY_WINDOW_MS = 60 * 60 * 1000;

function hmacSha256(value, secret) {
  return crypto.createHmac('sha256', secret).update(value).digest('hex');
}

function vnpaySignData(params) {
  return Object.keys(params)
    .filter((key) => key !== 'vnp_SecureHash' && key !== 'vnp_SecureHashType')
    .sort()
    .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(String(params[key])).replace(/%20/g, '+')}`)
    .join('&');
}

function vnpaySignature(params, secret) {
  return crypto.createHmac('sha512', secret).update(vnpaySignData(params)).digest('hex');
}

function safeEqual(left, right) {
  const leftBuffer = Buffer.from(String(left || ''));
  const rightBuffer = Buffer.from(String(right || ''));
  return leftBuffer.length === rightBuffer.length && crypto.timingSafeEqual(leftBuffer, rightBuffer);
}

function momoCreateSignature(payload, accessKey) {
  return hmacSha256(
    `accessKey=${accessKey}&amount=${payload.amount}&extraData=${payload.extraData}`
      + `&ipnUrl=${payload.ipnUrl}&orderId=${payload.orderId}&orderInfo=${payload.orderInfo}`
      + `&partnerCode=${payload.partnerCode}&redirectUrl=${payload.redirectUrl}`
      + `&requestId=${payload.requestId}&requestType=${payload.requestType}`,
    config.payments.momo.secretKey
  );
}

function momoIpnSignature(payload, accessKey) {
  return hmacSha256(
    `accessKey=${accessKey}&amount=${payload.amount}&extraData=${payload.extraData}`
      + `&message=${payload.message}&orderId=${payload.orderId}&orderInfo=${payload.orderInfo}`
      + `&orderType=${payload.orderType}&partnerCode=${payload.partnerCode}`
      + `&payType=${payload.payType}&requestId=${payload.requestId}`
      + `&responseTime=${payload.responseTime}&resultCode=${payload.resultCode}`
      + `&transId=${payload.transId}`,
    config.payments.momo.secretKey
  );
}

function publicUrl() {
  if (!config.payments.publicUrl) {
    throw new Error('Thiếu PAYMENT_PUBLIC_URL trong backend/.env.');
  }
  const url = new URL(config.payments.publicUrl);
  if ((config.isProduction || config.payments.momo.environment === 'production'
    || config.payments.vnpay.environment === 'production')
    && url.protocol !== 'https:') {
    throw new Error('PAYMENT_PUBLIC_URL phải sử dụng HTTPS trong production.');
  }
  return url.origin;
}

function formatVnpayDate(date) {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date);
  const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
  return `${values.year}${values.month}${values.day}${values.hour}${values.minute}${values.second}`;
}

class PaymentController extends BaseController {
  async preparePayment(req, res, next, method) {
    let connection;
    try {
      const orderCode = String(req.body?.orderCode || '').trim().toUpperCase();
      if (!ORDER_CODE_PATTERN.test(orderCode)) {
        return this.sendError(res, 'Mã đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }

      connection = await pool.getConnection();
      await connection.beginTransaction();
      const [orders] = await connection.execute(
        `SELECT order_code AS orderCode, order_data AS orderData, status
         FROM realtime_orders WHERE order_code = ? AND user_id = ? FOR UPDATE`,
        [orderCode, req.user.id]
      );
      const order = orders[0];
      if (!order) {
        await connection.rollback();
        return this.sendError(res, 'Không tìm thấy đơn hàng của bạn.', HTTP_STATUS.NOT_FOUND);
      }
      const orderData = typeof order.orderData === 'string' ? JSON.parse(order.orderData) : order.orderData;
      if (String(orderData.paymentMethod || '').toLowerCase() !== method) {
        await connection.rollback();
        return this.sendError(res, 'Phương thức thanh toán không khớp với đơn hàng.', HTTP_STATUS.CONFLICT);
      }
      if (!['awaiting_payment', 'payment_failed'].includes(order.status)) {
        await connection.rollback();
        return this.sendError(res, 'Đơn hàng không còn ở trạng thái chờ thanh toán.', HTTP_STATUS.CONFLICT);
      }

      const amount = Number(orderData.total);
      if (!Number.isSafeInteger(amount) || amount < 1) {
        await connection.rollback();
        return this.sendError(res, 'Số tiền đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }

      const [payments] = await connection.execute(
        `SELECT request_id AS requestId, status, payment_url AS paymentUrl, qr_url AS qrUrl,
                expires_at AS expiresAt, retry_until AS retryUntil
         FROM realtime_payments WHERE order_code = ? FOR UPDATE`,
        [orderCode]
      );
      let existing = payments[0];
      if (existing?.status === 'completed') {
        await connection.rollback();
        return this.sendError(res, 'Đơn hàng đã được thanh toán.', HTTP_STATUS.CONFLICT);
      }

      const now = Date.now();
      let retryUntil = existing?.retryUntil ? new Date(existing.retryUntil).getTime() : null;
      const expiresAt = existing?.expiresAt ? new Date(existing.expiresAt).getTime() : null;
      if (existing?.status === 'pending' && (!expiresAt || expiresAt <= now)) {
        await connection.execute(
          `UPDATE realtime_payments
           SET status = 'failed',
               expires_at = COALESCE(expires_at, CURRENT_TIMESTAMP),
               retry_until = COALESCE(retry_until, DATE_ADD(COALESCE(expires_at, CURRENT_TIMESTAMP), INTERVAL 1 HOUR))
           WHERE order_code = ? AND status = 'pending'`,
          [orderCode]
        );
        await connection.execute(
          `UPDATE realtime_orders SET status = 'payment_failed',
             order_data = JSON_SET(order_data, '$.paymentStatus', 'failed',
               '$.status', 'payment_failed', '$.statusText', 'Thanh toán không thành công')
           WHERE order_code = ? AND status = 'awaiting_payment'`,
          [orderCode]
        );
        existing = { ...existing, status: 'failed' };
        retryUntil = retryUntil || (expiresAt
          ? expiresAt + PAYMENT_RETRY_WINDOW_MS
          : now + PAYMENT_RETRY_WINDOW_MS);
      }

      if (retryUntil && now >= retryUntil) {
        await connection.commit();
        return this.sendError(
          res,
          'Đã hết thời gian 1 giờ để thanh toán lại hoặc đổi sang COD.',
          HTTP_STATUS.CONFLICT
        );
      }
      if (existing?.status === 'pending' && (existing.paymentUrl || existing.qrUrl)) {
        await connection.commit();
        return this.sendSuccess(res, 'Đã tạo yêu cầu thanh toán.', {
          orderCode,
          amount,
          paymentUrl: existing.paymentUrl,
          qrUrl: existing.qrUrl,
          expiresAt: existing.expiresAt,
          retryUntil: existing.retryUntil,
        }, HTTP_STATUS.OK);
      }

      const requestId = existing?.status === 'pending' && existing.requestId
        ? existing.requestId
        : crypto.randomUUID();
      const nextExpiresAt = new Date(Math.min(
        now + PAYMENT_ATTEMPT_MS,
        retryUntil || Number.MAX_SAFE_INTEGER
      ));
      const nextRetryUntil = retryUntil ? new Date(retryUntil) : new Date(nextExpiresAt.getTime() + PAYMENT_RETRY_WINDOW_MS);
      await connection.execute(
        `INSERT INTO realtime_payments
           (order_code, payment_method, request_id, amount, status, expires_at, retry_until)
         VALUES (?, ?, ?, ?, 'pending', ?, ?)
         ON DUPLICATE KEY UPDATE payment_method = VALUES(payment_method),
           request_id = VALUES(request_id), amount = VALUES(amount), status = 'pending',
           expires_at = VALUES(expires_at), retry_until = COALESCE(retry_until, VALUES(retry_until)),
           provider_transaction_id = NULL, payment_url = NULL, qr_url = NULL, paid_at = NULL`,
        [orderCode, method, requestId, amount, nextExpiresAt, nextRetryUntil]
      );
      await connection.execute(
        `UPDATE realtime_orders SET status = 'awaiting_payment',
           order_data = JSON_SET(order_data, '$.paymentMethod', ?, '$.paymentStatus', 'pending',
             '$.paymentExpiresAt', ?, '$.paymentRetryUntil', ?, '$.status', 'awaiting_payment',
             '$.statusText', 'Chờ thanh toán')
         WHERE order_code = ?`,
        [method.toUpperCase(), nextExpiresAt.toISOString(), nextRetryUntil.toISOString(), orderCode]
      );
      await connection.commit();
      return {
        orderCode,
        requestId,
        amount,
        expiresAt: nextExpiresAt,
        retryUntil: nextRetryUntil,
      };
    } catch (error) {
      if (connection) await connection.rollback();
      if (error instanceof TypeError && error.message.includes('Invalid URL')) {
        return this.sendError(res, 'PAYMENT_PUBLIC_URL không hợp lệ trong backend/.env.', HTTP_STATUS.SERVICE_UNAVAILABLE);
      }
      if (error.message.includes('PAYMENT_PUBLIC_URL')) {
        return this.sendError(res, error.message, HTTP_STATUS.SERVICE_UNAVAILABLE);
      }
      return next(error);
    } finally {
      connection?.release();
    }
  }

  async initiateMomo(req, res, next) {
    const prepared = await this.preparePayment(req, res, next, 'momo');
    if (!prepared || res.headersSent) return;
    const { partnerCode, accessKey, secretKey, endpoint } = config.payments.momo;
    if (!partnerCode || !accessKey || !secretKey) {
      try {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
      } catch (error) {
        return next(error);
      }
      return this.sendError(res, 'MoMo chưa được cấu hình đầy đủ trong backend/.env.', HTTP_STATUS.SERVICE_UNAVAILABLE);
    }

    try {
      const origin = publicUrl();
      const endpoint = new URL(config.payments.momo.endpoint);
      const expectedMomoHost = config.payments.momo.environment === 'production'
        ? 'payment.momo.vn'
        : 'test-payment.momo.vn';
      if (endpoint.protocol !== 'https:'
        || endpoint.hostname !== expectedMomoHost) {
        try {
          await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
        } catch (error) {
          return next(error);
        }
        return this.sendError(res, 'MOMO_API_URL phải là endpoint HTTPS chính thức của MoMo.', HTTP_STATUS.SERVICE_UNAVAILABLE);
      }
      const redirectUrl = `${origin}/pages/payment.html?orderCode=${encodeURIComponent(prepared.orderCode)}`;
      const payload = {
        partnerCode,
        requestId: prepared.requestId,
        amount: prepared.amount,
        orderId: `${prepared.orderCode}-${prepared.requestId.slice(0, 8)}`,
        orderInfo: `Thanh toan don ${prepared.orderCode}`,
        redirectUrl,
        ipnUrl: `${origin}/api/v1/payments/momo/ipn`,
        extraData: Buffer.from(JSON.stringify({ orderCode: prepared.orderCode })).toString('base64'),
        requestType: 'captureWallet',
        autoCapture: true,
        lang: 'vi',
      };
      payload.signature = momoCreateSignature(payload, accessKey);

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(15000),
      });
      const result = await response.json();
      let paymentHost = '';
      try {
        paymentHost = new URL(result.payUrl).hostname;
      } catch {
        paymentHost = '';
      }
      if (!response.ok || Number(result.resultCode) !== 0
        || !['payment.momo.vn', 'test-payment.momo.vn'].includes(paymentHost)) {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
        return this.sendError(
          res,
          result.message || 'MoMo không thể khởi tạo giao dịch.',
          HTTP_STATUS.SERVICE_UNAVAILABLE
        );
      }

      await pool.execute(
        `UPDATE realtime_payments SET payment_url = ?
         WHERE order_code = ? AND request_id = ? AND status = 'pending'`,
        [result.payUrl, prepared.orderCode, prepared.requestId]
      );
      return this.sendSuccess(res, 'Đã khởi tạo thanh toán MoMo.', {
        orderCode: prepared.orderCode,
        amount: prepared.amount,
        paymentUrl: result.payUrl,
      }, HTTP_STATUS.OK);
    } catch (error) {
      try {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
      } catch (markError) {
        return next(markError);
      }
      if (error.message.includes('PAYMENT_PUBLIC_URL') || error.message.includes('Invalid URL')) {
        return this.sendError(
          res,
          error.message.includes('PAYMENT_PUBLIC_URL')
            ? error.message
            : 'URL cấu hình thanh toán không hợp lệ trong backend/.env.',
          HTTP_STATUS.SERVICE_UNAVAILABLE
        );
      }
      return this.sendError(
        res,
        error.name === 'TimeoutError' ? 'MoMo phản hồi quá thời gian chờ.' : 'Không thể kết nối cổng thanh toán MoMo.',
        HTTP_STATUS.SERVICE_UNAVAILABLE
      );
    }
  }

  async initiateVnpay(req, res, next) {
    const prepared = await this.preparePayment(req, res, next, 'vnpay');
    if (!prepared || res.headersSent) return;
    const { tmnCode, hashSecret, endpoint } = config.payments.vnpay;
    if (!tmnCode || !hashSecret) {
      try {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
      } catch (error) {
        return next(error);
      }
      return this.sendError(res, 'VNPay chưa được cấu hình đầy đủ trong backend/.env.', HTTP_STATUS.SERVICE_UNAVAILABLE);
    }

    try {
      const origin = publicUrl();
      const paymentEndpoint = new URL(endpoint);
      const expectedHost = config.payments.vnpay.environment === 'production'
        ? 'pay.vnpay.vn'
        : 'sandbox.vnpayment.vn';
      if (paymentEndpoint.protocol !== 'https:' || paymentEndpoint.hostname !== expectedHost) {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
        return this.sendError(
          res,
          'VNPAY_URL phải là endpoint HTTPS chính thức của VNPay.',
          HTTP_STATUS.SERVICE_UNAVAILABLE
        );
      }

      const now = new Date();
      const clientIp = String(req.ip || '127.0.0.1').replace(/^::ffff:/, '');
      const params = {
        vnp_Version: '2.1.0',
        vnp_Command: 'pay',
        vnp_TmnCode: tmnCode,
        vnp_Amount: String(prepared.amount * 100),
        vnp_CurrCode: 'VND',
        vnp_TxnRef: prepared.requestId,
        vnp_OrderInfo: `Thanh toan don ${prepared.orderCode}`,
        vnp_OrderType: 'other',
        vnp_Locale: 'vn',
        vnp_ReturnUrl: `${origin}/api/v1/payments/vnpay/return`,
        vnp_IpAddr: clientIp,
        vnp_CreateDate: formatVnpayDate(now),
        vnp_ExpireDate: formatVnpayDate(new Date(now.getTime() + 15 * 60 * 1000)),
      };
      if (!Number.isSafeInteger(prepared.amount * 100)) {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
        return this.sendError(res, 'Số tiền đơn hàng vượt giới hạn thanh toán VNPay.', HTTP_STATUS.BAD_REQUEST);
      }

      const signature = vnpaySignature(params, hashSecret);
      const query = Object.keys(params)
        .sort()
        .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(params[key]).replace(/%20/g, '+')}`)
        .join('&');
      const paymentUrl = `${paymentEndpoint.toString()}?${query}&vnp_SecureHash=${signature}`;
      await pool.execute(
        `UPDATE realtime_payments SET payment_url = ?
         WHERE order_code = ? AND request_id = ? AND status = 'pending'`,
        [paymentUrl, prepared.orderCode, prepared.requestId]
      );
      return this.sendSuccess(res, 'Đã khởi tạo thanh toán VNPay.', {
        orderCode: prepared.orderCode,
        amount: prepared.amount,
        paymentUrl,
      }, HTTP_STATUS.OK);
    } catch (error) {
      try {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
      } catch (markError) {
        return next(markError);
      }
      if (error.message.includes('PAYMENT_PUBLIC_URL') || error.message.includes('Invalid URL')) {
        return this.sendError(
          res,
          error.message.includes('PAYMENT_PUBLIC_URL')
            ? error.message
            : 'URL cấu hình thanh toán không hợp lệ trong backend/.env.',
          HTTP_STATUS.SERVICE_UNAVAILABLE
        );
      }
      return next(error);
    }
  }

  async initiateBank(req, res, next) {
    const prepared = await this.preparePayment(req, res, next, 'banking');
    if (!prepared || res.headersSent) return;
    const { bankCode, accountNumber } = config.payments.sepay;
    if (!bankCode || !accountNumber || !config.payments.sepay.apiKey) {
      try {
        await this.markInitiationFailed(prepared.orderCode, prepared.requestId);
      } catch (error) {
        return next(error);
      }
      return this.sendError(res, 'Cấu hình tài khoản QR hoặc SEPAY_API_KEY còn thiếu trong backend/.env.', HTTP_STATUS.SERVICE_UNAVAILABLE);
    }

    try {
      const qr = new URL('https://qr.sepay.vn/img');
      qr.searchParams.set('acc', accountNumber);
      qr.searchParams.set('bank', bankCode);
      qr.searchParams.set('amount', String(prepared.amount));
      qr.searchParams.set('des', prepared.orderCode);
      qr.searchParams.set('template', 'compact');
      await pool.execute(
        `UPDATE realtime_payments SET qr_url = ?
         WHERE order_code = ? AND request_id = ? AND status = 'pending'`,
        [qr.toString(), prepared.orderCode, prepared.requestId]
      );
      return this.sendSuccess(res, 'Đã tạo mã QR chuyển khoản.', {
        orderCode: prepared.orderCode,
        amount: prepared.amount,
        qrUrl: qr.toString(),
        accountName: config.payments.sepay.accountName,
        bankCode,
        accountNumber,
        expiresAt: prepared.expiresAt,
        retryUntil: prepared.retryUntil,
      }, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  async markInitiationFailed(orderCode, requestId) {
    await pool.execute(
      `UPDATE realtime_payments SET status = 'failed'
       WHERE order_code = ? AND request_id = ? AND status = 'pending' AND payment_url IS NULL AND qr_url IS NULL`,
      [orderCode, requestId]
    );
    await pool.execute(
      `UPDATE realtime_orders SET status = 'payment_failed',
         order_data = JSON_SET(order_data, '$.paymentStatus', 'failed',
           '$.status', 'payment_failed', '$.statusText', 'Thanh toán không thành công')
       WHERE order_code = ? AND status = 'awaiting_payment'`,
      [orderCode]
    );
  }

  async expirePaymentAttempt(orderCode, io) {
    const connection = await pool.getConnection();
    try {
      await connection.beginTransaction();
      const [payments] = await connection.execute(
        `SELECT status, expires_at AS expiresAt, retry_until AS retryUntil
         FROM realtime_payments WHERE order_code = ? FOR UPDATE`,
        [orderCode]
      );
      const payment = payments[0];
      const expiresAt = payment?.expiresAt ? new Date(payment.expiresAt).getTime() : null;
      if (!payment || payment.status !== 'pending' || (expiresAt && expiresAt > Date.now())) {
        await connection.commit();
        return false;
      }

      await connection.execute(
        `UPDATE realtime_payments
         SET status = 'failed',
             expires_at = COALESCE(expires_at, CURRENT_TIMESTAMP),
             retry_until = COALESCE(retry_until, DATE_ADD(COALESCE(expires_at, CURRENT_TIMESTAMP), INTERVAL 1 HOUR))
         WHERE order_code = ? AND status = 'pending'`,
        [orderCode]
      );
      const [orders] = await connection.execute(
        `SELECT user_id AS userId, status, order_data AS orderData
         FROM realtime_orders WHERE order_code = ? FOR UPDATE`,
        [orderCode]
      );
      if (!orders[0]) {
        await connection.rollback();
        return false;
      }

      const orderData = typeof orders[0].orderData === 'string'
        ? JSON.parse(orders[0].orderData)
        : orders[0].orderData;
      const isCod = String(orderData.paymentMethod || '').toLowerCase() === 'cod';
      const orderChanged = orders[0].status === 'awaiting_payment' && !isCod;
      if (orderChanged) {
        await connection.execute(
          `UPDATE realtime_orders SET status = 'payment_failed',
             order_data = JSON_SET(order_data, '$.paymentStatus', 'failed',
               '$.status', 'payment_failed', '$.statusText', 'Thanh toán không thành công')
           WHERE order_code = ?`,
          [orderCode]
        );
      }
      await connection.commit();
      if (!isCod) {
        io?.to(`user:${orders[0].userId}`).emit('payment:updated', {
          orderCode,
          paymentStatus: 'failed',
        });
        io?.to('admins').emit('payment:updated', { orderCode, paymentStatus: 'failed' });
        if (orderChanged) {
          const update = {
            orderCode,
            status: 'payment_failed',
            displayStatus: 'Thanh toán không thành công',
            statusStep: 1,
          };
          io?.to(`user:${orders[0].userId}`).emit('order:updated', update);
          io?.to('admins').emit('order:updated', update);
        }
      }
      return true;
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }
  }

  async switchToCod(req, res, next) {
    const orderCode = String(req.params.orderCode || '').trim().toUpperCase();
    if (!ORDER_CODE_PATTERN.test(orderCode)) {
      return this.sendError(res, 'Mã đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
    }

    let connection;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();
      const [orders] = await connection.execute(
        `SELECT status, order_data AS orderData
         FROM realtime_orders WHERE order_code = ? AND user_id = ? FOR UPDATE`,
        [orderCode, req.user.id]
      );
      if (!orders[0]) {
        await connection.rollback();
        return this.sendError(res, 'Không tìm thấy đơn hàng của bạn.', HTTP_STATUS.NOT_FOUND);
      }

      const orderData = typeof orders[0].orderData === 'string'
        ? JSON.parse(orders[0].orderData)
        : orders[0].orderData;
      if (String(orderData.paymentMethod || '').toLowerCase() === 'cod') {
        await connection.commit();
        return this.sendSuccess(res, 'Đơn hàng đã thanh toán khi nhận hàng.', {
          orderCode,
          paymentMethod: 'cod',
          status: orders[0].status,
        }, HTTP_STATUS.OK);
      }
      if (!['awaiting_payment', 'payment_failed'].includes(orders[0].status)
        || String(orderData.paymentStatus || '').toLowerCase() === 'completed') {
        await connection.rollback();
        return this.sendError(res, 'Không thể đổi phương thức cho đơn hàng này.', HTTP_STATUS.CONFLICT);
      }

      const [payments] = await connection.execute(
        `SELECT status, retry_until AS retryUntil
         FROM realtime_payments WHERE order_code = ? FOR UPDATE`,
        [orderCode]
      );
      const payment = payments[0];
      if (payment?.status === 'completed') {
        await connection.rollback();
        return this.sendError(res, 'Đơn hàng đã được thanh toán.', HTTP_STATUS.CONFLICT);
      }
      if (payment?.retryUntil && new Date(payment.retryUntil).getTime() <= Date.now()) {
        await connection.rollback();
        return this.sendError(res, 'Đã hết thời gian 1 giờ để đổi sang COD.', HTTP_STATUS.CONFLICT);
      }
      if (payment) {
        await connection.execute(
          `UPDATE realtime_payments SET status = 'failed'
           WHERE order_code = ? AND status <> 'completed'`,
          [orderCode]
        );
      }
      await connection.execute(
        `UPDATE realtime_orders SET status = 'pending',
           order_data = JSON_SET(order_data, '$.paymentMethod', 'COD',
             '$.paymentStatus', 'pending', '$.paymentTransactionId', NULL, '$.paidAt', NULL,
             '$.paymentExpiresAt', NULL, '$.paymentRetryUntil', NULL,
             '$.status', 'pending', '$.statusText', 'Chờ xác nhận')
         WHERE order_code = ?`,
        [orderCode]
      );
      await connection.commit();

      const io = req.app.get('io');
      const paymentUpdate = { orderCode, paymentStatus: 'pending', paymentMethod: 'cod' };
      const orderUpdate = {
        ...paymentUpdate,
        status: 'pending',
        displayStatus: 'Chờ xác nhận',
        statusStep: 1,
      };
      io?.to(`user:${req.user.id}`).emit('payment:updated', paymentUpdate);
      io?.to('admins').emit('payment:updated', paymentUpdate);
      io?.to(`user:${req.user.id}`).emit('order:updated', orderUpdate);
      io?.to('admins').emit('order:updated', orderUpdate);
      return this.sendSuccess(res, 'Đã đổi đơn hàng sang thanh toán COD.', {
        orderCode,
        paymentMethod: 'cod',
        paymentStatus: 'pending',
        status: 'pending',
        statusText: 'Chờ xác nhận',
      }, HTTP_STATUS.OK);
    } catch (error) {
      if (connection) await connection.rollback();
      return next(error);
    } finally {
      connection?.release();
    }
  }

  async getStatus(req, res, next) {
    try {
      const orderCode = String(req.params.orderCode || '').trim().toUpperCase();
      const [access] = await pool.execute(
        'SELECT order_code FROM realtime_orders WHERE order_code = ? AND user_id = ? LIMIT 1',
        [orderCode, req.user.id]
      );
      if (!access[0]) return this.sendError(res, 'Không tìm thấy đơn hàng của bạn.', HTTP_STATUS.NOT_FOUND);
      await this.expirePaymentAttempt(orderCode, req.app.get('io'));
      const [rows] = await pool.execute(
        `SELECT o.order_data AS orderData, o.status AS orderStatus,
                p.payment_method AS paymentMethod, p.amount,
                p.status AS paymentStatus, p.payment_url AS paymentUrl,
                p.qr_url AS qrUrl, p.expires_at AS expiresAt,
                p.retry_until AS retryUntil, p.paid_at AS paidAt
         FROM realtime_orders o
         LEFT JOIN realtime_payments p ON p.order_code = o.order_code
         WHERE o.order_code = ? AND o.user_id = ? LIMIT 1`,
        [orderCode, req.user.id]
      );
      const row = rows[0];
      const orderData = typeof row.orderData === 'string' ? JSON.parse(row.orderData) : row.orderData;
      const isCod = String(orderData.paymentMethod || '').toLowerCase() === 'cod';
      const paymentMethod = isCod
        ? 'cod'
        : row.paymentMethod || String(orderData.paymentMethod || '').toLowerCase();
      const paymentStatus = isCod
        ? 'pending'
        : row.paymentStatus || orderData.paymentStatus || 'pending';
      const retryUntil = row.retryUntil || orderData.paymentRetryUntil || null;
      const canRetry = !isCod && paymentStatus === 'failed' && retryUntil
        && new Date(retryUntil).getTime() > Date.now();
      return this.sendSuccess(res, 'Lấy trạng thái thanh toán thành công.', {
        orderCode,
        amount: Number(row.amount ?? orderData.total),
        paymentMethod,
        paymentStatus,
        orderStatus: row.orderStatus,
        orderStatusText: orderData.statusText,
        paymentUrl: isCod || paymentStatus !== 'pending' ? null : row.paymentUrl,
        qrUrl: isCod || paymentStatus !== 'pending' ? null : row.qrUrl,
        expiresAt: row.expiresAt || orderData.paymentExpiresAt || null,
        retryUntil,
        canRetry: Boolean(canRetry),
        canSwitchToCod: !isCod && paymentStatus !== 'completed'
          && ['awaiting_payment', 'payment_failed'].includes(row.orderStatus)
          && (!retryUntil || new Date(retryUntil).getTime() > Date.now()),
        accountName: config.payments.sepay.accountName,
        bankCode: config.payments.sepay.bankCode,
        accountNumber: config.payments.sepay.accountNumber,
        paidAt: row.paidAt,
      }, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  async completePayment(orderCode, method, transactionId, status, io) {
    const connection = await pool.getConnection();
    let notify = false;
    try {
      await connection.beginTransaction();
      const [payments] = await connection.execute(
        `SELECT status, provider_transaction_id AS transactionId FROM realtime_payments
         WHERE order_code = ? AND payment_method = ? FOR UPDATE`,
        [orderCode, method]
      );
      if (!payments[0]) {
        await connection.rollback();
        return false;
      }
      if (payments[0].status === 'completed') {
        await connection.commit();
        return String(payments[0].transactionId || '') === String(transactionId || '');
      }

      const paidAt = status === 'completed' ? new Date() : null;
      await connection.execute(
        `UPDATE realtime_payments SET status = ?, provider_transaction_id = ?, paid_at = ?
         WHERE order_code = ? AND payment_method = ?`,
        [status, transactionId || null, paidAt, orderCode, method]
      );
      const [orders] = await connection.execute(
        `SELECT user_id AS userId, status, order_data AS orderData
         FROM realtime_orders WHERE order_code = ? FOR UPDATE`,
        [orderCode]
      );
      if (!orders[0]) {
        await connection.rollback();
        return false;
      }
      const orderData = typeof orders[0].orderData === 'string'
        ? JSON.parse(orders[0].orderData)
        : orders[0].orderData;
      const isCod = String(orderData.paymentMethod || '').toLowerCase() === 'cod';
      if (isCod && status === 'failed') {
        await connection.commit();
        return true;
      }

      const previousOrderStatus = orders[0].status;
      const orderStatus = status === 'completed'
        && ['awaiting_payment', 'payment_failed'].includes(previousOrderStatus)
        ? 'pending'
        : status === 'failed' && previousOrderStatus === 'awaiting_payment' && !isCod
          ? 'payment_failed'
          : previousOrderStatus;
      const orderPaymentMethod = status === 'completed' ? method.toUpperCase() : orderData.paymentMethod;
      const orderPaymentStatus = isCod && status === 'failed' ? 'pending' : status;
      const orderStatusText = ({
        awaiting_payment: 'Chờ thanh toán',
        payment_failed: 'Thanh toán không thành công',
        pending: 'Chờ xác nhận',
        preparing: 'Đang chuẩn bị',
        delivering: 'Đang giao',
        delivered: 'Đã giao',
        cancelled: 'Đã hủy',
      })[orderStatus];
      await connection.execute(
        `UPDATE realtime_orders SET status = ?, order_data = JSON_SET(
           order_data, '$.paymentMethod', ?, '$.paymentStatus', ?,
           '$.paymentTransactionId', ?, '$.paidAt', ?, '$.status', ?, '$.statusText', ?
         ) WHERE order_code = ?`,
        [
          orderPaymentMethod,
          orderPaymentStatus,
          transactionId || null,
          paidAt ? paidAt.toISOString() : null,
          orderStatus,
          orderStatusText,
          orderCode,
        ]
      );
      await connection.commit();
      notify = true;
      if (notify && orders[0]) {
        const paymentUpdate = {
          orderCode,
          paymentStatus: orderPaymentStatus,
          paymentMethod: String(orderPaymentMethod || '').toLowerCase(),
        };
        io?.to(`user:${orders[0].userId}`).emit('payment:updated', paymentUpdate);
        io?.to('admins').emit('payment:updated', paymentUpdate);
        if (previousOrderStatus !== orderStatus || isCod && status === 'completed') {
          const update = {
            orderCode,
            status: orderStatus,
            displayStatus: orderStatusText,
            statusStep: 1,
            paymentStatus: orderPaymentStatus,
            paymentMethod: String(orderPaymentMethod || '').toLowerCase(),
          };
          io?.to(`user:${orders[0].userId}`).emit('order:updated', update);
          io?.to('admins').emit('order:updated', update);
        }
      }
      return true;
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }
  }

  async momoIpn(req, res, next) {
    const body = req.body || {};
    const { partnerCode, accessKey, secretKey } = config.payments.momo;
    if (!partnerCode || !accessKey || !secretKey || body.partnerCode !== partnerCode
      || !safeEqual(body.signature, momoIpnSignature(body, accessKey))) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({ success: false, message: 'Chữ ký MoMo không hợp lệ.' });
    }
    try {
      const [rows] = await pool.execute(
        `SELECT order_code AS orderCode, amount
         FROM realtime_payments WHERE request_id = ? AND payment_method = 'momo' LIMIT 1`,
        [String(body.requestId || '')]
      );
      const payment = rows[0];
      if (!payment || Number(body.amount) !== Number(payment.amount)
        || body.orderId !== `${payment.orderCode}-${String(body.requestId).slice(0, 8)}`) {
        return res.status(HTTP_STATUS.BAD_REQUEST).json({ success: false, message: 'Giao dịch MoMo không khớp đơn hàng.' });
      }
      const resultStatus = Number(body.resultCode) === 0 ? 'completed' : 'failed';
      if (resultStatus === 'completed' && !body.transId) {
        return res.status(HTTP_STATUS.BAD_REQUEST).json({ success: false, message: 'MoMo thiếu mã giao dịch.' });
      }
      const updated = await this.completePayment(
        payment.orderCode,
        'momo',
        String(body.transId || ''),
        resultStatus,
        req.app.get('io')
      );
      if (!updated) return res.status(HTTP_STATUS.NOT_FOUND).json({ success: false, message: 'Không tìm thấy giao dịch.' });
      return res.status(HTTP_STATUS.OK).json({ success: true });
    } catch (error) {
      return next(error);
    }
  }

  async processVnpayCallback(query, io) {
    const { tmnCode, hashSecret } = config.payments.vnpay;
    if (!tmnCode || !hashSecret || !query.vnp_SecureHash
      || !safeEqual(query.vnp_SecureHash, vnpaySignature(query, hashSecret))
      || query.vnp_TmnCode !== tmnCode) {
      return { errorCode: '97', message: 'Chữ ký VNPay không hợp lệ.' };
    }

    const requestId = String(query.vnp_TxnRef || '');
    const [rows] = await pool.execute(
      `SELECT order_code AS orderCode, amount
       FROM realtime_payments WHERE request_id = ? AND payment_method = 'vnpay' LIMIT 1`,
      [requestId]
    );
    const payment = rows[0];
    if (!payment) return { errorCode: '01', message: 'Không tìm thấy giao dịch.' };

    const expectedAmount = Number(payment.amount) * 100;
    if (!Number.isSafeInteger(expectedAmount) || Number(query.vnp_Amount) !== expectedAmount) {
      return { errorCode: '04', message: 'Số tiền giao dịch không khớp.' };
    }

    const isPaid = query.vnp_ResponseCode === '00' && query.vnp_TransactionStatus === '00';
    const transactionId = isPaid ? String(query.vnp_TransactionNo || '') : '';
    if (isPaid && !transactionId) {
      return { errorCode: '99', message: 'VNPay thiếu mã giao dịch.' };
    }
    const updated = await this.completePayment(
      payment.orderCode,
      'vnpay',
      transactionId,
      isPaid ? 'completed' : 'failed',
      io
    );
    if (!updated) return { errorCode: '99', message: 'Không thể cập nhật giao dịch.' };
    return { orderCode: payment.orderCode };
  }

  async vnpayIpn(req, res, next) {
    try {
      const result = await this.processVnpayCallback(req.query || {}, req.app.get('io'));
      return res.status(HTTP_STATUS.OK).json({
        RspCode: result.errorCode || '00',
        Message: result.errorCode ? result.message : 'Confirm Success',
      });
    } catch (error) {
      return next(error);
    }
  }

  async vnpayReturn(req, res, next) {
    try {
      const result = await this.processVnpayCallback(req.query || {}, req.app.get('io'));
      if (result.errorCode) {
        return res.status(HTTP_STATUS.BAD_REQUEST).send(result.message);
      }
      const origin = publicUrl();
      return res.redirect(
        303,
        `${origin}/pages/payment.html?orderCode=${encodeURIComponent(result.orderCode)}`
      );
    } catch (error) {
      return next(error);
    }
  }

  async sepayWebhook(req, res, next) {
    const expected = config.payments.sepay.apiKey;
    const authorization = req.get('authorization') || '';
    const provided = authorization.startsWith('Apikey ') ? authorization.slice(7) : '';
    if (!expected || !safeEqual(provided, expected)) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({ success: false, message: 'Webhook key không hợp lệ.' });
    }

    const body = req.body || {};
    const transactionId = String(body.id || '');
    const accountNumber = String(body.accountNumber || '');
    const content = String(body.content || body.description || '').toUpperCase();
    const transferType = String(body.transferType || '').toLowerCase();
    const amount = Number(body.transferAmount);
    const orderCode = content.match(/FF(?:\d{4}|-[A-Z0-9]{6,8}|-\d{8}-\d{4})/)?.[0];
    if (!transactionId || accountNumber !== config.payments.sepay.accountNumber
      || transferType !== 'in' || !Number.isSafeInteger(amount) || !orderCode) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({ success: false, message: 'Thông tin giao dịch QR không hợp lệ.' });
    }

    try {
      const [rows] = await pool.execute(
        `SELECT amount FROM realtime_payments
         WHERE order_code = ? AND payment_method = 'banking' LIMIT 1`,
        [orderCode]
      );
      if (!rows[0] || Number(rows[0].amount) !== amount) {
        return res.status(HTTP_STATUS.CONFLICT).json({ success: false, message: 'Số tiền hoặc mã đơn không khớp.' });
      }
      const updated = await this.completePayment(
        orderCode,
        'banking',
        transactionId,
        'completed',
        req.app.get('io')
      );
      if (!updated) return res.status(HTTP_STATUS.NOT_FOUND).json({ success: false, message: 'Không tìm thấy giao dịch.' });
      return res.status(HTTP_STATUS.OK).json({ success: true });
    } catch (error) {
      return next(error);
    }
  }
}

const controller = new PaymentController();
controller.signatureHelpers = {
  hmacSha256,
  momoCreateSignature,
  momoIpnSignature,
  vnpaySignData,
  vnpaySignature,
  safeEqual,
};
module.exports = controller;
