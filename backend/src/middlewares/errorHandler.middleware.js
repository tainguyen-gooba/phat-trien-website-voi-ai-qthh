const ApiResponse = require('../utils/apiResponse');
const HTTP_STATUS = require('../constants/httpStatusCodes');
const logger = require('../utils/logger');
const config = require('../config/app.config');

/**
 * Centralized Error Handling Middleware
 * Bắt tất cả các lỗi chưa được xử lý trong ứng dụng Express
 */
function errorHandler(err, req, res, next) { // eslint-disable-line no-unused-vars
  logger.error(`Unhandled Error: ${err.message}`, {
    stack: err.stack,
    url: req.originalUrl,
    method: req.method,
  });

  // MySQL specific errors
  if (err.code === 'ER_DUP_ENTRY') {
    return ApiResponse.error(
      res,
      'Dữ liệu đã tồn tại trong hệ thống (trùng lặp khóa)',
      HTTP_STATUS.CONFLICT,
      config.isDevelopment ? { detail: err.message } : null
    );
  }

  if (err.code === 'ECONNREFUSED' || err.code === 'PROTOCOL_CONNECTION_LOST') {
    return ApiResponse.error(
      res,
      'Không thể kết nối MySQL. Hãy bật dịch vụ MySQL và kiểm tra cấu hình backend/.env.',
      HTTP_STATUS.SERVICE_UNAVAILABLE,
      config.isDevelopment ? { detail: err.message } : null
    );
  }

  if (err.code === 'ER_BAD_DB_ERROR') {
    return ApiResponse.error(
      res,
      `Database '${config.db.database}' chưa được tạo. Hãy chạy 'npm run db:init' sau khi bật MySQL.`,
      HTTP_STATUS.SERVICE_UNAVAILABLE,
      config.isDevelopment ? { detail: err.message } : null
    );
  }

  if (err.type === 'entity.too.large') {
    const isProductImageUpload = req.originalUrl.includes('/realtime/uploads/product-image');
    return ApiResponse.error(
      res,
      isProductImageUpload ? 'Ảnh tải lên vượt quá dung lượng tối đa 5 MB.' : 'Dữ liệu gửi lên vượt quá dung lượng cho phép.',
      HTTP_STATUS.PAYLOAD_TOO_LARGE
    );
  }

  // JWT errors
  if (err.name === 'JsonWebTokenError') {
    return ApiResponse.error(
      res,
      'Mã xác thực không hợp lệ',
      HTTP_STATUS.UNAUTHORIZED
    );
  }

  if (err.name === 'TokenExpiredError') {
    return ApiResponse.error(
      res,
      'Mã xác thực đã hết hạn',
      HTTP_STATUS.UNAUTHORIZED
    );
  }

  // Default fallback internal server error
  const statusCode = err.statusCode || HTTP_STATUS.INTERNAL_SERVER_ERROR;
  const message = err.message || 'Lỗi hệ thống máy chủ nội bộ';

  return ApiResponse.error(
    res,
    message,
    statusCode,
    config.isDevelopment ? { stack: err.stack } : null
  );
}

module.exports = errorHandler;
