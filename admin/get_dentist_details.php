<?php
require_once '../includes/config.php';
requireRole('admin');

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Dentist ID is required']);
    exit;
}

$dentist_id = intval($_GET['id']);

try {
    // Get dentist details
    $stmt = $pdo->prepare("
        SELECT u.*, 
               dp.specialization,
               dp.license_number,
               dp.years_of_experience,
               dp.education,
               dp.bio,
               COUNT(a.id) as total_appointments,
               COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments,
               COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
               MAX(a.appointment_date) as last_appointment_date
        FROM users u
        LEFT JOIN dentist_profiles dp ON u.id = dp.user_id
        LEFT JOIN appointments a ON u.id = a.dentist_id
        WHERE u.id = ? AND u.role = 'dentist'
        GROUP BY u.id
    ");
    $stmt->execute([$dentist_id]);
    $dentist = $stmt->fetch();
    
    if (!$dentist) {
        echo json_encode(['success' => false, 'error' => 'Dentist not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'dentist' => $dentist
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error loading dentist details: ' . $e->getMessage()
    ]);
}
?>
