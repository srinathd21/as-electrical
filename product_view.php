<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';
include('includes/functions.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$can_edit = in_array($user_role, ['admin', 'shop_manager', 'stock_manager']);
$current_shop_id = $_SESSION['current_shop_id'] ?? null;
$current_business_id = $_SESSION['current_business_id'] ?? null;

// Get product ID
$product_id = $_GET['id'] ?? 0;
if (!$product_id || !is_numeric($product_id)) {
    set_flash_message('error', 'Invalid product');
    header('Location: products.php');
    exit();
}

try {
    // Main product query with new pricing fields
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, g.hsn_code, 
               (g.cgst_rate + g.sgst_rate + g.igst_rate) AS gst_total,
               s.subcategory_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        LEFT JOIN gst_rates g ON p.gst_id = g.id
        WHERE p.id = ? AND p.business_id = ?
    ");
    $stmt->execute([$product_id, $current_business_id]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash_message('error', 'Product not found');
        header('Location: products.php');
        exit();
    }
    
    // Check if old pricing fields exist and migrate to new structure for display
    if (!isset($product['cost_price']) && isset($product['stock_price'])) {
        // This is an old product with old pricing structure
        // Initialize new pricing fields with old values for display
        $product['mrp'] = $product['stock_price'] ?? 0;
        $product['cost_price'] = $product['stock_price'] ?? 0;
        $product['discount_type'] = 'percentage';
        $product['discount_value'] = 0;
        $product['retail_price_type'] = 'percentage';
        $product['retail_price_value'] = 0;
        $product['wholesale_price_type'] = 'percentage';
        $product['wholesale_price_value'] = 0;
        
        // Calculate retail markup if retail_price exists
        if (isset($product['retail_price']) && $product['retail_price'] > 0 && $product['cost_price'] > 0) {
            $product['retail_price_value'] = (($product['retail_price'] - $product['cost_price']) / $product['cost_price']) * 100;
        }
        
        // Calculate wholesale markup if wholesale_price exists
        if (isset($product['wholesale_price']) && $product['wholesale_price'] > 0 && $product['cost_price'] > 0) {
            $product['wholesale_price_value'] = (($product['wholesale_price'] - $product['cost_price']) / $product['cost_price']) * 100;
        }
    }
    
    // Ensure all required fields exist
    $required_fields = [
        'mrp', 'cost_price', 'discount_type', 'discount_value',
        'retail_price_type', 'retail_price_value', 'retail_price',
        'wholesale_price_type', 'wholesale_price_value', 'wholesale_price'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($product[$field])) {
            $product[$field] = 0;
        }
    }

    // Get stock in current shop
    $shop_stock = 0;
    if ($current_shop_id) {
        $stmt = $pdo->prepare("SELECT quantity FROM product_stocks WHERE product_id = ? AND shop_id = ? AND business_id = ?");
        $stmt->execute([$product_id, $current_shop_id, $current_business_id]);
        $shop_stock = $stmt->fetchColumn() ?: 0;
    }

    // Get warehouse stock
    $warehouse_stmt = $pdo->prepare("SELECT id FROM shops WHERE is_warehouse = 1 AND business_id = ? LIMIT 1");
    $warehouse_stmt->execute([$current_business_id]);
    $warehouse_id = $warehouse_stmt->fetchColumn();

    $warehouse_stock = 0;
    if ($warehouse_id) {
        $stmt = $pdo->prepare("SELECT quantity FROM product_stocks WHERE product_id = ? AND shop_id = ? AND business_id = ?");
        $stmt->execute([$product_id, $warehouse_id, $current_business_id]);
        $warehouse_stock = $stmt->fetchColumn() ?: 0;
    }

    $total_stock = $shop_stock + $warehouse_stock;
    
    // Calculate profit using new pricing structure
    $cost_price = $product['cost_price'] ?? $product['stock_price'] ?? 0;
    $retail_price = $product['retail_price'] ?? 0;
    $wholesale_price = $product['wholesale_price'] ?? 0;
    
    $retail_profit_per_unit = $retail_price - $cost_price;
    $wholesale_profit_per_unit = $wholesale_price - $cost_price;
    
    $retail_profit_margin = $cost_price > 0 ? round(($retail_profit_per_unit / $cost_price) * 100, 1) : 0;
    $wholesale_profit_margin = $cost_price > 0 ? round(($wholesale_profit_per_unit / $cost_price) * 100, 1) : 0;
    
    // Calculate discount amount and percentage
    $discount_amount = 0;
    $discount_percentage = 0;
    if ($product['discount_value'] > 0) {
        if ($product['discount_type'] == 'percentage') {
            $discount_percentage = $product['discount_value'];
            $discount_amount = $product['mrp'] * ($discount_percentage / 100);
        } else {
            $discount_amount = $product['discount_value'];
            $discount_percentage = $product['mrp'] > 0 ? ($discount_amount / $product['mrp']) * 100 : 0;
        }
    }
    
    // Format discount display
    $discount_display = '';
    if ($product['discount_value'] > 0) {
        if ($product['discount_type'] == 'percentage') {
            $discount_display = $product['discount_value'] . '%';
        } else {
            $discount_display = '₹' . number_format($product['discount_value'], 2);
        }
    }

} catch (Exception $e) {
    error_log("Product view error: " . $e->getMessage());
    set_flash_message('error', 'Error loading product: ' . $e->getMessage());
    header('Location: products.php');
    exit();
}
?>

