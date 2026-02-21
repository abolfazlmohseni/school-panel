<?php
session_start();
require_once '../config.php';
require_once '../includes/jdf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'مدیر';
$last_name = $_SESSION['last_name'] ?? '';

// تابع جدید برای تمیز کردن تاریخ دریافتی
function clean_jalali_date($date)
{
    if (empty($date)) {
        return '';
    }

    $date = str_replace('%2F', '/', $date);
    $date = urldecode($date);
    $date = preg_replace('/[^0-9\/]/', '', $date);

    return $date;
}

// پردازش تاریخ‌ها
$error = '';

// دریافت مقادیر از GET و تمیز کردن آنها
$jalali_from_raw = isset($_GET['jalali_from']) ? $_GET['jalali_from'] : '';
$jalali_to_raw = isset($_GET['jalali_to']) ? $_GET['jalali_to'] : '';
$class_filter = isset($_GET['class_id']) ? $_GET['class_id'] : '';

// تمیز کردن تاریخ‌ها
$jalali_from = clean_jalali_date($jalali_from_raw);
$jalali_to = clean_jalali_date($jalali_to_raw);

// تنظیم تاریخ‌های پیش‌فرض
$use_default_dates = false;
if (empty($jalali_from) || empty($jalali_to)) {
    $use_default_dates = true;
    $date_from = date('Y-m-d', strtotime('-7 days'));
    $date_to = date('Y-m-d');

    $from_parts = explode('-', $date_from);
    $to_parts = explode('-', $date_to);
    $jalali_from_arr = gregorian_to_jalali($from_parts[0], $from_parts[1], $from_parts[2]);
    $jalali_to_arr = gregorian_to_jalali($to_parts[0], $to_parts[1], $to_parts[2]);
    $jalali_from = $jalali_from_arr[0] . '/' . sprintf('%02d', $jalali_from_arr[1]) . '/' . sprintf('%02d', $jalali_from_arr[2]);
    $jalali_to = $jalali_to_arr[0] . '/' . sprintf('%02d', $jalali_to_arr[1]) . '/' . sprintf('%02d', $jalali_to_arr[2]);
} else {
    $from_parts = explode('/', $jalali_from);
    $to_parts = explode('/', $jalali_to);

    if (
        count($from_parts) == 3 && count($to_parts) == 3 &&
        is_numeric($from_parts[0]) && is_numeric($from_parts[1]) && is_numeric($from_parts[2]) &&
        is_numeric($to_parts[0]) && is_numeric($to_parts[1]) && is_numeric($to_parts[2])
    ) {

        $greg_from = jalali_to_gregorian($from_parts[0], $from_parts[1], $from_parts[2]);
        $greg_to = jalali_to_gregorian($to_parts[0], $to_parts[1], $to_parts[2]);

        $date_from = sprintf("%04d-%02d-%02d", $greg_from[0], $greg_from[1], $greg_from[2]);
        $date_to = sprintf("%04d-%02d-%02d", $greg_to[0], $greg_to[1], $greg_to[2]);

        if (!strtotime($date_from) || !strtotime($date_to)) {
            $error = "تاریخ وارد شده معتبر نیست.";
            $use_default_dates = true;
        }
    } else {
        $error = "فرمت تاریخ نامعتبر است. لطفاً تاریخ را به صورت صحیح وارد کنید.";
        $use_default_dates = true;
    }
}

if ($use_default_dates) {
    $date_from = date('Y-m-d', strtotime('-7 days'));
    $date_to = date('Y-m-d');

    $from_parts = explode('-', $date_from);
    $to_parts = explode('-', $date_to);
    $jalali_from_arr = gregorian_to_jalali($from_parts[0], $from_parts[1], $from_parts[2]);
    $jalali_to_arr = gregorian_to_jalali($to_parts[0], $to_parts[1], $to_parts[2]);
    $jalali_from = $jalali_from_arr[0] . '/' . sprintf('%02d', $jalali_from_arr[1]) . '/' . sprintf('%02d', $jalali_from_arr[2]);
    $jalali_to = $jalali_to_arr[0] . '/' . sprintf('%02d', $jalali_to_arr[1]) . '/' . sprintf('%02d', $jalali_to_arr[2]);
}

