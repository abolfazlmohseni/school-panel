<?php
session_start();
require_once 'config.php';

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    echo "ورودی نامعتبر.";
    exit;
}

$username = trim($_POST['username']);
$password = $_POST['password'];

// دریافت اطلاعات کامل کاربر از دیتابیس
$stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // بررسی رمز امن
    if (password_verify($password, $user['password'])) {

        // ذخیره اطلاعات کامل کاربر در سشن
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
            exit;
        } else {
            header("Location: teacher/dashboard.php");
            exit;
        }
    } else {
        echo "رمز عبور اشتباه است.";
    }
} else {
    echo "کاربر یافت نشد.";
}

$stmt->close();
$conn->close();
