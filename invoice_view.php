<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['current_business_id'] ?? $_SESSION['business_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'seller';
$current_shop_id = $_SESSION['current_shop_id'] ?? null;

// Check shop access
if (!$current_shop_id && $user_role !== 'admin') {
    header('Location: select_shop.php');
    exit();
}

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
if (!$invoice_id) {
    header('Location: invoices.php');
    exit();
}

// Fetch invoice with site and engineer details - respecting business_id and shop_id
$sql = "
    SELECT i.*,
           c.name as customer_name, c.phone as customer_phone, c.gstin as customer_gstin,
           c.address as customer_address,
           u.full_name as seller_name,
           s.shop_name, s.address as shop_address, s.phone as shop_phone, s.gstin as shop_gstin,
           si.site_id, si.site_name, si.site_address, si.city as site_city, si.state as site_state,
           si.postal_code as site_postal_code, si.project_type,
           e.engineer_id, e.first_name as engineer_first_name, e.last_name as engineer_last_name,
           e.phone as engineer_phone, e.email as engineer_email, e.specialization as engineer_specialization
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN users u ON i.seller_id = u.id
    LEFT JOIN shops s ON i.shop_id = s.id
    LEFT JOIN sites si ON i.site_id = si.site_id
    LEFT JOIN engineers e ON i.engineer_id = e.engineer_id
    WHERE i.id = ? AND i.business_id = ?
";

// Add shop filter for non-admin users
$params = [$invoice_id, $business_id];
if ($user_role !== 'admin') {
    $sql .= " AND i.shop_id = ?";
    $params[] = $current_shop_id;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoice = $stmt->fetch();

if (!$invoice) {
    $_SESSION['error'] = "Invoice not found or you don't have permission to view it!";
    header('Location: invoices.php');
    exit();
}

// Fetch all payments for this invoice to calculate total paid correctly
$payment_stmt = $pdo->prepare("
    SELECT 
        p.payment_amount AS amount,
        p.payment_method,
        p.reference_no,
        p.payment_date,
        p.notes AS payment_note,
        p.created_at AS payment_recorded_at,
        u.full_name AS collected_by
    FROM invoice_payments p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.invoice_id = ? AND p.business_id = ?
    ORDER BY 
        COALESCE(p.payment_date, p.created_at) DESC
");
$payment_stmt->execute([$invoice_id, $business_id]);
$payment_history = $payment_stmt->fetchAll();

// Calculate total payments received from payment history
$total_payments_from_history = array_sum(array_column($payment_history, 'amount'));

// Calculate initial payment from invoice (cash + upi + bank + cheque)
$initial_payment = ($invoice['cash_amount'] ?? 0) + 
                   ($invoice['upi_amount'] ?? 0) + 
                   ($invoice['bank_amount'] ?? 0) + 
                   ($invoice['cheque_amount'] ?? 0);

// Total paid = initial payment + additional payments
$total_paid = $initial_payment + $total_payments_from_history;

// Calculate pending amount correctly
$invoice_total = $invoice['total'] ?? 0;
$pending = $invoice_total - $total_paid;

// Update invoice record if pending_amount is incorrect
if ($pending != ($invoice['pending_amount'] ?? 0)) {
    $update_stmt = $pdo->prepare("UPDATE invoices SET pending_amount = ? WHERE id = ?");
    $update_stmt->execute([$pending, $invoice_id]);
    $invoice['pending_amount'] = $pending;
}

// Payment status based on calculated pending amount
$payment_status = $pending <= 0 ? 'paid' : ($total_paid > 0 ? 'partial' : 'unpaid');
$status_class = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$payment_status];

// Fetch items with product details
$items_stmt = $pdo->prepare("
    SELECT 
        ii.id as item_id,
        ii.product_id,
        ii.quantity,
        ii.return_qty,
        ii.unit_price,
        ii.discount_amount,
        ii.sale_type,
        ii.unit as item_unit,
        ii.hsn_code as item_hsn,
        p.product_name,
        p.product_code,
        p.hsn_code as product_hsn,
        p.unit_of_measure as product_unit
    FROM invoice_items ii
    LEFT JOIN products p ON ii.product_id = p.id
    WHERE ii.invoice_id = ?
    ORDER BY ii.id
");
$items_stmt->execute([$invoice_id]);
$items = $items_stmt->fetchAll();

// Get returned quantities from returns table
$returned_qty = [];
$ret_stmt = $pdo->prepare("
    SELECT invoice_item_id, SUM(quantity) as returned_qty
    FROM return_items ri
    JOIN returns r ON ri.return_id = r.id
    WHERE r.invoice_id = ? AND r.business_id = ?
    GROUP BY invoice_item_id
");
$ret_stmt->execute([$invoice_id, $business_id]);
foreach ($ret_stmt->fetchAll() as $ret) {
    $returned_qty[$ret['invoice_item_id']] = (int)$ret['returned_qty'];
}

// Calculate totals
$subtotal = 0;
$total_discount = 0;
$active_total = 0;
$processed_items = [];

foreach ($items as &$item) {
    $sold_qty = $item['quantity'];
    $returned_this = $returned_qty[$item['item_id']] ?? 0;
    $remaining_qty = $sold_qty - $returned_this;
    
    // MRP × Qty (GST Inclusive)
    $line_total = $item['unit_price'] * $sold_qty;
    $discount = $item['discount_amount'] ?? 0;
    $net_total = $line_total - $discount;
    
    // Active total after return
    $active_amount = $remaining_qty > 0 ? ($remaining_qty / $sold_qty) * $net_total : 0;
    $active_total += $active_amount;
    $subtotal += $line_total;
    $total_discount += $discount;
    
    // Get unit - prioritize item_unit from invoice_items, fallback to product_unit from products
    $unit = !empty($item['item_unit']) ? $item['item_unit'] : 
            (!empty($item['product_unit']) ? $item['product_unit'] : 'PCS');
    
    // Get HSN code
    $hsn_code = !empty($item['item_hsn']) ? $item['item_hsn'] : 
               (!empty($item['product_hsn']) ? $item['product_hsn'] : '');
    
    // Store for display
    $processed_items[] = [
        'item_id' => $item['item_id'],
        'product_id' => $item['product_id'],
        'product_name' => $item['product_name'] ?? 'Unknown Product',
        'product_code' => $item['product_code'] ?? '',
        'hsn_code' => $hsn_code,
        'sale_type' => $item['sale_type'],
        'unit' => $unit,
        'sold_qty' => $sold_qty,
        'returned_qty' => $returned_this,
        'remaining_qty' => $remaining_qty,
        'unit_price' => $item['unit_price'],
        'discount_amount' => $discount,
        'line_total_inclusive' => $net_total,
        'active_amount' => $active_amount
    ];
}

// Format site address
$site_full_address = $invoice['site_address'] ?? '';
if (!empty($invoice['site_city'])) {
    $site_full_address .= (!empty($site_full_address) ? ', ' : '') . $invoice['site_city'];
}
if (!empty($invoice['site_state'])) {
    $site_full_address .= (!empty($site_full_address) ? ', ' : '') . $invoice['site_state'];
}
if (!empty($invoice['site_postal_code'])) {
    $site_full_address .= (!empty($site_full_address) ? ' - ' : '') . $invoice['site_postal_code'];
}

// Format engineer name
$engineer_full_name = '';
if (!empty($invoice['engineer_first_name'])) {
    $engineer_full_name = trim($invoice['engineer_first_name'] . ' ' . ($invoice['engineer_last_name'] ?? ''));
}

// Get business name for display
$business_name = $_SESSION['current_business_name'] ?? 'Business';

// Debug info (can be removed in production)
$debug_info = "Invoice Total: ₹$invoice_total, Initial Payment: ₹$initial_payment, Additional Payments: ₹$total_payments_from_history, Total Paid: ₹$total_paid, Pending: ₹$pending";
?>
<!doctype html>
<html lang="en">
<?php $page_title = "Invoice #" . htmlspecialchars($invoice['invoice_number']); include 'includes/head.php'; ?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include 'includes/topbar.php'; ?>
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
                        <div class="page-title-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h4 class="mb-0">
                                    <i class="bx bx-receipt me-2"></i>
                                    Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?>
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-buildings me-1"></i>
                                        <?= htmlspecialchars($business_name) ?>
                                        <?php if ($user_role !== 'admin'): ?>
                                        <span class="badge bg-info ms-2">
                                            <i class="bx bx-store me-1"></i>
                                            <?= htmlspecialchars($invoice['shop_name'] ?? 'Current Shop') ?>
                                        </span>
                                        <?php endif; ?>
                                    </small>
                                </h4>
                                <p class="text-muted mb-0">
                                    <i class="bx bx-calendar me-1"></i>
                                    <?= date('d M Y, h:i A', strtotime($invoice['created_at'])) ?>
                                </p>
                                <!-- Debug info - remove in production -->
                                <!-- <small class="text-muted d-block mt-1"><?= $debug_info ?></small> -->
                            </div>
                            <div class="d-flex gap-2">
                                <a href="invoice_print.php?invoice_id=<?php echo $invoice['id']; ?>" 
                                   class="btn btn-outline-secondary" 
                                   target="_blank">
                                    <i class="bx bx-printer me-1"></i> Print
                                </a>
                                <a href="invoices.php" class="btn btn-outline-primary">
                                    <i class="bx bx-arrow-back me-1"></i> Back to Invoices
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bx bx-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bx bx-error-circle me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4 g-3">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Invoice Total<br><small>(GST Inclusive)</small></h6>
                                <h3 class="mb-0 text-primary">₹<?= number_format($invoice_total, 2) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-success border-4 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Active Total<br><small>(After Returns)</small></h6>
                                <h3 class="mb-0 text-success">₹<?= number_format($active_total, 2) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-warning border-4 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Pending Amount</h6>
                                <h3 class="mb-0 <?= $pending > 0 ? 'text-warning' : 'text-success' ?>">
                                    ₹<?= number_format($pending, 2) ?>
                                </h3>
                                <?php if($pending <= 0): ?>
                                <small class="text-success"><i class="bx bx-check-circle"></i> Fully Paid</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-info border-4 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Payment Status</h6>
                                <span class="badge bg-<?= $status_class ?> bg-opacity-10 text-<?= $status_class ?> px-4 py-2 fs-5">
                                    <?= ucfirst($payment_status) ?>
                                </span>
                                <?php if($pending > 0): ?>
                                <div class="mt-2">
                                    <a href="collect_payment.php?invoice_id=<?= $invoice_id ?>" class="btn btn-sm btn-warning">
                                        <i class="bx bx-money me-1"></i> Collect Payment
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Summary Card - Moved before Invoice Summary -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bx bx-credit-card me-2"></i> Payment Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Initial Payment (at invoice time):</h6>
                                <div class="ps-3">
                                    <?php if($invoice['cash_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Cash</span>
                                        <strong class="text-success">₹<?= number_format($invoice['cash_amount'], 2) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($invoice['upi_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>UPI</span>
                                        <strong class="text-primary">₹<?= number_format($invoice['upi_amount'], 2) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($invoice['bank_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Bank Transfer</span>
                                        <strong class="text-info">₹<?= number_format($invoice['bank_amount'], 2) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($invoice['cheque_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Cheque</span>
                                        <strong class="text-warning">₹<?= number_format($invoice['cheque_amount'], 2) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(($invoice['change_given'] ?? 0) > 0): ?>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Change Given</span>
                                        <strong class="text-success">₹<?= number_format($invoice['change_given'], 2) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($initial_payment == 0): ?>
                                    <div class="text-muted">No initial payment</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">Payment Overview:</h6>
                                <div class="ps-3">
                                    <div class="d-flex justify-content-between py-2">
                                        <span>Invoice Total:</span>
                                        <strong>₹<?= number_format($invoice_total, 2) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 text-primary">
                                        <span>Initial Payment:</span>
                                        <strong>₹<?= number_format($initial_payment, 2) ?></strong>
                                    </div>
                                    <?php if($total_payments_from_history > 0): ?>
                                    <div class="d-flex justify-content-between py-2 text-success">
                                        <span>Additional Payments:</span>
                                        <strong>₹<?= number_format($total_payments_from_history, 2) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="d-flex justify-content-between py-2 fw-bold fs-5">
                                        <span>Total Received:</span>
                                        <strong class="text-primary">₹<?= number_format($total_paid, 2) ?></strong>
                                    </div>
                                    <?php if($pending > 0): ?>
                                    <div class="d-flex justify-content-between py-2 text-danger fw-bold">
                                        <span>Pending Amount:</span>
                                        <strong>₹<?= number_format($pending, 2) ?></strong>
                                    </div>
                                    <?php else: ?>
                                    <div class="d-flex justify-content-between py-2 text-success fw-bold">
                                        <span>Status:</span>
                                        <strong><i class="bx bx-check-circle"></i> Fully Paid</strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                       
                        <?php if($pending > 0): ?>
                        <div class="alert alert-warning mt-3">
                            <div class="d-flex align-items-center">
                                <i class="bx bx-alarm-exclamation fs-4 me-3"></i>
                                <div>
                                    <strong>Pending Payment: ₹<?= number_format($pending, 2) ?></strong>
                                    <p class="mb-0 mt-1">This invoice has pending amount. You can collect payment using the button below.</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="collect_payment.php?invoice_id=<?= $invoice_id ?>" class="btn btn-warning">
                                    <i class="bx bx-money me-1"></i> Collect Payment
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success mt-3">
                            <div class="d-flex align-items-center">
                                <i class="bx bx-check-circle fs-4 me-3"></i>
                                <div>
                                    <strong>Invoice Fully Paid</strong>
                                    <p class="mb-0 mt-1">This invoice has been fully paid. Total received: ₹<?= number_format($total_paid, 2) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Invoice Summary - Bill To / Ship To Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row">
                            <!-- Bill To (Customer) -->
                            <div class="col-md-6">
                                <div class="bg-light p-3 rounded">
                                    <h6 class="fw-bold mb-3">
                                        <i class="bx bx-user me-2"></i>Bill To
                                    </h6>
                                    <div class="ps-2">
                                        <strong><?= htmlspecialchars($invoice['customer_name'] ?? 'Walk-in Customer') ?></strong><br>
                                        <?php if ($invoice['customer_phone']): ?>
                                        <span><i class="bx bx-phone me-1 text-muted"></i><?= htmlspecialchars($invoice['customer_phone']) ?></span><br>
                                        <?php endif; ?>
                                        <?php if ($invoice['customer_gstin']): ?>
                                        <span><i class="bx bx-certification me-1 text-muted"></i>GSTIN: <?= htmlspecialchars($invoice['customer_gstin']) ?></span><br>
                                        <?php endif; ?>
                                        <?php if ($invoice['customer_address']): ?>
                                        <span><i class="bx bx-map me-1 text-muted"></i><?= htmlspecialchars($invoice['customer_address']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ship To (Site/Engineer) -->
                            <div class="col-md-6">
                                <div class="bg-light p-3 rounded">
                                    <h6 class="fw-bold mb-3">
                                        <i class="bx bx-map-pin me-2"></i>Ship To
                                    </h6>
                                    <div class="ps-2">
                                        <?php if (!empty($invoice['site_name'])): ?>
                                            <!-- Site Information -->
                                            <div class="mb-2">
                                                <strong><i class="bx bx-building me-1"></i>Site: <?= htmlspecialchars($invoice['site_name']) ?></strong>
                                            </div>
                                            <?php if (!empty($site_full_address)): ?>
                                            <div class="mb-1">
                                                <i class="bx bx-map me-1 text-muted"></i><?= htmlspecialchars($site_full_address) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($invoice['project_type'])): ?>
                                            <div class="mb-1">
                                                <i class="bx bx-briefcase me-1 text-muted"></i>Project: <?= htmlspecialchars($invoice['project_type']) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Engineer Information -->
                                            <?php if (!empty($engineer_full_name)): ?>
                                            <div class="mt-2 pt-2 border-top">
                                                <div class="mb-1">
                                                    <strong><i class="bx bx-user-circle me-1"></i>Engineer: <?= htmlspecialchars($engineer_full_name) ?></strong>
                                                </div>
                                                <?php if (!empty($invoice['engineer_phone'])): ?>
                                                <div class="mb-1">
                                                    <i class="bx bx-phone me-1 text-muted"></i><?= htmlspecialchars($invoice['engineer_phone']) ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($invoice['engineer_email'])): ?>
                                                <div class="mb-1">
                                                    <i class="bx bx-envelope me-1 text-muted"></i><?= htmlspecialchars($invoice['engineer_email']) ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($invoice['engineer_specialization'])): ?>
                                                <div class="mb-1">
                                                    <i class="bx bx-wrench me-1 text-muted"></i><?= htmlspecialchars($invoice['engineer_specialization']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- No site assigned - show same as Bill To -->
                                            <div class="text-muted fst-italic">
                                                <i class="bx bx-info-circle me-1"></i>Same as Bill To
                                            </div>
                                            <div class="mt-2">
                                                <strong><?= htmlspecialchars($invoice['customer_name'] ?? 'Walk-in Customer') ?></strong><br>
                                                <?php if ($invoice['customer_address']): ?>
                                                <span><?= htmlspecialchars($invoice['customer_address']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shop & Seller Info Row -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="bg-light p-3 rounded">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-2">
                                                <i class="bx bx-store me-2"></i>Shop
                                            </h6>
                                            <strong><?= htmlspecialchars($invoice['shop_name'] ?? 'Main Shop') ?></strong>
                                            <?php if (!empty($invoice['shop_address'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($invoice['shop_address']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($invoice['shop_phone'])): ?>
                                            <br><small><i class="bx bx-phone me-1"></i><?= htmlspecialchars($invoice['shop_phone']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($invoice['shop_gstin'])): ?>
                                            <br><small><i class="bx bx-certification me-1"></i>GST: <?= htmlspecialchars($invoice['shop_gstin']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-2">
                                                <i class="bx bx-user-check me-2"></i>Seller
                                            </h6>
                                            <strong><?= htmlspecialchars($invoice['seller_name'] ?? 'N/A') ?></strong>
                                            <?php if (!empty($invoice['notes'])): ?>
                                            <br><small class="text-muted"><i class="bx bx-note me-1"></i>Note: <?= htmlspecialchars($invoice['notes']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History Card -->
                <?php if (!empty($payment_history)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bx bx-history me-2"></i> Payment History
                            <span class="badge bg-primary ms-2"><?= count($payment_history) ?> payments</span>
                        </h5>
                        <div class="text-end">
                            <span class="text-success fw-bold">Total Additional: ₹<?= number_format($total_payments_from_history, 2) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Payment Mode</th>
                                        <th>Amount</th>
                                        <th>Collected By</th>
                                        <th>Notes</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_history as $payment):
                                        $payment_mode = $payment['payment_method'] ?? 'cash';
                                        $mode_class = [
                                            'cash' => 'success',
                                            'upi' => 'primary',
                                            'bank' => 'info',
                                            'cheque' => 'warning',
                                            'other' => 'secondary'
                                        ][$payment_mode] ?? 'secondary';

                                        $pay_date = $payment['payment_date']
                                            ? date('d M Y', strtotime($payment['payment_date']))
                                            : date('d M Y', strtotime($payment['payment_recorded_at']));
                                        $pay_time = $payment['payment_date']
                                            ? date('h:i A', strtotime($payment['payment_date']))
                                            : date('h:i A', strtotime($payment['payment_recorded_at']));
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= $pay_date ?></strong><br>
                                            <small class="text-muted"><?= $pay_time ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $mode_class ?> bg-opacity-10 text-<?= $mode_class ?> px-3 py-1">
                                                <i class="bx bx-<?= $payment_mode == 'upi' ? 'qr' : ($payment_mode == 'bank' ? 'credit-card' : $payment_mode) ?> me-1"></i>
                                                <?= ucfirst(str_replace('_', ' ', $payment_mode)) ?>
                                            </span>
                                        </td>
                                        <td class="text-success fw-bold fs-5">
                                            ₹<?= number_format($payment['amount'], 2) ?>
                                        </td>
                                        <td><?= htmlspecialchars($payment['collected_by'] ?? 'System') ?></td>
                                        <td>
                                            <?php if ($payment['payment_note']): ?>
                                            <small class="text-muted"><?= htmlspecialchars($payment['payment_note']) ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">
                                                <i class="bx bx-check-circle me-1"></i> Completed
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Items Table -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bx bx-list-ul me-2"></i> Invoice Items (GST Inclusive in MRP)
                            <span class="badge bg-primary ms-2"><?= count($processed_items) ?> items</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th class="text-center">Item ID</th>
                                        <th class="text-center">Quantity & Unit</th>
                                        <th class="text-center">Sold</th>
                                        <th class="text-center">Returned</th>
                                        <th class="text-center">Remaining</th>
                                        <th class="text-end">Rate</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Total (Inclusive)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $item_count = 1;
                                    foreach ($processed_items as $item):
                                        $is_fully_returned = $item['remaining_qty'] <= 0;
                                        $is_partially_returned = $item['returned_qty'] > 0 && $item['remaining_qty'] > 0;
                                        $row_class = $is_fully_returned ? 'table-danger' : ($is_partially_returned ? 'table-warning' : '');
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td><?= $item_count++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                            <?php if ($item['product_code']): ?>
                                            <small class="text-muted">Code: <?= htmlspecialchars($item['product_code']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if (!empty($item['hsn_code'])): ?>
                                            <small class="text-muted">HSN: <?= htmlspecialchars($item['hsn_code']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($item['sale_type'] == 'wholesale'): ?>
                                            <br><small class="badge bg-info bg-opacity-10 text-info">Wholesale</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <small class="text-muted">#<?= $item['item_id'] ?></small><br>
                                            <small class="text-muted">PID: <?= $item['product_id'] ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold"><?= $item['sold_qty'] ?></span><br>
                                            <small class="text-muted"><?= htmlspecialchars(strtoupper($item['unit'])) ?></small>
                                        </td>
                                        <td class="text-center fw-bold"><?= $item['sold_qty'] ?> <?= htmlspecialchars(strtoupper($item['unit'])) ?></td>
                                        <td class="text-center text-danger fw-bold"><?= $item['returned_qty'] ?> <?= htmlspecialchars(strtoupper($item['unit'])) ?></td>
                                        <td class="text-center text-success fw-bold"><?= $item['remaining_qty'] ?> <?= htmlspecialchars(strtoupper($item['unit'])) ?></td>
                                        <td class="text-end">
                                            ₹<?= number_format($item['unit_price'], 2) ?><br>
                                            <small class="text-muted">per <?= htmlspecialchars(strtoupper($item['unit'])) ?></small>
                                        </td>
                                        <td class="text-end text-danger">-₹<?= number_format($item['discount_amount'] ?? 0, 2) ?></td>
                                        <td class="text-end fw-bold text-primary">₹<?= number_format($item['line_total_inclusive'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light fw-bold">
                                        <td colspan="9" class="text-end">Original Total:</td>
                                        <td class="text-end text-primary">₹<?= number_format($invoice_total, 2) ?></td>
                                    </tr>
                                    <tr class="table-success fw-bold fs-5">
                                        <td colspan="9" class="text-end">Active Total (After Returns):</td>
                                        <td class="text-end text-success">₹<?= number_format($active_total, 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center text-muted mt-3">
                            <small><i class="bx bx-info-circle me-1"></i> All prices are inclusive of GST (as per MRP)</small>
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
.table-danger { background-color: rgba(248, 215, 218, 0.3) !important; }
.table-warning { background-color: rgba(255, 243, 205, 0.3) !important; }
.avatar-sm {
    width: 48px;
    height: 48px;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
.bg-light {
    background-color: #f8f9fa !important;
}
</style>
<script>
$(document).ready(function() {
    // Auto-close alerts
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
});
</script>
</body>
</html>