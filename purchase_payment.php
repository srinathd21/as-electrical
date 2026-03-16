<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'warehouse_manager'])) {
    header('Location: dashboard.php');
    exit();
}

$purchase_id = (int)($_GET['id'] ?? 0);
if (!$purchase_id) {
    header('Location: purchases.php');
    exit();
}

// Fetch purchase
$stmt = $pdo->prepare("
    SELECT p.*, m.name as manufacturer_name,
           p.total_amount - p.paid_amount as balance_due
    FROM purchases p
    JOIN manufacturers m ON p.manufacturer_id = m.id
    WHERE p.id = ?
");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: purchases.php');
    exit();
}

$success = $error = '';
$transaction_started = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

    if ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif ($amount > $purchase['balance_due']) {
        $error = "Amount cannot exceed balance: ₹" . number_format($purchase['balance_due'], 2);
    } else {
        try {
            $pdo->beginTransaction();
            $transaction_started = true;

            // FIXED: 7 placeholders = 7 values
            $stmt = $pdo->prepare("INSERT INTO payments 
                (payment_date, type, reference_id, amount, payment_method, reference_no, recorded_by, notes)
                VALUES (?, 'supplier', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $payment_date,
                $purchase_id,
                $amount,
                $payment_method,
                $reference_no,
                $_SESSION['user_id'],
                $notes
            ]);

            // Update purchase
            $new_paid = $purchase['paid_amount'] + $amount;
            $status = ($new_paid >= $purchase['total_amount']) ? 'paid' : 'partial';

            $pdo->prepare("UPDATE purchases SET paid_amount = ?, payment_status = ? WHERE id = ?")
                ->execute([$new_paid, $status, $purchase_id]);

            $pdo->commit();
            // REDIRECT TO PURCHASES LIST WITH SUCCESS
            header("Location: purchases.php?success=payment&po=" . $purchase['purchase_number']);
            exit();

            // Refresh data
            $stmt->execute([$purchase_id]);
            $purchase = $stmt->fetch();
        } catch (Exception $e) {
            if ($transaction_started) $pdo->rollBack();
            $error = "Failed: " . $e->getMessage();
        }
    }
}

