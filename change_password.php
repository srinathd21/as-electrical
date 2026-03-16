<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'] ?? 0;

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND business_id = ?");
$stmt->execute([$user_id, $business_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?
                WHERE id = ? AND business_id = ?
            ");
            $result = $stmt->execute([$hashed_password, $user_id, $business_id]);
            
            if ($result) {
                $success = "Password changed successfully!";
                
                // Optional: Log the password change activity
                // You can add activity logging here if needed
                
                // Clear POST data to prevent form resubmission
                $_POST = array();
            } else {
                $error = "Failed to update password. Please try again.";
            }
            
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            $error = "Database error occurred. Please try again later.";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <?php include 'includes/head.php'; ?>
    <style>
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include 'includes/topbar.php'; ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include 'includes/sidebar.php'; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">
                                <i class="bx bx-lock me-2"></i> Change Password
                            </h4>
                            <div class="d-flex gap-2">
                                <a href="profile_edit.php" class="btn btn-outline-secondary">
                                    <i class="bx bx-arrow-back me-1"></i> Back to Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bx bx-check-circle me-2"></i>
                    <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bx bx-error-circle me-2"></i>
                    <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Change Password Form -->
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card shadow">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-shield me-2"></i> Update Your Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="passwordForm" class="needs-validation" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bx bx-lock-alt me-1"></i> Current Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bx bx-key"></i></span>
                                            <input type="password" class="form-control" name="current_password" 
                                                   id="current_password" required placeholder="Enter current password">
                                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                                                <i class="bx bx-show"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bx bx-lock me-1"></i> New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bx bx-key"></i></span>
                                            <input type="password" class="form-control" name="new_password" 
                                                   id="new_password" required minlength="6" placeholder="Enter new password">
                                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                                                <i class="bx bx-show"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <small class="text-muted">
                                            <i class="bx bx-info-circle me-1"></i>
                                            Minimum 6 characters
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bx bx-lock me-1"></i> Confirm New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bx bx-key"></i></span>
                                            <input type="password" class="form-control" name="confirm_password" 
                                                   id="confirm_password" required placeholder="Confirm new password">
                                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                                                <i class="bx bx-show"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback" id="passwordMatchFeedback"></div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="bx bx-info-circle me-2"></i>
                                        <strong>Password Requirements:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Minimum 6 characters</li>
                                            <li>Should not be easily guessable</li>
                                            <li>Different from your previous passwords</li>
                                            <li>Use a mix of letters, numbers, and special characters for better security</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i class="bx bx-save me-1"></i> Change Password
                                        </button>
                                        <a href="profile_edit.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-x me-1"></i> Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<?php include 'includes/rightbar.php'; ?>
<?php include 'includes/scripts.php'; ?>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bx-show');
            icon.classList.add('bx-hide');
        } else {
            input.type = 'password';
            icon.classList.remove('bx-hide');
            icon.classList.add('bx-show');
        }
    });
});

// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    
    // Remove existing classes
    strengthBar.className = 'password-strength';
    
    if (password.length === 0) {
        strengthBar.style.width = '0';
        return;
    }
    
    // Check password strength
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength += 1;
    if (password.length >= 12) strength += 1;
    
    // Character variety checks
    if (/[a-z]/.test(password)) strength += 1;  // lowercase
    if (/[A-Z]/.test(password)) strength += 1;  // uppercase
    if (/[0-9]/.test(password)) strength += 1;   // numbers
    if (/[^a-zA-Z0-9]/.test(password)) strength += 1;  // special characters
    
    // Determine strength level
    if (strength < 3) {
        strengthBar.classList.add('strength-weak');
    } else if (strength < 5) {
        strengthBar.classList.add('strength-medium');
    } else {
        strengthBar.classList.add('strength-strong');
    }
});

// Password match checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const feedback = document.getElementById('passwordMatchFeedback');
    
    if (confirmPassword.length > 0) {
        if (newPassword === confirmPassword) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            feedback.textContent = '';
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            feedback.textContent = 'Passwords do not match';
        }
    } else {
        this.classList.remove('is-valid', 'is-invalid');
        feedback.textContent = '';
    }
});

// Form validation and submission
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const current = document.getElementById('current_password');
    const newPass = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    
    // Reset custom validity
    current.setCustomValidity('');
    newPass.setCustomValidity('');
    confirm.setCustomValidity('');
    
    if (!current.value) {
        e.preventDefault();
        current.setCustomValidity('Please enter current password');
        current.reportValidity();
        return;
    }
    
    if (newPass.value.length < 6) {
        e.preventDefault();
        newPass.setCustomValidity('Password must be at least 6 characters long');
        newPass.reportValidity();
        return;
    }
    
    if (newPass.value !== confirm.value) {
        e.preventDefault();
        confirm.setCustomValidity('Passwords do not match');
        confirm.reportValidity();
        return;
    }
    
    // Disable submit button to prevent double submission
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Changing Password...';
});

// Clear form fields after successful submission (if there's a success message)
<?php if ($success): ?>
document.getElementById('passwordForm').reset();
<?php endif; ?>

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>
</body>
</html>