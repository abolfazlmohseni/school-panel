<?php
// school-panel-api/backend/api/autoloader.php

spl_autoload_register(function ($className) {
    $baseDir = __DIR__ . '/';
    
    // مسیرهای ممکن برای کلاس‌ها
    $paths = [
        'controllers/' . $className . '.php',
        'models/' . $className . '.php',
        'libraries/' . $className . '.php',
        $className . '.php'
    ];
    
    foreach ($paths as $path) {
        $file = $baseDir . $path;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    error_log("Class not found: $className");
    
    // در صورت عدم یافتن کلاس، خطا ندهید تا کلاس‌های سیستمی PHP نیز کار کنند
});
?>