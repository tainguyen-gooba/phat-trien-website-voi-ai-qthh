/**
 * FASTFOOD CHAT RATE LIMIT MIDDLEWARE
 * Giới hạn tần suất gửi tin nhắn để chống spam và bảo vệ tài nguyên hệ thống
 */

const ipRequests = new Map();
const WINDOW_MS = 60 * 1000; // 1 phút
const MAX_REQUESTS = 30; // Tối đa 30 tin nhắn / phút

function chatRateLimit(req, res, next) {
  const clientKey = req.user?.id 
    ? `user_${req.user.id}`
    : req.body?.sessionId 
      ? `session_${req.body.sessionId}` 
      : (req.ip || req.connection?.remoteAddress || 'unknown_ip');

  const now = Date.now();
  const record = ipRequests.get(clientKey) || { count: 0, resetTime: now + WINDOW_MS };

  if (now > record.resetTime) {
    record.count = 1;
    record.resetTime = now + WINDOW_MS;
  } else {
    record.count++;
  }

  ipRequests.set(clientKey, record);

  // Dọn dẹp cache định kỳ để giải phóng bộ nhớ
  if (ipRequests.size > 2000) {
    for (const [key, val] of ipRequests.entries()) {
      if (now > val.resetTime) ipRequests.delete(key);
    }
  }

  if (record.count > MAX_REQUESTS) {
    const retryAfter = Math.ceil((record.resetTime - now) / 1000);
    return res.status(429).json({
      success: false,
      message: `Bạn đang gửi tin nhắn quá nhanh. Vui lòng chờ ${retryAfter} giây rồi thử lại nhé!`,
      retryAfter,
    });
  }

  next();
}

module.exports = chatRateLimit;

