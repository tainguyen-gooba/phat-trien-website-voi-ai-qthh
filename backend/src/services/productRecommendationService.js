/**
 * PRODUCT RECOMMENDATION SERVICE
 * Truy vấn dữ liệu thực tế từ cửa hàng (frontend/js/data.js + MySQL realtime)
 * Đảm bảo AI luôn gợi ý đúng món, đúng giá, đúng ảnh và tự chạy được ngay cả khi MySQL chưa có dữ liệu mẫu đầy đủ.
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { pool } = require('../config/db.config');

const DEFAULT_FOOD_IMAGE = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80';

function normalizeVietnamese(str) {
  return String(str || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd')
    .toLowerCase()
    .trim();
}

function normalizeImageUrl(image) {
  const raw = String(image || '').trim();
  if (!raw) return DEFAULT_FOOD_IMAGE;
  if (raw.startsWith('../assets/')) {
    return `/assets/${raw.slice('../assets/'.length)}`;
  }
  if (raw.startsWith('./assets/')) {
    return `/assets/${raw.slice('./assets/'.length)}`;
  }
  return raw;
}

class ProductRecommendationService {
  constructor() {
    this._staticCatalog = null;
    this._liveContentCache = {
      data: null,
      expiresAt: 0,
    };
  }

  /**
   * Nạp dữ liệu 260 món ăn & 13 nhà hàng từ frontend/js/data.js một lần và cache trong RAM
   */
  loadStaticCatalog() {
    if (this._staticCatalog) {
      return this._staticCatalog;
    }

    const dataFilePath = path.resolve(__dirname, '../../../frontend/js/data.js');
    try {
      if (!fs.existsSync(dataFilePath)) {
        this._staticCatalog = { categories: [], restaurants: [], products: [] };
        return this._staticCatalog;
      }

      const code = fs.readFileSync(dataFilePath, 'utf8');
      const sandbox = {
        window: {},
        localStorage: {
          getItem: () => null,
          setItem: () => {},
          removeItem: () => {},
        },
        console: {
          log: () => {},
          warn: () => {},
          error: () => {},
        },
      };

      vm.runInNewContext(code, sandbox, { timeout: 2000 });
      const data = sandbox.window?.FASTFOOD_DATA || {};

      this._staticCatalog = {
        categories: Array.isArray(data.categories) ? data.categories : [],
        restaurants: Array.isArray(data.restaurants) ? data.restaurants : [],
        products: Array.isArray(data.products) ? data.products : [],
      };
    } catch (err) {
      console.warn('[ProductRecommendationService] Không thể đọc frontend/js/data.js:', err.message);
      this._staticCatalog = { categories: [], restaurants: [], products: [] };
    }

    return this._staticCatalog;
  }

  /**
   * Lấy dữ liệu live do Admin cập nhật trong bảng website_content (nếu MySQL sẵn sàng)
   */
  async loadLiveWebsiteContent() {
    const now = Date.now();
    if (this._liveContentCache.expiresAt > now) {
      return this._liveContentCache.data;
    }

    try {
      const [rows] = await pool.query(
        'SELECT content_json FROM website_content WHERE content_key = ? LIMIT 1',
        ['fastfood-live-content-v1']
      );
      if (rows.length > 0 && rows[0].content_json) {
        const parsed = typeof rows[0].content_json === 'string'
          ? JSON.parse(rows[0].content_json)
          : rows[0].content_json;
        this._liveContentCache = { data: parsed, expiresAt: now + 15000 };
        return parsed;
      }
    } catch (_) {
      // Bỏ qua nếu DB chưa kết nối hoặc chưa có bảng website_content
    }

    this._liveContentCache = { data: null, expiresAt: now + 10000 };
    return null;
  }

  /**
   * Chuẩn hóa 1 sản phẩm từ FASTFOOD_DATA / live store
   */
  formatStaticProduct(item, restaurantMap, categoryMap) {
    const price = Number(item.price || 0);
    const oldPrice = item.oldPrice !== undefined && item.oldPrice !== null
      ? Number(item.oldPrice)
      : (item.originalPrice !== undefined ? Number(item.originalPrice) : price);
    const hasDiscount = oldPrice > price && price > 0;
    const discountPercent = hasDiscount
      ? Math.round(((oldPrice - price) / oldPrice) * 100)
      : 0;
    const restaurant = restaurantMap.get(String(item.restaurantId || ''));
    const categoryObj = categoryMap.get(String(item.category || ''));

    return {
      id: String(item.id),
      dbId: item.dbId || null,
      name: item.name || 'Món ngon FASTFOOD',
      slug: item.slug || String(item.id),
      restaurantId: String(item.restaurantId || restaurant?.id || 'res-1'),
      restaurantName: restaurant?.name || item.restaurantName || 'FASTFOOD Partner',
      restaurantAddress: restaurant?.address || 'Buôn Ma Thuột, Đắk Lắk',
      category: item.category || 'mon-chinh',
      categoryName: categoryObj?.name || 'Món ăn',
      price,
      originalPrice: oldPrice,
      hasDiscount,
      discountPercent,
      description: item.description || '',
      image: normalizeImageUrl(item.image),
      imageCredit: item.imageCredit || null,
      rating: Number(item.rating || 4.8),
      reviews: Number(item.reviews || 0),
      time: item.time || '15 - 20 phút',
      isAvailable: item.isAvailable !== false,
      isBestSeller: Boolean(item.isBestSeller),
      isFlashDeal: Boolean(item.isFlashDeal),
    };
  }

  /**
   * Trả về danh sách toàn bộ món ăn hợp lệ đã chuẩn hóa
   */
  async getAllProducts() {
    const staticCatalog = this.loadStaticCatalog();
    const liveData = await this.loadLiveWebsiteContent();

    const categories = Array.isArray(liveData?.categories) && liveData.categories.length > 0
      ? liveData.categories
      : staticCatalog.categories;
    const restaurants = Array.isArray(liveData?.restaurants) && liveData.restaurants.length > 0
      ? liveData.restaurants
      : staticCatalog.restaurants;
    const rawProducts = Array.isArray(liveData?.products) && liveData.products.length > 0
      ? liveData.products
      : staticCatalog.products;

    if (rawProducts.length > 0) {
      const restaurantMap = new Map(restaurants.map(r => [String(r.id), r]));
      const categoryMap = new Map(categories.map(c => [String(c.id), c]));
      return rawProducts
        .map(item => this.formatStaticProduct(item, restaurantMap, categoryMap))
        .filter(item => item.isAvailable);
    }

    // Dự phòng cuối cùng: đọc từ bảng MySQL products + product_images nếu thiếu file frontend/js/data.js
    return this.loadProductsFromMySQL();
  }

  /**
   * Định dạng sản phẩm chuẩn từ DB MySQL (khi không có file data.js)
   */
  formatProduct(row) {
    const basePrice = Number(row.base_price || 0);
    const discountPrice = row.discount_price !== null ? Number(row.discount_price) : null;
    const finalPrice = discountPrice && discountPrice < basePrice ? discountPrice : basePrice;

    return {
      id: row.product_code || `prod-${row.id}`,
      dbId: row.id,
      name: row.name,
      slug: row.slug,
      restaurantId: row.restaurant_code || `res-${row.restaurant_id}`,
      restaurantName: row.restaurant_name || 'FASTFOOD Partner',
      restaurantAddress: row.restaurant_address || 'Buôn Ma Thuột, Đắk Lắk',
      category: row.category_slug || 'mon-chinh',
      categoryName: row.category_name || 'Món ăn',
      price: finalPrice,
      originalPrice: basePrice,
      hasDiscount: Boolean(discountPrice && discountPrice < basePrice),
      discountPercent: discountPrice && discountPrice < basePrice
        ? Math.round(((basePrice - discountPrice) / basePrice) * 100)
        : 0,
      description: row.description || '',
      image: normalizeImageUrl(row.image_url),
      rating: 4.8,
      reviews: 0,
      time: '15 - 20 phút',
      isAvailable: Boolean(row.is_available),
      isBestSeller: Boolean(row.is_featured),
      isFlashDeal: false,
    };
  }

  async loadProductsFromMySQL() {
    try {
      const [rows] = await pool.query(
        `SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address,
                c.name AS category_name, c.slug AS category_slug,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.display_order ASC LIMIT 1) AS image_url
         FROM products p
         LEFT JOIN restaurants r ON p.restaurant_id = r.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.is_available = 1
         ORDER BY p.is_featured DESC, p.id ASC`
      );
      return rows.map(r => this.formatProduct(r));
    } catch (err) {
      console.warn('[ProductRecommendationService] MySQL products fallback error:', err.message);
      return [];
    }
  }

  /**
   * Lấy danh sách món ăn bán chạy nhất (Best Sellers)
   */
  async getBestSellers(limit = 6) {
    const allProducts = await this.getAllProducts();
    return [...allProducts]
      .sort((a, b) => {
        if (Number(b.isBestSeller) !== Number(a.isBestSeller)) {
          return Number(b.isBestSeller) - Number(a.isBestSeller);
        }
        if (b.rating !== a.rating) return b.rating - a.rating;
        return b.reviews - a.reviews;
      })
      .slice(0, limit);
  }

  /**
   * Tìm món ăn theo tầm giá (ví dụ: dưới 50.000đ)
   */
  async getProductsUnderBudget(maxPrice = 50000, limit = 6) {
    const allProducts = await this.getAllProducts();
    return allProducts
      .filter(p => p.price > 0 && p.price <= Number(maxPrice))
      .sort((a, b) => {
        if (Number(b.isBestSeller) !== Number(a.isBestSeller)) {
          return Number(b.isBestSeller) - Number(a.isBestSeller);
        }
        if (b.discountPercent !== a.discountPercent) {
          return b.discountPercent - a.discountPercent;
        }
        return b.rating - a.rating;
      })
      .slice(0, limit);
  }

  /**
   * Tìm kiếm món ăn theo từ khóa thông minh (không phân biệt dấu)
   */
  async searchProducts(keyword, limit = 6) {
    const allProducts = await this.getAllProducts();
    const normQuery = normalizeVietnamese(keyword);
    if (!normQuery) {
      return this.getBestSellers(limit);
    }

    const stopWords = new Set(['toi', 'minh', 'ban', 'cho', 'xem', 'tim', 'mon', 'an', 'ngon', 'nao', 'co', 'khong', 'nhe', 'voi', 'a', 'gi']);
    const tokens = normQuery
      .split(/\s+/)
      .filter(t => t.length >= 2 && !stopWords.has(t));

    const scored = allProducts.map(product => {
      const nameNorm = normalizeVietnamese(product.name);
      const catNorm = normalizeVietnamese(`${product.category} ${product.categoryName}`);
      const resNorm = normalizeVietnamese(product.restaurantName);
      const descNorm = normalizeVietnamese(product.description);
      const fullText = `${nameNorm} ${catNorm} ${resNorm} ${descNorm}`;

      let score = 0;
      if (nameNorm.includes(normQuery)) score += 100;
      if (catNorm.includes(normQuery)) score += 60;
      if (resNorm.includes(normQuery)) score += 45;
      if (descNorm.includes(normQuery)) score += 20;

      if (tokens.length > 0) {
        let matchedTokens = 0;
        for (const token of tokens) {
          if (nameNorm.includes(token)) {
            score += 30;
            matchedTokens += 1;
          } else if (catNorm.includes(token)) {
            score += 18;
            matchedTokens += 1;
          } else if (fullText.includes(token)) {
            score += 10;
            matchedTokens += 1;
          }
        }
        if (matchedTokens === tokens.length) {
          score += 35;
        }
      }

      if (score > 0 && product.isBestSeller) score += 5;
      return { product, score };
    });

    return scored
      .filter(item => item.score > 0)
      .sort((a, b) => b.score - a.score || b.product.rating - a.product.rating)
      .slice(0, limit)
      .map(item => item.product);
  }

  /**
   * Lấy các combo hoặc món đang giảm giá sâu (Tiết kiệm / Flash Deal)
   */
  async getCombosAndDeals(limit = 6) {
    const allProducts = await this.getAllProducts();
    return allProducts
      .map(p => {
        const nameNorm = normalizeVietnamese(p.name);
        const isCombo = /(combo|set|phan doi|gia dinh)/.test(nameNorm);
        let score = p.discountPercent;
        if (isCombo) score += 40;
        if (p.isFlashDeal) score += 20;
        if (p.hasDiscount) score += 15;
        return { product: p, score };
      })
      .filter(item => item.score > 0)
      .sort((a, b) => b.score - a.score || a.product.price - b.product.price)
      .slice(0, limit)
      .map(item => item.product);
  }

  /**
   * Tra cứu đơn hàng theo mã đơn (kiểm tra cả realtime_orders và orders)
   */
  async lookupOrder(orderCode, userId = null) {
    const cleanCode = String(orderCode || '').replace(/^#/, '').trim().toUpperCase();
    if (!cleanCode) return { found: false };

    try {
      // 1. Ưu tiên kiểm tra trong realtime_orders
      const [rtRows] = await pool.query(
        'SELECT * FROM realtime_orders WHERE UPPER(order_code) = ? LIMIT 1',
        [cleanCode]
      );

      if (rtRows.length > 0) {
        const row = rtRows[0];
        let payload = {};
        try {
          payload = typeof row.payload_json === 'string' ? JSON.parse(row.payload_json) : (row.payload_json || {});
        } catch (_) {}

        const ownerId = row.user_id || payload.userId || null;
        if (ownerId && userId && Number(ownerId) !== Number(userId)) {
          return {
            found: true,
            isAuthorized: false,
            orderCode: cleanCode,
            message: 'Bạn không có quyền xem thông tin đơn hàng của tài khoản khác.',
          };
        }

        const itemNames = Array.isArray(payload.items)
          ? payload.items.map(i => `${i.name} (x${i.quantity})`).join(', ')
          : 'Đơn hàng FASTFOOD';

        return {
          found: true,
          isAuthorized: true,
          orderCode: row.order_code,
          status: row.status,
          displayStatus: this.mapStatusText(row.status),
          restaurantName: row.restaurant_name || payload.restaurantName || 'Hệ thống FASTFOOD',
          customerName: row.customer_name || payload.customerName || 'Khách hàng',
          totalAmount: Number(row.total_amount || payload.totalAmount || 0),
          itemsSummary: itemNames,
          createdAt: row.created_at,
        };
      }

      // 2. Kiểm tra trong bảng orders chính thức
      const [ordRows] = await pool.query(
        `SELECT o.*, r.name AS restaurant_name
         FROM orders o
         LEFT JOIN restaurants r ON o.restaurant_id = r.id
         WHERE UPPER(o.order_code) = ? LIMIT 1`,
        [cleanCode]
      );

      if (ordRows.length > 0) {
        const row = ordRows[0];
        if (row.user_id && userId && Number(row.user_id) !== Number(userId)) {
          return {
            found: true,
            isAuthorized: false,
            orderCode: cleanCode,
            message: 'Bạn không có quyền xem thông tin đơn hàng của tài khoản khác.',
          };
        }

        const [itemRows] = await pool.query(
          'SELECT product_name, quantity FROM order_items WHERE order_id = ?',
          [row.id]
        );
        const itemNames = itemRows.map(i => `${i.product_name} (x${i.quantity})`).join(', ');

        return {
          found: true,
          isAuthorized: true,
          orderCode: row.order_code,
          status: row.status,
          displayStatus: this.mapStatusText(row.status),
          restaurantName: row.restaurant_name || 'FASTFOOD Partner',
          totalAmount: Number(row.final_amount || row.subtotal || 0),
          itemsSummary: itemNames || 'Đơn hàng FASTFOOD',
          createdAt: row.created_at,
        };
      }
    } catch (err) {
      console.warn('[ProductRecommendationService] lookupOrder fallback:', err.message);
    }

    return { found: false, orderCode: cleanCode };
  }

  /**
   * Lấy các đơn hàng gần đây của người dùng đang đăng nhập
   */
  async getUserRecentOrders(userId, limit = 3) {
    if (!userId) return [];
    try {
      const [rows] = await pool.query(
        `SELECT order_code, status, total_amount, restaurant_name, created_at
         FROM realtime_orders
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ?`,
        [Number(userId), Number(limit)]
      );

      if (rows.length > 0) {
        return rows.map(r => ({
          orderCode: r.order_code,
          status: r.status,
          displayStatus: this.mapStatusText(r.status),
          totalAmount: Number(r.total_amount || 0),
          restaurantName: r.restaurant_name || 'FASTFOOD',
          createdAt: r.created_at,
        }));
      }

      const [ordRows] = await pool.query(
        `SELECT o.order_code, o.status, o.final_amount AS total_amount, r.name AS restaurant_name, o.created_at
         FROM orders o
         LEFT JOIN restaurants r ON o.restaurant_id = r.id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC
         LIMIT ?`,
        [Number(userId), Number(limit)]
      );

      return ordRows.map(r => ({
        orderCode: r.order_code,
        status: r.status,
        displayStatus: this.mapStatusText(r.status),
        totalAmount: Number(r.total_amount || 0),
        restaurantName: r.restaurant_name || 'FASTFOOD',
        createdAt: r.created_at,
      }));
    } catch (err) {
      console.warn('[ProductRecommendationService] getUserRecentOrders fallback:', err.message);
      return [];
    }
  }

  /**
   * Chuyển mã trạng thái tiếng Anh sang tiếng Việt thân thiện
   */
  mapStatusText(status) {
    const map = {
      pending: 'Đang chờ xác nhận',
      confirmed: 'Quán đã xác nhận',
      preparing: 'Quán đang chế biến',
      ready_for_pickup: 'Đã nấu xong - Chờ tài xế',
      delivering: 'Tài xế đang giao hàng',
      completed: 'Giao hàng thành công',
      delivered: 'Giao hàng thành công',
      cancelled: 'Đã hủy',
    };
    return map[status] || status || 'Đang xử lý';
  }

  /**
   * Chính sách sàn giao dịch và mã ưu đãi thực tế
   */
  getSystemPolicies() {
    return {
      shippingFee: {
        standardFee: 18000,
        freeShipThreshold: 100000,
        description: 'Phí ship cơ bản 18.000đ nội thành Buôn Ma Thuột. Đơn từ 100.000đ được miễn phí ship tối đa 18.000đ khi dùng mã FREESHIP.',
      },
      cancellationPolicy: {
        rule: 'Khách hàng có thể hủy đơn miễn phí trước khi quán bắt đầu làm món. Hủy sau khi làm món có thể bị tính 3% phụ phí vào đơn hàng tiếp theo.',
      },
      coupons: [
        { code: 'FASTFOOD20', discount: '20.000đ', minOrder: '60.000đ', note: 'Áp dụng cho mọi đơn từ 60K' },
        { code: 'FREESHIP', discount: '18.000đ (Free Ship)', minOrder: '100.000đ', note: 'Miễn phí giao hàng đơn từ 100K' },
        { code: 'GIAM50K', discount: '50.000đ', minOrder: '200.000đ', note: 'Giảm ngay 50K cho đơn từ 200K' },
      ],
      hotline: '1900 6868',
      operatingHours: '06:00 - 23:00 hàng ngày',
      location: 'Thành phố Buôn Ma Thuột, Đắk Lắk',
    };
  }
}

module.exports = new ProductRecommendationService();
