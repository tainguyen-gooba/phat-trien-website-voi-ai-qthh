const BaseController = require('./base.controller');
const HealthService = require('../services/health.service');
const HTTP_STATUS = require('../constants/httpStatusCodes');

class HealthController extends BaseController {
  /**
   * GET /api/v1/health
   * Trả về tình trạng sức khỏe của server API
   */
  async getSystemHealth(req, res, next) {
    try {
      const data = await HealthService.getFullHealthStatus();
      const isDbConnected = Boolean(data.database?.databaseConnected);
      const message = isDbConnected
        ? 'Hệ thống FASTFOOD API và Database đang hoạt động bình thường'
        : 'Backend API đang chạy, nhưng chưa kết nối được MySQL';

      // Trả về 200 để Frontend hiển thị chi tiết chẩn đoán từng phần (Server vs DB)
      return this.sendSuccess(res, message, data, HTTP_STATUS.OK);
    } catch (error) {
      next(error);
    }
  }

  /**
   * GET /api/v1/health/db
   * Kiểm tra riêng kết nối MySQL
   */
  async getDatabaseHealth(req, res, next) {
    try {
      const data = await HealthService.getDatabaseHealth();
      const isDbConnected = Boolean(data.databaseConnected);
      const message = isDbConnected
        ? 'Kết nối MySQL hoạt động bình thường'
        : 'Cơ sở dữ liệu MySQL chưa kết nối';

      return this.sendSuccess(res, message, data, HTTP_STATUS.OK);
    } catch (error) {
      next(error);
    }
  }
}

module.exports = new HealthController();

