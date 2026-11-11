const { pool } = require('../src/config/db.config');

async function initChatTables() {
  try {
    console.log('Creating chat tables in MySQL...');

    await pool.query(`
      CREATE TABLE IF NOT EXISTS ai_chat_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL UNIQUE,
        user_id BIGINT UNSIGNED NULL,
        customer_name VARCHAR(150) NULL DEFAULT 'Khách hàng',
        title VARCHAR(255) NOT NULL DEFAULT 'Hội thoại mới',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chat_session_user (user_id),
        INDEX idx_chat_session_created (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    console.log('Successfully created ai_chat_sessions!');

    await pool.query(`
      CREATE TABLE IF NOT EXISTS ai_chat_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        sender ENUM('user', 'assistant', 'system') NOT NULL DEFAULT 'user',
        message TEXT NOT NULL,
        metadata LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chat_msg_session (session_id),
        INDEX idx_chat_msg_user (user_id),
        INDEX idx_chat_msg_created (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    console.log('Successfully created ai_chat_messages!');

    const [tables] = await pool.query('SHOW TABLES LIKE "ai_chat%"');
    console.log('Found tables in DB:', tables);

    await pool.end();
    process.exit(0);
  } catch (err) {
    console.error('Failed to init chat tables:', err);
    process.exit(1);
  }
}

initChatTables();

