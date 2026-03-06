<?php
// debug.php - اختبار مباشر لـ PHPMailer
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<pre style='background:#1e1e1e;color:#0f0;padding:20px;font-family:monospace;font-size:12px;direction:ltr;text-align:left'>";
echo "🔧 Testing PHPMailer...\n";

try {
    $mail = new PHPMailer(true);
    
    // إعدادات الخادم
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'qwertyuiop4567890.n.a@gmail.com';
    $mail->Password = 'ilkt grih cnmu nkrj'; // 🔑 كلمة المرور
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    
    // 🔍 تفعيل سجل الأخطاء المفصل
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "[$level] $str\n";
    };
    
    // المرسل والمستلم
    $mail->setFrom('qwertyuiop4567890.n.a@gmail.com', 'Test Script');
    $mail->addAddress('qwertyuiop4567890.n.a@gmail.com');
    $mail->addReplyTo('test@example.com', 'Test Customer');
    
    // المحتوى
    $mail->isHTML(true);
    $mail->Subject = '✅ تجربة إرسال - ' . date('Y-m-d H:i:s');
    $mail->Body = '<h2 style="color:#2ecc71">✅ إذا وصلك هذا، فالإعدادات صحيحة!</h2>
    <p>وقت الإرسال: ' . date('Y-m-d H:i:s') . '</p>
    <table border="1" cellpadding="10" style="border-collapse:collapse">
        <tr><th>المنتج</th><th>الكمية</th><th>السعر</th></tr>
        <tr><td>أرز بسمتي</td><td>2</td><td>55.00 ريال</td></tr>
        <tr><td>زيت ذرة</td><td>1</td><td>28.50 ريال</td></tr>
    </table>
    <p><strong>الإجمالي: 138.50 ريال</strong></p>';
    $mail->AltBody = 'تم الإرسال بنجاح! الإجمالي: 138.50 ريال';
    
    echo "\n📤 Sending email...\n";
    $mail->send();
    echo "\n✅ SUCCESS! Email sent.\n";
    
} catch (Exception $e) {
    echo "\n❌ FAILED: " . $e->getMessage() . "\n";
    echo "SMTP Error: " . $mail->ErrorInfo . "\n";
}
echo "</pre>";
?>