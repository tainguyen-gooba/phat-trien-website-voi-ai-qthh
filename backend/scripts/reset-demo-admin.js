const bcrypt = require('bcryptjs');
const config = require('../src/config/app.config');
const { pool, closePool } = require('../src/config/db.config');

async function resetDemoAdminPassword() {
  const email = 'admin@fastfood.vn';
  const passwordHash = await bcrypt.hash('Admin@123456', 10);
  const [result] = await pool.execute(
    `UPDATE users
     SET password_hash = ?
     WHERE email = ?
       AND role_id = (SELECT id FROM roles WHERE name = 'admin' LIMIT 1)`,
    [passwordHash, email]
  );

  if (result.affectedRows === 0) {
    throw new Error(
      `Không tìm thấy tài khoản admin '${email}'. Hãy kiểm tra database '${config.db.database}' đã có dữ liệu seed chưa.`
    );
  }

  console.log(`Đã đặt lại mật khẩu cho ${email}. Dữ liệu khác không bị thay đổi.`);
}

resetDemoAdminPassword()
  .catch((error) => {
    console.error('[Admin password reset failed]', error.message);
    process.exitCode = 1;
  })
  .finally(closePool);
