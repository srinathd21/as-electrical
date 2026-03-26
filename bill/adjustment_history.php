<!-- adjustment_history.php -->
<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit(); 
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'warehouse_manager', 'stock_manager', 'shop_manager'])) {
    $_SESSION['error'] = "Access denied.";
    header('Location: dashboard.php');
    exit();
}

$current_business_id = (int) $_SESSION['current_business_id'];
$current_shop_id = $_SESSION['current_shop_id'] ?? null;
if (!$current_shop_id) { 
    header('Location: select_shop.php'); 
    exit(); 
}

// Get shop info
$shop_stmt = $pdo->prepare("SELECT shop_name FROM shops WHERE id = ? AND business_id = ?");
$shop_stmt->execute([$current_shop_id, $current_business_id]);
$shop_name = $shop_stmt->fetchColumn();

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_product = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$filter_reason = $_GET['reason'] ?? '';

// Build base query
$count_sql = "SELECT COUNT(*) FROM stock_adjustments sa 
              WHERE sa.shop_id = ?";
$data_sql = "SELECT sa.*, p.product_name, p.product_code, p.barcode,
                    u.full_name as adjusted_by_name, u.username
             FROM stock_adjustments sa
             LEFT JOIN products p ON sa.product_id = p.id
             LEFT JOIN users u ON sa.adjusted_by = u.id
             WHERE sa.shop_id = ?";

$params = [$current_shop_id];
$filter_conditions = [];

// Apply filters
if (!empty($filter_type)) {
    $filter_conditions[] = "sa.adjustment_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_date_from)) {
    $filter_conditions[] = "DATE(sa.adjusted_at) >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $filter_conditions[] = "DATE(sa.adjusted_at) <= ?";
    $params[] = $filter_date_to;
}

if ($filter_product > 0) {
    $filter_conditions[] = "sa.product_id = ?";
    $params[] = $filter_product;
}

if (!empty($filter_reason)) {
    $filter_conditions[] = "sa.reason = ?";
    $params[] = $filter_reason;
}

// Add conditions to queries
if (!empty($filter_conditions)) {
    $condition_str = " AND " . implode(" AND ", $filter_conditions);
    $count_sql .= $condition_str;
    $data_sql .= $condition_str;
}

