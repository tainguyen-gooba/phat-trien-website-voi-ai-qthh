/**
 * PRODUCT RECOMMENDATION SERVICE
 * Truy vấn dữ liệu thực tế từ MySQL (Sản phẩm, Món ăn, Giá, Nhà hàng, Đơn hàng)
 * Đảm bảo AI chỉ sử dụng dữ liệu có thực, không hallucinate / bịa giá
 */

const { pool } = require('../config/db.config');

class ProductRecommendationService {
  /**
   * Định dạng sản phẩm chuẩn từ DB trả về cho AI và Frontend
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
      hasDiscount: discountPrice && discountPrice < basePrice,
      discountPercent: discountPrice && discountPrice < basePrice
        ? Math.round(((basePrice - discountPrice) / basePrice) * 100)
        : 0,
      description: row.description || '',
      image: row.image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80',
      isAvailable: Boolean(row.is_available),
      isBestSeller: Boolean(row.is_featured),
    };
  }

  /**
   * Lấy danh sách món ăn bán chạy nhất (Best Sellers)
   */
  async getBestSellers(limit = 6) {
    try {
      const sql = `
        SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, r.slug AS restaurant_code,
               c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN restaurants r ON p.restaurant_id = r.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1 AND p.is_featured = 1
        ORDER BY p.id ASC
        LIMIT ?
      `;
      const [rows] = await pool.query(sql, [limit]);
      return rows.map(r => this.formatProduct(r));
    } catch (err) {
      console.error('[ProductRecommendationService] Error getBestSellers:', err.message);
      return [];
    }
  }

  /**
   * Gợi ý món ăn theo ngân sách (ví dụ: dưới 50.000đ)
   */
  async getProductsUnderBudget(maxPrice = 50000, limit = 6) {
    try {
      const sql = `
        SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, r.slug AS restaurant_code,
               c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN restaurants r ON p.restaurant_id = r.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1 
          AND COALESCE(p.discount_price, p.base_price) <= ?
        ORDER BY COALESCE(p.discount_price, p.base_price) ASC
        LIMIT ?
      `;
      const [rows] = await pool.query(sql, [maxPrice, limit]);
      return rows.map(r => this.formatProduct(r));
    } catch (err) {
      console.error('[ProductRecommendationService] Error getProductsUnderBudget:', err.message);
      return [];
    }
  }

  /**
   * Tìm kiếm món ăn theo từ khóa (tên món, mô tả, danh mục, quán ăn)
   */
  async searchProducts(keyword, limit = 6) {
    if (!keyword || !keyword.trim()) return [];
    try {
      const term = `%${keyword.trim()}%`;
      const sql = `
        SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, r.slug AS restaurant_code,
               c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN restaurants r ON p.restaurant_id = r.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1 
          AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR r.name LIKE ?)
        ORDER BY p.is_featured DESC, p.id ASC
        LIMIT ?
      `;
      const [rows] = await pool.query(sql, [term, term, term, term, limit]);
      return rows.map(r => this.formatProduct(r));
    } catch (err) {
      console.error('[ProductRecommendationService] Error searchProducts:', err.message);
      return [];
    }
  }

  /**
   * Lấy các món combo hoặc có mức giảm giá tốt
   */
  async getCombosAndDeals(limit = 6) {
    try {
      const sql = `
        SELECT p.*, r.name AS restaurant_name, r.address AS restaurant_address, r.slug AS restaurant_code,
               c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN restaurants r ON p.restaurant_id = r.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1 
          AND (p.discount_price IS NOT NULL AND p.discount_price < p.base_price OR p.name LIKE '%Combo%')
        ORDER BY (p.base_price - COALESCE(p.discount_price, p.base_price)) DESC
        LIMIT ?
      `;
      const [rows] = await pool.query(sql, [limit]);
      return rows.map(r => this.formatProduct(r));
    } catch (err) {
      console.error('[ProductRecommendationService] Error getCombosAndDeals:', err.message);
      return [];
    }
  }

