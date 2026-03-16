<?php
// pos3.php - Complete POS System with Modern UI and Advanced Features
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$shop_id = $_SESSION['current_shop_id'] ?? 1;
$business_id = $_SESSION['current_business_id'] ?? 1;

// Verify the user has access to this business
$stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_business = $stmt->fetchColumn();

if ($business_id != $user_business) {
    $role_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $role_stmt->execute([$user_id]);
    $user_role = $role_stmt->fetchColumn();

    if ($user_role !== 'admin') {
        $_SESSION['current_business_id'] = $user_business;
        $business_id = $user_business;
    }
}

// Get warehouse info for THIS BUSINESS ONLY
$warehouse = $pdo->prepare("SELECT id, shop_name FROM shops WHERE is_warehouse = 1 AND business_id = ? LIMIT 1");
$warehouse->execute([$business_id]);
$warehouse = $warehouse->fetch();
$warehouse_id = $warehouse['id'] ?? 0;
$warehouse_name = $warehouse['shop_name'] ?? 'Warehouse';

// Get user's shop name for THIS BUSINESS
$shop_name = $pdo->prepare("SELECT shop_name FROM shops WHERE id = ? AND business_id = ?");
$shop_name->execute([$shop_id, $business_id]);
$shop_name = $shop_name->fetchColumn() ?? 'Shop';

// Verify the shop belongs to the current business
$shop_check = $pdo->prepare("SELECT id FROM shops WHERE id = ? AND business_id = ?");
$shop_check->execute([$shop_id, $business_id]);
if (!$shop_check->fetch()) {
    $first_shop = $pdo->prepare("SELECT id, shop_name FROM shops WHERE business_id = ? AND is_active = 1 ORDER BY id LIMIT 1");
    $first_shop->execute([$business_id]);
    $shop = $first_shop->fetch();
    if ($shop) {
        $shop_id = $shop['id'];
        $_SESSION['shop_id'] = $shop_id;
        $shop_name = $shop['shop_name'];
    }
}

// Check for held invoice restoration - ONLY FOR THIS BUSINESS
$restore_invoice_id = $_GET['restore'] ?? 0;
$restored_invoice = null;
if ($restore_invoice_id) {
    $stmt = $pdo->prepare("SELECT * FROM held_invoices WHERE id = ? AND shop_id = ? AND business_id = ?");
    $stmt->execute([$restore_invoice_id, $shop_id, $business_id]);
    $restored_invoice = $stmt->fetch();
}

