<?php
// خط اول فایل - فقط یکبار session_start
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
$first_name = $_SESSION['first_name'] ?? 'دبیر';
$full_name = $_SESSION['full_name'] ?? '';

// دریافت program_id از URL
if (!isset($_GET['program_id'])) {
    die("برنامه کلاس مشخص نشده است.");
}

$program_id = intval($_GET['program_id']);
$today = date('Y-m-d');

// تبدیل تاریخ میلادی به شمسی (ساده)
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
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>ثبت حضور و غیاب - <?php echo htmlspecialchars($class_info['class_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Vazirmatn', sans-serif;
        }

        .present {
            background-color: #d1fae5;
        }

        .absent {
            background-color: #fee2e2;
        }

        input[type="radio"]:checked+label.present-label {
            background-color: #10b981 !important;
            color: white !important;
        }

        input[type="radio"]:checked+label.absent-label {
            background-color: #ef4444 !important;
            color: white !important;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto p-4">
        <!-- هدر -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        ثبت حضور و غیاب
                    </h1>
                    <div class="mt-2 text-gray-600">
                        <span class="font-medium">کلاس:</span>
                        <?php echo htmlspecialchars($class_info['class_name']); ?>
                        <span class="mx-2">•</span>
                        <span class="font-medium">روز:</span> <?php echo $class_info['day_of_week']; ?>
                        <span class="mx-2">•</span>
                        <span class="font-medium">زنگ:</span> <?php echo $class_info['schedule']; ?>
                        <span class="mx-2">•</span>
                        <span class="font-medium">تاریخ:</span> <?php echo $today_jalali_formatted; ?>
                    </div>
                </div>
                <div>
                    <a href="dashboard.php"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        بازگشت به داشبورد
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="mt-4 p-3 bg-green-100 text-green-800 rounded-lg">
                    ✅ حضور و غیاب با موفقیت ثبت شد.
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="mt-4 p-3 bg-red-100 text-red-800 rounded-lg">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- فرم حضور و غیاب -->
        <form method="POST" action="" class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- نوار اطلاعات -->
            <div class="bg-blue-50 p-4 border-b border-blue-100">
                <div class="flex justify-between items-center">
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
                        تعداد دانش‌آموزان: <?php echo count($students); ?> نفر
                    </div>
                </div>
            </div>

            <!-- لیست دانش‌آموزان -->
            <div class="divide-y divide-gray-100">
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $index => $student): ?>
                        <div class="p-4 hover:bg-gray-50 flex items-center justify-between 
                            <?php echo $student['attendance_status'] === 'حاضر' ? 'present' : 'absent'; ?>">
                            <div class="flex items-center">
                                <span class="w-8 text-gray-500"><?php echo $index + 1; ?></span>
                                <div class="mr-4">
                                    <div class="font-medium">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        کد ملی: <?php echo htmlspecialchars($student['national_code']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex space-x-2 space-x-reverse">
                                <!-- گزینه حاضر -->
                                <div class="relative">
                                    <input
                                        type="radio"
                                        id="present_<?php echo $student['id']; ?>"
                                        name="attendance[<?php echo $student['id']; ?>]"
                                        value="حاضر"
                                        <?php echo $student['attendance_status'] === 'حاضر' ? 'checked' : ''; ?>
                                        class="hidden attendance-radio"
                                        data-student-id="<?php echo $student['id']; ?>">
                                    <label
                                        for="present_<?php echo $student['id']; ?>"
                                        class="present-label cursor-pointer px-4 py-2 rounded-lg border border-green-500 
                                               <?php echo $student['attendance_status'] === 'حاضر' ? 'bg-green-500 text-white' : 'bg-white text-green-600 hover:bg-green-50'; ?>">
                                        حاضر
                                    </label>
                                </div>

                                <!-- گزینه غایب -->
                                <div class="relative">
                                    <input
                                        type="radio"
                                        id="absent_<?php echo $student['id']; ?>"
                                        name="attendance[<?php echo $student['id']; ?>]"
                                        value="غایب"
                                        <?php echo $student['attendance_status'] === 'غایب' ? 'checked' : ''; ?>
                                        class="hidden attendance-radio"
                                        data-student-id="<?php echo $student['id']; ?>">
                                    <label
                                        for="absent_<?php echo $student['id']; ?>"
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
                        📝 دانش‌آموزی در این کلاس ثبت‌نام نکرده است.
                    </div>
                <?php endif; ?>
            </div>

            <!-- دکمه‌های اقدام -->
            <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-between">
                <button
                    type="button"
                    onclick="selectAll('حاضر')"
                    class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition">
                    انتخاب همه به عنوان حاضر
                </button>

                <div class="flex space-x-3 space-x-reverse">
                    <button
                        type="button"
                        onclick="selectAll('غایب')"
                        class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition">
                        انتخاب همه به عنوان غایب
                    </button>

                    <button
                        type="submit"
                        name="submit_attendance"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        💾 ذخیره حضور و غیاب
                    </button>
                </div>
            </div>
        </form>

        <!-- آمار -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-xl shadow">
                <div class="text-gray-500 text-sm">کل دانش‌آموزان</div>
                <div class="text-2xl font-bold mt-1"><?php echo count($students); ?></div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow">
                <div class="text-gray-500 text-sm">حاضرین</div>
                <div class="text-2xl font-bold mt-1 text-green-600" id="present-count">
                    <?php
                    $present_count = 0;
                    foreach ($students as $student) {
                        if ($student['attendance_status'] === 'حاضر') {
                            $present_count++;
                        }
                    }
                    echo $present_count;
                    ?>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow">
                <div class="text-gray-500 text-sm">غایبین</div>
                <div class="text-2xl font-bold mt-1 text-red-600" id="absent-count">
                    <?php echo count($students) - $present_count; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // تابع برای انتخاب همه
        function selectAll(status) {
            const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
            radios.forEach(radio => {
                radio.checked = true;
                // فعال کردن رویداد change
                radio.dispatchEvent(new Event('change'));
            });
            updateStats();
        }

        // تابع به‌روزرسانی آمار
        function updateStats() {
            const presentCount = document.querySelectorAll('input[type="radio"][value="حاضر"]:checked').length;
            const absentCount = document.querySelectorAll('input[type="radio"][value="غایب"]:checked').length;

            document.getElementById('present-count').textContent = presentCount;
            document.getElementById('absent-count').textContent = absentCount;
        }

        // تغییر استایل هنگام کلیک روی دکمه‌ها
        document.querySelectorAll('.attendance-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                // حذف کلاس‌های فعال از همه برچسب‌های این دانش‌آموز
                const studentId = this.getAttribute('data-student-id');
                document.querySelectorAll(`input[data-student-id="${studentId}"] + label`).forEach(label => {
                    label.classList.remove('bg-green-500', 'text-white', 'bg-red-500', 'text-white');

                    if (label.classList.contains('present-label')) {
                        label.classList.add('bg-white', 'text-green-600');
                    } else {
                        label.classList.add('bg-white', 'text-red-600');
                    }
                });

                // اضافه کردن کلاس به برچسب انتخاب شده
                const label = document.querySelector(`label[for="${this.id}"]`);
                if (this.value === 'حاضر') {
                    label.classList.add('bg-green-500', 'text-white');
                    label.classList.remove('bg-white', 'text-green-600');
                } else {
                    label.classList.add('bg-red-500', 'text-white');
                    label.classList.remove('bg-white', 'text-red-600');
                }

                // تغییر کلاس ردیف
                const row = this.closest('.p-4');
                if (this.value === 'حاضر') {
                    row.classList.add('present');
                    row.classList.remove('absent');
                } else {
                    row.classList.add('absent');
                    row.classList.remove('present');
                }

                updateStats();
            });
        });

        // به‌روزرسانی اولیه آمار
        updateStats();
    </script>
</body>

</html>