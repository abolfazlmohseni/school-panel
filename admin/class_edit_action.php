<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: classes.php');
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$class_name = trim($_POST['class_name']);

if ($id <= 0 || empty($class_name)) {
    header('Location: classes.php');
    exit;
}

$stmt = $conn->prepare("UPDATE classes SET name = ? WHERE id = ?");
$stmt->bind_param("si", $class_name, $id);

if ($stmt->execute()) {
    header("Location: classes.php?success=2"); // کد موفقیت برای ویرایش
} else {
    echo "خطا در بروزرسانی کلاس: " . $conn->error;
}
