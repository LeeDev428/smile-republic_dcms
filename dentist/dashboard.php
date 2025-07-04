<?php
require_once '../includes/config.php';
requireRole('dentist');

// Include layout for rendering
require_once 'layout.php';

// Get dentist's dashboard statistics
try {
    $dentist_id = $_SESSION['user_id'];
    
    // Today's appointments for this dentist
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE dentist_id = ? AND appointment_date = CURDATE() AND status NOT IN ('cancelled', 'no_show')");
    $stmt->execute([$dentist_id]);
    $today_appointments = $stmt->fetch()['total'];
    
    // This week's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE dentist_id = ? AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY) AND status NOT IN ('cancelled', 'no_show')");
    $stmt->execute([$dentist_id]);
    $week_appointments = $stmt->fetch()['total'];
    
    // Completed treatments this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE dentist_id = ? AND MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE()) AND status = 'completed'");
    $stmt->execute([$dentist_id]);
    $monthly_treatments = $stmt->fetch()['total'];
    
    // Total patients treated
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE dentist_id = ? AND status = 'completed'");
    $stmt->execute([$dentist_id]);
    $total_patients = $stmt->fetch()['total'];
    
    // Today's schedule
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name as patient_first, p.last_name as patient_last, 
               p.phone as patient_phone, do.name as operation_name, do.duration_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.dentist_id = ? AND a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$dentist_id]);
    $today_schedule = $stmt->fetchAll();
    
    // Upcoming appointments (next 7 days)
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name as patient_first, p.last_name as patient_last,
               do.name as operation_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.dentist_id = ? AND a.appointment_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND a.status NOT IN ('cancelled', 'no_show')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$dentist_id]);
    $upcoming_appointments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}
?>
<?php
require_once 'layout.php';

function renderPageContent() {
    global $dentist_id, $today_appointments, $week_appointments, $monthly_treatments, 
           $total_patients, $today_schedule, $upcoming_appointments, $error;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Dentist Dashboard</h1>
                    <p style="margin: 0; color: var(--text-muted);">Good day, Dr. <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Here's your practice overview.</p>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.875rem;">
                        <i class="fas fa-calendar"></i>
                        <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($today_appointments); ?></div>
                            <div class="stat-label">Today's Appointments</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--primary-color); opacity: 0.7;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($week_appointments); ?></div>
                            <div class="stat-label">This Week</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--warning-color); opacity: 0.7;">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($monthly_treatments); ?></div>
                            <div class="stat-label">Treatments This Month</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--success-color); opacity: 0.7;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_patients); ?></div>
                            <div class="stat-label">Total Patients Treated</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--success-color); opacity: 0.7;">
                            <i class="fas fa-user-injured"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Today's Schedule</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($today_schedule)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No appointments scheduled for today.</p>
                                <p style="font-size: 0.875rem;">Enjoy your free day!</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($today_schedule as $appointment): ?>
                                    <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 1rem; background: var(--white);">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div style="font-weight: 600; color: var(--gray-900);">
                                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                                </div>
                                                <div style="margin: 0.5rem 0;">
                                                    <strong><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?></strong>
                                                </div>
                                                <div style="color: var(--text-muted); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($appointment['operation_name']); ?>
                                                    <span style="margin-left: 1rem;">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo $appointment['duration_minutes']; ?> min
                                                    </span>
                                                </div>
                                                <?php if ($appointment['patient_phone']): ?>
                                                    <div style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                                                        <i class="fas fa-phone"></i>
                                                        <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="badge badge-<?php 
                                                    echo match($appointment['status']) {
                                                        'scheduled' => 'primary',
                                                        'confirmed' => 'success',
                                                        'in_progress' => 'warning',
                                                        'completed' => 'success',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Upcoming Appointments</h3>
                            <a href="appointments.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_appointments)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                <i class="fas fa-calendar-plus" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No upcoming appointments in the next 7 days.</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 1rem; background: var(--gray-50);">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div style="font-weight: 600; color: var(--primary-color);">
                                                    <?php echo formatDate($appointment['appointment_date']); ?>
                                                </div>
                                                <div style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                                </div>
                                                <div style="margin-bottom: 0.25rem;">
                                                    <strong><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?></strong>
                                                </div>
                                                <div style="color: var(--text-muted); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($appointment['operation_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="schedule.php" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i>
                            View My Schedule
                        </a>
                        <a href="treatments.php?action=add" class="btn btn-success">
                            <i class="fas fa-plus"></i>
                            Add Treatment Notes
                        </a>
                        <a href="patients.php" class="btn btn-warning">
                            <i class="fas fa-users"></i>
                            View My Patients
                        </a>
                        <a href="profile.php" class="btn btn-secondary">
                            <i class="fas fa-user-cog"></i>
                            Update Profile
                        </a>
                    </div>
                </div>
            </div>

    <script>
        // Auto-refresh dashboard every 5 minutes
        setInterval(function() {
            window.location.reload();
        }, 300000);

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
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderDentistLayout('Dentist Dashboard', $pageContent, 'dashboard.php');
?>
