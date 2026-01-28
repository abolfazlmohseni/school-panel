<?php
header('Content-Type: application/json');

// تنظیمات CORS برای توسعه
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../user/config.php';

// کلید مخفی برای JWT (در محیط واقعی از محیط متغیرها استفاده کنید)
define('JWT_SECRET', 'your-secret-key-change-this-in-production');

// پاسخ استاندارد API
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// پاسخ خطا
function jsonError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

// پاسخ موفقیت
function jsonSuccess($data = null, $message = '') {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    jsonResponse($response);
}

// دریافت داده JSON از درخواست
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON data');
    }
    
    return $data;
}
?>