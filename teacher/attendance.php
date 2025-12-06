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

// دریافت program_id از URL
if (!isset($_GET['program_id'])) {
    die("برنامه کلاس مشخص نشده است.");
}

$program_id = intval($_GET['program_id']);
$today = date('Y-m-d');

// تبدیل تاریخ میلادی به شمسی
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

// تاریخ امروز به شمسی
$today_gregorian = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_gregorian[0], $today_gregorian[1], $today_gregorian[2]);
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

// ---------- دریافت اطلاعات کلاس ----------
$stmt = $conn->prepare("
    SELECT 
        p.id as program_id,
        p.day_of_week,
        p.schedule,
        c.id as class_id,
        c.name as class_name
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    WHERE p.id = ? AND p.teacher_id = ?
");
$stmt->bind_param("ii", $program_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("دسترسی غیرمجاز یا کلاس یافت نشد.");
}

$class_info = $result->fetch_assoc();
$stmt->close();

// ---------- دریافت دانش‌آموزان این کلاس ----------
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.first_name,
        s.last_name,
        s.national_code,
        IFNULL(a.status, 'غایب') as attendance_status
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id 
        AND a.program_id = ? 
        AND a.attendance_date = ?
    WHERE s.class_id = ?
    ORDER BY s.last_name, s.first_name
");
$stmt->bind_param("isi", $program_id, $today, $class_info['class_id']);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- پردازش فرم ثبت حضور و غیاب ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    // شروع تراکنش
    $conn->begin_transaction();

    try {
        // حذف حضور و غیاب قبلی برای این جلسه (اگر وجود دارد)
        $delete_stmt = $conn->prepare("
            DELETE FROM attendance 
            WHERE program_id = ? AND attendance_date = ?
        ");
        $delete_stmt->bind_param("is", $program_id, $today);
        $delete_stmt->execute();
        $delete_stmt->close();

        // ثبت حضور و غیاب جدید
        $insert_stmt = $conn->prepare("
            INSERT INTO attendance 
            (student_id, program_id, teacher_id, attendance_date, status) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($_POST['attendance'] as $student_id => $status) {
            $insert_stmt->bind_param(
                "iiiss",
                $student_id,
                $program_id,
                $teacher_id,
                $today,
                $status
            );
            $insert_stmt->execute();
        }

        $insert_stmt->close();
        $conn->commit();

        // رفرش صفحه برای نمایش تغییرات
        header("Location: attendance.php?program_id=" . $program_id . "&success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "خطا در ثبت اطلاعات: " . $e->getMessage();
    }
}

// آمار
$present_count = 0;
$absent_count = 0;

foreach ($students as $student) {
    if ($student['attendance_status'] === 'حاضر') {
        $present_count++;
    } else {
        $absent_count++;
    }
}
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>ثبت حضور و غیاب - سامانه حضور غیاب</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            box-sizing: border-box;
        }

        .present {
            background-color: rgba(34, 197, 94, 0.05);
        }

        .absent {
            background-color: rgba(239, 68, 68, 0.05);
        }

        .attendance-radio:checked+label {
            transform: scale(1.05);
        }

        label {
            transition: all 0.2s ease;
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
        <div class="max-w-4xl mx-auto p-4">
            <!-- هدر -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">ثبت حضور و غیاب</h1>
                        <div class="mt-2 text-gray-600 text-sm sm:text-base">
                            <span class="font-medium">کلاس:</span> <?php echo htmlspecialchars($class_info['class_name']); ?>
                            <span class="mx-2">•</span>
                            <span class="font-medium">روز:</span> <?php echo htmlspecialchars($class_info['day_of_week']); ?>
                            <span class="mx-2">•</span>
                            <span class="font-medium">زنگ:</span> <?php echo htmlspecialchars($class_info['schedule']); ?>
                            <span class="mx-2">•</span>
                            <span class="font-medium">تاریخ:</span> <?php echo $today_jalali_formatted; ?>
                        </div>
                    </div>
                    <div>
                        <a href="dashboard.php" class="inline-block px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition whitespace-nowrap">
                            بازگشت به داشبورد
                        </a>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div id="success-message" class="mt-4 p-3 bg-green-100 text-green-800 rounded-lg">
                        ✅ حضور و غیاب با موفقیت ثبت شد.
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div id="error-message" class="mt-4 p-3 bg-red-100 text-red-800 rounded-lg">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- فرم حضور و غیاب -->
            <form method="POST" action="" class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- نوار اطلاعات -->
                <div class="bg-blue-50 p-4 border-b border-blue-100">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex items-center space-x-4 space-x-reverse">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-sm">حاضر</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-500 rounded-full mr-2"></div>
                                <span class="text-sm">غایب</span>
                            </div>
                        </div>
                        <div class="text-gray-600 text-sm">
                            تعداد دانش‌آموزان: <span id="total-students"><?php echo count($students); ?></span> نفر
                        </div>
                    </div>
                </div>

                <!-- لیست دانش‌آموزان -->
                <div class="divide-y divide-gray-100" id="students-list">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $index => $student): ?>
                            <div class="p-4 hover:bg-gray-50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 <?php echo $student['attendance_status'] === 'حاضر' ? 'present' : 'absent'; ?>">
                                <div class="flex items-start sm:items-center w-full sm:w-auto">
                                    <span class="w-8 text-gray-500 mt-1 sm:mt-0"><?php echo $index + 1; ?></span>
                                    <div class="mr-4">
                                        <div class="font-medium">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1">
                                            کد ملی: <?php echo htmlspecialchars($student['national_code']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex space-x-2 space-x-reverse w-full sm:w-auto justify-end">
                                    <!-- گزینه حاضر -->
                                    <div class="relative">
                                        <input type="radio"
                                            id="present_<?php echo $student['id']; ?>"
                                            name="attendance[<?php echo $student['id']; ?>]"
                                            value="حاضر"
                                            <?php echo $student['attendance_status'] === 'حاضر' ? 'checked' : ''; ?>
                                            class="hidden attendance-radio"
                                            data-student-id="<?php echo $student['id']; ?>"
                                            onchange="updateRowStyle(this)">
                                        <label for="present_<?php echo $student['id']; ?>"
                                            class="present-label cursor-pointer px-4 py-2 rounded-lg border border-green-500 
                                                      <?php echo $student['attendance_status'] === 'حاضر' ? 'bg-green-500 text-white' : 'bg-white text-green-600 hover:bg-green-50'; ?>">
                                            حاضر
                                        </label>
                                    </div>

                                    <!-- گزینه غایب -->
                                    <div class="relative">
                                        <input type="radio"
                                            id="absent_<?php echo $student['id']; ?>"
                                            name="attendance[<?php echo $student['id']; ?>]"
                                            value="غایب"
                                            <?php echo $student['attendance_status'] === 'غایب' ? 'checked' : ''; ?>
                                            class="hidden attendance-radio"
                                            data-student-id="<?php echo $student['id']; ?>"
                                            onchange="updateRowStyle(this)">
                                        <label for="absent_<?php echo $student['id']; ?>"
                                            class="absent-label cursor-pointer px-4 py-2 rounded-lg border border-red-500 
                                                      <?php echo $student['attendance_status'] === 'غایب' ? 'bg-red-500 text-white' : 'bg-white text-red-600 hover:bg-red-50'; ?>">
                                            غایب
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <p>دانش‌آموزی در این کلاس ثبت‌نام نکرده است.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- دکمه‌های اقدام -->
                <div class="p-4 bg-gray-50 border-t border-gray-100 flex flex-col sm:flex-row justify-between gap-3">
                    <button type="button" onclick="selectAll('حاضر')" class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition">
                        انتخاب همه به عنوان حاضر
                    </button>
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 sm:space-x-reverse">
                        <button type="button" onclick="selectAll('غایب')" class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition">
                            انتخاب همه به عنوان غایب
                        </button>
                        <button type="submit" name="submit_attendance" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            💾 ذخیره حضور و غیاب
                        </button>
                    </div>
                </div>
            </form>

            <!-- آمار -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-xl shadow">
                    <div class="text-gray-500 text-sm">
                        کل دانش‌آموزان
                    </div>
                    <div class="text-2xl font-bold mt-1" id="total-count">
                        <?php echo count($students); ?>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow">
                    <div class="text-gray-500 text-sm">
                        حاضرین
                    </div>
                    <div class="text-2xl font-bold mt-1 text-green-600" id="present-count">
                        <?php echo $present_count; ?>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow">
                    <div class="text-gray-500 text-sm">
                        غایبین
                    </div>
                    <div class="text-2xl font-bold mt-1 text-red-600" id="absent-count">
                        <?php echo $absent_count; ?>
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

        // تابع برای انتخاب همه
        function selectAll(status) {
            const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
            radios.forEach(radio => {
                radio.checked = true;
                updateRowStyle(radio);
            });
            updateStats();
        }

        // تابع به‌روزرسانی استایل ردیف
        function updateRowStyle(radio) {
            const studentId = radio.getAttribute('data-student-id');

            // حذف کلاس‌های فعال از همه برچسب‌های این دانش‌آموز
            document.querySelectorAll(`input[data-student-id="${studentId}"] + label`).forEach(label => {
                label.classList.remove('bg-green-500', 'text-white', 'bg-red-500', 'text-white');

                if (label.classList.contains('present-label')) {
                    label.classList.add('bg-white', 'text-green-600');
                } else {
                    label.classList.add('bg-white', 'text-red-600');
                }
            });

            // اضافه کردن کلاس به برچسب انتخاب شده
            const label = document.querySelector(`label[for="${radio.id}"]`);
            if (radio.value === 'حاضر') {
                label.classList.add('bg-green-500', 'text-white');
                label.classList.remove('bg-white', 'text-green-600');
            } else {
                label.classList.add('bg-red-500', 'text-white');
                label.classList.remove('bg-white', 'text-red-600');
            }

            // تغییر کلاس ردیف
            const row = radio.closest('.p-4');
            if (radio.value === 'حاضر') {
                row.classList.add('present');
                row.classList.remove('absent');
            } else {
                row.classList.add('absent');
                row.classList.remove('present');
            }

            updateStats();
        }

        // تابع به‌روزرسانی آمار
        function updateStats() {
            const presentCount = document.querySelectorAll('input[type="radio"][value="حاضر"]:checked').length;
            const absentCount = document.querySelectorAll('input[type="radio"][value="غایب"]:checked').length;

            document.getElementById('present-count').textContent = presentCount;
            document.getElementById('absent-count').textContent = absentCount;
        }

        // فعال کردن رویداد change برای همه رادیوها
        document.querySelectorAll('.attendance-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                updateRowStyle(this);
            });
        });

        // تابع مدیریت ارسال فرم
        function handleSubmit(event) {
            event.preventDefault();

            // نمایش پیام موفقیت
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                successMessage.classList.remove('hidden');

                // مخفی کردن پیام بعد از 3 ثانیه
                setTimeout(() => {
                    successMessage.classList.add('hidden');
                }, 3000);
            }

            // اسکرول به بالای صفحه
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

            // ارسال فرم
            event.target.submit();
        }

        // به‌روزرسانی اولیه آمار
        updateStats();

        // رفرش خودکار صفحه هر 5 دقیقه
        setTimeout(function() {
            location.reload();
        }, 5 * 60 * 1000);
    </script>
</body>

</html>