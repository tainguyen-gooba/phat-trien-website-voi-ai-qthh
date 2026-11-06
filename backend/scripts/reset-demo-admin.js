const bcrypt = require('bcryptjs');
const { pool, closePool } = require('../src/config/db.config');

async function synchronizeDemoAdmin() {
  const email = 'admin@fastfood.vn';
  const fullName = 'Hệ Thống Quản Trị';
  const phone = '0901000001';
  const passwordHash = await bcrypt.hash('Admin@123456', 10);
  const connection = await pool.getConnection();

  try {
    await connection.beginTransaction();
    await connection.execute(
      `INSERT INTO roles (name, description)
       VALUES ('admin', 'Quản trị viên toàn quyền hệ thống')
       ON DUPLICATE KEY UPDATE description = VALUES(description)`
    );

    const [roles] = await connection.execute(
      'SELECT id FROM roles WHERE name = ? LIMIT 1',
      ['admin']
    );
    const roleId = roles[0]?.id;
    if (!roleId) {
      throw new Error('Không thể tìm thấy hoặc tạo vai trò admin.');
    }

    const [users] = await connection.execute(
      'SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE',
      [email]
    );
    if (users[0]) {
      await connection.execute(
        `UPDATE users
         SET role_id = ?, full_name = ?, phone = ?, password_hash = ?, status = 'active'
         WHERE id = ?`,
        [roleId, fullName, phone, passwordHash, users[0].id]
      );
    } else {
      await connection.execute(
        `INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
         VALUES (?, ?, ?, ?, ?, 'active')`,
        [roleId, fullName, email, phone, passwordHash]
      );
    }

    await connection.commit();
    console.log(`Đã đồng bộ tài khoản admin ${email} với mật khẩu mặc định.`);
  } catch (error) {
    try {
      await connection.rollback();
    } catch (rollbackError) {
      console.error('[Admin sync rollback failed]', rollbackError.message);
    }
    throw error;
  } finally {
    connection.release();
  }
}

synchronizeDemoAdmin()
  .catch((error) => {
    console.error('[Admin sync failed]', error.message);
    process.exitCode = 1;
  })
  .finally(closePool);
