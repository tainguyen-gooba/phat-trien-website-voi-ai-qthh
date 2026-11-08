const crypto = require('crypto');
const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const config = require('../config/app.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');

const ORDER_CODE_PATTERN = /^FF\d{4}$|^FF-[A-Z0-9]{6,8}$|^FF-\d{8}-\d{4}$/;

function hmacSha256(value, secret) {
  return crypto.createHmac('sha256', secret).update(value).digest('hex');
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
  if ((config.isProduction || config.payments.momo.environment === 'production')
    && url.protocol !== 'https:') {
    throw new Error('PAYMENT_PUBLIC_URL phải sử dụng HTTPS trong production.');
  }
  return url.origin;
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
      if (order.status === 'cancelled') {
        await connection.rollback();
        return this.sendError(res, 'Đơn hàng đã hủy, không thể thanh toán.', HTTP_STATUS.CONFLICT);
      }

      const amount = Number(orderData.total);
      if (!Number.isSafeInteger(amount) || amount < 1) {
        await connection.rollback();
        return this.sendError(res, 'Số tiền đơn hàng không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }

      const [payments] = await connection.execute(
        'SELECT request_id AS requestId, status, payment_url AS paymentUrl, qr_url AS qrUrl FROM realtime_payments WHERE order_code = ? FOR UPDATE',
        [orderCode]
      );
      const existing = payments[0];
      if (existing?.status === 'completed') {
        await connection.rollback();
        return this.sendError(res, 'Đơn hàng đã được thanh toán.', HTTP_STATUS.CONFLICT);
      }
      if (existing && existing.status === 'pending' && (existing.paymentUrl || existing.qrUrl)) {
        await connection.commit();
        return this.sendSuccess(res, 'Đã tạo yêu cầu thanh toán.', {
          orderCode,
          amount,
          paymentUrl: existing.paymentUrl,
          qrUrl: existing.qrUrl,
        }, HTTP_STATUS.OK);
      }

      const requestId = existing?.status === 'pending' && existing.requestId
        ? existing.requestId
        : crypto.randomUUID();
      await connection.execute(
        `INSERT INTO realtime_payments (order_code, payment_method, request_id, amount, status)
         VALUES (?, ?, ?, ?, 'pending')
         ON DUPLICATE KEY UPDATE payment_method = VALUES(payment_method),
           request_id = VALUES(request_id), amount = VALUES(amount), status = 'pending',
           provider_transaction_id = NULL, payment_url = NULL, qr_url = NULL, paid_at = NULL`,
        [orderCode, method, requestId, amount]
      );
      await connection.commit();
      return { orderCode, requestId, amount };
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
  }

  async getStatus(req, res, next) {
    try {
      const orderCode = String(req.params.orderCode || '').trim().toUpperCase();
      const [rows] = await pool.execute(
        `SELECT o.order_data AS orderData, p.payment_method AS paymentMethod,
                p.amount, p.status AS paymentStatus, p.payment_url AS paymentUrl,
                p.qr_url AS qrUrl, p.paid_at AS paidAt
         FROM realtime_orders o
         LEFT JOIN realtime_payments p ON p.order_code = o.order_code
         WHERE o.order_code = ? AND o.user_id = ? LIMIT 1`,
        [orderCode, req.user.id]
      );
      if (!rows[0]) return this.sendError(res, 'Không tìm thấy đơn hàng của bạn.', HTTP_STATUS.NOT_FOUND);
      const orderData = typeof rows[0].orderData === 'string' ? JSON.parse(rows[0].orderData) : rows[0].orderData;
      return this.sendSuccess(res, 'Lấy trạng thái thanh toán thành công.', {
        orderCode,
        amount: Number(rows[0].amount ?? orderData.total),
        paymentMethod: rows[0].paymentMethod || String(orderData.paymentMethod || '').toLowerCase(),
        paymentStatus: rows[0].paymentStatus || orderData.paymentStatus || 'pending',
        paymentUrl: rows[0].paymentUrl,
        qrUrl: rows[0].qrUrl,
        accountName: config.payments.sepay.accountName,
        bankCode: config.payments.sepay.bankCode,
        accountNumber: config.payments.sepay.accountNumber,
        paidAt: rows[0].paidAt,
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
      await connection.execute(
        `UPDATE realtime_orders SET order_data = JSON_SET(
           order_data, '$.paymentStatus', ?, '$.paymentTransactionId', ?, '$.paidAt', ?
         ) WHERE order_code = ?`,
        [status, transactionId || null, paidAt ? paidAt.toISOString() : null, orderCode]
      );
      const [orders] = await connection.execute(
        'SELECT user_id AS userId FROM realtime_orders WHERE order_code = ?',
        [orderCode]
      );
      await connection.commit();
      notify = true;
      if (notify && orders[0]) {
        io?.to(`user:${orders[0].userId}`).emit('payment:updated', { orderCode, paymentStatus: status });
        io?.to('admins').emit('payment:updated', { orderCode, paymentStatus: status });
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
controller.signatureHelpers = { hmacSha256, momoCreateSignature, momoIpnSignature, safeEqual };
module.exports = controller;
