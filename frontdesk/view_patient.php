<?php
require_once '../includes/config.php';
require_once 'layout.php';
requireRole('frontdesk');

$frontdesk_id = $_SESSION['user_id'];
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$patient = null;

// Fetch patient details
if ($patient_id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.id = ? AND a.frontdesk_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$patient_id, $frontdesk_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $error = "Patient not found or you don't have permission to view this record.";
    }
}


function renderPageContent() {
    global $error, $patient;
?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 style="margin-bottom: 0.5rem;">Patient Record</h1>
                <p style="margin: 0; color: var(--text-muted);">View detailed patient information</p>
            </div>
            <a href="patients.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($patient): ?>
            <div class="row">
                <!-- Patient Basic Information -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-circle"></i> Basic Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="patient-info">
                                <div class="info-item">
                                    <label>Name:</label>
                                    <span><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Age:</label>
                                    <span><?php echo htmlspecialchars($patient['age']); ?> years old</span>
                                </div>
                                <div class="info-item">
                                    <label>Gender:</label>
                                    <span><?php echo htmlspecialchars(ucfirst($patient['gender'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Date of Birth:</label>
                                    <span><?php echo htmlspecialchars(date('F j, Y', strtotime($patient['date_of_birth']))); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-address-card"></i> Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="patient-info">
                                <div class="info-item">
                                    <label>Email:</label>
                                    <span><?php echo htmlspecialchars($patient['email']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Phone:</label>
                                    <span><?php echo htmlspecialchars($patient['phone']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Address:</label>
                                    <span><?php echo htmlspecialchars($patient['address']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-phone-alt"></i> Emergency Contact
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="patient-info">
                                <div class="info-item">
                                    <label>Name:</label>
                                    <span><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Phone:</label>
                                    <span><?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-notes-medical"></i> Medical Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="patient-info">
                                <div class="info-item">
                                    <label>Medical History:</label>
                                    <div class="mt-2">
                                        <?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?>
                                    </div>
                                </div>
                                <div class="info-item mt-3">
                                    <label>Allergies:</label>
                                    <div class="mt-2">
                                        <?php echo nl2br(htmlspecialchars($patient['allergies'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Insurance Information -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-file-medical"></i> Insurance Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="patient-info">
                                <div class="info-item">
                                    <label>Insurance Info:</label>
                                    <div class="mt-2">
                                        <?php echo nl2br(htmlspecialchars($patient['insurance_info'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .card {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e9f2;
    }
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #e5e9f2;
        padding: 1rem;
    }
    .card-title {
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.1rem;
    }
    .card-title i {
        color: #3498db;
    }
    .patient-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .info-item label {
        font-weight: 600;
        color: #566a7f;
        font-size: 0.875rem;
    }
    .info-item span {
        color: #2c3e50;
    }
    </style>

<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Patient Record', $pageContent, 'patients');
