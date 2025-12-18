<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("send_sms_process.php شروع شد - POST: " . print_r($_POST, true));
require_once '../../user/config.php';

// تنظیمات SMS.ir
$smsir_api_key = "**********"; // کلید API شما
$smsir_line_number = "*************"; // شماره خط شما

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'لطفاً ابتدا وارد شوید';
    header("Location: ../login.php");
    exit();
}

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    $_SESSION['sms_error'] = 'متن پیامک نمی‌تواند خالی باشد.';
    header("Location: send_sms.php");
    exit();
}

if (strlen($message) > 160) {
    $_SESSION['sms_error'] = 'طول پیام نباید بیشتر از 160 کاراکتر باشد.';
    header("Location: send_sms.php");
    exit();
}

$today = date('Y-m-d');

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.phone,
            c.name as class_name,
            c.id as class_id
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN programs p ON a.program_id = p.id
        JOIN classes c ON p.class_id = c.id
        WHERE a.attendance_date = ?
        AND a.status = 'غایب'
        AND s.phone IS NOT NULL
        AND s.phone != ''
        AND TRIM(s.phone) != ''
        AND LENGTH(TRIM(s.phone)) >= 10
        ORDER BY c.name, s.last_name, s.first_name
    ");

    if (!$stmt) {
        throw new Exception("خطای آماده‌سازی کوئری: " . $conn->error);
    }

    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['sms_error'] = 'خطا در دریافت داده‌ها: ' . $e->getMessage();
    error_log($e->getMessage());
    header("Location: send_sms.php");
    exit();
}

function gregorian_to_jalali($gy, $gm, $gd)
{
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return array($jy, $jm, $jd);
}

$today_parts = explode('-', $today);
$today_jalali = gregorian_to_jalali($today_parts[0], $today_parts[1], $today_parts[2]);
$persian_date = $today_jalali[0] . '/' . sprintf('%02d', $today_jalali[1]) . '/' . sprintf('%02d', $today_jalali[2]);

$success_count = 0;
$failed_count = 0;
$failed_numbers = [];
$failed_names = [];

if (count($recipients) == 0) {
    $_SESSION['sms_result'] = [
        'success_count' => 0,
        'failed_count' => 0,
        'total_count' => 0,
        'failed_numbers' => [],
        'failed_names' => [],
        'message' => $message,
        'admin_name' => $_SESSION['full_name'] ?? 'مدیر',
        'date' => $persian_date
    ];
    // استفاده از جاوااسکریپت برای ریدایرکت
    echo '<script>window.location.href = "sms_result.php";</script>';
    exit();
}

// تابع پاکسازی و فرمت شماره تلفن
function formatPhoneNumber($phone)
{
    // حذف فاصله و کاراکترهای غیرعددی
    $phone = preg_replace('/\D/', '', $phone);

    // اگر شماره با 0 شروع شده باشد
    if (substr($phone, 0, 1) == '0') {
        $phone = '98' . substr($phone, 1);
    }

    // اگر شماره با 98 شروع نشده باشد، اضافه کردن کد کشور
    if (substr($phone, 0, 2) != '98') {
        $phone = '98' . $phone;
    }

    return $phone;
}

// تابع بررسی اعتبار شماره
function isValidPhoneNumber($phone)
{
    $phone = preg_replace('/\D/', '', $phone);
    return (strlen($phone) >= 10 && strlen($phone) <= 14);
}

// تابع ارسال پیامک (نسخه ساده و مستقیم)
function sendSingleSMS($apiKey, $mobile, $messageText, $lineNumber)
{
    $url = "https://api.sms.ir/v1/send/bulk";

    $data = [
        "lineNumber" => $lineNumber,
        "messageText" => $messageText,
        "mobiles" => [$mobile],
        "sendDateTime" => null
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // تایم‌اوت 30 ثانیه
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        return [
            'status' => 'error',
            'message' => 'خطای ارتباط با سرور: ' . $curlError,
            'http_code' => $httpCode
        ];
    }

    $result = json_decode($response, true);

    if ($httpCode == 200 && isset($result['status']) && $result['status'] == 1) {
        return [
            'status' => 'success',
            'data' => $result,
            'message' => 'پیامک با موفقیت ارسال شد',
            'http_code' => $httpCode
        ];
    } else {
        $error_msg = isset($result['message']) ? $result['message'] : 'خطای نامشخص از API';
        return [
            'status' => 'error',
            'message' => $error_msg,
            'http_code' => $httpCode,
            'api_response' => $result
        ];
    }
}

