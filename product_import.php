<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

// Authentication & basic checks
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['current_business_id'])) {
    header('Location: select_shop.php');
    exit();
}

$current_business_id = (int)$_SESSION['current_business_id'];
$current_shop_id = $_SESSION['current_shop_id'] ?? null;
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';
$preview_data = null;
$total_records = 0;
$valid_records = 0;
$invalid_records = 0;

// Fetch available shops/warehouses
$shops_stmt = $pdo->prepare("
    SELECT id, shop_name, location_type
    FROM shops
    WHERE business_id = ? AND is_active = 1
    ORDER BY location_type, shop_name
");
$shops_stmt->execute([$current_business_id]);
$shops = $shops_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────────────────────────────────────
// HANDLE CSV UPLOAD & PREVIEW
// ──────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    ini_set('auto_detect_line_endings', true);

    if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
        $error = "File too large (max 10MB).";
    } elseif (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = "Only CSV files are allowed.";
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            $error = "Cannot read file.";
        } else {
            $headers = fgetcsv($handle);
            if (!$headers) {
                $error = "Invalid or empty CSV.";
                fclose($handle);
            } else {
                // Expected headers - NO direct price columns
                $expected_headers = [
                    'Product Name', 'Product Code', 'Category', 'Subcategory', 'Current Stock',
                    'Barcode', 'HSN Code', 'Unit of Measure',
                    'MRP', 'Discount',
                    'Retail Markup Type', 'Retail Markup',
                    'Wholesale Markup Type', 'Wholesale Markup',
                    'Referral Enabled', 'Referral Type', 'Referral Value'
                ];

                $headers = array_map('trim', $headers);
                $missing = array_diff($expected_headers, $headers);

                if (!empty($missing)) {
                    $error = "Missing required columns: " . implode(', ', $missing);
                    fclose($handle);
                } else {
                    $preview_data = [];
                    $row_num = 1;

                    while (($row = fgetcsv($handle)) !== false) {
                        $row_num++;
                        if (count($row) !== count($expected_headers)) continue;

                        $data = array_combine($expected_headers, array_map('trim', $row));
                        $row_errors = [];

                        // Required fields
                        if (empty($data['Product Name'])) {
                            $row_errors[] = "Product Name is required";
                        }
                        if (empty($data['Unit of Measure'])) {
                            $row_errors[] = "Unit of Measure is required";
                        }
                        if (empty($data['MRP']) || floatval($data['MRP']) <= 0) {
                            $row_errors[] = "Valid MRP (>0) is required";
                        }

                        // Price inputs
                        $mrp = floatval($data['MRP'] ?? 0);
                        $discount_str = trim($data['Discount'] ?? '');
                        $retail_markup_type = strtolower(trim($data['Retail Markup Type'] ?? ''));
                        $retail_markup_val = floatval($data['Retail Markup'] ?? 0);
                        $wholesale_markup_type = strtolower(trim($data['Wholesale Markup Type'] ?? ''));
                        $wholesale_markup_val = floatval($data['Wholesale Markup'] ?? 0);

                        // 1. Calculate Stock Price
                        $stock_price = $mrp;
                        $stock_calc_method = 'mrp_only';

                        if ($discount_str !== '') {
                            $is_percentage = (stripos($discount_str, '%') !== false || stripos($discount_str, 'percent') !== false);
                            $disc_value = $is_percentage
                                ? floatval(str_replace(['%', 'percent'], '', $discount_str))
                                : floatval($discount_str);

                            if ($is_percentage) {
                                $disc_value = max(0, min(100, $disc_value));
                                $stock_price = $mrp * (1 - $disc_value / 100);
                            } else {
                                $stock_price = max(0, $mrp - $disc_value);
                            }
                            $stock_calc_method = 'mrp_discount';
                        }

                        $stock_price = round($stock_price, 2);

                        if ($stock_price <= 0) {
                            $row_errors[] = "Stock Price calculated as ₹0 or negative";
                        }

                        // 2. Calculate Retail Price
                        $retail_price = $stock_price;
                        $retail_calc_method = 'no_markup';

                        if ($retail_markup_val > 0 && in_array($retail_markup_type, ['percentage', 'percent', 'fixed', 'amount', 'rupees', '₹'])) {
                            $is_percent = in_array($retail_markup_type, ['percentage', 'percent']);
                            $markup_amount = $is_percent ? $stock_price * ($retail_markup_val / 100) : $retail_markup_val;
                            $retail_price = $stock_price + $markup_amount;
                            $retail_calc_method = 'markup';
                        }
                        $retail_price = round($retail_price, 2);

                        // 3. Calculate Wholesale Price
                        $wholesale_price = $stock_price;
                        $wholesale_calc_method = 'no_markup';

                        if ($wholesale_markup_val > 0 && in_array($wholesale_markup_type, ['percentage', 'percent', 'fixed', 'amount', 'rupees', '₹'])) {
                            $is_percent = in_array($wholesale_markup_type, ['percentage', 'percent']);
                            $markup_amount = $is_percent ? $stock_price * ($wholesale_markup_val / 100) : $wholesale_markup_val;
                            $wholesale_price = $stock_price + $markup_amount;
                            $wholesale_calc_method = 'markup';
                        }
                        $wholesale_price = round($wholesale_price, 2);

                        // Validations
                        if ($retail_price <= $stock_price) {
                            $row_errors[] = "Retail Price (₹$retail_price) not greater than Stock Price";
                        }
                        if ($wholesale_price < $stock_price) {
                            $row_errors[] = "Warning: Wholesale Price lower than Stock Price";
                        }
                        if ($retail_price > $mrp) {
                            $row_errors[] = "Retail Price exceeds MRP";
                        }
                        if ($wholesale_price > $mrp) {
                            $row_errors[] = "Wholesale Price exceeds MRP";
                        }

                        // Category lookup
                        $category_id = null;
                        if (!empty($data['Category'])) {
                            $stmt = $pdo->prepare("SELECT id FROM categories WHERE business_id = ? AND category_name = ? AND status = 'active'");
                            $stmt->execute([$current_business_id, $data['Category']]);
                            if ($cat = $stmt->fetch()) {
                                $category_id = $cat['id'];
                            } else {
                                $row_errors[] = "Category not found: " . htmlspecialchars($data['Category']);
                            }
                        }

                        // Subcategory lookup
                        $subcategory_id = null;
                        if (!empty($data['Subcategory']) && $category_id) {
                            $stmt = $pdo->prepare("SELECT id FROM subcategories WHERE business_id = ? AND category_id = ? AND subcategory_name = ? AND status = 'active'");
                            $stmt->execute([$current_business_id, $category_id, $data['Subcategory']]);
                            if ($sub = $stmt->fetch()) {
                                $subcategory_id = $sub['id'];
                            } else {
                                $row_errors[] = "Subcategory not found: " . htmlspecialchars($data['Subcategory']);
                            }
                        }

                        // HSN Code → GST
                        $gst_id = null;
                        $hsn_code = trim($data['HSN Code'] ?? '');
                        if ($hsn_code) {
                            $stmt = $pdo->prepare("SELECT id FROM gst_rates WHERE business_id = ? AND hsn_code = ? AND status = 'active'");
                            $stmt->execute([$current_business_id, $hsn_code]);
                            if ($gst = $stmt->fetch()) {
                                $gst_id = $gst['id'];
                            } else {
                                $row_errors[] = "HSN Code not found: $hsn_code";
                            }
                        }

                        // Referral
                        $ref_enabled_str = strtolower(trim($data['Referral Enabled'] ?? ''));
                        $referral_enabled = in_array($ref_enabled_str, ['yes', 'true', '1', 'y']) ? 1 : 0;
                        $referral_type = $referral_enabled ? strtolower($data['Referral Type'] ?? 'percentage') : 'percentage';
                        $referral_value = $referral_enabled ? floatval($data['Referral Value'] ?? 0) : 0;

                        // Store in preview
                        $data['_row_num'] = $row_num;
                        $data['_errors'] = $row_errors;
                        $data['_category_id'] = $category_id;
                        $data['_subcategory_id'] = $subcategory_id;
                        $data['_gst_id'] = $gst_id;
                        $data['_hsn_code'] = $hsn_code;
                        $data['_stock_price'] = $stock_price;
                        $data['_retail_price'] = $retail_price;
                        $data['_wholesale_price'] = $wholesale_price;
                        $data['_stock_calc_method'] = $stock_calc_method;
                        $data['_retail_calc_method'] = $retail_calc_method;
                        $data['_wholesale_calc_method'] = $wholesale_calc_method;
                        $data['_referral_enabled'] = $referral_enabled;
                        $data['_referral_type'] = $referral_type;
                        $data['_referral_value'] = $referral_value;

                        $preview_data[] = $data;

                        if (empty($row_errors)) {
                            $valid_records++;
                        } else {
                            $invalid_records++;
                        }
                    }

                    $total_records = count($preview_data);
                    fclose($handle);

                    if ($total_records === 0) {
                        $error = "No valid data rows found in CSV.";
                    }
                }
            }
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// CONFIRMED IMPORT - FIXED VERSION
// ──────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    $import_data = json_decode($_POST['import_data'] ?? '[]', true);

    if (empty($import_data)) {
        $error = "No valid data to import.";
    } elseif ($shop_id <= 0) {
        $error = "Please select a valid shop/warehouse.";
    } else {
        $pdo->beginTransaction();
        try {
            $inserted = $updated = $stock_updated = $failed = 0;
            $import_errors = [];

            foreach ($import_data as $row) {
                try {
                    $product_name = trim($row['Product Name']);
                    $unit = trim($row['Unit of Measure']);
                    $product_code = !empty($row['Product Code']) ? trim($row['Product Code']) : null;
                    $barcode = !empty($row['Barcode']) ? trim($row['Barcode']) : null;

                    // Find existing product
                    $existing = null;
                    if ($product_code) {
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE business_id = ? AND product_code = ?");
                        $stmt->execute([$current_business_id, $product_code]);
                        $existing = $stmt->fetch();
                    }
                    if (!$existing && $barcode) {
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE business_id = ? AND barcode = ?");
                        $stmt->execute([$current_business_id, $barcode]);
                        $existing = $stmt->fetch();
                    }
                    if (!$existing) {
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE business_id = ? AND product_name = ? AND unit_of_measure = ?");
                        $stmt->execute([$current_business_id, $product_name, $unit]);
                        $existing = $stmt->fetch();
                    }

                    // Use the PRE-VALIDATED values from preview (safe because only valid rows are here)
                    $category_id = $row['_category_id'];         // Already validated or null
                    $subcategory_id = $row['_subcategory_id'];   // Already validated or null
                    $gst_id = $row['_gst_id'];                   // Already validated or null
                    $hsn_code = $row['_hsn_code'] ?? null;

                    if ($existing) {
                        // UPDATE existing product
                        $stmt = $pdo->prepare("
                            UPDATE products SET
                                product_code = ?,
                                barcode = ?,
                                hsn_code = ?,
                                gst_id = ?,
                                category_id = ?,
                                subcategory_id = ?,
                                stock_price = ?,
                                retail_price = ?,
                                wholesale_price = ?,
                                mrp = ?,
                                referral_enabled = ?,
                                referral_type = ?,
                                referral_value = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $product_code,
                            $barcode,
                            $hsn_code,
                            $gst_id,
                            $category_id,
                            $subcategory_id,
                            $row['_stock_price'],
                            $row['_retail_price'],
                            $row['_wholesale_price'],
                            floatval($row['MRP'] ?? 0),
                            $row['_referral_enabled'],
                            $row['_referral_type'],
                            $row['_referral_value'],
                            $existing['id']
                        ]);
                        $updated++;
                    } else {
                        // INSERT new product
                        $stmt = $pdo->prepare("
                            INSERT INTO products (
                                business_id, product_name, product_code, barcode, hsn_code, gst_id,
                                category_id, subcategory_id, unit_of_measure,
                                stock_price, retail_price, wholesale_price, mrp,
                                referral_enabled, referral_type, referral_value,
                                is_active, created_at, updated_at
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
                            )
                        ");
                        $stmt->execute([
                            $current_business_id,
                            $product_name,
                            $product_code,
                            $barcode,
                            $hsn_code,
                            $gst_id,
                            $category_id,
                            $subcategory_id,
                            $unit,
                            $row['_stock_price'],
                            $row['_retail_price'],
                            $row['_wholesale_price'],
                            floatval($row['MRP'] ?? 0),
                            $row['_referral_enabled'],
                            $row['_referral_type'],
                            $row['_referral_value']
                        ]);
                        $inserted++;
                    }

                    $product_id = $existing ? $existing['id'] : $pdo->lastInsertId();

                    // Update stock
                    $qty = intval($row['Current Stock'] ?? 0);
                    if ($qty !== 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO product_stocks (product_id, shop_id, business_id, quantity, last_updated)
                            VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE
                                quantity = quantity + VALUES(quantity),
                                last_updated = NOW()
                        ");
                        $stmt->execute([$product_id, $shop_id, $current_business_id, $qty]);
                        if ($stmt->rowCount() > 0) $stock_updated++;
                    }

                } catch (Exception $e) {
                    $failed++;
                    $import_errors[] = "Row {$row['_row_num']}: " . $e->getMessage();
                }
            }

            $pdo->commit();

            $success = "Import completed successfully!<br>
                • New products: <strong>$inserted</strong><br>
                • Updated products: <strong>$updated</strong><br>
                • Stock updated: <strong>$stock_updated</strong><br>
                • Failed rows: <strong>$failed</strong>";

            if ($import_errors) {
                $error = "Some rows failed:<br>" . implode("<br>", array_slice($import_errors, 0, 10));
                if (count($import_errors) > 10) $error .= "<br><strong>...and " . (count($import_errors)-10) . " more</strong>";
            }

            $preview_data = null; // Clear preview after import

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="en">
<?php $page_title = "Import Products - Auto Price Calculation"; ?>
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu"><div data-simplebar class="h-100"><?php include('includes/sidebar.php'); ?></div></div>
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0"><i class="bx bx-import me-2"></i> Import Products (Auto Pricing)</h4>
                            <div>
                                <a href="products.php" class="btn btn-outline-secondary me-2"><i class="bx bx-arrow-back"></i> Back</a>
                                <a href="download_template.php" class="btn btn-info"><i class="bx bx-download"></i> Download Template</a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= nl2br(htmlspecialchars($error)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $success ?>
                    <div class="mt-3">
                        <a href="products.php" class="btn btn-success me-2">View Products</a>
                        <a href="product_import.php" class="btn btn-outline-secondary">Import More</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <?php if (!$preview_data): ?>
                <div class="card">
                    <div class="card-body">
                        <h5><i class="bx bx-cloud-upload"></i> Upload Product CSV</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Preview Import
                            </button>
                        </form>

                        <div class="mt-4 alert alert-info">
                            <strong>Required columns:</strong><br>
                            Product Name*, Unit of Measure*, MRP*, Discount, Retail Markup Type, Retail Markup,<br>
                            Wholesale Markup Type, Wholesale Markup<br><br>
                            <strong>Note:</strong> Stock Price, Retail Price & Wholesale Price are <u>automatically calculated</u> — no need to include them.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                                <!-- Preview Table -->
                <?php if ($preview_data): ?>
                <div class="card">
                    <div class="card-body">
                        <h5><i class="bx bx-list-check"></i> Import Preview (<?= $total_records ?> rows)</h5>
                        <div class="row mb-4">
                            <div class="col-md-3"><strong>Total:</strong> <?= $total_records ?></div>
                            <div class="col-md-3 text-success"><strong>Valid:</strong> <?= $valid_records ?></div>
                            <div class="col-md-3 text-warning"><strong>Issues:</strong> <?= $invalid_records ?></div>
                            <div class="col-md-3"><strong>Success rate:</strong> <?= $total_records > 0 ? round(($valid_records / $total_records) * 100, 1) : 0 ?>%</div>
                        </div>

                        <form method="POST" id="importForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Add stock to location:</label>
                                    <select name="shop_id" class="form-select" required>
                                        <option value="">-- Select Shop/Warehouse --</option>
                                        <?php foreach($shops as $shop): ?>
                                        <option value="<?= $shop['id'] ?>" <?= $shop['id'] == $current_shop_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($shop['shop_name']) ?> (<?= ucfirst($shop['location_type']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Unit</th>
                                            <th>Category</th>
                                            <th>Subcategory</th>
                                            <th>HSN Code</th>
                                            <th class="text-end">MRP</th>
                                            <th>Discount</th>
                                            <th class="text-end">Stock Price<br><small>(Calculated)</small></th>
                                            <th class="text-end">Retail Price<br><small>(Calculated)</small></th>
                                            <th class="text-end">Wholesale Price<br><small>(Calculated)</small></th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($preview_data as $row): ?>
                                        <tr class="<?= empty($row['_errors']) ? 'table-success' : 'table-warning' ?>">
                                            <td><?= $row['_row_num'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['Product Name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($row['Product Code'] ?? '') ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($row['Unit of Measure']) ?></td>
                                            
                                            <!-- Category -->
                                            <td>
                                                <?php if (!empty($row['Category'])): ?>
                                                    <?= htmlspecialchars($row['Category']) ?>
                                                    <?php if (is_null($row['_category_id'])): ?>
                                                        <span class="text-danger d-block small">(Not found!)</span>
                                                    <?php else: ?>
                                                        <span class="text-success small">✓</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em class="text-muted">-</em>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Subcategory -->
                                            <td>
                                                <?php if (!empty($row['Subcategory'])): ?>
                                                    <?= htmlspecialchars($row['Subcategory']) ?>
                                                    <?php if (is_null($row['_subcategory_id'])): ?>
                                                        <span class="text-danger d-block small">(Not found!)</span>
                                                    <?php else: ?>
                                                        <span class="text-success small">✓</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em class="text-muted">-</em>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- HSN Code -->
                                            <td>
                                                <?php if (!empty($row['HSN Code'])): ?>
                                                    <?= htmlspecialchars($row['HSN Code']) ?>
                                                    <?php if (is_null($row['_gst_id'])): ?>
                                                        <span class="text-danger d-block small">(Not in GST list)</span>
                                                    <?php else: ?>
                                                        <span class="text-success small">✓</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em class="text-muted">-</em>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="text-end"><?= number_format($row['MRP'] ?? 0, 2) ?></td>
                                            <td><?= htmlspecialchars($row['Discount'] ?? '-') ?></td>
                                            
                                            <td class="text-end">
                                                <?= number_format($row['_stock_price'], 2) ?>
                                                <small class="d-block text-muted">
                                                    <?= $row['_stock_calc_method'] === 'mrp_discount' ? '(MRP - Discount)' : '(= MRP)' ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <?= number_format($row['_retail_price'], 2) ?>
                                                <small class="d-block text-muted">
                                                    <?= $row['_retail_calc_method'] === 'markup' ? '(+ Markup)' : '(= Stock)' ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <?= number_format($row['_wholesale_price'], 2) ?>
                                                <small class="d-block text-muted">
                                                    <?= $row['_wholesale_calc_method'] === 'markup' ? '(+ Markup)' : '(= Stock)' ?>
                                                </small>
                                            </td>
                                            
                                            <td>
                                                <?php if (empty($row['_errors'])): ?>
                                                    <span class="badge bg-success">Valid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning" data-bs-toggle="tooltip" title="<?= implode("\n", array_map('htmlspecialchars', $row['_errors'])) ?>">
                                                        <?= count($row['_errors']) ?> issue(s)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <input type="hidden" name="import_data" value="<?= htmlspecialchars(json_encode(array_filter($preview_data, fn($r) => empty($r['_errors'])))) ?>">

                            <div class="mt-4 text-end">
                                <?php if ($valid_records > 0): ?>
                                    <button type="submit" name="confirm_import" class="btn btn-success btn-lg">
                                        <i class="bx bx-check-circle"></i> Import <?= $valid_records ?> Valid Products
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-lg" disabled>No valid records to import</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
<script>
    $(document).ready(function() {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
</body>
</html>