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

// فیلترهای جستجو
$class_filter = $_GET['class_id'] ?? '';
$student_filter = $_GET['student_name'] ?? '';

// ---------- دریافت کلاس‌های دبیر ----------
$stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    WHERE p.teacher_id = ?
    ORDER BY c.name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher_classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- دریافت تاریخچه حضور و غیاب ----------
$query = "
    SELECT 
        a.id,
        a.attendance_date,
        a.status,
        s.first_name,
        s.last_name,
        s.national_code,
        c.name as class_name,
        p.day_of_week,
        p.schedule,
        a.created_at
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN programs p ON a.program_id = p.id
    JOIN classes c ON p.class_id = c.id
    WHERE a.teacher_id = ?
";

$params = [$teacher_id];
$types = "i";

// اضافه کردن فیلترها
if (!empty($class_filter)) {
    $query .= " AND c.id = ?";
    $params[] = $class_filter;
    $types .= "i";
}


if (!empty($student_filter)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ?)";
    $params[] = "%$student_filter%";
    $params[] = "%$student_filter%";
    $types .= "ss";
}

$query .= " ORDER BY a.attendance_date DESC, a.created_at DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$attendance_history = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// آمار
$total_records = count($attendance_history);
$present_count = 0;
$absent_count = 0;

foreach ($attendance_history as $record) {
    if ($record['status'] === 'حاضر') {
        $present_count++;
    } else {
        $absent_count++;
    }
}

$attendance_rate = $total_records > 0 ? round(($present_count / $total_records) * 100, 1) : 0;

// تابع تبدیل تاریخ
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
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>تاریخچه حضور و غیاب - سامانه حضور غیاب</title>
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

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .table-row {
            transition: background-color 0.2s ease;
        }

        .table-row:hover {
            background-color: #f9fafb;
        }

        .present-badge {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .absent-badge {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .filter-card {
            transition: all 0.2s ease;
        }

        .filter-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.05);
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
                        <a href="attendance_history.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
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
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">تاریخچه حضور و غیاب</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <?php echo htmlspecialchars($full_name); ?> عزیز،
                        <span class="text-blue-600 font-medium">نمایش و مدیریت سوابق حضور و غیاب</span>
                    </p>
                </div>
                <!-- فیلترها -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8 filter-card">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">فیلترها</h2>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- فیلتر کلاس -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">کلاس</label>
                            <select name="class_id" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">همه کلاس‌ها</option>
                                <?php foreach ($teacher_classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"
                                        <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- فیلتر دانش‌آموز -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">نام دانش‌آموز</label>
                            <input type="text" name="student_name" value="<?php echo htmlspecialchars($student_filter); ?>"
                                placeholder="جستجو بر اساس نام"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- دکمه‌های فیلتر -->
                        <div class="flex items-end space-x-3 space-x-reverse">
                            <button type="submit"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium w-full">
                                اعمال فیلتر
                            </button>

                            <a href="attendance_history.php"
                                class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-center w-full">
                                حذف فیلترها
                            </a>
                        </div>
                    </form>
                </div>
                <!-- جدول تاریخچه -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- هدر جدول -->
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h2 class="font-medium text-gray-700 text-lg">سوابق حضور و غیاب</h2>
                            <span class="text-sm text-gray-500">
                                <?php echo $total_records; ?> مورد یافت شد
                            </span>
                        </div>
                    </div>

                    <!-- بدنه جدول -->
                    <?php if ($total_records > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">ردیف</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">دانش‌آموز</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">کلاس</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">تاریخ</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">روز / زنگ</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">وضعیت</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">زمان ثبت</th>
                                        <th class="py-3 px-4 text-right font-medium text-gray-700">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($attendance_history as $index => $record):
                                        // تبدیل تاریخ میلادی به شمسی
                                        $date_parts = explode('-', $record['attendance_date']);
                                        $jalali_date = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2]);
                                        $formatted_date = $jalali_date[0] . '/' . sprintf('%02d', $jalali_date[1]) . '/' . sprintf('%02d', $jalali_date[2]);
                                    ?>
                                        <tr class="table-row hover:bg-gray-50">
                                            <td class="py-4 px-4">
                                                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-sm">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="font-medium">
                                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($record['national_code']); ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span class="font-medium text-gray-800">
                                                    <?php echo htmlspecialchars($record['class_name']); ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="font-medium text-gray-800">
                                                    <?php echo $formatted_date; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo $record['attendance_date']; ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="text-gray-600">
                                                    <?php echo htmlspecialchars($record['day_of_week']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    زنگ <?php echo htmlspecialchars($record['schedule']); ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span class="px-3 py-1 rounded-full text-sm font-medium 
                                                    <?php echo $record['status'] === 'حاضر' ? 'present-badge' : 'absent-badge'; ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4 text-sm text-gray-500">
                                                <?php
                                                $created = new DateTime($record['created_at']);
                                                echo $created->format('H:i - Y/m/d');
                                                ?>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="flex space-x-2 space-x-reverse">
                                                    <a href="edit_attendance.php?id=<?php echo $record['id']; ?>"
                                                        class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition">
                                                        ویرایش
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $record['id']; ?>)"
                                                        class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200 transition">
                                                        حذف
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100"></div>

                    <?php else: ?>
                        <div class="p-12 text-center">
                            <div class="text-gray-400 mb-4">
                                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">سابقه‌ای یافت نشد</h3>
                            <p class="text-gray-500 mb-6">
                                <?php if (!empty($class_filter)  || !empty($student_filter)): ?>
                                    با فیلترهای اعمال شده هیچ رکوردی وجود ندارد.
                                <?php else: ?>
                                    هنوز حضور و غیابی ثبت نکرده‌اید.
                                <?php endif; ?>
                            </p>
                            <div class="space-x-4">
                                <a href="dashboard.php"
                                    class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                    📝 ثبت اولین حضور و غیاب
                                </a>
                                <a href="attendance_history.php"
                                    class="inline-block px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                                    حذف فیلترها
                                </a>
                            </div>
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

        // تابع حذف
        function confirmDelete(id) {
            if (confirm('آیا از حذف این رکورد اطمینان دارید؟')) {
                window.location.href = 'delete_attendance.php?id=' + id;
            }
        }
    </script>
</body>

</html>