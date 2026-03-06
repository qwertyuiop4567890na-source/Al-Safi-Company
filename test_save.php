<?php
require_once __DIR__ . '/config.php';

echo "<h3>اختبار الحفظ</h3>";

// اختبار 1: حفظ منتج
$products = getProducts();
$products['products'][] = [
    'id' => ++$products['last_id'],
    'name' => 'منتج اختبار',
    'price' => 10.00,
    'description' => 'وصف اختبار',
    'image' => 'products/no-image.png',
    'created_at' => date('Y-m-d H:i:s')
];

if (saveProducts($products)) {
    echo "<p style='color:green'>✅ saveProducts() يعمل</p>";
} else {
    echo "<p style='color:red'>❌ saveProducts() فشل</p>";
}

// اختبار 2: حفظ طلب
$test_order = [
    'order_id' => 'TEST-001',
    'date' => date('Y-m-d H:i:s'),
    'customer' => ['name' => 'Test', 'phone' => '123', 'email' => 'test@test.com', 'address' => 'Test'],
    'items' => [['name' => 'Item', 'qty' => 1, 'price' => 10]],
    'total' => 10,
    'status' => 'new'
];

if (saveOrder($test_order)) {
    echo "<p style='color:green'>✅ saveOrder() يعمل</p>";
} else {
    echo "<p style='color:red'>❌ saveOrder() فشل</p>";
}

echo "<p><a href='admin.php'>← العودة للوحة التحكم</a></p>";
?>