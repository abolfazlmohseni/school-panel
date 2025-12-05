<?php
$conn = new mysqli("localhost", "root", "", "attendance_system");

if ($conn->connect_error) {
    die("خطا در اتصال: " . $conn->connect_error);
}
