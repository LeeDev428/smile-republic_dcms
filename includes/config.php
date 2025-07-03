<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'simple_republic_dental_clinic_dc');

// Application configuration
define('SITE_URL', 'http://localhost/smile-republic_dcms');
define('SITE_NAME', 'Smile Republic Dental Clinic');

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set MySQL timezone to Asia/Manila
    $pdo->exec("SET time_zone = '+08:00'");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions
function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirect('index.php');
    }
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function generatePassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function getAvailableTimeSlots($date, $dentist_id, $duration_minutes) {
    global $pdo;
    
    $slots = [];
    $start_time = '08:00';
    $end_time = '18:00';
    $slot_duration = 15; // 15-minute increments
    
    // Get existing appointments for the dentist on this date
    $stmt = $pdo->prepare("SELECT appointment_time, duration_minutes FROM appointments 
                          WHERE dentist_id = ? AND appointment_date = ? AND status NOT IN ('cancelled', 'no_show')");
    $stmt->execute([$dentist_id, $date]);
    $existing_appointments = $stmt->fetchAll();
    
    $current_time = strtotime($start_time);
    $end_timestamp = strtotime($end_time);
    
    while ($current_time < $end_timestamp) {
        $slot_time = date('H:i', $current_time);
        $slot_end = date('H:i', $current_time + ($duration_minutes * 60));
        
        // Check if this slot conflicts with existing appointments
        $is_available = true;
        foreach ($existing_appointments as $appointment) {
            $apt_start = strtotime($appointment['appointment_time']);
            $apt_end = $apt_start + ($appointment['duration_minutes'] * 60);
            $slot_start = $current_time;
            $slot_end_timestamp = $current_time + ($duration_minutes * 60);
            
            if (($slot_start < $apt_end) && ($slot_end_timestamp > $apt_start)) {
                $is_available = false;
                break;
            }
        }
        
        if ($is_available && ($current_time + ($duration_minutes * 60)) <= $end_timestamp) {
            $slots[] = $slot_time;
        }
        
        $current_time += ($slot_duration * 60);
    }
    
    return $slots;
}
?>
