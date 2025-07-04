<?php
require_once '../includes/config.php';
requireRole('admin');

// Include layout for rendering
require_once 'layout.php';

$message = '';
$error = '';

// Handle service operations
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_service') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $duration_minutes = intval($_POST['duration_minutes']);
        $price = floatval($_POST['price']);
        $category = sanitize($_POST['category']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO dental_operations (name, description, duration_minutes, price, category, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $duration_minutes, $price, $category]);
            $message = "Service added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding service: " . $e->getMessage();
        }
    } elseif ($action === 'update_service') {
        $service_id = intval($_POST['service_id']);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $duration_minutes = intval($_POST['duration_minutes']);
        $price = floatval($_POST['price']);
        $category = sanitize($_POST['category']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE dental_operations 
                SET name = ?, description = ?, duration_minutes = ?, price = ?, category = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $duration_minutes, $price, $category, $service_id]);
            $message = "Service updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating service: " . $e->getMessage();
        }
    } elseif ($action === 'delete_service') {
        $service_id = intval($_POST['service_id']);
        
        try {
            // Check if service is used in appointments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE operation_id = ?");
            $stmt->execute([$service_id]);
            $appointment_count = $stmt->fetchColumn();
            
            if ($appointment_count > 0) {
                $error = "Cannot delete service. Service is used in existing appointments.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM dental_operations WHERE id = ?");
                $stmt->execute([$service_id]);
                $message = "Service deleted successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error deleting service: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_category) {
    $conditions[] = "category = ?";
    $params[] = $filter_category;
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get services with usage counts
    $stmt = $pdo->prepare("
        SELECT do.*, 
               COUNT(a.id) as usage_count,
               SUM(CASE WHEN a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_usage
        FROM dental_operations do
        LEFT JOIN appointments a ON do.id = a.operation_id
        $where_clause
        GROUP BY do.id
        ORDER BY do.name ASC
    ");
    $stmt->execute($params);
    $services = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_services,
            AVG(price) as avg_price,
            AVG(duration_minutes) as avg_duration,
            COUNT(DISTINCT category) as total_categories
        FROM dental_operations
    ");
    $stats = $stmt->fetch();
    
    // Get unique categories
    $stmt = $pdo->query("SELECT DISTINCT category FROM dental_operations WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Error loading services: " . $e->getMessage();
    $services = [];
    $stats = ['total_services' => 0, 'avg_price' => 0, 'avg_duration' => 0, 'total_categories' => 0];
    $categories = [];
}

function renderPageContent() {
    global $message, $error, $services, $stats, $categories, $search, $filter_category;
?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Services Management</h1>
                    <p style="margin: 0; color: var(--text-muted);">Manage dental services and procedures</p>
                </div>
                <div>
                    <button onclick="showAddServiceModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add New Service
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
                        <i class="fas fa-tooth"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['total_services']; ?></div>
                        <div class="stat-label">Total Services</div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo formatCurrency($stats['avg_price']); ?></div>
                        <div class="stat-label">Average Price</div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['avg_duration'] ? round($stats['avg_duration']) : 0; ?> min</div>
                        <div class="stat-label">Average Duration</div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['total_categories']; ?></div>
                        <div class="stat-label">Categories</div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-search"></i>
                        Search & Filter Services
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Service name or description..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Services List -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-list"></i>
                        Services (<?php echo count($services); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($services)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-tooth" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h5>No services found</h5>
                            <p>No services match your search criteria.</p>
                            <button onclick="showAddServiceModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Add First Service
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Service Name</th>
                                        <th>Category</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Usage</th>
                                        <th>Recent Usage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($service['name']); ?></div>
                                                <?php if ($service['description']): ?>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                        <?php echo htmlspecialchars(substr($service['description'], 0, 100)); ?>
                                                        <?php if (strlen($service['description']) > 100): ?>...<?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($service['category']): ?>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($service['category']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-style: italic;">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $service['duration_minutes']; ?> min</span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--success-color);">
                                                    <?php echo formatCurrency($service['price']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600;"><?php echo $service['usage_count']; ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">total</div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div style="font-weight: 600; color: var(--primary-color);"><?php echo $service['recent_usage']; ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">last 30d</div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button onclick="viewService(<?php echo $service['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="editService(<?php echo $service['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteService(<?php echo $service['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
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

    <!-- Add/Edit Service Modal -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="serviceModalTitle">Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="serviceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="serviceAction" value="add_service">
                        <input type="hidden" name="service_id" id="serviceId">
                        
                        <div class="mb-3">
                            <label class="form-label">Service Name *</label>
                            <input type="text" name="name" id="serviceName" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="serviceCategory" class="form-control" placeholder="e.g., Preventive, Restorative, Cosmetic">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" name="duration_minutes" id="serviceDuration" class="form-control" min="5" max="480" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price *</label>
                                <input type="number" name="price" id="servicePrice" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="serviceDescription" class="form-control" rows="3" placeholder="Brief description of the service..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="serviceSubmitBtn">Add Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddServiceModal() {
            document.getElementById('serviceModalTitle').textContent = 'Add New Service';
            document.getElementById('serviceAction').value = 'add_service';
            document.getElementById('serviceSubmitBtn').textContent = 'Add Service';
            document.getElementById('serviceForm').reset();
            new bootstrap.Modal(document.getElementById('serviceModal')).show();
        }

        function editService(serviceId) {
            document.getElementById('serviceModalTitle').textContent = 'Edit Service';
            document.getElementById('serviceAction').value = 'update_service';
            document.getElementById('serviceId').value = serviceId;
            document.getElementById('serviceSubmitBtn').textContent = 'Update Service';
            new bootstrap.Modal(document.getElementById('serviceModal')).show();
        }

        function viewService(serviceId) {
            // Placeholder for service details view
            alert('Service details view will be implemented here. ID: ' + serviceId);
        }

        function deleteService(serviceId) {
            if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" name="service_id" value="${serviceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add badge styles
        const style = document.createElement('style');
        style.textContent = `
            .badge-primary { background-color: var(--primary-color); }
            .badge-info { background-color: var(--info-color); }
        `;
        document.head.appendChild(style);
    </script>
<?php
}

ob_start();
renderPageContent();
$pageContent = ob_get_clean();

renderAdminLayout('Services Management', $pageContent, 'services');
?>
