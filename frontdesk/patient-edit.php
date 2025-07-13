<?php
require_once '../includes/config.php';
requireRole('frontdesk');

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

if ($patient_id <= 0) {
    die('Invalid patient ID.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'gender', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'medical_history', 'allergies', 'insurance_info'
    ];
    $updates = [];
    $params = [];
    foreach ($fields as $field) {
        $updates[] = "$field = ?";
        $params[] = $_POST[$field] ?? '';
    }
    $params[] = $patient_id;
    try {
        $stmt = $pdo->prepare("UPDATE patients SET ".implode(', ', $updates).", updated_at = NOW() WHERE id = ?");
        $stmt->execute($params);
        $message = "Patient record updated successfully.";
    } catch (PDOException $e) {
        $error = "Error updating patient: " . $e->getMessage();
    }
}

// Fetch patient info
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        die('Patient not found.');
    }
} catch (PDOException $e) {
    die('Error loading patient: ' . $e->getMessage());
}

function h($v) { return htmlspecialchars($v ?? ''); }
$gender = isset($patient['gender']) ? trim(strtolower($patient['gender'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Patient Record</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f9fbfd; font-family: Arial, sans-serif; }
        .container { max-width: 900px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); padding: 2rem; }
        h1 { margin-bottom: 1.5rem; }
        .form-row { display: flex; gap: 2rem; }
        .form-col { flex: 1; min-width: 0; }
        .form-group { margin-bottom: 1.2rem; }
        label { font-weight: 500; margin-bottom: 0.4rem; display: block; }
        input, textarea, select { width: 100%; padding: 0.7rem; border-radius: 8px; border: 1px solid #e3e7ef; font-size: 1rem; }
        button { padding: 0.7rem 2rem; border-radius: 8px; background: #19c197; color: #fff; border: none; font-weight: 600; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #e8f5e9; color: #388e3c; }
        .alert-danger { background: #ffebee; color: #d32f2f; }
        @media (max-width: 900px) {
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Patient Record</h1>
        <div style="position: absolute; top: 4rem; right: 22rem;">
            <a href="patients.php" style="color:#19c197; font-weight:600; text-decoration:none;">&larr; Back to Patients</a>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo h($patient['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo h($patient['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo h($patient['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo h($patient['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo h($patient['date_of_birth']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address"><?php echo h($patient['address']); ?></textarea>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?php echo h($patient['emergency_contact_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" value="<?php echo h($patient['emergency_contact_phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Medical History</label>
                        <textarea name="medical_history"><?php echo h($patient['medical_history']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Allergies</label>
                        <textarea name="allergies"><?php echo h($patient['allergies']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Insurance Info</label>
                        <textarea name="insurance_info"><?php echo h($patient['insurance_info']); ?></textarea>
                    </div>
                </div>
            </div>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</body>
</html>