// Fetch payment history - FIXED
$payments = $pdo->prepare("
    SELECT p.*, u.full_name as recorded_by
    FROM payments p
    JOIN users u ON p.recorded_by = u.id
    WHERE p.type = 'supplier' AND p.reference_id = ?
    ORDER BY p.payment_date DESC, p.created_at DESC
");
$payments->execute([$purchase_id]);
$payments = $payments->fetchAll();
?>

<!doctype html>
<html lang="en">
<?php 
$page_title = "Payment - PO #{$purchase['purchase_number']}"; 
include('includes/head.php'); 
?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php')?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <i class="bx bx-money me-2"></i> Record Supplier Payment
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-hash me-1"></i> <?= htmlspecialchars($purchase['purchase_number']) ?>
                                    </small>
                                </h4>
                                <p class="text-muted mb-0">
                                    <i class="bx bx-building me-1"></i> 
                                    <?= htmlspecialchars($purchase['manufacturer_name']) ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="purchases.php" class="btn btn-outline-secondary">
                                    <i class="bx bx-arrow-back me-1"></i> Back to Purchases
                                </a>
                                <a href="purchase_view.php?id=<?= $purchase_id ?>" class="btn btn-outline-info">
                                    <i class="bx bx-show me-1"></i> View PO
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bx bx-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bx bx-error-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Left Column: Payment Form -->
                    <div class="col-lg-8">
                        <!-- PO Summary Card -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-receipt me-2"></i> Purchase Order Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label small text-muted mb-1">PO Number</label>
                                            <p class="fw-bold mb-0 fs-5"><?= htmlspecialchars($purchase['purchase_number']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small text-muted mb-1">Supplier</label>
                                            <p class="mb-0 fs-6">
                                                <i class="bx bx-building me-2"></i>
                                                <?= htmlspecialchars($purchase['manufacturer_name']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label small text-muted mb-1">Purchase Date</label>
                                            <p class="mb-0">
                                                <i class="bx bx-calendar me-2"></i>
                                                <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small text-muted mb-1">Current Status</label>
                                            <p class="mb-0">
                                                <?php 
                                                $status_color = $purchase['payment_status'] === 'paid' ? 'success' : 
                                                              ($purchase['payment_status'] === 'partial' ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?> px-3 py-1">
                                                    <i class="bx bx-<?= $status_color === 'success' ? 'check-circle' : ($status_color === 'warning' ? 'time-five' : 'x-circle') ?> me-1"></i>
                                                    <?= ucfirst($purchase['payment_status']) ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Status Bars -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="progress" style="height: 12px;">
                                            <?php 
                                            $paid_percent = $purchase['total_amount'] > 0 ? ($purchase['paid_amount'] / $purchase['total_amount']) * 100 : 0;
                                            $pending_percent = $purchase['total_amount'] > 0 ? ($purchase['balance_due'] / $purchase['total_amount']) * 100 : 0;
                                            ?>
                                            <div class="progress-bar bg-success" style="width: <?= $paid_percent ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= $pending_percent ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <div class="text-center">
                                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">
                                                    <i class="bx bx-check-circle me-1"></i>
                                                    Paid: ₹<?= number_format($purchase['paid_amount'], 2) ?>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-1">
                                                    <i class="bx bx-time-five me-1"></i>
                                                    Due: ₹<?= number_format($purchase['balance_due'], 2) ?>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1">
                                                    <i class="bx bx-rupee me-1"></i>
                                                    Total: ₹<?= number_format($purchase['total_amount'], 2) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form Card -->
                        <?php if ($purchase['balance_due'] > 0): ?>
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-plus-circle me-2"></i> Record New Payment
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-4">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Payment Date <span class="text-danger">*</span></label>
                                            <input type="date" name="payment_date" class="form-control" 
                                                   value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Payment Method <span class="text-danger">*</span></label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="cash">Cash</option>
                                                <option value="bank">Bank Transfer</option>
                                                <option value="upi">UPI</option>
                                                <option value="cheque">Cheque</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Amount <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" name="amount" class="form-control form-control-lg text-end" 
                                                       step="0.01" min="1" max="<?= $purchase['balance_due'] ?>" 
                                                       value="<?= $purchase['balance_due'] ?>" required>
                                            </div>
                                            <small class="text-muted">Max: ₹<?= number_format($purchase['balance_due'], 2) ?></small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" name="reference_no" class="form-control" 
                                                   placeholder="e.g. Cheque no., UPI ref, etc.">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Notes (Optional)</label>
                                            <textarea name="notes" class="form-control" rows="1" 
                                                      placeholder="Additional notes about this payment..."></textarea>
                                        </div>
                                    </div>
                                    <div class="text-end mt-4">
                                        <a href="purchase_view.php?id=<?= $purchase_id ?>" class="btn btn-outline-secondary me-2">
                                            <i class="bx bx-x me-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-success btn-lg px-5">
                                            <i class="bx bx-check-circle me-2"></i> Record Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card shadow-sm">
                            <div class="card-body text-center py-5">
                                <div class="mb-4">
                                    <i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>
                                </div>
                                <h4 class="text-success mb-3">Purchase Order Fully Paid</h4>
                                <p class="text-muted">No balance amount pending for this purchase order.</p>
                                <div class="mt-4">
                                    <a href="purchase_view.php?id=<?= $purchase_id ?>" class="btn btn-outline-primary me-2">
                                        <i class="bx bx-show me-1"></i> View PO Details
                                    </a>
                                    <a href="purchases.php" class="btn btn-primary">
                                        <i class="bx bx-list-ul me-1"></i> Back to Purchases
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Payment History Card -->
                        <?php if ($payments): ?>
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="bx bx-history me-2"></i> Payment History
                                    </h5>
                                    <span class="badge bg-primary"><?= count($payments) ?> Payments</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Reference</th>
                                                <th>Recorded By</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $pay): ?>
                                            <tr class="payment-row">
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong><?= date('d M Y', strtotime($pay['payment_date'])) ?></strong>
                                                        <small class="text-muted"><?= date('h:i A', strtotime($pay['created_at'])) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">
                                                        <i class="bx bx-rupee me-1"></i> ₹<?= number_format($pay['amount'], 2) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $method_color = [
                                                        'cash' => 'info',
                                                        'bank' => 'primary',
                                                        'upi' => 'success',
                                                        'cheque' => 'warning'
                                                    ][$pay['payment_method']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $method_color ?> bg-opacity-10 text-<?= $method_color ?>">
                                                        <?= ucfirst($pay['payment_method']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($pay['reference_no']): ?>
                                                    <span class="badge bg-light text-dark">
                                                        <?= htmlspecialchars($pay['reference_no']) ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <i class="bx bx-user me-1"></i>
                                                        <?= htmlspecialchars($pay['recorded_by']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($pay['notes']): ?>
                                                    <small class="text-muted">
                                                        <i class="bx bx-note me-1"></i>
                                                        <?= htmlspecialchars(substr($pay['notes'], 0, 30)) . (strlen($pay['notes']) > 30 ? '...' : '') ?>
                                                    </small>
                                                    <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Quick Actions -->
                    <div class="col-lg-4">
                        <!-- Quick Actions Card -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-zap me-2"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-3">
                                    <a href="purchase_view.php?id=<?= $purchase_id ?>" 
                                       class="btn btn-outline-info btn-lg d-flex align-items-center justify-content-start px-4 py-3">
                                        <i class="bx bx-show fs-4 me-3"></i>
                                        <div class="text-start">
                                            <strong>View Purchase Order</strong>
                                            <small class="d-block text-muted">See complete details</small>
                                        </div>
                                    </a>
                                    <a href="purchase_print.php?id=<?= $purchase_id ?>" target="_blank"
                                       class="btn btn-outline-success btn-lg d-flex align-items-center justify-content-start px-4 py-3">
                                        <i class="bx bx-printer fs-4 me-3"></i>
                                        <div class="text-start">
                                            <strong>Print PO</strong>
                                            <small class="d-block text-muted">Generate printable version</small>
                                        </div>
                                    </a>
                                    <a href="purchase_payments_history.php?id=<?= $purchase_id ?>"
                                       class="btn btn-outline-primary btn-lg d-flex align-items-center justify-content-start px-4 py-3">
                                        <i class="bx bx-history fs-4 me-3"></i>
                                        <div class="text-start">
                                            <strong>Payment History</strong>
                                            <small class="d-block text-muted">View all payments</small>
                                        </div>
                                    </a>
                                    <a href="purchase_edit.php?id=<?= $purchase_id ?>"
                                       class="btn btn-outline-warning btn-lg d-flex align-items-center justify-content-start px-4 py-3">
                                        <i class="bx bx-edit fs-4 me-3"></i>
                                        <div class="text-start">
                                            <strong>Edit Purchase Order</strong>
                                            <small class="d-block text-muted">Modify PO details</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Summary Card -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-pie-chart-alt me-2"></i> Balance Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <span class="text-muted">Total Amount</span>
                                        <span class="fw-bold fs-5">₹<?= number_format($purchase['total_amount'], 2) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <span class="text-success">Amount Paid</span>
                                        <span class="fw-bold text-success">₹<?= number_format($purchase['paid_amount'], 2) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-warning">Balance Due</span>
                                        <span class="fw-bold text-warning">₹<?= number_format($purchase['balance_due'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Status Card -->
                        <div class="card shadow-sm mt-4 border-start border-<?= $status_color ?> border-4">
                            <div class="card-body">
                                <div class="text-center">
                                    <div class="mb-3">
                                        <?php if ($purchase['payment_status'] === 'paid'): ?>
                                        <i class="bx bx-check-circle text-success" style="font-size: 3rem;"></i>
                                        <?php elseif ($purchase['payment_status'] === 'partial'): ?>
                                        <i class="bx bx-time-five text-warning" style="font-size: 3rem;"></i>
                                        <?php else: ?>
                                        <i class="bx bx-x-circle text-danger" style="font-size: 3rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="mb-2">Payment Status</h5>
                                    <span class="badge bg-<?= $status_color ?> px-4 py-2 fs-6">
                                        <?= ucfirst($purchase['payment_status']) ?>
                                    </span>
                                    <div class="mt-3">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?= $paid_percent ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= $pending_percent ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <small class="text-success"><?= number_format($paid_percent, 1) ?>% Paid</small>
                                            <small class="text-warning"><?= number_format($pending_percent, 1) ?>% Due</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>

<style>
.card-hover {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15) !important;
}
.border-start {
    border-left-width: 4px !important;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
.payment-row:hover {
    background-color: rgba(0,0,0,0.02);
}
.btn-lg {
    border-radius: 10px;
}
.input-group-text {
    background-color: #f8f9fa;
    border-color: #ced4da;
}
@media (max-width: 768px) {
    .btn-lg {
        padding: 0.75rem 1rem;
    }
}
</style>

<script>
$(document).ready(function() {
    // Auto-close alerts after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);

    // Row hover effect
    $('.payment-row').hover(
        function() { $(this).addClass('bg-light'); },
        function() { $(this).removeClass('bg-light'); }
    );

    // Format amount input
    $('input[name="amount"]').on('input', function() {
        const max = parseFloat($(this).attr('max'));
        const value = parseFloat($(this).val());
        
        if (value > max) {
            $(this).val(max.toFixed(2));
            alert('Amount cannot exceed balance: ₹' + max.toFixed(2));
        }
    });
});
</script>
</body>
</html>