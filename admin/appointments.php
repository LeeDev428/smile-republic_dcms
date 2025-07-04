<?php
require_once '../includes/config.php';
requireRole('admin');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle status updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointment_id = intval($_POST['appointment_id']);
    
    if ($action === 'update_status') {
        $new_status = $_POST['status'];
        $valid_statuses = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
        
        if (in_array($new_status, $valid_statuses)) {
            try {
                $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $appointment_id]);
                $message = 'Appointment status updated successfully.';
            } catch (PDOException $e) {
                $error = 'Error updating appointment status: ' . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$dentist_filter = $_GET['dentist'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($dentist_filter) {
    $where_conditions[] = "a.dentist_id = ?";
    $params[] = $dentist_filter;
}

if ($date_filter) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get appointments with filters
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last, p.phone as patient_phone,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name, do.duration_minutes,
               fd.first_name as frontdesk_first, fd.last_name as frontdesk_last
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        LEFT JOIN users fd ON a.frontdesk_id = fd.id
        $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Get dentists for filter dropdown
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'dentist' AND status = 'active' ORDER BY first_name, last_name");
    $dentists = $stmt->fetchAll();
    
    // Get appointment statistics
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM appointments 
        GROUP BY status
    ");
    $stats = [];
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error = 'Error loading appointments: ' . $e->getMessage();
    $appointments = [];
    $dentists = [];
    $stats = [];
}

function renderPageContent() {
    global $message, $error, $appointments, $dentists, $stats, $status_filter, $dentist_filter, $date_filter;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">All Appointments</h1>
                    <p style="margin: 0; color: var(--text-muted);">Monitor and manage all clinic appointments</p>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="exportAppointments()" class="btn btn-secondary">
                        <i class="fas fa-download"></i>
                        Export
                    </button>
                    <a href="reports.php" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i>
                        View Reports
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo ($stats['scheduled'] ?? 0) + ($stats['confirmed'] ?? 0); ?></h3>
                        <p>Scheduled</p>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo ($stats['cancelled'] ?? 0) + ($stats['no_show'] ?? 0); ?></h3>
                        <p>Cancelled/No Show</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Filter Appointments</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control form-select">
                                <option value="">All Statuses</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="dentist" class="form-label">Dentist</label>
                            <select name="dentist" id="dentist" class="form-control form-select">
                                <option value="">All Dentists</option>
                                <?php foreach ($dentists as $dentist): ?>
                                    <option value="<?php echo $dentist['id']; ?>" 
                                            <?php echo $dentist_filter == $dentist['id'] ? 'selected' : ''; ?>>
                                        Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Appointments List</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-calendar-times" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No Appointments Found</h3>
                            <p>No appointments match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Patient</th>
                                        <th>Dentist</th>
                                        <th>Treatment</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo formatDate($appointment['appointment_date']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                                    (<?php echo $appointment['duration_minutes']; ?> min)
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($appointment['operation_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($appointment['status']) {
                                                        'completed' => 'success',
                                                        'cancelled', 'no_show' => 'danger',
                                                        'in_progress' => 'warning',
                                                        default => 'primary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo formatCurrency($appointment['total_cost']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($appointment['frontdesk_first']): ?>
                                                    <?php echo htmlspecialchars($appointment['frontdesk_first'] . ' ' . $appointment['frontdesk_last']); ?>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-primary" onclick="showStatusModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-secondary" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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

    <!-- Status Update Modal -->
    <div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--white); border-radius: var(--border-radius-lg); padding: 2rem; width: 90%; max-width: 400px;">
            <h3 style="margin-bottom: 1.5rem;">Update Appointment Status</h3>
            
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="modalAppointmentId">
                
                <div class="form-group">
                    <label for="modalStatus" class="form-label">Status</label>
                    <select name="status" id="modalStatus" class="form-control form-select" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showStatusModal(appointmentId, currentStatus) {
            document.getElementById('modalAppointmentId').value = appointmentId;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function viewAppointment(appointmentId) {
            // Implement view appointment details
            alert('View appointment details feature coming soon!');
        }

        function exportAppointments() {
            // Implement export functionality
            alert('Export feature coming soon!');
        }

        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge {
                display: inline-block;
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1;
                color: var(--white);
                text-align: center;
                white-space: nowrap;
                vertical-align: baseline;
                border-radius: 0.375rem;
            }
            .badge-primary { background-color: var(--primary-color); }
            .badge-success { background-color: var(--success-color); }
            .badge-warning { background-color: var(--warning-color); }
            .badge-danger { background-color: var(--danger-color); }
            .badge-secondary { background-color: var(--gray-500); }
            
            .row { display: flex; flex-wrap: wrap; margin: -0.75rem; }
            .col-md-3 { flex: 0 0 25%; max-width: 25%; padding: 0.75rem; }
            .g-3 > * { margin-bottom: 1rem; }
            .d-flex { display: flex; }
            .align-items-end { align-items: flex-end; }
            .me-2 { margin-right: 0.5rem; }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderAdminLayout('All Appointments', $pageContent, 'appointments.php');
?>
