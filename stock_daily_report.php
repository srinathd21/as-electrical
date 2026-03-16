<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$allowed_roles = ['admin', 'warehouse_manager', 'shop_manager','stock_manager'];
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['error'] = "Access denied.";
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'] ?? 1;
$user_shop_id = $_SESSION['current_shop_id'] ?? null;
$selected_date = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{1,2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Fetch shops for current business
$shop_query = "SELECT id, shop_name FROM shops WHERE business_id = ? AND is_active = 1 ORDER BY shop_name";
$shops = $pdo->prepare($shop_query);
$shops->execute([$business_id]);
$shops = $shops->fetchAll();

// === SHOP-WISE REPORT ===
$shop_report = [];
$total_opening = $total_inward = $total_outward = $total_closing = 0;

foreach ($shops as $shop) {
    $shop_id = $shop['id'];

    // Opening = Closing of previous day
    $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
    $opening_query = "
        SELECT COALESCE(SUM(ps.quantity), 0)
        FROM product_stocks ps
        JOIN products p ON ps.product_id = p.id
        WHERE ps.shop_id = ? 
          AND p.business_id = ?
          AND ps.last_updated <= ?
    ";
    $opening = (float)$pdo->prepare($opening_query)->execute([$shop_id, $business_id, $prev_date . ' 23:59:59']) 
               ? $pdo->query("SELECT COALESCE(SUM(ps.quantity), 0) FROM product_stocks ps JOIN products p ON ps.product_id = p.id WHERE ps.shop_id = $shop_id AND p.business_id = $business_id AND ps.last_updated <= '$prev_date 23:59:59'")->fetchColumn() 
               : 0;

    // Inward
    $inward_query = "
        SELECT COALESCE(SUM(sa.quantity), 0)
        FROM stock_adjustments sa
        JOIN products p ON sa.product_id = p.id
        WHERE sa.shop_id = ? 
          AND p.business_id = ?
          AND sa.adjustment_type IN ('add', 'transfer_in')
          AND DATE(sa.adjusted_at) = ?
    ";
    $inward = (float)$pdo->prepare($inward_query)->execute([$shop_id, $business_id, $selected_date]) 
              ? $pdo->query("SELECT COALESCE(SUM(sa.quantity), 0) FROM stock_adjustments sa JOIN products p ON sa.product_id = p.id WHERE sa.shop_id = $shop_id AND p.business_id = $business_id AND sa.adjustment_type IN ('add', 'transfer_in') AND DATE(sa.adjusted_at) = '$selected_date'")->fetchColumn() 
              : 0;

    // Outward
    $outward_query = "
        SELECT COALESCE(SUM(sa.quantity), 0)
        FROM stock_adjustments sa
        JOIN products p ON sa.product_id = p.id
        WHERE sa.shop_id = ? 
          AND p.business_id = ?
          AND sa.adjustment_type IN ('remove', 'transfer_out', 'damage', 'expiry')
          AND DATE(sa.adjusted_at) = ?
    ";
    $outward = (float)$pdo->prepare($outward_query)->execute([$shop_id, $business_id, $selected_date]) 
               ? $pdo->query("SELECT COALESCE(SUM(sa.quantity), 0) FROM stock_adjustments sa JOIN products p ON sa.product_id = p.id WHERE sa.shop_id = $shop_id AND p.business_id = $business_id AND sa.adjustment_type IN ('remove', 'transfer_out', 'damage', 'expiry') AND DATE(sa.adjusted_at) = '$selected_date'")->fetchColumn() 
               : 0;

    $closing = $opening + $inward - $outward;

    $shop_report[] = [
        'shop_id' => $shop_id,
        'shop_name' => $shop['shop_name'],
        'opening' => $opening,
        'inward' => $inward,
        'outward' => $outward,
        'closing' => $closing
    ];

    $total_opening += $opening;
    $total_inward += $inward;
    $total_outward += $outward;
    $total_closing += $closing;
}

// === PRODUCT-WISE REPORT (All Shops Combined) ===
$product_query = "
    SELECT 
        p.id,
        p.product_name,
        p.product_code,
        COALESCE(SUM(ps.quantity), 0) AS current_stock,
        COALESCE((
            SELECT SUM(sa.quantity)
            FROM stock_adjustments sa
            WHERE sa.product_id = p.id
              AND p.business_id = ?
              AND sa.adjustment_type IN ('add', 'transfer_in')
              AND DATE(sa.adjusted_at) = ?
        ), 0) AS inward,
        COALESCE((
            SELECT SUM(sa.quantity)
            FROM stock_adjustments sa
            WHERE sa.product_id = p.id
              AND p.business_id = ?
              AND sa.adjustment_type IN ('remove', 'transfer_out', 'damage', 'expiry')
              AND DATE(sa.adjusted_at) = ?
        ), 0) AS outward
    FROM products p
    LEFT JOIN product_stocks ps ON ps.product_id = p.id
    WHERE p.business_id = ?
    GROUP BY p.id
    HAVING inward > 0 OR outward > 0 OR current_stock > 0
    ORDER BY p.product_name
";

$product_report = $pdo->prepare($product_query);
$product_report->execute([$business_id, $selected_date, $business_id, $selected_date, $business_id]);
$product_report = $product_report->fetchAll();

foreach ($product_report as &$prod) {
    $prod['opening'] = $prod['current_stock'] - $prod['inward'] + $prod['outward'];
    $prod['closing'] = $prod['current_stock'];
}

// Messages
$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
?>

<!doctype html>
<html lang="en">
<?php 
$page_title = "Daily Stock Report - " . date('d M Y', strtotime($selected_date)); 
include 'includes/head.php'; 
?>
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
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">
                                <i class="bx bx-calendar-check me-2"></i> Daily Stock Report
                                <small class="text-muted ms-2">
                                    <i class="bx bx-buildings me-1"></i> 
                                    <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                </small>
                            </h4>
                            <div class="d-flex gap-2">
                                <form method="GET" class="d-inline">
                                    <input type="date" name="date" class="form-control" 
                                           value="<?= $selected_date ?>" onchange="this.form.submit()">
                                </form>
                                <button onclick="exportDailyReport()" class="btn btn-primary">
                                    <i class="bx bx-download me-1"></i> Export Excel
                                </button>
                                
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

                <!-- Date Navigation -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    <i class="bx bx-calendar me-2"></i><?= date('d M Y', strtotime($selected_date)) ?>
                                </h5>
                                <small class="text-muted">
                                    <?= date('l', strtotime($selected_date)) ?> • 
                                    <?= date('F', strtotime($selected_date)) ?> • 
                                    <?= date('W', strtotime($selected_date)) ?>th Week
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="?date=<?= date('Y-m-d', strtotime($selected_date . ' -1 day')) ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="bx bx-chevron-left me-1"></i> Previous Day
                                </a>
                                <?php if ($selected_date != date('Y-m-d')): ?>
                                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">
                                    <i class="bx bx-calendar me-1"></i> Today
                                </a>
                                <a href="?date=<?= date('Y-m-d', strtotime($selected_date . ' +1 day')) ?>" 
                                   class="btn btn-outline-secondary">
                                    Next Day <i class="bx bx-chevron-right ms-1"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Opening Stock</h6>
                                        <h3 class="mb-0 text-primary"><?= number_format($total_opening) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-primary bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-box text-primary"></i>
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
                                        <h6 class="text-muted mb-1">Inward (+)</h6>
                                        <h3 class="mb-0 text-success">+<?= number_format($total_inward) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-success bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-up-arrow-alt text-success"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-danger border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Outward (-)</h6>
                                        <h3 class="mb-0 text-danger">-<?= number_format($total_outward) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-danger bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-down-arrow-alt text-danger"></i>
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
                                        <h6 class="text-muted mb-1">Closing Stock</h6>
                                        <h3 class="mb-0 text-info"><?= number_format($total_closing) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-info bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-check-circle text-info"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shop-wise Report -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bx bx-store me-2"></i> Shop-wise Stock Movement
                            <small class="text-muted ms-2"><?= count($shop_report) ?> Locations</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="shopReportTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Shop/Location</th>
                                        <th class="text-center">Opening</th>
                                        <th class="text-center">Inward (+) </th>
                                        <th class="text-center">Outward (-)</th>
                                        <th class="text-center">Closing</th>
                                        <th class="text-center">Net Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shop_report as $row): 
                                        $net_change = $row['inward'] - $row['outward'];
                                        $net_change_class = $net_change > 0 ? 'text-success' : ($net_change < 0 ? 'text-danger' : 'text-muted');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 40px; height: 40px;">
                                                        <i class="bx bx-store fs-4"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <strong class="d-block"><?= htmlspecialchars($row['shop_name']) ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                                <?= number_format($row['opening']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                                <i class="bx bx-up-arrow-alt me-1"></i>+<?= number_format($row['inward']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">
                                                <i class="bx bx-down-arrow-alt me-1"></i>-<?= number_format($row['outward']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 fw-bold">
                                                <?= number_format($row['closing']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $net_change > 0 ? 'success' : ($net_change < 0 ? 'danger' : 'secondary') ?> bg-opacity-10 text-<?= $net_change > 0 ? 'success' : ($net_change < 0 ? 'danger' : 'secondary') ?> px-3 py-2">
                                                <?= $net_change > 0 ? '+' : '' ?><?= number_format($net_change) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th class="text-end">TOTAL</th>
                                        <th class="text-center"><?= number_format($total_opening) ?></th>
                                        <th class="text-center text-success">+<?= number_format($total_inward) ?></th>
                                        <th class="text-center text-danger">-<?= number_format($total_outward) ?></th>
                                        <th class="text-center text-primary fw-bold"><?= number_format($total_closing) ?></th>
                                        <th class="text-center <?= ($total_inward - $total_outward) > 0 ? 'text-success' : (($total_inward - $total_outward) < 0 ? 'text-danger' : 'text-muted') ?>">
                                            <?= ($total_inward - $total_outward) > 0 ? '+' : '' ?><?= number_format($total_inward - $total_outward) ?>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Product-wise Report -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bx bx-package me-2"></i> Product-wise Movement
                            <small class="text-muted ms-2"><?= count($product_report) ?> Products</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($product_report)): ?>
                        <div class="table-responsive">
                            <table id="productReportTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Product Details</th>
                                        <th class="text-center">Opening</th>
                                        <th class="text-center">Inward (+) </th>
                                        <th class="text-center">Outward (-)</th>
                                        <th class="text-center">Closing</th>
                                        <th class="text-center">Movement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($product_report as $i => $prod): 
                                        $movement = $prod['inward'] > 0 || $prod['outward'] > 0 ? 'Yes' : 'No';
                                        $movement_class = $movement === 'Yes' ? 'success' : 'secondary';
                                        $net_change = $prod['inward'] - $prod['outward'];
                                    ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3">
                                                    <div class="bg-light text-dark rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 40px; height: 40px;">
                                                        <i class="bx bx-package fs-4"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <strong class="d-block mb-1"><?= htmlspecialchars($prod['product_name']) ?></strong>
                                                    <?php if ($prod['product_code']): ?>
                                                    <small class="text-muted">
                                                        <i class="bx bx-hash me-1"></i><?= htmlspecialchars($prod['product_code']) ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                                <?= number_format($prod['opening']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                                <i class="bx bx-up-arrow-alt me-1"></i>+<?= number_format($prod['inward']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">
                                                <i class="bx bx-down-arrow-alt me-1"></i>-<?= number_format($prod['outward']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 fw-bold">
                                                <?= number_format($prod['closing']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="badge bg-<?= $movement_class ?> bg-opacity-10 text-<?= $movement_class ?> px-3 py-2 mb-1">
                                                    <?= $movement ?>
                                                </span>
                                                <?php if ($movement === 'Yes'): ?>
                                                <small class="text-muted">
                                                    <?= $net_change > 0 ? 'Net +' . number_format($net_change) : ($net_change < 0 ? 'Net ' . number_format($net_change) : 'No Change') ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state text-center py-5">
                            <i class="bx bx-package fs-1 text-muted mb-3"></i>
                            <h5>No Product Movement Found</h5>
                            <p class="text-muted">No stock adjustments were made on <?= date('d M Y', strtotime($selected_date)) ?></p>
                            <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">
                                <i class="bx bx-calendar me-1"></i> View Today's Report
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Report Summary -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3"><i class="bx bx-info-circle me-2"></i> Report Summary</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Report Date</small>
                                        <strong><?= date('d M Y, l', strtotime($selected_date)) ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Generated On</small>
                                        <strong><?= date('d M Y, h:i A') ?></strong>
                                    </div>
                                    <div class="col-6 mt-3">
                                        <small class="text-muted d-block">Total Locations</small>
                                        <strong><?= count($shop_report) ?></strong>
                                    </div>
                                    <div class="col-6 mt-3">
                                        <small class="text-muted d-block">Active Products</small>
                                        <strong><?= count($product_report) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3"><i class="bx bx-bar-chart me-2"></i> Daily Summary</h6>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="text-center p-2 border rounded">
                                            <small class="text-muted d-block">Opening</small>
                                            <h5 class="mb-0 text-primary"><?= number_format($total_opening) ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-center p-2 border rounded">
                                            <small class="text-muted d-block">Movement</small>
                                            <h5 class="mb-0 <?= ($total_inward - $total_outward) > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= ($total_inward - $total_outward) > 0 ? '+' : '' ?><?= number_format($total_inward - $total_outward) ?>
                                            </h5>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-center p-2 border rounded">
                                            <small class="text-muted d-block">Closing</small>
                                            <h5 class="mb-0 text-info"><?= number_format($total_closing) ?></h5>
                                        </div>
                                    </div>
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

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#shopReportTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search shops:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ shops",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    $('#productReportTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search products:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ products",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    // Auto-close alerts after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
});

function exportDailyReport() {
    const btn = event.target.closest('button');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bx bx-loader bx-spin me-1"></i> Exporting...';
    btn.disabled = true;
    
    // Build export URL with current date
    const exportUrl = 'stock_daily_report_export.php?date=<?= $selected_date ?>&business_id=<?= $business_id ?>';
    
    window.location = exportUrl;
    
    // Reset button after 3 seconds
    setTimeout(() => {
        btn.innerHTML = original;
        btn.disabled = false;
    }, 3000);
}
</script>

<style>
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}
.empty-state i {
    font-size: 4rem;
    opacity: 0.5;
}
.avatar-sm {
    width: 40px;
    height: 40px;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
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
</style>
</body>
</html>