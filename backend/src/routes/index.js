const express = require('express');
const router = express.Router();

const authRoutes = require('./auth.routes');
const healthRoutes = require('./health.routes');
const realtimeRoutes = require('./realtime.routes');
const paymentRoutes = require('./payment.routes');
const chatRoutes = require('./chatRoutes');

/**
 * Root API Router - Version 1 (/api/v1)
 */

// Route thông tin chào mừng API
router.get('/', (req, res) => {
  res.json({
    success: true,
    name: 'FASTFOOD E-Commerce Food Delivery RESTful API',
    version: '1.0.0',
    status: 'online',
    documentation: '/docs',
    endpoints: {
      health: '/api/v1/health',
      databaseHealth: '/api/v1/health/db',
      auth: '/api/v1/auth',
      login: '/api/v1/auth/login',
      currentUser: '/api/v1/auth/me',
      chat: '/api/v1/chat',
      restaurants: '/api/v1/restaurants (sắp ra mắt)',
      products: '/api/v1/products (sắp ra mắt)',
      orders: '/api/v1/orders (sắp ra mắt)',
    },
  });
});

// Đăng ký các module routes
router.use('/auth', authRoutes);
router.use('/health', healthRoutes);
router.use('/realtime', realtimeRoutes);
router.use('/payments', paymentRoutes);
router.use('/chat', chatRoutes);

// Các routes nghiệp vụ sẽ được phát triển tiếp ở các Prompt sau:
// router.use('/users', userRoutes);
// router.use('/restaurants', restaurantRoutes);
// router.use('/products', productRoutes);
// router.use('/cart', cartRoutes);
// router.use('/orders', orderRoutes);
// router.use('/coupons', couponRoutes);
// router.use('/reviews', reviewRoutes);
// router.use('/notifications', notificationRoutes);

module.exports = router;
