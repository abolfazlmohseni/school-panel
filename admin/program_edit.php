<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: programs.php');
    exit;
}

$id = intval($_GET['id']);

// گرفتن اطلاعات برنامه
$result = $conn->query("SELECT * FROM programs WHERE id=$id");
$program = $result->fetch_assoc();

// گرفتن لیست کلاس‌ها و دبیرها برای سلکت‌باکس
$classes_result = $conn->query("SELECT id, name FROM classes");
$teachers_result = $conn->query("SELECT id, first_name, last_name FROM users WHERE role='teacher'");
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>ویرایش برنامه</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 min-h-screen p-6">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">ویرایش برنامه</h1>
        <form action="program_update.php" method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?= $program['id'] ?>">

            <div>
                <label class="block mb-1">انتخاب کلاس</label>
                <select name="class_id" required class="w-full border px-3 py-2 rounded">
                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                        <option value="<?= $class['id'] ?>" <?= $class['id'] == $program['class_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block mb-1">انتخاب دبیر</label>
                <select name="teacher_id" required class="w-full border px-3 py-2 rounded">
                    <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                        <option value="<?= $teacher['id'] ?>" <?= $teacher['id'] == $program['teacher_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block mb-1">روز هفته</label>
                <select name="day_of_week" required class="w-full border px-3 py-2 rounded">
                    <?php
                    $days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
                    foreach ($days as $day): ?>
                        <option value="<?= $day ?>" <?= ($program['day_of_week'] == $day) ? 'selected' : '' ?>>
                            <?= $day ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>



            <div>
                <label class="block mb-1">زنگ کلاس</label>
                <select name="schedule" required class="w-full border px-3 py-2 rounded">
                    <option value="1" <?= $program['schedule'] == '1' ? 'selected' : '' ?>>1</option>
                    <option value="2" <?= $program['schedule'] == '2' ? 'selected' : '' ?>>2</option>
                    <option value="3" <?= $program['schedule'] == '3' ? 'selected' : '' ?>>3</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">ذخیره</button>
            <a href="programs.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">لغو</a>
        </form>
    </div>
</body>

</html>