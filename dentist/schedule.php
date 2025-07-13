<?php
require_once '../includes/config.php';
requireRole('dentist');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

$dentist_id = $_SESSION['user_id'];

// Handle schedule updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_availability') {
        $day = strtolower($_POST['day_of_week']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        try {
            // Check if availability record exists
            $stmt = $pdo->prepare("SELECT id FROM dentist_availability WHERE dentist_id = ?");
            $stmt->execute([$dentist_id]);
            
            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE dentist_availability 
                    SET {$day}_start = ?, {$day}_end = ?, {$day}_available = ?, updated_at = NOW()
                    WHERE dentist_id = ?
                ");
                $stmt->execute([$start_time, $end_time, $is_available, $dentist_id]);
            } else {
                // Insert new record with default values
                $stmt = $pdo->prepare("
                    INSERT INTO dentist_availability (
                        dentist_id,
                        monday_start, monday_end, monday_available,
                        tuesday_start, tuesday_end, tuesday_available,
                        wednesday_start, wednesday_end, wednesday_available,
                        thursday_start, thursday_end, thursday_available,
                        friday_start, friday_end, friday_available,
                        saturday_start, saturday_end, saturday_available,
                        sunday_start, sunday_end, sunday_available
                    ) VALUES (
                        ?,
                        '09:00', '17:00', 1,
                        '09:00', '17:00', 1,
                        '09:00', '17:00', 1,
                        '09:00', '17:00', 1,
                        '09:00', '17:00', 1,
                        '09:00', '17:00', 0,
                        '09:00', '17:00', 0
                    )
                ");
                $stmt->execute([$dentist_id]);
                
                // Then update the specific day
                $stmt = $pdo->prepare("
                    UPDATE dentist_availability 
                    SET {$day}_start = ?, {$day}_end = ?, {$day}_available = ?
                    WHERE dentist_id = ?
                ");
                $stmt->execute([$start_time, $end_time, $is_available, $dentist_id]);
            }
            
            $message = "Availability updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating availability: " . $e->getMessage();
        }
    }
}

