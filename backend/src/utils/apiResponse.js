const HTTP_STATUS = require('../constants/httpStatusCodes');

/**
 * Standardized API Response Utilities
 * Đảm bảo mọi phản hồi từ REST API đều tuân thủ chuẩn thống nhất:
 * {
 *   success: true | false,
 *   message: string,
 *   data: any,
 *   error: any,
 *   meta: object (pagination, timestamp, v.v.)
 * }
 */
class ApiResponse {
  /**
   * Phản hồi thành công (200 OK mặc định hoặc 201 Created)
   */
  static success(res, message = 'Thành công', data = null, statusCode = HTTP_STATUS.OK, meta = null) {
    const payload = {
      success: true,
      message,
      data,
      timestamp: new Date().toISOString(),
    };

    if (meta) {
      payload.meta = meta;
    }

    return res.status(statusCode).json(payload);
  }

  /**
   * Phản hồi tạo mới thành công (201 Created)
   */
  static created(res, message = 'Khởi tạo dữ liệu thành công', data = null) {
    return this.success(res, message, data, HTTP_STATUS.CREATED);
  }

  /**
   * Phản hồi lỗi
   */
  static error(res, message = 'Đã có lỗi xảy ra', statusCode = HTTP_STATUS.INTERNAL_SERVER_ERROR, errors = null) {
    const payload = {
      success: false,
      message,
      timestamp: new Date().toISOString(),
    };

    if (errors) {
      payload.errors = errors;
    }

    return res.status(statusCode).json(payload);
  }

  /**
   * Phản hồi dữ liệu phân trang
   */
  static paginate(res, message = 'Lấy danh sách thành công', items = [], pagination = {}) {
    const { page = 1, limit = 10, total = 0 } = pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    return this.success(res, message, items, HTTP_STATUS.OK, {
      page: Number(page),
      limit: Number(limit),
      total: Number(total),
      totalPages,
      hasNext: page < totalPages,
      hasPrev: page > 1,
    });
  }
}

module.exports = ApiResponse;