// تابع ذخیره لاگ
function saveSMSLog(
    $conn,
    $admin_id,
    $student_id,
    $recipient_name,
    $phone_number,
    $message,
    $status,
    $api_response,
    $class_id
) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO sms_logs 
            (admin_id, student_id, recipient_name, phone_number, message, status, api_response, class_id, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $api_response_json = json_encode($api_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $api_response_json = substr($api_response_json, 0, 500); // محدود کردن طول

        $stmt->bind_param(
            "iisssssi",
            $admin_id,
            $student_id,
            $recipient_name,
            $phone_number,
            $message,
            $status,
            $api_response_json,
            $class_id
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        error_log("خطا در ذخیره لاگ برای شماره $phone_number: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang='fa' dir='rtl'>

<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>در حال ارسال پیامک</title>
    <style>
        body {
            font-family: 'Tahoma', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .loading {
            margin: 30px 0;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .progress-bar {
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
            width: 0%;
            transition: width 0.3s ease;
        }

        .status {
            margin: 15px 0;
            font-size: 18px;
            color: #555;
        }

        .success {
            color: #4CAF50;
        }

        .failed {
            color: #f44336;
        }

        .details {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
            text-align: right;
        }

        .details div {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .completed {
            background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
        }

        .completed .container {
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>

<body>
    <div class='container'>
        <h1>📱 در حال ارسال پیامک</h1>
        <p>لطفاً صبر کنید...</p>

        <div class='loading'>
            <div class='spinner'></div>
            <div class='progress-bar'>
                <div class='progress' id='progress'></div>
            </div>
            <div class='status'>
                <span id='current'>0</span> از <span id='total'><?php echo count($recipients); ?></span> پیامک ارسال شد
            </div>
            <div class='status success' id='success-count'>موفق: 0</div>
            <div class='status failed' id='failed-count'>ناموفق: 0</div>
        </div>

        <div class='details' id='details'>
            <div>آغاز فرآیند ارسال...</div>
        </div>
    </div>

    <script>
        function updateProgress(current, total, success, failed) {
            document.getElementById('current').textContent = current;
            document.getElementById('total').textContent = total;
            document.getElementById('success-count').textContent = 'موفق: ' + success;
            document.getElementById('failed-count').textContent = 'ناموفق: ' + failed;
            document.getElementById('progress').style.width = (current / total * 100) + '%';
        }

        function addDetail(message) {
            const details = document.getElementById('details');
            const div = document.createElement('div');
            div.textContent = message;
            details.appendChild(div);
            details.scrollTop = details.scrollHeight;
        }

        function completeProcess() {
            document.body.classList.add('completed');
            document.querySelector('h1').textContent = '✅ ارسال تکمیل شد';
            document.querySelector('p').textContent = 'در حال انتقال به صفحه نتایج...';
            document.querySelector('.spinner').style.display = 'none';

            // ریدایرکت به صفحه نتایج بعد از 2 ثانیه
            setTimeout(function() {
                window.location.href = 'sms_result.php';
            }, 2000);
        }
    </script>
</body>

</html>
<?php
// ارسال پیامک‌ها
$admin_id = $_SESSION['user_id'];
$total_recipients = count($recipients);
$sent_count = 0;

ob_flush();
flush();

foreach ($recipients as $index => $recipient) {
    $original_phone = trim($recipient['phone']);
    $student_name = $recipient['first_name'] . ' ' . $recipient['last_name'];

    // بررسی اعتبار شماره تلفن
    if (!isValidPhoneNumber($original_phone)) {
        $failed_count++;
        $failed_numbers[] = $original_phone;
        $failed_names[] = $student_name;

        // ذخیره لاگ خطا
        saveSMSLog(
            $conn,
            $admin_id,
            $recipient['student_id'],
            $student_name,
            $original_phone,
            '',
            'failed',
            'شماره تلفن نامعتبر',
            $recipient['class_id']
        );

        echo "<script>addDetail('❌ شماره نامعتبر: {$student_name}');</script>";
        echo str_repeat(' ', 1024); // برای flush کردن بافر
        ob_flush();
        flush();
        continue;
    }

    $phone = formatPhoneNumber($original_phone);

    // شخصی‌سازی پیام
    $personalized_message = str_replace(
        ['{name}', '{class}', '{date}'],
        [$student_name, $recipient['class_name'], $persian_date],
        $message
    );

    // ارسال پیامک
    try {
        $result = sendSingleSMS($smsir_api_key, $phone, $personalized_message, $smsir_line_number);

        if ($result['status'] == 'success') {
            $success_count++;
            $status = 'success';
            $api_response = [
                'message' => 'ارسال موفق',
                'batchId' => $result['data']['batchId'] ?? null,
                'http_code' => $result['http_code']
            ];

            echo "<script>addDetail('✅ ارسال موفق به {$student_name}');</script>";
        } else {
            $failed_count++;
            $failed_numbers[] = $original_phone;
            $failed_names[] = $student_name;
            $status = 'failed';
            $api_response = [
                'error' => $result['message'],
                'http_code' => $result['http_code'],
                'api_response' => $result['api_response'] ?? null
            ];

            echo "<script>addDetail('❌ خطا در ارسال به {$student_name}: {$result['message']}');</script>";
        }

        // ذخیره لاگ
        saveSMSLog(
            $conn,
            $admin_id,
            $recipient['student_id'],
            $student_name,
            $original_phone,
            $personalized_message,
            $status,
            $api_response,
            $recipient['class_id']
        );

        $sent_count++;

        // به روز رسانی پیشرفت
        echo "<script>updateProgress($sent_count, $total_recipients, $success_count, $failed_count);</script>";
        echo str_repeat(' ', 1024); // برای flush کردن بافر
        ob_flush();
        flush();

        // تاخیر بین ارسال‌ها برای جلوگیری از Rate Limit
        if ($total_recipients > 10) {
            usleep(500000); // 0.5 ثانیه تاخیر
        }

        // اگر تعداد زیاد است، session را ذخیره کنید
        if ($sent_count % 20 == 0) {
            session_write_close();
            session_start();
        }
    } catch (Exception $e) {
        $failed_count++;
        $failed_numbers[] = $original_phone;
        $failed_names[] = $student_name;

        error_log("خطای غیرمنتظره در ارسال به {$original_phone}: " . $e->getMessage());

        echo "<script>addDetail('⚠️ خطای سیستمی برای {$student_name}');</script>";
        echo str_repeat(' ', 1024);
        ob_flush();
        flush();
    }
}

// ذخیره نتایج نهایی در session
$_SESSION['sms_result'] = [
    'success_count' => $success_count,
    'failed_count' => $failed_count,
    'total_count' => $total_recipients,
    'failed_numbers' => $failed_numbers,
    'failed_names' => $failed_names,
    'message' => $message,
    'admin_name' => $_SESSION['full_name'] ?? 'مدیر',
    'date' => $persian_date,
    'recipient_count' => $total_recipients,
    'processing_time' => date('Y-m-d H:i:s')
];

// اتمام فرآیند
echo "<script>
    addDetail('✅ فرآیند ارسال تکمیل شد');
    updateProgress($total_recipients, $total_recipients, $success_count, $failed_count);
    completeProcess();
</script>";
echo str_repeat(' ', 1024);
ob_flush();
flush();

// پایان فایل
exit();