  /**
   * Tra cứu đơn hàng của khách hàng (Có kiểm tra bảo mật quyền sở hữu đơn)
   * @param {string} orderCode - Mã đơn (ví dụ: FF7298)
   * @param {number|string|null} userId - ID người dùng hiện tại (nếu đã đăng nhập)
   */
  async lookupOrder(orderCode, userId = null) {
    if (!orderCode) return null;
    const cleanCode = String(orderCode).replace(/^#/, '').trim();

    try {
      // 1. Kiểm tra trong realtime_orders trước
      const [realtimeRows] = await pool.query(
        'SELECT * FROM realtime_orders WHERE order_code = ? LIMIT 1',
        [cleanCode]
      );

      if (realtimeRows.length > 0) {
        const row = realtimeRows[0];
        let orderData = {};
        try {
          orderData = typeof row.order_data === 'string' ? JSON.parse(row.order_data) : (row.order_data || {});
        } catch (_) {}

        // Kiểm tra quyền sở hữu đơn hàng
        const orderUserId = row.user_id || orderData.userId;
        const isAuthorized = !orderUserId || (userId && Number(orderUserId) === Number(userId));

        return {
          found: true,
          isAuthorized,
          orderCode: row.order_code,
          status: row.status,
          displayStatus: this.mapStatusText(row.status),
          createdAt: row.created_at,
          totalAmount: orderData.totalAmount || orderData.finalAmount || 0,
          customerName: orderData.customerName || orderData.fullName || 'Quý khách',
          customerPhone: isAuthorized ? (orderData.phone || '—') : '***',
          items: isAuthorized ? (orderData.items || []) : [],
          paymentStatus: orderData.paymentStatus || 'pending',
          paymentMethod: orderData.paymentMethod || 'cod',
        };
      }

      // 2. Kiểm tra trong bảng orders truyền thống
      const [orderRows] = await pool.query(
        'SELECT * FROM orders WHERE order_code = ? LIMIT 1',
        [cleanCode]
      );

      if (orderRows.length > 0) {
        const row = orderRows[0];
        const isAuthorized = !row.user_id || (userId && Number(row.user_id) === Number(userId));

        return {
          found: true,
          isAuthorized,
          orderCode: row.order_code,
          status: row.status,
          displayStatus: this.mapStatusText(row.status),
          createdAt: row.created_at,
          totalAmount: Number(row.final_amount || row.subtotal || 0),
          customerName: 'Quý khách',
          customerPhone: isAuthorized ? (row.phone || '—') : '***',
          items: [],
          paymentStatus: 'pending',
          paymentMethod: 'cod',
        };
      }

      return { found: false };
    } catch (err) {
      console.error('[ProductRecommendationService] Error lookupOrder:', err.message);
      return { found: false, error: err.message };
    }
  }

  /**
   * Lấy danh sách đơn hàng gần đây của khách đã đăng nhập
   */
  async getUserRecentOrders(userId, limit = 3) {
    if (!userId) return [];
    try {
      const [rows] = await pool.query(
        'SELECT * FROM realtime_orders WHERE user_id = ? ORDER BY id DESC LIMIT ?',
        [userId, limit]
      );

      return rows.map((r) => {
        let orderData = {};
        try {
          orderData = typeof r.order_data === 'string' ? JSON.parse(r.order_data) : (r.order_data || {});
        } catch (_) {}
        return {
          orderCode: r.order_code,
          status: r.status,
          displayStatus: this.mapStatusText(r.status),
          createdAt: r.created_at,
          totalAmount: orderData.totalAmount || orderData.finalAmount || 0,
          itemsCount: (orderData.items || []).length,
        };
      });
    } catch (err) {
      console.error('[ProductRecommendationService] Error getUserRecentOrders:', err.message);
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

