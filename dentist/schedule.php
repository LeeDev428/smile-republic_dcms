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
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        try {
            // Check if availability record exists
            $stmt = $pdo->prepare("SELECT id FROM dentist_availability WHERE dentist_id = ? AND day_of_week = ?");
            $stmt->execute([$dentist_id, $day_of_week]);
            
            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE dentist_availability 
                    SET start_time = ?, end_time = ?, is_available = ?, updated_at = NOW()
                    WHERE dentist_id = ? AND day_of_week = ?
                ");
                $stmt->execute([$start_time, $end_time, $is_available, $dentist_id, $day_of_week]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("
                    INSERT INTO dentist_availability (dentist_id, day_of_week, start_time, end_time, is_available, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$dentist_id, $day_of_week, $start_time, $end_time, $is_available]);
            }
            
            $message = "Availability updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating availability: " . $e->getMessage();
        }
    } elseif ($action === 'add_time_off') {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = sanitize($_POST['reason']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO dentist_time_off (dentist_id, start_date, end_date, reason, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$dentist_id, $start_date, $end_date, $reason]);
            $message = "Time off request added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding time off: " . $e->getMessage();
        }
    }
}

try {
    // Get current availability settings
    $stmt = $pdo->prepare("
        SELECT day_of_week, start_time, end_time, is_available 
        FROM dentist_availability 
        WHERE dentist_id = ?
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ");
    $stmt->execute([$dentist_id]);
    $availability = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get today's schedule
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               do.name as operation_name, do.duration_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        WHERE a.dentist_id = ? AND a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$dentist_id]);
    $today_schedule = $stmt->fetchAll();
    
    // Get upcoming appointments (next 7 days)
    $stmt = $pdo->prepare("
        SELECT a.appointment_date, COUNT(*) as appointment_count
        FROM appointments a
        WHERE a.dentist_id = ? 
        AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        GROUP BY a.appointment_date
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$dentist_id]);
    $upcoming_days = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get time off requests
    $stmt = $pdo->prepare("
        SELECT * FROM dentist_time_off 
        WHERE dentist_id = ? AND end_date >= CURDATE()
        ORDER BY start_date ASC
    ");
    $stmt->execute([$dentist_id]);
    $time_off = $stmt->fetchAll();
    
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
    $time_off = [];
    $stats = ['today_appointments' => 0, 'week_appointments' => 0, 'month_appointments' => 0];
}

// Days of the week
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function renderPageContent() {
    global $message, $error, $availability, $today_schedule, $upcoming_days, $time_off, $stats, $days_of_week;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">My Schedule</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage your availability and view upcoming appointments</p>
                </div>
                <div>
                    <button onclick="showTimeOffModal()" class="btn btn-warning">
                        <i class="fas fa-calendar-times"></i>
                        Request Time Off
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
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count($time_off); ?></div>
                        <div class="stat-label">Time Off Requests</div>
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
                                                <?php
                                                $status_class = match($appointment['status']) {
                                                    'scheduled' => 'secondary',
                                                    'confirmed' => 'primary',
                                                    'checked_in' => 'warning',
                                                    'in_progress' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
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
                                Next 7 Days
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_days)): ?>
                                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No appointments in the next 7 days.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <?php
                                        $date = date('Y-m-d', strtotime("+$i days"));
                                        $day_name = date('l', strtotime($date));
                                        $appointment_count = $upcoming_days[$date] ?? 0;
                                        ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div style="font-weight: 600;">
                                                    <?php echo $day_name; ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo formatDate($date); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="badge badge-<?php echo $appointment_count > 0 ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $appointment_count; ?> appointments
                                                </span>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
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
                            $day_availability = null;
                            foreach ($availability as $avail) {
                                if ($avail['day_of_week'] === $day) {
                                    $day_availability = $avail;
                                    break;
                                }
                            }
                            ?>
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-2">
                                    <label class="form-label" style="font-weight: 600;">
                                        <?php echo $day; ?>
                                    </label>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="is_available_<?php echo strtolower($day); ?>" 
                                               id="available_<?php echo strtolower($day); ?>"
                                               <?php echo ($day_availability['is_available'] ?? 0) ? 'checked' : ''; ?>
                                               onchange="toggleDayAvailability('<?php echo strtolower($day); ?>')">
                                        <label class="form-check-label" for="available_<?php echo strtolower($day); ?>">
                                            Available
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="time" name="start_time_<?php echo strtolower($day); ?>" 
                                           class="form-control" 
                                           value="<?php echo $day_availability['start_time'] ?? '09:00'; ?>"
                                           <?php echo !($day_availability['is_available'] ?? 0) ? 'disabled' : ''; ?>>
                                </div>
                                <div class="col-md-1 text-center">
                                    <span>to</span>
                                </div>
                                <div class="col-md-3">
                                    <input type="time" name="end_time_<?php echo strtolower($day); ?>" 
                                           class="form-control" 
                                           value="<?php echo $day_availability['end_time'] ?? '17:00'; ?>"
                                           <?php echo !($day_availability['is_available'] ?? 0) ? 'disabled' : ''; ?>>
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

            <!-- Time Off Requests -->
            <?php if (!empty($time_off)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 style="margin: 0;">
                            <i class="fas fa-calendar-times"></i>
                            Upcoming Time Off
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Reason</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($time_off as $request): ?>
                                        <tr>
                                            <td><?php echo formatDate($request['start_date']); ?></td>
                                            <td><?php echo formatDate($request['end_date']); ?></td>
                                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                            <td>
                                                <?php
                                                $start = new DateTime($request['start_date']);
                                                $end = new DateTime($request['end_date']);
                                                $days = $start->diff($end)->days + 1;
                                                echo $days . ' day' . ($days > 1 ? 's' : '');
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

    <!-- Time Off Modal -->
    <div class="modal fade" id="timeOffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Time Off</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="timeOffForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_time_off">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Vacation, training, personal, etc." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Submit Request</button>
                    </div>
                </form>
            </div>
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

        function showTimeOffModal() {
            new bootstrap.Modal(document.getElementById('timeOffModal')).show();
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
