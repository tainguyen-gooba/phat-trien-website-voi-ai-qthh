const mysql = require('mysql2/promise');
const config = require('../src/config/app.config');

async function migrateRealtimeTables() {
  let connection;
  try {
    connection = await mysql.createConnection({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      database: config.db.database,
    });

    await connection.query(`
      CREATE TABLE IF NOT EXISTS website_content (
        collection_name VARCHAR(50) PRIMARY KEY,
        content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await connection.query(`
      CREATE TABLE IF NOT EXISTS chat_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id BIGINT UNSIGNED NOT NULL,
        sender_id BIGINT UNSIGNED NOT NULL,
        sender_role ENUM('admin', 'customer') NOT NULL,
        message VARCHAR(1000) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_chat_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_chat_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
        INDEX idx_chat_customer_id (customer_id, id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await connection.query(`
      ALTER TABLE chat_messages
        MODIFY sender_id BIGINT UNSIGNED NULL,
        MODIFY sender_role ENUM('admin', 'customer', 'ai') NOT NULL
    `);
    await connection.query(`
      CREATE TABLE IF NOT EXISTS chat_automation (
        customer_id BIGINT UNSIGNED PRIMARY KEY,
        paused_until TIMESTAMP NULL DEFAULT NULL,
        CONSTRAINT fk_chat_automation_customer
          FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await connection.query(`
      CREATE TABLE IF NOT EXISTS realtime_orders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(30) NOT NULL UNIQUE,
        user_id BIGINT UNSIGNED NOT NULL,
        order_data JSON NOT NULL,
        status ENUM('pending', 'preparing', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_realtime_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        INDEX idx_realtime_orders_user (user_id, id),
        INDEX idx_realtime_orders_status (status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await connection.query(`
      CREATE TABLE IF NOT EXISTS realtime_payments (
        order_code VARCHAR(30) PRIMARY KEY,
        payment_method ENUM('banking', 'momo') NOT NULL,
        request_id VARCHAR(64) NOT NULL,
        amount BIGINT UNSIGNED NOT NULL,
        status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        provider_transaction_id VARCHAR(100) NULL UNIQUE,
        payment_url TEXT NULL,
        qr_url TEXT NULL,
        paid_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_realtime_payments_order
          FOREIGN KEY (order_code) REFERENCES realtime_orders (order_code) ON DELETE CASCADE,
        INDEX idx_realtime_payments_status (status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    console.log('Đã tạo/kiểm tra các bảng realtime và thanh toán.');
  } catch (error) {
    console.error('[Realtime migration failed]', error.message);
    process.exitCode = 1;
  } finally {
    if (connection) await connection.end();
  }
}

migrateRealtimeTables();
