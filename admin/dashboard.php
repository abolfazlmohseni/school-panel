<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';


$stmt = $conn->prepare("SELECT COUNT(*) as total FROM students");
$stmt->execute();
$result = $stmt->get_result();
$total_students = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM classes");
$stmt->execute();
$result = $stmt->get_result();
$total_classes = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'");
$stmt->execute();
$result = $stmt->get_result();
$total_teachers = $result->fetch_assoc()['total'];
$stmt->close();

$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'حاضر' THEN 1 ELSE 0 END) as present_count
    FROM attendance 
    WHERE attendance_date = ?
");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$attendance_data = $result->fetch_assoc();
$stmt->close();

$today_attendance_rate = $attendance_data['total_records'] > 0
    ? round(($attendance_data['present_count'] / $attendance_data['total_records']) * 100, 0)
    : 0;


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

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT p.id) as total 
    FROM programs p 
    WHERE p.day_of_week = ?
");
$stmt->bind_param("s", $today_persian);
$stmt->execute();
$result = $stmt->get_result();
$today_classes_count = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.attendance_date,
        a.status,
        s.first_name as student_first_name,
        s.last_name as student_last_name,
        c.name as class_name,
        u.first_name as teacher_first_name,
        u.last_name as teacher_last_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN programs p ON a.program_id = p.id
    JOIN classes c ON p.class_id = c.id
    JOIN users u ON a.teacher_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
$recent_attendance = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        p.id as program_id,
        c.name as class_name,
        p.schedule,
        u.first_name as teacher_first_name,
        u.last_name as teacher_last_name,
        (
            SELECT COUNT(*) 
            FROM students s 
            WHERE s.class_id = c.id
        ) as student_count
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    JOIN users u ON p.teacher_id = u.id
    WHERE p.day_of_week = ?
    ORDER BY p.schedule
");
$stmt->bind_param("s", $today_persian);
$stmt->execute();
$result = $stmt->get_result();
$today_classes_detail = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        id,
        first_name,
        last_name,
        username
    FROM users 
    WHERE role = 'teacher'
    ORDER BY first_name
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
$active_teachers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

