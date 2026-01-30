<?php
// api/index.php

// تنظیمات session برای API
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Lax');

// شروع session با آیدی خاص برای API
session_name('api_session');

require_once __DIR__ . '/routes/api.php';

$router = new ApiRouter();
$router->handleRequest();
?>