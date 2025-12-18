<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'مدیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

$today = date('Y-m-d');

$stmt = $conn->prepare("
    SELECT DISTINCT
        CONCAT(s.first_name, ' ', s.last_name) as name,
        COALESCE(s.phone) as phone,
        c.name as class_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN programs p ON a.program_id = p.id
    JOIN classes c ON p.class_id = c.id
    WHERE a.attendance_date = ?
    AND a.status = 'غایب'
    AND (s.phone IS NOT NULL)
    ORDER BY c.name, s.last_name, s.first_name
");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$recipients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_recipients = count($recipients);

$default_message = "والدین محترم
دانش‌آموز {name} کلاس {class} امروز {date} غایب است.
هنرستان سپهری راد";

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
    <title>ارسال پیامک</title>
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
                        <a href="today_absent.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            غایبین امروز
                        </a>
                    </li>
                    <li>
                        <a href="send_sms.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">ارسال پیامک به والدین</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <span class="text-green-600 font-medium">امروز <?php echo $today_persian; ?> <?php echo $today_jalali_formatted; ?></span>
                        - ارسال پیامک اطلاع‌رسانی غیبت به والدین دانش‌آموزان
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <!-- Total Recipients Card -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                قابل ارسال
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $total_recipients; ?></h3>
                        <p class="text-green-100 text-sm">تعداد گیرندگان</p>
                    </div>

                    <!-- Message Length Card -->
                    <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                حداکثر
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1">160</h3>
                        <p class="text-blue-100 text-sm">کاراکتر مجاز</p>
                    </div>

                    <!-- Date Card -->
                    <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                امروز
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-1"><?php echo $today_jalali_formatted; ?></h3>
                        <p class="text-purple-100 text-sm">تاریخ شمسی</p>
                    </div>
                </div>

                <!-- Main Card - فرم ارسال پیامک -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                    <div class="p-4 sm:p-6">
                        <!-- Action Bar -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">
                                متن پیامک
                                <?php if ($total_recipients > 0): ?>
                                    <span class="text-green-600 font-bold">(<?php echo $total_recipients; ?> گیرنده)</span>
                                <?php endif; ?>
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
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    متن پیامک:
                                    <span class="text-green-600 text-xs mr-2">(از متغیرهای {name}, {class}, {date} استفاده کنید)</span>
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
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-200">
                                <div class="text-gray-600 text-sm">
                                    <?php if ($total_recipients > 0): ?>
                                        <span class="font-medium text-green-600"><?php echo $total_recipients; ?> نفر</span>
                                        از والدین پیامک دریافت خواهند کرد
                                    <?php else: ?>
                                        <span class="text-red-600">هیچ گیرنده‌ای برای ارسال وجود ندارد</span>
                                    <?php endif; ?>
                                </div>
                                <button type="submit"
                                    class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:from-green-600 hover:to-green-700 transition-colors duration-200 text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed"
                                    <?php echo $total_recipients > 0 ? '' : 'disabled'; ?>>
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    ارسال پیامک
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- گیرندگان -->
                <?php if ($total_recipients > 0): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <h2 class="text-lg sm:text-xl font-semibold text-gray-900">لیست گیرندگان پیامک</h2>
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo $total_recipients; ?> نفر
                                </span>
                            </div>

                            <!-- Table Container -->
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50 border-b-2 border-gray-200">
                                        <tr>
                                            <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">#</th>
                                            <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">دانش‌آموز</th>
                                            <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">کلاس</th>
                                            <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap hidden md:table-cell">شماره تماس</th>
                                            <th class="px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($recipients as $index => $recipient): ?>
                                            <tr class="recipient-row hover:bg-gray-50 transition-colors duration-150">
                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 font-medium whitespace-nowrap">
                                                    <?= htmlspecialchars($recipient['name']) ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">
                                                    <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs">
                                                        <?= htmlspecialchars($recipient['class_name']) ?>
                                                    </span>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap hidden md:table-cell">
                                                    <?php if (!empty($recipient['phone'])): ?>
                                                        <span class="text-green-600 font-medium">
                                                            <?= htmlspecialchars($recipient['phone']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">ندارد</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    <?php if (!empty($recipient['phone'])): ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                            قابل ارسال
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                            بدون شماره
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                        <div class="text-green-500 text-6xl mb-4">
                            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-700 mb-3">هیچ غایبی وجود ندارد!</h3>
                        <p class="text-gray-600 mb-6">امروز هیچ دانش‌آموزی غایب نبوده است.</p>
                        <div class="space-x-4">
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
                        <svg class="w-5 h-5 ml-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        پیامک‌ها به صورت خودکار برای هر دانش‌آموز شخصی‌سازی می‌شوند. اطلاعات ارسال در بخش تاریخچه پیامک‌ها ثبت می‌شود.
                    </p>
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

        function updateCharCount(textarea) {
            const text = textarea.value;

            let charCount = 0;
            for (let i = 0; i < text.length; i++) {
                const charCode = text.charCodeAt(i);
                charCount++;
            }

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
            const defaultMessage = `والد محترم، سلام
دانش‌آموز {name} در کلاس {class} امروز {date} غایب بوده است.
لطفاً پیگیری لازم را به عمل آورید.
با تشکر - هنرستان سپهری راد`;

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

        document.getElementById('smsForm').addEventListener('submit', function(e) {
            const message = this.message.value.trim();
            const charCount = message.length;
            const totalRecipients = <?php echo $total_recipients; ?>;

            if (totalRecipients === 0) {
                e.preventDefault();
                alert('هیچ گیرنده‌ای برای ارسال وجود ندارد.');
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

            if (!confirm(`آیا از ارسال پیامک به ${totalRecipients} نفر اطمینان دارید؟`)) {
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

        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('textarea[name="message"]');
            updateCharCount(textarea);
        });
    </script>
</body>

</html>