$today_parts = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_parts[0], $today_parts[1], $today_parts[2]);
$today_jalali_formatted = $today_jalali[0] . '/' . sprintf('%02d', $today_jalali[1]) . '/' . sprintf('%02d', $today_jalali[2]);
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>داشبورد</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
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

        .quick-action-card {
            transition: all 0.2s ease;
        }

        .quick-action-card:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .attendance-badge-present {
            background-color: #d1fae5;
            color: #065f46;
        }

        .attendance-badge-absent {
            background-color: #fee2e2;
            color: #991b1b;
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
            <!-- Logo & Title -->
            <div class="p-6 bg-gradient-to-br from-blue-600 to-blue-800">
                <h1 class="text-xl font-bold text-white mb-1">هنرستان سپهری راد</h1>
                <p class="text-blue-100 text-sm">سامانه حضور و غیاب</p>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600  rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="teachers.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            بخش دبیران
                        </a>
                    </li>
                    <li>
                        <a href="classes.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            کلاس ها
                        </a>
                    </li>
                    <li>
                        <a href="students.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            دانش آموزان
                        </a>
                    </li>
                    <li>
                        <a href="programs.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            برنامه زمانی
                        </a>
                    </li>
                    <li>
                        <a href="today_absent.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            غایبین امروز
                        </a>
                    </li>
                    <li>
                        <a href="send_sms.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">داشبورد مدیریت</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        خوش آمدید <?php echo htmlspecialchars($full_name); ?>،
                        <span class="text-blue-600 font-medium">امروز <?php echo $today_persian; ?> <?php echo $today_jalali_formatted; ?></span>
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <!-- Total Students Card -->
                    <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                    </path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                +<?php echo $total_students > 0 ? round($total_students / 10) : 0; ?>%
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_students; ?></h3>
                        <p class="text-blue-100 text-sm">تعداد دانش‌آموزان</p>
                    </div>

                    <!-- Total Classes Card -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                <?php echo $today_classes_count; ?> امروز
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_classes; ?></h3>
                        <p class="text-green-100 text-sm">تعداد کلاس‌ها</p>
                    </div>

                    <!-- Total Teachers Card -->
                    <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                <?php echo count($active_teachers); ?> فعال
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_teachers; ?></h3>
                        <p class="text-purple-100 text-sm">تعداد دبیران</p>
                    </div>

                    <!-- Today's Attendance Card -->
                    <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                <?php echo $attendance_data['total_records'] ?? 0; ?> ثبت
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $today_attendance_rate; ?>%</h3>
                        <p class="text-orange-100 text-sm">حضور امروز</p>
                    </div>
                </div>

                <!-- دو ستون اصلی -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- کلاس‌های امروز -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                کلاس‌های امروز (<?php echo $today_persian; ?>)
                            </h2>
                        </div>
                        <div class="p-6">
                            <?php if (count($today_classes_detail) > 0): ?>
                                <div class="space-y-4">
                                    <?php foreach ($today_classes_detail as $class): ?>
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                                    <div class="text-sm text-gray-600">
                                                        دبیر: <?php echo htmlspecialchars($class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-medium text-gray-900">زنگ <?php echo htmlspecialchars($class['schedule']); ?></div>
                                                <div class="text-sm text-gray-600"><?php echo $class['student_count']; ?> دانش‌آموز</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 text-center">
                                    <a href="programs.php?day=<?php echo urlencode($today_persian); ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        مشاهده همه کلاس‌های امروز →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p>امروز کلاسی برنامه‌ریزی نشده است.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- آخرین حضور و غیاب‌ها -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                آخرین حضور و غیاب‌ها
                            </h2>
                        </div>
                        <div class="p-6">
                            <?php if (count($recent_attendance) > 0): ?>
                                <div class="space-y-3">
                                    <?php foreach ($recent_attendance as $record): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <span class="text-sm font-medium">
                                                        <?php echo mb_substr($record['student_first_name'], 0, 1, 'UTF-8') . mb_substr($record['student_last_name'], 0, 1, 'UTF-8'); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($record['student_first_name'] . ' ' . $record['student_last_name']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-600"><?php echo htmlspecialchars($record['class_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $record['status'] === 'حاضر' ? 'attendance-badge-present' : 'attendance-badge-absent'; ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?php echo htmlspecialchars($record['teacher_first_name'] . ' ' . $record['teacher_last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 text-center">
                                    <a href="attendance_report.php" class="text-green-600 hover:text-green-800 text-sm font-medium">
                                        مشاهده گزارش کامل حضور و غیاب →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p>هنوز حضور و غیابی ثبت نشده است.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">دسترسی سریع</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Add Class -->
                        <a href="class_add.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-blue-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">افزودن کلاس</h3>
                                    <p class="text-gray-500 text-xs">ثبت کلاس جدید</p>
                                </div>
                            </div>
                        </a>

                        <!-- Add Student -->
                        <a href="students_add.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-green-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">افزودن دانش‌آموز</h3>
                                    <p class="text-gray-500 text-xs">ثبت دانش‌آموز جدید</p>
                                </div>
                            </div>
                        </a>

                        <!-- View Schedule -->
                        <a href="programs.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-purple-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-purple-100 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">برنامه زمانی</h3>
                                    <p class="text-gray-500 text-xs">مشاهده برنامه</p>
                                </div>
                            </div>
                        </a>

                        <!-- View Teachers -->
                        <a href="teachers.php" class="quick-action-card bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-orange-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-orange-100 rounded-lg">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">لیست دبیران</h3>
                                    <p class="text-gray-500 text-xs">مشاهده دبیران</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- دبیران فعال -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            دبیران فعال
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (count($active_teachers) > 0): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                                <?php foreach ($active_teachers as $teacher): ?>
                                    <div class="text-center">
                                        <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-purple-200 rounded-full mx-auto mb-3 flex items-center justify-center">
                                            <span class="text-xl font-bold text-purple-700">
                                                <?php echo mb_substr($teacher['first_name'], 0, 1, 'UTF-8') . mb_substr($teacher['last_name'], 0, 1, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($teacher['first_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['last_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($teacher['username']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-6 text-center">
                                <a href="teachers.php" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition text-sm font-medium">
                                    مشاهده همه دبیران
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                <p>هنوز دبیری ثبت نشده است.</p>
                                <a href="teacher_add.php" class="mt-3 inline-block px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm">
                                    افزودن اولین دبیر
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
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
        }, 2 * 60 * 1000);
    </script>
</body>

</html>