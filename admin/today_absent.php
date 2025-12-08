<?php
session_start();
require_once '../config.php';

// چک ورود مدیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// در send_sms.php بعد از session_start()
$error = '';
if (isset($_SESSION['sms_error'])) {
    $error = $_SESSION['sms_error'];
    unset($_SESSION['sms_error']);
}

$admin_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'مدیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// تاریخ امروز
$today = date('Y-m-d');

// ---------- دریافت غایبین امروز ----------
$query = "
    SELECT 
        a.id,
        a.attendance_date,
        a.status,
        s.first_name,
        s.last_name,
        s.national_code,
        s.phone,
        c.name as class_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE a.attendance_date = ?
    AND a.status = 'غایب'
    ORDER BY s.last_name, s.first_name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$absent_today = [];
while ($row = $result->fetch_assoc()) {
    $absent_today[] = $row;
}
$stmt->close();

// ---------- آمار ----------
$total_absent = count($absent_today);

// شمارش دانش‌آموزانی که شماره تلفن دارند
$with_phone = 0;
foreach ($absent_today as $student) {
    if (!empty($student['phone'])) {
        $with_phone++;
    }
}

// ---------- تاریخ شمسی ----------
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

$weekday_number = date('w');
$today_persian = $weekdays_persian[$weekday_number];
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>غایبین امروز - سامانه حضور غیاب هنرستان سپهری راد</title>
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

        .student-row {
            transition: background-color 0.2s ease;
        }

        .student-row:hover {
            background-color: #f8fafc;
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
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
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
                        <a href="today_absent.php" class="flex items-center gap-3 px-4 py-3 text-white bg-red-600 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            غایبین امروز
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
                <a href="/attendance-system/logout.php"
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">غایبین امروز</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <span class="text-blue-600 font-medium">امروز <?php echo $today_persian; ?> <?php echo $today_jalali_formatted; ?> </span>
                    </p>
                </div>
                <!-- بعد از هدر در send_sms.php -->
                <?php if (!empty($error)): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-600 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <span class="text-red-700 font-medium">خطا: <?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <!-- Total Absent Card -->
                    <div class="stat-card bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                امروز
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_absent; ?></h3>
                        <p class="text-red-100 text-sm">تعداد غایبین</p>
                    </div>

                    <!-- Students With Phone Card -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                قابل اطلاع‌رسانی
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $with_phone; ?></h3>
                        <p class="text-green-100 text-sm">دارای شماره تماس</p>
                    </div>

                    <!-- بدون شماره Card -->
                    <div class="stat-card bg-gradient-to-br from-gray-500 to-gray-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                نیاز به ثبت
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_absent - $with_phone; ?></h3>
                        <p class="text-gray-100 text-sm">فاقد شماره تماس</p>
                    </div>

                    <!-- کلاس‌های دارای غایب Card -->
                    <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                کلاس‌های مختلف
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1">
                            <?php
                            // شمارش کلاس‌های منحصر به فرد
                            $unique_classes = [];
                            foreach ($absent_today as $student) {
                                if (!empty($student['class_name'])) {
                                    $unique_classes[$student['class_name']] = true;
                                }
                            }
                            echo count($unique_classes);
                            ?>
                        </h3>
                        <p class="text-orange-100 text-sm">کلاس دارای غایب</p>
                    </div>
                </div>

                <!-- Main Card - لیست غایبین -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                    <div class="p-4 sm:p-6">
                        <!-- Action Bar -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">
                                لیست دانش‌آموزان غایب امروز
                                <?php if ($total_absent > 0): ?>
                                    <span class="text-red-600 font-bold">(<?php echo $total_absent; ?> نفر)</span>
                                <?php endif; ?>
                            </h2>
                            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                <?php if ($total_absent > 0 && $with_phone > 0): ?>
                                    <a href="send_sms.php?type=absent_today&date=<?php echo $today; ?>"
                                        class="px-4 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors duration-200 text-center text-sm sm:text-base">
                                        ارسال پیامک گروهی
                                    </a>
                                <?php endif; ?>

                            </div>
                        </div>

                        <!-- Table Container -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b-2 border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">#</th>
                                        <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">دانش‌آموز</th>
                                        <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">کد ملی</th>
                                        <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">کلاس</th>
                                        <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap hidden md:table-cell">شماره تماس</th>
                                        <!-- <th class="px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">اقدامات</th> -->
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($total_absent > 0): ?>
                                        <?php foreach ($absent_today as $index => $student): ?>
                                            <tr class="student-row hover:bg-gray-50 transition-colors duration-150">
                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 font-medium whitespace-nowrap">
                                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap font-mono">
                                                    <?= htmlspecialchars($student['national_code']) ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">
                                                    <?= htmlspecialchars($student['class_name'] ?? 'نامشخص') ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap hidden md:table-cell">
                                                    <?php if (!empty($student['phone'])): ?>
                                                        <span class="text-green-600 font-medium">
                                                            <?= htmlspecialchars($student['phone']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">ندارد</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    <div class="flex gap-2 justify-center flex-wrap">
                                                        <?php if (!empty($student['phone'])): ?>
                                                            <a href="send_sms.php?phone=<?= urlencode($student['phone']) ?>&name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>"
                                                                class="px-3 py-1.5 bg-green-500 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-green-600 transition-colors duration-200">
                                                                ارسال پیامک
                                                            </a>
                                                        <?php endif; ?>

                                                        <a href="student_details.php?id=<?= $student['id'] ?>"
                                                            class="px-3 py-1.5 bg-blue-500 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-blue-600 transition-colors duration-200">
                                                            جزئیات
                                                        </a>

                                                        <a href="student_edit.php?id=<?= $student['id'] ?>"
                                                            class="px-3 py-1.5 bg-yellow-500 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-yellow-600 transition-colors duration-200">
                                                            ویرایش
                                                        </a>
                                                    </div>
                                                </td> -->
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center px-4 py-8 text-gray-500 text-sm sm:text-base">
                                                <div class="flex flex-col items-center justify-center">
                                                    <svg class="w-16 h-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <p class="text-lg font-medium text-gray-700 mb-2">هیچ غایبی برای امروز ثبت نشده است!</p>
                                                    <p class="text-gray-600">امروز همه دانش‌آموزان در کلاس‌های خود حاضر بودند.</p>
                                                    <a href="dashboard.php" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                                        بازگشت به داشبورد
                                                    </a>
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
                <div class="mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 text-xs sm:text-sm flex items-start">
                        <svg class="w-5 h-5 ml-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        لیست غایبین امروز به صورت خودکار بروز می‌شود. برای اطلاع‌رسانی به والدین از قابلیت ارسال پیامک استفاده کنید.
                    </p>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">دسترسی سریع</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Report -->
                        <a href="reports.php" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-blue-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">گزارشات جامع</h3>
                                    <p class="text-gray-500 text-xs">مشاهده آمار کامل</p>
                                </div>
                            </div>
                        </a>

                        <!-- Attendance -->
                        <a href="attendance_report.php" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-green-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">حضور و غیاب</h3>
                                    <p class="text-gray-500 text-xs">ثبت و مدیریت</p>
                                </div>
                            </div>
                        </a>

                        <!-- SMS History -->
                        <a href="sms_history.php" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-purple-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-purple-100 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">تاریخچه پیامک</h3>
                                    <p class="text-gray-500 text-xs">پیامک‌های ارسالی</p>
                                </div>
                            </div>
                        </a>

                        <!-- Edit Students -->
                        <a href="students.php" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-orange-500 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-orange-100 rounded-lg">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">مدیریت دانش‌آموزان</h3>
                                    <p class="text-gray-500 text-xs">ویرایش اطلاعات</p>
                                </div>
                            </div>
                        </a>
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

        // تابع خروجی اکسل
        function exportToExcel() {
            // ایجاد یک جدول موقت برای خروجی
            const table = document.createElement('table');
            const header = table.createTHead();
            const headerRow = header.insertRow();

            // اضافه کردن هدرها
            const headers = ['ردیف', 'نام و نام خانوادگی', 'کد ملی', 'کلاس', 'شماره تماس', 'وضعیت'];
            headers.forEach(headerText => {
                const th = document.createElement('th');
                th.textContent = headerText;
                headerRow.appendChild(th);
            });

            // اضافه کردن داده‌ها
            const tbody = table.createTBody();
            <?php foreach ($absent_today as $index => $student): ?>
                const row = tbody.insertRow();
                const cells = [
                    <?php echo $index + 1; ?>,
                    '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>',
                    '<?php echo htmlspecialchars($student['national_code']); ?>',
                    '<?php echo htmlspecialchars($student['class_name'] ?? 'نامشخص'); ?>',
                    '<?php echo htmlspecialchars($student['phone'] ?? 'ندارد'); ?>',
                    '<?php echo !empty($student['phone']) ? 'قابل پیامک' : 'فاقد شماره'; ?>'
                ];

                cells.forEach(cellData => {
                    const cell = row.insertCell();
                    cell.textContent = cellData;
                });
            <?php endforeach; ?>

            // تبدیل به CSV
            let csv = [];
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('th, td');
                cells.forEach(cell => {
                    rowData.push(`"${cell.textContent}"`);
                });
                csv.push(rowData.join(','));
            });

            const csvContent = csv.join('\n');
            const blob = new Blob(['\uFEFF' + csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `غایبین_امروز_<?php echo $today; ?>.csv`;
            link.click();

            alert('فایل اکسل با موفقیت دانلود شد.');
        }

        // آپدیت خودکار هر 5 دقیقه
        setTimeout(function() {
            location.reload();
        }, 5 * 60 * 1000);
    </script>
</body>

</html>