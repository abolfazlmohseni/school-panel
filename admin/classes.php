<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$search = '';
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}

$sql = "SELECT c.id, c.name,
       (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
        FROM classes c";

if ($search) {
    $sql .= " WHERE c.name LIKE '%$search%'";
}

$result = $conn->query($sql);
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>لیست کلاس‌ها</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .table-container {
            overflow-x: auto;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen p-6">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold">لیست کلاس‌ها</h1>
            <a href="class_add.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">افزودن کلاس جدید</a>
        </div>

        <form method="GET" class="mb-4 flex gap-2">
            <input type="text" name="search" placeholder="جستجو بر اساس نام کلاس ..." value="<?= htmlspecialchars($search) ?>" class="flex-1 px-4 py-2 border rounded">
            <button type="submit" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">جستجو</button>
        </form>

        <div class="table-container">
            <table class="w-full border border-gray-200 rounded">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 border text-right">#</th>
                        <th class="px-4 py-2 border text-right">نام کلاس</th>
                        <th class="px-4 py-2 border text-right">تعداد دانش‌آموزان</th>
                        <th class="px-4 py-2 border text-center">اقدامات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-4 py-2 border text-right"><?= $row['id'] ?></td>
                                <!-- لینک روی نام کلاس -->
                                <td class="px-4 py-2 border text-right">
                                    <a href="class_students.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-2 border text-right"><?= $row['student_count'] ?></td>
                                <td class="px-4 py-2 border text-center flex justify-center gap-2">
                                    <a href="class_edit.php?id=<?= $row['id'] ?>" class="px-2 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">ویرایش</a>
                                    <a href="class_delete.php?id=<?= $row['id'] ?>" class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700">حذف</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-center text-gray-500">هیچ کلاسی یافت نشد.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>