<?php
/**
 * 🛒 Super Market - متجر إلكتروني آمن
 * الإصدار: 3.0.0 | عربي فقط | وضع ليلي | متجاوب بالكامل
 */

declare(strict_types=1);
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true
]);

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

define('APP_ACCESS', true);
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================================================
// 🌐 النصوص العربية
// ============================================================================
$t = [
    'site_title' => COMPANY_NAME,
    'tagline' => 'استيراد المواد الغذائية - جودة عالية وأسعار منافسة',
    'cta' => 'اطلب منتجاتك الآن وسيتم التواصل معك فوراً',
    'products' => 'منتجاتنا',
    'available' => 'منتج متاح',
    'add_to_cart' => 'أضف للسلة',
    'added' => '✓ مضاف',
    'cart' => 'السلة',
    'checkout' => 'إتمام الطلب',
    'empty_cart' => 'السلة فارغة 🛒',
    'name' => 'الاسم الكامل',
    'phone' => 'رقم الجوال',
    'email' => 'البريد الإلكتروني',
    'address' => 'عنوان التوصيل',
    'total' => 'الإجمالي النهائي',
    'submit' => 'إرسال الطلب',
    'sending' => 'جاري الإرسال...',
    'cancel' => 'إلغاء',
    'success' => '✅ تم إرسال طلبك بنجاح!',
    'error' => '❌ حدث خطأ، يرجى المحاولة لاحقاً',
    'warning' => '⚠️',
    'required' => 'حقل مطلوب',
    'currency_symbol' => 'د.ل',
    'contact' => 'للتواصل',
    'footer_text' => 'جميع الحقوق محفوظة',
    'order_id' => 'رقم الطلب',
    'customer_info' => 'بيانات العميل',
    'order_details' => 'تفاصيل الطلبية',
    'product' => 'المنتج',
    'qty' => 'الكمية',
    'unit_price' => 'سعر الوحدة',
    'subtotal' => 'الإجمالي',
    'no_products' => 'لا توجد منتجات حالياً',
    'add_admin' => 'أضف منتجات من لوحة التحكم',
    'login_admin' => 'دخول للأدمن',
    'view_store' => 'عرض المتجر',
    // 📋 عناصر القائمة
    'nav_home' => 'الرئيسية',
    'nav_products' => 'المنتجات',
    'nav_contact' => 'تواصل معنا',
    'nav_about' => 'من نحن',
    'nav_orders' => 'الطلبات',
    // 🌙 الوضع الليلي
    'dark_mode' => 'الوضع الليلي',
    'light_mode' => 'الوضع النهاري',
];

$dir = 'rtl';

// ============================================================================
// 🛡️ دوال المساعدة
// ============================================================================
function sanitize($input, string $type = 'string') {
    if ($input === null) return null;
    switch($type) {
        case 'string': return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8', false);
        case 'email':
            $email = filter_var(trim($input), FILTER_VALIDATE_EMAIL);
            return $email ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8', false) : null;
        case 'phone': return preg_replace('/[^\d+\-\s()]/', '', trim($input));
        case 'int': return filter_var($input, FILTER_VALIDATE_INT) ?: 0;
        case 'float': return filter_var($input, FILTER_VALIDATE_FLOAT) ?: 0.0;
        default: return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8', false);
    }
}

$result = "";
$result_type = "";

