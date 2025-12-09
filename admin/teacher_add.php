<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>افزودن دبیر جدید - سامانه حضور غیاب هنرستان سپهری راد</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
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

        .input-focus:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-hidden lg:sidebar-hidden-false fixed top-0 right-0 h-full w-64 bg-white shadow-xl z-40">
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
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="teachers.php" class="flex items-center gap-3 px-4 py-3 text-white bg-blue-600 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            بخش دبیران
                        </a>
                    </li>
                    <li>
                        <a href="classes.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            کلاس ها
                        </a>
                    </li>
                    <li>
                        <a href="students.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            دانش آموزان
                        </a>
                    </li>
                    <li>
                        <a href="programs.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            برنامه زمانی
                        </a>
                    </li>
                    <li>
                        <a href="today_absent.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.157 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            غایبین امروز
                        </a>
                    </li>
                    <li>
                        <a href="send_sms.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            ارسال پیامک
                        </a>
                    </li>

                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="/attendance-system/logout.php"
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
        <div class="w-full min-h-screen py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">افزودن دبیر جدید</h1>
                    <p class="text-gray-600 text-sm sm:text-base">سامانه حضور غیاب هنرستان سپهری راد</p>
                </div>
                <!-- Main Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 sm:p-8">
                        <!-- Flash Message Placeholder -->
                        <form action="teacher_add_action.php" method="POST" class="space-y-6">
                            <!-- First Name -->
                            <div>
                                <label for="first_name" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> نام
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="first_name" autofocus name="first_name" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="نام را وارد کنید">
                            </div>
                            <!-- Last Name -->
                            <div>
                                <label for="last_name" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> نام خانوادگی
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="نام خانوادگی را وارد کنید">
                            </div>
                            <!-- Username -->
                            <div>
                                <label for="username" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> نام کاربری
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="username" name="username" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="نام کاربری را وارد کنید">
                            </div>
                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> رمز عبور
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password" name="password" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="رمز عبور را وارد کنید">
                            </div>
                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                                <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 text-sm sm:text-base"> افزودن دبیر </button>
                                <a href="teachers.php" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200 text-center text-sm sm:text-base"> بازگشت به لیست دبیران </a>
                            </div>
                        </form>
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
    </script>

</html>