/**
 * FASTFOOD REAL DATA STORE
 * Dữ liệu hình ảnh thật 100%, chất lượng cao, chuẩn giao diện Food Delivery cao cấp
 */

const FASTFOOD_DATA = {
  // 1. Danh mục món ăn (Hình ảnh thật từng món thơm ngon)
  categories: [
      {
          "id": "all",
          "name": "Tất Cả Món",
          "image": "https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=240&auto=format&fit=crop&q=80",
          "count": 260
      },
      {
          "id": "com",
          "name": "Cơm & Món Việt",
          "image": "https://images.unsplash.com/photo-1512058564366-18510be2db19?w=240&auto=format&fit=crop&q=80",
          "count": 40
      },
      {
          "id": "burger",
          "name": "Burger & Gà Rán",
          "image": "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=240&auto=format&fit=crop&q=80",
          "count": 60
      },
      {
          "id": "tra-sua",
          "name": "Trà Sữa & Đồ Uống",
          "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=240&auto=format&fit=crop&q=80",
          "count": 20
      },
      {
          "id": "pizza",
          "name": "Pizza & Mỳ Ý",
          "image": "https://images.unsplash.com/photo-1513104890138-7c749659a591?w=240&auto=format&fit=crop&q=80",
          "count": 40
      },
      {
          "id": "an-vat",
          "name": "Ăn Vặt & Bánh",
          "image": "https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=240&auto=format&fit=crop&q=80",
          "count": 60
      },
      {
          "id": "healthy",
          "name": "Salad & Healthy",
          "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=240&auto=format&fit=crop&q=80",
          "count": 20
      },
      {
          "id": "cafe",
          "name": "Cà Phê & Trà",
          "image": "https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=240&auto=format&fit=crop&q=80",
          "count": 0
      },
      {
          "id": "sushi",
          "name": "Sushi & Món Nhật",
          "image": "https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=240&auto=format&fit=crop&q=80",
          "count": 20
      }
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
      isLiked: false,
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
      banner: '/assets/images/foods/com-tam-dac-biet.jpg',
      logo: '/assets/images/foods/com-tam-suon-nuong.jpg',
      address: '68 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk',
      promo: 'FREESHIP',
      isVerified: true,
      tags: ['Cơm Sườn Nướng', 'Bì Chả Trứng', 'Canh Khổ Qua'],
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      isLiked: false,
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
      banner: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=120&auto=format&fit=crop&q=80',
      address: '36 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk',
      promo: 'SALAD TƯƠI',
      isVerified: true,
      tags: ['Salad', 'Món Ăn Healthy'],
      isLiked: false,
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
      banner: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800&auto=format&fit=crop&q=80',
      logo: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=120&auto=format&fit=crop&q=80',
      address: '08 Y Jút, Buôn Ma Thuột, Đắk Lắk',
      promo: 'MÌ Ý TƯƠI',
      isVerified: true,
      tags: ['Mì Ý', 'Món Ý'],
      isLiked: false,
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
      banner: '/assets/images/foods/banh-trang-nuong.jpg',
      logo: '/assets/images/foods/bap-xao.jpg',
      address: '102 Lê Duẩn, Buôn Ma Thuột, Đắk Lắk',
      promo: 'ĂN VẶT',
      isVerified: true,
      tags: ['Khoai Tây Chiên', 'Ăn Vặt'],
      isLiked: false,
    },
  ],

  // 3. Danh sách món ăn hình ảnh thật chụp trực diện, sắc nét, ngon miệng
  products: [
    {
        "id": "prod-1",
        "name": "Burger Bò Nướng Phô Mai Tan Chảy (Double Cheese)",
        "slug": "burger-bo-nuong-pho-mai",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 69000,
        "oldPrice": 89000,
        "rating": 4.9,
        "reviews": 142,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Thịt bò Úc 100% nướng lửa than hoa thơm nức mũi, kẹp phô mai Cheddar béo ngậy, sốt BBQ đặc chế và rau xà lách tươi giòn.",
        "toppings": [
            {
                "name": "Thêm phô mai lát Cheddar",
                "price": 10000
            },
            {
                "name": "Thịt xông khói giòn (Bacon)",
                "price": 15000
            },
            {
                "name": "Trứng ốp la lòng đào",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-2",
        "name": "Burger Bò Nướng Lửa Than Hoa (Whopper Classic)",
        "slug": "burger-bo-whopper-classic",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 89000,
        "oldPrice": 109000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt bò nướng lửa cỡ lớn đậm vị khói phương Tây, lát cà chua đỏ mọng và dưa chuột muối giòn tan.",
        "toppings": [
            {
                "name": "Gấp đôi thịt bò nướng",
                "price": 35000
            },
            {
                "name": "Thêm sốt BBQ khói",
                "price": 5000
            },
            {
                "name": "Phô mai lát",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-3",
        "name": "Burger Bò Tắm Sốt Nấm Truffle Đen Thượng Hạng",
        "slug": "burger-bo-sot-nam-truffle",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 99000,
        "oldPrice": 125000,
        "rating": 4.9,
        "reviews": 95,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1586190848861-99aa4a171e90?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sốt kem nấm Truffle đen quý hiếm phủ trọn miếng bò nướng mọng nước, hòa quyện sốt nấm xào thơm béo.",
        "toppings": [
            {
                "name": "Thêm sốt Truffle thơm ngậy",
                "price": 15000
            },
            {
                "name": "Nấm xào bơ tỏi",
                "price": 12000
            },
            {
                "name": "Phô mai Thụy Sĩ",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-4",
        "name": "Burger Bò Trứng Lòng Đào Thịt Ba Chỉ Xông Khói (Bacon)",
        "slug": "burger-bo-trung-long-dao-bacon",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 82000,
        "oldPrice": 99000,
        "rating": 4.7,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": true,
        "description": "Sự kết hợp hoàn hảo giữa thịt bò nướng than, trứng gà lòng đào tan chảy và 2 dải bacon áp chảo giòn rụm.",
        "toppings": [
            {
                "name": "Thêm 2 dải Bacon giòn",
                "price": 18000
            },
            {
                "name": "Trứng ốp la lòng đào",
                "price": 8000
            },
            {
                "name": "Khoai tây chiên ăn kèm",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-5",
        "name": "Burger Bò Cay Sốt Ớt Jalapeno Mễ Tây Cơ",
        "slug": "burger-bo-cay-jalapeno",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.8,
        "reviews": 76,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Dành cho tín đồ ăn cay! Ớt Jalapeno Mexico cay nồng kết hợp sốt chipotle mayo bốc khói kích thích vị giác.",
        "toppings": [
            {
                "name": "Thêm ớt Jalapeno ngâm giòn",
                "price": 8000
            },
            {
                "name": "Sốt cay bốc khói",
                "price": 6000
            },
            {
                "name": "Phô mai Cheddar",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-6",
        "name": "Burger Bò 3 Tầng Siêu Khủng (Triple Beast Burger)",
        "slug": "burger-bo-3-tang-sieu-khung",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 139000,
        "oldPrice": 169000,
        "rating": 4.9,
        "reviews": 130,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "3 lớp bò Úc nướng than hoa nặng gần 400g kết hợp 3 lát phô mai Cheddar tan chảy thơm lừng ngất ngây.",
        "toppings": [
            {
                "name": "Thêm lát bò nướng thứ 4",
                "price": 35000
            },
            {
                "name": "Thịt xông khói Bacon",
                "price": 15000
            },
            {
                "name": "Sốt phô mai béo ngậy",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-7",
        "name": "Burger Gà Phi Lê Nướng Sốt Teriyaki Mè Rang",
        "slug": "burger-ga-phi-le-teriyaki",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 65000,
        "oldPrice": 79000,
        "rating": 4.7,
        "reviews": 62,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt gà phi lê đùi rút xương ướp sốt teriyaki Nhật Bản nướng vàng óng, rắc mè trắng rang bùi thơm ngậy.",
        "toppings": [
            {
                "name": "Rong biển sấy giòn",
                "price": 6000
            },
            {
                "name": "Trứng ốp la",
                "price": 8000
            },
            {
                "name": "Sốt Teriyaki thêm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-8",
        "name": "Burger Cá Tuyết Phi Lê Chiên Giòn Sốt Tartar",
        "slug": "burger-ca-tuyet-sot-tartar",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 72000,
        "oldPrice": 85000,
        "rating": 4.8,
        "reviews": 54,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1521305916504-4a1121188589?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cá tuyết tươi tẩm bột xù chiên vàng rụm, thịt cá ngọt trắng mềm mịn kẹp sốt tartar chanh thì là thanh mát.",
        "toppings": [
            {
                "name": "Gấp đôi sốt Tartar chanh",
                "price": 6000
            },
            {
                "name": "Phô mai lát",
                "price": 10000
            },
            {
                "name": "Rau xà lách tươi giòn",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-9",
        "name": "Burger Tôm Hoàng Gia Giòn Rụm Sốt Kem Thousand Island",
        "slug": "burger-tom-hoang-gia",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 79000,
        "oldPrice": 95000,
        "rating": 4.9,
        "reviews": 81,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Chả tôm tươi nguyên con xay dối giữ trọn độ giòn ngọt, vỏ ngoài chiên giòn tan chấm sốt nghìn hòn đảo chua béo.",
        "toppings": [
            {
                "name": "Thêm phô mai lát",
                "price": 10000
            },
            {
                "name": "Trứng ốp la",
                "price": 8000
            },
            {
                "name": "Sốt Thousand Island",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-10",
        "name": "Burger Bò Sốt Phô Mai Xanh (Blue Cheese Burger)",
        "slug": "burger-bo-blue-cheese",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.8,
        "reviews": 45,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Vị đậm đà béo sâu đặc trưng của phô mai xanh kết hợp hành tây caramen ngọt dịu và bò nướng mọng nước.",
        "toppings": [
            {
                "name": "Thêm hành tây caramen",
                "price": 8000
            },
            {
                "name": "Bacon xông khói",
                "price": 15000
            },
            {
                "name": "Sốt phô mai xanh thêm",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-11",
        "name": "Combo Burger Bò Phô Mai + Khoai Tây Múi Cau + Coca-Cola",
        "slug": "combo-burger-bo-khoai-coca",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 99000,
        "oldPrice": 129000,
        "rating": 4.9,
        "reviews": 165,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Bữa ăn chuẩn Mỹ hoàn hảo gồm 1 Burger Bò Phô Mai, 1 phần khoai tây múi cau nướng muối giòn rụm và 1 ly Coca mát lạnh.",
        "toppings": [
            {
                "name": "Đổi sang khoai lắc phô mai",
                "price": 10000
            },
            {
                "name": "Thêm phô mai vào burger",
                "price": 10000
            },
            {
                "name": "Nâng size ly nước",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-12",
        "name": "Combo Cặp Đôi Burger King (2 Burger Bò + 2 Khoai + 2 Nước)",
        "slug": "combo-cap-doi-burger-king",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 189000,
        "oldPrice": 245000,
        "rating": 4.9,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1550547660-d9450f859349?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Set ăn thịnh soạn dành cho 2 người gồm 2 Burger Bò nướng cỡ vừa, 2 phần khoai tây chiên vàng và 2 nước ngọt có ga.",
        "toppings": [
            {
                "name": "Thêm 2 dải Bacon cho cả 2 bánh",
                "price": 25000
            },
            {
                "name": "Khoai tây sốt phô mai",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-13",
        "name": "Khoai Tây Múi Cau Nướng Muối Biển & Hương Thảo",
        "slug": "khoai-tay-mui-cau-nuong",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 39000,
        "oldPrice": 49000,
        "rating": 4.7,
        "reviews": 40,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai tây cắt múi cau để vỏ nướng vàng ruộm, tẩm muối biển tinh khiết và lá hương thảo thơm lừng phong cách châu Âu.",
        "toppings": [
            {
                "name": "Sốt tương cà & tương ớt",
                "price": 0
            },
            {
                "name": "Sốt phô mai chấm kèm",
                "price": 8000
            },
            {
                "name": "Sốt Mayonnaise tỏi",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-14",
        "name": "Khoai Tây Chiên Pháp Phủ Sốt Phô Mai Cheddar Nóng",
        "slug": "khoai-tay-chien-sot-pho-mai",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 75,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1585109649139-366815a0d713?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Khoai tây chiên vàng rộm ngập trong lớp sốt phô mai Cheddar nấu nóng sánh mịn, rắc thêm vụn thịt xông khói giòn tan.",
        "toppings": [
            {
                "name": "Thêm sốt phô mai",
                "price": 8000
            },
            {
                "name": "Vụn Bacon giòn",
                "price": 10000
            },
            {
                "name": "Ớt bột cay paprika",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-15",
        "name": "Vòng Hành Tây Chiên Xù Giòn Rụm (Crispy Onion Rings)",
        "slug": "vong-hanh-tay-chien-xu",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.6,
        "reviews": 38,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/onion-rings.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Hành tây ngọt thanh tẩm bột chiên xù vàng óng, giòn rụm bên ngoài và mềm mọng bên trong, chấm cùng sốt cocktail cay ngọt.",
        "toppings": [
            {
                "name": "Sốt chấm chua ngọt",
                "price": 0
            },
            {
                "name": "Sốt kem phô mai",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-16",
        "name": "Phô Mai Que Kéo Sợi Mozzarella Chiên Vàng (4 que)",
        "slug": "pho-mai-que-mozzarella",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 42000,
        "oldPrice": 52000,
        "rating": 4.8,
        "reviews": 84,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Phô mai Mozzarella nguyên chất kéo sợi dài bất tận, vỏ bột giòn rụm chấm tương cà chua ngọt cực cuốn.",
        "toppings": [
            {
                "name": "Thêm sốt mayonnaise tỏi",
                "price": 5000
            },
            {
                "name": "Tương ớt cay nồng",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-17",
        "name": "Salad Trộn Dầu Giấm Bò Nướng BBQ Than Hoa",
        "slug": "salad-tron-dau-giam-bo-nuong",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 59000,
        "oldPrice": 72000,
        "rating": 4.7,
        "reviews": 43,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Xà lách Romaine, cà chua bi, dưa leo và bắp ngọt trộn dầu giấm Ý, phủ những lát thịt bò nướng than hoa thơm nức.",
        "toppings": [
            {
                "name": "Thêm bò nướng than",
                "price": 20000
            },
            {
                "name": "Phô mai Parmesan bào",
                "price": 10000
            },
            {
                "name": "Sốt mè rang Nhật",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-18",
        "name": "Bánh Táo Nướng Quế Giòn Nóng Burger King (Apple Pie)",
        "slug": "banh-tao-nuong-que",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 29000,
        "oldPrice": 38000,
        "rating": 4.7,
        "reviews": 52,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Vỏ bánh ngàn lớp giòn tan bao bọc nhân táo tươi sốt caramel thơm lừng hương quế ấm áp, dùng nóng giòn sảng khoái.",
        "toppings": [
            {
                "name": "Thêm viên kem vani lạnh",
                "price": 15000
            },
            {
                "name": "Rưới sốt socola",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-19",
        "name": "Trà Chanh Vàng Mật Ong Đá Xay Mát Lạnh",
        "slug": "tra-chanh-vang-mat-ong",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.7,
        "reviews": 67,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/iced-lemon-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thức uống giải ngấy tuyệt hảo sau khi thưởng thức burger! Chanh vàng mọng nước hòa quyện mật ong nguyên chất.",
        "toppings": [
            {
                "name": "Thêm hạt chia hữu cơ",
                "price": 5000
            },
            {
                "name": "Thạch nha đam giòn",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-20",
        "name": "Nước Ngọt Coca-Cola Tươi Mát Lạnh (Ly Lớn 500ml)",
        "slug": "coca-cola-tuoi-mat-lanh",
        "restaurantId": "res-1",
        "restaurantName": "Burger King Station",
        "category": "burger",
        "price": 18000,
        "oldPrice": 22000,
        "rating": 4.8,
        "reviews": 115,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nước ngọt có ga sảng khoái mát lạnh giúp bữa ăn thêm phần trọn vị và sảng khoái bùng nổ.",
        "toppings": [
            {
                "name": "Thêm lát chanh tươi",
                "price": 2000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-21",
        "name": "Cơm Tấm Sườn Cốt Lết Nướng Than Hoa Bì Chả",
        "slug": "com-tam-suon-bi-cha",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 55000,
        "oldPrice": 65000,
        "rating": 4.9,
        "reviews": 280,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/com-tam-suon-nuong.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Dĩa cơm tấm hạt vỡ chuẩn vị, sườn cốt lết dày dặn ướp mật ong nướng than hoa vàng óng, bì dai giòn, chả trứng hấp béo bùi.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la lòng đào",
                "price": 7000
            },
            {
                "name": "Chén mỡ hành tép mỡ giòn",
                "price": 5000
            },
            {
                "name": "Canh rong biển thịt bằm",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-22",
        "name": "Cơm Tấm Sườn Bì Chả Trứng Ốp La (Suất Đặc Biệt)",
        "slug": "com-tam-suon-bi-cha-trung-dac-biet",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 65000,
        "oldPrice": 75000,
        "rating": 4.9,
        "reviews": 210,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/com-tam-suon-bi-cha.jpg",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Dĩa cơm thịnh soạn đủ 4 món tinh hoa: Miếng sườn to bản nướng mềm thơm, bì thính vàng ươm, chả trứng nấm mèo và trứng ốp la lòng đào.",
        "toppings": [
            {
                "name": "Thêm cây chả cua hấp",
                "price": 15000
            },
            {
                "name": "Tép mỡ giòn rụm",
                "price": 5000
            },
            {
                "name": "Chén đồ chua thêm",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-23",
        "name": "Cơm Tấm Sườn Cây Khổng Lồ Nướng Mật Ong Rừng",
        "slug": "com-tam-suon-cay-mat-ong",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.9,
        "reviews": 145,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/com-tam-dac-biet.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cọng sườn heo tơ cây dài chọn lọc, ướp mật ong rừng hoa cà phê Đắk Lắk và nước mắm nhỉ, nướng xém cạnh thơm nức mũi.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la",
                "price": 7000
            },
            {
                "name": "Canh khổ qua nhồi thịt",
                "price": 15000
            },
            {
                "name": "Thêm cơm tấm thơm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-24",
        "name": "Cơm Tấm Ba Chỉ Heo Rút Sườn Nướng Giòn Da",
        "slug": "com-tam-ba-chi-heo-nuong",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 52000,
        "oldPrice": 62000,
        "rating": 4.8,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/heo-quay.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Ba chỉ heo ba chỉ nạc mỡ đan xen đều đặn, nướng lửa than hoa giòn rụm phần da và mọng nước phần thịt.",
        "toppings": [
            {
                "name": "Thêm chả trứng hấp",
                "price": 10000
            },
            {
                "name": "Bì sợi thính thơm",
                "price": 8000
            },
            {
                "name": "Mỡ hành tép mỡ",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-25",
        "name": "Cơm Tấm Đùi Gà Nướng Mật Ong Rừng Vàng Ruộm",
        "slug": "com-tam-dui-ga-nuong-mat-ong",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 55000,
        "oldPrice": 65000,
        "rating": 4.8,
        "reviews": 114,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/dui-ga-nuong.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Nguyên chiếc đùi gà góc phần tư ướp gia vị cổ truyền và mật ong, nướng chín tới bóng bẩy, thịt gà mềm ngọt thơm lừng.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la",
                "price": 7000
            },
            {
                "name": "Đồ chua củ cải cà rốt",
                "price": 3000
            },
            {
                "name": "Canh rong biển thịt bằm",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-26",
        "name": "Cơm Tấm Đùi Gà Chiên Nước Mắm Tỏi Ớt Đậm Đà",
        "slug": "com-tam-dui-ga-chien-nuoc-mam",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 58000,
        "oldPrice": 68000,
        "rating": 4.8,
        "reviews": 86,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Đùi gà chiên lớp da giòn rụm ngào sốt nước mắm tỏi ớt kẹo sệt chua ngọt mặn mà đưa cơm vô cùng.",
        "toppings": [
            {
                "name": "Thêm mỡ hành tép mỡ",
                "price": 5000
            },
            {
                "name": "Trứng ốp la",
                "price": 7000
            },
            {
                "name": "Canh khổ qua",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-27",
        "name": "Cơm Tấm Chả Cua Biển Hấp Trứng Muối",
        "slug": "com-tam-cha-cua-trung-muoi",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 59000,
        "oldPrice": 70000,
        "rating": 4.9,
        "reviews": 78,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/suon-nuong-dia.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Chả làm từ thịt cua biển tươi, thịt heo nạc bằm, nấm mèo và lòng đỏ trứng muối bùi béo hấp cách thủy chín tới thơm lừng.",
        "toppings": [
            {
                "name": "Thêm miếng sườn nướng",
                "price": 25000
            },
            {
                "name": "Trứng ốp la",
                "price": 7000
            },
            {
                "name": "Canh chua cá hồi",
                "price": 18000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-28",
        "name": "Cơm Tấm Bò Xào Cần Tây Hành Tây Sốt Dầu Hào",
        "slug": "com-tam-bo-xao-can-tay",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 65000,
        "oldPrice": 78000,
        "rating": 4.7,
        "reviews": 52,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/bo-xao.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt bò phi lê xào lửa lớn cùng cần tây, hành tây và cà chua giòn ngọt, nước sốt bò đậm đà chan cơm tấm nóng hổi.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la",
                "price": 7000
            },
            {
                "name": "Thêm cơm tấm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-29",
        "name": "Cơm Tấm Xíu Mại Trứng Muối Sốt Cà Chua Tươi",
        "slug": "com-tam-xiu-mai-trung-muoi",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 48000,
        "oldPrice": 58000,
        "rating": 4.8,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/meatballs-tomato.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Viên xíu mại thịt heo bằm mềm mịn ôm trọn nhân trứng muối béo ngậy, ninh trong sốt cà chua tươi sánh mịn thơm ngào ngạt.",
        "toppings": [
            {
                "name": "Thêm 1 viên xíu mại",
                "price": 15000
            },
            {
                "name": "Bì sợi thính",
                "price": 8000
            },
            {
                "name": "Tép mỡ giòn",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-30",
        "name": "Cơm Tấm Thịt Ba Chỉ Kho Tàu Trứng Vịt Bùi Béo",
        "slug": "com-tam-thit-kho-tau",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 52000,
        "oldPrice": 62000,
        "rating": 4.7,
        "reviews": 70,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/thit-kho-tau.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt heo ba chỉ kho nước dừa xiêm béo mềm tan trong miệng, miếng thịt màu cánh gián và quả trứng vịt kho thấm đượm gia vị.",
        "toppings": [
            {
                "name": "Thêm trứng vịt kho",
                "price": 8000
            },
            {
                "name": "Dưa cải chua chấm nước kho",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-31",
        "name": "Cơm Tấm Cá Thu Kho Tiêu Tộ Đất Sài Gòn",
        "slug": "com-tam-ca-thu-kho-tieu",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 62000,
        "oldPrice": 75000,
        "rating": 4.8,
        "reviews": 59,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/ca-kho-to.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khúc cá thu tươi kho trong tộ đất với tiêu sọ Đắk Lắk cay thơm, nước cá sền sệt đậm đà chan cơm thơm ngon khó cưỡng.",
        "toppings": [
            {
                "name": "Thêm canh khổ qua",
                "price": 15000
            },
            {
                "name": "Rau luộc kho quẹt chấm kèm",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-32",
        "name": "Cơm Tấm Sườn Non Rang Muối Cay Hong Kong",
        "slug": "com-tam-suon-non-rang-muoi",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 68000,
        "oldPrice": 82000,
        "rating": 4.8,
        "reviews": 63,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1544025162-d76694265947?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Sườn non sụn giòn sần sật tẩm bột chiên vàng, xóc muối tỏi ớt rang khô đậm đà hấp dẫn vô cùng.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la",
                "price": 7000
            },
            {
                "name": "Chén mỡ hành tép mỡ",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-33",
        "name": "Cơm Tấm Tôm Rim Thịt Ba Chỉ Đậm Đà Cơm Nhà",
        "slug": "com-tam-tom-rim-ba-chi",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 58000,
        "oldPrice": 70000,
        "rating": 4.7,
        "reviews": 48,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1559847844-5315695dadae?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tôm đồng vỏ giòn thịt ngọt rim kẹo cùng thịt ba chỉ xắt hạt lựu, mặn mặn ngọt ngọt óng ả màu đường thốt nốt.",
        "toppings": [
            {
                "name": "Thêm chả trứng",
                "price": 10000
            },
            {
                "name": "Canh rau má thịt bằm",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-34",
        "name": "Cơm Tấm Mực Nhồi Thịt Sốt Cà Chua Chua Ngọt",
        "slug": "com-tam-muc-nhoi-thit",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 65000,
        "oldPrice": 78000,
        "rating": 4.8,
        "reviews": 55,
        "time": "15 - 20 phút",
        "image": "https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f1/Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_%282448208587%29.jpg/960px-Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_%282448208587%29.jpg",
        "imageCredit": {
            "author": "Alpha",
            "license": "CC BY-SA 2.0",
            "source": "https://commons.wikimedia.org/wiki/File:Meng_Hui%27s_Baked_squid_stuffed_with_rice_and_pinenuts_in_tomato_sauce_(2448208587).jpg",
            "licenseUrl": "https://creativecommons.org/licenses/by-sa/2.0/"
        },
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Mực ống tươi dày mình nhồi thịt bằm mộc nhĩ nấm hương, sốt cà chua chua thanh dậy mùi tiêu hành.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la",
                "price": 7000
            },
            {
                "name": "Mỡ hành tép mỡ",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-35",
        "name": "Tô Canh Khổ Qua Dồn Thịt Bằm Nước Trong Thanh Mát",
        "slug": "canh-kho-qua-don-thit",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.9,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/canh-kho-qua.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Món canh truyền thống giải nhiệt tuyệt vời! Khổ qua đắng thanh dồn thịt nạc vai xay nhuyễn, nước dùng xương hầm trong ngọt lịm.",
        "toppings": [
            {
                "name": "Thêm 1 khúc khổ qua thịt",
                "price": 12000
            },
            {
                "name": "Rắc thêm tiêu hành lá",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-36",
        "name": "Tô Canh Rong Biển Đậu Hũ Non Thịt Bằm",
        "slug": "canh-rong-bien-dau-hu-non",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 22000,
        "oldPrice": 28000,
        "rating": 4.8,
        "reviews": 90,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/canh-rong-bien.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Rong biển thanh mát bổ dưỡng nấu cùng đậu hũ non mềm mịn và thịt heo bằm, thơm ấm mùi gừng tươi xắt sợi.",
        "toppings": [
            {
                "name": "Thêm đậu hũ non",
                "price": 5000
            },
            {
                "name": "Thêm thịt bằm",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-37",
        "name": "Tô Canh Chua Cá Hồi Lá Giang Bạc Hà Đậm Vị Nam Bộ",
        "slug": "canh-chua-ca-hoi-la-giang",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 68,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/canh-chua.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Vị chua thanh mát của lá giang và me tươi quyện vị béo ngậy của lườn cá hồi tươi, kèm đậu bắp, bạc hà, giá đỗ giòn ngọt.",
        "toppings": [
            {
                "name": "Thêm cá hồi phi lê",
                "price": 15000
            },
            {
                "name": "Ớt sừng xắt lát cay nồng",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-38",
        "name": "Chả Cua Hấp Thơm Bùi Trứng Muối (Phần Thêm 1 Chiếc)",
        "slug": "cha-cua-hap-phan-them",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 18000,
        "oldPrice": 22000,
        "rating": 4.8,
        "reviews": 75,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/com-tam-suon-bi-cha.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Phần chả cua gọi thêm cho thực khách muốn ăn thêm chả, đẫm vị thịt cua và lòng đỏ trứng muối.",
        "toppings": [
            {
                "name": "Rưới thêm mỡ hành tép mỡ",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-39",
        "name": "Trà Sâm Dứa Hạt É Thơm Mát Giải Nhiệt",
        "slug": "tra-sam-dua-hat-e",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 15000,
        "oldPrice": 18000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Trà sâm dứa Đà Nẵng thơm nức nở kết hợp hạt é mát lành, chuẩn thức uống giải nhiệt số 1 quán cơm Sài Gòn xưa.",
        "toppings": [
            {
                "name": "Thêm hạt é",
                "price": 3000
            },
            {
                "name": "Thêm đá",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-40",
        "name": "Nước Mát Mía Lau Rễ Tranh Rong Biển Nấu Đường Phèn",
        "slug": "nuoc-mat-mia-lau-re-tranh",
        "restaurantId": "res-2",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa",
        "category": "com",
        "price": 18000,
        "oldPrice": 22000,
        "rating": 4.9,
        "reviews": 95,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/nuoc-mia.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nước mát nấu từ mía lau, rễ tranh, lá thuốc dòi và rong biển, vị ngọt thanh mát dịu từ đường phèn tự nhiên.",
        "toppings": [
            {
                "name": "Ít đá / Không đá",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-41",
        "name": "Trà Sữa Trân Châu Đường Đen Hoàng Gia (Size L)",
        "slug": "tra-sua-tran-chau-hoang-gia",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.9,
        "reviews": 310,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Trà đen Ceylon thơm nồng kết hợp sữa tươi thanh trùng béo nhẹ và trân châu đường đen dẻo dai nấu mới mỗi 3 tiếng.",
        "toppings": [
            {
                "name": "Kem Macchiato mặn béo",
                "price": 12000
            },
            {
                "name": "Trân châu trắng giòn hoàng gia",
                "price": 10000
            },
            {
                "name": "Thạch pudding trứng mịn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-42",
        "name": "Trà Đen Alisan Macchiato Kem Muối Biển (Size L)",
        "slug": "tra-alisan-macchiato",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 48000,
        "oldPrice": 58000,
        "rating": 4.9,
        "reviews": 220,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Trà núi cao Alisan Đài Loan nước vàng trong veo hương hoa thanh nhã, phủ lớp kem sữa phô mai mặn béo mịn màng dày 2cm.",
        "toppings": [
            {
                "name": "Gấp đôi kem Macchiato",
                "price": 15000
            },
            {
                "name": "Trân châu đen dẻo",
                "price": 8000
            },
            {
                "name": "Thạch dừa",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-43",
        "name": "Trà Sữa Oolong Nướng Trân Châu Hoàng Kim (Size L)",
        "slug": "tra-sua-oolong-nuong-hoang-kim",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 50000,
        "oldPrice": 62000,
        "rating": 4.8,
        "reviews": 175,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Lá trà Oolong sấy than hoa thơm lừng vị khói ấm áp, quyện cùng sữa béo ngọt và trân châu hoàng kim màu mật ong trong veo dai giòn.",
        "toppings": [
            {
                "name": "Trân châu hoàng kim",
                "price": 10000
            },
            {
                "name": "Kem phô mai Cheese",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-44",
        "name": "Trà Sữa Matcha Nhật Bản Đậu Đỏ Dẻo (Size L)",
        "slug": "tra-sua-matcha-dau-do",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 52000,
        "oldPrice": 65000,
        "rating": 4.8,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": true,
        "description": "Bột trà xanh Uji Kyoto nguyên chất đậm vị chát dịu kết hợp sữa béo thanh và hạt đậu đỏ Azuki ninh nhừ bùi ngọt.",
        "toppings": [
            {
                "name": "Thêm đậu đỏ Azuki",
                "price": 10000
            },
            {
                "name": "Trân châu trắng giòn",
                "price": 10000
            },
            {
                "name": "Kem trứng nướng",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-45",
        "name": "Trà Xanh Đào Dâu Tây Thạch Nha Đam Mát Lạnh (Size L)",
        "slug": "tra-xanh-dao-dau-tay",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 130,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Trà lài thơm ngát kết hợp mứt đào giòn ngọt và dâu tây tươi mọng, topping miếng đào ngâm giòn sần sật và nha đam thanh mát.",
        "toppings": [
            {
                "name": "Thêm 2 miếng đào ngâm",
                "price": 10000
            },
            {
                "name": "Thạch nha đam",
                "price": 8000
            },
            {
                "name": "Hạt chia hữu cơ",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-46",
        "name": "Trà Xoài Nhiệt Đới Hạt Chia Kem Cheese (Size L)",
        "slug": "tra-xoai-nhiet-doi-kem-cheese",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 52000,
        "oldPrice": 62000,
        "rating": 4.9,
        "reviews": 98,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/mango-fruit-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Xoài cát chín vàng xay nhuyễn cùng trà xanh nhài thanh khiết, hạt chia giàu dinh dưỡng và lớp kem phô mai béo ngậy trên miệng ly.",
        "toppings": [
            {
                "name": "Gấp đôi kem Cheese",
                "price": 15000
            },
            {
                "name": "Trân châu tuyết",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-47",
        "name": "Trà Vải Lài Hoa Hồng Thanh Mát (Size L)",
        "slug": "tra-vai-lai-hoa-hong",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 46000,
        "oldPrice": 56000,
        "rating": 4.7,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Hương thơm quyến rũ của cánh hoa hồng hữu cơ hòa quyện trà lài thanh tao và những trái vải thiều trắng mọng giòn ngọt.",
        "toppings": [
            {
                "name": "Thêm 3 trái vải mọng",
                "price": 12000
            },
            {
                "name": "Thạch lô hội",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-48",
        "name": "Trà Sữa Khoai Môn Tươi Sợi Dẻo (Taro Milk Tea)",
        "slug": "tra-sua-khoai-mon-tuoi",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 48000,
        "oldPrice": 58000,
        "rating": 4.8,
        "reviews": 112,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/taro-milk-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai môn tươi Đà Lạt hấp chín nghiền dẻo quánh, tạo nên ly trà sữa màu tím mộng mơ béo bùi tự nhiên không phẩm màu.",
        "toppings": [
            {
                "name": "Sốt khoai môn tươi dẻo",
                "price": 12000
            },
            {
                "name": "Pudding trứng",
                "price": 10000
            },
            {
                "name": "Trân châu đen",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-49",
        "name": "Trà Sữa Thái Đỏ Kem Cheese Trân Châu Đen",
        "slug": "tra-sua-thai-do-kem-cheese",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 42000,
        "oldPrice": 52000,
        "rating": 4.8,
        "reviews": 120,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Trà đỏ Thái Lan thơm nồng thảo mộc, sữa đặc ngọt béo ngậy và lớp kem phô mai sánh mịn phủ trên cùng.",
        "toppings": [
            {
                "name": "Trân châu đen dẻo",
                "price": 8000
            },
            {
                "name": "Kem cheese mặn",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-50",
        "name": "Trà Ô Long Bưởi Đỏ Mật Ong Rừng (Size L)",
        "slug": "tra-o-long-buoi-do",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 48000,
        "oldPrice": 58000,
        "rating": 4.7,
        "reviews": 74,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/peach-iced-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tép bưởi đỏ mọng nước chua thanh kết hợp trà Ô Long đậm vị và mật ong hoa cà phê, thức uống bổ sung vitamin C hoàn hảo.",
        "toppings": [
            {
                "name": "Thêm tép bưởi hồng",
                "price": 10000
            },
            {
                "name": "Hạt chia",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-51",
        "name": "Cà Phê Muối Cố Đô Đậm Đà Kem Béo Buôn Ma Thuột",
        "slug": "ca-phe-muoi-co-do",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 190,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cà phê Robusta Đắk Lắk nguyên chất pha phin đậm đặc, lớp kem sữa tươi đánh cùng muối biển hồng Himalaya mặn béo ngậy đỉnh cao.",
        "toppings": [
            {
                "name": "Gấp đôi kem muối béo",
                "price": 10000
            },
            {
                "name": "Thêm shot espresso",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-52",
        "name": "Cà Phê Cốt Dừa Đá Xay Béo Ngậy Thơm Nức",
        "slug": "ca-phe-cot-dua-da-xay",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 105,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cốt dừa Bến Tre xay mịn cùng sữa đặc và đá tuyết, rót cà phê đen nguyên chất thơm lừng lên trên bề mặt.",
        "toppings": [
            {
                "name": "Thêm dừa khô sấy giòn",
                "price": 6000
            },
            {
                "name": "Thạch cà phê dai giòn",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-53",
        "name": "Americano Cam Vàng Đá Xay Thanh Mát",
        "slug": "americano-cam-vang",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 39000,
        "oldPrice": 49000,
        "rating": 4.7,
        "reviews": 82,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Espresso nguyên chất chiết xuất áp suất cao kết hợp nước cam vàng Mỹ mọng nước và mật ong hoa rừng.",
        "toppings": [
            {
                "name": "Thêm lát cam vàng",
                "price": 5000
            },
            {
                "name": "Hạt chia",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-54",
        "name": "Trà Sữa Earl Grey Bá Tước Anh Quốc (Size L)",
        "slug": "tra-sua-earl-grey-ba-tuoc",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 49000,
        "oldPrice": 59000,
        "rating": 4.8,
        "reviews": 93,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Trà đen thượng hạng ướp tinh dầu cam Bergamot quý tộc tạo hương thơm sang trọng, vị chát êm ái quyện sữa ngậy.",
        "toppings": [
            {
                "name": "Kem macchiato",
                "price": 12000
            },
            {
                "name": "Trân châu trắng",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-55",
        "name": "Trà Chanh Mật Ong Kim Quất Nha Đam (Size L)",
        "slug": "tra-chanh-mat-ong-kim-quat",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/iced-lemon-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Chanh tươi, tắc mật ong thơm lừng kết hợp thạch nha đam thanh mát, vị chua ngọt giải nhiệt ngày hè cực đã.",
        "toppings": [
            {
                "name": "Thạch nha đam",
                "price": 8000
            },
            {
                "name": "Hạt chia",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-56",
        "name": "Sữa Tươi Trân Châu Đường Đen Phun Lửa (Crème Brûlée)",
        "slug": "sua-tuoi-tran-chau-creme-brulee",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.9,
        "reviews": 180,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/brown-sugar-boba.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sữa tươi thanh trùng Dalat Milk kết hợp trân châu nấu đường đen dẻo nóng, mặt trên là lớp kem trứng khè lửa giòn rụm thơm mùi caramel.",
        "toppings": [
            {
                "name": "Thêm lớp kem trứng khè lửa",
                "price": 15000
            },
            {
                "name": "Trân châu đường đen",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-57",
        "name": "Sữa Chua Lắc Dâu Tây & Việt Quất Tươi",
        "slug": "sua-chua-lac-dau-tay",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 78,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/yogurt-granola.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Sữa chua lên men tự nhiên sánh mịn đánh cùng mứt việt quất và dâu tây Đà Lạt tươi giòn ngọt lịm.",
        "toppings": [
            {
                "name": "Thạch dừa",
                "price": 8000
            },
            {
                "name": "Hạt chia",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-58",
        "name": "Sinh Tố Bơ Sáp Đắk Lắk Nước Cốt Dừa Thơm Béo",
        "slug": "sinh-to-bo-dak-lak",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 48000,
        "oldPrice": 58000,
        "rating": 4.9,
        "reviews": 160,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/avocado-smoothie.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bơ sáp loại 1 hái tại vườn Buôn Ma Thuột, cơm bơ vàng ươm dẻo quánh xay cùng nước cốt dừa và sữa đặc béo ngậy ngất ngây.",
        "toppings": [
            {
                "name": "Thêm dừa nạo sợi",
                "price": 5000
            },
            {
                "name": "Dừa khô giòn",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-59",
        "name": "Trà Sữa Hokkaido Vani Caramel Béo Thơm (Size L)",
        "slug": "tra-sua-hokkaido-caramel",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 49000,
        "oldPrice": 60000,
        "rating": 4.7,
        "reviews": 82,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Sữa bò Hokkaido Nhật Bản danh tiếng béo đậm đà kết hợp sốt caramel thơm ngọt và vani Madagascar tự nhiên.",
        "toppings": [
            {
                "name": "Pudding trứng",
                "price": 10000
            },
            {
                "name": "Trân châu đen",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-60",
        "name": "Bánh Bột Mì Nướng Croffle Phô Mai Trà Xanh (Croffle Matcha)",
        "slug": "croffle-matcha-pho-mai",
        "restaurantId": "res-3",
        "restaurantName": "GongCha Tea & Coffee",
        "category": "tra-sua",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 90,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/croffle-matcha.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh sừng bò ép nướng theo khuôn waffle giòn rụm bên ngoài xốp mềm bên trong, phủ sốt matcha và phô mai béo ngọt ăn kèm trà sữa.",
        "toppings": [
            {
                "name": "Thêm viên kem matcha",
                "price": 15000
            },
            {
                "name": "Hạnh nhân lát nướng",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-61",
        "name": "Pizza Hải Sản Sốt Pesto Ý Thượng Hạng (Size Vừa 9 inch)",
        "slug": "pizza-hai-san-pesto",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 139000,
        "oldPrice": 179000,
        "rating": 4.9,
        "reviews": 156,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Tôm sú tươi giòn, mực ống tươi và nghêu biển hòa quyện cùng sốt Pesto húng tây thơm nức, ngập tràn phô mai Mozzarella kéo sợi.",
        "toppings": [
            {
                "name": "Viền phô mai xúc xích nướng",
                "price": 35000
            },
            {
                "name": "Gấp đôi tôm mực tươi",
                "price": 30000
            },
            {
                "name": "Thêm phô mai Mozzarella",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-62",
        "name": "Pizza Bò Băm Nướng Phô Mai Xúc Xích BBQ (Size 9 inch)",
        "slug": "pizza-bo-bam-bbq",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 129000,
        "oldPrice": 159000,
        "rating": 4.8,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt bò băm ướp sốt BBQ đậm đà, xúc xích tiêu cay, nấm mỡ tươi và ớt chuông nướng giòn ngọt trên nền đế bánh xốp mềm.",
        "toppings": [
            {
                "name": "Viền phô mai béo ngậy",
                "price": 30000
            },
            {
                "name": "Gấp đôi phô mai",
                "price": 25000
            },
            {
                "name": "Thêm xúc xích nướng",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-63",
        "name": "Pizza 4 Loại Phô Mai Ý Kéo Sợi (Quattro Formaggi)",
        "slug": "pizza-4-loai-pho-mai",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 145000,
        "oldPrice": 185000,
        "rating": 4.9,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Đỉnh cao phô mai Ý! Sự kết hợp hoàn mỹ của Mozzarella kéo sợi, Gorgonzola thơm béo, Parmesan mặn mà và Ricotta bùi ngậy.",
        "toppings": [
            {
                "name": "Rưới mật ong rừng hoa thơm",
                "price": 10000
            },
            {
                "name": "Viền bánh phô mai nướng",
                "price": 30000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-64",
        "name": "Pizza Gà Nướng Nấm Rơm & Bơ Tỏi Đút Lò",
        "slug": "pizza-ga-nuong-nam",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 119000,
        "oldPrice": 149000,
        "rating": 4.7,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt gà nướng xé nhỏ ngấm gia vị bơ tỏi, nấm rơm tươi ngọt dịu và sốt kem trắng Alfredo béo mịn.",
        "toppings": [
            {
                "name": "Thêm nấm mỡ tươi",
                "price": 15000
            },
            {
                "name": "Thêm phô mai kéo sợi",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-65",
        "name": "Pizza Xúc Xích Ý Pepperoni Truyền Thống Nướng Giòn",
        "slug": "pizza-pepperoni-truyen-thong",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 125000,
        "oldPrice": 155000,
        "rating": 4.8,
        "reviews": 165,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Những lát xúc xích Pepperoni bò cay đỏ au nướng cong giòn trên nền sốt cà chua San Marzano và phô mai Mozzarella.",
        "toppings": [
            {
                "name": "Gấp đôi xúc xích Pepperoni",
                "price": 25000
            },
            {
                "name": "Viền phô mai xúc xích",
                "price": 35000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-66",
        "name": "Pizza Dăm Bông & Dứa Kiểu Hawaii (Hawaiian Pizza)",
        "slug": "pizza-hawaiian-dam-bong-dua",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 119000,
        "oldPrice": 145000,
        "rating": 4.7,
        "reviews": 125,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": true,
        "description": "Vị ngọt thanh mọng nước của từng lát dứa mật kết hợp thịt dăm bông hun khói và sốt cà chua truyền thống dễ ăn cực kỳ.",
        "toppings": [
            {
                "name": "Thêm dăm bông heo",
                "price": 20000
            },
            {
                "name": "Thêm dứa nướng",
                "price": 10000
            },
            {
                "name": "Thêm phô mai",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-67",
        "name": "Pizza Tôm Càng Sốt Bơ Tỏi Chanh Vàng Đút Lò",
        "slug": "pizza-tom-cang-bo-toi",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 159000,
        "oldPrice": 199000,
        "rating": 4.9,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Tôm càng tuyển chọn tươi sống áp chảo bơ tỏi thơm ngào ngạt, rưới chút chanh vàng Ý và phủ phô mai vàng óng.",
        "toppings": [
            {
                "name": "Viền phô mai phồng",
                "price": 30000
            },
            {
                "name": "Thêm tôm tươi",
                "price": 35000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-68",
        "name": "Pizza Nấm Truffle & Rau Chân Vịt (Pizza Chay Ý)",
        "slug": "pizza-nam-truffle-chay",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 135000,
        "oldPrice": 165000,
        "rating": 4.8,
        "reviews": 62,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Lựa chọn thanh đạm sang trọng với nấm mỡ, nấm đông cô, rau bina chân vịt tươi và sốt nấm Truffle đen quý phái.",
        "toppings": [
            {
                "name": "Thêm phô mai Mozzarella",
                "price": 25000
            },
            {
                "name": "Phô mai Parmesan",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-69",
        "name": "Pizza Thịt Heo Xông Khói & Phô Mai Cheddar Vàng",
        "slug": "pizza-bacon-cheddar",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 129000,
        "oldPrice": 159000,
        "rating": 4.7,
        "reviews": 75,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Dải thịt ba chỉ xông khói áp chảo giòn thơm ngập trong sốt phô mai Cheddar béo ngậy và hành tây ngọt dịu.",
        "toppings": [
            {
                "name": "Thêm dải bacon nướng",
                "price": 20000
            },
            {
                "name": "Viền phô mai",
                "price": 30000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-70",
        "name": "Pizza Calzone Bánh Gối Ý Gấp Nửa Nhân Bò Băm Phô Mai",
        "slug": "pizza-calzone-banh-goi-y",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 119000,
        "oldPrice": 145000,
        "rating": 4.8,
        "reviews": 70,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/calzone-pizza.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Pizza cuộn hình bán nguyệt truyền thống vùng Napoli, cắn vỡ lớp vỏ bánh giòn tan là dòng phô mai và thịt bò sốt cà chảy tràn.",
        "toppings": [
            {
                "name": "Chén sốt cà chua chấm thêm",
                "price": 10000
            },
            {
                "name": "Thêm phô mai bên trong",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-71",
        "name": "Mì Ý Spaghetti Bò Băm Sốt Cà Chua (Bolognese)",
        "slug": "spaghetti-bo-bam-bolognese",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 79000,
        "oldPrice": 95000,
        "rating": 4.8,
        "reviews": 185,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/spaghetti-bolognese.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sợi mì Ý luộc chuẩn Al Dente dai mềm, sốt bò băm hầm kỹ cùng rượu vang đỏ và cà chua bi Ý ngọt lành, rắc bột phô mai thơm phức.",
        "toppings": [
            {
                "name": "Rắc thêm phô mai Parmesan",
                "price": 15000
            },
            {
                "name": "Thêm thịt bò bằm",
                "price": 20000
            },
            {
                "name": "Bánh mì bơ tỏi ăn kèm",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-72",
        "name": "Mì Ý Sốt Kem Hải Sản Carbonara Tôm Mực Nấm",
        "slug": "spaghetti-hai-san-carbonara",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 135,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sốt kem tươi nấu cùng lòng đỏ trứng gà béo ngậy, tôm sú giòn ngọt và mực ống tươi đảo cùng nấm mỡ xào bơ tỏi.",
        "toppings": [
            {
                "name": "Thêm tôm mực",
                "price": 25000
            },
            {
                "name": "Phô mai bào",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-73",
        "name": "Nui Ống Xoắn Đút Lò Phô Mai Mozzarella Vàng Ruộm",
        "slug": "nui-dut-lo-pho-mai",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.8,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nui ống ngấm sốt bò bằm cà chua, mặt trên phủ kín lớp phô mai Mozzarella đút lò vàng xém thơm lừng kéo sợi hấp dẫn.",
        "toppings": [
            {
                "name": "Gấp đôi phô mai đút lò",
                "price": 20000
            },
            {
                "name": "Thịt xông khói giòn",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-74",
        "name": "Bánh Mì Bơ Tỏi Nướng Giòn Kiểu Pháp (Garlic Bread)",
        "slug": "banh-mi-bo-toi-phap",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 32000,
        "oldPrice": 42000,
        "rating": 4.8,
        "reviews": 120,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/garlic-bread.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh mì baguette nướng giòn rụm phết bơ lạt nguyên chất quyện tỏi bằm và lá mùi tây thơm ngát quyến rũ.",
        "toppings": [
            {
                "name": "Thêm lớp phô mai nướng chảy",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-75",
        "name": "Cánh Gà Nướng Sốt BBQ Mật Ong (6 miếng)",
        "slug": "canh-ga-nuong-bbq-mat-ong",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 69000,
        "oldPrice": 85000,
        "rating": 4.8,
        "reviews": 104,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cánh gà giữa tẩm ướp sốt BBQ mật ong đậm vị khói nướng trong lò đá, da gà vàng bóng óng ả thơm lừng.",
        "toppings": [
            {
                "name": "Sốt chấm phô mai cay",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-76",
        "name": "Mực Vòng Chiên Giòn Calamari Sốt Tartar Ý",
        "slug": "muc-vong-calamari-chot-tartar",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 65000,
        "oldPrice": 79000,
        "rating": 4.7,
        "reviews": 76,
        "time": "15 - 20 phút",
        "image": "https://thumb.wikimedia.org/wikipedia/commons/thumb/4/40/Deep-fried_Calamari_Rings.jpg/960px-Deep-fried_Calamari_Rings.jpg",
        "imageCredit": {
            "author": "Ceeseven",
            "license": "CC BY-SA 4.0",
            "source": "https://commons.wikimedia.org/wiki/File:Deep-fried_Calamari_Rings.jpg",
            "licenseUrl": "https://creativecommons.org/licenses/by-sa/4.0/"
        },
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Từng khoanh mực tươi tẩm bột chiên giòn rụm bên ngoài nhưng bên trong mực vẫn mềm mọng nước, chấm cùng sốt tartar chanh tươi.",
        "toppings": [
            {
                "name": "Thêm sốt Tartar",
                "price": 6000
            },
            {
                "name": "Thêm lát chanh vàng",
                "price": 2000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-77",
        "name": "Khoai Tây Chiên Phủ Phô Mai & Bột Thảo Mộc Đút Lò",
        "slug": "khoai-tay-phu-pho-mai-dut-lo",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai tây cọng lớn chiên vàng ươm, đút lò cùng phô mai Mozzarella và rắc lá thơm oregano chuẩn vị Ý.",
        "toppings": [
            {
                "name": "Thêm phô mai đút lò",
                "price": 15000
            },
            {
                "name": "Sốt tương cà",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-78",
        "name": "Salad Rau Trộn Kiểu Caesar với Ức Gà Nướng & Phô Mai",
        "slug": "salad-caesar-uc-ga",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 59000,
        "oldPrice": 72000,
        "rating": 4.8,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Xà lách Romaine tươi giòn trộn sốt Caesar cá cơm truyền thống, bánh mì nướng giòn vụn, phô mai Parmesan và ức gà nướng áp chảo.",
        "toppings": [
            {
                "name": "Thêm ức gà nướng",
                "price": 15000
            },
            {
                "name": "Bánh mì nướng giòn",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-79",
        "name": "Bánh Lava Socola Đút Lò Tan Chảy Kem Vani Lạnh",
        "slug": "banh-lava-socola-kem-vani",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 49000,
        "oldPrice": 62000,
        "rating": 4.9,
        "reviews": 115,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Món tráng miệng Ý quyến rũ! Xắn nhẹ lớp bánh nướng là dòng socola đen Bỉ ấm nóng tan chảy, ăn cùng viên kem vani mát lạnh tương phản.",
        "toppings": [
            {
                "name": "Thêm viên kem vani",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-80",
        "name": "Nước Ngọt Pepsi Tươi Có Ga Mát Lạnh (Ly Lớn 500ml)",
        "slug": "pepsi-tuoi-mat-lanh",
        "restaurantId": "res-4",
        "restaurantName": "Pizza Hut Italia",
        "category": "pizza",
        "price": 18000,
        "oldPrice": 22000,
        "rating": 4.8,
        "reviews": 130,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/cola-pepsi.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thức uống có ga kinh điển giúp cân bằng vị giác và thưởng thức trọn vẹn những miếng pizza phô mai béo ngậy.",
        "toppings": [
            {
                "name": "Thêm lát chanh",
                "price": 2000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-81",
        "name": "Phở Bò Tái Nạm Nước Dùng Ninh Xương Ống 18 Tiếng",
        "slug": "pho-bo-tai-nam",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 69000,
        "oldPrice": 79000,
        "rating": 4.9,
        "reviews": 198,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Nước dùng trong veo ninh từ xương ống bò Úc cùng hoa hồi, quế chi, thảo quả 18 tiếng ngọt thanh không mì chính. Bò tái mềm mọng, nạm bò giòn béo thơm lừng.",
        "toppings": [
            {
                "name": "Thêm quẩy giòn rụm (3 cái)",
                "price": 10000
            },
            {
                "name": "Trứng gà ta trần lòng đào",
                "price": 8000
            },
            {
                "name": "Thêm đĩa thịt bò tái",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-82",
        "name": "Phở Bò Tái Lăn Áp Chảo Thơm Lừng Tỏi Gừng",
        "slug": "pho-bo-tai-lan-ap-chao",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.9,
        "reviews": 142,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Thịt bò bắp thái mỏng lăn áp chảo trên lửa bùng cùng tỏi đập dập và gừng già, trút lên bát phở thơm ngút ngàn dậy vị Bắc.",
        "toppings": [
            {
                "name": "Thêm quẩy giòn",
                "price": 10000
            },
            {
                "name": "Chén tiết bò trần",
                "price": 10000
            },
            {
                "name": "Gấp đôi tỏi phi giòn",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-83",
        "name": "Bát Phở Bò Đặc Biệt (Tái, Nạm, Gầu, Gân, Bò Viên)",
        "slug": "pho-bo-dac-biet-thap-cam",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 215,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Tô phở thịnh soạn ngập tràn 5 loại thịt bò hảo hạng: Thịt bò tái mềm ngọt, nạm giòn, gầu béo ngậy, gân trong dẻo giòn và bò viên gân sần sật.",
        "toppings": [
            {
                "name": "Thêm bát tủy bò béo",
                "price": 20000
            },
            {
                "name": "Trứng gà trần",
                "price": 8000
            },
            {
                "name": "Quẩy giòn rụm",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-84",
        "name": "Phở Gầu Giòn Béo Ngậy Ninh Thảo Mộc",
        "slug": "pho-gau-gion-thao-moc",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 72000,
        "oldPrice": 85000,
        "rating": 4.8,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1576577445504-6af96477db52?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Miếng gầu bò có lớp mỡ trắng giòn sần sật không hề ngấy, quyện cùng nước dùng nóng rẫy đậm đà thảo mộc phương Bắc.",
        "toppings": [
            {
                "name": "Thêm đĩa gầu giòn",
                "price": 25000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            },
            {
                "name": "Trứng trần",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-85",
        "name": "Phở Bắp Bò Hoa Tươi Nhúng Tái Mềm Ngọt",
        "slug": "pho-bap-bo-hoa-nhung",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 78000,
        "oldPrice": 92000,
        "rating": 4.8,
        "reviews": 102,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bắp bò hoa thái mỏng như cánh lụa, nhúng vừa chín tới giữ trọn từng đường gân hoa giòn rụm và vị ngọt tự nhiên của thịt bò tươi.",
        "toppings": [
            {
                "name": "Thêm bắp bò hoa",
                "price": 28000
            },
            {
                "name": "Trứng gà trần",
                "price": 8000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-86",
        "name": "Phở Bò Sốt Vang Rượu Đỏ Rực Màu Gấc Hà Nội",
        "slug": "pho-bo-sot-vang-ha-noi",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.9,
        "reviews": 130,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Dẻ sườn bò và gân bò hầm nhừ cùng rượu vang đỏ, hạt tiêu và sốt gấc đỏ au sánh mịn, vị thơm lừng ấm áp cực kỳ cuốn hút.",
        "toppings": [
            {
                "name": "Bánh mì nướng ăn kèm",
                "price": 8000
            },
            {
                "name": "Thêm sốt vang",
                "price": 25000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-87",
        "name": "Phở Đuôi Bò Hầm Thuốc Bắc Bổ Dưỡng Đại Bổ",
        "slug": "pho-duoi-bo-ham-thuoc-bac",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 95000,
        "oldPrice": 120000,
        "rating": 4.9,
        "reviews": 76,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Khúc đuôi bò hầm cùng kỷ tử, đương quy, táo đỏ và hạt sen trong nhiều giờ, lớp da dẻo ngậy giòn sần sật đại bổ dưỡng.",
        "toppings": [
            {
                "name": "Thêm kỷ tử táo đỏ",
                "price": 10000
            },
            {
                "name": "Trứng trần",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-88",
        "name": "Phở Gân Bò Hầm Dẻo Giòn Sần Sật",
        "slug": "pho-gan-bo-ham-deo",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 68000,
        "oldPrice": 80000,
        "rating": 4.7,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Gân bò tươi làm sạch ninh vừa độ, trong suốt như ngọc, dẻo mềm nhưng cắn vào vẫn có độ giòn sần sật thú vị.",
        "toppings": [
            {
                "name": "Thêm đĩa gân bò",
                "price": 22000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-89",
        "name": "Phở Gà Ta Thả Vườn Xé Phay Lá Chanh Tươi",
        "slug": "pho-ga-ta-xe-phay",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 65000,
        "oldPrice": 78000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-ga.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt gà ta thả vườn Đắk Lắk da vàng ươm giòn sần sật, thịt ngọt thơm xé phay rắc lá chanh bắc thái chỉ, nước dùng gà thanh khiết.",
        "toppings": [
            {
                "name": "Thêm đùi gà chặt",
                "price": 25000
            },
            {
                "name": "Lòng mề gà xào",
                "price": 15000
            },
            {
                "name": "Trứng non lòng đào",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-90",
        "name": "Phở Đùi Gà Ta Chặt Miếng Chấm Muối Tiêu Chanh",
        "slug": "pho-dui-ga-ta-chat",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.8,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-ga.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nguyên chiếc đùi gà ta chặt miếng bày đẹp mắt trên tô phở, chấm đĩa muối tiêu chanh ớt hiểm cay nồng xuýt xoa.",
        "toppings": [
            {
                "name": "Thêm chùm trứng non",
                "price": 15000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-91",
        "name": "Phở Trộn Bò Nướng Tỏi Ớt & Đậu Phộng Rang (Phở Khô)",
        "slug": "pho-tron-bo-nuong",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 68000,
        "oldPrice": 82000,
        "rating": 4.8,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/bun-thit-nuong.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh phở tươi trộn sốt chua ngọt đặc chế, thịt bò xào tỏi thơm phức, rau thơm, giá đỗ, hành phi và đậu phộng rang giòn thơm, kèm bát nước dùng con.",
        "toppings": [
            {
                "name": "Thêm thịt bò xào",
                "price": 22000
            },
            {
                "name": "Thêm đậu phộng hành phi",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-92",
        "name": "Phở Xào Bò Cải Ngọt Giòn Rụm Lửa Lớn",
        "slug": "pho-xao-bo-cai-ngot",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.8,
        "reviews": 78,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1555126634-323283e090fa?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh phở áp chảo cháy cạnh thơm mùi khói chảo gang, xào cùng thịt bò bắp mọng nước và cải ngọt xanh non mướt mát.",
        "toppings": [
            {
                "name": "Thêm thịt bò xào",
                "price": 25000
            },
            {
                "name": "Thêm rau cải ngọt",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-93",
        "name": "Phở Cuốn Bò Xào Rau Thơm Nước Chấm Tỏi Ớt (5 chiếc)",
        "slug": "pho-cuon-bo-xao-ha-noi",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 55000,
        "oldPrice": 65000,
        "rating": 4.9,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/goi-cuon.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh phở tráng mỏng mềm mướt cuốn cùng thịt bò xào gừng tỏi, rau xà lách, rau mùi húng láng, chấm nước mắm chua ngọt đu đủ giòn.",
        "toppings": [
            {
                "name": "Thêm 1 đĩa phở cuốn (3 chiếc)",
                "price": 30000
            },
            {
                "name": "Chén nước chấm thêm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-94",
        "name": "Bánh Phở Chiên Phồng Xốt Thịt Bò Cải Xanh",
        "slug": "banh-pho-chien-phong-sot-bo",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 79000,
        "oldPrice": 95000,
        "rating": 4.8,
        "reviews": 68,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1555126634-323283e090fa?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Từng miếng bánh phở vuông vức chiên phồng xốp giòn rụm như bánh phồng tôm, rưới sốt thịt bò xào cải xanh sánh vàng óng ả.",
        "toppings": [
            {
                "name": "Thêm sốt bò xào cải",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-95",
        "name": "Bát Tiết Hầm Trứng Gà Ta Lòng Đào Hành Trần",
        "slug": "tiet-ham-trung-ga-ta",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.8,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tiết bò mềm mướt không tanh hầm trong nước dùng phở bò ngọt lịm, kèm 1 quả trứng gà ta trần lòng đào béo ngậy và hành hoa.",
        "toppings": [
            {
                "name": "Thêm quả trứng trần",
                "price": 8000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-96",
        "name": "Chén Tủy Bò Béo Ngậy Hành Trần Tiêu Sọ",
        "slug": "chen-tuy-bo-beo-ngay",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 30000,
        "oldPrice": 38000,
        "rating": 4.9,
        "reviews": 70,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/pho-bo.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tủy bò tươi lấy từ xương ống hầm béo ngậy, trần tái lăn cùng hành lá và hạt tiêu sọ Đắk Lắk thơm cay ấm nồng.",
        "toppings": [
            {
                "name": "Thêm hành trần tái",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-97",
        "name": "Quẩy Giòn Rụm Chiên Mới Ăn Kèm Phở (Phần 4 chiếc)",
        "slug": "quay-gion-rum-an-pho",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 12000,
        "oldPrice": 15000,
        "rating": 4.9,
        "reviews": 180,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/baguette-bread.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Quẩy vàng ruộm chiên nóng giòn xốp rụm, nhúng ngập vào nước dùng phở bò hút trọn tinh hoa nước hầm xương thơm ngọt.",
        "toppings": [
            {
                "name": "Chén giấm tỏi ớt ngâm",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-98",
        "name": "Trứng Gà Ta Trần Nước Dùng Phở (1 quả lòng đào)",
        "slug": "trung-ga-ta-tran-long-dao",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 8000,
        "oldPrice": 10000,
        "rating": 4.9,
        "reviews": 150,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Quả trứng gà ta nuôi thả tự nhiên trần vào nồi nước dùng sôi sùng sục, lòng trắng vừa chín tới, lòng đỏ béo dẻo tan trên đầu lưỡi.",
        "toppings": [
            {
                "name": "Rắc thêm tiêu sọ hành hoa",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-99",
        "name": "Trà Đá Hoa Nhài Chuẩn Vị Bắc Thơm Ngát",
        "slug": "tra-da-hoa-nhai-vi-bac",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 8000,
        "oldPrice": 10000,
        "rating": 4.8,
        "reviews": 200,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Trà búp Thái Nguyên ướp hoa nhài tươi tự nhiên, chát dịu hậu ngọt sâu và mát lạnh thơm ngát sau khi ăn bát phở nóng.",
        "toppings": [
            {
                "name": "Thêm đá mát",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-100",
        "name": "Sữa Đậu Nành Nấu Lá Dứa Nguyên Chất (Nóng / Đá)",
        "slug": "sua-dau-nanh-la-dua",
        "restaurantId": "res-5",
        "restaurantName": "Phở Bếp Việt",
        "category": "com",
        "price": 15000,
        "oldPrice": 20000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/soy-milk.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Đậu nành hạt tuyển chọn xay nấu thủ công cùng lá dứa thơm, không chất bảo quản, sánh mịn béo bùi thanh ngọt.",
        "toppings": [
            {
                "name": "Chọn uống Nóng hoặc Đá",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-101",
        "name": "Sushi Cá Hồi Cuộn Bơ Sáp & Trứng Cá Chuồn (8 khoanh)",
        "slug": "sushi-ca-hoi-cuon-bo",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 89000,
        "oldPrice": 109000,
        "rating": 4.9,
        "reviews": 168,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cá hồi Na Uy tươi nhập khẩu trong ngày cuộn cùng bơ sáp Đắk Lắk dẻo bùi, phủ trứng cá chuồn đỏ cam nổ lách tách trong miệng.",
        "toppings": [
            {
                "name": "Gấp đôi trứng cá chuồn Tobiko",
                "price": 15000
            },
            {
                "name": "Sốt Spicy Mayo cay béo",
                "price": 8000
            },
            {
                "name": "Thêm gừng hồng & mù tạt Wasabi",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-102",
        "name": "Sashimi Cá Hồi Tươi Na Uy Thượng Hạng (5 lát dày)",
        "slug": "sashimi-ca-hoi-na-uy",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 119000,
        "oldPrice": 145000,
        "rating": 4.9,
        "reviews": 195,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1534482421-64566f976cfa?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Từng lát cá hồi tươi sống cắt lát dày béo ngậy vân mỡ cẩm thạch trắng tuyết, ngọt tan trên đầu lưỡi, chuẩn ăn tươi sashimi Nhật.",
        "toppings": [
            {
                "name": "Thêm 3 lát cá hồi",
                "price": 55000
            },
            {
                "name": "Lá tía tô & củ cải sợi",
                "price": 0
            },
            {
                "name": "Nước tương Kikkoman & Wasabi tươi",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-103",
        "name": "Sashimi Cá Ngừ Đại Dương Phú Yên Tươi Sống (5 lát)",
        "slug": "sashimi-ca-ngu-dai-duong",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 109000,
        "oldPrice": 135000,
        "rating": 4.8,
        "reviews": 112,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cá ngừ đại dương đỏ au màu ngọc bích, thịt săn chắc ngọt thơm giàu đạm omega-3, chấm mù tạt wasabi cay nồng bốc mũi.",
        "toppings": [
            {
                "name": "Thêm 3 lát cá ngừ",
                "price": 50000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-104",
        "name": "Combo Sushi Tổng Hợp Tokyo Thượng Hạng (12 miếng)",
        "slug": "combo-sushi-tokyo-12-mieng",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 169000,
        "oldPrice": 210000,
        "rating": 4.9,
        "reviews": 145,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Set sushi đầy đặn gồm: Nigiri cá hồi, Nigiri cá ngừ, Nigiri tôm sú luộc, Nigiri bạch tuộc, Gunkan trứng cá chuồn và 6 khoanh cơm cuộn bơ cá hồi.",
        "toppings": [
            {
                "name": "Chén súp Miso rong biển",
                "price": 15000
            },
            {
                "name": "Salad rong biển mè rang",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-105",
        "name": "Cơm Cuộn California Roll Phủ Trứng Tôm (8 khoanh)",
        "slug": "california-roll-trung-tom",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 79000,
        "oldPrice": 95000,
        "rating": 4.8,
        "reviews": 120,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1553621042-f6e147245754?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thanh cua Nhật, dưa leo giòn mát và bơ sáp cuộn ngược rắc ngập tràn trứng tôm giòn bùi, chấm sốt mayonnaise ngọt dịu.",
        "toppings": [
            {
                "name": "Gấp đôi trứng tôm",
                "price": 12000
            },
            {
                "name": "Thêm thanh cua",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-106",
        "name": "Maki Tôm Chiên Giòn Tempura Sốt Mù Tạt Ngọt (8 khoanh)",
        "slug": "maki-tom-chien-tempura",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 85000,
        "oldPrice": 105000,
        "rating": 4.8,
        "reviews": 94,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tôm sú tươi tẩm bột tempura Nhật Bản chiên xù vàng rụm giòn tan, cuộn cơm dẻo rưới sốt teriyaki và sốt mù tạt mật ong ngọt cay nhẹ.",
        "toppings": [
            {
                "name": "Vụn bột chiên xù giòn tan",
                "price": 5000
            },
            {
                "name": "Sốt Teriyaki ngọt",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-107",
        "name": "Nigiri Bò Wagyu Khè Lửa Sốt Teriyaki (4 miếng)",
        "slug": "nigiri-bo-wagyu-khe-lua",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 119000,
        "oldPrice": 149000,
        "rating": 4.9,
        "reviews": 88,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt bò Wagyu vân mỡ cẩm thạch khè lửa tái chín tới tỏa hương thơm bơ ngất ngây, rưới sốt teriyaki và rắc tỏi phi giòn tan.",
        "toppings": [
            {
                "name": "Thêm 2 miếng bò Wagyu",
                "price": 55000
            },
            {
                "name": "Rắc hành lá & mè rang",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-108",
        "name": "Nigiri Lươn Nhật Nướng Unagi Sốt Đậm Đà (4 miếng)",
        "slug": "nigiri-luon-nhat-unagi",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 99000,
        "oldPrice": 125000,
        "rating": 4.8,
        "reviews": 82,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Lươn Nhật rút xương nướng than hoa phết sốt Unagi Kabayaki óng ả màu hổ phách, thịt lươn béo mềm tan biến trong miệng.",
        "toppings": [
            {
                "name": "Thêm sốt lươn Unagi",
                "price": 8000
            },
            {
                "name": "Gừng đỏ ngâm chua ngọt",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-109",
        "name": "Gunkan Trứng Cá Hồi Đỏ Ikura Cao Cấp (2 miếng)",
        "slug": "gunkan-trung-ca-hoi-ikura",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 75,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thuyền rong biển ôm trọn những viên trứng cá hồi Na Uy to tròn óng ánh như hạt ngọc ruby, vỡ tung vị mặn ngọt tươi nguyên của biển cả.",
        "toppings": [
            {
                "name": "Gấp đôi trứng cá hồi Ikura",
                "price": 40000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-110",
        "name": "Cơm Cuộn Da Cá Hồi Chiên Giòn Sốt Spicy Mayo (8 khoanh)",
        "slug": "cuon-da-ca-hoi-chien-gion",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.7,
        "reviews": 68,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Da cá hồi tẩm muối tiêu chiên giòn tan rụm cuộn cùng rau xà lách tươi và sốt cay béo, trải nghiệm giòn rụm khó quên.",
        "toppings": [
            {
                "name": "Sốt cay béo thêm",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-111",
        "name": "Mì Udon Xào Hải Sản Nhật Bản Sốt Dầu Hào Thơm Nức",
        "slug": "mi-udon-xao-hai-san",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 85000,
        "oldPrice": 105000,
        "rating": 4.8,
        "reviews": 105,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/udon-noodles.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sợi mì Udon cọng tròn to trắng muốt dẻo dai xào lửa lớn cùng tôm sú, mực ống, bắp cải tím, nấm hương và rắc cá ngừ bào Katsuobushi.",
        "toppings": [
            {
                "name": "Rắc thêm cá ngừ bào nhảy múa",
                "price": 10000
            },
            {
                "name": "Thêm tôm mực",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-112",
        "name": "Mì Ramen Xương Heo Hầm Tonkotsu Thịt Chashu",
        "slug": "mi-ramen-tonkotsu-chashu",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 130,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Nước dùng hầm từ xương ống heo 14 tiếng sánh trắng sữa béo ngậy, sợi mì ramen tươi, lát thịt Chashu cuộn nướng mềm rục và trứng ngâm tương lòng đào.",
        "toppings": [
            {
                "name": "Thêm 2 lát thịt Chashu",
                "price": 25000
            },
            {
                "name": "Thêm trứng ngâm tương Ajitsuke",
                "price": 12000
            },
            {
                "name": "Măng khô Menma & Rong biển",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-113",
        "name": "Cơm Bò Xào Hành Tây Kiểu Nhật Bản (Gyudon)",
        "slug": "com-bo-gyudon-nhat-ban",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 79000,
        "oldPrice": 95000,
        "rating": 4.8,
        "reviews": 92,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/gyudon-beef-bowl.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt bò Mỹ thái mỏng xào cùng hành tây ngọt thanh trong nước tương dashi và rượu mirin, phủ lên bát cơm dẻo nóng hổi kèm trứng gà trần onsen.",
        "toppings": [
            {
                "name": "Trứng gà Onsen lòng đào",
                "price": 10000
            },
            {
                "name": "Thêm thịt bò Gyudon",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-114",
        "name": "Cơm Lươn Nhật Nướng Than Hoa Sốt Teriyaki (Unadon)",
        "slug": "com-luon-unadon-nhat",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 139000,
        "oldPrice": 175000,
        "rating": 4.9,
        "reviews": 85,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/unadon-eel-bowl.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Miếng lươn Nhật lớn nướng than hoa đượm khói, tẩm đẫm nước sốt lươn mặn ngọt óng ánh đặt trên bát cơm hạt ngọc Nhật dẻo bùi.",
        "toppings": [
            {
                "name": "Thêm sốt lươn Nhật",
                "price": 10000
            },
            {
                "name": "Súp Miso nóng",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-115",
        "name": "Salad Rong Biển Trứng Tôm Sốt Mè Rang Nhật Bản",
        "slug": "salad-rong-bien-trung-tom",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 49000,
        "oldPrice": 59000,
        "rating": 4.8,
        "reviews": 110,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Rong biển xanh tươi tẩm gia vị mè cay nhẹ, dưa leo xắt sợi, trứng tôm giòn sần sật trộn cùng nước sốt mè rang Kewpie thơm béo.",
        "toppings": [
            {
                "name": "Thêm trứng tôm",
                "price": 10000
            },
            {
                "name": "Thanh cua xé sợi",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-116",
        "name": "Đậu Nành Nhật Bản Edamame Luộc Muối Hạt",
        "slug": "dau-nanh-nhat-edamame",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 35000,
        "oldPrice": 42000,
        "rating": 4.7,
        "reviews": 65,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/edamame-beans.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Những quả đậu nành xanh mướt luộc vừa chín tới rắc muối biển hạt tinh khiết, bóp nhẹ vỏ thưởng thức hạt đậu bùi béo ngọt lành.",
        "toppings": [
            {
                "name": "Lắc bột ớt Shichimi cay",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-117",
        "name": "Bánh Xèo Nhật Bản Okonomiyaki Phủ Cá Ngừ Bào Nhảy Múa",
        "slug": "banh-xeo-okonomiyaki-nhat",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 59000,
        "oldPrice": 72000,
        "rating": 4.8,
        "reviews": 95,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/okonomiyaki.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh xèo bột mì bắp cải ngọt giòn nhân tôm thịt, rưới sốt Otafuku nâu bóng, sốt mayonnaise zic-zac và phủ đầy lớp cá ngừ bào bốc khói nhảy múa.",
        "toppings": [
            {
                "name": "Gấp đôi cá ngừ bào",
                "price": 10000
            },
            {
                "name": "Thêm sốt mayonnaise",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-118",
        "name": "Chén Canh Rong Biển Đậu Hũ Non Súp Miso Nóng Hổi",
        "slug": "sup-miso-rong-bien",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 20000,
        "oldPrice": 25000,
        "rating": 4.8,
        "reviews": 140,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tương đậu nành Miso trắng lên men tự nhiên nấu cùng rong biển wakame mướt mát và đậu hũ non mềm tan xắt hạt lựu.",
        "toppings": [
            {
                "name": "Thêm đậu hũ non",
                "price": 5000
            },
            {
                "name": "Thêm rong biển",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-119",
        "name": "Trà Xanh Gạo Lứt Rang Genmaicha Nhật Bản (Ấm / Ly)",
        "slug": "tra-gao-lut-genmaicha",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.8,
        "reviews": 88,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Lá trà xanh búp non sao cùng gạo lứt rang nở hoa thơm nức mũi, vị thanh tao hỗ trợ tiêu hóa sau bữa ăn sushi tuyệt hảo.",
        "toppings": [
            {
                "name": "Uống Nóng hoặc Lạnh",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-120",
        "name": "Kem Trà Xanh Matcha Nhật Bản Đậu Đỏ Dẻo (1 viên)",
        "slug": "kem-matcha-dau-do-nhat",
        "restaurantId": "res-6",
        "restaurantName": "Sushi Maki House",
        "category": "sushi",
        "price": 29000,
        "oldPrice": 38000,
        "rating": 4.9,
        "reviews": 120,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/matcha-ice-cream.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Viên kem matcha béo mịn đượm vị đắng thanh quý phái từ trà xanh Kyoto, điểm xuyết hạt đậu đỏ ngọt bùi dẻo thơm.",
        "toppings": [
            {
                "name": "Thêm 1 viên kem matcha",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-121",
        "name": "Burger Gà Giòn Sốt Bơ Tỏi Đặc Biệt (Crispy Chicken)",
        "slug": "burger-ga-gion-sot-bo",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 39000,
        "oldPrice": 49000,
        "rating": 4.7,
        "reviews": 83,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1525059696034-4967a8e1dca2?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Đùi gà phi lê tẩm bột chiên vàng rụm lớp vỏ xù, đẫm sốt bơ tỏi thơm ngậy đặc chế, rau xà lách tươi giòn kẹp bánh mì nướng nóng.",
        "toppings": [
            {
                "name": "Thêm phô mai lát",
                "price": 8000
            },
            {
                "name": "Thêm miếng gà giòn",
                "price": 20000
            },
            {
                "name": "Sốt cay Tabasco",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-122",
        "name": "Burger Gà Giòn Cay Sốt Tabasco Mayonnaise",
        "slug": "burger-ga-cay-tabasco",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 75,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt gà phi lê chiên giòn cay rực lửa, hòa quyện sốt béo mayo và ớt đỏ nồng nàn dành riêng cho người mê ăn cay.",
        "toppings": [
            {
                "name": "Thêm phô mai",
                "price": 8000
            },
            {
                "name": "Dưa chuột muối",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-123",
        "name": "Burger Gà Nướng Phô Mai Tan Chảy Kéo Sợi",
        "slug": "burger-ga-nuong-pho-mai",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 48000,
        "oldPrice": 59000,
        "rating": 4.8,
        "reviews": 90,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt gà nướng mật ong mềm mọng nước, kẹp 2 lát phô mai Mozzarella chảy tràn thơm béo hấp dẫn.",
        "toppings": [
            {
                "name": "Thêm phô mai lát Cheddar",
                "price": 10000
            },
            {
                "name": "Bacon xông khói",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-124",
        "name": "Burger Gà Không Xương Sốt Mật Ong Mù Tạt Vàng (Honey Mustard)",
        "slug": "burger-ga-honey-mustard",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.7,
        "reviews": 62,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1525059696034-4967a8e1dca2?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Miếng phi lê gà chiên vàng ươm tắm sốt mù tạt mật ong thơm nồng chua ngọt kiểu Mỹ cực bắt vị.",
        "toppings": [
            {
                "name": "Gấp đôi sốt mật ong mù tạt",
                "price": 6000
            },
            {
                "name": "Phô mai lát",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-125",
        "name": "Burger Gà 2 Tầng Siêu Khổng Lồ (Double Crispy Monster)",
        "slug": "burger-ga-2-tang-khong-lo",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 79000,
        "oldPrice": 99000,
        "rating": 4.9,
        "reviews": 105,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "2 miếng phi lê gà chiên xù khổng lồ kẹp cùng 2 lát phô mai và sốt bơ tỏi, no nê bùng nổ năng lượng.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la",
                "price": 8000
            },
            {
                "name": "Thịt ba chỉ xông khói",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-126",
        "name": "Burger Gà Teriyaki Kiểu Nhật Rong Biển Sấy",
        "slug": "burger-ga-teriyaki-rong-bien",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 49000,
        "oldPrice": 59000,
        "rating": 4.7,
        "reviews": 58,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt gà áp chảo sốt teriyaki mặn ngọt óng ánh, phủ lớp rong biển sợi giòn tan và hạt mè trắng bùi thơm.",
        "toppings": [
            {
                "name": "Thêm rong biển sấy",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-127",
        "name": "2 Miếng Gà Rán Giòn Cay Truyền Thống Vàng Óng",
        "slug": "2-mieng-ga-ran-gion-cay",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 65000,
        "oldPrice": 78000,
        "rating": 4.8,
        "reviews": 145,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/fried-chicken-pieces.jpg",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "2 miếng gà lớn (1 đùi + 1 má đùi) tẩm ướp 11 loại gia vị bí truyền, chiên áp suất vàng rộm giòn tan rụm từng thớ thịt mọng nước.",
        "toppings": [
            {
                "name": "Sốt chấm phô mai cay",
                "price": 8000
            },
            {
                "name": "Tương cà & tương ớt",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-128",
        "name": "3 Miếng Đùi Gà Rán Giòn Rụm Lớp Vỏ Vàng Ruộm",
        "slug": "3-mieng-dui-ga-ran",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 95000,
        "oldPrice": 115000,
        "rating": 4.9,
        "reviews": 110,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Set 3 chiếc đùi tỏi gà to mập chiên giòn nóng hổi, vỏ ngoài giòn rụm bên trong thịt mềm thơm ngọt tự nhiên.",
        "toppings": [
            {
                "name": "Khoai tây chiên ăn kèm",
                "price": 15000
            },
            {
                "name": "Sốt bơ tỏi thêm",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-129",
        "name": "Cánh Gà Lắc Phô Mai Béo Bùi Vỏ Giòn (Hộp 4 cánh)",
        "slug": "canh-ga-lac-pho-mai",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 68000,
        "oldPrice": 82000,
        "rating": 4.8,
        "reviews": 95,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/chicken-wings.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cánh gà chiên giòn tan lắc đều bột phô mai cam thơm bùi ngọt mặn kích thích vị giác cực kỳ.",
        "toppings": [
            {
                "name": "Gấp đôi bột phô mai",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-130",
        "name": "Cánh Gà Sốt Bơ Tỏi Cay Kiểu Hàn Quốc (4 cánh)",
        "slug": "canh-ga-sot-bo-toi-cay",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 72000,
        "oldPrice": 88000,
        "rating": 4.9,
        "reviews": 88,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cánh gà chiên giòn đảo trên chảo ngập sốt bơ tỏi ớt gochujang cay ngọt đậm đà rắc vừng rang thơm lừng.",
        "toppings": [
            {
                "name": "Thêm củ cải trắng muối chua",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-131",
        "name": "Gà Popcorn Viên Chiên Giòn Lắc Rong Biển (Hộp Lớn)",
        "slug": "ga-popcorn-lac-rong-bien",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 130,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/popcorn-chicken.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Từng viên ức gà không xương tẩm bột bắp chiên giòn rụm vừa miệng ăn, lắc cùng bột rong biển sấy thơm nức nở.",
        "toppings": [
            {
                "name": "Lắc thêm phô mai",
                "price": 6000
            },
            {
                "name": "Sốt mayonnaise cay",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-132",
        "name": "Gà Miếng Không Xương Chiên Giòn Nuggets (6 miếng)",
        "slug": "ga-mieng-nuggets-6-mieng",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 42000,
        "oldPrice": 50000,
        "rating": 4.7,
        "reviews": 85,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt gà xay nhuyễn tẩm gia vị truyền thống chiên giòn vàng đều, món ăn khoái khẩu của cả người lớn và trẻ nhỏ.",
        "toppings": [
            {
                "name": "Sốt chua ngọt BBQ",
                "price": 0
            },
            {
                "name": "Sốt mù tạt mật ong",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-133",
        "name": "Combo 1 Người: 1 Burger Gà Giòn + 1 Miếng Gà + 1 Nước",
        "slug": "combo-1-nguoi-burger-ga",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 85000,
        "oldPrice": 105000,
        "rating": 4.9,
        "reviews": 160,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/fried-chicken-pieces.jpg",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Bữa ăn đầy đủ no bụng gồm 1 Burger gà sốt bơ, 1 miếng gà rán giòn cay và 1 ly Pepsi tươi có ga mát lạnh.",
        "toppings": [
            {
                "name": "Thêm khoai tây chiên nhỏ",
                "price": 15000
            },
            {
                "name": "Phô mai lát vào burger",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-134",
        "name": "Combo Gia Đình Gà Rán (4 Miếng Gà + 2 Burger + Khoai + 2 Nước)",
        "slug": "combo-gia-dinh-ga-ran",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 199000,
        "oldPrice": 260000,
        "rating": 4.9,
        "reviews": 95,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Đại tiệc gà rán thịnh soạn cho cả nhà thưởng thức sum vầy cuối tuần với ngập tràn món ngon giòn rụm.",
        "toppings": [
            {
                "name": "Đổi khoai sang khoai lắc phô mai",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-135",
        "name": "Khoai Tây Chiên Lắc Phô Mai Cay Kiểu Pháp",
        "slug": "khoai-tay-lac-pho-mai-cay",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 35000,
        "oldPrice": 42000,
        "rating": 4.8,
        "reviews": 115,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai tây cọng chiên vàng giòn rụm lắc ngập bột phô mai pha ớt bột Hàn Quốc cay the the béo bùi.",
        "toppings": [
            {
                "name": "Gấp đôi bột phô mai",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-136",
        "name": "Bắp Cải Trộn Salad Coleslaw Thanh Mát Sốt Mayo",
        "slug": "salad-bap-cai-coleslaw",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.7,
        "reviews": 75,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/coleslaw-salad.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bắp cải trắng, bắp cải tím và cà rốt bào sợi giòn sần sật trộn sốt mayonnaise chua béo thanh mát giải ngấy món gà rán.",
        "toppings": [
            {
                "name": "Thêm sốt sốt mayonnaise",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-137",
        "name": "Khoai Tây Nghiền Sốt Nâu Gravy Béo Ngậy Đậm Đà",
        "slug": "khoai-tay-nghien-sot-gravy",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.8,
        "reviews": 80,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/mashed-potatoes.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai tây hấp chín nghiền mịn như kem cùng bơ lạt và sữa tươi, rưới nước sốt nâu gravy hầm từ thịt gà đậm đà thơm ấm.",
        "toppings": [
            {
                "name": "Thêm sốt Gravy nóng",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-138",
        "name": "Bánh Mì Cuộn Gà Giòn Sốt Caesar Phô Mai (Chicken Twister)",
        "slug": "banh-mi-cuon-ga-gion-twister",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 49000,
        "oldPrice": 59000,
        "rating": 4.8,
        "reviews": 90,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Vỏ bánh tortilla nướng mềm cuộn chặt miếng gà giòn rụm không xương, rau xà lách, cà chua bi và sốt Caesar phô mai thơm phức.",
        "toppings": [
            {
                "name": "Thêm phô mai lát",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-139",
        "name": "Trà Đào Sả Tắc Mát Lạnh Sảng Khoái (Ly Lớn)",
        "slug": "tra-dao-sa-tac-mat-lanh",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.8,
        "reviews": 140,
        "time": "10 - 15 phút",
        "image": "https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Trà đen thơm lừng hương sả đập dập, chua thanh của quất tắc và ngọt ngào của đào ngâm giòn sần sật xua tan cơn khát tức thì.",
        "toppings": [
            {
                "name": "Thêm 2 miếng đào ngâm",
                "price": 8000
            },
            {
                "name": "Thạch nha đam",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-140",
        "name": "Trà Chanh Giã Tay Quảng Đông Thơm Nồng Mùi Tinh Dầu",
        "slug": "tra-chanh-gia-tay-quang-dong",
        "restaurantId": "res-7",
        "restaurantName": "Burger Gà Giòn",
        "category": "burger",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.9,
        "reviews": 125,
        "time": "10 - 15 phút",
        "image": "/assets/images/foods/iced-lemon-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Chanh nước hoa Quảng Đông giã tay nát bung tinh dầu thơm ngát kết hợp cốt trà Ô long đậm đặc và đá tuyết mát lạnh sảng khoái.",
        "toppings": [
            {
                "name": "Thêm thạch dừa giòn",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-141",
        "name": "Bánh Croissant Bơ Pháp Nướng Giòn Thơm Bơ Elle & Vire",
        "slug": "croissant-bo-phap",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 72,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Chiếc bánh sừng bò chuẩn kỹ thuật Pháp ngàn lớp vỏ mỏng tang giòn rụm bên ngoài, ruột xốp tổ ong mềm mướt thơm ngát bơ tươi hảo hạng.",
        "toppings": [
            {
                "name": "Sốt socola Nutella chấm kèm",
                "price": 10000
            },
            {
                "name": "Mứt dâu tây Đà Lạt",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-142",
        "name": "Bánh Pain Au Chocolat Nhân Socola Bỉ Đắng Ngọt",
        "slug": "pain-au-chocolat-bi",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.9,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Vỏ bột ngàn lớp nướng nở phồng ôm trọn 2 thanh socola đen Bỉ 70% cacao tan chảy ấm áp quyến rũ ngất ngây.",
        "toppings": [
            {
                "name": "Rắc bột đường tuyết vani",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-143",
        "name": "Bánh Sừng Bò Nhân Trứng Muối Tan Chảy Hoàng Kim",
        "slug": "croissant-trung-muoi-tan-chay",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.9,
        "reviews": 90,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Dòng sốt kem trứng muối lava vàng óng tan chảy bùi ngậy tuôn trào khi bẻ đôi chiếc bánh sừng bò giòn tan nóng hổi.",
        "toppings": [
            {
                "name": "Thêm sốt trứng muối",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-144",
        "name": "Bánh Mousse Dâu Tây Tươi Kem Mascarpone Pháp",
        "slug": "mousse-dau-tay-kem-phap",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.8,
        "reviews": 54,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Lớp kem mousse mịn màng màu hồng pastel mát lạnh tan ngay trong miệng, vị chua ngọt thanh tao từ dâu tây tươi và phô mai Mascarpone.",
        "toppings": [
            {
                "name": "Thêm dâu tây tươi trang trí",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-145",
        "name": "Bánh Tiramisu Ý Truyền Thống Cacao Cà Phê Rượu Rum",
        "slug": "tiramisu-y-truyen-thong",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 52000,
        "oldPrice": 65000,
        "rating": 4.9,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/tiramisu.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh sâm-panh nhúng đẫm cà phê espresso Buôn Ma Thuột hòa chút rượu rum thơm nồng, xen kẽ kem phô mai béo ngậy phủ bột cacao đắng mịn.",
        "toppings": [
            {
                "name": "Thêm bột cacao nguyên chất",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-146",
        "name": "Bánh Phô Mai Nướng Cháy Basque Burnt Cheesecake",
        "slug": "basque-burnt-cheesecake",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 58000,
        "oldPrice": 70000,
        "rating": 4.9,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh phô mai nướng nhiệt độ cao tạo lớp vỏ ngoài cháy xém màu caramel nồng nàn, bên trong lõi kem phô mai sánh dẻo béo ngậy tan chảy.",
        "toppings": [
            {
                "name": "Rưới sốt việt quất chua ngọt",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-147",
        "name": "Bánh Su Kem Vani Vỏ Giòn Craquelin (Hộp 4 chiếc)",
        "slug": "su-kem-vani-vo-gion",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 36000,
        "oldPrice": 45000,
        "rating": 4.8,
        "reviews": 78,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/cream-puff.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Vỏ bánh nướng phủ lớp đường giòn rụm Craquelin nứt hoa văn đẹp mắt, bơm ngập nhân kem sữa tươi vani ngọt mát béo ngậy.",
        "toppings": [
            {
                "name": "Nhân kem socola",
                "price": 5000
            },
            {
                "name": "Nhân kem matcha",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-148",
        "name": "Bánh Tart Trứng Bồ Đào Nha Nướng Cháy Xém (2 chiếc)",
        "slug": "tart-trung-bo-dao-nha",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.9,
        "reviews": 95,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/egg-tart.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Vỏ tart xếp lớp giòn rụm ngàn cánh ôm trọn phần kem trứng sữa nướng phồng xém mặt thơm nức mùi caramen béo ngậy.",
        "toppings": [
            {
                "name": "Rắc bột quế thơm",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-149",
        "name": "Bánh Donut Phủ Socola Bỉ Rắc Hạt Hạnh Nhân Nướng",
        "slug": "donut-socola-hanh-nhan",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.7,
        "reviews": 60,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Chiếc bánh donut vòng tròn mềm xốp thơm bơ chiên vàng đều, nhúng lớp socola Bỉ bóng bẩy và rắc hạnh nhân lát nướng giòn.",
        "toppings": [
            {
                "name": "Rắc kẹo cốm 7 màu",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-150",
        "name": "Bánh Mì Bơ Tỏi Phô Mai Kiểu Hàn Quốc (Garlic Cheese Bread)",
        "slug": "banh-mi-bo-toi-han-quoc",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 105,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/garlic-bread.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh mì tròn bổ múi khế bơm ngập kem phô mai béo ngậy, nhúng đẫm sốt bơ tỏi thơm ngát nướng xém vàng giòn tan vỏ.",
        "toppings": [
            {
                "name": "Thêm phô mai kem bên trong",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-151",
        "name": "Bánh Red Velvet Phủ Lớp Kem Phô Mai Cream Cheese Mịn",
        "slug": "red-velvet-cream-cheese",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 52000,
        "oldPrice": 65000,
        "rating": 4.8,
        "reviews": 72,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cốt bánh nhung đỏ ẩm mượt mềm tan xen kẽ từng lớp kem phô mai chua béo đặc trưng quyến rũ sang trọng.",
        "toppings": [
            {
                "name": "Trang trí bột bánh đỏ mịn",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-152",
        "name": "Bánh Cupcake Socola Đậm Đà Rắc Hạt Dẻ Cười Pistachio",
        "slug": "cupcake-socola-pistachio",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.7,
        "reviews": 48,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/chocolate-cupcake.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Chiếc cupcake nhỏ xinh xắn vị socola đậm đà, trên chóp bắt bông kem bơ béo ngậy và hạt dẻ cười nghiền thô bùi bùi.",
        "toppings": [
            {
                "name": "Thêm sốt caramel muối",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-153",
        "name": "Bánh Quy Bơ Hạnh Nhân Pháp Hộp Thiếc (100g)",
        "slug": "banh-quy-bo-hanh-nhan",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 65000,
        "oldPrice": 80000,
        "rating": 4.9,
        "reviews": 52,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh quy bơ giòn tan thơm lừng mùi bơ Pháp cao cấp kết hợp lát hạnh nhân mỏng dính, nhâm nhi cùng tách trà chiều cực phẩm.",
        "toppings": [
            {
                "name": "Đóng gói túi quà tặng",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-154",
        "name": "Bánh Macaron Pháp 5 Vị Sắc Màu (Hộp 5 chiếc)",
        "slug": "macaron-phap-5-vi",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 75000,
        "oldPrice": 95000,
        "rating": 4.9,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/macarons.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Những chiếc macaron chân váy nở xòe xinh xắn đủ 5 vị: Dâu tây, Matcha, Socola đen, Chanh vàng và Vani Madagascar.",
        "toppings": [
            {
                "name": "Hộp nơ quà tặng sang trọng",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-155",
        "name": "Bánh Bông Lan Trứng Muối Chà Bông Sốt Phô Mai Kim Sa",
        "slug": "bong-lan-trung-muoi-cha-bong",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 49000,
        "oldPrice": 60000,
        "rating": 4.9,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/banh-bong-lan-trung-muoi.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cốt bánh bông lan mềm xốp ẩm mịn, ngập tràn chà bông thịt heo tơi xốp, trứng muối bùi bùi và sốt phô mai kim sa chảy thơm nức mũi.",
        "toppings": [
            {
                "name": "Thêm trứng muối dẻo",
                "price": 10000
            },
            {
                "name": "Thêm sốt phô mai béo",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-156",
        "name": "Bánh Danish Đào Tươi Kem Custard Nướng Phồng",
        "slug": "danish-dao-tuoi-custard",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 42000,
        "oldPrice": 52000,
        "rating": 4.8,
        "reviews": 45,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bột bánh đan lớp nở giòn rụm, ở giữa là lớp kem custard vani vàng ươm và nửa quả đào ngâm giòn ngọt thanh mọng.",
        "toppings": [
            {
                "name": "Hạnh nhân lát nướng rắc kèm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-157",
        "name": "Bánh Baguette Pháp Truyền Thống Nướng Lò Đá (1 ổ lớn)",
        "slug": "baguette-phap-truyen-thong",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 22000,
        "oldPrice": 28000,
        "rating": 4.7,
        "reviews": 60,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/baguette-bread.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Ổ bánh mì dài kiểu Pháp da giòn rụm nứt hoa văn chân chim, ruột xốp dai thơm nồng men chua tự nhiên.",
        "toppings": [
            {
                "name": "Hộp bơ lạt Pháp ăn kèm",
                "price": 8000
            },
            {
                "name": "Mứt dâu tây",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-158",
        "name": "Trà Hoa Cúc Mật Ong Hoa Cà Phê Ấm Áp",
        "slug": "tra-hoa-cuc-mat-ong-am",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.8,
        "reviews": 55,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Những đóa hoa cúc khô hãm nước sôi thơm hương thảo mộc dịu nhẹ, hòa mật ong rừng ngọt thanh giúp an thần thư thái khi ăn bánh ngọt.",
        "toppings": [
            {
                "name": "Thêm kỷ tử đỏ bổ dưỡng",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-159",
        "name": "Cà Phê Latte Kem Béo Hạt Dẻ Nướng Nóng Hổi",
        "slug": "latte-hat-de-nuong",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 42000,
        "oldPrice": 52000,
        "rating": 4.8,
        "reviews": 80,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Espresso thơm nồng đánh cùng sữa tươi nóng tạo bọt nghệ thuật đẹp mắt, hòa quyện si-rô hạt dẻ nướng thơm ấm ngọt ngào.",
        "toppings": [
            {
                "name": "Vẽ Latte Art trái tim",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-160",
        "name": "Trà Bá Tước Earl Grey Hương Cam Bergamot Sang Trọng",
        "slug": "tra-earl-grey-nong",
        "restaurantId": "res-8",
        "restaurantName": "Tiệm Bánh Bonjour",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.8,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tách trà quý tộc Anh Quốc tỏa hương thơm cam Bergamot tươi mát, hậu vị chát ngọt êm đềm kết hợp hoàn hảo cùng bánh croissant.",
        "toppings": [
            {
                "name": "Thêm lát chanh vàng",
                "price": 2000
            },
            {
                "name": "Kèm viên đường phèn",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-161",
        "name": "Bánh Pancake Mật Ong Rừng & Dâu Tây Tươi (Set 3 chiếc)",
        "slug": "pancake-mat-ong-dau-tay",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 39000,
        "oldPrice": 49000,
        "rating": 4.8,
        "reviews": 68,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Bánh kếp nướng vàng xốp mềm mịn thơm ngậy mùi bơ sữa tươi, rưới mật ong rừng hoa cà phê Đắk Lắk và điểm xuyết dâu tây tươi mọng.",
        "toppings": [
            {
                "name": "Viên kem vani lạnh",
                "price": 12000
            },
            {
                "name": "Thêm lát bơ tươi",
                "price": 6000
            },
            {
                "name": "Rưới sốt socola",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-162",
        "name": "Bánh Mì Chảo Thập Cẩm Đặc Biệt (Pate, Xíu Mại, Bò, Trứng)",
        "slug": "banh-mi-chao-thap-cam",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.9,
        "reviews": 155,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/banh-mi-chao.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Chảo gang nóng sôi xèo xèo gồm pate gan béo ngậy, thịt bò mềm ngọt xào tỏi, xíu mại sốt cà, trứng ốp la lòng đào và xúc xích nướng.",
        "toppings": [
            {
                "name": "Thêm ổ bánh mì giòn nóng",
                "price": 5000
            },
            {
                "name": "Thêm miếng phô mai con bò cười",
                "price": 8000
            },
            {
                "name": "Thêm pate gan béo",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-163",
        "name": "Bánh Mì Xíu Mại Trứng Muối Sốt Cà Cay Nóng Hổi",
        "slug": "banh-mi-xiu-mai-cay-nong",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/banh-mi-chao.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Tô xíu mại nước ngọt thơm gồm 2 viên xíu mại nhân trứng muối mềm mịn, da heo giòn sần sật chan sốt ớt cay ấm chấm bánh mì giòn tan.",
        "toppings": [
            {
                "name": "Thêm viên xíu mại",
                "price": 12000
            },
            {
                "name": "Thêm da heo giòn",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-164",
        "name": "Bánh Mì Kẹp Thịt Nguội Bơ Pháp & Dưa Leo Đồ Chua",
        "slug": "banh-mi-thit-nguoi-bo-phap",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh mì vỏ giòn tan quét pate gan thơm nức, bơ thơm béo, kẹp thịt nguội jambon, chả lụa quết tiêu, dưa leo và ngò rí đậm đà.",
        "toppings": [
            {
                "name": "Thêm chả lụa & jambon",
                "price": 10000
            },
            {
                "name": "Ớt hiểm cay xắt lát",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-165",
        "name": "Bánh Mì Heo Quay Giòn Bì Rưới Nước Sốt Đậm Đà",
        "slug": "banh-mi-heo-quay-gion-bi",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 130,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/heo-quay.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt heo quay da nổ giòn tan rụm từng tiếng, nạc mỡ đan xen mềm ngọt chan nước sốt heo quay bí truyền đậm đà thơm ấm.",
        "toppings": [
            {
                "name": "Thêm thịt heo quay giòn bì",
                "price": 15000
            },
            {
                "name": "Đồ chua củ cải",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-166",
        "name": "Sandwich Bơ Quả & Trứng Gà Lòng Đào Áp Chảo (Avocado Toast)",
        "slug": "sandwich-bo-trung-long-dao",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 76,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh mì gối ngũ cốc nướng giòn phết bơ sáp Đắk Lắk nghiền béo bùi, đặt quả trứng gà ốp la lòng đào rắc tiêu đen và ớt bột paprika.",
        "toppings": [
            {
                "name": "Thêm thịt xông khói bacon",
                "price": 15000
            },
            {
                "name": "Phô mai Parmesan bào",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-167",
        "name": "Xôi Xéo Gà Xé Nấm Hương Rưới Mỡ Hành Thơm Lừng",
        "slug": "xoi-xeo-ga-xe-nam-huong",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.9,
        "reviews": 105,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/xoi-xeo.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Hạt nếp nương đồ vàng óng dẻo quánh, đỗ xanh thái mỏng bùi béo, gà ta xé phay xào nấm hương thơm lừng và rưới mỡ hành hành phi giòn tan.",
        "toppings": [
            {
                "name": "Thêm đùi gà chặt",
                "price": 20000
            },
            {
                "name": "Thêm hành phi giòn",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-168",
        "name": "Xôi Mặn Thập Cẩm Chà Bông Lạp Xưởng Trứng Cút",
        "slug": "xoi-man-thap-cam",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 42000,
        "rating": 4.8,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/xoi-thap-cam.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Hộp xôi nóng hổi ngập tràn topping: lạp xưởng nướng thái lát, chà bông heo tơi xốp, trứng cút chiên giòn, mỡ hành và sốt tương ớt đậm đà.",
        "toppings": [
            {
                "name": "Thêm lạp xưởng nướng",
                "price": 10000
            },
            {
                "name": "Thêm 2 trứng cút",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-169",
        "name": "Bún Bò Huế Chả Cua Giò Heo Cay Nồng Thơm Mắm Ruốc",
        "slug": "bun-bo-hue-cha-cua-gio-heo",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.9,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/bun-bo-hue.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Nước dùng bún bò hầm xương ống dậy mùi mắm ruốc Huế và sả phi ớt bột cay nồng, khoanh giò heo ninh mềm dẻo và chả cua biển béo bùi.",
        "toppings": [
            {
                "name": "Thêm khoanh giò heo",
                "price": 18000
            },
            {
                "name": "Thêm đĩa chả cua",
                "price": 12000
            },
            {
                "name": "Rau chuối bắp giá đỗ",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-170",
        "name": "Hủ Tiếu Nam Vang Tôm Mực Thịt Nạc Thơm Mỡ Tỏi Phi",
        "slug": "hu-tieu-nam-vang-tom-muc",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 55000,
        "oldPrice": 65000,
        "rating": 4.8,
        "reviews": 115,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/hu-tieu.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sợi hủ tiếu dai mềm trụng nóng, tôm sú bóc vỏ ngọt lịm, mực tươi giòn sần sật, thịt nạc xắt mỏng, trứng cút và hành phi thơm lừng bát canh xương.",
        "toppings": [
            {
                "name": "Chọn ăn Khô hoặc Nước",
                "price": 0
            },
            {
                "name": "Thêm tôm mực",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-171",
        "name": "Bánh Canh Cua Đồng Chả Cá Thác Lác Dai Ngon",
        "slug": "banh-canh-cua-dong-cha-ca",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 48000,
        "oldPrice": 58000,
        "rating": 4.7,
        "reviews": 82,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/bun-rieu.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Sợi bánh canh bột gạo dẻo dai nấu trong nước dùng riêu cua đồng thơm ngọt tự nhiên, chả cá thác lác Đắk Lắk quết dai giòn rụm.",
        "toppings": [
            {
                "name": "Thêm miếng chả cá",
                "price": 12000
            },
            {
                "name": "Quẩy giòn ăn kèm",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-172",
        "name": "Cháo Sườn Non Hạt Sen Nấu Nấm Rơm Bổ Dưỡng",
        "slug": "chao-suon-non-hat-sen",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 90,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/chao-suon.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Gạo tẻ ninh nhừ sánh mịn như lụa cùng sườn non sụn mềm rục, hạt sen bùi béo và nấm rơm ngọt nước, rắc hành hoa tiêu đen ấm bụng.",
        "toppings": [
            {
                "name": "Quẩy giòn rụm",
                "price": 10000
            },
            {
                "name": "Trứng gà lòng đào",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-173",
        "name": "Cháo Gà Ta Xé Phay Tiêu Sọ Cháo Nhuyễn Mịn",
        "slug": "chao-ga-ta-xe-phay",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.8,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/chao-suon.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cháo nấu từ nước luộc gà ngọt lịm thơm béo, gà ta xé phay rắc lá tía tô, hành hoa và hạt tiêu sọ Đắk Lắk cay ấm giải cảm tuyệt vời.",
        "toppings": [
            {
                "name": "Thêm đĩa gà xé phay",
                "price": 20000
            },
            {
                "name": "Quẩy giòn",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-174",
        "name": "Bánh Cuốn Nóng Tráng Tay Nhân Thịt Mộc Nhĩ Chả Quế",
        "slug": "banh-cuon-nong-trang-tay",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.9,
        "reviews": 120,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/banh-cuon.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Từng lớp bánh mỏng tang tráng tay trên nồi hơi nước nóng hổi, cuộn thịt nạc bằm mộc nhĩ nấm hương giòn sần sật, rắc hành phi giòn và chả quế.",
        "toppings": [
            {
                "name": "Thêm 2 lát chả quế",
                "price": 10000
            },
            {
                "name": "Thêm chả lụa",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-175",
        "name": "Nui Nấu Sườn Heo & Rau Củ Tươi Ngọt Nước Súp",
        "slug": "nui-nau-suon-heo-rau-cu",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.7,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/macaroni-soup.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nui vàng luộc mềm nấu cùng sườn heo ninh nhừ, cà rốt, củ cải trắng và bắp ngọt, nước súp trong veo ngọt thanh tự nhiên dễ ăn buổi sáng.",
        "toppings": [
            {
                "name": "Thêm sườn heo",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-176",
        "name": "Sữa Bắp Nếp Tươi Nấu Trong Ngày Thơm Béo Mịn",
        "slug": "sua-bap-nep-tuoi",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 18000,
        "oldPrice": 22000,
        "rating": 4.8,
        "reviews": 95,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/soy-milk.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bắp nếp ngọt thu hoạch tại vườn xay nấu tươi cùng sữa tươi thanh trùng, sánh đặc vàng óng thơm nức nở vị ngô nếp.",
        "toppings": [
            {
                "name": "Uống Nóng hoặc Lạnh",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-177",
        "name": "Sữa Hạt Sen Đậu Đỏ Dinh Dưỡng Bổ Huyết",
        "slug": "sua-hat-sen-dau-do",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 22000,
        "oldPrice": 28000,
        "rating": 4.8,
        "reviews": 70,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/soy-milk.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Hạt sen tươi xay cùng đậu đỏ ninh mềm và đường phèn thanh ngọt, thức uống ấm áp giàu dinh dưỡng cho bữa sáng tràn đầy năng lượng.",
        "toppings": [
            {
                "name": "Chọn uống Nóng hoặc Đá",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-178",
        "name": "Cà Phê Phin Sữa Đá Đậm Chất Buôn Ma Thuột",
        "slug": "ca-phe-phin-sua-da-bmt",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 25000,
        "oldPrice": 30000,
        "rating": 4.9,
        "reviews": 160,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/ca-phe-sua-da.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Hạt cà phê Robusta chín đỏ hái từ nông trường Buôn Ma Thuột rang mộc xay mịn nhỏ giọt qua phin nhôm truyền thống, hòa sữa đặc béo ngọt sánh đậm.",
        "toppings": [
            {
                "name": "Cà phê Đen hoặc Cà phê Sữa",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-179",
        "name": "Trà Gừng Mật Ong Ấm Bụng Khởi Đầu Ngày Mới",
        "slug": "tra-gung-mat-ong-am-bung",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 20000,
        "oldPrice": 25000,
        "rating": 4.8,
        "reviews": 60,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Gừng sẻ tươi giã dập nấu sôi hòa mật ong hoa cà phê nguyên chất, thức uống thanh lọc cơ thể ấm nồng đánh thức giác quan.",
        "toppings": [
            {
                "name": "Thêm lát chanh tươi",
                "price": 2000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-180",
        "name": "Nước Cam Tươi Vắt Ép Mật Ong Nguyên Chất (Ly Lớn)",
        "slug": "nuoc-cam-tuoi-vat-mat-ong",
        "restaurantId": "res-9",
        "restaurantName": "Bếp Sáng Mộc Miên",
        "category": "an-vat",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.9,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/orange-juice.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cam sành miền Tây mọng nước vắt tươi 100% không pha nước lọc, ngọt thanh nhờ mật ong hoa rừng bổ sung vitamin C dồi dào.",
        "toppings": [
            {
                "name": "Ít đường hoặc Không đường",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-181",
        "name": "Gà Rán Giòn Sốt Cay Hàn Quốc (Hot Spicy Crispy Chicken)",
        "slug": "ga-ran-gion-sot-cay",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 75000,
        "oldPrice": 92000,
        "rating": 4.8,
        "reviews": 98,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Gà tươi rán giòn rụm lớp vỏ ngoài, phủ đẫm sốt cay nồng đặc chế kiểu Hàn Quốc, thịt mềm ngọt mọng nước bên trong.",
        "toppings": [
            {
                "name": "Sốt cay Hàn Quốc thêm",
                "price": 8000
            },
            {
                "name": "Phô mai tuyết rắc",
                "price": 10000
            },
            {
                "name": "Khoai tây chiên ăn kèm",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-182",
        "name": "Gà Rán Sốt Đậu Tỏi Nồng Đượm (Soy Garlic Chicken)",
        "slug": "ga-ran-sot-dau-toi",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 78000,
        "oldPrice": 95000,
        "rating": 4.9,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Miếng gà chiên giòn áo lớp sốt đậu tương lên men và tỏi phi thơm nồng, vị ngọt mặn hài hòa cực bắt vị.",
        "toppings": [
            {
                "name": "Thêm sốt đậu tỏi",
                "price": 8000
            },
            {
                "name": "Củ cải trắng muối chua",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-183",
        "name": "Gà Rán Phủ Phô Mai Tuyết Trắng Béo Ngậy (Snow Cheese Chicken)",
        "slug": "ga-ran-pho-mai-tuyet",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 82000,
        "oldPrice": 99000,
        "rating": 4.8,
        "reviews": 90,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1562967914-608f82629710?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Lớp bột phô mai tuyết ngọt bùi thơm lừng phủ kín miếng gà rán vàng rộm vừa vớt từ chảo dầu nóng hổi.",
        "toppings": [
            {
                "name": "Gấp đôi bột phô mai tuyết",
                "price": 10000
            },
            {
                "name": "Sốt chấm cay ngọt",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-184",
        "name": "Burger Bò Nướng Sốt Tiêu Đen Phú Quốc Phô Mai",
        "slug": "burger-bo-sot-tieu-den",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 72000,
        "oldPrice": 88000,
        "rating": 4.8,
        "reviews": 74,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bò nướng than hoa quyện sốt tiêu đen cay ấm nồng nàn, phô mai Cheddar béo ngậy và hành tây xào caramen.",
        "toppings": [
            {
                "name": "Thêm phô mai Cheddar",
                "price": 10000
            },
            {
                "name": "Trứng ốp la",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-185",
        "name": "Burger Bò Nướng BBQ Thịt Ba Chỉ Xông Khói (Bacon King)",
        "slug": "burger-bo-bacon-king",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 105,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sự kết hợp giữa 2 dải bacon giòn rụm, miếng bò nướng lửa thơm khói và lớp phô mai Mỹ chảy ngập tràn.",
        "toppings": [
            {
                "name": "Thêm 2 dải Bacon giòn",
                "price": 18000
            },
            {
                "name": "Thêm sốt BBQ",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-186",
        "name": "Burger Phi Lê Gà Giòn Sốt Cay Phô Mai Chảy",
        "slug": "burger-phi-le-ga-cay-pho-mai",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 65000,
        "oldPrice": 79000,
        "rating": 4.7,
        "reviews": 68,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Phi lê gà tẩm bột chiên xù giòn tan rụm, rưới sốt ớt cay đỏ au và lát phô mai Mozzarella chảy dẻo.",
        "toppings": [
            {
                "name": "Thêm phô mai",
                "price": 8000
            },
            {
                "name": "Rau xà lách thêm",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-187",
        "name": "Cơm Gà Rán Giòn Sốt Cà Ri Nhật Bản Kèm Canh Rong Biển",
        "slug": "com-ga-ran-ca-ri-nhat",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 68000,
        "oldPrice": 82000,
        "rating": 4.8,
        "reviews": 82,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Miếng gà rán giòn rụm thái lát đặt trên dĩa cơm trắng nóng dẻo, rưới nước sốt cà ri Nhật củ cải cà rốt đậm đà thơm béo.",
        "toppings": [
            {
                "name": "Thêm trứng ốp la lòng đào",
                "price": 7000
            },
            {
                "name": "Thêm sốt cà ri",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-188",
        "name": "Cơm Gà Xào Nấm Sốt Teriyaki Đậm Đà Kiểu Nhật",
        "slug": "com-ga-xao-nam-teriyaki",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 62000,
        "oldPrice": 75000,
        "rating": 4.7,
        "reviews": 58,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/teriyaki-chicken-rice.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt gà đùi xào săn cùng nấm hương tươi và sốt teriyaki bóng bẩy, rắc mè trắng rang bùi thơm đưa cơm.",
        "toppings": [
            {
                "name": "Trứng ốp la",
                "price": 7000
            },
            {
                "name": "Thêm cơm trắng",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-189",
        "name": "Đùi Gà Rán Vàng Giòn Rụm Lớp Vỏ Xù (1 chiếc đùi lớn)",
        "slug": "dui-ga-ran-gion-rum-lon",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 36000,
        "oldPrice": 45000,
        "rating": 4.8,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Chiếc đùi gà tỏi cỡ bự chiên xù giòn tan rụm, thịt bên trong trắng mềm mọng nước không khô xác.",
        "toppings": [
            {
                "name": "Sốt chấm phô mai cay",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-190",
        "name": "Cánh Gà Chiên Bơ Tỏi Thơm Nức Mũi (Hộp 3 cánh)",
        "slug": "canh-ga-chien-bo-toi-3-canh",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 59000,
        "oldPrice": 72000,
        "rating": 4.8,
        "reviews": 76,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/chicken-wings.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cánh gà chiên giòn xóc bơ tỏi tươi phi vàng, thơm nức mũi chấm sốt tương ớt ngọt cực bắt mồi.",
        "toppings": [
            {
                "name": "Thêm sốt bơ tỏi",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-191",
        "name": "Khoai Lang Kén Lắc Phô Mai Béo Bùi Giòn Vỏ",
        "slug": "khoai-lang-ken-lac-pho-mai",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.7,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai lang kén vàng ươm thơm béo vị nước cốt dừa nướng giòn rụm bên ngoài dẻo ngọt bên trong, lắc bột phô mai bùi thơm.",
        "toppings": [
            {
                "name": "Gấp đôi bột phô mai",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-192",
        "name": "Khoai Tây Lắc Phô Mai & Bột Rong Biển (Hộp Lớn)",
        "slug": "khoai-tay-lac-rong-bien-pho-mai",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1585109649139-366815a0d713?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Khoai tây chiên giòn xốc trong túi cùng bột phô mai cam béo ngậy và rong biển nướng thơm phức.",
        "toppings": [
            {
                "name": "Gấp đôi phô mai rong biển",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-193",
        "name": "Phô Mai Viên Chiên Giòn Nhân Mozzarella Kéo Sợi (5 viên)",
        "slug": "pho-mai-vien-chien-gion",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/cheese-balls.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Viên phô mai tròn vo chiên phồng vàng rụm, cắn vào dòng phô mai dẻo quánh kéo sợi dài vô tận béo ngậy ngất ngây.",
        "toppings": [
            {
                "name": "Sốt chấm tương ớt phô mai",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-194",
        "name": "Bánh Tart Phô Mai Nướng Xém Vàng (Cheese Tart)",
        "slug": "banh-tart-pho-mai-nuong",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 30000,
        "oldPrice": 38000,
        "rating": 4.7,
        "reviews": 50,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/egg-tart.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Vỏ tart bơ giòn rụm ôm nhân kem phô mai chua béo nướng vàng xém, món tráng miệng ngọt dịu sau khi ăn gà rán.",
        "toppings": [
            {
                "name": "Rắc bột quế thơm",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-195",
        "name": "Mì Ý Gà Rán Giòn Sốt Cà Chua Phô Mai Đút Lò",
        "slug": "mi-y-ga-ran-sot-ca-chua",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.8,
        "reviews": 80,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/spaghetti-bolognese.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sợi mì Ý sốt cà chua đút lò phủ phô mai, ăn kèm miếng gà rán giòn rụm nóng hổi cắt lát.",
        "toppings": [
            {
                "name": "Thêm phô mai đút lò",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-196",
        "name": "Combo Gà Rán 2 Miếng + Khoai Tây Chiên + Coca-Cola",
        "slug": "combo-ga-ran-2-mieng-khoai-coca",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 140,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Combo no nê chuẩn chỉnh: 2 miếng gà rán giòn cay, 1 gói khoai tây chiên vàng và 1 ly Coca-Cola mát lạnh sảng khoái.",
        "toppings": [
            {
                "name": "Đổi sang gà sốt cay Hàn Quốc",
                "price": 10000
            },
            {
                "name": "Khoai lắc phô mai",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-197",
        "name": "Combo Burger Bò Tiêu Đen + 1 Miếng Gà + 1 Ly Nước",
        "slug": "combo-burger-bo-tieu-1-ga-nuoc",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 105000,
        "oldPrice": 135000,
        "rating": 4.8,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Set ăn thịnh soạn kết hợp cả burger bò nướng sốt tiêu và gà rán giòn rụm kèm nước giải khát.",
        "toppings": [
            {
                "name": "Thêm phô mai vào burger",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-198",
        "name": "Salad Gà Rán Sốt Mè Rang Nhật Bản Thanh Mát",
        "slug": "salad-ga-ran-sot-me-rang",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 49000,
        "oldPrice": 60000,
        "rating": 4.7,
        "reviews": 62,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Xà lách tươi xanh, dưa leo, cà chua bi giòn ngọt trộn cùng những lát gà rán cắt hạt lựu và sốt mè rang béo bùi.",
        "toppings": [
            {
                "name": "Thêm gà rán",
                "price": 15000
            },
            {
                "name": "Thêm sốt mè rang",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-199",
        "name": "Trà Chanh Dây Hạt Chia Mát Lạnh Giải Nhiệt",
        "slug": "tra-chanh-day-hat-chia",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.8,
        "reviews": 105,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/mango-fruit-tea.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nước cốt chanh dây tươi chua thanh thơm ngát hòa quyện hạt chia nở giòn, thức uống giải ngấy số 1 khi ăn đồ chiên rán.",
        "toppings": [
            {
                "name": "Thêm thạch nha đam",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-200",
        "name": "Kem Tươi Vani Ốc Quế Giòn Tan (Vanilla Soft Cone)",
        "slug": "kem-tuoi-vani-oc-que",
        "restaurantId": "res-10",
        "restaurantName": "Burger King Station - Chi Nhánh Nguyễn Tất Thành",
        "category": "burger",
        "price": 15000,
        "oldPrice": 20000,
        "rating": 4.8,
        "reviews": 130,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/ice-cream-cone.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Cây kem tươi vani xoắn ốc mềm mịn ngọt dịu mát lạnh trên chiếc bánh ốc quế nướng giòn rụm thơm bơ.",
        "toppings": [
            {
                "name": "Rưới sốt socola",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-201",
        "name": "Salad Ức Gà Áp Chảo Rau Củ Sốt Mè Rang Nhật Bản",
        "slug": "salad-uc-ga-ap-chao",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 52000,
        "oldPrice": 62000,
        "rating": 4.8,
        "reviews": 64,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Ức gà phi lê áp chảo tiêu đen không dầu mềm mọng, xà lách Romaine, cà chua bi, dưa leo, ngô ngọt và sốt mè rang béo bùi chuẩn eat-clean.",
        "toppings": [
            {
                "name": "Gấp đôi ức gà nướng áp chảo",
                "price": 18000
            },
            {
                "name": "Thêm trứng gà luộc lòng đào",
                "price": 8000
            },
            {
                "name": "Hạt điều rang muối Đắk Lắk",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-202",
        "name": "Salad Bơ Sáp Đắk Lắk Trứng Gà Luộc & Hạt Điều Rang",
        "slug": "salad-bo-sap-trung-luoc",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.9,
        "reviews": 88,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Bơ sáp Buôn Ma Thuột béo ngậy cắt lát, quả trứng gà ta luộc lòng đào, hạt điều rang thơm giòn và sốt sữa chua chanh mật ong thanh nhẹ.",
        "toppings": [
            {
                "name": "Thêm quả trứng luộc",
                "price": 8000
            },
            {
                "name": "Thêm hạt chia hữu cơ",
                "price": 5000
            },
            {
                "name": "Sốt mè rang thêm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-203",
        "name": "Salad Cá Hồi Na Uy Áp Chảo Sốt Chanh Dây Olive",
        "slug": "salad-ca-hoi-chanh-day",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 85000,
        "oldPrice": 105000,
        "rating": 4.9,
        "reviews": 75,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/salmon-teriyaki.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Khúc lườn cá hồi Na Uy áp chảo xém vàng thơm béo, giàu Omega-3, hòa cùng xà lách tím, cà chua bi và sốt chanh dây dầu olive nguyên chất.",
        "toppings": [
            {
                "name": "Thêm cá hồi áp chảo",
                "price": 35000
            },
            {
                "name": "Phô mai Parmesan bào",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-204",
        "name": "Salad Bò Úc Nướng Thảo Mộc Sốt Dầu Giấm Balsamic Ý",
        "slug": "salad-bo-uc-balsamic",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 68000,
        "oldPrice": 82000,
        "rating": 4.8,
        "reviews": 56,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt thăn bò Úc thái lát mỏng ướp hương thảo áp chảo chín tới mọng nước, trộn rau rocket, cà chua bi và giấm đen Balsamic đậm vị Ý.",
        "toppings": [
            {
                "name": "Thêm thịt thăn bò nướng",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-205",
        "name": "Salad Tôm Sú Nướng Bơ Tỏi & Rau Rocket Cà Chua Bi",
        "slug": "salad-tom-su-nuong-rocket",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 65000,
        "oldPrice": 79000,
        "rating": 4.8,
        "reviews": 62,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1559847844-5315695dadae?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Tôm sú tươi bóc nõn nướng bơ tỏi thơm ngào ngạt, rau mầm cải, xà lách giòn và sốt cam tươi thanh mát tràn đầy sức sống.",
        "toppings": [
            {
                "name": "Thêm tôm sú nướng (3 con)",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-206",
        "name": "Cơm Gạo Lứt Ức Gà Nướng Mật Ong Rừng & Bông Cải Xanh",
        "slug": "com-gao-lut-uc-ga-nuong",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 58000,
        "oldPrice": 70000,
        "rating": 4.9,
        "reviews": 115,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/healthy-chicken-bowl.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cơm gạo lứt dẻo thơm nấu chín tới giàu chất xơ, ức gà nướng mật ong ít calo và đĩa bông cải xanh cà rốt luộc ngọt thanh.",
        "toppings": [
            {
                "name": "Trứng ốp la không dầu",
                "price": 8000
            },
            {
                "name": "Thêm cơm gạo lứt",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-207",
        "name": "Cơm Gạo Lứt Cá Hồi Na Uy Áp Chảo Măng Tây Nướng",
        "slug": "com-gao-lut-ca-hoi-mang-tay",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 80,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/salmon-teriyaki.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bữa ăn giàu đạm lành mạnh với cá hồi phi lê áp chảo bơ lạt, măng tây non nướng giòn ngọt và cơm gạo lứt hạt sen bùi béo.",
        "toppings": [
            {
                "name": "Thêm măng tây nướng",
                "price": 15000
            },
            {
                "name": "Súp rau củ thanh đạm",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-208",
        "name": "Cơm Gạo Lứt Thăn Bò Áp Chảo Rau Củ Xào Dầu Hào Nhạt",
        "slug": "com-gao-lut-than-bo",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 68000,
        "oldPrice": 82000,
        "rating": 4.8,
        "reviews": 74,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/gyudon-beef-bowl.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thăn bò mềm xào ớt chuông, hành tây và nấm đông cô với lượng muối thấp theo chế độ giảm mỡ tăng cơ lành mạnh.",
        "toppings": [
            {
                "name": "Thêm thịt thăn bò",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-209",
        "name": "Cơm Tấm Gạo Lứt Sườn Cốt Lết Ít Muối Ăn Kèm Canh Rau",
        "slug": "com-tam-gao-lut-suon-nuong",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 58000,
        "oldPrice": 70000,
        "rating": 4.8,
        "reviews": 85,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/suon-nuong-dia.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Biến tấu cơm tấm truyền thống trên nền gạo lứt tím than, sườn cốt lết nướng than không phẩm màu và nhiều rau dưa tươi mát.",
        "toppings": [
            {
                "name": "Chả trứng hấp nạc",
                "price": 10000
            },
            {
                "name": "Trứng gà lòng đào",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-210",
        "name": "Poke Bowl Cá Hồi Tươi Rau Mầm & Hạt Quinoa Dinh Dưỡng",
        "slug": "poke-bowl-ca-hoi-quinoa",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 92,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Tô Poke chuẩn Hawaii đầy màu sắc: cá hồi sống cắt hạt lựu, quả bơ, ngô ngọt, đậu nành edamame, hạt diêm mạch Quinoa và sốt ponzu thanh mát.",
        "toppings": [
            {
                "name": "Thêm cá hồi tươi",
                "price": 30000
            },
            {
                "name": "Thêm rong biển sợi",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-211",
        "name": "Poke Bowl Đậu Hũ Non Áp Chảo & Bơ Sáp Sốt Teriyaki (Chay)",
        "slug": "poke-bowl-dau-hu-chay",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.8,
        "reviews": 50,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Món chay healthy thanh lọc cơ thể với đậu hũ non nướng vàng thơm, bơ sáp, củ cải đường, dưa leo và gạo lứt dẻo thơm.",
        "toppings": [
            {
                "name": "Thêm đậu nành Edamame",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-212",
        "name": "Bánh Mì Cuộn Wrap Ức Gà Rau Cải Kale & Sốt Sữa Chua",
        "slug": "wrap-uc-ga-cai-kale",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 49000,
        "oldPrice": 59000,
        "rating": 4.8,
        "reviews": 68,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh cuộn Tortilla nguyên cám gói ghém ức gà nướng áp chảo, lá cải xoăn kale giòn mát và sốt sữa chua Hy Lạp nhẹ bụng.",
        "toppings": [
            {
                "name": "Thêm phô mai ít béo",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-213",
        "name": "Bánh Mì Cuộn Wrap Bò Úc Rau Củ & Phô Mai Ít Béo",
        "slug": "wrap-bo-uc-rau-cu",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 55000,
        "oldPrice": 65000,
        "rating": 4.7,
        "reviews": 54,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt bò Úc xắt sợi áp chảo thơm nức cuộn cùng xà lách xoăn, cà chua bi và phô mai ít béo tốt cho tim mạch.",
        "toppings": [
            {
                "name": "Thêm thịt bò cuộn",
                "price": 18000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-214",
        "name": "Tô Canh Bí Đỏ Thịt Nạc Bằm Nấu Nấm Tươi Dinh Dưỡng",
        "slug": "canh-bi-do-thit-nac-nam",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.8,
        "reviews": 70,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bí đỏ hồ lô ngọt bùi ninh nhừ cùng nạc heo bằm và nấm rơm tươi, vị ngọt thanh tự nhiên tốt cho thị lực và não bộ.",
        "toppings": [
            {
                "name": "Thêm thịt nạc bằm",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-215",
        "name": "Tô Canh Cải Xoong Nấu Tôm Tươi Ngọt Nước Tự Nhiên",
        "slug": "canh-cai-xoong-tom-tuoi",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.9,
        "reviews": 65,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/canh-chua.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Rau cải xoong giòn ngọt nấu cùng tôm tươi băm dối, vị ngọt lịm từ biển cả giúp giải nhiệt và bổ sung sắt dồi dào.",
        "toppings": [
            {
                "name": "Thêm tôm tươi bằm",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-216",
        "name": "Súp Gà Rau Củ Nấm Hương Thanh Nhẹ Ít Calo",
        "slug": "sup-ga-rau-cu-nam-huong",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.8,
        "reviews": 80,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/chicken-soup.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Súp nấu từ nước luộc gà không mỡ, thịt ức gà xé, bắp ngọt, nấm tuyết và lòng trắng trứng gà mềm mướt ấm bụng.",
        "toppings": [
            {
                "name": "Thêm ức gà xé",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-217",
        "name": "Nước Ép Cần Tây Táo Xanh Detox Tươi Mát Thải Độc",
        "slug": "nuoc-ep-can-tay-tao-xanh",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 120,
        "time": "15 - 20 phút",
        "image": "https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cần Tây hữu cơ ép tươi cùng táo xanh giòn ngọt và lát gừng tươi, không thêm đường, công thức detox thanh lọc cơ thể chuẩn khoa học.",
        "toppings": [
            {
                "name": "Thêm hạt chia",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-218",
        "name": "Nước Ép Củ Dền Cà Rốt Cam Vàng Sáng Da Đẹp Dáng",
        "slug": "nuoc-ep-cu-den-ca-rot-cam",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.8,
        "reviews": 95,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/beetroot-juice.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Màu đỏ ruby rực rỡ từ củ dền Đà Lạt, cà rốt ngọt lịm và cam vàng mọng nước, giàu chất chống oxy hóa giúp làn da rạng rỡ.",
        "toppings": [
            {
                "name": "Không đá / Ít đá",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-219",
        "name": "Sinh Tố Bơ Chuối Hạt Chia Sữa Hạt Yến Mạch (Không Đường)",
        "slug": "sinh-to-bo-chuoi-yen-mach",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 42000,
        "oldPrice": 52000,
        "rating": 4.9,
        "reviews": 110,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/avocado-smoothie.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bơ sáp Buôn Ma Thuột xay cùng chuối tiêu chín tự nhiên và sữa yến mạch thơm bùi, thức uống giàu năng lượng cho người tập gym.",
        "toppings": [
            {
                "name": "Rắc hạt Granola giòn",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-220",
        "name": "Sữa Chua Hy Lạp Trái Cây Tươi & Hạt Granola Nướng Giòn",
        "slug": "sua-chua-hy-lap-granola",
        "restaurantId": "res-11",
        "restaurantName": "Cơm Tấm Sài Gòn Xưa - Chi Nhánh Phan Chu Trinh",
        "category": "healthy",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.9,
        "reviews": 135,
        "time": "15 - 20 phút",
        "image": "/assets/images/foods/yogurt-granola.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sữa chua Hy Lạp lên men đặc sánh dẻo giàu protein, phủ dâu tây tươi, việt quất và hạt granola nướng mật ong giòn rụm.",
        "toppings": [
            {
                "name": "Thêm hạt Granola giòn",
                "price": 8000
            },
            {
                "name": "Thêm dâu tây tươi",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-221",
        "name": "Mì Ý Spaghetti Bò Bằm Sốt Cà Chua Bolognese Truyền Thống",
        "slug": "spaghetti-bo-bam-bolognese-y-jut",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 79000,
        "oldPrice": 95000,
        "rating": 4.8,
        "reviews": 112,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Sợi mì luộc mềm dai vừa tới ngập tràn sốt thịt bò bằm hầm cà chua bi tươi ngọt thanh, rắc phô mai Parmesan béo bùi và lá húng quế Ý.",
        "toppings": [
            {
                "name": "Thêm phô mai Parmesan bào",
                "price": 12000
            },
            {
                "name": "Bánh mì bơ tỏi giòn rụm",
                "price": 10000
            },
            {
                "name": "Thịt bò bằm hầm thêm",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-222",
        "name": "Mì Ý Hải Sản Tôm Sú Mực Ống Sốt Pesto Húng Tây Xanh",
        "slug": "spaghetti-hai-san-pesto-y-jut",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 98,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Hương vị biển cả Địa Trung Hải! Sốt pesto xanh ngát thơm nồng lá basil và hạt thông béo bùi, tôm sú giòn ngọt và mực tươi xào bơ tỏi.",
        "toppings": [
            {
                "name": "Thêm tôm sú tươi",
                "price": 25000
            },
            {
                "name": "Phô mai Parmesan bào",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-223",
        "name": "Mì Ý Sốt Kem Tươi Nấm & Thịt Ba Chỉ Xông Khói Carbonara",
        "slug": "spaghetti-carbonara-bacon",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 85000,
        "oldPrice": 105000,
        "rating": 4.8,
        "reviews": 88,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Nước sốt kem tươi sánh vàng béo ngậy nấu cùng lòng đỏ trứng gà, thịt ba chỉ hun khói áp chảo giòn thơm và nấm mỡ tươi.",
        "toppings": [
            {
                "name": "Thịt xông khói Bacon giòn",
                "price": 15000
            },
            {
                "name": "Gấp đôi kem phô mai",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-224",
        "name": "Mì Ý Cua Biển Sốt Kem Cà Chua Hồng (Pink Sauce Crab Spaghetti)",
        "slug": "spaghetti-cua-bien-pink-sauce",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 99000,
        "oldPrice": 125000,
        "rating": 4.9,
        "reviews": 76,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/spaghetti-seafood.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Thịt cua biển tươi gỡ miếng đảo cùng sốt kem hồng sánh mịn hòa quyện giữa vị chua thanh của cà chua và vị béo của kem whipping cream.",
        "toppings": [
            {
                "name": "Thêm thịt cua biển gỡ",
                "price": 35000
            },
            {
                "name": "Phô mai bào",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-225",
        "name": "Mì Ý Tôm Tươi Xào Tỏi Ớt Dầu Olive (Spaghetti Aglio e Olio)",
        "slug": "spaghetti-aglio-olio-tom",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 75000,
        "oldPrice": 89000,
        "rating": 4.7,
        "reviews": 65,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Món mì kinh điển nước Ý mộc mạc mà quyến rũ: Sợi mì đảo cùng dầu olive extra virgin, tỏi phi vàng ruộm, ớt hiểm cay nồng và tôm tươi giòn ngọt.",
        "toppings": [
            {
                "name": "Thêm tôm sú tươi",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-226",
        "name": "Mì Ý Thăn Bò Nướng Sốt Tiêu Đen Cay Nhẹ",
        "slug": "spaghetti-bo-sot-tieu-den",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 85000,
        "oldPrice": 105000,
        "rating": 4.8,
        "reviews": 70,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Từng lát thịt thăn bò mềm thơm nướng xém cạnh, rưới sốt tiêu đen hầm vang đỏ cay ấm nồng nàn trên đĩa mì Ý nóng sốt.",
        "toppings": [
            {
                "name": "Thêm thăn bò nướng",
                "price": 25000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-227",
        "name": "Mì Dẹt Fettuccine Sốt Kem Nấm Truffle Đen Quý Phái",
        "slug": "fettuccine-sot-nam-truffle",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 95000,
        "oldPrice": 120000,
        "rating": 4.9,
        "reviews": 82,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1612874742237-6526221588e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Sợi mì dẹt bản lớn dẻo dai đẫm sốt nấm Truffle đen thơm ngát đặc trưng ẩm thực quý tộc Ý, rắc nấm mỡ xào bơ tỏi.",
        "toppings": [
            {
                "name": "Thêm sốt nấm Truffle",
                "price": 20000
            },
            {
                "name": "Phô mai Parmesan",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-228",
        "name": "Lasagna Bò Băm Đút Lò Phô Mai Kéo Sợi Béo Ngậy",
        "slug": "lasagna-bo-bam-dut-lo",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.9,
        "reviews": 95,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Từng lá bột mì xếp lớp xen kẽ sốt bò băm Bolognese, sốt bechamel béo mịn và phủ ngập phô mai Mozzarella đút lò vàng ruộm thơm lừng.",
        "toppings": [
            {
                "name": "Gấp đôi phô mai đút lò",
                "price": 20000
            },
            {
                "name": "Bánh mì bơ tỏi ăn kèm",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-229",
        "name": "Nui Xoắn Fusilli Đút Lò Sốt Phô Mai Cheddar & Mozzarella",
        "slug": "nui-xoan-dut-lo-cheddar",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 75000,
        "oldPrice": 90000,
        "rating": 4.8,
        "reviews": 78,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nui xoắn giữ trọn sốt phô mai béo ngậy đút lò cháy xém mặt, vị mặn mòi thơm phức quyến rũ từng thìa.",
        "toppings": [
            {
                "name": "Thịt xông khói Bacon giòn",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-230",
        "name": "Nui Ống Xào Bò Băm Nấm Rơm Sốt Cà Chua Phô Mai",
        "slug": "nui-ong-xao-bo-bam-nam",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 69000,
        "oldPrice": 85000,
        "rating": 4.7,
        "reviews": 62,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/spaghetti-bolognese.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nui ống luộc mềm xào cùng thịt bò bằm ngấm sốt cà chua tươi và nấm rơm ngọt nước, món ăn ngon miệng hấp dẫn.",
        "toppings": [
            {
                "name": "Thêm thịt bò bằm",
                "price": 18000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-231",
        "name": "Mì Ý Ravioli Bánh Xếp Nhân Phô Mai Ricotta Rau Chân Vịt",
        "slug": "ravioli-ricotta-rau-chan-vit",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 89000,
        "oldPrice": 110000,
        "rating": 4.8,
        "reviews": 54,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1587740908075-9e245070dfaa?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Bánh bột mì xếp thủ công nhồi phô mai Ricotta bùi ngậy và rau chân vịt thanh mát, tắm trong sốt bơ xô thơm lừng.",
        "toppings": [
            {
                "name": "Thêm phô mai Parmesan",
                "price": 12000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-232",
        "name": "Pizza Margherita Truyền Thống Phô Mai Mozzarella & Lá Basil",
        "slug": "pizza-margherita-y-jut",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 99000,
        "oldPrice": 125000,
        "rating": 4.8,
        "reviews": 86,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Chiếc pizza mang màu cờ nước Ý: Sốt cà chua đỏ au, phô mai Mozzarella tươi trắng tinh và lá húng tây xanh ngát nướng lò củi giòn xốp.",
        "toppings": [
            {
                "name": "Thêm phô mai tươi Mozzarella",
                "price": 20000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-233",
        "name": "Pizza Xúc Xích Ý & Ớt Chuông Nướng Lò Than Đá",
        "slug": "pizza-xuc-xich-ot-chuong",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 115000,
        "oldPrice": 139000,
        "rating": 4.7,
        "reviews": 70,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1534308983496-4fabb1a015ee?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Xúc xích Ý đậm đà thái lát kết hợp ớt chuông Đà Lạt 3 màu nướng ngọt giòn và phô mai kéo sợi thơm phức.",
        "toppings": [
            {
                "name": "Gấp đôi xúc xích Ý",
                "price": 22000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-234",
        "name": "Bánh Mì Nướng Bơ Tỏi Phô Mai Mozzarella Đút Lò",
        "slug": "banh-mi-bo-toi-pho-mai-y-jut",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 105,
        "time": "20 - 30 phút",
        "image": "/assets/images/foods/garlic-bread.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Từng lát baguette nướng giòn rụm thơm bơ tỏi, phủ lớp phô mai Mozzarella đút lò vàng ruộm kéo sợi dài bất tận.",
        "toppings": [
            {
                "name": "Thêm phô mai đút lò",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-235",
        "name": "Soup Nấm Rừng Kem Tươi Nóng Hổi Hương Thảo Mộc",
        "slug": "soup-nam-rung-kem-tuoi",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.8,
        "reviews": 75,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Nấm mỡ, nấm hương xào bơ xay nhuyễn cùng kem tươi béo ngậy, dùng nóng thơm lừng chấm cùng bánh mì nướng giòn.",
        "toppings": [
            {
                "name": "Bánh mì bơ tỏi ăn kèm",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-236",
        "name": "Salad Caesar Rau Xanh Ức Gà Nướng Phô Mai Bào Parmesan",
        "slug": "salad-caesar-y-jut",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 55000,
        "oldPrice": 68000,
        "rating": 4.8,
        "reviews": 68,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Xà lách Romaine giòn ngọt trộn sốt Caesar cá cơm truyền thống, bánh mì vụn nướng giòn và phô mai Parmesan bào.",
        "toppings": [
            {
                "name": "Thêm ức gà nướng",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-237",
        "name": "Khoai Tây Nghiền Đút Lò Phô Mai Vàng Óng Kéo Sợi",
        "slug": "khoai-tay-nghien-dut-lo-pho-mai",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 82,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai tây hấp mịn màng trộn bơ lạt, phủ phô mai Mozzarella đút lò thơm nức mũi béo ngậy tan trên đầu lưỡi.",
        "toppings": [
            {
                "name": "Vụn thịt xông khói bacon",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-238",
        "name": "Bánh Panna Cotta Sốt Dâu Rừng Ý Mềm Mịn Thanh Mát",
        "slug": "panna-cotta-dau-rung",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.9,
        "reviews": 92,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Món tráng miệng Ý danh tiếng mềm mịn tan biến trong miệng như thạch sữa béo ngậy, sốt dâu rừng đỏ mọng chua ngọt quyến rũ.",
        "toppings": [
            {
                "name": "Thêm dâu tươi trang trí",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-239",
        "name": "Trà Táo Quế Nóng Hương Thảo Mộc Ấm Áp",
        "slug": "tra-tao-que-nong",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.8,
        "reviews": 60,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Trà đen ủ cùng thanh quế già thơm lừng và lát táo tươi, hòa chút mật ong ngọt dịu làm ấm lòng thực khách.",
        "toppings": [
            {
                "name": "Thêm lát táo tươi",
                "price": 3000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-240",
        "name": "Nước Khoáng Có Ga San Pellegrino Vị Chanh Vàng (Chai)",
        "slug": "san-pellegrino-chanh-vang",
        "restaurantId": "res-12",
        "restaurantName": "Pizza Hut Italia - Chi Nhánh Y Jút",
        "category": "pizza",
        "price": 35000,
        "oldPrice": 42000,
        "rating": 4.8,
        "reviews": 55,
        "time": "20 - 30 phút",
        "image": "https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Dòng nước khoáng có ga đóng chai danh tiếng nước Ý, sảng khoái với bọt khí li ti và hương chanh vàng thanh nhã.",
        "toppings": [
            {
                "name": "Kèm ly đá chanh tươi",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-241",
        "name": "Khoai Tây Lắc Phô Mai Béo Ngậy Giòn Rụm (Hộp Lớn)",
        "slug": "khoai-tay-lac-pho-mai-le-duan",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 42000,
        "rating": 4.9,
        "reviews": 215,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": true,
        "description": "Khoai tây cọng chiên vàng giòn rụm, lắc đều tay trong túi giấy với ngập tràn bột phô mai cam thơm bùi ngọt mặn hấp dẫn.",
        "toppings": [
            {
                "name": "Lắc bột rong biển",
                "price": 5000
            },
            {
                "name": "Sốt chấm mayonnaise tỏi",
                "price": 5000
            },
            {
                "name": "Gấp đôi bột phô mai",
                "price": 8000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-242",
        "name": "Khoai Tây Lắc Rong Biển Sấy Giòn Sốt Mayonnaise",
        "slug": "khoai-tay-lac-rong-bien-le-duan",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 42000,
        "rating": 4.8,
        "reviews": 140,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Khoai chiên giòn tan lắc cùng bột rong biển sấy cay nhẹ thơm nồng phong cách Nhật Bản, chấm sốt mayo béo ngậy.",
        "toppings": [
            {
                "name": "Thêm sốt mayonnaise cay",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-243",
        "name": "Khoai Lang Lắc Xí Muội Chua Ngọt Giòn Bùi",
        "slug": "khoai-lang-lac-xi-muoi",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.8,
        "reviews": 98,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1630384060421-cb20d0e0649d?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoai lang vàng Đắk Lắk dẻo ngọt bùi chiên giòn vỏ, xóc bột xí muội ô mai chua chua ngọt ngọt mặn mặn cực cuốn.",
        "toppings": [
            {
                "name": "Gấp đôi bột xí muội",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-244",
        "name": "Khoai Môn Lệ Phố Chiên Giòn Nhân Trứng Muối Tán Nhuyễn",
        "slug": "khoai-mon-le-pho-trung-muoi",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.9,
        "reviews": 110,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/cheese-balls.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Từng viên khoai môn lệ phố tròn xoe tím mộng mơ chiên xù vàng óng, cắn ngập nhân trứng muối sữa dừa béo bùi ngọt dịu.",
        "toppings": [
            {
                "name": "Thêm 2 viên khoai môn",
                "price": 15000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-245",
        "name": "Phô Mai Que Mozzarella Kéo Sợi Dài Bất Tận (Hộp 4 que)",
        "slug": "pho-mai-que-keo-soi-le-duan",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 42000,
        "oldPrice": 52000,
        "rating": 4.9,
        "reviews": 165,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Lõi phô mai Mozzarella nguyên chất béo ngậy được tẩm 3 lớp bột chiên giòn vàng ruộm, kéo sợi dài cả cánh tay chấm tương cà chua ngọt.",
        "toppings": [
            {
                "name": "Sốt Mayonnaise tỏi chấm kèm",
                "price": 5000
            },
            {
                "name": "Thêm 1 que phô mai",
                "price": 10000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-246",
        "name": "Xúc Xích Đức Nướng Than Hoa Phủ Sốt Cay Phô Mai (2 chiếc)",
        "slug": "xuc-xich-duc-nuong-than",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 92,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/grilled-sausage.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Xúc xích Đức khía hoa nướng than hoa đượm khói giòn sần sật, rưới sốt cay phô mai và tương ớt đậm đà.",
        "toppings": [
            {
                "name": "Thêm 1 cây xúc xích",
                "price": 18000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-247",
        "name": "Bánh Gà Phô Mai Kéo Sợi Vỏ Giòn Rụm (Set 3 chiếc)",
        "slug": "banh-ga-pho-mai-keo-soi",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 85,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/popcorn-chicken.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Thịt ức gà tươi xay nêm nếm gia vị đậm đà ôm trọn viên phô mai Mozzarella, tẩm bột chiên xù vàng ươm giòn tan.",
        "toppings": [
            {
                "name": "Sốt tương ớt chua ngọt",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-248",
        "name": "Bánh Tráng Nướng Trứng Cút Thịt Bằm Phô Mai Đà Lạt",
        "slug": "banh-trang-nuong-da-lat",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.9,
        "reviews": 180,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/banh-trang-nuong.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Pizza Việt Nam danh tiếng! Bánh tráng nướng than hoa giòn rụm với trứng cút, thịt heo bằm, tép khô, mỡ hành, sốt bơ và phô mai béo ngậy.",
        "toppings": [
            {
                "name": "Gấp đôi phô mai kéo sợi",
                "price": 8000
            },
            {
                "name": "Thêm xúc xích nướng",
                "price": 7000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-249",
        "name": "Bánh Tráng Cuộn Sốt Bơ Bò Khô Trứng Cút (Hộp 5 cuốn)",
        "slug": "banh-trang-cuon-sot-bo",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 28000,
        "oldPrice": 35000,
        "rating": 4.8,
        "reviews": 145,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/banh-trang-nuong.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Bánh tráng dẻo tôm phơi sương cuộn bò khô xé cay, trứng cút chiên, rau răm, xoài xanh và chan ngập sốt bơ trứng gà vàng ươm béo ngậy.",
        "toppings": [
            {
                "name": "Thêm sốt bơ trứng",
                "price": 6000
            },
            {
                "name": "Thêm hành phi giòn",
                "price": 4000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-250",
        "name": "Gà Viên Popcorn Giòn Rụm Lắc Bột Ớt Paprika Cay Nhẹ",
        "slug": "ga-popcorn-le-duan",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 39000,
        "oldPrice": 48000,
        "rating": 4.8,
        "reviews": 110,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/popcorn-chicken.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Gà viên không xương tẩm ướp chiên giòn tan từng viên vừa miệng, lắc bột ớt ngọt Paprika thơm nồng hấp dẫn.",
        "toppings": [
            {
                "name": "Lắc thêm phô mai",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-251",
        "name": "Mực Viên Chiên Nước Mắm Bơ Tỏi Giòn Sần Sật (Hộp 6 viên)",
        "slug": "muc-vien-chien-nuoc-mam",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 38000,
        "oldPrice": 48000,
        "rating": 4.7,
        "reviews": 75,
        "time": "12 - 18 phút",
        "image": "https://thumb.wikimedia.org/wikipedia/commons/thumb/0/09/Squid_Ball.jpg/960px-Squid_Ball.jpg",
        "imageCredit": {
            "author": "Bim24",
            "license": "CC BY 4.0",
            "source": "https://commons.wikimedia.org/wiki/File:Squid_Ball.jpg",
            "licenseUrl": "https://creativecommons.org/licenses/by/4.0/"
        },
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Chả mực tươi quết nhuyễn chiên vàng ruộm ngào qua sốt nước mắm tỏi ớt kẹo sệt cay the the thơm nức.",
        "toppings": [
            {
                "name": "Đồ chua dưa leo chấm kèm",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-252",
        "name": "Nem Chua Rán Phố Cổ Hà Nội Giòn Cay (Phần 5 chiếc)",
        "slug": "nem-chua-ran-pho-co",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 45000,
        "oldPrice": 55000,
        "rating": 4.9,
        "reviews": 160,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/nem-ran.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Nem chua tẩm bột chiên xù giòn tan bên ngoài dẻo dai béo ngậy bên trong, chấm tương ớt Mường Khương chuẩn vị Hà Thành.",
        "toppings": [
            {
                "name": "Thêm đĩa nem chua (3 chiếc)",
                "price": 25000
            },
            {
                "name": "Dưa leo quả xắt lát",
                "price": 0
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-253",
        "name": "Chả Cá Viên Chiên Nước Mắm Tỏi Ớt Đậm Đà (Hộp 10 viên)",
        "slug": "ca-vien-chien-nuoc-mam",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.8,
        "reviews": 125,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/cheese-balls.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Cá viên chiên phồng xù đảo trên chảo sốt mắm đường tỏi ớt kẹo quánh và rau muống giòn, món ăn vặt tuổi thơ gây nghiện.",
        "toppings": [
            {
                "name": "Thêm rau muống xào sốt mắm",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-254",
        "name": "Bắp Xào Bơ Tép Khô Mỡ Hành Thơm Lừng",
        "slug": "bap-xao-bo-tep-mo-hanh",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.8,
        "reviews": 90,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/bap-xao.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Hạt bắp Mỹ vàng ngọt mọng nước xào bơ Tường An thơm ngào ngạt, tép khô mặn mặn ngọt ngọt và mỡ hành xanh mướt.",
        "toppings": [
            {
                "name": "Thêm phô mai kéo sợi",
                "price": 8000
            },
            {
                "name": "Thêm tép khô",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-255",
        "name": "Bánh Hành Tây Chiên Giòn Xù Kiểu Mỹ (Onion Rings Le Duan)",
        "slug": "hanh-tay-chien-gion-le-duan",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 32000,
        "oldPrice": 40000,
        "rating": 4.7,
        "reviews": 68,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/onion-rings.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Khoanh hành tây ngọt giòn tẩm bột chiên xù vàng óng ả chấm sốt tương cà ngọt chua thanh mát.",
        "toppings": [
            {
                "name": "Sốt Mayonnaise tỏi",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-256",
        "name": "Xíu Mại Viên Hấp Trứng Cút Nóng Hổi Chấm Bánh Mì (3 viên)",
        "slug": "xiu-mai-vien-trung-cut",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.8,
        "reviews": 88,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/meatballs-tomato.jpg",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Viên thịt xíu mại to mập ôm trọn trứng cút bên trong, ninh trong nước sốt cà chua ấm nồng thơm mùi hành ngò.",
        "toppings": [
            {
                "name": "Kèm ổ bánh mì giòn",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-257",
        "name": "Da Gà Chiên Giòn Rụm Chấm Muối Tiêu Chanh Ớt",
        "slug": "da-ga-chien-gion-rum",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.9,
        "reviews": 140,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1567620832903-9fc6debc209f?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Da gà làm sạch lọc bỏ hoàn toàn mỡ dưới da, chiên phồng xốp giòn rụm tan ngay trong miệng, rắc muối ớt cay tê tê.",
        "toppings": [
            {
                "name": "Lắc phô mai cay",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-258",
        "name": "Trà Tắc Xí Muội Khổng Lồ Mát Lạnh (Ly Lớn 700ml)",
        "slug": "tra-tac-xi-muoi-khong-lo",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 22000,
        "oldPrice": 28000,
        "rating": 4.9,
        "reviews": 175,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Ly trà tắc khổng lồ thơm ngào ngạt cốt quất tươi hòa quyện trái xí muội mặn ngọt chua thanh sảng khoái đánh bay cơn khát.",
        "toppings": [
            {
                "name": "Thêm trái xí muội mặn ngọt",
                "price": 5000
            },
            {
                "name": "Thạch nha đam",
                "price": 5000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-259",
        "name": "Trà Sữa Trân Châu Sợi Dừa Nướng Thơm Béo Bùi",
        "slug": "tra-sua-dua-nuong",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 35000,
        "oldPrice": 45000,
        "rating": 4.8,
        "reviews": 110,
        "time": "12 - 18 phút",
        "image": "https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80",
        "isBestSeller": false,
        "isFlashDeal": false,
        "description": "Trà sữa thơm ngậy mùi dừa nướng Bến Tre, topping dừa nướng sấy giòn rụm rắc trên mặt ly uống vui miệng cực kỳ.",
        "toppings": [
            {
                "name": "Thêm dừa nướng giòn",
                "price": 6000
            },
            {
                "name": "Trân châu đen",
                "price": 6000
            }
        ],
        "isLiked": false
    },
    {
        "id": "prod-260",
        "name": "Tàu Hũ Trân Châu Đường Đen Cốt Dừa Béo Ngậy",
        "slug": "tau-hu-tran-chau-duong-den",
        "restaurantId": "res-13",
        "restaurantName": "Burger King Station - Chi Nhánh Lê Duẩn",
        "category": "an-vat",
        "price": 25000,
        "oldPrice": 32000,
        "rating": 4.9,
        "reviews": 160,
        "time": "12 - 18 phút",
        "image": "/assets/images/foods/tau-hu-tran-chau.jpg",
        "isBestSeller": true,
        "isFlashDeal": false,
        "description": "Tàu hũ non núng nính mềm mịn tan ngay trên đầu lưỡi, chan nước cốt dừa béo ngậy và trân châu đường đen dẻo nóng ngọt ngào.",
        "toppings": [
            {
                "name": "Gấp đôi trân châu đường đen",
                "price": 6000
            }
        ],
        "isLiked": false
    }
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

const FLASH_DEALS_STORAGE_KEY = 'fastfood-flash-deals-v2';
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
        const image = String(sameNamedProduct?.image || product.image || '').trim();
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
    const addedProducts = originalProducts.filter((product) => (
      !liveProducts.some((item) => String(item.id) === String(product.id) || item.name.toLocaleLowerCase('vi') === product.name.toLocaleLowerCase('vi'))
    ));
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

/* ============================================================
   PERSISTENCE: Lưu trạng thái YÊU THÍCH vào localStorage
   ============================================================ */
const FAVORITE_STORAGE_KEY = 'fastfood-favorites-v1';

function loadFavoritesFromStorage() {
  try {
    const raw = localStorage.getItem(FAVORITE_STORAGE_KEY);
    if (!raw) return { products: [], restaurants: [] };
    const parsed = JSON.parse(raw);
    const favProducts = new Set((parsed.products || []).map(String));
    const favRestaurants = new Set((parsed.restaurants || []).map(String));

    if (Array.isArray(FASTFOOD_DATA.products)) {
      FASTFOOD_DATA.products.forEach(p => {
        p.isLiked = favProducts.has(String(p.id));
      });
    }
    if (Array.isArray(FASTFOOD_DATA.restaurants)) {
      FASTFOOD_DATA.restaurants.forEach(r => {
        r.isLiked = favRestaurants.has(String(r.id));
      });
    }
    return { products: Array.from(favProducts), restaurants: Array.from(favRestaurants) };
  } catch (e) {
    console.error('Không thể đọc yêu thích từ localStorage:', e);
    return { products: [], restaurants: [] };
  }
}

function saveFavoritesToStorage() {
  try {
    const likedProducts = (FASTFOOD_DATA.products || []).filter(p => p.isLiked).map(p => String(p.id));
    const likedRestaurants = (FASTFOOD_DATA.restaurants || []).filter(r => r.isLiked).map(r => String(r.id));
    localStorage.setItem(FAVORITE_STORAGE_KEY, JSON.stringify({
      products: likedProducts,
      restaurants: likedRestaurants,
    }));
  } catch (e) {
    console.error('Không thể lưu yêu thích vào localStorage:', e);
  }
}

function toggleProductFavorite(productId) {
  if (!FASTFOOD_DATA || !Array.isArray(FASTFOOD_DATA.products)) return false;
  const product = FASTFOOD_DATA.products.find(p => String(p.id) === String(productId));
  if (!product) return false;
  product.isLiked = !product.isLiked;
  saveFavoritesToStorage();
  if (window.UIComponents && typeof window.UIComponents.updateFavoriteBadge === 'function') {
    window.UIComponents.updateFavoriteBadge();
  }
  return product.isLiked;
}

function toggleRestaurantFavorite(resId) {
  if (!FASTFOOD_DATA || !Array.isArray(FASTFOOD_DATA.restaurants)) return false;
  const res = FASTFOOD_DATA.restaurants.find(r => String(r.id) === String(resId));
  if (!res) return false;
  res.isLiked = !res.isLiked;
  saveFavoritesToStorage();
  if (window.UIComponents && typeof window.UIComponents.updateFavoriteBadge === 'function') {
    window.UIComponents.updateFavoriteBadge();
  }
  return res.isLiked;
}

// Khôi phục trạng thái yêu thích khi tải file data.js
loadFavoritesFromStorage();

// Expose để toàn bộ các trang khác có thể gọi đồng bộ
window.FAVORITE_STORAGE_KEY = FAVORITE_STORAGE_KEY;
window.loadFavoritesFromStorage = loadFavoritesFromStorage;
window.saveFavoritesToStorage = saveFavoritesToStorage;
window.toggleProductFavorite = toggleProductFavorite;
window.toggleRestaurantFavorite = toggleRestaurantFavorite;
