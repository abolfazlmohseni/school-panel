<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: programs.php');
    exit;
}

$id = intval($_GET['id']);

$sql = "DELETE FROM programs WHERE id=$id";

if ($conn->query($sql)) {
    $_SESSION['message'] = "برنامه با موفقیت حذف شد.";
    header('Location: programs.php');
    exit;
} else {
    die("خطا در حذف برنامه: " . $conn->error);
}
