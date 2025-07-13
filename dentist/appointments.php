<?php
require_once '../includes/config.php';
requireRole('dentist');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle status updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointment_id = intval($_POST['appointment_id']);
    $dentist_id = $_SESSION['user_id'];
    
    if ($action === 'update_status') {
        $new_status = $_POST['status'];
        // Accept spaces and line breaks in notes
        $notes = isset($_POST['notes']) ? trim(str_replace(["\r\n", "\r", "\n"], "\n", $_POST['notes'])) : '';
        $valid_statuses = ['scheduled', 'completed', 'cancelled'];
        
        if (in_array($new_status, $valid_statuses)) {
            try {
                // Verify this appointment belongs to the current dentist
                $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND dentist_id = ?");
                $stmt->execute([$appointment_id, $dentist_id]);
                
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = ?, updated_at = NOW() WHERE id = ? AND dentist_id = ?");
                    $stmt->execute([$new_status, $notes, $appointment_id, $dentist_id]);
                    $message = 'Appointment status updated successfully.';
                } else {
                    $error = 'You can only update your own appointments.';
                }
            } catch (PDOException $e) {
                $error = 'Error updating appointment status: ' . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query with filters
$where_conditions = ["a.dentist_id = :dentist_id"];
$params = [':dentist_id' => $_SESSION['user_id']];

if ($status_filter) {
    $where_conditions[] = "a.status = :status";
    $params[':status'] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "a.appointment_date = :date";
    $params[':date'] = $date_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get paginated appointments
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last, 
               p.phone as patient_phone, p.date_of_birth,
               do.name as operation_name, do.duration_minutes,
               fd.first_name as frontdesk_first, fd.last_name as frontdesk_last
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN dental_operations do ON a.operation_id = do.id
        LEFT JOIN users fd ON a.frontdesk_id = fd.id
        $where_clause
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT :per_page OFFSET :offset
    ");
    
    // Execute with all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $appointments = $stmt->fetchAll();
    
    // Get appointment statistics for this dentist
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM appointments 
        WHERE dentist_id = ?
        GROUP BY status
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = [];
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error = 'Error loading appointments: ' . $e->getMessage();
    $appointments = [];
    $stats = [];
}

function renderPageContent() {
    global $message, $error, $appointments, $stats, $status_filter, $date_filter, $total_records;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">My Appointments</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage your patient appointments and treatment schedules</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="schedule.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt"></i>
                        View Schedule
                    </a>
                </div>
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

            <!-- Statistics Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo ($stats['scheduled'] ?? 0) + ($stats['confirmed'] ?? 0); ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo ($stats['cancelled'] ?? 0) + ($stats['no_show'] ?? 0); ?></h3>
                        <p>Cancelled</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Filter Appointments</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control form-select">
                                <option value="">All Statuses</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Appointments List</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-calendar-times" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No Appointments Found</h3>
                            <p>No appointments match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Patient</th>
                                        <th>Treatment</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo formatDate($appointment['appointment_date']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                                    (<?php echo $appointment['duration_minutes']; ?> min)
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                                </div>
                                                <?php if ($appointment['date_of_birth']): ?>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                        Age: <?php echo date('Y') - date('Y', strtotime($appointment['date_of_birth'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($appointment['operation_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($appointment['status']) {
                                                        'completed' => 'success',
                                                        'cancelled', 'no_show' => 'danger',
                                                        'in_progress' => 'warning',
                                                        default => 'primary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo formatCurrency($appointment['total_cost']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($appointment['notes']): ?>
                                                    <div style="max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                                         title="<?php echo htmlspecialchars($appointment['notes']); ?>">
                                                        <?php echo htmlspecialchars($appointment['notes']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">No notes</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;"></div>
<button style="background: none; border: none; padding: 0.25rem 0.5rem; color: #222; cursor: pointer;"
    onclick="showStatusModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>', '<?php echo addslashes(str_replace(array("\r", "\n", "'", "\\"), array(' ', ' ', "\\'", "\\\\"), $appointment['notes'])); ?>')">
    <i class="fas fa-edit" style="color: #222;"></i>
</button>
                                                    <button style="background: none; border: none; padding: 0.25rem 0.5rem; color: #222; cursor: pointer;" onclick="viewPatientDetails(<?php echo $appointment['patient_id']; ?>)">
                                                        <i class="fas fa-eye" style="color: #222;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div id="loading" style="display: none; text-align: center; padding: 20px;">
                                <i class="fas fa-spinner fa-spin"></i> Loading more appointments...
                            </div>
                        </div>
                        
                        <?php if ($total_records > count($appointments)): ?>
                            <div id="loadMore" style="text-align: center; padding: 20px;">
                                <button class="btn btn-secondary" onclick="loadMoreAppointments()">
                                    <i class="fas fa-sync"></i> Load More
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

    <!-- Status Update Modal -->
    <div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--white); border-radius: var(--border-radius-lg); padding: 2rem; width: 90%; max-width: 500px;">
            <h3 style="margin-bottom: 1.5rem;">Update Appointment Status</h3>
            
            <form id="statusForm" autocomplete="off">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="modalAppointmentId">
                <div class="form-group">
                    <label for="modalStatus" class="form-label">Status</label>
                    <select name="status" id="modalStatus" class="form-control form-select" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalNotes" class="form-label">Treatment Notes</label>
                    <textarea name="notes" id="modalNotes" class="form-control" rows="15" 
                              placeholder="Add treatment notes, observations, or next steps..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateStatusBtn">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div id="patientModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--white); border-radius: var(--border-radius-lg); padding: 2rem; width: 90%; max-width: 600px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Patient Information</h3>
                <button onclick="closePatientModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--gray-400); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="patientDetails">
                <!-- Patient details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Floating Success Message -->
    <div id="floatingSuccess" style="display:none;position:fixed;top:2rem;right:2rem;z-index:2000;min-width:220px;max-width:350px;">
        <div class="alert alert-success" style="box-shadow:0 2px 8px rgba(0,0,0,0.15);">
            <i class="fas fa-check-circle"></i> <span id="floatingSuccessMsg"></span>
        </div>
    </div>
    <script>
        let currentPage = 1;
        let isLoading = false;

        function loadMoreAppointments() {
            if (isLoading) return;
            isLoading = true;
            currentPage++;

            const loading = document.getElementById('loading');
            const loadMore = document.getElementById('loadMore');
            loading.style.display = 'block';
            loadMore.style.display = 'none';

            // Get current filter values
            const status = document.getElementById('status').value;
            const date = document.getElementById('date').value;

            // Fetch more appointments
            fetch(`appointments.php?page=${currentPage}&status=${status}&date=${date}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('table tbody');
                    
                    data.appointments.forEach(appointment => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>
                                <div style="font-weight: 600;">
                                    ${formatDate(appointment.appointment_date)}
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                    ${formatTime(appointment.appointment_time)}
                                    (${appointment.duration_minutes} min)
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    ${escapeHtml(appointment.patient_first + ' ' + appointment.patient_last)}
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                    <i class="fas fa-phone"></i> ${escapeHtml(appointment.patient_phone)}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    ${escapeHtml(appointment.operation_name)}
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-${getStatusClass(appointment.status)}">
                                    ${ucFirst(appointment.status.replace('_', ' '))}
                                </span>
                            </td>
                            <td>
                                <strong>${formatCurrency(appointment.total_cost)}</strong>
                            </td>
                            <td>
                                ${appointment.notes ? 
                                    `<div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                          title="${escapeHtml(appointment.notes)}">
                                        ${escapeHtml(appointment.notes)}
                                    </div>` : 
                                    '<span style="color: var(--text-muted);">No notes</span>'}
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    ${['scheduled', 'confirmed', 'in_progress'].includes(appointment.status) ?
                                        `<button class="btn btn-sm btn-primary" onclick="showStatusModal(${appointment.id}, '${appointment.status}', '${escapeHtml(appointment.notes || '')}')">
                                            <i class="fas fa-edit"></i>
                                        </button>` : ''}
                                    <button class="btn btn-sm btn-secondary" onclick="viewPatientDetails(${appointment.patient_id})">
                                        <i class="fas fa-user"></i>
                                    </button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    loading.style.display = 'none';
                    if (data.hasMore) {
                        loadMore.style.display = 'block';
                    }
                    isLoading = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    loading.style.display = 'none';
                    loadMore.style.display = 'block';
                    isLoading = false;
                });
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatDate(date) {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(time) {
            return new Date('2000-01-01T' + time).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function ucFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        function getStatusClass(status) {
            return {
                'completed': 'success',
                'cancelled': 'danger',
                'no_show': 'danger',
                'in_progress': 'warning',
            }[status] || 'primary';
        }

        function showStatusModal(appointmentId, currentStatus, currentNotes) {
            document.getElementById('modalAppointmentId').value = appointmentId;
            // Set status dropdown to current status
            var statusSelect = document.getElementById('modalStatus');
            for (var i = 0; i < statusSelect.options.length; i++) {
                if (statusSelect.options[i].value === currentStatus) {
                    statusSelect.selectedIndex = i;
                    break;
                }
            }
            var notesField = document.getElementById('modalNotes');
            notesField.value = currentNotes || '';
            notesField.readOnly = false;
            notesField.disabled = false;
            document.getElementById('statusModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // AJAX status update
        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('updateStatusBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            var formData = new FormData(this);
            fetch('appointments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Try to parse success message from HTML
                let msg = 'Appointment status updated successfully.';
                if (html.includes('Appointment status updated successfully.')) {
                    showFloatingSuccess(msg);
                } else {
                    msg = 'Failed to update appointment.';
                }
                // Update table row (status and notes)
                var id = document.getElementById('modalAppointmentId').value;
                var status = document.getElementById('modalStatus').value;
                var notes = document.getElementById('modalNotes').value;
                var row = document.querySelector('tr td input[value="' + id + '"]');
                if (!row) {
                    // fallback: find by data-id attribute if you add it
                }
                // Instead, update by traversing table rows
                var trs = document.querySelectorAll('table tbody tr');
                trs.forEach(function(tr) {
                    if (tr.innerHTML.includes('showStatusModal(' + id + ',')) {
                        // Update status badge
                        var statusCell = tr.querySelector('span.badge');
                        if (statusCell) {
                            statusCell.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                            statusCell.className = 'badge badge-' + getStatusClass(status);
                        }
                        // Update notes
                        var notesCell = tr.querySelector('td:nth-child(6) div, td:nth-child(6) span');
                        if (notesCell) {
                            if (notes) {
                                notesCell.textContent = notes;
                                notesCell.title = notes;
                                notesCell.style.color = '';
                            } else {
                                notesCell.textContent = 'No notes';
                                notesCell.title = '';
                                notesCell.style.color = 'var(--text-muted)';
                            }
                        }
                        // Get patient ID reliably
                        var patientId = null;
                        // Try to get from existing button, else fallback to data attribute
                        var viewBtn = tr.querySelector('button[onclick^=viewPatientDetails]');
                        if (viewBtn) {
                            var match = viewBtn.getAttribute('onclick').match(/viewPatientDetails\((\d+)\)/);
                            if (match) patientId = match[1];
                        }
                        if (!patientId) {
                            // Try to get from a data attribute if you add it in PHP
                            patientId = tr.getAttribute('data-patient-id') || '';
                        }
                        // Escape notes for JS string
                        function escapeForJs(str) {
                            return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, '\\n').replace(/"/g, '\\"');
                        }
                        var actionsCell = tr.querySelector('td:nth-child(7)');
                        if (actionsCell) {
                            actionsCell.innerHTML = `
                                <div style="display: flex; gap: 0.5rem;">
                                    <button style="background: none; border: none; padding: 0.25rem 0.5rem; color: #222; cursor: pointer;" onclick="showStatusModal(${id}, '${status}', '${escapeForJs(notes)}')">
                                        <i class='fas fa-edit' style='color: #222;'></i>
                                    </button>
                                    <button style="background: none; border: none; padding: 0.25rem 0.5rem; color: #222; cursor: pointer;" onclick="viewPatientDetails(${patientId})">
                                        <i class='fas fa-eye' style='color: #222;'></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                });
                closeStatusModal();
                btn.disabled = false;
                btn.innerHTML = 'Update Status';
            })
            .catch(function() {
                showFloatingSuccess('Failed to update appointment.');
                btn.disabled = false;
                btn.innerHTML = 'Update Status';
            });
        });

        function showFloatingSuccess(msg) {
            var box = document.getElementById('floatingSuccess');
            var msgSpan = document.getElementById('floatingSuccessMsg');
            msgSpan.textContent = msg;
            box.style.display = 'block';
            setTimeout(function() {
                box.style.display = 'none';
            }, 3000);
        }

        function viewPatientDetails(patientId) {
            window.location.href = 'view_patient_appointments.php?id=' + encodeURIComponent(patientId);
        }

        function closePatientModal() {
            document.getElementById('patientModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });

        document.getElementById('patientModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePatientModal();
            }
        });

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge {
                display: inline-block;
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1;
                color: var(--white);
                text-align: center;
                white-space: nowrap;
                vertical-align: baseline;
                border-radius: 0.375rem;
            }
            .badge-primary { background-color: var(--primary-color); }
            .badge-success { background-color: var(--success-color); }
            .badge-warning { background-color: var(--warning-color); }
            .badge-danger { background-color: var(--danger-color); }
            .badge-secondary { background-color: var(--gray-500); }
            
            .row { display: flex; flex-wrap: wrap; margin: -0.75rem; }
            .col-md-4, .col-md-6 { flex: 0 0 33.333333%; max-width: 33.333333%; padding: 0.75rem; }
            .col-md-6 { flex: 0 0 50%; max-width: 50%; }
            .col-md-12 { flex: 0 0 100%; max-width: 100%; padding: 0.75rem; }
            .g-3 > * { margin-bottom: 1rem; }
            .d-flex { display: flex; }
            .align-items-end { align-items: flex-end; }
            .me-2 { margin-right: 0.5rem; }
            .mb-3 { margin-bottom: 1rem; }
            .mb-4 { margin-bottom: 1.5rem; }
            .p-4 { padding: 1.5rem; }
            .mt-2 { margin-top: 0.5rem; }
            .text-center { text-align: center; }
            
            .patient-info h4 {
                color: var(--primary-color);
                padding-bottom: 0.5rem;
                border-bottom: 1px solid var(--border-color);
                margin-bottom: 1rem;
            }
            
            .patient-info p {
                margin-bottom: 0.5rem;
                line-height: 1.5;
            }
            
            .patient-info strong {
                color: var(--gray-700);
            }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderDentistLayout('My Appointments', $pageContent, 'appointments.php');
?>
