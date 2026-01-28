<?php
require_once '../config.php';

function authenticate() {
    $headers = getallheaders();
    $token = null;

    // دریافت توکن از هدر Authorization
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }

    // اگر توکن وجود ندارد، خطا برگردان
    if (!$token) {
        jsonError('توکن احراز هویت ارسال نشده است', 401);
    }

    // بررسی توکن در دیتابیس
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.role, u.first_name, u.last_name, u.api_token, u.token_expiry 
        FROM users u 
        WHERE u.api_token = ? AND u.token_expiry > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        jsonError('توکن نامعتبر یا منقضی شده است', 401);
    }

    $user = $result->fetch_assoc();
    
    // ذخیره کاربر در متغیر سراسری برای استفاده در API
    global $current_user;
    $current_user = $user;

    return $user;
}

// تعریف متغیر سراسری برای کاربر فعلی
$current_user = null;
?>