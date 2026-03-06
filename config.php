<?php
/**
 * ⚙️ config.php - إعدادات النظام الكاملة
 * الإصدار: 3.2.1 | جميع الدوال المطلوبة
 */

// ============================================================================
// 🌐 إعدادات الموقع الأساسية
// ============================================================================
define('COMPANY_NAME', 'شركة السافي الوطنية لاستيراد المواد الغذائية');
define('ADMIN_EMAIL', 'admin@example.com');           // ⚠️ غيّر لإيميلك
define('ADMIN_PASSWORD', 'Admin@2026');               // ⚠️ غيّر لكلمة قوية

// 📧 إعدادات SMTP
define('SMTP_EMAIL', 'qwertyuiop4567890.n.a@gmail.com');
define('SMTP_PASSWORD', 'hxav jfbk qcjn udkt');

// ============================================================================
// 📁 مسارات الملفات
// ============================================================================
$base_dir = __DIR__;

define('DATA_DIR', $base_dir . '/data');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
define('UPLOAD_DIR', $base_dir . '/products/');
define('LOGS_DIR', $base_dir . '/logs');
define('ERROR_LOG', LOGS_DIR . '/error.log');
define('ORDERS_DIR', $base_dir . '/orders');

// ============================================================================
// 📦 دوال المنتجات
// ============================================================================

function getProducts() {
    if (!file_exists(PRODUCTS_FILE)) {
        $default = ['products' => [], 'last_id' => 0];
        saveProducts($default);
        return $default;
    }
    $content = @file_get_contents(PRODUCTS_FILE);
    if ($content === false) return ['products' => [], 'last_id' => 0];
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $data = json_decode($content, true);
    return $data ?? ['products' => [], 'last_id' => 0];
}

function saveProducts($data) {
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0755, true)) return false;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents(PRODUCTS_FILE, $json) !== false;
}

// ============================================================================
// 📦 دوال الطلبات (موجودة هنا - هذا هو الحل)
// ============================================================================

function saveOrder($order) {
    if (!is_dir(ORDERS_DIR) && !mkdir(ORDERS_DIR, 0755, true)) return false;
    $month_file = ORDERS_DIR . '/' . date('Y-m') . '.json';
    $orders = file_exists($month_file) 
        ? (json_decode(@file_get_contents($month_file), true) ?? []) 
        : [];
    $orders[] = $order;
    $json = json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($month_file, $json, LOCK_EX) !== false;
}

function getOrders($limit = 100) {
    if (!is_dir(ORDERS_DIR)) return [];
    $orders = [];
    $files = glob(ORDERS_DIR . '/*.json');
    rsort($files);
    foreach (array_slice($files, 0, 12) as $file) {
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $file_orders = json_decode($content, true) ?? [];
            $orders = array_merge($orders, $file_orders);
        }
    }
    usort($orders, fn($a, $b) => strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0));
    return array_slice($orders, 0, $limit);
}

// ============================================================================
// 🖼️ دوال الصور
// ============================================================================

function uploadProductImage($file) {
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif']) || $file['size'] > 5*1024*1024) return null;
    $filename = 'prod_' . uniqid() . '.' . $ext;
    $target = UPLOAD_DIR . $filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        @chmod($target, 0644);
        return 'products/' . $filename;
    }
    return null;
}

function deleteProductImage($path) {
    if (!$path || strpos($path, 'no-image') !== false) return;
    $full = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (file_exists($full) && is_file($full)) @unlink($full);
}

// ============================================================================
// 🛡️ دوال مساعدة
// ============================================================================

function sanitizeInput($input, $type = 'string') {
    if ($input === null) return null;
    switch($type) {
        case 'string': return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8', false);
        case 'email': $e = filter_var(trim($input), FILTER_VALIDATE_EMAIL); return $e ? htmlspecialchars($e, ENT_QUOTES, 'UTF-8', false) : null;
        case 'phone': return preg_replace('/[^\d+\-\s()]/', '', trim($input));
        case 'int': return filter_var($input, FILTER_VALIDATE_INT) ?: 0;
        case 'float': return filter_var($input, FILTER_VALIDATE_FLOAT) ?: 0.0;
        default: return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8', false);
    }
}