<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// چک ورود دبیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? '';

// فیلترهای جستجو
$class_filter = $_GET['class_id'] ?? '';
$date_filter = $_GET['date'] ?? '';
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

if (!empty($date_filter)) {
    $query .= " AND a.attendance_date = ?";
    $params[] = $date_filter;
    $types .= "s";
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
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تاریخچه حضور و غیاب</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Vazirmatn', sans-serif; }
        
        .present-badge { background-color: #d1fae5; color: #065f46; }
        .absent-badge { background-color: #fee2e2; color: #991b1b; }
        
        .table-row:hover { background-color: #f9fafb; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto p-4">
        <!-- هدر -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">تاریخچه حضور و غیاب</h1>
                    <p class="text-gray-600 mt-1">نمایش و مدیریت سوابق حضور و غیاب</p>
                </div>
                <div class="flex space-x-3 space-x-reverse">
                    <a href="dashboard.php" 
                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                       داشبورد
                    </a>
                    <a href="weekly_schedule.php" 
                       class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                       برنامه هفتگی
                    </a>
                </div>
            </div>
        </div>
        
        <!-- فیلترها -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- فیلتر کلاس -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">کلاس</label>
                    <select name="class_id" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">همه کلاس‌ها</option>
                        <?php foreach ($teacher_classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- فیلتر تاریخ -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                           class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- فیلتر دانش‌آموز -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نام دانش‌آموز</label>
                    <input type="text" name="student_name" value="<?php echo htmlspecialchars($student_filter); ?>"
                           placeholder="جستجو بر اساس نام"
                           class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- دکمه‌های فیلتر -->
                <div class="md:col-span-3 flex justify-between items-center pt-4 border-t border-gray-100">
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        🔍 اعمال فیلتر
                    </button>
                    
                    <a href="attendance_history.php" 
                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                       حذف فیلترها
                    </a>
                </div>
            </form>
        </div>
        
        <!-- آمار -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-6 rounded-xl shadow">
                <div class="text-gray-500 text-sm">کل رکوردها</div>
                <div class="text-2xl font-bold mt-1"><?php echo $total_records; ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
                <div class="text-gray-500 text-sm">حاضرین</div>
                <div class="text-2xl font-bold mt-1 text-green-600"><?php echo $present_count; ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
                <div class="text-gray-500 text-sm">غایبین</div>
                <div class="text-2xl font-bold mt-1 text-red-600"><?php echo $absent_count; ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
                <div class="text-gray-500 text-sm">میانگین حضور</div>
                <div class="text-2xl font-bold mt-1 text-blue-600"><?php echo $attendance_rate; ?>%</div>
            </div>
        </div>
        
        <!-- جدول تاریخچه -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- هدر جدول -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                <div class="flex justify-between items-center">
                    <h2 class="font-medium text-gray-700">سوابق حضور و غیاب</h2>
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
                                    <td class="py-3 px-4"><?php echo $index + 1; ?></td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium">
                                            <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($record['national_code']); ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php echo htmlspecialchars($record['class_name']); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php echo $formatted_date; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-gray-600">
                                            <?php echo htmlspecialchars($record['day_of_week']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            زنگ <?php echo htmlspecialchars($record['schedule']); ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium 
                                            <?php echo $record['status'] === 'حاضر' ? 'present-badge' : 'absent-badge'; ?>">
                                            <?php echo $record['status']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-500">
                                        <?php 
                                            $created = new DateTime($record['created_at']);
                                            echo $created->format('H:i - Y/m/d');
                                        ?>
                                    </td>
                                    <td class="py-3 px-4">
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
                
                <!-- پاورقی جدول -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            آخرین به‌روزرسانی: <?php echo date('H:i - Y/m/d'); ?>
                        </div>
                        <div>
                            <button onclick="window.print()" 
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
                                🖨️ چاپ گزارش
                            </button>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="p-12 text-center">
                    <div class="text-gray-400 mb-4">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">سابقه‌ای یافت نشد</h3>
                    <p class="text-gray-500">
                        <?php if (!empty($class_filter) || !empty($date_filter) || !empty($student_filter)): ?>
                            با فیلترهای اعمال شده هیچ رکوردی وجود ندارد.
                        <?php else: ?>
                            هنوز حضور و غیابی ثبت نکرده‌اید.
                        <?php endif; ?>
                    </p>
                    <div class="mt-6">
                        <a href="dashboard.php" 
                           class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                           📝 ثبت اولین حضور و غیاب
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // تابع حذف
        function confirmDelete(id) {
            if (confirm('آیا از حذف این رکورد اطمینان دارید؟')) {
                window.location.href = 'delete_attendance.php?id=' + id;
            }
        }
        
        // تاریخ شمسی (تابع باید در PHP تعریف شده باشد)
        <?php
        function gregorian_to_jalali($gy, $gm, $gd) {
            $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
            $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
            $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm-1];
            $jy = -1595 + (33 * ((int)($days / 12053)));
            $days %= 12053;
            $jy += 4 * ((int)($days / 1461));
            $days %= 1461;
            if ($days > 365) {
                $jy += (int)(($days - 1) / 365);
                $days = ($days-1) % 365;
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
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // فعال کردن datepicker فارسی (اگر نیاز دارید)
        flatpickr("input[type='date']", {
            locale: "fa",
            dateFormat: "Y-m-d",
        });
    </script>
</body>
</html>