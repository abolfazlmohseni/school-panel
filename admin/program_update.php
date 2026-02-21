<?php
session_start();
require_once '../config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: programs.php');
    exit;
}

$id = intval($_POST['id']);
$class_id = intval($_POST['class_id']);
$teacher_id = intval($_POST['teacher_id']);
$day_of_week = $conn->real_escape_string($_POST['day_of_week']);
$schedule = $conn->real_escape_string($_POST['schedule']);
$class_name_text = isset($_POST['class_name_text']) ? $conn->real_escape_string($_POST['class_name_text']) : '';

$allowed_days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
if (!in_array($day_of_week, $allowed_days)) {
    die("مقدار روز هفته معتبر نیست.");
}

$sql = "UPDATE programs SET 
        class_id=$class_id, 
        teacher_id=$teacher_id, 
        day_of_week='$day_of_week', 
        schedule='$schedule',
        class_name_text='$class_name_text'
        WHERE id=$id";

if ($conn->query($sql)) {
    $_SESSION['message'] = "برنامه با موفقیت به‌روزرسانی شد.";
    header('Location: programs.php');
    exit;
} else {
    die("خطا در به‌روزرسانی برنامه: " . $conn->error);
}
?>