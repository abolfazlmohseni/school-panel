<?php
// test-simple.php
header('Content-Type: application/json');

// تست اتصال دیتابیس
try {
    $conn = new mysqli('localhost', 'root', '', 'sepehrir_school');
    
    if ($conn->connect_error) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
    } else {
        // تست query
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'user_count' => $row['count'],
            'server' => $_SERVER
        ]);
        
        $conn->close();
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>