// دریافت لیست کلاس‌ها
$classes_query = "SELECT id, name FROM classes ORDER BY name";
$classes_result = $conn->query($classes_query);
$classes = [];
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// ساخت کوئری اصلی
$query = "
    SELECT 
        a.attendance_date,
        s.id as student_id,
        s.first_name,
        s.last_name,
        s.national_code,
        s.phone,
        c.id as class_id,
        c.name as class_name,
        COUNT(a.id) as absent_count,
        GROUP_CONCAT(
            CONCAT(p.schedule)
            ORDER BY FIELD(p.schedule, 'زنگ 1', 'زنگ 2', 'زنگ 3', 'زنگ 4')
            SEPARATOR '، '
        ) as absent_periods
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN programs p ON a.program_id = p.id
    JOIN users u ON a.teacher_id = u.id
    WHERE a.attendance_date BETWEEN ? AND ?
    AND a.status = 'غایب'
";

$params = [$date_from, $date_to];
$types = "ss";

if (!empty($class_filter)) {
    $query .= " AND s.class_id = ?";
    $params[] = $class_filter;
    $types .= "i";
}

$query .= " GROUP BY a.attendance_date, s.id, s.first_name, s.last_name, s.national_code, s.phone, c.id, c.name";
$query .= " ORDER BY a.attendance_date DESC, s.last_name, s.first_name";

$absent_history = [];
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $absent_history[] = $row;
        }
    }
    $stmt->close();
}

// آمار کلی
$total_absent = count($absent_history);
$unique_students = [];
$unique_dates = [];
foreach ($absent_history as $record) {
    $unique_students[$record['student_id']] = true;
    $unique_dates[$record['attendance_date']] = true;
}
$total_unique_students = count($unique_students);
$total_days = count($unique_dates);

// محاسبه تاریخ‌های پیش‌فرض شمسی برای دکمه‌های سریع
$today = date('Y-m-d');
$today_parts = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_parts[0], $today_parts[1], $today_parts[2]);
$today_jalali_str = $today_jalali[0] . '/' . sprintf('%02d', $today_jalali[1]) . '/' . sprintf('%02d', $today_jalali[2]);

$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_parts = explode('-', $yesterday);
$yesterday_jalali = gregorian_to_jalali($yesterday_parts[0], $yesterday_parts[1], $yesterday_parts[2]);
$yesterday_jalali_str = $yesterday_jalali[0] . '/' . sprintf('%02d', $yesterday_jalali[1]) . '/' . sprintf('%02d', $yesterday_jalali[2]);

$week_ago = date('Y-m-d', strtotime('-7 days'));
$week_ago_parts = explode('-', $week_ago);
$week_ago_jalali = gregorian_to_jalali($week_ago_parts[0], $week_ago_parts[1], $week_ago_parts[2]);
$week_ago_jalali_str = $week_ago_jalali[0] . '/' . sprintf('%02d', $week_ago_jalali[1]) . '/' . sprintf('%02d', $week_ago_jalali[2]);

$month_start = date('Y-m-01');
$month_start_parts = explode('-', $month_start);
$month_start_jalali = gregorian_to_jalali($month_start_parts[0], $month_start_parts[1], $month_start_parts[2]);
$month_start_jalali_str = $month_start_jalali[0] . '/' . sprintf('%02d', $month_start_jalali[1]) . '/' . sprintf('%02d', $month_start_jalali[2]);

$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));
$last_month_start_parts = explode('-', $last_month_start);
$last_month_end_parts = explode('-', $last_month_end);
$last_month_start_jalali = gregorian_to_jalali($last_month_start_parts[0], $last_month_start_parts[1], $last_month_start_parts[2]);
$last_month_end_jalali = gregorian_to_jalali($last_month_end_parts[0], $last_month_end_parts[1], $last_month_end_parts[2]);
$last_month_start_jalali_str = $last_month_start_jalali[0] . '/' . sprintf('%02d', $last_month_start_jalali[1]) . '/' . sprintf('%02d', $last_month_start_jalali[2]);
$last_month_end_jalali_str = $last_month_end_jalali[0] . '/' . sprintf('%02d', $last_month_end_jalali[1]) . '/' . sprintf('%02d', $last_month_end_jalali[2]);
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>تاریخچه غیبت‌ها</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../styles/output.css">

    <!-- اضافه کردن فایل‌های محلی jalalidatepicker -->
    <link rel="stylesheet" href="./style/jalalidatepicker.min.css">
    <script src="./js/jalalidatepicker.min.js"></script>

    <!-- اضافه کردن xlsx از فایل محلی -->
    <script src="../assets/xlsx/xlsx.full.min.js"></script>

    <style>
        body {
            box-sizing: border-box;
            font-family: system-ui, -apple-system, sans-serif;
        }

        .sidebar {
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-hidden {
            transform: translateX(100%);
        }

        @media (min-width: 1024px) {
            .sidebar-hidden {
                transform: translateX(0);
            }
        }

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .record-row {
            transition: background-color 0.2s ease;
        }

        .record-row:hover {
            background-color: #f8fafc;
        }

        .filter-btn {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .filter-btn:hover {
            background-color: #e5e7eb;
        }

        .period-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            margin: 2px;
        }

        .periods-container {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 300px;
        }

        .absent-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            border-radius: 9999px;
            background-color: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 8px;
            padding: 0 6px;
        }

        .jalali-date-input {
            direction: ltr;
            text-align: left;
        }

        /* رفع مشکل نمایش تقویم */
        .jdp-container {
            z-index: 9999 !important;
        }

        input[data-jdp] {
            background-color: #fff !important;
            cursor: pointer !important;
        }
    </style>
