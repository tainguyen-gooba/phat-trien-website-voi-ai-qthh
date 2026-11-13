const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { pool } = require('../src/config/db.config');

const DATA_JS_PATH = path.resolve(__dirname, '../../frontend/js/data.js');

function loadFrontendCatalog() {
  const code = fs.readFileSync(DATA_JS_PATH, 'utf8');
  const sandbox = { window: {} };
  vm.runInNewContext(code, sandbox);
  const catalog = sandbox.FASTFOOD_DATA || sandbox.window.FASTFOOD_DATA;
  if (!catalog || !Array.isArray(catalog.categories) || !Array.isArray(catalog.restaurants) || !Array.isArray(catalog.products)) {
    throw new Error(`Không đọc được FASTFOOD_DATA từ ${DATA_JS_PATH}`);
  }
  return catalog;
}

function toNumericId(rawId, prefix) {
  const num = Number(String(rawId || '').replace(new RegExp(`^${prefix}-`), ''));
  return Number.isInteger(num) && num > 0 ? num : null;
}

function toSlug(text, fallback = 'item') {
  const normalized = String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return normalized || fallback;
}

async function syncCatalogToDatabase({ closeAfter = false } = {}) {
  const catalog = loadFrontendCatalog();
  const realCategories = catalog.categories.filter((c) => c.id && c.id !== 'all');
  const categoryNameBySlug = Object.fromEntries(realCategories.map((c) => [c.id, c.name]));

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    // 1. Lấy owner_id hợp lệ từ bảng users
    const [userRows] = await connection.query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
    const ownerId = userRows[0]?.id || 1;

    // 2. Đồng bộ bảng categories
    for (let idx = 0; idx < realCategories.length; idx += 1) {
      const cat = realCategories[idx];
      await connection.execute(
        `INSERT INTO categories (restaurant_id, name, slug, icon_url, display_order, is_active)
         VALUES (NULL, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
           name = VALUES(name),
           icon_url = VALUES(icon_url),
           display_order = VALUES(display_order),
           is_active = 1`,
        [cat.name, cat.id, cat.image || null, idx + 1]
      );
    }

    const [dbCategoryRows] = await connection.query('SELECT id, slug FROM categories');
    const categoryIdBySlug = Object.fromEntries(dbCategoryRows.map((row) => [row.slug, row.id]));
    const fallbackCategoryId = dbCategoryRows[0]?.id || 1;

    // 3. Đồng bộ bảng restaurants
    for (const res of catalog.restaurants) {
      const numericId = toNumericId(res.id, 'res');
      if (!numericId) continue;
      const slug = res.slug || `${toSlug(res.name, 'restaurant')}-${numericId}`;
      const rating = Math.min(5, Math.max(0, Number(res.rating || 4.8))).toFixed(2);
      const reviewCount = Math.max(0, parseInt(res.reviews, 10) || 0);

      await connection.execute(
        `INSERT INTO restaurants (
           id, owner_id, name, slug, description, address, phone,
           banner_url, logo_url, is_active, is_verified, rating_avg, rating_count
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
         ON DUPLICATE KEY UPDATE
           name = VALUES(name),
           slug = VALUES(slug),
           description = VALUES(description),
           address = VALUES(address),
           phone = VALUES(phone),
           banner_url = VALUES(banner_url),
           logo_url = VALUES(logo_url),
           is_active = VALUES(is_active),
           is_verified = 1,
           rating_avg = VALUES(rating_avg),
           rating_count = VALUES(rating_count)`,
        [
          numericId,
          ownerId,
          res.name,
          slug,
          res.description || '',
          res.address || 'Buôn Ma Thuột, Đắk Lắk',
          res.phone || '',
          res.banner || null,
          res.logo || null,
          res.isOpen === false ? 0 : 1,
          rating,
          reviewCount,
        ]
      );
    }

    // 4. Đồng bộ bảng products & product_images
    const [prodCols] = await connection.query('SHOW COLUMNS FROM products');
    const prodColSet = new Set(prodCols.map((c) => c.Field));
    const hasProductCode = prodColSet.has('product_code');
    const hasImageUrl = prodColSet.has('image_url');

    const [wcCols] = await connection.query('SHOW COLUMNS FROM website_content');
    const hasWcVersion = wcCols.some((c) => c.Field === 'version');

    const syncedProductIds = [];
    for (const prod of catalog.products) {
      const numericId = toNumericId(prod.id, 'prod');
      const restaurantId = toNumericId(prod.restaurantId, 'res') || 1;
      const categoryId = categoryIdBySlug[prod.category] || fallbackCategoryId;
      if (!numericId) continue;

      const basePrice = Math.max(0, Number(prod.price) || 0);
      const oldPrice = Number(prod.oldPrice) || 0;
      const discountPrice = oldPrice > basePrice ? basePrice : null;
      const prepMinutes = Math.max(5, parseInt(prod.time, 10) || 15);
      const slug = prod.slug || `${toSlug(prod.name, 'product')}-${numericId}`;

      const fields = ['id'];
      const values = [numericId];
      if (hasProductCode) {
        fields.push('product_code');
        values.push(prod.id);
      }
      fields.push('restaurant_id', 'category_id', 'name', 'slug', 'description');
      values.push(restaurantId, categoryId, prod.name, slug, prod.description || '');
      if (hasImageUrl) {
        fields.push('image_url');
        values.push(prod.image || null);
      }
      fields.push('base_price', 'discount_price', 'is_available', 'is_featured', 'prep_time_minutes');
      values.push(basePrice, discountPrice, 1, prod.isBestSeller ? 1 : 0, prepMinutes);

      const placeholders = fields.map(() => '?').join(', ');
      const updateClause = fields
        .filter((f) => f !== 'id')
        .map((f) => `${f} = VALUES(${f})`)
        .join(', ');

      await connection.execute(
        `INSERT INTO products (${fields.join(', ')}) VALUES (${placeholders})
         ON DUPLICATE KEY UPDATE ${updateClause}`,
        values
      );

      await connection.execute(
        'DELETE FROM product_images WHERE product_id = ? AND is_primary = 1',
        [numericId]
      );
      if (prod.image) {
        await connection.execute(
          `INSERT INTO product_images (product_id, image_url, is_primary, display_order)
           VALUES (?, ?, 1, 1)`,
          [numericId, prod.image]
        );
      }

      syncedProductIds.push(numericId);
    }

    // 5. Đồng bộ bảng website_content (categories, restaurants, products)
    const contentCategories = realCategories.map((cat) => ({
      id: cat.id,
      name: cat.name,
      slug: cat.id,
      icon: cat.icon || '🍽️',
      image: cat.image || '',
      status: 'Đang hoạt động',
    }));

    const contentRestaurants = catalog.restaurants.map((res) => ({
      id: res.id,
      name: res.name,
      slug: res.slug || toSlug(res.name),
      category: res.category,
      banner: res.banner || '',
      logo: res.logo || '',
      address: res.address || '',
      district: res.district || '',
      phone: res.phone || '',
      rating: Number(res.rating || 4.8),
      reviews: Number(res.reviews || 0),
      deliveryTime: res.deliveryTime || '15-25 phút',
      deliveryFee: Number(res.deliveryFee || 0),
      distance: res.distance || '1.0 km',
      isOpen: res.isOpen !== false,
      isTrending: Boolean(res.isTrending),
      tags: Array.isArray(res.tags) ? res.tags : [],
      description: res.description || '',
      status: 'Đang hoạt động',
    }));

    const contentProducts = catalog.products.map((prod) => ({
      id: prod.id,
      name: prod.name,
      slug: prod.slug || toSlug(prod.name),
      restaurantId: prod.restaurantId,
      restaurant: prod.restaurantName || '',
      restaurantName: prod.restaurantName || '',
      categoryId: prod.category,
      category: categoryNameBySlug[prod.category] || prod.category,
      price: Number(prod.price || 0),
      oldPrice: Number(prod.oldPrice || prod.price || 0),
      rating: Number(prod.rating || 4.8),
      reviews: Number(prod.reviews || 0),
      time: prod.time || '15-20 phút',
      image: prod.image || '',
      isBestSeller: Boolean(prod.isBestSeller),
      description: prod.description || '',
      toppings: Array.isArray(prod.toppings) ? prod.toppings : [],
      status: 'Đang bán',
    }));

    const contentCollections = [
      ['categories', JSON.stringify(contentCategories)],
      ['restaurants', JSON.stringify(contentRestaurants)],
      ['products', JSON.stringify(contentProducts)],
    ];

    for (const [collectionName, contentJson] of contentCollections) {
      if (hasWcVersion) {
        await connection.execute(
          `INSERT INTO website_content (collection_name, content, version)
           VALUES (?, ?, 1)
           ON DUPLICATE KEY UPDATE
             content = VALUES(content),
             version = version + 1`,
          [collectionName, contentJson]
        );
      } else {
        await connection.execute(
          `INSERT INTO website_content (collection_name, content)
           VALUES (?, ?)
           ON DUPLICATE KEY UPDATE
             content = VALUES(content)`,
          [collectionName, contentJson]
        );
      }
    }

    await connection.commit();
    return {
      categories: realCategories.length,
      restaurants: catalog.restaurants.length,
      products: syncedProductIds.length,
      productImages: syncedProductIds.length,
    };
  } catch (err) {
    await connection.rollback();
    throw err;
  } finally {
    connection.release();
    if (closeAfter) {
      await pool.end();
    }
  }
}

async function syncCatalogIfSparse() {
  const [[prodCountRow]] = await pool.query('SELECT COUNT(*) AS cnt FROM products');
  const [[imgCountRow]] = await pool.query('SELECT COUNT(*) AS cnt FROM product_images');
  if (Number(prodCountRow?.cnt || 0) < 10 || Number(imgCountRow?.cnt || 0) < 10) {
    return syncCatalogToDatabase({ closeAfter: false });
  }
  return null;
}

if (require.main === module) {
  syncCatalogToDatabase({ closeAfter: true })
    .then((summary) => {
      console.log('Đồng bộ dữ liệu lên MySQL thành công:', summary);
      process.exit(0);
    })
    .catch((err) => {
      console.error('Lỗi đồng bộ dữ liệu lên MySQL:', err);
      process.exit(1);
    });
}

module.exports = { syncCatalogToDatabase, syncCatalogIfSparse, loadFrontendCatalog };
