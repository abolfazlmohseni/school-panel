<?php
// sms_result.php
session_start();
require_once '../../user/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['sms_result'])) {
    header("Location: send_sms.php");
    exit;
}

$result = $_SESSION['sms_result'];
unset($_SESSION['sms_result']); // پاک کردن نتایج بعد از نمایش
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتایج ارسال پیامک</title>
    <link rel="stylesheet" href="../styles/output.css">
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto p-4">
        <div class="max-w-4xl mx-auto">
            <!-- هدر -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-4">نتایج ارسال پیامک</h1>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-green-700"><?php echo $result['success_count']; ?></div>
                        <div class="text-green-600">پیامک موفق</div>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-red-700"><?php echo $result['failed_count']; ?></div>
                        <div class="text-red-600">پیامک ناموفق</div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-blue-700"><?php echo $result['total_count']; ?></div>
                        <div class="text-blue-600">کل گیرندگان</div>
                    </div>
                </div>
            </div>

            <!-- جزئیات -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">جزئیات ارسال</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-600 mb-1">تاریخ:</label>
                        <div class="font-medium"><?php echo $result['date']; ?></div>
                    </div>
                    <div>
                        <label class="block text-gray-600 mb-1">ارسال کننده:</label>
                        <div class="font-medium"><?php echo htmlspecialchars($result['admin_name']); ?></div>
                    </div>
                    <div>
                        <label class="block text-gray-600 mb-1">متن پیام:</label>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <?php echo nl2br(htmlspecialchars($result['message'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- لیست ناموفق‌ها -->
            <?php if ($result['failed_count'] > 0): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold text-red-700 mb-4">پیامک‌های ناموفق (<?php echo $result['failed_count']; ?> مورد)</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-red-50">
                                <tr>
                                    <th class="p-3 text-right">#</th>
                                    <th class="p-3 text-right">نام دانش‌آموز</th>
                                    <th class="p-3 text-right">شماره تلفن</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result['failed_names'] as $index => $name): ?>
                                    <tr class="border-b border-gray-200 hover:bg-red-50">
                                        <td class="p-3"><?php echo $index + 1; ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($name); ?></td>
                                        <td class="p-3 font-mono text-red-600">
                                            <?php echo isset($result['failed_numbers'][$index]) ? htmlspecialchars($result['failed_numbers'][$index]) : ''; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- دکمه‌های اقدام -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <a href="send_sms.php" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg text-center transition-colors">
                        ارسال پیامک جدید
                    </a>
                    <a href="today_absent.php" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg text-center transition-colors">
                        مشاهده غایبین
                    </a>
                    <a href="dashboard.php" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg text-center transition-colors">
                        بازگشت به داشبورد
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>



