/**
 * FASTFOOD REAL DATA STORE
 * Dữ liệu hình ảnh thật 100%, chất lượng cao, chuẩn giao diện Food Delivery cao cấp
 */

const FASTFOOD_DATA = {
  // 1. Danh mục món ăn (Hình ảnh thật từng món thơm ngon)
  categories: [
    {
      id: 'all',
      name: 'Tất Cả Món',
      image: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=240&auto=format&fit=crop&q=80',
      count: 48,
    },
    {
      id: 'com',
      name: 'Cơm & Món Việt',
      image: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=240&auto=format&fit=crop&q=80',
      count: 18,
    },
    {
      id: 'burger',
      name: 'Burger & Gà Rán',
      image: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=240&auto=format&fit=crop&q=80',
      count: 12,
    },
    {
      id: 'tra-sua',
      name: 'Trà Sữa & Đồ Uống',
      image: 'https://images.unsplash.com/photo-1541696432-82c6da8ce7bf?w=240&auto=format&fit=crop&q=80',
      count: 15,
    },
    {
      id: 'pizza',
      name: 'Pizza & Mỳ Ý',
      image: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=240&auto=format&fit=crop&q=80',
      count: 9,
    },
    {
      id: 'an-vat',
      name: 'Ăn Vặt & Bánh',
      image: 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=240&auto=format&fit=crop&q=80',
      count: 14,
    },
    {
      id: 'healthy',
      name: 'Salad & Healthy',
      image: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=240&auto=format&fit=crop&q=80',
      count: 8,
    },
    {
      id: 'cafe',
      name: 'Cà Phê & Trà',
      image: 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=240&auto=format&fit=crop&q=80',
      count: 11,
    },
  ],

  // 2. Nhà hàng đối tác nổi bật (Hình ảnh thật mặt tiền quán & banner không gian)
  restaurants: [
    {
      id: 'res-1',
      name: 'Burger King Station - Buôn Ma Thuột',
      slug: 'burger-king-station',
      category: 'burger',
      rating: 4.9,
      reviewCount: 382,
      deliveryTime: '20 - 25 phút',
      distance: '1.2 km',
      minOrder: 30000,
      banner: 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=120&auto=format&fit=crop&q=80',
      address: '25 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk',
      promo: 'GIẢM 25K',
      isVerified: true,
      tags: ['Burger Bò Úc', 'Gà Giòn Cay', 'Khoai Tây Lắc'],
    },
    {
      id: 'res-2',
      name: 'Cơm Tấm Sài Gòn Xưa - Buôn Ma Thuột',
      slug: 'com-tam-sai-gon-xua',
      category: 'com',
      rating: 4.8,
      reviewCount: 520,
      deliveryTime: '15 - 20 phút',
      distance: '0.8 km',
      minOrder: 25000,
      banner: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=120&auto=format&fit=crop&q=80',
      address: '68 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk',
      promo: 'FREESHIP',
      isVerified: true,
      tags: ['Cơm Sườn Nướng', 'Bì Chả Trứng', 'Canh Khổ Qua'],
    },
    {
      id: 'res-3',
      name: 'Tiệm Trà Sữa GongCha Tea & Coffee',
      slug: 'gongcha-tea-coffee',
      category: 'tra-sua',
      rating: 4.9,
      reviewCount: 640,
      deliveryTime: '20 - 30 phút',
      distance: '2.1 km',
      minOrder: 40000,
      banner: 'https://images.unsplash.com/photo-1541696432-82c6da8ce7bf?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1541696432-82c6da8ce7bf?w=120&auto=format&fit=crop&q=80',
      address: '91 Lê Thánh Tông, Buôn Ma Thuột, Đắk Lắk',
      promo: 'MUA 1 TẶNG 1',
      isVerified: true,
      tags: ['Trà Đen Macchiato', 'Trân Châu Hoàng Gia', 'Trà Trái Cây'],
    },
    {
      id: 'res-4',
      name: 'Pizza Hut Italia - Buôn Ma Thuột',
      slug: 'pizza-hut-pasteur',
      category: 'pizza',
      rating: 4.7,
      reviewCount: 290,
      deliveryTime: '30 - 35 phút',
      distance: '2.5 km',
      minOrder: 60000,
      banner: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=120&auto=format&fit=crop&q=80',
      address: '12 Y Jút, Buôn Ma Thuột, Đắk Lắk',
      promo: 'GIẢM 50K',
      isVerified: true,
      tags: ['Pizza Hải Sản', 'Mì Ý Sốt Bò Bằm', 'Bánh Tỏi'],
    },
  ],

  // 3. Danh sách món ăn hình ảnh thật chụp trực diện, sắc nét, ngon miệng
  products: [
    {
      id: 'prod-1',
      name: 'Burger Bò Nướng Phô Mai Tan Chảy (Double Cheese)',
      slug: 'burger-bo-nuong-pho-mai',
      restaurantId: 'res-1',
      restaurantName: 'Burger King Station',
      category: 'burger',
      price: 69000,
      oldPrice: 89000,
      rating: 4.9,
      reviews: 142,
      time: '15 phút',
      image: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80',
      isBestSeller: true,
      isFlashDeal: true,
      description: 'Thịt bò Úc 100% nướng lửa than hoa thơm nức mũi, kẹp phô mai Cheddar béo ngậy, sốt BBQ đặc chế và rau xà lách tươi giòn.',
      toppings: [
        { name: 'Thêm phô mai lát Cheddar', price: 10000 },
        { name: 'Thịt xông khói giòn (Bacon)', price: 15000 },
        { name: 'Trứng ốp la lòng đào', price: 8000 },
      ],
    },
    {
      id: 'prod-2',
      name: 'Cơm Tấm Sườn Cốt Lết Nướng Than Hoa Bì Chả',
      slug: 'com-tam-suon-bi-cha',
      restaurantId: 'res-2',
      restaurantName: 'Cơm Tấm Sài Gòn Xưa',
      category: 'com',
      price: 55000,
      oldPrice: 65000,
      rating: 4.9,
      reviews: 280,
      time: '12 phút',
      image: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80',
      isBestSeller: true,
      isFlashDeal: false,
      description: 'Dĩa cơm tấm hạt vỡ chuẩn vị, sườn cốt lết dày dặn ướp mật ong nướng than hoa vàng óng, bì dai giòn, chả trứng hấp béo bùi.',
      toppings: [
        { name: 'Thêm trứng ốp la lòng đào', price: 7000 },
        { name: 'Chén mỡ hành tép mỡ giòn', price: 5000 },
        { name: 'Canh rong biển thịt bằm', price: 10000 },
      ],
    },
    {
      id: 'prod-3',
      name: 'Trà Sữa Trân Châu Đường Đen Hoàng Gia (Size L)',
      slug: 'tra-sua-tran-chau-hoang-gia',
      restaurantId: 'res-3',
      restaurantName: 'GongCha Tea & Coffee',
      category: 'tra-sua',
      price: 45000,
      oldPrice: 55000,
      rating: 4.8,
      reviews: 310,
      time: '10 phút',
      image: 'https://images.unsplash.com/photo-1541696432-82c6da8ce7bf?w=600&auto=format&fit=crop&q=80',
      isBestSeller: true,
      isFlashDeal: true,
      description: 'Trà đen Ceylon thơm nồng kết hợp sữa tươi thanh trùng béo nhẹ và trân châu đường đen dẻo dai nấu mới mỗi 3 tiếng.',
      toppings: [
        { name: 'Kem Macchiato mặn béo', price: 12000 },
        { name: 'Trân châu trắng giòn hoàng gia', price: 10000 },
        { name: 'Thạch pudding trứng mịn', price: 10000 },
      ],
    },
    {
      id: 'prod-4',
      name: 'Gà Rán Giòn Cay Sốt Cay Hàn Quốc (2 Miếng Lớn)',
      slug: 'ga-ran-gion-sot-cay',
      restaurantId: 'res-1',
      restaurantName: 'Burger King Station',
      category: 'burger',
      price: 75000,
      oldPrice: 92000,
      rating: 4.8,
      reviews: 98,
      time: '18 phút',
      image: 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: true,
      description: 'Gà tươi ướp gia vị bí truyền, chiên ngập dầu vỏ giòn rụm bên trong mọng nước, phủ sốt Gochujang cay ngọt đậm đà rắc mè rang.',
      toppings: [
        { name: 'Sốt chấm phô mai cay', price: 8000 },
        { name: 'Khoai tây chiên nhỏ', price: 15000 },
      ],
    },
    {
      id: 'prod-5',
      name: 'Pizza Hải Sản Pesto Ý Thượng Hạng (Size 24cm)',
      slug: 'pizza-hai-san-pesto',
      restaurantId: 'res-4',
      restaurantName: 'Pizza Hut Italia',
      category: 'pizza',
      price: 139000,
      oldPrice: 179000,
      rating: 4.7,
      reviews: 85,
      time: '25 phút',
      image: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80',
      isBestSeller: true,
      isFlashDeal: false,
      description: 'Đế bánh bột tươi ủ chậm 24h, tôm sú tươi, mực lá giòn sần sật kết hợp sốt lá quế tây Pesto và phô mai Mozzarella kéo sợi.',
      toppings: [
        { name: 'Viền phô mai xúc xích cao cấp', price: 35000 },
        { name: 'Gấp đôi phô mai nướng', price: 25000 },
      ],
    },
    {
      id: 'prod-6',
      name: 'Salad Ức Gà Áp Chảo Sốt Mè Rang Healthy',
      slug: 'salad-uc-ga-ap-chao',
      restaurantId: 'res-2',
      restaurantName: 'Cơm Tấm Sài Gòn Xưa',
      category: 'healthy',
      price: 52000,
      oldPrice: 62000,
      rating: 4.6,
      reviews: 64,
      time: '10 phút',
      image: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: false,
      description: 'Ức gà nướng mềm mọng, xà lách Romaine, cà chua bi, dưa leo Nhật, trứng luộc hồng đào và sốt mè rang Nhật Bản ít calo.',
      toppings: [
        { name: 'Bơ quả tươi cắt lát', price: 15000 },
        { name: 'Hạt hạnh nhân rang giòn', price: 10000 },
      ],
    },
    {
      id: 'prod-7',
      name: 'Mì Ý Spaghetti Bò Bằm Sốt Cà Chua Bolognese',
      slug: 'spaghetti-bo-bam-bolognese',
      restaurantId: 'res-4',
      restaurantName: 'Pizza Hut Italia',
      category: 'pizza',
      price: 79000,
      oldPrice: 95000,
      rating: 4.8,
      reviews: 112,
      time: '15 phút',
      image: 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: true,
      description: 'Mì Ý luộc al dente chuẩn độ dai, sốt thịt bò bằm hầm nhừ với rượu vang và cà chua Ý tươi mát rắc phô mai Parmesan bào nhuyễn.',
      toppings: [
        { name: 'Bánh mì bơ tỏi nướng giòn', price: 12000 },
      ],
    },
    {
      id: 'prod-8',
      name: 'Khoai Tây Chiên Lắc Phô Mai Lốc Xoáy',
      slug: 'khoai-tay-lac-pho-mai',
      restaurantId: 'res-1',
      restaurantName: 'Burger King Station',
      category: 'an-vat',
      price: 35000,
      oldPrice: 42000,
      rating: 4.9,
      reviews: 215,
      time: '8 phút',
      image: 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80',
      isBestSeller: true,
      isFlashDeal: false,
      description: 'Khoai tây tươi cắt sợi lớn chiên vàng rụm, lắc đều bột phô mai béo ngậy ngọt mặn cực kỳ cuốn hút.',
      toppings: [
        { name: 'Sốt chấm tương ớt cay nồng', price: 0 },
      ],
    },
  ],

  // 4. Mã giảm giá Voucher
  coupons: [
    { code: 'FASTFOOD20', title: 'Giảm 20K cho đơn từ 60K', discount: 20000, minOrder: 60000, icon: 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=80&auto=format&fit=crop&q=80' },
    { code: 'FREESHIP', title: 'Miễn phí giao hàng (Tối đa 18K)', discount: 18000, minOrder: 100000, icon: 'https://images.unsplash.com/photo-1526367790999-0150786686a2?w=80&auto=format&fit=crop&q=80' },
    { code: 'GIAM50K', title: 'Giảm ngay 50K cho đơn từ 200K', discount: 50000, minOrder: 200000, icon: 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=80&auto=format&fit=crop&q=80' },
  ],

  // 5. Đánh giá khách hàng (Chân dung người thật 100%)
  reviews: [
    {
      id: 1,
      author: 'Nguyễn Thùy Linh',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=160&auto=format&fit=crop&q=80',
      rating: 5,
      comment: 'Giao hàng siêu nhanh chỉ trong 18 phút! Burger bò nóng hổi phô mai chảy béo ngậy ăn cực mê. Chắc chắn sẽ ủng hộ FASTFOOD dài dài.',
      orderInfo: 'Đã mua: Burger Bò Nướng Phô Mai',
      date: 'Hôm qua lúc 19:30',
    },
    {
      id: 2,
      author: 'Trần Minh Quân',
      avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=160&auto=format&fit=crop&q=80',
      rating: 5,
      comment: 'Cơm tấm sườn ở đây xuất sắc, miếng sườn to đùng nướng than thơm phức, mỡ hành tép mỡ giòn rụm. Đóng hộp giấy bảo vệ môi trường rất ưng ý!',
      orderInfo: 'Đã mua: Cơm Tấm Sườn Bì Chả',
      date: '3 ngày trước',
    },
    {
      id: 3,
      author: 'Hoàng Lan Anh',
      avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=160&auto=format&fit=crop&q=80',
      rating: 5,
      comment: 'App đặt đồ ăn mượt mà, giao diện màu cam hiện đại nhìn bắt mắt. Tài xế giao đồ tận cửa nhiệt tình lễ phép 10 điểm chất lượng!',
      orderInfo: 'Đã mua: Trà Sữa GongCha + Pizza Hải Sản',
      date: '5 ngày trước',
    },
  ],
};

function getRestaurantForProduct(product) {
  if (!product) return null;

  const restaurants = FASTFOOD_DATA.restaurants;
  const normalizeName = (value) => String(value || '')
    .toLocaleLowerCase('vi')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
  const productRestaurantName = normalizeName(product.restaurantName || product.restaurant);
  if (!productRestaurantName) return null;

  const byName = restaurants.find((restaurant) => {
    const restaurantName = normalizeName(restaurant.name);
    return restaurantName === productRestaurantName
      || restaurantName.startsWith(`${productRestaurantName} `)
      || productRestaurantName.startsWith(`${restaurantName} `);
  });
  return byName || restaurants.find((restaurant) => restaurant.id === product.restaurantId) || null;
}

function getRestaurantAddressForProduct(product) {
  return product?.address || getRestaurantForProduct(product)?.address || 'Buôn Ma Thuột, Đắk Lắk';
}

function getRestaurantUrlForProduct(product) {
  const restaurant = getRestaurantForProduct(product);
  return restaurant ? (restaurant.url || '') : (product?.restaurantUrl || '');
}

function getWebsiteUrl(value, fallback) {
  const url = String(value || '').trim();
  if (/^https?:\/\/\S+$/i.test(url) || /^(\/(?!\/)|\.{1,2}\/)\S*$/.test(url)) {
    return url;
  }
  return fallback;
}

function isExternalWebsiteUrl(value) {
  return /^https?:\/\//i.test(String(value || '').trim());
}

function getProductUrl(product) {
  return getWebsiteUrl(product?.url, `./food-detail.html?id=${encodeURIComponent(product?.id || '')}`);
}

function getRestaurantUrl(restaurant) {
  return getWebsiteUrl(restaurant?.url, `./foods.html?restaurant=${encodeURIComponent(restaurant?.id || '')}`);
}

function applySavedWebsiteContent() {
  let content;
  try {
    content = JSON.parse(localStorage.getItem('fastfood-live-content-v1') || '{}');
  } catch (error) {
    console.error('Không thể đọc nội dung cập nhật realtime:', error);
    return;
  }

  if (Array.isArray(content.categories)) {
    const originalCategories = FASTFOOD_DATA.categories;
    const categories = content.categories
      .filter((category) => !String(category.status || '').toLocaleLowerCase('vi').includes('ẩn'))
      .map((category, index) => {
        const existing = originalCategories.find((item) => item.name.toLocaleLowerCase('vi') === String(category.name).toLocaleLowerCase('vi'));
        return {
          ...existing,
          id: existing?.id || `live-category-${category.id || index}`,
          name: String(category.name || 'Danh mục mới'),
          image: existing?.image || 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=240&auto=format&fit=crop&q=80',
          count: Number(category.itemCount) || existing?.count || 0,
        };
      });
    FASTFOOD_DATA.categories = [
      originalCategories.find((category) => category.id === 'all'),
      ...categories,
    ];
  }

  if (Array.isArray(content.restaurants)) {
    const originalRestaurants = FASTFOOD_DATA.restaurants;
    FASTFOOD_DATA.restaurants = content.restaurants
      .filter((restaurant) => !String(restaurant.status || '').toLocaleLowerCase('vi').includes('ẩn'))
      .map((restaurant, index) => {
      const existing = originalRestaurants.find((item) => item.id === restaurant.id)
        || originalRestaurants.find((item) => (
          String(item.name).toLocaleLowerCase('vi')
            .startsWith(String(restaurant.name || '').toLocaleLowerCase('vi'))
        ));
      return {
        ...existing,
        ...restaurant,
        id: existing?.id || restaurant.id || `live-restaurant-${index}`,
        name: String(restaurant.name || existing?.name || 'Quán ăn'),
        address: String(restaurant.address || existing?.address || 'Buôn Ma Thuột, Đắk Lắk'),
      };
      });
  }

  if (Array.isArray(content.products)) {
    const originalProducts = FASTFOOD_DATA.products;
    const categories = FASTFOOD_DATA.categories;
    FASTFOOD_DATA.products = content.products
      .filter((product) => !String(product.status || '').toLocaleLowerCase('vi').includes('tạm hết'))
      .map((product, index) => {
        const name = String(product.name || 'Món ăn mới');
        const existing = originalProducts.find((item) => item.name.toLocaleLowerCase('vi') === name.toLocaleLowerCase('vi'))
          || originalProducts.find((item) => String(item.id).replace('prod-', '') === String(product.id).replace(/^prd-/, ''));
        const category = categories.find((item) => item.name.toLocaleLowerCase('vi') === String(product.category || '').toLocaleLowerCase('vi'));
        const restaurantName = String(product.restaurant || existing?.restaurantName || 'FASTFOOD');
        const restaurant = getRestaurantForProduct({
          restaurantId: product.restaurantId || existing?.restaurantId,
          restaurantName,
        });
        return {
          ...existing,
          id: existing?.id || `live-product-${product.id || index}`,
          name,
          slug: existing?.slug || name.toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
          category: category?.id || existing?.category || 'all',
          restaurantName: restaurant?.name || restaurantName,
          restaurantId: restaurant?.id || product.restaurantId || existing?.restaurantId,
          address: String(product.address || existing?.address || restaurant?.address || ''),
          url: String(product.url ?? existing?.url ?? ''),
          restaurantUrl: String(restaurant ? (restaurant.url || '') : (product.restaurantUrl || '')),
          price: Number(product.price) || existing?.price || 0,
          image: existing?.image || 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&auto=format&fit=crop&q=80',
          isBestSeller: existing?.isBestSeller ?? true,
          isFlashDeal: existing?.isFlashDeal || false,
          description: existing?.description || '',
          toppings: existing?.toppings || [],
        };
      });
  }
}

applySavedWebsiteContent();
window.FASTFOOD_DATA = FASTFOOD_DATA;
window.getFastfoodRestaurantForProduct = getRestaurantForProduct;
window.getFastfoodRestaurantAddressForProduct = getRestaurantAddressForProduct;
window.getFastfoodRestaurantUrlForProduct = getRestaurantUrlForProduct;
window.getFastfoodProductUrl = getProductUrl;
window.getFastfoodRestaurantUrl = getRestaurantUrl;
window.isFastfoodExternalUrl = isExternalWebsiteUrl;
