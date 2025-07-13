<?php
require_once '../includes/config.php';
requireRole('dentist');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

$dentist_id = $_SESSION['user_id'];

// Handle patient search/filter
$search = $_GET['search'] ?? '';
$filter_age_min = $_GET['age_min'] ?? '';
$filter_age_max = $_GET['age_max'] ?? '';

// Build query conditions for patients who have appointments with this dentist
$conditions = ["a.dentist_id = ?"];
$params = [$dentist_id];

if ($search) {
    $conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_age_min) {
    $conditions[] = "p.age >= ?";
    $params[] = intval($filter_age_min);
}

if ($filter_age_max) {
    $conditions[] = "p.age <= ?";
    $params[] = intval($filter_age_max);
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get patients who have appointments with this dentist
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as calculated_age,
               COUNT(a.id) as appointment_count,
               MAX(a.appointment_date) as last_appointment_date,
               MIN(a.appointment_date) as first_appointment_date,
               SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
               MAX(CASE WHEN a.status = 'completed' THEN a.notes END) as last_notes
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        $where_clause
        GROUP BY p.id
        ORDER BY MAX(a.appointment_date) DESC
    ");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
    // Get statistics for this dentist
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.patient_id) as total_patients,
            COUNT(a.id) as total_appointments,
            COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
            COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments
        FROM appointments a 
        WHERE a.dentist_id = ?
    ");
    $stmt->execute([$dentist_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error loading patients: " . $e->getMessage();
    $patients = [];
    $stats = ['total_patients' => 0, 'total_appointments' => 0, 'completed_appointments' => 0, 'upcoming_appointments' => 0];
}

function renderPageContent() {
    global $message, $error, $patients, $stats, $search, $filter_age_min, $filter_age_max;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">My Patients</h1>
                    <p style="margin: 0; color: var(--text-muted);">View and manage your patient records</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="search-container mb-4">
                <form method="GET" id="searchForm" class="search-form">
                    <div class="search-group">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search by name, phone, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   autocomplete="off">
                        </div>
                        <div class="search-filters">
                            <div class="filter-group">
                                <label class="filter-label">Age Range</label>
                                <div class="filter-inputs">
                                    <input type="number" 
                                           name="age_min" 
                                           class="form-control form-control-sm" 
                                           placeholder="Min" 
                                           min="0" 
                                           max="120"
                                           value="<?php echo htmlspecialchars($filter_age_min); ?>">
                                    <span class="filter-separator">-</span>
                                    <input type="number" 
                                           name="age_max" 
                                           class="form-control form-control-sm" 
                                           placeholder="Max" 
                                           min="0" 
                                           max="120"
                                           value="<?php echo htmlspecialchars($filter_age_max); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-search">
                                Search
                            </button>
                            <?php if ($search || $filter_age_min || $filter_age_max): ?>
                                <a href="patients.php" class="btn btn-outline-secondary btn-clear">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <style>
            .search-container {
                background: var(--white);
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
            .search-form {
                padding: 1rem;
            }
            .search-group {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            .search-input-wrapper {
                position: relative;
                flex-grow: 1;
            }
            .search-icon {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: var(--gray-400);
            }
            .search-input {
                width: 100%;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                border: 1px solid var(--gray-200);
                border-radius: 6px;
                font-size: 1rem;
                transition: all 0.2s;
            }
            .search-input:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
            }
            .search-filters {
                display: flex;
                align-items: center;
                gap: 1rem;
                flex-wrap: wrap;
            }
            .filter-group {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            .filter-label {
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--gray-600);
                white-space: nowrap;
            }
            .filter-inputs {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .filter-separator {
                color: var(--gray-400);
            }
            .filter-inputs input {
                width: 80px;
            }
            .btn-search {
                padding: 0.5rem 1.5rem;
            }
            .btn-clear {
                padding: 0.5rem 1rem;
            }
            @media (min-width: 768px) {
                .search-group {
                    flex-direction: row;
                }
            }
            </style>

            <!-- Patients List -->
            <div class="patients-container">
                <div class="patients-header">
                    <div class="patients-title">
                        <h5>
                            <i class="fas fa-users"></i>
                            Patient Records
                        </h5>
                        <span class="patients-count"><?php echo count($patients); ?> patients</span>
                    </div>
                </div>
                
                <div class="patients-content">
                    <?php if (empty($patients)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-times"></i>
                            <h5>No patients found</h5>
                            <p>No patients match your search criteria or you haven't treated any patients yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Age</th>
                                        <th>Contact Information</th>
                                        <th>First Visit</th>
                                        <th>Last Visit</th>
                                        <th>History</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <div class="patient-name">
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                    <span class="patient-id">#<?php echo $patient['id']; ?></span>
                                                </div>
                                            </td>
                                           </td>                            <td>
                                                <span ><?php echo $patient['calculated_age'] ?? 'N/A'; ?> years</span>
                                            </td>
                                            <td>
                                                <div class="contact-info">
                                                    <?php if ($patient['phone']): ?>
                                                        <div class="contact-item">
                                                            <i class="fas fa-phone"></i>
                                                            <?php echo htmlspecialchars($patient['phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($patient['email']): ?>
                                                        <div class="contact-item">
                                                            <i class="fas fa-envelope"></i>
                                                            <?php echo htmlspecialchars($patient['email']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-info">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo formatDate($patient['first_appointment_date']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-info">
                                                    <i class="fas fa-calendar-check"></i>
                                                    <?php echo formatDate($patient['last_appointment_date']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="appointment-stats">
                                                    <span class="total-appointments" title="Total Appointments">
                                                        <i class="fas fa-calendar-alt"></i> <?php echo $patient['appointment_count']; ?>
                                                    </span>
                                                    <span class="completed-appointments" title="Completed Appointments">
                                                        <i class="fas fa-check-circle"></i> <?php echo $patient['completed_appointments']; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i></i>
                                                    </a>
                                                    <a href="view_appointment_history.php?id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-icon btn-info" 
                                                       title="View Appointment History">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <style>
            .patients-container {
                background: var(--white);
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
            .patients-header {
                padding: 1.25rem;
                border-bottom: 1px solid var(--gray-200);
            }
            .patients-title {
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            .patients-title h5 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 1.1rem;
            }
            .patients-count {
                font-size: 0.875rem;
                color: var(--gray-500);
                padding: 0.25rem 0.75rem;
                background: var(--gray-100);
                border-radius: 1rem;
            }
            .patients-content {
                padding: 1rem;
            }
            .empty-state {
                text-align: center;
                padding: 3rem 1rem;
                color: var(--gray-500);
            }
            .empty-state i {
                font-size: 3rem;
                margin-bottom: 1rem;
                opacity: 0.5;
            }
            .empty-state h5 {
                margin-bottom: 0.5rem;
                color: var(--gray-700);
            }
            .empty-state p {
                margin: 0;
                font-size: 0.875rem;
            }
            .table {
                margin: 0;
            }
            .table th {
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--gray-600);
                border-bottom-width: 1px;
                padding: 0.75rem;
            }
            .table td {
                padding: 1rem 0.75rem;
                vertical-align: middle;
            }
            .patient-name {
                font-weight: 600;
                color: var(--gray-800);
            }
            .patient-id {
                font-size: 0.75rem;
                color: var(--gray-500);
                margin-left: 0.5rem;
            }
            .age-badge {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                font-size: 0.75rem;
                font-weight: 600;
                color: var(--gray-700);
                background: var(--gray-100);
                border-radius: 1rem;
            }
            .contact-info {
                font-size: 0.875rem;
            }
            .contact-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--gray-600);
            }
            .contact-item i {
                color: var(--gray-400);
            }
            .date-info {
                font-size: 0.875rem;
                color: var(--gray-600);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .date-info i {
                color: var(--gray-400);
            }
            .appointment-stats {
                display: flex;
                gap: 1rem;
                font-size: 0.875rem;
            }
            .total-appointments, .completed-appointments {
                display: flex;
                align-items: center;
                gap: 0.375rem;
            }
            .total-appointments i {
                color: var(--primary-color);
            }
            .completed-appointments i {
                color: var(--success-color);
            }
            .action-buttons {
                display: flex;
                gap: 0.5rem;
            }
            .btn-icon {
                width: 32px;
                height: 32px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                transition: all 0.2s;
            }
            .btn-icon:hover {
                transform: translateY(-1px);
            }
            .btn-icon i {
                font-size: 0.875rem;
            }
            </style>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderDentistLayout('My Patients', $pageContent, 'patients');
?>
