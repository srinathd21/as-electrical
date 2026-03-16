<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Role check
if (!in_array($_SESSION['role'], ['admin', 'warehouse_manager', 'shop_manager','stock_manager'])) {
    header('Location: dashboard.php');
    exit();
}

$current_business_id = (int)$_SESSION['current_business_id'];

$success = $error = '';

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE categories SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND business_id = ?");
        $stmt->execute([$id, $current_business_id]);
        $success = "Category status updated!";
    } catch (Exception $e) {
        $error = "Error updating status.";
    }
}

// Delete Category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND business_id = ?");
        $stmt->execute([$id, $current_business_id]);
        $success = "Category deleted successfully!";
    } catch (Exception $e) {
        $error = "Cannot delete category: it has sub-categories or associated products.";
    }
}

// Save (Add or Update) Category
if (isset($_POST['save_category'])) {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['category_name']);
    $parent_id = $_POST['parent_id'] == 0 ? null : (int)$_POST['parent_id'];
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        try {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, parent_id = ?, description = ? WHERE id = ? AND business_id = ?");
                $stmt->execute([$name, $parent_id, $description, $id, $current_business_id]);
                $success = "Category updated successfully!";
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO categories (category_name, parent_id, description, created_by, business_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $parent_id, $description, $_SESSION['user_id'], $current_business_id]);
                $success = "Category '$name' added successfully!";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $error = "Category name already exists.";
            } else {
                $error = "Database error.";
            }
        }
    }
}

