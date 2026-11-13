const fs = require('fs');
const path = require('path');
const { pool } = require('../src/config/db.config');

function escapeSql(val) {
  if (val === null || val === undefined) return 'NULL';
  if (typeof val === 'number') return String(val);
  if (typeof val === 'boolean') return val ? '1' : '0';
  if (val instanceof Date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `'${val.getFullYear()}-${pad(val.getMonth() + 1)}-${pad(val.getDate())} ${pad(val.getHours())}:${pad(val.getMinutes())}:${pad(val.getSeconds())}'`;
  }
  const str = String(val);
  return `'${str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '\\r')}'`;
}

async function exportTableInserts(connection, tableName) {
  const [rows] = await connection.query(`SELECT * FROM \`${tableName}\``);
  if (!rows.length) return '';

  const cols = Object.keys(rows[0]);
  const colList = cols.map(c => `\`${c}\``).join(', ');

  const batchSize = 50;
  let sql = `--\n-- Dữ liệu bảng \`${tableName}\` (${rows.length} bản ghi)\n--\n`;
  sql += `ALTER TABLE \`${tableName}\` DISABLE KEYS;\n`;

  for (let i = 0; i < rows.length; i += batchSize) {
    const chunk = rows.slice(i, i + batchSize);
    const valueTuples = chunk.map(row => {
      const vals = cols.map(c => escapeSql(row[c]));
      return `(${vals.join(', ')})`;
    });
    sql += `INSERT INTO \`${tableName}\` (${colList}) VALUES\n${valueTuples.join(',\n')}\nON DUPLICATE KEY UPDATE \`${cols[0]}\` = VALUES(\`${cols[0]}\`);\n`;
  }

  sql += `ALTER TABLE \`${tableName}\` ENABLE KEYS;\n\n`;
  return sql;
}

async function dumpDatabase() {
  const connection = await pool.getConnection();
  try {
    console.log('Đang xuất toàn bộ dữ liệu từ MySQL...');

    const tablesToDump = [
      'roles',
      'users',
      'categories',
      'restaurants',
      'products',
      'product_images',
      'coupons',
      'orders',
      'order_items',
      'realtime_orders',
      'chat_automation',
      'chat_messages',
      'website_content'
    ];

    let fullDataSql = `USE \`fastfood_db\`;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";\n\n`;

    for (const tbl of tablesToDump) {
      const tblSql = await exportTableInserts(connection, tbl);
      fullDataSql += tblSql;
    }

    fullDataSql += `SET FOREIGN_KEY_CHECKS = 1;\n`;

    const dumpPath = path.resolve(__dirname, '../../database/seeds.sql');
    fs.writeFileSync(dumpPath, fullDataSql, 'utf8');
    console.log(`Đã xuất dữ liệu seeds thành công ra: ${dumpPath}`);

    // Cập nhật fastfood_db.sql hoàn chỉnh (Schema + full Seeds)
    const schemaPath = path.resolve(__dirname, '../../database/schema.sql');
    if (fs.existsSync(schemaPath)) {
      const schemaSql = fs.readFileSync(schemaPath, 'utf8');
      const combinedSql = `${schemaSql}\n\n-- ===================================================================\n-- SEEDS DATA (DỮ LIỆU ĐẦY ĐỦ PHÂN QUYỀN, TÀI KHOẢN & SẢN PHẨM)\n-- ===================================================================\n\n${fullDataSql}`;
      const fastfoodDbSqlPath = path.resolve(__dirname, '../../database/fastfood_db.sql');
      fs.writeFileSync(fastfoodDbSqlPath, combinedSql, 'utf8');
      console.log(`Đã cập nhật fastfood_db.sql thành công (${(combinedSql.length / 1024).toFixed(1)} KB)`);
    }

  } catch (err) {
    console.error('Lỗi khi xuất dữ liệu:', err);
  } finally {
    connection.release();
    await pool.end();
  }
}

dumpDatabase();

