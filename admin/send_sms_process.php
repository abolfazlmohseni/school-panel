<?php
// ابتدا session_start
session_start();

// فعال کردن خطاها
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("send_sms_process.php شروع شد - POST: " . print_r($_POST, true));
require_once '../config.php';

// چک session و نقش
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'لطفاً ابتدا وارد شوید';
    header("Location: ../login.php");
    exit();
}

// دریافت و اعتبارسنجی داده‌های فرم
$message = trim($_POST['message'] ?? '');

// اعتبارسنجی
if (empty($message)) {
    $_SESSION['sms_error'] = 'متن پیامک نمی‌تواند خالی باشد.';
    header("Location: send_sms.php");
    exit();
}

if (strlen($message) > 160) {
    $_SESSION['sms_error'] = 'طول پیام نباید بیشتر از 160 کاراکتر باشد.';
    header("Location: send_sms.php");
    exit();
}

// تاریخ امروز
$today = date('Y-m-d');

// ---------- دریافت غایبین امروز ----------
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.phone,
            c.name as class_name,
            c.id as class_id
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN programs p ON a.program_id = p.id
        JOIN classes c ON p.class_id = c.id
        WHERE a.attendance_date = ?
        AND a.status = 'غایب'
        AND s.phone IS NOT NULL
        AND s.phone != ''
        AND TRIM(s.phone) != ''
        ORDER BY c.name, s.last_name, s.first_name
    ");

    if (!$stmt) {
        throw new Exception("خطای آماده‌سازی کوئری: " . $conn->error);
    }

    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['sms_error'] = 'خطا در دریافت داده‌ها: ' . $e->getMessage();
    error_log($e->getMessage());
    header("Location: send_sms.php");
    exit();
}

// ---------- تابع تبدیل تاریخ ----------
function gregorian_to_jalali($gy, $gm, $gd)
{
    // ... همان تابع قبلی ...
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return array($jy, $jm, $jd);
}

// تاریخ شمسی
$today_parts = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_parts[0], $today_parts[1], $today_parts[2]);
$persian_date = $today_jalali[0] . '/' . sprintf('%02d', $today_jalali[1]) . '/' . sprintf('%02d', $today_jalali[2]);

// ---------- ارسال پیامک ----------
$success_count = 0;
$failed_count = 0;
$failed_numbers = [];
$failed_names = [];

// اگر گیرنده‌ای وجود ندارد
if (count($recipients) == 0) {
    $_SESSION['sms_result'] = [
        'success_count' => 0,
        'failed_count' => 0,
        'total_count' => 0,
        'failed_numbers' => [],
        'failed_names' => [],
        'message' => $message,
        'admin_name' => $_SESSION['full_name'] ?? 'مدیر',
        'date' => $persian_date
    ];
    header("Location: sms_result.php");
    exit();
}

// ارسال پیامک‌ها
foreach ($recipients as $recipient) {
    $phone = trim($recipient['phone']);

    // شخصی‌سازی پیام
    $personalized_message = $message;
    $personalized_message = str_replace('{name}', $recipient['first_name'] . ' ' . $recipient['last_name'], $personalized_message);
    $personalized_message = str_replace('{class}', $recipient['class_name'], $personalized_message);
    $personalized_message = str_replace('{date}', $persian_date, $personalized_message);

    // شبیه‌سازی ارسال (90% موفقیت)
    $random = rand(1, 100);

    if ($random <= 90) {
        $success_count++;
        $status = 'success';
        $api_response = 'پیامک با موفقیت ارسال شد (حالت تست)';
    } else {
        $failed_count++;
        $failed_numbers[] = $phone;
        $failed_names[] = $recipient['first_name'] . ' ' . $recipient['last_name'];
        $status = 'failed';
        $api_response = 'خطای شبیه‌سازی (حالت تست)';
    }

    // ذخیره در دیتابیس
    try {
        $stmt = $conn->prepare("
            INSERT INTO sms_logs 
            (admin_id, student_id, recipient_name, phone_number, message, status, api_response, class_id, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            error_log("خطای آماده‌سازی INSERT: " . $conn->error);
            continue; // ادامه به رکورد بعدی
        }

        $admin_id = $_SESSION['user_id'];
        $student_name = $recipient['first_name'] . ' ' . $recipient['last_name'];

        $stmt->bind_param(
            "iisssssi",
            $admin_id,
            $recipient['student_id'],
            $student_name,
            $phone,
            $personalized_message,
            $status,
            $api_response,
            $recipient['class_id']
        );

        if (!$stmt->execute()) {
            error_log("خطای اجرای INSERT: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("خطا در ذخیره لاگ: " . $e->getMessage());
    }
}

// ذخیره نتایج در session
$_SESSION['sms_result'] = [
    'success_count' => $success_count,
    'failed_count' => $failed_count,
    'total_count' => count($recipients),
    'failed_numbers' => $failed_numbers,
    'failed_names' => $failed_names,
    'message' => $message,
    'admin_name' => $_SESSION['full_name'] ?? 'مدیر',
    'date' => $persian_date,
    'recipient_count' => count($recipients)
];

// هدایت به صفحه نتیجه
header("Location: sms_result.php");
exit();
