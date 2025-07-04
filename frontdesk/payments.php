<?php
require_once '../includes/config.php';
requireRole('frontdesk');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle payment recording
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'record_payment') {
        $appointment_id = intval($_POST['appointment_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);
        $notes = sanitize($_POST['notes'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (appointment_id, amount, payment_method, payment_date, notes, created_by) 
                VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$appointment_id, $amount, $payment_method, $notes, $_SESSION['user_id']]);
            
            // Update appointment status to paid if full amount is paid
            $stmt = $pdo->prepare("SELECT do.price FROM appointments a JOIN dental_operations do ON a.operation_id = do.id WHERE a.id = ?");
            $stmt->execute([$appointment_id]);
            $service_price = $stmt->fetchColumn();
            
            if ($amount >= $service_price) {
                $stmt = $pdo->prepare("UPDATE appointments SET payment_status = 'paid' WHERE id = ?");
                $stmt->execute([$appointment_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE appointments SET payment_status = 'partial' WHERE id = ?");
                $stmt->execute([$appointment_id]);
            }
            
            $message = "Payment recorded successfully.";
        } catch (PDOException $e) {
            $error = "Error recording payment: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($filter_status) {
    $conditions[] = "a.payment_status = ?";
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

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get appointments with payment information
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last, 
               p.phone as patient_phone,
               u.first_name as dentist_first, u.last_name as dentist_last,
               do.name as operation_name, do.price as service_price,
               COALESCE(SUM(py.amount), 0) as total_paid
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.dentist_id = u.id
        JOIN dental_operations do ON a.operation_id = do.id
        LEFT JOIN payments py ON a.id = py.appointment_id
        $where_clause
        GROUP BY a.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Get payment statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(py.amount) as total_revenue
        FROM appointments a
        LEFT JOIN payments py ON a.id = py.appointment_id
        WHERE a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error loading payment data: " . $e->getMessage();
    $appointments = [];
    $stats = ['paid_count' => 0, 'partial_count' => 0, 'pending_count' => 0, 'total_revenue' => 0];
}

function renderPageContent() {
    global $message, $error, $appointments, $stats, $filter_status, $filter_date, $search;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Payments</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage patient payments and billing</p>
                </div>
                <div>
                    <button onclick="generateInvoice()" class="btn btn-success">
                        <i class="fas fa-file-invoice"></i>
                        Generate Invoice
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Payment Statistics -->
            <div class="stats-grid mb-4">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['paid_count'] ?? 0; ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['partial_count'] ?? 0; ?></div>
                        <div class="stat-label">Partial Payment</div>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['pending_count'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></div>
                        <div class="stat-label">Revenue (30 days)</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-filter"></i>
                        Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Payment Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search Patient</label>
                            <input type="text" name="search" class="form-control" placeholder="Name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                    Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment Records -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-credit-card"></i>
                        Payment Records (<?php echo count($appointments); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h5>No payment records found</h5>
                            <p>No appointments match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <?php
                                        $balance = $appointment['service_price'] - $appointment['total_paid'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo formatDate($appointment['appointment_date']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);"><?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['operation_name']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    Dr. <?php echo htmlspecialchars($appointment['dentist_first'] . ' ' . $appointment['dentist_last']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo formatCurrency($appointment['service_price']); ?></div>
                                            </td>
                                            <td>
                                                <div style="color: var(--success-color); font-weight: 600;"><?php echo formatCurrency($appointment['total_paid']); ?></div>
                                            </td>
                                            <td>
                                                <div style="color: <?php echo $balance > 0 ? 'var(--danger-color)' : 'var(--success-color)'; ?>; font-weight: 600;">
                                                    <?php echo formatCurrency($balance); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = match($appointment['payment_status'] ?? 'pending') {
                                                    'paid' => 'success',
                                                    'partial' => 'warning', 
                                                    'pending' => 'danger',
                                                    'refunded' => 'info',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst($appointment['payment_status'] ?? 'pending'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($balance > 0): ?>
                                                        <button onclick="recordPayment(<?php echo $appointment['id']; ?>, <?php echo $balance; ?>)" 
                                                                class="btn btn-sm btn-success" title="Record Payment">
                                                            <i class="fas fa-credit-card"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button onclick="viewPaymentHistory(<?php echo $appointment['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="Payment History">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                    
                                                    <button onclick="printInvoice(<?php echo $appointment['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-secondary" title="Print Invoice">
                                                        <i class="fas fa-print"></i>
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

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="paymentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="record_payment">
                        <input type="hidden" name="appointment_id" id="paymentAppointmentId">
                        
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" id="paymentAmount" class="form-control" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">Select payment method</option>
                                <option value="cash">Cash</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="check">Check</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="insurance">Insurance</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function recordPayment(appointmentId, balance) {
            document.getElementById('paymentAppointmentId').value = appointmentId;
            document.getElementById('paymentAmount').value = balance.toFixed(2);
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        function viewPaymentHistory(appointmentId) {
            // Placeholder for payment history modal
            alert('Payment history view will be implemented here. ID: ' + appointmentId);
        }

        function printInvoice(appointmentId) {
            // Placeholder for invoice printing
            alert('Invoice printing will be implemented here. ID: ' + appointmentId);
        }

        function generateInvoice() {
            // Placeholder for batch invoice generation
            alert('Batch invoice generation will be implemented here.');
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-success { background-color: var(--success-color); }
            .badge-warning { background-color: var(--warning-color); }
            .badge-danger { background-color: var(--danger-color); }
            .badge-info { background-color: var(--info-color); }
            .badge-secondary { background-color: var(--gray-500); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderFrontdeskLayout('Payments', $pageContent, 'payments');
?>
