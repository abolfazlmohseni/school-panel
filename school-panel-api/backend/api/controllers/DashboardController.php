<?php
// api/controllers/DashboardController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class DashboardController {
    
    // دریافت آمار کلی داشبورد
    public function getStats() {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // آمار دانش‌آموزان
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_students = $result->fetch_assoc()['total'];
        $stmt->close();
        
        // آمار کلاس‌ها
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM classes");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_classes = $result->fetch_assoc()['total'];
        $stmt->close();
        
        // آمار معلمان
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_teachers = $result->fetch_assoc()['total'];
        $stmt->close();
        
        // آمار حضور و غیاب امروز
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'حاضر' THEN 1 ELSE 0 END) as present_count
            FROM attendance 
            WHERE attendance_date = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_data = $result->fetch_assoc();
        $stmt->close();
        
        $today_attendance_rate = $attendance_data['total_records'] > 0
            ? round(($attendance_data['present_count'] / $attendance_data['total_records']) * 100, 0)
            : 0;
        
        // روز هفته فارسی
        $weekdays_persian = [
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنج‌شنبه',
            5 => 'جمعه',
            6 => 'شنبه'
        ];
        $weekday_number = date('w');
        $today_persian = $weekdays_persian[$weekday_number];
        
        // تعداد کلاس‌های امروز
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT p.id) as total 
            FROM programs p 
            WHERE p.day_of_week = ?
        ");
        $stmt->bind_param("s", $today_persian);
        $stmt->execute();
        $result = $stmt->get_result();
        $today_classes_count = $result->fetch_assoc()['total'];
        $stmt->close();
        
        return [
            'success' => true,
            'data' => [
                'stats' => [
                    'total_students' => (int)$total_students,
                    'total_classes' => (int)$total_classes,
                    'total_teachers' => (int)$total_teachers,
                    'today_attendance_rate' => (int)$today_attendance_rate,
                    'today_attendance_count' => (int)($attendance_data['total_records'] ?? 0),
                    'today_classes_count' => (int)$today_classes_count
                ],
                'today_info' => [
                    'persian_day' => $today_persian,
                    'gregorian_date' => $today,
                    'attendance_present' => (int)($attendance_data['present_count'] ?? 0),
                    'attendance_total' => (int)($attendance_data['total_records'] ?? 0)
                ]
            ]
        ];
    }
    
    // دریافت کلاس‌های امروز
    public function getTodayClasses() {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // روز هفته فارسی
        $weekdays_persian = [
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنج‌شنبه',
            5 => 'جمعه',
            6 => 'شنبه'
        ];
        $weekday_number = date('w');
        $today_persian = $weekdays_persian[$weekday_number];
        
        $stmt = $conn->prepare("
            SELECT 
                p.id as program_id,
                c.name as class_name,
                p.schedule,
                u.first_name as teacher_first_name,
                u.last_name as teacher_last_name,
                (
                    SELECT COUNT(*) 
                    FROM students s 
                    WHERE s.class_id = c.id
                ) as student_count
            FROM programs p
            JOIN classes c ON p.class_id = c.id
            JOIN users u ON p.teacher_id = u.id
            WHERE p.day_of_week = ?
            ORDER BY p.schedule
        ");
        $stmt->bind_param("s", $today_persian);
        $stmt->execute();
        $result = $stmt->get_result();
        $today_classes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'data' => [
                'day_name' => $today_persian,
                'classes' => $today_classes
            ]
        ];
    }
    
    // دریافت آخرین حضور و غیاب‌ها
    public function getRecentAttendance($limit = 5) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                a.id,
                a.attendance_date,
                a.status,
                s.first_name as student_first_name,
                s.last_name as student_last_name,
                c.name as class_name,
                u.first_name as teacher_first_name,
                u.last_name as teacher_last_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN programs p ON a.program_id = p.id
            JOIN classes c ON p.class_id = c.id
            JOIN users u ON a.teacher_id = u.id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $recent_attendance = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $recent_attendance
        ];
    }
    
    // دریافت معلمان فعال
    public function getActiveTeachers($limit = 5) {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                id,
                first_name,
                last_name,
                username
            FROM users 
            WHERE role = 'teacher'
            ORDER BY first_name
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $teachers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $teachers
        ];
    }
    
    // دریافت تاریخ شمسی
    public function getJalaliDate() {
        $today = date('Y-m-d');
        $today_parts = explode('-', $today);
        
        // تابع تبدیل میلادی به شمسی
        $jalali = $this->gregorianToJalali(
            (int)$today_parts[0], 
            (int)$today_parts[1], 
            (int)$today_parts[2]
        );
        
        $jalali_formatted = $jalali[0] . '/' . sprintf('%02d', $jalali[1]) . '/' . sprintf('%02d', $jalali[2]);
        
        return [
            'success' => true,
            'data' => [
                'jalali' => $jalali_formatted,
                'gregorian' => $today
            ]
        ];
    }
    
    // تابع تبدیل تاریخ میلادی به شمسی
    private function gregorianToJalali($gy, $gm, $gd) {
        $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return array($jy, $jm, $jd);
    }
    
    // دریافت تمام اطلاعات داشبورد
    public function getAllData() {
        $userId = AuthMiddleware::checkRole(['admin']);
        
        $stats = $this->getStats();
        $todayClasses = $this->getTodayClasses();
        $recentAttendance = $this->getRecentAttendance();
        $activeTeachers = $this->getActiveTeachers();
        $jalaliDate = $this->getJalaliDate();
        
        return [
            'success' => true,
            'data' => [
                'stats' => $stats['data']['stats'],
                'today_info' => $stats['data']['today_info'],
                'today_classes' => $todayClasses['data']['classes'],
                'recent_attendance' => $recentAttendance['data'],
                'active_teachers' => $activeTeachers['data'],
                'jalali_date' => $jalaliDate['data']['jalali'],
                'day_name' => $todayClasses['data']['day_name']
            ]
        ];
    }
}
?>