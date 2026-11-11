const fs = require('fs');
const vm = require('vm');
const path = require('path');
const { pool } = require('../src/config/db.config');

const dataPath = path.resolve(__dirname, '../../frontend/js/data.js');
const content = fs.readFileSync(dataPath, 'utf8');
const sandbox = { window: {} };
vm.createContext(sandbox);
vm.runInContext(content, sandbox);
const data = sandbox.FASTFOOD_DATA || sandbox.window.FASTFOOD_DATA;

(async () => {
  try {
    console.log(`Starting sync of ${data.products.length} products to MySQL...`);

    // 1. Sync Categories
    const categoryMap = {};
    for (const cat of data.categories) {
      const [existing] = await pool.query('SELECT id FROM categories WHERE slug = ?', [cat.id]);
      let catId;
      if (existing.length > 0) {
        catId = existing[0].id;
        await pool.query('UPDATE categories SET name = ?, icon_url = ? WHERE id = ?', [cat.name, cat.icon, catId]);
      } else {
        const [res] = await pool.query(
          'INSERT INTO categories (name, slug, icon_url, is_active) VALUES (?, ?, ?, 1)',
          [cat.name, cat.id, cat.icon]
        );
        catId = res.insertId;
      }
      categoryMap[cat.id] = catId;
    }

    // 2. Sync Restaurants
    const restaurantMap = {};
    for (const res of data.restaurants) {
      const [existing] = await pool.query('SELECT id FROM restaurants WHERE name = ?', [res.name]);
      let resId;
      if (existing.length > 0) {
        resId = existing[0].id;
        await pool.query(
          'UPDATE restaurants SET description = ?, address = ?, banner_url = ?, rating_avg = ?, is_active = 1 WHERE id = ?',
          [res.tags ? res.tags.join(', ') : '', res.address, res.image, res.rating || 4.8, resId]
        );
      } else {
        const [insertRes] = await pool.query(
          'INSERT INTO restaurants (owner_id, name, slug, description, address, banner_url, rating_avg, is_active, is_verified) VALUES (2, ?, ?, ?, ?, ?, ?, 1, 1)',
          [res.name, res.id, res.tags ? res.tags.join(', ') : '', res.address, res.image, res.rating || 4.8]
        );
        resId = insertRes.insertId;
      }
      restaurantMap[res.id] = resId;
    }

    // 3. Sync Products (260 products)
    let addedCount = 0;
    let updatedCount = 0;
    for (const prod of data.products) {
      const dbResId = restaurantMap[prod.restaurantId] || 1;
      const dbCatId = categoryMap[prod.category] || 1;
      const [existing] = await pool.query('SELECT id FROM products WHERE product_code = ? OR name = ?', [prod.id, prod.name]);
      if (existing.length > 0) {
        await pool.query(
          'UPDATE products SET product_code = ?, restaurant_id = ?, category_id = ?, name = ?, slug = ?, description = ?, image_url = ?, base_price = ?, discount_price = ?, is_available = 1, is_featured = ? WHERE id = ?',
          [
            prod.id, dbResId, dbCatId, prod.name, prod.slug, prod.description || '',
            prod.image, prod.oldPrice || prod.price, prod.price, prod.isBestSeller ? 1 : 0, existing[0].id
          ]
        );
        updatedCount++;
      } else {
        await pool.query(
          'INSERT INTO products (product_code, restaurant_id, category_id, name, slug, description, image_url, base_price, discount_price, is_available, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
          [
            prod.id, dbResId, dbCatId, prod.name, prod.slug, prod.description || '',
            prod.image, prod.oldPrice || prod.price, prod.price, prod.isBestSeller ? 1 : 0
          ]
        );
        addedCount++;
      }
    }

    console.log(`Sync complete! Added: ${addedCount}, Updated: ${updatedCount}. Total in DB: ${addedCount + updatedCount}`);
    await pool.end();
    process.exit(0);
  } catch (err) {
    console.error('Error during product sync:', err);
    process.exit(1);
  }
})();

