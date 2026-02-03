<?php
// api/controllers/TeacherController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class TeacherController {
    
    // دریافت لیست تمام دبیران
    public function getAll() {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, username, created_at 
            FROM users 
            WHERE role = 'teacher' 
            ORDER BY id DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $teachers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $teachers,
            'count' => count($teachers)
        ];
    }
    
    // دریافت اطلاعات یک دبیر
    public function getOne($data) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        if (!isset($data['id'])) {
            return [
                'success' => false,
                'message' => 'شناسه دبیر الزامی است.',
                'error_code' => 'TEACHER_ID_REQUIRED'
            ];
        }
        
        $id = (int)$data['id'];
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, username 
            FROM users 
            WHERE id = ? AND role = 'teacher'
            LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'دبیر مورد نظر یافت نشد.',
                'error_code' => 'TEACHER_NOT_FOUND'
            ];
        }
        
        $teacher = $result->fetch_assoc();
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $teacher
        ];
    }
    
    // افزودن دبیر جدید
    public function create($data) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        // اعتبارسنجی فیلدهای الزامی
        $required_fields = ['first_name', 'last_name', 'username', 'password'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return [
                    'success' => false,
                    'message' => "فیلد {$field} الزامی است.",
                    'error_code' => 'MISSING_FIELD_' . strtoupper($field)
                ];
            }
        }
        
        $first_name = trim($data['first_name']);
        $last_name = trim($data['last_name']);
        $username = trim($data['username']);
        $password = $data['password'];
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // بررسی تکراری نبودن نام کاربری
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'نام کاربری قبلا استفاده شده است.',
                'error_code' => 'USERNAME_EXISTS'
            ];
        }
        $stmt->close();
        
        // هش کردن رمز عبور
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // درج دبیر جدید
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, role, first_name, last_name) 
            VALUES (?, ?, 'teacher', ?, ?)
        ");
        $stmt->bind_param('ssss', $username, $hashed_password, $first_name, $last_name);
        
        if ($stmt->execute()) {
            $teacher_id = $stmt->insert_id;
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'دبیر با موفقیت اضافه شد.',
                'data' => [
                    'id' => $teacher_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username
                ]
            ];
        } else {
            $error = $stmt->error;
            $stmt->close();
            
            return [
                'success' => false,
                'message' => 'خطا در اضافه کردن دبیر: ' . $error,
                'error_code' => 'CREATE_TEACHER_ERROR'
            ];
        }
    }
    
    // ویرایش دبیر
    public function update($data) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        // اعتبارسنجی فیلدهای الزامی
        if (!isset($data['id'])) {
            return [
                'success' => false,
                'message' => 'شناسه دبیر الزامی است.',
                'error_code' => 'TEACHER_ID_REQUIRED'
            ];
        }
        
        $id = (int)$data['id'];
        $first_name = isset($data['first_name']) ? trim($data['first_name']) : '';
        $last_name = isset($data['last_name']) ? trim($data['last_name']) : '';
        $username = isset($data['username']) ? trim($data['username']) : '';
        $new_password = isset($data['new_password']) ? trim($data['new_password']) : '';
        
        if ($first_name === '' || $last_name === '' || $username === '') {
            return [
                'success' => false,
                'message' => 'نام، نام خانوادگی و نام کاربری الزامی هستند.',
                'error_code' => 'MISSING_REQUIRED_FIELDS'
            ];
        }
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // بررسی تکراری نبودن نام کاربری (به جز خود دبیر)
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $stmt->bind_param('si', $username, $id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'نام کاربری قبلا توسط دبیر دیگری استفاده شده است.',
                'error_code' => 'USERNAME_EXISTS'
            ];
        }
        $stmt->close();
        
        // اگر رمز جدید وارد شده باشد
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, username = ?, password = ? 
                WHERE id = ? AND role = 'teacher'
            ");
            $stmt->bind_param("ssssi", $first_name, $last_name, $username, $hashed_password, $id);
        } else {
            // فقط اطلاعات اصلی را آپدیت کند
            $stmt = $conn->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, username = ? 
                WHERE id = ? AND role = 'teacher'
            ");
            $stmt->bind_param("sssi", $first_name, $last_name, $username, $id);
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'اطلاعات دبیر با موفقیت به‌روزرسانی شد.',
                'data' => [
                    'id' => $id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username
                ]
            ];
        } else {
            $error = $stmt->error;
            $stmt->close();
            
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی دبیر: ' . $error,
                'error_code' => 'UPDATE_TEACHER_ERROR'
            ];
        }
    }
    
    // حذف دبیر
    public function delete($data) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        if (!isset($data['id'])) {
            return [
                'success' => false,
                'message' => 'شناسه دبیر الزامی است.',
                'error_code' => 'TEACHER_ID_REQUIRED'
            ];
        }
        
        $id = (int)$data['id'];
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // بررسی وجود دبیر
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'دبیر مورد نظر یافت نشد.',
                'error_code' => 'TEACHER_NOT_FOUND'
            ];
        }
        $stmt->close();
        
        // حذف دبیر
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'دبیر با موفقیت حذف شد.'
            ];
        } else {
            $error = $stmt->error;
            $stmt->close();
            
            return [
                'success' => false,
                'message' => 'خطا در حذف دبیر: ' . $error,
                'error_code' => 'DELETE_TEACHER_ERROR'
            ];
        }
    }
    
    // جستجوی دبیران
    public function search($data) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $search_term = isset($data['q']) ? '%' . trim($data['q']) . '%' : '%%';
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, username, created_at 
            FROM users 
            WHERE role = 'teacher' 
            AND (
                first_name LIKE ? 
                OR last_name LIKE ? 
                OR username LIKE ?
            )
            ORDER BY first_name
        ");
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $teachers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $teachers,
            'count' => count($teachers)
        ];
    }
}
?>