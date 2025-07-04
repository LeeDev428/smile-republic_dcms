<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    $emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = sanitize($_POST['emergency_contact_phone'] ?? '');
    $medical_history = sanitize($_POST['medical_history'] ?? '');
    $allergies = sanitize($_POST['allergies'] ?? '');
    $insurance_info = sanitize($_POST['insurance_info'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($phone)) {
        $error = 'First name, last name, and phone number are required.';
    } else {
        try {
            // Check if patient with same phone already exists
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'A patient with this phone number already exists.';
            } else {
                // Insert new patient
                $stmt = $pdo->prepare("
                    INSERT INTO patients (first_name, last_name, email, phone, date_of_birth, gender, address, 
                                        emergency_contact_name, emergency_contact_phone, medical_history, allergies, insurance_info) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $first_name, $last_name, $email, $phone, $date_of_birth ?: null, 
                    $gender ?: null, $address, $emergency_contact_name, $emergency_contact_phone, 
                    $medical_history, $allergies, $insurance_info
                ]);
                
                $patient_id = $pdo->lastInsertId();
                $message = 'Patient registered successfully! You can now schedule an appointment.';
                
                // Clear form data after successful submission
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = 'Error registering patient: ' . $e->getMessage();
        }
    }
}

function renderPageContent() {
    global $message, $error, $patient_id;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Register New Patient</h1>
                    <p style="margin: 0; color: var(--text-muted);">Add a new patient to the system</p>
                </div>
                <a href="patients.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i>
                    View All Patients
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <?php if (isset($patient_id)): ?>
                        <div style="margin-top: 1rem;">
                            <a href="schedule.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-calendar-plus"></i>
                                Schedule Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Registration Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-plus"></i>
                            Patient Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="patientForm">
                            <!-- Personal Information -->
                            <h4 style="margin-bottom: 1.5rem; color: var(--primary-color); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
                                <i class="fas fa-user"></i>
                                Personal Information
                            </h4>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">
                                        <i class="fas fa-user"></i>
                                        First Name *
                                    </label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name" class="form-label">
                                        <i class="fas fa-user"></i>
                                        Last Name *
                                    </label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        Email Address
                                    </label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone"></i>
                                        Phone Number *
                                    </label>
                                    <input type="tel" id="phone" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="date_of_birth" class="form-label">
                                        <i class="fas fa-birthday-cake"></i>
                                        Date of Birth
                                    </label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender" class="form-label">
                                        <i class="fas fa-venus-mars"></i>
                                        Gender
                                    </label>
                                    <select id="gender" name="gender" class="form-control form-select">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Address
                                </label>
                                <textarea id="address" name="address" class="form-control" rows="3" 
                                          placeholder="Street address, city, state, zip code"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>

                            <!-- Emergency Contact -->
                            <h4 style="margin: 2rem 0 1.5rem; color: var(--warning-color); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
                                <i class="fas fa-phone-alt"></i>
                                Emergency Contact
                            </h4>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="emergency_contact_name" class="form-label">
                                        <i class="fas fa-user-friends"></i>
                                        Contact Name
                                    </label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="emergency_contact_phone" class="form-label">
                                        <i class="fas fa-phone"></i>
                                        Contact Phone
                                    </label>
                                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Medical Information -->
                            <h4 style="margin: 2rem 0 1.5rem; color: var(--danger-color); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
                                <i class="fas fa-heartbeat"></i>
                                Medical Information
                            </h4>

                            <div class="form-group">
                                <label for="medical_history" class="form-label">
                                    <i class="fas fa-file-medical"></i>
                                    Medical History
                                </label>
                                <textarea id="medical_history" name="medical_history" class="form-control" rows="3" 
                                          placeholder="Previous surgeries, chronic conditions, medications, etc."><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="allergies" class="form-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Allergies
                                </label>
                                <textarea id="allergies" name="allergies" class="form-control" rows="2" 
                                          placeholder="List any known allergies (medications, foods, materials, etc.)"><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="insurance_info" class="form-label">
                                    <i class="fas fa-shield-alt"></i>
                                    Insurance Information
                                </label>
                                <textarea id="insurance_info" name="insurance_info" class="form-control" rows="2" 
                                          placeholder="Insurance provider, policy number, group number, etc."><?php echo htmlspecialchars($_POST['insurance_info'] ?? ''); ?></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i>
                                    Register Patient
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Actions & Info -->
                <div>
                    <!-- Registration Tips -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i>
                                Registration Tips
                            </h3>
                        </div>
                        <div class="card-body">
                            <div style="font-size: 0.875rem; line-height: 1.6;">
                                <div style="margin-bottom: 1rem;">
                                    <i class="fas fa-check-circle" style="color: var(--success-color); margin-right: 0.5rem;"></i>
                                    <strong>Required fields</strong> are marked with an asterisk (*)
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <i class="fas fa-phone" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                                    Phone number is used as unique identifier
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <i class="fas fa-heart" style="color: var(--danger-color); margin-right: 0.5rem;"></i>
                                    Medical history and allergies are important for safety
                                </div>
                                <div>
                                    <i class="fas fa-calendar-plus" style="color: var(--warning-color); margin-right: 0.5rem;"></i>
                                    You can schedule appointments immediately after registration
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; gap: 0.75rem;">
                                <a href="patients.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-users"></i>
                                    View All Patients
                                </a>
                                <a href="schedule.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-calendar-plus"></i>
                                    Schedule Appointment
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-tachometer-alt"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Form validation
        document.getElementById('patientForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const phone = document.getElementById('phone').value.trim();

            if (!firstName || !lastName || !phone) {
                e.preventDefault();
                alert('Please fill in all required fields (First Name, Last Name, and Phone Number).');
                return false;
            }

            // Phone number format validation
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number.');
                return false;
            }
        });

        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            e.target.value = value;
        });

        // Emergency contact phone formatting
        document.getElementById('emergency_contact_phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            e.target.value = value;
        });

        // Auto-capitalize names
        document.getElementById('first_name').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        document.getElementById('last_name').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        document.getElementById('emergency_contact_name').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        // Set max date for date of birth (today)
        document.getElementById('date_of_birth').max = new Date().toISOString().split('T')[0];
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Register Patient', $pageContent, 'patient-registration.php');
?>
