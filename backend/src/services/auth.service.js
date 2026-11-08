const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const config = require('../config/app.config');
const AuthModel = require('../models/auth.model');

class AuthService {
  static createSession(user) {
    const safeUser = {
      id: String(user.id),
      fullName: user.full_name,
      email: user.email,
      phone: user.phone,
      address: user.address,
      role: user.role,
    };
    const token = jwt.sign(
      { id: safeUser.id, email: safeUser.email, role: safeUser.role },
      config.jwt.secret,
      { expiresIn: config.jwt.expiresIn }
    );

    return { token, user: safeUser };
  }

  static async login(email, password) {
    const user = await AuthModel.findByEmail(email);
    if (!user || user.status !== 'active') return null;

    const passwordMatches = await bcrypt.compare(password, user.password_hash);
    if (!passwordMatches) return null;

    return this.createSession(user);
  }

  static async register({ email, password }) {
    const existingUser = await AuthModel.findByEmail(email);
    if (existingUser) {
      const error = new Error('Email đã được đăng ký.');
      error.statusCode = 409;
      throw error;
    }

    const roleId = await AuthModel.findRoleIdByName('customer');
    if (!roleId) {
      throw new Error('Chưa cấu hình vai trò khách hàng. Hãy khởi tạo database trước.');
    }

    const passwordHash = await bcrypt.hash(password, 10);
    const result = await AuthModel.createCustomer({ email, passwordHash });

    if (result.affectedRows !== 1) {
      throw new Error('Không thể tạo tài khoản khách hàng. Hãy kiểm tra dữ liệu vai trò.');
    }

    return this.createSession({
      id: result.insertId,
      full_name: 'Khách hàng',
      email,
      phone: null,
      role: 'customer',
    });
  }

  static async updateCustomerProfile(id, { fullName, phone, address }) {
    const { rows: existingPhone } = await AuthModel.query(
      'SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1',
      [phone, id]
    );
    if (existingPhone.length) {
      const error = new Error('Số điện thoại đã được đăng ký.');
      error.statusCode = 409;
      throw error;
    }

    await AuthModel.updateCustomerProfile(id, { fullName, phone, address });
    const user = await AuthModel.findActiveById(id);
    if (!user) return null;

    return {
      id: String(user.id),
      fullName: user.full_name,
      email: user.email,
      phone: user.phone,
      address: user.address,
      role: user.role,
    };
  }

  static async completeCustomerProfile(id, profile) {
    const { rows } = await AuthModel.query(
      'SELECT phone, address FROM users WHERE id = ? AND status = ? LIMIT 1',
      [id, 'active']
    );
    if (!rows.length) return null;
    if (rows[0].phone || rows[0].address) {
      const error = new Error('Hồ sơ khách hàng đã được hoàn tất.');
      error.statusCode = 409;
      throw error;
    }

    return AuthModel.completeCustomerProfile(id, profile);
  }

  static async getActiveUser(id) {
    const user = await AuthModel.findActiveById(id);
    if (!user) return null;

    return {
      id: String(user.id),
      fullName: user.full_name,
      email: user.email,
      phone: user.phone,
      address: user.address,
      role: user.role,
    };
  }
}

module.exports = AuthService;
