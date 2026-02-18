<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'مدیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

$today = date('Y-m-d');

// بخش جستجو - بهبود یافته
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// بخش مرتب‌سازی - بهبود یافته
$order = isset($_GET['order']) ? $_GET['order'] : 'last_name';
$direction = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';

// ستون‌های مجاز برای مرتب‌سازی
$allowed_columns = [
    'first_name' => 's.first_name',
    'last_name' => 's.last_name',
    'class_name' => 'c.name',
    'national_code' => 's.national_code',
    'phone' => 's.phone'
];

if (array_key_exists($order, $allowed_columns)) {
    $order_column = $allowed_columns[$order];
} else {
    $order_column = 's.last_name';
}

// دریافت اطلاعات کامل غایبین با جزئیات زنگ‌ها
$query = "
    SELECT 
        s.id,
        s.first_name,
        s.last_name,
        s.national_code,
        s.phone,
        c.name as class_name,
        c.id as class_id,
        GROUP_CONCAT(
            CONCAT(p.schedule, '|', p.day_of_week)
            ORDER BY FIELD(p.schedule, 'زنگ 1', 'زنگ 2', 'زنگ 3', 'زنگ 4')
            SEPARATOR ','
        ) as absent_periods,
        COUNT(a.id) as absent_count
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    JOIN programs p ON a.program_id = p.id
    WHERE a.attendance_date = ?
    AND a.status = 'غایب'";

// اضافه کردن شرط جستجو - بهبود یافته
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (
        s.first_name LIKE '%$search%' 
        OR s.last_name LIKE '%$search%' 
        OR CONCAT(s.first_name, ' ', s.last_name) LIKE '%$search%'
        OR s.national_code LIKE '%$search%' 
        OR s.phone LIKE '%$search%'
        OR c.name LIKE '%$search%'
    )";
}

$query .= " GROUP BY s.id, s.first_name, s.last_name, s.national_code, s.phone, c.name, c.id
            ORDER BY $order_column $direction, s.last_name, s.first_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$all_absent_students = [];
while ($row = $result->fetch_assoc()) {
    // پردازش زنگ‌های غیبت
    $periods = [];
    if (!empty($row['absent_periods'])) {
        $items = explode(',', $row['absent_periods']);
        foreach ($items as $item) {
            list($schedule, $day) = explode('|', $item);
            $periods[] = [
                'schedule' => $schedule,
                'day' => $day
            ];
        }
    }
    $row['periods'] = $periods;
    $all_absent_students[] = $row;
}
$stmt->close();

// فیلتر کردن دانش‌آموزان دارای شماره تلفن برای نمایش اولیه
$recipients = array_filter($all_absent_students, function ($student) {
    return !empty($student['phone']);
});
// بازنشانی ایندکس‌ها
$recipients = array_values($recipients);

$total_recipients = count($recipients);
$total_absent = count($all_absent_students);
$without_phone = $total_absent - $total_recipients;

$default_message = "والدین محترم
دانش‌آموز {name} کلاس {class} امروز {date} غایب است.
هنرستان سپهری راد";

