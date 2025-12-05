<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// لیست کلاس‌ها و دبیران برای سلکت‌باکس
$classes_result = $conn->query("SELECT id, name FROM classes");
$teachers_result = $conn->query("SELECT id, first_name, last_name FROM users WHERE role='teacher'");
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>افزودن برنامه جدید</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 min-h-screen p-6">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">افزودن برنامه جدید</h1>
        <form action="program_add_action.php" method="POST" class="space-y-4">

            <!-- انتخاب کلاس -->
            <div>
                <label class="block mb-1">نام کلاس</label>
                <select name="class_id" required class="w-full border px-3 py-2 rounded">
                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                        <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- انتخاب دبیر -->
            <div>
                <label class="block mb-1">نام دبیر</label>
                <select name="teacher_id" required class="w-full border px-3 py-2 rounded">
                    <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                        <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- روز هفته -->
            <div>
                <label class="block mb-1">روز هفته</label>
                <select name="day_of_week" required class="w-full border px-3 py-2 rounded">
                    <?php
                    $days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
                    foreach ($days as $day): ?>
                        <option value="<?= $day ?>"><?= $day ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ساعت/زنگ کلاس -->
            <div>
                <label class="block mb-1">زنگ کلاس</label>
                <select name="schedule" required class="w-full border px-3 py-2 rounded">
                    <?php
                    $periods = ['زنگ 1', 'زنگ 2', 'زنگ 3'];
                    foreach ($periods as $p): ?>
                        <option value="<?= $p ?>"><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">ذخیره برنامه</button>
            <a href="programs.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">لغو</a>
        </form>
    </div>
</body>

</html>