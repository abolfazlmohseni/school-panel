<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: students.php');
    exit;
}

// دریافت اطلاعات فرم
$first_name = $conn->real_escape_string($_POST['first_name']);
$last_name = $conn->real_escape_string($_POST['last_name']);
$national_code = $conn->real_escape_string($_POST['national_code']);
$phone = $conn->real_escape_string($_POST['phone']);
$class_id = intval($_POST['class_id']);

// بررسی تکراری بودن کد ملی
$check_sql = "SELECT id FROM students WHERE national_code='$national_code' LIMIT 1";
$result = $conn->query($check_sql);
if ($result->num_rows > 0) {
    die("این کد ملی قبلاً ثبت شده است.");
}


// درج در جدول students
$sql = "INSERT INTO students (first_name, last_name, national_code, phone, class_id)
        VALUES ('$first_name', '$last_name', '$national_code', '$phone', $class_id)";

if ($conn->query($sql)) {
    header('Location: students.php');
    exit;
} else {
    die("خطا در ذخیره دانش‌آموز: " . $conn->error);
}
