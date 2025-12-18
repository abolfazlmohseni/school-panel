<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    die("شناسه حضور و غیاب مشخص نشده است.");
}

$attendance_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT 
        a.*,
        s.first_name,
        s.last_name,
        s.national_code,
        c.name as class_name,
        p.day_of_week,
        p.schedule,
        p.id as program_id
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN programs p ON a.program_id = p.id
    JOIN classes c ON p.class_id = c.id
    WHERE a.id = ? AND a.teacher_id = ?
");
$stmt->bind_param("ii", $attendance_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("رکورد یافت نشد یا دسترسی ندارید.");
}

$attendance = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $new_status = $_POST['status'];

    $update_stmt = $conn->prepare("
        UPDATE attendance 
        SET status = ?, created_at = NOW() 
        WHERE id = ? AND teacher_id = ?
    ");
    $update_stmt->bind_param("sii", $new_status, $attendance_id, $teacher_id);

    if ($update_stmt->execute()) {
        $success = "حضور و غیاب با موفقیت به‌روزرسانی شد.";
        // رفرش اطلاعات
        $attendance['status'] = $new_status;
    } else {
        $error = "خطا در به‌روزرسانی: " . $conn->error;
    }

    $update_stmt->close();
}

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

$date_parts = explode('-', $attendance['attendance_date']);
$jalali_date = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2]);
$formatted_date = $jalali_date[0] . '/' . sprintf('%02d', $jalali_date[1]) . '/' . sprintf('%02d', $jalali_date[2]);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>ویرایش حضور و غیاب</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Vazirmatn', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto p-4">
        <!-- هدر -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">ویرایش حضور و غیاب</h1>
                    <p class="text-gray-600 mt-1">به‌روزرسانی وضعیت حضور دانش‌آموز</p>
                </div>
                <div>
                    <a href="attendance_history.php"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        بازگشت به تاریخچه
                    </a>
                </div>
            </div>
        </div>

        <!-- فرم ویرایش -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <?php if (isset($success)): ?>
                <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- اطلاعات دانش‌آموز -->
            <div class="mb-8 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-medium text-gray-700 mb-3">اطلاعات دانش‌آموز</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500">نام کامل</div>
                        <div class="font-medium">
                            <?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">کد ملی</div>
                        <div class="font-medium"><?php echo htmlspecialchars($attendance['national_code']); ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">کلاس</div>
                        <div class="font-medium"><?php echo htmlspecialchars($attendance['class_name']); ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">برنامه کلاس</div>
                        <div class="font-medium">
                            <?php echo htmlspecialchars($attendance['day_of_week']); ?> -
                            زنگ <?php echo htmlspecialchars($attendance['schedule']); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">تاریخ جلسه</div>
                        <div class="font-medium"><?php echo $formatted_date; ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">تاریخ ثبت</div>
                        <div class="font-medium">
                            <?php
                            $created = new DateTime($attendance['created_at']);
                            echo $created->format('H:i - Y/m/d');
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- فرم تغییر وضعیت -->
            <form method="POST" action="">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">وضعیت حضور</label>
                    <div class="flex space-x-4 space-x-reverse">
                        <div class="flex items-center">
                            <input type="radio" id="status_present" name="status" value="حاضر"
                                class="h-5 w-5 text-green-600"
                                <?php echo $attendance['status'] === 'حاضر' ? 'checked' : ''; ?>>
                            <label for="status_present" class="mr-2 flex items-center cursor-pointer">
                                <span class="w-3 h-3 bg-green-500 rounded-full ml-2"></span>
                                <span class="text-gray-700">حاضر</span>
                            </label>
                        </div>

                        <div class="flex items-center">
                            <input type="radio" id="status_absent" name="status" value="غایب"
                                class="h-5 w-5 text-red-600"
                                <?php echo $attendance['status'] === 'غایب' ? 'checked' : ''; ?>>
                            <label for="status_absent" class="mr-2 flex items-center cursor-pointer">
                                <span class="w-3 h-3 bg-red-500 rounded-full ml-2"></span>
                                <span class="text-gray-700">غایب</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- دکمه‌های اقدام -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-100">
                    <button type="submit" name="update_attendance"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        ذخیره تغییرات
                    </button>

                    <a href="attendance.php?program_id=<?php echo $attendance['program_id']; ?>"
                        class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition">
                        بازگشت به ثبت حضور
                    </a>
                </div>
            </form>
        </div>

        <!-- نکات مهم -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div class="flex items-start">
                <div class="text-yellow-600 ml-3">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-medium text-yellow-800">توجه</h4>
                    <ul class="mt-2 text-sm text-yellow-700 space-y-1">
                        <li>• ویرایش حضور و غیاب فقط برای شما (دبیر مربوطه) امکان‌پذیر است</li>
                        <li>• پس از ویرایش، تاریخ به‌روزرسانی تغییر می‌کند</li>
                        <li>• مدیر سیستم نیز می‌تواند این تغییرات را مشاهده کند</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>

</html>