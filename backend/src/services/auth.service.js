const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const config = require('../config/app.config');
const AuthModel = require('../models/auth.model');

class AuthService {
  static async login(email, password) {
    const user = await AuthModel.findByEmail(email);
    if (!user || user.status !== 'active') return null;

    const passwordMatches = await bcrypt.compare(password, user.password_hash);
    if (!passwordMatches) return null;

    const safeUser = {
      id: String(user.id),
      fullName: user.full_name,
      email: user.email,
      phone: user.phone,
      role: user.role,
    };
    const token = jwt.sign(
      { id: safeUser.id, email: safeUser.email, role: safeUser.role },
      config.jwt.secret,
      { expiresIn: config.jwt.expiresIn }
    );

    return { token, user: safeUser };
  }

  static async getActiveUser(id) {
    const user = await AuthModel.findActiveById(id);
    if (!user) return null;

    return {
      id: String(user.id),
      fullName: user.full_name,
      email: user.email,
      phone: user.phone,
      role: user.role,
    };
  }
}

module.exports = AuthService;
