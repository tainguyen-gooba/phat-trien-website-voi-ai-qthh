const BaseModel = require('./BaseModel');

class AuthModel extends BaseModel {
  static async findByEmail(email) {
    const { rows } = await this.query(
      `SELECT u.id, u.full_name, u.email, u.phone, u.password_hash, u.status, r.name AS role
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
      `SELECT u.id, u.full_name, u.email, u.phone, u.status, r.name AS role
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
