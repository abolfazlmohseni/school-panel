<?php
session_start();
require_once '../config.php';

// چک ورود دبیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? '';

// دریافت برنامه هفتگی
$stmt = $conn->prepare("
    SELECT 
        p.id as program_id,
        c.name as class_name,
        p.day_of_week,
        p.schedule,
        p.created_at
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
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>برنامه هفتگی</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Vazirmatn', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto p-4">
        <!-- هدر -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">برنامه هفتگی</h1>
                    <p class="text-gray-600 mt-1">برنامه کلاسی <?php echo htmlspecialchars($full_name); ?></p>
                </div>
                <div>
                    <a href="dashboard.php"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        بازگشت به داشبورد
                    </a>
                </div>
            </div>
        </div>

        <!-- روزهای هفته -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $days_order = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

            foreach ($days_order as $day):
                $day_classes = $grouped_schedule[$day] ?? [];
            ?>
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <!-- هدر روز -->
                    <div class="bg-blue-600 text-white p-4">
                        <h3 class="text-lg font-bold"><?php echo $day; ?></h3>
                        <p class="text-sm opacity-90">
                            <?php echo count($day_classes); ?> کلاس
                        </p>
                    </div>

                    <!-- لیست کلاس‌های این روز -->
                    <div class="divide-y divide-gray-100">
                        <?php if (count($day_classes) > 0): ?>
                            <?php foreach ($day_classes as $class): ?>
                                <div class="p-4 hover:bg-blue-50">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                زنگ <?php echo htmlspecialchars($class['schedule']); ?>
                                            </div>
                                        </div>
                                        <a href="attendance.php?program_id=<?php echo $class['program_id']; ?>"
                                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition">
                                            ثبت حضور
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-400">
                                📅 کلاسی در این روز ندارید
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>