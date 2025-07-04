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
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        
        try {
            // Check if username/email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                $pdo->beginTransaction();
                try {
                    // Insert user record
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, email, first_name, last_name, phone, role, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'dentist', 'active', NOW())
                    ");
                    $stmt->execute([$username, $hashed_password, $email, $first_name, $last_name, $phone]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Insert dentist profile record
                    $stmt = $pdo->prepare("
                        INSERT INTO dentist_profiles (user_id, specialization, license_number, years_of_experience, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$user_id, $specialization, $license_number, $experience_years]);
                    
                    $pdo->commit();
                    $message = "Dentist added successfully.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
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
        $status = sanitize($_POST['status']);
        
        try {
            $pdo->beginTransaction();
            
            // Update user record
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND role = 'dentist'
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $status, $dentist_id]);
            
            // Update dentist profile record
            $stmt = $pdo->prepare("
                UPDATE dentist_profiles 
                SET specialization = ?, license_number = ?, years_of_experience = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$specialization, $license_number, $experience_years, $dentist_id]);
            
            $pdo->commit();
            $message = "Dentist updated successfully.";
        } catch (PDOException $e) {
            $pdo->rollBack();
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
$conditions = ["u.role = 'dentist'"];
$params = [];

if ($search) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_status) {
    $conditions[] = "u.status = ?";
    $params[] = $filter_status;
}

