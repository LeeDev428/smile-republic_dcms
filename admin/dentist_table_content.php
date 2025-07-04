<?php
// This file renders the dentist table content for AJAX updates
foreach ($dentists as $dentist): ?>
<tr>
    <td>
        <div class="d-flex align-items-center">
            <div class="avatar-circle me-3">
                <?php echo strtoupper(substr($dentist['first_name'], 0, 1) . substr($dentist['last_name'], 0, 1)); ?>
            </div>
            <div>
                <div class="fw-bold"><?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($dentist['email']); ?></small>
            </div>
        </div>
    </td>
    <td><?php echo htmlspecialchars($dentist['phone'] ?? 'Not provided'); ?></td>
    <td><?php echo htmlspecialchars($dentist['specialization'] ?? 'General'); ?></td>
    <td><?php echo htmlspecialchars($dentist['license_number'] ?? 'Not provided'); ?></td>
    <td><?php echo intval($dentist['years_of_experience'] ?? 0); ?> years</td>
    <td>
        <span class="badge badge-<?php echo $dentist['status'] === 'active' ? 'success' : 'warning'; ?>">
            <?php echo ucfirst($dentist['status']); ?>
        </span>
    </td>
    <td>
        <span class="badge badge-info">
            <?php echo intval($dentist['appointment_count']); ?> total
        </span>
        <?php if ($dentist['upcoming_appointments'] > 0): ?>
            <br><span class="badge badge-primary">
                <?php echo intval($dentist['upcoming_appointments']); ?> upcoming
            </span>
        <?php endif; ?>
    </td>
    <td>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-primary" 
                    onclick="viewDentist(<?php echo $dentist['id']; ?>)" 
                    title="View Details">
                <i class="fas fa-eye"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" 
                    onclick="editDentist(<?php echo $dentist['id']; ?>)" 
                    title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-<?php echo $dentist['status'] === 'active' ? 'warning' : 'success'; ?>" 
                    onclick="toggleDentistStatus(<?php echo $dentist['id']; ?>, '<?php echo $dentist['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                    title="<?php echo $dentist['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                <i class="fas fa-<?php echo $dentist['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
            </button>
        </div>
    </td>
</tr>
<?php endforeach; ?>

<?php if (empty($dentists)): ?>
<tr>
    <td colspan="8" class="text-center text-muted py-4">
        <i class="fas fa-user-md fa-3x mb-3 text-muted"></i>
        <br>
        No dentists found matching your criteria.
    </td>
</tr>
<?php endif; ?>