<!doctype html>
<html lang="en">
<?php $page_title = "Product: " . htmlspecialchars($product['product_name']); include 'includes/head.php'; ?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include 'includes/topbar.php'; ?>
    <div class="vertical-menu"><div data-simplebar class="h-100">
        <?php include 'includes/sidebar.php'; ?>
    </div></div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- Breadcrumb -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">
                                <a href="products.php" class="text-muted"><i class="bx bx-arrow-back"></i> Products</a>
                                <span class="mx-2 text-muted">/</span>
                                <span><?= htmlspecialchars($product['product_name']) ?></span>
                            </h4>
                            <div>
                                <?php if ($can_edit): ?>
                                <a href="product_edit.php?id=<?= $product['id'] ?>" class="btn btn-warning">
                                    <i class="bx bx-edit"></i> Edit Product
                                </a>
                                <?php endif; ?>
                                <?php if ($current_shop_id && in_array($user_role, ['admin', 'shop_manager', 'seller', 'cashier'])): ?>
                                <a href="pos.php?add=<?= $product['id'] ?>" class="btn btn-success">
                                    <i class="bx bx-cart-add"></i> Quick Sale
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php display_flash_message(); ?>

                <div class="row">
                    <!-- Product Info -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <!-- Product Image -->
                                    <div class="col-md-4 text-center">
                                        <div class="bg-light rounded p-4">
                                            <?php if ($product['image_thumbnail_path']): ?>
                                                <img src="<?= htmlspecialchars($product['image_thumbnail_path']) ?>" 
                                                     alt="<?= htmlspecialchars($product['image_alt_text'] ?? $product['product_name']) ?>"
                                                     class="img-fluid rounded mb-3" style="max-height: 200px; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="avatar-lg mx-auto mb-3 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="bx bx-package font-size-48 text-primary"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h4><?= htmlspecialchars($product['product_name']) ?></h4>
                                            <p class="text-muted">
                                                <strong>Code:</strong> <?= htmlspecialchars($product['product_code'] ?: '—') ?><br>
                                                <strong>Barcode:</strong> <?= htmlspecialchars($product['barcode'] ?: '—') ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Details -->
                                    <div class="col-md-8">
                                        <div class="table-responsive">
                                            <table class="table table-borderless">
                                                <tr><th width="180">Category</th><td><?= htmlspecialchars($product['category_name'] ?: '—') ?></td></tr>
                                                <?php if ($product['subcategory_name']): ?>
                                                <tr><th>Subcategory</th><td><?= htmlspecialchars($product['subcategory_name']) ?></td></tr>
                                                <?php endif; ?>
                                                <tr><th>HSN Code</th><td><?= htmlspecialchars($product['hsn_code'] ?: '—') ?></td></tr>
                                                <tr><th>GST Rate</th><td><?= $product['gst_total'] ?: 0 ?>%</td></tr>
                                                <tr><th>Unit</th><td><?= htmlspecialchars($product['unit_of_measure']) ?></td></tr>
                                                <tr><th>Min Stock Level</th><td><?= $product['min_stock_level'] ?></td></tr>
                                                <tr><th>Status</th>
                                                    <td>
                                                        <span class="badge bg-<?= $product['is_active'] ? 'success' : 'danger' ?>">
                                                            <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php if ($product['description']): ?>
                                                <tr><th>Description</th><td><?= nl2br(htmlspecialchars($product['description'])) ?></td></tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Card -->
                        <div class="card mt-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bx bx-rupee me-2"></i> Pricing Details</h5>
                            </div>
                            <div class="card-body">
                                <!-- MRP and Discount Section -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded">
                                            <h6 class="text-muted mb-2">MRP (Maximum Retail Price)</h6>
                                            <h4 class="text-dark">₹<?= number_format($product['mrp'], 2) ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded">
                                            <h6 class="text-muted mb-2">Discount</h6>
                                            <h4 class="<?= $discount_amount > 0 ? 'text-success' : 'text-muted' ?>">
                                                <?= $discount_display ?: 'No Discount' ?>
                                            </h4>
                                            <?php if ($discount_amount > 0): ?>
                                            <small class="text-muted">You save: ₹<?= number_format($discount_amount, 2) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cost Price Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="p-3 border rounded bg-light">
                                            <h6 class="text-muted mb-2">Cost Price</h6>
                                            <h3 class="text-primary">₹<?= number_format($cost_price, 2) ?></h3>
                                            <?php if ($discount_amount > 0): ?>
                                            <small class="text-muted">
                                                Calculated from MRP (₹<?= number_format($product['mrp'], 2) ?>) 
                                                <?php if ($product['discount_type'] == 'percentage'): ?>
                                                with <?= $product['discount_value'] ?>% discount
                                                <?php else: ?>
                                                less ₹<?= number_format($product['discount_value'], 2) ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Retail Price Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="bx bx-store-alt me-1"></i> Retail Price (For Retail Customers)
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <h6 class="text-muted mb-2">Markup</h6>
                                                    <h5 class="text-info">
                                                        <?php if ($product['retail_price_type'] == 'percentage'): ?>
                                                            <?= number_format($product['retail_price_value'], 1) ?>%
                                                        <?php else: ?>
                                                            ₹<?= number_format($product['retail_price_value'], 2) ?>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <small class="text-muted">
                                                        <?= $product['retail_price_type'] == 'percentage' ? 'Percentage' : 'Fixed' ?> markup
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <h6 class="text-muted mb-2">Retail Price</h6>
                                                    <h3 class="text-success">₹<?= number_format($retail_price, 2) ?></h3>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <h6 class="text-muted mb-2">Profit Margin</h6>
                                                    <h4 class="text-success"><?= number_format($retail_profit_margin, 1) ?>%</h4>
                                                    <small class="text-muted">
                                                        Profit: ₹<?= number_format($retail_profit_per_unit, 2) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Wholesale Price Section -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="bx bx-building-house me-1"></i> Wholesale Price (For Wholesale Customers)
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <h6 class="text-muted mb-2">Markup</h6>
                                                    <h5 class="text-info">
                                                        <?php if ($product['wholesale_price_type'] == 'percentage'): ?>
                                                            <?= number_format($product['wholesale_price_value'], 1) ?>%
                                                        <?php else: ?>
                                                            ₹<?= number_format($product['wholesale_price_value'], 2) ?>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <small class="text-muted">
                                                        <?= $product['wholesale_price_type'] == 'percentage' ? 'Percentage' : 'Fixed' ?> markup
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <h6 class="text-muted mb-2">Wholesale Price</h6>
                                                    <h3 class="text-info">₹<?= number_format($wholesale_price, 2) ?></h3>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <h6 class="text-muted mb-2">Profit Margin</h6>
                                                    <h4 class="text-primary"><?= number_format($wholesale_profit_margin, 1) ?>%</h4>
                                                    <small class="text-muted">
                                                        Profit: ₹<?= number_format($wholesale_profit_per_unit, 2) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary Section -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="p-3 bg-light rounded">
                                            <div class="row text-center">
                                                <div class="col-md-4">
                                                    <h6>Retail vs Wholesale</h6>
                                                    <h5 class="text-dark">
                                                        ₹<?= number_format(($retail_price - $wholesale_price), 2) ?>
                                                    </h5>
                                                    <small class="text-muted">Difference</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6>Best Margin</h6>
                                                    <h5 class="<?= $retail_profit_margin >= $wholesale_profit_margin ? 'text-success' : 'text-primary' ?>">
                                                        <?= max($retail_profit_margin, $wholesale_profit_margin) ?>%
                                                    </h5>
                                                    <small class="text-muted">
                                                        <?= $retail_profit_margin >= $wholesale_profit_margin ? 'Retail' : 'Wholesale' ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6>Price Ratio</h6>
                                                    <h5 class="text-dark">
                                                        <?= $wholesale_price > 0 ? number_format(($retail_price / $wholesale_price), 2) : '0.00' ?>:1
                                                    </h5>
                                                    <small class="text-muted">Retail:Wholesale</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Summary -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bx bx-box me-2"></i> Stock Summary</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($current_shop_id): ?>
                                <div class="mb-4 p-3 bg-light rounded text-center">
                                    <h3 class="mb-1 <?= $shop_stock == 0 ? 'text-danger' : ($shop_stock < $product['min_stock_level'] ? 'text-warning' : 'text-success') ?>">
                                        <?= $shop_stock ?>
                                    </h3>
                                    <p class="mb-0 text-muted">Current Shop Stock</p>
                                    <small>
                                        <?= htmlspecialchars($_SESSION['current_shop_name'] ?? 'Current Shop') ?>
                                    </small>
                                </div>
                                <?php endif; ?>

                                <div class="text-center mb-4">
                                    <h2 class="<?= $total_stock == 0 ? 'text-danger' : ($total_stock < $product['min_stock_level'] ? 'text-warning' : 'text-success') ?>">
                                        <?= $total_stock ?>
                                    </h2>
                                    <p class="mb-1">Total Available Stock</p>
                                    <small class="text-muted">Minimum Required: <?= $product['min_stock_level'] ?></small>
                                </div>

                                <hr>

                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="p-2">
                                            <h5><?= $shop_stock ?></h5>
                                            <small class="text-muted">In Shop</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2">
                                            <h5><?= $warehouse_stock ?></h5>
                                            <small class="text-muted">In Warehouse</small>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($total_stock < $product['min_stock_level']): ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="bx bx-error"></i> <strong>Low stock alert!</strong> Below minimum level.
                                </div>
                                <?php elseif ($total_stock == 0): ?>
                                <div class="alert alert-danger mt-3 mb-0">
                                    <i class="bx bx-box"></i> <strong>Out of stock!</strong> No units available.
                                </div>
                                <?php endif; ?>

                                <!-- Stock Value -->
                                <div class="mt-4 p-3 border rounded">
                                    <h6 class="text-muted mb-2">Stock Value (at Cost Price)</h6>
                                    <h4 class="text-primary">₹<?= number_format($total_stock * $cost_price, 2) ?></h4>
                                    <small class="text-muted">
                                        Potential Retail Value: ₹<?= number_format($total_stock * $retail_price, 2) ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bx bx-info-circle me-2"></i> Additional Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="140">Product ID</th>
                                        <td><?= $product['id'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Created</th>
                                        <td><?= date('d M Y', strtotime($product['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Updated</th>
                                        <td><?= date('d M Y', strtotime($product['updated_at'] ?? $product['created_at'])) ?></td>
                                    </tr>
                                    <?php if ($product['referral_enabled']): ?>
                                    <tr>
                                        <th>Referral Commission</th>
                                        <td>
                                            <span class="badge bg-success">Enabled</span>
                                            <?= $product['referral_value'] ?>
                                            <?= $product['referral_type'] == 'percentage' ? '%' : '₹' ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($product['image_alt_text']): ?>
                                    <tr>
                                        <th>Image Alt Text</th>
                                        <td><?= htmlspecialchars($product['image_alt_text']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bx bx-rocket me-2"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($current_shop_id && in_array($user_role, ['admin', 'shop_manager', 'stock_manager'])): ?>
                                    <a href="stock_adjustment.php?product_id=<?= $product['id'] ?>" class="btn btn-outline-primary">
                                        <i class="bx bx-adjust"></i> Adjust Stock
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($current_shop_id && in_array($user_role, ['admin', 'shop_manager', 'seller', 'cashier'])): ?>
                                    <a href="pos.php?add=<?= $product['id'] ?>" class="btn btn-success">
                                        <i class="bx bx-cart-add"></i> Sell This Product
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($can_edit): ?>
                                    <a href="product_edit.php?id=<?= $product['id'] ?>" class="btn btn-outline-warning">
                                        <i class="bx bx-edit"></i> Edit Product Details
                                    </a>
                                    <?php endif; ?>
                                    <a href="stock_history.php?product_id=<?= $product['id'] ?>" class="btn btn-outline-info">
                                        <i class="bx bx-history"></i> View Stock History
                                    </a>
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

<?php include 'includes/rightbar.php'; ?>
<?php include 'includes/scripts.php'; ?>

<script>
// Add any JavaScript functionality if needed
document.addEventListener('DOMContentLoaded', function() {
    // Optional: Add any interactivity here
});
</script>

<style>
.avatar-lg {
    width: 120px;
    height: 120px;
}
.border-dashed {
    border-style: dashed;
}
.text-summary {
    font-size: 0.9rem;
}
.card-header {
    border-bottom: 2px solid rgba(0,0,0,.125);
}
.table-borderless th {
    font-weight: 600;
    color: #495057;
}
.bg-light {
    background-color: #f8f9fa !important;
}
</style>
</body>
</html>