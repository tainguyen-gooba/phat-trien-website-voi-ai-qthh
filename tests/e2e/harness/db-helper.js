/**
 * db-helper.js - Zero-NPM Database Bridge for FastFood E2E Tests.
 * Executes queries directly against MariaDB/MySQL (fastfood_db on port 3301)
 * via child_process invoking PHP CLI and config/database.php.
 */

const { execFileSync } = require('child_process');
const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

const PHP_BRIDGE_SCRIPT = `
require 'config/database.php';
$input = json_decode(base64_decode($argv[1]), true);
if (!$input || empty($input['sql'])) {
    echo json_encode(['success' => false, 'error' => 'Missing SQL payload']);
    exit(0);
}

try {
    $db = getDB();
    $sql = trim($input['sql']);
    $params = $input['params'] ?? [];

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if (preg_match('/^\\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\\b/i', $sql)) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
    } else {
        $rowCount = $stmt->rowCount();
        $lastId = $db->lastInsertId();
        echo json_encode(['success' => true, 'rowCount' => $rowCount, 'lastInsertId' => $lastId, 'data' => []]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit(0);
}
`;

class DbHelper {
  constructor(projectRoot = PROJECT_ROOT) {
    this.projectRoot = projectRoot;
  }

  /**
   * Executes a parameterized SQL query via PHP CLI PDO bridge.
   *
   * @param {string} sql - SQL query string with ? placeholders
   * @param {Array} [params=[]] - Array of bind parameters
   * @returns {Array} Array of row objects for SELECT/SHOW, or empty array for mutations
   */
  query(sql, params = []) {
    const payload = JSON.stringify({ sql, params });
    const b64 = Buffer.from(payload, 'utf8').toString('base64');

    let output;
    try {
      output = execFileSync('php', ['-r', PHP_BRIDGE_SCRIPT, b64], {
        cwd: this.projectRoot,
        encoding: 'utf8',
        maxBuffer: 10 * 1024 * 1024,
        windowsHide: true
      });
    } catch (err) {
      throw new Error(`PHP execution error: ${err.message} | SQL: ${sql}`);
    }

    let parsed;
    try {
      parsed = JSON.parse(output.trim());
    } catch (e) {
      throw new Error(`Failed to parse DB bridge output: ${output.slice(0, 200)} | SQL: ${sql}`);
    }

    if (!parsed.success) {
      throw new Error(`DB Error: ${parsed.error} | SQL: ${sql}`);
    }

    return parsed.data || [];
  }

  /**
   * Fetches user loyalty information (tier, points_balance, total_spent).
   *
   * @param {number|string} userId
   * @returns {{ member_tier: string, points_balance: number, total_spent: number }|null}
   */
  getUserLoyalty(userId) {
    try {
      const rows = this.query(
        'SELECT member_tier, points_balance, total_spent FROM users WHERE id = ? LIMIT 1',
        [userId]
      );
      if (!rows || rows.length === 0) return null;
      const r = rows[0];
      return {
        member_tier: r.member_tier,
        points_balance: r.points_balance !== null && r.points_balance !== undefined ? Number(r.points_balance) : 0,
        total_spent: r.total_spent !== null && r.total_spent !== undefined ? Number(r.total_spent) : 0
      };
    } catch (err) {
      // Column may not exist prior to migration
      if (err.message && err.message.includes('Unknown column')) {
        return null;
      }
      throw err;
    }
  }

