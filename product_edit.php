<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';
include('includes/functions.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current business and shop from session
$current_business_id = $_SESSION['current_business_id'] ?? null;
$current_shop_id = $_SESSION['current_shop_id'] ?? null;

if (!$current_business_id || !$current_shop_id) {
    set_flash_message('error', 'Please select a business and shop first');
    header('Location: select_shop.php');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'warehouse_manager', 'stock_manager','shop_manager'])) {
    set_flash_message('error', 'You do not have permission to edit products');
    header('Location: dashboard.php');
    exit();
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    set_flash_message('error', 'Product ID is required');
    header('Location: products.php');
    exit();
}

$success = $error = '';
$categories = $gst_rates = [];
$product = null;
$current_stock = 0;

// Image upload configuration
$upload_dir = '../uploads/products/';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_file_size = 2 * 1024 * 1024; // 2MB

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fetch categories & GST rates
try {
    $categories = $pdo->prepare("
        SELECT id, category_name 
        FROM categories 
        WHERE business_id = ? AND status = 'active' AND parent_id IS NULL 
        ORDER BY category_name
    ");
    $categories->execute([$current_business_id]);
    $categories = $categories->fetchAll();

    $gst_rates = $pdo->prepare("
        SELECT id, hsn_code, cgst_rate, sgst_rate, igst_rate,
               CONCAT(hsn_code, ' (', cgst_rate + sgst_rate + igst_rate, '%)') as display_label
        FROM gst_rates 
        WHERE business_id = ? AND status = 'active' 
        ORDER BY hsn_code
    ");
    $gst_rates->execute([$current_business_id]);
    $gst_rates = $gst_rates->fetchAll();

    // Fetch product data
    $product_stmt = $pdo->prepare("
        SELECT p.*, 
               c.category_name,
               sc.category_name as subcategory_name,
               g.hsn_code as gst_hsn_code
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN categories sc ON p.subcategory_id = sc.id
        LEFT JOIN gst_rates g ON p.gst_id = g.id
        WHERE p.id = ? AND p.business_id = ?
    ");
    $product_stmt->execute([$product_id, $current_business_id]);
    $product = $product_stmt->fetch();

    if (!$product) {
        set_flash_message('error', 'Product not found or you do not have permission to edit it');
        header('Location: products.php');
        exit();
    }

    // Get current stock for this shop
    $stock_stmt = $pdo->prepare("
        SELECT quantity FROM product_stocks 
        WHERE product_id = ? AND shop_id = ?
    ");
    $stock_stmt->execute([$product_id, $current_shop_id]);
    $stock_row = $stock_stmt->fetch();
    $current_stock = $stock_row ? $stock_row['quantity'] : 0;

} catch (Exception $e) {
    $error = "Failed to load data: " . $e->getMessage();
}

// Thumbnail function
function createThumbnail($source_path, $dest_path, $max_width = 200, $max_height = 200) {
    try {
        $image_info = getimagesize($source_path);
        if (!$image_info) return false;
        list($orig_width, $orig_height, $image_type) = $image_info;

        $ratio = min($max_width / $orig_width, $max_height / $orig_height);
        $new_width = (int)($orig_width * $ratio);
        $new_height = (int)($orig_height * $ratio);

        switch ($image_type) {
            case IMAGETYPE_JPEG: $source_image = imagecreatefromjpeg($source_path); break;
            case IMAGETYPE_PNG: $source_image = imagecreatefrompng($source_path); break;
            case IMAGETYPE_GIF: $source_image = imagecreatefromgif($source_path); break;
            case IMAGETYPE_WEBP: $source_image = imagecreatefromwebp($source_path); break;
            default: return false;
        }

        $thumbnail = imagecreatetruecolor($new_width, $new_height);

        if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
            imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }

        imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

        switch ($image_type) {
            case IMAGETYPE_JPEG: imagejpeg($thumbnail, $dest_path, 85); break;
            case IMAGETYPE_PNG: imagepng($thumbnail, $dest_path, 9); break;
            case IMAGETYPE_GIF: imagegif($thumbnail, $dest_path); break;
            case IMAGETYPE_WEBP: imagewebp($thumbnail, $dest_path, 85); break;
        }

        imagedestroy($source_image);
        imagedestroy($thumbnail);
        return true;
    } catch (Exception $e) {
        error_log("Thumbnail error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request.";
    } else {
        $product_name = trim($_POST['product_name'] ?? '');
        $product_code = trim($_POST['product_code'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $unit = $_POST['unit'] ?? 'pcs';

        // Secondary unit fields
        $secondary_unit = !empty($_POST['secondary_unit']) ? trim($_POST['secondary_unit']) : null;
        $sec_unit_conversion = !empty($_POST['sec_unit_conversion']) ? (float)$_POST['sec_unit_conversion'] : null;
        $sec_unit_price_type = $_POST['sec_unit_price_type'] ?? 'fixed';
        $sec_unit_extra_charge = !empty($_POST['sec_unit_extra_charge']) ? (float)$_POST['sec_unit_extra_charge'] : 0.00;

        $mrp = (float)($_POST['mrp'] ?? 0);
        
        // Combined discount field handling
        $discount_input = trim($_POST['discount'] ?? '');
        $discount_type = 'percentage';
        $discount_value = 0;
        
        if (!empty($discount_input)) {
            if (strpos($discount_input, '%') !== false) {
                $discount_type = 'percentage';
                $discount_value = (float)str_replace('%', '', $discount_input);
            } else {
                $discount_type = 'fixed';
                $discount_value = (float)$discount_input;
            }
        }
        
        $stock_price = (float)($_POST['stock_price'] ?? 0);
        $retail_price_type = $_POST['retail_price_type'] ?? 'percentage';
        $retail_price_value = (float)($_POST['retail_price_value'] ?? 0);
        $retail_price = (float)($_POST['retail_price'] ?? 0);
        
        $wholesale_price_type = $_POST['wholesale_price_type'] ?? 'percentage';
        $wholesale_price_value = (float)($_POST['wholesale_price_value'] ?? 0);
        $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
        
        $min_stock_level = (int)($_POST['min_stock_level'] ?? 10);
        $gst_id = !empty($_POST['gst_id']) ? (int)$_POST['gst_id'] : null;
        $image_alt_text = trim($_POST['image_alt_text'] ?? '');
        $referral_enabled = isset($_POST['referral_enabled']) ? 1 : 0;
        $referral_type = $_POST['referral_type'] ?? 'percentage';
        $referral_value = (float)($_POST['referral_value'] ?? 0);
        
        // Get is_active value from POST or default to 1
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        // Calculate stock price from MRP and discount (only if not manually entered)
        if ($mrp > 0 && $discount_value > 0 && $stock_price == 0) {
            $calculated_stock_price = $discount_type === 'percentage' 
                ? $mrp - ($mrp * $discount_value / 100)
                : $mrp - $discount_value;
            
            $stock_price = $calculated_stock_price;
        } elseif ($mrp > 0 && $stock_price == 0) {
            $stock_price = $mrp;
        }

        // Calculate retail price (only if not manually entered)
        if ($stock_price > 0 && $retail_price_value > 0 && $retail_price == 0) {
            $retail_markup_amount = $retail_price_type === 'percentage' 
                ? $stock_price * $retail_price_value / 100
                : $retail_price_value;
            $retail_price = $stock_price + $retail_markup_amount;
        } elseif ($stock_price > 0 && $retail_price == 0) {
            $retail_price = $stock_price;
        }

        // Calculate wholesale price (only if not manually entered)
        if ($stock_price > 0 && $wholesale_price_value > 0 && $wholesale_price == 0) {
            $wholesale_markup_amount = $wholesale_price_type === 'percentage' 
                ? $stock_price * $wholesale_price_value / 100
                : $wholesale_price_value;
            $wholesale_price = $stock_price + $wholesale_markup_amount;
        } elseif ($stock_price > 0 && $wholesale_price == 0) {
            $wholesale_price = $stock_price;
        }

        // Fetch HSN code
        $hsn_code = '';
        if ($gst_id) {
            $gst_stmt = $pdo->prepare("SELECT hsn_code FROM gst_rates WHERE id = ? AND business_id = ?");
            $gst_stmt->execute([$gst_id, $current_business_id]);
            $gst_row = $gst_stmt->fetch();
            $hsn_code = $gst_row['hsn_code'] ?? '';
        }

        $image_path = $product['image_path'];
        $image_thumbnail_path = $product['image_thumbnail_path'];

        // Image upload handling
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['product_image'];
            $file_name = basename($file['name']);
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $errors = [];
            if (!in_array($file_ext, $allowed_extensions)) {
                $errors[] = "Invalid file type.";
            }
            if ($file_size > $max_file_size) {
                $errors[] = "File too large (max 2MB).";
            }
            if (!getimagesize($file_tmp)) {
                $errors[] = "Not a valid image.";
            }

            if (empty($errors)) {
                $unique_name = uniqid('prod_', true) . '_' . time() . '.' . $file_ext;
                $new_image_path = $upload_dir . $unique_name;

                if (move_uploaded_file($file_tmp, $new_image_path)) {
                    // Delete old images if they exist
                    if ($image_path && file_exists('../' . $image_path)) {
                        @unlink('../' . $image_path);
                    }
                    if ($image_thumbnail_path && file_exists('../' . $image_thumbnail_path)) {
                        @unlink('../' . $image_thumbnail_path);
                    }

                    $thumbnail_name = 'thumb_' . $unique_name;
                    $thumbnail_path = $upload_dir . $thumbnail_name;
                    if (createThumbnail($new_image_path, $thumbnail_path)) {
                        $image_thumbnail_path = $thumbnail_path;
                    }
                    $image_path = str_replace('../', '', $new_image_path);
                    $image_thumbnail_path = $image_thumbnail_path ? str_replace('../', '', $image_thumbnail_path) : null;
                } else {
                    $errors[] = "Upload failed.";
                }
            }
            if (!empty($errors)) {
                $error = implode("<br>", $errors);
            }
        }

        // Handle image removal
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if ($image_path && file_exists('../' . $image_path)) {
                @unlink('../' . $image_path);
            }
            if ($image_thumbnail_path && file_exists('../' . $image_thumbnail_path)) {
                @unlink('../' . $image_thumbnail_path);
            }
            $image_path = null;
            $image_thumbnail_path = null;
        }

        if (empty($error)) {
            $errors = [];
            if (empty($product_name)) $errors[] = "Product name required.";
            if ($stock_price <= 0) $errors[] = "Stock price must be greater than 0.";
            if ($retail_price <= 0) $errors[] = "Retail price must be greater than 0.";
            if ($wholesale_price <= 0) $errors[] = "Wholesale price must be greater than 0.";
            if ($mrp < 0) $errors[] = "MRP cannot be negative.";
            
            // Price hierarchy validation
            if ($stock_price > 0 && $wholesale_price > 0 && $wholesale_price < $stock_price) {
                $errors[] = "Wholesale price must be equal to or greater than Stock Price.";
            }
            
            if ($stock_price > 0 && $retail_price > 0 && $retail_price <= $stock_price) {
                $errors[] = "Retail price must be greater than Stock Price.";
            }
            
            if ($wholesale_price > 0 && $retail_price > 0 && $wholesale_price > $retail_price) {
                $errors[] = "Wholesale price should be less than or equal to Retail Price.";
            }
            
            // MRP validation
            if ($mrp > 0) {
                if ($retail_price > $mrp) $errors[] = "Retail price cannot be higher than MRP.";
                if ($wholesale_price > $mrp) $errors[] = "Wholesale price cannot be higher than MRP.";
                if ($stock_price > $mrp) $errors[] = "Stock price cannot be higher than MRP.";
            }

            if ($discount_value < 0) $errors[] = "Discount cannot be negative.";
            if ($discount_type === 'percentage' && $discount_value > 100) $errors[] = "Discount % cannot exceed 100.";
            if ($discount_type === 'fixed' && $discount_value > $mrp && $mrp > 0) $errors[] = "Discount amount cannot exceed MRP.";

            if ($retail_price_value < 0) $errors[] = "Retail markup cannot be negative.";
            if ($wholesale_price_value < 0) $errors[] = "Wholesale markup cannot be negative.";

            if ($referral_enabled && $referral_value <= 0) $errors[] = "Referral value must be > 0.";
            if ($referral_enabled && $referral_type === 'percentage' && $referral_value > 100) $errors[] = "Referral % cannot exceed 100.";

            // Duplicate checks (excluding current product)
            if (!empty($barcode)) {
                $check = $pdo->prepare("SELECT id FROM products WHERE barcode = ? AND business_id = ? AND id != ?");
                $check->execute([$barcode, $current_business_id, $product_id]);
                if ($check->fetch()) $errors[] = "Barcode already exists for another product.";
            }
            if (!empty($product_code)) {
                $check = $pdo->prepare("SELECT id FROM products WHERE product_code = ? AND business_id = ? AND id != ?");
                $check->execute([$product_code, $current_business_id, $product_id]);
                if ($check->fetch()) $errors[] = "Product code already exists for another product.";
            }

            if (!empty($errors)) {
                $error = implode("<br>", $errors);
            } else {
                try {
                    $pdo->beginTransaction();

                    // Update product
                    $stmt = $pdo->prepare("
                        UPDATE products SET
                            product_name = ?,
                            product_code = ?,
                            barcode = ?,
                            image_path = ?,
                            image_thumbnail_path = ?,
                            image_alt_text = ?,
                            category_id = ?,
                            subcategory_id = ?,
                            description = ?,
                            unit_of_measure = ?,
                            secondary_unit = ?,
                            sec_unit_conversion = ?,
                            sec_unit_price_type = ?,
                            sec_unit_extra_charge = ?,
                            stock_price = ?,
                            retail_price = ?,
                            wholesale_price = ?,
                            min_stock_level = ?,
                            gst_id = ?,
                            hsn_code = ?,
                            referral_enabled = ?,
                            referral_type = ?,
                            referral_value = ?,
                            mrp = ?,
                            discount_type = ?,
                            discount_value = ?,
                            retail_price_type = ?,
                            retail_price_value = ?,
                            wholesale_price_type = ?,
                            wholesale_price_value = ?,
                            is_active = ?,
                            updated_at = NOW()
                        WHERE id = ? AND business_id = ?
                    ");

                    $stmt->execute([
                        $product_name,
                        $product_code ?: null,
                        $barcode ?: null,
                        $image_path,
                        $image_thumbnail_path,
                        $image_alt_text ?: null,
                        $category_id,
                        $subcategory_id,
                        $description ?: null,
                        $unit,
                        $secondary_unit,
                        $sec_unit_conversion,
                        $sec_unit_price_type,
                        $sec_unit_extra_charge,
                        $stock_price,
                        $retail_price,
                        $wholesale_price,
                        $min_stock_level,
                        $gst_id,
                        $hsn_code,
                        $referral_enabled,
                        $referral_type,
                        $referral_value,
                        $mrp,
                        $discount_type,
                        $discount_value,
                        $retail_price_type,
                        $retail_price_value,
                        $wholesale_price_type,
                        $wholesale_price_value,
                        $is_active,
                        $product_id,
                        $current_business_id
                    ]);

                    // Handle stock adjustment
                    if (isset($_POST['stock_adjustment_type']) && isset($_POST['stock_adjustment_qty'])) {
                        $adjustment_type = $_POST['stock_adjustment_type'];
                        $adjustment_qty = (int)$_POST['stock_adjustment_qty'];
                        
                        if ($adjustment_qty > 0) {
                            if ($adjustment_type === 'set') {
                                // Set to specific quantity
                                $check_stock = $pdo->prepare("SELECT id FROM product_stocks WHERE product_id = ? AND shop_id = ?");
                                $check_stock->execute([$product_id, $current_shop_id]);
                                
                                if ($check_stock->fetch()) {
                                    $pdo->prepare("UPDATE product_stocks SET quantity = ? WHERE product_id = ? AND shop_id = ?")
                                        ->execute([$adjustment_qty, $product_id, $current_shop_id]);
                                } else {
                                    $pdo->prepare("INSERT INTO product_stocks (product_id, shop_id, business_id, quantity) VALUES (?, ?, ?, ?)")
                                        ->execute([$product_id, $current_shop_id, $current_business_id, $adjustment_qty]);
                                }
                            } else {
                                // Add/remove quantity
                                $new_qty = $current_stock;
                                if ($adjustment_type === 'add') {
                                    $new_qty += $adjustment_qty;
                                } elseif ($adjustment_type === 'remove') {
                                    $new_qty -= $adjustment_qty;
                                    if ($new_qty < 0) $new_qty = 0;
                                }
                                
                                $check_stock = $pdo->prepare("SELECT id FROM product_stocks WHERE product_id = ? AND shop_id = ?");
                                $check_stock->execute([$product_id, $current_shop_id]);
                                
                                if ($check_stock->fetch()) {
                                    $pdo->prepare("UPDATE product_stocks SET quantity = ? WHERE product_id = ? AND shop_id = ?")
                                        ->execute([$new_qty, $product_id, $current_shop_id]);
                                } else {
                                    $pdo->prepare("INSERT INTO product_stocks (product_id, shop_id, business_id, quantity) VALUES (?, ?, ?, ?)")
                                        ->execute([$product_id, $current_shop_id, $current_business_id, $new_qty]);
                                }
                            }
                        }
                    }

                    $pdo->commit();
                    set_flash_message('success', "Product '$product_name' updated successfully!");
                    
                    // Refresh product data
                    $product_stmt->execute([$product_id, $current_business_id]);
                    $product = $product_stmt->fetch();
                    
                    // Refresh stock data
                    $stock_stmt->execute([$product_id, $current_shop_id]);
                    $stock_row = $stock_stmt->fetch();
                    $current_stock = $stock_row ? $stock_row['quantity'] : 0;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Database error: " . $e->getMessage();
                    error_log("Update product error: " . $e->getMessage());
                    error_log("SQL Error Info: " . print_r($pdo->errorInfo(), true));
                }
            }
        }
    }
}

// If form wasn't submitted, populate with existing data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $product) {
    $_POST['product_name'] = $product['product_name'];
    $_POST['product_code'] = $product['product_code'];
    $_POST['barcode'] = $product['barcode'];
    $_POST['category_id'] = $product['category_id'];
    $_POST['subcategory_id'] = $product['subcategory_id'];
    $_POST['description'] = $product['description'];
    $_POST['unit'] = $product['unit_of_measure'];
    $_POST['secondary_unit'] = $product['secondary_unit'];
    $_POST['sec_unit_conversion'] = $product['sec_unit_conversion'];
    $_POST['sec_unit_price_type'] = $product['sec_unit_price_type'];
    $_POST['sec_unit_extra_charge'] = $product['sec_unit_extra_charge'];
    $_POST['mrp'] = $product['mrp'];
    $_POST['stock_price'] = $product['stock_price'];
    $_POST['retail_price'] = $product['retail_price'];
    $_POST['wholesale_price'] = $product['wholesale_price'];
    $_POST['retail_price_type'] = $product['retail_price_type'];
    $_POST['retail_price_value'] = $product['retail_price_value'];
    $_POST['wholesale_price_type'] = $product['wholesale_price_type'];
    $_POST['wholesale_price_value'] = $product['wholesale_price_value'];
    $_POST['min_stock_level'] = $product['min_stock_level'];
    $_POST['gst_id'] = $product['gst_id'];
    $_POST['image_alt_text'] = $product['image_alt_text'];
    $_POST['referral_enabled'] = $product['referral_enabled'];
    $_POST['referral_type'] = $product['referral_type'];
    $_POST['referral_value'] = $product['referral_value'];
    $_POST['is_active'] = $product['is_active'];
    
    // Set discount field based on discount_type and discount_value
    if ($product['discount_type'] === 'percentage') {
        $_POST['discount'] = $product['discount_value'] . '%';
    } else {
        $_POST['discount'] = $product['discount_value'];
    }
}
?>
<!doctype html>
<html lang="en">
<?php $page_title = "Edit Product"; ?>
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

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">
                                <i class="bx bx-edit me-2"></i> Edit Product
                            </h4>
                            <div>
                                <a href="products.php" class="btn btn-outline-secondary me-2">
                                    <i class="bx bx-arrow-back me-1"></i> Back to Products
                                </a>
                                <a href="view_product.php?id=<?= $product_id ?>" class="btn btn-outline-info">
                                    <i class="bx bx-show me-1"></i> View Product
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php display_flash_message(); ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bx bx-error-circle fs-4 me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" id="editProductForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">
                                        <i class="bx bx-package me-1"></i> Product Information
                                        <small class="text-muted ms-2">
                                            Business: <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'N/A') ?>
                                            | Shop: <?= htmlspecialchars($_SESSION['current_shop_name'] ?? 'N/A') ?>
                                            | Current Stock: <span class="badge bg-success"><?= $current_stock ?></span>
                                        </small>
                                    </h5>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Category</strong></label>
                                            <select name="category_id" id="categorySelect" class="form-select" required>
                                                <option value="">-- Select Category --</option>
                                                <?php foreach($categories as $c): ?>
                                                <option value="<?= $c['id'] ?>" 
                                                    <?= (isset($_POST['category_id']) && $_POST['category_id'] == $c['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['category_name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (empty($categories)): ?>
                                            <div class="form-text text-warning">
                                                <i class="bx bx-info-circle"></i> No categories found. 
                                                <a href="categories.php" class="text-decoration-underline">Create one first</a>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Subcategory</strong></label>
                                            <select name="subcategory_id" id="subcategorySelect" class="form-select">
                                                <option value="">-- Select Subcategory --</option>
                                                <?php 
                                                // Fetch subcategories for the selected category
                                                if (isset($_POST['category_id']) && $_POST['category_id']) {
                                                    $subcategories_stmt = $pdo->prepare("
                                                        SELECT id, category_name as subcategory_name 
                                                        FROM categories 
                                                        WHERE parent_id = ? AND business_id = ? AND status = 'active'
                                                        ORDER BY category_name
                                                    ");
                                                    $subcategories_stmt->execute([$_POST['category_id'], $current_business_id]);
                                                    $subcategories = $subcategories_stmt->fetchAll();
                                                    
                                                    foreach($subcategories as $subcat): ?>
                                                    <option value="<?= $subcat['id'] ?>" 
                                                        <?= (isset($_POST['subcategory_id']) && $_POST['subcategory_id'] == $subcat['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($subcat['subcategory_name']) ?>
                                                    </option>
                                                    <?php endforeach;
                                                }
                                                ?>
                                            </select>
                                            <div class="form-text">Optional - select subcategory for better organization</div>
                                            <div id="subcategoryLoading" class="form-text text-muted" style="display: none;">
                                                <i class="bx bx-loader bx-spin"></i> Loading subcategories...
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Product Name <span class="text-danger">*</span></strong></label>
                                            <input type="text" name="product_name" class="form-control form-control-lg" 
                                                   value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" 
                                                   required autofocus>
                                            <div class="form-text">Enter the product display name</div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Product Code</strong></label>
                                            <div class="input-group">
                                                <span class="input-group-text">#</span>
                                                <input type="text" name="product_code" class="form-control" 
                                                       value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>" 
                                                       placeholder="e.g., PROD001">
                                            </div>
                                            <div class="form-text">Unique product identifier (optional)</div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Barcode</strong></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bx bx-barcode"></i></span>
                                                <input type="text" name="barcode" class="form-control" 
                                                       value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>" 
                                                       placeholder="Scan or type barcode">
                                                <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">
                                                    <i class="bx bx-refresh"></i> Generate
                                                </button>
                                            </div>
                                            <div class="form-text">Scan barcode or enter manually</div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Unit of Measure</strong></label>
                                            <select name="unit" class="form-select">
                                                <option value="pcs" <?= ($_POST['unit'] ?? 'pcs') == 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                                                <option value="coil" <?= ($_POST['unit'] ?? '') == 'coil' ? 'selected' : '' ?>>Coil</option>
                                                <option value="mtr" <?= ($_POST['unit'] ?? '') == 'mtr' ? 'selected' : '' ?>>Meter (mtr)</option>
                                                <option value="kg" <?= ($_POST['unit'] ?? '') == 'kg' ? 'selected' : '' ?>>Kilogram (kg)</option>
                                                <option value="ltr" <?= ($_POST['unit'] ?? '') == 'ltr' ? 'selected' : '' ?>>Liter (ltr)</option>
                                                <option value="nos" <?= ($_POST['unit'] ?? '') == 'nos' ? 'selected' : '' ?>>Number (nos)</option>
                                                <option value="box" <?= ($_POST['unit'] ?? '') == 'box' ? 'selected' : '' ?>>Box</option>
                                                <option value="feet" <?= ($_POST['unit'] ?? '') == 'feet' ? 'selected' : '' ?>>Feet</option>
                                                <option value="length" <?= ($_POST['unit'] ?? '') == 'length' ? 'selected' : '' ?>>Length</option>
                                                <option value="roll/mtr" <?= ($_POST['unit'] ?? '') == 'roll/mtr' ? 'selected' : '' ?>>Roll/Meter</option>
                                            </select>
                                        </div>

                                        <!-- Secondary Unit Section -->
                                        <div class="col-md-12 mt-4">
                                            <h6 class="border-bottom pb-2 mb-3">
                                                <i class="bx bx-transfer me-1"></i> Secondary Unit Conversion (Optional)
                                                <small class="text-muted">– e.g., sell in meters when primary is coil</small>
                                            </h6>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Secondary Unit</label>
                                            <input type="text" name="secondary_unit" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['secondary_unit'] ?? '') ?>"
                                                   placeholder="e.g., mtr, kg">
                                            <div class="form-text">Leave blank if not needed</div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Conversion Rate</label>
                                            <div class="input-group">
                                                <span class="input-group-text">1 primary =</span>
                                                <input type="number" step="0.0001" min="0" name="sec_unit_conversion"
                                                       class="form-control text-end" id="secUnitConversion"
                                                       value="<?= htmlspecialchars($_POST['sec_unit_conversion'] ?? '') ?>">
                                                <span class="input-group-text">sec units</span>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Extra Charge Type</label>
                                            <select name="sec_unit_price_type" id="secUnitPriceType" class="form-select">
                                                <option value="fixed" <?= ($_POST['sec_unit_price_type'] ?? 'fixed') == 'fixed' ? 'selected' : '' ?>>Fixed (₹)</option>
                                                <option value="percentage" <?= ($_POST['sec_unit_price_type'] ?? '') == 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Extra Charge Value</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" name="sec_unit_extra_charge"
                                                       class="form-control text-end" id="secUnitExtraCharge"
                                                       value="<?= htmlspecialchars($_POST['sec_unit_extra_charge'] ?? '0') ?>">
                                                <span class="input-group-text" id="secUnitExtraUnit">₹</span>
                                            </div>
                                        </div>

                                        <div class="col-md-12 mt-3" id="secondaryPricePreview" style="display:none;">
                                            <div class="alert alert-info py-2">
                                                <strong>Secondary Unit Price Preview:</strong><br>
                                                <span id="secRetailPreview">Retail: Calculating...</span><br>
                                                <span id="secWholesalePreview">Wholesale: Calculating...</span>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="3" 
                                                      placeholder="Features, brand, specifications..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4"><i class="bx bx-image me-1"></i> Product Image</h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label"><strong>Product Image</strong></label>
                                                <input type="file" name="product_image" id="productImage" class="form-control" accept="image/*">
                                                <div class="form-text">
                                                    Upload new product image (max 2MB). Supported formats: JPG, PNG, GIF, WEBP
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Image Alt Text</label>
                                                <input type="text" name="image_alt_text" class="form-control" 
                                                       value="<?= htmlspecialchars($_POST['image_alt_text'] ?? '') ?>" 
                                                       placeholder="Brief description of image for accessibility">
                                                <div class="form-text">Describe the image for screen readers (optional)</div>
                                            </div>

                                            <?php if ($product['image_path']): ?>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                                                <label class="form-check-label text-danger" for="removeImage">
                                                    <i class="bx bx-trash me-1"></i> Remove current image
                                                </label>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="text-center">
                                                <div id="imagePreview" class="border rounded p-3 mb-3" style="min-height: 200px; background-color: #f8f9fa;">
                                                    <?php if ($product['image_path'] && file_exists('../' . $product['image_path'])): ?>
                                                        <img src="../<?= htmlspecialchars($product['image_path']) ?>" 
                                                             class="img-fluid rounded" style="max-height: 200px; object-fit: contain;">
                                                        <p class="mt-2 mb-0">
                                                            <small>Current image</small>
                                                        </p>
                                                    <?php else: ?>
                                                        <i class="bx bx-image fs-1 text-muted"></i>
                                                        <p class="text-muted mt-2 mb-0">No image uploaded</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="form-text">Preview of current/new image</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4"><i class="bx bx-rupee me-1"></i> Pricing & Tax</h5>
                            
                                    <div class="row g-3">
                                        <!-- MRP and Discount Section -->
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>MRP</strong></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" name="mrp" 
                                                       class="form-control form-control-lg text-end" 
                                                       value="<?= htmlspecialchars($_POST['mrp'] ?? '') ?>" 
                                                       id="mrp">
                                            </div>
                                            <div class="form-text">Maximum Retail Price </div>
                                        </div>

                                        <!-- Combined Discount Field -->
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Discount</strong></label>
                                            <div class="input-group">
                                                <input type="text" name="discount" 
                                                       class="form-control text-end" 
                                                       value="<?= htmlspecialchars($_POST['discount'] ?? '') ?>"
                                                       id="discount"
                                                       placeholder="e.g., 10% or 50">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span id="discountSymbol">%</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" onclick="setDiscountSymbol('%')">Percentage (%)</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="setDiscountSymbol('₹')">Fixed Amount (₹)</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" onclick="clearDiscount()">Clear Discount</a></li>
                                                </ul>
                                            </div>
                                            <div class="form-text">Enter discount as percentage (10%) or fixed amount (50)</div>
                                        </div>

                                        <!-- Stock Price Section -->
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Stock Price (Cost) <span class="text-danger">*</span></strong></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" name="stock_price" 
                                                       class="form-control form-control-lg text-end" 
                                                       value="<?= htmlspecialchars($_POST['stock_price'] ?? '') ?>" 
                                                       id="stockPrice" required>
                                                <button type="button" class="btn btn-outline-secondary" onclick="clearManualStockPrice()" title="Clear manual entry">
                                                    <i class="bx bx-refresh"></i>
                                                </button>
                                            </div>
                                            <div class="form-text" id="stockPriceText">Calculated from MRP & Discount</div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>You Save</strong></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control text-end" id="youSave" readonly>
                                                <span class="input-group-text">₹</span>
                                            </div>
                                            <div class="form-text" id="discountPercentageText">
                                                Discount: 0%
                                            </div>
                                        </div>

                                        <!-- Retail Price Section -->
                                        <div class="col-md-12 mt-4">
                                            <h6 class="border-bottom pb-2 mb-3">
                                                <i class="bx bx-store-alt me-1"></i> Retail Price (For Customers)
                                            </h6>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Markup Type </strong></label>
                                            <select name="retail_price_type" id="retailPriceType" class="form-select">
                                                <option value="percentage" <?= ($_POST['retail_price_type'] ?? 'percentage') == 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                                <option value="fixed" <?= ($_POST['retail_price_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Fixed Amount (₹)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Markup Value </strong></label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" name="retail_price_value" 
                                                       class="form-control text-end" 
                                                       value="<?= htmlspecialchars($_POST['retail_price_value'] ?? '0') ?>"
                                                       id="retailPriceValue">
                                                <span class="input-group-text">
                                                    <span id="retailPriceUnit">%</span>
                                                </span>
                                            </div>
                                            <div class="form-text" id="retailMarkupText">
                                                Markup: ₹0.00
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Retail Price <span class="text-danger">*</span></strong></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" name="retail_price" 
                                                       class="form-control form-control-lg text-end" 
                                                       value="<?= htmlspecialchars($_POST['retail_price'] ?? '') ?>" 
                                                       id="retailPrice" required>
                                                <button type="button" class="btn btn-outline-secondary" onclick="clearManualRetailPrice()" title="Clear manual entry">
                                                    <i class="bx bx-refresh"></i>
                                                </button>
                                            </div>
                                            <div class="form-text" id="retailPriceText">
                                                Based on stock price + markup
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Profit Margin</strong></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control text-end" id="retailProfitMargin" readonly>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <div class="form-text" id="retailProfitAmountText">
                                                Profit: ₹0.00
                                            </div>
                                        </div>

                                        <!-- Wholesale Price Section -->
                                        <div class="col-md-12 mt-4">
                                            <h6 class="border-bottom pb-2 mb-3">
                                                <i class="bx bx-building-house me-1"></i> Wholesale Price (For Customers)
                                            </h6>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Markup Type </strong></label>
                                            <select name="wholesale_price_type" id="wholesalePriceType" class="form-select">
                                                <option value="percentage" <?= ($_POST['wholesale_price_type'] ?? 'percentage') == 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                                <option value="fixed" <?= ($_POST['wholesale_price_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Fixed Amount (₹)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Markup Value</strong></label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" name="wholesale_price_value" 
                                                       class="form-control text-end" 
                                                       value="<?= htmlspecialchars($_POST['wholesale_price_value'] ?? '0') ?>"
                                                       id="wholesalePriceValue">
                                                <span class="input-group-text">
                                                    <span id="wholesalePriceUnit">%</span>
                                                </span>
                                            </div>
                                            <div class="form-text" id="wholesaleMarkupText">
                                                Markup: ₹0.00
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Wholesale Price <span class="text-danger">*</span></strong></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" name="wholesale_price" 
                                                       class="form-control form-control-lg text-end" 
                                                       value="<?= htmlspecialchars($_POST['wholesale_price'] ?? '') ?>"
                                                       id="wholesalePrice" required>
                                                <button type="button" class="btn btn-outline-secondary" onclick="clearManualWholesalePrice()" title="Clear manual entry">
                                                    <i class="bx bx-refresh"></i>
                                                </button>
                                            </div>
                                            <div class="form-text" id="wholesalePriceText">
                                                Based on stock price + markup
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Profit Margin</strong></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control text-end" id="wholesaleProfitMargin" readonly>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <div class="form-text" id="wholesaleProfitAmountText">
                                                Profit: ₹0.00
                                            </div>
                                        </div>

                                        <!-- Other Fields -->
                                        <div class="col-md-3">
                                            <label class="form-label">Min Stock Level</label>
                                            <input type="number" name="min_stock_level" class="form-control text-end" 
                                                   value="<?= htmlspecialchars($_POST['min_stock_level'] ?? '0') ?>">
                                            <div class="form-text">Low stock alert level</div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">GST Rate</label>
                                            <select name="gst_id" class="form-select">
                                                <option value="">-- Select GST Rate --</option>
                                                <?php foreach($gst_rates as $g): ?>
                                                <option value="<?= $g['id'] ?>" 
                                                    <?= (isset($_POST['gst_id']) && $_POST['gst_id'] == $g['id']) ? 'selected' : '' ?>>
                                                    <?= $g['hsn_code'] ?> - CGST: <?= $g['cgst_rate'] ?>%, SGST: <?= $g['sgst_rate'] ?>%, IGST: <?= $g['igst_rate'] ?>%
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Select applicable GST rate</div>
                                            <?php if (empty($gst_rates)): ?>
                                            <div class="form-text text-warning">
                                                <i class="bx bx-info-circle"></i> No GST rates configured. 
                                                <a href="gst_rates.php" class="text-decoration-underline">Add GST rates</a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stock Management Section -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">
                                        <i class="bx bx-package me-1"></i> Stock Management
                                        <span class="badge bg-primary ms-2">Current Stock: <?= $current_stock ?></span>
                                    </h5>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Stock Adjustment Type</strong></label>
                                            <select name="stock_adjustment_type" class="form-select" id="stockAdjustmentType">
                                                <option value="">-- No Adjustment --</option>
                                                <option value="add">Add Stock</option>
                                                <option value="remove">Remove Stock</option>
                                                <option value="set">Set to Specific Quantity</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Adjustment Quantity</strong></label>
                                            <div class="input-group">
                                                <input type="number" name="stock_adjustment_qty" class="form-control text-end" 
                                                       value="0" min="0" id="stockAdjustmentQty" disabled>
                                                <span class="input-group-text">units</span>
                                            </div>
                                            <div class="form-text" id="stockAdjustmentInfo">
                                                Select adjustment type first
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><strong>Resulting Stock</strong></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control text-end" id="resultingStock" readonly>
                                                <span class="input-group-text">units</span>
                                            </div>
                                            <div class="form-text">Stock after adjustment</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">
                                        <i class="bx bx-gift me-1"></i> Referral Commission
                                    </h5>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="referralEnabled" 
                                               name="referral_enabled"
                                               <?= (isset($_POST['referral_enabled']) && $_POST['referral_enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="referralEnabled">
                                            Enable referral commission for this product
                                        </label>
                                    </div>

                                    <div id="referralBox" style="<?= (isset($_POST['referral_enabled']) && $_POST['referral_enabled']) ? 'display:block' : 'display:none' ?>;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Commission Type</label>
                                                <select name="referral_type" class="form-select">
                                                    <option value="percentage" <?= ($_POST['referral_type'] ?? 'percentage') == 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                                    <option value="fixed" <?= ($_POST['referral_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Fixed Amount (₹)</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Commission Value</label>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" min="0"
                                                           name="referral_value"
                                                           class="form-control text-end"
                                                           value="<?= htmlspecialchars($_POST['referral_value'] ?? '') ?>"
                                                           placeholder="Enter value">
                                                    <span class="input-group-text">
                                                        <span id="commissionUnit">%</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-text mt-2">
                                            <i class="bx bx-info-circle me-1"></i>
                                            This commission will be credited to referrers when sales are completed.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Status Section -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">
                                        <i class="bx bx-cog me-1"></i> Product Status
                                    </h5>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="isActive" 
                                                       name="is_active" value="1"
                                                       <?= (isset($_POST['is_active']) && $_POST['is_active'] == 1) ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="isActive">
                                                    Product is Active
                                                </label>
                                            </div>
                                            <div class="form-text">
                                                <i class="bx bx-info-circle me-1"></i>
                                                Inactive products won't appear in sales or listings
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Quick Actions</h5>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                                        <button type="submit" name="submit" value="save" class="btn btn-success btn-lg px-4">
                                            <i class="bx bx-save me-2"></i> Update Product
                                        </button>
                                        <button type="button" class="btn btn-warning px-4" onclick="resetForm()">
                                            <i class="bx bx-reset me-1"></i> Reset Changes
                                        </button>
                                        <a href="products.php" class="btn btn-outline-secondary px-4">
                                            <i class="bx bx-x me-1"></i> Cancel
                                        </a>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="quickAddCategory()">
                                            <i class="bx bx-plus"></i> Quick Add Category
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="quickAddSubcategory()">
                                            <i class="bx bx-plus"></i> Quick Add Subcategory
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Product Information -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3"><i class="bx bx-info-circle me-1"></i> Product Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>Created:</strong></td>
                                                    <td><?= date('d M Y, h:i A', strtotime($product['created_at'])) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Last Updated:</strong></td>
                                                    <td><?= $product['updated_at'] ? date('d M Y, h:i A', strtotime($product['updated_at'])) : 'Never' ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Product ID:</strong></td>
                                                    <td><code>#<?= $product['id'] ?></code></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>Current Status:</strong></td>
                                                    <td>
                                                        <?php if ($product['is_active'] == 1): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>HSN Code:</strong></td>
                                                    <td><?= htmlspecialchars($product['hsn_code'] ?: 'Not set') ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/rightbar.php'); ?>
<?php include('includes/scripts.php'); ?>

<script>
// Track manual entries
let manualStockPrice = false;
let manualRetailPrice = false;
let manualWholesalePrice = false;
let discountSymbol = '<?= strpos($_POST['discount'] ?? '', '%') !== false ? '%' : '₹' ?>'; // Set based on existing value
const currentStock = <?= $current_stock ?>;

function setDiscountSymbol(symbol) {
    discountSymbol = symbol;
    document.getElementById('discountSymbol').textContent = symbol;
    calculateStockPrice();
}

function clearDiscount() {
    document.getElementById('discount').value = '';
    discountSymbol = '%';
    document.getElementById('discountSymbol').textContent = '%';
    calculateStockPrice();
}

// Clear manual stock price entry
function clearManualStockPrice() {
    manualStockPrice = false;
    document.getElementById('stockPrice').value = '';
    document.getElementById('stockPriceText').innerHTML = 'Will be calculated from MRP & Discount';
    document.getElementById('stockPriceText').style.color = '#6c757d';
    calculateStockPrice();
}

// Clear manual retail price entry
function clearManualRetailPrice() {
    manualRetailPrice = false;
    document.getElementById('retailPrice').value = '';
    calculateRetailPrice();
}

// Clear manual wholesale price entry
function clearManualWholesalePrice() {
    manualWholesalePrice = false;
    document.getElementById('wholesalePrice').value = '';
    calculateWholesalePrice();
}

// Calculate Stock Price from MRP and discount
function calculateStockPrice() {
    const mrp = parseFloat(document.getElementById('mrp').value) || 0;
    const discountInput = document.getElementById('discount').value.trim();
    const stockPriceInput = document.getElementById('stockPrice');
    const youSaveInput = document.getElementById('youSave');
    const discountPercentageText = document.getElementById('discountPercentageText');
    const stockPriceText = document.getElementById('stockPriceText');
    
    let discountValue = 0;
    let discountType = 'percentage';
    let discountAmount = 0;
    let currentStockPrice = parseFloat(stockPriceInput.value) || 0;
    let calculatedStockPrice = 0;
    
    // If MRP is provided, calculate stock price
    if (mrp > 0) {
        if (discountInput) {
            if (discountSymbol === '%') {
                discountType = 'percentage';
                discountValue = parseFloat(discountInput.replace('%', '')) || 0;
                
                if (discountValue > 100) {
                    alert('Discount percentage cannot exceed 100%');
                    discountValue = 100;
                    document.getElementById('discount').value = '100%';
                }
                
                discountAmount = mrp * discountValue / 100;
                calculatedStockPrice = mrp - discountAmount;
            } else {
                discountType = 'fixed';
                discountValue = parseFloat(discountInput) || 0;
                
                if (discountValue > mrp) {
                    alert('Discount amount cannot exceed MRP');
                    discountValue = mrp;
                    document.getElementById('discount').value = mrp.toFixed(2);
                }
                
                discountAmount = discountValue;
                calculatedStockPrice = mrp - discountValue;
            }
        } else {
            // No discount, stock price equals MRP
            calculatedStockPrice = mrp;
        }
        
        // Ensure calculated stock price is not negative
        if (calculatedStockPrice < 0) {
            calculatedStockPrice = 0;
        }
        
        // If user hasn't manually entered stock price, use calculated value
        if (!manualStockPrice) {
            stockPriceInput.value = calculatedStockPrice.toFixed(2);
            if (discountAmount > 0) {
                stockPriceText.innerHTML = `Calculated from MRP (₹${mrp.toFixed(2)}) - Discount`;
                stockPriceText.style.color = '#198754';
            } else {
                stockPriceText.innerHTML = `Same as MRP (no discount)`;
                stockPriceText.style.color = '#6c757d';
            }
        } else {
            stockPriceText.innerHTML = `Manually entered`;
            stockPriceText.style.color = '#0d6efd';
        }
    } else {
        // No MRP provided
        if (currentStockPrice > 0) {
            stockPriceText.innerHTML = `Manually entered`;
            stockPriceText.style.color = '#0d6efd';
        } else {
            stockPriceText.innerHTML = `Enter cost price manually`;
            stockPriceText.style.color = '#6c757d';
        }
    }
    
    // Update "You Save" field
    youSaveInput.value = discountAmount.toFixed(2);
    
    // Update discount percentage text
    if (mrp > 0 && discountAmount > 0) {
        const discountPercentage = (discountAmount / mrp) * 100;
        discountPercentageText.innerHTML = `Discount: ${discountPercentage.toFixed(1)}%`;
        discountPercentageText.style.color = '#198754';
    } else {
        discountPercentageText.innerHTML = `Discount: 0%`;
        discountPercentageText.style.color = '#6c757d';
    }
    
    // Trigger other calculations
    calculateRetailPrice();
    calculateWholesalePrice();
    calculateProfitMargins();
    calculateSecondaryPrices();
    validatePriceHierarchy();
}

// Calculate Retail Price based on Stock Price and Retail Markup
function calculateRetailPrice() {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const retailPriceType = document.getElementById('retailPriceType').value;
    const retailPriceValue = parseFloat(document.getElementById('retailPriceValue').value) || 0;
    const retailPriceInput = document.getElementById('retailPrice');
    const retailMarkupText = document.getElementById('retailMarkupText');
    const retailPriceText = document.getElementById('retailPriceText');
    
    let markupAmount = 0;
    let currentRetailPrice = parseFloat(retailPriceInput.value) || 0;
    let calculatedRetailPrice = stockPrice;
    
    // Calculate markup amount and retail price
    if (stockPrice > 0 && retailPriceValue > 0) {
        if (retailPriceType === 'percentage') {
            markupAmount = stockPrice * retailPriceValue / 100;
            calculatedRetailPrice = stockPrice + markupAmount;
        } else {
            markupAmount = retailPriceValue;
            calculatedRetailPrice = stockPrice + retailPriceValue;
        }
        
        // Only update if retail price is not manually set
        if (!manualRetailPrice) {
            retailPriceInput.value = calculatedRetailPrice.toFixed(2);
            retailPriceText.innerHTML = `Based on stock price + ${retailPriceValue}${retailPriceType === 'percentage' ? '%' : '₹'} markup`;
            retailPriceText.style.color = '#198754';
        } else {
            retailPriceText.innerHTML = `Manually entered`;
            retailPriceText.style.color = '#0d6efd';
        }
    } else if (stockPrice > 0) {
        if (!manualRetailPrice) {
            retailPriceInput.value = stockPrice.toFixed(2);
            retailPriceText.innerHTML = `Same as stock price (no markup)`;
            retailPriceText.style.color = '#6c757d';
        } else {
            retailPriceText.innerHTML = `Manually entered`;
            retailPriceText.style.color = '#0d6efd';
        }
    }
    
    // Update markup amount display
    retailMarkupText.innerHTML = `Markup: ₹${markupAmount.toFixed(2)}`;
    
    // Calculate profit margin
    calculateProfitMargins();
    calculateSecondaryPrices();
    validatePriceHierarchy();
}

// Calculate Wholesale Price based on Stock Price and Wholesale Markup
function calculateWholesalePrice() {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const wholesalePriceType = document.getElementById('wholesalePriceType').value;
    const wholesalePriceValue = parseFloat(document.getElementById('wholesalePriceValue').value) || 0;
    const wholesalePriceInput = document.getElementById('wholesalePrice');
    const wholesaleMarkupText = document.getElementById('wholesaleMarkupText');
    const wholesalePriceText = document.getElementById('wholesalePriceText');
    
    let markupAmount = 0;
    let currentWholesalePrice = parseFloat(wholesalePriceInput.value) || 0;
    let calculatedWholesalePrice = stockPrice;
    
    // Calculate markup amount and wholesale price
    if (stockPrice > 0 && wholesalePriceValue > 0) {
        if (wholesalePriceType === 'percentage') {
            markupAmount = stockPrice * wholesalePriceValue / 100;
            calculatedWholesalePrice = stockPrice + markupAmount;
        } else {
            markupAmount = wholesalePriceValue;
            calculatedWholesalePrice = stockPrice + wholesalePriceValue;
        }
        
        // Only update if wholesale price is not manually set
        if (!manualWholesalePrice) {
            wholesalePriceInput.value = calculatedWholesalePrice.toFixed(2);
            wholesalePriceText.innerHTML = `Based on stock price + ${wholesalePriceValue}${wholesalePriceType === 'percentage' ? '%' : '₹'} markup`;
            wholesalePriceText.style.color = '#0d6efd';
        } else {
            wholesalePriceText.innerHTML = `Manually entered`;
            wholesalePriceText.style.color = '#0d6efd';
        }
    } else if (stockPrice > 0) {
        if (!manualWholesalePrice) {
            wholesalePriceInput.value = stockPrice.toFixed(2);
            wholesalePriceText.innerHTML = `Same as stock price (no markup)`;
            wholesalePriceText.style.color = '#6c757d';
        } else {
            wholesalePriceText.innerHTML = `Manually entered`;
            wholesalePriceText.style.color = '#0d6efd';
        }
    }
    
    // Update markup amount display
    wholesaleMarkupText.innerHTML = `Markup: ₹${markupAmount.toFixed(2)}`;
    
    // Calculate profit margin
    calculateProfitMargins();
    calculateSecondaryPrices();
    validatePriceHierarchy();
}

// Calculate Profit Margins based on selling prices
function calculateProfitMargins() {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const retailPrice = parseFloat(document.getElementById('retailPrice').value) || 0;
    const wholesalePrice = parseFloat(document.getElementById('wholesalePrice').value) || 0;
    const retailProfitMarginInput = document.getElementById('retailProfitMargin');
    const retailProfitAmountText = document.getElementById('retailProfitAmountText');
    const wholesaleProfitMarginInput = document.getElementById('wholesaleProfitMargin');
    const wholesaleProfitAmountText = document.getElementById('wholesaleProfitAmountText');
    
    // Calculate Retail Profit Margin: ((Retail Price - Stock Price) / Retail Price) × 100
    if (stockPrice > 0 && retailPrice > 0) {
        const retailProfit = retailPrice - stockPrice;
        const retailProfitMargin = retailPrice > 0 ? (retailProfit / retailPrice) * 100 : 0;
        
        retailProfitMarginInput.value = retailProfitMargin.toFixed(2);
        retailProfitAmountText.innerHTML = `Profit: ₹${retailProfit.toFixed(2)}`;
        
        // Color code based on profit margin
        if (retailProfitMargin > 20) {
            retailProfitMarginInput.style.color = '#198754';
            retailProfitAmountText.style.color = '#198754';
        } else if (retailProfitMargin > 10) {
            retailProfitMarginInput.style.color = '#fd7e14';
            retailProfitAmountText.style.color = '#fd7e14';
        } else if (retailProfitMargin > 0) {
            retailProfitMarginInput.style.color = '#0d6efd';
            retailProfitAmountText.style.color = '#0d6efd';
        } else {
            retailProfitMarginInput.style.color = '#dc3545';
            retailProfitAmountText.style.color = '#dc3545';
        }
    } else {
        retailProfitMarginInput.value = '';
        retailProfitAmountText.innerHTML = 'Profit: ₹0.00';
        retailProfitMarginInput.style.color = '';
        retailProfitAmountText.style.color = '';
    }
    
    // Calculate Wholesale Profit Margin: ((Wholesale Price - Stock Price) / Wholesale Price) × 100
    if (stockPrice > 0 && wholesalePrice > 0) {
        const wholesaleProfit = wholesalePrice - stockPrice;
        const wholesaleProfitMargin = wholesalePrice > 0 ? (wholesaleProfit / wholesalePrice) * 100 : 0;
        
        wholesaleProfitMarginInput.value = wholesaleProfitMargin.toFixed(2);
        wholesaleProfitAmountText.innerHTML = `Profit: ₹${wholesaleProfit.toFixed(2)}`;
        
        // Color code based on profit margin
        if (wholesaleProfitMargin > 15) {
            wholesaleProfitMarginInput.style.color = '#198754';
            wholesaleProfitAmountText.style.color = '#198754';
        } else if (wholesaleProfitMargin > 5) {
            wholesaleProfitMarginInput.style.color = '#fd7e14';
            wholesaleProfitAmountText.style.color = '#fd7e14';
        } else if (wholesaleProfitMargin > 0) {
            wholesaleProfitMarginInput.style.color = '#0d6efd';
            wholesaleProfitAmountText.style.color = '#0d6efd';
        } else {
            wholesaleProfitMarginInput.style.color = '#dc3545';
            wholesaleProfitAmountText.style.color = '#dc3545';
        }
    } else {
        wholesaleProfitMarginInput.value = '';
        wholesaleProfitAmountText.innerHTML = 'Profit: ₹0.00';
        wholesaleProfitMarginInput.style.color = '';
        wholesaleProfitAmountText.style.color = '';
    }
}

// Calculate Secondary Unit Prices
function calculateSecondaryPrices() {
    const secondaryUnit = document.querySelector('input[name="secondary_unit"]').value.trim();
    const conversion = parseFloat(document.getElementById('secUnitConversion').value) || 0;
    const extraType = document.getElementById('secUnitPriceType').value;
    const extraCharge = parseFloat(document.getElementById('secUnitExtraCharge').value) || 0;
    const retailPrice = parseFloat(document.getElementById('retailPrice').value) || 0;
    const wholesalePrice = parseFloat(document.getElementById('wholesalePrice').value) || 0;

    const previewBox = document.getElementById('secondaryPricePreview');

    if (secondaryUnit && conversion > 0 && (retailPrice > 0 || wholesalePrice > 0)) {
        previewBox.style.display = 'block';

        let retailPerUnit = retailPrice / conversion;
        let wholesalePerUnit = wholesalePrice / conversion;

        if (extraType === 'fixed') {
            retailPerUnit += extraCharge;
            wholesalePerUnit += extraCharge;
        } else {
            retailPerUnit += retailPerUnit * (extraCharge / 100);
            wholesalePerUnit += wholesalePerUnit * (extraCharge / 100);
        }

        document.getElementById('secRetailPreview').innerHTML = 
            `<strong>Retail:</strong> ₹${retailPerUnit.toFixed(2)} per ${secondaryUnit}`;
        document.getElementById('secWholesalePreview').innerHTML = 
            `<strong>Wholesale:</strong> ₹${wholesalePerUnit.toFixed(2)} per ${secondaryUnit}`;
    } else {
        previewBox.style.display = 'none';
    }
}

// Calculate stock adjustment
function calculateStockAdjustment() {
    const adjustmentType = document.getElementById('stockAdjustmentType').value;
    const adjustmentQty = parseFloat(document.getElementById('stockAdjustmentQty').value) || 0;
    const resultingStockInput = document.getElementById('resultingStock');
    const adjustmentInfo = document.getElementById('stockAdjustmentInfo');
    
    let resultingStock = currentStock;
    let infoText = 'Select adjustment type first';
    
    if (adjustmentType) {
        document.getElementById('stockAdjustmentQty').disabled = false;
        
        switch(adjustmentType) {
            case 'add':
                resultingStock = currentStock + adjustmentQty;
                infoText = `Add ${adjustmentQty} units to current stock (${currentStock})`;
                break;
            case 'remove':
                resultingStock = currentStock - adjustmentQty;
                if (resultingStock < 0) resultingStock = 0;
                infoText = `Remove ${adjustmentQty} units from current stock (${currentStock})`;
                break;
            case 'set':
                resultingStock = adjustmentQty;
                infoText = `Set stock to ${adjustmentQty} units (replaces current ${currentStock})`;
                break;
        }
    } else {
        document.getElementById('stockAdjustmentQty').disabled = true;
        document.getElementById('stockAdjustmentQty').value = 0;
    }
    
    resultingStockInput.value = resultingStock;
    adjustmentInfo.innerHTML = infoText;
    
    // Color code based on stock level
    const minStockLevel = parseFloat(document.querySelector('input[name="min_stock_level"]').value) || 0;
    if (resultingStock === 0) {
        resultingStockInput.style.color = '#dc3545';
    } else if (resultingStock <= minStockLevel) {
        resultingStockInput.style.color = '#fd7e14';
    } else {
        resultingStockInput.style.color = '#198754';
    }
}

// Validate price hierarchy: Stock Price ≤ Wholesale Price ≤ Retail Price ≤ MRP
function validatePriceHierarchy() {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const wholesalePrice = parseFloat(document.getElementById('wholesalePrice').value) || 0;
    const retailPrice = parseFloat(document.getElementById('retailPrice').value) || 0;
    const mrp = parseFloat(document.getElementById('mrp').value) || 0;
    
    // Clear previous warnings
    document.getElementById('stockPriceText').style.color = '';
    document.getElementById('wholesalePriceText').style.color = '';
    document.getElementById('retailPriceText').style.color = '';
    
    if (stockPrice > 0 && wholesalePrice > 0 && retailPrice > 0) {
        // Check hierarchy
        if (wholesalePrice < stockPrice) {
            document.getElementById('wholesalePriceText').innerHTML = '<span class="text-danger">Error: Must be ≥ Stock Price</span>';
            document.getElementById('wholesalePriceText').style.color = '#dc3545';
        }
        
        if (retailPrice <= stockPrice) {
            document.getElementById('retailPriceText').innerHTML = '<span class="text-danger">Error: Must be > Stock Price</span>';
            document.getElementById('retailPriceText').style.color = '#dc3545';
        }
        
        if (wholesalePrice > retailPrice) {
            document.getElementById('wholesalePriceText').innerHTML = '<span class="text-danger">Error: Must be ≤ Retail Price</span>';
            document.getElementById('wholesalePriceText').style.color = '#dc3545';
        }
        
        // Check MRP constraints
        if (mrp > 0) {
            if (retailPrice > mrp) {
                document.getElementById('retailPriceText').innerHTML = '<span class="text-danger">Error: Must be ≤ MRP</span>';
                document.getElementById('retailPriceText').style.color = '#dc3545';
            }
            
            if (wholesalePrice > mrp) {
                document.getElementById('wholesalePriceText').innerHTML = '<span class="text-danger">Error: Must be ≤ MRP</span>';
                document.getElementById('wholesalePriceText').style.color = '#dc3545';
            }
            
            if (stockPrice > mrp) {
                document.getElementById('stockPriceText').innerHTML = '<span class="text-danger">Error: Must be ≤ MRP</span>';
                document.getElementById('stockPriceText').style.color = '#dc3545';
            }
        }
    }
}

// Update retail price unit display
function updateRetailPriceUnit() {
    const retailPriceType = document.getElementById('retailPriceType').value;
    document.getElementById('retailPriceUnit').textContent = retailPriceType === 'percentage' ? '%' : '₹';
    calculateRetailPrice();
}

// Update wholesale price unit display
function updateWholesalePriceUnit() {
    const wholesalePriceType = document.getElementById('wholesalePriceType').value;
    document.getElementById('wholesalePriceUnit').textContent = wholesalePriceType === 'percentage' ? '%' : '₹';
    calculateWholesalePrice();
}

// Update secondary unit extra charge unit
function updateSecUnitExtraUnit() {
    const extraType = document.getElementById('secUnitPriceType').value;
    document.getElementById('secUnitExtraUnit').textContent = extraType === 'percentage' ? '%' : '₹';
    calculateSecondaryPrices();
}

// Detect manual entry of prices
document.getElementById('stockPrice').addEventListener('input', function() {
    const value = parseFloat(this.value) || 0;
    if (value > 0) {
        manualStockPrice = true;
        document.getElementById('stockPriceText').innerHTML = 'Manually entered';
        document.getElementById('stockPriceText').style.color = '#0d6efd';
    }
    calculateRetailPrice();
    calculateWholesalePrice();
    calculateProfitMargins();
    calculateSecondaryPrices();
    validatePriceHierarchy();
});

document.getElementById('retailPrice').addEventListener('input', function() {
    const value = parseFloat(this.value) || 0;
    if (value > 0) {
        manualRetailPrice = true;
        document.getElementById('retailPriceText').innerHTML = 'Manually entered';
        document.getElementById('retailPriceText').style.color = '#0d6efd';
    }
    calculateProfitMargins();
    calculateSecondaryPrices();
    validatePriceHierarchy();
});

document.getElementById('wholesalePrice').addEventListener('input', function() {
    const value = parseFloat(this.value) || 0;
    if (value > 0) {
        manualWholesalePrice = true;
        document.getElementById('wholesalePriceText').innerHTML = 'Manually entered';
        document.getElementById('wholesalePriceText').style.color = '#0d6efd';
    }
    calculateProfitMargins();
    calculateSecondaryPrices();
    validatePriceHierarchy();
});

// Stock adjustment event listeners
document.getElementById('stockAdjustmentType').addEventListener('change', calculateStockAdjustment);
document.getElementById('stockAdjustmentQty').addEventListener('input', calculateStockAdjustment);

// Validate prices before submission
document.getElementById('editProductForm').addEventListener('submit', function(e) {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const wholesalePrice = parseFloat(document.getElementById('wholesalePrice').value) || 0;
    const retailPrice = parseFloat(document.getElementById('retailPrice').value) || 0;
    const mrp = parseFloat(document.getElementById('mrp').value) || 0;
    const discountInput = document.getElementById('discount').value.trim();
    const adjustmentType = document.getElementById('stockAdjustmentType').value;
    const adjustmentQty = parseFloat(document.getElementById('stockAdjustmentQty').value) || 0;
    
    let errors = [];
    
    // Check if stock price is valid
    if (stockPrice <= 0) {
        errors.push('Stock price is required and must be greater than 0.');
    }
    
    // Check if wholesale price is valid
    if (wholesalePrice <= 0) {
        errors.push('Wholesale price is required and must be greater than 0.');
    }
    
    // Check if retail price is valid
    if (retailPrice <= 0) {
        errors.push('Retail price is required and must be greater than 0.');
    }
    
    // Check price hierarchy
    if (stockPrice > 0 && wholesalePrice > 0 && retailPrice > 0) {
        if (wholesalePrice < stockPrice) {
            errors.push('Wholesale price must be equal to or greater than Stock Price.');
        }
        
        if (retailPrice <= stockPrice) {
            errors.push('Retail price must be greater than Stock Price.');
        }
        
        if (wholesalePrice > retailPrice) {
            errors.push('Wholesale price should be less than or equal to Retail Price.');
        }
        
        // Check MRP constraints
        if (mrp > 0) {
            if (retailPrice > mrp) {
                errors.push('Retail price cannot be higher than MRP.');
            }
            
            if (wholesalePrice > mrp) {
                errors.push('Wholesale price cannot be higher than MRP.');
            }
            
            if (stockPrice > mrp) {
                errors.push('Stock price cannot be higher than MRP.');
            }
        }
    }
    
    // Check discount if MRP provided
    if (mrp > 0 && discountInput) {
        if (discountSymbol === '%') {
            const discountValue = parseFloat(discountInput.replace('%', '')) || 0;
            if (discountValue > 100) {
                errors.push('Discount percentage cannot exceed 100%.');
            }
        } else {
            const discountValue = parseFloat(discountInput) || 0;
            if (discountValue > mrp) {
                errors.push('Discount amount cannot exceed MRP.');
            }
        }
    }
    
    // Check stock adjustment
    if (adjustmentType && adjustmentQty > 0) {
        if (adjustmentType === 'remove' && adjustmentQty > currentStock) {
            if (!confirm(`You are trying to remove ${adjustmentQty} units, but current stock is only ${currentStock}. This will result in 0 stock. Continue?`)) {
                e.preventDefault();
                document.getElementById('stockAdjustmentQty').focus();
                return;
            }
        }
    }
    
    // Check if retail price is less than stock price
    if (retailPrice > 0 && stockPrice > 0 && retailPrice < stockPrice) {
        if (!confirm('Retail price is less than stock price. This will result in a loss. Continue anyway?')) {
            e.preventDefault();
            document.getElementById('retailPrice').focus();
            return;
        }
    }
    
    // Check if wholesale price is less than stock price
    if (wholesalePrice > 0 && stockPrice > 0 && wholesalePrice < stockPrice) {
        if (!confirm('Wholesale price is less than stock price. This will result in a loss. Continue anyway?')) {
            e.preventDefault();
            document.getElementById('wholesalePrice').focus();
            return;
        }
    }
    
    // Show errors if any
    if (errors.length > 0) {
        e.preventDefault();
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
        return;
    }
    
    // Validate file size (client-side)
    const fileInput = document.getElementById('productImage');
    if (fileInput.files.length > 0) {
        const fileSize = fileInput.files[0].size;
        const maxSize = 2 * 1024 * 1024; // 2MB
        if (fileSize > maxSize) {
            e.preventDefault();
            alert('File size exceeds 2MB limit. Please choose a smaller image.');
            fileInput.focus();
        }
    }
});

// Generate random barcode
function generateBarcode() {
    const prefix = '89'; // Country code for India
    const random = Math.floor(Math.random() * 10000000000).toString().padStart(10, '0');
    const barcode = prefix + random;
    document.querySelector('input[name="barcode"]').value = barcode;
}

// AJAX: Load subcategories when category changes
document.getElementById('categorySelect').addEventListener('change', function() {
    const categoryId = this.value;
    const subcategorySelect = document.getElementById('subcategorySelect');
    const loadingDiv = document.getElementById('subcategoryLoading');
    
    if (!categoryId) {
        // Clear subcategories
        subcategorySelect.innerHTML = '<option value="">-- Select Subcategory --</option>';
        return;
    }
    
    // Show loading
    loadingDiv.style.display = 'block';
    subcategorySelect.disabled = true;
    
    // Make AJAX request
    fetch(`ajax/get_subcategories.php?category_id=${categoryId}&business_id=<?= $current_business_id ?>`)
        .then(response => response.json())
        .then(data => {
            // Clear existing options except first
            subcategorySelect.innerHTML = '<option value="">-- Select Subcategory --</option>';
            
            if (data.success && data.subcategories.length > 0) {
                data.subcategories.forEach(subcat => {
                    const option = document.createElement('option');
                    option.value = subcat.id;
                    option.textContent = subcat.subcategory_name;
                    subcategorySelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.textContent = 'No subcategories available';
                option.disabled = true;
                subcategorySelect.appendChild(option);
            }
            
            // Preselect if previously selected
            const currentSubcategoryId = <?= $_POST['subcategory_id'] ?? 'null' ?>;
            if (currentSubcategoryId) {
                subcategorySelect.value = currentSubcategoryId;
            }
        })
        .catch(error => {
            console.error('Error loading subcategories:', error);
            subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
        })
        .finally(() => {
            loadingDiv.style.display = 'none';
            subcategorySelect.disabled = false;
        });
});

// Referral commission toggle
const referralToggle = document.getElementById('referralEnabled');
const referralBox = document.getElementById('referralBox');
const commissionUnit = document.getElementById('commissionUnit');
const referralTypeSelect = document.querySelector('select[name="referral_type"]');

function toggleReferralBox() {
    referralBox.style.display = referralToggle.checked ? 'block' : 'none';
}

function updateCommissionUnit() {
    commissionUnit.textContent = referralTypeSelect.value === 'percentage' ? '%' : '₹';
}

referralToggle.addEventListener('change', toggleReferralBox);
referralTypeSelect.addEventListener('change', updateCommissionUnit);

// Image preview
document.getElementById('productImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `
                <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px; object-fit: contain;">
                <p class="mt-2 mb-0"><small>${file.name} (${(file.size / 1024).toFixed(1)} KB)</small></p>
            `;
        };
        
        reader.readAsDataURL(file);
    } else {
        // Show current image if exists
        <?php if ($product['image_path']): ?>
        preview.innerHTML = `
            <img src="../<?= htmlspecialchars($product['image_path']) ?>" 
                 class="img-fluid rounded" style="max-height: 200px; object-fit: contain;">
            <p class="mt-2 mb-0"><small>Current image</small></p>
        `;
        <?php else: ?>
        preview.innerHTML = `
            <i class="bx bx-image fs-1 text-muted"></i>
            <p class="text-muted mt-2 mb-0">No image uploaded</p>
        `;
        <?php endif; ?>
    }
});

// Smart discount input handling
document.getElementById('discount').addEventListener('input', function(e) {
    let value = this.value.trim();
    
    // Auto-detect percentage symbol
    if (value.includes('%')) {
        discountSymbol = '%';
        document.getElementById('discountSymbol').textContent = '%';
        // Remove any extra % symbols
        value = value.replace(/[%]/g, '');
        // Keep only one % at the end
        this.value = value + '%';
    }
    
    // Calculate stock price on input
    calculateStockPrice();
});

// Event listeners for price calculations
document.getElementById('mrp').addEventListener('input', calculateStockPrice);
document.getElementById('retailPriceValue').addEventListener('input', calculateRetailPrice);
document.getElementById('retailPriceType').addEventListener('change', updateRetailPriceUnit);
document.getElementById('wholesalePriceValue').addEventListener('input', calculateWholesalePrice);
document.getElementById('wholesalePriceType').addEventListener('change', updateWholesalePriceUnit);

// Secondary unit event listeners
document.getElementById('secUnitPriceType').addEventListener('change', updateSecUnitExtraUnit);
document.getElementById('secUnitConversion').addEventListener('input', calculateSecondaryPrices);
document.getElementById('secUnitExtraCharge').addEventListener('input', calculateSecondaryPrices);
document.querySelector('input[name="secondary_unit"]').addEventListener('input', calculateSecondaryPrices);

// Reset form to original values
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will reload the page.')) {
        window.location.reload();
    }
}

// Initialize on page load
toggleReferralBox();
updateCommissionUnit();
updateRetailPriceUnit();
updateWholesalePriceUnit();
updateSecUnitExtraUnit();

// Set initial discount symbol
document.getElementById('discountSymbol').textContent = discountSymbol;

// Check if prices were manually entered on page load
document.addEventListener('DOMContentLoaded', function() {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const retailPrice = parseFloat(document.getElementById('retailPrice').value) || 0;
    const wholesalePrice = parseFloat(document.getElementById('wholesalePrice').value) || 0;
    
    if (stockPrice > 0) manualStockPrice = true;
    if (retailPrice > 0) manualRetailPrice = true;
    if (wholesalePrice > 0) manualWholesalePrice = true;
    
    // Calculate prices on page load
    calculateStockPrice();
    calculateSecondaryPrices();
    calculateStockAdjustment();
});

// Quick add category function
function quickAddCategory() {
    const categoryName = prompt('Enter new category name:');
    if (categoryName && categoryName.trim()) {
        fetch('ajax/quick_add_category.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `category_name=${encodeURIComponent(categoryName.trim())}&business_id=<?= $current_business_id ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new option to category select
                const categorySelect = document.getElementById('categorySelect');
                const newOption = document.createElement('option');
                newOption.value = data.category_id;
                newOption.textContent = data.category_name;
                categorySelect.appendChild(newOption);
                categorySelect.value = data.category_id;
                alert('Category added successfully!');
                
                // Trigger change event to load subcategories
                categorySelect.dispatchEvent(new Event('change'));
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to add category');
        });
    }
}

// Quick add subcategory function
function quickAddSubcategory() {
    const categorySelect = document.getElementById('categorySelect');
    if (!categorySelect.value) {
        alert('Please select a category first');
        return;
    }
    
    const subcategoryName = prompt('Enter new subcategory name:');
    if (subcategoryName && subcategoryName.trim()) {
        fetch('ajax/quick_add_subcategory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `subcategory_name=${encodeURIComponent(subcategoryName.trim())}&category_id=${categorySelect.value}&business_id=<?= $current_business_id ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new option to subcategory select
                const subcategorySelect = document.getElementById('subcategorySelect');
                const newOption = document.createElement('option');
                newOption.value = data.subcategory_id;
                newOption.textContent = data.subcategory_name;
                subcategorySelect.appendChild(newOption);
                subcategorySelect.value = data.subcategory_id;
                alert('Subcategory added successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to add subcategory');
        });
    }
}

// Trigger category change on page load if category is already selected
<?php if (isset($_POST['category_id']) && $_POST['category_id']): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('categorySelect').dispatchEvent(new Event('change'));
});
<?php endif; ?>
</script>

<style>
.form-control-lg { 
    font-size: 1.1rem; 
    font-weight: 500;
}
.text-end::-webkit-inner-spin-button, 
.text-end::-webkit-outer-spin-button {
    opacity: 1;
}
.card-title {
    border-bottom: 2px solid var(--bs-primary);
    padding-bottom: 0.75rem;
}
.input-group-text {
    background-color: #f8f9fa;
    font-weight: 500;
}
#referralBox {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    margin-top: 10px;
}
#imagePreview {
    display: flex;
    flex-direction:column;
    align-items: center;
    justify-content: center;
}
#imagePreview img {
    max-width: 100%;
    max-height: 200px;
}
#stockPriceText, #retailMarkupText, #retailPriceText, 
#retailProfitAmountText, #wholesaleMarkupText, #wholesalePriceText, #wholesaleProfitAmountText {
    font-size: 0.875rem;
    font-weight: 500;
}
#stockPriceText {
    color: #0d6efd;
}
#retailMarkupText {
    color: #fd7e14;
}
#retailPriceText {
    color: #6f42c1;
}
#retailProfitAmountText {
    color: #20c997;
}
#wholesaleMarkupText {
    color: #0dcaf0;
}
#wholesalePriceText {
    color: #6610f2;
}
#wholesaleProfitAmountText {
    color: #17a2b8;
}
#discountPercentageText {
    color: #198754;
    font-weight: 500;
}
.border-bottom {
    border-color: #dee2e6 !important;
}
#youSave, #retailProfitMargin, #wholesaleProfitMargin, #resultingStock {
    background-color: #f8f9fa;
    cursor: not-allowed;
}
.alert-info {
    border-left: 4px solid #0dcaf0;
}
.btn-outline-secondary {
    border-color: #dee2e6;
}
</style>
</body>
</html>