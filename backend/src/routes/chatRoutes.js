/**
 * FASTFOOD CHAT API ROUTES
 * Đường dẫn API phục vụ hệ thống Chat Nhân viên hỗ trợ + Trợ lý tự động khi Admin bận
 */

const express = require('express');
const router = express.Router();
const jwt = require('jsonwebtoken');
const config = require('../config/app.config');
const chatController = require('../controllers/chatController');
const chatRateLimit = require('../middlewares/chatRateLimit');
const { authenticate, authorize } = require('../middlewares/auth.middleware');

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
    req.user = null;
  }
  next();
}

// Khách hàng & Khách vãng lai
router.post('/message', optionalAuth, chatRateLimit, chatController.sendMessage);
router.get('/history', optionalAuth, chatController.getHistory);
router.post('/new-session', optionalAuth, chatController.newSession);
router.get('/status', optionalAuth, chatController.getSupportStatus);
router.get('/recommendations', chatController.getRecommendations);

// Admin & Nhân viên quản lý hộp thư "Nhân viên hỗ trợ"
router.get('/admin/conversations', authenticate, authorize('admin', 'staff'), chatController.getAdminConversations);
router.get('/admin/sessions/:sessionId/messages', authenticate, authorize('admin', 'staff'), chatController.getAdminSessionMessages);
router.post('/admin/sessions/:sessionId/reply', authenticate, authorize('admin', 'staff'), chatController.sendAdminReply);
router.patch('/admin/status', authenticate, authorize('admin', 'staff'), chatController.updateAdminBusyStatus);
router.patch('/admin/sessions/:sessionId/automation', authenticate, authorize('admin', 'staff'), chatController.toggleSessionAutomation);

module.exports = router;
