const express = require('express');
const { authenticate, authorize } = require('../middlewares/auth.middleware');
const imageUploadController = require('../controllers/image-upload.controller');

const router = express.Router();

router.post(
  '/product-image',
  authenticate,
  authorize('admin'),
  express.raw({ type: ['image/jpeg', 'image/png', 'image/webp'], limit: '5mb' }),
  (req, res, next) => imageUploadController.uploadProductImage(req, res, next)
);

module.exports = router;
