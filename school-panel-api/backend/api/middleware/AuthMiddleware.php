<?php
// api/middleware/AuthMiddleware.php

class AuthMiddleware {
    public static function check() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'احراز هویت ناموفق',
                'error_code' => 'UNAUTHORIZED'
            ]);
            exit;
        }
        
        return $_SESSION['user_id'];
    }
    
    public static function checkRole($allowedRoles) {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'احراز هویت ناموفق',
                'error_code' => 'UNAUTHORIZED'
            ]);
            exit;
        }
        
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'دسترسی غیرمجاز',
                'error_code' => 'FORBIDDEN'
            ]);
            exit;
        }
        
        return $_SESSION['user_id'];
    }
    
    public static function getUser() {
        session_start();
        
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'full_name' => $_SESSION['full_name']
            ];
        }
        
        return null;
    }
}
?>