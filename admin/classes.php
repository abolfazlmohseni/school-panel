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
$i = 1;
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>لیست کلاس‌ها</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../styles/output.css">
    <style>
        body {
            box-sizing: border-box;
        }

        .sidebar {
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-hidden {
            transform: translateX(100%);
        }

        @media (min-width: 1024px) {
            .sidebar-hidden {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body class="min-h-full bg-gray-100">
    <!-- Mobile Menu Button -->
    <button onclick="toggleSidebar()"
        class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-blue-600 text-white rounded-lg shadow-lg">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Overlay for mobile -->
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden">
    </div>

    <!-- Sidebar -->
    <aside id="sidebar"
        class="sidebar sidebar-hidden lg:sidebar-hidden-false fixed top-0 right-0 h-full w-64 bg-white shadow-xl z-40">
        <div class="h-full flex flex-col">
            <!-- Logo & Title -->
            <div class="p-6 bg-gradient-to-br from-blue-600 to-blue-800">
                <h1 class="text-xl font-bold text-white mb-1">هنرستان سپهری راد</h1>
                <p class="text-blue-100 text-sm">سامانه حضور و غیاب</p>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                </path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="teachers.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            بخش دبیران
                        </a>
                    </li>
                    <li>
                        <a href="classes.php"
                            class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                            کلاس ها
                        </a>
                    </li>
                    <li>
                        <a href="students.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                            دانش آموزان
                        </a>
                    </li>
                    <li>
                        <a href="programs.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            برنامه زمانی
                        </a>
                    </li>
                    <li>
                        <a href="today_absent.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z">
                                </path>
                            </svg>
                            غایبین امروز
                        </a>
                    </li>
                    <li>
                        <a href="absent_history.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            تاریخچه غیبت‌ها
                        </a>
                    </li>
                    <li>
                        <a href="send_sms.php"
                            class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            ارسال پیامک
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="/logout.php"
                    class="w-full flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                        </path>
                    </svg>
                    خروج
                </a>
            </div>
        </div>
    </aside>
    <!-- Main Content -->
    <div class="min-h-screen lg:mr-64">
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="w-full">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">لیست کلاس‌ها</h1>
                    <p class="text-gray-600 text-sm sm:text-base">سامانه حضور غیاب هنرستان سپهری راد</p>
                </div>
                <!-- Main Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-4 sm:p-6">
                        <!-- Action Bar -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">مدیریت کلاس‌ها</h2>
                            <a href="class_add.php"
                                class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 text-center text-sm sm:text-base">
                                افزودن کلاس جدید </a>
                        </div>
                        <?php if (isset($_GET['success'])): ?>
                            <div class="mb-4 p-3 rounded-lg bg-green-100 border border-green-400 text-green-700">
                                <?php
                                $msg = [
                                    1 => 'کلاس با موفقیت اضافه شد.',
                                    2 => 'کلاس با موفقیت ویرایش شد.',
                                    3 => 'کلاس با موفقیت حذف شد.'
                                ];
                                echo $msg[$_GET['success']] ?? 'عملیات موفقیت‌آمیز بود.';
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
                            <div class="mb-4 p-3 rounded-lg bg-red-100 border border-red-400 text-red-700">
                                امکان حذف کلاس وجود ندارد. ابتدا دانش‌آموزان این کلاس را حذف کنید.
                            </div>
                        <?php endif; ?>
                        <!-- Search Form -->
                        <form method="GET" class="mb-6 flex flex-col sm:flex-row gap-3">
                            <input type="text" name="search" placeholder="جستجو بر اساس نام کلاس ..."
                                value="<?= htmlspecialchars($search) ?>"
                                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-sm sm:text-base">
                            <button type="submit"
                                class="px-6 py-2.5 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors duration-200 text-sm sm:text-base">جستجو</button>
                        </form>
                        <!-- Table Container -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b-2 border-gray-200">
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            #</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            نام کلاس</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            تعداد دانش‌آموزان</th>
                                        <th
                                            class="px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            اقدامات</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <?= $i ?>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm whitespace-nowrap">
                                                    <a href="class_students.php?id=<?= $row['id'] ?>"
                                                        class="text-blue-600 hover:text-blue-800 font-medium hover:underline">
                                                        <?= htmlspecialchars($row['name']) ?>
                                                    </a>
                                                </td>

                                                <td class="px-4 py-3 text-xs sm:text-sm text-gray-900 whitespace-nowrap">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?= $row['student_count'] ?> نفر
                                                    </span>
                                                </td>

                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    <div class="flex gap-2 justify-center flex-wrap">
                                                        <a href="class_edit.php?id=<?= $row['id'] ?>"
                                                            class="px-3 py-1.5 bg-yellow-500 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-yellow-600 transition-colors duration-200">
                                                            ویرایش
                                                        </a>

                                                        <a href="class_delete.php?id=<?= $row['id'] ?>"
                                                            onclick="return confirm('آیا مطمئن هستید این کلاس حذف شود؟')"
                                                            class="px-3 py-1.5 bg-red-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-red-700 transition-colors duration-200">
                                                            حذف
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                            $i += 1;
                                        endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4"
                                                class="text-center px-4 py-8 text-gray-500 text-sm sm:text-base">
                                                هیچ کلاسی یافت نشد.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800 text-xs sm:text-sm">💡 با کلیک روی نام هر کلاس، می‌توانید دانش‌آموزان آن
                        کلاس را مشاهده کنید.</p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
        }
    </script>

</html>