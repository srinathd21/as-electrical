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
$is_manager = in_array($user_role, ['admin', 'shop_manager']);

// Only admin and managers can manage sites
if (!$is_manager) {
    header('Location: dashboard.php');
    exit();
}

// ==================== HANDLE FORM SUBMISSIONS ====================
$message = '';
$error = '';

// Add/Edit Site
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add New Site
        if ($_POST['action'] === 'add' && isset($_POST['site_name'], $_POST['site_address'])) {
            try {
                $site_name = trim($_POST['site_name']);
                $site_address = trim($_POST['site_address']);
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $country = trim($_POST['country'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                $project_type = trim($_POST['project_type'] ?? '');
                $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                $expected_end_date = !empty($_POST['expected_end_date']) ? $_POST['expected_end_date'] : null;
                $status = $_POST['status'] ?? 'active';
                $engineer_id = !empty($_POST['engineer_id']) ? (int)$_POST['engineer_id'] : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO sites (site_name, site_address, city, state, country, postal_code, 
                                      project_type, start_date, expected_end_date, status, engineer_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$site_name, $site_address, $city, $state, $country, $postal_code, 
                               $project_type, $start_date, $expected_end_date, $status, $engineer_id]);
                
                $_SESSION['success'] = "Site added successfully!";
                header('Location: sites.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding site: " . $e->getMessage();
                header('Location: sites.php');
                exit();
            }
        }
        
        // Edit Site
        elseif ($_POST['action'] === 'edit' && isset($_POST['site_id'], $_POST['site_name'], $_POST['site_address'])) {
            try {
                $site_id = (int)$_POST['site_id'];
                $site_name = trim($_POST['site_name']);
                $site_address = trim($_POST['site_address']);
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $country = trim($_POST['country'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                $project_type = trim($_POST['project_type'] ?? '');
                $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                $expected_end_date = !empty($_POST['expected_end_date']) ? $_POST['expected_end_date'] : null;
                $status = $_POST['status'] ?? 'active';
                $engineer_id = !empty($_POST['engineer_id']) ? (int)$_POST['engineer_id'] : null;
                
                $stmt = $pdo->prepare("
                    UPDATE sites 
                    SET site_name = ?, site_address = ?, city = ?, state = ?, country = ?, 
                        postal_code = ?, project_type = ?, start_date = ?, expected_end_date = ?, 
                        status = ?, engineer_id = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE site_id = ?
                ");
                $stmt->execute([$site_name, $site_address, $city, $state, $country, $postal_code, 
                               $project_type, $start_date, $expected_end_date, $status, $engineer_id, $site_id]);
                
                $_SESSION['success'] = "Site updated successfully!";
                header('Location: sites.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating site: " . $e->getMessage();
                header('Location: sites.php');
                exit();
            }
        }
        
        // Delete Site
        elseif ($_POST['action'] === 'delete' && isset($_POST['site_id'])) {
            try {
                $site_id = (int)$_POST['site_id'];
                
                // Check if site is associated with any invoices
                $check_invoices = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE site_id = ?");
                $check_invoices->execute([$site_id]);
                $invoice_count = $check_invoices->fetchColumn();
                
                if ($invoice_count > 0) {
                    $_SESSION['error'] = "Cannot delete site because it is associated with $invoice_count invoice(s).";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM sites WHERE site_id = ?");
                    $stmt->execute([$site_id]);
                    
                    $_SESSION['success'] = "Site deleted successfully!";
                }
                header('Location: sites.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting site: " . $e->getMessage();
                header('Location: sites.php');
                exit();
            }
        }
    }
}

// ==================== GET ALL SITES ====================
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$engineer_filter = isset($_GET['engineer_id']) ? (int)$_GET['engineer_id'] : 0;

$sql = "SELECT s.*, 
        e.first_name, e.last_name, e.email as engineer_email, e.phone as engineer_phone,
        (SELECT COUNT(*) FROM invoices WHERE site_id = s.site_id) as invoice_count
        FROM sites s
        LEFT JOIN engineers e ON s.engineer_id = e.engineer_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.site_name LIKE ? OR s.site_address LIKE ? OR s.city LIKE ? OR s.project_type LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($status_filter)) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
}

if ($engineer_filter > 0) {
    $sql .= " AND s.engineer_id = ?";
    $params[] = $engineer_filter;
}

$sql .= " ORDER BY s.site_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

// Get site for editing if ID is provided
$edit_site = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE site_id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_site = $stmt->fetch();
    if (!$edit_site) {
        $_SESSION['error'] = "Site not found!";
        header('Location: sites.php');
        exit();
    }
}

// Get site for viewing if ID is provided
$view_site = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, e.first_name, e.last_name, e.email as engineer_email, e.phone as engineer_phone,
               e.specialization as engineer_specialization
        FROM sites s
        LEFT JOIN engineers e ON s.engineer_id = e.engineer_id
        WHERE s.site_id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $view_site = $stmt->fetch();
    if (!$view_site) {
        $_SESSION['error'] = "Site not found!";
        header('Location: sites.php');
        exit();
    }
}

// Get all engineers for dropdown
$engineers_stmt = $pdo->prepare("SELECT engineer_id, first_name, last_name, email FROM engineers WHERE status = 'active' ORDER BY first_name, last_name");
$engineers_stmt->execute();
$engineers_list = $engineers_stmt->fetchAll();

// Statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'on_hold' => 0,
    'inactive' => 0
];

