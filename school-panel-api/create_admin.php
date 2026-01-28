<?php
// tools/create_admin.php
require_once __DIR__ . '/user/config.php';

// تنظیمات: نام کاربری و پسوردی که میخوای بذاری
$username = 'SepehriRad';
$password_plain = '12135811228aM#@';
$first = 'مدیریت';
$last = 'مدرسه';

// هش امن
$hash = password_hash($password_plain, PASSWORD_DEFAULT);

// چک کن ببین وجود داره؛ اگر هست آپدیت کن در غیر اینصورت درج کن
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // آپدیت
    $stmt->close();
    $u = $conn->prepare("UPDATE users SET password = ?, role = 'admin', first_name = ?, last_name = ? WHERE username = ?");
    $u->bind_param('ssss', $hash, $first, $last, $username);
    if ($u->execute()) {
        echo "کاربر '$username' آپدیت شد. پسورد حالا: $password_plain\n";
    } else {
        echo "خطا در آپدیت: " . $u->error;
    }
    $u->close();
} else {
    $stmt->close();
    $i = $conn->prepare("INSERT INTO users (username, password, role, first_name, last_name) VALUES (?, ?, 'admin', ?, ?)");
    $i->bind_param('ssss', $username, $hash, $first, $last);
    if ($i->execute()) {
        echo "کاربر '$username' ساخته شد. پسورد: $password_plain\n";
    } else {
        echo "خطا در درج کاربر: " . $i->error;
    }
    $i->close();
}

$conn->close();
