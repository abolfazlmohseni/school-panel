<?php
session_start();
require_once '../config.php';

// فقط مدیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /attendance-system/login.php");
    exit;
}

// حذف دبیر
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
    header("Location: teachers.php");
    exit;
}

// دریافت لیست دبیران
$result = $conn->query("SELECT id, first_name, last_name, username, created_at FROM users WHERE role='teacher' ORDER BY id DESC");
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>لیست دبیران - سامانه حضور غیاب هنرستان سپهری راد</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            box-sizing: border-box;
        }

        .delete-confirm {
            display: none;
        }

        .delete-confirm.active {
            display: inline-flex;
        }

        @media (max-width: 640px) {
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body class="min-h-full bg-gray-50">
    <div class="w-full min-h-screen py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">

            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">لیست دبیران</h1>
                <p class="text-gray-600 text-sm sm:text-base">سامانه حضور غیاب هنرستان سپهری راد</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-4 sm:p-6">

                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">مدیریت دبیران</h2>
                        <a href="teacher_add.php" class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 text-center text-sm sm:text-base">
                            افزودن دبیر جدید
                        </a>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-full">
                            <thead class="bg-gray-50 border-b-2 border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700">#</th>
                                    <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700">نام و نام خانوادگی</th>
                                    <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700">نام کاربری</th>
                                    <th class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 hidden sm:table-cell">تاریخ ایجاد</th>
                                    <th class="px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-700">اقدامات</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200">
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-150" id="row-<?= $row['id'] ?>">
                                        <td class="px-4 py-3 text-xs sm:text-sm text-gray-900">
                                            <?= htmlspecialchars($row['id']) ?>
                                        </td>

                                        <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 font-medium">
                                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                        </td>

                                        <td class="px-4 py-3 text-xs sm:text-sm text-gray-600">
                                            <?= htmlspecialchars($row['username']) ?>
                                        </td>

                                        <td class="px-4 py-3 text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                            <?= htmlspecialchars($row['created_at']) ?>
                                        </td>

                                        <td class="px-4 py-3 text-center">
                                            <div class="flex justify-center gap-2">

                                                <!-- دکمه حذف -->
                                                <button onclick="showConfirm(<?= $row['id'] ?>)"
                                                    class="delete-btn-<?= $row['id'] ?> px-3 py-1.5 bg-red-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-red-700 transition-colors duration-200">
                                                    حذف
                                                </button>

                                                <!-- تایید حذف -->
                                                <div class="delete-confirm delete-confirm-<?= $row['id'] ?> gap-2">
                                                    <a href="teachers.php?delete_id=<?= $row['id'] ?>"
                                                        class="px-3 py-1.5 bg-red-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-red-700 transition-colors duration-200">
                                                        تأیید
                                                    </a>

                                                    <button onclick="hideConfirm(<?= $row['id'] ?>)"
                                                        class="px-3 py-1.5 bg-gray-300 text-gray-700 text-xs sm:text-sm font-medium rounded-lg hover:bg-gray-400 transition-colors duration-200">
                                                        لغو
                                                    </button>
                                                </div>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>

                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-800 text-xs sm:text-sm">برای حذف هر دبیر، روی دکمه حذف کلیک کرده و سپس تأیید نمایید.</p>
            </div>

        </div>
    </div>

    <script>
        function showConfirm(id) {
            document.querySelector('.delete-btn-' + id).style.display = 'none';
            document.querySelector('.delete-confirm-' + id).classList.add('active');
        }

        function hideConfirm(id) {
            document.querySelector('.delete-btn-' + id).style.display = 'inline-block';
            document.querySelector('.delete-confirm-' + id).classList.remove('active');
        }
    </script>

</html>