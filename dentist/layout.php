<?php
// Dentist Layout - Reusable layout for dentist pages
// Usage: Include this file and call renderDentistLayout() with page content

function renderDentistLayout($pageTitle, $pageContent, $activeMenu = '', $additionalCSS = '', $additionalJS = '') {
    require_once '../includes/config.php';
    requireRole('dentist');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Smile Republic Dental Clinic</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo $additionalCSS; ?>
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
                    <div style="font-weight: 600; color: var(--success-color); font-size: 0.875rem;">Dentist</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" <?php echo $activeMenu === 'dashboard' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="schedule.php" <?php echo $activeMenu === 'schedule' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-calendar-alt"></i>
                    My Schedule
                </a>
                <a href="appointments.php" <?php echo $activeMenu === 'appointments' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-calendar-check"></i>
                    Appointments
                </a>
                <a href="patients.php" <?php echo $activeMenu === 'patients' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-users"></i>
                    My Patients
                </a>
                <a href="compensation.php" <?php echo $activeMenu === 'compensation' ? 'class="active"' : ''; ?> >
                    <i class="fas fa-money-check-alt"></i>
                    Commission
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
            <?php echo $pageContent; ?>
        </main>
    </div>

    <?php echo $additionalJS; ?>
</body>
</html>
<?php
}
?>