$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM sites
");
$stats_data = $stats_stmt->fetch();
if ($stats_data) {
    $stats = $stats_data;
}
?>

<!doctype html>
<html lang="en">
<?php $page_title = "Sites Management"; include 'includes/head.php'; ?>
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
                                    <i class="bx bx-map-pin me-2"></i> Sites Management
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-buildings me-1"></i>
                                        <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                    </small>
                                </h4>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="engineers.php" class="btn btn-info">
                                    <i class="bx bx-hard-hat me-1"></i> Manage Engineers
                                </a>
                                <button type="button" class="btn btn-primary" onclick="openAddSiteModal()">
                                    <i class="bx bx-plus-circle me-1"></i> Add New Site
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages - Will be shown via SweetAlert -->
                <?php if (isset($_SESSION['success'])): ?>
                <div style="display: none;" class="session-message" data-type="success" data-message="<?= htmlspecialchars($_SESSION['success']) ?>"></div>
                <?php unset($_SESSION['success']); endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                <div style="display: none;" class="session-message" data-type="error" data-message="<?= htmlspecialchars($_SESSION['error']) ?>"></div>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4 g-3">
                    <div class="col-xl-2 col-md-4">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Sites</h6>
                                        <h3 class="mb-0 text-primary"><?= number_format($stats['total'] ?? 0) ?></h3>
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
                    <div class="col-xl-2 col-md-4">
                        <div class="card card-hover border-start border-success border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Active</h6>
                                        <h3 class="mb-0 text-success"><?= number_format($stats['active'] ?? 0) ?></h3>
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
                    <div class="col-xl-2 col-md-4">
                        <div class="card card-hover border-start border-info border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Completed</h6>
                                        <h3 class="mb-0 text-info"><?= number_format($stats['completed'] ?? 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-info bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-check-double text-info"></i>
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
                                        <h6 class="text-muted mb-1">On Hold</h6>
                                        <h3 class="mb-0 text-warning"><?= number_format($stats['on_hold'] ?? 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-warning bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-pause-circle text-warning"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-danger border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Inactive</h6>
                                        <h3 class="mb-0 text-danger"><?= number_format($stats['inactive'] ?? 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-danger bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-x-circle text-danger"></i>
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
                            <i class="bx bx-filter-alt me-1"></i> Filter Sites
                        </h5>
                        <form method="GET" id="filterForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bx bx-search"></i>
                                        </span>
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Site Name / Address / City / Project Type"
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="on_hold" <?= $status_filter === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Engineer</label>
                                    <select name="engineer_id" class="form-select">
                                        <option value="">All Engineers</option>
                                        <?php foreach ($engineers_list as $eng): ?>
                                        <option value="<?= $eng['engineer_id'] ?>" <?= $engineer_filter == $eng['engineer_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-filter me-1"></i> Apply
                                        </button>
                                        <?php if (!empty($search) || !empty($status_filter) || $engineer_filter > 0): ?>
                                        <a href="sites.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-reset"></i> Reset
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sites Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="sitesTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Site Details</th>
                                        <th>Location</th>
                                        <th>Project Type</th>
                                        <th class="text-center">Timeline</th>
                                        <th class="text-center">Status</th>
                                        <th>Assigned Engineer</th>
                                        <th class="text-center">Invoices</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sites)): ?>
                                    
                                    <?php else: ?>
                                    <?php foreach ($sites as $site): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3 flex-shrink-0">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 48px; height: 48px;">
                                                        <i class="bx bx-buildings fs-4"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <strong class="d-block mb-1"><?= htmlspecialchars($site['site_name']) ?></strong>
                                                    <small class="text-muted">ID: #<?= $site['site_id'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="mb-1">
                                                    <i class="bx bx-map text-muted me-1"></i>
                                                    <?= htmlspecialchars($site['site_address'] ?? '') ?>
                                                </div>
                                                <?php if (!empty($site['city'])): ?>
                                                <div>
                                                    <i class="bx bx-city text-muted me-1"></i>
                                                    <?= htmlspecialchars($site['city']) ?>
                                                    <?php if (!empty($site['state'])): ?>, <?= htmlspecialchars($site['state']) ?><?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($site['postal_code'])): ?>
                                                <div>
                                                    <i class="bx bx-mail-send text-muted me-1"></i>
                                                    <?= htmlspecialchars($site['postal_code']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($site['project_type'])): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2">
                                                <?= htmlspecialchars($site['project_type']) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($site['start_date']): ?>
                                            <div>
                                                <small class="text-muted">Start:</small>
                                                <span class="badge bg-light text-dark d-block mt-1">
                                                    <?= date('d M Y', strtotime($site['start_date'])) ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($site['expected_end_date']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">End:</small>
                                                <span class="badge bg-light text-dark d-block mt-1">
                                                    <?= date('d M Y', strtotime($site['expected_end_date'])) ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $status_class = [
                                                'active' => 'success',
                                                'completed' => 'info',
                                                'on_hold' => 'warning',
                                                'inactive' => 'danger'
                                            ][$site['status']] ?? 'secondary';
                                            
                                            $status_icon = [
                                                'active' => 'check-circle',
                                                'completed' => 'check-double',
                                                'on_hold' => 'pause-circle',
                                                'inactive' => 'x-circle'
                                            ][$site['status']] ?? 'circle';
                                            ?>
                                            <span class="badge bg-<?= $status_class ?> bg-opacity-10 text-<?= $status_class ?> px-3 py-2">
                                                <i class="bx bx-<?= $status_icon ?> me-1"></i>
                                                <?= ucfirst(str_replace('_', ' ', $site['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($site['engineer_id']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <i class="bx bx-user-circle text-primary fs-5"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($site['first_name'] . ' ' . $site['last_name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bx bx-phone me-1"></i><?= htmlspecialchars($site['engineer_phone'] ?? 'No phone') ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                                <i class="bx bx-receipt me-1"></i> <?= $site['invoice_count'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-success btn-sm" 
                                                        onclick="viewSite(<?= $site['site_id'] ?>)" title="View Details">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        onclick="editSite(<?= $site['site_id'] ?>)" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="deleteSite(<?= $site['site_id'] ?>, '<?= htmlspecialchars(addslashes($site['site_name'])) ?>')" 
                                                        title="Delete">
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
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<!-- Add Site Modal -->
<div class="modal fade" id="addSiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-map-plus me-2"></i> Add New Site</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="sites.php" id="addSiteForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Site Name <span class="text-danger">*</span></label>
                            <input type="text" name="site_name" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Site Address <span class="text-danger">*</span></label>
                            <textarea name="site_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="India" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Project Type</label>
                            <input type="text" name="project_type" class="form-control" placeholder="e.g., Residential, Commercial, Industrial" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Assigned Engineer</label>
                            <select name="engineer_id" class="form-select">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($engineers_list as $eng): ?>
                                <option value="<?= $eng['engineer_id'] ?>">
                                    <?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected End Date</label>
                            <input type="date" name="expected_end_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="on_hold">On Hold</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save Site
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Site Modal -->
<div class="modal fade" id="editSiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="bx bx-edit me-2"></i> Edit Site</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="sites.php" id="editSiteForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="site_id" id="edit_site_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Site Name <span class="text-danger">*</span></label>
                            <input type="text" name="site_name" id="edit_site_name" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Site Address <span class="text-danger">*</span></label>
                            <textarea name="site_address" id="edit_site_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="edit_city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" id="edit_state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" id="edit_country" class="form-control" value="India">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" id="edit_postal_code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Project Type</label>
                            <input type="text" name="project_type" id="edit_project_type" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Assigned Engineer</label>
                            <select name="engineer_id" id="edit_engineer_id" class="form-select">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($engineers_list as $eng): ?>
                                <option value="<?= $eng['engineer_id'] ?>">
                                    <?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected End Date</label>
                            <input type="date" name="expected_end_date" id="edit_expected_end_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="on_hold">On Hold</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-save me-1"></i> Update Site
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Site Details Modal -->
<div class="modal fade" id="viewSiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-map-detail me-2"></i> Site Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="siteDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-3">Loading site details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Site Form (Hidden) -->
<form method="POST" id="deleteSiteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="site_id" id="deleteSiteId">
</form>

<?php include('includes/rightbar.php') ?>
<?php include('includes/scripts.php') ?>

<!-- Include SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var sitesTable = $('#sitesTable').DataTable({
        responsive: true,
        pageLength: 25,
        ordering: true,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search sites:",
            lengthMenu: "Show _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ sites",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    // Show session messages with SweetAlert
    $('.session-message').each(function() {
        const type = $(this).data('type');
        const message = $(this).data('message');
        
        Swal.fire({
            icon: type,
            title: type === 'success' ? 'Success!' : 'Error!',
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    });

    // Check if we need to open edit modal from URL parameter
    <?php if ($edit_site): ?>
    openEditSiteModal(<?= json_encode($edit_site) ?>);
    <?php endif; ?>

    // Check if we need to open view modal from URL parameter
    <?php if ($view_site): ?>
    showSiteDetails(<?= json_encode($view_site) ?>);
    <?php endif; ?>
});

// Function to open add site modal
function openAddSiteModal() {
    $('#addSiteForm')[0].reset();
    $('#addSiteModal').modal('show');
}

// Function to open edit site modal
function editSite(siteId) {
    // Show loading
    Swal.fire({
        title: 'Loading...',
        text: 'Fetching site details',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch site details via AJAX
    $.ajax({
        url: 'ajax/get_site_for_edit.php',
        method: 'GET',
        data: { site_id: siteId },
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const site = response.data;
                
                // Populate edit form
                $('#edit_site_id').val(site.site_id);
                $('#edit_site_name').val(site.site_name);
                $('#edit_site_address').val(site.site_address);
                $('#edit_city').val(site.city || '');
                $('#edit_state').val(site.state || '');
                $('#edit_country').val(site.country || 'India');
                $('#edit_postal_code').val(site.postal_code || '');
                $('#edit_project_type').val(site.project_type || '');
                $('#edit_engineer_id').val(site.engineer_id || '');
                $('#edit_start_date').val(site.start_date || '');
                $('#edit_expected_end_date').val(site.expected_end_date || '');
                $('#edit_status').val(site.status || 'active');
                
                // Show modal
                $('#editSiteModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to load site details'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load site details: ' + error
            });
        }
    });
}

// Function to view site details
function viewSite(siteId) {
    $('#viewSiteModal').modal('show');
    $('#siteDetailsContent').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-info" role="status"></div>
            <p class="mt-3">Loading site details...</p>
        </div>
    `);
    
    $.ajax({
        url: 'ajax/get_site_details.php',
        method: 'GET',
        data: { site_id: siteId },
        success: function(response) {
            $('#siteDetailsContent').html(response);
        },
        error: function() {
            $('#siteDetailsContent').html(`
                <div class="alert alert-danger m-4">
                    <i class="bx bx-error-circle me-2"></i>
                    Failed to load site details. Please try again.
                </div>
            `);
        }
    });
}

// Function to show site details directly from PHP data
function showSiteDetails(site) {
    let html = `
        <div class="p-3">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-3">${escapeHtml(site.site_name)}</h4>
                    
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Location Information</h6>
                            <p><strong>Address:</strong> ${escapeHtml(site.site_address).replace(/\n/g, '<br>')}</p>
    `;
    
    if (site.city || site.state || site.postal_code) {
        html += `<p><strong>City/State:</strong> ${escapeHtml(site.city || '')}`;
        if (site.state) html += `, ${escapeHtml(site.state)}`;
        if (site.postal_code) html += ` - ${escapeHtml(site.postal_code)}`;
        html += `</p>`;
    }
    
    if (site.country) {
        html += `<p><strong>Country:</strong> ${escapeHtml(site.country)}</p>`;
    }
    
    html += `
                        </div>
                    </div>
                    
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Project Details</h6>
    `;
    
    if (site.project_type) {
        html += `<p><strong>Project Type:</strong> ${escapeHtml(site.project_type)}</p>`;
    }
    
    html += `<p><strong>Timeline:</strong> `;
    if (site.start_date) {
        html += formatDate(site.start_date);
    } else {
        html += 'Not started';
    }
    html += ` → `;
    if (site.expected_end_date) {
        html += formatDate(site.expected_end_date);
    } else {
        html += 'No end date';
    }
    html += `</p>`;
    
    let statusClass = {
        'active': 'success',
        'completed': 'info',
        'on_hold': 'warning',
        'inactive': 'danger'
    }[site.status] || 'secondary';
    
    html += `
                            <p><strong>Status:</strong> <span class="badge bg-${statusClass} ms-2">${escapeHtml(site.status.replace('_', ' '))}</span></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Assigned Engineer</h6>
    `;
    
    if (site.engineer_id) {
        html += `
                            <div class="text-center mb-3">
                                <div class="avatar-lg mx-auto mb-3">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                         style="width: 80px; height: 80px; margin: 0 auto;">
                                        <i class="bx bx-user fs-1"></i>
                                    </div>
                                </div>
                                <h5>${escapeHtml(site.first_name)} ${escapeHtml(site.last_name)}</h5>
        `;
        if (site.engineer_specialization) {
            html += `<p class="text-muted">${escapeHtml(site.engineer_specialization)}</p>`;
        }
        html += `
                            </div>
                            <div class="mt-3">
                                <p><i class="bx bx-envelope me-2"></i> ${escapeHtml(site.engineer_email)}</p>
        `;
        if (site.engineer_phone) {
            html += `<p><i class="bx bx-phone me-2"></i> ${escapeHtml(site.engineer_phone)}</p>`;
        }
        html += `
                            </div>
                            <div class="d-grid mt-3">
                                <a href="engineers.php?view=${site.engineer_id}" class="btn btn-outline-primary btn-sm">
                                    View Engineer Profile
                                </a>
                            </div>
        `;
    } else {
        html += `<p class="text-muted text-center">No engineer assigned to this site.</p>`;
    }
    
    html += `
                        </div>
                    </div>
                    
                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Metadata</h6>
                            <p><small><strong>Created:</strong> ${formatDateTime(site.created_at)}</small></p>
                            <p><small><strong>Last Updated:</strong> ${formatDateTime(site.updated_at)}</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#siteDetailsContent').html(html);
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to format date
function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

// Helper function to format datetime
function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Delete site function with SweetAlert
function deleteSite(siteId, siteName) {
    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete site <strong>${siteName}</strong><br><br>This action cannot be undone if the site has no associated invoices.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteSiteId').value = siteId;
            document.getElementById('deleteSiteForm').submit();
        }
    });
}

// Function to open edit modal with pre-filled data (for PHP direct edit)
function openEditSiteModal(site) {
    $('#edit_site_id').val(site.site_id);
    $('#edit_site_name').val(site.site_name);
    $('#edit_site_address').val(site.site_address);
    $('#edit_city').val(site.city || '');
    $('#edit_state').val(site.state || '');
    $('#edit_country').val(site.country || 'India');
    $('#edit_postal_code').val(site.postal_code || '');
    $('#edit_project_type').val(site.project_type || '');
    $('#edit_engineer_id').val(site.engineer_id || '');
    $('#edit_start_date').val(site.start_date || '');
    $('#edit_expected_end_date').val(site.expected_end_date || '');
    $('#edit_status').val(site.status || 'active');
    
    $('#editSiteModal').modal('show');
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
.avatar-lg {
    width: 80px;
    height: 80px;
}
.badge.bg-opacity-10 {
    opacity: 0.9;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    vertical-align: middle;
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
.btn-group .btn {
    padding: 0.25rem 0.5rem;
}
</style>
</body>
</html>