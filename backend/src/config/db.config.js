const mysql = require('mysql2/promise');
const config = require('./app.config');

/**
 * MySQL Connection Pool instance
 * Sử dụng connection pool để tái sử dụng kết nối và tối ưu hiệu năng
 */
const pool = mysql.createPool({
  host: config.db.host,
  port: config.db.port,
  user: config.db.user,
  password: config.db.password,
  database: config.db.database,
  waitForConnections: config.db.waitForConnections,
  connectionLimit: config.db.connectionLimit,
  queueLimit: config.db.queueLimit,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
  charset: 'utf8mb4',
});

/**
 * Kiểm tra kết nối tới MySQL database
 * @returns {Promise<{connected: boolean, message: string, serverVersion?: string}>}
 */
async function testConnection() {
  let connection;
  try {
    connection = await pool.getConnection();
    const [rows] = await connection.query('SELECT VERSION() AS version');
    const version = rows[0]?.version || 'Unknown';
    return {
      connected: true,
      message: 'Kết nối MySQL thành công',
      serverVersion: version,
      database: config.db.database,
      host: `${config.db.host}:${config.db.port}`,
    };
  } catch (error) {
    const errorDetail = error.message || error.code || 'Không có phản hồi từ cổng MySQL';
    return {
      connected: false,
      message: `Không thể kết nối MySQL (${errorDetail})`,
      errorCode: error.code || 'UNKNOWN_ERROR',
      hint: error.code === 'ECONNREFUSED'
        ? 'Hãy kiểm tra dịch vụ MySQL (XAMPP / MySQL Service) đã được bật trên cổng 3306 chưa.'
        : error.code === 'ER_BAD_DB_ERROR'
        ? `Database '${config.db.database}' chưa được tạo. Hãy chạy 'npm run db:init' để khởi tạo.`
        : 'Kiểm tra lại thông tin DB_HOST, DB_USER, DB_PASSWORD trong file .env.',
    };
  } finally {
    if (connection) connection.release();
  }
}

/**
 * Đóng connection pool an toàn khi tắt server
 */
async function closePool() {
  try {
    await pool.end();
    console.log('[Database] MySQL Connection Pool đã được đóng an toàn.');
  } catch (error) {
    console.error('[Database] Lỗi khi đóng Connection Pool:', error.message);
  }
}

module.exports = {
  pool,
  testConnection,
  closePool,
};
