<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /attendance-system/login.php");
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

$stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, username=? WHERE id=? AND role='teacher'");
$stmt->bind_param("sssi", $first_name, $last_name, $username, $id);

if ($stmt->execute()) {
    $_SESSION['message'] = "اطلاعات دبیر با موفقیت به‌روزرسانی شد.";
    $stmt->close();
    header("Location: teachers.php");
    exit;
} else {
    $stmt->close();
    die("خطا در به‌روزرسانی دبیر: " . $conn->error);
}