</head>

<body class="min-h-full bg-gray-100" onload="initializeDatepicker()">
    <!-- Mobile Menu Button -->
    <button onclick="toggleSidebar()"
        class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-blue-600 text-white rounded-lg shadow-lg">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Overlay for mobile -->
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden">
    </div>

    <!-- Sidebar -->
    <aside id="sidebar"
        class="sidebar sidebar-hidden lg:sidebar-hidden-false fixed top-0 right-0 h-full w-64 bg-white shadow-xl z-40">
        <div class="h-full flex flex-col">
            <!-- Logo & Title -->
            <div class="p-6 bg-gradient-to-br from-blue-600 to-blue-800">
                <h1 class="text-xl font-bold text-white mb-1">هنرستان سپهری راد</h1>
                <p class="text-blue-100 text-sm">سامانه حضور و غیاب</p>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                </path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="teachers.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            بخش دبیران
                        </a>
                    </li>
                    <li>
                        <a href="classes.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                            کلاس ها
                        </a>
                    </li>
                    <li>
                        <a href="students.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                            دانش آموزان
                        </a>
                    </li>
                    <li>
                        <a href="programs.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            برنامه زمانی
                        </a>
                    </li>
                    <li>
                        <a href="today_absent.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z">
                                </path>
                            </svg>
                            غایبین امروز
                        </a>
                    </li>
                    <li>
                        <a href="absent_history.php"
                            class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            تاریخچه غیبت‌ها
                        </a>
                    </li>
                    <li>
                        <a href="send_sms.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            ارسال پیامک
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="../logout.php"
                    class="w-full flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                        </path>
                    </svg>
                    خروج
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="min-h-screen lg:mr-64">
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="w-full">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">تاریخچه غیبت‌ها</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        مشاهده و گزارش‌گیری از غیبت‌های دانش‌آموزان در بازه‌های زمانی مختلف
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <div
                        class="stat-card bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/30 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_absent; ?></h3>
                        <p class="text-red-100 text-sm">تعداد روزهای غیبت</p>
                    </div>

                    <div
                        class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/30 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_unique_students; ?></h3>
                        <p class="text-orange-100 text-sm">دانش‌آموز غایب</p>
                    </div>

                    <div
                        class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/30 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_days; ?></h3>
                        <p class="text-blue-100 text-sm">روز دارای غیبت</p>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                    <div class="p-4 sm:p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">فیلترهای جستجو</h2>

                        <!-- Quick Filters -->
                        <div class="flex flex-wrap gap-2 mb-6">
                            <button type="button"
                                onclick="setDateRange('<?php echo $today_jalali_str; ?>', '<?php echo $today_jalali_str; ?>')"
                                class="filter-btn px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-gray-300">
                                امروز
                            </button>
                            <button type="button"
                                onclick="setDateRange('<?php echo $yesterday_jalali_str; ?>', '<?php echo $yesterday_jalali_str; ?>')"
                                class="filter-btn px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-gray-300">
                                دیروز
                            </button>
                            <button type="button"
                                onclick="setDateRange('<?php echo $week_ago_jalali_str; ?>', '<?php echo $today_jalali_str; ?>')"
                                class="filter-btn px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-gray-300">
                                این هفته
                            </button>
                            <button type="button"
                                onclick="setDateRange('<?php echo $month_start_jalali_str; ?>', '<?php echo $today_jalali_str; ?>')"
                                class="filter-btn px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-gray-300">
                                این ماه
                            </button>
                            <button type="button"
                                onclick="setDateRange('<?php echo $last_month_start_jalali_str; ?>', '<?php echo $last_month_end_jalali_str; ?>')"
                                class="filter-btn px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-gray-300">
                                ماه گذشته
                            </button>
                        </div>

                        <!-- Filter Form -->
                        <form method="GET" action="" id="filterForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">از تاریخ (شمسی)</label>
                                    <input type="text" name="jalali_from" id="jalali_from"
                                        value="<?php echo htmlspecialchars($jalali_from); ?>" placeholder="1403/01/01"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 jalali-date-input"
                                        autocomplete="off" data-jdp>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ (شمسی)</label>
                                    <input type="text" name="jalali_to" id="jalali_to"
                                        value="<?php echo htmlspecialchars($jalali_to); ?>" placeholder="1403/12/29"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 jalali-date-input"
                                        autocomplete="off" data-jdp>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">کلاس</label>
                                    <select name="class_id" id="class_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">همه کلاس‌ها</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit"
                                    class="px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                    اعمال فیلترها
                                </button>
                                <a href="absent_history.php"
                                    class="px-6 py-2.5 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                                    پاک کردن فیلترها
                                </a>
                                <button type="button" onclick="exportToExcel()"
                                    class="px-6 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors mr-auto">
                                    خروجی اکسل
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-4 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">
                                نتایج جستجو
                                <?php if ($total_absent > 0): ?>
                                    <span class="text-red-600 font-bold mr-2">(<?php echo $total_absent; ?> مورد)</span>
                                <?php endif; ?>
                            </h2>
                            <span class="text-sm text-gray-500">
                                از تاریخ <?php echo $jalali_from; ?> تا <?php echo $jalali_to; ?>
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full" id="attendance-table">
                                <thead class="bg-gray-50 border-b-2 border-gray-200">
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            #</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            تاریخ</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            دانش‌آموز</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            کلاس</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            زنگ‌های غیبت</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap hidden md:table-cell">
                                            شماره تماس</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($total_absent > 0): ?>
                                        <?php foreach ($absent_history as $index => $record):
                                            $date_parts = explode('-', $record['attendance_date']);
                                            $jalali_date = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2]);
                                            $jalali_date_formatted = $jalali_date[0] . '/' . sprintf('%02d', $jalali_date[1]) . '/' . sprintf('%02d', $jalali_date[2]);

                                            $timestamp = strtotime($record['attendance_date']);
                                            $weekday_number = date('w', $timestamp);
                                            $weekdays_persian = [
                                                0 => 'یکشنبه',
                                                1 => 'دوشنبه',
                                                2 => 'سه‌شنبه',
                                                3 => 'چهارشنبه',
                                                4 => 'پنج‌شنبه',
                                                5 => 'جمعه',
                                                6 => 'شنبه'
                                            ];
                                            $weekday_persian = $weekdays_persian[$weekday_number];
                                            ?>
                                            <tr class="record-row hover:bg-gray-50 transition-colors duration-150">
                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <?php echo $index + 1; ?>
                                                </td>
                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <div class="font-medium"><?php echo $jalali_date_formatted; ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo $weekday_persian; ?></div>
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-xs sm:text-sm text-gray-900 font-medium whitespace-nowrap">
                                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                    <span class="absent-count"><?php echo $record['absent_count']; ?></span>
                                                </td>
                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">
                                                    <?php echo htmlspecialchars($record['class_name']); ?>
                                                </td>
                                                <td class="px-4 py-3 text-xs sm:text-sm">
                                                    <div class="periods-container">
                                                        <?php
                                                        $periods = explode('،', $record['absent_periods']);
                                                        foreach ($periods as $period):
                                                            ?>
                                                            <span class="period-badge">
                                                                <?php echo trim($period); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap hidden md:table-cell">
                                                    <?php if (!empty($record['phone'])): ?>
                                                        <span
                                                            class="text-green-600 font-medium ltr block text-left"><?php echo htmlspecialchars($record['phone']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">ندارد</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6"
                                                class="text-center px-4 py-8 text-gray-500 text-sm sm:text-base">
                                                <div class="flex flex-col items-center justify-center">
                                                    <svg class="w-16 h-16 text-gray-400 mb-4" fill="none"
                                                        stroke="currentColor" viewbox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <p class="text-lg font-medium text-gray-700 mb-2">موردی یافت نشد!</p>
                                                    <p class="text-gray-600">در بازه زمانی انتخاب شده هیچ غیبتی ثبت نشده
                                                        است.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800 text-xs sm:text-sm flex items-start">
                        <svg class="w-5 h-5 ml-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                            viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        تاریخچه غیبت‌ها به صورت تجمیعی در هر روز نمایش داده می‌شود. تعداد زنگ‌های غیبت در کنار نام
                        دانش‌آموز مشخص شده است.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // تابع راه‌اندازی تاریخ‌picker
        function initializeDatepicker() {
            if (typeof jalaliDatepicker !== 'undefined') {
                jalaliDatepicker.startWatch({
                    minDate: "1390/01/01",
                    maxDate: "1410/12/29",
                    autoHide: true,
                    hideAfterChange: true,
                    showTodayBtn: true,
                    showEmptyBtn: true,
                    format: "YYYY/MM/DD"
                });

                console.log("Datepicker initialized successfully");
            } else {
                console.error("jalaliDatepicker not loaded yet, retrying in 500ms");
                setTimeout(initializeDatepicker, 500);
            }
        }

        // اجرا بعد از لود کامل صفحه
        document.addEventListener('DOMContentLoaded', function () {
            initializeDatepicker();
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
        }

        function setDateRange(from, to) {
            document.getElementById('jalali_from').value = from;
            document.getElementById('jalali_to').value = to;
            document.getElementById('filterForm').submit();
        }

        function exportToExcel() {
            if (typeof XLSX === 'undefined') {
                alert("کتابخانه اکسل بارگذاری نشده است!");
                return;
            }

            const table = document.getElementById('attendance-table');
            const rows = table.querySelectorAll('tbody tr');
            const headers = ['ردیف', 'تاریخ', 'روز هفته', 'نام دانش‌آموز', 'کلاس', 'زنگ‌های غیبت', 'شماره تماس'];

            const data = [];
            data.push(headers);

            rows.forEach((row, index) => {
                if (row.querySelector('td[colspan]')) return;

                const cells = row.querySelectorAll('td');
                if (cells.length < 5) return;

                const dateCell = cells[1].querySelector('div.font-medium')?.textContent.trim() || cells[1].textContent.trim();
                const weekday = cells[1].querySelector('div.text-xs')?.textContent.trim() || '';

                const nameCell = cells[2];
                const nameText = nameCell.textContent.trim();
                const studentName = nameText.replace(/[0-9]+/g, '').trim();

                const className = cells[3].textContent.trim();

                const periods = [];
                cells[4].querySelectorAll('.period-badge').forEach(badge => {
                    periods.push(badge.textContent.trim());
                });
                const periodsText = periods.join(' - ');

                let phone = cells[5]?.textContent.trim() || '';
                if (phone === 'ندارد') phone = '';

                data.push([
                    index + 1,
                    dateCell,
                    weekday,
                    studentName,
                    className,
                    periodsText,
                    phone
                ]);
            });

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(data);

            const colWidths = [
                { wch: 5 },  // ردیف
                { wch: 12 }, // تاریخ
                { wch: 10 }, // روز هفته
                { wch: 25 }, // نام دانش‌آموز
                { wch: 15 }, // کلاس
                { wch: 30 }, // زنگ‌های غیبت
                { wch: 17 }  // شماره تماس
            ];
            ws['!cols'] = colWidths;

            XLSX.utils.book_append_sheet(wb, ws, 'تاریخچه غیبت‌ها');
            const fileName = `تاریخچه_غیبت‌ها_${new Date().toLocaleDateString('fa-IR').replace(/\//g, '-')}.xlsx`;
            XLSX.writeFile(wb, fileName);
        }

        // اضافه کردن validation برای فرم قبل از ارسال
        document.getElementById('filterForm').addEventListener('submit', function (e) {
            const fromDate = document.getElementById('jalali_from').value;
            const toDate = document.getElementById('jalali_to').value;

            const datePattern = /^\d{4}\/\d{2}\/\d{2}$/;

            if (fromDate && !datePattern.test(fromDate)) {
                e.preventDefault();
                alert('فرمت تاریخ شروع صحیح نیست. لطفاً به صورت 1403/01/01 وارد کنید.');
                return;
            }

            if (toDate && !datePattern.test(toDate)) {
                e.preventDefault();
                alert('فرمت تاریخ پایان صحیح نیست. لطفاً به صورت 1403/01/01 وارد کنید.');
                return;
            }
        });
    </script>
</body>

</html>