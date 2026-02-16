<?php
// school-panel-api/backend/api/controllers/AuthController.php

class AuthController {
    
    private function getDB() {
        // اتصال مستقیم به دیتابیس
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'sepehrir_school';
        
        $conn = new mysqli($host, $username, $password, $database);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    }
    
    public function login($data) {
        error_log("Login attempt with data: " . json_encode($data));
        
        if (!isset($data['username']) || !isset($data['password'])) {
            return [
                'success' => false,
                'message' => 'نام کاربری و رمز عبور الزامی است.',
                'error_code' => 'INVALID_INPUT'
            ];
        }
        
        $username = trim($data['username']);
        $password = $data['password'];
        
        try {
            $conn = $this->getDB();
            
            $stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    // ذخیره اطلاعات در session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['last_activity'] = time();
                    
                    error_log("Login successful for user: " . $username);
                    
                    return [
                        'success' => true,
                        'message' => 'ورود موفقیت‌آمیز.',
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'role' => $user['role'],
                            'full_name' => $user['first_name'] . ' ' . $user['last_name']
                        ]
                    ];
                } else {
                    error_log("Login failed: Wrong password for " . $username);
                    return [
                        'success' => false,
                        'message' => 'رمز عبور اشتباه است.',
                        'error_code' => 'WRONG_PASSWORD'
                    ];
                }
            } else {
                error_log("Login failed: User not found - " . $username);
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.',
                    'error_code' => 'USER_NOT_FOUND'
                ];
            }
            
            $stmt->close();
            $conn->close();
            
        } catch (Exception $e) {
            error_log("Database error in login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرور.',
                'error_code' => 'DATABASE_ERROR'
            ];
        }
    }
    
    public function logout() {
        // تخریب session
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
        if (isset($_SESSION['user_id'])) {
            return [
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'role' => $_SESSION['role'],
                    'full_name' => $_SESSION['full_name']
                ]
            ];
        } else {
            return [
                'success' => true,
                'authenticated' => false
            ];
        }
    }
    
    public function getProfile() {
        if (!isset($_SESSION['user_id'])) {
            return [
                'success' => false,
                'message' => 'احراز هویت ناموفق',
                'error_code' => 'UNAUTHORIZED'
            ];
        }
        
        try {
            $conn = $this->getDB();
            
            $stmt = $conn->prepare("SELECT id, username, role, first_name, last_name, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            $stmt->close();
            $conn->close();
            
            return [
                'success' => true,
                'data' => $user
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در دریافت اطلاعات پروفایل',
                'error_code' => 'DATABASE_ERROR'
            ];
        }
    }
}
?>