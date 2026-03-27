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

// Only admin and managers can manage engineers
if (!$is_manager) {
    header('Location: dashboard.php');
    exit();
}

// ==================== HANDLE FORM SUBMISSIONS ====================
$message = '';
$error = '';

// Add/Edit Engineer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add New Engineer
        if ($_POST['action'] === 'add' && isset($_POST['first_name'], $_POST['last_name'], $_POST['email'])) {
            try {
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone'] ?? '');
                $specialization = trim($_POST['specialization'] ?? '');
                $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
                $status = $_POST['status'] ?? 'active';
                
               
                    $stmt = $pdo->prepare("
                        INSERT INTO engineers (first_name, last_name, email, phone, specialization, hire_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $specialization, $hire_date, $status]);
                    
                    $_SESSION['success'] = "Engineer added successfully!";
                    header('Location: engineers.php');
                    exit();
               
            } catch (PDOException $e) {
                $error = "Error adding engineer: " . $e->getMessage();
            }
        }
        
        // Edit Engineer
        elseif ($_POST['action'] === 'edit' && isset($_POST['engineer_id'], $_POST['first_name'], $_POST['last_name'], $_POST['email'])) {
            try {
                $engineer_id = (int)$_POST['engineer_id'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone'] ?? '');
                $specialization = trim($_POST['specialization'] ?? '');
                $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
                $status = $_POST['status'] ?? 'active';
                
                // Check if email already exists for another engineer
                $check_stmt = $pdo->prepare("SELECT engineer_id FROM engineers WHERE email = ? AND engineer_id != ?");
                $check_stmt->execute([$email, $engineer_id]);
                if ($check_stmt->fetch()) {
                    $error = "Email already exists for another engineer!";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE engineers 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                            specialization = ?, hire_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE engineer_id = ?
                    ");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $specialization, $hire_date, $status, $engineer_id]);
                    
                    $_SESSION['success'] = "Engineer updated successfully!";
                    header('Location: engineers.php');
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Error updating engineer: " . $e->getMessage();
            }
        }
        
        // Delete Engineer
        elseif ($_POST['action'] === 'delete' && isset($_POST['engineer_id'])) {
            try {
                $engineer_id = (int)$_POST['engineer_id'];
                
                // Check if engineer is assigned to any sites
                $check_sites = $pdo->prepare("SELECT COUNT(*) as count FROM sites WHERE engineer_id = ?");
                $check_sites->execute([$engineer_id]);
                $site_count = $check_sites->fetchColumn();
                
                if ($site_count > 0) {
                    $error = "Cannot delete engineer because they are assigned to $site_count site(s). Please reassign or delete the sites first.";
                } else {
                    // Check if engineer is assigned to any invoices
                    $check_invoices = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE engineer_id = ?");
                    $check_invoices->execute([$engineer_id]);
                    $invoice_count = $check_invoices->fetchColumn();
                    
                    if ($invoice_count > 0) {
                        $error = "Cannot delete engineer because they are associated with $invoice_count invoice(s). Please reassign or delete the invoices first.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM engineers WHERE engineer_id = ?");
                        $stmt->execute([$engineer_id]);
                        
                        $_SESSION['success'] = "Engineer deleted successfully!";
                        header('Location: engineers.php');
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $error = "Error deleting engineer: " . $e->getMessage();
            }
        }
    }
}

// ==================== GET ALL ENGINEERS ====================
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM sites WHERE engineer_id = e.engineer_id) as site_count,
        (SELECT COUNT(*) FROM invoices WHERE engineer_id = e.engineer_id) as invoice_count
        FROM engineers e
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.phone LIKE ? OR e.specialization LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($status_filter)) {
    $sql .= " AND e.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY e.first_name, e.last_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$engineers = $stmt->fetchAll();

// Get engineer for editing if ID is provided
$edit_engineer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM engineers WHERE engineer_id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_engineer = $stmt->fetch();
}

// Statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'on_leave' => 0
];

$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave
    FROM engineers
");
$stats_data = $stats_stmt->fetch();
if ($stats_data) {
    $stats = $stats_data;
}
?>