if ($filter_specialization) {
    $conditions[] = "dp.specialization LIKE ?";
    $params[] = "%$filter_specialization%";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get dentists with appointment counts
    $stmt = $pdo->prepare("
        SELECT u.*, 
               dp.specialization,
               dp.license_number,
               dp.years_of_experience,
               COUNT(a.id) as appointment_count,
               COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments,
               MAX(a.appointment_date) as last_appointment_date
        FROM users u
        LEFT JOIN dentist_profiles dp ON u.id = dp.user_id
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
            COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_dentists,
            COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_dentists_30d,
            AVG(dp.years_of_experience) as avg_experience
        FROM users u
        LEFT JOIN dentist_profiles dp ON u.id = dp.user_id
        WHERE u.role = 'dentist'
    ");
    $stats = $stmt->fetch();
    
    // Get unique specializations
    $stmt = $pdo->query("SELECT DISTINCT specialization FROM dentist_profiles WHERE specialization IS NOT NULL ORDER BY specialization");
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

            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-search"></i>
                        Search & Filter Dentists
                    </h5>
                </div>
                <div class="card-body">
                    <form id="searchForm" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" id="searchInput" class="form-control" 
                                   placeholder="Name, email, or phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="statusFilter" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Specialization</label>
                            <select name="specialization" id="specializationFilter" class="form-select">
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
                                <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times"></i>
                                    Clear
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
                            <table class="table table-hover" id="dentistTable">
                                <thead>
                                    <tr>
                                        <th>Dentist</th>
                                        <th>Phone</th>
                                        <th>Specialization</th>
                                        <th>License</th>
                                        <th>Experience</th>
                                        <th>Status</th>
                                        <th>Appointments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="dentistTableBody">
                                    <?php include 'dentist_table_content.php'; ?>
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
                                <label class="form-label">Status</label>
                                <select name="status" id="dentistStatus" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
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

    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // AJAX search functionality
        let searchTimeout;
        
        function performSearch() {
            const formData = new FormData(document.getElementById('searchForm'));
            const params = new URLSearchParams(formData);
            
            fetch('search_dentists.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('dentistTableBody').innerHTML = data.html;
                        document.querySelector('.card-header h5').innerHTML = 
                            `<i class="fas fa-list"></i> Dentist Records (${data.count})`;
                    } else {
                        console.error('Search error:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                });
        }
        
        // Debounced search on input
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });
        
        // Immediate search on filter change
        document.getElementById('statusFilter').addEventListener('change', performSearch);
        document.getElementById('specializationFilter').addEventListener('change', performSearch);
        
        // Clear filters
        document.getElementById('clearFilters').addEventListener('click', function() {
            document.getElementById('searchForm').reset();
            performSearch();
        });

        function showAddDentistModal() {
            document.getElementById('dentistModalTitle').textContent = 'Add New Dentist';
            document.getElementById('dentistAction').value = 'add_dentist';
            document.getElementById('dentistSubmitBtn').textContent = 'Add Dentist';
            document.getElementById('loginCredentials').style.display = 'block';
            document.getElementById('statusField').style.display = 'none';
            document.getElementById('dentistForm').reset();
            new bootstrap.Modal(document.getElementById('dentistModal')).show();
        }

        async function editDentist(dentistId) {
            try {
                const response = await fetch(`get_dentist_details.php?id=${dentistId}`);
                const data = await response.json();
                
                if (data.success) {
                    const dentist = data.dentist;
                    
                    document.getElementById('dentistModalTitle').textContent = 'Edit Dentist';
                    document.getElementById('dentistAction').value = 'update_dentist';
                    document.getElementById('dentistId').value = dentistId;
                    document.getElementById('dentistSubmitBtn').textContent = 'Update Dentist';
                    document.getElementById('loginCredentials').style.display = 'none';
                    document.getElementById('statusField').style.display = 'none';
                    
                    // Populate form fields
                    document.getElementById('dentistFirstName').value = dentist.first_name || '';
                    document.getElementById('dentistLastName').value = dentist.last_name || '';
                    document.getElementById('dentistEmail').value = dentist.email || '';
                    document.getElementById('dentistPhone').value = dentist.phone || '';
                    document.getElementById('dentistSpecialization').value = dentist.specialization || '';
                    document.getElementById('dentistLicense').value = dentist.license_number || '';
                    document.getElementById('dentistExperience').value = dentist.years_of_experience || '';
                    document.getElementById('dentistStatus').value = dentist.status || 'active';
                    
                    new bootstrap.Modal(document.getElementById('dentistModal')).show();
                } else {
                    Swal.fire('Error', data.error || 'Failed to load dentist details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        async function viewDentist(dentistId) {
            try {
                const response = await fetch(`get_dentist_details.php?id=${dentistId}`);
                const data = await response.json();
                
                if (data.success) {
                    const dentist = data.dentist;
                    
                    Swal.fire({
                        title: `Dr. ${dentist.first_name} ${dentist.last_name}`,
                        html: `
                            <div class="text-start">
                                <p><strong>Email:</strong> ${dentist.email || 'Not provided'}</p>
                                <p><strong>Phone:</strong> ${dentist.phone || 'Not provided'}</p>
                                <p><strong>Specialization:</strong> ${dentist.specialization || 'General'}</p>
                                <p><strong>License Number:</strong> ${dentist.license_number || 'Not provided'}</p>
                                <p><strong>Experience:</strong> ${dentist.years_of_experience || 0} years</p>
                                <p><strong>Status:</strong> <span class="badge badge-${dentist.status === 'active' ? 'success' : 'warning'}">${dentist.status}</span></p>
                                <p><strong>Member Since:</strong> ${new Date(dentist.created_at).toLocaleDateString()}</p>
                            </div>
                        `,
                        showCloseButton: true,
                        showConfirmButton: false,
                        width: '500px'
                    });
                } else {
                    Swal.fire('Error', data.error || 'Failed to load dentist details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        function toggleDentistStatus(dentistId, newStatus) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Dentist?`,
                text: `Are you sure you want to ${action} this dentist?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: newStatus === 'active' ? '#28a745' : '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action}!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
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
            });
        }

        // Add CSS for badges and avatar circles
        const style = document.createElement('style');
        style.textContent = `
            .badge-primary { background-color: #007bff; color: white; }
            .badge-info { background-color: #17a2b8; color: white; }
            .badge-success { background-color: #28a745; color: white; }
            .badge-warning { background-color: #ffc107; color: #212529; }
            .badge-danger { background-color: #dc3545; color: white; }
            
            .avatar-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 14px;
                flex-shrink: 0;
            }
            
            .table th {
                border-top: none;
                font-weight: 600;
                color: #495057;
                background-color: #f8f9fa;
            }
            
            .table-hover tbody tr:hover {
                background-color: rgba(0, 123, 255, 0.05);
            }
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