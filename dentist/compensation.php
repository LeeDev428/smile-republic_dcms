
<?php
require_once '../includes/config.php';
require_once 'layout.php';
requireRole('dentist');

$dentist_id = $_SESSION['user_id'];

// Get selected month (default: current month)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Query all appointments for this dentist in selected month
$stmt = $pdo->prepare('
    SELECT a.id AS appointment_id, a.appointment_date, a.patient_id, p.first_name AS patient_first, p.last_name AS patient_last,
           do.name AS operation_name, do.price, do.commission,
           ROUND(do.price * (do.commission/100), 2) AS commission_amount
    FROM appointments a
    JOIN dental_operations do ON a.operation_id = do.id
    JOIN patients p ON a.patient_id = p.id
    WHERE a.dentist_id = ? AND a.appointment_date >= ? AND a.appointment_date <= ?
    ORDER BY a.appointment_date DESC
');
$stmt->execute([$dentist_id, $month_start, $month_end]);
$rows = $stmt->fetchAll();

// Calculate total commission and per operation statistics
$total_commission = 0;
$operation_stats = [];
foreach ($rows as $row) {
    $total_commission += $row['commission_amount'];
    $op = $row['operation_name'];
    if (!isset($operation_stats[$op])) {
        $operation_stats[$op] = 0;
    }
    $operation_stats[$op] += $row['commission_amount'];
}

ob_start();
?>
<div class="card mb-4">
    <div class="card-header">
        <h4 style="margin:0;">
            <i class="fas fa-money-check-alt"></i> Commission (Monthly)
        </h4>
        <p style="color:var(--text-muted);margin:0;">Your monthly earnings based on completed appointments and service commission rates.</p>
    </div>
    <div class="card-body">
     
        <!-- Horizontal compact statistics cards -->
        <div class="d-flex flex-row gap-2 mb-4" style="overflow-x:auto;">
            <?php foreach ($operation_stats as $op => $comm): ?>
                <div class="card text-center shadow-sm" style="min-width:160px;max-width:180px;padding:0.5rem 0.5rem;">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1" style="font-size:0.95rem;font-weight:600;white-space:nowrap;"><?php echo htmlspecialchars($op); ?></h6>
                        <div style="font-size:1.1rem;color:var(--success-color);font-weight:700;">₱<?php echo number_format($comm,2); ?></div>
                        <div style="font-size:0.8rem;color:var(--text-muted);">Commission</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Month filter above total commission/table -->
        <form method="GET" class="mb-3">
            <div class="d-flex align-items-center gap-2">
                <label for="month" class="form-label mb-0 me-2">Select Month</label>
                <input type="month" id="month" name="month" class="form-control" style="min-width:140px;" value="<?php echo htmlspecialchars($selected_month); ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-calendar"></i> Filter</button>
            </div>
        </form>
        <div style="margin-bottom:2rem;">
            <span style="font-size:1.2rem;font-weight:600;">Total Commission (<?php echo date('F Y', strtotime($month_start)); ?>):</span>
            <span style="font-size:1.5rem;color:var(--success-color);font-weight:700;">₱<?php echo number_format($total_commission,2); ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Commission (%)</th>
                        <th>Commission Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['appointment_date']))); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_first'] . ' ' . $row['patient_last']); ?></td>
                            <td><?php echo htmlspecialchars($row['operation_name']); ?></td>
                            <td>₱<?php echo number_format($row['price'],2); ?></td>
                            <td><?php echo $row['commission'] !== null ? $row['commission'] : '0'; ?>%</td>
                            <td style="color:var(--success-color);font-weight:600;">₱<?php echo number_format($row['commission_amount'],2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
renderDentistLayout('Compensation', $pageContent, 'compensation');
?>