// Fetch all categories (for list)
$categories_stmt = $pdo->prepare("
    SELECT c.*,
           p.category_name as parent_name,
           (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id AND pr.business_id = ?) as product_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    WHERE c.business_id = ?
    ORDER BY COALESCE(c.parent_id, 0), c.category_name
");
$categories_stmt->execute([$current_business_id, $current_business_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch main categories (active only) for parent dropdown
$main_cats_stmt = $pdo->prepare("
    SELECT id, category_name 
    FROM categories 
    WHERE parent_id IS NULL AND status = 'active' AND business_id = ? 
    ORDER BY category_name
");
$main_cats_stmt->execute([$current_business_id]);
$main_cats = $main_cats_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<?php $page_title = "Product Categories"; ?>
<?php include('includes/head.php') ?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php') ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php') ?>
        </div>
    </div>
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="card-title mb-0">
                                        <i class="bx bx-category me-2"></i> Product Categories
                                        <span class="badge bg-primary fs-6"><?= count($categories) ?></span>
                                    </h4>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="bx bx-plus me-1"></i> Add Category
                                    </button>
                                </div>

                                <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="bx bx-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>

                                <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="bx bx-error-circle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table id="categoriesTable" class="table table-hover table-bordered align-middle w-100">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Category Name</th>
                                                <th>Parent Category</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center">Products</th>
                                                <th>Description</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($categories) > 0): ?>
                                                <?php foreach ($categories as $cat): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($cat['parent_id']): ?>
                                                            <i class="bx bx-subdirectory-right text-muted me-2"></i>
                                                            <?php endif; ?>
                                                            <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                                                            <?php if ($cat['parent_id']): ?>
                                                            <span class="badge bg-info ms-2">Sub-category</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<em class="text-muted">Main Category</em>' ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= ($cat['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?> rounded-pill">
                                                            <?= ucfirst($cat['status'] ?? 'active') ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= $cat['product_count'] > 0 ? 'success' : 'secondary' ?> rounded-pill">
                                                            <?= $cat['product_count'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?= $cat['description'] ? htmlspecialchars($cat['description']) : '—' ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-warning"
                                                                    onclick="editCategory(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['category_name'])) ?>', <?= $cat['parent_id'] ?: '0' ?>, '<?= addslashes(htmlspecialchars($cat['description'] ?? '')) ?>')">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                            <a href="?toggle=<?= $cat['id'] ?>" class="btn btn-outline-info" title="Toggle Status">
                                                                <i class="bx <?= ($cat['status'] ?? 'active') === 'active' ? 'bx-hide' : 'bx-show' ?>"></i>
                                                            </a>
                                                            <button class="btn btn-outline-danger delete-btn"
                                                                    data-id="<?= $cat['id'] ?>"
                                                                    data-name="<?= htmlspecialchars($cat['category_name']) ?>"
                                                                    data-has-products="<?= $cat['product_count'] > 0 ? 'true' : 'false' ?>"
                                                                    title="Delete Category">
                                                                <i class="bx bx-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <div class="empty-state">
                                                            <i class="bx bx-category fs-1 text-muted mb-3"></i>
                                                            <h5>No categories found</h5>
                                                            <p class="text-muted">Get started by adding your first category</p>
                                                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                                                <i class="bx bx-plus me-1"></i> Add Category
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('includes/footer.php') ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bx bx-plus-circle"></i> Add Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><strong>Category Name <span class="text-danger">*</span></strong></label>
                    <input type="text" name="category_name" id="catName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Parent Category</label>
                    <select name="parent_id" id="catParent" class="form-select">
                        <option value="0">None (Main Category)</option>
                        <?php foreach ($main_cats as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="catDesc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_category" id="saveCategoryBtn" class="btn btn-primary">
                    <i class="bx bx-save me-2"></i> Save Category
                </button>
            </div>
        </form>
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
                <h5 class="mb-3">Delete Category</h5>
                <p class="text-muted mb-2">Are you sure you want to delete <strong id="deleteCategoryName"></strong>?</p>
                <div id="deleteWarning" class="alert alert-warning d-none">
                    <i class="bx bx-error-circle me-1"></i>
                    <span id="deleteWarningMessage"></span>
                </div>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Delete Category
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php') ?>
<?php include('includes/scripts.php') ?>
<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#categoriesTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']], // Sort by Category Name
        columnDefs: [
            { orderable: false, targets: [5] } // Disable sorting on Actions column
        ],
        language: {
            search: "Search categories:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ categories",
            emptyTable: "No categories found",
            paginate: {
                previous: "<i class='bx bx-chevron-left'></i>",
                next: "<i class='bx bx-chevron-right'></i>"
            }
        }
    });

    // Delete button handler
    $('.delete-btn').click(function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const hasProducts = $(this).data('has-products') === true;
        
        $('#deleteCategoryName').text(name);
        $('#confirmDeleteBtn').attr('href', '?delete=' + id);
        
        // Show warning if category has products
        if (hasProducts) {
            $('#deleteWarning').removeClass('d-none');
            $('#deleteWarningMessage').text('This category has associated products. Deleting it will make those products uncategorized.');
        } else {
            $('#deleteWarning').addClass('d-none');
        }
        
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    });

    // Auto-close alerts
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});

function editCategory(id, name, parent, desc) {
    document.getElementById('modalTitle').innerHTML = '<i class="bx bx-edit"></i> Edit Category';
    document.getElementById('editId').value = id;
    document.getElementById('catName').value = name;
    document.getElementById('catParent').value = parent;
    document.getElementById('catDesc').value = desc;
    document.getElementById('saveCategoryBtn').innerHTML = '<i class="bx bx-save me-2"></i> Update Category';
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

// Reset modal when closed
document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').innerHTML = '<i class="bx bx-plus-circle"></i> Add Category';
    document.querySelector('#categoryModal form').reset();
    document.getElementById('editId').val('');
    document.getElementById('saveCategoryBtn').innerHTML = '<i class="bx bx-save me-2"></i> Save Category';
});

// Reset delete modal when closed
document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
    $('#deleteWarning').addClass('d-none');
});
</script>
<style>
.table th { font-weight: 600; background-color: #f8f9fa; }
.btn-group .btn { padding: 0.375rem 0.75rem; }
.btn-group .btn:hover { transform: translateY(-1px); }
.empty-state {
    text-align: center;
    padding: 2rem;
}
.empty-state i {
    font-size: 4rem;
    opacity: 0.5;
}
#deleteModal .modal-body {
    padding: 2rem;
}
</style>
</body>
</html>