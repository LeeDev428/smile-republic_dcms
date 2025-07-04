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
        $date_of_birth = $_POST['date_of_birth'] ? $_POST['date_of_birth'] : null;
        $gender = sanitize($_POST['gender']);
        $emergency_contact_name = sanitize($_POST['emergency_contact_name']);
        $emergency_contact_phone = sanitize($_POST['emergency_contact_phone']);
        $medical_history = sanitize($_POST['medical_history']);
        $allergies = sanitize($_POST['allergies']);
        $insurance_info = sanitize($_POST['insurance_info']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO patients (first_name, last_name, phone, email, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, medical_history, allergies, insurance_info, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$first_name, $last_name, $phone, $email, $address, $date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone, $medical_history, $allergies, $insurance_info]);
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
        $date_of_birth = $_POST['date_of_birth'] ? $_POST['date_of_birth'] : null;
        $gender = sanitize($_POST['gender']);
        $emergency_contact_name = sanitize($_POST['emergency_contact_name']);
        $emergency_contact_phone = sanitize($_POST['emergency_contact_phone']);
        $medical_history = sanitize($_POST['medical_history']);
        $allergies = sanitize($_POST['allergies']);
        $insurance_info = sanitize($_POST['insurance_info']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE patients 
                SET first_name = ?, last_name = ?, phone = ?, email = ?, address = ?, date_of_birth = ?, gender = ?,
                    emergency_contact_name = ?, emergency_contact_phone = ?, medical_history = ?, allergies = ?, insurance_info = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $phone, $email, $address, $date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone, $medical_history, $allergies, $insurance_info, $patient_id]);
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
    $conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?";
    $params[] = intval($filter_age_min);
}

