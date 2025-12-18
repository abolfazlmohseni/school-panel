<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: classes.php');
    exit;
}

// ابتدا بررسی می‌کنیم که آیا دانش‌آموزی در این کلاس وجود دارد یا نه
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE class_id = ?");
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$row = $check_result->fetch_assoc();

if ($row['count'] > 0) {
    // اگر دانش‌آموزی در کلاس وجود دارد، حذف نمی‌کنیم
    header("Location: classes.php?error=1");
    exit;
}

// در صورت نبود دانش‌آموز، کلاس حذف می‌شود
$stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: classes.php?success=3"); // کد موفقیت برای حذف
} else {
    echo "خطا در حذف کلاس: " . $conn->error;
}
