<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$business_id = $_SESSION['business_id'] ?? 1;
$allowed_roles = ['admin', 'seller', 'staff', 'warehouse_manager', 'field_executive','shop_manager'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit();
}

// Handle Delete Action
if (isset($_POST['delete']) && $user_role === 'field_executive' && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $visit_id = (int)$_POST['delete'];
    
    $stmt = $pdo->prepare("
        SELECT sv.id 
        FROM store_visits sv 
        LEFT JOIN store_requirements sr ON sr.store_visit_id = sv.id
        WHERE sv.id = ? AND sv.field_executive_id = ? 
        AND sr.invoice_id IS NULL 
        AND sr.requirement_status = 'pending'
    ");
    $stmt->execute([$visit_id, $user_id]);
    
    if ($stmt->fetch()) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM store_requirements WHERE store_visit_id = ?")->execute([$visit_id]);
            $pdo->prepare("DELETE FROM store_visits WHERE id = ?")->execute([$visit_id]);
            $pdo->commit();
            $_SESSION['success'] = "Visit deleted successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to delete visit: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Cannot delete visit: Invalid or non-pending visit.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['approve_visit']) && $user_role === 'seller' && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $visit_id = (int)$_POST['approve_visit'];

    try {
        $pdo->beginTransaction();

        // Verify visit exists and has pending items
        $check_stmt = $pdo->prepare("
            SELECT 1
            FROM store_visits sv
            JOIN store_requirements sr ON sr.store_visit_id = sv.id
            WHERE sv.id = ? AND sr.requirement_status = 'pending'
        ");
        $check_stmt->execute([$visit_id]);
        
        if ($check_stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE store_requirements
                SET requirement_status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE store_visit_id = ? AND requirement_status = 'pending'
            ");
            $stmt->execute([$user_id, $visit_id]);

            $_SESSION['success'] = "Items approved successfully.";
        } else {
            $_SESSION['error'] = "No pending items found for this visit.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to approve items: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Staff Status Update
if (isset($_POST['update_status']) && in_array($user_role, ['staff', 'warehouse_manager']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $visit_id = (int)$_POST['visit_id'];
    $new_status = $_POST['new_status'];
    $tracking_number = $_POST['tracking_number'] ?? null;
    
    $allowed_statuses = ['packed', 'shipped', 'delivered'];
    if (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['error'] = "Invalid status selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Verify visit exists and has approved items
        $check_stmt = $pdo->prepare("
            SELECT 1
            FROM store_visits sv
            JOIN store_requirements sr ON sr.store_visit_id = sv.id
            WHERE sv.id = ? AND sr.requirement_status IN ('approved', 'packed', 'shipped')
        ");
        $check_stmt->execute([$visit_id]);

        if ($check_stmt->fetch()) {
            $update_data = [
                'requirement_status' => $new_status,
                $new_status . '_by' => $user_id,
                $new_status . '_at' => date('Y-m-d H:i:s'),
                'store_visit_id' => $visit_id
            ];
            $query = "
                UPDATE store_requirements
                SET requirement_status = :requirement_status,
                    {$new_status}_by = :{$new_status}_by,
                    {$new_status}_at = :{$new_status}_at
            ";
            if ($new_status === 'shipped' && $tracking_number) {
                $query .= ", tracking_number = :tracking_number";
                $update_data['tracking_number'] = $tracking_number;
            }
            $query .= " WHERE store_visit_id = :store_visit_id AND requirement_status IN ('approved', 'packed', 'shipped')";

            $stmt = $pdo->prepare($query);
            $stmt->execute($update_data);

            $_SESSION['success'] = "Status updated to $new_status successfully.";
        } else {
            $_SESSION['error'] = "No eligible items to update.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to update status: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// === FILTERS ===
$store_filter = (int)($_GET['store'] ?? 0);
$executive_filter = (int)($_GET['executive'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$where = "WHERE 1=1";
$params = [];
if ($user_role === 'field_executive') {
    $where .= " AND sv.field_executive_id = ?";
    $params[] = $user_id;
    $executive_filter = $user_id;
}
if ($store_filter > 0) { $where .= " AND sv.store_id = ?"; $params[] = $store_filter; }
if ($user_role !== 'field_executive' && $executive_filter > 0) { $where .= " AND sv.field_executive_id = ?"; $params[] = $executive_filter; }
if ($date_from) { $where .= " AND DATE(sv.visit_date) >= ?"; $params[] = $date_from; }
if ($date_to) { $where .= " AND DATE(sv.visit_date) <= ?"; $params[] = $date_to; }
if ($status_filter) {
    $status_conditions = [
        'pending' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'pending')",
        'approved' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'approved')",
        'packed' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'packed')",
        'shipped' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'shipped')",
        'delivered' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'delivered')"
    ];
    $where .= $status_conditions[$status_filter] ?? '';
}

// Fetch visits
$visits = $pdo->prepare("
    SELECT
        sv.id, sv.visit_date, sv.created_at, sv.visit_type, sv.next_followup_date,
        s.store_code, s.store_name, s.city,
        u.full_name AS executive_name,
        COUNT(sr.id) AS total_items,
        SUM(CASE WHEN sr.requirement_status = 'pending' THEN 1 ELSE 0 END) AS pending_items,
        SUM(CASE WHEN sr.requirement_status = 'approved' THEN 1 ELSE 0 END) AS approved_items,
        SUM(CASE WHEN sr.requirement_status = 'packed' THEN 1 ELSE 0 END) AS packed_items,
        SUM(CASE WHEN sr.requirement_status = 'shipped' THEN 1 ELSE 0 END) AS shipped_items,
        SUM(CASE WHEN sr.requirement_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_items,
        MAX(CASE WHEN sr.invoice_id IS NOT NULL THEN 1 ELSE 0 END) AS has_invoice,
        MAX(packer.full_name) AS packed_by_name,
        MAX(sr.packed_at) AS packed_date,
        MAX(shipper.full_name) AS shipped_by_name,
        MAX(sr.shipped_at) AS shipped_date,
        MAX(sr.tracking_number) AS tracking_number,
        MAX(approver.full_name) AS approved_by_name,
        MAX(sr.approved_at) AS approved_date
    FROM store_visits sv
    JOIN stores s ON sv.store_id = s.id
    JOIN users u ON sv.field_executive_id = u.id
    LEFT JOIN store_requirements sr ON sr.store_visit_id = sv.id
    LEFT JOIN users packer ON sr.packed_by = packer.id
    LEFT JOIN users shipper ON sr.shipped_by = shipper.id
    LEFT JOIN users approver ON sr.approved_by = approver.id
    $where
    GROUP BY sv.id
    ORDER BY sv.visit_date DESC, sv.created_at DESC
");
$visits->execute($params);
$visits = $visits->fetchAll();

// Filters data
$stores = $pdo->query("SELECT id, store_code, store_name FROM stores WHERE business_id = $business_id AND is_active = 1 ORDER BY store_name")->fetchAll();
$executives = ($user_role !== 'field_executive')
    ? $pdo->query("SELECT id, full_name FROM users WHERE business_id = $business_id AND role = 'field_executive' AND is_active = 1 ORDER BY full_name")->fetchAll()
    : [];

// Stats
$total_visits = count($visits);
$total_items = array_sum(array_column($visits, 'total_items'));
$pending_items = array_sum(array_column($visits, 'pending_items'));
$delivered_items = array_sum(array_column($visits, 'delivered_items'));

// Messages
$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
?>
<!doctype html>
<html lang="en">
<?php 
$page_title = $user_role === 'field_executive' ? "My Store Visits" : "Store Visits & Requirements"; 
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
                                <i class="bx bx-map me-2"></i> <?= $page_title ?>
                                <small class="text-muted ms-2">
                                    <i class="bx bx-buildings me-1"></i> 
                                    <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                </small>
                            </h4>
                            <div class="d-flex gap-2">
                                
                                <?php if ($user_role === 'field_executive' || $user_role === 'admin'): ?>
                                <a href="store_visit_form.php" class="btn btn-primary">
                                    <i class="bx bx-plus-circle me-1"></i> New Visit
                                </a>
                                <?php endif; ?>
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

                <!-- Filter Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bx bx-filter-alt me-1"></i> Filter Visits
                        </h5>
                        <form method="GET" id="filterForm">
                            <div class="row g-3">
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                                </div>
                                <?php if ($user_role !== 'field_executive'): ?>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Store</label>
                                    <select name="store" class="form-select">
                                        <option value="">All Stores</option>
                                        <?php foreach($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $store_filter == $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['store_code']) ?> - <?= htmlspecialchars($s['store_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Executive</label>
                                    <select name="executive" class="form-select">
                                        <option value="">All Executives</option>
                                        <?php foreach($executives as $e): ?>
                                        <option value="<?= $e['id'] ?>" <?= $executive_filter == $e['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['full_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Pending</option>
                                        <option value="approved" <?= $status_filter=='approved'?'selected':'' ?>>Approved</option>
                                        <option value="packed" <?= $status_filter=='packed'?'selected':'' ?>>Packed</option>
                                        <option value="shipped" <?= $status_filter=='shipped'?'selected':'' ?>>Shipped</option>
                                        <option value="delivered" <?= $status_filter=='delivered'?'selected':'' ?>>Delivered</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-lg-3 col-md-12">
                                    <label class="form-label d-none d-md-block">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bx bx-filter me-1"></i> Apply Filters
                                        </button>
                                        <?php if ($date_from != date('Y-m-01') || $date_to != date('Y-m-d') || $store_filter || $executive_filter || $status_filter): ?>
                                        <a href="store_requirements.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-reset me-1"></i> Clear
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Visits</h6>
                                        <h3 class="mb-0 text-primary"><?= $total_visits ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-primary bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-map text-primary"></i>
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
                                        <h6 class="text-muted mb-1">Total Items</h6>
                                        <h3 class="mb-0 text-success"><?= $total_items ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-success bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-package text-success"></i>
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
                                        <h6 class="text-muted mb-1">Pending Items</h6>
                                        <h3 class="mb-0 text-warning"><?= $pending_items ?></h3>
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
                                        <h6 class="text-muted mb-1">Delivered Items</h6>
                                        <h3 class="mb-0 text-info"><?= $delivered_items ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-info bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-truck text-info"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visits Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="visitsTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visit Details</th>
                                        <?php if ($user_role !== 'field_executive'): ?><th class="text-center">Executive</th><?php endif; ?>
                                        <th class="text-center">Items Status</th>
                                        <th class="text-center">Process Flow</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($visits)): ?>
                                   
                                    <?php else: ?>
                                    <?php foreach($visits as $i => $v): 
                                        // Determine overall status
                                        $status = 'pending';
                                        if ($v['total_items'] > 0) {
                                            if ($v['delivered_items'] == $v['total_items']) $status = 'delivered';
                                            elseif ($v['shipped_items'] > 0) $status = 'shipped';
                                            elseif ($v['packed_items'] > 0) $status = 'packed';
                                            elseif ($v['approved_items'] == $v['total_items']) $status = 'approved';
                                        }
                                        
                                        $status_color = [
                                            'pending' => 'warning',
                                            'approved' => 'info', 
                                            'packed' => 'primary',
                                            'shipped' => 'dark',
                                            'delivered' => 'success'
                                        ];
                                        $status_icon = [
                                            'pending' => 'bx-time-five',
                                            'approved' => 'bx-check-circle',
                                            'packed' => 'bx-package',
                                            'shipped' => 'bx-send',
                                            'delivered' => 'bx-check-double'
                                        ];
                                    ?>
                                    <tr class="visit-row" data-id="<?= $v['id'] ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3">
                                                    <div class="bg-<?= $status_color[$status] ?> bg-opacity-10 text-<?= $status_color[$status] ?> rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 48px; height: 48px;">
                                                        <i class="bx <?= $status_icon[$status] ?> fs-4"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <strong class="d-block mb-1"><?= htmlspecialchars($v['store_name']) ?></strong>
                                                    <small class="text-muted d-block">
                                                        <i class="bx bx-hash me-1"></i><?= $v['store_code'] ?> | <?= $v['city'] ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="bx bx-calendar me-1"></i><?= date('d M Y', strtotime($v['visit_date'])) ?>
                                                    </small>
                                                    <?php if ($v['visit_type']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bx bx-tag me-1"></i><?= ucfirst($v['visit_type']) ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <?php if ($user_role !== 'field_executive'): ?>
                                        <td class="text-center">
                                            <div class="mb-2">
                                                <span class="badge bg-info bg-opacity-10 text-info px-3 py-1">
                                                    <i class="bx bx-user-check me-1"></i><?= htmlspecialchars($v['executive_name']) ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bx bx-time me-1"></i><?= date('h:i A', strtotime($v['created_at'])) ?>
                                            </small>
                                        </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <div class="mb-2">
                                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fs-6">
                                                    <i class="bx bx-package me-1"></i> <?= $v['total_items'] ?>
                                                </span>
                                                <small class="text-muted d-block">Total Items</small>
                                            </div>
                                            <div class="d-flex justify-content-center gap-2">
                                                <?php if ($v['pending_items'] > 0): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning px-2 py-1">
                                                    <?= $v['pending_items'] ?> Pending
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($v['approved_items'] > 0): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                                                    <?= $v['approved_items'] ?> Approved
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="process-flow">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <?php if ($v['approved_by_name']): ?>
                                                    <div class="text-center">
                                                        <i class="bx bx-check-circle text-success fs-4"></i>
                                                        <small class="d-block text-muted"><?= date('d M', strtotime($v['approved_date'])) ?></small>
                                                        <small class="d-block"><?= htmlspecialchars($v['approved_by_name']) ?></small>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($v['packed_by_name']): ?>
                                                    <div class="text-center">
                                                        <i class="bx bx-package text-primary fs-4"></i>
                                                        <small class="d-block text-muted"><?= date('d M', strtotime($v['packed_date'])) ?></small>
                                                        <small class="d-block"><?= htmlspecialchars($v['packed_by_name']) ?></small>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($v['shipped_by_name']): ?>
                                                    <div class="text-center">
                                                        <i class="bx bx-send text-dark fs-4"></i>
                                                        <small class="d-block text-muted"><?= date('d M', strtotime($v['shipped_date'])) ?></small>
                                                        <small class="d-block">
                                                            <?= htmlspecialchars($v['shipped_by_name']) ?>
                                                            <?php if ($v['tracking_number']): ?>
                                                            <br><small class="text-muted">#<?= htmlspecialchars($v['tracking_number']) ?></small>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($v['next_followup_date']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="bx bx-calendar-event me-1"></i>
                                                        Next Follow-up: <?= date('d M Y', strtotime($v['next_followup_date'])) ?>
                                                    </small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info"
                                                        onclick="viewVisit(<?= $v['id'] ?>)"
                                                        data-bs-toggle="tooltip"
                                                        title="View Details">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if ($user_role === 'field_executive'): ?>
                                                <a href="store_visit_form.php?edit=<?= $v['id'] ?>" 
                                                   class="btn btn-outline-warning"
                                                   data-bs-toggle="tooltip"
                                                   title="Edit Visit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                <?php if (!$v['has_invoice'] && $v['pending_items'] == $v['total_items']): ?>
                                                <form action="" method="POST" style="display:inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this visit? This action cannot be undone.')">
                                                    <input type="hidden" name="delete" value="<?= $v['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger"
                                                            data-bs-toggle="tooltip" title="Delete Visit">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <?php elseif ($user_role === 'seller' && $v['pending_items'] > 0): ?>
                                                <form action="" method="POST" style="display:inline;" 
                                                      onsubmit="return confirm('Approve all items in this visit?')">
                                                    <input type="hidden" name="approve_visit" value="<?= $v['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-success"
                                                            data-bs-toggle="tooltip" title="Approve Items">
                                                        <i class="bx bx-check"></i> Approve
                                                    </button>
                                                </form>
                                                <?php elseif (in_array($user_role, ['staff', 'warehouse_manager']) && in_array($status, ['approved', 'packed', 'shipped'])): ?>
                                                <form action="" method="POST" style="display:inline;" 
                                                      onsubmit="return confirm('Update status for all items?')" 
                                                      class="d-flex gap-1">
                                                    <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <select name="new_status" class="form-select form-select-sm w-auto">
                                                        <option value="packed" <?= $status == 'approved' ? 'selected' : '' ?>>Packed</option>
                                                        <option value="shipped" <?= $status == 'packed' ? 'selected' : '' ?>>Shipped</option>
                                                        <option value="delivered" <?= $status == 'shipped' ? 'selected' : '' ?>>Delivered</option>
                                                    </select>
                                                    <?php if ($status == 'packed'): ?>
                                                    <input type="text" name="tracking_number" 
                                                           placeholder="Tracking No" 
                                                           class="form-control form-control-sm w-auto" 
                                                           style="width: 120px;">
                                                    <?php endif; ?>
                                                    <button type="submit" name="update_status" 
                                                            class="btn btn-primary btn-sm">
                                                        <i class="bx bx-sync"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="visitModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-map me-2"></i> Visit Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="visitDetails">Loading...</div>
        </div>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#visitsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search visits:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ visits",
            infoFiltered: "(filtered from <?= $total_visits ?> total visits)",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    // Tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Auto-submit on filter change (optional)
    $('select[name="store"], select[name="executive"], select[name="status"]').on('change', function() {
        $('#filterForm').submit();
    });

    // Auto-close alerts after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);

    // Row hover effect
    $('.visit-row').hover(
        function() { $(this).addClass('bg-light'); },
        function() { $(this).removeClass('bg-light'); }
    );
});

// View visit details
function viewVisit(id) {
    $('#visitDetails').html('<div class="text-center p-5"><i class="bx bx-loader bx-spin fs-1 text-primary"></i><p class="mt-3">Loading visit details...</p></div>');
    
    fetch('ajax_store_visit_details.php?id=' + id)
        .then(response => response.text())
        .then(html => {
            $('#visitDetails').html(html);
            new bootstrap.Modal('#visitModal').show();
        })
        .catch(error => {
            $('#visitDetails').html('<div class="alert alert-danger">Failed to load visit details. Please try again.</div>');
        });
}

// Export function
function exportVisits() {
    const btn = event.target.closest('button');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bx bx-loader bx-spin me-1"></i> Exporting...';
    btn.disabled = true;
    
    // Build export URL with current search parameters
    const params = new URLSearchParams(window.location.search);
    const exportUrl = 'visits_export.php' + (params.toString() ? '?' + params.toString() : '');
    
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
    width: 48px;
    height: 48px;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}
.btn-group .btn {
    padding: 0.375rem 0.75rem;
    font-size: 14px;
}
.btn-group .btn:hover {
    transform: translateY(-1px);
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
.process-flow {
    min-width: 200px;
}
.visit-row:hover .avatar-sm .rounded-circle {
    transform: scale(1.1);
    transition: transform 0.3s ease;
}
@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .btn-group .btn {
        width: 100%;
    }
    .btn-group form {
        width: 100%;
    }
    .btn-group select, 
    .btn-group input[type="text"] {
        width: 100% !important;
        margin-bottom: 5px;
    }
    .avatar-sm {
        width: 40px;
        height: 40px;
    }
    .process-flow {
        min-width: 150px;
    }
}
</style>
</body>
</html> 