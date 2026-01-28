<?php
require_once '../config.php';

// فقط درخواست‌های POST را قبول می‌کنیم
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// دریافت داده‌ها
$data = getJsonInput();
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

// اعتبارسنجی ورودی‌ها
if (empty($username) || empty($password)) {
    jsonError('نام کاربری و رمز عبور الزامی است');
}

// جستجوی کاربر
$stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    jsonError('کاربر یافت نشد');
}

$user = $result->fetch_assoc();

// بررسی رمز عبور
if (!password_verify($password, $user['password'])) {
    jsonError('رمز عبور اشتباه است');
}

// تولید توکن (نسخه ساده - می‌توانید JWT اضافه کنید)
$token = bin2hex(random_bytes(32));
$token_expiry = date('Y-m-d H:i:s', strtotime('+1 day'));

// ذخیره توکن در دیتابیس
$update_stmt = $conn->prepare("UPDATE users SET api_token = ?, token_expiry = ? WHERE id = ?");
$update_stmt->bind_param("ssi", $token, $token_expiry, $user['id']);
$update_stmt->execute();

// حذف رمز عبور از پاسخ
unset($user['password']);

// پاسخ موفقیت
jsonSuccess([
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'full_name' => $user['first_name'] . ' ' . $user['last_name']
    ],
    'expires_at' => $token_expiry
]);
?>