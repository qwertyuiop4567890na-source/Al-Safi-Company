<?php
require_once __DIR__ . '/config.php';

echo "<h3>اختبار config.php</h3>";

// اختبار 1: قراءة المنتجات
$products = getProducts();
echo "<p>✅ getProducts(): " . (is_array($products) ? 'نجاح' : 'فشل') . "</p>";

// اختبار 2: حفظ منتج تجريبي
$test_product = [
    'products' => [
        ['id' => 1, 'name' => 'تجربة', 'price' => 10, 'description' => 'منتج اختبار', 'image' => 'products/no-image.png']
    ],
    'last_id' => 1
];
$saved = saveProducts($test_product);
echo "<p>✅ saveProducts(): " . ($saved ? 'نجاح' : 'فشل') . "</p>";

// اختبار 3: حفظ طلب تجريبي
$test_order = [
    'order_id' => 'TEST-001',
    'date' => date('Y-m-d H:i:s'),
    'customer' => ['name' => 'تجربة', 'phone' => '123456', 'email' => 'test@test.com', 'address' => 'Test'],
    'items' => [['name' => 'منتج', 'qty' => 1, 'price' => 10]],
    'total' => 10,
    'status' => 'new'
];
$order_saved = saveOrder($test_order);
echo "<p>✅ saveOrder(): " . ($order_saved ? 'نجاح' : 'فشل') . "</p>";

// اختبار 4: قراءة الطلبات
$orders = getOrders();
echo "<p>✅ getOrders(): " . (is_array($orders) ? 'نجاح' : 'فشل') . "</p>";

echo "<p><a href='index.php'>← العودة للمتجر</a></p>";
?>