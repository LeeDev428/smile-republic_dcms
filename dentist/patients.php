<?php
require_once '../includes/config.php';
requireRole('dentist');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

$dentist_id = $_SESSION['user_id'];

// Handle patient search/filter
$search = $_GET['search'] ?? '';
$filter_age_min = $_GET['age_min'] ?? '';
$filter_age_max = $_GET['age_max'] ?? '';

// Build query conditions for patients who have appointments with this dentist
$conditions = ["a.dentist_id = ?"];
$params = [$dentist_id];

if ($search) {
    $conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_age_min) {
    $conditions[] = "p.age >= ?";
    $params[] = intval($filter_age_min);
}

if ($filter_age_max) {
    $conditions[] = "p.age <= ?";
    $params[] = intval($filter_age_max);
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get patients who have appointments with this dentist
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(a.id) as appointment_count,
               MAX(a.appointment_date) as last_appointment_date,
               MIN(a.appointment_date) as first_appointment_date,
               SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
               MAX(CASE WHEN a.status = 'completed' THEN a.notes END) as last_notes
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        $where_clause
        GROUP BY p.id
        ORDER BY MAX(a.appointment_date) DESC
    ");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
    // Get statistics for this dentist
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.patient_id) as total_patients,
            COUNT(a.id) as total_appointments,
            COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
            COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments
        FROM appointments a 
        WHERE a.dentist_id = ?
    ");
    $stmt->execute([$dentist_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error loading patients: " . $e->getMessage();
    $patients = [];
    $stats = ['total_patients' => 0, 'total_appointments' => 0, 'completed_appointments' => 0, 'upcoming_appointments' => 0];
}

function renderPageContent() {
    global $message, $error, $patients, $stats, $search, $filter_age_min, $filter_age_max;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">My Patients</h1>
                    <p style="margin: 0; color: var(--text-muted);">View and manage your patient records</p>
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
                        <div class="stat-label">My Patients</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['completed_appointments']; ?></div>
                        <div class="stat-label">Completed Treatments</div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['total_appointments']; ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['upcoming_appointments']; ?></div>
                        <div class="stat-label">Upcoming</div>
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
                            <p>No patients match your search criteria or you haven't treated any patients yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Age</th>
                                        <th>Contact</th>
                                        <th>First Visit</th>
                                        <th>Last Visit</th>
                                        <th>Appointments</th>
                                        <th>Completed</th>
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
                                                <span class="badge badge-secondary"><?php echo $patient['age'] ?? 'N/A'; ?></span>
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
                                                    <?php echo formatDate($patient['first_appointment_date']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php echo formatDate($patient['last_appointment_date']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600; font-size: 1.2rem;"><?php echo $patient['appointment_count']; ?></div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">total</div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600; color: var(--success-color); font-size: 1.2rem;"><?php echo $patient['completed_appointments']; ?></div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">done</div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="viewTreatmentHistory(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-info" title="Treatment History">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                    <button onclick="addTreatmentNote(<?php echo $patient['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-success" title="Add Note">
                                                        <i class="fas fa-notes-medical"></i>
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

    <!-- Treatment Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Treatment Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="notesForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_note">
                        <input type="hidden" name="patient_id" id="notesPatientId">
                        
                        <div class="mb-3">
                            <label class="form-label">Patient</label>
                            <input type="text" id="notesPatientName" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Treatment Notes</label>
                            <textarea name="notes" class="form-control" rows="5" placeholder="Enter treatment notes, observations, or recommendations..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Next Appointment Recommendation</label>
                            <textarea name="next_appointment" class="form-control" rows="2" placeholder="Recommended follow-up or next steps..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function viewPatientDetails(patientId) {
            // Placeholder for patient details modal
            alert('Patient details view will be implemented here. ID: ' + patientId);
        }

        function viewTreatmentHistory(patientId) {
            // Placeholder for treatment history view
            alert('Treatment history view will be implemented here. ID: ' + patientId);
        }

        function addTreatmentNote(patientId) {
            document.getElementById('notesPatientId').value = patientId;
            // In a real implementation, you would fetch patient name via AJAX
            document.getElementById('notesPatientName').value = 'Patient #' + patientId;
            new bootstrap.Modal(document.getElementById('notesModal')).show();
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

renderDentistLayout('My Patients', $pageContent, 'patients');
?>
