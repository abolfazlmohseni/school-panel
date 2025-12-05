<?php
session_start();
require_once '../config.php';

// فقط مدیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /attendance-system/login.php");
    exit;
}

// گرفتن داده‌ها و پاکسازی اولیه
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($first_name === '' || $last_name === '' || $username === '' || $password === '') {
    $_SESSION['flash'] = "همه فیلدها باید پر شوند.";
    header("Location: teacher_add.php");
    exit;
}

// بررسی تکراری نبودن username
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $_SESSION['flash'] = "نام کاربری قبلا استفاده شده است.";
    $stmt->close();
    header("Location: teacher_add.php");
    exit;
}
$stmt->close();

// هش پسورد امن
$hashed = password_hash($password, PASSWORD_DEFAULT);

// درج در دیتابیس
$insert = $conn->prepare("INSERT INTO users (username, password, role, first_name, last_name) VALUES (?, ?, 'teacher', ?, ?)");
$insert->bind_param('ssss', $username, $hashed, $first_name, $last_name);

if ($insert->execute()) {
    $_SESSION['flash'] = "دبیر با موفقیت اضافه شد.";
    $insert->close();
    header("Location: teachers.php");
    exit;
} else {
    $_SESSION['flash'] = "خطا در اضافه کردن دبیر.";
    $insert->close();
    header("Location: teacher_add.php");
    exit;
}
