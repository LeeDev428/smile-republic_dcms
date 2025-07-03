<?php
require_once '../includes/config.php';
requireRole('frontdesk');

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    $patient_id = intval($_POST['patient_id']);
    $dentist_id = intval($_POST['dentist_id']);
    $operation_id = intval($_POST['operation_id']);
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($patient_id) || empty($dentist_id) || empty($operation_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = 'All fields are required.';
    } else {
        try {
            // Get operation details
            $stmt = $pdo->prepare("SELECT price, duration_minutes FROM dental_operations WHERE id = ? AND status = 'active'");
            $stmt->execute([$operation_id]);
            $operation = $stmt->fetch();
            
            if (!$operation) {
                $error = 'Invalid operation selected.';
            } else {
                // Check if time slot is available
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM appointments 
                    WHERE dentist_id = ? AND appointment_date = ? 
                    AND appointment_time = ? 
                    AND status NOT IN ('cancelled', 'no_show')
                ");
                $stmt->execute([$dentist_id, $appointment_date, $appointment_time]);
                $existing = $stmt->fetch();
                
                if ($existing['count'] > 0) {
                    $error = 'This time slot is already booked. Please choose another time.';
                } else {
                    // Create appointment
                    $stmt = $pdo->prepare("
                        INSERT INTO appointments (patient_id, dentist_id, operation_id, frontdesk_id, appointment_date, appointment_time, duration_minutes, notes, total_cost) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $patient_id, 
                        $dentist_id, 
                        $operation_id, 
                        $_SESSION['user_id'], 
                        $appointment_date, 
                        $appointment_time, 
                        $operation['duration_minutes'], 
                        $notes, 
                        $operation['price']
                    ]);
                    
                    $message = 'Appointment scheduled successfully!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error scheduling appointment: ' . $e->getMessage();
        }
    }
}

// Get patients
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, phone FROM patients ORDER BY first_name, last_name");
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
}

// Get active dentists
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'dentist' AND status = 'active' ORDER BY first_name, last_name");
    $dentists = $stmt->fetchAll();
} catch (PDOException $e) {
    $dentists = [];
}

