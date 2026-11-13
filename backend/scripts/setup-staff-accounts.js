const path = require('path');
const bcrypt = require('bcryptjs');
const { pool } = require('../src/config/db.config');

async function setupRolesAndAccounts() {
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    // 1. Đảm bảo role staff tồn tại
    await connection.execute(`
      INSERT INTO roles (id, name, description)
      VALUES (5, 'staff', 'Thành viên ban quản trị / Nhân viên vận hành')
      ON DUPLICATE KEY UPDATE name = 'staff', description = 'Thành viên ban quản trị / Nhân viên vận hành'
    `);

    const passwordHash = await bcrypt.hash('123456', 10);

    // 2. Cập nhật mật khẩu cho Admin
    await connection.execute(`
      UPDATE users
      SET password_hash = ?, status = 'active'
      WHERE email = 'admin@fastfood.vn'
    `, [passwordHash]);

    // 3. Danh sách 4 tài khoản thành viên
    const staffAccounts = [
      {
        fullName: 'Nguyễn Văn Thành (Thành viên 1)',
        email: 'staff1@fastfood.vn',
        phone: '0911000001',
      },
      {
        fullName: 'Trần Thị Mai (Thành viên 2)',
        email: 'staff2@fastfood.vn',
        phone: '0911000002',
      },
      {
        fullName: 'Lê Hoàng Long (Thành viên 3)',
        email: 'staff3@fastfood.vn',
        phone: '0911000003',
      },
      {
        fullName: 'Phạm Thu Hương (Thành viên 4)',
        email: 'staff4@fastfood.vn',
        phone: '0911000004',
      },
    ];

    for (const acc of staffAccounts) {
      await connection.execute(`
        INSERT INTO users (role_id, full_name, email, phone, password_hash, address, status)
        VALUES (5, ?, ?, ?, ?, 'Buôn Ma Thuột, Đắk Lắk', 'active')
        ON DUPLICATE KEY UPDATE
          role_id = 5,
          full_name = VALUES(full_name),
          phone = VALUES(phone),
          password_hash = VALUES(password_hash),
          status = 'active'
      `, [acc.fullName, acc.email, acc.phone, passwordHash]);
    }

    // 4. Cập nhật các tài khoản khách với mật khẩu 123456
    const customerEmails = ['ab@gmail.com', 'anh@gmail.com', 'thin@gmail.com'];
    for (const email of customerEmails) {
      await connection.execute(`
        UPDATE users
        SET password_hash = ?, status = 'active'
        WHERE email = ?
      `, [passwordHash, email]);
    }

    await connection.commit();
    console.log('Tạo/cập nhật vai trò và các tài khoản thành công!');

    // Hiển thị danh sách tài khoản quản trị & thành viên
    const [rows] = await connection.query(`
      SELECT u.id, u.email, u.full_name, r.name AS role, u.status
      FROM users u
      JOIN roles r ON r.id = u.role_id
      WHERE r.name IN ('admin', 'staff')
      ORDER BY u.role_id ASC, u.id ASC
    `);
    console.log('DANH SÁCH TÀI KHOẢN QUẢN TRỊ & THÀNH VIÊN:');
    console.table(rows);

  } catch (error) {
    await connection.rollback();
    console.error('Lỗi khi thiết lập tài khoản:', error);
  } finally {
    connection.release();
    await pool.end();
  }
}

if (require.main === module) {
  setupRolesAndAccounts();
}

module.exports = { setupRolesAndAccounts };

