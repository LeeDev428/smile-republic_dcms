<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('admin/dashboard.php');
            break;
        case 'dentist':
            redirect('dentist/dashboard.php');
            break;
        case 'frontdesk':
            redirect('frontdesk/dashboard.php');
            break;
    }
}

$error = '';

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role, first_name, last_name, status FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            redirect('admin/dashboard.php');
                            break;
                        case 'dentist':
                            redirect('dentist/dashboard.php');
                            break;
                        case 'frontdesk':
                            redirect('frontdesk/dashboard.php');
                            break;
                    }
                } else {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Smile Republic Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                    <i class="fas fa-tooth" style="font-size: 2.5rem; color: var(--primary-color); margin-right: 0.75rem;"></i>
                    <h2 style="margin: 0; color: var(--primary-color); font-size: 1.75rem;">Smile Republic</h2>
                </div>
                <h3>Staff Login</h3>
                <p>Welcome back! Please sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="margin-bottom: 2rem;">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i>
                        Username or Email
                    </label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           placeholder="Enter your username or email" 
                           autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter your password" 
                               autocomplete="current-password" required>
                        <button type="button" id="togglePassword" 
                                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); 
                                       border: none; background: none; color: var(--gray-400); cursor: pointer; 
                                       padding: 0; font-size: 1rem;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <button type="submit" class="btn btn-primary w-100" style="font-size: 1rem; padding: 1rem;">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In to Dashboard
                    </button>
                </div>
            </form>

            <div style="text-align: center; margin-bottom: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--gray-200);">
                <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.875rem;">
                    Need help accessing your account?
                </p>
                <a href="index.php" class="btn btn-secondary" style="font-size: 0.875rem;">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>

            <!-- Demo Credentials Info -->
            <div class="alert alert-info">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                    <h5 style="margin: 0; font-weight: 600;">Demo Login Credentials</h5>
                </div>
                <div style="font-size: 0.875rem; line-height: 1.6;">
                    <div style="background: var(--gray-50); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem; font-family: 'Courier New', monospace;">
                        <div style="margin-bottom: 0.5rem;"><strong>Username:</strong> admin</div>
                        <div><strong>Password:</strong> password</div>
                    </div>
                    <p style="margin: 0; color: var(--text-muted);">
                        <small>This is the default administrator account. Additional staff accounts can be created through the admin panel.</small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');
            
            toggleButton.addEventListener('click', function() {
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordField.type = 'password';
                    toggleButton.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                showAlert('Please fill in all required fields.', 'danger');
                return false;
            }
        });

        // Add loading state to submit button
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            submitBtn.disabled = true;
            
            // Re-enable button after 5 seconds (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Alert function
        function showAlert(message, type = 'info') {
            const existingAlert = document.querySelector('.alert');
            if (existingAlert && !existingAlert.classList.contains('alert-info')) {
                existingAlert.remove();
            }
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
            
            const form = document.querySelector('form');
            form.parentNode.insertBefore(alert, form);
            
            setTimeout(() => alert.remove(), 5000);
        }

        // Auto-fill demo credentials (for development)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                document.getElementById('username').value = 'admin';
                document.getElementById('password').value = 'password';
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