<!doctype html>
<html lang="en">
<?php $page_title = "Engineers Management"; include 'includes/head.php'; ?>
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
                                    <i class="bx bx-hard-hat me-2"></i> Engineers Management
                                    <small class="text-muted ms-2">
                                        <i class="bx bx-buildings me-1"></i>
                                        <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                    </small>
                                </h4>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="sites.php" class="btn btn-info">
                                    <i class="bx bx-map me-1"></i> Manage Sites
                                </a>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEngineerModal">
                                    <i class="bx bx-plus-circle me-1"></i> Add New Engineer
                                </button>
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
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bx bx-error-circle me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4 g-3">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-primary border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Engineers</h6>
                                        <h3 class="mb-0 text-primary"><?= number_format($stats['total'] ?? 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-primary bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-group text-primary"></i>
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
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-hover border-start border-warning border-4 shadow-sm h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">On Leave</h6>
                                        <h3 class="mb-0 text-warning"><?= number_format($stats['on_leave'] ?? 0) ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-warning bg-opacity-10 rounded-circle fs-3">
                                            <i class="bx bx-time text-warning"></i>
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
                            <i class="bx bx-filter-alt me-1"></i> Filter Engineers
                        </h5>
                        <form method="GET" id="filterForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-8 col-md-6">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bx bx-search"></i>
                                        </span>
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Name / Email / Phone / Specialization"
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        <option value="on_leave" <?= $status_filter === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                                    </select>
                                </div>
                                <div class="col-lg-1 col-md-2">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-filter me-1"></i>
                                        </button>
                                        <?php if (!empty($search) || !empty($status_filter)): ?>
                                        <a href="engineers.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-reset"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Engineers Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="engineersTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Engineer</th>
                                        <th>Contact</th>
                                        <th>Specialization</th>
                                        <th class="text-center">Hire Date</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Assigned</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($engineers)): ?>
                                    
                                    <?php else: ?>
                                    <?php foreach ($engineers as $eng): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3 flex-shrink-0">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 48px; height: 48px;">
                                                        <i class="bx bx-user fs-4"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <strong class="d-block mb-1"><?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?></strong>
                                                    <small class="text-muted">ID: #<?= $eng['engineer_id'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="mb-1">
                                                    <i class="bx bx-envelope text-muted me-1"></i>
                                                    <a href="mailto:<?= htmlspecialchars($eng['email']) ?>"><?= htmlspecialchars($eng['email']) ?></a>
                                                </div>
                                                <?php if (!empty($eng['phone'])): ?>
                                                <div>
                                                    <i class="bx bx-phone text-muted me-1"></i>
                                                    <a href="tel:<?= htmlspecialchars($eng['phone']) ?>"><?= htmlspecialchars($eng['phone']) ?></a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($eng['specialization'])): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2">
                                                <?= htmlspecialchars($eng['specialization']) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($eng['hire_date']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="bx bx-calendar me-1"></i><?= date('d M Y', strtotime($eng['hire_date'])) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $status_class = [
                                                'active' => 'success',
                                                'inactive' => 'danger',
                                                'on_leave' => 'warning'
                                            ][$eng['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $status_class ?> bg-opacity-10 text-<?= $status_class ?> px-3 py-2">
                                                <i class="bx bx-<?= $eng['status'] === 'active' ? 'check-circle' : ($eng['status'] === 'on_leave' ? 'time' : 'x-circle') ?> me-1"></i>
                                                <?= ucfirst(str_replace('_', ' ', $eng['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                                    <i class="bx bx-map me-1"></i> Sites: <?= $eng['site_count'] ?>
                                                </span>
                                                <span class="badge bg-info bg-opacity-10 text-info">
                                                    <i class="bx bx-receipt me-1"></i> Invoices: <?= $eng['invoice_count'] ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="?edit=<?= $eng['engineer_id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-success btn-sm" 
                                                        onclick="viewEngineer(<?= $eng['engineer_id'] ?>)" title="View Details">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="deleteEngineer(<?= $eng['engineer_id'] ?>, '<?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?>')" 
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

<!-- Add Engineer Modal -->
<div class="modal fade" id="addEngineerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-user-plus me-2"></i> Add New Engineer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="engineers.php" id="addEngineerForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                                <input type="email" name="email" class="form-control"  maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bx bx-phone"></i></span>
                                <input type="tel" name="phone" class="form-control" required maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-control" placeholder="e.g., Electrical, Plumbing, etc." maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hire Date</label>
                            <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save Engineer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Engineer Modal -->
<?php if ($edit_engineer): ?>
<div class="modal fade" id="editEngineerModal" tabindex="-1" aria-hidden="true" style="display: block;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="bx bx-edit me-2"></i> Edit Engineer</h5>
                <button type="button" class="btn-close btn-close-white" onclick="window.location.href='engineers.php'"></button>
            </div>
            <form method="POST" action="engineers.php" id="editEngineerForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="engineer_id" value="<?= $edit_engineer['engineer_id'] ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($edit_engineer['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($edit_engineer['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_engineer['email']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bx bx-phone"></i></span>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($edit_engineer['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($edit_engineer['specialization'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hire Date</label>
                            <input type="date" name="hire_date" class="form-control" value="<?= $edit_engineer['hire_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $edit_engineer['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $edit_engineer['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="on_leave" <?= $edit_engineer['status'] === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='engineers.php'">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-save me-1"></i> Update Engineer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Engineer Form (Hidden) -->
<form method="POST" id="deleteEngineerForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="engineer_id" id="deleteEngineerId">
</form>

<!-- View Engineer Details Modal -->
<div class="modal fade" id="viewEngineerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-user-detail me-2"></i> Engineer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="engineerDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-3">Loading engineer details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php') ?>
<?php include('includes/scripts.php') ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var engineersTable = $('#engineersTable').DataTable({
        responsive: true,
        pageLength: 25,
        ordering: true,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [6] }
        ],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "Search engineers:",
            lengthMenu: "Show _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ engineers",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    // Auto-hide alerts
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
});

// Delete engineer function
function deleteEngineer(engineerId, engineerName) {
    if (confirm(`Are you sure you want to delete engineer "${engineerName}"?\n\nThis action cannot be undone if the engineer has no associated records.`)) {
        document.getElementById('deleteEngineerId').value = engineerId;
        document.getElementById('deleteEngineerForm').submit();
    }
}

// View engineer details
function viewEngineer(engineerId) {
    $('#viewEngineerModal').modal('show');
    
    $.ajax({
        url: 'ajax/get_engineer_details.php',
        method: 'GET',
        data: { engineer_id: engineerId },
        success: function(response) {
            $('#engineerDetailsContent').html(response);
        },
        error: function() {
            $('#engineerDetailsContent').html(`
                <div class="alert alert-danger m-4">
                    <i class="bx bx-error-circle me-2"></i>
                    Failed to load engineer details. Please try again.
                </div>
            `);
        }
    });
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