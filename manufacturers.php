<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'warehouse_manager','shop_manager'])) {
    header('Location: dashboard.php');
    exit();
}

// Get current user's business and shop info
$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'];
$shop_id = $_SESSION['shop_id'] ?? null;

// Get available shops for the current business
$shops_stmt = $pdo->prepare("
    SELECT id, shop_name, shop_code, is_warehouse 
    FROM shops 
    WHERE business_id = ? AND is_active = 1
    ORDER BY shop_name
");
$shops_stmt->execute([$business_id]);
$shops = $shops_stmt->fetchAll();

// Display messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

// Search
$search = trim($_GET['search'] ?? '');
$shop_filter = $_GET['shop_filter'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$where = "WHERE m.business_id = :business_id";
$params = ['business_id' => $business_id];

if ($search) {
    $where .= " AND (m.name LIKE :search OR m.contact_person LIKE :search2 OR m.phone LIKE :search3 OR m.gstin LIKE :search4 OR m.account_number LIKE :search5 OR m.ifsc_code LIKE :search6)";
    $like = "%$search%";
    $params['search'] = $like;
    $params['search2'] = $like;
    $params['search3'] = $like;
    $params['search4'] = $like;
    $params['search5'] = $like;
    $params['search6'] = $like;
}

if ($shop_filter) {
    $where .= " AND m.shop_id = :shop_filter";
    $params['shop_filter'] = $shop_filter;
}

if ($status_filter !== 'all') {
    $where .= " AND m.is_active = :status";
    $params['status'] = ($status_filter === 'active') ? 1 : 0;
}

// Get summary statistics
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM manufacturers WHERE business_id = ?");
$total_stmt->execute([$business_id]);
$total_suppliers = $total_stmt->fetchColumn();

$active_stmt = $pdo->prepare("SELECT COUNT(*) FROM manufacturers WHERE business_id = ? AND is_active = 1");
$active_stmt->execute([$business_id]);
$active_suppliers = $active_stmt->fetchColumn();

$inactive_stmt = $pdo->prepare("SELECT COUNT(*) FROM manufacturers WHERE business_id = ? AND is_active = 0");
$inactive_stmt->execute([$business_id]);
$inactive_suppliers = $inactive_stmt->fetchColumn();

// Get total purchases and amount for the business
$purchases_stmt = $pdo->prepare("
    SELECT COUNT(*), COALESCE(SUM(total_amount), 0) 
    FROM purchases 
    WHERE business_id = ?
");
$purchases_stmt->execute([$business_id]);
$purchase_result = $purchases_stmt->fetch();

$total_purchases = $purchase_result[0] ?? 0;
$total_amount = $purchase_result[1] ?? 0;

// Get manufacturers data
$sql = "
    SELECT m.*, 
           s.shop_name,
           s.shop_code,
           (SELECT COUNT(*) FROM purchases p WHERE p.manufacturer_id = m.id) as total_purchases,
           (SELECT COALESCE(SUM(p.total_amount), 0) FROM purchases p WHERE p.manufacturer_id = m.id) as total_spent
    FROM manufacturers m
    LEFT JOIN shops s ON m.shop_id = s.id
    $where
    ORDER BY m.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$manufacturers = $stmt->fetchAll();
?>

<!doctype html>
<html lang="en">
<?php $page_title = "Manufacturers & Suppliers"; ?>
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php'); ?>
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
                                <i class="bx bx-buildings me-2"></i> Manufacturers & Suppliers
                                <small class="text-muted ms-2">
                                    <i class="bx bx-store me-1"></i>
                                    <?= htmlspecialchars($_SESSION['current_shop_name'] ?? 'All Shops') ?>
                                </small>
                            </h4>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addManufacturerModal">
                                    <i class="bx bx-plus-circle me-1"></i> Add Supplier
                                </button>
                                <button class="btn btn-outline-secondary" onclick="exportManufacturers()">
                                    <i class="bx bx-download me-1"></i> Export
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

                <!-- Quick Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Suppliers</h6>
                                        <h3 class="mb-0 text-primary"><?= $total_suppliers ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-primary bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-buildings text-primary"></i>
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
                                        <h6 class="text-muted mb-1">Active Suppliers</h6>
                                        <h3 class="mb-0 text-success"><?= $active_suppliers ?></h3>
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
                                        <h6 class="text-muted mb-1">Total Purchases</h6>
                                        <h3 class="mb-0 text-warning"><?= $total_purchases ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-warning bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-cart text-warning"></i>
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
                                        <h6 class="text-muted mb-1">Total Amount</h6>
                                        <h3 class="mb-0 text-info">₹<?= number_format($total_amount, 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-info bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-rupee text-info"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bx bx-filter-alt me-1"></i> Filter Suppliers
                        </h5>
                        <form method="GET" id="filterForm">
                            <div class="row g-3">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bx bx-search"></i>
                                        </span>
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Name, Contact, Phone, GSTIN, Account No..."
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Filter by Shop</label>
                                    <select name="shop_filter" class="form-select">
                                        <option value="">All Shops</option>
                                        <?php foreach ($shops as $shop): ?>
                                        <option value="<?= $shop['id'] ?>" <?= $shop_filter == $shop['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($shop['shop_name']) ?> (<?= $shop['shop_code'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-12">
                                    <label class="form-label d-none d-md-block">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bx bx-filter me-1"></i> Apply Filters
                                        </button>
                                        <?php if ($search || $shop_filter || $status_filter !== 'all'): ?>
                                        <a href="manufacturers.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-reset me-1"></i> Clear
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Suppliers Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="suppliersTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Contact Information</th>
                                        <th>Bank Details</th>
                                        <th class="text-center">Shop</th>
                                        <th class="text-center">Purchases</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($manufacturers)): ?>
                                   
                                    <?php else: ?>
                                    <?php foreach ($manufacturers as $i => $m): ?>
                                    <tr class="supplier-row" data-id="<?= $m['id'] ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 48px; height: 48px;">
                                                        <span class="fw-bold fs-5">
                                                            <?= strtoupper(substr($m['name'], 0, 2)) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <strong class="d-block mb-1"><?= htmlspecialchars($m['name']) ?></strong>
                                                    <?php if ($m['contact_person']): ?>
                                                    <small class="text-muted"><i class="bx bx-user me-1"></i><?= htmlspecialchars($m['contact_person']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($m['gstin']): ?>
                                                    <br><small class="text-muted"><i class="bx bx-barcode me-1"></i><?= htmlspecialchars($m['gstin']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($m['phone']): ?>
                                            <div class="mb-1">
                                                <a href="tel:<?= htmlspecialchars($m['phone']) ?>" class="text-decoration-none d-flex align-items-center">
                                                    <i class="bx bx-phone text-primary me-2"></i> <?= htmlspecialchars($m['phone']) ?>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($m['email']): ?>
                                            <div>
                                                <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="text-decoration-none d-flex align-items-center">
                                                    <i class="bx bx-envelope text-info me-2"></i> <?= htmlspecialchars($m['email']) ?>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($m['address']): ?>
                                            <div class="mt-1">
                                                <small class="text-muted">
                                                    <i class="bx bx-map me-1"></i><?= htmlspecialchars(substr($m['address'], 0, 50)) ?>
                                                    <?= strlen($m['address']) > 50 ? '...' : '' ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($m['account_holder_name']): ?>
                                            <div class="mb-1">
                                                <small class="text-muted d-flex align-items-center">
                                                    <i class="bx bx-user-circle me-1"></i> 
                                                    <span class="text-truncate" title="<?= htmlspecialchars($m['account_holder_name']) ?>">
                                                        <?= htmlspecialchars($m['account_holder_name']) ?>
                                                    </span>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($m['bank_name']): ?>
                                            <div class="mb-1">
                                                <small class="text-muted d-flex align-items-center">
                                                    <i class="bx bx-bank me-1"></i> 
                                                    <span class="text-truncate" title="<?= htmlspecialchars($m['bank_name']) ?>">
                                                        <?= htmlspecialchars($m['bank_name']) ?>
                                                    </span>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($m['account_number']): ?>
                                            <div class="mb-1">
                                                <small class="text-muted d-flex align-items-center">
                                                    <i class="bx bx-credit-card me-1"></i> 
                                                    A/C: ****<?= substr($m['account_number'], -4) ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($m['ifsc_code']): ?>
                                            <div>
                                                <small class="text-muted d-flex align-items-center">
                                                    <i class="bx bx-code me-1"></i> 
                                                    IFSC: <?= htmlspecialchars($m['ifsc_code']) ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($m['shop_name']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <i class="bx bx-store me-1"></i>
                                                <?= htmlspecialchars($m['shop_code']) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                                Not Assigned
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill px-3 py-1 fs-6">
                                                <?= $m['total_purchases'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success">₹<?= number_format($m['total_spent'], 2) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($m['is_active']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">
                                                <i class="bx bx-circle me-1"></i>Active
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1">
                                                <i class="bx bx-circle me-1"></i>Inactive
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info edit-btn"
                                                        data-id="<?= $m['id'] ?>"
                                                        data-name="<?= htmlspecialchars($m['name']) ?>"
                                                        data-person="<?= htmlspecialchars($m['contact_person'] ?? '') ?>"
                                                        data-phone="<?= htmlspecialchars($m['phone'] ?? '') ?>"
                                                        data-email="<?= htmlspecialchars($m['email'] ?? '') ?>"
                                                        data-address="<?= htmlspecialchars($m['address'] ?? '') ?>"
                                                        data-gstin="<?= htmlspecialchars($m['gstin'] ?? '') ?>"
                                                        data-accountholder="<?= htmlspecialchars($m['account_holder_name'] ?? '') ?>"
                                                        data-bankname="<?= htmlspecialchars($m['bank_name'] ?? '') ?>"
                                                        data-accountno="<?= htmlspecialchars($m['account_number'] ?? '') ?>"
                                                        data-ifsc="<?= htmlspecialchars($m['ifsc_code'] ?? '') ?>"
                                                        data-branch="<?= htmlspecialchars($m['branch_name'] ?? '') ?>"
                                                        data-active="<?= $m['is_active'] ?>"
                                                        data-shop="<?= $m['shop_id'] ?? '' ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Edit Supplier">
                                                    <i class="bx bx-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger delete-btn"
                                                        data-id="<?= $m['id'] ?>"
                                                        data-name="<?= htmlspecialchars($m['name']) ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Delete Supplier">
                                                    <i class="bx bx-trash"></i>
                                                </button>
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
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addManufacturerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle me-2"></i> 
                    <span id="modalTitle">Add Supplier</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manufacturers_process.php">
                <input type="hidden" name="id" id="editId" value="">
                <div class="modal-body">
                    <ul class="nav nav-tabs nav-tabs-custom" id="supplierTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab">
                                <i class="bx bx-building-house me-1"></i> Basic Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact-tab-pane" type="button" role="tab">
                                <i class="bx bx-contact me-1"></i> Contact Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank-tab-pane" type="button" role="tab">
                                <i class="bx bx-credit-card me-1"></i> Bank Details
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="basic-tab-pane" role="tabpanel" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label"><strong>Company Name <span class="text-danger">*</span></strong></label>
                                    <input type="text" name="name" id="companyName" 
                                           class="form-control form-control-lg" 
                                           required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" id="contactPerson" 
                                           class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">GSTIN</label>
                                    <input type="text" name="gstin" id="gstin" 
                                           class="form-control"
                                           placeholder="33ABCDE1234F1Z5">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Shop / Warehouse</label>
                                    <select name="shop_id" id="shopId" class="form-select">
                                        <option value="">-- Select Shop (Optional) --</option>
                                        <?php foreach ($shops as $shop): ?>
                                        <option value="<?= $shop['id'] ?>">
                                            <?= htmlspecialchars($shop['shop_name']) ?> 
                                            (<?= $shop['shop_code'] ?>)
                                            <?= $shop['is_warehouse'] == 1 ? ' - Warehouse' : '' ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Assign supplier to a specific shop or warehouse</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Details Tab -->
                        <div class="tab-pane fade" id="contact-tab-pane" role="tabpanel" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" id="phone" 
                                           class="form-control"
                                           placeholder="+91 9876543210">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="email" 
                                           class="form-control"
                                           placeholder="supplier@example.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" id="address" 
                                              class="form-control" 
                                              rows="3"
                                              placeholder="Full address with city, state, and pin code"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bank Details Tab -->
                        <div class="tab-pane fade" id="bank-tab-pane" role="tabpanel" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Account Holder Name</label>
                                    <input type="text" name="account_holder_name" id="accountHolderName" 
                                           class="form-control"
                                           placeholder="Name as per bank account">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" id="bankName" 
                                           class="form-control"
                                           placeholder="e.g., State Bank of India">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" name="account_number" id="accountNumber" 
                                           class="form-control"
                                           placeholder="Account number">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" name="ifsc_code" id="ifscCode" 
                                           class="form-control"
                                           placeholder="SBIN0001234">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Branch Name</label>
                                    <input type="text" name="branch_name" id="branchName" 
                                           class="form-control"
                                           placeholder="Branch location">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Common fields -->
                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           name="is_active" id="isActive" 
                                           value="1" checked>
                                    <label class="form-check-label" for="isActive">
                                        <strong>Active Supplier</strong>
                                        <small class="text-muted d-block">Inactive suppliers won't appear in purchase forms</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" name="action" value="save" class="btn btn-primary">
                        <i class="bx bx-save me-2"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <div class="mb-4">
                    <i class="bx bx-trash text-danger" style="font-size: 4rem;"></i>
                </div>
                <h5 class="mb-3">Delete Supplier</h5>
                <p class="text-muted mb-4">Are you sure you want to delete <strong id="deleteSupplierName"></strong>? This action cannot be undone.</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Delete Supplier
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php'); ?>
<?php include('includes/scripts.php'); ?>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#suppliersTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search suppliers:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ suppliers",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    // Tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Edit button handler
    $('.edit-btn').click(function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const person = $(this).data('person');
        const phone = $(this).data('phone');
        const email = $(this).data('email');
        const address = $(this).data('address');
        const gstin = $(this).data('gstin');
        const accountholder = $(this).data('accountholder');
        const bankname = $(this).data('bankname');
        const accountno = $(this).data('accountno');
        const ifsc = $(this).data('ifsc');
        const branch = $(this).data('branch');
        const active = $(this).data('active');
        const shop = $(this).data('shop');

        // Set form values
        $('#editId').val(id);
        $('#companyName').val(name);
        $('#contactPerson').val(person);
        $('#phone').val(phone);
        $('#email').val(email);
        $('#address').val(address);
        $('#gstin').val(gstin);
        $('#accountHolderName').val(accountholder);
        $('#bankName').val(bankname);
        $('#accountNumber').val(accountno);
        $('#ifscCode').val(ifsc);
        $('#branchName').val(branch);
        $('#shopId').val(shop);
        $('#isActive').prop('checked', active == 1);
        
        // Update modal title
        $('#modalTitle').text('Edit Supplier');
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('addManufacturerModal'));
        modal.show();
        
        // Activate first tab
        $('.nav-tabs button[data-bs-target="#basic-tab-pane"]').tab('show');
    });

    // Delete button handler
    $('.delete-btn').click(function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#deleteSupplierName').text(name);
        $('#confirmDeleteBtn').attr('href', 'manufacturers_process.php?delete=' + id);
        
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    });

    // Reset modal when closed
    $('#addManufacturerModal').on('hidden.bs.modal', function () {
        $('#modalTitle').text('Add Supplier');
        $(this).find('form')[0].reset();
        $('#editId').val('');
        $('#isActive').prop('checked', true);
        $('.nav-tabs button[data-bs-target="#basic-tab-pane"]').tab('show');
    });

    // Export function
    window.exportManufacturers = function() {
        const btn = event.target.closest('button');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bx bx-loader bx-spin me-1"></i> Exporting...';
        btn.disabled = true;
        
        // Build export URL with current search parameters
        const params = new URLSearchParams(window.location.search);
        const exportUrl = 'manufacturers_export.php' + (params.toString() ? '?' + params.toString() : '');
        
        window.location = exportUrl;
        
        // Reset button after 3 seconds
        setTimeout(() => {
            btn.innerHTML = original;
            btn.disabled = false;
        }, 3000);
    };

    // Print function
    window.printManufacturers = function() {
        window.print();
    };

    // Real-time search debounce
    let searchTimer;
    $('input[name="search"]').on('keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => $('#filterForm').submit(), 500);
    });

    // Row hover
    $('.supplier-row').hover(
        function() { $(this).addClass('bg-light'); },
        function() { $(this).removeClass('bg-light'); }
    );

    // Toast function (if needed)
    function showToast(type, message) {
        $('.toast').remove();
        const toast = $(`<div class="toast align-items-center text-bg-${type} border-0" role="alert"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
        if ($('.toast-container').length === 0) $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999"></div>');
        $('.toast-container').append(toast);
        new bootstrap.Toast(toast[0]).show();
    }

    // Auto-close alerts
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});

// Style for warehouse options in dropdown
const style = document.createElement('style');
style.textContent = `
    .nav-tabs-custom .nav-link {
        padding: 0.75rem 1.5rem;
        font-weight: 500;
    }
    .nav-tabs-custom .nav-link.active {
        border-bottom: 3px solid #5b73e8;
    }
    option[data-warehouse="true"] {
        font-style: italic;
        color: #666;
    }
    .avatar-sm .bg-primary {
        transition: all 0.3s ease;
    }
    .supplier-row:hover .avatar-sm .bg-primary {
        transform: scale(1.1);
    }
    .empty-state {
        text-align: center;
        padding: 2rem;
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
    .form-switch .form-check-input:checked {
        background-color: #5b73e8;
        border-color: #5b73e8;
    }
    .modal-header {
        border-bottom: 1px solid #dee2e6;
    }
    .modal-footer {
        border-top: 1px solid #dee2e6;
    }
    .nav-tabs-custom {
        border-bottom: 2px solid #dee2e6;
    }
    .nav-tabs-custom .nav-link {
        color: #6c757d;
        border: none;
        margin-bottom: -2px;
    }
    .nav-tabs-custom .nav-link:hover {
        color: #495057;
        border: none;
    }
    .nav-tabs-custom .nav-link.active {
        color: #5b73e8;
        background-color: transparent;
        border: none;
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
        .modal-dialog {
            margin: 0.5rem;
        }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>