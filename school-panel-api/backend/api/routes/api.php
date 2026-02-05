<?php
// school-panel-api/backend/api/routes/api.php

class ApiRouter {
    private $routes = [];
    
    public function __construct() {
        $this->registerRoutes();
    }
    
    private function registerRoutes() {
        // Routeهای GET
        $this->routes['GET'] = [
            '/' => function() {
                return [
                    'success' => true, 
                    'message' => 'School Panel API',
                    'version' => '1.0',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            },
            '/test' => function() {
                return [
                    'success' => true, 
                    'message' => 'API Test Successful',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            },
            '/auth/check' => ['AuthController', 'check'],
            '/auth/profile' => ['AuthController', 'getProfile'],
            '/dashboard/stats' => ['DashboardController', 'getStats'],
            '/dashboard/all' => ['DashboardController', 'getAllData'],
            '/teachers' => ['TeacherController', 'getAll'],
            '/teachers/get' => ['TeacherController', 'getOne'],
        ];
        
        // Routeهای POST
        $this->routes['POST'] = [
            '/auth/login' => ['AuthController', 'login'],
            '/auth/logout' => ['AuthController', 'logout'],
            '/teachers' => ['TeacherController', 'create'],
            '/teachers/update' => ['TeacherController', 'update'],
            '/teachers/delete' => ['TeacherController', 'delete'],
            '/teachers/search' => ['TeacherController', 'search'],
        ];
    }
    
    public function handleRequest($request_path) {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // نرمال‌سازی مسیر
        $request_path = '/' . trim($request_path, '/');
        
        error_log("Handling request: $method $request_path");
        
        // بررسی وجود route دقیق
        if (isset($this->routes[$method][$request_path])) {
            $handler = $this->routes[$method][$request_path];
            $this->executeHandler($handler);
        } else {
            // اگر route پیدا نشد، تلاش برای یافتن مسیرهای مشابه
            $found = false;
            foreach ($this->routes[$method] as $route => $handler) {
                // تبدیل مسیرهای پویا مانند /teachers/123 به /teachers/get
                if ($this->isDynamicRoute($route, $request_path)) {
                    $this->executeHandler($handler);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // اگر route پیدا نشد
                $this->sendResponse([
                    'success' => false,
                    'message' => 'Endpoint not found: ' . $request_path,
                    'error_code' => 'NOT_FOUND',
                    'available_routes' => array_keys($this->routes[$method] ?? [])
                ], 404);
            }
        }
    }
    
    private function isDynamicRoute($route, $request_path) {
        // بررسی مسیرهای پویا (مانند /teachers/123)
        // فعلاً فقط مسیرهای دقیق را بررسی می‌کنیم
        return false;
    }
    
    private function executeHandler($handler) {
        try {
            if (is_callable($handler)) {
                // اگر handler تابع باشد
                $result = $handler();
            } elseif (is_array($handler) && count($handler) === 2) {
                // اگر handler آرایه [کلاس, متد] باشد
                list($className, $methodName) = $handler;
                
                // بررسی وجود کلاس
                if (!class_exists($className)) {
                    throw new Exception("Class $className not found. Make sure autoloader is working.");
                }
                
                // ایجاد نمونه از کلاس
                $controller = new $className();
                
                // بررسی وجود متد
                if (!method_exists($controller, $methodName)) {
                    throw new Exception("Method $methodName not found in $className");
                }
                
                // دریافت داده‌های ورودی
                $input = $this->getInputData();
                
                // فراخوانی متد
                $result = $controller->$methodName($input);
            } else {
                throw new Exception("Invalid handler type");
            }
            
            // ارسال پاسخ
            $this->sendResponse($result);
            
        } catch (Exception $e) {
            error_log("Handler execution error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->sendResponse([
                'success' => false,
                'message' => 'Handler Error: ' . $e->getMessage(),
                'error_code' => 'HANDLER_ERROR',
                'debug_info' => [
                    'handler' => $handler,
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
    
    private function getInputData() {
        $input = [];
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $jsonInput = file_get_contents('php://input');
                $input = json_decode($jsonInput, true) ?? [];
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON parse error: " . json_last_error_msg());
                    $input = [];
                }
            } else {
                $input = $_POST;
                
                // اگر POST خالی بود، سعی کن JSON رو از php://input بخونی
                if (empty($input)) {
                    $jsonInput = file_get_contents('php://input');
                    if (!empty($jsonInput)) {
                        $input = json_decode($jsonInput, true) ?? [];
                    }
                }
            }
        } elseif ($method === 'GET') {
            $input = $_GET;
        }
        
        // لاگ برای دیباگ
        error_log("Input data for $method: " . json_encode($input));
        
        return $input;
    }
    
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // اضافه کردن timestamp اگر داده آرایه باشد
        if (is_array($data) && !isset($data['timestamp'])) {
            $data['timestamp'] = date('Y-m-d H:i:s');
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
?>