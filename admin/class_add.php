<?php
session_start();
require_once '../config.php';

// چک‌کردن ورود مدیر
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
?>

<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>افزودن کلاس</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-6">

    <div class="max-w-xl mx-auto bg-white p-6 rounded shadow">

        <h1 class="text-2xl font-bold mb-4">افزودن کلاس جدید</h1>

        <form action="class_add_action.php" method="post">

            <label class="block mb-3">
                <span>نام کلاس:</span>
                <input type="text" name="class_name" class="w-full border p-2 rounded" required>
            </label>

            <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                ثبت کلاس
            </button>

        </form>

    </div>

</body>

</html>