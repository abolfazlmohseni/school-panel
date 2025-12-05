<?php
session_start();
require_once 'config.php'; // فایل اتصال به دیتابیس

$username = $_POST['username'];
$password = $_POST['password'];

// هش ساده (فقط برای تست)
$password_hashed = md5($password);

$sql = "SELECT * FROM users WHERE username='$username' AND password='$password_hashed' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] === 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    } else {
        header("Location: teacher/dashboard.php");
        exit;
    }
} else {
    echo "نام کاربری یا رمز عبور اشتباه است.";
}
