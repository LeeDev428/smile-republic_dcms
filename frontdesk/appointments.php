<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Handle AJAX request for appointment details
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($appointment) {
            echo json_encode(['success' => true, 'appointment' => $appointment]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle status updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = $_POST['status'];
        
        if (in_array($new_status, ['confirmed', 'cancelled', 'rescheduled'])) {
            try {
                $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $appointment_id]);
                $message = "Appointment status updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating appointment status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_payment_status') {
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = $_POST['payment_status'];
        
        if (in_array($new_status, ['paid', 'partial', 'pending'])) {
            try {
                $stmt = $pdo->prepare("UPDATE appointments SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $appointment_id]);
                $message = "Payment status updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating payment status: " . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_payment = $_GET['payment_status'] ?? '';
$search = $_GET['search'] ?? '';

// Get counts for each status
try {
    $upcoming_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()")->fetchColumn();
    $in_progress_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'in_progress' AND appointment_date >= CURDATE()")->fetchColumn();
    $completed_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND appointment_date >= CURDATE()")->fetchColumn();
    $cancelled_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled' AND appointment_date >= CURDATE()")->fetchColumn();
} catch (PDOException $e) {
    $upcoming_count = $in_progress_count = $completed_count = $cancelled_count = 0;
    $error = "Error fetching status counts: " . $e->getMessage();
}

// Build query conditions
$conditions = ["a.appointment_date >= CURDATE()"];
$params = [];

if ($filter_status) {
    $conditions[] = "a.status = ?";
    $params[] = $filter_status;
}

if ($filter_date) {
    $conditions[] = "a.appointment_date = ?";
    $params[] = $filter_date;
}

if ($search) {
    $conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

try {
    // Get appointments with filters
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last, 
               p.phone as patient_phone, p.email as patient_email,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name, do.duration_minutes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        $where_clause
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Get today's appointments count
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
    $today_count = $stmt->fetchColumn();
    
    // Get upcoming appointments count
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date > CURDATE()");
    $upcoming_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Error loading appointments: " . $e->getMessage();
    $appointments = [];
    $today_count = 0;
    $upcoming_count = 0;
}

function renderPageContent() {
    global $message, $error, $appointments, $upcoming_count, $in_progress_count, $completed_count, $cancelled_count, $filter_status, $filter_date, $search;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Appointments</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage patient appointments and scheduling</p>
                </div>
                <div>
                    <a href="schedule.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        New Appointment
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
            <div class="stats-container mb-4">
                <div class="stat-card upcoming">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $upcoming_count; ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                </div>
                
                <div class="stat-card in-progress">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $in_progress_count; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                </div>
                
                <div class="stat-card completed">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $completed_count; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>

                <div class="stat-card cancelled">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $cancelled_count; ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section mb-4">
                <div class="filter-header">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        <span>Filter Appointments</span>
                    </div>
                    <button type="button" class="btn-clear-filter" onclick="clearFilters()">
                        <i class="fas fa-times"></i>
                        <span>Clear Filters</span>
                    </button>
                </div>
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div class="filter-group search-group">
                        <label class="filter-label">Search</label>
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" class="form-control" placeholder="Search patient name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="filter-group" style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary w-100">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-list"></i>
                        Appointments (<?php echo count($appointments); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h5>No appointments found</h5>
                            <p>No appointments match your current filters.</p>
                            <a href="schedule.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Schedule New Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="appointments-table-wrapper">
                            <table class="table table-hover appointments-table">
                                <thead>
                                    <tr>
                                        <th width="12%">Date & Time</th>
                                        <th width="15%">Patient</th>
                                        <th width="15%">Contact</th>
                                        <th width="15%">Dentist</th>
                                        <th width="15%">Service</th>
                                        <th width="8%">Duration</th>
                                        <th width="10%">Status</th>
                                        <th width="10%">Payment</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo formatDate($appointment['appointment_date']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);"><?php echo formatTime($appointment['appointment_time']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if ($appointment['patient_phone']): ?>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['patient_email']): ?>
                                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['patient_email']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['operation_name']); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo $appointment['duration_minutes']; ?> min</span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = match($appointment['status']) {
                                                    'scheduled' => 'primary',
                                                    'confirmed' => 'success',
                                                    'in_progress' => 'warning',
                                                    'completed' => 'info',
                                                    'cancelled' => 'danger',
                                                    'no_show' => 'dark',
                                                    default => 'secondary'
                                                };
                                                $payment_class = match($appointment['payment_status']) {
                                                    'paid' => 'success',
                                                    'partial' => 'warning',
                                                    'pending' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <!-- Only show the select dropdown for payment status -->
                                                <div class="payment-select-wrapper">
                                                    <select class="form-select payment-select" onchange="updatePaymentStatus(<?php echo $appointment['id']; ?>, this.value)">
                                                        <option value="pending" <?php echo ($appointment['payment_status'] === 'pending' || !$appointment['payment_status']) ? 'selected' : ''; ?>>Pending  </option>
                                                        <option value="paid" <?php echo ($appointment['payment_status'] === 'paid') ? 'selected' : ''; ?>>Paid  </option>
                                                        <option value="partial" <?php echo ($appointment['payment_status'] === 'partial') ? 'selected' : ''; ?>>Partial  </option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                                        <button onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')" 
                                                                class="btn btn-sm btn-success" title="Confirm">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($appointment['status'], ['scheduled', 'confirmed'])): ?>
                                                        <button onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')" 
                                                                class="btn btn-sm btn-danger" title="Cancel">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button onclick="viewAppointment(<?php echo $appointment['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
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

    <script>
        function updateStatus(appointmentId, status) {
            if (confirm('Are you sure you want to update this appointment status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updatePaymentStatus(appointmentId, status) {
            if (confirm('Are you sure you want to update this payment status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                    <input type="hidden" name="payment_status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Global escapeHtml for all modals
        function escapeHtml(text) {
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function viewAppointment(appointmentId) {
            // Show loading spinner in modal
            const modal = document.getElementById('appointmentDetailsModal');
            const modalBody = document.getElementById('appointmentDetailsBody');
            modalBody.innerHTML = '<div style="text-align:center;padding:2rem;"><span class="spinner-border"></span> Loading...</div>';
            modal.style.display = 'block';

            // Fetch appointment details via AJAX
            fetch('appointments.php?ajax=1&id=' + appointmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const a = data.appointment;
                        let notes = a.notes ? String(a.notes) : '';
                        let truncated = notes.length > 80 
                            ? `<span style="background:#f1f5f9;padding:0.25em 0.5em;border-radius:6px;">${escapeHtml(notes.substring(0, 20))}...</span> 
                               <button id='expandNotesBtn' class='btn btn-sm btn-outline-primary' style='margin-left:0.5em;padding:0.2em 0.7em;border-radius:6px;font-size:0.85rem;vertical-align:middle;'>
                                   <i class="fas fa-expand"></i> View Full
                               </button>`
                            : `<span style="background:#f1f5f9;padding:0.25em 0.5em;border-radius:6px;">${escapeHtml(notes)}</span>`;
                        let notesCell = notes.length > 80
                            ? `<span id='truncatedNotes'>${truncated}</span> <button id='expandNotesBtn' class='btn btn-sm btn-link' style='padding:0 0.25em;'></button>`
                            : `<span>${truncated}</span>`;
                        modalBody.innerHTML = `
                            <table class="table table-bordered">
                                <tr><th>Patient ID</th><td>${a.patient_id}</td></tr>
                                <tr><th>Dentist ID</th><td>${a.dentist_id}</td></tr>
                                <tr><th>Operation ID</th><td>${a.operation_id}</td></tr>
                                <tr><th>Frontdesk ID</th><td>${a.frontdesk_id}</td></tr>
                                <tr><th>Appointment Date</th><td>${a.appointment_date}</td></tr>
                                <tr><th>Appointment Time</th><td>${a.appointment_time}</td></tr>
                                <tr><th>Duration (minutes)</th><td>${a.duration_minutes}</td></tr>
                                <tr><th>Status</th><td>${a.status}</td></tr>
                                <tr><th>Notes</th><td>${notesCell}</td></tr>
                                <tr><th>Total Cost</th><td>${a.total_cost}</td></tr>
                                <tr><th>Payment Status</th><td>${a.payment_status}</td></tr>
                                <tr><th>Created At</th><td>${a.created_at}</td></tr>
                                <tr><th>Updated At</th><td>${a.updated_at}</td></tr>
                            </table>
                        `;
                        // Add logic to open full notes modal
                        if (notes.length > 80) {
                            setTimeout(function() {
                                var btn = document.getElementById('expandNotesBtn');
                                if (btn) {
                                    btn.onclick = function(e) {
                                        e.preventDefault();
                                        showFullNotesModal(notes);
                                    };
                                }
                            }, 50);
                        }
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-danger">Failed to load appointment details.</div>';
                    }
                })
                .catch(() => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading appointment details.</div>';
                });
        }

        function closeAppointmentModal() {
            document.getElementById('appointmentDetailsModal').style.display = 'none';
        }

        // Show full notes modal
        function showFullNotesModal(notes) {
            var modal = document.getElementById('fullNotesModal');
            var body = document.getElementById('fullNotesBody');
            body.innerHTML = `<pre style='white-space:pre-wrap;font-size:1rem;'>${escapeHtml(notes)}</pre>`;
            modal.style.display = 'flex';
        }

        function closeFullNotesModal() {
            document.getElementById('fullNotesModal').style.display = 'none';
        }

        function clearFilters() {
            window.location.href = window.location.pathname;
        }

        // Add badge styles and table scroll/nowrap styles
        const style = document.createElement('style');
        style.textContent = `
            .badge {
                padding: 0.5em 0.8em;
                font-weight: 500;
                border-radius: 6px;
            }
            .badge.dropdown-toggle {
                width: 100%;
                text-align: center;
                padding: 0.5em 1em;
            }
            .badge.dropdown-toggle::after {
                margin-left: 0.5em;
            }
            .stats-container {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
            }
            .stat-card {
                flex: 1;
                min-width: 200px;
                padding: 1rem;
                border-radius: 12px;
                background: #fff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            .stat-card .stat-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
            }
            .stat-card .stat-content {
                flex: 1;
            }
            .stat-card .stat-number {
                font-size: 1.75rem;
                font-weight: 600;
                line-height: 1;
                margin-bottom: 0.25rem;
            }
            .stat-card .stat-label {
                color: #6c757d;
                font-size: 0.875rem;
            }
            .stat-card.upcoming .stat-icon {
                background: #e3f2fd;
                color: #1976d2;
            }
            .stat-card.in-progress .stat-icon {
                background: #fff3e0;
                color: #f57c00;
            }
            .stat-card.completed .stat-icon {
                background: #e8f5e9;
                color: #388e3c;
            }
            .stat-card.cancelled .stat-icon {
                background: #ffebee;
                color: #d32f2f;
            }
            /* Appointments table scroll and nowrap */
            .appointments-table-wrapper {
                overflow-x: auto;
                width: 100%;
            }
            .appointments-table {
                min-width: 1200px;
                width: max-content;
            }
            .appointments-table th, .appointments-table td {
                white-space: nowrap;
                vertical-align: middle;
            }
        `;
        document.head.appendChild(style);

        // Additional styles for filters
        const filterStyles = document.createElement('style');
        filterStyles.textContent = `
            .filter-section {
                background: #fff;
                padding: 1.5rem;
                border-radius: 12px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            }
            .filter-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.25rem;
            }
            .filter-title {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.95rem;
                font-weight: 600;
                color: #1e293b;
            }
            .filter-title i {
                color: #64748b;
            }
            .btn-clear-filter {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                color: #64748b;
                background: none;
                border: none;
                cursor: pointer;
                transition: color 0.2s;
            }
            .btn-clear-filter:hover {
                color: #ef4444;
            }
            .filter-form {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
            .filter-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .filter-label {
                font-size: 0.85rem;
                font-weight: 500;
                color: #64748b;
            }
            .search-group {
                grid-column: 1 / -2;
            }
            .search-input-wrapper {
                position: relative;
            }
            .search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #64748b;
                font-size: 0.9rem;
            }
            .search-input-wrapper input {
                padding-left: 2.5rem;
            }
            .form-select, .form-control {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background-color: #fff;
            }
            .form-select:focus, .form-control:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            /* Appointments list styles */
            .card {
                border: none;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                border-radius: 12px;
            }
            .card-header {
                background: #fff;
                border-bottom: 1px solid #f1f5f9;
                padding: 1rem 1.5rem;
            }
            .card-header h5 {
                font-size: 0.95rem;
                color: #1e293b;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .card-header h5 i {
                color: #64748b;
            }
            .table {
                font-size: 0.9rem;
            }
            .table th {
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                font-size: 0.75rem;
                letter-spacing: 0.5px;
            }
            /* Payment dropdown styles */
            .payment-dropdown .dropdown-menu {
                padding: 0.5rem;
                border: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            .payment-dropdown .dropdown-item {
                padding: 0.5rem 1rem;
                border-radius: 6px;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.9rem;
            }
            .payment-dropdown .dropdown-item:hover {
                background-color: #f8fafc;
            }
            .payment-dropdown .dropdown-divider {
                margin: 0.5rem 0;
                border-color: #f1f5f9;
            }
        `;
        document.head.appendChild(filterStyles);
    </script>
    <!-- Appointment Details Modal -->
    <!-- Full Notes Modal -->
    <div id="fullNotesModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:10000;align-items:center;justify-content:center;">
      <div style="background:#fff;max-width:700px;width:95vw;margin:5% auto;padding:2.5rem 2rem 2rem 2rem;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.15);position:relative;max-height:80vh;overflow-y:auto;display:flex;flex-direction:column;">
        <button onclick="closeFullNotesModal()" style="position:absolute;top:18px;right:18px;background:none;border:none;font-size:2rem;color:#888;cursor:pointer;transition:color 0.2s;"><i class="fas fa-times"></i></button>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
          <i class="fas fa-file-alt" style="font-size:2rem;color:#1976d2;"></i>
          <h3 style="margin:0;font-size:1.35rem;font-weight:600;color:#222;">Full Patient Notes</h3>
        </div>
        <div id="fullNotesBody" style="background:#f8fafc;border-radius:10px;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.04);font-size:1.05rem;line-height:1.7;color:#222;white-space:pre-wrap;word-break:break-word;max-height:60vh;overflow-y:auto;"></div>
      </div>
    </div>
    <div id="appointmentDetailsModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:500px;margin:5% auto;padding:2rem;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.15);position:relative;max-height:80vh;overflow-y:auto;">
            <button onclick="closeAppointmentModal()" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:1.5rem;color:#888;cursor:pointer;">&times;</button>
            <h4 style="margin-bottom:1.5rem;">Appointment Details</h4>
            <div id="appointmentDetailsBody"></div>
        </div>
    </div>

<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Appointments', $pageContent, 'appointments');
