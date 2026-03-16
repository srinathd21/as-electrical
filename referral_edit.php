<?php
date_default_timezone_set('Asia/Kolkata');
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['current_business_id'])) {
    header('Location: select_shop.php');
    exit();
}

$current_business_id = (int) $_SESSION['current_business_id'];
$referral_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($referral_id <= 0) {
    header('Location: referral_persons.php');
    exit();
}

// Fetch referral details
$stmt = $pdo->prepare("SELECT * FROM referral_person WHERE id = ? AND business_id = ?");
$stmt->execute([$referral_id, $current_business_id]);
$referral = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$referral) {
    header('Location: referral_persons.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $commission_percent = (float)$_POST['commission_percent'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $notes = trim($_POST['notes']);
    
    // Validate
    if (empty($full_name)) {
        $error = 'Full name is required';
    } elseif (!empty($phone)) {
        // Check if phone already exists for another referral in same business
        $check_stmt = $pdo->prepare("SELECT id FROM referral_person WHERE phone = ? AND business_id = ? AND id != ?");
        $check_stmt->execute([$phone, $current_business_id, $referral_id]);
        if ($check_stmt->fetch()) {
            $error = 'Phone number already exists for another referral';
        }
    }
    
    if (!$error) {
        try {
            $pdo->beginTransaction();
            
            $update_stmt = $pdo->prepare("
                UPDATE referral_person 
                SET full_name = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    commission_percent = ?,
                    is_active = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND business_id = ?
            ");
            
            $update_stmt->execute([
                $full_name,
                $phone,
                $email,
                $address,
                $commission_percent,
                $is_active,
                $notes,
                $referral_id,
                $current_business_id
            ]);
            
            $pdo->commit();
            $success = 'Referral person updated successfully!';
            
            // Refresh referral data
            $stmt->execute([$referral_id, $current_business_id]);
            $referral = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to update referral: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<?php 
$page_title = "Edit Referral - " . htmlspecialchars($referral['full_name']); 
include(__DIR__ . '/includes/head.php'); 
?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include(__DIR__ . '/includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include(__DIR__ . '/includes/sidebar.php'); ?>
        </div>
    </div>
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-1">
                                    <i class="bx bx-edit me-2"></i> Edit Referral Person
                                </h4>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="referral_persons.php">Referral Persons</a></li>
                                        <li class="breadcrumb-item"><a href="referral_view.php?id=<?= $referral_id ?>"><?= htmlspecialchars($referral['full_name']) ?></a></li>
                                        <li class="breadcrumb-item active">Edit</li>
                                    </ol>
                                </nav>
                            </div>
                            <div>
                                <a href="referral_view.php?id=<?= $referral_id ?>" class="btn btn-outline-secondary">
                                    <i class="bx bx-arrow-back me-1"></i> Back to View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bx bx-error-circle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bx bx-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name *</label>
                                                <input type="text" class="form-control" name="full_name" 
                                                       value="<?= htmlspecialchars($referral['full_name']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Referral Code</label>
                                                <input type="text" class="form-control" 
                                                       value="<?= htmlspecialchars($referral['referral_code']) ?>" readonly>
                                                <small class="text-muted">Referral code cannot be changed</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone</label>
                                                <input type="text" class="form-control" name="phone" 
                                                       value="<?= htmlspecialchars($referral['phone'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?= htmlspecialchars($referral['email'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($referral['address'] ?? '') ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Commission Percentage</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="commission_percent" 
                                                           value="<?= $referral['commission_percent'] ?>" step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <small class="text-muted">Percentage commission on referred sales</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" class="form-check-input" name="is_active" 
                                                           id="is_active" <?= $referral['is_active'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="is_active">
                                                        Active
                                                    </label>
                                                </div>
                                                <small class="text-muted">Inactive referrals won't receive new commissions</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($referral['notes'] ?? '') ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="referral_view.php?id=<?= $referral_id ?>" class="btn btn-secondary">
                                            <i class="bx bx-x me-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-save me-1"></i> Update Referral
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Stats Card -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Current Statistics</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Balance Due</span>
                                            <strong class="text-success">₹<?= number_format($referral['balance_due'], 2) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Total Debit</span>
                                            <strong class="text-danger">₹<?= number_format($referral['debit_amount'], 2) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Total Paid</span>
                                            <strong class="text-info">₹<?= number_format($referral['paid_amount'], 2) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Total Referrals</span>
                                            <strong><?= $referral['total_referrals'] ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Total Sales</span>
                                            <strong class="text-warning">₹<?= number_format($referral['total_sales_amount'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                       
                    </div>
                </div>
            </div>
        </div>
        <?php include(__DIR__ . '/includes/footer.php'); ?>
    </div>
</div>
<?php include(__DIR__ . '/includes/rightbar.php'); ?>
<?php include(__DIR__ . '/includes/scripts.php'); ?>

<script>
$(document).ready(function() {
    // Auto-hide alerts
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
});
</script>
</body>
</html>