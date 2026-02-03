<?php
// api/routes/api.php

require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../controllers/TeacherController.php';

class ApiRouter {
    private $routes = [];
    
    public function __construct() {
        CorsMiddleware::handle();
        $this->registerRoutes();
    }
    
    private function registerRoutes() {
        $this->routes = [
            'POST' => [
                // احراز هویت
                '/api/auth/login' => ['AuthController', 'login'],
                '/api/auth/logout' => ['AuthController', 'logout'],
                
                // دبیران
                '/api/teachers' => ['TeacherController', 'create'],
                '/api/teachers/update' => ['TeacherController', 'update'],
                '/api/teachers/delete' => ['TeacherController', 'delete'],
                '/api/teachers/search' => ['TeacherController', 'search']
            ],
            'GET' => [
                // احراز هویت
                '/api/auth/check' => ['AuthController', 'check'],
                '/api/auth/profile' => ['AuthController', 'getProfile'],
                
                // داشبورد
                '/api/dashboard/stats' => ['DashboardController', 'getStats'],
                '/api/dashboard/today-classes' => ['DashboardController', 'getTodayClasses'],
                '/api/dashboard/recent-attendance' => ['DashboardController', 'getRecentAttendance'],
                '/api/dashboard/active-teachers' => ['DashboardController', 'getActiveTeachers'],
                '/api/dashboard/jalali-date' => ['DashboardController', 'getJalaliDate'],
                '/api/dashboard/all' => ['DashboardController', 'getAllData'],
                
                // دبیران
                '/api/teachers' => ['TeacherController', 'getAll'],
                '/api/teachers/get' => ['TeacherController', 'getOne'],
                
                // تست API
                '/api/test' => function() {
                    return ['success' => true, 'message' => 'API is working'];
                }
            ]
        ];
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // حذف query string از مسیر
        $path = strtok($path, '?');
        
        // یافتن مسیر مطابق
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            if ($route === $path) {
                // اگر handler یک تابع باشد
                if (is_callable($handler)) {
                    $result = $handler();
                } 
                // اگر handler یک کلاس و متد باشد
                else if (is_array($handler) && count($handler) === 2) {
                    $controllerName = $handler[0];
                    $methodName = $handler[1];
                    
                    $controller = new $controllerName();
                    
                    // دریافت داده‌های ورودی
                    $input = $this->getInputData($method);
                    
                    // فراخوانی متد کنترلر
                    $result = $controller->$methodName($input);
                } else {
                    $result = [
                        'success' => false,
                        'message' => 'Invalid route handler',
                        'error_code' => 'INVALID_HANDLER'
                    ];
                }
                
                $this->sendResponse($result);
                return;
            }
        }
        
        // اگر مسیر پیدا نشد
        $this->sendResponse([
            'success' => false,
            'message' => 'Endpoint not found.',
            'error_code' => 'NOT_FOUND'
        ], 404);
    }
    
    private function getInputData($method) {
        $input = [];
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $input = $_POST;
                
                // برای GET پارامترها در query string
                if (empty($input) && $method === 'POST') {
                    parse_str(file_get_contents('php://input'), $input);
                }
            }
            
            // همچنین query string را نیز بررسی می‌کنیم
            if (!empty($_GET)) {
                $input = array_merge($input, $_GET);
            }
        } elseif ($method === 'GET') {
            $input = $_GET;
        }
        
        return $input;
    }
    
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // اضافه کردن timestamp به پاسخ
        $data['timestamp'] = date('Y-m-d H:i:s');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
?>