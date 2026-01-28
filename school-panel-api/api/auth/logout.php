<?php
require_once '../config.php';
require_once '../middleware/auth.php';

// فقط درخواست‌های POST را قبول می‌کنیم
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// احراز هویت کاربر
$user = authenticate();

// پاک کردن توکن از دیتابیس
$stmt = $conn->prepare("UPDATE users SET api_token = NULL, token_expiry = NULL WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();

jsonSuccess(null, 'با موفقیت خارج شدید');
?>