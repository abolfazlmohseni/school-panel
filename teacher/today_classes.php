<?php
session_start();
require_once '../config.php';

// چک ورود دبیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'دبیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// آرایه روزهای هفته فارسی
$weekdays_persian = [
    0 => 'یکشنبه',
    1 => 'دوشنبه',
    2 => 'سه‌شنبه',
    3 => 'چهارشنبه',
    4 => 'پنج‌شنبه',
    5 => 'جمعه',
    6 => 'شنبه'
];

// تاریخ امروز به شمسی
function gregorian_to_jalali($gy, $gm, $gd)
{
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

$today = date('Y-m-d');
$today_parts = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_parts[0], $today_parts[1], $today_parts[2]);
$today_jalali_formatted = $today_jalali[0] . '/' . sprintf('%02d', $today_jalali[1]) . '/' . sprintf('%02d', $today_jalali[2]);

// نام روز امروز
$weekday_number = date('w');
$today_persian = $weekdays_persian[$weekday_number];

// ---------- دریافت کلاس‌های امروز ----------
$stmt = $conn->prepare("
    SELECT 
        p.id as program_id,
        c.name as class_name,
        p.schedule,
        c.id as class_id,
        (
            SELECT COUNT(*) 
            FROM students s 
            WHERE s.class_id = c.id
        ) as student_count,
        (
            SELECT COUNT(DISTINCT a.student_id)
            FROM attendance a
            WHERE a.program_id = p.id 
            AND a.attendance_date = ?
            AND a.status = 'حاضر'
        ) as present_count
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    WHERE p.teacher_id = ? 
    AND p.day_of_week = ?
    ORDER BY 
    CASE 
        WHEN p.schedule LIKE '%1%' THEN 1
        WHEN p.schedule LIKE '%2%' THEN 2
        WHEN p.schedule LIKE '%3%' THEN 3
        WHEN p.schedule LIKE '%4%' THEN 4
        WHEN p.schedule LIKE '%5%' THEN 5
        ELSE 6
    END
");
$stmt->bind_param("sis", $today, $teacher_id, $today_persian);
$stmt->execute();
$result = $stmt->get_result();
$today_classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- بررسی آیا امروز تعطیل است ----------
$is_holiday = count($today_classes) === 0;
$next_class = null;

// اگر امروز کلاسی نیست، اولین کلاس بعدی را پیدا کن
if ($is_holiday) {
    $stmt = $conn->prepare("
        SELECT 
            p.id as program_id,
            c.name as class_name,
            p.day_of_week,
            p.schedule,
            c.id as class_id
        FROM programs p
        JOIN classes c ON p.class_id = c.id
        WHERE p.teacher_id = ?
        AND p.day_of_week != ?
        ORDER BY 
            CASE p.day_of_week
                WHEN 'شنبه' THEN 0
                WHEN 'یکشنبه' THEN 1
                WHEN 'دوشنبه' THEN 2
                WHEN 'سه‌شنبه' THEN 3
                WHEN 'چهارشنبه' THEN 4
                WHEN 'پنج‌شنبه' THEN 5
                WHEN 'جمعه' THEN 6
                ELSE 7
            END
        LIMIT 1
    ");
    $stmt->bind_param("is", $teacher_id, $today_persian);
    $stmt->execute();
    $result = $stmt->get_result();
    $next_class = $result->fetch_assoc();
    $stmt->close();
}

// ---------- آمار امروز ----------
$total_students_today = 0;
$total_present_today = 0;

foreach ($today_classes as $class) {
    $total_students_today += $class['student_count'];
    $total_present_today += $class['present_count'];
}

$attendance_rate_today = $total_students_today > 0
    ? round(($total_present_today / $total_students_today) * 100, 1)
    : 0;
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>کلاس‌های امروز - سامانه حضور غیاب</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            box-sizing: border-box;
        }

        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Vazirmatn', sans-serif;
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

        .class-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background-color: #e5e7eb;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .holiday-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .today-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .quick-action-card {
            transition: all 0.2s ease;
        }

        .quick-action-card:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="min-h-full bg-gray-100">
    <!-- Mobile Menu Button -->
    <button onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-blue-600 text-white rounded-lg shadow-lg">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Overlay for mobile -->
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-hidden lg:sidebar-hidden-false fixed top-0 right-0 h-full w-64 bg-white shadow-xl z-40">
        <div class="h-full flex flex-col">
            <!-- Logo & User Info -->
            <div class="p-6 bg-gradient-to-br from-blue-600 to-blue-800">
                <h1 class="text-xl font-bold text-white mb-3">هنرستان سپهری راد</h1>
                <div class="flex items-center gap-3 bg-white bg-opacity-20 rounded-lg p-3">
                    <div class="w-10 h-10 bg-white text-blue-600 rounded-full flex items-center justify-center font-bold text-lg">
                        <?php echo mb_substr($first_name, 0, 1, 'UTF-8') . mb_substr($last_name, 0, 1, 'UTF-8'); ?>
                    </div>
                    <div>
                        <div class="font-medium text-white text-sm">
                            <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?>
                        </div>
                        <div class="text-xs text-blue-100">
                            دبیر
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="today_classes.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            کلاس‌های امروز
                        </a>
                    </li>
                    <li>
                        <a href="weekly_schedule.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            برنامه هفتگی
                        </a>
                    </li>
                    <li>
                        <a href="attendance_history.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            تاریخچه حضور
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            گزارش‌ها
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="../logout.php"
                    onclick="return confirm('آیا می‌خواهید خارج شوید؟')"
                    class="w-full flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    خروج
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="min-h-screen lg:mr-64">
        <div class="w-full min-h-screen py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">کلاس‌های امروز</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <span class="text-blue-600 font-medium"> <?php echo $today_persian; ?> <?php echo $today_jalali_formatted; ?></span>
                    </p>
                </div>

                <?php if ($is_holiday): ?>
                    <!-- صفحه تعطیل -->
                    <div class="holiday-bg rounded-xl shadow-lg overflow-hidden mb-8">
                        <div class="p-12 text-center text-white">
                            <div class="text-6xl mb-6">🎉</div>
                            <h2 class="text-3xl font-bold mb-4">امروز تعطیل هستید!</h2>
                            <p class="text-xl opacity-90 mb-8">هیچ کلاسی برای امروز برنامه‌ریزی نشده است.</p>

                            <?php if ($next_class): ?>
                                <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-xl p-6 inline-block">
                                    <div class="text-lg font-medium mb-2">اولین کلاس بعدی:</div>
                                    <div class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($next_class['class_name']); ?></div>
                                    <div class="flex items-center justify-center space-x-4 space-x-reverse">
                                        <div class="bg-white bg-opacity-30 px-4 py-2 rounded-lg">
                                            📅 <?php echo $next_class['day_of_week']; ?>
                                        </div>
                                        <div class="bg-white bg-opacity-30 px-4 py-2 rounded-lg">
                                            ⏰ زنگ <?php echo $next_class['schedule']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- اقدامات جایگزین -->
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">اقدامات جایگزین</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <a href="attendance_history.php"
                                class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-blue-500 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-blue-100 rounded-lg">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 text-sm">بررسی تاریخچه</h3>
                                        <p class="text-gray-500 text-xs">مشاهده و ویرایش حضور و غیاب‌های قبلی</p>
                                    </div>
                                </div>
                            </a>

                            <a href="reports.php"
                                class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-green-500 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-green-100 rounded-lg">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 text-sm">گزارش‌گیری</h3>
                                        <p class="text-gray-500 text-xs">تحلیل آمار و عملکرد کلاس‌ها</p>
                                    </div>
                                </div>
                            </a>

                            <a href="weekly_schedule.php"
                                class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-purple-500 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-purple-100 rounded-lg">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 text-sm">برنامه هفتگی</h3>
                                        <p class="text-gray-500 text-xs">مشاهده کامل برنامه هفتگی</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- لیست کلاس‌های امروز -->
                    <div class="mb-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($today_classes as $class):
                                $present_percentage = $class['student_count'] > 0
                                    ? round(($class['present_count'] / $class['student_count']) * 100, 1)
                                    : 0;
                            ?>
                                <div class="bg-white rounded-xl shadow-lg overflow-hidden class-card border border-gray-200">
                                    <!-- هدر کارت -->
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 text-white">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-bold text-lg"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                                <div class="text-sm opacity-90 mt-1">زنگ <?php echo htmlspecialchars($class['schedule']); ?></div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- بدنه کارت -->
                                    <div class="p-4">
                                        <!-- آمار کلاس -->
                                        <div class="mb-4">
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span>وضعیت حضور</span>
                                                <span><?php echo $present_percentage; ?>%</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill <?php echo $present_percentage >= 80 ? 'bg-green-500' : ($present_percentage >= 50 ? 'bg-yellow-500' : 'bg-red-500'); ?>"
                                                    style="width: <?php echo min($present_percentage, 100); ?>%"></div>
                                            </div>

                                            <div class="flex justify-between text-xs text-gray-500 mt-2">
                                                <span>حاضر: <?php echo $class['present_count']; ?></span>
                                                <span>کل: <?php echo $class['student_count']; ?></span>
                                            </div>
                                        </div>

                                        <!-- وضعیت -->
                                        <div class="mb-4">
                                            <?php if ($class['present_count'] == $class['student_count']): ?>
                                                <div class="flex items-center text-green-600 bg-green-50 p-2 rounded-lg">
                                                    <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="font-medium">تمام دانش‌آموزان حاضرند</span>
                                                </div>
                                            <?php elseif ($class['present_count'] == 0): ?>
                                                <div class="flex items-center text-red-600 bg-red-50 p-2 rounded-lg">
                                                    <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="font-medium">هیچ‌کس حاضر نیست</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center text-blue-600 bg-blue-50 p-2 rounded-lg">
                                                    <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="font-medium"><?php echo $class['student_count'] - $class['present_count']; ?> نفر غایب</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- دکمه‌های اقدام -->
                                        <div class="flex space-x-3 space-x-reverse">
                                            <a href="attendance.php?program_id=<?php echo $class['program_id']; ?>"
                                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-center">
                                                ثبت حضور و غیاب
                                            </a>

                                            <a href="attendance_history.php?class_id=<?php echo $class['class_id']; ?>&date=<?php echo $today; ?>"
                                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-center">
                                                تاریخچه
                                            </a>
                                        </div>
                                    </div>

                                    <!-- فوتر کارت -->
                                    <div class="bg-gray-50 px-4 py-2 border-t border-gray-100 text-xs text-gray-500">
                                        <div class="flex justify-between">
                                            <span>کلاس ID: <?php echo $class['class_id']; ?></span>
                                            <span>برنامه ID: <?php echo $class['program_id']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
        }

        // رفرش خودکار صفحه هر 5 دقیقه
        setTimeout(function() {
            location.reload();
        }, 5 * 60 * 1000); // 5 دقیقه
    </script>
</body>

</html>