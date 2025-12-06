<?php
// config.php
$host = 'localhost';
$username = 'root';  // تغییر به نام کاربری دیتابیس شما
$password = '';      // تغییر به رمز دیتابیس شما
$database = 'attendance_system';  // تغییر به نام دیتابیس شما

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// تنظیمات UTF-8
$conn->set_charset("utf8mb4");

// تنظیمات تاریخ فارسی (اگر نیاز دارید)
date_default_timezone_set('Asia/Tehran');
