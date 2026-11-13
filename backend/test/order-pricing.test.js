const test = require('node:test');
const assert = require('node:assert/strict');
const realtimeController = require('../src/controllers/realtime.controller');

function fakeConnection(product) {
  return {
    async execute(query) {
      if (query.includes('website_content')) {
        return [[{ content: JSON.stringify([product]) }]];
      }
      return [[]];
    },
  };
}

test('online order pricing uses published product and topping prices', async () => {
  const connection = fakeConnection({
    id: 'prd-1',
    name: 'Published meal',
    price: 100,
    status: 'Đang bán',
    toppings: [{ name: 'Cheese', price: 25 }],
  });
  const result = await realtimeController.calculateTrustedItems(connection, [{
    productId: 'prod-1',
    name: 'Altered client name',
    qty: 2,
    basePrice: 100,
    price: 125,
    selectedToppings: [{ name: 'Cheese', price: 25 }],
  }]);

  assert.equal(result.subtotal, 250);
  assert.equal(result.items[0].name, 'Published meal');
  assert.equal(result.items[0].price, 125);
});

test('online order pricing rejects client-modified prices and unknown toppings', async () => {
  const connection = fakeConnection({
    id: 'prd-1',
    name: 'Published meal',
    price: 100,
    status: 'Đang bán',
    toppings: [],
  });
  await assert.rejects(
    realtimeController.calculateTrustedItems(connection, [{
      productId: 'prod-1',
      name: 'Published meal',
      qty: 1,
      basePrice: 1,
      price: 1,
      selectedToppings: [],
    }]),
    { code: 'INVALID_ORDER_PRICING' }
  );
  await assert.rejects(
    realtimeController.calculateTrustedItems(connection, [{
      productId: 'prod-1',
      name: 'Published meal',
      qty: 1,
      basePrice: 100,
      price: 101,
      selectedToppings: [{ name: 'Unknown', price: 1 }],
    }]),
    { code: 'INVALID_ORDER_PRICING' }
  );
});

test('online coupon discounts are calculated from server data', async () => {
  const connection = {
    async execute() {
      return [[]];
    },
  };

  assert.deepEqual(
    await realtimeController.validateDiscount(connection, 'FASTFOOD20', 1, 100000, 20000),
    { discount: 20000, couponId: null }
  );
  await assert.rejects(
    realtimeController.validateDiscount(connection, 'FASTFOOD20', 1, 100000, 1),
    { code: 'INVALID_ORDER_PRICING' }
  );
});

test('unpaid online orders are shown as awaiting payment until paid', () => {
  const unpaid = realtimeController.orderFromRow({
    order_code: 'FF1234',
    order_data: JSON.stringify({ paymentMethod: 'MOMO', paymentStatus: 'pending' }),
    status: 'awaiting_payment',
  });
  const paid = realtimeController.orderFromRow({
    order_code: 'FF1235',
    order_data: JSON.stringify({ paymentMethod: 'MOMO', paymentStatus: 'completed' }),
    status: 'pending',
  });
  const expired = realtimeController.orderFromRow({
    order_code: 'FF1236',
    order_data: JSON.stringify({ paymentMethod: 'BANKING', paymentStatus: 'pending' }),
    paymentMethod: 'banking',
    paymentStatus: 'pending',
    paymentExpiresAt: new Date(Date.now() - 1000),
    paymentRetryUntil: new Date(Date.now() + 60 * 60 * 1000),
    status: 'awaiting_payment',
  });
  const retryExpired = realtimeController.orderFromRow({
    order_code: 'FF1237',
    order_data: JSON.stringify({ paymentMethod: 'MOMO', paymentStatus: 'failed' }),
    paymentMethod: 'momo',
    paymentStatus: 'failed',
    paymentExpiresAt: new Date(Date.now() - 61 * 60 * 1000),
    paymentRetryUntil: new Date(Date.now() - 1000),
    status: 'payment_failed',
  });

  assert.equal(unpaid.statusText, 'Chờ thanh toán');
  assert.equal(unpaid.canRetryPayment, true);
  assert.equal(paid.statusText, 'Chờ xác nhận');
  assert.equal(expired.statusText, 'Thanh toán không thành công');
  assert.equal(expired.canRetryPayment, true);
  assert.equal(expired.canSwitchToCod, true);
  assert.equal(retryExpired.status, 'payment_failed');
  assert.equal(retryExpired.statusText, 'Thanh toán không thành công');
  assert.equal(retryExpired.canRetryPayment, false);
  assert.equal(retryExpired.canSwitchToCod, false);
});
