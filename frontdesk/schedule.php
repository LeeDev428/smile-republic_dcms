<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Include layout for rendering
require_once 'layout.php';

// AJAX endpoint for getting available time slots
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_available_slots') {
    header('Content-Type: application/json');
    
    $dentist_id = intval($_GET['dentist_id'] ?? 0);
    $appointment_date = $_GET['appointment_date'] ?? '';
    
    if (!$dentist_id || !$appointment_date) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    try {
        // Get booked appointments with their durations for this dentist on this date
        $stmt = $pdo->prepare("
            SELECT a.appointment_time, a.duration_minutes
            FROM appointments a
            WHERE a.dentist_id = ? AND a.appointment_date = ? 
            AND a.status NOT IN ('cancelled', 'no_show')
        ");
        $stmt->execute([$dentist_id, $appointment_date]);
        $booked_appointments = $stmt->fetchAll();
        
        // Generate all possible time slots (8:00 AM to 6:00 PM in 15-minute intervals)
        $all_slots = [];
        $start_hour = 8;
        $end_hour = 18;
        
        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 15) {
                $time_string = sprintf('%02d:%02d:00', $hour, $minute);
                $all_slots[] = $time_string;
            }
        }
        
        // Calculate blocked slots based on appointment duration
        $blocked_slots = [];
        foreach ($booked_appointments as $appointment) {
            $start_time = $appointment['appointment_time'];
            $duration = intval($appointment['duration_minutes']);
            
            // Convert start time to minutes since midnight
            $time_parts = explode(':', $start_time);
            $start_minutes = ($time_parts[0] * 60) + $time_parts[1];
            
            // Calculate how many 15-minute slots this appointment blocks
            $slots_needed = ceil($duration / 15);
            
            // Block consecutive slots
            for ($i = 0; $i < $slots_needed; $i++) {
                $slot_minutes = $start_minutes + ($i * 15);
                $slot_hour = floor($slot_minutes / 60);
                $slot_minute = $slot_minutes % 60;
                
                // Only block slots within business hours
                if ($slot_hour >= 8 && $slot_hour < 18) {
                    $blocked_slot = sprintf('%02d:%02d:00', $slot_hour, $slot_minute);
                    $blocked_slots[] = $blocked_slot;
                }
            }
        }
        
        // Remove blocked slots from available slots
        $available_slots = array_diff($all_slots, $blocked_slots);
        
        echo json_encode(['available_slots' => array_values($available_slots)]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error']);
        exit;
    }
}

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
                // Check if time slot is available considering duration
                $stmt = $pdo->prepare("
                    SELECT a.appointment_time, a.duration_minutes
                    FROM appointments a
                    WHERE a.dentist_id = ? AND a.appointment_date = ? 
                    AND a.status NOT IN ('cancelled', 'no_show')
                ");
                $stmt->execute([$dentist_id, $appointment_date]);
                $existing_appointments = $stmt->fetchAll();
                
                // Convert appointment time to minutes
                $time_parts = explode(':', $appointment_time);
                $new_start_minutes = ($time_parts[0] * 60) + $time_parts[1];
                $new_duration = intval($operation['duration_minutes']);
                $new_end_minutes = $new_start_minutes + $new_duration;
                
                $conflict = false;
                foreach ($existing_appointments as $existing) {
                    $existing_time_parts = explode(':', $existing['appointment_time']);
                    $existing_start_minutes = ($existing_time_parts[0] * 60) + $existing_time_parts[1];
                    $existing_end_minutes = $existing_start_minutes + intval($existing['duration_minutes']);
                    
                    // Check for time overlap
                    if (($new_start_minutes < $existing_end_minutes) && ($new_end_minutes > $existing_start_minutes)) {
                        $conflict = true;
                        break;
                    }
                }
                
                if ($conflict) {
                    $error = 'This time slot conflicts with an existing appointment. Please choose another time.';
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

function renderPageContent() {
    global $message, $error, $patients, $dentists, $operations;
?>
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
                                <label for="patient_search" class="form-label">
                                    <i class="fas fa-user"></i>
                                    Patient *
                                </label>
                                <div style="position: relative;">
                                    <input type="text" id="patient_search" name="patient_search" class="form-control" 
                                           placeholder="Type to search patients..." 
                                           autocomplete="off" 
                                           onkeyup="searchPatients()" 
                                           onfocus="showPatientDropdown()" 
                                           required>
                                    <input type="hidden" id="patient_id" name="patient_id" required>
                                    <div id="patient_dropdown" style="position: absolute; top: 100%; left: 0; right: 0; 
                                                                      background: var(--white); border: 2px solid var(--border-color); 
                                                                      border-top: none; border-radius: 0 0 var(--border-radius) var(--border-radius); 
                                                                      max-height: 200px; overflow-y: auto; z-index: 1000; display: none;">
                                        <!-- Patient options will be populated here -->
                                    </div>
                                </div>
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
        // Patient data for search functionality
        const patients = <?php echo json_encode($patients); ?>;
        
        function searchPatients() {
            const searchTerm = document.getElementById('patient_search').value.toLowerCase();
            const dropdown = document.getElementById('patient_dropdown');
            
            if (searchTerm.length === 0) {
                dropdown.style.display = 'none';
                document.getElementById('patient_id').value = '';
                document.getElementById('patientInfo').innerHTML = '';
                return;
            }
            
            // Filter patients based on search term
            const filteredPatients = patients.filter(patient => {
                const fullName = (patient.first_name + ' ' + patient.last_name).toLowerCase();
                return fullName.includes(searchTerm);
            });
            
            // Show dropdown with filtered results
            if (filteredPatients.length > 0) {
                let dropdownHTML = '';
                filteredPatients.forEach(patient => {
                    const fullName = patient.first_name + ' ' + patient.last_name;
                    const phone = patient.phone ? ` - ${patient.phone}` : '';
                    dropdownHTML += `
                        <div class="patient-option" 
                             onclick="selectPatient(${patient.id}, '${fullName.replace(/'/g, "\\'")}', '${patient.phone || ''}')"
                             style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid var(--gray-200); 
                                    transition: var(--transition);"
                             onmouseover="this.style.backgroundColor='var(--gray-100)'"
                             onmouseout="this.style.backgroundColor='var(--white)'">
                            <div style="font-weight: 500;">${fullName}</div>
                            ${phone ? `<small style="color: var(--text-muted);">${patient.phone}</small>` : ''}
                        </div>
                    `;
                });
                
                dropdown.innerHTML = dropdownHTML;
                dropdown.style.display = 'block';
            } else {
                dropdown.innerHTML = `
                    <div style="padding: 1rem; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-search"></i> No patients found
                        <br><small>Try a different search term</small>
                    </div>
                `;
                dropdown.style.display = 'block';
            }
        }
        
        function showPatientDropdown() {
            const searchTerm = document.getElementById('patient_search').value;
            if (searchTerm.length === 0) {
                // Show all patients when focused
                const dropdown = document.getElementById('patient_dropdown');
                let dropdownHTML = '';
                patients.forEach(patient => {
                    const fullName = patient.first_name + ' ' + patient.last_name;
                    const phone = patient.phone ? ` - ${patient.phone}` : '';
                    dropdownHTML += `
                        <div class="patient-option" 
                             onclick="selectPatient(${patient.id}, '${fullName.replace(/'/g, "\\'")}', '${patient.phone || ''}')"
                             style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid var(--gray-200); 
                                    transition: var(--transition);"
                             onmouseover="this.style.backgroundColor='var(--gray-100)'"
                             onmouseout="this.style.backgroundColor='var(--white)'">
                            <div style="font-weight: 500;">${fullName}</div>
                            ${phone ? `<small style="color: var(--text-muted);">${patient.phone}</small>` : ''}
                        </div>
                    `;
                });
                
                dropdown.innerHTML = dropdownHTML;
                dropdown.style.display = 'block';
            }
        }
        
        function selectPatient(patientId, patientName, patientPhone) {
            document.getElementById('patient_search').value = patientName;
            document.getElementById('patient_id').value = patientId;
            document.getElementById('patient_dropdown').style.display = 'none';
            
            // Show patient info
            const info = document.getElementById('patientInfo');
            if (patientPhone) {
                info.innerHTML = `<i class="fas fa-phone"></i> ${patientPhone}`;
            } else {
                info.innerHTML = '<i class="fas fa-check"></i> Patient selected';
            }
            
            // Update field styling to show it's valid
            document.getElementById('patient_search').style.borderColor = 'var(--success-color)';
        }
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const searchField = document.getElementById('patient_search');
            const dropdown = document.getElementById('patient_dropdown');
            
            if (!searchField.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Reset patient selection when input changes manually
        document.getElementById('patient_search').addEventListener('input', function() {
            if (this.value !== document.querySelector('#patient_dropdown .patient-option')?.textContent?.trim()) {
                document.getElementById('patient_id').value = '';
                document.getElementById('patientInfo').innerHTML = '';
                this.style.borderColor = 'var(--border-color)';
            }
        });

        function updateOperationInfo() {
            const select = document.getElementById('operation_id');
            const info = document.getElementById('operationInfo');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                const duration = selectedOption.getAttribute('data-duration');
                info.innerHTML = `<i class="fas fa-peso-sign"></i> ₱${price} | <i class="fas fa-clock"></i> ${duration} minutes`;
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

            // Show loading
            preview.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>Loading available time slots...</p>
                </div>
            `;

            // Fetch available slots from server
            fetch(`schedule.php?ajax=get_available_slots&dentist_id=${dentistId}&appointment_date=${appointmentDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        preview.innerHTML = `
                            <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>Error loading time slots</p>
                            </div>
                        `;
                        return;
                    }
                    
                    const availableSlots = data.available_slots;
                    
                    // Update time select
                    timeSelect.innerHTML = '<option value="">Select Time</option>';
                    availableSlots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot;
                        option.textContent = formatTime(slot);
                        timeSelect.appendChild(option);
                    });

                    // Update preview
                    if (availableSlots.length === 0) {
                        preview.innerHTML = `
                            <div style="text-align: center; padding: 2rem; color: var(--warning-color);">
                                <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>No available time slots for this date</p>
                                <small>All slots are booked for this dentist</small>
                            </div>
                        `;
                    } else {
                        let previewHTML = `
                            <div style="margin-bottom: 1rem;">
                                <strong>Available slots for ${appointmentDate}</strong>
                                <small style="display: block; color: var(--text-muted);">${availableSlots.length} slots available</small>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.5rem;">
                        `;
                        
                        availableSlots.forEach(slot => {
                            previewHTML += `
                                <button type="button" class="time-slot-btn" onclick="selectTimeSlot('${slot}')"
                                        style="padding: 0.75rem 0.5rem; border: 2px solid var(--border-color); border-radius: var(--border-radius); 
                                               background: var(--white); cursor: pointer; font-size: 0.875rem; transition: var(--transition);
                                               font-weight: 500;">
                                    ${formatTime(slot)}
                                </button>
                            `;
                        });
                        
                        previewHTML += '</div>';
                        preview.innerHTML = previewHTML;
                    }
                })
                .catch(error => {
                    console.error('Error fetching available slots:', error);
                    preview.innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <p>Error loading time slots</p>
                        </div>
                    `;
                });
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

        function formatTime(timeString) {
            const [hour, minute] = timeString.split(':').map(Number);
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
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Schedule Appointment', $pageContent, 'schedule.php');
?>
