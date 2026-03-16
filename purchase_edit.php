<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'] ?? 1;
$user_role = $_SESSION['role'] ?? '';

if (!in_array($user_role, ['admin', 'warehouse_manager'])) {
    header('Location: dashboard.php');
    exit();
}

$purchase_id = $_GET['id'] ?? 0;
$success = $error = '';

if (!$purchase_id || !is_numeric($purchase_id)) {
    header('Location: purchases.php');
    exit();
}

// Fetch existing purchase data
$stmt = $pdo->prepare("
    SELECT p.*, 
           m.name as manufacturer_name,
           m.contact_person,
           m.phone as m_phone, 
           m.email as m_email,
           m.address as m_address,
           m.gstin as m_gstin,
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

// Fetch existing items
$stmt_items = $pdo->prepare("
    SELECT pi.*, 
           p.product_name, 
           p.product_code, 
           p.hsn_code,
           p.unit_of_measure,
           p.stock_price as original_price
    FROM purchase_items pi
    JOIN products p ON pi.product_id = p.id AND p.business_id = ?
    WHERE pi.purchase_id = ? AND pi.business_id = ?
    ORDER BY pi.id
");
$stmt_items->execute([$business_id, $purchase_id, $business_id]);
$existing_items = $stmt_items->fetchAll();

// Fetch data for form
$manufacturers = $pdo->prepare("
    SELECT id, name 
    FROM manufacturers 
    WHERE business_id = ? 
      AND is_active = 1 
    ORDER BY name
");
$manufacturers->execute([$business_id]);
$manufacturers = $manufacturers->fetchAll();

$products = $pdo->prepare("
    SELECT p.id, p.product_name, p.product_code, p.hsn_code,
           p.stock_price, p.unit_of_measure,
           COALESCE(g.cgst_rate, 0) as cgst_rate,
           COALESCE(g.sgst_rate, 0) as sgst_rate,
           COALESCE(g.igst_rate, 0) as igst_rate
    FROM products p
    LEFT JOIN gst_rates g ON p.gst_id = g.id AND g.business_id = p.business_id
    WHERE p.business_id = ?
      AND p.is_active = 1
    ORDER BY p.product_name
");
$products->execute([$business_id]);
$products = $products->fetchAll();

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

// Calculate percentages
$paid_percent = $purchase['total_amount'] > 0 ? ($purchase['paid_amount'] / $purchase['total_amount']) * 100 : 0;
$pending_amount = $purchase['total_amount'] - $purchase['paid_amount'];
$pending_percent = $purchase['total_amount'] > 0 ? ($pending_amount / $purchase['total_amount']) * 100 : 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manufacturer_id = (int)($_POST['manufacturer_id'] ?? 0);
    $purchase_date   = $_POST['purchase_date'] ?? date('Y-m-d');
    $reference       = trim($_POST['reference'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');
    $items           = $_POST['items'] ?? [];

    if ($manufacturer_id <= 0 || empty($items)) {
        $error = "Please select supplier and add at least one product.";
    } else {
        try {
            $pdo->beginTransaction();

            // Update purchase header
            $stmt = $pdo->prepare("
                UPDATE purchases 
                SET manufacturer_id = ?, 
                    purchase_date = ?, 
                    reference = ?, 
                    notes = ?,
                    total_amount = 0,
                    total_gst = 0,
                    updated_at = NOW()
                WHERE id = ? AND business_id = ?
            ");
            $stmt->execute([
                $manufacturer_id, 
                $purchase_date, 
                $reference, 
                $notes,
                $purchase_id,
                $business_id
            ]);

            // Get existing item IDs to track what to delete
            $existing_ids = array_column($existing_items, 'id');
            $new_ids = [];

            // Update or insert items
            $grand_total = 0;
            $total_gst   = 0;
            
            foreach ($items as $item) {
                $item_id = (int)($item['item_id'] ?? 0);
                $product_id = (int)($item['product_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                $unit_price = (float)($item['unit_price'] ?? 0);
                $cgst_rate = (float)($item['cgst_rate'] ?? 0);
                $sgst_rate = (float)($item['sgst_rate'] ?? 0);
                $igst_rate = (float)($item['igst_rate'] ?? 0);

                if ($product_id > 0 && $quantity > 0 && $unit_price >= 0) {
                    $taxable_amount = $quantity * $unit_price;
                    $cgst_amount = $taxable_amount * $cgst_rate / 100;
                    $sgst_amount = $taxable_amount * $sgst_rate / 100;
                    $igst_amount = $taxable_amount * $igst_rate / 100;
                    $total_with_tax = $taxable_amount + $cgst_amount + $sgst_amount + $igst_amount;

                    // Get HSN code
                    $hsn_stmt = $pdo->prepare("SELECT hsn_code FROM products WHERE id = ? AND business_id = ?");
                    $hsn_stmt->execute([$product_id, $business_id]);
                    $hsn_code = $hsn_stmt->fetchColumn() ?? '';

                    if ($item_id > 0) {
                        // Update existing item
                        $stmt = $pdo->prepare("
                            UPDATE purchase_items 
                            SET product_id = ?, 
                                quantity = ?, 
                                unit_price = ?, 
                                hsn_code = ?,
                                cgst_rate = ?, 
                                sgst_rate = ?, 
                                igst_rate = ?,
                                cgst_amount = ?, 
                                sgst_amount = ?, 
                                igst_amount = ?,
                                total_price = ?,
                                updated_at = NOW()
                            WHERE id = ? AND purchase_id = ? AND business_id = ?
                        ");
                        $stmt->execute([
                            $product_id, $quantity, $unit_price, $hsn_code,
                            $cgst_rate, $sgst_rate, $igst_rate,
                            $cgst_amount, $sgst_amount, $igst_amount,
                            $total_with_tax,
                            $item_id, $purchase_id, $business_id
                        ]);
                        
                        $new_ids[] = $item_id;
                    } else {
                        // Insert new item
                        $stmt = $pdo->prepare("
                            INSERT INTO purchase_items 
                            (purchase_id, product_id, quantity, unit_price, hsn_code,
                             cgst_rate, sgst_rate, igst_rate, 
                             cgst_amount, sgst_amount, igst_amount, total_price, business_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $purchase_id, $product_id, $quantity, $unit_price, $hsn_code,
                            $cgst_rate, $sgst_rate, $igst_rate,
                            $cgst_amount, $sgst_amount, $igst_amount,
                            $total_with_tax, $business_id
                        ]);
                    }

                    $grand_total += $total_with_tax;
                    $total_gst   += $cgst_amount + $sgst_amount + $igst_amount;
                }
            }

            // Delete items that were removed
            $items_to_delete = array_diff($existing_ids, $new_ids);
            if (!empty($items_to_delete)) {
                $placeholders = str_repeat('?,', count($items_to_delete) - 1) . '?';
                $stmt = $pdo->prepare("
                    DELETE FROM purchase_items 
                    WHERE id IN ($placeholders) 
                      AND purchase_id = ? 
                      AND business_id = ?
                ");
                $params = array_merge($items_to_delete, [$purchase_id, $business_id]);
                $stmt->execute($params);
            }

            // Update final totals
            $pdo->prepare("
                UPDATE purchases 
                SET total_amount = ?, 
                    total_gst = ?,
                    updated_at = NOW()
                WHERE id = ? AND business_id = ?
            ")->execute([$grand_total, $total_gst, $purchase_id, $business_id]);

            $pdo->commit();
            $_SESSION['success'] = "Purchase order #{$purchase['purchase_number']} updated successfully!";
            header("Location: purchase_view.php?id=" . $purchase_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update purchase: " . $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="en">
<?php 
$page_title = "Edit Purchase Order #{$purchase['purchase_number']}"; 
include 'includes/head.php'; 
?>
<!-- Add Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.select2-container--default .select2-selection--single {
    height: 38px;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
}
.item-row {
    transition: all 0.3s ease;
}
.item-row:hover {
    background-color: rgba(0,0,0,0.02);
}
.tax-input {
    width: 70px;
    text-align: center;
}
.tax-label {
    font-size: 0.75rem;
    color: #6c757d;
}
</style>
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
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <i class="bx bx-edit me-2"></i> Edit Purchase Order
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
                                <a href="purchase_view.php?id=<?= $purchase_id ?>" class="btn btn-outline-secondary">
                                    <i class="bx bx-arrow-back me-1"></i> Back to View
                                </a>
                                <a href="purchases.php" class="btn btn-outline-primary">
                                    <i class="bx bx-list-ul me-1"></i> All Purchases
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bx bx-error-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

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
                                            <?= count($existing_items) ?> items
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

                <form method="POST" id="purchaseForm">
                    <div class="row g-4">
                        <!-- Purchase Details Card -->
                        <div class="col-lg-4">
                            <div class="card card-hover border-start border-primary border-4 shadow-sm h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="bx bx-detail me-2"></i> Edit Purchase Details
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Purchase Number</label>
                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($purchase['purchase_number']) ?>" readonly>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Purchase Date <span class="text-danger">*</span></label>
                                        <input type="date" name="purchase_date" class="form-control" 
                                               value="<?= htmlspecialchars($purchase['purchase_date']) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Supplier <span class="text-danger">*</span></label>
                                        <select name="manufacturer_id" class="form-select select2-supplier" required>
                                            <option value="">-- Select Supplier --</option>
                                            <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?= $m['id'] ?>" <?= $purchase['manufacturer_id'] == $m['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Bill/Reference No.</label>
                                        <input type="text" name="reference" class="form-control" 
                                               value="<?= htmlspecialchars($purchase['reference']) ?>" 
                                               placeholder="Optional">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Notes (Optional)</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="Any special instructions..."><?= htmlspecialchars($purchase['notes']) ?></textarea>
                                    </div>

                                    <div class="alert alert-warning">
                                        <i class="bx bx-info-circle me-2"></i>
                                        <small>
                                            <strong>Note:</strong> Editing this purchase order will update the totals but will NOT adjust stock levels automatically.
                                            You'll need to manually adjust stock if quantities have changed.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Products Section -->
                        <div class="col-lg-8">
                            <div class="card shadow-sm">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="bx bx-package me-2"></i> Edit Purchase Items
                                            <small class="text-muted ms-2">(Click on values to edit)</small>
                                        </h5>
                                        <span class="badge bg-primary" id="itemCount"><?= count($existing_items) ?> Items</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Add New Product Section -->
                                    <div class="row g-3 mb-4 p-3 border rounded bg-light">
                                        <div class="col-md-6">
                                            <label class="form-label">Add New Product</label>
                                            <select class="form-select select2-products" id="commonProductSelect">
                                                <option value="">-- Select Product to Add --</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($p['product_name']) ?>"
                                                        data-code="<?= htmlspecialchars($p['product_code']) ?>"
                                                        data-price="<?= $p['stock_price'] ?>"
                                                        data-cgst="<?= $p['cgst_rate'] ?>"
                                                        data-sgst="<?= $p['sgst_rate'] ?>"
                                                        data-igst="<?= $p['igst_rate'] ?>"
                                                        data-hsn="<?= $p['hsn_code'] ?>">
                                                    <?= htmlspecialchars($p['product_name']) ?> 
                                                    <?php if (!empty($p['product_code'])): ?>
                                                    (<?= htmlspecialchars($p['product_code']) ?>)
                                                    <?php endif; ?>
                                                    - ₹<?= number_format($p['stock_price'], 2) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Price</label>
                                            <input type="number" step="0.01" id="commonPrice" class="form-control" value="0">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Qty</label>
                                            <input type="number" id="commonQuantity" class="form-control" min="1" value="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" id="addProductBtn" class="btn btn-primary w-100">
                                                <i class="bx bx-plus me-1"></i> Add
                                            </button>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="row g-2 mt-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small">CGST %</label>
                                                    <input type="number" step="0.01" id="commonCGST" class="form-control" value="9">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">SGST %</label>
                                                    <input type="number" step="0.01" id="commonSGST" class="form-control" value="9">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">IGST %</label>
                                                    <input type="number" step="0.01" id="commonIGST" class="form-control" value="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selected Products Table -->
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle" id="selectedProductsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="25%">Product</th>
                                                    <th width="10%" class="text-center">Qty</th>
                                                    <th width="15%" class="text-center">Price</th>
                                                    <th width="20%" class="text-center">Tax Rate</th>
                                                    <th width="15%" class="text-end">Total</th>
                                                    <th width="10%" class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="selectedProductsBody">
                                                <?php if (empty($existing_items)): ?>
                                                <tr id="emptyRow" class="text-center">
                                                    <td colspan="7" class="py-4">
                                                        <i class="bx bx-package fs-1 text-muted mb-3 d-block"></i>
                                                        <p class="text-muted">No products added yet</p>
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($existing_items as $i => $item): 
                                                    $total = $item['total_price'];
                                                    $tax_rate = ($item['cgst_rate'] + $item['sgst_rate'] + $item['igst_rate']);
                                                ?>
                                                <tr class="item-row" data-id="<?= $item['product_id'] ?>">
                                                    <td class="text-center fw-bold"><?= $i + 1 ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                                        <?php if ($item['product_code']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small>
                                                        <?php endif; ?>
                                                        <input type="hidden" name="items[<?= $i ?>][item_id]" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $item['product_id'] ?>">
                                                        <input type="hidden" name="items[<?= $i ?>][quantity]" class="qty-hidden" value="<?= $item['quantity'] ?>">
                                                        <input type="hidden" name="items[<?= $i ?>][unit_price]" class="price-hidden" value="<?= $item['unit_price'] ?>">
                                                        <input type="hidden" name="items[<?= $i ?>][cgst_rate]" class="cgst-hidden" value="<?= $item['cgst_rate'] ?>">
                                                        <input type="hidden" name="items[<?= $i ?>][sgst_rate]" class="sgst-hidden" value="<?= $item['sgst_rate'] ?>">
                                                        <input type="hidden" name="items[<?= $i ?>][igst_rate]" class="igst-hidden" value="<?= $item['igst_rate'] ?>">
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-action="decrease">
                                                                <i class="bx bx-minus"></i>
                                                            </button>
                                                            <span class="badge bg-primary rounded-pill px-3 py-1 mx-2 qty-display"><?= $item['quantity'] ?></span>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-action="increase">
                                                                <i class="bx bx-plus"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="input-group input-group-sm justify-content-center">
                                                            <span class="input-group-text">₹</span>
                                                            <input type="number" step="0.01" 
                                                                   class="form-control price-input text-end" 
                                                                   value="<?= $item['unit_price'] ?>"
                                                                   style="width: 100px;">
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex flex-column align-items-center gap-1">
                                                            <div class="d-flex align-items-center gap-1">
                                                                <span class="tax-label">C:</span>
                                                                <input type="number" step="0.01" 
                                                                       class="form-control form-control-sm tax-input cgst-input" 
                                                                       value="<?= $item['cgst_rate'] ?>">
                                                                <span class="tax-label">%</span>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-1">
                                                                <span class="tax-label">S:</span>
                                                                <input type="number" step="0.01" 
                                                                       class="form-control form-control-sm tax-input sgst-input" 
                                                                       value="<?= $item['sgst_rate'] ?>">
                                                                <span class="tax-label">%</span>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-1">
                                                                <span class="tax-label">I:</span>
                                                                <input type="number" step="0.01" 
                                                                       class="form-control form-control-sm tax-input igst-input" 
                                                                       value="<?= $item['igst_rate'] ?>">
                                                                <span class="tax-label">%</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-end fw-bold item-total">
                                                        ₹<span class="total-display"><?= number_format($total, 2) ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-btn">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <td colspan="5" class="text-end fw-bold">Grand Total:</td>
                                                    <td class="text-end fw-bold" id="grandTotal">
                                                        ₹<span id="grandTotalValue"><?= number_format($purchase['total_amount'], 2) ?></span>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <i class="bx bx-info-circle me-2"></i>
                                        <strong>Purchase Summary:</strong>
                                        <span id="stockSummary">
                                            <?= count($existing_items) ?> items | Total: ₹<?= number_format($purchase['total_amount'], 2) ?>
                                            | Items Total: ₹<span id="itemsTotalValue"><?= number_format($purchase['total_amount'] - $purchase['total_gst'], 2) ?></span>
                                            | GST: ₹<span id="gstTotalValue"><?= number_format($purchase['total_gst'], 2) ?></span>
                                        </span>
                                    </div>

                                    <hr>

                                    <div class="text-end">
                                        <a href="purchase_view.php?id=<?= $purchase_id ?>" class="btn btn-outline-secondary me-2">
                                            <i class="bx bx-x me-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                            <i class="bx bx-save me-2"></i> Update Purchase Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<!-- Add Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2-products, .select2-supplier').select2({
        placeholder: "Select...",
        allowClear: true,
        width: '100%',
        theme: 'classic'
    });

    let selectedProducts = new Map();
    let itemIndex = <?= count($existing_items) ?>;
    
    // Initialize with existing items
    <?php foreach ($existing_items as $item): ?>
    selectedProducts.set('<?= $item['id'] ?>', {
        item_id: '<?= $item['id'] ?>',
        id: '<?= $item['product_id'] ?>',
        name: '<?= addslashes($item['product_name']) ?>',
        code: '<?= addslashes($item['product_code']) ?>',
        price: <?= $item['unit_price'] ?>,
        quantity: <?= $item['quantity'] ?>,
        cgst: <?= $item['cgst_rate'] ?>,
        sgst: <?= $item['sgst_rate'] ?>,
        igst: <?= $item['igst_rate'] ?>,
        total: <?= $item['total_price'] ?>
    });
    <?php endforeach; ?>

    // Update common fields when product selected
    $(document).on('change', '#commonProductSelect', function() {
        const option = $(this).find('option:selected');
        if (option.length && option.val()) {
            $('#commonPrice').val(option.data('price') || 0);
            $('#commonCGST').val(option.data('cgst') || 0);
            $('#commonSGST').val(option.data('sgst') || 0);
            $('#commonIGST').val(option.data('igst') || 0);
        }
    });

    // Add product button
    $(document).on('click', '#addProductBtn', function() {
        const select = $('#commonProductSelect');
        const option = select.find('option:selected');
        
        if (!option.length || !option.val()) {
            alert('Please select a product first');
            return;
        }

        const productId = option.val();
        const productName = option.data('name');
        const productCode = option.data('code');
        const price = parseFloat($('#commonPrice').val()) || 0;
        const quantity = parseInt($('#commonQuantity').val()) || 1;
        const cgst = parseFloat($('#commonCGST').val()) || 0;
        const sgst = parseFloat($('#commonSGST').val()) || 0;
        const igst = parseFloat($('#commonIGST').val()) || 0;
        const hsn = option.data('hsn') || '';

        if (price <= 0) {
            alert('Price must be greater than 0');
            return;
        }

        if (quantity <= 0) {
            alert('Quantity must be greater than 0');
            return;
        }

        // Check if product already added
        const existingItem = Array.from(selectedProducts.values()).find(p => p.id == productId);
        if (existingItem) {
            alert('This product is already in the list');
            return;
        }

        // Calculate total
        const taxable = price * quantity;
        const total = taxable * (1 + (cgst + sgst + igst) / 100);

        // Generate unique ID for new item
        const newId = 'new_' + Date.now();
        
        // Add to selected products
        selectedProducts.set(newId, {
            item_id: newId,
            id: productId,
            name: productName,
            code: productCode,
            hsn: hsn,
            price: price,
            quantity: quantity,
            cgst: cgst,
            sgst: sgst,
            igst: igst,
            total: total
        });

        // Update table
        updateProductsTable();
        updateSummary();

        // Reset common selector
        select.val(null).trigger('change');
        $('#commonPrice').val('0');
        $('#commonQuantity').val(1);
        $('#commonCGST').val('9');
        $('#commonSGST').val('9');
        $('#commonIGST').val('0');
    });

    // Update products table
    function updateProductsTable() {
        const tbody = $('#selectedProductsBody');
        tbody.empty();
        let itemsTotal = 0;
        let gstTotal = 0;
        let rowIndex = 0;

        if (selectedProducts.size === 0) {
            tbody.append('<tr id="emptyRow" class="text-center"><td colspan="7" class="py-4"><i class="bx bx-package fs-1 text-muted mb-3 d-block"></i><p class="text-muted">No products added yet</p></td></tr>');
            $('#grandTotalValue').text('0.00');
            $('#itemsTotalValue').text('0.00');
            $('#gstTotalValue').text('0.00');
            $('#itemCount').text('0 Items');
            $('#submitBtn').prop('disabled', true);
            return;
        }

        selectedProducts.forEach((product, productKey) => {
            const taxable = product.price * product.quantity;
            const itemGst = taxable * (product.cgst + product.sgst + product.igst) / 100;
            itemsTotal += taxable;
            gstTotal += itemGst;
            
            const isExisting = product.item_id.toString().startsWith('new_') ? false : true;
            const itemIdField = isExisting ? 
                `<input type="hidden" name="items[${rowIndex}][item_id]" value="${product.item_id}">` : '';
            
            const row = $(`
                <tr class="item-row" data-key="${productKey}">
                    <td class="text-center fw-bold">${rowIndex + 1}</td>
                    <td>
                        <strong>${product.name}</strong>
                        ${product.code ? `<br><small class="text-muted">${product.code}</small>` : ''}
                        ${isExisting ? '' : '<span class="badge bg-warning badge-sm mt-1">New</span>'}
                        ${itemIdField}
                        <input type="hidden" name="items[${rowIndex}][product_id]" value="${product.id}">
                        <input type="hidden" name="items[${rowIndex}][quantity]" class="qty-hidden" value="${product.quantity}">
                        <input type="hidden" name="items[${rowIndex}][unit_price]" class="price-hidden" value="${product.price}">
                        <input type="hidden" name="items[${rowIndex}][cgst_rate]" class="cgst-hidden" value="${product.cgst}">
                        <input type="hidden" name="items[${rowIndex}][sgst_rate]" class="sgst-hidden" value="${product.sgst}">
                        <input type="hidden" name="items[${rowIndex}][igst_rate]" class="igst-hidden" value="${product.igst}">
                    </td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-action="decrease">
                                <i class="bx bx-minus"></i>
                            </button>
                            <span class="badge bg-primary rounded-pill px-3 py-1 mx-2 qty-display">${product.quantity}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-action="increase">
                                <i class="bx bx-plus"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="input-group input-group-sm justify-content-center">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" 
                                   class="form-control price-input text-end" 
                                   value="${product.price.toFixed(2)}"
                                   style="width: 100px;">
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <div class="d-flex align-items-center gap-1">
                                <span class="tax-label">C:</span>
                                <input type="number" step="0.01" 
                                       class="form-control form-control-sm tax-input cgst-input" 
                                       value="${product.cgst}">
                                <span class="tax-label">%</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="tax-label">S:</span>
                                <input type="number" step="0.01" 
                                       class="form-control form-control-sm tax-input sgst-input" 
                                       value="${product.sgst}">
                                <span class="tax-label">%</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="tax-label">I:</span>
                                <input type="number" step="0.01" 
                                       class="form-control form-control-sm tax-input igst-input" 
                                       value="${product.igst}">
                                <span class="tax-label">%</span>
                            </div>
                        </div>
                    </td>
                    <td class="text-end fw-bold item-total">
                        ₹<span class="total-display">${product.total.toFixed(2)}</span>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-btn">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            tbody.append(row);
            rowIndex++;
        });

        const grandTotal = itemsTotal + gstTotal;
        $('#grandTotalValue').text(grandTotal.toFixed(2));
        $('#itemsTotalValue').text(itemsTotal.toFixed(2));
        $('#gstTotalValue').text(gstTotal.toFixed(2));
        
        const itemCount = selectedProducts.size;
        $('#itemCount').text(`${itemCount} Item${itemCount !== 1 ? 's' : ''}`);
        $('#submitBtn').prop('disabled', itemCount === 0);
    }

    // Update summary
    function updateSummary() {
        if (selectedProducts.size === 0) {
            $('#stockSummary').html('No products selected');
            return;
        }

        let totalQty = 0;
        let itemsTotal = 0;
        let gstTotal = 0;

        selectedProducts.forEach((product) => {
            const taxable = product.price * product.quantity;
            totalQty += product.quantity;
            itemsTotal += taxable;
            gstTotal += taxable * (product.cgst + product.sgst + product.igst) / 100;
        });

        const grandTotal = itemsTotal + gstTotal;
        const summary = `Total Items: <strong>${selectedProducts.size}</strong> | Total Quantity: <strong>${totalQty}</strong> | Items Total: <strong>₹${itemsTotal.toFixed(2)}</strong> | GST: <strong>₹${gstTotal.toFixed(2)}</strong> | Grand Total: <strong>₹${grandTotal.toFixed(2)}</strong>`;
        $('#stockSummary').html(summary);
    }

    // Handle quantity buttons
    $(document).on('click', '.qty-btn', function() {
        const row = $(this).closest('tr');
        const productKey = row.data('key');
        const product = selectedProducts.get(productKey);
        const action = $(this).data('action');
        
        if (action === 'increase') {
            product.quantity += 1;
        } else if (action === 'decrease' && product.quantity > 1) {
            product.quantity -= 1;
        }
        
        // Update hidden field
        row.find('.qty-hidden').val(product.quantity);
        row.find('.qty-display').text(product.quantity);
        
        // Recalculate and update
        updateProductTotal(product);
        updateProductsTable();
        updateSummary();
    });

    // Handle price input changes
    $(document).on('change', '.price-input', function() {
        const row = $(this).closest('tr');
        const productKey = row.data('key');
        const product = selectedProducts.get(productKey);
        
        product.price = parseFloat($(this).val()) || 0;
        if (product.price < 0) product.price = 0;
        
        // Update hidden field
        row.find('.price-hidden').val(product.price);
        
        // Recalculate and update
        updateProductTotal(product);
        updateProductsTable();
        updateSummary();
    });

    // Handle tax input changes
    $(document).on('change', '.cgst-input, .sgst-input, .igst-input', function() {
        const row = $(this).closest('tr');
        const productKey = row.data('key');
        const product = selectedProducts.get(productKey);
        
        product.cgst = parseFloat(row.find('.cgst-input').val()) || 0;
        product.sgst = parseFloat(row.find('.sgst-input').val()) || 0;
        product.igst = parseFloat(row.find('.igst-input').val()) || 0;
        
        // Update hidden fields
        row.find('.cgst-hidden').val(product.cgst);
        row.find('.sgst-hidden').val(product.sgst);
        row.find('.igst-hidden').val(product.igst);
        
        // Recalculate and update
        updateProductTotal(product);
        updateProductsTable();
        updateSummary();
    });

    // Helper function to update product total
    function updateProductTotal(product) {
        const taxable = product.price * product.quantity;
        product.total = taxable * (1 + (product.cgst + product.sgst + product.igst) / 100);
    }

    // Handle remove button
    $(document).on('click', '.remove-btn', function() {
        const row = $(this).closest('tr');
        const productKey = row.data('key');
        
        if (confirm('Are you sure you want to remove this item from the purchase?')) {
            selectedProducts.delete(productKey);
            updateProductsTable();
            updateSummary();
        }
    });

    // Form validation
    $('#purchaseForm').on('submit', function(e) {
        if (selectedProducts.size === 0) {
            e.preventDefault();
            alert('Please add at least one product to the purchase');
            return;
        }

        // Validate required fields
        if (!$('select[name="manufacturer_id"]').val()) {
            e.preventDefault();
            alert('Please select a supplier');
            return;
        }
    });
});
</script>
</body>
</html>