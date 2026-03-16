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

$purchase_id = $_GET['id'] ?? 0;
$business_id = $_SESSION['business_id'] ?? 1;

if (!$purchase_id || !is_numeric($purchase_id)) {
    header('Location: purchases.php');
    exit();
}

// Fetch Purchase Header with business_id filter - REMOVED shop_id JOIN
$stmt = $pdo->prepare("
    SELECT p.*, 
           m.name as manufacturer_name,
           m.contact_person,
           m.phone as m_phone, 
           m.email as m_email,
           m.address as m_address,
           m.gstin as m_gstin,
           m.account_holder_name,
           m.bank_name,
           m.account_number,
           m.ifsc_code,
           m.branch_name,
           u.full_name as created_by_name
    FROM purchases p
    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ? AND p.business_id = ?
");
$stmt->execute([$purchase_id, $business_id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: purchases.php');
    exit();
}

// Fetch Items with business_id filter - REMOVED shop_id from JOIN condition
$stmt_items = $pdo->prepare("
    SELECT pi.*, 
           p.product_name, 
           p.product_code, 
           p.hsn_code,
           p.unit_of_measure,
           ps.quantity as current_stock
    FROM purchase_items pi
    JOIN products p ON pi.product_id = p.id AND p.business_id = ?
    LEFT JOIN product_stocks ps ON ps.product_id = p.id AND ps.business_id = p.business_id
    WHERE pi.purchase_id = ? AND pi.business_id = ?
    ORDER BY pi.id
");
$stmt_items->execute([$business_id, $purchase_id, $business_id]);
$items = $stmt_items->fetchAll();

// Calculate totals
$subtotal = $cgst_total = $sgst_total = $igst_total = 0;
foreach ($items as $item) {
    $taxable = $item['quantity'] * $item['unit_price'];
    $subtotal      += $taxable;
    $cgst_total    += $item['cgst_amount'];
    $sgst_total    += $item['sgst_amount'];
    $igst_total    += $item['igst_amount'];
}

// Payment status color mapping
$status_classes = [
    'paid' => 'success',
    'partial' => 'warning',
    'unpaid' => 'danger'
];
$status_color = $status_classes[$purchase['payment_status']] ?? 'secondary';
$status_icon = [
    'paid' => 'bx-check-circle',
    'partial' => 'bx-time-five',
    'unpaid' => 'bx-x-circle'
][$purchase['payment_status']] ?? 'bx-receipt';

// Calculate percentages for progress bars
$paid_percent = $purchase['total_amount'] > 0 ? ($purchase['paid_amount'] / $purchase['total_amount']) * 100 : 0;
$pending_amount = $purchase['total_amount'] - $purchase['paid_amount'];
$pending_percent = $purchase['total_amount'] > 0 ? ($pending_amount / $purchase['total_amount']) * 100 : 0;

// Check if bank details exist
$has_bank_details = !empty($purchase['account_holder_name']) || 
                    !empty($purchase['bank_name']) || 
                    !empty($purchase['account_number']) || 
                    !empty($purchase['ifsc_code']) || 
                    !empty($purchase['branch_name']);
?>

<!doctype html>
<html lang="en">
<?php 
$page_title = "Purchase Order #{$purchase['purchase_number']}"; 
include 'includes/head.php'; 
?>
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
                            <div>
                                <h4 class="mb-0">
                                    <i class="bx bx-receipt me-2"></i> Purchase Order Details
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-hash me-1"></i> <?= htmlspecialchars($purchase['purchase_number']) ?>
                                    </small>
                                </h4>
                                <p class="text-muted mb-0">
                                    <i class="bx bx-buildings me-1"></i> 
                                    <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="purchases.php" class="btn btn-outline-secondary">
                                    <i class="bx bx-arrow-back me-1"></i> Back to List
                                </a>
                                <button onclick="window.print()" class="btn btn-success">
                                    <i class="bx bx-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">PO Amount</h6>
                                        <h3 class="mb-0 text-primary">₹<?= number_format($purchase['total_amount'], 2) ?></h3>
                                        <small class="text-muted">
                                            <?= count($items) ?> items
                                            <?php if ($purchase['total_gst'] > 0): ?>
                                            | GST: ₹<?= number_format($purchase['total_gst'], 2) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-primary bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-rupee text-primary"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-success border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Paid Amount</h6>
                                        <h3 class="mb-0 text-success">₹<?= number_format($purchase['paid_amount'], 2) ?></h3>
                                        <small class="text-muted">
                                            <?= number_format($paid_percent, 1) ?>% paid
                                        </small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-success bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-check-circle text-success"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-warning border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Balance Due</h6>
                                        <h3 class="mb-0 text-warning">₹<?= number_format($pending_amount, 2) ?></h3>
                                        <small class="text-muted">
                                            <?= number_format($pending_percent, 1) ?>% pending
                                        </small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-warning bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-time-five text-warning"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-info border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Payment Status</h6>
                                        <div class="mb-2">
                                            <span class="badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?> px-3 py-2">
                                                <i class="bx <?= $status_icon ?> me-1"></i><?= ucfirst($purchase['payment_status']) ?>
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?= $paid_percent ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= $pending_percent ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-info bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-pie-chart-alt text-info"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bx bx-detail me-2"></i> Purchase Order Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="border rounded p-4 bg-light h-100">
                                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                                        <i class="bx bx-building me-2"></i>Supplier Details
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted mb-1">Supplier Name</label>
                                            <p class="fw-bold mb-0 fs-5"><?= htmlspecialchars($purchase['manufacturer_name']) ?></p>
                                        </div>
                                        <?php if ($purchase['contact_person']): ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Contact Person</label>
                                            <p class="mb-0"><?= htmlspecialchars($purchase['contact_person']) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($purchase['m_phone']): ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Phone</label>
                                            <p class="mb-0"><?= htmlspecialchars($purchase['m_phone']) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($purchase['m_email']): ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Email</label>
                                            <p class="mb-0"><?= htmlspecialchars($purchase['m_email']) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($purchase['m_gstin']): ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">GSTIN</label>
                                            <p class="mb-0"><?= htmlspecialchars($purchase['m_gstin']) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($purchase['m_address']): ?>
                                        <div class="col-12">
                                            <label class="form-label small text-muted mb-1">Address</label>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($purchase['m_address'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Bank Details Section -->
                                        <?php if ($has_bank_details): ?>
                                        <div class="col-12 mt-4">
                                            <h6 class="fw-bold text-success mb-3 border-bottom pb-2">
                                                <i class="bx bx-bank me-2"></i>Bank Details
                                            </h6>
                                            <div class="row g-3">
                                                <?php if ($purchase['account_holder_name']): ?>
                                                <div class="col-md-6">
                                                    <label class="form-label small text-muted mb-1">Account Holder</label>
                                                    <p class="fw-bold mb-0"><?= htmlspecialchars($purchase['account_holder_name']) ?></p>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($purchase['bank_name']): ?>
                                                <div class="col-md-6">
                                                    <label class="form-label small text-muted mb-1">Bank Name</label>
                                                    <p class="mb-0"><?= htmlspecialchars($purchase['bank_name']) ?></p>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($purchase['account_number']): ?>
                                                <div class="col-md-6">
                                                    <label class="form-label small text-muted mb-1">Account Number</label>
                                                    <p class="mb-0">
                                                        <code><?= htmlspecialchars($purchase['account_number']) ?></code>
                                                    </p>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($purchase['ifsc_code']): ?>
                                                <div class="col-md-6">
                                                    <label class="form-label small text-muted mb-1">IFSC Code</label>
                                                    <p class="mb-0">
                                                        <span class="badge bg-info bg-opacity-10 text-info"><?= htmlspecialchars($purchase['ifsc_code']) ?></span>
                                                    </p>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($purchase['branch_name']): ?>
                                                <div class="col-12">
                                                    <label class="form-label small text-muted mb-1">Branch Name</label>
                                                    <p class="mb-0"><?= htmlspecialchars($purchase['branch_name']) ?></p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-4 bg-light h-100">
                                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                                        <i class="bx bx-info-circle me-2"></i>PO Information
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">PO Number</label>
                                            <p class="fw-bold mb-0"><?= htmlspecialchars($purchase['purchase_number']) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Date</label>
                                            <p class="mb-0">
                                                <i class="bx bx-calendar me-1"></i>
                                                <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                                            </p>
                                        </div>
                                        <?php if ($purchase['reference']): ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Reference</label>
                                            <p class="mb-0">
                                                <i class="bx bx-hash me-1"></i>
                                                <?= htmlspecialchars($purchase['reference']) ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Created By</label>
                                            <p class="mb-0">
                                                <i class="bx bx-user me-1"></i>
                                                <?= htmlspecialchars($purchase['created_by_name']) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Created On</label>
                                            <p class="mb-0">
                                                <i class="bx bx-time me-1"></i>
                                                <?= date('d M Y, h:i A', strtotime($purchase['created_at'])) ?>
                                            </p>
                                        </div>
                                        <?php if ($purchase['notes']): ?>
                                        <div class="col-12">
                                            <label class="form-label small text-muted mb-1">Notes</label>
                                            <div class="alert alert-info py-2 px-3 mb-0">
                                                <i class="bx bx-note me-2"></i><?= nl2br(htmlspecialchars($purchase['notes'])) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bx bx-package me-2"></i> Purchase Items
                                <small class="text-muted ms-2">(<?= count($items) ?> items)</small>
                            </h5>
                            <span class="badge bg-primary">
                                Total: ₹<?= number_format($purchase['total_amount'], 2) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Product Details</th>
                                        <th class="text-center">HSN</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-center">Unit</th>
                                        <th class="text-end">Rate</th>
                                        <th class="text-end">Taxable</th>
                                        <th class="text-center">Tax Rate</th>
                                        <th class="text-end">Tax Amount</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $i => $item): 
                                        $taxable = $item['quantity'] * $item['unit_price'];
                                        $total_gst = $item['cgst_amount'] + $item['sgst_amount'] + $item['igst_amount'];
                                        $stock_class = $item['current_stock'] <= 10 ? 'danger' : 'success';
                                    ?>
                                    <tr class="item-row">
                                        <td class="text-center fw-bold"><?= $i + 1 ?></td>
                                        <td>
                                            <strong class="d-block"><?= htmlspecialchars($item['product_name']) ?></strong>
                                            <small class="text-muted">
                                                <i class="bx bx-hash me-1"></i><?= htmlspecialchars($item['product_code'] ?: 'N/A') ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <?= $item['hsn_code'] ?: '<span class="text-muted">—</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill px-3 py-1 fs-6"><?= $item['quantity'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $item['unit_of_measure'] ?></span>
                                        </td>
                                        <td class="text-end fw-bold">₹<?= number_format($item['unit_price'], 2) ?></td>
                                        <td class="text-end">₹<?= number_format($taxable, 2) ?></td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column small">
                                                <?php if ($item['cgst_rate'] > 0): ?>
                                                <span class="text-success">C: <?= $item['cgst_rate'] ?>%</span>
                                                <?php endif; ?>
                                                <?php if ($item['sgst_rate'] > 0): ?>
                                                <span class="text-info">S: <?= $item['sgst_rate'] ?>%</span>
                                                <?php endif; ?>
                                                <?php if ($item['igst_rate'] > 0): ?>
                                                <span class="text-warning">I: <?= $item['igst_rate'] ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex flex-column small">
                                                <?php if ($item['cgst_amount'] > 0): ?>
                                                <span class="text-success">C: ₹<?= number_format($item['cgst_amount'], 2) ?></span>
                                                <?php endif; ?>
                                                <?php if ($item['sgst_amount'] > 0): ?>
                                                <span class="text-info">S: ₹<?= number_format($item['sgst_amount'], 2) ?></span>
                                                <?php endif; ?>
                                                <?php if ($item['igst_amount'] > 0): ?>
                                                <span class="text-warning">I: ₹<?= number_format($item['igst_amount'], 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-primary">
                                            ₹<?= number_format($item['total_price'], 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $stock_class ?> bg-opacity-10 text-<?= $stock_class ?> px-3 py-1">
                                                <?= $item['current_stock'] ?? 0 ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold">Subtotal:</td>
                                        <td class="text-end fw-bold">₹<?= number_format($subtotal, 2) ?></td>
                                        <td colspan="2" class="text-end fw-bold">Total GST:</td>
                                        <td class="text-end fw-bold text-success">₹<?= number_format($purchase['total_gst'], 2) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td colspan="9" class="text-end fw-bold fs-5">GRAND TOTAL:</td>
                                        <td class="text-end fw-bold fs-5 text-primary">₹<?= number_format($purchase['total_amount'], 2) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bx bx-info-circle fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="alert-heading mb-1">Payment Status</h6>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-grow-1 me-3">
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $paid_percent ?>%"></div>
                                                        <div class="progress-bar bg-warning" style="width: <?= $pending_percent ?>%"></div>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-success me-2">Paid: ₹<?= number_format($purchase['paid_amount'], 2) ?></span>
                                                    <?php if ($pending_amount > 0): ?>
                                                    <span class="badge bg-warning">Due: ₹<?= number_format($pending_amount, 2) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group">
                                    <a href="purchase_payments_history.php?id=<?= $purchase['id'] ?>" 
                                       class="btn btn-outline-secondary">
                                        <i class="bx bx-history me-1"></i> Payment History
                                    </a>
                                    <?php if ($purchase['payment_status'] !== 'paid'): ?>
                                    <a href="purchase_payment.php?id=<?= $purchase['id'] ?>" 
                                       class="btn btn-outline-success">
                                        <i class="bx bx-money me-1"></i> Add Payment
                                    </a>
                                    <?php endif; ?>
                                    <a href="purchase_edit.php?id=<?= $purchase['id'] ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="bx bx-edit me-1"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>

<style>
.avatar-sm {
    width: 48px;
    height: 48px;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
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
.item-row:hover {
    background-color: rgba(0,0,0,0.02);
}
@media print {
    .no-print, .vertical-menu, .topbar, .page-title-box .btn-group,
    .card-header, .btn-group, .alert, .text-end .btn-group {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .badge {
        border: 1px solid #ccc !important;
        background-color: #fff !important;
        color: #000 !important;
    }
}
</style>

<script>
$(document).ready(function() {
    // Tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Row hover effect
    $('.item-row').hover(
        function() { $(this).addClass('bg-light'); },
        function() { $(this).removeClass('bg-light'); }
    );
});
</script>
</body>
</html>