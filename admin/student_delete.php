<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['msg'] = "شناسه دانش‌آموز مشخص نشده است.";
    header('Location: students.php');
    exit;
}

$id = intval($_GET['id']);

// بررسی وجود دانش‌آموز قبل از حذف
$check_sql = "SELECT id, first_name, last_name FROM students WHERE id = $id LIMIT 1";
$check_result = $conn->query($check_sql);

if (!$check_result) {
    $_SESSION['error'] = "خطا در بررسی اطلاعات: " . $conn->error;
    header('Location: students.php');
    exit;
}

if ($check_result->num_rows === 0) {
    $_SESSION['error'] = "دانش‌آموز مورد نظر یافت نشد.";
    header('Location: students.php');
    exit;
}

$student = $check_result->fetch_assoc();
$student_name = $student['first_name'] . ' ' . $student['last_name'];

// شروع تراکنش برای اطمینان از یکپارچگی داده‌ها
$conn->begin_transaction();

try {
    // 1. ابتدا تمام رکوردهای حضور و غیاب این دانش‌آموز را حذف می‌کنیم
    $delete_attendance_sql = "DELETE FROM attendance WHERE student_id = $id";
    if (!$conn->query($delete_attendance_sql)) {
        throw new Exception("خطا در حذف سوابق حضور و غیاب: " . $conn->error);
    }

    $attendance_deleted = $conn->affected_rows;

    // 2. سپس خود دانش‌آموز را حذف می‌کنیم
    $delete_student_sql = "DELETE FROM students WHERE id = $id";
    if (!$conn->query($delete_student_sql)) {
        throw new Exception("خطا در حذف دانش‌آموز: " . $conn->error);
    }

    // تایید تراکنش
    $conn->commit();

    $_SESSION['msg'] = "دانش‌آموز '$student_name' با موفقیت حذف شد. ($attendance_deleted رکورد حضور و غیاب نیز حذف شد)";
} catch (Exception $e) {
    // برگشت تراکنش در صورت خطا
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header('Location: students.php');
exit;
