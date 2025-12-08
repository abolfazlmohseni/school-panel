<?php
session_start();
require_once '../config.php';

// بارگذاری PhpSpreadsheet
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $class_id = intval($_POST['class_id']);
    $file = $_FILES['excel_file'];

    // بررسی وجود فایل
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "خطا در آپلود فایل. کد خطا: " . $file['error'];
        header("Location: students_add.php");
        exit;
    }

    // بررسی پسوند فایل
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['xls', 'xlsx', 'csv', 'ods'];

    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['error'] = "لطفاً فقط فایل‌های Excel (xls, xlsx) یا CSV آپلود کنید.";
        header("Location: students_add.php");
        exit;
    }

    // افزایش محدودیت‌ها
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 300);

    $inserted = 0;
    $skipped = 0;
    $errors = [];

    try {
        // بارگذاری فایل اکسل
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();

        // دریافت بالاترین ردیف و ستون
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // روش بهتر: استفاده از toArray() برای خواندن کل داده‌ها
        $rows = $worksheet->toArray();

        // شروع از ردیف دوم (ردیف اول هدر است)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $row_number = $i + 1; // شماره ردیف واقعی

            // اگر ردیف خالی باشد، ردش کن
            $all_empty = true;
            foreach ($row as $cell) {
                if (!empty(trim($cell ?? ''))) {
                    $all_empty = false;
                    break;
                }
            }

            if ($all_empty) {
                continue;
            }

            // استخراج داده‌ها - فرض می‌کنیم ستون‌ها به ترتیب هستند
            $first_name = isset($row[0]) ? trim($row[0]) : '';
            $last_name = isset($row[1]) ? trim($row[1]) : '';
            $national_code = isset($row[2]) ? trim($row[2]) : '';
            $phone = isset($row[3]) ? trim($row[3]) : '';

            // پاکسازی داده‌ها
            $first_name = $conn->real_escape_string($first_name);
            $last_name = $conn->real_escape_string($last_name);
            $national_code = $conn->real_escape_string($national_code);
            $phone = $conn->real_escape_string($phone);

            // بررسی فیلدهای ضروری
            if (empty($first_name) || empty($last_name) || empty($national_code)) {
                $skipped++;
                $errors[] = "ردیف $row_number: فیلدهای ضروری (نام، نام خانوادگی یا کد ملی) خالی هستند";
                continue;
            }

            // بررسی اعتبار کد ملی
            if (!isValidNationalCode($national_code)) {
                $skipped++;
                $errors[] = "ردیف $row_number: کد ملی '$national_code' نامعتبر است";
                continue;
            }

            // بررسی تکراری بودن کد ملی
            $check = $conn->query("SELECT id FROM students WHERE national_code='$national_code'");
            if ($check && $check->num_rows > 0) {
                $skipped++;
                $errors[] = "ردیف $row_number: کد ملی '$national_code' تکراری است";
                continue;
            }

            // درج در دیتابیس
            $sql = "INSERT INTO students (first_name, last_name, national_code, phone, class_id) 
                    VALUES ('$first_name', '$last_name', '$national_code', '$phone', $class_id)";

            if ($conn->query($sql)) {
                $inserted++;
            } else {
                $skipped++;
                $errors[] = "ردیف $row_number: خطا در ثبت - " . $conn->error;
            }
        }
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        $_SESSION['error'] = "خطا در خواندن فایل اکسل: " . $e->getMessage();
        header("Location: students_add.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "خطا در پردازش فایل: " . $e->getMessage();
        header("Location: students_add.php");
        exit;
    }

    // ذخیره نتایج در session
    if ($inserted > 0) {
        $_SESSION['msg'] = "$inserted دانش‌آموز با موفقیت اضافه شد.";
    }

    if ($skipped > 0) {
        $_SESSION['warning'] = "$skipped دانش‌آموز اضافه نشد.";
        $_SESSION['bulk_errors'] = array_slice($errors, 0, 20);
    }

    header("Location: students.php");
    exit;
} else {
    header("Location: students_add.php");
    exit;
}

/**
 * تابع بررسی اعتبار کد ملی
 */
function isValidNationalCode($national_code)
{
    // حذف فاصله و کاراکترهای اضافی
    $national_code = preg_replace('/[^0-9]/', '', $national_code);

    // بررسی طول
    if (strlen($national_code) != 10) {
        return false;
    }

    // بررسی اینکه همه ارقام یکسان نباشند
    if (preg_match('/^(\d)\1{9}$/', $national_code)) {
        return false;
    }

    // الگوریتم اعتبارسنجی کد ملی
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int)$national_code[$i] * (10 - $i);
    }

    $remainder = $sum % 11;
    $control_digit = (int)$national_code[9];

    if ($remainder < 2) {
        return $control_digit == $remainder;
    } else {
        return $control_digit == (11 - $remainder);
    }
}
