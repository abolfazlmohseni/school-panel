<?php
// sms_result.php - نسخه تصحیح شده
session_start();

// فعال کردن خطاها
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// چک ورود مدیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// دریافت نتایج از session
if (!isset($_SESSION['sms_result'])) {
    header("Location: send_sms.php");
    exit;
}

$result = $_SESSION['sms_result'];
unset($_SESSION['sms_result']); // حذف نتایج بعد از نمایش

$first_name = $_SESSION['first_name'] ?? 'مدیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// تاریخ شمسی
$today = date('Y-m-d');
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

// ادامه کد HTML شما...
// (کد HTML که قبلاً داشتید اینجا قرار می‌گیرد)
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>نتیجه ارسال پیامک - سامانه حضور غیاب هنرستان سپهری راد</title>
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
                <div class="mt-2 text-xs text-blue-200">
                    مدیر سیستم: <?php echo htmlspecialchars($first_name); ?>
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
                        <a href="today_absent.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            غایبین امروز
                        </a>
                    </li>
                    <li>
                        <a href="send_sms.php" class="flex items-center gap-3 px-4 py-3 text-white bg-green-600 rounded-lg font-medium">
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">نتیجه ارسال پیامک</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <span class="text-green-600 font-medium">امروز <?php echo $today_jalali_formatted; ?></span>
                        - وضعیت ارسال پیامک‌های اطلاع‌رسانی
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <!-- Success Card -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                موفق
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $result['success_count']; ?></h3>
                        <p class="text-green-100 text-sm">پیامک ارسال شده</p>
                    </div>

                    <!-- Failed Card -->
                    <div class="stat-card bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                ناموفق
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $result['failed_count']; ?></h3>
                        <p class="text-red-100 text-sm">پیامک ناموفق</p>
                    </div>

                    <!-- Total Card -->
                    <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white bg-opacity-20 rounded-lg">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </div>
                            <div class="text-xs bg-white bg-opacity-30 px-2 py-1 rounded-full">
                                مجموع
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $result['total_count']; ?></h3>
                        <p class="text-blue-100 text-sm">گیرنده کل</p>
                    </div>
                </div>

                <!-- Main Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                    <div class="p-4 sm:p-6">
                        <!-- Action Bar -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">جزئیات ارسال پیامک</h2>
                            <div class="flex gap-3">
                                <?php
                                $success_rate = ($result['total_count'] > 0)
                                    ? round(($result['success_count'] / $result['total_count']) * 100, 1)
                                    : 0;
                                $badge_class = ($success_rate >= 80) ? 'bg-green-100 text-green-800' : (($success_rate >= 50) ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                ?>
                                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $badge_class; ?>">
                                    <?php echo $success_rate; ?>% موفقیت
                                </span>
                            </div>
                        </div>

                        <!-- متن پیام -->
                        <div class="mb-8">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">متن پیامک ارسال شده:</h3>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($result['message']); ?></div>
                            </div>
                        </div>

                        <!-- نتایج -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- موفق‌ها -->
                            <div>
                                <h3 class="text-sm font-medium text-green-700 mb-3 flex items-center">
                                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    پیامک‌های ارسال شده موفق (<?php echo $result['success_count']; ?>)
                                </h3>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="text-green-700">
                                        پیامک‌های شما با موفقیت برای
                                        <span class="font-bold"><?php echo $result['success_count']; ?> نفر</span>
                                        از والدین ارسال شد.
                                    </div>
                                    <div class="mt-2 text-sm text-green-600">
                                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        این پیامک‌ها در تاریخچه سیستم ثبت شده‌اند.
                                    </div>
                                </div>
                            </div>

                            <!-- ناموفق‌ها -->
                            <div>
                                <h3 class="text-sm font-medium text-red-700 mb-3 flex items-center">
                                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    پیامک‌های ارسال نشده (<?php echo $result['failed_count']; ?>)
                                </h3>
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <?php if ($result['failed_count'] > 0): ?>
                                        <div class="text-red-700 mb-2">
                                            ارسال پیامک برای
                                            <span class="font-bold"><?php echo $result['failed_count']; ?> نفر</span>
                                            ناموفق بود.
                                        </div>
                                        <?php if (!empty($result['failed_names'])): ?>
                                            <div class="text-sm text-red-600">
                                                <div class="font-medium mb-1">دانش‌آموزان ناموفق:</div>
                                                <div class="space-y-1">
                                                    <?php for ($i = 0; $i < count($result['failed_names']); $i++): ?>
                                                        <div class="flex items-center">
                                                            <svg class="w-3 h-3 ml-1 text-red-500" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                            <?php echo htmlspecialchars($result['failed_names'][$i]); ?>
                                                            <?php if (!empty($result['failed_numbers'][$i])): ?>
                                                                <span class="mr-2 text-gray-500">(<?php echo htmlspecialchars($result['failed_numbers'][$i]); ?>)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="text-green-600">
                                            ✅ تمام پیامک‌ها با موفقیت ارسال شدند.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- دکمه‌ها -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="send_sms.php"
                                    class="flex-1 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 text-center">
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    ارسال پیامک جدید
                                </a>
                                <a href="sms_history.php"
                                    class="flex-1 px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors duration-200 text-center">
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    مشاهده تاریخچه
                                </a>
                                <a href="dashboard.php"
                                    class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200 text-center">
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                    </svg>
                                    بازگشت به داشبورد
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800 text-xs sm:text-sm flex items-start">
                        <svg class="w-5 h-5 ml-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        جزئیات کامل هر پیامک ارسال شده در بخش تاریخچه پیامک‌ها قابل مشاهده و جستجو است.
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

        // هدایت خودکار به داشبورد بعد از 30 ثانیه
        setTimeout(function() {
            window.location.href = 'dashboard.php';
        }, 30000);
    </script>
</body>

</html>