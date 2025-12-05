<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: students.php');
    exit;
}

$id = intval($_GET['id']);
$conn->query("DELETE FROM students WHERE id = $id");
header('Location: students.php');
exit;