if ($filter_age_max) {
    $conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?";
    $params[] = intval($filter_age_max);
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get patients with appointment counts
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
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_patients,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_patients_30d
        FROM patients 
        WHERE date_of_birth IS NOT NULL
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
                        <div class="stat-value"><?php echo $stats['avg_age'] ? round($stats['avg_age']) : 0; ?></div>
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
                                            </td>                            <td>
                                <span class="badge badge-info"><?php echo $patient['calculated_age'] ?? 'N/A'; ?></span>
                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($patient['phone']): ?>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>                            <td>
                                <div class="text-center">
                                    <div style="font-weight: 600; font-size: 1.2rem;"><?php echo $patient['appointment_count']; ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">total visits</div>
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
                                            </td>                            <td>
                                <span class="badge badge-success">Active</span>
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
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="patientDateOfBirth" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" id="patientGender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Info</label>
                                <input type="text" name="insurance_info" id="patientInsurance" class="form-control" placeholder="Insurance provider/policy">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="patientAddress" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="patientEmergencyContactName" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" id="patientEmergencyContactPhone" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Medical History</label>
                                <textarea name="medical_history" id="patientMedicalHistory" class="form-control" rows="3" placeholder="Medical conditions, surgeries, etc."></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Allergies</label>
                                <textarea name="allergies" id="patientAllergies" class="form-control" rows="3" placeholder="Known allergies to medications, foods, etc."></textarea>
                            </div>
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

    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function showAddPatientModal() {
            document.getElementById('patientModalTitle').textContent = 'Add New Patient';
            document.getElementById('patientAction').value = 'add_patient';
            document.getElementById('patientSubmitBtn').textContent = 'Add Patient';
            document.getElementById('patientForm').reset();
            new bootstrap.Modal(document.getElementById('patientModal')).show();
        }

        async function editPatient(patientId) {
            try {
                const response = await fetch(`get_patient_details.php?id=${patientId}`);
                const data = await response.json();
                
                if (data.success) {
                    const patient = data.patient;
                    
                    document.getElementById('patientModalTitle').textContent = 'Edit Patient';
                    document.getElementById('patientAction').value = 'update_patient';
                    document.getElementById('patientId').value = patientId;
                    document.getElementById('patientSubmitBtn').textContent = 'Update Patient';
                    
                    // Populate form fields
                    document.getElementById('patientFirstName').value = patient.first_name || '';
                    document.getElementById('patientLastName').value = patient.last_name || '';
                    document.getElementById('patientPhone').value = patient.phone || '';
                    document.getElementById('patientEmail').value = patient.email || '';
                    document.getElementById('patientDateOfBirth').value = patient.date_of_birth || '';
                    document.getElementById('patientGender').value = patient.gender || '';
                    document.getElementById('patientAddress').value = patient.address || '';
                    document.getElementById('patientEmergencyContactName').value = patient.emergency_contact_name || '';
                    document.getElementById('patientEmergencyContactPhone').value = patient.emergency_contact_phone || '';
                    document.getElementById('patientMedicalHistory').value = patient.medical_history || '';
                    document.getElementById('patientAllergies').value = patient.allergies || '';
                    document.getElementById('patientInsurance').value = patient.insurance_info || '';
                    
                    new bootstrap.Modal(document.getElementById('patientModal')).show();
                } else {
                    Swal.fire('Error', data.error || 'Failed to load patient details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        async function viewPatient(patientId) {
            try {
                const response = await fetch(`get_patient_details.php?id=${patientId}`);
                const data = await response.json();
                
                if (data.success) {
                    const patient = data.patient;
                    
                    // Format the patient information
                    const formatCurrency = (amount) => {
                        return new Intl.NumberFormat('en-PH', {
                            style: 'currency',
                            currency: 'PHP'
                        }).format(amount || 0);
                    };
                    
                    const formatDate = (dateString) => {
                        if (!dateString) return 'Never';
                        return new Date(dateString).toLocaleDateString('en-PH', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    };
                    
                    Swal.fire({
                        title: `${patient.first_name} ${patient.last_name}`,
                        html: `
                            <div class="text-start">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Patient ID:</strong><br>
                                        <span class="badge badge-secondary">#${patient.id}</span>
                                    </div>
                                    <div class="col-6">
                                        <strong>Age:</strong><br>
                                        ${patient.calculated_age || 'Not specified'} years
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Phone:</strong><br>
                                        ${patient.phone || 'Not provided'}
                                    </div>
                                    <div class="col-6">
                                        <strong>Email:</strong><br>
                                        ${patient.email || 'Not provided'}
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Date of Birth:</strong><br>
                                        ${formatDate(patient.date_of_birth) || 'Not provided'}
                                    </div>
                                    <div class="col-6">
                                        <strong>Gender:</strong><br>
                                        ${patient.gender || 'Not specified'}
                                    </div>
                                </div>
                                
                                ${patient.address ? `
                                <div class="mb-3">
                                    <strong>Address:</strong><br>
                                    ${patient.address}
                                </div>
                                ` : ''}
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Emergency Contact:</strong><br>
                                        ${patient.emergency_contact_name || 'Not provided'}
                                    </div>
                                    <div class="col-6">
                                        <strong>Emergency Phone:</strong><br>
                                        ${patient.emergency_contact_phone || 'Not provided'}
                                    </div>
                                </div>
                                
                                ${patient.allergies ? `
                                <div class="mb-3">
                                    <strong>Allergies:</strong><br>
                                    <div class="bg-warning bg-opacity-10 p-2 rounded border border-warning">
                                        ${patient.allergies}
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${patient.insurance_info ? `
                                <div class="mb-3">
                                    <strong>Insurance Information:</strong><br>
                                    <div class="bg-light p-2 rounded">
                                        ${patient.insurance_info}
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${patient.medical_history ? `
                                <div class="mb-3">
                                    <strong>Medical History:</strong><br>
                                    <div class="bg-light p-2 rounded" style="max-height: 100px; overflow-y: auto;">
                                        ${patient.medical_history}
                                    </div>
                                </div>
                                ` : ''}
                                
                                <hr>
                                
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-card-mini">
                                            <div class="stat-value-mini">${patient.total_appointments || 0}</div>
                                            <div class="stat-label-mini">Total Visits</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-card-mini">
                                            <div class="stat-value-mini">${patient.upcoming_appointments || 0}</div>
                                            <div class="stat-label-mini">Upcoming</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-card-mini">
                                            <div class="stat-value-mini">${patient.completed_appointments || 0}</div>
                                            <div class="stat-label-mini">Completed</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <strong>Last Visit:</strong> ${formatDate(patient.last_appointment_date)}<br>
                                    <strong>Member Since:</strong> ${formatDate(patient.created_at)}
                                </div>
                            </div>
                        `,
                        showCloseButton: true,
                        showConfirmButton: false,
                        width: '600px',
                        customClass: {
                            popup: 'patient-details-modal'
                        }
                    });
                } else {
                    Swal.fire('Error', data.error || 'Failed to load patient details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        function deletePatient(patientId) {
            Swal.fire({
                title: 'Delete Patient?',
                text: 'Are you sure you want to delete this patient? This action cannot be undone and will fail if the patient has existing appointments.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete_patient">
                        <input type="hidden" name="patient_id" value="${patientId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Add badge and styling
        const style = document.createElement('style');
        style.textContent = `
            .badge-secondary { background-color: #6c757d; color: white; }
            .badge-primary { background-color: #007bff; color: white; }
            .badge-info { background-color: #17a2b8; color: white; }
            .badge-warning { background-color: #ffc107; color: #212529; }
            .badge-success { background-color: #28a745; color: white; }
            .badge-danger { background-color: #dc3545; color: white; }
            
            .stat-card-mini {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 10px;
                margin: 5px;
            }
            
            .stat-value-mini {
                font-size: 1.2rem;
                font-weight: bold;
                color: #495057;
            }
            
            .stat-label-mini {
                font-size: 0.75rem;
                color: #6c757d;
                margin-top: 2px;
            }
            
            .patient-details-modal .swal2-html-container {
                text-align: left !important;
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

renderAdminLayout('Patient Management', $pageContent, 'patients');
?>
