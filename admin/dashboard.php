<?php
require_once '../includes/config.php';
requireRole('admin');

// Get dashboard statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $total_users = $stmt->fetch()['total'];
    
    // Total patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
    $total_patients = $stmt->fetch()['total'];
    
    // Today's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE() AND status NOT IN ('cancelled', 'no_show')");
    $stmt->execute();
    $today_appointments = $stmt->fetch()['total'];
    
    // Total revenue this month
    $stmt = $pdo->prepare("SELECT SUM(total_cost) as total FROM appointments WHERE MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE()) AND status = 'completed'");
    $stmt->execute();
    $monthly_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Recent appointments
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name as patient_first, p.last_name as patient_last, 
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_appointments = $stmt->fetchAll();
    
    // Get dental operations count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dental_operations WHERE status = 'active'");
    $total_operations = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smile Republic Dental Clinic</title>
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
                    <div style="font-weight: 600; color: var(--primary-color); font-size: 0.875rem;">Administrator</div>
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
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    Staff Management
                </a>
                <a href="patients.php">
                    <i class="fas fa-user-injured"></i>
                    Patients
                </a>
                <a href="appointments.php">
                    <i class="fas fa-calendar-alt"></i>
                    Appointments
                </a>
                <a href="operations.php">
                    <i class="fas fa-teeth"></i>
                    Dental Operations
                </a>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    Settings
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
                    <h1 style="margin-bottom: 0.5rem;">Admin Dashboard</h1>
                    <p style="margin: 0; color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Here's what's happening at your clinic today.</p>
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
                            <div class="stat-value"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Active Staff Members</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--primary-color); opacity: 0.7;">
                            <i class="fas fa-users"></i>
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
                            <i class="fas fa-user-injured"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($today_appointments); ?></div>
                            <div class="stat-label">Today's Appointments</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--warning-color); opacity: 0.7;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo formatCurrency($monthly_revenue); ?></div>
                            <div class="stat-label">Monthly Revenue</div>
                        </div>
                        <div style="font-size: 2rem; color: var(--success-color); opacity: 0.7;">
                            <i class="fas fa-peso-sign"></i>
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
                        <a href="users.php?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Add New Staff
                        </a>
                        <a href="patients.php?action=add" class="btn btn-success">
                            <i class="fas fa-user-injured"></i>
                            Add New Patient
                        </a>
                        <a href="operations.php?action=add" class="btn btn-warning">
                            <i class="fas fa-plus"></i>
                            Add Dental Operation
                        </a>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fas fa-chart-line"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Recent Appointments</h3>
                        <a href="appointments.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_appointments)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>No recent appointments found.</p>
                            <a href="appointments.php?action=add" class="btn btn-primary">Schedule First Appointment</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Dentist</th>
                                        <th>Operation</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['operation_name']); ?></td>
                                            <td>
                                                <div><?php echo formatDate($appointment['appointment_date']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                                </div>
                                            </td>
                                            <td>
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
                                            </td>
                                            <td style="font-weight: 600;">
                                                <?php echo formatCurrency($appointment['total_cost']); ?>
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
</body>
</html>
