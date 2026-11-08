const mysql = require('mysql2/promise');
const config = require('../src/config/app.config');

async function migrateCustomerProfile() {
  let connection;
  try {
    connection = await mysql.createConnection({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      database: config.db.database,
    });

    const [columns] = await connection.query(
      `SELECT COLUMN_NAME, IS_NULLABLE
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'
         AND COLUMN_NAME IN ('phone', 'address')`,
      [config.db.database]
    );
    const phoneColumn = columns.find((column) => column.COLUMN_NAME === 'phone');
    const addressColumn = columns.find((column) => column.COLUMN_NAME === 'address');

    if (!phoneColumn) {
      throw new Error('Không tìm thấy cột users.phone trong database.');
    }
    if (phoneColumn.IS_NULLABLE !== 'YES') {
      await connection.query(
        "ALTER TABLE users MODIFY COLUMN phone VARCHAR(20) NULL COMMENT 'Số điện thoại'"
      );
    }
    if (!addressColumn) {
      await connection.query(
        "ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL COMMENT 'Địa chỉ mặc định của khách hàng' AFTER phone"
      );
    }

    const [couponColumns] = await connection.query(
      `SELECT COLUMN_NAME
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'coupons' AND COLUMN_NAME = 'user_id'`,
      [config.db.database]
    );
    if (!couponColumns.length) {
      await connection.query(
        'ALTER TABLE coupons ADD COLUMN user_id BIGINT UNSIGNED NULL COMMENT \'NULL cho voucher chung, có ID nếu voucher được cấp riêng\' AFTER id'
      );
    }

    const [couponIndexes] = await connection.query(
      `SELECT INDEX_NAME
       FROM information_schema.STATISTICS
       WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'coupons' AND INDEX_NAME = 'uq_coupons_user_id'`,
      [config.db.database]
    );
    if (!couponIndexes.length) {
      await connection.query(
        'ALTER TABLE coupons ADD UNIQUE INDEX uq_coupons_user_id (user_id)'
      );
    }

    const [couponConstraints] = await connection.query(
      `SELECT CONSTRAINT_NAME
       FROM information_schema.TABLE_CONSTRAINTS
       WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'coupons'
         AND CONSTRAINT_NAME = 'fk_coupons_user' AND CONSTRAINT_TYPE = 'FOREIGN KEY'`,
      [config.db.database]
    );
    if (!couponConstraints.length) {
      await connection.query(
        'ALTER TABLE coupons ADD CONSTRAINT fk_coupons_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE'
      );
    }

    console.log('Đã cập nhật users và coupons cho hồ sơ khách hàng và voucher cá nhân.');
  } catch (error) {
    console.error('[Customer profile migration failed]', error.message);
    process.exitCode = 1;
  } finally {
    if (connection) await connection.end();
  }
}

migrateCustomerProfile();
