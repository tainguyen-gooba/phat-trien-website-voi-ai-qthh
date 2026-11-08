const app = require('./app');
const http = require('http');
const { createRealtimeServer } = require('./realtime');
const config = require('./config/app.config');
const { testConnection, closePool } = require('./config/db.config');
const logger = require('./utils/logger');

const PORT = config.port;
const httpServer = http.createServer(app);
app.set('io', createRealtimeServer(httpServer));

/**
 * Khởi động máy chủ FASTFOOD Express
 */
const server = httpServer.listen(PORT, async () => {
  logger.info(`=======================================================`);
  logger.info(`   FASTFOOD E-Commerce API Server Started Successfully `);
  logger.info(`   Môi trường : ${config.env.toUpperCase()}            `);
  logger.info(`   Địa chỉ    : http://localhost:${PORT}               `);
  logger.info(`   API V1     : http://localhost:${PORT}/api/v1        `);
  logger.info(`   Health DB  : http://localhost:${PORT}/api/v1/health `);
  logger.info(`   Frontend UI: http://localhost:${PORT}/index.html    `);
  logger.info(`=======================================================`);

  // Kiểm tra kết nối cơ sở dữ liệu MySQL khi server start
  logger.info('Đang kiểm tra kết nối tới MySQL...');
  const dbStatus = await testConnection();

  if (dbStatus.connected) {
    logger.info(`[Database] Đã kết nối thành công tới MySQL (${dbStatus.host})!`);
    logger.info(`[Database] Phiên bản: ${dbStatus.serverVersion} | Tên CSDL: ${dbStatus.database}`);
  } else {
    logger.warn(`[Database] CẢNH BÁO: ${dbStatus.message}`);
    logger.warn(`[Database] Gợi ý: ${dbStatus.hint}`);
  }
});

/**
 * Graceful Shutdown handling
 */
const shutdown = async (signal) => {
  logger.info(`Nhận tín hiệu ${signal}. Đang tiến hành đóng server an toàn...`);
  server.close(async () => {
    logger.info('HTTP Server đã ngừng nhận request.');
    await closePool();
    logger.info('Toàn bộ tiến trình FASTFOOD đã tắt an toàn.');
    process.exit(0);
  });

  // Ép buộc thoát nếu quá 10 giây
  setTimeout(() => {
    logger.error('Quá thời gian chờ shutdown, ép buộc dừng tiến trình!');
    process.exit(1);
  }, 10000);
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));

module.exports = server;
