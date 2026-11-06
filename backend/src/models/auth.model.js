const BaseModel = require('./BaseModel');

class AuthModel extends BaseModel {
  static async findRoleIdByName(name) {
    const { rows } = await this.query(
      'SELECT id FROM roles WHERE name = ? LIMIT 1',
      [name]
    );

    return rows[0]?.id || null;
  }

  static async createCustomer({ fullName, email, phone, passwordHash }) {
    return this.execute(
      `INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
       SELECT id, ?, ?, ?, ?, 'active'
       FROM roles
       WHERE name = 'customer'
       LIMIT 1`,
      [fullName, email, phone, passwordHash]
    );
  }

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
