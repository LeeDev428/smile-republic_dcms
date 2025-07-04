<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle check-in/check-out
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointment_id = intval($_POST['appointment_id']);
    
    if ($action === 'check_in') {
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'checked_in', check_in_time = NOW() WHERE id = ? AND appointment_date = CURDATE()");
            $stmt->execute([$appointment_id]);
            $message = "Patient checked in successfully.";
        } catch (PDOException $e) {
            $error = "Error checking in patient: " . $e->getMessage();
        }
    } elseif ($action === 'check_out') {
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', check_out_time = NOW() WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $message = "Patient checked out successfully.";
        } catch (PDOException $e) {
            $error = "Error checking out patient: " . $e->getMessage();
        }
    }
}

try {
    // Get today's appointments for check-in
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last, 
               p.phone as patient_phone,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name, do.duration_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute();
    $today_appointments = $stmt->fetchAll();
    
    // Get waiting room status (checked in but not in progress)
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name,
               TIMESTAMPDIFF(MINUTE, a.check_in_time, NOW()) as waiting_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.status = 'checked_in' AND a.appointment_date = CURDATE()
        ORDER BY a.check_in_time ASC
    ");
    $stmt->execute();
    $waiting_patients = $stmt->fetchAll();
    
    // Get patients currently in treatment
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name,
               TIMESTAMPDIFF(MINUTE, a.treatment_start_time, NOW()) as treatment_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.status = 'in_progress' AND a.appointment_date = CURDATE()
        ORDER BY a.treatment_start_time ASC
    ");
    $stmt->execute();
    $in_treatment = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error loading check-in data: " . $e->getMessage();
    $today_appointments = [];
    $waiting_patients = [];
    $in_treatment = [];
}

function renderPageContent() {
    global $message, $error, $today_appointments, $waiting_patients, $in_treatment;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Patient Check-In</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage patient arrivals and waiting room</p>
                </div>
                <div>
                    <span class="badge badge-primary" style="font-size: 1rem; padding: 0.5rem 1rem;">
                        Today: <?php echo date('F j, Y'); ?>
                    </span>
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
                        <div class="stat-value"><?php echo count($today_appointments); ?></div>
                        <div class="stat-label">Today's Schedule</div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count($waiting_patients); ?></div>
                        <div class="stat-label">Waiting</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count($in_treatment); ?></div>
                        <div class="stat-label">In Treatment</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Today's Appointments -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 style="margin: 0;">
                                <i class="fas fa-calendar-day"></i>
                                Today's Appointments
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($today_appointments)): ?>
                                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No appointments scheduled for today.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($today_appointments as $appointment): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div style="font-weight: 600;">
                                                    <?php echo formatTime($appointment['appointment_time']); ?> - 
                                                    <?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?> - 
                                                    <?php echo htmlspecialchars($appointment['operation_name']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php
                                                $status_class = match($appointment['status']) {
                                                    'scheduled' => 'secondary',
                                                    'confirmed' => 'primary',
                                                    'checked_in' => 'warning',
                                                    'in_progress' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?> me-2">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                                
                                                <?php if ($appointment['status'] === 'scheduled' || $appointment['status'] === 'confirmed'): ?>
                                                    <button onclick="checkIn(<?php echo $appointment['id']; ?>)" 
                                                            class="btn btn-sm btn-success">
                                                        <i class="fas fa-sign-in-alt"></i> Check In
                                                    </button>
                                                <?php elseif ($appointment['status'] === 'completed'): ?>
                                                    <button onclick="checkOut(<?php echo $appointment['id']; ?>)" 
                                                            class="btn btn-sm btn-info">
                                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Waiting Room -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 style="margin: 0;">
                                <i class="fas fa-clock"></i>
                                Waiting Room
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($waiting_patients)): ?>
                                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <i class="fas fa-chair" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No patients waiting.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($waiting_patients as $patient): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div style="font-weight: 600;">
                                                        <?php echo htmlspecialchars($patient['patient_first'] . ' ' . $patient['patient_last']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                        Dr. <?php echo htmlspecialchars($patient['dentist_first'] . ' ' . $patient['dentist_last']); ?> - 
                                                        <?php echo htmlspecialchars($patient['operation_name']); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge badge-warning">
                                                        <?php echo $patient['waiting_minutes']; ?> min
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currently in Treatment -->
            <?php if (!empty($in_treatment)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 style="margin: 0;">
                            <i class="fas fa-user-md"></i>
                            Currently in Treatment
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($in_treatment as $patient): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div style="font-weight: 600;">
                                                        <?php echo htmlspecialchars($patient['patient_first'] . ' ' . $patient['patient_last']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                        Dr. <?php echo htmlspecialchars($patient['dentist_first'] . ' ' . $patient['dentist_last']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem;">
                                                        <?php echo htmlspecialchars($patient['operation_name']); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge badge-success">
                                                        <?php echo $patient['treatment_minutes'] ?? 0; ?> min
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

    <script>
        function checkIn(appointmentId) {
            if (confirm('Check in this patient?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="check_in">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function checkOut(appointmentId) {
            if (confirm('Check out this patient?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="check_out">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-primary { background-color: var(--primary-color); }
            .badge-secondary { background-color: var(--gray-500); }
            .badge-success { background-color: var(--success-color); }
            .badge-warning { background-color: var(--warning-color); }
            .badge-info { background-color: var(--info-color); }
            .badge-danger { background-color: var(--danger-color); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Check-In', $pageContent, 'check-in');
?>
