/**
 * FASTFOOD Standalone Database Connection Tester
 * Chạy lệnh: npm run db:test
 */
const { testConnection, closePool } = require('../src/config/db.config');
const logger = require('../src/utils/logger');

async function run() {
  logger.info('====================================================');
  logger.info('      KIỂM TRA KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL          ');
  logger.info('====================================================');

  const result = await testConnection();

  if (result.connected) {
    logger.info(' Trạng thái: KẾT NỐI THÀNH CÔNG');
    logger.info(` Máy chủ: ${result.host}`);
    logger.info(` Cơ sở dữ liệu: ${result.database}`);
    logger.info(` Phiên bản MySQL: ${result.serverVersion}`);
  } else {
    logger.error(' Trạng thái: KẾT NỐI THẤT BẠI');
    logger.error(` Lỗi: ${result.message}`);
    logger.warn(` Gợi ý khắc phục: ${result.hint}`);
  }

  await closePool();
  process.exit(result.connected ? 0 : 1);
}

run();

