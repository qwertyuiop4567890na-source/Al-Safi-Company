<?php
session_start();
session_unset();
session_destroy();

// حذف كوكي الجلسة إن وُجد
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header("Location: admin_login.php");
exit;
?>