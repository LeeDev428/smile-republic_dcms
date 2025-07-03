<?php
require_once '../includes/config.php';
requireRole('admin');

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($username) || empty($email) || empty($role) || empty($first_name) || empty($last_name) || empty($password)) {
            $error = 'All fields except phone are required.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!in_array($role, ['dentist', 'frontdesk'])) {
            $error = 'Invalid role selected.';
        } else {
            try {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists.';
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $role, $first_name, $last_name, $phone]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // If creating a dentist, create dentist profile
                    if ($role === 'dentist') {
                        $specialization = sanitize($_POST['specialization'] ?? '');
                        $license_number = sanitize($_POST['license_number'] ?? '');
                        $years_experience = intval($_POST['years_experience'] ?? 0);
                        $education = sanitize($_POST['education'] ?? '');
                        
                        $stmt = $pdo->prepare("INSERT INTO dentist_profiles (user_id, specialization, license_number, years_of_experience, education) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $specialization, $license_number, $years_experience, $education]);
                    }
                    
                    $message = ucfirst($role) . ' account created successfully for ' . $first_name . ' ' . $last_name . '.';
                }
            } catch (PDOException $e) {
                $error = 'Error creating account: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_status') {
        $user_id = intval($_POST['user_id']);
        $status = $_POST['status'];
        
        if (in_array($status, ['active', 'inactive'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'");
                $stmt->execute([$status, $user_id]);
                $message = 'User status updated successfully.';
            } catch (PDOException $e) {
                $error = 'Error updating status: ' . $e->getMessage();
            }
        }
    }
}

// Get all staff users (exclude admin)
try {
    $stmt = $pdo->prepare("
        SELECT u.*, dp.specialization, dp.license_number, dp.years_of_experience 
        FROM users u 
        LEFT JOIN dentist_profiles dp ON u.id = dp.user_id 
        WHERE u.role != 'admin' 
        ORDER BY u.role, u.first_name, u.last_name
    ");
    $stmt->execute();
    $staff_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading staff list: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Smile Republic Dental Clinic</title>
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
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="users.php" class="active">
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
                    <h1 style="margin-bottom: 0.5rem;">Staff Management</h1>
                    <p style="margin: 0; color: var(--text-muted);">Create and manage dentist and front desk staff accounts</p>
                </div>
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Add New Staff
                </button>
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

            <!-- Staff List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Current Staff Members</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($staff_users)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-users" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No Staff Members</h3>
                            <p>Start by adding your first dentist or front desk staff member.</p>
                            <button onclick="openCreateModal()" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i>
                                Add First Staff Member
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Specialization</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staff_users as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo $user['role'] === 'dentist' ? 'Dr. ' : ''; ?>
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    @<?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role'] === 'dentist' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($user['specialization'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $user['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>" 
                                                                onclick="return confirm('Are you sure you want to <?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                                            <i class="fas fa-<?php echo $user['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                            <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
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
        </main>
    </div>

    <!-- Create Staff Modal -->
    <div id="createModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--white); border-radius: var(--border-radius-lg); padding: 2rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Create New Staff Account</h3>
                <button onclick="closeCreateModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--gray-400); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="first_name" class="form-label">
                            <i class="fas fa-user"></i>
                            First Name *
                        </label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label">
                            <i class="fas fa-user"></i>
                            Last Name *
                        </label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">
                        <i class="fas fa-user-tag"></i>
                        Role *
                    </label>
                    <select id="role" name="role" class="form-control form-select" required onchange="toggleDentistFields()">
                        <option value="">Select Role</option>
                        <option value="dentist">Dentist</option>
                        <option value="frontdesk">Front Desk</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-at"></i>
                            Username *
                        </label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email *
                        </label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone"></i>
                        Phone Number
                    </label>
                    <input type="tel" id="phone" name="phone" class="form-control">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password *
                        </label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Confirm Password *
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <!-- Dentist-specific fields -->
                <div id="dentistFields" style="display: none;">
                    <hr style="margin: 2rem 0;">
                    <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">
                        <i class="fas fa-user-md"></i>
                        Dentist Profile Information
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="specialization" class="form-label">
                                <i class="fas fa-stethoscope"></i>
                                Specialization
                            </label>
                            <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., Orthodontics, Oral Surgery">
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number" class="form-label">
                                <i class="fas fa-id-card"></i>
                                License Number
                            </label>
                            <input type="text" id="license_number" name="license_number" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="years_experience" class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Years of Experience
                        </label>
                        <input type="number" id="years_experience" name="years_experience" class="form-control" min="0" max="50">
                    </div>

                    <div class="form-group">
                        <label for="education" class="form-label">
                            <i class="fas fa-graduation-cap"></i>
                            Education
                        </label>
                        <textarea id="education" name="education" class="form-control" rows="3" placeholder="Education background, degrees, certifications..."></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('createForm').reset();
            document.getElementById('dentistFields').style.display = 'none';
        }

        function toggleDentistFields() {
            const role = document.getElementById('role').value;
            const dentistFields = document.getElementById('dentistFields');
            
            if (role === 'dentist') {
                dentistFields.style.display = 'block';
            } else {
                dentistFields.style.display = 'none';
            }
        }

        // Close modal when clicking outside
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });

        // Form validation
        document.getElementById('createForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
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
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
