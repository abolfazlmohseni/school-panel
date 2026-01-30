<?php
// api/controllers/AuthController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {
    
    public function login($data) {
        session_start();
        
        if (!isset($data['username']) || !isset($data['password'])) {
            return [
                'success' => false,
                'message' => 'ورودی نامعتبر.',
                'error_code' => 'INVALID_INPUT'
            ];
        }
        
        $username = trim($data['username']);
        $password = $data['password'];
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // ذخیره اطلاعات کاربر در سشن
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['last_activity'] = time();
                
                // ایجاد توکن اگر نیاز باشد (برای موبایل)
                $token = bin2hex(random_bytes(32));
                $_SESSION['api_token'] = $token;
                
                return [
                    'success' => true,
                    'message' => 'ورود موفقیت‌آمیز.',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                        'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                        'token' => $token
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'رمز عبور اشتباه است.',
                    'error_code' => 'WRONG_PASSWORD'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'کاربر یافت نشد.',
                'error_code' => 'USER_NOT_FOUND'
            ];
        }
    }
    
    public function logout() {
        session_start();
        
        // تخریب تمام session
        session_unset();
        session_destroy();
        
        // حذف کوکی session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        return [
            'success' => true,
            'message' => 'خروج موفقیت‌آمیز.'
        ];
    }
    
    public function check() {
        $user = AuthMiddleware::getUser();
        
        return [
            'success' => true,
            'authenticated' => $user !== null,
            'user' => $user
        ];
    }
    
    public function getProfile() {
        $userId = AuthMiddleware::check();
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id, username, role, first_name, last_name, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        return [
            'success' => true,
            'data' => $user
        ];
    }
}
?>