// Check for editing quotation
$edit_quotation_id = $_GET['edit_quotation'] ?? 0;
$editing_quotation = null;
if ($edit_quotation_id) {
    // Get quotation details
    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ? AND business_id = ? AND shop_id = ?");
    $stmt->execute([$edit_quotation_id, $business_id, $shop_id]);
    $editing_quotation = $stmt->fetch();

    // Get quotation items
    if ($editing_quotation) {
        $itemsStmt = $pdo->prepare("
            SELECT qi.*, p.product_name, p.product_code, p.barcode,
                   p.retail_price, p.wholesale_price, p.stock_price,
                   p.hsn_code, p.gst_id, p.unit_of_measure,
                   p.secondary_unit, p.sec_unit_conversion, p.sec_unit_price_type, p.sec_unit_extra_charge,
                   COALESCE(g.cgst_rate, 0) as cgst_rate,
                   COALESCE(g.sgst_rate, 0) as sgst_rate,
                   COALESCE(g.igst_rate, 0) as igst_rate
            FROM quotation_items qi
            LEFT JOIN products p ON qi.product_id = p.id
            LEFT JOIN gst_rates g ON p.gst_id = g.id
            WHERE qi.quotation_id = ?
        ");
        $itemsStmt->execute([$edit_quotation_id]);
        $editing_items = $itemsStmt->fetchAll();
    }
}

// Get next quotation number
$quotation_number = 'QTN' . date('Ym') . '-0001';
$last_quotation = $pdo->prepare("SELECT quotation_number FROM quotations WHERE business_id = ? ORDER BY id DESC LIMIT 1");
$last_quotation->execute([$business_id]);
$last_quote = $last_quotation->fetch();
if ($last_quote) {
    $last_num = intval(substr($last_quote['quotation_number'], -4));
    $quotation_number = 'QTN' . date('Ym') . '-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// Get loyalty settings for this business
$loyalty_stmt = $pdo->prepare("SELECT * FROM loyalty_settings WHERE business_id = ?");
$loyalty_stmt->execute([$business_id]);
$loyalty_settings = $loyalty_stmt->fetch();

if (!$loyalty_settings) {
    // Create default loyalty settings
    $insert_stmt = $pdo->prepare("
        INSERT INTO loyalty_settings (business_id, points_per_amount, amount_per_point, 
                                      redeem_value_per_point, min_points_to_redeem, expiry_months, is_active)
        VALUES (?, 0.01, 100.00, 1.00, 50, NULL, 1)
    ");
    $insert_stmt->execute([$business_id]);
    $loyalty_settings = [
        'points_per_amount' => 0.01,
        'amount_per_point' => 100.00,
        'redeem_value_per_point' => 1.00,
        'min_points_to_redeem' => 50,
        'expiry_months' => null
    ];
}

// Get shops for site selection
$shops_stmt = $pdo->prepare("SELECT id, shop_name FROM shops WHERE business_id = ? AND is_active = 1");
$shops_stmt->execute([$business_id]);
$shops = $shops_stmt->fetchAll();

// Get categories
$categories_stmt = $pdo->prepare("
    SELECT id, category_name, category_code 
    FROM categories 
    WHERE business_id = ? AND status = 'active' 
    ORDER BY category_name
");
$categories_stmt->execute([$business_id]);
$categories = $categories_stmt->fetchAll();

// Get products - UPDATED QUERY FROM pos.php
$prodSql = "SELECT p.id, p.product_name, p.product_code, p.barcode,
           p.retail_price, p.wholesale_price, p.stock_price, p.mrp,
           p.hsn_code, p.unit_of_measure,
           p.discount_type, p.discount_value,
           p.retail_price_type, p.retail_price_value,
           p.wholesale_price_type, p.wholesale_price_value,
           g.cgst_rate, g.sgst_rate, g.igst_rate,
           p.referral_enabled, p.referral_type, p.referral_value,
           p.secondary_unit, p.sec_unit_conversion, 
           p.sec_unit_price_type, p.sec_unit_extra_charge,
           c.category_name,
           s.subcategory_name,
           COALESCE(ps_shop.quantity, 0) as shop_stock,
           COALESCE(ps_warehouse.quantity, 0) as warehouse_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN subcategories s ON p.subcategory_id = s.id
    LEFT JOIN gst_rates g ON p.gst_id = g.id
    LEFT JOIN product_stocks ps_shop ON p.id = ps_shop.product_id 
        AND ps_shop.shop_id = ?
    LEFT JOIN product_stocks ps_warehouse ON p.id = ps_warehouse.product_id 
        AND ps_warehouse.shop_id = ?
    WHERE p.is_active = 1 AND p.business_id = ?
    ORDER BY p.product_name";

$prodStmt = $pdo->prepare($prodSql);
$prodStmt->execute([$shop_id, $warehouse_id, $business_id]);
$products = $prodStmt->fetchAll();

// Get referral persons
$referral_stmt = $pdo->prepare("
    SELECT id, referral_code, full_name, phone
    FROM referral_person
    WHERE business_id = ? AND is_active = 1
    ORDER BY full_name
");
$referral_stmt->execute([$business_id]);
$referrals = $referral_stmt->fetchAll();

// Prepare JavaScript products array
$jsProducts = [];
$barcodeMap = [];
foreach ($products as $p) {
    $pid = (int) $p['id'];
    $name = htmlspecialchars($p['product_name']);
    $retail = (float) $p['retail_price'];
    $wholesale = (float) $p['wholesale_price'];
    $cost = (float) $p['stock_price'];
    $mrp = (float) $p['mrp'];
    $code = $p['product_code'] ? htmlspecialchars($p['product_code']) : sprintf('P%06d', $pid);
    $barcode = htmlspecialchars($p['barcode'] ?? '');
    $shop_stock = (int) $p['shop_stock'];
    $warehouse_stock = (int) $p['warehouse_stock'];
    $total_stock = $shop_stock + $warehouse_stock;
    $hsn = htmlspecialchars($p['hsn_code'] ?? '');
    $unit_of_measure = htmlspecialchars($p['unit_of_measure'] ?? 'PCS');
    $cgst = (float) ($p['cgst_rate'] ?? 0);
    $sgst = (float) ($p['sgst_rate'] ?? 0);
    $igst = (float) ($p['igst_rate'] ?? 0);
    $total_gst = $cgst + $sgst + $igst;
    
    // Category and Subcategory
    $category = htmlspecialchars($p['category_name'] ?? '');
    $subcategory = htmlspecialchars($p['subcategory_name'] ?? '');

    // Discount fields
    $discount_type = $p['discount_type'] ?? 'percentage';
    $discount_value = (float) ($p['discount_value'] ?? 0);
    
    // Retail price fields
    $retail_price_type = $p['retail_price_type'] ?? 'percentage';
    $retail_price_value = (float) ($p['retail_price_value'] ?? 0);
    
    // Wholesale price fields
    $wholesale_price_type = $p['wholesale_price_type'] ?? 'percentage';
    $wholesale_price_value = (float) ($p['wholesale_price_value'] ?? 0);

    // Referral info
    $referral_enabled = (int) $p['referral_enabled'];
    $referral_type = $p['referral_type'] ?? 'percentage';
    $referral_value = (float) ($p['referral_value'] ?? 0);

    // Secondary unit info
    $secondary_unit = $p['secondary_unit'] ?? null;
    $sec_unit_conversion = (float) ($p['sec_unit_conversion'] ?? 0);
    $sec_unit_price_type = $p['sec_unit_price_type'] ?? 'fixed';
    $sec_unit_extra_charge = (float) ($p['sec_unit_extra_charge'] ?? 0);

    $jsProducts[] = [
        'id' => $pid,
        'name' => $p['product_name'],
        'retail_price' => $retail,
        'wholesale_price' => $wholesale,
        'stock_price' => $cost,
        'mrp' => $mrp,
        'code' => $code,
        'barcode' => $p['barcode'] ?? '',
        'shop_stock' => $shop_stock,
        'warehouse_stock' => $warehouse_stock,
        'total_stock' => $total_stock,
        'hsn_code' => $p['hsn_code'] ?? '',
        'unit_of_measure' => $unit_of_measure,
        'category' => $category,
        'subcategory' => $subcategory,
        'cgst_rate' => $cgst,
        'sgst_rate' => $sgst,
        'igst_rate' => $igst,
        'total_gst_rate' => $total_gst,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'retail_price_type' => $retail_price_type,
        'retail_price_value' => $retail_price_value,
        'wholesale_price_type' => $wholesale_price_type,
        'wholesale_price_value' => $wholesale_price_value,
        'referral_enabled' => $referral_enabled,
        'referral_type' => $referral_type,
        'referral_value' => $referral_value,
        'secondary_unit' => $secondary_unit,
        'sec_unit_conversion' => $sec_unit_conversion,
        'sec_unit_price_type' => $sec_unit_price_type,
        'sec_unit_extra_charge' => $sec_unit_extra_charge,
        'business_id' => $business_id
    ];

    if ($p['barcode'])
        $barcodeMap[$p['barcode']] = $pid;
    $barcodeMap[$code] = $pid;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AS Electricals POS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            touch-action: manipulation;
        }

        body {
            background-color: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }

        /* Top Header */
        .top-header {
            background: white;
            padding: 10px 15px;
            border-bottom: 2px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            flex-wrap: wrap;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            white-space: nowrap;
        }

        .header-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .site-select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
            min-width: 160px;
        }

        .referral-badge {
            padding: 8px 15px;
            background: #2196F3;
            color: white;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .close-btn {
            padding: 8px 15px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }

        .close-btn:hover {
            background: #d32f2f;
        }

        /* Advanced Controls Bar */
        .advanced-controls {
            background: #f9f9f9;
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            height: auto;
            min-height: 60px;
        }

        .control-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .control-label {
            font-size: 13px;
            color: #333;
            font-weight: bold;
            white-space: nowrap;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #4CAF50;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .toggle-label {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
        }

        .bill-type {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 12px;
            min-width: 100px;
        }

        .action-btn-small {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .action-btn-small:hover {
            background: #f0f0f0;
        }

        /* Loyalty Points */
        .loyalty-points {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            white-space: nowrap;
        }

        .points-value {
            font-weight: bold;
            color: #ffd700;
        }

        /* Main POS Container */
        .pos-container {
            display: flex;
            height: calc(100vh - 140px);
            flex-wrap: wrap;
        }

        /* Categories Section */
        .categories-section {
            width: 15%;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            min-width: 180px;
        }

        .section-title {
            padding: 12px;
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #ddd;
            background: #f9f9f9;
            font-size: 14px;
        }

        .categories-list {
            flex: 1;
            overflow-y: auto;
        }

        .category-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            font-size: 13px;
            position: relative;
        }

        .category-item:hover {
            background: #f0f0f0;
        }

        .category-item.active {
            background: #e3f2fd;
            font-weight: bold;
            border-left: 4px solid #2196F3;
        }

        /* Products Section */
        .products-section {
            width: 35%;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            min-width: 300px;
        }

        .products-header {
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            background: #f9f9f9;
            flex-shrink: 0;
        }

        .current-category {
            font-weight: bold;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
        }

        .search-box {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 180px;
            font-size: 12px;
            min-width: 150px;
        }

        .products-list {
            flex: 1;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            padding: 10px;
            grid-auto-rows: min-content;
        }

        .product-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s;
            height: fit-content;
        }

        .product-card:hover {
            border-color: #4CAF50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .product-info h4 {
            font-size: 13px;
            color: #333;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            line-height: 1.3;
        }

        .product-info .code {
            font-size: 11px;
            color: #666;
            margin-bottom: 6px;
        }

        .product-details {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .product-price {
            margin-top: 8px;
            text-align: right;
        }

        .price-main {
            font-weight: bold;
            color: #4CAF50;
            font-size: 14px;
        }

        .price-secondary {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }

        /* Billing Section */
        .billing-section {
            width: 50%;
            background: white;
            display: flex;
            flex-direction: column;
            min-width: 400px;
        }

        .billing-header {
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            background: #f9f9f9;
            flex-shrink: 0;
        }

        .cart-title {
            font-weight: bold;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
        }

        .cart-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cart-count {
            background: #2196F3;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            white-space: nowrap;
        }

        .cart-actions {
            display: flex;
            gap: 5px;
        }

        .cart-btn {
            padding: 4px 8px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }

        .cart-btn:hover {
            background: #f0f0f0;
        }

        .clear-btn {
            background: #f44336;
            color: white;
            border-color: #f44336;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            min-height: 250px;
        }

        .cart-item {
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #fafafa;
        }

        .cart-item-info h4 {
            font-size: 13px;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 4px;
        }

        .cart-item-price {
            color: #4CAF50;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .cart-item-details {
            font-size: 10px;
            color: #666;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 8px;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .qty-btn {
            width: 26px;
            height: 26px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-display {
            min-width: 40px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }

        .remove-btn {
            width: 26px;
            height: 26px;
            border: 1px solid #f44336;
            background: #ffebee;
            color: #f44336;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .summary-section {
            padding: 15px;
            border-top: 2px solid #ddd;
            background: #f9f9f9;
            flex-shrink: 0;
            max-height: 50vh;
            overflow-y: auto;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .summary-label {
            color: #666;
            font-weight: 500;
        }

        .summary-value {
            font-weight: 600;
        }

        .total-row {
            font-size: 15px;
            font-weight: bold;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            margin-top: 8px;
        }

        .discount-controls {
            display: flex;
            gap: 6px;
            align-items: center;
            margin: 6px 0;
            flex-wrap: wrap;
        }

        .discount-input {
            flex: 1;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            min-width: 100px;
        }

        .discount-type {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 12px;
            width: 60px;
        }

        .payment-methods {
            display: flex;
            gap: 6px;
            margin: 8px 0;
            flex-wrap: wrap;
        }

        .payment-method {
            flex: 1;
            padding: 7px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            text-align: center;
            font-size: 11px;
            min-width: 70px;
        }

        .payment-method.active {
            border-color: #4CAF50;
            background: #e8f5e9;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            position: sticky;
            bottom: 0;
            background: #f9f9f9;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
        }

        .print-btn {
            background: #666;
            color: white;
        }

        .checkout-btn {
            background: #4CAF50;
            color: white;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            margin-right: 2px;
        }

        .badge-stock {
            background: #4CAF50;
            color: white;
        }

        .badge-low {
            background: #ff9800;
            color: white;
        }

        .badge-out {
            background: #f44336;
            color: white;
        }

        .badge-wh {
            background: #666;
            color: white;
        }

        .badge-gst {
            background: #2196F3;
            color: white;
        }

        .badge-referral {
            background: #9C27B0;
            color: white;
        }

        .badge-secondary {
            background: #795548;
            color: white;
        }

        /* Modal Overlays */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        /* Quantity Modal */
        .quantity-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1001;
            min-width: 300px;
            max-width: 400px;
            width: 90%;
        }

        .modal-title {
            margin-bottom: 12px;
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }

        .modal-product-info {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .modal-product-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .modal-product-details {
            font-size: 11px;
            color: #666;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .quantity-input-group {
            margin-bottom: 12px;
        }

        .quantity-input-label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }

        .quantity-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            text-align: center;
        }

        .unit-select {
            margin-top: 6px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            width: 100%;
        }

        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-bottom: 12px;
        }

        .num-btn {
            padding: 10px;
            border: 1px solid #ddd;
            background: white;
            font-size: 15px;
            cursor: pointer;
            border-radius: 4px;
        }

        .num-btn:hover {
            background: #f0f0f0;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
        }

        .modal-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
        }

        .cancel-btn {
            background: #f5f5f5;
            color: #333;
        }

        .add-btn {
            background: #4CAF50;
            color: white;
        }

        /* Referral Modal */
        .referral-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1002;
            min-width: 300px;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .referral-options {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 12px 0;
            max-height: 250px;
            overflow-y: auto;
        }

        .referral-option {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .referral-option:hover {
            border-color: #2196F3;
            background: #f0f8ff;
        }

        .referral-option.selected {
            border-color: #2196F3;
            background: #e3f2fd;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 300px;
        }

        .toast {
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 8px;
            padding: 10px;
            min-width: 250px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 4px solid #4CAF50;
            animation: slideIn 0.3s ease-out;
        }

        .toast.error {
            border-left-color: #f44336;
        }

        .toast.warning {
            border-left-color: #ff9800;
        }

        .toast.info {
            border-left-color: #2196F3;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #ccc;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #999;
        }

        /* Product Details Button */
        .details-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 10px;
            padding: 2px;
        }

        .details-btn:hover {
            color: #2196F3;
        }

        /* Item Discount Input */
        .item-discount-controls {
            display: flex;
            gap: 4px;
            margin-top: 4px;
            align-items: center;
        }

        .item-discount-input {
            width: 65px;
            padding: 3px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 10px;
        }

        .item-discount-type {
            padding: 3px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: white;
            font-size: 10px;
            width: 40px;
        }

        /* Invoice Date Input */
        .invoice-date-input {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            width: 130px;
        }

        /* Hold List Modal */
        .hold-list-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1005;
            min-width: 300px;
            max-width: 500px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .hold-list-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 12px;
        }

        .hold-list-table th,
        .hold-list-table td {
            padding: 6px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .hold-list-table th {
            background: #f9f9f9;
            font-weight: bold;
        }

        /* Quotation List Modal */
        .quotation-list-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1006;
            min-width: 300px;
            max-width: 500px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        /* Quotation Modal */
        .quotation-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1007;
            min-width: 300px;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .quotation-input-group {
            margin-bottom: 12px;
        }

        .quotation-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .quotation-notes {
            min-height: 80px;
            resize: vertical;
        }

        /* Profit Modal */
        .profit-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1008;
            min-width: 300px;
            max-width: 500px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .profit-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 11px;
        }

        .profit-table th,
        .profit-table td {
            padding: 6px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .profit-table th {
            background: #f9f9f9;
            font-weight: bold;
        }

        .profit-total-row {
            background: #f0f9ff;
            font-weight: bold;
        }

        .profit-percentage {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .profit-positive {
            background: #d4edda;
            color: #155724;
        }

        .profit-negative {
            background: #f8d7da;
            color: #721c24;
        }

        /* Loyalty Modal */
        .loyalty-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1009;
            min-width: 300px;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .points-summary {
            text-align: center;
            margin-bottom: 15px;
        }

        .points-total {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin: 5px 0;
        }

        .points-input {
            width: 100%;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            text-align: center;
        }

        .points-value {
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
            color: #4CAF50;
        }

        /* Customer Details Modal */
        .customer-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1010;
            min-width: 300px;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .customer-input-group {
            margin-bottom: 10px;
        }

        .customer-input-label {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            color: #666;
        }

        .customer-input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Hold Modal */
        .hold-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1011;
            min-width: 300px;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .hold-input-group {
            margin-bottom: 10px;
        }

        .hold-input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }

        .hold-expiry {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* Confirmation Modal */
        .confirmation-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1012;
            min-width: 300px;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }

        .confirmation-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .confirmation-message {
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        /* Payment Inputs */
        .payment-inputs {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .payment-input-group {
            margin-bottom: 8px;
        }

        .payment-input-label {
            display: block;
            margin-bottom: 3px;
            font-size: 11px;
            color: #666;
        }

        .payment-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 11px;
        }

        /* Invoice Preview Modal */
        .invoice-preview-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1013;
            min-width: 300px;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            width: 90%;
        }

        .preview-content {
            margin: 15px 0;
            font-size: 13px;
        }

        .preview-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .preview-label {
            font-weight: 500;
            color: #666;
        }

        .preview-value {
            font-weight: 600;
        }

        .preview-total {
            font-size: 16px;
            font-weight: bold;
            padding-top: 10px;
            border-top: 2px solid #333;
            margin-top: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .products-list {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
            
            .pos-container {
                height: calc(100vh - 150px);
            }
        }

        @media (max-width: 992px) {
            .top-header {
                height: auto;
                padding: 8px 12px;
            }
            
            .advanced-controls {
                padding: 6px 12px;
                min-height: 50px;
            }
            
            .pos-container {
                flex-direction: column;
                height: calc(100vh - 180px);
            }
            
            .categories-section,
            .products-section,
            .billing-section {
                width: 100%;
                height: 300px;
                border-right: none;
                border-bottom: 1px solid #ddd;
                min-width: unset;
            }
            
            .billing-section {
                height: 400px;
            }
            
            .products-list {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 8px;
                padding: 8px;
            }
        }

        @media (max-width: 768px) {
            .header-controls {
                gap: 6px;
            }
            
            .site-select {
                min-width: 140px;
                font-size: 13px;
            }
            
            .referral-badge {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .close-btn {
                padding: 6px 10px;
                font-size: 13px;
            }
            
            .loyalty-points {
                padding: 5px 8px;
                font-size: 11px;
            }
            
            .advanced-controls {
                gap: 8px;
            }
            
            .control-group {
                gap: 5px;
            }
            
            .action-btn-small {
                padding: 5px 8px;
                font-size: 11px;
            }
            
            .bill-type {
                padding: 5px 8px;
                font-size: 11px;
                min-width: 80px;
            }
            
            .products-list {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            
            .product-card {
                padding: 8px;
            }
            
            .modal-overlay {
                padding: 10px;
            }
            
            .quantity-modal,
            .referral-modal,
            .loyalty-modal,
            .profit-modal,
            .hold-modal,
            .quotation-modal,
            .hold-list-modal,
            .quotation-list-modal,
            .customer-modal,
            .confirmation-modal {
                width: 95%;
                padding: 15px;
            }
        }

        @media (max-width: 576px) {
            .company-name {
                font-size: 16px;
            }
            
            .top-header {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }
            
            .header-controls {
                width: 100%;
                justify-content: space-between;
            }
            
            .pos-container {
                height: calc(100vh - 200px);
            }
            
            .products-list {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            }
            
            .product-info h4 {
                font-size: 12px;
            }
            
            .price-main {
                font-size: 13px;
            }
            
            .summary-section {
                padding: 10px;
            }
            
            .action-btn {
                padding: 8px;
                font-size: 12px;
            }
            
            .payment-methods {
                gap: 4px;
            }
            
            .payment-method {
                font-size: 10px;
                padding: 6px;
                min-width: 60px;
            }
        }

        @media (max-width: 400px) {
            .products-list {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .search-box {
                width: 140px;
                min-width: 120px;
            }
            
            .control-label {
                font-size: 12px;
            }
            
            .toggle-label {
                font-size: 12px;
            }
            
            .action-btn-small span {
                display: none;
            }
            
            .action-btn-small {
                min-width: 36px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="top-header">
        <div class="company-name">AS ELECTRICALS</div>
        <div class="header-controls">
            <select class="site-select" id="site-select">
                <?php foreach ($shops as $shop): ?>
                    <option value="<?= $shop['id'] ?>" <?= $shop['id'] == $shop_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($shop['shop_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="referral-badge" id="referral-badge" onclick="showCustomerModal()">
                Customer: <span id="customer-display">Walk-in Customer</span>
            </div>
            <div class="loyalty-points" id="loyalty-points" onclick="showLoyaltyModal()" style="display: none;">
                <span class="points-label">Points:</span>
                <span class="points-value" id="points-value">0</span>
            </div>
            <a href="invoices.php" class="close-btn">Close POS</a>
        </div>
    </div>

    <!-- Advanced Controls Bar -->
    <div class="advanced-controls">
        <div class="control-group">
            <span class="control-label">Bill Type:</span>
            <select id="bill-type" class="bill-type">
                <option value="gst">GST Bill</option>
                <option value="non-gst">Non-GST Bill</option>
            </select>
        </div>

        <div class="control-group">
            <span class="control-label">Price Type:</span>
            <label class="toggle-switch">
                <input type="checkbox" id="price-toggle">
                <span class="slider"></span>
            </label>
            <span class="toggle-label" id="price-label">Retail</span>
            <button class="action-btn-small" onclick="applyPriceToAll()">
                <span>Apply All</span>
            </button>
        </div>

        <div class="control-group">
            <button class="action-btn-small" onclick="showQuotationModal()">
                <span>📋 Quotation</span>
            </button>
            <button class="action-btn-small" onclick="showHoldModal()">
                <span>⏸️ Hold</span>
            </button>
            <button class="action-btn-small" onclick="showProfitModal()">
                <span>📈 Profit</span>
            </button>
            <button class="action-btn-small" onclick="showQuotationListModal()">
                <span>📄 Quotations</span>
            </button>
            <button class="action-btn-small" onclick="showHoldListModal()">
                <span>📋 Holds</span>
            </button>
        </div>

        <div class="control-group">
            <input type="text" id="barcode-input" placeholder="Scan Barcode" 
                   style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 160px; font-size: 12px;">
        </div>
        
        <div class="control-group">
            <input type="date" id="invoice-date" class="invoice-date-input" value="<?= date('Y-m-d') ?>">
        </div>
    </div>

    <!-- Main POS Container -->
    <div class="pos-container">
        <!-- Categories -->
        <section class="categories-section">
            <div class="section-title">Categories</div>
            <div class="categories-list" id="categories-list">
                <div class="category-item active" data-category="0">All Products</div>
                <?php foreach ($categories as $category): ?>
                    <div class="category-item" data-category="<?= $category['id'] ?>">
                        <?= htmlspecialchars($category['category_name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Products -->
        <section class="products-section">
            <div class="products-header">
                <div class="current-category" id="current-category">All Products</div>
                <input type="text" class="search-box" id="search-box" placeholder="Search products...">
            </div>
            <div class="products-list" id="products-list">
                <?php foreach ($products as $product): ?>
                    <?php
                    $total_stock = $product['shop_stock'] + $product['warehouse_stock'];
                    $stock_class = $product['shop_stock'] > 10 ? 'badge-stock' : 
                                  ($product['shop_stock'] > 0 ? 'badge-low' : 'badge-out');
                    $gst_rate = ($product['cgst_rate'] + $product['sgst_rate'] + $product['igst_rate']);
                    ?>
                    <div class="product-card" data-product-id="<?= $product['id'] ?>" 
                         data-category="<?= $product['category_name'] ?>"
                         data-subcategory="<?= $product['subcategory_name'] ?>">
                        <div class="product-info">
                            <h4>
                                <?= htmlspecialchars($product['product_name']) ?>
                                <button class="details-btn" onclick="showProductDetails(<?= $product['id'] ?>)">
                                    ℹ️
                                </button>
                            </h4>
                            <div class="code"><?= htmlspecialchars($product['product_code']) ?></div>
                            <div class="product-details">
                                <span class="<?= $stock_class ?>">
                                    S: <?= $product['shop_stock'] ?>
                                </span>
                                <?php if ($product['warehouse_stock'] > 0): ?>
                                    <span class="badge-wh">WH: <?= $product['warehouse_stock'] ?></span>
                                <?php endif; ?>
                                <?php if ($gst_rate > 0): ?>
                                    <span class="badge-gst">GST: <?= $gst_rate ?>%</span>
                                <?php endif; ?>
                                <?php if ($product['referral_enabled']): ?>
                                    <span class="badge-referral">Ref</span>
                                <?php endif; ?>
                                <?php if ($product['secondary_unit']): ?>
                                    <span class="badge-secondary"><?= $product['secondary_unit'] ?></span>
                                <?php endif; ?>
                                <?php if ($product['mrp'] > 0): ?>
                                    <span style="color: #f44336; font-weight: bold; font-size: 10px;">
                                        MRP: ₹<?= number_format($product['mrp'], 2) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="product-price">
                            <div class="price-main">
                                ₹<?= number_format($product['retail_price'], 2) ?>
                            </div>
                            <div class="price-secondary">
                                Wholesale: ₹<?= number_format($product['wholesale_price'], 2) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Billing -->
        <section class="billing-section">
            <div class="billing-header">
                <div class="cart-title">Bill Summary</div>
                <div class="cart-info">
                    <span class="cart-count" id="cart-count">0 items</span>
                    <div class="cart-actions">
                        <button class="cart-btn clear-btn" onclick="clearCart()">
                            🗑️ Clear
                        </button>
                    </div>
                </div>
            </div>
            <div class="cart-items" id="cart-items">
                <div class="empty-cart">
                    <p>No items in cart</p>
                    <p style="font-size: 13px; margin-top: 5px;">Tap products to add</p>
                </div>
            </div>
            <div class="summary-section">
                <div class="summary-row">
                    <span class="summary-label">Subtotal:</span>
                    <span class="summary-value" id="subtotal">₹0.00</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Item Discount:</span>
                    <span class="summary-value" id="item-discount">-₹0.00</span>
                </div>
                
                <div class="discount-controls">
                    <input type="number" id="overall-discount-value" class="discount-input" 
                           value="0" min="0" step="0.01" placeholder="Overall Discount">
                    <select id="overall-discount-type" class="discount-type">
                        <option value="percent">%</option>
                        <option value="flat">₹</option>
                    </select>
                </div>
                
                <div class="summary-row">
                    <span class="summary-label">Overall Discount:</span>
                    <span class="summary-value" id="overall-discount-amount">-₹0.00</span>
                </div>
                
                <div class="summary-row" id="loyalty-discount-row" style="display: none;">
                    <span class="summary-label">Points Discount:</span>
                    <span class="summary-value" id="loyalty-discount">-₹0.00</span>
                </div>
                
                <div class="summary-row" id="gst-row">
                    <span class="summary-label">GST:</span>
                    <span class="summary-value" id="gst-amount">₹0.00</span>
                </div>
                
                <div class="summary-row" id="referral-commission-row" style="display: none;">
                    <span class="summary-label">Referral Comm:</span>
                    <span class="summary-value" id="referral-commission">₹0.00</span>
                </div>
                
                <div class="summary-row total-row">
                    <span class="summary-label">Grand Total:</span>
                    <span class="summary-value" id="total">₹0.00</span>
                </div>

                <!-- Payment Methods -->
                <div class="payment-methods">
                    <div class="payment-method active" data-method="cash" onclick="selectPaymentMethod('cash')">
                        💵 Cash
                    </div>
                    <div class="payment-method" data-method="upi" onclick="selectPaymentMethod('upi')">
                        📱 UPI
                    </div>
                    <div class="payment-method" data-method="bank" onclick="selectPaymentMethod('bank')">
                        🏦 Bank
                    </div>
                    <div class="payment-method" data-method="cheque" onclick="selectPaymentMethod('cheque')">
                        🧾 Cheque
                    </div>
                </div>

                <!-- Payment Inputs -->
                <div class="payment-inputs" id="cash-payment-inputs">
                    <div class="payment-input-group">
                        <label class="payment-input-label">Cash Amount</label>
                        <input type="number" class="payment-input" id="cash-amount" value="0" min="0" step="0.01">
                    </div>
                </div>

                <div class="payment-inputs" id="upi-payment-inputs">
                    <div class="payment-input-group">
                        <label class="payment-input-label">UPI Amount</label>
                        <input type="number" class="payment-input" id="upi-amount" value="0" min="0" step="0.01">
                    </div>
                    <div class="payment-input-group">
                        <label class="payment-input-label">UPI Reference (Optional)</label>
                        <input type="text" class="payment-input" id="upi-reference" placeholder="UPI reference">
                    </div>
                </div>

                <div class="payment-inputs" id="bank-payment-inputs">
                    <div class="payment-input-group">
                        <label class="payment-input-label">Bank Amount</label>
                        <input type="number" class="payment-input" id="bank-amount" value="0" min="0" step="0.01">
                    </div>
                    <div class="payment-input-group">
                        <label class="payment-input-label">Bank Reference (Optional)</label>
                        <input type="text" class="payment-input" id="bank-reference" placeholder="Bank reference">
                    </div>
                </div>

                <div class="payment-inputs" id="cheque-payment-inputs">
                    <div class="payment-input-group">
                        <label class="payment-input-label">Cheque Amount</label>
                        <input type="number" class="payment-input" id="cheque-amount" value="0" min="0" step="0.01">
                    </div>
                    <div class="payment-input-group">
                        <label class="payment-input-label">Cheque Number (Optional)</label>
                        <input type="text" class="payment-input" id="cheque-number" placeholder="Cheque number">
                    </div>
                </div>

                <!-- Payment Summary -->
                <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #ddd;">
                    <div class="summary-row">
                        <span class="summary-label">Total Paid:</span>
                        <span class="summary-value" id="total-paid">₹0.00</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Change Due:</span>
                        <span class="summary-value" id="change-due">₹0.00</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Pending Amount:</span>
                        <span class="summary-value" id="pending-amount">₹0.00</span>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="action-btn print-btn" onclick="printBill()">
                        🖨️ Print Bill
                    </button>
                    <button class="action-btn checkout-btn" onclick="previewInvoice()">
                        👁️ Preview Invoice
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal Overlays -->
    <div class="modal-overlay" id="quantity-modal"></div>
    <div class="modal-overlay" id="customer-modal"></div>
    <div class="modal-overlay" id="loyalty-modal"></div>
    <div class="modal-overlay" id="profit-modal"></div>
    <div class="modal-overlay" id="hold-modal"></div>
    <div class="modal-overlay" id="quotation-modal"></div>
    <div class="modal-overlay" id="hold-list-modal"></div>
    <div class="modal-overlay" id="quotation-list-modal"></div>
    <div class="modal-overlay" id="confirmation-modal"></div>
    <div class="modal-overlay" id="invoice-preview-modal"></div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        // Configuration from pos.php backend
        const PRODUCTS = <?php echo json_encode($jsProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const BARCODE_MAP = <?php echo json_encode($barcodeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const SHOP_ID = <?= $shop_id ?>;
        const WAREHOUSE_ID = <?= $warehouse_id ?>;
        const LOYALTY_SETTINGS = <?php echo json_encode($loyalty_settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const MIN_POINTS_TO_REDEEM = <?= $loyalty_settings['min_points_to_redeem'] ?>;
        const REDEEM_VALUE_PER_POINT = <?= $loyalty_settings['redeem_value_per_point'] ?>;
        const BUSINESS_ID = <?= $business_id ?>;
        const USER_ID = <?= $user_id ?>;
        
        // State from pos.php
        let CART = [];
        let GLOBAL_PRICE_TYPE = 'retail';
        let ACTIVE_PAYMENT_METHODS = new Set(['cash']);
        let SELECTED_REFERRAL_ID = null;
        let SELECTED_REFERRAL_NAME = 'Walk-in Customer';
        let GST_TYPE = 'gst';
        let PENDING_CONFIRMATION = null;
        let CURRENT_CUSTOMER_ID = null;
        let CUSTOMER_POINTS = {
            available_points: 0,
            total_points_earned: 0,
            total_points_redeemed: 0
        };
        let LOYALTY_POINTS_DISCOUNT = 0;
        let POINTS_USED = 0;
        let CURRENT_PRODUCT = null;
        let CURRENT_CATEGORY = 0;
        let INVOICE_DATE = "<?= date('Y-m-d') ?>";
        
        // Helper functions from pos.php
        function findProductById(id) {
            return PRODUCTS.find(p => p.id == id);
        }

        function findProductByBarcode(code) {
            const prodId = BARCODE_MAP[String(code).trim()];
            if (prodId) return findProductById(prodId);

            const prod = PRODUCTS.find(p =>
                p.code === String(code).trim() ||
                p.barcode === String(code).trim()
            );
            return prod || null;
        }

        function formatMoney(n) {
            return '₹ ' + parseFloat(n).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#666;font-size:18px;">×</button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement === container) {
                    container.removeChild(toast);
                }
            }, 3000);
        }

        function showConfirmation(title, message, callback) {
            const modalHTML = `
                <div class="confirmation-modal">
                    <div class="confirmation-title">${title}</div>
                    <div class="confirmation-message">${message}</div>
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('confirmation')">Cancel</button>
                        <button class="modal-btn add-btn" onclick="confirmAction()">Confirm</button>
                    </div>
                </div>
            `;
            
            document.getElementById('confirmation-modal').innerHTML = modalHTML;
            document.getElementById('confirmation-modal').style.display = 'block';
            PENDING_CONFIRMATION = callback;
        }

        function confirmAction() {
            if (PENDING_CONFIRMATION) {
                PENDING_CONFIRMATION();
                PENDING_CONFIRMATION = null;
            }
            closeModal('confirmation');
        }

        function closeModal(type) {
            document.getElementById(`${type}-modal`).style.display = 'none';
            document.getElementById(`${type}-modal`).innerHTML = '';
        }

        // Product modal function from pos.php
        function showProductModal(product) {
            CURRENT_PRODUCT = product;
            
            const price = GLOBAL_PRICE_TYPE === 'wholesale' ? parseFloat(product.wholesale_price) : parseFloat(product.retail_price);
            const mrp = parseFloat(product.mrp) || 0;
            const autoDiscount = mrp > 0 ? ((mrp - price) / mrp * 100).toFixed(2) : 0;
            
            const modalHTML = `
                <div class="quantity-modal">
                    <div class="modal-title">Add ${product.name}</div>
                    <div class="modal-product-info">
                        <div class="modal-product-name">${product.name}</div>
                        <div>Code: ${product.code}</div>
                        <div>Price: ₹${price.toFixed(2)} (${GLOBAL_PRICE_TYPE})</div>
                        ${mrp > 0 ? `<div>MRP: ₹${mrp.toFixed(2)} (Auto Discount: ${autoDiscount}%)</div>` : ''}
                        <div>Stock: ${product.shop_stock} in shop, ${product.warehouse_stock} in warehouse</div>
                        ${product.secondary_unit ? `<div>Secondary Unit: ${product.secondary_unit} (${product.sec_unit_conversion} per primary)</div>` : ''}
                    </div>
                    
                    <div class="quantity-input-group">
                        <label class="quantity-input-label">Quantity</label>
                        <input type="number" class="quantity-input" id="modal-quantity" value="1" min="0.01" step="0.01">
                        ${product.secondary_unit ? `
                            <select class="unit-select" id="modal-unit">
                                <option value="primary">${product.unit_of_measure || 'PCS'}</option>
                                <option value="secondary">${product.secondary_unit}</option>
                            </select>
                        ` : ''}
                    </div>
                    
                    <div class="numpad">
                        <button class="num-btn" data-num="1">1</button>
                        <button class="num-btn" data-num="2">2</button>
                        <button class="num-btn" data-num="3">3</button>
                        <button class="num-btn" data-num="4">4</button>
                        <button class="num-btn" data-num="5">5</button>
                        <button class="num-btn" data-num="6">6</button>
                        <button class="num-btn" data-num="7">7</button>
                        <button class="num-btn" data-num="8">8</button>
                        <button class="num-btn" data-num="9">9</button>
                        <button class="num-btn" data-num="0">0</button>
                        <button class="num-btn" id="modal-backspace">⌫</button>
                        <button class="num-btn" id="modal-clear">C</button>
                    </div>
                    
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('quantity')">Cancel</button>
                        <button class="modal-btn add-btn" onclick="addToCart()">Add to Cart</button>
                    </div>
                </div>
            `;
            
            document.getElementById('quantity-modal').innerHTML = modalHTML;
            document.getElementById('quantity-modal').style.display = 'block';
            
            // Setup numpad
            setTimeout(() => {
                document.querySelectorAll('.num-btn[data-num]').forEach(btn => {
                    btn.onclick = function() {
                        const num = this.getAttribute('data-num');
                        const input = document.getElementById('modal-quantity');
                        input.value = input.value === '0' ? num : input.value + num;
                    };
                });
                
                document.getElementById('modal-backspace').onclick = function() {
                    const input = document.getElementById('modal-quantity');
                    input.value = input.value.slice(0, -1) || '1';
                };
                
                document.getElementById('modal-clear').onclick = function() {
                    document.getElementById('modal-quantity').value = '1';
                };
            }, 100);
        }

        // Add to cart function from pos.php
        function addToCart() {
            const quantity = parseFloat(document.getElementById('modal-quantity').value) || 1;
            const unit = document.getElementById('modal-unit') ? document.getElementById('modal-unit').value : 'primary';
            
            if (!CURRENT_PRODUCT || quantity <= 0) return;
            
            // Check stock
            if (quantity > CURRENT_PRODUCT.shop_stock) {
                showToast(`Only ${CURRENT_PRODUCT.shop_stock} units available in shop stock`, 'warning');
                return;
            }
            
            // Calculate actual quantity for secondary units
            let actualQuantity = quantity;
            let secondaryQuantity = null;
            let isSecondaryUnit = false;
            
            if (unit === 'secondary' && CURRENT_PRODUCT.secondary_unit) {
                const conversionRate = parseFloat(CURRENT_PRODUCT.sec_unit_conversion) || 1;
                actualQuantity = quantity / conversionRate;
                secondaryQuantity = quantity;
                isSecondaryUnit = true;
                
                // Check stock with actual quantity
                if (actualQuantity > CURRENT_PRODUCT.shop_stock) {
                    showToast(`Not enough stock for ${quantity} ${CURRENT_PRODUCT.secondary_unit}`, 'warning');
                    return;
                }
            }
            
            const price = GLOBAL_PRICE_TYPE === 'wholesale' ? CURRENT_PRODUCT.wholesale_price : CURRENT_PRODUCT.retail_price;
            const existingIndex = CART.findIndex(item => 
                item.id === CURRENT_PRODUCT.id && 
                item.price_type === GLOBAL_PRICE_TYPE &&
                item.is_secondary_unit === isSecondaryUnit
            );
            
            if (existingIndex >= 0) {
                // Update existing item
                CART[existingIndex].qty += actualQuantity;
                if (secondaryQuantity) {
                    CART[existingIndex].secondary_unit_qty += secondaryQuantity;
                }
            } else {
                // Add new item
                CART.push({
                    id: CURRENT_PRODUCT.id,
                    name: CURRENT_PRODUCT.name,
                    retail_price: CURRENT_PRODUCT.retail_price,
                    wholesale_price: CURRENT_PRODUCT.wholesale_price,
                    stock_price: CURRENT_PRODUCT.stock_price,
                    mrp: CURRENT_PRODUCT.mrp,
                    price_type: GLOBAL_PRICE_TYPE,
                    qty: actualQuantity,
                    code: CURRENT_PRODUCT.code,
                    discountValue: CURRENT_PRODUCT.discount_value,
                    discountType: CURRENT_PRODUCT.discount_type,
                    shop_stock: CURRENT_PRODUCT.shop_stock,
                    warehouse_stock: CURRENT_PRODUCT.warehouse_stock,
                    total_stock: CURRENT_PRODUCT.total_stock,
                    hsn_code: CURRENT_PRODUCT.hsn_code,
                    unit_of_measure: CURRENT_PRODUCT.unit_of_measure || 'PCS',
                    category: CURRENT_PRODUCT.category,
                    subcategory: CURRENT_PRODUCT.subcategory,
                    cgst_rate: CURRENT_PRODUCT.cgst_rate,
                    sgst_rate: CURRENT_PRODUCT.sgst_rate,
                    igst_rate: CURRENT_PRODUCT.igst_rate,
                    total_gst_rate: CURRENT_PRODUCT.total_gst_rate,
                    discount_type: CURRENT_PRODUCT.discount_type,
                    discount_value: CURRENT_PRODUCT.discount_value,
                    retail_price_type: CURRENT_PRODUCT.retail_price_type,
                    retail_price_value: CURRENT_PRODUCT.retail_price_value,
                    wholesale_price_type: CURRENT_PRODUCT.wholesale_price_type,
                    wholesale_price_value: CURRENT_PRODUCT.wholesale_price_value,
                    referral_enabled: CURRENT_PRODUCT.referral_enabled || 0,
                    referral_type: CURRENT_PRODUCT.referral_type || 'percentage',
                    referral_value: CURRENT_PRODUCT.referral_value || 0,
                    secondary_unit: CURRENT_PRODUCT.secondary_unit,
                    sec_unit_conversion: CURRENT_PRODUCT.sec_unit_conversion,
                    sec_unit_price_type: CURRENT_PRODUCT.sec_unit_price_type,
                    sec_unit_extra_charge: CURRENT_PRODUCT.sec_unit_extra_charge,
                    is_secondary_unit: isSecondaryUnit,
                    secondary_unit_qty: secondaryQuantity,
                    actual_unit: isSecondaryUnit ? CURRENT_PRODUCT.secondary_unit : (CURRENT_PRODUCT.unit_of_measure || 'PCS')
                });
            }
            
            closeModal('quantity');
            updateCart();
            showToast(`${CURRENT_PRODUCT.name} added to cart`, 'success');
        }

        // Cart functions from pos.php
        function updateCart() {
            const container = document.getElementById('cart-items');
            const itemCount = CART.reduce((sum, item) => sum + item.qty, 0);
            
            document.getElementById('cart-count').textContent = `${CART.length} items`;
            
            if (CART.length === 0) {
                container.innerHTML = `
                    <div class="empty-cart">
                        <p>No items in cart</p>
                        <p style="font-size: 13px; margin-top: 5px;">Tap products to add</p>
                    </div>
                `;
            } else {
                let html = '';
                CART.forEach((item, index) => {
                    const total = calculateItemTotal(item);
                    const displayQty = item.is_secondary_unit ? item.secondary_unit_qty : item.qty;
                    const displayUnit = item.actual_unit;
                    
                    const price = getItemPrice(item);
                    const isWholesale = item.price_type === 'wholesale';
                    
                    // Stock badges
                    const shop_badge = item.shop_stock > 0 ?
                        (item.shop_stock < 10 ? `<span class='badge low'>S:${item.shop_stock}</span>` : `<span class='badge stock'>S:${item.shop_stock}</span>`) :
                        `<span class='badge out'>S:0</span>`;

                    const warehouse_badge = item.warehouse_stock > 0 ?
                        `<span class='badge wh'>W:${item.warehouse_stock}</span>` :
                        "";
                        
                    const referral_badge = item.referral_enabled ? `<span class='badge referral'>Ref</span>` : '';
                    const secondary_badge = item.is_secondary_unit ? `<span class='badge secondary'>${item.secondary_unit}</span>` : '';
                    
                    // MRP info
                    let mrpInfo = '';
                    if (item.mrp > 0) {
                        const mrpDiscount = ((item.mrp - price) / item.mrp * 100).toFixed(1);
                        mrpInfo = `<span style="color: #f44336; font-weight: bold; font-size: 10px;">MRP: ₹${item.mrp.toFixed(2)} (${mrpDiscount}% off)</span>`;
                    }
                    
                    html += `
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <h4>
                                    ${item.name}
                                    <span class="${isWholesale ? 'badge-wh' : 'badge-gst'}">
                                        ${isWholesale ? 'Wholesale' : 'Retail'}
                                    </span>
                                    ${referral_badge}
                                    ${secondary_badge}
                                </h4>
                                <div class="cart-item-price">
                                    ₹${price.toFixed(2)} × ${displayQty.toFixed(2)} ${displayUnit} = ₹${total.toFixed(2)}
                                </div>
                                <div class="cart-item-details">
                                    <span>Code: ${item.code}</span>
                                    ${item.hsn_code ? `<span>HSN: ${item.hsn_code}</span>` : ''}
                                    ${item.is_secondary_unit ? `<span>Primary: ${item.qty.toFixed(2)} ${item.unit_of_measure || 'PCS'}</span>` : ''}
                                    ${item.total_gst_rate > 0 && GST_TYPE === 'gst' ? `<span>GST: ${item.total_gst_rate}%</span>` : ''}
                                    ${mrpInfo}
                                </div>
                                <div class="item-discount-controls">
                                    <input type="number" class="item-discount-input" 
                                           value="${item.discountValue || 0}" 
                                           onchange="updateItemDiscount(${index}, this.value)"
                                           placeholder="Disc" step="0.01" min="0">
                                    <select class="item-discount-type" 
                                            onchange="updateItemDiscountType(${index}, this.value)">
                                        <option value="percent" ${item.discountType === 'percent' ? 'selected' : ''}>%</option>
                                        <option value="flat" ${item.discountType === 'flat' ? 'selected' : ''}>₹</option>
                                    </select>
                                </div>
                            </div>
                            <div class="cart-item-controls">
                                <button class="qty-btn" onclick="updateCartQty(${index}, -0.5)">-½</button>
                                <button class="qty-btn" onclick="updateCartQty(${index}, -1)">-1</button>
                                <div class="qty-display">${displayQty.toFixed(2)}</div>
                                <button class="qty-btn" onclick="updateCartQty(${index}, 1)">+1</button>
                                <button class="qty-btn" onclick="updateCartQty(${index}, 0.5)">+½</button>
                                <button class="remove-btn" onclick="removeFromCart(${index})">×</button>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }
            
            updateSummary();
        }

        function getItemPrice(item) {
            if (item.is_secondary_unit) {
                // Calculate price for secondary unit
                const basePrice = item.price_type === 'wholesale' ? item.wholesale_price : item.retail_price;
                const primaryQty = item.qty; // This is already in primary units
                let totalPrice = basePrice * primaryQty;
                
                // Add extra charge
                if (item.sec_unit_price_type === 'percentage') {
                    const extraAmount = totalPrice * (item.sec_unit_extra_charge / 100);
                    totalPrice += extraAmount;
                } else {
                    const extraAmount = item.sec_unit_extra_charge * item.secondary_unit_qty;
                    totalPrice += extraAmount;
                }
                
                return totalPrice; // Return total price for the entire secondary quantity
            }
            return item.price_type === 'wholesale' ? item.wholesale_price : item.retail_price;
        }

        function calculateItemTotal(item) {
            const price = getItemPrice(item);
            const quantity = item.is_secondary_unit ? 1 : item.qty; // For secondary unit, we have 1 "item" with the calculated price
            const base = item.is_secondary_unit ? price : (price * quantity);
            const disc = item.discountType === 'percent' ?
                base * (item.discountValue / 100) :
                item.discountValue;
            return Math.max(0, base - disc);
        }

        function calculateItemDiscount(item) {
            const price = getItemPrice(item);
            const quantity = item.is_secondary_unit ? 1 : item.qty;
            const base = item.is_secondary_unit ? price : (price * quantity);
            return item.discountType === 'percent' ?
                base * (item.discountValue / 100) :
                item.discountValue;
        }

        function calculateItemGST(item) {
            const itemNet = calculateItemTotal(item);
            const totalGSTRate = item.total_gst_rate || 0;

            if (totalGSTRate <= 0 || GST_TYPE === 'non-gst') {
                return {
                    taxable_value: itemNet,
                    cgst_amount: 0,
                    sgst_amount: 0,
                    igst_amount: 0,
                    total_gst: 0
                };
            }

            const taxable_value = itemNet / (1 + (totalGSTRate / 100));
            const total_gst = itemNet - taxable_value;

            const cgst_rate = item.cgst_rate || 0;
            const sgst_rate = item.sgst_rate || 0;
            const igst_rate = item.igst_rate || 0;

            const cgst_amount = total_gst * (cgst_rate / totalGSTRate);
            const sgst_amount = total_gst * (sgst_rate / totalGSTRate);
            const igst_amount = total_gst * (igst_rate / totalGSTRate);

            return {
                taxable_value: taxable_value,
                cgst_amount: cgst_amount,
                sgst_amount: sgst_amount,
                igst_amount: igst_amount,
                total_gst: total_gst
            };
        }

        function calculateItemReferralCommission(item) {
            if (!item.referral_enabled || !SELECTED_REFERRAL_ID) {
                return 0;
            }

            const itemNet = calculateItemTotal(item);

            if (item.referral_type === 'percentage') {
                return itemNet * (item.referral_value / 100);
            } else {
                return item.referral_value * item.qty;
            }
        }

        function updateCartQty(index, change) {
            if (index < 0 || index >= CART.length) return;
            
            const item = CART[index];
            const newQty = item.qty + change;
            
            if (newQty < 0.01) {
                removeFromCart(index);
                return;
            }
            
            // Check stock
            if (newQty > item.shop_stock) {
                showToast(`Only ${item.shop_stock} units available`, 'warning');
                return;
            }
            
            item.qty = newQty;
            if (item.is_secondary_unit) {
                item.secondary_unit_qty = newQty * item.sec_unit_conversion;
            }
            
            updateCart();
        }

        function updateItemDiscount(index, value) {
            if (index < 0 || index >= CART.length) return;
            CART[index].discountValue = parseFloat(value) || 0;
            updateCart();
        }

        function updateItemDiscountType(index, type) {
            if (index < 0 || index >= CART.length) return;
            CART[index].discountType = type;
            updateCart();
        }

        function removeFromCart(index) {
            if (index >= 0 && index < CART.length) {
                CART.splice(index, 1);
                updateCart();
                showToast('Item removed from cart', 'info');
            }
        }

        function clearCart() {
            if (CART.length > 0) {
                showConfirmation(
                    'Clear Cart',
                    `Clear all ${CART.length} items from cart?`,
                    function() {
                        CART = [];
                        updateCart();
                        showToast('Cart cleared', 'info');
                    }
                );
            }
        }

        // Summary calculation from pos.php
        function calculateTotals() {
            let subtotal = 0;
            let totalItemDiscount = 0;
            let totalTaxable = 0;
            let totalCGST = 0;
            let totalSGST = 0;
            let totalIGST = 0;
            let totalGST = 0;
            let totalReferralCommission = 0;

            CART.forEach(item => {
                const price = getItemPrice(item);
                const quantity = item.is_secondary_unit ? 1 : item.qty;
                const itemTotal = price * quantity;
                const itemDiscount = calculateItemDiscount(item);
                const itemNet = Math.max(0, itemTotal - itemDiscount);

                subtotal += itemTotal;
                totalItemDiscount += itemDiscount;

                const gst = calculateItemGST(item);
                totalTaxable += gst.taxable_value;
                totalCGST += gst.cgst_amount;
                totalSGST += gst.sgst_amount;
                totalIGST += gst.igst_amount;
                totalGST += gst.total_gst;

                totalReferralCommission += calculateItemReferralCommission(item);
            });

            const subtotalAfterItems = subtotal - totalItemDiscount;

            const overallDiscVal = parseFloat(document.getElementById('overall-discount-value').value) || 0;
            const overallDiscType = document.getElementById('overall-discount-type').value;
            const overallDiscount = overallDiscType === 'percent' ?
                subtotalAfterItems * (overallDiscVal / 100) :
                Math.min(overallDiscVal, subtotalAfterItems);

            const totalBeforePoints = Math.max(0, subtotalAfterItems - overallDiscount);
            
            // Apply loyalty points discount
            const pointsDiscount = LOYALTY_POINTS_DISCOUNT > totalBeforePoints ? totalBeforePoints : LOYALTY_POINTS_DISCOUNT;
            const grandTotal = Math.max(0, totalBeforePoints - pointsDiscount + (GST_TYPE === 'gst' ? totalGST : 0));

            return {
                subtotal: subtotal,
                totalItemDiscount: totalItemDiscount,
                overallDiscount: overallDiscount,
                loyaltyPointsDiscount: pointsDiscount,
                totalTaxable: totalTaxable,
                totalCGST: totalCGST,
                totalSGST: totalSGST,
                totalIGST: totalIGST,
                totalGST: totalGST,
                grandTotal: grandTotal,
                totalReferralCommission: totalReferralCommission
            };
        }

        function updateSummary() {
            const totals = calculateTotals();
            
            document.getElementById('subtotal').textContent = '₹' + totals.subtotal.toFixed(2);
            document.getElementById('item-discount').textContent = '-₹' + totals.totalItemDiscount.toFixed(2);
            document.getElementById('overall-discount-amount').textContent = '-₹' + totals.overallDiscount.toFixed(2);
            document.getElementById('gst-amount').textContent = '₹' + totals.totalGST.toFixed(2);
            document.getElementById('total').textContent = '₹' + totals.grandTotal.toFixed(2);
            
            // Show/hide loyalty points discount
            const loyaltyRow = document.getElementById('loyalty-discount-row');
            if (totals.loyaltyPointsDiscount > 0) {
                loyaltyRow.style.display = 'flex';
                document.getElementById('loyalty-discount').textContent = '-₹' + totals.loyaltyPointsDiscount.toFixed(2);
            } else {
                loyaltyRow.style.display = 'none';
            }
            
            // Show/hide referral commission
            const referralRow = document.getElementById('referral-commission-row');
            if (totals.totalReferralCommission > 0) {
                referralRow.style.display = 'flex';
                document.getElementById('referral-commission').textContent = '₹' + totals.totalReferralCommission.toFixed(2);
            } else {
                referralRow.style.display = 'none';
            }
            
            // Show/hide GST based on bill type
            document.getElementById('gst-row').style.display = GST_TYPE === 'gst' ? 'flex' : 'none';
            
            // Update payment summary
            updatePaymentSummary();
        }

        // Payment functions from pos.php
        function selectPaymentMethod(method) {
            // Update UI
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`.payment-method[data-method="${method}"]`).classList.add('active');
            
            // Hide all payment inputs
            document.querySelectorAll('.payment-inputs').forEach(el => {
                el.style.display = 'none';
            });
            
            // Show selected payment input
            document.getElementById(`${method}-payment-inputs`).style.display = 'block';
            
            // Update payment methods set
            ACTIVE_PAYMENT_METHODS.clear();
            ACTIVE_PAYMENT_METHODS.add(method);
            
            // Auto-fill payment amount if cash
            if (method === 'cash') {
                const totals = calculateTotals();
                document.getElementById('cash-amount').value = totals.grandTotal.toFixed(2);
            } else {
                // Clear other payment amounts
                ['upi-amount', 'bank-amount', 'cheque-amount'].forEach(id => {
                    document.getElementById(id).value = '0';
                });
            }
            
            updatePaymentSummary();
        }

        function updatePaymentSummary() {
            let totalPaid = 0;
            
            if (ACTIVE_PAYMENT_METHODS.has('cash')) {
                totalPaid += parseFloat(document.getElementById('cash-amount').value) || 0;
            }
            if (ACTIVE_PAYMENT_METHODS.has('upi')) {
                totalPaid += parseFloat(document.getElementById('upi-amount').value) || 0;
            }
            if (ACTIVE_PAYMENT_METHODS.has('bank')) {
                totalPaid += parseFloat(document.getElementById('bank-amount').value) || 0;
            }
            if (ACTIVE_PAYMENT_METHODS.has('cheque')) {
                totalPaid += parseFloat(document.getElementById('cheque-amount').value) || 0;
            }
            
            const totals = calculateTotals();
            const changeDue = Math.max(0, totalPaid - totals.grandTotal);
            const pendingAmount = Math.max(0, totals.grandTotal - totalPaid);
            
            document.getElementById('total-paid').textContent = '₹' + totalPaid.toFixed(2);
            document.getElementById('change-due').textContent = '₹' + changeDue.toFixed(2);
            document.getElementById('pending-amount').textContent = '₹' + pendingAmount.toFixed(2);
        }

        // Customer modal from pos.php
        function showCustomerModal() {
            // Fetch customers for dropdown
            fetch(`get_customers.php?business_id=${BUSINESS_ID}`)
                .then(response => response.json())
                .then(customers => {
                    let optionsHTML = `
                        <div class="referral-option ${!SELECTED_REFERRAL_ID ? 'selected' : ''}" 
                             data-customer-id="0" onclick="selectCustomer('0', 'Walk-in Customer')">
                            Walk-in Customer
                        </div>`;
                    
                    customers.forEach(customer => {
                        optionsHTML += `
                            <div class="referral-option ${SELECTED_REFERRAL_ID == customer.id ? 'selected' : ''}" 
                                 data-customer-id="${customer.id}" 
                                 onclick="selectCustomer('${customer.id}', '${customer.name}')">
                                ${customer.name} (${customer.phone || 'No phone'})
                            </div>
                        `;
                    });
                    
                    const modalHTML = `
                        <div class="customer-modal">
                            <div class="modal-title">Select Customer</div>
                            <div class="referral-options" id="customer-options">
                                ${optionsHTML}
                            </div>
                            <div class="modal-actions">
                                <button class="modal-btn cancel-btn" onclick="closeModal('customer')">Cancel</button>
                                <button class="modal-btn add-btn" onclick="confirmCustomer()">Confirm</button>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('customer-modal').innerHTML = modalHTML;
                    document.getElementById('customer-modal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load customers', 'error');
                });
        }

        function selectCustomer(customerId, customerName) {
            CURRENT_CUSTOMER_ID = customerId === '0' ? null : parseInt(customerId);
            SELECTED_REFERRAL_NAME = customerName;
            
            // Update UI
            document.querySelectorAll('.referral-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelector(`.referral-option[data-customer-id="${customerId}"]`).classList.add('selected');
        }

        function confirmCustomer() {
            document.getElementById('customer-display').textContent = SELECTED_REFERRAL_NAME;
            document.getElementById('referral-badge').textContent = `Customer: ${SELECTED_REFERRAL_NAME}`;
            
            closeModal('customer');
            
            // Load loyalty points if customer selected
            if (CURRENT_CUSTOMER_ID) {
                loadCustomerPoints(CURRENT_CUSTOMER_ID);
            } else {
                document.getElementById('loyalty-points').style.display = 'none';
                CUSTOMER_POINTS = {
                    available_points: 0,
                    total_points_earned: 0,
                    total_points_redeemed: 0
                };
                LOYALTY_POINTS_DISCOUNT = 0;
                POINTS_USED = 0;
                updateSummary();
            }
        }

        // Loyalty functions from pos.php
        function loadCustomerPoints(customerId) {
            fetch(`get_customer_points.php?customer_id=${customerId}&business_id=${BUSINESS_ID}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        CUSTOMER_POINTS = data.points;
                        document.getElementById('loyalty-points').style.display = 'flex';
                        document.getElementById('points-value').textContent = CUSTOMER_POINTS.available_points;
                    } else {
                        document.getElementById('loyalty-points').style.display = 'none';
                        showToast('Customer not found in loyalty program', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loyalty-points').style.display = 'none';
                });
        }

        function showLoyaltyModal() {
            if (!CURRENT_CUSTOMER_ID) {
                showToast('Please select a customer first', 'warning');
                return;
            }
            
            const totals = calculateTotals();
            const maxPoints = Math.min(CUSTOMER_POINTS.available_points, 
                Math.floor(totals.grandTotal / REDEEM_VALUE_PER_POINT));
            
            const modalHTML = `
                <div class="loyalty-modal">
                    <div class="modal-title">Loyalty Points</div>
                    <div class="points-summary">
                        <div>Available Points</div>
                        <div class="points-total">${CUSTOMER_POINTS.available_points}</div>
                        <div>Value: ₹${(CUSTOMER_POINTS.available_points * REDEEM_VALUE_PER_POINT).toFixed(2)}</div>
                    </div>
                    
                    <div class="quantity-input-group">
                        <label class="quantity-input-label">Points to Redeem (Min: ${MIN_POINTS_TO_REDEEM})</label>
                        <input type="number" class="points-input" id="modal-points" 
                               value="${POINTS_USED}" min="0" max="${maxPoints}" 
                               oninput="updatePointsPreview()">
                        <button style="width: 100%; margin-top: 10px; padding: 8px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;"
                                onclick="useMaxPoints()">
                            Use Maximum (${maxPoints})
                        </button>
                    </div>
                    
                    <div class="points-value" id="points-preview">
                        Discount: ₹${(POINTS_USED * REDEEM_VALUE_PER_POINT).toFixed(2)}
                    </div>
                    
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('loyalty')">Cancel</button>
                        <button class="modal-btn add-btn" onclick="applyPointsDiscount()">Apply Discount</button>
                    </div>
                </div>
            `;
            
            document.getElementById('loyalty-modal').innerHTML = modalHTML;
            document.getElementById('loyalty-modal').style.display = 'block';
        }

        function updatePointsPreview() {
            const points = parseInt(document.getElementById('modal-points').value) || 0;
            const discount = points * REDEEM_VALUE_PER_POINT;
            document.getElementById('points-preview').textContent = `Discount: ₹${discount.toFixed(2)}`;
        }

        function useMaxPoints() {
            const totals = calculateTotals();
            const maxPoints = Math.min(CUSTOMER_POINTS.available_points, 
                Math.floor(totals.grandTotal / REDEEM_VALUE_PER_POINT));
            document.getElementById('modal-points').value = maxPoints;
            updatePointsPreview();
        }

        function applyPointsDiscount() {
            const points = parseInt(document.getElementById('modal-points').value) || 0;
            
            if (points < MIN_POINTS_TO_REDEEM) {
                showToast(`Minimum ${MIN_POINTS_TO_REDEEM} points required to redeem`, 'warning');
                return;
            }
            
            if (points > CUSTOMER_POINTS.available_points) {
                showToast('Cannot use more points than available', 'error');
                return;
            }
            
            const discount = points * REDEEM_VALUE_PER_POINT;
            const totals = calculateTotals();
            
            if (discount > totals.grandTotal) {
                showToast('Discount cannot exceed grand total', 'warning');
                return;
            }
            
            POINTS_USED = points;
            LOYALTY_POINTS_DISCOUNT = discount;
            
            closeModal('loyalty');
            updateSummary();
            showToast(`Applied ${points} points for ₹${discount.toFixed(2)} discount`, 'success');
        }

        // Product details from pos.php
        function showProductDetails(productId) {
            const product = findProductById(productId);
            if (!product) return;

            const retailDiscountFromMRP = product.mrp > 0 ? 
                ((product.mrp - product.retail_price) / product.mrp * 100).toFixed(2) : 0;
            const wholesaleDiscountFromMRP = product.mrp > 0 ? 
                ((product.mrp - product.wholesale_price) / product.mrp * 100).toFixed(2) : 0;

            let details = `
                <strong>${product.name}</strong><br>
                Code: ${product.code}<br>
                HSN: ${product.hsn_code || 'N/A'}<br>
                Category: ${product.category || 'N/A'}<br>
                Subcategory: ${product.subcategory || 'N/A'}<br>
                Unit: ${product.unit_of_measure || 'PCS'}<br><br>
                
                <strong>Pricing:</strong><br>
                Cost Price: ₹${product.stock_price.toFixed(2)}<br>
                Retail Price: ₹${product.retail_price.toFixed(2)} ${product.mrp > 0 ? `(${retailDiscountFromMRP}% off MRP)` : ''}<br>
                Wholesale Price: ₹${product.wholesale_price.toFixed(2)} ${product.mrp > 0 ? `(${wholesaleDiscountFromMRP}% off MRP)` : ''}<br>
                ${product.mrp > 0 ? `MRP: ₹${product.mrp.toFixed(2)}<br>` : ''}<br>
                
                <strong>Stock:</strong><br>
                Shop: ${product.shop_stock}<br>
                Warehouse: ${product.warehouse_stock}<br>
                Total: ${product.total_stock}<br><br>
                
                ${product.total_gst_rate > 0 ? `<strong>GST:</strong> ${product.total_gst_rate}% (CGST: ${product.cgst_rate}%, SGST: ${product.sgst_rate}%, IGST: ${product.igst_rate}%)<br><br>` : ''}
                
                ${product.secondary_unit ? `
                <strong>Secondary Unit:</strong><br>
                Unit: ${product.secondary_unit}<br>
                Conversion: 1 ${product.unit_of_measure || 'PCS'} = ${product.sec_unit_conversion} ${product.secondary_unit}<br>
                Extra Charge: ${product.sec_unit_extra_charge} ${product.sec_unit_price_type === 'percentage' ? '%' : '₹'}<br><br>
                ` : ''}
                
                ${product.referral_enabled ? `
                <strong>Referral Commission:</strong><br>
                Type: ${product.referral_type}<br>
                Value: ${product.referral_value} ${product.referral_type === 'percentage' ? '%' : '₹'}<br>
                Commission per unit: ₹${(product.referral_type === 'percentage' ? product.retail_price * (product.referral_value / 100) : product.referral_value).toFixed(2)}<br>
                ` : ''}
            `;
            
            alert(details);
        }

        // Profit analysis from pos.php
        function showProfitModal() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }

            let totalCost = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
            let totalReferralCommission = 0;
            
            let html = `
                <table class="profit-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Cost</th>
                            <th>Revenue</th>
                            <th>Profit</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            CART.forEach(item => {
                const price = getItemPrice(item);
                const itemRevenue = calculateItemTotal(item);
                const itemCost = item.stock_price * item.qty;
                const itemProfit = itemRevenue - itemCost;
                const profitPercentage = itemCost > 0 ? ((itemProfit / itemCost) * 100) : 0;
                const itemReferralCommission = calculateItemReferralCommission(item);
                
                totalCost += itemCost;
                totalRevenue += itemRevenue;
                totalProfit += itemProfit;
                totalReferralCommission += itemReferralCommission;

                let profitClass = 'profit-neutral';
                if (itemProfit > 0) profitClass = 'profit-positive';
                else if (itemProfit < 0) profitClass = 'profit-negative';

                html += `
                    <tr>
                        <td>
                            <div style="font-weight: bold; font-size: 10px;">${item.name}</div>
                            <small style="font-size: 9px; color: #666;">${item.code}</small>
                        </td>
                        <td>${item.is_secondary_unit ? item.secondary_unit_qty + ' ' + item.secondary_unit : item.qty + ' ' + (item.unit_of_measure || 'PCS')}</td>
                        <td>₹${itemCost.toFixed(2)}</td>
                        <td>₹${itemRevenue.toFixed(2)}</td>
                        <td>
                            <span class="profit-percentage ${profitClass}">
                                ${profitPercentage.toFixed(1)}%
                            </span>
                        </td>
                    </tr>
                `;
            });

            const totalProfitPercentage = totalCost > 0 ? ((totalProfit / totalCost) * 100) : 0;
            let totalProfitClass = 'profit-neutral';
            if (totalProfit > 0) totalProfitClass = 'profit-positive';
            else if (totalProfit < 0) totalProfitClass = 'profit-negative';

            html += `
                    </tbody>
                    <tfoot>
                        <tr class="profit-total-row">
                            <td><strong>TOTAL</strong></td>
                            <td></td>
                            <td><strong>₹${totalCost.toFixed(2)}</strong></td>
                            <td><strong>₹${totalRevenue.toFixed(2)}</strong></td>
                            <td>
                                <span class="profit-percentage ${totalProfitClass}">
                                    ${totalProfitPercentage.toFixed(1)}%
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style="margin-top: 15px; padding: 10px; background: #f0f9ff; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Total Cost:</span>
                        <span style="font-weight: bold;">₹${totalCost.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Total Revenue:</span>
                        <span style="font-weight: bold;">₹${totalRevenue.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; border-top: 1px solid #ddd; padding-top: 5px;">
                        <span>Gross Profit:</span>
                        <span style="font-weight: bold; color: #4CAF50;">₹${totalProfit.toFixed(2)}</span>
                    </div>
                    ${totalReferralCommission > 0 ? `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Referral Commission:</span>
                        <span style="font-weight: bold; color: #f44336;">-₹${totalReferralCommission.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid #ddd; padding-top: 5px;">
                        <span>Net Profit:</span>
                        <span style="font-weight: bold; color: #4CAF50;">₹${(totalProfit - totalReferralCommission).toFixed(2)}</span>
                    </div>
                    ` : ''}
                </div>
            `;

            const modalHTML = `
                <div class="profit-modal">
                    <div class="modal-title">Profit Analysis</div>
                    ${html}
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('profit')">Close</button>
                    </div>
                </div>
            `;
            
            document.getElementById('profit-modal').innerHTML = modalHTML;
            document.getElementById('profit-modal').style.display = 'block';
        }

        // Hold functions from pos.php
        function showHoldModal() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }

            const modalHTML = `
                <div class="hold-modal">
                    <div class="modal-title">Hold Invoice</div>
                    <div class="hold-input-group">
                        <label style="display: block; margin-bottom: 5px; color: #666;">Reference (Optional)</label>
                        <input type="text" class="hold-input" id="hold-reference" placeholder="e.g., Customer name or note">
                    </div>
                    <div class="hold-input-group">
                        <label style="display: block; margin-bottom: 5px; color: #666;">Expiry</label>
                        <div class="hold-expiry">
                            <input type="number" class="hold-input" id="hold-expiry" value="48" min="1" max="720">
                            <span style="color: #666;">hours</span>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('hold')">Cancel</button>
                        <button class="modal-btn add-btn" onclick="saveHoldInvoice()">Hold Invoice</button>
                    </div>
                </div>
            `;
            
            document.getElementById('hold-modal').innerHTML = modalHTML;
            document.getElementById('hold-modal').style.display = 'block';
        }

        function saveHoldInvoice() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }

            const reference = document.getElementById('hold-reference').value.trim() || 'Held Invoice';
            const expiryHours = parseInt(document.getElementById('hold-expiry').value) || 48;
            const totals = calculateTotals();

            const payload = {
                reference: reference,
                expiry_hours: expiryHours,
                invoice_date: INVOICE_DATE,
                customer_name: SELECTED_REFERRAL_NAME,
                customer_phone: '',
                customer_gstin: '',
                customer_address: '',
                shop_id: SHOP_ID,
                seller_id: USER_ID,
                business_id: BUSINESS_ID,
                subtotal: totals.subtotal,
                item_discount: totals.totalItemDiscount,
                overall_discount: totals.overallDiscount,
                total: totals.grandTotal,
                cart_items: CART.map(item => ({
                    product_id: item.id,
                    product_name: item.name,
                    quantity: item.qty,
                    price_type: item.price_type,
                    unit_price: getItemPrice(item),
                    item_discount: item.discountValue || 0,
                    item_discount_type: item.discountType || 'percent',
                    total: calculateItemTotal(item),
                    is_secondary_unit: item.is_secondary_unit,
                    secondary_unit_qty: item.secondary_unit_qty,
                    secondary_unit: item.secondary_unit,
                    unit: item.actual_unit
                })),
                cart_json: JSON.stringify(CART)
            };

            fetch('save_hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Invoice held successfully!', 'success');
                        closeModal('hold');
                        
                        // Clear cart after hold
                        CART = [];
                        updateCart();
                    } else {
                        showToast('Error: ' + (data.message || 'Unknown error'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to hold invoice', 'danger');
                });
        }

        function showHoldListModal() {
            fetch('get_hold_invoices.php?shop_id=' + SHOP_ID + '&business_id=' + BUSINESS_ID)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showToast(data.error, 'error');
                        return;
                    }

                    if (!data.length) {
                        const modalHTML = `
                            <div class="hold-list-modal">
                                <div class="modal-title">Held Invoices</div>
                                <p style="text-align: center; padding: 20px; color: #666;">No held invoices found</p>
                                <div class="modal-actions">
                                    <button class="modal-btn cancel-btn" onclick="closeModal('hold-list')">Close</button>
                                </div>
                            </div>
                        `;
                        document.getElementById('hold-list-modal').innerHTML = modalHTML;
                        document.getElementById('hold-list-modal').style.display = 'block';
                        return;
                    }

                    let tableHTML = `
                        <table class="hold-list-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.forEach((invoice, index) => {
                        const heldDate = new Date(invoice.created_at);
                        const expiryDate = new Date(invoice.expiry_at);
                        const now = new Date();
                        const isExpired = expiryDate < now;

                        tableHTML += `
                            <tr>
                                <td>
                                    <div>${heldDate.toLocaleDateString()}</div>
                                    <small class="text-muted">${heldDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</small>
                                </td>
                                <td>${invoice.reference || 'No Reference'}</td>
                                <td>${invoice.customer_name}</td>
                                <td>${JSON.parse(invoice.cart_items || '[]').length}</td>
                                <td>₹${parseFloat(invoice.total).toFixed(2)}</td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button onclick="loadHoldInvoice(${invoice.id})" 
                                                style="padding: 3px 6px; background: #2196F3; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 10px;"
                                                ${isExpired ? 'disabled title="Expired invoice"' : ''}>
                                            Load
                                        </button>
                                        <button onclick="deleteHoldInvoice(${invoice.id})" 
                                                style="padding: 3px 6px; background: #f44336; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 10px;">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });

                    tableHTML += `
                            </tbody>
                        </table>
                    `;

                    const modalHTML = `
                        <div class="hold-list-modal">
                            <div class="modal-title">Held Invoices</div>
                            ${tableHTML}
                            <div class="modal-actions">
                                <button class="modal-btn cancel-btn" onclick="closeModal('hold-list')">Close</button>
                            </div>
                        </div>
                    `;

                    document.getElementById('hold-list-modal').innerHTML = modalHTML;
                    document.getElementById('hold-list-modal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load hold list', 'error');
                });
        }

        function loadHoldInvoice(holdId) {
            fetch('load_hold_invoice.php?hold_id=' + holdId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Load cart items from hold invoice
                        CART = data.items.map(item => ({
                            id: item.product_id,
                            name: item.product_name,
                            retail_price: item.retail_price || 0,
                            wholesale_price: item.wholesale_price || 0,
                            stock_price: item.stock_price || 0,
                            mrp: item.mrp || 0,
                            price_type: item.price_type || 'retail',
                            qty: item.quantity,
                            code: item.product_code || '',
                            discountValue: item.item_discount || 0,
                            discountType: item.item_discount_type || 'percent',
                            shop_stock: item.shop_stock || 999,
                            warehouse_stock: item.warehouse_stock || 0,
                            total_stock: (item.shop_stock || 0) + (item.warehouse_stock || 0),
                            hsn_code: item.hsn_code || '',
                            unit_of_measure: item.unit_of_measure || 'PCS',
                            category: item.category || '',
                            subcategory: item.subcategory || '',
                            cgst_rate: item.cgst_rate || 0,
                            sgst_rate: item.sgst_rate || 0,
                            igst_rate: item.igst_rate || 0,
                            total_gst_rate: (item.cgst_rate || 0) + (item.sgst_rate || 0) + (item.igst_rate || 0),
                            discount_type: item.discount_type || 'percentage',
                            discount_value: item.discount_value || 0,
                            retail_price_type: item.retail_price_type || 'percentage',
                            retail_price_value: item.retail_price_value || 0,
                            wholesale_price_type: item.wholesale_price_type || 'percentage',
                            wholesale_price_value: item.wholesale_price_value || 0,
                            referral_enabled: item.referral_enabled || 0,
                            referral_type: item.referral_type || 'percentage',
                            referral_value: item.referral_value || 0,
                            secondary_unit: item.secondary_unit || null,
                            sec_unit_conversion: item.sec_unit_conversion || 0,
                            sec_unit_price_type: item.sec_unit_price_type || 'fixed',
                            sec_unit_extra_charge: item.sec_unit_extra_charge || 0,
                            is_secondary_unit: item.is_secondary_unit || false,
                            secondary_unit_qty: item.secondary_unit_qty || null,
                            actual_unit: item.unit || 'PCS'
                        }));

                        // Update customer info
                        SELECTED_REFERRAL_NAME = data.customer_name || 'Walk-in Customer';
                        document.getElementById('customer-display').textContent = SELECTED_REFERRAL_NAME;
                        document.getElementById('referral-badge').textContent = `Customer: ${SELECTED_REFERRAL_NAME}`;

                        // Update UI
                        updateCart();
                        closeModal('hold-list');
                        showToast('Hold invoice loaded successfully', 'success');
                    } else {
                        showToast('Error loading hold invoice', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load hold invoice', 'error');
                });
        }

        function deleteHoldInvoice(id) {
            showConfirmation(
                'Delete Held Invoice',
                'Are you sure you want to delete this held invoice?',
                function() {
                    fetch('delete_hold_invoice.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('Held invoice deleted', 'success');
                                showHoldListModal(); // Refresh list
                            } else {
                                showToast('Error: ' + (data.message || 'Unknown error'), 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('Failed to delete', 'danger');
                        });
                }
            );
        }

        // Quotation functions from pos.php
        function showQuotationModal() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }
            
            const modalHTML = `
                <div class="quotation-modal">
                    <div class="modal-title">Create Quotation</div>
                    <div class="quotation-input-group">
                        <label style="display: block; margin-bottom: 5px; color: #666;">Quotation Number</label>
                        <input type="text" class="quotation-input" id="quotation-number" 
                               value="<?= $quotation_number ?>" readonly>
                    </div>
                    <div class="quotation-input-group">
                        <label style="display: block; margin-bottom: 5px; color: #666;">Customer Name</label>
                        <input type="text" class="quotation-input" id="quotation-customer" 
                               value="${SELECTED_REFERRAL_NAME}" placeholder="Customer Name">
                    </div>
                    <div class="quotation-input-group">
                        <label style="display: block; margin-bottom: 5px; color: #666;">Valid Until</label>
                        <input type="date" class="quotation-input" id="quotation-valid-until" 
                               value="<?= date('Y-m-d', strtotime('+15 days')) ?>">
                    </div>
                    <div class="quotation-input-group">
                        <label style="display: block; margin-bottom: 5px; color: #666;">Notes (Optional)</label>
                        <textarea class="quotation-input quotation-notes" id="quotation-notes" 
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('quotation')">Cancel</button>
                        <button class="modal-btn add-btn" onclick="saveQuotation()">Save Quotation</button>
                    </div>
                </div>
            `;
            
            document.getElementById('quotation-modal').innerHTML = modalHTML;
            document.getElementById('quotation-modal').style.display = 'block';
        }

        function saveQuotation() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }

            const customerName = document.getElementById('quotation-customer').value.trim();
            const validUntil = document.getElementById('quotation-valid-until').value;
            const notes = document.getElementById('quotation-notes').value.trim();
            
            if (!customerName) {
                showToast('Customer name is required', 'warning');
                return;
            }

            const totals = calculateTotals();
            const isEditing = <?= $edit_quotation_id ? 'true' : 'false' ?>;
            const quotationId = <?= $edit_quotation_id ?: 'null' ?>;

            const payload = {
                business_id: BUSINESS_ID,
                shop_id: SHOP_ID,
                quotation_number: document.getElementById('quotation-number').value,
                quotation_date: INVOICE_DATE,
                valid_until: validUntil,
                customer_name: customerName,
                customer_phone: '',
                customer_gstin: '',
                customer_address: '',
                subtotal: totals.subtotal,
                total_discount: totals.totalItemDiscount + totals.overallDiscount,
                grand_total: totals.grandTotal,
                notes: notes,
                created_by: USER_ID,
                items: CART.map(item => ({
                    product_id: item.id,
                    product_name: item.name,
                    quantity: item.qty,
                    secondary_quantity: item.is_secondary_unit ? item.secondary_unit_qty : null,
                    secondary_unit: item.is_secondary_unit ? item.secondary_unit : null,
                    unit: item.actual_unit,
                    unit_price: getItemPrice(item),
                    discount_amount: item.discountValue || 0,
                    discount_type: item.discountType || 'percent',
                    total_price: calculateItemTotal(item),
                    price_type: item.price_type
                })),
                is_editing: isEditing,
                quotation_id: quotationId
            };

            fetch('save_quotation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const action = isEditing ? 'updated' : 'saved';
                        showToast(`Quotation ${action} successfully!`, 'success');
                        closeModal('quotation');
                        
                        // Clear cart after saving quotation
                        CART = [];
                        updateCart();

                        // Reset quotation form
                        document.getElementById('quotation-valid-until').value = '<?= date('Y-m-d', strtotime('+15 days')) ?>';
                        document.getElementById('quotation-notes').value = '';

                        // Redirect to view page if editing
                        if (isEditing) {
                            setTimeout(() => {
                                window.location.href = 'view_quotation.php?id=' + quotationId;
                            }, 1500);
                        }
                    } else {
                        showToast('Error: ' + (data.message || 'Unknown error'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to save quotation', 'danger');
                });
        }

        function showQuotationListModal() {
            fetch('get_quotations.php?shop_id=' + SHOP_ID + '&business_id=' + BUSINESS_ID)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showToast(data.error, 'error');
                        return;
                    }

                    if (!data.length) {
                        const modalHTML = `
                            <div class="quotation-list-modal">
                                <div class="modal-title">Quotations</div>
                                <p style="text-align: center; padding: 20px; color: #666;">No quotations found</p>
                                <div class="modal-actions">
                                    <button class="modal-btn cancel-btn" onclick="closeModal('quotation-list')">Close</button>
                                </div>
                            </div>
                        `;
                        document.getElementById('quotation-list-modal').innerHTML = modalHTML;
                        document.getElementById('quotation-list-modal').style.display = 'block';
                        return;
                    }

                    let tableHTML = `
                        <table class="hold-list-table">
                            <thead>
                                <tr>
                                    <th>Quotation No</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Valid Until</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.forEach((quote, index) => {
                        const date = new Date(quote.quotation_date);
                        const validUntil = new Date(quote.valid_until);
                        const statusClass = {
                            'draft': 'warning',
                            'sent': 'info',
                            'accepted': 'success',
                            'rejected': 'danger',
                            'expired': 'secondary'
                        }[quote.status] || 'secondary';

                        tableHTML += `
                            <tr>
                                <td>${quote.quotation_number}</td>
                                <td>${date.toLocaleDateString()}</td>
                                <td>${quote.customer_name}</td>
                                <td>${validUntil.toLocaleDateString()}</td>
                                <td>₹${parseFloat(quote.grand_total).toFixed(2)}</td>
                                <td>
                                    <span style="display: inline-block; padding: 2px 6px; background: #${statusClass === 'warning' ? 'ff9800' : statusClass === 'info' ? '2196F3' : statusClass === 'success' ? '4CAF50' : statusClass === 'danger' ? 'f44336' : '666'}; color: white; border-radius: 3px; font-size: 10px;">
                                        ${quote.status}
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button onclick="viewQuotation(${quote.id})" 
                                                style="padding: 3px 6px; background: #2196F3; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 10px;">
                                            View
                                        </button>
                                        <button onclick="convertQuotationToInvoice(${quote.id})" 
                                                style="padding: 3px 6px; background: #4CAF50; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 10px;"
                                                ${quote.status !== 'sent' && quote.status !== 'accepted' ? 'disabled' : ''}>
                                            Convert
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });

                    tableHTML += `
                            </tbody>
                        </table>
                    `;

                    const modalHTML = `
                        <div class="quotation-list-modal">
                            <div class="modal-title">Quotations</div>
                            ${tableHTML}
                            <div class="modal-actions">
                                <button class="modal-btn cancel-btn" onclick="closeModal('quotation-list')">Close</button>
                            </div>
                        </div>
                    `;

                    document.getElementById('quotation-list-modal').innerHTML = modalHTML;
                    document.getElementById('quotation-list-modal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load quotations', 'error');
                });
        }

        function viewQuotation(id) {
            window.open('view_quotation.php?id=' + id, '_blank');
        }

        function convertQuotationToInvoice(id) {
            showConfirmation(
                'Convert Quotation',
                'Convert this quotation to invoice?',
                function() {
                    fetch('convert_quotation.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ quotation_id: id })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('Quotation converted to invoice!', 'success');
                                showQuotationListModal(); // Refresh list
                            } else {
                                showToast('Error: ' + (data.message || 'Unknown error'), 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('Failed to convert', 'danger');
                        });
                }
            );
        }

        // NEW: Print Bill function
        function printBill() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }

            const customerName = SELECTED_REFERRAL_NAME;
            if (!customerName || customerName === 'Walk-in Customer') {
                showToast('Please select a customer first', 'warning');
                return;
            }

            // First save the invoice
            saveInvoiceAndPrint();
        }

        // NEW: Preview Invoice function
        function previewInvoice() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }

            const customerName = SELECTED_REFERRAL_NAME;
            if (!customerName || customerName === 'Walk-in Customer') {
                showToast('Please select a customer first', 'warning');
                return;
            }

            // Show invoice preview modal
            const totals = calculateTotals();
            const referralCommission = totals.totalReferralCommission;
            const currentDate = new Date();
            
            let previewHTML = `
                <div class="invoice-preview-modal">
                    <div class="modal-title">Invoice Preview</div>
                    <div class="preview-content">
                        <div class="preview-row">
                            <span class="preview-label">Customer:</span>
                            <span class="preview-value">${customerName}</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Date:</span>
                            <span class="preview-value">${currentDate.toLocaleDateString()} ${currentDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Bill Type:</span>
                            <span class="preview-value">${GST_TYPE === 'gst' ? 'GST Bill' : 'Non-GST Bill'} (${GLOBAL_PRICE_TYPE})</span>
                        </div>
                        
                        <div style="margin: 15px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                            <div style="font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Items</div>
            `;

            CART.forEach((item, index) => {
                const displayQty = item.is_secondary_unit ? item.secondary_unit_qty : item.qty;
                const displayUnit = item.actual_unit;
                const itemTotal = calculateItemTotal(item);
                const price = getItemPrice(item);
                
                previewHTML += `
                    <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                        <div style="font-weight: 500;">${item.name}</div>
                        <div style="font-size: 11px; color: #666;">
                            ${displayQty.toFixed(2)} ${displayUnit} × ₹${price.toFixed(2)} = ₹${itemTotal.toFixed(2)}
                        </div>
                    </div>
                `;
            });

            previewHTML += `
                        </div>
                        
                        <div class="preview-row">
                            <span class="preview-label">Subtotal:</span>
                            <span class="preview-value">₹${totals.subtotal.toFixed(2)}</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Item Discount:</span>
                            <span class="preview-value">-₹${totals.totalItemDiscount.toFixed(2)}</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Overall Discount:</span>
                            <span class="preview-value">-₹${totals.overallDiscount.toFixed(2)}</span>
                        </div>
            `;

            if (totals.loyaltyPointsDiscount > 0) {
                previewHTML += `
                        <div class="preview-row">
                            <span class="preview-label">Points Discount:</span>
                            <span class="preview-value">-₹${totals.loyaltyPointsDiscount.toFixed(2)}</span>
                        </div>
                `;
            }

            if (GST_TYPE === 'gst') {
                previewHTML += `
                        <div class="preview-row">
                            <span class="preview-label">GST:</span>
                            <span class="preview-value">₹${totals.totalGST.toFixed(2)}</span>
                        </div>
                `;
            }

            if (referralCommission > 0) {
                previewHTML += `
                        <div class="preview-row">
                            <span class="preview-label">Referral Commission:</span>
                            <span class="preview-value">₹${referralCommission.toFixed(2)}</span>
                        </div>
                `;
            }

            previewHTML += `
                        <div class="preview-row preview-total">
                            <span class="preview-label">Grand Total:</span>
                            <span class="preview-value">₹${totals.grandTotal.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal('invoice-preview')">Cancel</button>
                        <button class="modal-btn add-btn" onclick="saveInvoiceAndPrint()">Print Invoice</button>
                    </div>
                </div>
            `;

            document.getElementById('invoice-preview-modal').innerHTML = previewHTML;
            document.getElementById('invoice-preview-modal').style.display = 'block';
        }

        // NEW: Save invoice and print
        function saveInvoiceAndPrint() {
            const customerName = SELECTED_REFERRAL_NAME;
            
            // Get payment amounts
            let cashAmount = 0, upiAmount = 0, bankAmount = 0, chequeAmount = 0;
            let upiReference = '', bankReference = '', chequeNumber = '';

            if (ACTIVE_PAYMENT_METHODS.has('cash')) {
                cashAmount = parseFloat(document.getElementById('cash-amount').value) || 0;
            }
            if (ACTIVE_PAYMENT_METHODS.has('upi')) {
                upiAmount = parseFloat(document.getElementById('upi-amount').value) || 0;
                upiReference = document.getElementById('upi-reference').value.trim();
            }
            if (ACTIVE_PAYMENT_METHODS.has('bank')) {
                bankAmount = parseFloat(document.getElementById('bank-amount').value) || 0;
                bankReference = document.getElementById('bank-reference').value.trim();
            }
            if (ACTIVE_PAYMENT_METHODS.has('cheque')) {
                chequeAmount = parseFloat(document.getElementById('cheque-amount').value) || 0;
                chequeNumber = document.getElementById('cheque-number').value.trim();
            }

            const totals = calculateTotals();
            const totalPaid = cashAmount + upiAmount + bankAmount + chequeAmount;
            const changeDue = Math.max(0, totalPaid - totals.grandTotal);
            const pendingAmount = Math.max(0, totals.grandTotal - totalPaid);
            const totalReferralCommission = totals.totalReferralCommission;

            // Check payment if it's not a walk-in customer
            if (customerName !== 'Walk-in Customer' && totalPaid < totals.grandTotal && pendingAmount > 0.01) {
                showToast(`Payment incomplete. Still pending: ₹${pendingAmount.toFixed(2)}`, 'warning');
                return;
            }

            const payload = {
                business_id: BUSINESS_ID,
                shop_id: SHOP_ID,
                invoice_date: INVOICE_DATE,
                customer_name: customerName,
                customer_type: GLOBAL_PRICE_TYPE === 'wholesale' ? 'wholesale' : 'retail',
                gst_status: GST_TYPE === 'gst' ? 1 : 0,
                gst_type: GST_TYPE,
                subtotal: totals.subtotal,
                item_discount: totals.totalItemDiscount,
                discount: parseFloat(document.getElementById('overall-discount-value').value) || 0,
                discount_type: document.getElementById('overall-discount-type').value,
                overall_discount: totals.overallDiscount,
                gst_amount: totals.totalGST,
                total: totals.grandTotal,
                
                cash_amount: cashAmount,
                upi_amount: upiAmount,
                bank_amount: bankAmount,
                cheque_amount: chequeAmount,
                cheque_number: chequeNumber,
                upi_reference: upiReference,
                bank_reference: bankReference,

                change_given: changeDue,
                pending_amount: pendingAmount,

                payment_method: Array.from(ACTIVE_PAYMENT_METHODS).join('+'),
                seller_id: USER_ID,
                referral_id: SELECTED_REFERRAL_ID,
                referral_commission_amount: totalReferralCommission,
                loyalty_points_used: POINTS_USED,
                loyalty_points_discount: totals.loyaltyPointsDiscount,

                items: CART.map(item => {
                    const itemNet = calculateItemTotal(item);
                    const gst = calculateItemGST(item);
                    const referral_commission = calculateItemReferralCommission(item);
                    
                    return {
                        product_id: item.id,
                        quantity: item.qty,
                        sale_type: item.price_type,
                        unit_price: getItemPrice(item),
                        item_discount: item.discountValue || 0,
                        item_discount_type: item.discountType || 'percent',
                        original_price: item.stock_price,
                        total_price: itemNet,
                        hsn_code: item.hsn_code,
                        cgst_rate: item.cgst_rate,
                        sgst_rate: item.sgst_rate,
                        igst_rate: item.igst_rate,
                        cgst_amount: gst.cgst_amount,
                        sgst_amount: gst.sgst_amount,
                        igst_amount: gst.igst_amount,
                        total_with_gst: itemNet + gst.total_gst,
                        taxable_value: gst.taxable_value,
                        profit: itemNet - (item.stock_price * item.qty),
                        gst_inclusive: GST_TYPE === 'gst' ? 1 : 0,
                        referral_commission: referral_commission,
                        unit: item.actual_unit,
                        secondary_quantity: item.is_secondary_unit ? item.secondary_unit_qty : null,
                        secondary_unit: item.is_secondary_unit ? item.secondary_unit : null
                    };
                })
            };

            // Save invoice
            fetch('save_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const invoiceNo = data.invoice_number || 'INV' + Date.now().toString().slice(-6);
                    
                    showToast('Invoice saved successfully!', 'success');
                    
                    // Close preview modal if open
                    closeModal('invoice-preview');
                    
                    // Redirect to invoice_print.php
                    if (data.invoice_id) {
                        window.open(`invoice_print.php?invoice_id=${data.invoice_id}`, '_blank');
                    }
                    
                    // Reset for next customer
                    resetForm();
                    
                    // Show success message
                    let message = `Payment of ₹${totals.grandTotal.toFixed(2)} received!\n\n`;
                    message += `Invoice No: ${invoiceNo}\n`;
                    message += `Date: ${new Date(INVOICE_DATE).toLocaleDateString()}\n`;
                    message += `Customer: ${customerName}\n`;
                    if (POINTS_USED > 0) {
                        message += `Points Used: ${POINTS_USED} (₹${totals.loyaltyPointsDiscount.toFixed(2)})\n`;
                    }
                    if (totalReferralCommission > 0) {
                        message += `Referral Commission: ₹${totalReferralCommission.toFixed(2)}\n`;
                    }
                    message += `\nThank you for your business!`;
                    
                    setTimeout(() => {
                        alert(message);
                        // Auto-select walk-in customer for next transaction
                        SELECTED_REFERRAL_NAME = 'Walk-in Customer';
                        document.getElementById('customer-display').textContent = SELECTED_REFERRAL_NAME;
                        document.getElementById('referral-badge').textContent = `Customer: ${SELECTED_REFERRAL_NAME}`;
                    }, 500);
                    
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to save invoice', 'danger');
            });
        }

        function resetForm() {
            CART = [];
            updateCart();
            
            // Reset payment
            document.getElementById('cash-amount').value = '0';
            document.getElementById('upi-amount').value = '0';
            document.getElementById('bank-amount').value = '0';
            document.getElementById('cheque-amount').value = '0';
            document.getElementById('upi-reference').value = '';
            document.getElementById('bank-reference').value = '';
            document.getElementById('cheque-number').value = '';
            
            // Reset discount
            document.getElementById('overall-discount-value').value = '0';
            
            // Reset loyalty points
            POINTS_USED = 0;
            LOYALTY_POINTS_DISCOUNT = 0;
            
            updateSummary();
        }

        // Price functions
        function applyPriceToAll() {
            if (CART.length === 0) {
                showToast('No items in cart', 'warning');
                return;
            }
            
            showConfirmation(
                'Apply Price Type',
                `Apply ${GLOBAL_PRICE_TYPE} pricing to all ${CART.length} items?`,
                function() {
                    CART.forEach(item => {
                        item.price_type = GLOBAL_PRICE_TYPE;
                    });
                    updateCart();
                    showToast(`Applied ${GLOBAL_PRICE_TYPE} pricing to all items`);
                }
            );
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            init();
        });

        function init() {
            // Set initial state
            updateCart();
            updateSummary();
            
            // Set invoice date
            document.getElementById('invoice-date').value = INVOICE_DATE;
            document.getElementById('invoice-date').onchange = function() {
                INVOICE_DATE = this.value;
            };
            
            // Bill type change
            document.getElementById('bill-type').onchange = function() {
                GST_TYPE = this.value;
                updateSummary();
                showToast(`Switched to ${this.value === 'gst' ? 'GST' : 'Non-GST'} billing`, 'info');
            };
            
            // Price toggle
            document.getElementById('price-toggle').onchange = function() {
                GLOBAL_PRICE_TYPE = this.checked ? 'wholesale' : 'retail';
                document.getElementById('price-label').textContent = GLOBAL_PRICE_TYPE === 'wholesale' ? 'Wholesale' : 'Retail';
                updateSummary();
            };
            
            // Barcode scanner
            document.getElementById('barcode-input').onkeydown = function(e) {
                if (e.key === 'Enter') {
                    const barcode = this.value.trim();
                    if (barcode) {
                        const product = findProductByBarcode(barcode);
                        if (product) {
                            showProductModal(product);
                        } else {
                            showToast(`No product found for barcode: ${barcode}`, 'error');
                        }
                        this.value = '';
                    }
                }
            };
            
            // Category selection
            document.querySelectorAll('.category-item').forEach(item => {
                item.onclick = function() {
                    document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    CURRENT_CATEGORY = parseInt(this.getAttribute('data-category')) || 0;
                    filterProducts();
                };
            });
            
            // Product selection
            document.querySelectorAll('.product-card').forEach(card => {
                card.onclick = function(e) {
                    if (!e.target.classList.contains('details-btn')) {
                        const productId = parseInt(this.getAttribute('data-product-id'));
                        const product = findProductById(productId);
                        if (product) {
                            showProductModal(product);
                        }
                    }
                };
            });
            
            // Search
            document.getElementById('search-box').oninput = filterProducts;
            
            // Discount input
            document.getElementById('overall-discount-value').oninput = updateSummary;
            document.getElementById('overall-discount-type').onchange = updateSummary;
            
            // Payment amount inputs
            ['cash-amount', 'upi-amount', 'bank-amount', 'cheque-amount'].forEach(id => {
                document.getElementById(id).oninput = updatePaymentSummary;
            });
            
            // Default payment method
            selectPaymentMethod('cash');
            
            // If editing quotation, populate cart
            <?php if ($editing_quotation && isset($editing_items)): ?>
                setTimeout(() => {
                    <?php foreach ($editing_items as $item): ?>
                        <?php
                        $product_js = '';
                        foreach ($jsProducts as $prod) {
                            if ($prod['id'] == $item['product_id']) {
                                $product_js = json_encode($prod, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                break;
                            }
                        }
                        ?>
                        const editProduct = <?= $product_js ?: 'null' ?>;
                        if (editProduct) {
                            CART.push({
                                id: editProduct.id,
                                name: editProduct.name,
                                retail_price: editProduct.retail_price,
                                wholesale_price: editProduct.wholesale_price,
                                stock_price: editProduct.stock_price,
                                mrp: editProduct.mrp,
                                price_type: '<?= $item['price_type'] ?>',
                                qty: <?= $item['quantity'] ?>,
                                code: editProduct.code,
                                discountValue: <?= $item['discount_amount'] ?>,
                                discountType: '<?= $item['discount_type'] ?>',
                                shop_stock: editProduct.shop_stock,
                                warehouse_stock: editProduct.warehouse_stock,
                                total_stock: editProduct.total_stock,
                                hsn_code: editProduct.hsn_code,
                                unit_of_measure: editProduct.unit_of_measure || 'PCS',
                                category: editProduct.category,
                                subcategory: editProduct.subcategory,
                                cgst_rate: editProduct.cgst_rate,
                                sgst_rate: editProduct.sgst_rate,
                                igst_rate: editProduct.igst_rate,
                                total_gst_rate: editProduct.total_gst_rate,
                                discount_type: editProduct.discount_type,
                                discount_value: editProduct.discount_value,
                                retail_price_type: editProduct.retail_price_type,
                                retail_price_value: editProduct.retail_price_value,
                                wholesale_price_type: editProduct.wholesale_price_type,
                                wholesale_price_value: editProduct.wholesale_price_value,
                                referral_enabled: editProduct.referral_enabled || 0,
                                referral_type: editProduct.referral_type || 'percentage',
                                referral_value: editProduct.referral_value || 0,
                                secondary_unit: editProduct.secondary_unit,
                                sec_unit_conversion: editProduct.sec_unit_conversion,
                                sec_unit_price_type: editProduct.sec_unit_price_type,
                                sec_unit_extra_charge: editProduct.sec_unit_extra_charge,
                                is_secondary_unit: <?= !empty($item['secondary_unit']) ? 'true' : 'false' ?>,
                                secondary_unit_qty: <?= $item['secondary_quantity'] ?? 'null' ?>,
                                actual_unit: '<?= $item['unit'] ?? ($editProduct['unit_of_measure'] ?? 'PCS') ?>'
                            });
                        }
                    <?php endforeach; ?>
                    updateCart();
                    showToast('Editing quotation #<?= $editing_quotation["quotation_number"] ?>', 'info');
                    
                    // Set customer
                    SELECTED_REFERRAL_NAME = '<?= addslashes($editing_quotation['customer_name']) ?>';
                    document.getElementById('customer-display').textContent = SELECTED_REFERRAL_NAME;
                    document.getElementById('referral-badge').textContent = `Customer: ${SELECTED_REFERRAL_NAME}`;
                }, 500);
            <?php endif; ?>
            
            <?php if ($restored_invoice): ?>
                setTimeout(() => {
                    const heldItems = <?php echo json_encode(json_decode($restored_invoice['cart_items'] ?? '[]', true)); ?>;
                    if (heldItems && heldItems.length > 0) {
                        heldItems.forEach(item => {
                            const prod = findProductById(item.product_id);
                            if (prod) {
                                CART.push({
                                    id: prod.id,
                                    name: prod.name,
                                    retail_price: prod.retail_price,
                                    wholesale_price: prod.wholesale_price,
                                    stock_price: prod.stock_price,
                                    mrp: prod.mrp,
                                    price_type: item.price_type || 'retail',
                                    qty: item.quantity,
                                    code: prod.code,
                                    discountValue: item.discount_value || 0,
                                    discountType: item.discount_type || 'percent',
                                    shop_stock: prod.shop_stock,
                                    warehouse_stock: prod.warehouse_stock,
                                    total_stock: prod.total_stock,
                                    hsn_code: prod.hsn_code,
                                    unit_of_measure: prod.unit_of_measure || 'PCS',
                                    category: prod.category,
                                    subcategory: prod.subcategory,
                                    cgst_rate: prod.cgst_rate,
                                    sgst_rate: prod.sgst_rate,
                                    igst_rate: prod.igst_rate,
                                    total_gst_rate: prod.total_gst_rate,
                                    discount_type: prod.discount_type,
                                    discount_value: prod.discount_value,
                                    retail_price_type: prod.retail_price_type,
                                    retail_price_value: prod.retail_price_value,
                                    wholesale_price_type: prod.wholesale_price_type,
                                    wholesale_price_value: prod.wholesale_price_value,
                                    referral_enabled: prod.referral_enabled || 0,
                                    referral_type: prod.referral_type || 'percentage',
                                    referral_value: prod.referral_value || 0,
                                    secondary_unit: prod.secondary_unit,
                                    sec_unit_conversion: prod.sec_unit_conversion,
                                    sec_unit_price_type: prod.sec_unit_price_type,
                                    sec_unit_extra_charge: prod.sec_unit_extra_charge,
                                    is_secondary_unit: item.is_secondary_unit || false,
                                    secondary_unit_qty: item.secondary_unit_qty || null,
                                    actual_unit: item.unit || (prod.unit_of_measure || 'PCS')
                                });
                            }
                        });
                        updateCart();
                        showToast('Restored held invoice #<?= $restored_invoice["hold_number"] ?>', 'info');
                        
                        // Set customer
                        SELECTED_REFERRAL_NAME = '<?= addslashes($restored_invoice['customer_name']) ?>';
                        document.getElementById('customer-display').textContent = SELECTED_REFERRAL_NAME;
                        document.getElementById('referral-badge').textContent = `Customer: ${SELECTED_REFERRAL_NAME}`;
                    }
                }, 500);
            <?php endif; ?>
        }

        function filterProducts() {
            const searchTerm = document.getElementById('search-box').value.toLowerCase();
            const categoryTitle = CURRENT_CATEGORY === 0 ? 'All Products' : 
                <?php echo json_encode(array_column($categories, 'category_name', 'id')); ?>[CURRENT_CATEGORY] || 'All Products';
            
            document.getElementById('current-category').textContent = categoryTitle;
            
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                const productId = parseInt(card.getAttribute('data-product-id'));
                const product = findProductById(productId);
                if (!product) {
                    card.style.display = 'none';
                    return;
                }
                
                const name = product.name.toLowerCase();
                const code = product.code.toLowerCase();
                const category = product.category ? product.category.toLowerCase() : '';
                const subcategory = product.subcategory ? product.subcategory.toLowerCase() : '';
                
                const inCategory = CURRENT_CATEGORY === 0 || 
                                 (product.category && product.category.toLowerCase().includes(categoryTitle.toLowerCase())) ||
                                 (product.subcategory && product.subcategory.toLowerCase().includes(categoryTitle.toLowerCase()));
                
                const matchesSearch = !searchTerm || 
                                    name.includes(searchTerm) || 
                                    code.includes(searchTerm) ||
                                    category.includes(searchTerm) ||
                                    subcategory.includes(searchTerm);
                
                card.style.display = (inCategory && matchesSearch) ? 'block' : 'none';
            });
        }

        // Make functions available globally
        window.updateCartQty = updateCartQty;
        window.updateItemDiscount = updateItemDiscount;
        window.updateItemDiscountType = updateItemDiscountType;
        window.removeFromCart = removeFromCart;
        window.clearCart = clearCart;
        window.showCustomerModal = showCustomerModal;
        window.selectCustomer = selectCustomer;
        window.confirmCustomer = confirmCustomer;
        window.showLoyaltyModal = showLoyaltyModal;
        window.updatePointsPreview = updatePointsPreview;
        window.useMaxPoints = useMaxPoints;
        window.applyPointsDiscount = applyPointsDiscount;
        window.showProductDetails = showProductDetails;
        window.showProfitModal = showProfitModal;
        window.showHoldModal = showHoldModal;
        window.saveHoldInvoice = saveHoldInvoice;
        window.showHoldListModal = showHoldListModal;
        window.loadHoldInvoice = loadHoldInvoice;
        window.deleteHoldInvoice = deleteHoldInvoice;
        window.showQuotationModal = showQuotationModal;
        window.saveQuotation = saveQuotation;
        window.showQuotationListModal = showQuotationListModal;
        window.viewQuotation = viewQuotation;
        window.convertQuotationToInvoice = convertQuotationToInvoice;
        window.selectPaymentMethod = selectPaymentMethod;
        window.printBill = printBill;
        window.previewInvoice = previewInvoice;
        window.saveInvoiceAndPrint = saveInvoiceAndPrint;
        window.closeModal = closeModal;
        window.showToast = showToast;
        window.applyPriceToAll = applyPriceToAll;
        window.confirmAction = confirmAction;
    </script>
</body>
</html>