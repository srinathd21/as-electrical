<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['current_business_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'seller';
$current_shop_id = $_SESSION['current_shop_id'] ?? null;

if (!$current_shop_id && $user_role !== 'admin') {
    header('Location: select_shop.php');
    exit();
}

// ==================== PERMISSION CHECK ====================
$is_admin = ($user_role === 'admin');
$is_shop_manager = in_array($user_role, ['admin', 'shop_manager']);
$is_seller = in_array($user_role, ['admin', 'shop_manager', 'seller', 'cashier']);

// ==================== GET FILTER DATA ====================
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$customer_id_filter = (int)($_GET['customer_id'] ?? 0);
$engineer_id_filter = (int)($_GET['engineer_id'] ?? 0);
$site_id_filter = (int)($_GET['site_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$payment_status_filter = $_GET['payment_status'] ?? '';

// ==================== GET ENGINEERS AND SITES FOR FILTER ====================
// Get engineers list
$engineers = [];
try {
    $eng_stmt = $pdo->prepare("SELECT engineer_id, first_name, last_name, phone FROM engineers WHERE status = 'active' ORDER BY first_name");
    $eng_stmt->execute();
    $engineers = $eng_stmt->fetchAll();
} catch (Exception $e) {
    // Engineers table might not exist
    $engineers = [];
}

// Get sites list
$sites = [];
try {
    $site_stmt = $pdo->prepare("SELECT site_id, site_name, site_address, engineer_id FROM sites WHERE status = 'active' ORDER BY site_name");
    $site_stmt->execute();
    $sites = $site_stmt->fetchAll();
} catch (Exception $e) {
    // Sites table might not exist
    $sites = [];
}

// Build WHERE clause
$where = "WHERE i.business_id = ?";
$params = [$business_id];

// Add date filter only if both dates are provided
if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND DATE(i.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($user_role !== 'admin') {
    $where .= " AND i.shop_id = ?";
    $params[] = $current_shop_id;
}

if ($customer_id_filter > 0) {
    $where .= " AND i.customer_id = ?";
    $params[] = $customer_id_filter;
}

// Add engineer filter
if ($engineer_id_filter > 0) {
    $where .= " AND i.engineer_id = ?";
    $params[] = $engineer_id_filter;
}

// Add site filter
if ($site_id_filter > 0) {
    $where .= " AND i.site_id = ?";
    $params[] = $site_id_filter;
}

// Add payment status filter
if ($payment_status_filter === 'paid') {
    $where .= " AND i.pending_amount = 0";
} elseif ($payment_status_filter === 'pending') {
    $where .= " AND i.pending_amount > 0";
}

if ($search) {
    $where .= " AND (i.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Stats
$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*) as count,
        SUM(i.total) as sales,
        SUM(i.total - i.pending_amount) as collected,
        SUM(i.pending_amount) as pending,
        SUM(CASE WHEN i.pending_amount = 0 THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN i.pending_amount > 0 THEN 1 ELSE 0 END) as pending_count
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    $where
");
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// Fetch invoices with customer_id, engineer name, and site name
$stmt = $pdo->prepare("
    SELECT 
        i.*, 
        c.name as customer_name, 
        c.phone as customer_phone, 
        c.id as customer_id,
        e.first_name as engineer_first_name,
        e.last_name as engineer_last_name,
        e.engineer_id,
        s.site_name,
        s.site_address,
        s.site_id
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN engineers e ON i.engineer_id = e.engineer_id
    LEFT JOIN sites s ON i.site_id = s.site_id
    $where
    ORDER BY i.created_at DESC, i.id DESC
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Customers for filter
$customers = $pdo->prepare("SELECT id, name, phone FROM customers WHERE business_id = ? ORDER BY name");
$customers->execute([$business_id]);
$customers_list = $customers->fetchAll();

// ==================== GET BUSINESS SETTINGS FOR WHATSAPP ====================
$business_settings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE business_id = ? LIMIT 1");
    $stmt->execute([$business_id]);
    $business_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
    $business_settings = [];
}

// Default business name if not set
$business_name = $_SESSION['current_business_name'] ?? 'Our Store';

// Check if any filters are applied
$has_filters = !empty($start_date) || !empty($end_date) || $customer_id_filter > 0 || $engineer_id_filter > 0 || $site_id_filter > 0 || !empty($search) || !empty($payment_status_filter);
?>

<!doctype html>
<html lang="en">
<?php $page_title = "Invoices"; include 'includes/head.php'; ?>
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
                        <div class="page-title-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h4 class="mb-0">
                                    <i class="bx bx-receipt me-2"></i> Invoices
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-buildings me-1"></i>
                                        <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                    </small>
                                </h4>
                                <?php if (!$has_filters): ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="bx bx-info-circle me-1"></i> Showing all invoices. Use filters above to narrow down results.
                                </small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="pos.php" class="btn btn-primary">
                                    <i class="bx bx-plus-circle me-1"></i> New Invoice
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
                
                <!-- WhatsApp Success/Error Messages -->
                <?php if (isset($_SESSION['whatsapp_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bx bxl-whatsapp me-2"></i> <?= htmlspecialchars($_SESSION['whatsapp_success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['whatsapp_success']); endif; ?>
                <?php if (isset($_SESSION['whatsapp_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bx bx-error-circle me-2"></i> <?= htmlspecialchars($_SESSION['whatsapp_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['whatsapp_error']); endif; ?>

                <!-- Filter Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bx bx-filter-alt me-1"></i> Filter Invoices
                            <?php if ($has_filters): ?>
                            <span class="badge bg-primary ms-2">Filters Applied</span>
                            <?php endif; ?>
                        </h5>
                        <form method="GET" id="filterForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Customer</label>
                                    <select name="customer_id" class="form-select">
                                        <option value="">All Customers</option>
                                        <?php foreach($customers_list as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $customer_id_filter == $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Engineer</label>
                                    <select name="engineer_id" class="form-select">
                                        <option value="">All Engineers</option>
                                        <?php foreach($engineers as $eng): ?>
                                        <option value="<?= $eng['engineer_id'] ?>" <?= $engineer_id_filter == $eng['engineer_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($eng['first_name'] . ' ' . ($eng['last_name'] ?? '')) ?>
                                            <?php if (!empty($eng['phone'])): ?>(<?= $eng['phone'] ?>)<?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Site</label>
                                    <select name="site_id" class="form-select">
                                        <option value="">All Sites</option>
                                        <?php foreach($sites as $site): ?>
                                        <option value="<?= $site['site_id'] ?>" <?= $site_id_filter == $site['site_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($site['site_name']) ?>
                                            <?php if (!empty($site['site_address'])): ?>(<?= htmlspecialchars(substr($site['site_address'], 0, 20)) ?>)<?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Payment Status</label>
                                    <select name="payment_status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="paid" <?= $payment_status_filter === 'paid' ? 'selected' : '' ?>>Paid Only</option>
                                        <option value="pending" <?= $payment_status_filter === 'pending' ? 'selected' : '' ?>>Pending / Partial</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bx bx-search"></i>
                                        </span>
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Invoice / Name / Phone"
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-12">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-filter me-1"></i> Apply Filters
                                        </button>
                                        <?php if ($has_filters): ?>
                                        <a href="invoices.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-reset"></i> Clear All Filters
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Active Filter Indicators -->
                            <?php if ($has_filters): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="bx bx-info-circle me-1"></i>
                                        <strong>Active Filters:</strong>
                                        <?php 
                                        $filter_texts = [];
                                        if (!empty($start_date) && !empty($end_date)) {
                                            $filter_texts[] = "Date: " . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
                                        }
                                        if ($customer_id_filter > 0) {
                                            foreach($customers_list as $c) {
                                                if ($c['id'] == $customer_id_filter) {
                                                    $filter_texts[] = "Customer: " . htmlspecialchars($c['name']);
                                                    break;
                                                }
                                            }
                                        }
                                        if ($engineer_id_filter > 0) {
                                            foreach($engineers as $eng) {
                                                if ($eng['engineer_id'] == $engineer_id_filter) {
                                                    $filter_texts[] = "Engineer: " . htmlspecialchars($eng['first_name'] . ' ' . ($eng['last_name'] ?? ''));
                                                    break;
                                                }
                                            }
                                        }
                                        if ($site_id_filter > 0) {
                                            foreach($sites as $site) {
                                                if ($site['site_id'] == $site_id_filter) {
                                                    $filter_texts[] = "Site: " . htmlspecialchars($site['site_name']);
                                                    break;
                                                }
                                            }
                                        }
                                        if ($payment_status_filter === 'paid') {
                                            $filter_texts[] = "Status: Paid Only";
                                        } elseif ($payment_status_filter === 'pending') {
                                            $filter_texts[] = "Status: Pending/Partial";
                                        }
                                        if (!empty($search)) {
                                            $filter_texts[] = "Search: \"" . htmlspecialchars($search) . "\"";
                                        }
                                        echo implode(' • ', $filter_texts);
                                        ?>
                                        <span class="badge bg-info ms-2"><?= $stats['count'] ?? 0 ?> invoices found</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4 g-3">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Sales</h6>
                                        <h3 class="mb-0 text-primary stats-total">₹<?= number_format($stats['sales'] ?? 0, 0) ?></h3>
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
                        <div class="card card-hover border-start border-success border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Collected</h6>
                                        <h3 class="mb-0 text-success stats-collected">₹<?= number_format($stats['collected'] ?? 0, 0) ?></h3>
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
                        <div class="card card-hover border-start border-warning border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Pending</h6>
                                        <h3 class="mb-0 text-warning stats-pending">₹<?= number_format($stats['pending'] ?? 0, 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-warning bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-error text-warning"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-info border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Invoices</h6>
                                        <h3 class="mb-0 text-info stats-count"><?= number_format($stats['count'] ?? 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-info bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-receipt text-info"></i>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($stats['paid_count'] > 0 || $stats['pending_count'] > 0): ?>
                                <div class="mt-2 small">
                                    <span class="text-success me-2"><i class="bx bx-check-circle"></i> Paid: <?= $stats['paid_count'] ?></span>
                                    <span class="text-warning"><i class="bx bx-time"></i> Pending: <?= $stats['pending_count'] ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats for Engineer/Site -->
                <?php if (!empty($engineers) || !empty($sites)): ?>
                <div class="row mb-4 g-3">
                    <?php if (!empty($engineers)): ?>
                    <div class="col-xl-6">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="bx bx-user me-2"></i> Engineer Distribution</h6>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php
                                    $engineer_counts = [];
                                    foreach ($invoices as $inv) {
                                        if (!empty($inv['engineer_first_name'])) {
                                            $eng_name = $inv['engineer_first_name'] . ' ' . ($inv['engineer_last_name'] ?? '');
                                            if (!isset($engineer_counts[$eng_name])) {
                                                $engineer_counts[$eng_name] = 0;
                                            }
                                            $engineer_counts[$eng_name]++;
                                        }
                                    }
                                    arsort($engineer_counts);
                                    $count = 0;
                                    foreach ($engineer_counts as $name => $cnt):
                                        if ($count >= 5) break;
                                    ?>
                                    <div class="text-center">
                                        <div class="avatar-sm mx-auto mb-2">
                                            <div class="avatar-title bg-info bg-opacity-10 rounded-circle">
                                                <i class="bx bx-user text-info"></i>
                                            </div>
                                        </div>
                                        <h6 class="mb-0"><?= htmlspecialchars($name) ?></h6>
                                        <small class="text-muted"><?= $cnt ?> invoices</small>
                                    </div>
                                    <?php 
                                        $count++;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($sites)): ?>
                    <div class="col-xl-6">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="bx bx-map me-2"></i> Site Distribution</h6>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php
                                    $site_counts = [];
                                    foreach ($invoices as $inv) {
                                        if (!empty($inv['site_name'])) {
                                            $site_name = $inv['site_name'];
                                            if (!isset($site_counts[$site_name])) {
                                                $site_counts[$site_name] = 0;
                                            }
                                            $site_counts[$site_name]++;
                                        }
                                    }
                                    arsort($site_counts);
                                    $count = 0;
                                    foreach ($site_counts as $name => $cnt):
                                        if ($count >= 5) break;
                                    ?>
                                    <div class="text-center">
                                        <div class="avatar-sm mx-auto mb-2">
                                            <div class="avatar-title bg-success bg-opacity-10 rounded-circle">
                                                <i class="bx bx-map text-success"></i>
                                            </div>
                                        </div>
                                        <h6 class="mb-0"><?= htmlspecialchars($name) ?></h6>
                                        <small class="text-muted"><?= $cnt ?> invoices</small>
                                    </div>
                                    <?php 
                                        $count++;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Invoices Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($invoices)): ?>
                        <div class="empty-state py-5">
                            <i class="bx bx-receipt bx-lg text-muted"></i>
                            <h5 class="mt-3">No Invoices Found</h5>
                            <p class="text-muted">
                                <?php if ($has_filters): ?>
                                    No invoices match your filter criteria. Try adjusting your filters.
                                <?php else: ?>
                                    No invoices have been created yet.
                                <?php endif; ?>
                            </p>
                            <?php if ($has_filters): ?>
                            <a href="invoices.php" class="btn btn-primary mt-2">
                                <i class="bx bx-reset me-1"></i> Clear Filters
                            </a>
                            <?php else: ?>
                            <a href="pos.php" class="btn btn-primary mt-2">
                                <i class="bx bx-plus-circle me-1"></i> Create First Invoice
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table id="invoicesTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 18%;">Invoice Details</th>
                                        <th class="text-center" style="width: 12%;">Customer</th>
                                        <th class="text-center" style="width: 12%;">Engineer</th>
                                        <th class="text-center" style="width: 12%;">Site</th>
                                        <th class="text-center" style="width: 8%;">Items</th>
                                        <th class="text-end" style="width: 18%;">Amount Details</th>
                                        <th class="text-center" style="width: 20%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    
                                    <?php 
                                    foreach ($invoices as $i => $inv):
                                        $total = (float)($inv['total'] ?? 0);
                                        $pending = (float)($inv['pending_amount'] ?? 0);
                                        $paid = $total - $pending;
                                        $item_count = 0;
                                        
                                        try {
                                            // Get total items count
                                            $item_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoice_items WHERE invoice_id = ?");
                                            $item_stmt->execute([$inv['id']]);
                                            $item_count = (int)$item_stmt->fetchColumn();
                                        } catch (Exception $e) {
                                            $item_count = 0;
                                        }
                                        
                                        // Check if any items have been returned
                                        $returned_count = 0;
                                        try {
                                            $return_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM returns WHERE invoice_id = ?");
                                            $return_stmt->execute([$inv['id']]);
                                            $returned_count = (int)$return_stmt->fetchColumn();
                                        } catch (Exception $e) {
                                            $returned_count = 0;
                                        }
                                        
                                        $payment_status = $pending == 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                                        $status_class = [
                                            'paid' => 'success',
                                            'partial' => 'warning',
                                            'unpaid' => 'danger'
                                        ][$payment_status] ?? 'secondary';
                                        
                                        // Check if customer has phone number for WhatsApp
                                        $has_whatsapp = !empty($inv['customer_phone']);
                                    ?>
                                    <tr class="invoice-row" data-id="<?= $inv['id'] ?>" data-customer-id="<?= $inv['customer_id'] ?? 0 ?>" data-payment-status="<?= $payment_status ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3 flex-shrink-0">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 48px; height: 48px;">
                                                        <i class="bx bx-receipt fs-4"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <strong class="d-block mb-1 text-primary"><?= htmlspecialchars($inv['invoice_number']) ?></strong>
                                                    <small class="text-muted d-block">
                                                        <i class="bx bx-calendar me-1"></i><?= date('d M Y', strtotime($inv['created_at'])) ?>
                                                        <i class="bx bx-time ms-2 me-1"></i><?= date('h:i A', strtotime($inv['created_at'])) ?>
                                                    </small>
                                                    <div class="d-flex gap-2 mt-2">
                                                        <span class="badge bg-<?= $status_class ?> bg-opacity-10 text-<?= $status_class ?> px-3 py-1 d-inline-block">
                                                            <i class="bx bx-<?= $status_class == 'success' ? 'check-circle' : ($status_class == 'warning' ? 'time-five' : 'x-circle') ?> me-1"></i>
                                                            <?= ucfirst($payment_status) ?>
                                                        </span>
                                                        <?php if ($returned_count > 0): ?>
                                                        <span class="badge bg-info bg-opacity-10 text-info px-3 py-1 d-inline-block">
                                                            <i class="bx bx-undo me-1"></i> Returned
                                                        </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div>
                                                <strong class="d-block mb-1"><?= htmlspecialchars($inv['customer_name'] ?? 'Walk-in Customer') ?></strong>
                                                <?php if ($inv['customer_phone']): ?>
                                                <small class="text-muted">
                                                    <i class="bx bx-phone me-1"></i><?= htmlspecialchars($inv['customer_phone']) ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($inv['engineer_first_name'])): ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($inv['engineer_first_name'] . ' ' . ($inv['engineer_last_name'] ?? '')) ?></strong>
                                                    <?php if ($engineer_id_filter == $inv['engineer_id']): ?>
                                                    <span class="badge bg-info bg-opacity-10 text-info d-block mt-1">Filtered</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($inv['site_name'])): ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($inv['site_name']) ?></strong>
                                                    <?php if (!empty($inv['site_address'])): ?>
                                                        <small class="text-muted d-block">
                                                            <i class="bx bx-map me-1"></i><?= htmlspecialchars(substr($inv['site_address'], 0, 30)) ?>
                                                            <?php if (strlen($inv['site_address']) > 30): ?>...<?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($site_id_filter == $inv['site_id']): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success d-block mt-1">Filtered</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2 fs-6">
                                                <i class="bx bx-package me-1"></i> <?= $item_count ?>
                                                <small class="ms-1">items</small>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="mb-2">
                                                <strong class="text-primary fs-5">₹<?= number_format($total, 2) ?></strong>
                                                <small class="text-muted d-block">Total</small>
                                            </div>
                                            <div class="d-flex justify-content-end gap-3 mb-1">
                                                <div class="text-end">
                                                    <span class="text-success fw-bold">₹<?= number_format($paid, 2) ?></span>
                                                    <small class="text-muted d-block">Paid</small>
                                                </div>
                                                <?php if ($pending > 0): ?>
                                                <div class="text-end">
                                                    <span class="text-danger fw-bold">₹<?= number_format($pending, 2) ?></span>
                                                    <small class="text-muted d-block">Due</small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group-vertical btn-group-sm mb-2" style="width: 100%;" role="group">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="invoice_print.php?invoice_id=<?= $inv['id'] ?>" target="_blank"
                                                       class="btn btn-outline-success" title="Print A4 Invoice">
                                                        <i class="bx bx-printer"></i>
                                                    </a>
                                                    <a href="thermal_print.php?invoice_id=<?= $inv['id'] ?>" target="_blank"
                                                       class="btn btn-outline-warning" title="Print Thermal Receipt (80mm)">
                                                        <i class="bx bx-receipt"></i>
                                                    </a>
                                                    <a href="invoice_view.php?invoice_id=<?= $inv['id'] ?>"
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    <?php if ($pending > 0): ?>
                                                    <a href="collect_payment.php?invoice_id=<?= $inv['id'] ?>"
                                                       class="btn btn-outline-warning" title="Collect Payment">
                                                        <i class="bx bx-money"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="payment_history.php?invoice_id=<?= $inv['id'] ?>"
                                                       class="btn btn-outline-info" title="Payment History">
                                                       <i class="bx bx-history"></i>
                                                    </a>
                                                </div>
                                                <div class="btn-group btn-group-sm mt-2" role="group">
                                                    <?php if ($has_whatsapp): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-success whatsapp-btn"
                                                            data-invoice-id="<?= $inv['id'] ?>"
                                                            data-invoice-number="<?= htmlspecialchars($inv['invoice_number']) ?>"
                                                            data-customer-name="<?= htmlspecialchars($inv['customer_name'] ?? 'Customer') ?>"
                                                            data-customer-phone="<?= htmlspecialchars($inv['customer_phone']) ?>"
                                                            data-total="<?= $total ?>"
                                                            title="Send Invoice via WhatsApp">
                                                        <i class="bx bxl-whatsapp"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-secondary" 
                                                            disabled
                                                            title="Customer phone number not available">
                                                        <i class="bx bxl-whatsapp"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($is_admin || $is_shop_manager): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger return-item-btn"
                                                            data-invoice-id="<?= $inv['id'] ?>"
                                                            data-invoice-number="<?= htmlspecialchars($inv['invoice_number']) ?>"
                                                            title="Return Items">
                                                        <i class="bx bx-undo"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Delete button will be added here via JavaScript on hover -->
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                   
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Export Options -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="bx bx-export me-1"></i> Export
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="exportTableToCSV('invoices_export.csv')">Export as CSV</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="window.print()">Print</a></li>
                                    </ul>
                                </div>
                                <span class="text-muted ms-3">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Showing <?= count($invoices) ?> invoices
                                    <?php if ($engineer_id_filter > 0 || $site_id_filter > 0): ?>
                                    with selected filters
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<!-- WhatsApp Send Modal -->
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-labelledby="whatsappModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="whatsappModalLabel">
                    <i class="bx bxl-whatsapp me-2"></i> Send Invoice via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="send_invoice_whatsapp.php" id="whatsappForm">
                <div class="modal-body">
                    <input type="hidden" name="invoice_id" id="whatsappInvoiceId">
                    <input type="hidden" name="customer_phone" id="whatsappCustomerPhone">
                    
                    <div class="text-center mb-4">
                        <div class="avatar-lg mx-auto mb-3">
                            <div class="avatar-title bg-success bg-opacity-10 rounded-circle fs-1">
                                <i class="bx bxl-whatsapp text-success"></i>
                            </div>
                        </div>
                        <h5 id="whatsappCustomerNameDisplay"></h5>
                        <p class="text-muted" id="whatsappInvoiceNumberDisplay"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Recipient Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-phone"></i></span>
                            <input type="text" class="form-control" id="whatsappPhoneDisplay" readonly>
                        </div>
                        <small class="text-muted">WhatsApp will open with this number</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Template</label>
                        <div class="bg-light p-3 rounded">
                            <p id="whatsappMessagePreview" class="mb-0 small"></p>
                        </div>
                        <small class="text-muted">You can edit the message before sending</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-1"></i>
                        The invoice link will be sent. Customer can view and download the invoice without login.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="sendWhatsappBtn">
                        <i class="bx bxl-whatsapp me-1"></i> Open WhatsApp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Items Modal -->
<div class="modal fade" id="returnItemsModal" tabindex="-1" aria-labelledby="returnItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="returnItemsModalLabel">
                    <i class="bx bx-undo me-2"></i> Return Items from Invoice
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process_return.php" id="returnForm">
                <div class="modal-body">
                    <input type="hidden" name="invoice_id" id="returnInvoiceId">
                    <input type="hidden" name="customer_id" id="returnCustomerId">
                    
                    <div class="text-center mb-4">
                        <div class="avatar-lg mx-auto mb-3">
                            <div class="avatar-title bg-warning bg-opacity-10 rounded-circle fs-1">
                                <i class="bx bx-undo text-warning"></i>
                            </div>
                        </div>
                        <h5>Invoice <span id="returnInvoiceNumber" class="text-primary"></span></h5>
                        <p class="text-muted">Select items to return</p>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Note:</strong> Returned items will be restocked to inventory and the invoice amount will be adjusted.
                        <?php if ($pending > 0): ?>
                        <span class="d-block mt-2 text-warning">This invoice has pending payment. Returns will affect the due amount.</span>
                        <?php endif; ?>
                    </div>
                    
                    <div id="returnItemsContainer">
                        <!-- Items will be loaded here via AJAX -->
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading invoice items...</p>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Return Reason</label>
                            <select name="return_reason" class="form-select" required>
                                <option value="">Select Reason</option>
                                <option value="defective">Defective Product</option>
                                <option value="damaged">Damaged in Transit</option>
                                <option value="wrong_item">Wrong Item Delivered</option>
                                <option value="customer_unsatisfied">Customer Unsatisfied</option>
                                <option value="exchange">Exchange</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Refund Method</label>
                            <select name="refund_method" class="form-select" required>
                                <option value="adjust">Adjust in Invoice (Reduce Amount)</option>
                                <option value="cash">Cash Refund</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="credit">Add to Customer Credit</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Enter any additional notes about the return..."></textarea>
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 rounded mt-4">
                        <div class="row">
                            <div class="col-6">
                                <strong>Total Return Amount:</strong>
                            </div>
                            <div class="col-6 text-end">
                                <h4 class="text-danger mb-0" id="totalReturnAmount">₹0.00</h4>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>New Invoice Total:</strong>
                            </div>
                            <div class="col-6 text-end">
                                <h4 class="text-primary mb-0" id="newInvoiceTotal">₹0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning" id="processReturnBtn">
                        <i class="bx bx-undo me-1"></i> Process Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Invoice Confirmation Modal -->
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bx bx-trash me-2"></i> Delete Invoice
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteInvoiceId">
                
                <div class="text-center mb-4">
                    <div class="avatar-lg mx-auto mb-3">
                        <div class="avatar-title bg-danger bg-opacity-10 rounded-circle fs-1">
                            <i class="bx bx-error-circle text-danger"></i>
                        </div>
                    </div>
                    <h5 class="mb-2">Are you sure?</h5>
                    <p class="text-muted">
                        You are about to delete invoice <strong id="deleteInvoiceNumber"></strong><br>
                        Total Amount: <strong id="deleteInvoiceTotal"></strong>
                    </p>
                </div>
                
                <div class="alert alert-warning">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action will:
                    <ul class="mb-0 mt-2">
                        <li>Delete the invoice permanently</li>
                        <li>Restock all items with exact quantities (including secondary units)</li>
                        <li>Reverse any loyalty points earned</li>
                        <li>Remove all payment records</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <i class="bx bx-time me-2"></i>
                    This action cannot be undone. Please confirm.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="bx bx-check me-1"></i> Yes, Delete Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php') ?>
<?php include('includes/scripts.php') ?>

<script>
$(document).ready(function() {
    // Initialize DataTable only if there are rows
    <?php if (!empty($invoices)): ?>
    var invoicesTable = $('#invoicesTable').DataTable({
        responsive: true,
        pageLength: 25,
        ordering: false,
        columnDefs: [
            { 
                targets: '_all', 
                orderable: false 
            }
        ],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search invoices:",
            lengthMenu: "Show _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ invoices",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });
    <?php endif; ?>

    $('[data-bs-toggle="tooltip"]').tooltip();

    // ==================== RETURN ITEM BUTTON HANDLER ====================
    $('#invoicesTable tbody').on('click', '.return-item-btn', function(e) {
        e.preventDefault();
        
        // Get data from button
        const invoiceId = $(this).data('invoice-id');
        const invoiceNumber = $(this).data('invoice-number');
        
        // Get customer ID from row
        const customerId = $(this).closest('tr').data('customer-id');
        
        // Set values in modal
        $('#returnInvoiceId').val(invoiceId);
        $('#returnCustomerId').val(customerId);
        $('#returnInvoiceNumber').text(invoiceNumber);
        
        // Show loading in container
        $('#returnItemsContainer').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading invoice items...</p>
            </div>
        `);
        
        // Reset totals
        $('#totalReturnAmount').text('₹0.00');
        $('#newInvoiceTotal').text('₹0.00');
        
        // Load invoice items via AJAX
        $.ajax({
            url: 'get_invoice_items_for_return.php',
            method: 'GET',
            data: { invoice_id: invoiceId },
            success: function(response) {
                if (response.success && response.items && response.items.length > 0) {
                    displayReturnItems(response.items, response.invoice_total);
                } else {
                    $('#returnItemsContainer').html(`
                        <div class="alert alert-warning">
                            <i class="bx bx-error-circle me-2"></i>
                            No items available for return or all items have already been returned.
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading items:', error);
                $('#returnItemsContainer').html(`
                    <div class="alert alert-danger">
                        <i class="bx bx-error-circle me-2"></i>
                        Error loading invoice items. Please try again.
                    </div>
                `);
            }
        });
        
        // Show modal
        const returnModal = new bootstrap.Modal(document.getElementById('returnItemsModal'));
        returnModal.show();
    });

    // Function to display return items
    function displayReturnItems(items, invoiceTotal) {
        let html = `
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;"><input type="checkbox" id="selectAllItems"></th>
                        <th style="width: 30%;">Product</th>
                        <th style="width: 10%;" class="text-center">Qty Sold</th>
                        <th style="width: 10%;" class="text-center">Unit Price</th>
                        <th style="width: 15%;" class="text-center">Max Return</th>
                        <th style="width: 15%;" class="text-center">Return Qty</th>
                        <th style="width: 15%;" class="text-end">Return Value</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        let totalReturn = 0;
        
        items.forEach(item => {
            // Determine max returnable quantity (sold - already returned)
            const maxReturn = item.quantity - (item.return_qty || 0);
            const unitPrice = parseFloat(item.unit_price);
            const disabled = maxReturn <= 0 ? 'disabled' : '';
            
            if (maxReturn > 0) {
                html += `
                    <tr class="return-item-row" data-item-id="${item.id}" data-unit-price="${unitPrice}" data-max-return="${maxReturn}">
                        <td class="text-center">
                            <input type="checkbox" class="item-select" name="selected_items[]" value="${item.id}" ${disabled}>
                        </td>
                        <td>
                            <strong>${escapeHtml(item.product_name || 'Product')}</strong>
                            <small class="d-block text-muted">${item.hsn_code ? 'HSN: ' + item.hsn_code : ''}</small>
                        </td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-center">₹${unitPrice.toFixed(2)}</td>
                        <td class="text-center">${maxReturn}</td>
                        <td class="text-center">
                            <input type="number" 
                                   class="form-control form-control-sm return-qty" 
                                   name="return_qty[${item.id}]" 
                                   min="1" 
                                   max="${maxReturn}" 
                                   value="${maxReturn}"
                                   data-item-id="${item.id}"
                                   data-unit-price="${unitPrice}"
                                   ${disabled}>
                        </td>
                        <td class="text-end return-value" id="return-value-${item.id}">₹${(maxReturn * unitPrice).toFixed(2)}</td>
                    </tr>
                `;
                totalReturn += maxReturn * unitPrice;
            }
        });
        
        html += `
                </tbody>
            </table>
        `;
        
        $('#returnItemsContainer').html(html);
        
        // Update totals
        const newTotal = parseFloat(invoiceTotal) - totalReturn;
        $('#totalReturnAmount').text('₹' + totalReturn.toFixed(2));
        $('#newInvoiceTotal').text('₹' + newTotal.toFixed(2));
        
        // Store totals
        $('#returnForm').data('invoice-total', invoiceTotal);
        
        // Select All functionality
        $('#selectAllItems').change(function() {
            $('.item-select:not(:disabled)').prop('checked', $(this).prop('checked'));
            calculateTotalReturn();
        });
        
        // Item select change
        $('.item-select').change(function() {
            calculateTotalReturn();
        });
        
        // Quantity change
        $('.return-qty').on('input', function() {
            const itemId = $(this).data('item-id');
            const unitPrice = $(this).data('unit-price');
            const maxReturn = parseInt($(this).attr('max'));
            let qty = parseInt($(this).val()) || 0;
            
            // Validate quantity
            if (qty < 1) qty = 1;
            if (qty > maxReturn) qty = maxReturn;
            $(this).val(qty);
            
            // Update return value for this item
            const returnValue = qty * unitPrice;
            $(`#return-value-${itemId}`).text('₹' + returnValue.toFixed(2));
            
            // Auto-select the checkbox if quantity > 0
            if (qty > 0) {
                $(this).closest('tr').find('.item-select').prop('checked', true);
            }
            
            calculateTotalReturn();
        });
    }
    
    // Function to calculate total return amount
    function calculateTotalReturn() {
        let total = 0;
        
        $('.return-item-row').each(function() {
            const checkbox = $(this).find('.item-select');
            const qtyInput = $(this).find('.return-qty');
            
            if (checkbox.prop('checked') && !checkbox.prop('disabled')) {
                const qty = parseInt(qtyInput.val()) || 0;
                const unitPrice = parseFloat(qtyInput.data('unit-price'));
                total += qty * unitPrice;
            }
        });
        
        const invoiceTotal = parseFloat($('#returnForm').data('invoice-total') || 0);
        const newTotal = invoiceTotal - total;
        
        $('#totalReturnAmount').text('₹' + total.toFixed(2));
        $('#newInvoiceTotal').text('₹' + newTotal.toFixed(2));
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== WHATSAPP BUTTON HANDLER ====================
    $('#invoicesTable tbody').on('click', '.whatsapp-btn', function(e) {
        e.preventDefault();
        
        // Get data from button
        const invoiceId = $(this).data('invoice-id');
        const invoiceNumber = $(this).data('invoice-number');
        const customerName = $(this).data('customer-name');
        const customerPhone = $(this).data('customer-phone');
        const total = $(this).data('total');
        
        // Set values in modal
        $('#whatsappInvoiceId').val(invoiceId);
        $('#whatsappCustomerPhone').val(customerPhone);
        $('#whatsappCustomerNameDisplay').text(customerName);
        $('#whatsappInvoiceNumberDisplay').text('Invoice #' + invoiceNumber);
        $('#whatsappPhoneDisplay').val(customerPhone);
        
        // Show loading state
        $('#whatsappMessagePreview').text('Generating invoice link...');
        $('#sendWhatsappBtn').prop('disabled', true);
        
        // Get or generate token from server
        $.ajax({
            url: 'get_invoice_token.php',
            method: 'GET',
            data: { invoice_id: invoiceId },
            success: function(response) {
                if (response.token) {
                    // Generate invoice URL with proper token
                    const baseUrl = window.location.origin + window.location.pathname.replace('invoices.php', '');
                    const invoiceUrl = baseUrl + 'public_invoice.php?id=' + invoiceId + '&token=' + response.token;
                    
                    // Format total amount
                    const formattedTotal = new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        minimumFractionDigits: 2
                    }).format(total);
                    
                    // Create message preview
                    const businessName = '<?= htmlspecialchars($business_name) ?>';
                    const date = new Date().toLocaleDateString('en-IN', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                    
                    const message = `Dear ${customerName},\n\nThank you for your purchase from ${businessName}!\n\nInvoice Details:\nInvoice No: ${invoiceNumber}\nDate: ${date}\nTotal Amount: ${formattedTotal}\n\nYou can view and download your invoice here:\n${invoiceUrl}\n\nFor any queries, please contact us.\n\nThank you for your business!`;
                    
                    $('#whatsappMessagePreview').text(message);
                    
                    // Store message for form submission
                    $('#whatsappForm').data('message', message);
                    $('#whatsappForm').data('token', response.token);
                    
                    // Enable send button
                    $('#sendWhatsappBtn').prop('disabled', false);
                } else {
                    $('#whatsappMessagePreview').text('Error: Could not generate invoice link. Please try again.');
                    $('#sendWhatsappBtn').prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error getting token:', error);
                $('#whatsappMessagePreview').text('Error generating invoice link. Please try again.');
                $('#sendWhatsappBtn').prop('disabled', true);
            }
        });
        
        // Show modal
        const whatsappModal = new bootstrap.Modal(document.getElementById('whatsappModal'));
        whatsappModal.show();
    });
    
    // Handle WhatsApp form submission
    $('#whatsappForm').submit(function(e) {
        e.preventDefault();
        
        const invoiceId = $('#whatsappInvoiceId').val();
        const customerPhone = $('#whatsappCustomerPhone').val();
        const message = $(this).data('message');
        const token = $(this).data('token');
        
        // Clean phone number - remove non-digits
        let cleanPhone = customerPhone.replace(/\D/g, '');
        
        // Ensure phone has country code (assume India +91 if not present)
        if (cleanPhone.length === 10) {
            cleanPhone = '91' + cleanPhone;
        } else if (cleanPhone.length === 11 && cleanPhone.startsWith('0')) {
            cleanPhone = '91' + cleanPhone.substring(1);
        }
        
        // Encode message for URL
        const encodedMessage = encodeURIComponent(message);
        
        // Create WhatsApp URL
        const whatsappUrl = `https://wa.me/${cleanPhone}?text=${encodedMessage}`;
        
        // Open WhatsApp in new tab
        window.open(whatsappUrl, '_blank');
        
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('whatsappModal')).hide();
        
        // Log the send action via AJAX
        $.ajax({
            url: 'log_whatsapp_send.php',
            method: 'POST',
            data: {
                invoice_id: invoiceId,
                phone: customerPhone,
                status: 'sent',
                token: token
            },
            success: function(response) {
                console.log('WhatsApp send logged');
            },
            error: function(xhr, status, error) {
                console.log('Error logging WhatsApp send:', error);
            }
        });
    });

    // Reset WhatsApp modal when closed
    $('#whatsappModal').on('hidden.bs.modal', function() {
        $('#sendWhatsappBtn').prop('disabled', false);
        $('#whatsappMessagePreview').text('');
        $('#whatsappForm').data('message', '');
        $('#whatsappForm').data('token', '');
    });

    // Auto-hide alerts
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);

    // ==================== DELETE INVOICE FUNCTIONALITY ====================
    // Add delete button to each invoice row on hover
    $('#invoicesTable tbody').on('mouseenter', 'tr', function() {
        // Check if delete button already exists
        if ($(this).find('.delete-invoice-btn').length === 0) {
            // Get invoice data
            const invoiceId = $(this).data('id');
            const invoiceNumber = $(this).find('td:first strong').text().trim();
            
            // Find total amount
            let totalElement = $(this).find('.text-primary.fs-5');
            if (totalElement.length === 0) {
                totalElement = $(this).find('td:eq(5) strong.text-primary');
            }
            const total = totalElement.text().trim();
            
            // Create delete button if user has permission
            <?php if (in_array($user_role, ['admin', 'shop_manager'])): ?>
            const deleteBtn = `
                <button type="button" 
                        class="btn btn-sm btn-outline-danger delete-invoice-btn ms-1" 
                        title="Delete Invoice" 
                        data-invoice-id="${invoiceId}" 
                        data-invoice-number="${invoiceNumber}"
                        data-total="${total}">
                    <i class="bx bx-trash"></i>
                </button>
            `;
            
            // Add button to actions column - find the last td and append to its btn-group
            const actionsCell = $(this).find('td:last .btn-group-vertical .btn-group:last');
            if (actionsCell.length) {
                actionsCell.append(deleteBtn);
            } else {
                // Fallback: append to the last td
                $(this).find('td:last').append(deleteBtn);
            }
            <?php endif; ?>
        }
    });

    // Handle delete button click
    $(document).on('click', '.delete-invoice-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const invoiceId = $(this).data('invoice-id');
        const invoiceNumber = $(this).data('invoice-number');
        const total = $(this).data('total');
        
        // Show confirmation modal with details
        $('#deleteInvoiceId').val(invoiceId);
        $('#deleteInvoiceNumber').text(invoiceNumber);
        $('#deleteInvoiceTotal').text(total);
        
        // Show the modal
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteInvoiceModal'));
        deleteModal.show();
    });

    // Handle delete confirmation
    $('#confirmDeleteBtn').click(function() {
        const invoiceId = $('#deleteInvoiceId').val();
        const btn = $(this);
        const originalText = btn.html();
        
        btn.html('<i class="bx bx-loader bx-spin me-2"></i> Deleting...');
        btn.prop('disabled', true);
        
        $.ajax({
            url: 'delete_invoice.php',
            method: 'POST',
            data: { invoice_id: invoiceId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide modal
                    bootstrap.Modal.getInstance(document.getElementById('deleteInvoiceModal')).hide();
                    
                    // Show success message
                    showNotification('success', response.message);
                    
                    // Remove the row from table
                    $(`tr[data-id="${invoiceId}"]`).fadeOut(500, function() {
                        $(this).remove();
                        
                        // Update DataTable
                        if (typeof invoicesTable !== 'undefined') {
                            invoicesTable.row($(this)).remove().draw(false);
                        }
                        
                        // Update stats
                        updateStats();
                    });
                } else {
                    showNotification('error', response.message);
                    btn.html(originalText);
                    btn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                console.error('Response:', xhr.responseText);
                
                let errorMessage = 'Failed to delete invoice. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch(e) {
                    // Not JSON, use default message
                }
                
                showNotification('error', errorMessage);
                btn.html(originalText);
                btn.prop('disabled', false);
            }
        });
    });

    // Add filter change event to show loading indicator
    $('#filterForm').on('submit', function() {
        $(this).find('button[type="submit"]').html('<i class="bx bx-loader bx-spin me-1"></i> Loading...').prop('disabled', true);
    });
});

// Function to show notifications
function showNotification(type, message) {
    const notificationHtml = `
        <div class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show fixed-notification" role="alert">
            <i class="bx bx-${type === 'success' ? 'check-circle' : 'error-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove any existing notifications
    $('.fixed-notification').remove();
    
    // Insert notification
    $('body').append(notificationHtml);
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        $('.fixed-notification').fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
}

// Function to update stats after deletion
function updateStats() {
    $.ajax({
        url: 'get_invoice_stats.php',
        method: 'GET',
        data: {
            start_date: $('input[name="start_date"]').val(),
            end_date: $('input[name="end_date"]').val(),
            customer_id: $('select[name="customer_id"]').val(),
            engineer_id: $('select[name="engineer_id"]').val(),
            site_id: $('select[name="site_id"]').val(),
            payment_status: $('select[name="payment_status"]').val()
        },
        success: function(response) {
            if (response) {
                // Update stats cards
                $('.stats-total').text('₹' + new Intl.NumberFormat('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(response.total || 0));
                $('.stats-collected').text('₹' + new Intl.NumberFormat('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(response.collected || 0));
                $('.stats-pending').text('₹' + new Intl.NumberFormat('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(response.pending || 0));
                $('.stats-count').text(response.count || 0);
                
                // Add highlight animation
                $('.card').addClass('stats-updated');
                setTimeout(() => {
                    $('.card').removeClass('stats-updated');
                }, 1000);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error updating stats:', error);
        }
    });
}

// Export table to CSV
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("#invoicesTable tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length; j++) {
            // Clean the text - remove HTML tags and extra spaces
            var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/\s+/g, ' ').trim();
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    var downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
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
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    vertical-align: middle;
}
.btn-group .btn {
    padding: 0.375rem 0.75rem;
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
.invoice-row .d-flex {
    min-width: 0;
}
.invoice-row .flex-grow-1 {
    min-width: 0;
}
.btn-group-vertical .btn-group {
    width: 100%;
}
.btn-group-vertical .btn-group .btn {
    flex: 1;
}
.whatsapp-btn {
    border-color: #25D366;
    color: #25D366;
}
.whatsapp-btn:hover {
    background-color: #25D366;
    color: white;
}
.whatsapp-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Return button styling */
.return-item-btn {
    border-color: #ffc107;
    color: #ffc107;
}
.return-item-btn:hover {
    background-color: #ffc107;
    color: #212529;
    border-color: #ffc107;
}

/* Delete button styling */
.delete-invoice-btn {
    transition: all 0.3s ease;
    margin-left: 5px;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.delete-invoice-btn:hover {
    background-color: #dc3545;
    color: white;
    border-color: #dc3545;
}
.delete-invoice-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Return items table styling */
#returnItemsContainer .table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
#returnItemsContainer .return-qty {
    width: 80px;
    margin: 0 auto;
}
#returnItemsContainer .return-value {
    font-weight: 600;
}

/* Fixed position notifications */
.fixed-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Animation for row deletion */
.fade-out-row {
    animation: fadeOutRow 0.5s ease forwards;
}
@keyframes fadeOutRow {
    from { 
        opacity: 1;
        transform: translateX(0);
    }
    to { 
        opacity: 0;
        transform: translateX(20px);
    }
}

/* Stats cards update animation */
.stats-updated {
    animation: highlightUpdate 1s ease;
}
@keyframes highlightUpdate {
    0% { 
        background-color: rgba(40, 167, 69, 0.2);
        transform: scale(1.02);
    }
    100% { 
        background-color: transparent;
        transform: scale(1);
    }
}

@media (max-width: 992px) {
    .page-title-box .d-flex {
        flex-direction: column;
        align-items: stretch !important;
        text-align: center;
    }
    .page-title-box .d-flex > div:last-child {
        margin-top: 1rem;
    }
}
@media (max-width: 576px) {
    .btn-group {
        display: flex;
        flex-direction: column;
        width: 100%;
    }
    .btn-group .btn {
        width: 100%;
        margin-bottom: 4px;
    }
}
</style>
</body>
</html>