// Get total records for pagination
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute(array_slice($params, 0, count($params)));
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get paginated data
$data_sql .= " ORDER BY sa.adjusted_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$adjustments_stmt = $pdo->prepare($data_sql);
$adjustments_stmt->execute($params);
$adjustments = $adjustments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for filter dropdown
$products_stmt = $pdo->prepare("
    SELECT id, product_name, product_code 
    FROM products 
    WHERE business_id = ? AND is_active = 1 
    ORDER BY product_name
");
$products_stmt->execute([$current_business_id]);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique reasons for filter dropdown
$reasons_stmt = $pdo->prepare("
    SELECT DISTINCT reason 
    FROM stock_adjustments 
    WHERE shop_id = ? AND reason IS NOT NULL AND reason != ''
    ORDER BY reason
");
$reasons_stmt->execute([$current_shop_id]);
$reasons = $reasons_stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate summary statistics
$summary_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_adjustments,
        SUM(CASE WHEN adjustment_type = 'add' THEN quantity ELSE 0 END) as total_added,
        SUM(CASE WHEN adjustment_type = 'remove' THEN quantity ELSE 0 END) as total_removed,
        COUNT(DISTINCT product_id) as products_affected
    FROM stock_adjustments 
    WHERE shop_id = ?
");
$summary_stmt->execute([$current_shop_id]);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<?php 
$page_title = "Adjustment History - " . htmlspecialchars($shop_name); 
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
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <i class="bx bx-history me-2"></i> Stock Adjustment History
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-store me-1"></i><?= htmlspecialchars($shop_name) ?>
                                    </small>
                                </h4>
                                <p class="text-muted mb-0">View and filter all stock adjustments</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="stock_adjustment.php" class="btn btn-primary">
                                    <i class="bx bx-plus me-1"></i> New Adjustment
                                </a>
                                <a href="javascript:void(0)" onclick="exportToCSV()" class="btn btn-outline-secondary">
                                    <i class="bx bx-export me-1"></i> Export
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bx bx-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); endif; ?>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-light text-primary rounded-circle fs-3">
                                            <i class="bx bx-transfer"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Total Adjustments</p>
                                        <h4 class="mb-0"><?= number_format($summary['total_adjustments'] ?? 0) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-light text-success rounded-circle fs-3">
                                            <i class="bx bx-up-arrow-alt"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Total Added</p>
                                        <h4 class="mb-0">+<?= number_format($summary['total_added'] ?? 0) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-light text-danger rounded-circle fs-3">
                                            <i class="bx bx-down-arrow-alt"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Total Removed</p>
                                        <h4 class="mb-0">-<?= number_format($summary['total_removed'] ?? 0) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-light text-info rounded-circle fs-3">
                                            <i class="bx bx-package"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Products Affected</p>
                                        <h4 class="mb-0"><?= number_format($summary['products_affected'] ?? 0) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="add" <?= $filter_type === 'add' ? 'selected' : '' ?>>Add</option>
                                    <option value="remove" <?= $filter_type === 'remove' ? 'selected' : '' ?>>Remove</option>
                                    <option value="set" <?= $filter_type === 'set' ? 'selected' : '' ?>>Set</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Product</label>
                                <select name="product_id" class="form-select select2">
                                    <option value="">All Products</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>" <?= $filter_product === $product['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($product['product_name']) ?>
                                        <?= !empty($product['product_code']) ? '(' . htmlspecialchars($product['product_code']) . ')' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Reason</label>
                                <select name="reason" class="form-select">
                                    <option value="">All Reasons</option>
                                    <?php foreach ($reasons as $reason): ?>
                                    <option value="<?= htmlspecialchars($reason) ?>" <?= $filter_reason === $reason ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($reason) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bx bx-filter-alt"></i>
                                </button>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <a href="adjustment_history.php" class="btn btn-outline-secondary w-100">
                                    <i class="bx bx-reset"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Adjustments Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Date & Time</th>
                                        <th>Product</th>
                                        <th>Type</th>
                                        <th class="text-end">Old Stock</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">New Stock</th>
                                        <th>Reason</th>
                                        <th>Adjusted By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($adjustments)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="bx bx-history display-4 mb-3"></i>
                                                <h5>No adjustments found</h5>
                                                <p>Try adjusting your filters or create a new adjustment</p>
                                                <a href="stock_adjustment.php" class="btn btn-primary btn-sm">
                                                    <i class="bx bx-plus"></i> New Adjustment
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($adjustments as $adj): 
                                        $type_class = $adj['adjustment_type'] === 'add' ? 'success' : ($adj['adjustment_type'] === 'remove' ? 'danger' : 'warning');
                                        $type_icon = $adj['adjustment_type'] === 'add' ? 'bx-up-arrow-alt' : ($adj['adjustment_type'] === 'remove' ? 'bx-down-arrow-alt' : 'bx-target-lock');
                                    ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted fw-monospace"><?= htmlspecialchars($adj['adjustment_number']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= date('d M Y', strtotime($adj['adjusted_at'])) ?></div>
                                            <small class="text-muted"><?= date('h:i A', strtotime($adj['adjusted_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($adj['product_name']) ?></div>
                                            <small class="text-muted">
                                                Code: <?= htmlspecialchars($adj['product_code'] ?? 'N/A') ?>
                                                <?php if (!empty($adj['barcode'])): ?>
                                                <br>Barcode: <?= htmlspecialchars($adj['barcode']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $type_class ?> bg-opacity-10 text-<?= $type_class ?>">
                                                <i class="bx <?= $type_icon ?> me-1"></i>
                                                <?= ucfirst($adj['adjustment_type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-medium"><?= number_format($adj['old_stock']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($adj['adjustment_type'] === 'add'): ?>
                                            <span class="text-success fw-bold">+<?= number_format($adj['quantity']) ?></span>
                                            <?php elseif ($adj['adjustment_type'] === 'remove'): ?>
                                            <span class="text-danger fw-bold">-<?= number_format(abs($adj['quantity'])) ?></span>
                                            <?php else: ?>
                                            <span class="text-warning fw-bold">→ <?= number_format($adj['quantity']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-medium"><?= number_format($adj['new_stock']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= htmlspecialchars($adj['reason']) ?></span>
                                            <?php if (!empty($adj['notes'])): ?>
                                            <i class="bx bx-message-dots ms-1 text-muted" 
                                               data-bs-toggle="tooltip" 
                                               title="<?= htmlspecialchars($adj['notes']) ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($adj['adjusted_by_name'] ?? 'Unknown') ?></div>
                                            <small class="text-muted">@<?= htmlspecialchars($adj['username'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="viewDetails(<?= htmlspecialchars(json_encode($adj)) ?>)"
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <i class="bx bx-show"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="bx bx-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i >= $page - 2 && $i <= $page + 2): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        <i class="bx bx-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <div class="text-muted text-center mt-3">
                            Showing <?= count($adjustments) ?> of <?= number_format($total_records) ?> adjustments
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-info-circle me-2"></i>Adjustment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <!-- Content will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<!-- Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search product...'
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// View adjustment details
function viewDetails(adjustment) {
    const modalBody = document.getElementById('detailsModalBody');
    const typeClass = adjustment.adjustment_type === 'add' ? 'success' : (adjustment.adjustment_type === 'remove' ? 'danger' : 'warning');
    const typeIcon = adjustment.adjustment_type === 'add' ? 'bx-up-arrow-alt' : (adjustment.adjustment_type === 'remove' ? 'bx-down-arrow-alt' : 'bx-target-lock');
    const quantityChange = adjustment.adjustment_type === 'add' ? '+' + adjustment.quantity : (adjustment.adjustment_type === 'remove' ? '-' + adjustment.quantity : '→ ' + adjustment.quantity);
    
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Adjustment Information</h6>
                        <p><strong>Number:</strong> ${adjustment.adjustment_number}</p>
                        <p><strong>Date:</strong> ${new Date(adjustment.adjusted_at).toLocaleString()}</p>
                        <p><strong>Type:</strong> 
                            <span class="badge bg-${typeClass}">${adjustment.adjustment_type.toUpperCase()}</span>
                        </p>
                        <p><strong>Reason:</strong> ${adjustment.reason || 'N/A'}</p>
                        <p><strong>Notes:</strong> ${adjustment.notes || 'No notes'}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Product Information</h6>
                        <p><strong>Name:</strong> ${adjustment.product_name}</p>
                        <p><strong>Code:</strong> ${adjustment.product_code || 'N/A'}</p>
                        <p><strong>Barcode:</strong> ${adjustment.barcode || 'N/A'}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="card border-primary mb-3">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">Old Stock</h6>
                        <h3 class="text-primary mb-0">${adjustment.old_stock}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-${typeClass} mb-3">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">Change</h6>
                        <h3 class="text-${typeClass} mb-0">${quantityChange}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success mb-3">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">New Stock</h6>
                        <h3 class="text-success mb-0">${adjustment.new_stock}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Adjusted By</h6>
                        <p class="mb-0"><strong>${adjustment.adjusted_by_name || 'Unknown'}</strong> (${adjustment.username || ''})</p>
                        <small class="text-muted">User ID: ${adjustment.adjusted_by}</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

// Export to CSV function
function exportToCSV() {
    const filters = new URLSearchParams(window.location.search);
    const params = {};
    for (let [key, value] of filters.entries()) {
        params[key] = value;
    }
    params['export'] = 'csv';
    
    window.location.href = 'export_adjustments.php?' + new URLSearchParams(params);
}

// Auto-submit filters when select changes
document.querySelectorAll('select[name="type"], select[name="product_id"], select[name="reason"]').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});

// Date validation
document.querySelector('input[name="date_to"]').addEventListener('change', function() {
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    const dateTo = this.value;
    
    if (dateFrom && dateTo && dateTo < dateFrom) {
        alert('To Date cannot be earlier than From Date');
        this.value = '';
    }
});
</script>

<style>
.card-hover {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.fw-monospace {
    font-family: monospace;
}
.select2-container--bootstrap-5 .select2-selection {
    min-height: 38px;
    border: 1px solid #ced4da;
}
.select2-container--bootstrap-5 .select2-selection:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.table > :not(caption) > * > * {
    padding: 1rem 0.75rem;
}
</style>
</body>
</html>