<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>افزودن دبیر - سامانه حضور غیاب هنرستان سپهری راد</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            box-sizing: border-box;
        }

        .input-focus:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
    </style>
</head>

<body class="min-h-full bg-gray-50">
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
                            <label for="first_name" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> نام <span class="text-red-500">*</span> </label>
                            <input type="text" id="first_name" name="first_name" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="نام را وارد کنید">
                        </div>
                        <!-- Last Name -->
                        <div>
                            <label for="last_name" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> نام خانوادگی <span class="text-red-500">*</span> </label>
                            <input type="text" id="last_name" name="last_name" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="نام خانوادگی را وارد کنید">
                        </div>
                        <!-- Username -->
                        <div>
                            <label for="username" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> نام کاربری <span class="text-red-500">*</span> </label>
                            <input type="text" id="username" name="username" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="نام کاربری را وارد کنید">
                        </div>
                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-gray-700 font-medium mb-2 text-sm sm:text-base"> رمز عبور <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password" name="password" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 input-focus transition-all duration-200 text-sm sm:text-base" placeholder="رمز عبور را وارد کنید">
                        </div>
                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3 pt-4">
                            <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 text-sm sm:text-base"> افزودن دبیر </button> <a href="teachers.php" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200 text-center text-sm sm:text-base"> بازگشت به لیست دبیران </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>

</html>