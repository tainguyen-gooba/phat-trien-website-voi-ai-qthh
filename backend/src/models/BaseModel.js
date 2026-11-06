const { pool } = require('../config/db.config');

/**
 * BaseModel
 * Lớp trừu tượng cung cấp các hàm thao tác MySQL chuẩn bằng prepared statements
 */
class BaseModel {
  /**
   * Thực thi truy vấn SQL với tham số an toàn (tránh SQL Injection)
   */
  static async query(sql, params = []) {
    const [rows, fields] = await pool.query(sql, params);
    return { rows, fields };
  }

  /**
   * Thực thi lệnh INSERT/UPDATE/DELETE trả về OkPacket
   */
  static async execute(sql, params = []) {
    const [result] = await pool.execute(sql, params);
    return result;
  }

  /**
   * Tìm bản ghi theo ID
   */
  static async findById(tableName, id, primaryKey = 'id') {
    const sql = `SELECT * FROM \`${tableName}\` WHERE \`${primaryKey}\` = ? LIMIT 1`;
    const { rows } = await this.query(sql, [id]);
    return rows[0] || null;
  }

  /**
   * Đếm số lượng bản ghi trong một bảng
   */
  static async count(tableName, whereClause = '', params = []) {
    const sql = `SELECT COUNT(*) AS total FROM \`${tableName}\` ${whereClause ? `WHERE ${whereClause}` : ''}`;
    const { rows } = await this.query(sql, params);
    return rows[0]?.total || 0;
  }
}

module.exports = BaseModel;

