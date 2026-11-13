const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

process.env.MOMO_SECRET_KEY = 'unit-test-only-secret';
const { signatureHelpers } = require('../src/controllers/payment.controller');

test('MoMo create signature follows the provider canonical field order', () => {
  const payload = {
    amount: '125000',
    extraData: 'e30=',
    ipnUrl: 'https://fastfood.example/api/v1/payments/momo/ipn',
    orderId: 'FF1234-a1b2c3d4',
    orderInfo: 'Thanh toan don FF1234',
    partnerCode: 'PARTNER',
    redirectUrl: 'https://fastfood.example/pages/payment.html?orderCode=FF1234',
    requestId: 'a1b2c3d4',
    requestType: 'captureWallet',
  };
  const canonical = 'accessKey=test-access&amount=125000&extraData=e30='
    + '&ipnUrl=https://fastfood.example/api/v1/payments/momo/ipn'
    + '&orderId=FF1234-a1b2c3d4&orderInfo=Thanh toan don FF1234'
    + '&partnerCode=PARTNER'
    + '&redirectUrl=https://fastfood.example/pages/payment.html?orderCode=FF1234'
    + '&requestId=a1b2c3d4&requestType=captureWallet';
  const expected = crypto.createHmac('sha256', process.env.MOMO_SECRET_KEY)
    .update(canonical)
    .digest('hex');

  assert.equal(signatureHelpers.momoCreateSignature(payload, 'test-access'), expected);
});

test('MoMo IPN signature includes transaction result fields', () => {
  const payload = {
    amount: 125000,
    extraData: 'e30=',
    message: 'Successful.',
    orderId: 'FF1234-a1b2c3d4',
    orderInfo: 'Thanh toan don FF1234',
    orderType: 'momo_wallet',
    partnerCode: 'PARTNER',
    payType: 'qr',
    requestId: 'a1b2c3d4',
    responseTime: 1750000000000,
    resultCode: 0,
    transId: 987654321,
  };
  const canonical = 'accessKey=test-access&amount=125000&extraData=e30='
    + '&message=Successful.&orderId=FF1234-a1b2c3d4'
    + '&orderInfo=Thanh toan don FF1234&orderType=momo_wallet'
    + '&partnerCode=PARTNER&payType=qr&requestId=a1b2c3d4'
    + '&responseTime=1750000000000&resultCode=0&transId=987654321';
  const expected = crypto.createHmac('sha256', process.env.MOMO_SECRET_KEY)
    .update(canonical)
    .digest('hex');

  assert.equal(signatureHelpers.momoIpnSignature(payload, 'test-access'), expected);
});

test('VNPay signature sorts and encodes fields while excluding signature fields', () => {
  const payload = {
    vnp_OrderInfo: 'Thanh toan don FF1234',
    vnp_Amount: '12500000',
    vnp_SecureHashType: 'HmacSHA512',
    vnp_SecureHash: 'ignored',
    vnp_TxnRef: 'request-123',
  };
  const canonical = 'vnp_Amount=12500000&vnp_OrderInfo=Thanh+toan+don+FF1234&vnp_TxnRef=request-123';
  const expected = crypto.createHmac('sha512', 'test-secret').update(canonical).digest('hex');

  assert.equal(signatureHelpers.vnpaySignData(payload), canonical);
  assert.equal(signatureHelpers.vnpaySignature(payload, 'test-secret'), expected);
});

test('signature comparison is timing-safe and rejects different values', () => {
  assert.equal(signatureHelpers.safeEqual('abc123', 'abc123'), true);
  assert.equal(signatureHelpers.safeEqual('abc123', 'abc124'), false);
  assert.equal(signatureHelpers.safeEqual('abc123', 'short'), false);
});
