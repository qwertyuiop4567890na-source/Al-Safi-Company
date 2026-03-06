<?php
/**
 * 🔐 Admin Login - صفحة تسجيل الدخول للوحة التحكم
 * الإصدار: 1.1.0 | مع حماية CSRF ومتوافقة مع جميع الشاشات
 */

declare(strict_types=1);
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/config.php';

// 🛡️ توليد رمز الحماية CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

// 🔐 معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من رمز الحماية
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = "❌ خطأ أمني: طلب غير صالح";
        error_log("CSRF failed in login - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    else {
        $password = $_POST['password'] ?? '';
        
        // 🚦 نظام منع التكرار البسيط
        $login_attempts = $_SESSION['login_attempts'] ?? [];
        $now = time();
        $login_attempts = array_filter($login_attempts, fn($t) => $t > $now - 300);
        
        if (count($login_attempts) >= 5) {
            $error = "⏳ كثرت المحاولات، يرجى الانتظار 5 دقائق";
            error_log("Rate limit exceeded - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
        elseif ($password === ADMIN_PASSWORD) {
            // ✅ نجاح الدخول
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_time'] = $now;
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['login_attempts'] = []; // تصفير المحاولات
            
            // 🔄 إعادة التوجيه الآمن
            header("Location: admin.php");
            exit;
        } else {
            // ❌ فشل الدخول
            $login_attempts[] = $now;
            $_SESSION['login_attempts'] = $login_attempts;
            $error = "❌ كلمة المرور غير صحيحة";
            error_log("Failed login attempt - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>تسجيل الدخول - لوحة التحكم</title>
    
    <!-- 🎨 المكتبات - روابط نظيفة بدون مسافات -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3c72; --secondary: #2a5298; --accent: #2ecc71;
            --shadow: 0 20px 60px rgba(0,0,0,0.3); --radius: 20px;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: white;
            border-radius: var(--radius);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow);
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            font-size: 3.5rem;
            text-align: center;
            margin-bottom: 0.5rem;
            display: block;
        }
        .card-title {
            text-align: center;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.15);
        }
        .btn-login {
            background: linear-gradient(135deg, var(--accent), #27ae60);
            border: none;
            padding: 0.85rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .alert {
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: gap 0.2s;
        }
        .back-link a:hover {
            gap: 0.5rem;
        }
        
        /* 📱 Responsive */
        @media (max-width: 480px) {
            .login-card { padding: 2rem 1.5rem; }
            .logo { font-size: 3rem; }
            .card-title { font-size: 1.3rem; }
        }
        
        /* 🌙 Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-card { background: #1e1e32; }
            .form-label { color: #eee; }
            .form-control { background: #2a2a4a; border-color: #444; color: #eee; }
            .card-title { color: #fff; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <span class="logo">🔐</span>
    <h2 class="card-title">لوحة التحكم</h2>
    
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="loginForm">
        <!-- 🛡️ حقل الحماية CSRF -->
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="mb-4">
            <label class="form-label" for="password">كلمة المرور</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control form-control-lg" 
                       required placeholder="أدخل كلمة المرور" autocomplete="current-password" minlength="6">
            </div>
        </div>
        
        <button type="submit" class="btn btn-login text-white btn-lg" id="submitBtn">
            <i class="bi bi-box-arrow-in-right"></i> تسجيل الدخول
        </button>
    </form>
    
    <div class="back-link">
        <a href="../index.php">
            <i class="bi bi-arrow-right"></i> العودة للمتجر
        </a>
    </div>
</div>

<!-- 📦 المكتبات -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- 🧠 كود الجافاسكريبت -->
<script>

// ⌨️ منع إرسال النموذج مرتين
document.getElementById('loginForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري التحقق...';
    }
});

// ⚡ دعم زر Enter في حقل كلمة المرور
document.getElementById('password')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.form.requestSubmit();
    }
});
</script>

</body>
</html>