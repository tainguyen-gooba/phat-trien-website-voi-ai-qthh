const BaseModel = require('./BaseModel');
const config = require('../config/app.config');

class HealthModel extends BaseModel {
  /**
   * Kiểm tra truy vấn ping tới MySQL
   */
  static async checkDatabase() {
    const startTime = Date.now();
    const { rows } = await this.query('SELECT 1 + 1 AS test_sum, NOW() AS db_time, VERSION() AS version');
    const responseTime = Date.now() - startTime;

    // Lấy danh sách các bảng hiện có trong database
    const { rows: tableRows } = await this.query(
      'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
      [config.db.database]
    );

    const tables = tableRows.map((t) => t.TABLE_NAME);

    return {
      connected: true,
      database: config.db.database,
      version: rows[0]?.version,
      serverTime: rows[0]?.db_time,
      latencyMs: responseTime,
      totalTables: tables.length,
      tables,
    };
  }
}

module.exports = HealthModel;

