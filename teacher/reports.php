<?php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'دبیر';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT c.id) as total_classes
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    WHERE p.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as total_students
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    JOIN students s ON s.class_id = c.id
    WHERE p.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_students'] = $result->fetch_assoc()['total_students'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_attendance,
        SUM(CASE WHEN status = 'حاضر' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN status = 'غایب' THEN 1 ELSE 0 END) as total_absent
    FROM attendance 
    WHERE teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$attendance_stats = $result->fetch_assoc();
$stmt->close();

$attendance_rate = $attendance_stats['total_attendance'] > 0
    ? round(($attendance_stats['total_present'] / $attendance_stats['total_attendance']) * 100, 1)
    : 0;


$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name as class_name,
        COUNT(DISTINCT s.id) as student_count,
        COUNT(a.id) as attendance_count,
        SUM(CASE WHEN a.status = 'حاضر' THEN 1 ELSE 0 END) as present_count,
        ROUND((SUM(CASE WHEN a.status = 'حاضر' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as attendance_rate
    FROM programs p
    JOIN classes c ON p.class_id = c.id
    LEFT JOIN students s ON s.class_id = c.id
    LEFT JOIN attendance a ON a.program_id = p.id AND a.teacher_id = ?
    WHERE p.teacher_id = ?
    GROUP BY c.id, c.name
    ORDER BY c.name
");
$stmt->bind_param("ii", $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$class_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>گزارش‌ها و آمار</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../styles/output.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background-color: #e5e7eb;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .report-card {
            transition: all 0.2s ease;
        }

        .report-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="min-h-full bg-gray-100">
    <!-- Mobile Menu Button -->
    <button onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-blue-600 text-white rounded-lg shadow-lg">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Overlay for mobile -->
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-30 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-hidden lg:sidebar-hidden-false fixed top-0 right-0 h-full w-64 bg-white shadow-xl z-40">
        <div class="h-full flex flex-col">
            <!-- Logo & User Info -->
           <div class="p-6 bg-gradient-to-br from-blue-600 to-blue-800">
                <h1 class="text-xl font-bold text-white mb-3">هنرستان سپهری راد</h1>
                <div class="flex items-center gap-3 bg-white/20 rounded-lg p-3">
                    <div class="w-10 h-10 bg-white text-blue-600 rounded-full flex items-center justify-center font-bold text-lg">
                        <?php echo mb_substr($first_name, 0, 1, 'UTF-8') . mb_substr($last_name, 0, 1, 'UTF-8'); ?>
                    </div>
                    <div>
                        <div class="font-medium text-white text-sm">
                            <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?>
                        </div>
                        <div class="text-xs text-blue-100">
                            دبیر
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="today_classes.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            کلاس‌های امروز
                        </a>
                    </li>
                    <li>
                        <a href="weekly_schedule.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            برنامه هفتگی
                        </a>
                    </li>
                    <li>
                        <a href="attendance_history.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            تاریخچه حضور
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            گزارش‌ها
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="../logout.php"
                    onclick="return confirm('آیا می‌خواهید خارج شوید؟')"
                    class="w-full flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    خروج
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="min-h-screen lg:mr-64">
        <div class="w-full min-h-screen py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">گزارش‌ها و آمار</h1>
                    <p class="text-gray-600 text-sm sm:text-base">
                        <?php echo htmlspecialchars($full_name); ?> عزیز،
                        <span class="text-blue-600 font-medium">آمار کلی و گزارش‌های عملکردی شما</span>
                    </p>
                </div>

                <!-- آمار کلی -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <!-- کل کلاس‌ها -->
                    <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $stats['total_classes'] ?? 0; ?></h3>
                        <p class="text-blue-100 text-sm">کل کلاس‌ها</p>
                    </div>

                    <!-- کل دانش‌آموزان -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $stats['total_students'] ?? 0; ?></h3>
                        <p class="text-green-100 text-sm">کل دانش‌آموزان</p>
                    </div>

                    <!-- ثبت حضور و غیاب -->
                    <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $attendance_stats['total_attendance'] ?? 0; ?></h3>
                        <p class="text-purple-100 text-sm">ثبت حضور و غیاب</p>
                    </div>

                    <!-- میانگین حضور -->
                    <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-md p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold mb-1"><?php echo $attendance_rate; ?>%</h3>
                        <p class="text-orange-100 text-sm">میانگین حضور</p>
                    </div>
                </div>
                <!-- آمار کلاس‌ها -->
                <div class="bg-white rounded-xl shadow-lg p-6 report-card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">آمار کلاس‌ها</h2>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                            <?php echo count($class_stats); ?> کلاس
                        </span>
                    </div>

                    <?php if (count($class_stats) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($class_stats as $class): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition hover:shadow-sm">
                                    <div class="flex justify-between items-center mb-3">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo $class['student_count']; ?> دانش‌آموز
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>نرخ حضور</span>
                                            <span><?php echo $class['attendance_rate']; ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-blue-500"
                                                style="width: <?php echo min($class['attendance_rate'], 100); ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between text-sm">
                                        <span class="text-green-600 font-medium">
                                            ✅ حاضر: <?php echo $class['present_count'] ?? 0; ?>
                                        </span>
                                        <span class="text-gray-500">
                                            📊 کل ثبت‌ها: <?php echo $class['attendance_count'] ?? 0; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <div class="text-gray-400 mb-4">
                                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-700 mb-2">هنوز آماری برای نمایش وجود ندارد.</p>
                            <p class="text-sm text-gray-500">پس از ثبت چند حضور و غیاب، آمار در اینجا نمایش داده می‌شود.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- نمودار حضور و غیاب -->
                <div class="mt-8 bg-white rounded-xl shadow-lg p-6 report-card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">نمودار حضور و غیاب</h2>
                        <span class="text-sm text-gray-500">
                            آمار کلی ثبت‌ها
                        </span>
                    </div>

                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-green-500 rounded-full ml-2"></div>
                                <div class="text-green-800 font-medium">حاضرین</div>
                            </div>
                            <div class="text-2xl font-bold text-green-700 mt-1"><?php echo $attendance_stats['total_present'] ?? 0; ?></div>
                            <div class="text-sm text-green-600 mt-1">تعداد ثبت شده</div>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-500 rounded-full ml-2"></div>
                                <div class="text-red-800 font-medium">غایبین</div>
                            </div>
                            <div class="text-2xl font-bold text-red-700 mt-1"><?php echo $attendance_stats['total_absent'] ?? 0; ?></div>
                            <div class="text-sm text-red-600 mt-1">تعداد ثبت شده</div>
                        </div>
                    </div>
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

        // نمودار
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['حاضر', 'غایب'],
                datasets: [{
                    data: [
                        <?php echo $attendance_stats['total_present'] ?? 0; ?>,
                        <?php echo $attendance_stats['total_absent'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#10b981', 
                        '#ef4444' 
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true,
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                family: 'yekan, sans-serif',
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        rtl: true,
                        bodyFont: {
                            family: 'yekan, sans-serif'
                        },
                        titleFont: {
                            family: 'yekan, sans-serif'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>