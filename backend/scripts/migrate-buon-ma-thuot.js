const mysql = require('mysql2/promise');
const config = require('../src/config/app.config');

async function migrateRestaurantsToBuonMaThuot() {
  let connection;
  try {
    connection = await mysql.createConnection({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      database: config.db.database,
    });

    const [result] = await connection.query(`
      UPDATE restaurants
      SET
        name = CASE slug
          WHEN 'burger-king-station' THEN 'Burger King Station - Buôn Ma Thuột'
          WHEN 'tiem-com-tam-sai-gon-xua' THEN 'Tiệm Cơm Tấm Sài Gòn Xưa - Buôn Ma Thuột'
          ELSE name
        END,
        address = CASE slug
          WHEN 'burger-king-station' THEN '25 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk'
          WHEN 'tiem-com-tam-sai-gon-xua' THEN '68 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk'
          ELSE address
        END,
        latitude = CASE slug
          WHEN 'burger-king-station' THEN 12.679500
          WHEN 'tiem-com-tam-sai-gon-xua' THEN 12.666100
          ELSE latitude
        END,
        longitude = CASE slug
          WHEN 'burger-king-station' THEN 108.050500
          WHEN 'tiem-com-tam-sai-gon-xua' THEN 108.045500
          ELSE longitude
        END,
        phone = CASE slug
          WHEN 'burger-king-station' THEN '0262388888'
          WHEN 'tiem-com-tam-sai-gon-xua' THEN '0262388889'
          ELSE phone
        END
      WHERE slug IN ('burger-king-station', 'tiem-com-tam-sai-gon-xua')
    `);

    console.log(`Đã cập nhật ${result.affectedRows} quán mẫu sang Buôn Ma Thuột.`);
  } catch (error) {
    console.error('[Buôn Ma Thuột migration failed]', error.message);
    process.exitCode = 1;
  } finally {
    if (connection) await connection.end();
  }
}

migrateRestaurantsToBuonMaThuot();
