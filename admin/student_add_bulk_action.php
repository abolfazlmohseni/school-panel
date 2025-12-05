<?php
session_start();
require_once '../config.php';

// بارگذاری کلاس‌ها به صورت دستی
require '../libs/PhpSpreadsheet/vendor/autoload.php'; // مسیر کتابخانه که دانلود کردی

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $class_id = intval($_POST['class_id']);
    $file = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $inserted = 0;
        $skipped = 0;

        // فرض: ردیف اول هدر هست
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $first_name = $conn->real_escape_string($row[0]);
            $last_name = $conn->real_escape_string($row[1]);
            $national_code = $conn->real_escape_string($row[2]);
            $phone = $conn->real_escape_string($row[3]);

            // بررسی یونیک بودن کد ملی
            $check = $conn->query("SELECT id FROM students WHERE national_code='$national_code'");
            if ($check->num_rows > 0) {
                $skipped++;
                continue;
            }

            $conn->query("INSERT INTO students (first_name, last_name, national_code, phone, class_id) 
                         VALUES ('$first_name', '$last_name', '$national_code', '$phone', $class_id)");
            $inserted++;
        }

        $_SESSION['message'] = "$inserted دانش‌آموز اضافه شد، $skipped رد شد (کد ملی تکراری).";
        header("Location: students.php");
        exit;
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        die("خطا در خواندن فایل اکسل: " . $e->getMessage());
    }
} else {
    header("Location: students.php");
    exit;
}