// ============================================================================
// 🛒 معالجة الطلب
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cart'])) {
    
    $name = sanitize($_POST['cname'] ?? '', 'string');
    $phone = sanitize($_POST['cphone'] ?? '', 'phone');
    $email = sanitize($_POST['cemail'] ?? '', 'email');
    $address = sanitize($_POST['caddress'] ?? '', 'text');
    
    $items = [];
    $cart_data = json_decode($_POST['cart'] ?? '[]', true);
    
    if (is_array($cart_data)) {
        foreach ($cart_data as $it) {
            if (!isset($it['id'], $it['name'], $it['price'], $it['qty'])) continue;
            $items[] = [
                'name' => sanitize($it['name'], 'string'),
                'qty' => max(1, min(99, intval($it['qty']))),
                'price' => max(0, min(99999, floatval($it['price'])))
            ];
        }
    }
    
    $errors = [];
    if (mb_strlen($name) < 2) $errors[] = 'name';
    if (!preg_match('/^[\d+\-\s()]{7,20}$/', $phone)) $errors[] = 'phone';
    if (empty($items)) $errors[] = 'cart';
    
    if (!empty($errors)) {
        $result = "⚠️ الرجاء تصحيح الأخطاء";
        $result_type = "warning";
    } else {
        $order_id = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $sub = "🛒 طلب جديد #$order_id - " . COMPANY_NAME;
        
        $tbl = "";
        $grand_total = 0;
        $currency = $t['currency_symbol'];
        foreach ($items as $i) {
            $s = $i['price'] * $i['qty'];
            $grand_total += $s;
            $tbl .= "<tr>
                <td style='padding:10px;border:1px solid #ddd;text-align:right'>{$i['name']}</td>
                <td style='padding:10px;border:1px solid #ddd;text-align:center'>{$i['qty']}</td>
                <td style='padding:10px;border:1px solid #ddd;text-align:center'>" . number_format($i['price'],2) . " $currency</td>
                <td style='padding:10px;border:1px solid #ddd;text-align:center;font-weight:bold'>" . number_format($s,2) . " $currency</td>
            </tr>";
        }
        
        $body = "
        <html dir='rtl' lang='ar'>
        <head><meta charset='UTF-8'><style>
            body{font-family:'Segoe UI',Tahoma,sans-serif;direction:rtl;margin:0;padding:0;background:#f5f5f5;color:#333}
            .container{max-width:600px;margin:20px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 3px 15px rgba(0,0,0,0.1)}
            .header{background:linear-gradient(135deg,#1e3c72,#2a5298);color:#fff;padding:25px;text-align:center}
            .header h1{margin:0;font-size:22px}
            .content{padding:20px}
            .info{background:#f8f9fa;padding:12px;margin:10px 0;border-radius:6px}
            table{width:100%;border-collapse:collapse;margin:15px 0}
            th{background:#1e3c72;color:#fff;padding:10px;text-align:center}
            td{padding:10px;border:1px solid #eee;text-align:center}
            .total{background:#2ecc71;color:#fff;font-weight:bold}
            .footer{background:#1e3c72;color:#fff;padding:15px;text-align:center;font-size:12px}
        </style></head>
        <body>
        <div class='container'>
            <div class='header'>
                <div style='font-size:35px;margin-bottom:8px'>🏢</div>
                <h1>" . COMPANY_NAME . "</h1>
                <p style='margin:5px 0 0;font-size:12px;opacity:0.9'>#$order_id | " . date('Y-m-d H:i') . "</p>
            </div>
            <div class='content'>
                <div class='info'><strong>👤 العميل:</strong> $name<br>
                <strong>📱 الجوال:</strong> $phone<br>
                <strong>📧 البريد:</strong> " . ($email ?: 'غير متاح') . "<br>
                <strong>📍 العنوان:</strong> " . ($address ?: 'غير محدد') . "</div>
                <table><thead><tr><th style='text-align:right'>المنتج</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
                <tbody>$tbl<tr class='total'><td colspan='3' style='text-align:left;padding:12px'><strong>💰 الإجمالي:</strong></td><td style='padding:12px;font-size:16px'>" . number_format($grand_total, 2) . " $currency</td></tr></tbody></table>
            </div>
            <div class='footer'>
                <p style='margin:0;font-weight:600'>" . COMPANY_NAME . "</p>
                <p style='margin:5px 0 0;opacity:0.8'>" . ADMIN_EMAIL . "</p>
            </div>
        </div></body></html>";
        
        $alt = COMPANY_NAME . "\nطلب جديد: #$order_id\nالعميل: $name\nالجوال: $phone\nالإجمالي: $grand_total $currency";
        
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_EMAIL;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom(SMTP_EMAIL, COMPANY_NAME);
            $mail->addAddress(ADMIN_EMAIL);
            if ($email) $mail->addReplyTo($email, $name);
            
            $mail->isHTML(true);
            $mail->Subject = $sub;
            $mail->Body = $body;
            $mail->AltBody = $alt;
            
            $mail->send();
            
            $result = $t['success'] . " #$order_id";
            $result_type = "success";
            echo "<script>localStorage.removeItem('cart'); setTimeout(() => window.location.href = 'index.php', 2500);</script>";
            
        } catch(Exception $e) {
            $result = $t['error'];
            $result_type = "danger";
            error_log("Mail Error: " . $e->getMessage());
        }
    }
}

// 📦 جلب المنتجات
$data = getProducts();
$products = $data['products'] ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($t['site_title']); ?></title>
    
    <!-- 🎨 المكتبات -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3c72;
            --primary-dark: #162e54;
            --secondary: #2a5298;
            --accent: #2ecc71;
            --accent-hover: #27ae60;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --gray: #6c757d;
            --border: #e9ecef;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --radius: 16px;
            --radius-sm: 10px;
            --transition: all 0.3s ease;
            
            /* 🌙 ألوان الوضع النهاري (الافتراضي) */
            --bg-primary: #f5f7fa;
            --bg-secondary: #e4e8f0;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --card-bg: #ffffff;
            --navbar-bg: linear-gradient(135deg, #1e3c72, #2a5298);
        }
        
        /* 🌙 ألوان الوضع الليلي */
        [data-theme="dark"] {
            --bg-primary: #0f0f1a;
            --bg-secondary: #1a1a2e;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0a0;
            --card-bg: #1e1e32;
            --navbar-bg: linear-gradient(135deg, #1a1a2e, #2d2d44);
            --border: #333344;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.5);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
            transition: var(--transition);
        }
        
        /* 🌙 زر تبديل الوضع الليلي */
        .theme-switcher {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1050;
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 0.3rem;
            display: flex;
            gap: 2px;
            border: 2px solid var(--border);
        }
        .theme-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            background: none;
            border-radius: 18px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .theme-btn.active {
            background: var(--primary);
            color: #fff;
        }
        .theme-btn:hover:not(.active) {
            color: var(--text-primary);
            background: var(--light);
        }
        
        /* 🧭 Navbar */
        .navbar {
            background: var(--navbar-bg);
            box-shadow: var(--shadow);
            padding: 0.8rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            transition: var(--transition);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff !important;
        }
        .navbar-brand i { font-size: 1.4rem; }
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: var(--transition);
        }
        .navbar-nav .nav-link:hover {
            color: #2ecc71 !important;
            transform: translateY(-2px);
        }
        .navbar-nav .nav-link.active {
            color: #2ecc71 !important;
            font-weight: 600;
        }
        .navbar-text {
            color: rgba(255,255,255,0.85) !important;
            font-size: 0.9rem;
        }
        
        /* 🎯 Hero */
        .hero {
            background: var(--navbar-bg);
            color: #fff;
            padding: 4rem 0 3rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 8s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }
        .hero-content { position: relative; z-index: 1; text-align: center; }
        .hero-icon { font-size: 3.5rem; margin-bottom: 1rem; display: block; }
        .hero h1 {
            font-weight: 800;
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        .hero .lead { font-size: 1.1rem; opacity: 0.95; margin-bottom: 0.5rem; }
        .hero .cta { opacity: 0.9; font-size: 0.95rem; }
        
        /* 🛍️ Products */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .section-title {
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .badge-count {
            background: var(--primary);
            color: #fff;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .product-card {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            overflow: hidden;
            height: 100%;
            background: var(--card-bg);
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }
        .product-image {
            height: 180px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        [data-theme="dark"] .product-image {
            background: linear-gradient(135deg, #2d2d44 0%, #1e1e32 100%);
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .product-card:hover .product-image img { transform: scale(1.05); }
        .product-image .placeholder { font-size: 3rem; opacity: 0.4; }
        .product-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; }
        .product-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: var(--text-primary);
            min-height: 2.4rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-desc {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 0.8rem;
            flex: 1;
            min-height: 2.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 0.8rem;
        }
        .btn-add {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border: none;
            color: #fff;
            border-radius: 25px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            width: 100%;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4);
            color: #fff;
        }
        .btn-add.added { background: linear-gradient(135deg, #27ae60, #219653) !important; }
        
        /* 🛒 Float Cart */
        .float-cart {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            color: #fff;
            padding: 0.9rem 1.5rem;
            border-radius: 50px;
            z-index: 1040;
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.35);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            animation: floatPulse 2.5s ease-in-out infinite;
        }
        @keyframes floatPulse {
            0%, 100% { box-shadow: 0 8px 25px rgba(46, 204, 113, 0.35); }
            50% { box-shadow: 0 12px 35px rgba(46, 204, 113, 0.55); }
        }
        .float-cart:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(46, 204, 113, 0.5);
        }
        .float-cart .badge {
            background: #fff;
            color: var(--accent);
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            font-size: 0.8rem;
        }
        
        /* 🧾 Modal */
        .modal-content {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            background: var(--card-bg);
        }
        .modal-header {
            background: var(--navbar-bg);
            color: #fff;
            border-radius: var(--radius) var(--radius) 0 0 !important;
            border: none;
            padding: 1rem 1.5rem;
        }
        .modal-title { font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .btn-close-white { filter: brightness(0) invert(1); opacity: 0.85; }
        .cart-list { max-height: 220px; overflow-y: auto; padding: 0.5rem 0; }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            gap: 0.8rem;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cart-item-meta { font-size: 0.8rem; color: var(--text-secondary); }
        .cart-item-actions { display: flex; align-items: center; gap: 0.4rem; }
        .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            background: var(--card-bg);
            color: var(--accent);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .qty-btn:hover { background: var(--accent); color: #fff; }
        .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-remove {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: #e74c3c;
            color: #fff;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 0.8rem;
        }
        .btn-remove:hover { background: #c0392b; transform: scale(1.1); }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-bottom: 0.4rem;
        }
        .form-control {
            border-radius: var(--radius-sm);
            border: 2px solid var(--border);
            padding: 0.6rem 0.9rem;
            font-size: 0.95rem;
            background: var(--card-bg);
            color: var(--text-primary);
            transition: var(--transition);
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.15);
            outline: none;
        }
        .form-control::placeholder { color: var(--text-secondary); opacity: 0.7; }
        .total-box {
            background: linear-gradient(135deg, var(--light), var(--border));
            border-radius: var(--radius-sm);
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border);
        }
        .total-label { font-size: 0.85rem; color: var(--text-secondary); display: block; }
        .total-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            margin-top: 0.2rem;
        }
        
        /* 🔔 Alerts */
        .alert {
            border: none;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem 1.2rem;
            font-size: 0.95rem;
        }
        .alert i { font-size: 1.2rem; flex-shrink: 0; }
        
        /* 🦶 Footer */
        .footer {
            background: var(--navbar-bg);
            color: #fff;
            padding: 2.5rem 0 2rem;
            margin-top: 4rem;
            text-align: center;
        }
        .footer-logo { font-size: 2.5rem; margin-bottom: 0.8rem; display: block; }
        .footer-title { font-weight: 700; font-size: 1.2rem; margin-bottom: 0.3rem; }
        .footer-text { opacity: 0.85; font-size: 0.9rem; margin-bottom: 0.3rem; }
        .footer-copyright { opacity: 0.7; font-size: 0.8rem; margin-top: 1rem; }
        
        /* 📞 قسم التواصل */
        .contact-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: var(--shadow);
        }
        .contact-card {
            background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
            border-radius: var(--radius-sm);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 2px solid var(--border);
        }
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }
        .contact-icon {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 1rem;
            display: block;
        }
        .contact-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .contact-value {
            font-size: 1rem;
            color: var(--text-secondary);
            margin: 0.3rem 0;
        }
        .contact-value a {
            color: var(--accent);
            text-decoration: none;
            transition: var(--transition);
        }
        .contact-value a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        /* 📱 Responsive */
        @media (max-width: 992px) {
            .navbar-nav { padding: 1rem 0; }
            .navbar-nav .nav-link { margin: 0.3rem 0; }
        }
        @media (max-width: 768px) {
            .hero { padding: 3rem 0 2rem; }
            .hero h1 { font-size: 1.6rem; }
            .section-title { font-size: 1.2rem; }
            .product-image { height: 150px; }
            .float-cart { bottom: 15px; left: 15px; padding: 0.8rem 1.2rem; font-size: 0.9rem; }
            .theme-switcher { top: 8px; left: 8px; }
            .contact-section { padding: 1.5rem; }
            .contact-card { padding: 1rem; }
        }
        @media (max-width: 576px) {
            .navbar-brand { font-size: 1.1rem; }
            .navbar-text { display: none; }
            .product-image { height: 130px; }
            .product-title { font-size: 0.95rem; }
            .product-price { font-size: 1.1rem; }
            .btn-add { padding: 0.5rem 1rem; font-size: 0.85rem; }
            .float-cart { padding: 0.7rem 1rem; font-size: 0.85rem; }
            .hero-icon { font-size: 2.5rem; }
            .section-header { flex-direction: column; align-items: flex-start; }
            .badge-count { align-self: flex-end; }
        }
        
        /* ♿ إمكانية الوصول */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body>

<!-- 🌙 زر تبديل الوضع الليلي/النهاري -->
<div class="theme-switcher" role="group" aria-label="تبديل السمة">
    <button class="theme-btn active" onclick="setTheme('light')" id="lightBtn" aria-pressed="true">
        <i class="bi bi-sun"></i> نهاري
    </button>
    <button class="theme-btn" onclick="setTheme('dark')" id="darkBtn" aria-pressed="false">
        <i class="bi bi-moon"></i> ليلي
    </button>
</div>

<!-- 🧭 شريط التنقل -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link active" href="#home"><i class="bi bi-house"></i> <?php echo $t['nav_home']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="#products"><i class="bi bi-grid"></i> <?php echo $t['nav_products']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="#contact"><i class="bi bi-telephone"></i> <?php echo $t['nav_contact']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="#about"><i class="bi bi-info-circle"></i> <?php echo $t['nav_about']; ?></a></li>
            </ul>
            <span class="navbar-text">
                <i class="bi bi-telephone-fill"></i> +218 924435387
            </span>
        </div>
    </div>
</nav>

<!-- 🎯 قسم الهيرو -->
<section class="hero" id="home">
    <div class="container hero-content">
        <span class="hero-icon">🏢</span>
        <h1 class="mb-3"><?php echo htmlspecialchars(COMPANY_NAME); ?></h1>
        <p class="lead mb-2"><?php echo htmlspecialchars($t['tagline']); ?></p>
        <p class="cta mb-0"><?php echo htmlspecialchars($t['cta']); ?></p>
    </div>
</section>

<!-- 📦 المحتوى الرئيسي -->
<div class="container">
    
    <!-- 🔔 رسائل النظام -->
    <?php if($result): ?>
        <div class="alert alert-<?php echo $result_type === 'success' ? 'success' : ($result_type === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $result_type === 'success' ? 'check-circle-fill' : ($result_type === 'warning' ? 'exclamation-triangle-fill' : 'x-circle-fill'); ?>"></i>
            <span><?php echo htmlspecialchars($result); ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 🛍️ قسم المنتجات -->
    <div class="section-header" id="products">
        <h2 class="section-title"><i class="bi bi-grid-3x3-gap-fill"></i> <?php echo htmlspecialchars($t['products']); ?></h2>
        <span class="badge-count"><?php echo count($products); ?> <?php echo $t['available']; ?></span>
    </div>

    <div class="row g-4">
        <?php if(empty($products)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted fs-5"><?php echo htmlspecialchars($t['no_products']); ?></p>
                <a href="admin_login.php" class="btn btn-primary mt-2"><i class="bi bi-lock"></i> <?php echo htmlspecialchars($t['login_admin']); ?></a>
            </div>
        <?php else: ?>
            <?php foreach($products as $p): 
            $pName = htmlspecialchars($p['name'], ENT_QUOTES);
            $pPrice = floatval($p['price']);
            $pId = intval($p['id']);
            ?>
            <div class="col-6 col-sm-4 col-lg-3">
                <article class="card product-card h-100">
                    <div class="product-image">
                        <?php if(!empty($p['image']) && file_exists($p['image'])): ?>
                            <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo $pName; ?>" loading="lazy" onerror="this.closest('.product-image').innerHTML='<span class=\'placeholder\'>📦</span>'">
                        <?php else: ?>
                            <span class="placeholder">📦</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <h3 class="product-title"><?php echo $pName; ?></h3>
                        <p class="product-desc"><?php echo htmlspecialchars($p['description']); ?></p>
                        <p class="product-price"><?php echo number_format($pPrice, 2); ?> <?php echo $t['currency_symbol']; ?></p>
                        <button class="btn-add" data-id="<?php echo $pId; ?>" data-price="<?php echo $pPrice; ?>" data-name="<?php echo $pName; ?>">
                            <i class="bi bi-cart-plus"></i><span class="btn-text"><?php echo htmlspecialchars($t['add_to_cart']); ?></span>
                        </button>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- 📞 قسم التواصل -->
    <section class="contact-section" id="contact">
        <h3 class="text-center mb-4"><i class="bi bi-telephone-fill" style="color: var(--accent);"></i> <?php echo $t['nav_contact']; ?></h3>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="contact-card">
                    <span class="contact-icon"><i class="bi bi-telephone"></i></span>
                    <h5 class="contact-title">اتصل بنا</h5>
                    <p class="contact-value"><a href="tel:0912129984">0912129984</a></p>
                    <p class="contact-value"><a href="tel:0922129984">0922129984</a></p>
                    <p class="contact-value"><a href="tel:0913750308">0913750308</a></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="contact-card">
                    <span class="contact-icon"><i class="bi bi-envelope"></i></span>
                    <h5 class="contact-title">البريد الإلكتروني</h5>
                    <p class="contact-value"><a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="contact-card">
                    <span class="contact-icon"><i class="bi bi-geo-alt"></i></span>
                    <h5 class="contact-title">العنوان</h5>
                    <p class="contact-value">ليبيا - طرابلس</p>
                    <p class="contact-value">الموقعة الكريمية</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ℹ️ قسم من نحن -->
    <section class="contact-section" id="about">
        <h3 class="text-center mb-4"><i class="bi bi-info-circle-fill" style="color: var(--accent);"></i> <?php echo $t['nav_about']; ?></h3>
        <div class="card p-4" style="background: var(--card-bg); border: 2px solid var(--border);">
            <p class="lead text-center" style="color: var(--text-primary);"><?php echo htmlspecialchars(COMPANY_NAME); ?></p>
            <p class="text-muted text-center" style="color: var(--text-secondary);">شركة متخصصة في استيراد المواد الغذائية عالية الجودة بأسعار منافسة. نحرص على توفير أفضل المنتجات لعملائنا الكرام مع ضمان الجودة والخدمة المتميزة.</p>
            <div class="text-center mt-3">
                <span class="badge bg-success fs-6">✅ جودة مضمونة</span>
                <span class="badge bg-primary fs-6 ms-2">✅ أسعار منافسة</span>
                <span class="badge bg-info fs-6 ms-2">✅ توصيل سريع</span>
            </div>
        </div>
    </section>
    
</div>

<!-- 🧾 نافذة إتمام الطلب -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="orderForm" novalidate>
                <input type="hidden" name="cart" id="cart-json">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bag-check-fill"></i> <?php echo htmlspecialchars($t['checkout']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="card bg-light mb-3" style="background: var(--card-bg); border: 1px solid var(--border);">
                        <div class="card-header fw-bold d-flex justify-content-between" style="background: var(--light); color: var(--text-primary);">
                            <span><i class="bi bi-cart3"></i> <?php echo htmlspecialchars($t['products']); ?></span>
                            <span class="badge bg-primary" id="cart-count">0</span>
                        </div>
                        <div id="cart-list" class="cart-list"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($t['name']); ?> *</label>
                            <input type="text" name="cname" class="form-control" required minlength="2" maxlength="100" placeholder="أحمد محمد">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($t['phone']); ?> *</label>
                            <input type="tel" name="cphone" class="form-control" required pattern="^[\d+\-\s()]{7,20}$" placeholder="091xxxxxxx">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><i class="bi bi-envelope-fill"></i> <?php echo htmlspecialchars($t['email']); ?></label>
                            <input type="email" name="cemail" class="form-control" placeholder="email@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($t['address']); ?></label>
                            <textarea name="caddress" class="form-control" rows="2" maxlength="300" placeholder="المدينة، الحي، الشارع..."></textarea>
                        </div>
                    </div>
                    <div class="total-box mt-3">
                        <span class="total-label"><?php echo htmlspecialchars($t['total']); ?></span>
                        <strong class="total-value"><span id="modal-total">0.00</span> <?php echo $t['currency_symbol']; ?></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo htmlspecialchars($t['cancel']); ?></button>
                    <button type="submit" class="btn btn-success btn-sm" id="submitBtn"><i class="bi bi-send-fill"></i> <?php echo htmlspecialchars($t['submit']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 🛒 زر السلة العائم -->
<button class="float-cart" data-bs-toggle="modal" data-bs-target="#checkoutModal" id="floatCartBtn">
    <i class="bi bi-cart3-fill"></i>
    <span><?php echo htmlspecialchars($t['cart']); ?>: <strong id="cart-total">0.00</strong> <?php echo $t['currency_symbol']; ?></span>
    <span class="badge rounded-pill" id="cart-badge">0</span>
</button>

<!-- 🦶 التذييل -->
<footer class="footer">
    <div class="container">
        <span class="footer-logo">🏢</span>
        <h4 class="footer-title"><?php echo htmlspecialchars(COMPANY_NAME); ?></h4>
        <p class="footer-text">استيراد المواد الغذائية</p>
        <p class="footer-text">📍 ليبيا - طرابلس - الموقع الكريمية</p>
        <p class="footer-copyright">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($t['footer_text']); ?></p>
        <div class="mt-3">
            <a href="admin_login.php" class="text-white-50 small text-decoration-none"><i class="bi bi-lock"></i> دخول الأدمن</a>
        </div>
    </div>
</footer>

<!-- 📦 المكتبات -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- 🧠 كود الجافاسكريبت -->
<script>
// 🌙 تبديل الوضع الليلي/النهاري
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    const lightBtn = document.getElementById('lightBtn');
    const darkBtn = document.getElementById('darkBtn');
    
    if (theme === 'dark') {
        lightBtn.classList.remove('active');
        darkBtn.classList.add('active');
        lightBtn.setAttribute('aria-pressed', 'false');
        darkBtn.setAttribute('aria-pressed', 'true');
    } else {
        lightBtn.classList.add('active');
        darkBtn.classList.remove('active');
        lightBtn.setAttribute('aria-pressed', 'true');
        darkBtn.setAttribute('aria-pressed', 'false');
    }
}

// تحميل السمة المحفوظة
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
});

// 🛒 إدارة السلة
let cart = [];
try { cart = JSON.parse(localStorage.getItem('cart') || '[]'); } catch(e) { cart = []; }

function addToCart(id, price, name) {
    const existing = cart.find(i => i.id === id);
    if (existing) { existing.qty = Math.min(99, existing.qty + 1); }
    else { cart.push({ id: id, name: name, price: parseFloat(price), qty: 1 }); }
    saveCart(); renderCart();
    const btn = event.currentTarget;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> <span class="btn-text">✓ مضاف</span>';
    btn.classList.add('added');
    setTimeout(() => { btn.innerHTML = original; btn.classList.remove('added'); }, 1000);
}

function saveCart() { localStorage.setItem('cart', JSON.stringify(cart)); }

function renderCart() {
    const list = document.getElementById('cart-list');
    const totalEl = document.getElementById('cart-total');
    const modalTotal = document.getElementById('modal-total');
    const countEl = document.getElementById('cart-count');
    const badge = document.getElementById('cart-badge');
    const cartJson = document.getElementById('cart-json');
    const floatBtn = document.getElementById('floatCartBtn');
    let total = 0, count = 0;
    const currency = 'د.ل';
    
    if (cart.length === 0) {
        list.innerHTML = '<p class="text-muted text-center py-3 mb-0">السلة فارغة 🛒</p>';
        if (floatBtn) floatBtn.style.display = 'none';
    } else {
        list.innerHTML = cart.map((item, idx) => {
            const itemTotal = item.price * item.qty;
            total += itemTotal; count += item.qty;
            const safeName = item.name.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            return '<div class="cart-item"><div class="cart-item-info"><div class="cart-item-name" title="' + safeName + '">' + safeName + '</div><div class="cart-item-meta">' + item.price.toFixed(2) + ' ' + currency + ' × ' + item.qty + '</div></div><div class="cart-item-actions"><button class="qty-btn" onclick="changeQty(' + idx + ', -1)" ' + (item.qty <= 1 ? 'disabled' : '') + '>−</button><span class="fw-bold">' + item.qty + '</span><button class="qty-btn" onclick="changeQty(' + idx + ', 1)" ' + (item.qty >= 99 ? 'disabled' : '') + '>+</button><button class="btn-remove" onclick="removeItem(' + idx + ')"><i class="bi bi-trash"></i></button></div></div>';
        }).join('');
        if (floatBtn) floatBtn.style.display = 'flex';
    }
    totalEl.textContent = total.toFixed(2);
    modalTotal.textContent = total.toFixed(2);
    countEl.textContent = count;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline-block' : 'none';
    cartJson.value = JSON.stringify(cart);
}

function changeQty(index, delta) {
    if (!cart[index]) return;
    cart[index].qty = Math.max(1, Math.min(99, cart[index].qty + delta));
    if (cart[index].qty <= 0) cart.splice(index, 1);
    saveCart(); renderCart();
}

function removeItem(index) {
    if (confirm('هل تريد إزالة هذا المنتج؟')) {
        cart.splice(index, 1);
        saveCart(); renderCart();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    renderCart();
    
    document.querySelectorAll('.btn-add').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            addToCart(parseInt(this.dataset.id), parseFloat(this.dataset.price), this.dataset.name);
        });
    });
    
    const checkoutModal = document.getElementById('checkoutModal');
    if (checkoutModal) checkoutModal.addEventListener('show.bs.modal', renderCart);
    
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', function(e) {
            if (!orderForm.checkValidity()) {
                e.preventDefault(); e.stopPropagation(); orderForm.classList.add('was-validated'); return false;
            }
            if (cart.length === 0) {
                e.preventDefault(); alert('⚠️ أضف منتجات للسلة أولاً!'); return false;
            }
            const btn = document.getElementById('submitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الإرسال...';
                setTimeout(() => { if (btn.disabled) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i> إرسال الطلب'; } }, 10000);
            }
        });
    }
    
    const floatBtn = document.getElementById('floatCartBtn');
    if (floatBtn && cart.length === 0) floatBtn.style.display = 'none';
    
    // 📜 تمرير سلس للروابط الداخلية
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

document.getElementById('cart-json')?.addEventListener('input', function(e) {
    try {
        const parsed = JSON.parse(e.target.value);
        if (!Array.isArray(parsed)) { e.target.value = '[]'; cart = []; renderCart(); }
    } catch { e.target.value = '[]'; cart = []; renderCart(); }
});
</script>
</body>
</html>