const ApiResponse = require('../utils/apiResponse');
const HTTP_STATUS = require('../constants/httpStatusCodes');

/**
 * Middleware bắt các route không tồn tại (404 Not Found)
 */
function notFoundHandler(req, res, next) {
  return ApiResponse.error(
    res,
    `Không tìm thấy tài nguyên: ${req.method} ${req.originalUrl}`,
    HTTP_STATUS.NOT_FOUND,
    {
      path: req.originalUrl,
      method: req.method,
      suggestion: 'Vui lòng kiểm tra lại đường dẫn API hoặc xem tài liệu tại /api/v1/health',
    }
  );
}

module.exports = notFoundHandler;

