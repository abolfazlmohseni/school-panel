<?php
session_start();
require_once '../config.php';

// بررسی ورود مدیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// دریافت لیست برنامه‌ها با اطلاعات دبیر و تعداد دانش‌آموزان
$sql = "SELECT p.id, c.name AS class_name, p.day_of_week, p.schedule,
       u.first_name, u.last_name,
       (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
        FROM programs p
        JOIN classes c ON p.class_id = c.id
        JOIN users u ON c.teacher_id = u.id";
$result = $conn->query($sql);
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>لیست برنامه‌ها</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 min-h-screen p-6">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">لیست برنامه‌ها</h1>
            <a href="program_add.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">افزودن برنامه جدید</a>
        </div>

        <table class="w-full border border-gray-200 rounded">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 border">#</th>
                    <th class="px-4 py-2 border">نام کلاس</th>
                    <th class="px-4 py-2 border">دبیر</th>
                    <th class="px-4 py-2 border">روز هفته</th>
                    <th class="px-4 py-2 border">زنگ کلاس</th>
                    <th class="px-4 py-2 border">تعداد دانش‌آموزان</th>
                    <th class="px-4 py-2 border">اقدامات</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 border"><?= $row['id'] ?></td>
                        <td class="px-4 py-2 border"><?= htmlspecialchars($row['class_name']) ?></td>
                        <td class="px-4 py-2 border"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td class="px-4 py-2 border"><?= htmlspecialchars($row['day_of_week']) ?></td>
                        <td class="px-4 py-2 border"><?= htmlspecialchars($row['schedule']) ?></td>
                        <td class="px-4 py-2 border"><?= $row['student_count'] ?></td>
                        <td class="px-4 py-2 border">
                            <a href="program_edit.php?id=<?= $row['id'] ?>" class="px-2 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">ویرایش</a>
                            <a href="program_delete.php?id=<?= $row['id'] ?>" class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700">حذف</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>

</html>