  /**
   * Sets user loyalty state for test fixture setup.
   *
   * @param {number|string} userId
   * @param {{ member_tier?: string, points_balance?: number, total_spent?: number }} loyaltyData
   */
  setUserLoyalty(userId, { member_tier, points_balance, total_spent } = {}) {
    const updates = [];
    const params = [];

    if (member_tier !== undefined) {
      updates.push('member_tier = ?');
      params.push(member_tier);
    }
    if (points_balance !== undefined) {
      updates.push('points_balance = ?');
      params.push(points_balance);
    }
    if (total_spent !== undefined) {
      updates.push('total_spent = ?');
      params.push(total_spent);
    }

    if (updates.length === 0) return;
    params.push(userId);

    return this.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, params);
  }

  /**
   * Fetches order loyalty columns and current status.
   *
   * @param {string} orderCode
   * @returns {{ points_earned: number, points_used: number, points_discount: number, order_status: string }|null}
   */
  getOrderLoyalty(orderCode) {
    try {
      const rows = this.query(
        'SELECT points_earned, points_used, points_discount, order_status FROM orders WHERE order_code = ? LIMIT 1',
        [orderCode]
      );
      if (!rows || rows.length === 0) return null;
      const r = rows[0];
      return {
        points_earned: r.points_earned !== null && r.points_earned !== undefined ? Number(r.points_earned) : 0,
        points_used: r.points_used !== null && r.points_used !== undefined ? Number(r.points_used) : 0,
        points_discount: r.points_discount !== null && r.points_discount !== undefined ? Number(r.points_discount) : 0,
        order_status: r.order_status
      };
    } catch (err) {
      if (err.message && err.message.includes('Unknown column')) {
        return null;
      }
      throw err;
    }
  }

  /**
   * Retrieves all point transaction records for a user.
   *
   * @param {number|string} userId
   * @returns {Array<object>}
   */
  getTransactions(userId) {
    try {
      return this.query(
        'SELECT * FROM point_transactions WHERE user_id = ? ORDER BY id ASC',
        [userId]
      );
    } catch (err) {
      if (err.message && (err.message.includes("Table") || err.message.includes("doesn't exist"))) {
        return [];
      }
      throw err;
    }
  }

  /**
   * Retrieves all coupon records claimed/owned by a user.
   *
   * @param {number|string} userId
   * @returns {Array<object>}
   */
  getCoupons(userId) {
    try {
      return this.query(
        'SELECT * FROM coupons WHERE user_id = ? ORDER BY id DESC',
        [userId]
      );
    } catch (err) {
      throw err;
    }
  }

  /**
   * Removes test records where order_code LIKE 'E2E%' or email LIKE 'e2e_%'.
   */
  cleanupTestData() {
    try {
      // 1. Delete order items for E2E orders
      this.query(`
        DELETE FROM order_items 
        WHERE order_id IN (SELECT id FROM orders WHERE order_code LIKE 'E2E%')
      `);

      // 2. Delete realtime records
      this.query("DELETE FROM realtime_payments WHERE order_code LIKE 'E2E%'");
      this.query("DELETE FROM realtime_orders WHERE order_code LIKE 'E2E%'");

      // 3. Delete point transactions for test orders or test users
      try {
        this.query(`
          DELETE FROM point_transactions 
          WHERE order_id IN (SELECT id FROM orders WHERE order_code LIKE 'E2E%')
             OR user_id IN (SELECT id FROM users WHERE email LIKE 'e2e_%')
        `);
      } catch (_) {}

      // 4. Delete test coupons
      this.query(`
        DELETE FROM coupons 
        WHERE code LIKE 'E2E%' 
           OR user_id IN (SELECT id FROM users WHERE email LIKE 'e2e_%')
      `);

      // 5. Delete test orders
      this.query("DELETE FROM orders WHERE order_code LIKE 'E2E%'");

      // 6. Delete test cart items and carts
      this.query(`
        DELETE FROM cart_items 
        WHERE cart_id IN (SELECT id FROM carts WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'e2e_%'))
      `);
      this.query("DELETE FROM carts WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'e2e_%')");

      // 7. Delete test users
      this.query("DELETE FROM users WHERE email LIKE 'e2e_%'");

      return true;
    } catch (err) {
      console.warn('[WARN] cleanupTestData encountered an error:', err.message);
      return false;
    }
  }
}

// Export singleton instance and class
const dbHelper = new DbHelper();
module.exports = dbHelper;
module.exports.DbHelper = DbHelper;
