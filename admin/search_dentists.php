<?php
require_once '../includes/config.php';
requireRole('admin');

// Set content type to JSON
header('Content-Type: application/json');

// Get search parameters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_specialization = $_GET['specialization'] ?? '';

// Build query conditions
$conditions = ["u.role = 'dentist'"];
$params = [];

if ($search) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_status) {
    $conditions[] = "u.status = ?";
    $params[] = $filter_status;
}

if ($filter_specialization) {
    $conditions[] = "dp.specialization LIKE ?";
    $params[] = "%$filter_specialization%";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get dentists with appointment counts
    $stmt = $pdo->prepare("
        SELECT u.*, 
               dp.specialization,
               dp.license_number,
               dp.years_of_experience,
               COUNT(a.id) as appointment_count,
               COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments,
               MAX(a.appointment_date) as last_appointment_date
        FROM users u
        LEFT JOIN dentist_profiles dp ON u.id = dp.user_id
        LEFT JOIN appointments a ON u.id = a.dentist_id
        $where_clause
        GROUP BY u.id
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute($params);
    $dentists = $stmt->fetchAll();
    
    // Capture the table content
    ob_start();
    include 'dentist_table_content.php';
    $tableContent = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $tableContent,
        'count' => count($dentists)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error loading dentists: ' . $e->getMessage()
    ]);
}
?>
