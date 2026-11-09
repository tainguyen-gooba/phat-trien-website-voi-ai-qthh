const express = require('express');
const realtimeController = require('../controllers/realtime.controller');
const { authenticate, authorize } = require('../middlewares/auth.middleware');
const imageUploadRoutes = require('./image-upload.routes');

const router = express.Router();

router.get('/content', (req, res, next) => realtimeController.getWebsiteContent(req, res, next));
router.use('/uploads', imageUploadRoutes);
router.route('/orders')
  .get(authenticate, authorize('admin', 'customer'), (req, res, next) => realtimeController.getOrders(req, res, next))
  .post(authenticate, authorize('customer'), (req, res, next) => realtimeController.createOrder(req, res, next));
router.get('/orders/shipping-policy', authenticate, authorize('customer'), (req, res, next) => (
  realtimeController.getShippingPolicy(req, res, next)
));
router.patch('/orders/:orderCode/cancel', authenticate, authorize('customer'), (req, res, next) => (
  realtimeController.cancelOrder(req, res, next)
));
router.post('/orders/:orderCode/reviews/:target', authenticate, authorize('customer'), (req, res, next) => (
  realtimeController.submitOrderReview(req, res, next)
));
router.get('/orders/reviews', authenticate, authorize('admin'), (req, res, next) => (
  realtimeController.getOrderReviews(req, res, next)
));
router.get('/chat/conversations', authenticate, authorize('admin'), (req, res, next) => (
  realtimeController.getChatConversations(req, res, next)
));
router.post('/chat/ai/suggest', authenticate, authorize('admin'), (req, res, next) => (
  realtimeController.getAISuggestion(req, res, next)
));
router.post('/chat/ai/copilot', authenticate, authorize('admin'), (req, res, next) => (
  realtimeController.consultAICopilot(req, res, next)
));
router.patch('/chat/:customerId/automation', authenticate, authorize('admin'), (req, res, next) => (
  realtimeController.toggleChatAutomation(req, res, next)
));
router.get('/chat/:customerId', authenticate, authorize('admin', 'customer'), (req, res, next) => (
  realtimeController.getChatHistory(req, res, next)
));

module.exports = router;
