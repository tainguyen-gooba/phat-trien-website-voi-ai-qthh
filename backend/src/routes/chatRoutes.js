/**
 * FASTFOOD CHAT API ROUTES
 * Đường dẫn API phục vụ hệ thống Chat AI thông minh
 */

const express = require('express');
const router = express.Router();
const jwt = require('jsonwebtoken');
const config = require('../config/app.config');
const chatController = require('../controllers/chatController');
const chatRateLimit = require('../middlewares/chatRateLimit');

/**
 * Middleware xác thực tùy chọn (Optional Authentication)
 * Cho phép cả khách vãng lai (Guest) và khách đã đăng nhập (Logged-in User)
 */
function optionalAuth(req, res, next) {
  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    req.user = null;
    return next();
  }

  const token = authHeader.split(' ')[1];
  try {
    const decoded = jwt.verify(token, config.jwt.secret);
    req.user = decoded;
  } catch (_) {
    req.user = null; // Token lỗi hoặc hết hạn thì coi như khách vãng lai
  }
  next();
}

// 1. Gửi tin nhắn hỏi đáp với AI (có Rate Limiting chống spam)
router.post('/message', optionalAuth, chatRateLimit, chatController.sendMessage);

// 2. Lấy lịch sử hội thoại của phiên
router.get('/history', optionalAuth, chatController.getHistory);

// 3. Khởi tạo phiên trò chuyện mới
router.post('/new-session', optionalAuth, chatController.newSession);

// 4. Lấy dữ liệu món ăn gợi ý nhanh ban đầu từ MySQL
router.get('/recommendations', chatController.getRecommendations);

module.exports = router;

