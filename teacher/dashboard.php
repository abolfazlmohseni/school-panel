<?php
session_start();
require_once '../config.php';

// ---------- فعال کردن نمایش خطاها ----------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---------- چک ورود دبیر ----------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// استفاده مستقیم از نام ذخیره شده در سشن
$first_name = $_SESSION['first_name'] ?? 'دبیر';
$full_name = $_SESSION['full_name'] ?? '';

$teacher_id = $_SESSION['user_id'];

// ---------- گرفتن نام دبیر ----------
$stmt = $conn->prepare("SELECT first_name FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$first_name = $user['first_name'] ?? 'دبیر';

// ---------- آرایه تبدیل روزهای هفته ----------
$weekdays_persian = [
    0 => 'یکشنبه',
    1 => 'دوشنبه',
    2 => 'سه‌شنبه',
    3 => 'چهارشنبه',
    4 => 'پنج‌شنبه',
    5 => 'جمعه',
    6 => 'شنبه'
];

// ---------- گرفتن کلاس‌های منحصر به فرد دبیر ----------
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
$all_classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- گرفتن برنامه‌های کامل دبیر با روزهای فارسی ----------
$stmt = $conn->prepare("
    SELECT 
        p.id as program_id, 
        c.name as class_name, 
        p.schedule, 
        p.day_of_week,
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
$programs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- فیلتر برنامه‌های امروز ----------
// ابتدا باید نام روز امروز را به فارسی دریافت کنیم
$weekday_number = date('w'); // 0=یکشنبه, 1=دوشنبه, ...
$today_persian = $weekdays_persian[$weekday_number];

// فیلتر برنامه‌های امروز
$today_classes = array_filter($programs, function ($p) use ($today_persian) {
    return $p['day_of_week'] === $today_persian;
});
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>داشبورد دبیر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Vazirmatn', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 p-6">

    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800">سلام، <?php echo htmlspecialchars($full_name); ?>! 👋</h1>
        <!-- بخش کلاس‌های امروز -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">
                    کلاس‌های امروز
                    <span class="text-blue-600">(<?php echo $today_persian; ?>)</span>
                </h2>
                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                    <?php echo count($today_classes); ?> کلاس
                </span>
            </div>

            <?php if (count($today_classes) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($today_classes as $class): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex justify-between items-center hover:bg-blue-100 transition duration-200">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($class['class_name']); ?></span>
                                <span class="text-gray-600 mr-3">•</span>
                                <span class="text-gray-700">زنگ <?php echo htmlspecialchars($class['schedule']); ?></span>
                            </div>
                            <a href="attendance.php?program_id=<?php echo $class['program_id']; ?>"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 font-medium text-sm">
                                ثبت حضور و غیاب
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 mb-2">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-600">امروز کلاس ثبت شده‌ای ندارید.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- بخش کل برنامه هفتگی -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">برنامه هفتگی شما</h2>

            <?php if (count($programs) > 0): ?>
                <!-- گروه‌بندی برنامه‌ها بر اساس روز هفته -->
                <?php
                $grouped_by_day = [];
                foreach ($programs as $program) {
                    $day = $program['day_of_week'];
                    if (!isset($grouped_by_day[$day])) {
                        $grouped_by_day[$day] = [];
                    }
                    $grouped_by_day[$day][] = $program;
                }

                // ترتیب روزهای هفته به ترتیب فارسی
                $persian_days_order = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
                ?>

                <div class="space-y-6">
                    <?php foreach ($persian_days_order as $day): ?>
                        <?php if (isset($grouped_by_day[$day]) && count($grouped_by_day[$day]) > 0): ?>
                            <div>
                                <div class="flex items-center mb-2">
                                    <h3 class="font-medium text-gray-800 text-lg">
                                        <?php echo $day; ?>
                                        <?php if ($day === $today_persian): ?>
                                            <span class="mr-2 text-sm bg-green-100 text-green-800 px-2 py-0.5 rounded">امروز</span>
                                        <?php endif; ?>
                                    </h3>
                                    <span class="mr-2 text-gray-500 text-sm">
                                        (<?php echo count($grouped_by_day[$day]); ?> کلاس)
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <?php foreach ($grouped_by_day[$day] as $program): ?>
                                        <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-sm transition duration-200">
                                            <div class="font-medium text-gray-800 mb-1">
                                                <?php echo htmlspecialchars($program['class_name']); ?>
                                            </div>
                                            <div class="text-gray-600 text-sm mb-3">
                                                زنگ <?php echo htmlspecialchars($program['schedule']); ?>
                                            </div>
                                            <a href="attendance.php?program_id=<?php echo $program['program_id']; ?>"
                                                class="inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200 transition duration-200">
                                                مدیریت کلاس
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-4 border-gray-100">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 mb-2">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-600">هنوز هیچ برنامه‌ای برای شما ثبت نشده است.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>