<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Get frontdesk dashboard statistics
try {
    $frontdesk_id = $_SESSION['user_id'];
    
    // Today's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE() AND status NOT IN ('cancelled', 'no_show')");
    $stmt->execute();
    $today_appointments = $stmt->fetch()['total'];
    
    // Appointments created by this frontdesk staff today
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE frontdesk_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$frontdesk_id]);
    $today_created = $stmt->fetch()['total'];
    
    // Total patients registered
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
    $total_patients = $stmt->fetch()['total'];
    
    // Pending appointments (scheduled but not confirmed)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()");
    $stmt->execute();
    $pending_appointments = $stmt->fetch()['total'];
    
    // Today's schedule overview
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name as patient_first, p.last_name as patient_last, 
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
    $today_schedule = $stmt->fetchAll();
    
    // Recent patients
    $stmt = $pdo->prepare("
        SELECT * FROM patients 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_patients = $stmt->fetchAll();
    
    // Available dentists today
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, dp.specialization
        FROM users u
        LEFT JOIN dentist_profiles dp ON u.id = dp.user_id
        WHERE u.role = 'dentist' AND u.status = 'active'
        AND (dp.is_available = TRUE OR dp.is_available IS NULL)
    ");
    $stmt->execute();
    $available_dentists = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Front Desk Dashboard - Smile Republic Dental Clinic</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="#" class="logo">
                    <i class="fas fa-tooth"></i>
                    <span>Smile Republic</span>
                </a>
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--gray-100); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-weight: 600; color: var(--warning-color); font-size: 0.875rem;">Front Desk</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="appointments.php">
                    <i class="fas fa-calendar-alt"></i>
                    Appointments
                </a>
                <a href="schedule.php">
                    <i class="fas fa-calendar-check"></i>
                    Schedule Appointment
                </a>
                <a href="patients.php">
                    <i class="fas fa-users"></i>
                    Patients
                </a>
                <a href="patient-registration.php">
                    <i class="fas fa-user-plus"></i>
                    Register Patient
                </a>
                <a href="check-in.php">
                    <i class="fas fa-check-circle"></i>
                    Patient Check-in
                </a>
                <a href="payments.php">
                    <i class="fas fa-credit-card"></i>
                    Payments
                </a>
                <div style="border-top: 1px solid var(--border-color); margin: 1rem 0; padding-top: 1rem;">
                    <a href="../logout.php" style="color: var(--danger-color);">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Front Desk Dashboard</h1>
                    <p style="margin: 0; color: var(--text-muted);">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Manage appointments and assist patients.</p>
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
                
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($today_created); ?></div>
                            <div class="stat-label">Appointments Scheduled Today</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--success-color); opacity: 0.7;">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($pending_appointments); ?></div>
                            <div class="stat-label">Pending Confirmations</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--warning-color); opacity: 0.7;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_patients); ?></div>
                            <div class="stat-label">Registered Patients</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--success-color); opacity: 0.7;">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="schedule.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i>
                            Schedule Appointment
                        </a>
                        <a href="patient-registration.php" class="btn btn-success">
                            <i class="fas fa-user-plus"></i>
                            Register New Patient
                        </a>
                        <a href="check-in.php" class="btn btn-warning">
                            <i class="fas fa-check-circle"></i>
                            Patient Check-in
                        </a>
                        <a href="payments.php" class="btn btn-secondary">
                            <i class="fas fa-credit-card"></i>
                            Process Payment
                        </a>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Today's Schedule</h3>
                            <a href="appointments.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($today_schedule)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No appointments scheduled for today.</p>
                                <a href="schedule.php" class="btn btn-primary">Schedule First Appointment</a>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php foreach ($today_schedule as $appointment): ?>
                                    <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 1rem; background: var(--white);">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div style="flex: 1;">
                                                <div class="d-flex align-items-center gap-1rem">
                                                    <div style="font-weight: 600; color: var(--primary-color); margin-right: 1rem;">
                                                        <?php echo formatTime($appointment['appointment_time']); ?>
                                                    </div>
                                                    <div style="margin-right: 1rem;">
                                                        <div style="font-weight: 600;">
                                                            <?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?>
                                                        </div>
                                                        <div style="color: var(--text-muted); font-size: 0.875rem;">
                                                            Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div style="color: var(--text-muted); font-size: 0.875rem;">
                                                            <?php echo htmlspecialchars($appointment['operation_name']); ?>
                                                        </div>
                                                        <div style="color: var(--text-muted); font-size: 0.75rem;">
                                                            <i class="fas fa-clock"></i>
                                                            <?php echo $appointment['duration_minutes']; ?> min
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <span class="badge badge-<?php 
                                                    echo match($appointment['status']) {
                                                        'scheduled' => 'primary',
                                                        'confirmed' => 'success',
                                                        'in_progress' => 'warning',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                        'no_show' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                                <div style="margin-top: 0.5rem; font-size: 0.875rem; font-weight: 600;">
                                                    <?php echo formatCurrency($appointment['total_cost']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Available Dentists -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Available Dentists</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($available_dentists)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                <i class="fas fa-user-md" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No dentists available.</p>
                            </div>
                        <?php else: ?>
                            <div>
                                <?php foreach ($available_dentists as $dentist): ?>
                                    <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 1rem; background: var(--gray-50);">
                                        <div style="font-weight: 600; color: var(--gray-900);">
                                            Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>
                                        </div>
                                        <?php if ($dentist['specialization']): ?>
                                            <div style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                                                <i class="fas fa-stethoscope"></i>
                                                <?php echo htmlspecialchars($dentist['specialization']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="margin-top: 0.75rem;">
                                            <a href="schedule.php?dentist_id=<?php echo $dentist['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-calendar-plus"></i>
                                                Schedule
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Patients -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Recent Patients</h3>
                        <a href="patients.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_patients)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>No patients registered yet.</p>
                            <a href="patient-registration.php" class="btn btn-primary">Register First Patient</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo formatDate($patient['created_at']); ?></td>
                                            <td>
                                                <a href="schedule.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-calendar-plus"></i>
                                                    Schedule
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh dashboard every 2 minutes (more frequent for front desk)
        setInterval(function() {
            window.location.reload();
        }, 120000);

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
</body>
</html>
