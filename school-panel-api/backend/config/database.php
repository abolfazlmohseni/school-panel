<?php
// backend/config/database.php
class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'attendance_system';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            $this->conn->set_charset("utf8mb4");
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => 'خطا در اتصال به پایگاه داده'
            ]));
        }
        
        return $this->conn;
    }
}

// تنظیمات تاریخ
date_default_timezone_set('Asia/Tehran');