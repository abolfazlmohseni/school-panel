<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: teachers.php");
    exit;
}

$id = intval($_POST['id']);
$first_name = $conn->real_escape_string($_POST['first_name']);
$last_name = $conn->real_escape_string($_POST['last_name']);
$username = $conn->real_escape_string($_POST['username']);
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

// بررسی یکتایی نام کاربری (به جز خود کاربر)
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
$stmt->bind_param('si', $username, $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $_SESSION['flash'] = "نام کاربری قبلا توسط کاربر دیگری استفاده شده است.";
    $stmt->close();
    header("Location: teacher_edit.php?id=" . $id);
    exit;
}
$stmt->close();

// اگر رمز جدید وارد شده، آن را هش کرده و آپدیت کن
if (!empty($new_password)) {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, username=?, password=? WHERE id=? AND role='teacher'");
    $stmt->bind_param("ssssi", $first_name, $last_name, $username, $hashed_password, $id);
} else {
    // فقط اطلاعات اصلی را آپدیت کن
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, username=? WHERE id=? AND role='teacher'");
    $stmt->bind_param("sssi", $first_name, $last_name, $username, $id);
}

if ($stmt->execute()) {
    $_SESSION['message'] = "اطلاعات دبیر با موفقیت به‌روزرسانی شد.";
    $stmt->close();
    header("Location: teachers.php");
    exit;
} else {
    $error = $stmt->error;
    $stmt->close();
    die("خطا در به‌روزرسانی دبیر: " . $error);
}
