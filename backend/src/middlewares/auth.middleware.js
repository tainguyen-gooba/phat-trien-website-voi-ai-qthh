const jwt = require('jsonwebtoken');
const config = require('../config/app.config');
const ApiResponse = require('../utils/apiResponse');
const HTTP_STATUS = require('../constants/httpStatusCodes');

/**
 * Authentication Middleware
 * Xác thực Bearer Token JWT từ header Authorization
 */
function authenticate(req, res, next) {
  const authHeader = req.headers.authorization;

  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return ApiResponse.error(
      res,
      'Yêu cầu xác thực: Token không được cung cấp hoặc sai định dạng',
      HTTP_STATUS.UNAUTHORIZED
    );
  }

  const token = authHeader.split(' ')[1];

  try {
    const decoded = jwt.verify(token, config.jwt.secret);
    req.user = decoded; // { id, role, email, ... }
    next();
  } catch (error) {
    if (error.name === 'TokenExpiredError') {
      return ApiResponse.error(res, 'Phiên đăng nhập đã hết hạn', HTTP_STATUS.UNAUTHORIZED);
    }
    return ApiResponse.error(res, 'Mã xác thực không hợp lệ', HTTP_STATUS.UNAUTHORIZED);
  }
}

/**
 * Role-Based Access Control (RBAC) Middleware
 * @param  {...string} allowedRoles Danh sách role được phép truy cập
 */
function authorize(...allowedRoles) {
  return (req, res, next) => {
    if (!req.user) {
      return ApiResponse.error(res, 'Người dùng chưa được xác thực', HTTP_STATUS.UNAUTHORIZED);
    }

    if (!allowedRoles.includes(req.user.role)) {
      return ApiResponse.error(
        res,
        'Bạn không có quyền thực hiện hành động này',
        HTTP_STATUS.FORBIDDEN
      );
    }

    next();
  };
}

module.exports = {
  authenticate,
  authorize,
};

