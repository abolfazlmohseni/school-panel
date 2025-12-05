<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: programs.php');
    exit;
}

// گرفتن اطلاعات از فرم
$class_id = intval($_POST['class_id']);
$teacher_id = intval($_POST['teacher_id']);
$day_of_week = $conn->real_escape_string($_POST['day_of_week']);
$schedule = $conn->real_escape_string($_POST['schedule']);

// اضافه کردن برنامه به جدول programs
$sql = "INSERT INTO programs (class_id, teacher_id, day_of_week, schedule) 
        VALUES ($class_id, $teacher_id, '$day_of_week', '$schedule')";

if ($conn->query($sql)) {
    header('Location: programs.php');
    exit;
} else {
    die("خطا در ذخیره برنامه: " . $conn->error);
}
