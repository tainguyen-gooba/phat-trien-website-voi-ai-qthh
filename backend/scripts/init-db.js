/**
 * FASTFOOD Database Initializer & Migration Script
 * Chạy lệnh: npm run db:init
 * Tự động tạo cơ sở dữ liệu, 23 bảng và nạp dữ liệu mẫu
 */
const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');
const config = require('../src/config/app.config');
const logger = require('../src/utils/logger');

async function initDatabase() {
  logger.info('====================================================');
  logger.info('       KHỞI TẠO CƠ SỞ DỮ LIỆU DỰ ÁN FASTFOOD        ');
  logger.info('====================================================');

  let connection;
  try {
    // 1. Kết nối tới MySQL Server (chưa chỉ định database)
    logger.info(`Đang kết nối tới máy chủ MySQL tại ${config.db.host}:${config.db.port}...`);
    connection = await mysql.createConnection({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      multipleStatements: true,
    });
    logger.info('Kết nối tới MySQL Server thành công!');

    // 2. Tạo database nếu chưa có
    logger.info(`Kiểm tra và tạo database '${config.db.database}'...`);
    await connection.query(
      `CREATE DATABASE IF NOT EXISTS \`${config.db.database}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
    );
    await connection.query(`USE \`${config.db.database}\`;`);
    logger.info(`Đã chọn database '${config.db.database}'.`);

    // 3. Đọc và thực thi schema.sql
    const schemaPath = path.resolve(__dirname, '../../database/schema.sql');
    if (fs.existsSync(schemaPath)) {
      logger.info('Đang nạp cấu trúc 23 bảng từ database/schema.sql...');
      const schemaSql = fs.readFileSync(schemaPath, 'utf8');
      await connection.query(schemaSql);
      logger.info('Đã tạo thành công 23 bảng và thiết lập quan hệ khóa ngoại/indexes!');
    } else {
      throw new Error(`Không tìm thấy file schema tại ${schemaPath}`);
    }

    // 4. Đọc và thực thi seeds.sql
    const seedsPath = path.resolve(__dirname, '../../database/seeds.sql');
    if (fs.existsSync(seedsPath)) {
      logger.info('Đang nạp dữ liệu mẫu từ database/seeds.sql...');
      const seedsSql = fs.readFileSync(seedsPath, 'utf8');
      await connection.query(seedsSql);
      logger.info('Đã nạp dữ liệu mẫu thành công!');
    }

    // 5. Thống kê bảng
    const [tables] = await connection.query(
      'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
      [config.db.database]
    );

    logger.info('====================================================');
    logger.info(` HOÀN TẤT KHỞI TẠO! Tổng số bảng đã tạo: ${tables.length}`);
    tables.forEach((t, i) => {
      logger.info(`  ${i + 1}. ${t.TABLE_NAME}`);
    });
    logger.info('====================================================');
  } catch (error) {
    logger.error('LỖI KHỞI TẠO DATABASE:', error.message);
    if (error.code === 'ECONNREFUSED') {
      logger.warn('Gợi ý: Hãy bật MySQL service (XAMPP / MySQL Server) trước khi chạy lệnh.');
    }
    process.exit(1);
  } finally {
    if (connection) {
      await connection.end();
    }
  }
}

initDatabase();

