<?php
// backend/api/auth/login.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// اجازه‌دهی به پیش‌درخواست‌های OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

// فقط POST مجاز است
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// دریافت داده‌های JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'نام کاربری و رمز عبور الزامی است'
    ]);
    exit();
}

$username = trim($data['username']);
$password = $data['password'];

$database = new Database();
$conn = $database->getConnection();

// دریافت اطلاعات کاربر
$stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['password'])) {
        // اطلاعات کاربر بدون رمز عبور
        unset($user['password']);
        
        echo json_encode([
            'success' => true,
            'message' => 'ورود موفقیت‌آمیز',
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'رمز عبور اشتباه است'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'کاربر یافت نشد'
    ]);
}

$stmt->close();
$conn->close();