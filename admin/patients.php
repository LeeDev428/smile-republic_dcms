<?php
require_once '../includes/config.php';
requireRole('admin');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle patient operations
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_patient') {
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $age = intval($_POST['age']);
        $emergency_contact = sanitize($_POST['emergency_contact']);
        $emergency_phone = sanitize($_POST['emergency_phone']);
        $medical_history = sanitize($_POST['medical_history']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO patients (first_name, last_name, phone, email, address, age, emergency_contact, emergency_phone, medical_history, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$first_name, $last_name, $phone, $email, $address, $age, $emergency_contact, $emergency_phone, $medical_history]);
            $message = "Patient added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding patient: " . $e->getMessage();
        }
    } elseif ($action === 'update_patient') {
        $patient_id = intval($_POST['patient_id']);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $age = intval($_POST['age']);
        $emergency_contact = sanitize($_POST['emergency_contact']);
        $emergency_phone = sanitize($_POST['emergency_phone']);
        $medical_history = sanitize($_POST['medical_history']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE patients 
                SET first_name = ?, last_name = ?, phone = ?, email = ?, address = ?, age = ?, 
                    emergency_contact = ?, emergency_phone = ?, medical_history = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $phone, $email, $address, $age, $emergency_contact, $emergency_phone, $medical_history, $patient_id]);
            $message = "Patient updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating patient: " . $e->getMessage();
        }
    } elseif ($action === 'delete_patient') {
        $patient_id = intval($_POST['patient_id']);
        
        try {
            // Check if patient has appointments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $appointment_count = $stmt->fetchColumn();
            
            if ($appointment_count > 0) {
                $error = "Cannot delete patient. Patient has existing appointments.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
                $stmt->execute([$patient_id]);
                $message = "Patient deleted successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error deleting patient: " . $e->getMessage();
        }
    }
}

// Get filter parameters
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
    // Get patients with appointment counts
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(a.id) as appointment_count,
               MAX(a.appointment_date) as last_appointment_date,
               SUM(CASE WHEN a.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments
        FROM patients p
        LEFT JOIN appointments a ON p.id = a.patient_id
        $where_clause
        GROUP BY p.id
        ORDER BY p.first_name ASC, p.last_name ASC
    ");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_patients,
            AVG(age) as avg_age,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_patients_30d
        FROM patients 
        WHERE age IS NOT NULL
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error loading patients: " . $e->getMessage();
    $patients = [];
    $stats = ['total_patients' => 0, 'avg_age' => 0, 'new_patients_30d' => 0];
}

function renderPageContent() {
    global $message, $error, $patients, $stats, $search, $filter_age_min, $filter_age_max;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Patient Management</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage all patient records and information</p>
                </div>
                <div>
                    <button onclick="showAddPatientModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add New Patient
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['total_patients']; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['new_patients_30d']; ?></div>
                        <div class="stat-label">New (30 days)</div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo round($stats['avg_age']); ?></div>
                        <div class="stat-label">Average Age</div>
                    </div>
                </div>
                
                <div class="stat-card warning">
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
                            <button onclick="showAddPatientModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Add First Patient
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Contact</th>
                                        <th>Appointments</th>
                                        <th>Last Visit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-secondary">#<?php echo $patient['id']; ?></span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                                <?php if ($patient['email']): ?>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);"><?php echo htmlspecialchars($patient['email']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $patient['age'] ?? 'N/A'; ?></span>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($patient['phone']): ?>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600; font-size: 1.2rem;"><?php echo $patient['appointment_count']; ?></div>
                                                    <?php if ($patient['pending_payments'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: var(--danger-color);">
                                                            <?php echo $patient['pending_payments']; ?> pending
                                                        </div>
                                                    <?php endif; ?>
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
                                                <?php if ($patient['pending_payments'] > 0): ?>
                                                    <span class="badge badge-warning">Pending Payment</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button onclick="viewPatient(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="editPatient(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deletePatient(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
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

    <!-- Add/Edit Patient Modal -->
    <div class="modal fade" id="patientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientModalTitle">Add New Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="patientForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="patientAction" value="add_patient">
                        <input type="hidden" name="patient_id" id="patientId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" id="patientFirstName" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" id="patientLastName" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" name="phone" id="patientPhone" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="patientEmail" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Age</label>
                                <input type="number" name="age" id="patientAge" class="form-control" min="0" max="120">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="patientAddress" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" id="patientEmergencyContact" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Phone</label>
                                <input type="tel" name="emergency_phone" id="patientEmergencyPhone" class="form-control">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Medical History</label>
                            <textarea name="medical_history" id="patientMedicalHistory" class="form-control" rows="3" placeholder="Any allergies, medical conditions, medications..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="patientSubmitBtn">Add Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddPatientModal() {
            document.getElementById('patientModalTitle').textContent = 'Add New Patient';
            document.getElementById('patientAction').value = 'add_patient';
            document.getElementById('patientSubmitBtn').textContent = 'Add Patient';
            document.getElementById('patientForm').reset();
            new bootstrap.Modal(document.getElementById('patientModal')).show();
        }

        function editPatient(patientId) {
            // In a real implementation, you would fetch patient data via AJAX
            // For now, show modal for editing
            document.getElementById('patientModalTitle').textContent = 'Edit Patient';
            document.getElementById('patientAction').value = 'update_patient';
            document.getElementById('patientId').value = patientId;
            document.getElementById('patientSubmitBtn').textContent = 'Update Patient';
            new bootstrap.Modal(document.getElementById('patientModal')).show();
        }

        function viewPatient(patientId) {
            // Placeholder for patient details view
            alert('Patient details view will be implemented here. ID: ' + patientId);
        }

        function deletePatient(patientId) {
            if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_patient">
                    <input type="hidden" name="patient_id" value="${patientId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-secondary { background-color: var(--gray-500); }
            .badge-primary { background-color: var(--primary-color); }
            .badge-info { background-color: var(--info-color); }
            .badge-warning { background-color: var(--warning-color); }
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

renderAdminLayout('Patient Management', $pageContent, 'patients');
?>
