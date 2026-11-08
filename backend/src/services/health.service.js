const HealthModel = require('../models/health.model');
const os = require('os');
const config = require('../config/app.config');

class HealthService {
  /**
   * Lấy thông tin tổng quan trạng thái hệ thống và Node.js
   */
  static getSystemHealth() {
    const memoryUsage = process.memoryUsage();
    return {
      status: 'UP',
      service: 'FASTFOOD API Server',
      environment: config.env,
      uptimeSeconds: Math.floor(process.uptime()),
      timestamp: new Date().toISOString(),
      system: {
        platform: process.platform,
        nodeVersion: process.version,
        cpuCount: os.cpus().length,
        freeMemoryMB: Math.round(os.freemem() / (1024 * 1024)),
        totalMemoryMB: Math.round(os.totalmem() / (1024 * 1024)),
        memoryUsageMB: {
          rss: Math.round(memoryUsage.rss / (1024 * 1024)),
          heapTotal: Math.round(memoryUsage.heapTotal / (1024 * 1024)),
          heapUsed: Math.round(memoryUsage.heapUsed / (1024 * 1024)),
        },
      },
    };
  }

  /**
   * Kiểm tra tình trạng kết nối tới cơ sở dữ liệu MySQL
   */
  static async getDatabaseHealth() {
    try {
      const dbInfo = await HealthModel.checkDatabase();
      return {
        databaseConnected: true,
        ...dbInfo,
      };
    } catch (error) {
      return {
        databaseConnected: false,
        error: error.message,
        errorCode: error.code || 'UNKNOWN_DB_ERROR',
        hint: error.code === 'ECONNREFUSED'
          ? 'MySQL service chưa được chạy hoặc cấu hình sai port trong .env.'
          : error.code === 'ER_BAD_DB_ERROR'
          ? `Chưa tạo database '${config.db.database}'. Chạy 'npm run db:init' để khởi tạo.`
          : 'Vui lòng kiểm tra lại cấu hình DB_HOST, DB_USER, DB_PASSWORD trong .env.',
      };
    }
  }

  /**
   * Lấy toàn bộ trạng thái hệ thống + DB
   */
  static async getFullHealthStatus() {
    const system = this.getSystemHealth();
    const database = await this.getDatabaseHealth();

    return {
      ...system,
      database,
    };
  }
}

module.exports = HealthService;

