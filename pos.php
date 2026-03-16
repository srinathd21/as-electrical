<?php
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
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>

<body data-sidebar="dark">

    <!-- Toast Container -->
    <div id="toastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 9999; margin-top: 70px;">
        <!-- Toasts will be added here dynamically -->
    </div>

    <style>
        /* Remove all vertical scrolling from main page */
        html, body {
            height: 100%;
            
        }

        #wrapper {
            height: 100vh;
            overflow: hidden;
        }

        .page-content {
            height: calc(100vh - 70px);
            overflow: hidden;
        }

        /* Make everything smaller */
        html {
            font-size: 16px;
        }

        .container-fluid {
            padding: 0.25rem;
            height: 100%;
        }

        /* Main layout */
        .main-row {
            display: flex;
            height: 100%;
            margin: 0;
        }

        .left-column {
            width: 75%;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding-right: 0.25rem;
        }

        .right-column {
            width: 25%;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding-left: 0.25rem;
        }

        /* Compact styles */
        .compact-mode .card-body {
            padding: 0.5rem;
        }

        .compact-mode .form-control {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            height: calc(1.5em + 0.5rem + 2px);
        }

        .compact-mode .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            height: calc(1.5em + 0.5rem + 2px);
        }

        .compact-mode .table {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .compact-mode .table th,
        .compact-mode .table td {
            padding: 0.25rem;
        }

        .compact-mode .badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
        }

        .compact-mode h4,
        .compact-mode h5,
        .compact-mode h6 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .compact-mode .card-title {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        /* Make cart table scrollable vertically */
        .cart-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            min-height: 0;
        }

        .cart-container .table {
            margin-bottom: 0;
        }

        /* Stock info */
        .compact-mode .stock-summary {
            padding: 6px;
            margin-bottom: 8px;
        }

        .compact-mode .stock-summary-row {
            font-size: 0.7rem;
            margin-bottom: 3px;
            padding: 2px 0;
        }

        .compact-mode .stock-header {
            margin-bottom: 6px;
        }

        .compact-mode .stock-header .badge {
            font-size: 0.6rem;
            padding: 1px 4px;
        }

        /* Cart table */
        .compact-mode .table-cart td,
        .compact-mode .table-cart th {
            white-space: nowrap;
        }

        .compact-mode .product-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Payment methods */
        .compact-mode .payment-checkbox-group {
            gap: 3px;
        }

        .compact-mode .payment-checkbox {
            padding: 4px 8px;
            font-size: 0.7rem;
        }

        .compact-mode .payment-input-container {
            padding: 6px;
        }

        /* Referral info */
        .referral-badge {
            background: #6f42c1;
            color: white;
            font-size: 0.6rem;
        }

        .referral-commission-info {
            font-size: 0.65rem;
        }

        .referral-product-indicator {
            border-left: 2px solid #6f42c1;
            padding-left: 3px;
        }

        /* Toast styles */
        .custom-toast {
            min-width: 300px;
            font-size: 0.8rem;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .custom-toast.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        .custom-toast .toast-body {
            padding: 0.75rem;
        }

        .custom-toast i {
            font-size: 0.9rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-row {
                flex-direction: column;
            }

            .left-column,
            .right-column {
                width: 100%;
                height: auto;
                padding: 0;
            }

            .compact-mode .row {
                margin-left: -3px;
                margin-right: -3px;
            }

            .compact-mode .col,
            .compact-mode .col-lg-8,
            .compact-mode .col-lg-4 {
                padding-left: 3px;
                padding-right: 3px;
            }

            .compact-mode .table-responsive {
                font-size: 0.7rem;
            }

            .compact-mode .input-group-sm {
                width: auto !important;
            }

            .compact-mode .btn-action {
                width: 20px;
                height: 20px;
                padding: 0;
            }

            .compact-mode .gst-breakdown {
                font-size: 0.6rem;
            }

            .custom-toast {
                min-width: 250px;
            }
        }

        /* Hold invoice modal */
        .hold-invoice-modal .modal-dialog {
            max-width: 400px;
        }

        .hold-invoice-modal .modal-content {
            border-radius: 6px;
        }

        .hold-invoice-modal .modal-header {
            padding: 0.5rem;
            font-size: 0.9rem;
        }

        .hold-invoice-modal .modal-body {
            padding: 1rem;
        }

        .hold-invoice-modal .modal-footer {
            padding: 0.5rem;
        }

        .hold-list-modal .modal-dialog {
            max-width: 700px;
        }

        .hold-list-table {
            font-size: 0.75rem;
        }

        .hold-list-table th {
            background: #f8f9fa;
            padding: 0.4rem;
        }

        .hold-badge {
            font-size: 0.6rem;
            padding: 1px 4px;
        }

        /* Original styles - compacted */
        .stock-info {
            font-size: 0.7rem;
            padding: 2px 4px;
            border-radius: 2px;
            margin-right: 3px;
        }

        .shop-stock {
            background: #17a2b8;
            color: white;
        }

        .warehouse-stock {
            background: #6c757d;
            color: white;
        }

        .low-stock {
            background: #dc3545;
            color: white;
        }

        .out-of-stock {
            background: #343a40;
            color: white;
        }

        .stock-summary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            padding: 8px;
            margin-bottom: 10px;
        }

        .product-stock-info {
            font-size: 0.7rem;
            margin-top: 3px;
        }

        .table-cart th {
            background-color: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .billing-summary-card {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .billing-summary-content {
            flex: 1;
            overflow-y: auto;
            max-height: 250px ;
        }

        .billing-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.75rem;
        }

        .billing-summary-label {
            font-weight: 500;
            color: #495057;
        }

        .billing-summary-value {
            font-weight: 600;
            color: #212529;
        }

        .billing-total-row {
            border-top: 2px solid #333;
            margin-top: 8px;
            padding-top: 8px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .item-total {
            font-weight: bold;
            color: #198754;
            font-size: 0.75rem;
        }

        .discount-input {
            width: 60px;
            font-size: 0.7rem !important;
        }

        .discount-type {
            width: 50px;
            font-size: 0.7rem !important;
        }

        .auto-add-notification {
            display: none;
        }

        .stock-warning {
            border-color: #dc3545 !important;
        }

        .payment-method-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
        }

        .payment-checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }

        .payment-checkbox {
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 3px 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .payment-checkbox:hover {
            border-color: #0d6efd;
        }

        .payment-checkbox.active {
            background: #e7f1ff;
            border-color: #0d6efd;
        }

        .payment-checkbox input[type="checkbox"] {
            width: 12px;
            height: 12px;
            cursor: pointer;
        }

        .payment-checkbox label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.7rem;
        }

        .payment-input-container {
            display: none;
            margin-top: 6px;
            padding: 6px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 3px;
        }

        .payment-input-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-3px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .payment-input-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .payment-amount-input {
            max-width: 150px;
            font-size: 0.7rem !important;
            padding: 0.2rem 0.4rem !important;
        }

        .payment-percentage {
            font-size: 0.6rem;
            padding: 1px 4px;
            border-radius: 8px;
        }

        .price-badge {
            font-size: 0.65rem;
            padding: 1px 4px;
            border-radius: 2px;
        }

        .retail-badge {
            background: #17a2b8;
            color: white;
        }

        .wholesale-badge {
            background: #28a745;
            color: white;
        }

        .gst-badge {
            background: #6f42c1;
            color: white;
        }

        .gst-breakdown {
            font-size: 0.6rem;
            color: #666;
            border-left: 1px solid #6f42c1;
            padding-left: 4px;
            margin-top: 2px;
        }

        .alert-warning {
            padding: 6px 8px;
            margin-top: 6px;
            font-size: 0.7rem;
        }

        .alert-light {
            padding: 6px 8px;
            font-size: 0.7rem;
        }

        .alert-info {
            padding: 6px 8px;
            font-size: 0.7rem;
        }

        .page-title-box {
            padding: 0.5rem 0;
        }

        .row {
            margin-bottom: 0.5rem;
        }

        .mb-1 {
            margin-bottom: 0.25rem !important;
        }

        .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        .mb-3 {
            margin-bottom: 0.75rem !important;
        }

        .mb-4 {
            margin-bottom: 1rem !important;
        }

        .mt-1 {
            margin-top: 0.25rem !important;
        }

        .mt-2 {
            margin-top: 0.5rem !important;
        }

        .mt-3 {
            margin-top: 0.75rem !important;
        }

        .py-1 {
            padding-top: 0.25rem !important;
            padding-bottom: 0.25rem !important;
        }

        .py-2 {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }

        .py-3 {
            padding-top: 0.75rem !important;
            padding-bottom: 0.75rem !important;
        }

        /* Select2 smaller */
        .select2-container .select2-selection--single {
            height: calc(1.5em + 0.5rem + 2px) !important;
            font-size: 0.75rem !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + 0.5rem) !important;
            font-size: 0.75rem !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 0.5rem + 2px) !important;
        }

        .select2-dropdown {
            font-size: 0.75rem !important;
        }

        /* Form labels */
        .form-label {
            font-size: 0.75rem;
            margin-bottom: 0.1rem;
        }

        /* Input group smaller */
        .input-group-sm>.form-control,
        .input-group-sm>.form-select,
        .input-group-sm>.input-group-text,
        .input-group-sm>.btn {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
            height: calc(1.5em + 0.4rem + 2px);
        }

        /* Modal smaller */
        .modal-content {
            font-size: 0.8rem;
        }

        .modal-header {
            padding: 0.5rem 1rem;
        }

        .modal-body {
            padding: 0.75rem;
        }

        .modal-footer {
            padding: 0.5rem;
        }

        /* Customer address field */
        .address-field {
            font-size: 0.7rem;
            height: auto;
            min-height: 40px;
            max-height: 60px;
            resize: vertical;
        }

        /* Product details modal */
        .product-details-modal .modal-dialog {
            max-width: 600px;
        }

        .product-details-modal .modal-body {
            padding: 1rem;
        }

        .product-details-section {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .product-details-section h6 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        /* Product details button */
        .show-details-btn {
            position: absolute;
            right: 10px;
            top: 10px;
            padding: 0.1rem 0.3rem;
            font-size: 0.6rem;
        }

        /* Remove product details from add product section */
        #productDetails {
            display: none !important;
        }

        /* GST Type Selector */
        .gst-type-selector {
            margin-left: 10px;
        }

        .gst-type-selector .form-select {
            font-size: 0.7rem;
            height: calc(1.5em + 0.5rem);
            padding: 0.2rem 0.5rem;
            width: 120px;
        }

        /* Scrollable modal bodies */
        .modal-body-scrollable {
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 10px;
        }

        /* Hide original auto-add notification */
        #autoAddNotification {
            display: none !important;
        }
        .close-btn{
            position: fixed;
            top: 5px;
            right: 10px;
            z-index: 10;
        }
        
        /* Profit Analysis Modal */
        .profit-analysis-modal .modal-dialog {
            max-width: 500px;
        }
        
        .profit-item {
            border-bottom: 1px solid #dee2e6;
            padding: 8px 0;
        }
        
        .profit-item:last-child {
            border-bottom: none;
        }
        
        .profit-header {
            background: #f8f9fa;
            font-weight: bold;
            border-bottom: 2px solid #dee2e6;
        }
        
        .profit-total {
            background: #e8f5e8;
            font-weight: bold;
            border-top: 2px solid #28a745;
        }
        
        .profit-percentage {
            font-size: 0.7rem;
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
        
        .profit-neutral {
            background: #e2e3e5;
            color: #383d41;
        }
        #product-cart{
            height: 63vh !important;
            overflow-y: scroll;
        }
        
        /* Secondary Unit Conversion */
        .secondary-unit-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
        }
        
        .secondary-unit-label {
            font-size: 0.7rem;
            font-weight: bold;
            color: #495057;
        }
        
        .secondary-unit-value {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        .convert-secondary-btn {
            font-size: 0.65rem;
            padding: 2px 6px;
        }
        
        /* Remove button style - smaller */
        .btn-remove-item {
            font-size: 0.6rem;
            padding: 1px 3px;
            min-width: 20px;
            height: 20px;
        }
        
        /* Compact Loyalty Points */
        .loyalty-compact {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            padding: 4px 8px;
            color: white;
        }
        
        .loyalty-compact .points-value {
            font-size: 0.8rem;
            font-weight: bold;
            color: #ffd700;
        }
        
        .loyalty-compact .points-label {
            font-size: 0.6rem;
            opacity: 0.9;
        }
        
        .loyalty-compact .btn-apply-points {
            background: #ffd700;
            color: #333;
            border: none;
            font-size: 0.6rem;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .loyalty-compact .btn-apply-points:hover {
            background: #ffed4e;
        }
        
        .loyalty-compact .btn-apply-points:disabled {
            background: #6c757d;
            color: white;
            opacity: 0.7;
        }
        
        /* Product details icon near product name */
        .product-name-with-details {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .product-details-icon {
            color: #6c757d;
            cursor: pointer;
            font-size: 0.7rem;
            opacity: 0.7;
        }
        
        .product-details-icon:hover {
            color: #0d6efd;
            opacity: 1;
        }
        
        /* Unit display in cart */
        .unit-display {
            font-size: 0.6rem;
            color: #6c757d;
            font-style: italic;
        }
    </style>
    
    <div class="d-flex gap-2 close-btn">
        <div>
            <button id="btnProfitAnalysis" class="btn btn-success btn-sm">
                <i class="fas fa-chart-line me-1"></i> 
            </button>
        </div>    
        <a class="btn btn-danger p-0" href="invoices.php" style="z-index:5; padding:3px 15px 0px !important;">Close</a>
    </div>

    <?php include('pos/models.php'); ?>

    <div class="compact-mode">
        <div class="">
            <div class="container-fluid">
               
                <!-- Auto-add notification (hidden, using toast instead) -->
                <div class="auto-add-notification" id="autoAddNotification"></div>
                <h3>POS</h3>
                
                <!-- Main Content Row -->
                <div class="main-row">
                    
                    <!-- Left Column: Customer + Add Products + Cart Items -->
                    <div class="left-column">
                        
                        <!-- Customer Section - First Row -->
                        <div class="card mb-1">
                            <div class="card-body py-2">
                                <h5 class="card-title mb-0" style="font-size: 0.85rem;">Customer</h5> 
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="d-flex align-items-center gap-1">
                                            <label class="mb-0 fw-semibold" style="font-size: 0.75rem;"> Price Type:</label>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="globalPriceToggle"
                                                    style="width: 2em; height: 1.1em;">
                                                <label class="form-check-label ms-1" for="globalPriceToggle"
                                                    style="font-size: 0.75rem;">
                                                    <span id="globalPriceLabel">Retail</span>
                                                </label>
                                            </div>
                                            <button id="btnApplyToAll" class="btn btn-sm btn-outline-secondary"
                                                style="font-size: 0.65rem; padding: 0.15rem 0.3rem;">Apply All</button>
                                        </div>
                                        
                                        <!-- GST Type Selector -->
                                        <div class="gst-type-selector">
                                            <select id="gstType" class="form-select form-select-sm">
                                                <option value="gst">GST Bill</option>
                                                <option value="non-gst">Non-GST Bill</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <button id="btnQuotationList" class="btn btn-outline-info btn-sm"
                                            title="Quotation List" style="padding: 0.2rem 0.4rem;">
                                            <i class="fas fa-file-alt"></i>
                                        </button>
                                        <button id="btnQuotation" class="btn btn-info btn-sm"
                                            title="Add Quotation" style="padding: 0.2rem 0.4rem;">
                                            <i class="fas fa-file-contract"></i>
                                        </button>
                                        <button id="btnHoldList" class="btn btn-outline-secondary btn-sm"
                                            title="Hold List" style="padding: 0.2rem 0.4rem;">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        <button id="btnHold" class="btn btn-warning btn-sm" title="Hold"
                                            style="padding: 0.2rem 0.4rem;">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Loyalty Points - Compact Single Row -->
                                <div class="loyalty-compact mb-2" id="loyaltyPointsSection" style="display: none;">
                                    <div class="flex-grow-1">
                                        <div class="points-label">LOYALTY POINTS</div>
                                        <div class="points-value" id="customerPointsDisplay">0</div>
                                    </div>
                                    <button class="btn-apply-points" id="btnShowPointsDetails">
                                        <i class="fas fa-star me-1"></i> Apply
                                    </button>
                                </div>
                                
                                <div class="row g-2 align-items-end">
                                    <!-- Name -->
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" style="font-size: 0.7rem;">Name <span class="text-danger">*</span></label>
                                        <input type="text" id="custName" class="form-control form-control-sm"
                                            value="<?=
                                                $editing_quotation ? htmlspecialchars($editing_quotation['customer_name']) :
                                                ($restored_invoice ? htmlspecialchars($restored_invoice['customer_name']) : 'Walk-in Customer')
                                                ?>" required>
                                    </div>

                                    <!-- Phone -->
                                    <div class="col-12 col-md-2">
                                        <label class="form-label" style="font-size: 0.7rem;">Phone</label>
                                        <select id="custPhone" class="form-control form-control-sm">
                                            <option value="">-- Select phone --</option>
                                            <?php
                                            $selected_phone = $editing_quotation ? $editing_quotation['customer_phone'] : '';
                                            $stmt = $pdo->prepare("SELECT id, name, phone, customer_type FROM customers WHERE phone != '' AND business_id = ? ORDER BY name");
                                            $stmt->execute([$business_id]);
                                            foreach ($stmt as $customer) {
                                                $phone = htmlspecialchars($customer['phone']);
                                                $name = htmlspecialchars($customer['name']);
                                                $type = htmlspecialchars($customer['customer_type']);
                                                $selected = ($phone == $selected_phone) ? 'selected' : '';
                                                echo "<option value='{$phone}' data-name='{$name}' data-type='{$type}' data-customer-id='{$customer['id']}' {$selected}>{$phone} - {$name}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <!-- Address -->
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" style="font-size: 0.7rem;">Address (Optional)</label>
                                        <textarea id="custAddress" class="form-control form-control-sm address-field"
                                            placeholder="Enter customer address" rows="1"><?=
                                                $editing_quotation ? htmlspecialchars($editing_quotation['customer_address'] ?? '') :
                                                ($restored_invoice ? htmlspecialchars($restored_invoice['customer_address'] ?? '') : '')
                                                ?></textarea>
                                    </div>

                                    <!-- GSTIN -->
                                    <div class="col-12 col-md-2">
                                        <label class="form-label" style="font-size: 0.7rem;">GSTIN (Optional)</label>
                                        <input type="text" id="custGSTIN" class="form-control form-control-sm"
                                            placeholder="Enter GSTIN" value="<?=
                                                $editing_quotation ? htmlspecialchars($editing_quotation['customer_gstin'] ?? '') :
                                                ($restored_invoice ? htmlspecialchars($restored_invoice['customer_gstin'] ?? '') : '')
                                                ?>">
                                    </div>

                                    <!-- Referral -->
                                    <div class="col-12 col-md-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0" style="font-size: 0.7rem;">Referral</label>
                                        </div>
                                        <select id="referralSelect" class="form-control form-control-sm">
                                            <option value="">-- No referral --</option>
                                            <?php
                                            $referral_stmt = $pdo->prepare("
                                                SELECT id, referral_code, full_name, phone
                                                FROM referral_person
                                                WHERE business_id = ? AND is_active = 1
                                                ORDER BY full_name
                                            ");
                                            $referral_stmt->execute([$business_id]);
                                            while ($referral = $referral_stmt->fetch()) {
                                                echo "<option value='{$referral['id']}'>
                                                    {$referral['full_name']} ({$referral['referral_code']})
                                                </option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Referral Commission Info -->
                                <div class="row mt-1">
                                    <div class="col-12">
                                        <div id="noCommissionInfo" class="alert alert-light p-1 mb-0">
                                            <small id="commissionDetails" style="font-size: 0.65rem;">No referral selected or no eligible products in cart</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Products Section - Updated with more inputs -->
                        <div class="card mb-1">
                            <div class="card-body py-2">
                                <h5 class="card-title mb-2" style="font-size: 0.85rem;">Add Products</h5>
                                
                                <div class="row g-2 align-items-end">
                                    <!-- Search Product with Category/Subcategory -->
                                    <div class="col-12 col-xl-4 col-lg-4">
                                        <label class="form-label" style="font-size: 0.7rem;">Search Product</label>
                                        <div class="position-relative">
                                            <select id="productSelect" class="form-control form-control-sm">
                                                <option value="">-- Search product --</option>
                                                <?php
                                                // Updated query to include category and subcategory
                                                $prodSql = "SELECT p.id, p.product_name, p.product_code, p.barcode,
                                                         p.retail_price, p.wholesale_price, p.stock_price,
                                                         p.hsn_code, p.unit_of_measure, p.mrp,
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
                                                $prodRes = $prodStmt->fetchAll();

                                                $jsProducts = [];
                                                $barcodeMap = [];

                                                foreach ($prodRes as $p) {
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

                                                    $shop_badge = $shop_stock > 0 ?
                                                        ($shop_stock < 10 ? "<span class='badge low-stock'>S:$shop_stock</span>" : "<span class='badge shop-stock'>S:$shop_stock</span>") :
                                                        "<span class='badge out-of-stock'>S:0</span>";

                                                    $warehouse_badge = $warehouse_stock > 0 ?
                                                        "<span class='badge warehouse-stock'>W:$warehouse_stock</span>" :
                                                        "<span class='badge out-of-stock'>W:0</span>";

                                                    $referral_badge = $referral_enabled ?
                                                        "<span class='badge referral-badge'>R:" . ($referral_type == 'percentage' ? $referral_value . '%' : '₹' . $referral_value) . "</span>" :
                                                        "";

                                                    $secondary_badge = $secondary_unit ?
                                                        "<span class='badge bg-dark'>" . $secondary_unit . "</span>" :
                                                        "";

                                                    // MRP badge
                                                    $mrp_badge = $mrp > 0 ? "<span class='badge bg-danger'>MRP: ₹" . $mrp . "</span>" : "";
                                                    
                                                    // Category info
                                                    $category_info = $category ? 
                                                        "<small class='text-muted'>[" . ($subcategory ? "$category → $subcategory" : $category) . "]</small>" : 
                                                        "";

                                                    echo "<option value='{$pid}'
                                                  data-retail='{$retail}'
                                                  data-wholesale='{$wholesale}'
                                                  data-cost='{$cost}'
                                                  data-mrp='{$mrp}'
                                                  data-code='{$code}'
                                                  data-barcode='{$barcode}'
                                                  data-shop-stock='{$shop_stock}'
                                                  data-warehouse-stock='{$warehouse_stock}'
                                                  data-hsn='{$hsn}'
                                                  data-unit='{$unit_of_measure}'
                                                  data-cgst='{$cgst}'
                                                  data-sgst='{$sgst}'
                                                  data-igst='{$igst}'
                                                  data-totalgst='{$total_gst}'
                                                  data-category='{$category}'
                                                  data-subcategory='{$subcategory}'
                                                  data-discount-type='{$discount_type}'
                                                  data-discount-value='{$discount_value}'
                                                  data-retail-price-type='{$retail_price_type}'
                                                  data-retail-price-value='{$retail_price_value}'
                                                  data-wholesale-price-type='{$wholesale_price_type}'
                                                  data-wholesale-price-value='{$wholesale_price_value}'
                                                  data-referral-enabled='{$referral_enabled}'
                                                  data-referral-type='{$referral_type}'
                                                  data-referral-value='{$referral_value}'
                                                  data-secondary-unit='{$secondary_unit}'
                                                  data-sec-unit-conversion='{$sec_unit_conversion}'
                                                  data-sec-unit-price-type='{$sec_unit_price_type}'
                                                  data-sec-unit-extra-charge='{$sec_unit_extra_charge}'>
                                                  {$name} {$category_info} {$shop_badge} {$warehouse_badge} {$referral_badge} {$secondary_badge} {$mrp_badge}" .
                                                        ($total_gst > 0 ? " [GST: {$total_gst}%]" : "") . "
                                                  </option>";

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
                                            </select>
                                        </div>
                                    </div>

                                    <!-- MRP & Discount Section -->
                                    <div class="col-12 col-xl-4 col-lg-4">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label" style="font-size: 0.7rem;">MRP</label>
                                                <input type="number" id="productMRP" class="form-control form-control-sm" 
                                                       placeholder="MRP" step="0.01" readonly>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label" style="font-size: 0.7rem;">Discount</label>
                                                <input type="number" id="productDiscountValue" class="form-control form-control-sm" 
                                                       placeholder="Value" step="0.01">
                                                <small class="text-info" style="font-size: 0.6rem; display: none;" id="autoDiscountNote"></small>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label" style="font-size: 0.7rem;">Type</label>
                                                <select id="productDiscountType" class="form-select form-select-sm">
                                                    <option value="percent">%</option>
                                                    <option value="flat">₹</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-6">
                                                <label class="form-label" style="font-size: 0.7rem;">Retail Price</label>
                                                <input type="number" id="productRetailPrice" class="form-control form-control-sm" 
                                                       placeholder="Retail" step="0.01">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label" style="font-size: 0.7rem;">Wholesale Price</label>
                                                <input type="number" id="productWholesalePrice" class="form-control form-control-sm" 
                                                       placeholder="Wholesale" step="0.01">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quantity & Unit Section -->
                                    <div class="col-12 col-xl-4 col-lg-4">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label" style="font-size: 0.7rem;">Quantity</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="addProductQty" class="form-control form-control-sm"
                                                        value="1" min="0.01" step="0.01" placeholder="Qty">
                                                    <span class="input-group-text" id="qtyUnitDisplay">PCS</span>
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <label class="form-label" style="font-size: 0.7rem;">Unit Conversion</label>
                                                <div class="input-group input-group-sm">
                                                    <button id="btnConvertUnit" class="btn btn-outline-secondary btn-sm w-100"
                                                        style="padding: 0.25rem;" disabled>
                                                        <i class="fas fa-exchange-alt me-1"></i> Convert
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Add Button -->
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <button id="btnAdd" class="btn btn-primary btn-sm w-100"
                                                    style="padding: 0.25rem;">
                                                    <i class="fas fa-plus me-1"></i> Add to Cart
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Secondary Unit Conversion Section (Hidden by default) -->
                                <div id="secondaryUnitConvertSection" class="row g-2 align-items-end mt-2" style="display: none;">
                                    <!-- Same as before, keep existing secondary unit section -->
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" style="font-size: 0.7rem;">Convert to Secondary Unit</label>
                                        <select id="secondaryUnitSelect" class="form-control form-control-sm">
                                            <option value="primary">Primary Unit</option>
                                            <!-- Secondary unit options will be loaded dynamically -->
                                        </select>
                                    </div>
                                    
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" style="font-size: 0.7rem;">Secondary Quantity</label>
                                        <input type="number" id="secondaryUnitQtyInput" class="form-control form-control-sm"
                                            value="1" min="0.01" step="0.01" placeholder="Qty">
                                    </div>
                                    
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" style="font-size: 0.7rem;">Price Info</label>
                                        <div class="alert alert-light p-1 mb-0">
                                            <small id="secondaryUnitPriceInfo">Select secondary unit</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Items -->
                        <div class="card flex-grow-1" id="product-cart">
                            <div class="card-body p-2 d-flex flex-column h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h5 class="card-title mb-0" style="font-size: 0.85rem;">Cart Items</h5>
                                    <div class="d-flex gap-1">
                                        <button id="btnClearCart" class="btn btn-outline-danger btn-sm"
                                            style="padding: 0.2rem 0.4rem;">
                                            <i class="fas fa-trash"></i> Clear
                                        </button>
                                    </div>
                                </div>
                                <div class="cart-container">
                                    <table class="table table-bordered table-hover table-cart" id="cartTable">
                                        <thead>
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="25%">Product</th>
                                                <th width="12%">Price Type</th>
                                                <th width="10%">Price</th>
                                                <th width="10%">Qty</th>
                                                <th width="15%">Discount</th>
                                                <th width="10%">Total</th>
                                                <th width="5%">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cartBody">
                                            <tr id="emptyRow">
                                                <td colspan="8" class="text-center text-muted py-2">No items in cart
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Billing Summary -->
                    <div class="right-column">
                        <div class="billing-summary-card">
                            <h5 class="mb-1" style="font-size: 0.85rem;">Billing Summary</h5>

                            <div class="billing-summary-content" style="height:200px !important;">
                                <div class="billing-summary-row">
                                    <span class="billing-summary-label">Subtotal:</span>
                                    <span class="billing-summary-value" id="subtotal">₹ 0.00</span>
                                </div>

                                <div class="billing-summary-row">
                                    <span class="billing-summary-label">Item Disc:</span>
                                    <span class="billing-summary-value text-danger" id="totalItemDiscount">- ₹
                                        0.00</span>
                                </div>

                                <!-- Overall Discount -->
                                <div class="mb-1">
                                    <label class="form-label" style="font-size: 0.7rem;">Overall Discount</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="overallDiscountValue"
                                            class="form-control form-control-sm" value="0" min="0" step="0.01">
                                        <select id="overallDiscountType" class="form-select form-select-sm"
                                            style="width: 50px;">
                                            <option value="percent">%</option>
                                            <option value="flat">₹</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="billing-summary-row">
                                    <span class="billing-summary-label">Overall Disc:</span>
                                    <span class="billing-summary-value text-danger" id="overallDiscount">- ₹ 0.00</span>
                                </div>

                                <!-- Loyalty Points Discount -->
                                <div class="billing-summary-row" id="loyaltyPointsDiscountRow" style="display: none;">
                                    <span class="billing-summary-label">Points Discount:</span>
                                    <span class="billing-summary-value text-info" id="loyaltyPointsDiscount">- ₹ 0.00</span>
                                </div>

                                <!-- Referral Commission -->
                                <div class="billing-summary-row" id="referralCommissionRow" style="display: none;">
                                    <span class="billing-summary-label">Referral Comm:</span>
                                    <span class="billing-summary-value text-warning" id="referralCommission">₹
                                        0.00</span>
                                </div>

                                <!-- GST Breakdown (shown/hidden based on GST type) -->
                                <div id="gstSummary" class="mt-1" style="display: none;">
                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">Taxable Amt:</span>
                                        <span class="billing-summary-value" id="totalTaxable">₹ 0.00</span>
                                    </div>
                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">CGST:</span>
                                        <span class="billing-summary-value" id="totalCGST">₹ 0.00</span>
                                    </div>
                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">SGST:</span>
                                        <span class="billing-summary-value" id="totalSGST">₹ 0.00</span>
                                    </div>
                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">IGST:</span>
                                        <span class="billing-summary-value" id="totalIGST">₹ 0.00</span>
                                    </div>
                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">Total GST:</span>
                                        <span class="billing-summary-value text-purple" id="totalGST">₹ 0.00</span>
                                    </div>
                                </div>

                                <div class="billing-summary-row billing-total-row">
                                    <span class="billing-summary-label">Grand Total:</span>
                                    <span class="billing-summary-value" id="grandTotal">₹ 0.00</span>
                                </div>
                            </div>

                            <!-- Multiple Payment Methods - Compact -->
                            <div class="payment-method-container mt-1">
                                <label class="form-label fw-semibold" style="font-size: 0.7rem;">Payment Methods</label>

                                <div class="payment-checkbox-group">
                                    <div class="payment-checkbox active" data-method="cash">
                                        <input type="checkbox" id="cashCheckbox" checked>
                                        <label for="cashCheckbox" style="font-size: 0.65rem;">
                                            <i class="fas fa-money-bill-wave me-1"></i> Cash
                                        </label>
                                    </div>
                                    <div class="payment-checkbox" data-method="upi">
                                        <input type="checkbox" id="upiCheckbox">
                                        <label for="upiCheckbox" style="font-size: 0.65rem;">
                                            <i class="fas fa-qrcode me-1"></i> UPI
                                        </label>
                                    </div>
                                    <div class="payment-checkbox" data-method="bank">
                                        <input type="checkbox" id="bankCheckbox">
                                        <label for="bankCheckbox" style="font-size: 0.65rem;">
                                            <i class="fas fa-university me-1"></i> Bank
                                        </label>
                                    </div>
                                    <div class="payment-checkbox" data-method="cheque">
                                        <input type="checkbox" id="chequeCheckbox">
                                        <label for="chequeCheckbox" style="font-size: 0.65rem;">
                                            <i class="fas fa-money-check me-1"></i> Cheque
                                        </label>
                                    </div>
                                </div>

                                <!-- Payment Inputs -->
                                <div class="payment-input-container active" id="cashInputContainer">
                                    <div class="payment-input-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0" style="font-size: 0.65rem;">Cash
                                                Amount</label>
                                            <span class="badge bg-success payment-percentage"
                                                id="cashPercentage">0%</span>
                                        </div>
                                        <input type="number" id="cashAmount"
                                            class="form-control form-control-sm payment-amount-input" value="0" min="0"
                                            step="0.01" placeholder="Cash amount">
                                    </div>
                                </div>

                                <div class="payment-input-container" id="upiInputContainer">
                                    <div class="payment-input-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0" style="font-size: 0.65rem;">UPI
                                                Amount</label>
                                            <span class="badge bg-info payment-percentage" id="upiPercentage">0%</span>
                                        </div>
                                        <input type="number" id="upiAmount"
                                            class="form-control form-control-sm payment-amount-input" value="0" min="0"
                                            step="0.01" placeholder="UPI amount">
                                        <input type="text" id="upiReference" class="form-control form-control-sm mt-1"
                                            placeholder="UPI Reference (Optional)">
                                    </div>
                                </div>

                                <div class="payment-input-container" id="bankInputContainer">
                                    <div class="payment-input-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0" style="font-size: 0.65rem;">Bank
                                                Amount</label>
                                            <span class="badge bg-warning payment-percentage"
                                                id="bankPercentage">0%</span>
                                        </div>
                                        <input type="number" id="bankAmount"
                                            class="form-control form-control-sm payment-amount-input" value="0" min="0"
                                            step="0.01" placeholder="Bank amount">
                                        <input type="text" id="bankReference" class="form-control form-control-sm mt-1"
                                            placeholder="Bank Ref (Optional)">
                                    </div>
                                </div>

                                <div class="payment-input-container" id="chequeInputContainer">
                                    <div class="payment-input-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0" style="font-size: 0.65rem;">Cheque
                                                Amount</label>
                                            <span class="badge bg-secondary payment-percentage"
                                                id="chequePercentage">0%</span>
                                        </div>
                                        <input type="number" id="chequeAmount"
                                            class="form-control form-control-sm payment-amount-input" value="0" min="0"
                                            step="0.01" placeholder="Cheque amount">
                                        <input type="text" id="chequeNumber" class="form-control form-control-sm mt-1"
                                            placeholder="Cheque No. (Optional)">
                                    </div>
                                </div>

                                <!-- Payment Summary -->
                                <div class="mt-1 pt-1 border-top">
                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">Total Paid:</span>
                                        <span class="billing-summary-value" id="totalPaid">₹ 0.00</span>
                                    </div>

                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">Change:</span>
                                        <span class="billing-summary-value text-success" id="changeDue">₹ 0.00</span>
                                    </div>

                                    <div class="billing-summary-row">
                                        <span class="billing-summary-label">Pending:</span>
                                        <span class="billing-summary-value text-warning" id="pendingAmount">₹
                                            0.00</span>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-1 p-1" id="paymentWarning"
                                    style="display: none; font-size: 0.65rem;">
                                    <small><i class="fas fa-exclamation-triangle me-1"></i> Payment amount
                                        mismatch!</small>
                                </div>
                            </div>

                            <div class="d-grid gap-1 mt-2">
                                <button id="btnGenerateBill" class="btn btn-primary btn-sm py-1"
                                    style="font-size: 0.75rem;">
                                    <i class="fas fa-file-invoice me-1"></i> Generate Invoice
                                </button>
                                <button id="btnPrintBill" class="btn btn-success btn-sm py-1"
                                    style="font-size: 0.75rem;">
                                    <i class="fas fa-print me-1"></i> Print Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include('includes/scripts.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php include('pos/script.php'); ?>
    
</body>
</html>