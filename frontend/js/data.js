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
      count: 13,
    },
    {
      id: 'com',
      name: 'Cơm & Món Việt',
      image: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=240&auto=format&fit=crop&q=80',
      count: 2,
    },
    {
      id: 'burger',
      name: 'Burger & Gà Rán',
      image: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=240&auto=format&fit=crop&q=80',
      count: 3,
    },
    {
      id: 'tra-sua',
      name: 'Trà Sữa & Đồ Uống',
      image: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=240&auto=format&fit=crop&q=80',
      count: 1,
    },
    {
      id: 'pizza',
      name: 'Pizza & Mỳ Ý',
      image: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=240&auto=format&fit=crop&q=80',
      count: 2,
    },
    {
      id: 'an-vat',
      name: 'Ăn Vặt & Bánh',
      image: 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=240&auto=format&fit=crop&q=80',
      count: 3,
    },
    {
      id: 'healthy',
      name: 'Salad & Healthy',
      image: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=240&auto=format&fit=crop&q=80',
      count: 1,
    },
    {
      id: 'cafe',
      name: 'Cà Phê & Trà',
      image: 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=240&auto=format&fit=crop&q=80',
      count: 0,
    },
    {
      id: 'sushi',
      name: 'Sushi & Món Nhật',
      image: 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=240&auto=format&fit=crop&q=80',
      count: 1,
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
      banner: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=120&auto=format&fit=crop&q=80',
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
      banner: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=120&auto=format&fit=crop&q=80',
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
    {
      id: 'res-5',
      name: 'Phở Bếp Việt',
      slug: 'pho-bep-viet',
      category: 'com',
      rating: 4.8,
      reviewCount: 126,
      deliveryTime: '15 - 20 phút',
      distance: '1.4 km',
      minOrder: 35000,
      banner: 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=120&auto=format&fit=crop&q=80',
      address: '23 Lý Thường Kiệt, Buôn Ma Thuột, Đắk Lắk',
      promo: 'MÓN VIỆT',
      isVerified: true,
      tags: ['Phở Bò', 'Nước Dùng Ninh Xương'],
    },
    {
      id: 'res-6',
      name: 'Sushi Maki House',
      slug: 'sushi-maki-house',
      category: 'sushi',
      rating: 4.8,
      reviewCount: 94,
      deliveryTime: '20 - 25 phút',
      distance: '1.8 km',
      minOrder: 50000,
      banner: 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=120&auto=format&fit=crop&q=80',
      address: '88 Nguyễn Công Trứ, Buôn Ma Thuột, Đắk Lắk',
      promo: 'SUSHI TƯƠI',
      isVerified: true,
      tags: ['Sushi Cá Hồi', 'Món Nhật'],
    },
    {
      id: 'res-7',
      name: 'Burger Gà Giòn',
      slug: 'burger-ga-gion',
      category: 'burger',
      rating: 4.7,
      reviewCount: 83,
      deliveryTime: '10 - 15 phút',
      distance: '1.1 km',
      minOrder: 20000,
      banner: 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=120&auto=format&fit=crop&q=80',
      address: '16 Hoàng Diệu, Buôn Ma Thuột, Đắk Lắk',
      promo: 'GÀ GIÒN MỚI NÓNG',
      isVerified: true,
      tags: ['Burger Gà', 'Sốt Bơ Đặc Biệt'],
    },
    {
      id: 'res-8',
      name: 'Tiệm Bánh Bonjour',
      slug: 'tiem-banh-bonjour',
      category: 'an-vat',
      rating: 4.9,
      reviewCount: 72,
      deliveryTime: '15 - 20 phút',
      distance: '2 km',
      minOrder: 30000,
      banner: 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=120&auto=format&fit=crop&q=80',
      address: '102 Y Moan, Buôn Ma Thuột, Đắk Lắk',
      promo: 'NƯỚNG MỖI NGÀY',
      isVerified: true,
      tags: ['Bánh Croissant', 'Bánh Ngọt'],
    },
    {
      id: 'res-9',
      name: 'Bếp Sáng Mộc Miên',
      slug: 'bep-sang-moc-mien',
      category: 'an-vat',
      rating: 4.7,
      reviewCount: 68,
      deliveryTime: '15 - 20 phút',
      distance: '2.3 km',
      minOrder: 30000,
      banner: 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=120&auto=format&fit=crop&q=80',
      address: '41 Ama Khê, Buôn Ma Thuột, Đắk Lắk',
      promo: 'BỮA SÁNG',
      isVerified: true,
      tags: ['Pancake', 'Bữa Sáng'],
    },
    {
      id: 'res-10',
      name: 'Burger King Station - Chi Nhánh Nguyễn Tất Thành',
      slug: 'burger-king-nguyen-tat-thanh',
      category: 'burger',
      rating: 4.8,
      reviewCount: 98,
      deliveryTime: '18 - 22 phút',
      distance: '1.6 km',
      minOrder: 30000,
      banner: 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=120&auto=format&fit=crop&q=80',
      address: '54 Nguyễn Tất Thành, Buôn Ma Thuột, Đắk Lắk',
      promo: 'GÀ RÁN GIÒN',
      isVerified: true,
      tags: ['Gà Rán', 'Món Ăn Nóng'],
    },
    {
      id: 'res-11',
      name: 'Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh',
      slug: 'com-tam-phan-chu-trinh',
      category: 'com',
      rating: 4.7,
      reviewCount: 64,
      deliveryTime: '15 - 20 phút',
      distance: '1.3 km',
      minOrder: 25000,
      banner: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=120&auto=format&fit=crop&q=80',
      address: '36 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk',
      promo: 'SALAD TƯƠI',
      isVerified: true,
      tags: ['Salad', 'Món Ăn Healthy'],
    },
    {
      id: 'res-12',
      name: 'Pizza Hut Italia - Chi Nhánh Y Jút',
      slug: 'pizza-hut-y-jut',
      category: 'pizza',
      rating: 4.7,
      reviewCount: 112,
      deliveryTime: '20 - 25 phút',
      distance: '2.1 km',
      minOrder: 50000,
      banner: 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=120&auto=format&fit=crop&q=80',
      address: '08 Y Jút, Buôn Ma Thuột, Đắk Lắk',
      promo: 'MÌ Ý TƯƠI',
      isVerified: true,
      tags: ['Mì Ý', 'Món Ý'],
    },
    {
      id: 'res-13',
      name: 'Burger King Station - Chi Nhánh Lê Duẩn',
      slug: 'burger-king-le-duan',
      category: 'an-vat',
      rating: 4.8,
      reviewCount: 215,
      deliveryTime: '12 - 18 phút',
      distance: '2.4 km',
      minOrder: 20000,
      banner: 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=120&auto=format&fit=crop&q=80',
      address: '102 Lê Duẩn, Buôn Ma Thuột, Đắk Lắk',
      promo: 'ĂN VẶT',
      isVerified: true,
      tags: ['Khoai Tây Chiên', 'Ăn Vặt'],
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
      image: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=600&auto=format&fit=crop&q=80',
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
      image: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80',
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
      restaurantId: 'res-10',
      restaurantName: 'Burger King Station - Chi Nhánh Nguyễn Tất Thành',
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
      restaurantId: 'res-11',
      restaurantName: 'Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh',
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
      restaurantId: 'res-12',
      restaurantName: 'Pizza Hut Italia - Chi Nhánh Y Jút',
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
      restaurantId: 'res-13',
      restaurantName: 'Burger King Station - Chi Nhánh Lê Duẩn',
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
    {
      id: 'prod-9',
      name: 'Phở Bò Tái Nạm Nước Dùng Ninh Xương',
      slug: 'pho-bo-tai-nam',
      restaurantId: 'res-5',
      restaurantName: 'Phở Bếp Việt',
      category: 'com',
      price: 69000,
      oldPrice: 79000,
      rating: 4.8,
      reviews: 126,
      time: '15 phút',
      image: 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: false,
      description: 'Bánh phở mềm, thịt bò tái và nạm bò ăn kèm nước dùng ninh xương thơm ngọt, rau thơm và chanh tươi.',
      toppings: [{ name: 'Thêm thịt bò', price: 20000 }],
    },
    {
      id: 'prod-10',
      name: 'Sushi Cá Hồi Cuộn Bơ Nhật Bản',
      slug: 'sushi-ca-hoi-cuon-bo',
      restaurantId: 'res-6',
      restaurantName: 'Sushi Maki House',
      category: 'sushi',
      price: 89000,
      oldPrice: 109000,
      rating: 4.8,
      reviews: 94,
      time: '20 phút',
      image: 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: false,
      description: 'Cơm sushi dẻo thơm cuộn cá hồi tươi và bơ chín béo nhẹ, dùng kèm gừng hồng và nước tương Nhật.',
      toppings: [{ name: 'Thêm sashimi cá hồi', price: 30000 }],
    },
    {
      id: 'prod-11',
      name: 'Burger Gà Giòn Sốt Bơ Đặc Biệt',
      slug: 'burger-ga-gion-sot-bo',
      restaurantId: 'res-7',
      restaurantName: 'Burger Gà Giòn',
      category: 'burger',
      price: 39000,
      oldPrice: 49000,
      rating: 4.7,
      reviews: 83,
      time: '10 phút',
      image: 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: false,
      description: 'Burger gà giòn rụm, rau tươi và sốt bơ béo nhẹ trong chiếc bánh mềm nóng.',
      toppings: [{ name: 'Thêm phô mai', price: 10000 }],
    },
    {
      id: 'prod-12',
      name: 'Bánh Croissant Bơ Pháp Nướng Giòn',
      slug: 'croissant-bo-phap',
      restaurantId: 'res-8',
      restaurantName: 'Tiệm Bánh Bonjour',
      category: 'an-vat',
      price: 35000,
      oldPrice: 45000,
      rating: 4.9,
      reviews: 72,
      time: '12 phút',
      image: 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: false,
      description: 'Bánh croissant nhiều lớp cán bơ thơm, nướng vàng giòn bên ngoài và mềm xốp bên trong.',
      toppings: [{ name: 'Mứt dâu nhà làm', price: 8000 }],
    },
    {
      id: 'prod-13',
      name: 'Pancake Mật Ong Trái Cây Tươi',
      slug: 'pancake-mat-ong-trai-cay',
      restaurantId: 'res-9',
      restaurantName: 'Bếp Sáng Mộc Miên',
      category: 'an-vat',
      price: 49000,
      oldPrice: 59000,
      rating: 4.7,
      reviews: 68,
      time: '15 phút',
      image: 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80',
      isBestSeller: false,
      isFlashDeal: false,
      description: 'Pancake mềm xốp ăn cùng mật ong thơm dịu, bơ lạt và trái cây tươi theo mùa.',
      toppings: [{ name: 'Thêm kem tươi', price: 10000 }],
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

const FLASH_DEALS_STORAGE_KEY = 'fastfood-flash-deals-v1';
const FLASH_DEALS_DURATION_MS = 60 * 60 * 1000;
const FLASH_DEALS_COUNT = 5;

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
    const addedCategories = originalCategories.filter((category) => (
      category.id === 'sushi' && !categories.some((item) => item.id === category.id)
    ));
    FASTFOOD_DATA.categories = [
      originalCategories.find((category) => category.id === 'all'),
      ...categories,
      ...addedCategories,
    ];
  }

  if (Array.isArray(content.restaurants)) {
    const originalRestaurants = FASTFOOD_DATA.restaurants;
    const restaurants = content.restaurants
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
    const addedRestaurants = originalRestaurants.filter((restaurant) => (
      Number(restaurant.id.replace('res-', '')) >= 5
      && !restaurants.some((item) => item.id === restaurant.id)
    ));
    FASTFOOD_DATA.restaurants = [...restaurants, ...addedRestaurants];
  }

  if (Array.isArray(content.products)) {
    const originalProducts = FASTFOOD_DATA.products;
    const categories = FASTFOOD_DATA.categories;
    const liveProducts = content.products
      .filter((product) => !String(product.status || '').toLocaleLowerCase('vi').includes('tạm hết'))
      .map((product, index) => {
        const name = String(product.name || 'Món ăn mới');
        const sameNamedProduct = originalProducts.find((item) => (
          item.name.toLocaleLowerCase('vi') === name.toLocaleLowerCase('vi')
        ));
        const existing = sameNamedProduct
          || originalProducts.find((item) => String(item.id).replace('prod-', '') === String(product.id).replace(/^prd-/, ''));
        const image = String(product.image || sameNamedProduct?.image || '').trim();
        if (!image) {
          console.error(`Ẩn món "${name}" vì chưa có ảnh riêng khớp với món ăn.`);
          return null;
        }
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
          oldPrice: Number(product.oldPrice) || existing?.oldPrice || 0,
          image,
          isBestSeller: existing?.isBestSeller ?? true,
          isFlashDeal: existing?.isFlashDeal || false,
          description: String(product.description || sameNamedProduct?.description || ''),
          toppings: Array.isArray(product.toppings) ? product.toppings : (existing?.toppings || []),
        };
      })
      .filter(Boolean);
    let addedProducts = originalProducts.filter((product) => (
      Number(product.id.replace('prod-', '')) >= 9
      && !liveProducts.some((item) => item.id === product.id)
    ));
    if (liveProducts.length + addedProducts.length <= FLASH_DEALS_COUNT) {
      addedProducts = originalProducts.filter((product) => (
        !liveProducts.some((item) => item.id === product.id)
      ));
    }
    FASTFOOD_DATA.products = [...liveProducts, ...addedProducts];
  }
}