try {
    // Get current availability settings
    $stmt = $pdo->prepare("
        SELECT * FROM dentist_availability 
        WHERE dentist_id = ?
    ");
    $stmt->execute([$dentist_id]);
    $availability = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$availability) {
        // If no availability record exists, create one with default values
        $stmt = $pdo->prepare("
            INSERT INTO dentist_availability (
                dentist_id,
                monday_start, monday_end, monday_available,
                tuesday_start, tuesday_end, tuesday_available,
                wednesday_start, wednesday_end, wednesday_available,
                thursday_start, thursday_end, thursday_available,
                friday_start, friday_end, friday_available,
                saturday_start, saturday_end, saturday_available,
                sunday_start, sunday_end, sunday_available
            ) VALUES (
                ?,
                '09:00', '17:00', 1,
                '09:00', '17:00', 1,
                '09:00', '17:00', 1,
                '09:00', '17:00', 1,
                '09:00', '17:00', 1,
                '09:00', '17:00', 0,
                '09:00', '17:00', 0
            )
        ");
        $stmt->execute([$dentist_id]);
        
        // Fetch the newly created availability
        $stmt = $pdo->prepare("SELECT * FROM dentist_availability WHERE dentist_id = ?");
        $stmt->execute([$dentist_id]);
        $availability = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get today's schedule with full details
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.duration_minutes,
            a.status,
            a.notes,
            p.first_name as patient_first, 
            p.last_name as patient_last,
            do.name as operation_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.dentist_id = ? 
        AND DATE(a.appointment_date) = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$dentist_id]);
    $today_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get this week's appointments (Monday to Sunday)
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.duration_minutes,
            a.status,
            a.notes,
            p.first_name as patient_first, 
            p.last_name as patient_last,
            do.name as operation_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.dentist_id = ? 
        AND WEEK(a.appointment_date) = WEEK(CURDATE())
        AND YEAR(a.appointment_date) = YEAR(CURDATE())
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$dentist_id]);
    $upcoming_days = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN appointment_date = CURDATE() THEN 1 END) as today_appointments,
            COUNT(CASE WHEN appointment_date > CURDATE() AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_appointments,
            COUNT(CASE WHEN appointment_date > CURDATE() AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_appointments
        FROM appointments 
        WHERE dentist_id = ?
    ");
    $stmt->execute([$dentist_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error loading schedule data: " . $e->getMessage();
    $availability = [];
    $today_schedule = [];
    $upcoming_days = [];
    $stats = ['today_appointments' => 0, 'week_appointments' => 0, 'month_appointments' => 0];
}

// Days of the week
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function renderPageContent() {
    global $message, $error, $availability, $today_schedule, $upcoming_days, $stats, $days_of_week;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">My Schedule</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage your availability and view upcoming appointments</p>
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
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['today_appointments']; ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['week_appointments']; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['month_appointments']; ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>
                

            </div>

            <div class="row">
                <!-- Today's Schedule -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 style="margin: 0;">
                                <i class="fas fa-calendar-day"></i>
                                Today's Schedule - <?php echo date('F j, Y'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($today_schedule)): ?>
                                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No appointments scheduled for today.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($today_schedule as $appointment): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div style="font-weight: 600;">
                                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                                </div>
                                                <div>
                                                    <?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo htmlspecialchars($appointment['operation_name']); ?> (<?php echo $appointment['duration_minutes']; ?> min)
                                                </div>
                                            </div>
                                            <div>
                                                <span style="color: var(--text-muted); font-size: 0.95em;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Weekly Overview -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 style="margin: 0;">
                                <i class="fas fa-calendar-week"></i>
                                This Week's Schedule
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Group appointments by date
                            $week_appointments = [];
                            foreach ($upcoming_days as $appt) {
                                $date = $appt['appointment_date'];
                                if (!isset($week_appointments[$date])) {
                                    $week_appointments[$date] = [];
                                }
                                $week_appointments[$date][] = $appt;
                            }
                            
                            // Get Monday of current week
                            $monday = date('Y-m-d', strtotime('monday this week'));
                            ?>
                            
                            <div class="list-group list-group-flush">
                                <?php for ($i = 0; $i < 7; $i++): 
                                    $date = date('Y-m-d', strtotime($monday . " +$i days"));
                                    $day_name = date('l', strtotime($date));
                                    $day_appointments = $week_appointments[$date] ?? [];
                                ?>
                                    <div style="padding: 0.75rem 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div style="font-weight: 600;">
                                                    <?php echo $day_name; ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo date('F j, Y', strtotime($date)); ?>
                                                </div>
                                            </div>                            <span style="color: var(--text-muted); font-size: 0.95em;">
                                <?php echo !empty($day_appointments) ? count($day_appointments) . ' appointments' : 'No appointments'; ?>
                            </span>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Availability Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-clock"></i>
                        Weekly Availability
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="availabilityForm">
                        <input type="hidden" name="action" value="update_availability">
                        
                        <?php foreach ($days_of_week as $day): ?>
                            <?php
                                            $day_lower = strtolower($day);
                            $is_available = $availability["{$day_lower}_available"] ?? 0;
                            $start_time = $availability["{$day_lower}_start"] ?? '09:00';
                            $end_time = $availability["{$day_lower}_end"] ?? '17:00';
                            ?>
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-2">
                                    <label class="form-label" style="font-weight: 600;">
                                        <?php echo $day; ?>
                                    </label>
                                </div>
                                <div class="col-md-2">appointments
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="is_available_<?php echo $day_lower; ?>" 
                                               id="available_<?php echo $day_lower; ?>"
                                               <?php echo $is_available ? 'checked' : ''; ?>
                                               onchange="toggleDayAvailability('<?php echo $day_lower; ?>')">
                                        <label class="form-check-label" for="available_<?php echo strtolower($day); ?>">
                                            Available
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="time" name="start_time_<?php echo strtolower($day); ?>" 
                                           class="form-control" 
                                           value="<?php echo $start_time; ?>"
                                           <?php echo !$is_available ? 'disabled' : ''; ?>>
                                </div>
                                <div class="col-md-1 text-center">
                                    <span>to</span>
                                </div>
                                <div class="col-md-3">
                                    <input type="time" name="end_time_<?php echo strtolower($day); ?>" 
                                           class="form-control" 
                                           value="<?php echo $end_time; ?>"
                                           <?php echo !$is_available ? 'disabled' : ''; ?>>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="updateDayAvailability('<?php echo $day; ?>')">
                                        Save
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </form>
                </div>
            </div>



    <script>
        function toggleDayAvailability(day) {
            const checkbox = document.getElementById('available_' + day);
            const startTime = document.querySelector(`input[name="start_time_${day}"]`);
            const endTime = document.querySelector(`input[name="end_time_${day}"]`);
            
            if (checkbox.checked) {
                startTime.disabled = false;
                endTime.disabled = false;
            } else {
                startTime.disabled = true;
                endTime.disabled = true;
            }
        }

        function updateDayAvailability(day) {
            const dayLower = day.toLowerCase();
            const checkbox = document.querySelector(`input[name="is_available_${dayLower}"]`);
            const startTime = document.querySelector(`input[name="start_time_${dayLower}"]`);
            const endTime = document.querySelector(`input[name="end_time_${dayLower}"]`);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_availability">
                <input type="hidden" name="day_of_week" value="${day}">
                <input type="hidden" name="start_time" value="${startTime.value}">
                <input type="hidden" name="end_time" value="${endTime.value}">
                ${checkbox.checked ? '<input type="hidden" name="is_available" value="1">' : ''}
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-primary { background-color: var(--primary-color); }
            .badge-secondary { background-color: var(--gray-500); }
            .badge-success { background-color: var(--success-color); }
            .badge-warning { background-color: var(--warning-color); }
            .badge-info { background-color: var(--info-color); }
            .badge-danger { background-color: var(--danger-color); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderDentistLayout('My Schedule', $pageContent, 'schedule');
?>
