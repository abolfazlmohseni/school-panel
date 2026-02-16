<?php
// school-panel-api/backend/api/index.php

// نمایش خطاها
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تنظیمات CORS
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// پاسخ به OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// تنظیم session (بهتر است اینجا باشد تا در همه کنترلرها در دسترس باشد)
session_name('api_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// گرفتن مسیر درخواست
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// حذف بخش base از مسیر
$base_path = dirname($script_name);
if ($base_path !== '/' && $base_path !== '\\') {
    $request_path = substr($request_uri, strlen($base_path));
} else {
    $request_path = $request_uri;
}

// حذف query string
$request_path = strtok($request_path, '?');

// نرمال‌سازی مسیر (حذف اسلش‌های اضافی)
$request_path = '/' . trim($request_path, '/');

// دیباگ
error_log("Base Path: $base_path");
error_log("Request URI: $request_uri");
error_log("Request Path: $request_path");

// آپلود اتولودر
require_once __DIR__ . '/autoloader.php';

// ایجاد روتر و پردازش درخواست
try {
    $router = new ApiRouter();
    $router->handleRequest($request_path);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Server Error: ' . $e->getMessage(),
        'error_code' => 'INTERNAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>