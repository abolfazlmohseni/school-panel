<?php
$servername = "localhost";
$username = "root";
$password = ""; // اگر رمز داری بذار اینجا
$dbname = "attendance_system";

// اتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// بررسی اتصال
if ($conn->connect_error) {
    die("اتصال به دیتابیس موفق نبود: " . $conn->connect_error);
}
