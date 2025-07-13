<?php
// Handle AJAX request for patient details
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['id'])) {
    require_once '../includes/config.php';
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as calculated_age FROM patients WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($patient) {
            echo json_encode(['success' => true, 'patient' => $patient]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

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
    $conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?";
    $params[] = intval($filter_age_min);
}

if ($filter_age_max) {
    $conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?";
    $params[] = intval($filter_age_max);
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get patients with filters
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as calculated_age,
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
            <?php
            // Calculate gender and age statistics
            $male_count = 0;
            $female_count = 0;
            $others_count = 0;
            $age_below_18 = 0;
            $age_above_18 = 0;
            foreach ($patients as $p) {
                if (isset($p['gender'])) {
                    $gender = strtolower(trim($p['gender']));
                    if ($gender === 'male') {
                        $male_count++;
                    } elseif ($gender === 'female') {
                        $female_count++;
                    } else {
                        $others_count++;
                    }
                } else {
                    $others_count++;
                }
                $age = isset($p['calculated_age']) ? intval($p['calculated_age']) : null;
                if ($age !== null) {
                    if ($age < 18) {
                        $age_below_18++;
                    } else {
                        $age_above_18++;
                    }
                }
            }
            ?>

            <!-- Compact Stats Cards Styles -->
            <style>
                .stats-grid {
                    display: flex;
                    gap: 1.2rem;
                    margin-bottom: 1.2rem;
                }
                .stat-card {
                    flex: 1;
                    background: #fff;
                    border-radius: 10px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                    display: flex;
                    align-items: center;
                    min-height: 70px;
                    padding: 0.7rem 1rem;
                    font-size: 0.95rem;
                }
                .stat-icon {
                    font-size: 1.5rem;
                    margin-right: 0.7rem;
                    color: #5da2d6;
                }
                .stat-value {
                    font-size: 1.3rem;
                    font-weight: 700;
                    color: #222;
                }
                .stat-label {
                    font-size: 0.85rem;
                    color: #64748b;
                    font-weight: 500;
                    letter-spacing: 0.5px;
                }
                .stat-card.primary .stat-icon { color: #5da2d6; }
                .stat-card.info .stat-icon { color: #6c63ff; }
                .stat-card.warning .stat-icon { color: #ffb300; }
                .stat-card.secondary .stat-icon { color: #64748b; }
                .stat-card.success .stat-icon { color: #22c55e; }
                .stat-card.danger .stat-icon { color: #ef4444; }
            </style>
            <!-- Stats Cards: 3 above, 3 below -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $total_patients; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-child"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $age_below_18; ?></div>
                        <div class="stat-label">18 Below</div>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $age_above_18; ?></div>
                        <div class="stat-label">19 Above</div>
                    </div>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-card info">
                    <div class="stat-icon"><i class="fas fa-venus"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $female_count; ?></div>
                        <div class="stat-label">Female</div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-mars"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $male_count; ?></div>
                        <div class="stat-label">Male</div>
                    </div>
                </div>
                <div class="stat-card secondary">
                    <div class="stat-icon"><i class="fas fa-genderless"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $others_count; ?></div>
                        <div class="stat-label">Others</div>
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
                    <form method="GET" class="d-flex align-items-center" style="gap: 2rem;">
                        <div style="flex: 1; position: relative;">
                            <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 1.2rem;">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control"
                                   style="padding-left: 2.5rem; height: 48px; font-size: 1.1rem; border-radius: 8px; border: 1px solid #e3e7ef; background: #f9fbfd;"
                                   placeholder="Search patient name or phone..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <button type="submit" class="btn btn-lg"
                                style="background: #5da2d6; color: #fff; min-width: 180px; font-weight: 600; border-radius: 8px; border: none;">
                            Apply Filters
                        </button>
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
                                        <th style="text-align: center;">Actions</th>
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
                                            </td>                            <td>
                                                <span ><?php echo $patient['calculated_age'] ?? 'N/A'; ?> years</span>
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
                                            </td>                            <td>
                                <div style="font-size: 0.875rem;">
                                    <?php if ($patient['emergency_contact_name']): ?>
                                        <?php echo htmlspecialchars($patient['emergency_contact_name']); ?>
                                        <?php if ($patient['emergency_contact_phone']): ?>
                                            <br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>
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
                                                  <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye" style="color: #222;"></i>
                                                    </a>
                                                    <button onclick="editPatient(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                   <a href="view_appointment_history.php?id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-icon btn-info" 
                                                       title="View Appointment History">
                                                        <i class="fas fa-history" style="color: #222"></i>
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
            // Show loading spinner in modal
            const modal = document.getElementById('patientDetailsModal');
            const modalBody = document.getElementById('patientDetailsBody');
            modalBody.innerHTML = '<div style="text-align:center;padding:2rem;"><span class="spinner-border"></span> Loading...</div>';
            modal.style.display = 'block';

            // Fetch patient details via AJAX
            fetch('patients.php?ajax=1&id=' + patientId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.patient;
                        modalBody.innerHTML = `
                            <table class="table table-bordered" style="width:100%;margin-bottom:0;">
                                <tr><th style="width:40%;text-align:left;color:#64748b;background:#f8fafc;">Patient ID</th><td>${p.id}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Name</th><td>${p.first_name} ${p.last_name}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Age</th><td>${p.calculated_age} years</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Gender</th><td>${p.gender}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Date of Birth</th><td>${p.date_of_birth}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Email</th><td>${p.email}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Phone</th><td>${p.phone}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Address</th><td>${p.address}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Emergency Contact Name</th><td>${p.emergency_contact_name}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Emergency Contact Phone</th><td>${p.emergency_contact_phone}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Medical History</th><td>${p.medical_history}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Allergies</th><td>${p.allergies}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Insurance Info</th><td>${p.insurance_info}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Created At</th><td>${p.created_at}</td></tr>
                                <tr><th style="text-align:left;color:#64748b;background:#f8fafc;">Updated At</th><td>${p.updated_at}</td></tr>
                            </table>
                        `;
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-danger">Failed to load patient details.</div>';
                    }
                })
                .catch(() => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading patient details.</div>';
                });
        }

        function editPatient(patientId) {
            window.location.href = 'patient-edit.php?id=' + patientId;
        }

        function viewHistory(patientId) {
            window.location.href = 'view_appointment_history.php?patient_id=' + patientId;
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-secondary { background-color: var(--gray-500); }
        `;
        document.head.appendChild(style);
    </script>
    <!-- Patient Details Modal -->
    <div id="patientDetailsModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:600px;margin:5% auto;padding:2rem;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.15);position:relative;max-height:80vh;overflow-y:auto;">
            <button onclick="document.getElementById('patientDetailsModal').style.display='none'" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:1.5rem;color:#888;cursor:pointer;">&times;</button>
            <h3 style="margin-bottom:1.5rem;">Patient Record</h3>
            <div id="patientDetailsBody"></div>
        </div>
    </div>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Patients', $pageContent, 'patients');
?>
