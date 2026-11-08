const BaseModel = require('./BaseModel');
const crypto = require('crypto');
const { pool } = require('../config/db.config');

class AuthModel extends BaseModel {
  static async findRoleIdByName(name) {
    const { rows } = await this.query(
      'SELECT id FROM roles WHERE name = ? LIMIT 1',
      [name]
    );

    return rows[0]?.id || null;
  }

  static async createCustomer({ email, passwordHash }) {
    return this.execute(
      `INSERT INTO users (role_id, full_name, email, password_hash, status)
       SELECT id, 'Khách hàng', ?, ?, 'active'
       FROM roles
       WHERE name = 'customer'
       LIMIT 1`,
      [email, passwordHash]
    );
  }

  static async updateCustomerProfile(id, { fullName, phone, address }) {
    return this.execute(
      'UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ? AND status = ?',
      [fullName, phone, address, id, 'active']
    );
  }

  static async completeCustomerProfile(id, { fullName, phone, address }) {
    const connection = await pool.getConnection();
    try {
      await connection.beginTransaction();
      const [users] = await connection.execute(
        'SELECT id, full_name, email, phone, address FROM users WHERE id = ? AND status = ? FOR UPDATE',
        [id, 'active']
      );
      const user = users[0];
      if (!user) {
        await connection.rollback();
        return null;
      }
      if (user.phone || user.address) {
        const error = new Error('Hồ sơ khách hàng đã được hoàn tất.');
        error.statusCode = 409;
        throw error;
      }

      await connection.execute(
        'UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?',
        [fullName, phone, address, id]
      );

      const [existingCoupons] = await connection.execute(
        'SELECT code FROM coupons WHERE user_id = ? LIMIT 1',
        [id]
      );
      let voucherCode = existingCoupons[0]?.code;
      if (!voucherCode) {
        voucherCode = `FF50-${crypto.randomBytes(4).toString('hex').toUpperCase()}`;
        await connection.execute(
          `INSERT INTO coupons
            (user_id, restaurant_id, code, title, discount_type, discount_value,
             min_order_value, usage_limit, usage_count, start_date, end_date, is_active)
           VALUES (?, NULL, ?, 'Voucher hoàn tất hồ sơ - giảm 50.000đ',
              'fixed', 50000, 0, 1, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1)`,
          [id, voucherCode]
        );
      }

      await connection.commit();
      return {
        user: {
          id: String(user.id),
          fullName,
          email: user.email,
          phone,
          address,
        },
        voucher: {
          code: voucherCode,
          title: 'Voucher hoàn tất hồ sơ - giảm 50.000đ',
          discount: 50000,
          minOrder: 0,
        },
      };
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }
  }

  static async findByEmail(email) {
    const { rows } = await this.query(
      `SELECT u.id, u.full_name, u.email, u.phone, u.address, u.password_hash, u.status, r.name AS role
       FROM users u
       INNER JOIN roles r ON r.id = u.role_id
       WHERE u.email = ?
       LIMIT 1`,
      [email]
    );

    return rows[0] || null;
  }

  static async findActiveById(id) {
    const { rows } = await this.query(
      `SELECT u.id, u.full_name, u.email, u.phone, u.address, u.status, r.name AS role
       FROM users u
       INNER JOIN roles r ON r.id = u.role_id
       WHERE u.id = ? AND u.status = 'active'
       LIMIT 1`,
      [id]
    );

    return rows[0] || null;
  }
}

module.exports = AuthModel;
