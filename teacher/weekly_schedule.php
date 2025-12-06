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

// دریافت برنامه هفتگی
$stmt = $conn->prepare("
    SELECT 
        p.id as program_id,
        c.name as class_name,
        p.day_of_week,
        p.schedule,
        p.created_at,
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
$schedule = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// گروه‌بندی بر اساس روز
$grouped_schedule = [];
foreach ($schedule as $item) {
    $day = $item['day_of_week'];
    if (!isset($grouped_schedule[$day])) {
        $grouped_schedule[$day] = [];
    }
    $grouped_schedule[$day][] = $item;
}

// آمار کلاس‌ها
$total_classes = count($schedule);
$total_days = count($grouped_schedule);
$max_classes_per_day = 0;
foreach ($grouped_schedule as $day_classes) {
    $max_classes_per_day = max($max_classes_per_day, count($day_classes));
}

// ترتیب روزهای هفته فارسی
$days_order = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>برنامه هفتگی - سامانه حضور غیاب</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
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

        .day-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .day-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .class-item {
            transition: all 0.2s ease;
        }

        .class-item:hover {
            background-color: #f0f9ff;
            border-color: #3b82f6;
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
                        <a href="today_classes.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            کلاس‌های امروز
                        </a>
                    </li>
                    <li>
                        <a href="weekly_schedule.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">برنامه هفتگی</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        برنامه کلاسی <?php echo htmlspecialchars($full_name); ?>،
                        <span class="text-blue-600 font-medium">امروز <?php echo $today_persian; ?> <?php echo $today_jalali_formatted; ?></span>
                    </p>
                </div>

                <!-- آمار -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 text-sm">کل کلاس‌ها</div>
                                <div class="text-2xl font-bold mt-1"><?php echo $total_classes; ?></div>
                            </div>
                            <div class="text-blue-500">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 text-sm">روزهای کلاسی</div>
                                <div class="text-2xl font-bold mt-1"><?php echo $total_days; ?></div>
                            </div>
                            <div class="text-green-500">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 text-sm">بیشترین کلاس در روز</div>
                                <div class="text-2xl font-bold mt-1"><?php echo $max_classes_per_day; ?></div>
                            </div>
                            <div class="text-purple-500">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- برنامه هفتگی -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">برنامه کلاسی هفتگی</h2>

                    <?php if ($total_classes > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($days_order as $day):
                                $day_classes = $grouped_schedule[$day] ?? [];
                                $is_today = ($day === $today_persian);
                            ?>
                                <div class="bg-white rounded-xl shadow overflow-hidden day-card border <?php echo $is_today ? 'border-blue-300' : 'border-gray-200'; ?>">
                                    <!-- هدر روز -->
                                    <div class="<?php echo $is_today ? 'bg-gradient-to-r from-blue-500 to-blue-600' : 'bg-gradient-to-r from-gray-600 to-gray-800'; ?> text-white p-4">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h3 class="text-lg font-bold flex items-center">
                                                    <?php echo $day; ?>
                                                    <?php if ($is_today): ?>
                                                        <span class="mr-2 text-xs bg-yellow-400 text-gray-800 px-2 py-0.5 rounded-full today-badge">امروز</span>
                                                    <?php endif; ?>
                                                </h3>
                                                <p class="text-sm opacity-90 mt-1">
                                                    <?php echo count($day_classes); ?> کلاس
                                                </p>
                                            </div>
                                            <div class="text-2xl">
                                                <?php if ($is_today): ?>
                                                    📅
                                                <?php elseif (count($day_classes) > 0): ?>
                                                    📚
                                                <?php else: ?>
                                                    🌴
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- لیست کلاس‌های این روز -->
                                    <div class="divide-y divide-gray-100">
                                        <?php if (count($day_classes) > 0): ?>
                                            <?php foreach ($day_classes as $class):
                                                // تعداد دانش‌آموزان این کلاس
                                                $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM students WHERE class_id = ?");
                                                $stmt->bind_param("i", $class['class_id']);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $class_stats = $result->fetch_assoc();
                                                $stmt->close();
                                            ?>
                                                <div class="p-4 hover:bg-blue-50 class-item border-l-4 border-blue-300">
                                                    <div class="flex justify-between items-start">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-600 mt-1">
                                                                زنگ <?php echo htmlspecialchars($class['schedule']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-2">
                                                                <?php echo $class_stats['student_count']; ?> دانش‌آموز
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-col space-y-2">
                                                            <?php if ($is_today): ?>
                                                                <a href="attendance.php?program_id=<?php echo $class['program_id']; ?>"
                                                                    class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition whitespace-nowrap">
                                                                    ثبت حضور
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="attendance_history.php?program_id=<?php echo $class['program_id']; ?>"
                                                                class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200 transition whitespace-nowrap">
                                                                تاریخچه
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="p-8 text-center text-gray-400">
                                                <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <p class="text-gray-500">کلاسی در این روز ندارید</p>
                                                <?php if ($is_today): ?>
                                                    <p class="text-sm text-gray-400 mt-1">روز خوبی داشته باشید!</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- فوتر کارت -->
                                    <?php if (count($day_classes) > 0): ?>
                                        <div class="bg-gray-50 px-4 py-2 border-t border-gray-100">
                                            <div class="flex justify-between text-xs text-gray-500">
                                                <span>کلاس: <?php echo count($day_classes); ?></span>
                                                <span>زنگ:
                                                    <?php
                                                    $schedules = array_map(function ($c) {
                                                        return $c['schedule'];
                                                    }, $day_classes);
                                                    echo implode('، ', $schedules);
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- نکات مهم -->
                        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
                            <h3 class="font-bold text-blue-800 mb-3 flex items-center">
                                <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                نکات مهم
                            </h3>
                            <ul class="text-sm text-blue-700 space-y-2">
                                <li class="flex items-start">
                                    <span class="text-blue-500 ml-2">•</span>
                                    <span>برای ثبت حضور و غیاب روی دکمه "ثبت حضور" در روزهای کلاسی کلیک کنید</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="text-blue-500 ml-2">•</span>
                                    <span>روزهای بدون کلاس با آیکون 🌴 مشخص شده‌اند</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="text-blue-500 ml-2">•</span>
                                    <span>برای مشاهده تاریخچه حضور و غیاب هر کلاس، روی دکمه "تاریخچه" کلیک کنید</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="text-blue-500 ml-2">•</span>
                                    <span>امروز <span class="font-bold"><?php echo $today_persian; ?></span> است و با رنگ آبی مشخص شده</span>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                            <div class="text-gray-400 mb-4">
                                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">هنوز برنامه‌ای ثبت نشده است</h3>
                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                هیچ برنامه کلاسی برای شما تنظیم نشده است. لطفاً با مدیر سیستم تماس بگیرید.
                            </p>
                            <div class="space-x-4">
                                <a href="dashboard.php"
                                    class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                    بازگشت به داشبورد
                                </a>
                                <a href="today_classes.php"
                                    class="inline-block px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                                    کلاس‌های امروز
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- دکمه چاپ -->
                <?php if ($total_classes > 0): ?>
                    <div class="text-center">
                        <button onclick="window.print()"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            🖨️ چاپ برنامه هفتگی
                        </button>
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

        // استایل برای چاپ
        const printStyle = document.createElement('style');
        printStyle.textContent = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .max-w-7xl, .max-w-7xl * {
                    visibility: visible;
                }
                .max-w-7xl {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    padding: 0;
                }
                button, a, .sidebar, .lg\\:hidden {
                    display: none !important;
                }
                .grid {
                    display: grid !important;
                }
            }
        `;
        document.head.appendChild(printStyle);
    </script>
</body>

</html>