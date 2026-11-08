const ApiResponse = require('../utils/apiResponse');

/**
 * Base Controller
 * Lớp cha cho tất cả các controller trong hệ thống
 */
class BaseController {
  constructor() {
    this.sendSuccess = ApiResponse.success.bind(ApiResponse);
    this.sendCreated = ApiResponse.created.bind(ApiResponse);
    this.sendError = ApiResponse.error.bind(ApiResponse);
    this.sendPaginated = ApiResponse.paginate.bind(ApiResponse);
  }
}

module.exports = BaseController;

