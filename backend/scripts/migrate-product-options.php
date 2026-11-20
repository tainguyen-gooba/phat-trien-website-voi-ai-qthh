<?php
/**
 * FASTFOOD - Migration Script: Biến thể & Tùy chọn món ăn (Product Options & Variants)
 * Thêm cột `options` vào bảng `products` và nạp dữ liệu mẫu tùy chọn (Size, Topping, Độ cay, Ngọt, Đá...)
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

echo "====================================================\n";
echo "   MIGRATION: BIẾN THỂ & TÙY CHỌN MÓN ĂN (FASTFOOD)   \n";
echo "====================================================\n\n";

try {
    $db = getDB();

    // 1. Kiểm tra và thêm cột `options` vào bảng `products`
    $cols = $db->query("SHOW COLUMNS FROM products LIKE 'options'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `options` LONGTEXT NULL COMMENT 'Biến thể & tùy chọn: size, topping, khẩu vị' AFTER `prep_time_minutes`");
        echo "[OK] Đã thêm cột `options` vào bảng `products` thành công.\n";
    } else {
        echo "[INFO] Cột `options` đã tồn tại trong bảng `products`.\n";
    }

    // 2. Lấy danh sách sản phẩm và danh mục để tạo options phù hợp
    $products = $db->query("SELECT p.id, p.name, p.category_id, c.name AS category_name, c.slug AS category_slug 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id")->fetchAll();

    echo "[INFO] Đang tạo dữ liệu biến thể & tùy chọn cho " . count($products) . " món ăn...\n";

    $updateStmt = $db->prepare("UPDATE products SET options = ? WHERE id = ?");

    $count = 0;
    foreach ($products as $p) {
        $catName = mb_strtolower($p['category_name'] ?? '', 'UTF-8');
        $catSlug = strtolower($p['category_slug'] ?? '');
        $pName   = mb_strtolower($p['name'] ?? '', 'UTF-8');

        $options = [];

        // Phân loại theo loại món
        if (strpos($catName, 'trà sữa') !== false || strpos($catName, 'uống') !== false || strpos($catSlug, 'tra-sua') !== false || strpos($pName, 'trà') !== false || strpos($pName, 'sữa') !== false || strpos($pName, 'cà phê') !== false) {
            // Nhóm Đồ uống & Trà sữa
            $options = [
                'sizes' => [
                    ['name' => 'Size M (500ml)', 'price' => 0, 'is_default' => true],
                    ['name' => 'Size L (700ml)', 'price' => 8000, 'is_default' => false]
                ],
                'toppings' => [
                    ['name' => 'Trân châu đường đen dẻo', 'price' => 6000],
                    ['name' => 'Kem cheese mặn béo ngậy', 'price' => 10000],
                    ['name' => 'Thạch nha đam thanh mát', 'price' => 5000],
                    ['name' => 'Pudding trứng mịn màng', 'price' => 7000],
                    ['name' => 'Thạch dừa sần sật', 'price' => 5000]
                ],
                'attributes' => [
                    [
                        'name' => 'Lượng đường',
                        'options' => ['100% đường (Chuẩn)', '70% đường', '50% đường', '30% đường', 'Không đường']
                    ],
                    [
                        'name' => 'Lượng đá',
                        'options' => ['100% đá (Chuẩn)', '50% đá (Ít đá)', 'Không đá']
                    ]
                ]
            ];
        } elseif (strpos($catName, 'burger') !== false || strpos($catName, 'gà') !== false || strpos($catSlug, 'burger') !== false || strpos($pName, 'burger') !== false || strpos($pName, 'gà rán') !== false) {
            // Nhóm Burger & Gà Rán
            $options = [
                'sizes' => [
                    ['name' => 'Cỡ Vừa (Regular)', 'price' => 0, 'is_default' => true],
                    ['name' => 'Cỡ Lớn (Up-size)', 'price' => 15000, 'is_default' => false],
                    ['name' => 'Double Nhân Đôi (Siêu To)', 'price' => 25000, 'is_default' => false]
                ],
                'toppings' => [
                    ['name' => 'Thêm phô mai lát Cheddar', 'price' => 10000],
                    ['name' => 'Thịt ba chỉ xông khói Bacon', 'price' => 15000],
                    ['name' => 'Khoai tây chiên nhỏ đi kèm', 'price' => 15000],
                    ['name' => 'Sốt phô mai cay béo ngậy', 'price' => 8000]
                ],
                'attributes' => [
                    [
                        'name' => 'Hương vị & Độ cay',
                        'options' => ['Giòn truyền thống', 'Sốt cay Hàn Quốc', 'Lắc phô mai (+0đ)']
                    ]
                ]
            ];
        } elseif (strpos($catName, 'pizza') !== false || strpos($catName, 'mỳ') !== false || strpos($catName, 'mì') !== false || strpos($catSlug, 'pizza') !== false || strpos($pName, 'pizza') !== false || strpos($pName, 'spaghetti') !== false) {
            // Nhóm Pizza & Mì Ý
            $options = [
                'sizes' => [
                    ['name' => 'Cỡ Nhỏ (6 inch)', 'price' => 0, 'is_default' => true],
                    ['name' => 'Cỡ Vừa (9 inch)', 'price' => 35000, 'is_default' => false],
                    ['name' => 'Cỡ Lớn (12 inch)', 'price' => 70000, 'is_default' => false]
                ],
                'toppings' => [
                    ['name' => 'Gấp đôi phô mai Mozzarella', 'price' => 15000],
                    ['name' => 'Thêm xúc xích Pepperoni cay', 'price' => 18000],
                    ['name' => 'Viền phô mai béo ngậy', 'price' => 20000],
                    ['name' => 'Viền xúc xích phô mai', 'price' => 25000]
                ],
                'attributes' => [
                    [
                        'name' => 'Đế bánh Pizza',
                        'options' => ['Đế dày xốp mềm', 'Đế mỏng giòn rụm', 'Đế vừa truyền thống']
                    ]
                ]
            ];
        } elseif (strpos($catName, 'sushi') !== false || strpos($catName, 'nhật') !== false || strpos($pName, 'sushi') !== false || strpos($pName, 'sashimi') !== false) {
            // Nhóm Sushi & Món Nhật
            $options = [
                'sizes' => [
                    ['name' => 'Phần Tiêu Chuẩn', 'price' => 0, 'is_default' => true],
                    ['name' => 'Phần Đặc Biệt (Gấp rưỡi)', 'price' => 25000, 'is_default' => false]
                ],
                'toppings' => [
                    ['name' => 'Thêm trứng cá hồi Tobiko', 'price' => 20000],
                    ['name' => 'Súp Miso rong biển nóng', 'price' => 12000],
                    ['name' => 'Gừng hồng chua ngọt Nhật', 'price' => 6000]
                ],
                'attributes' => [
                    [
                        'name' => 'Mù tạt Wasabi & Nước tương',
                        'options' => ['Wasabi vừa (Chuẩn)', 'Nhiều Wasabi cay nồng', 'Không lấy Wasabi']
                    ]
                ]
            ];
        } elseif (strpos($catName, 'cơm') !== false || strpos($catName, 'việt') !== false || strpos($catSlug, 'com') !== false || strpos($pName, 'cơm') !== false || strpos($pName, 'bún') !== false || strpos($pName, 'phở') !== false) {
            // Nhóm Cơm & Món Việt
            $options = [
                'sizes' => [
                    ['name' => 'Hộp Tiêu Chuẩn', 'price' => 0, 'is_default' => true],
                    ['name' => 'Suất Lớn (+ Cơm & Thịt)', 'price' => 10000, 'is_default' => false],
                    ['name' => 'Suất Đặc Biệt Siêu No', 'price' => 20000, 'is_default' => false]
                ],
                'toppings' => [
                    ['name' => 'Trứng ốp la lòng đào', 'price' => 8000],
                    ['name' => 'Chả lụa / Chả trứng thơm', 'price' => 10000],
                    ['name' => 'Canh rong biển súp ấm', 'price' => 12000],
                    ['name' => 'Thêm cơm trắng dẻo thơm', 'price' => 5000],
                    ['name' => 'Kim chi chua ngọt cay giòn', 'price' => 7000]
                ],
                'attributes' => [
                    [
                        'name' => 'Mức độ cay',
                        'options' => ['Không cay', 'Cay vừa', 'Cay nhiều']
                    ],
                    [
                        'name' => 'Ghi chú chế biến',
                        'options' => ['Đầy đủ mỡ hành & tóp mỡ', 'Ít mỡ hành', 'Không hành lá / tỏi']
                    ]
                ]
            ];
        } else {
            // Nhóm Ăn Vặt / Món chung
            $options = [
                'sizes' => [
                    ['name' => 'Phần Vừa', 'price' => 0, 'is_default' => true],
                    ['name' => 'Phần Lớn (+ Thêm món)', 'price' => 12000, 'is_default' => false]
                ],
                'toppings' => [
                    ['name' => 'Thêm phô mai sợi kéo béo', 'price' => 10000],
                    ['name' => 'Trứng cút chiên giòn', 'price' => 8000],
                    ['name' => 'Sốt chấm đặc biệt FASTFOOD', 'price' => 5000]
                ],
                'attributes' => [
                    [
                        'name' => 'Khẩu vị',
                        'options' => ['Ăn nóng giòn liền', 'Để riêng sốt chấm', 'Cay vừa']
                    ]
                ]
            ];
        }

        $jsonOptions = json_encode($options, JSON_UNESCAPED_UNICODE);
        $updateStmt->execute([$jsonOptions, $p['id']]);
        $count++;
    }

    echo "[SUCCESS] Đã cập nhật thành công biến thể & tùy chọn cho {$count} món ăn!\n";

} catch (Exception $e) {
    echo "[ERROR] Thất bại: " . $e->getMessage() . "\n";
    exit(1);
}
