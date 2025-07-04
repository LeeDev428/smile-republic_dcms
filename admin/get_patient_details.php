<?php
require_once '../includes/config.php';
requireRole('admin');

// Set content type to JSON
header('Content-Type: application/json');

$patient_id = intval($_GET['id'] ?? 0);

if ($patient_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid patient ID'
    ]);
    exit;
}

try {
    // Get patient details with appointment information
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as calculated_age,
               COUNT(a.id) as total_appointments,
               COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments,
               COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
               MAX(a.appointment_date) as last_appointment_date
        FROM patients p
        LEFT JOIN appointments a ON p.id = a.patient_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        echo json_encode([
            'success' => false,
            'error' => 'Patient not found'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'patient' => $patient
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
