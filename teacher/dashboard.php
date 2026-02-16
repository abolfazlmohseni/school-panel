<?php
session_start();
require_once '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$first_name = $_SESSION['first_name'] ?? 'دبیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
$teacher_id = $_SESSION['user_id'];

$weekdays_persian = [
    0 => 'یکشنبه',
    1 => 'دوشنبه',
    2 => 'سه‌شنبه',
    3 => 'چهارشنبه',
    4 => 'پنج‌شنبه',
    5 => 'جمعه',
    6 => 'شنبه'
];

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

$stmt = $conn->prepare("
    SELECT 
        p.id as program_id, 
        c.name as class_name, 
        p.schedule, 
        p.day_of_week,
        c.id as class_id
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    WHERE p.teacher_id = ?
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
        END,
        p.schedule
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$programs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$weekday_number = date('w'); // 0=یکشنبه, 1=دوشنبه, ...
$today_persian = $weekdays_persian[$weekday_number];
$today_classes = array_filter($programs, function ($p) use ($today_persian) {
    return $p['day_of_week'] === $today_persian;
});

$grouped_by_day = [];
foreach ($programs as $program) {
    $day = $program['day_of_week'];
    if (!isset($grouped_by_day[$day])) {
        $grouped_by_day[$day] = [];
    }
    $grouped_by_day[$day][] = $program;
}

$total_students_today = 0;
$total_present_today = 0;

foreach ($today_classes as $class) {
    $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM students WHERE class_id = ?");
    $stmt->bind_param("i", $class['class_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_stats = $result->fetch_assoc();
    $stmt->close();

    $total_students_today += $class_stats['student_count'];

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT a.student_id) as present_count
        FROM attendance a
        WHERE a.program_id = ? 
        AND a.attendance_date = ?
        AND a.status = 'حاضر'
    ");
    $stmt->bind_param("is", $class['program_id'], $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_stats = $result->fetch_assoc();
    $stmt->close();

    $total_present_today += $attendance_stats['present_count'];
}

$attendance_rate_today = $total_students_today > 0
    ? round(($total_present_today / $total_students_today) * 100, 0)
    : 0;

$persian_days_order = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>داشبورد دبیر</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../styles/output.css">
    <style>
        body {
            box-sizing: border-box;
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
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-30 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-hidden lg:sidebar-hidden-false fixed top-0 right-0 h-full w-64 bg-white shadow-xl z-40">
        <div class="h-full flex flex-col">
            <!-- Logo & User Info -->
           <div class="p-6 bg-gradient-to-br from-blue-600 to-blue-800">
                <h1 class="text-xl font-bold text-white mb-3">هنرستان سپهری راد</h1>
                <div class="flex items-center gap-3 bg-white/20 rounded-lg p-3">
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
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="today_classes.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">داشبورد دبیر</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        خوش آمدید <?php echo htmlspecialchars($full_name); ?>،
                        <span class="text-blue-600 font-medium">امروز <?php echo $today_persian; ?> <?php echo $today_jalali_formatted; ?></span>
                    </p>
                </div>

                <!-- آمار امروز -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <!-- کلاس‌های امروز -->
                    <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo count($today_classes); ?></h3>
                        <p class="text-blue-100 text-sm">کلاس‌های امروز</p>
                    </div>

                    <!-- دانش‌آموزان امروز -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_students_today; ?></h3>
                        <p class="text-green-100 text-sm">دانش‌آموزان امروز</p>
                    </div>

                    <!-- حاضرین امروز -->
                    <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_present_today; ?></h3>
                        <p class="text-purple-100 text-sm">حاضرین امروز</p>
                    </div>

                    <!-- درصد حضور امروز -->
                    <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $attendance_rate_today; ?>%</h3>
                        <p class="text-orange-100 text-sm">میانگین حضور</p>
                    </div>
                </div>

                <!-- بخش کلاس‌های امروز -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                        <h2 class="text-xl font-semibold text-gray-700">
                            کلاس‌های امروز
                            <span class="text-blue-600">(<?php echo $today_persian; ?>)</span>
                        </h2>
                        <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                            <?php echo count($today_classes); ?> کلاس
                        </span>
                    </div>

                    <?php if (count($today_classes) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($today_classes as $class):
                                $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM students WHERE class_id = ?");
                                $stmt->bind_param("i", $class['class_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $class_stats = $result->fetch_assoc();
                                $stmt->close();

                                $stmt = $conn->prepare("
                                    SELECT COUNT(DISTINCT a.student_id) as present_count
                                    FROM attendance a
                                    WHERE a.program_id = ? 
                                    AND a.attendance_date = ?
                                    AND a.status = 'حاضر'
                                ");
                                $stmt->bind_param("is", $class['program_id'], $today);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $attendance_stats = $result->fetch_assoc();
                                $stmt->close();

                                $present_count = $attendance_stats['present_count'] ?? 0;
                                $absent_count = $class_stats['student_count'] - $present_count;
                            ?>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 hover:bg-blue-100 transition duration-200">
                                    <div>
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($class['class_name']); ?></span>
                                        <span class="text-gray-600 mx-3">•</span>
                                        <span class="text-gray-700">زنگ <?php echo htmlspecialchars($class['schedule']); ?></span>
                                        <div class="mt-2 text-sm text-gray-600">
                                            <?php echo $present_count; ?> حاضر | <?php echo $absent_count; ?> غایب
                                        </div>
                                    </div>
                                    <a href="attendance.php?program_id=<?php echo $class['program_id']; ?>"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 font-medium text-sm whitespace-nowrap">
                                        ثبت حضور و غیاب
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>امروز کلاسی ندارید. روز خوبی داشته باشید!</p>
                            <a href="weekly_schedule.php" class="mt-3 inline-block text-blue-600 hover:text-blue-800 text-sm">
                                مشاهده برنامه هفتگی →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- دسترسی سریع -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">دسترسی سریع</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- برنامه هفتگی -->
                        <a href="weekly_schedule.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-blue-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">برنامه هفتگی</h3>
                                    <p class="text-gray-500 text-xs">مشاهده برنامه کلاسی</p>
                                </div>
                            </div>
                        </a>

                        <!-- تاریخچه حضور -->
                        <a href="attendance_history.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-green-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">تاریخچه حضور</h3>
                                    <p class="text-gray-500 text-xs">مشاهده سوابق حضور</p>
                                </div>
                            </div>
                        </a>

                        <!-- گزارش‌ها -->
                        <a href="reports.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-purple-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-purple-100 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">گزارش‌ها</h3>
                                    <p class="text-gray-500 text-xs">آمار و تحلیل کلاس‌ها</p>
                                </div>
                            </div>
                        </a>

                        <!-- کلاس‌های امروز -->
                        <a href="today_classes.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-orange-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-orange-100 rounded-lg">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">کلاس‌های امروز</h3>
                                    <p class="text-gray-500 text-xs">نمایش کامل کلاس‌های امروز</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">برنامه هفتگی شما</h2>

                    <?php if (count($programs) > 0): ?>
                        <div class="space-y-6">
                            <?php foreach ($persian_days_order as $day): ?>
                                <?php if (isset($grouped_by_day[$day]) && count($grouped_by_day[$day]) > 0): ?>
                                    <div>
                                        <div class="flex items-center mb-3">
                                            <h3 class="font-medium text-gray-800 text-lg">
                                                <?php echo $day; ?>
                                                <?php if ($day === $today_persian): ?>
                                                    <span class="mr-2 text-sm bg-green-100 text-green-800 px-2 py-0.5 rounded">امروز</span>
                                                <?php endif; ?>
                                            </h3>
                                            <span class="mr-2 text-gray-500 text-sm">
                                                (<?php echo count($grouped_by_day[$day]); ?> کلاس)
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <?php foreach ($grouped_by_day[$day] as $program): ?>
                                                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-sm transition duration-200">
                                                    <div class="font-medium text-gray-800 mb-1">
                                                        <?php echo htmlspecialchars($program['class_name']); ?>
                                                    </div>
                                                    <div class="text-gray-600 text-sm mb-3">
                                                         <?php echo htmlspecialchars($program['schedule']); ?>
                                                    </div>
                                                    <a href="attendance.php?program_id=<?php echo $program['program_id']; ?>"
                                                        class="inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200 transition duration-200">
                                                        مدیریت کلاس
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php if ($day !== end($persian_days_order)): ?>
                                        <hr class="my-4 border-gray-100">
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-6 text-center">
                            <a href="weekly_schedule.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                مشاهده برنامه هفتگی کامل →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p>هنوز برنامه‌ای برای شما ثبت نشده است.</p>
                        </div>
                    <?php endif; ?>
                </div>
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

        setTimeout(function() {
            location.reload();
        }, 3 * 60 * 1000);
    </script>
</body>

</html>