<?php
session_start();

// تخریب تمام session
session_unset();
session_destroy();

// حذف کوکی session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// انتقال به صفحه لاگین
header("Location: login.php");
exit;
