const express = require('express');
const paymentController = require('../controllers/payment.controller');
const { authenticate, authorize } = require('../middlewares/auth.middleware');

const router = express.Router();

router.post('/momo/ipn', (req, res, next) => paymentController.momoIpn(req, res, next));
router.post('/sepay/webhook', (req, res, next) => paymentController.sepayWebhook(req, res, next));
router.post('/momo/initiate', authenticate, authorize('customer'), (req, res, next) => (
  paymentController.initiateMomo(req, res, next)
));
router.post('/bank/initiate', authenticate, authorize('customer'), (req, res, next) => (
  paymentController.initiateBank(req, res, next)
));
router.get('/:orderCode/status', authenticate, authorize('customer'), (req, res, next) => (
  paymentController.getStatus(req, res, next)
));

module.exports = router;
