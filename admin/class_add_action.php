<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$class_name = $_POST['class_name'];

$stmt = $conn->prepare("INSERT INTO classes (name) VALUES (?)");
$stmt->bind_param("s", $class_name);

if ($stmt->execute()) {
    header("Location: classes.php?success=1");
} else {
    echo "خطا در ذخیره کلاس: " . $conn->error;
}
