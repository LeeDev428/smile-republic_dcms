<?php if (empty($dentists)): ?>
    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
        <i class="fas fa-user-md" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
        <h5>No dentists found</h5>
        <p>No dentists match your search criteria.</p>
        <button onclick="showAddDentistModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add First Dentist
        </button>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th><i class="fas fa-user-md me-2"></i>Dentist</th>
                    <th><i class="fas fa-address-book me-2"></i>Contact</th>
                    <th><i class="fas fa-stethoscope me-2"></i>Specialization</th>
                    <th><i class="fas fa-graduation-cap me-2"></i>Experience</th>
                    <th><i class="fas fa-certificate me-2"></i>License</th>
                    <th><i class="fas fa-calendar-check me-2"></i>Appointments</th>
                    <th><i class="fas fa-toggle-on me-2"></i>Status</th>
                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dentists as $dentist): ?>
                    <tr class="align-middle">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary">Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?></div>
                                    <small class="text-muted">ID: #<?php echo $dentist['id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="contact-info">
                                <?php if ($dentist['email']): ?>
                                    <div class="mb-1">
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <small><?php echo htmlspecialchars($dentist['email']); ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($dentist['phone']): ?>
                                    <div>
                                        <i class="fas fa-phone text-success me-2"></i>
                                        <small><?php echo htmlspecialchars($dentist['phone']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($dentist['specialization']): ?>
                                <span class="badge bg-primary fs-6 px-3 py-2">
                                    <i class="fas fa-star me-1"></i>
                                    <?php echo htmlspecialchars($dentist['specialization']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark fs-6 px-3 py-2">
                                    <i class="fas fa-user-md me-1"></i>
                                    General Dentistry
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="experience-badge">
                                <span class="badge bg-info fs-6 px-3 py-2">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo $dentist['years_of_experience'] ?? 0; ?> years
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="license-info">
                                <?php if ($dentist['license_number']): ?>
                                    <span class="badge bg-secondary fs-6 px-3 py-2">
                                        <i class="fas fa-certificate me-1"></i>
                                        <?php echo htmlspecialchars($dentist['license_number']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-minus me-1"></i>
                                        Not specified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-center">
                                <div class="appointment-stats">
                                    <div class="fw-bold fs-5 text-primary"><?php echo $dentist['appointment_count']; ?></div>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo $dentist['upcoming_appointments']; ?> upcoming
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            $status_class = $dentist['status'] === 'active' ? 'success' : 'danger';
                            $status_icon = $dentist['status'] === 'active' ? 'check-circle' : 'times-circle';
                            ?>
                            <span class="badge bg-<?php echo $status_class; ?> fs-6 px-3 py-2">
                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                <?php echo ucfirst($dentist['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button onclick="viewDentist(<?php echo $dentist['id']; ?>)" 
                                        class="btn btn-outline-primary btn-sm" 
                                        title="View Details"
                                        data-bs-toggle="tooltip">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editDentistData(<?php echo $dentist['id']; ?>)" 
                                        class="btn btn-outline-secondary btn-sm" 
                                        title="Edit Dentist"
                                        data-bs-toggle="tooltip">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="toggleStatus(<?php echo $dentist['id']; ?>, '<?php echo $dentist['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                                        class="btn btn-outline-<?php echo $dentist['status'] === 'active' ? 'danger' : 'success'; ?> btn-sm" 
                                        title="<?php echo $dentist['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>"
                                        data-bs-toggle="tooltip">
                                    <i class="fas fa-<?php echo $dentist['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