// Get active operations
try {
    $stmt = $pdo->query("SELECT id, name, price, duration_minutes, category FROM dental_operations WHERE status = 'active' ORDER BY category, name");
    $operations = $stmt->fetchAll();
} catch (PDOException $e) {
    $operations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - Smile Republic Dental Clinic</title>
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
                    <div style="font-weight: 600; color: var(--warning-color); font-size: 0.875rem;">Front Desk</div>
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
                <a href="appointments.php">
                    <i class="fas fa-calendar-alt"></i>
                    Appointments
                </a>
                <a href="schedule.php" class="active">
                    <i class="fas fa-calendar-check"></i>
                    Schedule Appointment
                </a>
                <a href="patients.php">
                    <i class="fas fa-users"></i>
                    Patients
                </a>
                <a href="patient-registration.php">
                    <i class="fas fa-user-plus"></i>
                    Register Patient
                </a>
                <a href="check-in.php">
                    <i class="fas fa-check-circle"></i>
                    Patient Check-in
                </a>
                <a href="payments.php">
                    <i class="fas fa-credit-card"></i>
                    Payments
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
                    <h1 style="margin-bottom: 0.5rem;">Schedule Appointment</h1>
                    <p style="margin: 0; color: var(--text-muted);">Book a new appointment for a patient</p>
                </div>
                <a href="patient-registration.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i>
                    Register New Patient
                </a>
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

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Appointment Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-plus"></i>
                            New Appointment
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="appointmentForm">
                            <div class="form-group">
                                <label for="patient_id" class="form-label">
                                    <i class="fas fa-user"></i>
                                    Patient *
                                </label>
                                <select id="patient_id" name="patient_id" class="form-control form-select" required onchange="loadPatientInfo()">
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" data-phone="<?php echo htmlspecialchars($patient['phone']); ?>">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="patientInfo" style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-muted);"></div>
                            </div>

                            <div class="form-group">
                                <label for="dentist_id" class="form-label">
                                    <i class="fas fa-user-md"></i>
                                    Dentist *
                                </label>
                                <select id="dentist_id" name="dentist_id" class="form-control form-select" required onchange="updateAvailableSlots()">
                                    <option value="">Select Dentist</option>
                                    <?php foreach ($dentists as $dentist): ?>
                                        <option value="<?php echo $dentist['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="operation_id" class="form-label">
                                    <i class="fas fa-teeth"></i>
                                    Treatment/Operation *
                                </label>
                                <select id="operation_id" name="operation_id" class="form-control form-select" required onchange="updateOperationInfo()">
                                    <option value="">Select Treatment</option>
                                    <?php 
                                    $current_category = '';
                                    foreach ($operations as $operation): 
                                        if ($current_category !== $operation['category']):
                                            if ($current_category !== '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($operation['category']) . '">';
                                            $current_category = $operation['category'];
                                        endif;
                                    ?>
                                        <option value="<?php echo $operation['id']; ?>" 
                                                data-price="<?php echo $operation['price']; ?>" 
                                                data-duration="<?php echo $operation['duration_minutes']; ?>">
                                            <?php echo htmlspecialchars($operation['name']); ?> - <?php echo formatCurrency($operation['price']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($current_category !== '') echo '</optgroup>'; ?>
                                </select>
                                <div id="operationInfo" style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-muted);"></div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="appointment_date" class="form-label">
                                        <i class="fas fa-calendar"></i>
                                        Date *
                                    </label>
                                    <input type="date" id="appointment_date" name="appointment_date" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required onchange="updateAvailableSlots()">
                                </div>

                                <div class="form-group">
                                    <label for="appointment_time" class="form-label">
                                        <i class="fas fa-clock"></i>
                                        Time *
                                    </label>
                                    <select id="appointment_time" name="appointment_time" class="form-control form-select" required>
                                        <option value="">Select Time</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note"></i>
                                    Notes
                                </label>
                                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Any special instructions or notes..."></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i>
                                    Schedule Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Available Time Slots Preview -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clock"></i>
                            Available Time Slots
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="timeSlotsPreview">
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                <i class="fas fa-calendar-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>Select a dentist and date to view available time slots</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="patient-registration.php" class="btn btn-success">
                            <i class="fas fa-user-plus"></i>
                            Register New Patient
                        </a>
                        <a href="appointments.php" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i>
                            View All Appointments
                        </a>
                        <a href="patients.php" class="btn btn-warning">
                            <i class="fas fa-users"></i>
                            View Patients
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-tachometer-alt"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function loadPatientInfo() {
            const select = document.getElementById('patient_id');
            const info = document.getElementById('patientInfo');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const phone = selectedOption.getAttribute('data-phone');
                info.innerHTML = `<i class="fas fa-phone"></i> ${phone}`;
            } else {
                info.innerHTML = '';
            }
        }

        function updateOperationInfo() {
            const select = document.getElementById('operation_id');
            const info = document.getElementById('operationInfo');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                const duration = selectedOption.getAttribute('data-duration');
                info.innerHTML = `<i class="fas fa-dollar-sign"></i> $${price} | <i class="fas fa-clock"></i> ${duration} minutes`;
            } else {
                info.innerHTML = '';
            }
        }

        function updateAvailableSlots() {
            const dentistId = document.getElementById('dentist_id').value;
            const appointmentDate = document.getElementById('appointment_date').value;
            const timeSelect = document.getElementById('appointment_time');
            const preview = document.getElementById('timeSlotsPreview');
            
            if (!dentistId || !appointmentDate) {
                timeSelect.innerHTML = '<option value="">Select Time</option>';
                preview.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-calendar-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Select a dentist and date to view available time slots</p>
                    </div>
                `;
                return;
            }

            // Generate time slots (8:00 AM to 6:00 PM in 15-minute intervals)
            const slots = [];
            const startHour = 8;
            const endHour = 18;
            
            for (let hour = startHour; hour < endHour; hour++) {
                for (let minute = 0; minute < 60; minute += 15) {
                    const timeString = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                    const displayTime = formatTime12Hour(hour, minute);
                    slots.push({ value: timeString, display: displayTime });
                }
            }

            // Update time select
            timeSelect.innerHTML = '<option value="">Select Time</option>';
            slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.display;
                timeSelect.appendChild(option);
            });

            // Update preview
            let previewHTML = `
                <div style="margin-bottom: 1rem;">
                    <strong>Available slots for ${appointmentDate}</strong>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.5rem;">
            `;
            
            slots.forEach(slot => {
                previewHTML += `
                    <button type="button" class="time-slot-btn" onclick="selectTimeSlot('${slot.value}')"
                            style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); 
                                   background: var(--white); cursor: pointer; font-size: 0.875rem; transition: var(--transition);">
                        ${slot.display}
                    </button>
                `;
            });
            
            previewHTML += '</div>';
            preview.innerHTML = previewHTML;
        }

        function selectTimeSlot(time) {
            document.getElementById('appointment_time').value = time;
            
            // Highlight selected slot
            document.querySelectorAll('.time-slot-btn').forEach(btn => {
                btn.style.background = 'var(--white)';
                btn.style.borderColor = 'var(--border-color)';
                btn.style.color = 'var(--text-color)';
            });
            
            event.target.style.background = 'var(--primary-color)';
            event.target.style.borderColor = 'var(--primary-color)';
            event.target.style.color = 'var(--white)';
        }

        function formatTime12Hour(hour, minute) {
            const period = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
            return `${displayHour}:${String(minute).padStart(2, '0')} ${period}`;
        }

        // Form validation
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            const requiredFields = ['patient_id', 'dentist_id', 'operation_id', 'appointment_date', 'appointment_time'];
            let hasError = false;

            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    hasError = true;
                    field.style.borderColor = 'var(--danger-color)';
                } else {
                    field.style.borderColor = 'var(--border-color)';
                }
            });

            if (hasError) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });

        // Set minimum date to today
        document.getElementById('appointment_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
