<?php
session_start();
require_once 'config.php';

// پاک کردن ارورهای قبلی
unset($_SESSION['login_error']);

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    $_SESSION['login_error'] = "ورودی نامعتبر.";
    header("Location: login.php");
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

        // پاک کردن هرگونه ارور احتمالی
        unset($_SESSION['login_error']);

        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
            exit;
        } else {
            header("Location: teacher/dashboard.php");
            exit;
        }
    } else {
        $_SESSION['login_error'] = "رمز عبور اشتباه است.";
        header("Location: login.php");
        exit;
    }
} else {
    $_SESSION['login_error'] = "کاربر یافت نشد.";
    header("Location: login.php");
    exit;
}

$stmt->close();
$conn->close();
