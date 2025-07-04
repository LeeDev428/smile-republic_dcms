<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle status updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = $_POST['status'];
        
        if (in_array($new_status, ['confirmed', 'cancelled', 'rescheduled'])) {
            try {
                $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $appointment_id]);
                $message = "Appointment status updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating appointment status: " . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = ["a.appointment_date >= CURDATE()"];
$params = [];

if ($filter_status) {
    $conditions[] = "a.status = ?";
    $params[] = $filter_status;
}

if ($filter_date) {
    $conditions[] = "a.appointment_date = ?";
    $params[] = $filter_date;
}

if ($search) {
    $conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get appointments with filters
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last, 
               p.phone as patient_phone, p.email as patient_email,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name, do.duration_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        $where_clause
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Get today's appointments count
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
    $today_count = $stmt->fetchColumn();
    
    // Get upcoming appointments count
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date > CURDATE()");
    $upcoming_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Error loading appointments: " . $e->getMessage();
    $appointments = [];
    $today_count = 0;
    $upcoming_count = 0;
}

function renderPageContent() {
    global $message, $error, $appointments, $today_count, $upcoming_count, $filter_status, $filter_date, $search;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Appointments</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage patient appointments and scheduling</p>
                </div>
                <div>
                    <a href="schedule.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        New Appointment
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-grid mb-4">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $today_count; ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $upcoming_count; ?></div>
                        <div class="stat-label">Upcoming Appointments</div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count($appointments); ?></div>
                        <div class="stat-label">Total Listed</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-filter"></i>
                        Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search Patient</label>
                            <input type="text" name="search" class="form-control" placeholder="Name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                    Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-list"></i>
                        Appointments (<?php echo count($appointments); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h5>No appointments found</h5>
                            <p>No appointments match your current filters.</p>
                            <a href="schedule.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Schedule New Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Patient</th>
                                        <th>Contact</th>
                                        <th>Dentist</th>
                                        <th>Service</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo formatDate($appointment['appointment_date']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);"><?php echo formatTime($appointment['appointment_time']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($appointment['patient_phone']): ?>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['patient_email']): ?>
                                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['patient_email']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['operation_name']); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo $appointment['duration_minutes']; ?> min</span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = match($appointment['status']) {
                                                    'scheduled' => 'primary',
                                                    'confirmed' => 'success',
                                                    'in_progress' => 'warning',
                                                    'completed' => 'info',
                                                    'cancelled' => 'danger',
                                                    'no_show' => 'dark',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                                        <button onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')" 
                                                                class="btn btn-sm btn-success" title="Confirm">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($appointment['status'], ['scheduled', 'confirmed'])): ?>
                                                        <button onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')" 
                                                                class="btn btn-sm btn-danger" title="Cancel">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button onclick="viewAppointment(<?php echo $appointment['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View Details">
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

    <script>
        function updateStatus(appointmentId, status) {
            if (confirm('Are you sure you want to update this appointment status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewAppointment(appointmentId) {
            // Placeholder for appointment details modal
            alert('Appointment details view will be implemented here. ID: ' + appointmentId);
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-secondary { background-color: var(--gray-500); }
            .badge-primary { background-color: var(--primary-color); }
            .badge-info { background-color: var(--info-color); }
            .badge-warning { background-color: var(--warning-color); }
            .badge-success { background-color: var(--success-color); }
            .badge-danger { background-color: var(--danger-color); }
            .badge-dark { background-color: var(--dark-color); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Appointments', $pageContent, 'appointments');
?>
