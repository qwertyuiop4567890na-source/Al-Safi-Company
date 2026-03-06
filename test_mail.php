<?php
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h3>🔧 اختبار إرسال إيميل</h3>";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_EMAIL;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    
    $mail->setFrom(SMTP_EMAIL, 'Test');
    $mail->addAddress(SMTP_EMAIL); // أرسل لنفسك للاختبار
    
    $mail->isHTML(true);
    $mail->Subject = '✅ اختبار نجاح الإرسال';
    $mail->Body = '<h1>نجح الإرسال!</h1><p>إذا ترى هذه الرسالة، فالإعدادات صحيحة.</p>';
    $mail->AltBody = 'نجح الإرسال!';
    
    $mail->send();
    echo "<p style='color:green; font-size:1.2rem'>✅ تم إرسال الإيميل بنجاح!</p>";
    
} catch(Exception $e) {
    echo "<p style='color:red'>❌ فشل: " . $mail->ErrorInfo . "</p>";
}
?>