function gregorian_to_jalali($gy, $gm, $gd)
{
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100)) + ((int) (($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int) ($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int) ($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int) ($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return array($jy, $jm, $jd);
}

$today_parts = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_parts[0], $today_parts[1], $today_parts[2]);
$today_jalali_formatted = $today_jalali[0] . '/' . sprintf('%02d', $today_jalali[1]) . '/' . sprintf('%02d', $today_jalali[2]);

$weekdays_persian = [
    0 => 'یکشنبه',
    1 => 'دوشنبه',
    2 => 'سه‌شنبه',
    3 => 'چهارشنبه',
    4 => 'پنج‌شنبه',
    5 => 'جمعه',
    6 => 'شنبه'
];
$weekday_number = date('w');
$today_persian = $weekdays_persian[$weekday_number];

// تابع کمکی برای بررسی وجود زنگ خاص
function hasPeriod($periods, $targetSchedule)
{
    foreach ($periods as $period) {
        if ($period['schedule'] === $targetSchedule) {
            return true;
        }
    }
    return false;
}
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>ارسال پیامک</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../styles/output.css">
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

        .sms-preview {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }

        .recipient-row {
            transition: background-color 0.2s ease;
        }

        .recipient-row:hover {
            background-color: #f8fafc;
        }

        textarea {
            resize: none;
        }

        .period-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 2px;
        }

        .period-absent {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .period-present {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .periods-container {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            display: inline-block;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .checkbox-custom.checked {
            background-color: #10b981;
            border-color: #10b981;
        }

        .checkbox-custom.checked::after {
            content: '';
            position: absolute;
            left: 6px;
            top: 2px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .select-all-btn {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .select-all-btn:hover {
            background-color: #e5e7eb;
        }

        .no-phone-badge {
            background-color: #f3f4f6;
            color: #6b7280;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 9999px;
            display: inline-block;
        }

        .sort-link {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #4b5563;
            transition: color 0.2s;
            text-decoration: none;
        }

        .sort-link:hover {
            color: #10b981;
        }

        .sort-active {
            color: #10b981;
            font-weight: 600;
        }

        .search-box {
            transition: all 0.2s ease;
        }

        .search-box:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .filter-badge {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .filter-badge button:hover {
            color: #dc2626;
        }
    </style>
</head>

<body class="min-h-full bg-gray-100">
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
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 ounded-lg font-medium transition-colors">
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
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
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
                            class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
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
                <a href="/logout.php"
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">ارسال پیامک به والدین</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <span class="text-green-600 font-medium">امروز <?php echo $today_persian; ?>
                            <?php echo $today_jalali_formatted; ?></span>
                        - ارسال پیامک اطلاع‌رسانی غیبت به والدین دانش‌آموزان
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <!-- Total Absent Card -->
                    <div
                        class="stat-card bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z">
                                    </path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white/30 px-2 py-1 rounded-full">
                                امروز
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_absent; ?></h3>
                        <p class="text-red-100 text-sm">کل غایبین امروز</p>
                    </div>

                    <!-- Total Recipients Card -->
                    <div
                        class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white/30 px-2 py-1 rounded-full">
                                قابل ارسال
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1" id="total-recipients-count"><?php echo $total_recipients; ?>
                        </h3>
                        <p class="text-green-100 text-sm">دارای شماره تماس</p>
                    </div>

                    <!-- Selected Count Card -->
                    <div
                        class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white/30 px-2 py-1 rounded-full">
                                انتخاب شده
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1" id="selected-count"><?php echo $total_recipients; ?></h3>
                        <p class="text-blue-100 text-sm">دریافت‌کننده نهایی</p>
                    </div>
                </div>



                <!-- Main Card - فرم ارسال پیامک -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                    <div class="p-4 sm:p-6">
                        <!-- Action Bar -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">
                                متن پیامک
                            </h2>
                            <div class="flex gap-3">
                                <button onclick="resetMessage()"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm">
                                    بازنشانی متن
                                </button>
                                <button onclick="showPreview()"
                                    class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 text-sm">
                                    پیش‌نمایش
                                </button>
                            </div>
                        </div>

                        <!-- Form -->
                        <form id="smsForm" action="send_sms_process.php" method="POST">
                            <input type="hidden" name="selected_students" id="selected-students-input" value="">

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    متن پیامک:
                                    <span class="text-green-600 text-xs mr-2">(از متغیرهای {name}, {class}, {date}
                                        استفاده کنید)</span>
                                </label>
                                <textarea name="message" id="message" rows="6"
                                    class="w-full border border-gray-300 rounded-lg p-4 focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm sm:text-base"
                                    oninput="updateCharCount(this)"
                                    required><?php echo htmlspecialchars($default_message); ?></textarea>
                                <div class="flex justify-between items-center mt-3">
                                    <div class="text-xs text-gray-500">
                                        <span class="font-medium">متغیرها:</span>
                                        <span class="mx-2">• {name} = نام دانش‌آموز</span>
                                        <span class="mx-2">• {class} = نام کلاس</span>
                                        <span class="mx-2">• {date} = تاریخ امروز</span>
                                    </div>
                                    <div id="charCount" class="text-sm font-medium text-gray-700">
                                        0/160 کاراکتر
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Box (hidden by default) -->
                            <div id="previewBox" class="hidden mb-6 p-4 sms-preview rounded-lg">
                                <h3 class="text-sm font-semibold text-green-800 mb-2">پیش‌نمایش پیامک:</h3>
                                <div id="previewContent" class="text-gray-700 whitespace-pre-line"></div>
                            </div>

                            <!-- Submit Button -->
                            <div
                                class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-200">
                                <div class="text-gray-600 text-sm">
                                    <span id="selected-count-display"
                                        class="font-medium text-blue-600"><?php echo $total_recipients; ?></span>
                                    نفر از والدین انتخاب شده‌اند
                                    <?php if ($without_phone > 0): ?>
                                        <span class="mr-2 text-gray-500">(<?php echo $without_phone; ?> نفر بدون
                                            شماره)</span>
                                    <?php endif; ?>
                                </div>
                                <button type="submit"
                                    class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:from-green-600 hover:to-green-700 transition-colors duration-200 text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed"
                                    id="submit-btn">
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor"
                                        viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    ارسال پیامک
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- گیرندگان -->
                <?php if ($total_absent > 0): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <div>
                                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900">لیست دانش‌آموزان غایب</h2>
                                    <p class="text-sm text-gray-500 mt-1">با تیک زدن هر دانش‌آموز، به لیست ارسال اضافه
                                        می‌شود</p>
                                </div>
                                <div class="flex gap-3">
                                    <button onclick="selectAll(true)"
                                        class="select-all-btn px-4 py-2 bg-green-100 text-green-700 rounded-lg text-sm font-medium hover:bg-green-200 transition-colors">
                                        ✅ انتخاب همه
                                    </button>
                                    <button onclick="selectAll(false)"
                                        class="select-all-btn px-4 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors">
                                        ❌ لغو همه
                                    </button>
                                </div>
                            </div>

                            <!-- Table Container -->
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50 border-b-2 border-gray-200">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap w-16">
                                                <div class="flex items-center justify-center">
                                                    <span>انتخاب</span>
                                                </div>
                                            </th>
                                            <th
                                                class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                                #</th>
                                            <th
                                                class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                                <a href="?search=<?php echo urlencode($search); ?>&order=first_name&dir=<?php echo ($order == 'first_name' && $direction == 'asc') ? 'desc' : 'asc'; ?>"
                                                    class="sort-link <?php echo ($order == 'first_name') ? 'sort-active' : ''; ?>">
                                                    نام
                                                    <?php if ($order == 'first_name'): ?>
                                                        <span><?php echo $direction == 'asc' ? '↑' : '↓'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th
                                                class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                                <a href="?search=<?php echo urlencode($search); ?>&order=last_name&dir=<?php echo ($order == 'last_name' && $direction == 'asc') ? 'desc' : 'asc'; ?>"
                                                    class="sort-link <?php echo ($order == 'last_name') ? 'sort-active' : ''; ?>">
                                                    نام خانوادگی
                                                    <?php if ($order == 'last_name'): ?>
                                                        <span><?php echo $direction == 'asc' ? '↑' : '↓'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th
                                                class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                                وضعیت زنگ‌ها</th>
                                            <th
                                                class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                                <a href="?search=<?php echo urlencode($search); ?>&order=phone&dir=<?php echo ($order == 'phone' && $direction == 'asc') ? 'desc' : 'asc'; ?>"
                                                    class="sort-link <?php echo ($order == 'phone') ? 'sort-active' : ''; ?>">
                                                    شماره تماس
                                                    <?php if ($order == 'phone'): ?>
                                                        <span><?php echo $direction == 'asc' ? '↑' : '↓'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th
                                                class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                                <a href="?search=<?php echo urlencode($search); ?>&order=class_name&dir=<?php echo ($order == 'class_name' && $direction == 'asc') ? 'desc' : 'asc'; ?>"
                                                    class="sort-link <?php echo ($order == 'class_name') ? 'sort-active' : ''; ?>">
                                                    کلاس
                                                    <?php if ($order == 'class_name'): ?>
                                                        <span><?php echo $direction == 'asc' ? '↑' : '↓'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($all_absent_students as $index => $student):
                                            $hasPhone = !empty($student['phone']);
                                            $studentId = $student['id'];
                                            ?>
                                            <tr class="recipient-row hover:bg-gray-50 transition-colors duration-150 <?php echo !$hasPhone ? 'opacity-60' : ''; ?>"
                                                data-student-id="<?php echo $studentId; ?>"
                                                data-has-phone="<?php echo $hasPhone ? 'true' : 'false'; ?>">

                                                <td class="px-4 py-3 text-center">
                                                    <?php if ($hasPhone): ?>
                                                        <div class="flex items-center justify-center">
                                                            <div class="checkbox-custom student-checkbox <?php echo $hasPhone ? 'checked' : ''; ?>"
                                                                data-student-id="<?php echo $studentId; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                                data-class-name="<?php echo htmlspecialchars($student['class_name'] ?? ''); ?>"
                                                                data-phone="<?php echo htmlspecialchars($student['phone']); ?>"
                                                                data-class-id="<?php echo $student['class_id']; ?>"
                                                                onclick="toggleStudent(this)"></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="flex items-center justify-center">
                                                            <span class="no-phone-badge">بدون شماره</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <?php echo $index + 1; ?>
                                                </td>

                                                <td
                                                    class="px-4 py-3 text-xs sm:text-sm text-gray-900 font-medium whitespace-nowrap">
                                                    <?php echo htmlspecialchars($student['first_name']); ?>
                                                </td>

                                                <td
                                                    class="px-4 py-3 text-xs sm:text-sm text-gray-900 font-medium whitespace-nowrap">
                                                    <?php echo htmlspecialchars($student['last_name']); ?>
                                                    <?php if ($student['absent_count'] > 1): ?>
                                                        <span
                                                            class="mr-1 text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full"><?php echo $student['absent_count']; ?>
                                                            زنگ</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm">
                                                    <div class="periods-container">
                                                        <?php
                                                        $allPeriods = ['زنگ 1', 'زنگ 2', 'زنگ 3', 'زنگ 4'];
                                                        foreach ($allPeriods as $period):
                                                            $isAbsent = hasPeriod($student['periods'], $period);
                                                            ?>
                                                            <div
                                                                class="period-badge <?php echo $isAbsent ? 'period-absent' : 'period-present'; ?>">
                                                                <?php if ($isAbsent): ?>
                                                                    <span class="flex items-center gap-1">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                        </svg>
                                                                        <?php echo $period; ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="flex items-center gap-1">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                        </svg>
                                                                        <?php echo $period; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">
                                                    <?php if (!empty($student['phone'])): ?>
                                                        <span class="text-green-600 font-medium dir-ltr text-left block">
                                                            <?php echo htmlspecialchars($student['phone']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">ندارد</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">
                                                    <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs">
                                                        <?php echo htmlspecialchars($student['class_name'] ?? 'نامشخص'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- نمایش تعداد نتایج -->
                            <div class="mt-4 text-sm text-gray-500">
                                تعداد کل: <?php echo count($all_absent_students); ?> دانش‌آموز
                                <?php if (!empty($search)): ?>
                                    <span class="mx-2">|</span>
                                    <a href="send_sms.php" class="text-green-600 hover:text-green-800">
                                        نمایش همه
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                        <div class="text-green-500 text-6xl mb-4">
                            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-700 mb-3">هیچ غایبی وجود ندارد!</h3>
                        <p class="text-gray-600 mb-6">امروز هیچ دانش‌آموزی غایب نبوده است.</p>
                        <div class="space-x-4 space-y-4">
                            <a href="today_absent.php"
                                class="inline-block px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                مشاهده غایبین
                            </a>
                            <a href="dashboard.php"
                                class="inline-block px-6 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                بازگشت به داشبورد
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Box -->
                <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-800 text-xs sm:text-sm flex items-start">
                        <svg class="w-5 h-5 ml-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                            viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        با تیک زدن هر دانش‌آموز، می‌توانید انتخاب کنید به چه کسانی پیامک ارسال شود. دانش‌آموزان بدون
                        شماره تلفن قابل انتخاب نیستند.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ذخیره اطلاعات دانش‌آموزان انتخاب شده
        let selectedStudents = [];

        // مقداردهی اولیه - همه دانش‌آموزان دارای شماره تلفن انتخاب شوند
        document.addEventListener('DOMContentLoaded', function () {
            // پیدا کردن همه چک‌باکس‌ها
            const checkboxes = document.querySelectorAll('.checkbox-custom');

            // اضافه کردن همه دانش‌آموزان دارای شماره تلفن به لیست انتخاب شده‌ها
            checkboxes.forEach(checkbox => {
                const studentId = checkbox.getAttribute('data-student-id');
                const studentName = checkbox.getAttribute('data-student-name');
                const className = checkbox.getAttribute('data-class-name');
                const phone = checkbox.getAttribute('data-phone');
                const classId = checkbox.getAttribute('data-class-id');

                selectedStudents.push({
                    id: studentId,
                    name: studentName,
                    class: className,
                    phone: phone,
                    class_id: classId
                });
            });

            updateSelectedCount();
            updateSelectedInput();

            // به‌روزرسانی شمارنده کاراکتر
            const textarea = document.querySelector('textarea[name="message"]');
            updateCharCount(textarea);
        });

        // تابع toggle برای انتخاب/لغو انتخاب دانش‌آموز
        function toggleStudent(element) {
            const studentId = element.getAttribute('data-student-id');
            const studentName = element.getAttribute('data-student-name');
            const className = element.getAttribute('data-class-name');
            const phone = element.getAttribute('data-phone');
            const classId = element.getAttribute('data-class-id');

            // تغییر کلاس ظاهری
            element.classList.toggle('checked');

            // پیدا کردن ایندکس دانش‌آموز در لیست انتخاب شده‌ها
            const index = selectedStudents.findIndex(s => s.id === studentId);

            if (index === -1) {
                // اضافه کردن به لیست
                selectedStudents.push({
                    id: studentId,
                    name: studentName,
                    class: className,
                    phone: phone,
                    class_id: classId
                });
            } else {
                // حذف از لیست
                selectedStudents.splice(index, 1);
            }

            updateSelectedCount();
            updateSelectedInput();
        }

        // تابع انتخاب/لغو انتخاب همه
        function selectAll(select) {
            const checkboxes = document.querySelectorAll('.checkbox-custom');

            // پاک کردن لیست انتخاب شده‌ها
            selectedStudents = [];

            if (select) {
                // اضافه کردن همه به لیست
                checkboxes.forEach(checkbox => {
                    checkbox.classList.add('checked');

                    const studentId = checkbox.getAttribute('data-student-id');
                    const studentName = checkbox.getAttribute('data-student-name');
                    const className = checkbox.getAttribute('data-class-name');
                    const phone = checkbox.getAttribute('data-phone');
                    const classId = checkbox.getAttribute('data-class-id');

                    selectedStudents.push({
                        id: studentId,
                        name: studentName,
                        class: className,
                        phone: phone,
                        class_id: classId
                    });
                });
            } else {
                // حذف همه از لیست
                checkboxes.forEach(checkbox => {
                    checkbox.classList.remove('checked');
                });
            }

            updateSelectedCount();
            updateSelectedInput();
        }

        // به‌روزرسانی تعداد انتخاب شده‌ها
        function updateSelectedCount() {
            const count = selectedStudents.length;
            document.getElementById('selected-count').textContent = count;
            document.getElementById('selected-count-display').textContent = count;

            // فعال/غیرفعال کردن دکمه ارسال
            const submitBtn = document.getElementById('submit-btn');
            if (count === 0) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // به‌روزرسانی فیلد مخفی با اطلاعات دانش‌آموزان انتخاب شده
        function updateSelectedInput() {
            const input = document.getElementById('selected-students-input');
            input.value = JSON.stringify(selectedStudents);
        }

        // توابع قبلی
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
        }

        function updateCharCount(textarea) {
            const text = textarea.value;
            const charCountElement = document.getElementById('charCount');
            charCountElement.textContent = `${text.length}/160 کاراکتر`;

            if (text.length > 160) {
                charCountElement.classList.remove('text-gray-700', 'text-yellow-600');
                charCountElement.classList.add('text-red-600', 'font-bold');
            } else if (text.length > 140) {
                charCountElement.classList.remove('text-gray-700', 'text-red-600');
                charCountElement.classList.add('text-yellow-600');
            } else {
                charCountElement.classList.remove('text-red-600', 'text-yellow-600');
                charCountElement.classList.add('text-gray-700');
            }

            return text.length;
        }

        function resetMessage() {
            const defaultMessage = `والدین محترم
دانش‌آموز {name} کلاس {class} امروز {date} غایب است.
هنرستان سپهری راد`;

            document.getElementById('message').value = defaultMessage;
            updateCharCount(document.getElementById('message'));

            document.getElementById('previewBox').classList.add('hidden');
        }

        function showPreview() {
            const message = document.getElementById('message').value;
            const previewContent = message
                .replace(/{name}/g, 'علی محمدی')
                .replace(/{class}/g, 'دهم ریاضی')
                .replace(/{date}/g, '<?php echo $today_jalali_formatted; ?>');

            document.getElementById('previewContent').textContent = previewContent;
            document.getElementById('previewBox').classList.remove('hidden');
        }

        document.getElementById('smsForm').addEventListener('submit', function (e) {
            const message = this.message.value.trim();
            const charCount = message.length;
            const selectedCount = selectedStudents.length;

            if (selectedCount === 0) {
                e.preventDefault();
                alert('هیچ دانش‌آموزی برای ارسال انتخاب نشده است.');
                return;
            }

            if (charCount > 160) {
                e.preventDefault();
                alert('طول پیام نباید بیشتر از 160 کاراکتر باشد.');
                return;
            }

            if (message === '') {
                e.preventDefault();
                alert('لطفاً متن پیام را وارد کنید.');
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                در حال ارسال...
            `;
            submitBtn.disabled = true;

            if (!confirm(`آیا از ارسال پیامک به ${selectedCount} نفر اطمینان دارید؟`)) {
                e.preventDefault();
                submitBtn.innerHTML = `
                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    ارسال پیامک
                `;
                submitBtn.disabled = false;
            }
        });
    </script>
</body>

</html>