applySavedWebsiteContent();

function chooseFlashDeals(products, excludedIds = []) {
  const excluded = new Set(excludedIds);
  let candidates = products.filter((product) => !excluded.has(product.id));
  if (candidates.length < FLASH_DEALS_COUNT) candidates = [...products];

  for (let index = candidates.length - 1; index > 0; index--) {
    const swapIndex = Math.floor(Math.random() * (index + 1));
    [candidates[index], candidates[swapIndex]] = [candidates[swapIndex], candidates[index]];
  }
  return candidates.slice(0, FLASH_DEALS_COUNT);
}

function getActiveFlashDeals(now = Date.now()) {
  const products = FASTFOOD_DATA.products;
  if (products.length <= FLASH_DEALS_COUNT) {
    return { products, endsAt: now + FLASH_DEALS_DURATION_MS };
  }

  let state;
  try {
    state = JSON.parse(localStorage.getItem(FLASH_DEALS_STORAGE_KEY) || 'null');
  } catch (error) {
    console.error('Không thể đọc lịch flash sale đã lưu:', error);
  }

  const currentIds = new Set(products.map((product) => product.id));
  const stateIsValid = state
    && Number.isFinite(state.startedAt)
    && Array.isArray(state.productIds)
    && state.productIds.length === FLASH_DEALS_COUNT
    && state.productIds.every((id) => currentIds.has(id));
  let shouldPersist = !stateIsValid;

  if (!stateIsValid) {
    state = {
      startedAt: now,
      productIds: chooseFlashDeals(products).map((product) => product.id),
    };
  } else if (now >= state.startedAt + FLASH_DEALS_DURATION_MS) {
    const elapsedPeriods = Math.floor((now - state.startedAt) / FLASH_DEALS_DURATION_MS);
    state.startedAt += elapsedPeriods * FLASH_DEALS_DURATION_MS;
    state.productIds = chooseFlashDeals(products, state.productIds).map((product) => product.id);
    shouldPersist = true;
  }

  if (shouldPersist) {
    try {
      localStorage.setItem(FLASH_DEALS_STORAGE_KEY, JSON.stringify(state));
    } catch (error) {
      console.error('Không thể lưu lịch flash sale:', error);
    }
  }

  return {
    products: state.productIds
      .map((id) => products.find((product) => product.id === id))
      .filter(Boolean),
    endsAt: state.startedAt + FLASH_DEALS_DURATION_MS,
  };
}

window.FASTFOOD_DATA = FASTFOOD_DATA;
window.getActiveFlashDeals = getActiveFlashDeals;
window.getFastfoodRestaurantForProduct = getRestaurantForProduct;
window.getFastfoodRestaurantAddressForProduct = getRestaurantAddressForProduct;
window.getFastfoodRestaurantUrlForProduct = getRestaurantUrlForProduct;
window.getFastfoodProductUrl = getProductUrl;
window.getFastfoodRestaurantUrl = getRestaurantUrl;
window.isFastfoodExternalUrl = isExternalWebsiteUrl;
