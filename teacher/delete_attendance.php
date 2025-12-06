<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// چک ورود دبیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

// دریافت ID حضور و غیاب
if (!isset($_GET['id'])) {
    header("Location: attendance_history.php?error=شناسه مشخص نشده");
    exit;
}

$attendance_id = intval($_GET['id']);

// حذف رکورد
$stmt = $conn->prepare("DELETE FROM attendance WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $attendance_id, $teacher_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        header("Location: attendance_history.php?success=رکورد با موفقیت حذف شد");
    } else {
        header("Location: attendance_history.php?error=رکورد یافت نشد یا دسترسی ندارید");
    }
} else {
    header("Location: attendance_history.php?error=خطا در حذف رکورد");
}

$stmt->close();
$conn->close();
exit;
