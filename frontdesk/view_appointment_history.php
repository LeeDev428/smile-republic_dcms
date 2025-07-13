<?php
require_once '../includes/config.php';
require_once 'layout.php';
requireRole('frontdesk');

$frontdesk_id = $_SESSION['user_id'];
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 5;
$error = '';
$patient = null;
$appointments = [];
$total_appointments = 0;

// Fetch patient details and appointments
if ($patient_id > 0) {
    // Get patient info
    $stmt = $pdo->prepare("
        SELECT p.*,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.id = ? AND a.frontdesk_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$patient_id, $frontdesk_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        // Get total appointments count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM appointments a
            WHERE a.patient_id = ? AND a.frontdesk_id = ?
        ");
        $stmt->execute([$patient_id, $frontdesk_id]);
        $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get paginated appointments
        $offset = ($page - 1) * $per_page;
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   u.first_name as dentist_first_name,
                   u.last_name as dentist_last_name
            FROM appointments a
            LEFT JOIN users u ON a.dentist_id = u.id
            WHERE a.patient_id = ? AND a.frontdesk_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT " . intval($per_page) . " OFFSET " . intval($offset) . "
        ");
        $stmt->execute([$patient_id, $frontdesk_id]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Patient not found or you don't have permission to view this record.";
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        case 'no-show':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'paid':
            return 'bg-success';
        case 'partial':
            return 'bg-warning text-dark';
        case 'unpaid':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
function renderPageContent() {
    global $error, $patient, $appointments, $per_page, $total_appointments, $page, $patient_id;
?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 style="margin-bottom: 0.5rem;">Appointment History</h1>
                <p style="margin: 0; color: var(--text-muted);">View patient's appointment history</p>
            </div>
            <a href="patients.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($patient): ?>
            <!-- Patient Summary -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="patient-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="patient-summary">
                            <h4><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h4>
                            <div class="patient-details">
                                <span><i class="fas fa-birthday-cake"></i> <?php echo htmlspecialchars($patient['age']); ?> years old</span>
                                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></span>
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-alt"></i> Appointments
                        </h5>
                        <span class="appointment-count">
                            Total: <?php echo count($appointments); ?> appointments
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No appointments found</h5>
                            <p>This patient has no appointment history with you yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-timeline">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="appointment-date">
                                        <div class="date-badge">
                                            <?php echo date('M j', strtotime($appointment['appointment_date'])); ?>
                                            <div class="year"><?php echo date('Y', strtotime($appointment['appointment_date'])); ?></div>
                                            <div class="time"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="appointment-content">
                                        <div class="appointment-header">
                                            <div class="appointment-title">
                                                <h6>Operation #<?php echo htmlspecialchars($appointment['operation_id']); ?></h6>
                                                <div class="appointment-meta">
                                                    <span>Status: <?php echo ucfirst($appointment['status']); ?></span>
                                                    <span>Payment: <?php echo ucfirst($appointment['payment_status']); ?></span>
                                                </div>
                                            </div>
                                            <div class="appointment-info">
                                                <div class="info-row">
                                                    <span><i class="fas fa-user-md"></i> Dentist ID: <?php echo htmlspecialchars($appointment['dentist_id']); ?></span>
                                                    <span><i class="fas fa-tooth"></i> Operation ID: <?php echo htmlspecialchars($appointment['operation_id']); ?></span>
                                                    <span><i class="fas fa-user"></i> Front Desk ID: <?php echo htmlspecialchars($appointment['frontdesk_id']); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span><i class="far fa-clock"></i> Duration: <?php echo htmlspecialchars($appointment['duration_minutes']); ?> minutes</span>
                                                    <span><i class="fas fa-money-bill-wave"></i> Total Cost: ₱<?php echo number_format($appointment['total_cost'], 2); ?></span>
                                                </div>
                                                <?php if ($appointment['notes']): ?>
                                                    <div class="info-row">
                                                        <span class="notes"><i class="fas fa-sticky-note"></i> Notes: <?php echo htmlspecialchars($appointment['notes']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="info-row timestamps">
                                                    <span><i class="fas fa-calendar-plus"></i> Created: <?php echo date('M j, Y g:i A', strtotime($appointment['created_at'])); ?></span>
                                                    <span><i class="fas fa-calendar-check"></i> Updated: <?php echo date('M j, Y g:i A', strtotime($appointment['updated_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($total_appointments > $per_page): ?>
                            <div class="pagination-container">
                                <nav aria-label="Appointment history pagination">
                                    <ul class="pagination d-flex justify-content-center align-items-center">
                                        <?php
                                        $total_pages = ceil($total_appointments / $per_page);
                                        $max_visible_pages = 5;
                                        $start_page = max(1, min($page - floor($max_visible_pages / 2), $total_pages - $max_visible_pages + 1));
                                        $end_page = min($start_page + $max_visible_pages - 1, $total_pages);
                                        
                                        // Previous button
                                        if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $patient_id; ?>&page=<?php echo ($page - 1); ?>" aria-label="Previous">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif;

                                        // Page numbers
                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php echo ($i == $page ? 'active' : ''); ?>">
                                                <a class="page-link" href="?id=<?php echo $patient_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor;

                                        // Next button
                                        if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $patient_id; ?>&page=<?php echo ($page + 1); ?>" aria-label="Next">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .card {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e9f2;
    }
    .patient-avatar {
        font-size: 3rem;
        color: #3498db;
        margin-right: 1.5rem;
    }
    .patient-summary h4 {
        margin: 0;
        color: #2c3e50;
    }
    .patient-details {
        display: flex;
        gap: 1.5rem;
        margin-top: 0.5rem;
        color: #566a7f;
        font-size: 0.875rem;
    }
    .patient-details span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .appointment-count {
        font-size: 0.875rem;
        color: #566a7f;
        background: #f8f9fa;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
    }
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #566a7f;
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    .empty-state h5 {
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }
    .empty-state p {
        margin: 0;
        font-size: 0.875rem;
    }
    .appointments-timeline {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .appointment-item {
        display: flex;
        gap: 2rem;
        padding: 1.5rem;
        background: #fff;
        border: 1px solid #e5e9f2;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .appointment-date {
        min-width: 120px;
    }
    .date-badge {
        text-align: center;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .year {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    .time {
        font-size: 0.875rem;
        color: #2c3e50;
        margin-top: 0.5rem;
        font-weight: 500;
    }
    .appointment-title {
        margin-bottom: 1rem;
    }
    .appointment-title h6 {
        font-size: 1.1rem;
        color: #2c3e50;
        margin: 0 0 0.5rem 0;
    }
    .appointment-meta {
        display: flex;
        gap: 1.5rem;
        color: #6c757d;
        font-size: 0.875rem;
    }
    .appointment-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .info-row {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        font-size: 0.875rem;
        color: #495057;
    }
    .info-row span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .info-row i {
        color: #3498db;
        width: 16px;
    }
    .info-row.timestamps {
        color: #6c757d;
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }
    .notes {
        flex-basis: 100%;
        font-style: italic;
        color: #666;
    }
    .pagination-container {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }
    .pagination {
        display: inline-flex !important;
        gap: 0.5rem;
    }
    .pagination .page-item {
        margin: 0;
        list-style: none;
    }
    .pagination .page-link {
        color: #566a7f;
        border-radius: 0.5rem;
        border: 1px solid #e5e9f2;
        padding: 0.6rem 1rem;
        min-width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
        line-height: 1;
    }
    .pagination .page-item.active .page-link {
        background-color: #3498db;
        border-color: #3498db;
        color: #fff;
        box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
    }
    .pagination .page-link:hover {
        background-color: #f8f9fa;
        border-color: #3498db;
        color: #3498db;
    }
    .pagination .page-item:first-child .page-link,
    .pagination .page-item:last-child .page-link {
        padding: 0.5rem 0.75rem;
    }
    .pagination .page-item.disabled .page-link {
        color: #b4bdc6;
        border-color: #e5e9f2;
        background-color: #f8f9fa;
        pointer-events: none;
    }
    @media (max-width: 768px) {
        .appointment-item {
            flex-direction: column;
        }
        .appointment-date {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: auto;
        }
        .date-badge {
            min-width: 100px;
        }
        .time {
            margin-top: 0;
        }
        .pagination {
            gap: 0.15rem;
        }
        .pagination .page-link {
            padding: 0.4rem 0.8rem;
            min-width: 2.2rem;
        }
    }
    </style>

<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Appointment History', $pageContent, 'patients');
