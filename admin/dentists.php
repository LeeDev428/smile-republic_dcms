<?php
require_once '../includes/config.php';
requireRole('admin');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle dentist operations
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_dentist') {
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $specialization = sanitize($_POST['specialization']);
        $license_number = sanitize($_POST['license_number']);
        $experience_years = intval($_POST['experience_years']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        
        try {
            // Check if username/email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, email, first_name, last_name, phone, role, status, specialization, license_number, experience_years, consultation_fee, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'dentist', 'active', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $hashed_password, $email, $first_name, $last_name, $phone, $specialization, $license_number, $experience_years, $consultation_fee]);
                $message = "Dentist added successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error adding dentist: " . $e->getMessage();
        }
    } elseif ($action === 'update_dentist') {
        $dentist_id = intval($_POST['dentist_id']);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $specialization = sanitize($_POST['specialization']);
        $license_number = sanitize($_POST['license_number']);
        $experience_years = intval($_POST['experience_years']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $status = sanitize($_POST['status']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, specialization = ?, 
                    license_number = ?, experience_years = ?, consultation_fee = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND role = 'dentist'
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $specialization, $license_number, $experience_years, $consultation_fee, $status, $dentist_id]);
            $message = "Dentist updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating dentist: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $dentist_id = intval($_POST['dentist_id']);
        $new_status = $_POST['new_status'];
        
        if (in_array($new_status, ['active', 'inactive'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND role = 'dentist'");
                $stmt->execute([$new_status, $dentist_id]);
                $message = "Dentist status updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating dentist status: " . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_specialization = $_GET['specialization'] ?? '';

// Build query conditions
$conditions = ["role = 'dentist'"];
$params = [];

if ($search) {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_status) {
    $conditions[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_specialization) {
    $conditions[] = "specialization LIKE ?";
    $params[] = "%$filter_specialization%";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get dentists with appointment counts
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(a.id) as appointment_count,
               COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments,
               MAX(a.appointment_date) as last_appointment_date
        FROM users u
        LEFT JOIN appointments a ON u.id = a.dentist_id
        $where_clause
        GROUP BY u.id
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute($params);
    $dentists = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_dentists,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_dentists,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_dentists_30d,
            AVG(experience_years) as avg_experience
        FROM users 
        WHERE role = 'dentist'
    ");
    $stats = $stmt->fetch();
    
    // Get unique specializations
    $stmt = $pdo->query("SELECT DISTINCT specialization FROM users WHERE role = 'dentist' AND specialization IS NOT NULL ORDER BY specialization");
    $specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Error loading dentists: " . $e->getMessage();
    $dentists = [];
    $stats = ['total_dentists' => 0, 'active_dentists' => 0, 'new_dentists_30d' => 0, 'avg_experience' => 0];
    $specializations = [];
}

function renderPageContent() {
    global $message, $error, $dentists, $stats, $specializations, $search, $filter_status, $filter_specialization;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Dentist Management</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage dentist profiles and information</p>
                </div>
                <div>
                    <button onclick="showAddDentistModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add New Dentist
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid mb-4">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['total_dentists']; ?></div>
                        <div class="stat-label">Total Dentists</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['active_dentists']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo round($stats['avg_experience']); ?></div>
                        <div class="stat-label">Avg Experience (years)</div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['new_dentists_30d']; ?></div>
                        <div class="stat-label">New (30 days)</div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-search"></i>
                        Search & Filter Dentists
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Name, email, or phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Specialization</label>
                            <select name="specialization" class="form-select">
                                <option value="">All Specializations</option>
                                <?php foreach ($specializations as $spec): ?>
                                    <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $filter_specialization === $spec ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($spec); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dentists List -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-list"></i>
                        Dentist Records (<?php echo count($dentists); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($dentists)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-user-md" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h5>No dentists found</h5>
                            <p>No dentists match your search criteria.</p>
                            <button onclick="showAddDentistModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Add First Dentist
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Dentist</th>
                                        <th>Contact</th>
                                        <th>Specialization</th>
                                        <th>Experience</th>
                                        <th>License</th>
                                        <th>Fee</th>
                                        <th>Appointments</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dentists as $dentist): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;">Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    ID: #<?php echo $dentist['id']; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($dentist['email']): ?>
                                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($dentist['email']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($dentist['phone']): ?>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($dentist['phone']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($dentist['specialization']): ?>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($dentist['specialization']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-style: italic;">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $dentist['experience_years'] ?? 0; ?> years</span>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($dentist['license_number'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo formatCurrency($dentist['consultation_fee'] ?? 0); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600;"><?php echo $dentist['appointment_count']; ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        <?php echo $dentist['upcoming_appointments']; ?> upcoming
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = $dentist['status'] === 'active' ? 'success' : 'danger';
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst($dentist['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button onclick="viewDentist(<?php echo $dentist['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="editDentist(<?php echo $dentist['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="toggleStatus(<?php echo $dentist['id']; ?>, '<?php echo $dentist['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                                                            class="btn btn-sm btn-outline-<?php echo $dentist['status'] === 'active' ? 'danger' : 'success'; ?>" 
                                                            title="<?php echo $dentist['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $dentist['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
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

    <!-- Add/Edit Dentist Modal -->
    <div class="modal fade" id="dentistModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dentistModalTitle">Add New Dentist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="dentistForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="dentistAction" value="add_dentist">
                        <input type="hidden" name="dentist_id" id="dentistId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" id="dentistFirstName" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" id="dentistLastName" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" id="dentistEmail" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" id="dentistPhone" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specialization</label>
                                <input type="text" name="specialization" id="dentistSpecialization" class="form-control" placeholder="e.g., Orthodontics, Endodontics">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">License Number</label>
                                <input type="text" name="license_number" id="dentistLicense" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Experience (Years)</label>
                                <input type="number" name="experience_years" id="dentistExperience" class="form-control" min="0" max="50">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Consultation Fee</label>
                                <input type="number" name="consultation_fee" id="dentistFee" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="row" id="loginCredentials">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" id="dentistUsername" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" id="dentistPassword" class="form-control">
                            </div>
                        </div>
                        
                        <div class="mb-3" id="statusField" style="display: none;">
                            <label class="form-label">Status</label>
                            <select name="status" id="dentistStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="dentistSubmitBtn">Add Dentist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddDentistModal() {
            document.getElementById('dentistModalTitle').textContent = 'Add New Dentist';
            document.getElementById('dentistAction').value = 'add_dentist';
            document.getElementById('dentistSubmitBtn').textContent = 'Add Dentist';
            document.getElementById('loginCredentials').style.display = 'block';
            document.getElementById('statusField').style.display = 'none';
            document.getElementById('dentistForm').reset();
            new bootstrap.Modal(document.getElementById('dentistModal')).show();
        }

        function editDentist(dentistId) {
            document.getElementById('dentistModalTitle').textContent = 'Edit Dentist';
            document.getElementById('dentistAction').value = 'update_dentist';
            document.getElementById('dentistId').value = dentistId;
            document.getElementById('dentistSubmitBtn').textContent = 'Update Dentist';
            document.getElementById('loginCredentials').style.display = 'none';
            document.getElementById('statusField').style.display = 'block';
            new bootstrap.Modal(document.getElementById('dentistModal')).show();
        }

        function viewDentist(dentistId) {
            // Placeholder for dentist details view
            alert('Dentist details view will be implemented here. ID: ' + dentistId);
        }

        function toggleStatus(dentistId, newStatus) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this dentist?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="dentist_id" value="${dentistId}">
                    <input type="hidden" name="new_status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-primary { background-color: var(--primary-color); }
            .badge-info { background-color: var(--info-color); }
            .badge-success { background-color: var(--success-color); }
            .badge-danger { background-color: var(--danger-color); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderAdminLayout('Dentist Management', $pageContent, 'dentists');
?>
