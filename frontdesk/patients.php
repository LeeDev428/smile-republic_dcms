<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle patient search/filter
$search = $_GET['search'] ?? '';
$filter_age_min = $_GET['age_min'] ?? '';
$filter_age_max = $_GET['age_max'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_age_min) {
    $conditions[] = "age >= ?";
    $params[] = intval($filter_age_min);
}

if ($filter_age_max) {
    $conditions[] = "age <= ?";
    $params[] = intval($filter_age_max);
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get patients with filters
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(a.id) as appointment_count,
               MAX(a.appointment_date) as last_appointment_date
        FROM patients p
        LEFT JOIN appointments a ON p.id = a.patient_id
        $where_clause
        GROUP BY p.id
        ORDER BY p.first_name ASC, p.last_name ASC
    ");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
    // Get total patients count
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
    $total_patients = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Error loading patients: " . $e->getMessage();
    $patients = [];
    $total_patients = 0;
}

function renderPageContent() {
    global $message, $error, $patients, $total_patients, $search, $filter_age_min, $filter_age_max;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Patients</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage patient records and information</p>
                </div>
                <div>
                    <a href="patient-registration.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Register New Patient
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $total_patients; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count($patients); ?></div>
                        <div class="stat-label">Search Results</div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-search"></i>
                        Search & Filter Patients
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Name, phone, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Minimum Age</label>
                            <input type="number" name="age_min" class="form-control" 
                                   placeholder="e.g., 18" min="0" max="120"
                                   value="<?php echo htmlspecialchars($filter_age_min); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maximum Age</label>
                            <input type="number" name="age_max" class="form-control" 
                                   placeholder="e.g., 65" min="0" max="120"
                                   value="<?php echo htmlspecialchars($filter_age_max); ?>">
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

            <!-- Patients List -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-list"></i>
                        Patient Records (<?php echo count($patients); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($patients)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-user-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h5>No patients found</h5>
                            <p>No patients match your search criteria.</p>
                            <a href="patient-registration.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Register New Patient
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Age</th>
                                        <th>Contact Info</th>
                                        <th>Address</th>
                                        <th>Emergency Contact</th>
                                        <th>Appointments</th>
                                        <th>Last Visit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    ID: #<?php echo $patient['id']; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo $patient['age'] ?? 'N/A'; ?> years</span>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($patient['phone']): ?>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($patient['email']): ?>
                                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($patient['address']): ?>
                                                        <?php echo htmlspecialchars($patient['address']); ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic;">No address</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($patient['emergency_contact']): ?>
                                                        <?php echo htmlspecialchars($patient['emergency_contact']); ?>
                                                        <?php if ($patient['emergency_phone']): ?>
                                                            <br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['emergency_phone']); ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic;">None provided</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600; font-size: 1.2rem;"><?php echo $patient['appointment_count']; ?></div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">total</div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($patient['last_appointment_date']): ?>
                                                    <div style="font-size: 0.875rem;">
                                                        <?php echo formatDate($patient['last_appointment_date']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-style: italic;">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button onclick="viewPatient(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="editPatient(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="schedule.php?patient_id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-sm btn-success" title="Schedule Appointment">
                                                        <i class="fas fa-calendar-plus"></i>
                                                    </a>
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
        function viewPatient(patientId) {
            // Placeholder for patient details modal
            alert('Patient details view will be implemented here. ID: ' + patientId);
        }

        function editPatient(patientId) {
            // Redirect to edit patient page (could be modal or separate page)
            window.location.href = 'patient-edit.php?id=' + patientId;
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-secondary { background-color: var(--gray-500); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Patients', $pageContent, 'patients');
?>
