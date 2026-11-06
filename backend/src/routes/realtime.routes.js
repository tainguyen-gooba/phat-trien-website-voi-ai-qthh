const express = require('express');
const realtimeController = require('../controllers/realtime.controller');
const { authenticate, authorize } = require('../middlewares/auth.middleware');

const router = express.Router();

router.get('/content', (req, res, next) => realtimeController.getWebsiteContent(req, res, next));
router.route('/orders')
  .get(authenticate, authorize('admin', 'customer'), (req, res, next) => realtimeController.getOrders(req, res, next))
  .post(authenticate, authorize('customer'), (req, res, next) => realtimeController.createOrder(req, res, next));
router.get('/chat/:customerId', authenticate, authorize('admin', 'customer'), (req, res, next) => (
  realtimeController.getChatHistory(req, res, next)
));

module.exports = router;
