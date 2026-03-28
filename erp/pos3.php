<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AS Electricals - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
        }

        body {
            background-color: #f5f5f5;
            height: 100vh;
        }

        /* Top Header */
        .top-header {
            background: white;
            padding: 8px 12px;
            border-bottom: 2px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .company-name i {
            color: #2196F3;
            font-size: 20px;
        }

        .header-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .barcode-input {
            padding: 6px 12px;
            border: 1px solid #2196F3;
            border-radius: 4px;
            font-size: 13px;
            width: 160px;
            background: #fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="%232196F3" viewBox="0 0 16 16"><path d="M1.5 1a.5.5 0 0 0-.5.5v3a.5.5 0 0 1-1 0v-3A1.5 1.5 0 0 1 1.5 0h3a.5.5 0 0 1 0 1h-3zM11 .5a.5.5 0 0 1 .5-.5h3A1.5 1.5 0 0 1 16 1.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 1-.5-.5zM.5 11a.5.5 0 0 1 .5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 1 0 1h-3A1.5 1.5 0 0 1 0 14.5v-3a.5.5 0 0 1 .5-.5zm15 0a.5.5 0 0 1 .5.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 1 .5-.5z"/></svg>') no-repeat right 8px center;
            background-size: 14px;
            padding-right: 30px;
        }

        .barcode-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
            border-color: #2196F3;
        }

        .site-select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 13px;
            min-width: 160px;
        }

        .referral-badge {
            padding: 6px 12px;
            background: #2196F3;
            color: white;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .btn-sm-custom {
            padding: 6px 12px;
            font-size: 13px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        .btn-warning-sm {
            background: #ffc107;
            color: #333;
        }

        .btn-info-sm {
            background: #17a2b8;
            color: white;
        }

        .btn-danger-sm {
            background: #dc3545;
            color: white;
        }

        /* Settings Bar */
        .settings-bar {
            background: #f9f9f9;
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .settings-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .setting-btn {
            padding: 6px 15px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .setting-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .customer-select {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 13px;
            min-width: 160px;
        }

        .new-customer-btn {
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cart-info {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cart-count-badge {
            background: #2196F3;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
        }

        /* Main Container */
        .pos-container {
            display: flex;
            height: calc(100vh - 120px);
        }

        /* Categories Section */
        .categories-section {
            width: 20%;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
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
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-item:hover {
            background: #f0f0f0;
        }

        .category-item.active {
            background: #e3f2fd;
            font-weight: bold;
            border-left: 4px solid #2196F3;
        }

        .category-count {
            background: #2196F3;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
        }

        /* Products Section */
        .products-section {
            width: 45%;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }

        .products-header {
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            background: #f9f9f9;
        }

        .current-category {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        .search-box {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
            font-size: 13px;
        }

        .products-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .product-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .product-info {
            display: flex !important;
            justify-content: space-between !important;
        }

        .product-info h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .product-code {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }

        .product-price {
            font-weight: bold;
            color: #4CAF50;
            font-size: 16px;
            margin-bottom: 3px;
        }

        .wholesale-price {
            font-size: 11px;
            color: #666;
        }

        .product-stock {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }

        .stock-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 3px;
        }

        .in-stock {
            background: #4CAF50;
            color: white;
        }

        .low-stock {
            background: #FF9800;
            color: white;
        }

        .out-of-stock {
            background: #f44336;
            color: white;
        }

        /* Billing Section */
        .billing-section {
            width: 35%;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .billing-header {
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            background: #f9f9f9;
        }

        .cart-title {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        .cart-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-btn {
            padding: 6px 12px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .cart-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 4px;
            margin-bottom: 6px;
            border: 1px solid #e0e0e0;
        }

        .cart-item-info h4 {
            font-size: 13px;
            margin-bottom: 2px;
            font-weight: 600;
        }

        .cart-item-code {
            font-size: 10px;
            color: #666;
            margin-bottom: 3px;
        }

        .cart-item-price {
            color: #4CAF50;
            font-size: 13px;
            font-weight: bold;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .qty-btn {
            width: 24px;
            height: 24px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .qty-display {
            min-width: 35px;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
        }

        .remove-btn {
            width: 24px;
            height: 24px;
            border: 1px solid #f44336;
            background: #ffebee;
            color: #f44336;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .empty-cart {
            text-align: center;
            padding: 30px 15px;
            color: #666;
            font-size: 14px;
        }

        .summary {
            padding: 15px;
            border-top: 2px solid #ddd;
            background: #f9f9f9;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            color: #333;
        }

        .total-row {
            font-size: 16px;
            font-weight: bold;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            color: #2196F3;
        }

        .site-info {
            font-size: 11px;
            color: #666;
            margin-bottom: 8px;
            text-align: right;
        }

        .action-buttons {
            margin-top: 15px;
        }

        .checkout-btn {
            width: 100%;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            background: #45a049;
        }

        /* Overall Discount Input */
        .overall-discount-input-container {
            margin: 12px 0;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .overall-discount-input {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .apply-discount-btn {
            padding: 6px 12px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
        }

        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        /* Modal Styles */
        .quantity-modal,
        .customer-modal,
        .referral-modal {
            max-height: 85vh;
            overflow-y: auto;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }

        .quantity-modal {
            min-width: 400px;
        }

        .customer-modal {
            min-width: 500px;
            max-width: 550px;
        }

        .referral-modal {
            min-width: 300px;
        }

        .modal-title {
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .modal-product-info {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }

        .modal-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .modal-input-group {
            flex: 1;
        }

        .modal-input-group label {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        .modal-input {
            width: 100%;
            padding: 8px 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
        }

        .modal-select {
            width: 100%;
            padding: 8px 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            background: white;
        }

        .discount-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .discount-type-select {
            width: 100px;
            padding: 8px 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            background: white;
        }

        .discount-value-input {
            flex: 1;
            padding: 8px 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
        }

        .price-preview {
            background: #e8f5e9;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #c8e6c9;
            font-size: 13px;
        }

        .price-preview-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .price-preview-total {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #c8e6c9;
            font-weight: bold;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .modal-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .cancel-btn {
            background: #f5f5f5;
            color: #333;
        }

        .cancel-btn:hover {
            background: #e0e0e0;
        }

        .add-btn {
            background: #4CAF50;
            color: white;
        }

        .add-btn:hover {
            background: #45a049;
        }

        /* Payment Modal Specific */
        .customer-info-section {
            margin-bottom: 15px;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .customer-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .customer-info-title {
            font-size: 13px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .toggle-edit-btn {
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 3px 8px;
            font-size: 11px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .customer-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .form-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
        }

        .form-input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
        }

        .autofill-btn {
            background: #FF9800;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 3px 8px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 5px;
        }

        .scrollable-content {
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 5px;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        /* SweetAlert Customization */
        .swal2-popup {
            font-size: 12px !important;
            padding: 15px !important;
        }

        .swal2-title {
            font-size: 16px !important;
        }

        .swal2-html-container {
            font-size: 13px !important;
        }

        .swal2-confirm {
            font-size: 13px !important;
            padding: 8px 16px !important;
        }

        .swal2-cancel {
            font-size: 13px !important;
            padding: 8px 16px !important;
        }

        /* Table styles */
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            font-size: 12px;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 8px;
            font-weight: 600;
        }

        .table td {
            padding: 6px 8px;
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }

        .btn-sm {
            padding: 3px 6px;
            font-size: 11px;
            margin: 1px;
        }
    </style>
</head>

<body>
    <!-- Toast Container (will be replaced by SweetAlert) -->
    <div id="toastContainer" style="display: none;"></div>

    <!-- Top Header -->
    <div class="top-header">
        <div class="company-name">
            <i class="fas fa-bolt"></i>
            AS ELECTRICALS POS
        </div>
        <div class="header-controls">
            <input type="text" class="barcode-input" id="barcode-input" placeholder="Scan barcode..."
                autocomplete="off">

            <select class="site-select" id="site-select">
                <option value="">None</option>

            </select>
            <div class="referral-badge" id="current-referral">
                <i class="fas fa-user-friends"></i>
                <span id="referral-display">Referral: None</span>
            </div>
            <button class="btn-sm-custom btn-warning-sm" id="hold-btn">
                <i class="fas fa-pause"></i> Hold
            </button>
            <button class="btn-sm-custom btn-info-sm" id="quotation-btn">
                <i class="fas fa-file-alt"></i> Quotation
            </button>
            <button class="btn-sm-custom btn-danger-sm" onclick="window.location.href='invoices.php'">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <!-- Settings Bar -->
    <div class="settings-bar">
        <div class="settings-group">
            <button class="setting-btn active" id="gst-btn">
                <i class="fas fa-file-invoice"></i> GST Bill
            </button>
            <button class="setting-btn" id="non-gst-btn">
                <i class="fas fa-file"></i> Non-GST Bill
            </button>
            <select class="customer-select" id="customer-type">
                <option value="walk-in">Walk-in Customer</option>
                <!-- Customers will be loaded here -->
            </select>
            <button class="new-customer-btn" id="new-customer-btn">
                <i class="fas fa-user-plus"></i> New
            </button>
            <button class="btn-sm-custom btn-secondary" id="holds-list-btn">
                <i class="fas fa-list"></i> Hold
            </button>
            <button class="btn-sm-custom btn-secondary" id="quotations-list-btn">
                <i class="fas fa-file-invoice"></i> Quotation
            </button>
        </div>
        <div class="settings-group">
            <div class="cart-info">
                <i class="fas fa-shopping-cart"></i>
                Items: <span class="cart-count-badge" id="item-count">0</span>
                <span id="total-items-display">0 items</span>
            </div>
        </div>
    </div>

    <!-- Main POS Container -->
    <div class="pos-container">

        <!-- Categories Section -->
        <section class="categories-section">
            <div class="section-title">
                <i class="fas fa-list"></i> Categories
            </div>
            <div class="categories-list" id="categories-list">
                <!-- Categories with subcategories will be loaded here -->
            </div>
        </section>

        <!-- Products -->
        <section class="products-section">
            <div class="products-header">
                <div class="current-category" id="current-category">All Products</div>
                <div style="display: flex; gap: 5px;">
                    <input type="text" class="search-box" id="search-box" placeholder="Search products...">
                    <button class="btn-sm-custom" id="manual-product-btn" style="background: #FF9800; color: white;">
                        <i class="fas fa-plus-circle"></i> Manual
                    </button>
                </div>
            </div>
            <div class="products-list" id="products-list">
                <!-- Products will be loaded here -->
            </div>
        </section>

        <!-- Billing -->
        <section class="billing-section">
            <div class="billing-header">
                <div class="cart-title">Bill Summary</div>
                <div class="cart-actions">
                    <button class="clear-btn" id="clear-cart">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </div>
            <div class="cart-items" id="cart-items">
                <div class="empty-cart">
                    <p>No items in cart</p>
                    <p style="font-size: 12px; margin-top: 5px; color: #999;">Tap products to add</p>
                </div>
            </div>
            <div class="summary">
                <div class="overall-discount-input-container">
                    <input type="number" class="overall-discount-input" id="overall-discount-input"
                        placeholder="Enter overall discount" min="0" step="0.01">
                    <button class="apply-discount-btn" id="apply-overall-discount">
                        <i class="fas fa-percentage"></i> Apply
                    </button>
                </div>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">₹0.00</span>
                </div>
                <div class="summary-row">
                    <span>Item Discount:</span>
                    <span id="item-discount">-₹0.00</span>
                </div>
                <div class="summary-row">
                    <span>Overall Discount:</span>
                    <span id="overall-discount-display">-₹0.00</span>
                </div>
                <div class="summary-row" id="gst-row" style="display: none;">
                    <span>GST:</span>
                    <span id="gst-amount">₹0.00</span>
                </div>
                <div class="summary-row total-row">
                    <span>Total Amount:</span>
                    <span id="total">₹0.00</span>
                </div>
                <div class="site-info">
                    Site: <span id="current-site">Main Store</span> |
                    Type: <span id="bill-type-display">GST Bill</span>
                </div>
                <div class="action-buttons">
                    <button class="checkout-btn" id="checkout-btn">
                        <i class="fas fa-credit-card"></i> Checkout (₹<span id="checkout-amount">0.00</span>)
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- Quantity Modal -->
    <div class="modal-overlay" id="quantity-modal">
        <div class="quantity-modal">
            <div class="modal-title" id="modal-product-name">Add to Cart</div>
            <div class="modal-product-info" id="modal-product-info"></div>

            <div class="modal-row">
                <div class="modal-input-group">
                    <label>Quantity</label>
                    <input type="number" class="modal-input" id="quantity-input" value="1" min="0.01" max="9999"
                        step="0.01">
                </div>
                <div class="modal-input-group">
                    <label>Unit</label>
                    <select class="modal-select" id="unit-select">
                        <option value="PCS">PCS</option>
                    </select>
                </div>
            </div>

            <div class="modal-row">
                <div class="modal-input-group">
                    <label>Price Type</label>
                    <select class="modal-select" id="price-type-select">
                        <option value="retail">Retail</option>
                        <option value="wholesale">Wholesale</option>
                    </select>
                </div>
                <div class="modal-input-group">
                    <label>Selling Price</label>
                    <input type="number" class="modal-input" id="selling-price-input" value="0" min="0" step="0.01">
                </div>
            </div>

            <div class="discount-row">
                <div class="modal-input-group">
                    <label>Discount Type</label>
                    <select class="discount-type-select" id="discount-type-select">
                        <option value="percentage">%</option>
                        <option value="fixed">₹ Fixed</option>
                    </select>
                </div>
                <div class="modal-input-group">
                    <label>Discount Value</label>
                    <input type="number" class="discount-value-input" id="discount-value-input" value="0" min="0"
                        step="0.01">
                </div>
            </div>

            <div class="price-preview" id="price-preview">
                <div class="price-preview-row">
                    <span>Base Price:</span>
                    <span id="preview-base-price">₹0.00</span>
                </div>
                <div class="price-preview-row">
                    <span>Discount:</span>
                    <span id="preview-discount">-₹0.00</span>
                </div>
                <div class="price-preview-total">
                    <span>Final Price:</span>
                    <span id="preview-final-price">₹0.00</span>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-btn">Cancel</button>
                <button class="modal-btn add-btn" id="add-to-cart-btn">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        </div>
    </div>

    <!-- Customer Modal -->
    <div class="modal-overlay" id="customer-modal">
        <div class="customer-modal">
            <div class="modal-title">
                <i class="fas fa-user-plus"></i> Add New Customer
            </div>

            <div class="scrollable-content">
                <div class="modal-section">
                    <div class="modal-section-title">Basic Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-input" id="customer-name-input"
                                placeholder="Enter customer name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" class="form-input" id="customer-phone-input"
                                placeholder="Enter phone number">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" id="customer-email-input" placeholder="Enter email">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Customer Type</label>
                            <select class="form-select" id="customer-type-input">
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Address Information</div>
                    <div class="form-group full-width">
                        <label class="form-label">Complete Address</label>
                        <textarea class="form-input" id="customer-address-input" rows="2"
                            placeholder="Enter complete address"></textarea>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Tax Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">GSTIN Number</label>
                            <input type="text" class="form-input" id="customer-gstin-input" placeholder="Enter GSTIN">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Credit Limit (₹)</label>
                            <input type="number" class="form-input" id="customer-credit-input" value="0" min="0"
                                step="0.01">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-customer-btn">Cancel</button>
                <button class="modal-btn add-btn" id="save-customer-btn">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal-overlay" id="payment-modal">
        <div class="quantity-modal" style="min-width: 500px;">
            <div class="modal-title">
                <i class="fas fa-credit-card"></i> Payment Details
                <button class="autofill-btn" id="autofill-payment-btn" style="float: right;">
                    <i class="fas fa-magic"></i> Autofill
                </button>
            </div>

            <div class="scrollable-content">
                <!-- Customer Information Section -->
                <div class="customer-info-section">
                    <div class="customer-info-header">
                        <div class="customer-info-title">
                            <i class="fas fa-user me-2"></i>Customer Information
                        </div>
                        <button class="toggle-edit-btn" id="toggle-edit-btn">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    <div class="customer-details-grid">
                        <div>
                            <label style="font-size: 11px; color: #666;">Name *</label>
                            <input type="text" class="form-input" id="payment-customer-name" placeholder="Enter name"
                                readonly>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: #666;">Contact *</label>
                            <input type="tel" class="form-input" id="payment-customer-contact" placeholder="Enter phone"
                                readonly>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: #666;">GSTIN</label>
                            <input type="text" class="form-input" id="payment-customer-gstin" placeholder="Enter GSTIN"
                                readonly>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: #666;">Email</label>
                            <input type="email" class="form-input" id="payment-customer-email" placeholder="Enter email"
                                readonly>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label style="font-size: 11px; color: #666;">Address</label>
                            <textarea class="form-input" id="payment-customer-address" rows="1"
                                placeholder="Enter address" readonly></textarea>
                        </div>
                    </div>
                </div>

                <!-- Site and Engineer Selection Section -->
                <!-- Site and Engineer Selection Section (Optional) -->
                <div class="modal-section" style="margin: 20px 0; border-top: 1px solid #eee; padding-top: 15px;">
                    <div class="modal-section-title">Site & Engineer Details (Optional)</div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Select Site</label>
                            <select class="form-input select2-site-payment" id="payment-site-select"
                                style="width: 100%;">
                                <option value="">-- Select Site (Optional) --</option>
                                <!-- Sites will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Select Engineer</label>
                            <select class="form-input select2-engineer-payment" id="payment-engineer-select"
                                style="width: 100%;">
                                <option value="">-- Select Engineer (Optional) --</option>
                                <!-- Engineers will be loaded dynamically -->
                            </select>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Site and engineer information is optional
                    </div>
                </div>

                <div class="modal-product-info" id="payment-summary">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Total Amount:</span>
                        <span style="font-size: 16px; font-weight: bold; color: #2196F3;">₹<span
                                id="payment-total">0.00</span></span>
                    </div>
                </div>

                <div class="modal-row">
                    <div class="modal-input-group">
                        <label>Cash (₹)</label>
                        <input type="number" class="modal-input" id="cash-payment" value="0" min="0" step="0.01">
                    </div>
                    <div class="modal-input-group">
                        <label>UPI (₹)</label>
                        <input type="number" class="modal-input" id="upi-payment" value="0" min="0" step="0.01">
                    </div>
                </div>

                <div class="modal-row">
                    <div class="modal-input-group">
                        <label>Card/Bank (₹)</label>
                        <input type="number" class="modal-input" id="bank-payment" value="0" min="0" step="0.01">
                    </div>
                    <div class="modal-input-group">
                        <label>Credit (₹)</label>
                        <input type="number" class="modal-input" id="credit-payment" value="0" min="0" step="0.01">
                    </div>
                </div>

                <div class="modal-row">
                    <div class="modal-input-group">
                        <label>Total Paid</label>
                        <input type="number" class="modal-input" id="total-paid" value="0" readonly
                            style="background: #f5f5f5;">
                    </div>
                    <div class="modal-input-group">
                        <label>Change</label>
                        <input type="number" class="modal-input" id="change-amount" value="0" readonly
                            style="background: #f5f5f5;">
                    </div>
                </div>

                <!-- Payment Reference Notes -->
                <div class="modal-section" style="margin: 10px 0;">
                    <div class="modal-section-title">Payment Reference</div>
                    <div class="modal-row">
                        <div class="modal-input-group">
                            <label>UPI Ref</label>
                            <input type="text" class="form-input" id="payment-upi-reference"
                                placeholder="UPI reference">
                        </div>
                        <div class="modal-input-group">
                            <label>Bank Ref</label>
                            <input type="text" class="form-input" id="payment-bank-reference"
                                placeholder="Bank reference">
                        </div>
                    </div>
                    <div class="modal-input-group" style="margin-top: 5px;">
                        <label>Cheque Number</label>
                        <input type="text" class="form-input" id="payment-cheque-number"
                            placeholder="Enter cheque number">
                    </div>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 15px; display: flex; gap: 8px;">
                <button class="modal-btn cancel-btn" id="cancel-payment-btn">Cancel</button>
                <button class="modal-btn add-btn" id="save-invoice-btn" style="background: #4CAF50;">
                    <i class="fas fa-save"></i> Save Only
                </button>
                <button class="modal-btn add-btn" id="save-print-invoice-btn" style="background: #2196F3;">
                    <i class="fas fa-print"></i> Print A4
                </button>
                <button class="modal-btn add-btn" id="save-thermal-invoice-btn" style="background: #FF9800;">
                    <i class="fas fa-receipt"></i> Thermal
                </button>
            </div>
        </div>
    </div>

    <!-- Referral Modal -->
    <div class="modal-overlay" id="referral-modal">
        <div class="referral-modal">
            <div class="modal-title">Select Referral Source</div>
            <div class="referral-options" id="referral-options-list" style="max-height: 300px; overflow-y: auto;">
                <!-- Referral options will be loaded dynamically -->
                <div class="referral-option" data-referral-id="none" data-referral-code="">
                    <i class="fas fa-user"></i> No Referral
                </div>
            </div>
            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-referral-btn">Skip</button>
                <button class="modal-btn add-btn" id="confirm-referral-btn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Hold Modal -->
    <div class="modal-overlay" id="hold-modal">
        <div class="customer-modal" style="min-width: 400px;">
            <div class="modal-title">
                <i class="fas fa-pause"></i> Hold Invoice
            </div>

            <div class="modal-section">
                <div class="modal-section-title">Hold Information</div>
                <div class="form-group">
                    <label class="form-label">Reference Name *</label>
                    <input type="text" class="form-input" id="hold-reference-input" placeholder="Enter customer name"
                        value="Walk-in Customer">
                </div>
                <div class="form-group">
                    <label class="form-label">Hold Number</label>
                    <input type="text" class="form-input" id="hold-number-input" placeholder="Auto-generated" readonly>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-title">Cart Summary</div>
                <div class="modal-product-info">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Items:</span>
                        <span id="hold-item-count">0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Subtotal:</span>
                        <span>₹<span id="hold-subtotal">0.00</span></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: bold;">
                        <span>Total:</span>
                        <span style="color: #2196F3;">₹<span id="hold-total">0.00</span></span>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-hold-btn">Cancel</button>
                <button class="modal-btn add-btn" id="confirm-hold-btn" style="background: #FF9800;">
                    <i class="fas fa-pause"></i> Hold
                </button>
            </div>
        </div>
    </div>

    <!-- Holds List Modal -->
    <div class="modal-overlay" id="holds-list-modal">
        <div class="customer-modal" style="min-width: 650px; max-width: 700px;">
            <div class="modal-title">
                <i class="fas fa-list"></i> Held Invoices
            </div>

            <div class="modal-section">
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-striped table-hover">

                        <tbody id="holds-list-table">
                            <!-- Holds will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div id="no-holds-message" style="text-align: center; padding: 15px; color: #666; display: none;">
                    <i class="fas fa-inbox" style="font-size: 36px; opacity: 0.5; margin-bottom: 5px;"></i>
                    <p>No held invoices found</p>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="close-holds-list-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Quotation Modal -->
    <div class="modal-overlay" id="quotation-modal">
        <div class="customer-modal" style="min-width: 650px; max-width: 700px;">
            <div class="modal-title">
                <i class="fas fa-file-alt"></i> Create Quotation
            </div>

            <div class="scrollable-content" style="max-height: 400px;">
                <!-- Customer Information -->
                <div class="customer-info-section">
                    <div class="customer-info-header">
                        <div class="customer-info-title">
                            <i class="fas fa-user me-2"></i>Customer Information
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" class="form-input" id="quotation-customer-name" placeholder="Enter name"
                                value="Walk-in Customer">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-input" id="quotation-customer-phone"
                                placeholder="Enter phone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" id="quotation-customer-email"
                                placeholder="Enter email">
                        </div>
                        <div class="form-group">
                            <label class="form-label">GSTIN</label>
                            <input type="text" class="form-input" id="quotation-customer-gstin"
                                placeholder="Enter GSTIN">
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Address</label>
                            <textarea class="form-input" id="quotation-customer-address" rows="1"
                                placeholder="Enter address"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Quotation Details -->
                <div class="modal-section">
                    <div class="modal-section-title">Quotation Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Quotation Number</label>
                            <input type="text" class="form-input" id="quotation-number-input"
                                placeholder="Auto-generated" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Valid Until (Days)</label>
                            <input type="number" class="form-input" id="quotation-valid-days" value="15" min="1"
                                max="365">
                        </div>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="modal-section">
                    <div class="modal-section-title">Quotation Summary</div>
                    <div class="modal-product-info">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Items:</span>
                            <span id="quotation-item-count">0</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Subtotal:</span>
                            <span>₹<span id="quotation-subtotal">0.00</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold;">
                            <span>Total:</span>
                            <span style="color: #2196F3;">₹<span id="quotation-total">0.00</span></span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="modal-section">
                    <div class="modal-section-title">Additional Notes</div>
                    <div class="form-group">
                        <textarea class="form-input" id="quotation-notes" rows="2" placeholder="Enter notes"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-quotation-btn">Cancel</button>
                <button class="modal-btn add-btn" id="confirm-quotation-btn">
                    <i class="fas fa-save"></i> Save Quotation
                </button>
            </div>
        </div>
    </div>

    <!-- Quotations List Modal -->
    <div class="modal-overlay" id="quotations-list-modal">
        <div class="customer-modal" style="min-width: 750px; max-width: 800px;">
            <div class="modal-title">
                <i class="fas fa-file-invoice"></i> Quotations List
            </div>

            <div class="modal-section">
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-striped table-hover">

                        <tbody id="quotations-list-table">
                            <!-- Quotations will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div id="no-quotations-message" style="text-align: center; padding: 15px; color: #666; display: none;">
                    <i class="fas fa-file" style="font-size: 36px; opacity: 0.5; margin-bottom: 5px;"></i>
                    <p>No quotations found</p>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="close-quotations-list-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Retrieve Hold Modal -->
    <div class="modal-overlay" id="retrieve-hold-modal">
        <div class="referral-modal">
            <div class="modal-title">Retrieve Held Invoice</div>
            <div class="modal-product-info" id="retrieve-hold-info">
                <!-- Hold details will be shown here -->
            </div>
            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-retrieve-hold-btn">Cancel</button>
                <button class="modal-btn add-btn" id="confirm-retrieve-hold-btn">
                    <i class="fas fa-shopping-cart"></i> Load
                </button>
            </div>
        </div>
    </div>

    <!-- Manual Product Modal for Quotations -->
    <div class="modal-overlay" id="manual-product-modal">
        <div class="quantity-modal" style="min-width: 450px;">
            <div class="modal-title">
                <i class="fas fa-plus-circle"></i> Add Manual Product
                <small style="display: block; font-size: 11px; color: #666; margin-top: 5px;">For quotation only - no
                    stock tracking</small>
            </div>

            <div class="modal-product-info" id="manual-product-info">
                <i class="fas fa-info-circle"></i> Enter product details manually
            </div>

            <div class="modal-row">
                <div class="modal-input-group">
                    <label>Product Name *</label>
                    <input type="text" class="modal-input" id="manual-product-name" placeholder="Enter product name"
                        autocomplete="off">
                </div>
            </div>

            <div class="modal-row">
                <div class="modal-input-group">
                    <label>Product Code/SKU</label>
                    <input type="text" class="modal-input" id="manual-product-code" placeholder="Optional">
                </div>
                <div class="modal-input-group">
                    <label>HSN Code</label>
                    <input type="text" class="modal-input" id="manual-hsn-code" placeholder="Optional">
                </div>
            </div>

            <div class="modal-row">
                <div class="modal-input-group">
                    <label>Quantity *</label>
                    <input type="number" class="modal-input" id="manual-quantity" value="1" min="0.01" step="0.01">
                </div>
                <div class="modal-input-group">
                    <label>Unit</label>
                    <select class="modal-select" id="manual-unit-select">
                        <option value="PCS">PCS</option>
                        <option value="MTR">MTR</option>
                        <option value="KG">KG</option>
                        <option value="BOX">BOX</option>
                        <option value="SET">SET</option>
                        <option value="NOS">NOS</option>
                        <option value="LTR">LTR</option>
                        <option value="M">M</option>
                        <option value="FT">FT</option>
                    </select>
                </div>
            </div>

            <div class="modal-row">
                <div class="modal-input-group">
                    <label>Unit Price (₹) *</label>
                    <input type="number" class="modal-input" id="manual-unit-price" value="0" min="0" step="0.01">
                </div>
                <div class="modal-input-group">
                    <label>MRP (₹)</label>
                    <input type="number" class="modal-input" id="manual-mrp" value="0" min="0" step="0.01">
                </div>
            </div>

            <div class="discount-row">
                <div class="modal-input-group">
                    <label>Discount Type</label>
                    <select class="discount-type-select" id="manual-discount-type">
                        <option value="percentage">%</option>
                        <option value="fixed">₹ Fixed</option>
                    </select>
                </div>
                <div class="modal-input-group">
                    <label>Discount Value</label>
                    <input type="number" class="discount-value-input" id="manual-discount-value" value="0" min="0"
                        step="0.01">
                </div>
            </div>

            <div class="price-preview" id="manual-price-preview">
                <div class="price-preview-row">
                    <span>Subtotal:</span>
                    <span id="manual-preview-subtotal">₹0.00</span>
                </div>
                <div class="price-preview-row">
                    <span>Discount:</span>
                    <span id="manual-preview-discount">-₹0.00</span>
                </div>
                <div class="price-preview-total">
                    <span>Final Price:</span>
                    <span id="manual-preview-total">₹0.00</span>
                </div>
            </div>

            <div class="modal-section" style="margin-top: 10px;">
                <div class="modal-section-title">GST Details (Optional)</div>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <div>
                        <label style="font-size: 11px;">CGST (%)</label>
                        <input type="number" class="form-input" id="manual-cgst" value="0" min="0" step="0.1">
                    </div>
                    <div>
                        <label style="font-size: 11px;">SGST (%)</label>
                        <input type="number" class="form-input" id="manual-sgst" value="0" min="0" step="0.1">
                    </div>
                    <div>
                        <label style="font-size: 11px;">IGST (%)</label>
                        <input type="number" class="form-input" id="manual-igst" value="0" min="0" step="0.1">
                    </div>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 15px;">
                <button class="modal-btn cancel-btn" id="cancel-manual-btn">Cancel</button>
                <button class="modal-btn add-btn" id="add-manual-product-btn" style="background: #FF9800;">
                    <i class="fas fa-plus"></i> Add to Quotation
                </button>
            </div>
        </div>
    </div>

    <!-- Retrieve Quotation Modal -->
    <div class="modal-overlay" id="retrieve-quotation-modal">
        <div class="referral-modal">
            <div class="modal-title">Retrieve Quotation</div>
            <div class="modal-product-info" id="retrieve-quotation-info">
                <!-- Quotation details will be shown here -->
            </div>
            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancel-retrieve-quotation-btn">Cancel</button>
                <button class="modal-btn add-btn" id="confirm-retrieve-quotation-btn">
                    <i class="fas fa-shopping-cart"></i> Load
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
// ==================== GLOBAL STATE ====================
let CART = [];
let PRODUCTS = [];
let CATEGORIES = [];
let CUSTOMERS = [];
let CURRENT_PRODUCT = null;
let CURRENT_CATEGORY_ID = 0;
let IS_GST_BILL = true;
let IS_WHOLESALE = false;
let SELECTED_REFERRAL = "none";
let SELECTED_SITE = "main";
let BARCODE_MAP = {};
let CURRENT_CUSTOMER = null;
let CUSTOMER_POINTS = 0;
let POINTS_DISCOUNT = 0;
let LOYALTY_RATE = 1;
let BUSINESS_ID = 1;
let SHOP_ID = 1;
let USER_ID = 1;
let IS_EDITING_CUSTOMER_INFO = false;
let OVERALL_DISCOUNT = 0;
let HOLDS = [];
let QUOTATIONS = [];
let CURRENT_HOLD = null;
let CURRENT_QUOTATION = null;
let REFERRALS = [];
let SELECTED_REFERRAL_ID = null;
let SELECTED_REFERRAL_CODE = '';
let SELECTED_REFERRAL_NAME = 'None';
let CURRENT_UNIT_IS_SECONDARY = false;
let CURRENT_SELECTED_UNIT = 'primary';
let BARCODE_CACHE = {};
let SITES = [];
let ENGINEERS = [];

// Helper function to round to nearest integer
function roundValue(value) {
    return Math.round(value);
}

// Helper function to format price without decimals
function formatPrice(price) {
    return '₹' + roundValue(price);
}

// ==================== API CONFIG ====================
const API_BASE = 'api/';
const API_CUSTOMERS = API_BASE + 'customers.php';
const API_PRODUCTS = API_BASE + 'products.php';
const API_INVOICES = API_BASE + 'invoices.php';
const API_HOLDS = API_BASE + 'holds.php';
const API_QUOTATIONS = API_BASE + 'quotations.php';

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('AS Electricals POS: Initializing...');
    init();
});

// ==================== RESET FUNCTIONS ====================
function resetToWalkInCustomer() {
    // Reset customer selection to walk-in
    CURRENT_CUSTOMER = CUSTOMERS.find(c => c.id === 'walk-in');
    CUSTOMER_POINTS = 0;
    IS_WHOLESALE = false;
    
    // Update dropdown
    $('#customer-type').val('walk-in').trigger('change');
    
    // Clear any customer-specific data
    POINTS_DISCOUNT = 0;
    
    console.log('Reset to walk-in customer');
    showInfoToast('Reset to walk-in customer');
}

function refreshPageAfterDelay(delay = 3000) {
    setTimeout(() => {
        location.reload();
    }, delay);
}

async function init() {
    try {
        setupEventListeners();
        await loadCustomers();
        await loadProducts();
        await loadReferrals();
        await loadSites();
        await loadEngineers();
        updateCustomerDropdown();
        renderCategories();
        loadCartFromSession();
        CURRENT_CATEGORY_ID = 0;
        renderProductsByCategory(0, 'All Products');
        
        setTimeout(() => {
            setupSelect2();
            setupPaymentSelect2();
        }, 100);
        
        updateUI();
        
        // Setup manual product event listeners
        setupManualProductEventListeners();
        
        showSuccessToast('System ready! Welcome to AS Electricals POS');
        
    } catch (error) {
        console.error('Initialization error:', error);
        showErrorToast('System initialization failed. Please refresh.');
    }
}

// ==================== SWEETALERT TOASTS ====================
function showSuccessToast(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}

function showErrorToast(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: true
    });
}

function showWarningToast(message) {
    Swal.fire({
        icon: 'warning',
        title: 'Warning',
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: true
    });
}

function showInfoToast(message) {
    Swal.fire({
        icon: 'info',
        title: 'Info',
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: true
    });
}

function showConfirmDialog(title, text, icon = 'question') {
    return Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    });
}

// ==================== CUSTOMER FUNCTIONS ====================
async function loadCustomers() {
    try {
        const response = await fetch(`${API_CUSTOMERS}?action=list`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();
        
        if (data.success && data.customers) {
            CUSTOMERS = data.customers.map(customer => ({
                id: customer.id,
                name: customer.name,
                contact: customer.phone || '',
                email: customer.email || '',
                gstin: customer.gstin || '',
                address: customer.address || '',
                type: customer.customer_type || 'retail',
                credit_limit: roundValue(parseFloat(customer.credit_limit) || 0),
                outstanding: roundValue(parseFloat(customer.outstanding_amount) || 0),
                points: 0
            }));
            console.log('Customers loaded:', CUSTOMERS.length);
        } else {
            throw new Error(data.message || 'Failed to load customers');
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        CUSTOMERS = [{ id: 'walk-in', name: "Walk-in Customer", contact: "", email: "", gstin: "", address: "", type: "retail", credit_limit: 0, outstanding: 0, points: 0 }];
        showWarningToast('Using offline customer data');
    }
    
    if (!CUSTOMERS.find(c => c.id === 'walk-in')) {
        CUSTOMERS.unshift({ id: 'walk-in', name: 'Walk-in Customer', contact: '', email: '', gstin: '', address: '', type: 'retail', credit_limit: 0, outstanding: 0, points: 0 });
    }
}

// ==================== ENGINEER & SITE FUNCTIONS ====================
async function loadSites() {
    try {
        const response = await fetch('api/sites.php?action=list');
        const data = await response.json();
        if (data.success && data.sites) {
            SITES = data.sites;
            console.log('Sites loaded:', SITES.length);
            updateSiteDropdowns();
        } else {
            SITES = [
                { site_id: 1, site_name: 'Main Store', city: 'Dharmapuri', engineer_id: 1 },
                { site_id: 2, site_name: 'Aanandhamayam Branch', city: 'Salem', engineer_id: 2 }
            ];
            updateSiteDropdowns();
        }
    } catch (error) {
        console.error('Error loading sites:', error);
        SITES = [
            { site_id: 1, site_name: 'Main Store', city: 'Dharmapuri', engineer_id: 1 },
            { site_id: 2, site_name: 'Aanandhamayam Branch', city: 'Salem', engineer_id: 2 }
        ];
        updateSiteDropdowns();
    }
}

async function loadEngineers() {
    try {
        const response = await fetch('api/engineers.php?action=list');
        const data = await response.json();
        if (data.success && data.engineers) {
            ENGINEERS = data.engineers;
            console.log('Engineers loaded:', ENGINEERS.length);
            updateEngineerDropdowns();
        } else {
            ENGINEERS = [
                { engineer_id: 1, first_name: 'Rajesh', last_name: 'Kumar', phone: '9876543210' },
                { engineer_id: 2, first_name: 'Suresh', last_name: 'Reddy', phone: '9876543211' }
            ];
            updateEngineerDropdowns();
        }
    } catch (error) {
        console.error('Error loading engineers:', error);
        ENGINEERS = [
            { engineer_id: 1, first_name: 'Rajesh', last_name: 'Kumar', phone: '9876543210' },
            { engineer_id: 2, first_name: 'Suresh', last_name: 'Reddy', phone: '9876543211' }
        ];
        updateEngineerDropdowns();
    }
}

function updateSiteDropdowns() {
    const mainSiteSelect = $('#site-select');
    mainSiteSelect.empty();
   
    
    const paymentSiteSelect = $('#payment-site-select');
    paymentSiteSelect.empty();
    paymentSiteSelect.append(new Option('-- Select Site --', ''));
    SITES.forEach(site => {
        paymentSiteSelect.append(new Option(site.site_name + (site.city ? ` (${site.city})` : ''), site.site_id));
    });
    paymentSiteSelect.trigger('change');
    
    
}

function updateEngineerDropdowns() {
    const paymentEngineerSelect = $('#payment-engineer-select');
    paymentEngineerSelect.empty();
    paymentEngineerSelect.append(new Option('-- Select Engineer --', ''));
    ENGINEERS.forEach(engineer => {
        paymentEngineerSelect.append(new Option(`${engineer.first_name} ${engineer.last_name}`, engineer.engineer_id));
    });
    paymentEngineerSelect.trigger('change');
}

// ==================== PRODUCT FUNCTIONS ====================
async function loadProducts() {
    try {
        console.log('Fetching products...');
        const response = await fetch(`${API_PRODUCTS}?action=list&_=${Date.now()}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();
        
        if (data.success && data.products) {
            PRODUCTS = data.products.map(product => ({
                id: product.id,
                name: product.product_name || 'Unnamed Product',
                code: product.product_code || `PROD-${product.id}`,
                price: roundValue(parseFloat(product.retail_price) || 0),
                wholesale_price: roundValue(parseFloat(product.wholesale_price) || 0),
                mrp: roundValue(parseFloat(product.mrp) || 0),
                category_id: product.category_id || 1,
                category_name: product.category_name || 'General',
                subcategory_name: product.subcategory_name || '',
                shop_stock: roundValue(parseFloat(product.shop_stock_primary) || 0),
                unit_of_measure: product.unit_of_measure || 'nos',
                secondary_unit: product.secondary_unit,
                sec_unit_conversion: parseFloat(product.sec_unit_conversion) || null,
                sec_unit_price_type: product.sec_unit_price_type || 'fixed',
                sec_unit_extra_charge: roundValue(parseFloat(product.sec_unit_extra_charge) || 0),
                cgst_rate: roundValue(parseFloat(product.cgst_rate) || 0),
                sgst_rate: roundValue(parseFloat(product.sgst_rate) || 0),
                igst_rate: roundValue(parseFloat(product.igst_rate) || 0),
                barcode: product.barcode || product.product_code,
                hsn_code: product.hsn_code || '',
                discount_type: product.discount_type || 'fixed',
                discount_value: roundValue(parseFloat(product.discount_value) || 0),
                stock_price: roundValue(parseFloat(product.stock_price) || 0),
                referral_enabled: parseInt(product.referral_enabled) || 0,
                referral_type: product.referral_type || 'percentage',
                referral_value: roundValue(parseFloat(product.referral_value) || 0)
            }));
            BARCODE_MAP = data.barcode_map || {};
            extractCategories();
            console.log('Products loaded:', PRODUCTS.length);
            return true;
        } else {
            throw new Error(data.message || 'Failed to load products');
        }
    } catch (error) {
        console.error('Error loading products:', error);
        PRODUCTS = [{ id: 1, name: "Lenovo Thinkpad", code: "PROD-1", price: 18400, wholesale_price: 17600, mrp: 20000, category_id: 1, category_name: "Laptop", subcategory_name: "", shop_stock: 44, unit_of_measure: "nos", secondary_unit: null, sec_unit_conversion: null, cgst_rate: 0, sgst_rate: 0, igst_rate: 0, barcode: null, hsn_code: "", discount_type: "fixed", discount_value: 20, stock_price: 16000 }];
        BARCODE_MAP = {};
        extractCategories();
        showWarningToast('Using offline product data');
        return false;
    }
}

function extractCategories() {
    CATEGORIES = [{ id: 0, name: "All Products", product_count: PRODUCTS.length }];
    const categoryMap = {};
    PRODUCTS.forEach(product => {
        const categoryName = product.category_name || 'General';
        const subcategoryName = product.subcategory_name || '';
        const fullCategoryName = subcategoryName ? `${categoryName} - ${subcategoryName}` : categoryName;
        if (!categoryMap[fullCategoryName]) {
            categoryMap[fullCategoryName] = {
                id: Object.keys(categoryMap).length + 1,
                name: fullCategoryName,
                original_category: categoryName,
                subcategory: subcategoryName,
                product_count: 0
            };
        }
        categoryMap[fullCategoryName].product_count++;
    });
    Object.values(categoryMap).forEach(cat => CATEGORIES.push(cat));
}

async function getProductByBarcode(barcode) {
    try {
        if (!barcode || barcode.trim() === '') return null;
        barcode = barcode.trim();
        
        // Check cache first
        if (BARCODE_CACHE[barcode]) return BARCODE_CACHE[barcode];
        
        // Check in barcode map
        if (BARCODE_MAP[barcode]) {
            const product = PRODUCTS.find(p => p.id == BARCODE_MAP[barcode]);
            if (product) {
                BARCODE_CACHE[barcode] = product;
                return product;
            }
        }
        
        // Check by product ID
        const productById = PRODUCTS.find(p => p.id == barcode);
        if (productById) {
            BARCODE_CACHE[barcode] = productById;
            return productById;
        }
        
        // Check by product code
        const productByCode = PRODUCTS.find(p => p.code && p.code.toString().toLowerCase() === barcode.toLowerCase());
        if (productByCode) {
            BARCODE_CACHE[barcode] = productByCode;
            return productByCode;
        }
        
        return null;
    } catch (error) {
        console.error('Error getting product by barcode:', error);
        return null;
    }
}

async function searchProducts(query) {
    if (!query || query.length < 2) {
        renderProductsByCategory(CURRENT_CATEGORY_ID, CATEGORIES.find(c => c.id === CURRENT_CATEGORY_ID)?.name || '');
        return;
    }
    
    try {
        const response = await fetch(`${API_PRODUCTS}?action=search&q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success && data.products) {
            const searchResults = data.products.map(product => ({
                id: product.id,
                name: product.product_name || 'Unnamed Product',
                code: product.product_code || `PROD-${product.id}`,
                price: roundValue(parseFloat(product.retail_price) || 0),
                wholesale_price: roundValue(parseFloat(product.wholesale_price) || 0),
                mrp: roundValue(parseFloat(product.mrp) || 0),
                category_id: 1,
                category_name: product.category_name || 'General',
                shop_stock: roundValue(parseFloat(product.shop_stock_primary) || 0),
                unit_of_measure: product.unit_of_measure || 'nos',
                secondary_unit: product.secondary_unit,
                sec_unit_conversion: parseFloat(product.sec_unit_conversion) || 1,
                cgst_rate: 0,
                sgst_rate: 0,
                igst_rate: 0,
                barcode: product.barcode || product.product_code
            }));
            renderSearchResults(searchResults, query);
        } else {
            const filtered = PRODUCTS.filter(p => p.name.toLowerCase().includes(query.toLowerCase()) || p.code.toLowerCase().includes(query.toLowerCase()));
            renderSearchResults(filtered, query);
        }
    } catch (error) {
        console.error('Error searching products:', error);
        const filtered = PRODUCTS.filter(p => p.name.toLowerCase().includes(query.toLowerCase()) || p.code.toLowerCase().includes(query.toLowerCase()));
        renderSearchResults(filtered, query);
    }
}

// ==================== BARCODE SCANNER ====================
function setupBarcodeScanner() {
    const barcodeInput = document.getElementById('barcode-input');
    if (!barcodeInput) return;
    
    let barcode = '';
    let lastTime = 0;
    let isProcessing = false;
    

    
    barcodeInput.addEventListener('blur', function() {
        setTimeout(() => {
            if (!document.querySelector('.modal-overlay[style*="block"]')) {
                this.focus();
            }
        }, 100);
    });

    barcodeInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            return;
        }
    });

    barcodeInput.addEventListener('input', async function(e) {
        const currentTime = new Date().getTime();
        
        if (currentTime - lastTime > 200) {
            barcode = this.value;
        } else {
            barcode += this.value.slice(-1);
        }
        
        lastTime = currentTime;
        
        if (barcode.length >= 8 && !isProcessing) {
            isProcessing = true;
            try {
                await processBarcode(barcode);
            } finally {
                isProcessing = false;
            }
        }
    });

    barcodeInput.addEventListener('keyup', async function(e) {
        if (e.key === 'Enter' && !isProcessing) {
            isProcessing = true;
            try {
                await processBarcode(this.value);
                this.value = '';
            } finally {
                isProcessing = false;
                setTimeout(() => this.focus(), 100);
            }
        }
    });
}

async function processBarcode(barcode) {
    if (!barcode || barcode.trim() === '') {
        showWarningToast('Please scan a valid barcode');
        return;
    }
    
    try {
        const product = await getProductByBarcode(barcode);
        
        if (product) {
            if (product.shop_stock <= 0) {
                showWarningToast(`${product.name} is out of stock!`);
                return;
            }
            
            CURRENT_PRODUCT = product;
            showProductModal(product);
        } else {
            showWarningToast('Product not found. Please scan a valid barcode.');
        }
    } catch (error) {
        console.error('Error processing barcode:', error);
        showErrorToast('Error scanning barcode');
    }
}

// ==================== RENDERING FUNCTIONS ====================
function renderCategories() {
    const container = document.getElementById('categories-list');
    if (!container) return;
    container.innerHTML = '';
    
    const allDiv = document.createElement('div');
    allDiv.className = 'category-item' + (CURRENT_CATEGORY_ID === 0 ? ' active' : '');
    allDiv.innerHTML = `<span><i class="fas fa-boxes me-2"></i>All Products</span><span class="category-count">${PRODUCTS.length}</span>`;
    allDiv.onclick = () => { CURRENT_CATEGORY_ID = 0; renderCategories(); renderProductsByCategory(0, 'All Products'); };
    container.appendChild(allDiv);
    
    const categoryGroups = {};
    PRODUCTS.forEach(product => {
        const mainCat = product.category_name || 'General';
        const subCat = product.subcategory_name || '';
        if (!categoryGroups[mainCat]) {
            categoryGroups[mainCat] = { name: mainCat, productCount: 0, subcategories: {} };
        }
        if (subCat) {
            if (!categoryGroups[mainCat].subcategories[subCat]) {
                categoryGroups[mainCat].subcategories[subCat] = { name: subCat, productCount: 0, mainCategory: mainCat };
            }
            categoryGroups[mainCat].subcategories[subCat].productCount++;
        }
        categoryGroups[mainCat].productCount++;
    });
    
    Object.values(categoryGroups).forEach(category => {
        const hasSubcategories = Object.keys(category.subcategories).length > 0;
        const catContainer = document.createElement('div');
        catContainer.className = 'category-container';
        
        const mainDiv = document.createElement('div');
        mainDiv.className = 'category-item' + (CURRENT_CATEGORY_ID === category.name ? ' active' : '');
        mainDiv.innerHTML = `<span><i class="fas fa-folder ${hasSubcategories ? 'text-warning' : 'text-primary'} me-2"></i>${category.name}${hasSubcategories ? '<i class="fas fa-chevron-down ms-1" style="font-size: 10px;"></i>' : ''}</span><span class="category-count">${category.productCount}</span>`;
        
        if (!hasSubcategories) {
            mainDiv.onclick = () => { CURRENT_CATEGORY_ID = category.name; renderCategories(); renderProductsByCategory(category.name, category.name); };
        } else {
            mainDiv.onclick = function(e) {
                e.stopPropagation();
                const subContainer = this.nextElementSibling;
                if (subContainer) {
                    subContainer.style.display = subContainer.style.display === 'none' ? 'block' : 'none';
                    const chevron = this.querySelector('.fa-chevron-down');
                    if (chevron) chevron.style.transform = subContainer.style.display === 'none' ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            };
        }
        
        catContainer.appendChild(mainDiv);
        
        if (hasSubcategories) {
            const subContainer = document.createElement('div');
            subContainer.className = 'subcategories-container';
            subContainer.style.display = 'none';
            subContainer.style.paddingLeft = '15px';
            
            Object.values(category.subcategories).forEach(subcat => {
                const subDiv = document.createElement('div');
                subDiv.className = 'category-item subcategory' + (CURRENT_CATEGORY_ID === `${category.name}-${subcat.name}` ? ' active' : '');
                subDiv.innerHTML = `<span><i class="fas fa-folder-open text-success me-2" style="font-size: 11px;"></i>${subcat.name}</span><span class="category-count" style="background: #4CAF50;">${subcat.productCount}</span>`;
                subDiv.onclick = () => { CURRENT_CATEGORY_ID = `${category.name}-${subcat.name}`; renderCategories(); renderProductsByCategory(`${category.name}-${subcat.name}`, `${category.name} - ${subcat.name}`); };
                subContainer.appendChild(subDiv);
            });
            
            catContainer.appendChild(subContainer);
        }
        
        container.appendChild(catContainer);
    });
}

function renderProductsByCategory(categoryId, categoryName = '') {
    const container = document.getElementById('products-list');
    const title = document.getElementById('current-category');
    if (!container || !title) return;
    
    let filteredProducts = PRODUCTS;
    
    if (categoryId !== 0) {
        if (typeof categoryId === 'string' && categoryId.includes('-')) {
            const [mainCat, subCat] = categoryId.split('-');
            filteredProducts = PRODUCTS.filter(p => p.category_name === mainCat && p.subcategory_name === subCat);
        } else if (typeof categoryId === 'string') {
            filteredProducts = PRODUCTS.filter(p => p.category_name === categoryId);
        } else {
            const selectedCategory = CATEGORIES.find(c => c.id === categoryId);
            if (selectedCategory && selectedCategory.name !== 'All Products') {
                if (selectedCategory.subcategory) {
                    filteredProducts = PRODUCTS.filter(p => p.category_name === selectedCategory.original_category && p.subcategory_name === selectedCategory.subcategory);
                } else {
                    filteredProducts = PRODUCTS.filter(p => p.category_name === selectedCategory.original_category);
                }
            }
        }
    }
    
    title.textContent = categoryName ? `${categoryName} (${filteredProducts.length})` : `All Products (${filteredProducts.length})`;
    renderProductCards(filteredProducts);
}

function renderProductCards(products) {
    const container = document.getElementById('products-list');
    if (!container) return;
    container.innerHTML = '';
    
    if (products.length === 0) {
        container.innerHTML = `<div style="text-align: center; padding: 30px; color: #666;"><i class="fas fa-search" style="font-size: 36px; margin-bottom: 10px; opacity: 0.5;"></i><p>No products found</p></div>`;
        return;
    }
    
    products.forEach(product => {
        const price = IS_WHOLESALE ? product.wholesale_price : product.price;
        const retailPrice = IS_WHOLESALE ? `Retail: ₹${product.price}` : '';
        let stockClass = 'in-stock';
        let stockText = `${product.shop_stock} ${product.unit_of_measure}`;
        if (product.shop_stock < 10) stockClass = 'low-stock';
        if (product.shop_stock === 0) { stockClass = 'out-of-stock'; stockText = 'Out of stock'; }
        
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `<div class="product-info"><div><h4>${product.name}</h4><div class="product-code">${product.code}</div><div class="product-price">₹${price}</div></div><div>${retailPrice ? `<div class="wholesale-price">${retailPrice}</div>` : ''}<div class="product-stock"><span class="stock-badge ${stockClass}">${stockText}</span></div></div></div>`;
        card.onclick = () => { CURRENT_PRODUCT = product; showProductModal(product); };
        container.appendChild(card);
    });
}

function renderSearchResults(results, searchTerm) {
    const container = document.getElementById('products-list');
    const title = document.getElementById('current-category');
    if (!container || !title) return;
    
    title.textContent = `Search: "${searchTerm}" (${results.length})`;
    container.innerHTML = '';
    
    if (results.length === 0) {
        container.innerHTML = `<div style="text-align: center; padding: 30px; color: #666;"><i class="fas fa-search" style="font-size: 36px; margin-bottom: 10px; opacity: 0.5;"></i><p>No products found for "${searchTerm}"</p></div>`;
        return;
    }
    
    results.forEach(product => {
        const price = IS_WHOLESALE ? product.wholesale_price : product.price;
        const retailPrice = IS_WHOLESALE ? `Retail: ₹${product.price}` : '';
        let stockClass = 'in-stock';
        let stockText = `${product.shop_stock} ${product.unit_of_measure}`;
        if (product.shop_stock < 10) stockClass = 'low-stock';
        if (product.shop_stock === 0) { stockClass = 'out-of-stock'; stockText = 'Out of stock'; }
        
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `<div class="product-info"><div><h4>${product.name}</h4><div class="product-code">${product.code}</div><div class="product-price">₹${price}</div></div><div>${retailPrice ? `<div class="wholesale-price">${retailPrice}</div>` : ''}<div class="product-stock"><span class="stock-badge ${stockClass}">${stockText}</span></div></div></div>`;
        card.onclick = () => { CURRENT_PRODUCT = product; showProductModal(product); };
        container.appendChild(card);
    });
}

// ==================== PRODUCT MODAL ====================
function showProductModal(product) {
    const modal = document.getElementById('quantity-modal');
    if (!modal) return;
    
    CURRENT_PRODUCT = product;
    CURRENT_UNIT_IS_SECONDARY = false;
    CURRENT_SELECTED_UNIT = 'primary';
    
    const basePrice = IS_WHOLESALE ? product.wholesale_price : product.price;
    const stockText = `${product.shop_stock} ${product.unit_of_measure}`;
    
    document.getElementById('modal-product-name').textContent = product.name;
    
    let pricePerMeterInfo = '';
    if (product.secondary_unit && product.sec_unit_conversion) {
        const conversion = parseFloat(product.sec_unit_conversion);
        let pricePerMeter = roundValue(basePrice / conversion);
        if (product.sec_unit_price_type === 'percentage') {
            pricePerMeter = roundValue((basePrice * (1 + (parseFloat(product.sec_unit_extra_charge) || 0) / 100)) / conversion);
        } else if (product.sec_unit_price_type === 'fixed') {
            pricePerMeter = roundValue((basePrice + (parseFloat(product.sec_unit_extra_charge) || 0)) / conversion);
        }
        pricePerMeterInfo = `<br>Price per ${product.secondary_unit}: ₹${pricePerMeter}`;
    }
    
    let secondaryInfo = '';
    if (product.secondary_unit && product.sec_unit_conversion) {
        const conversion = parseFloat(product.sec_unit_conversion);
        secondaryInfo = `<br>Secondary: ${product.secondary_unit}<br>1 ${product.unit_of_measure} = ${conversion} ${product.secondary_unit}${pricePerMeterInfo}`;
    }
    
    document.getElementById('modal-product-info').innerHTML = `<strong>Product Details:</strong><br>Code: ${product.code}<br>MRP: ₹${product.mrp} | Price: ₹${basePrice}<br>Stock: ${stockText}<br>${secondaryInfo}${IS_GST_BILL ? `GST: ${(product.cgst_rate + product.sgst_rate + product.igst_rate)}%` : 'Non-GST'}`;
    
    document.getElementById('quantity-input').value = '1';
    document.getElementById('discount-type-select').value = 'percentage';
    document.getElementById('discount-value-input').value = '0';
    
    const unitSelect = document.getElementById('unit-select');
    if (unitSelect) {
        unitSelect.innerHTML = '';
        const primaryOption = document.createElement('option');
        primaryOption.value = product.unit_of_measure;
        primaryOption.textContent = product.unit_of_measure;
        primaryOption.dataset.unitType = 'primary';
        unitSelect.appendChild(primaryOption);
        
        if (product.secondary_unit && product.sec_unit_conversion) {
            const secondaryOption = document.createElement('option');
            secondaryOption.value = product.secondary_unit;
            secondaryOption.textContent = `${product.secondary_unit} (${product.sec_unit_conversion} ${product.unit_of_measure})`;
            secondaryOption.dataset.unitType = 'secondary';
            unitSelect.appendChild(secondaryOption);
        }
        
        unitSelect.onchange = function() {
            const unitType = this.options[this.selectedIndex].dataset.unitType;
            CURRENT_UNIT_IS_SECONDARY = unitType === 'secondary';
            CURRENT_SELECTED_UNIT = unitType;
            updateUnitConversion();
        };
    }
    
    document.getElementById('price-type-select').value = IS_WHOLESALE ? 'wholesale' : 'retail';
    document.getElementById('selling-price-input').value = basePrice;
    updatePricePreview();
    
    modal.style.display = 'block';
    document.getElementById('quantity-input').focus();
}

function updateUnitConversion() {
    if (!CURRENT_PRODUCT) return;
    
    const quantityInput = document.getElementById('quantity-input');
    const sellingPriceInput = document.getElementById('selling-price-input');
    const unitSelect = document.getElementById('unit-select');
    if (!quantityInput || !sellingPriceInput || !unitSelect) return;
    
    const currentQty = parseFloat(quantityInput.value) || 1;
    
    if (CURRENT_UNIT_IS_SECONDARY && CURRENT_PRODUCT.secondary_unit && CURRENT_PRODUCT.sec_unit_conversion) {
        const conversion = parseFloat(CURRENT_PRODUCT.sec_unit_conversion);
        
        if (CURRENT_SELECTED_UNIT === 'secondary') {
            // PRIMARY -> SECONDARY: Multiply quantity, adjust price per secondary unit
            const convertedQty = currentQty * conversion;
            quantityInput.value = roundValue(convertedQty);
            
            const basePrice = IS_WHOLESALE ? CURRENT_PRODUCT.wholesale_price : CURRENT_PRODUCT.price;
            let pricePerUnit = roundValue(basePrice / conversion);
            if (CURRENT_PRODUCT.sec_unit_price_type === 'percentage') {
                pricePerUnit = roundValue((basePrice * (1 + (parseFloat(CURRENT_PRODUCT.sec_unit_extra_charge) || 0) / 100)) / conversion);
            } else if (CURRENT_PRODUCT.sec_unit_price_type === 'fixed') {
                pricePerUnit = roundValue((basePrice + (parseFloat(CURRENT_PRODUCT.sec_unit_extra_charge) || 0)) / conversion);
            }
            sellingPriceInput.value = pricePerUnit;
            showInfoToast(`Converted to ${CURRENT_PRODUCT.secondary_unit} - ${roundValue(convertedQty)} ${CURRENT_PRODUCT.secondary_unit}`);
        } else {
            // SECONDARY -> PRIMARY: Divide quantity, price back to primary
            const convertedQty = currentQty / conversion;
            quantityInput.value = convertedQty.toFixed(3); // Keep decimal for fractional primary units
            const primaryPrice = IS_WHOLESALE ? CURRENT_PRODUCT.wholesale_price : CURRENT_PRODUCT.price;
            sellingPriceInput.value = primaryPrice;
            showInfoToast(`Converted to ${CURRENT_PRODUCT.unit_of_measure}`);
        }
    } else {
        const basePrice = IS_WHOLESALE ? CURRENT_PRODUCT.wholesale_price : CURRENT_PRODUCT.price;
        sellingPriceInput.value = basePrice;
    }
    updatePricePreview();
}

function updatePricePreview() {
    const quantity = parseFloat(document.getElementById('quantity-input').value) || 1;
    const sellingPrice = parseFloat(document.getElementById('selling-price-input').value) || 0;
    const discountType = document.getElementById('discount-type-select').value;
    const discountValue = parseFloat(document.getElementById('discount-value-input').value) || 0;
    
    const basePrice = roundValue(sellingPrice * quantity);
    let discountAmount = 0;
    if (discountType === 'percentage' && discountValue > 0) {
        discountAmount = roundValue((basePrice * discountValue) / 100);
    } else if (discountType === 'fixed' && discountValue > 0) {
        discountAmount = roundValue(discountValue * quantity);
    }
    const finalPrice = basePrice - discountAmount;
    
    document.getElementById('preview-base-price').textContent = `₹${basePrice}`;
    document.getElementById('preview-discount').textContent = `-₹${discountAmount}`;
    document.getElementById('preview-final-price').textContent = `₹${finalPrice}`;
}

// ==================== CART FUNCTIONS ====================
function addToCart() {
    if (!CURRENT_PRODUCT) return;
    
    const product = CURRENT_PRODUCT;
    const quantity = parseFloat(document.getElementById('quantity-input').value) || 1;
    const unit = document.getElementById('unit-select').value;
    const priceType = document.getElementById('price-type-select').value;
    const sellingPrice = roundValue(parseFloat(document.getElementById('selling-price-input').value) || product.price);
    const discountType = document.getElementById('discount-type-select').value;
    const discountValue = parseFloat(document.getElementById('discount-value-input').value) || 0;
    
    if (quantity <= 0) {
        showWarningToast('Please enter a valid quantity');
        return;
    }
    
    let finalQuantity = quantity;
    let finalUnit = unit;
    let finalPrice = sellingPrice;
    let isSecondaryUnit = false;
    let quantityInPrimary = quantity;
    
    if (unit === product.secondary_unit && product.sec_unit_conversion) {
        const conversion = parseFloat(product.sec_unit_conversion);
        if (CURRENT_SELECTED_UNIT === 'secondary') {
            // Convert secondary quantity to primary for stock check
            quantityInPrimary = roundValue(quantity / conversion);
            isSecondaryUnit = true;
            finalPrice = sellingPrice;
            
            console.log('Adding secondary unit:', {
                quantity: quantity,
                unit: unit,
                sellingPrice: sellingPrice,
                conversion: conversion,
                quantityInPrimary: quantityInPrimary
            });
        }
    } else {
        quantityInPrimary = quantity;
        isSecondaryUnit = false;
        finalPrice = sellingPrice;
    }
    
    // Stock check using primary units
    if (quantityInPrimary > product.shop_stock) {
        const availableSecondary = roundValue(product.shop_stock * (product.sec_unit_conversion || 1));
        showWarningToast(`Insufficient stock! Available: ${product.shop_stock} ${product.unit_of_measure} (≈${availableSecondary} ${product.secondary_unit || product.unit_of_measure})`);
        return;
    }
    
    let discountAmount = 0;
    const basePrice = roundValue(finalPrice * quantity);
    if (discountType === 'percentage' && discountValue > 0) {
        discountAmount = roundValue((basePrice * discountValue) / 100);
    } else if (discountType === 'fixed' && discountValue > 0) {
        discountAmount = roundValue(discountValue * quantity);
    }
    const totalPrice = basePrice - discountAmount;
    
    const cartItemId = `${product.id}-${finalUnit}-${priceType}-${isSecondaryUnit ? 'secondary' : 'primary'}`;
    const existingIndex = CART.findIndex(item => item.product_id === product.id && item.unit === finalUnit && item.price_type === priceType && item.is_secondary_unit === isSecondaryUnit);
    
    if (existingIndex >= 0) {
        const existingItem = CART[existingIndex];
        const newTotalInPrimary = existingItem.quantity_in_primary + quantityInPrimary;
        if (newTotalInPrimary > product.shop_stock) {
            showWarningToast(`Cannot add more. Total would exceed available stock of ${product.shop_stock} ${product.unit_of_measure}`);
            return;
        }
        CART[existingIndex].quantity += quantity;
        CART[existingIndex].quantity_in_primary = newTotalInPrimary;
        CART[existingIndex].price = finalPrice;
        CART[existingIndex].discount_type = discountType;
        CART[existingIndex].discount_value = discountValue;
        CART[existingIndex].discount_amount = discountAmount;
        CART[existingIndex].total = totalPrice;
        showSuccessToast(`${product.name} quantity updated`);
    } else {
        CART.push({
            id: cartItemId,
            product_id: product.id,
            name: product.name,
            code: product.code,
            price: finalPrice,
            price_type: priceType,
            quantity: quantity,
            unit: finalUnit,
            discount_type: discountType,
            discount_value: discountValue,
            discount_amount: discountAmount,
            total: totalPrice,
            mrp: product.mrp,
            cgst_rate: product.cgst_rate,
            sgst_rate: product.sgst_rate,
            igst_rate: product.igst_rate,
            hsn_code: product.hsn_code,
            shop_stock: product.shop_stock,
            unit_of_measure: product.unit_of_measure,
            secondary_unit: product.secondary_unit,
            sec_unit_conversion: product.sec_unit_conversion,
            sec_unit_price_type: product.sec_unit_price_type,
            sec_unit_extra_charge: product.sec_unit_extra_charge,
            stock_price: product.stock_price,
            is_secondary_unit: isSecondaryUnit,
            quantity_in_primary: quantityInPrimary,
            is_manual: false
        });
        showSuccessToast(`${product.name} added to cart (${roundValue(quantity)} ${finalUnit})`);
    }
    
    document.getElementById('quantity-modal').style.display = 'none';
    saveCartToSession();
    updateUI();
}

function updateCartItemQuantity(productId, change) {
    const itemIndex = CART.findIndex(item => item.id === productId);
    if (itemIndex === -1) return;
    
    const item = CART[itemIndex];
    
    // For manual products, no stock check needed
    if (item.is_manual) {
        const newQuantity = item.quantity + change;
        if (newQuantity < 0) {
            showWarningToast('Cannot reduce quantity below zero');
            return;
        }
        
        item.quantity = newQuantity;
        item.quantity_in_primary = newQuantity;
        
        // Recalculate totals
        const basePrice = roundValue(item.price * item.quantity);
        let discountAmount = 0;
        if (item.discount_type === 'percentage' && item.discount_value > 0) {
            discountAmount = roundValue((basePrice * item.discount_value) / 100);
        } else if (item.discount_type === 'fixed' && item.discount_value > 0) {
            discountAmount = roundValue(item.discount_value * item.quantity);
        }
        item.discount_amount = discountAmount;
        item.total = basePrice - discountAmount;
        
        saveCartToSession();
        updateUI();
        return;
    }
    
    // Regular product stock check
    const product = PRODUCTS.find(p => p.id === item.product_id);
    if (!product) return;
    
    if (item.is_secondary_unit && item.sec_unit_conversion) {
        const conversion = parseFloat(item.sec_unit_conversion);
        const primaryChange = roundValue(change / conversion);
        const newTotalInPrimary = item.quantity_in_primary + primaryChange;
        
        if (newTotalInPrimary < 0) {
            showWarningToast('Cannot reduce quantity below zero');
            return;
        }
        if (newTotalInPrimary > product.shop_stock) {
            const availableSecondary = roundValue(product.shop_stock * conversion);
            showWarningToast(`Cannot add more. Available: ${availableSecondary} ${item.unit}`);
            return;
        }
        item.quantity += change;
        item.quantity_in_primary = newTotalInPrimary;
    } else {
        const newQuantity = item.quantity + change;
        if (newQuantity < 0) {
            showWarningToast('Cannot reduce quantity below zero');
            return;
        }
        if (newQuantity > product.shop_stock) {
            showWarningToast(`Cannot add more. Available: ${product.shop_stock} ${item.unit}`);
            return;
        }
        item.quantity = newQuantity;
        item.quantity_in_primary = newQuantity;
    }
    
    const basePrice = roundValue(item.price * item.quantity);
    let discountAmount = 0;
    if (item.discount_type === 'percentage' && item.discount_value > 0) {
        discountAmount = roundValue((basePrice * item.discount_value) / 100);
    } else if (item.discount_type === 'fixed' && item.discount_value > 0) {
        discountAmount = roundValue(item.discount_value * item.quantity);
    }
    item.discount_amount = discountAmount;
    item.total = basePrice - discountAmount;
    
    saveCartToSession();
    updateUI();
}

function removeFromCart(productId) {
    const itemIndex = CART.findIndex(item => item.id === productId);
    if (itemIndex === -1) return;
    const itemName = CART[itemIndex].name;
    CART.splice(itemIndex, 1);
    showInfoToast(`${itemName} removed from cart`);
    saveCartToSession();
    updateUI();
}

function renderCart() {
    const container = document.getElementById('cart-items');
    if (!container) return;
    
    if (CART.length === 0) {
        container.innerHTML = `<div class="empty-cart"><p>No items in cart</p><p style="font-size: 12px; margin-top: 5px; color: #999;">Tap products to add</p></div>`;
        return;
    }
    
    let html = '';
    CART.forEach(item => {
        let displayQuantity = item.quantity;
        let primaryConversionInfo = '';
        if (item.is_secondary_unit && item.sec_unit_conversion) {
            const primaryQty = roundValue(item.quantity / parseFloat(item.sec_unit_conversion));
            primaryConversionInfo = `<br><small class="text-muted">(${primaryQty} ${item.unit_of_measure})</small>`;
        }
        const displayQtyText = `${roundValue(item.quantity)} ${item.unit}${primaryConversionInfo}`;
        const perUnitPrice = roundValue(item.total / item.quantity);
        
        html += `<div class="cart-item">
            <div class="cart-item-info">
                <h4>
                    ${item.name}
                    ${item.is_manual ? '<span class="manual-badge" style="display: inline-block; background: #FF9800; color: white; font-size: 9px; padding: 1px 5px; border-radius: 8px; margin-left: 5px; vertical-align: middle;">Manual</span>' : ''}
                </h4>
                <div class="cart-item-code">${item.code} | ${displayQtyText}</div>
                <div class="cart-item-price">₹${perUnitPrice} × ${roundValue(item.quantity)} = ₹${item.total}</div>
                ${item.discount_amount > 0 ? `<div style="font-size:11px;color:#FF9800;">Discount: ₹${item.discount_amount}</div>` : ''}
            </div>
            <div class="cart-item-controls">
                <button class="qty-btn" onclick="updateCartItemQuantity('${item.id}', -1)">-</button>
                <div class="qty-display">${roundValue(item.quantity)}</div>
                <button class="qty-btn" onclick="updateCartItemQuantity('${item.id}', 1)">+</button>
                <button class="remove-btn" onclick="removeFromCart('${item.id}')">×</button>
            </div>
        </div>`;
    });
    
    container.innerHTML = html;
}

function applyOverallDiscount() {
    const discountInput = document.getElementById('overall-discount-input');
    const discountValue = roundValue(parseFloat(discountInput.value) || 0);
    
    if (discountValue < 0) {
        showWarningToast('Discount cannot be negative');
        return;
    }
    
    const subtotal = roundValue(CART.reduce((sum, item) => sum + (item.price * item.quantity), 0));
    if (discountValue > subtotal) {
        showWarningToast('Discount cannot exceed subtotal');
        discountInput.value = '';
        return;
    }
    
    OVERALL_DISCOUNT = discountValue;
    showSuccessToast(`Overall discount applied: ₹${discountValue}`);
    updateCartSummary();
}

function calculateCartSummary() {
    const baseSubtotal = roundValue(CART.reduce((sum, item) => sum + (item.price * item.quantity), 0));
    let itemDiscount = roundValue(CART.reduce((sum, item) => sum + (Number(item.discount_amount) || 0), 0));
    
    const totalDiscount = itemDiscount + POINTS_DISCOUNT + OVERALL_DISCOUNT;
    const discountedTotal = Math.max(0, baseSubtotal - totalDiscount);
    
    let gst = 0;
    let taxable = discountedTotal;
    
    if (IS_GST_BILL && discountedTotal > 0 && baseSubtotal > 0) {
        CART.forEach(item => {
            const gstRate = (Number(item.cgst_rate) || 0) + (Number(item.sgst_rate) || 0) + (Number(item.igst_rate) || 0);
            if (gstRate > 0) {
                const itemBase = roundValue(item.price * item.quantity);
                const itemProportion = itemBase / baseSubtotal;
                const itemDiscountedValue = roundValue(discountedTotal * itemProportion);
                const itemGST = roundValue(itemDiscountedValue * (gstRate / (100 + gstRate)));
                gst += itemGST;
            }
        });
        gst = roundValue(gst);
        taxable = roundValue(discountedTotal - gst);
    }
    
    const total = roundValue(discountedTotal);
    
    return {
        subtotal: baseSubtotal,
        item_discount: itemDiscount,
        overall_discount: OVERALL_DISCOUNT,
        
        points_discount: POINTS_DISCOUNT,
        total_discount: totalDiscount,
        taxable: taxable,
        gst: gst,
        total: total
    };
}

function updateCartSummary() {
    const summary = calculateCartSummary();
    document.getElementById('subtotal').textContent = `₹${summary.subtotal}`;
    document.getElementById('item-discount').textContent = `-₹${summary.item_discount}`;
    document.getElementById('overall-discount-display').textContent = `-₹${summary.overall_discount}`;
    document.getElementById('gst-amount').textContent = `₹${summary.gst}`;
    document.getElementById('total').textContent = `₹${summary.total}`;
    document.getElementById('checkout-amount').textContent = summary.total;
    
    const totalItems = roundValue(CART.reduce((sum, item) => sum + item.quantity, 0));
    document.getElementById('item-count').textContent = totalItems;
    document.getElementById('total-items-display').textContent = `${totalItems} item${totalItems !== 1 ? 's' : ''}`;
}

// ==================== MANUAL PRODUCT FUNCTIONS ====================

function openManualProductModal() {
    const modal = document.getElementById('manual-product-modal');
    if (!modal) return;
    
    // Reset form
    document.getElementById('manual-product-name').value = '';
    document.getElementById('manual-product-code').value = '';
    document.getElementById('manual-hsn-code').value = '';
    document.getElementById('manual-quantity').value = '1';
    document.getElementById('manual-unit-price').value = '0';
    document.getElementById('manual-mrp').value = '0';
    document.getElementById('manual-discount-type').value = 'percentage';
    document.getElementById('manual-discount-value').value = '0';
    document.getElementById('manual-cgst').value = '0';
    document.getElementById('manual-sgst').value = '0';
    document.getElementById('manual-igst').value = '0';
    
    // Set default unit
    const unitSelect = document.getElementById('manual-unit-select');
    if (unitSelect) {
        unitSelect.value = 'PCS';
    }
    
    // Focus on product name
    setTimeout(() => {
        document.getElementById('manual-product-name').focus();
    }, 100);
    
    // Update preview
    updateManualProductPreview();
    
    modal.style.display = 'block';
}

function updateManualProductPreview() {
    const quantity = parseFloat(document.getElementById('manual-quantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('manual-unit-price').value) || 0;
    const discountType = document.getElementById('manual-discount-type').value;
    const discountValue = parseFloat(document.getElementById('manual-discount-value').value) || 0;
    
    const subtotal = roundValue(unitPrice * quantity);
    let discountAmount = 0;
    if (discountType === 'percentage' && discountValue > 0) {
        discountAmount = roundValue((subtotal * discountValue) / 100);
    } else if (discountType === 'fixed' && discountValue > 0) {
        discountAmount = roundValue(discountValue * quantity);
    }
    const total = subtotal - discountAmount;
    
    document.getElementById('manual-preview-subtotal').textContent = `₹${subtotal}`;
    document.getElementById('manual-preview-discount').textContent = `-₹${discountAmount}`;
    document.getElementById('manual-preview-total').textContent = `₹${total}`;
}

function addManualProductToCart() {
    // Validate required fields
    const productName = document.getElementById('manual-product-name').value.trim();
    if (!productName) {
        showWarningToast('Please enter a product name');
        document.getElementById('manual-product-name').focus();
        return;
    }
    
    const quantity = parseFloat(document.getElementById('manual-quantity').value) || 0;
    if (quantity <= 0) {
        showWarningToast('Please enter a valid quantity');
        document.getElementById('manual-quantity').focus();
        return;
    }
    
    const unitPrice = parseFloat(document.getElementById('manual-unit-price').value) || 0;
    if (unitPrice <= 0) {
        showWarningToast('Please enter a valid unit price');
        document.getElementById('manual-unit-price').focus();
        return;
    }
    
    // Get values
    const productCode = document.getElementById('manual-product-code').value.trim() || `MAN-${Date.now()}`;
    const unit = document.getElementById('manual-unit-select').value;
    const mrp = parseFloat(document.getElementById('manual-mrp').value) || unitPrice;
    const discountType = document.getElementById('manual-discount-type').value;
    const discountValue = parseFloat(document.getElementById('manual-discount-value').value) || 0;
    const cgst = parseFloat(document.getElementById('manual-cgst').value) || 0;
    const sgst = parseFloat(document.getElementById('manual-sgst').value) || 0;
    const igst = parseFloat(document.getElementById('manual-igst').value) || 0;
    const hsnCode = document.getElementById('manual-hsn-code').value.trim() || '';
    
    // Calculate totals
    const subtotal = roundValue(unitPrice * quantity);
    let discountAmount = 0;
    if (discountType === 'percentage' && discountValue > 0) {
        discountAmount = roundValue((subtotal * discountValue) / 100);
    } else if (discountType === 'fixed' && discountValue > 0) {
        discountAmount = roundValue(discountValue * quantity);
    }
    const total = subtotal - discountAmount;
    
    // Create manual product object
    const manualProductId = `manual-${Date.now()}-${Math.random().toString(36).substr(2, 6)}`;
    
    const manualProduct = {
        id: manualProductId,
        product_id: 0, // 0 indicates manual product
        name: productName,
        code: productCode,
        price: unitPrice,
        price_type: 'retail',
        quantity: quantity,
        unit: unit,
        discount_type: discountType,
        discount_value: discountValue,
        discount_amount: discountAmount,
        total: total,
        mrp: mrp,
        cgst_rate: cgst,
        sgst_rate: sgst,
        igst_rate: igst,
        hsn_code: hsnCode,
        shop_stock: 999999, // Unlimited for manual products
        unit_of_measure: unit,
        secondary_unit: null,
        sec_unit_conversion: null,
        stock_price: unitPrice,
        is_secondary_unit: false,
        quantity_in_primary: quantity,
        is_manual: true // Flag to identify manual products
    };
    
    // Add to cart
    CART.push(manualProduct);
    
    // Save and update UI
    saveCartToSession();
    updateUI();
    
    // Close modal
    document.getElementById('manual-product-modal').style.display = 'none';
    
    showSuccessToast(`Manual product "${productName}" added to quotation`);
}

function setupManualProductEventListeners() {
    // Open modal button
    const manualBtn = document.getElementById('manual-product-btn');
    if (manualBtn) {
        manualBtn.onclick = openManualProductModal;
    }
    
    // Cancel button
    const cancelManualBtn = document.getElementById('cancel-manual-btn');
    if (cancelManualBtn) {
        cancelManualBtn.onclick = () => {
            document.getElementById('manual-product-modal').style.display = 'none';
        };
    }
    
    // Add button
    const addManualBtn = document.getElementById('add-manual-product-btn');
    if (addManualBtn) {
        addManualBtn.onclick = addManualProductToCart;
    }
    
    // Preview update listeners
    const previewInputs = [
        'manual-quantity', 'manual-unit-price', 'manual-discount-value',
        'manual-discount-type', 'manual-cgst', 'manual-sgst', 'manual-igst'
    ];
    
    previewInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', updateManualProductPreview);
            if (input.tagName === 'SELECT') {
                input.addEventListener('change', updateManualProductPreview);
            }
        }
    });
    
    // Enter key support
    const nameInput = document.getElementById('manual-product-name');
    if (nameInput) {
        nameInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('manual-unit-price').focus();
            }
        });
    }
    
    const priceInput = document.getElementById('manual-unit-price');
    if (priceInput) {
        priceInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addManualProductToCart();
            }
        });
    }
    
    const quantityInput = document.getElementById('manual-quantity');
    if (quantityInput) {
        quantityInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('manual-unit-price').focus();
            }
        });
    }
}

// ==================== PAYMENT MODAL ====================
function showPaymentModal() {
    const modal = document.getElementById('payment-modal');
    if (!modal) return;
    
    const summary = calculateCartSummary();
    document.getElementById('payment-total').textContent = summary.total;
    updatePaymentCustomerInfo();
    
    // Reset select2 values properly
    if ($('#payment-site-select').length) {
        $('#payment-site-select').val('').trigger('change');
    }
    if ($('#payment-engineer-select').length) {
        $('#payment-engineer-select').val('').trigger('change');
    }
    
    ['cash-payment', 'upi-payment', 'bank-payment', 'credit-payment'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });
    
    document.getElementById('payment-upi-reference').value = '';
    document.getElementById('payment-bank-reference').value = '';
    document.getElementById('payment-cheque-number').value = '';
    document.getElementById('total-paid').value = '0';
    document.getElementById('change-amount').value = '0';
    
    modal.style.display = 'block';
    document.getElementById('cash-payment').focus();
}

function autofillPayment() {
    const summary = calculateCartSummary();
    const total = summary.total;
    document.getElementById('cash-payment').value = total;
    updatePaymentTotals();
    showSuccessToast(`Payment autofilled with ₹${total}`);
}

function updatePaymentTotals() {
    const cash = roundValue(parseFloat(document.getElementById('cash-payment').value) || 0);
    const upi = roundValue(parseFloat(document.getElementById('upi-payment').value) || 0);
    const bank = roundValue(parseFloat(document.getElementById('bank-payment').value) || 0);
    const credit = roundValue(parseFloat(document.getElementById('credit-payment').value) || 0);
    
    const totalPaid = cash + upi + bank + credit;
    const totalAmount = roundValue(parseFloat(document.getElementById('payment-total').textContent));
    const change = Math.max(0, totalPaid - totalAmount);
    
    document.getElementById('total-paid').value = totalPaid;
    document.getElementById('change-amount').value = change;
}

// ==================== INVOICE FUNCTIONS ====================
function printInvoiceToConsole(invoiceData) {
    console.log('=================================');
    console.log('      INVOICE DETAILS');
    console.log('=================================');
    console.log('Invoice Number:', invoiceData.invoice_number);
    console.log('Date:', new Date().toLocaleString());
    console.log('---------------------------------');
    console.log('Customer:', invoiceData.customer_name);
    console.log('Phone:', invoiceData.customer_phone);
    console.log('GSTIN:', invoiceData.customer_gstin);
    console.log('---------------------------------');
    console.log('Items:');
    invoiceData.items.forEach((item, index) => {
        console.log(`${index + 1}. ${item.product_name} - ${item.quantity} ${item.unit} x ₹${item.price} = ₹${item.total}`);
    });
    console.log('---------------------------------');
    console.log('Subtotal:', invoiceData.subtotal);
    console.log('Discount:', invoiceData.discount);
    console.log('GST:', invoiceData.gst);
    console.log('Total:', invoiceData.grand_total);
    console.log('---------------------------------');
    console.log('Payment:');
    if (invoiceData.payment_details.cash > 0) console.log('Cash: ₹' + invoiceData.payment_details.cash);
    if (invoiceData.payment_details.upi > 0) console.log('UPI: ₹' + invoiceData.payment_details.upi);
    if (invoiceData.payment_details.bank > 0) console.log('Bank: ₹' + invoiceData.payment_details.bank);
    if (invoiceData.payment_details.credit > 0) console.log('Credit: ₹' + invoiceData.payment_details.credit);
    console.log('---------------------------------');
    console.log('Site:', invoiceData.site);
    console.log('Referral:', invoiceData.referral_name || 'None');
    console.log('=================================');
}

async function processPayment(action = 'save') {
    const summary = calculateCartSummary();
    const totalAmount = summary.total;
    
    const cash = roundValue(parseFloat(document.getElementById('cash-payment').value) || 0);
    const upi = roundValue(parseFloat(document.getElementById('upi-payment').value) || 0);
    const bank = roundValue(parseFloat(document.getElementById('bank-payment').value) || 0);
    const credit = roundValue(parseFloat(document.getElementById('credit-payment').value) || 0);
    
    // Get engineer selection
    const engineerSelect = document.getElementById('payment-engineer-select');
    const selectedEngineerId = engineerSelect && engineerSelect.value ? engineerSelect.value : null;
    
    // Get site selection
    const siteSelect = document.getElementById('payment-site-select');
    const selectedSiteId = siteSelect && siteSelect.value ? siteSelect.value : null;
    
    const totalPaid = cash + upi + bank + credit;
    const pending = totalAmount - totalPaid;
    const change = Math.max(0, totalPaid - totalAmount);
    
    const upiReference = document.getElementById('payment-upi-reference').value.trim();
    const bankReference = document.getElementById('payment-bank-reference').value.trim();
    const chequeNumber = document.getElementById('payment-cheque-number').value.trim();
    
    if (pending > 0) {
        showWarningToast(`Insufficient payment. Pending: ₹${pending}`);
        return;
    }
    
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: 'Saving your invoice...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const invoiceResult = await saveInvoice(action, {
            cash, upi, bank, credit, change,
            upi_reference: upiReference,
            bank_reference: bankReference,
            cheque_number: chequeNumber,
            site_id: selectedSiteId,
            engineer_id: selectedEngineerId
        });
        
        printInvoiceToConsole(invoiceResult);
        
        let paymentMethod = '';
        if (cash > 0) paymentMethod += `Cash: ₹${cash} `;
        if (upi > 0) paymentMethod += `UPI: ₹${upi} `;
        if (bank > 0) paymentMethod += `Bank: ₹${bank} `;
        if (credit > 0) paymentMethod += `Credit: ₹${credit} `;
        
        const customerName = CURRENT_CUSTOMER ? CURRENT_CUSTOMER.name : 'Walk-in Customer';
        let actionText = 'Saved';
        if (action === 'print') actionText = 'Saved & Printed (A4)';
        else if (action === 'thermal') actionText = 'Saved & Thermal Printed';
        
        // Close payment modal first
        document.getElementById('payment-modal').style.display = 'none';
        
        // Show success SweetAlert with longer duration
        await Swal.fire({
            icon: 'success',
            title: 'Success!',
            html: `<strong>${actionText}</strong><br>Customer: ${customerName}<br>Total: ₹${totalAmount}<br>Paid: ₹${totalPaid}${change > 0 ? `<br>Change: ₹${change}` : ''}<br><br>Thank you for your business!<br><br><small>Page will refresh in 3 seconds...</small>`,
            showConfirmButton: true,
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true,
            allowOutsideClick: false
        });
        
        // Complete the sale (clear cart, reset)
        completeSale();
        
        // Refresh after SweetAlert is closed
        setTimeout(() => {
            location.reload();
        }, 500);
        
    } catch (error) {
        Swal.close(); // Close loading
        showErrorToast('Payment failed: ' + error.message);
    }
}

async function saveInvoice(action = 'save', paymentDetails = {}) {
    const summary = calculateCartSummary();
    const invoiceNumber = await getNextInvoiceNumber(IS_GST_BILL ? 'gst' : 'non-gst');
    
    let totalReferralCommission = 0;
    let referralName = '';
    let referralCode = '';
    
    if (SELECTED_REFERRAL_ID && SELECTED_REFERRAL_ID !== 'none') {
        const selectedReferral = REFERRALS.find(r => r.id == SELECTED_REFERRAL_ID);
        if (selectedReferral) {
            referralName = selectedReferral.name || '';
            referralCode = selectedReferral.code || '';
            CART.forEach(item => {
                // Skip manual products for referral commission
                if (!item.is_manual) {
                    const product = PRODUCTS.find(p => p.id == item.product_id);
                    if (product && product.referral_enabled == 1 && product.referral_value > 0) {
                        if (product.referral_type === 'percentage') {
                            totalReferralCommission += roundValue((item.total * product.referral_value) / 100);
                        } else {
                            totalReferralCommission += roundValue(product.referral_value * item.quantity);
                        }
                    }
                }
            });
        }
    }
    
    const invoiceData = {
        customer_id: CURRENT_CUSTOMER && CURRENT_CUSTOMER.id !== 'walk-in' ? CURRENT_CUSTOMER.id : null,
        customer_name: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.name : 'Walk-in Customer',
        customer_phone: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.contact : '',
        customer_gstin: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.gstin : '',
        customer_address: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.address : '',
        invoice_number: invoiceNumber,
        invoice_type: IS_GST_BILL ? 'gst' : 'non-gst',
        subtotal: summary.subtotal,
        discount: OVERALL_DISCOUNT,
        discount_type: 'fixed',
        overall_discount: summary.total_discount,
        grand_total: summary.total,
        gst: summary.gst,
        points_used: roundValue(POINTS_DISCOUNT / LOYALTY_RATE),
        points_discount: POINTS_DISCOUNT,
        engineer_id: paymentDetails.engineer_id ? parseInt(paymentDetails.engineer_id) : null,
        site_id: paymentDetails.site_id ? parseInt(paymentDetails.site_id) : null,
        items: CART.map(item => {
            const product = !item.is_manual ? PRODUCTS.find(p => p.id == item.product_id) : null;
            return {
                product_id: item.product_id,
                product_name: item.name,
                quantity: roundValue(item.quantity),
                price: item.price,
                unit: item.unit,
                is_secondary_unit: item.is_secondary_unit || false,
                sec_unit_conversion: item.sec_unit_conversion || 1,
                discount_type: item.discount_type || 'percentage',
                discount_value: item.discount_value || 0,
                discount_amount: item.discount_amount || 0,
                price_type: item.price_type,
                base_price: item.stock_price || item.price,
                hsn_code: item.hsn_code,
                cgst_rate: item.cgst_rate || 0,
                sgst_rate: item.sgst_rate || 0,
                igst_rate: item.igst_rate || 0,
                stock_price: item.stock_price || 0,
                total: item.total,
                is_manual: item.is_manual || false
            };
        }),
        payment_details: {
            cash: paymentDetails.cash || 0,
            upi: paymentDetails.upi || 0,
            bank: paymentDetails.bank || 0,
            credit: paymentDetails.credit || 0,
            change: paymentDetails.change || 0,
            upi_reference: paymentDetails.upi_reference || '',
            bank_reference: paymentDetails.bank_reference || '',
            cheque_number: paymentDetails.cheque_number || ''
        },
        pending_amount: Math.max(0, summary.total - (paymentDetails.cash || 0 + paymentDetails.upi || 0 + paymentDetails.bank || 0 + paymentDetails.credit || 0)),
        referral_id: SELECTED_REFERRAL_ID !== 'none' ? SELECTED_REFERRAL_ID : null,
        referral_code: referralCode,
        referral_name: referralName,
        referral_commission: totalReferralCommission
    };
    
    let endpoint = 'save';
    if (action === 'print') endpoint = 'save_for_print';
    else if (action === 'thermal') endpoint = 'save_for_thermal';
    
    try {
        const response = await fetch(`${API_INVOICES}?action=${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(invoiceData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (action === 'print' && data.print_url) {
                window.open(data.print_url, '_blank');
            } else if (action === 'thermal' && data.thermal_url) {
                window.open(data.thermal_url, '_blank');
            }
            
            showSuccessToast(`Invoice ${invoiceNumber} saved successfully!`);
            return invoiceData;
        } else {
            throw new Error(data.message || 'Failed to save invoice');
        }
    } catch (error) {
        console.error('Error saving invoice:', error);
        throw error;
    }
}

function completeSale() {
    CART = [];
    OVERALL_DISCOUNT = 0;
    POINTS_DISCOUNT = 0;
    
    const discountInput = document.getElementById('overall-discount-input');
    if (discountInput) discountInput.value = '';
    
    saveCartToSession();
    updateUI();
    
    // Reset to walk-in customer
    resetToWalkInCustomer();
}

// ==================== REFERRAL FUNCTIONS ====================
async function loadReferrals() {
    try {
        const response = await fetch('api/referrals.php?action=list');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();
        
        if (data.success && data.referrals && data.referrals.length > 0) {
            REFERRALS = data.referrals.map(referral => ({
                id: referral.id,
                code: referral.referral_code,
                name: referral.full_name,
                phone: referral.phone || '',
                email: referral.email || '',
                commission_percent: roundValue(parseFloat(referral.commission_percent) || 0),
                is_active: parseInt(referral.is_active) || 0
            }));
            console.log('Referrals loaded:', REFERRALS.length);
            return true;
        } else {
            REFERRALS = [];
            return true;
        }
    } catch (error) {
        console.error('Error loading referrals:', error);
        REFERRALS = [];
        showWarningToast('Could not load referral list');
        return false;
    }
}

function openReferralModal() {
    const modal = document.getElementById('referral-modal');
    if (!modal) return;
    renderReferralOptions();
    modal.style.display = 'block';
}

function renderReferralOptions() {
    const container = document.getElementById('referral-options-list');
    if (!container) return;
    
    let html = `<div class="referral-option" data-referral-id="none" data-referral-code="" data-referral-name="No Referral"><i class="fas fa-user"></i> No Referral</div>`;
    
    const activeReferrals = REFERRALS.filter(ref => ref.is_active === 1);
    activeReferrals.forEach(referral => {
        html += `<div class="referral-option" data-referral-id="${referral.id}" data-referral-code="${referral.code}" data-referral-name="${referral.name}"><i class="fas fa-user-friends"></i> ${referral.name}${referral.code ? `<br><small>${referral.code}</small>` : ''}</div>`;
    });
    
    container.innerHTML = html;
    
    document.querySelectorAll('.referral-option').forEach(option => {
        option.onclick = function() {
            document.querySelectorAll('.referral-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            SELECTED_REFERRAL_ID = this.getAttribute('data-referral-id');
            SELECTED_REFERRAL_CODE = this.getAttribute('data-referral-code') || '';
            SELECTED_REFERRAL_NAME = this.getAttribute('data-referral-name') || 'No Referral';
        };
    });
    
    document.querySelectorAll('.referral-option').forEach(opt => {
        if (opt.getAttribute('data-referral-id') === (SELECTED_REFERRAL_ID || 'none')) {
            opt.classList.add('selected');
        }
    });
}

function updateReferralDisplay() {
    const referralDisplay = document.getElementById('referral-display');
    if (!referralDisplay) return;
    let displayText = 'Referral: ';
    if (SELECTED_REFERRAL_ID && SELECTED_REFERRAL_ID !== 'none' && SELECTED_REFERRAL_NAME) {
        displayText += SELECTED_REFERRAL_NAME;
        if (SELECTED_REFERRAL_CODE) displayText += ` (${SELECTED_REFERRAL_CODE})`;
    } else {
        displayText += 'None';
    }
    referralDisplay.textContent = displayText;
}

// ==================== CUSTOMER MODAL ====================
function openCustomerModal() {
    const modal = document.getElementById('customer-modal');
    if (modal) {
        document.getElementById('customer-name-input').value = '';
        document.getElementById('customer-phone-input').value = '';
        document.getElementById('customer-email-input').value = '';
        document.getElementById('customer-address-input').value = '';
        document.getElementById('customer-gstin-input').value = '';
        document.getElementById('customer-credit-input').value = '0';
        document.getElementById('customer-type-input').value = 'retail';
        modal.style.display = 'block';
        document.getElementById('customer-name-input').focus();
    }
}

async function saveCustomerModal() {
    const name = document.getElementById('customer-name-input').value.trim();
    const phone = document.getElementById('customer-phone-input').value.trim();
    const email = document.getElementById('customer-email-input').value.trim();
    const address = document.getElementById('customer-address-input').value.trim();
    const gstin = document.getElementById('customer-gstin-input').value.trim();
    const creditLimit = roundValue(parseFloat(document.getElementById('customer-credit-input').value) || 0);
    const type = document.getElementById('customer-type-input').value;
    
    if (!name) {
        showWarningToast('Customer name is required');
        document.getElementById('customer-name-input').focus();
        return;
    }
    if (!phone) {
        showWarningToast('Phone number is required');
        document.getElementById('customer-phone-input').focus();
        return;
    }
    
    const customerData = { name, phone, email, address, gstin, credit_limit: creditLimit, customer_type: type };
    
    try {
        const customerId = await saveCustomer(customerData);
        const newCustomer = { id: customerId, name, contact: phone, email, gstin, address, type, credit_limit: creditLimit, outstanding: 0, points: 0 };
        CUSTOMERS.push(newCustomer);
        updateCustomerDropdown();
        $('#customer-type').val(customerId).trigger('change');
        document.getElementById('customer-modal').style.display = 'none';
        showSuccessToast(`Customer saved: ${name}`);
    } catch (error) {
        showErrorToast('Failed to save customer: ' + error.message);
    }
}

async function saveCustomer(customerData) {
    const response = await fetch(`${API_CUSTOMERS}?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(customerData)
    });
    const data = await response.json();
    if (data.success) return data.customer_id;
    else throw new Error(data.message || 'Failed to save customer');
}

function updateCustomerDropdown() {
    const select = $('#customer-type');
    select.empty();
    select.append(new Option('Walk-in Customer', 'walk-in'));
    CUSTOMERS.forEach(customer => {
        if (customer.id === 'walk-in') return;
        select.append(new Option(customer.name + (customer.contact ? ` (${customer.contact})` : ''), customer.id));
    });
    select.trigger('change.select2');
}

function updatePaymentCustomerInfo() {
    const nameField = document.getElementById('payment-customer-name');
    const contactField = document.getElementById('payment-customer-contact');
    const emailField = document.getElementById('payment-customer-email');
    const gstinField = document.getElementById('payment-customer-gstin');
    const addressField = document.getElementById('payment-customer-address');
    
    if (CURRENT_CUSTOMER) {
        nameField.value = CURRENT_CUSTOMER.name || '';
        contactField.value = CURRENT_CUSTOMER.contact || '';
        emailField.value = CURRENT_CUSTOMER.email || '';
        gstinField.value = CURRENT_CUSTOMER.gstin || '';
        addressField.value = CURRENT_CUSTOMER.address || '';
    } else {
        nameField.value = ''; contactField.value = ''; emailField.value = ''; gstinField.value = ''; addressField.value = '';
    }
    
    IS_EDITING_CUSTOMER_INFO = false;
    const toggleBtn = document.getElementById('toggle-edit-btn');
    if (toggleBtn) {
        toggleBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
        toggleBtn.classList.remove('save-mode');
    }
    
    [nameField, contactField, emailField, gstinField, addressField].forEach(field => {
        if (field) {
            field.setAttribute('readonly', true);
            field.style.background = '#f5f5f5';
        }
    });
}

function toggleCustomerEditMode() {
    IS_EDITING_CUSTOMER_INFO = !IS_EDITING_CUSTOMER_INFO;
    const toggleBtn = document.getElementById('toggle-edit-btn');
    const fields = ['payment-customer-name', 'payment-customer-contact', 'payment-customer-email', 'payment-customer-gstin', 'payment-customer-address'];
    
    if (IS_EDITING_CUSTOMER_INFO) {
        toggleBtn.innerHTML = '<i class="fas fa-check"></i> Save';
        toggleBtn.classList.add('save-mode');
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.removeAttribute('readonly');
                field.style.background = '#fff';
            }
        });
        document.getElementById('payment-customer-name').focus();
    } else {
        toggleBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
        toggleBtn.classList.remove('save-mode');
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.setAttribute('readonly', true);
                field.style.background = '#f5f5f5';
            }
        });
        updateCustomerFromPaymentForm();
    }
}

function updateCustomerFromPaymentForm() {
    const name = document.getElementById('payment-customer-name').value.trim();
    const contact = document.getElementById('payment-customer-contact').value.trim();
    const email = document.getElementById('payment-customer-email').value.trim();
    const gstin = document.getElementById('payment-customer-gstin').value.trim();
    const address = document.getElementById('payment-customer-address').value.trim();
    
    if (!CURRENT_CUSTOMER || CURRENT_CUSTOMER.id === 'walk-in') {
        if (name && contact) {
            CURRENT_CUSTOMER = {
                id: 'temp-' + Date.now(), name, contact, email, gstin, address,
                type: 'retail', credit_limit: 0, outstanding: 0, points: 0
            };
            updateCustomerDropdown();
            $('#customer-type').val(CURRENT_CUSTOMER.id).trigger('change');
            showSuccessToast('New customer information saved');
        }
    } else {
        CURRENT_CUSTOMER.name = name;
        CURRENT_CUSTOMER.contact = contact;
        CURRENT_CUSTOMER.email = email;
        CURRENT_CUSTOMER.gstin = gstin;
        CURRENT_CUSTOMER.address = address;
        showSuccessToast('Customer information updated');
    }
}

// ==================== HOLD FUNCTIONS ====================
async function openHoldModal() {
    const modal = document.getElementById('hold-modal');
    if (!modal) return;
    
    const summary = calculateCartSummary();
    const holdNumber = await getNextHoldNumber();
    document.getElementById('hold-number-input').value = holdNumber;
    document.getElementById('hold-item-count').textContent = CART.length;
    document.getElementById('hold-subtotal').textContent = summary.subtotal;
    document.getElementById('hold-total').textContent = summary.total;
    
    const reference = CURRENT_CUSTOMER ? CURRENT_CUSTOMER.name : 'Walk-in Customer';
    document.getElementById('hold-reference-input').value = reference;
    
    modal.style.display = 'block';
    document.getElementById('hold-reference-input').focus();
}

async function getNextHoldNumber() {
    try {
        const response = await fetch(`${API_HOLDS}?action=get_next_number`);
        const data = await response.json();
        if (data.success) return data.hold_number;
    } catch (error) {
        console.error('Error getting hold number:', error);
    }
    const yearMonth = new Date().getFullYear().toString().slice(-2) + ('0' + (new Date().getMonth() + 1)).slice(-2);
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `HOLD${yearMonth}-${random}`;
}

async function saveHold() {
    const reference = document.getElementById('hold-reference-input').value.trim();
    const holdNumber = document.getElementById('hold-number-input').value;
    
    if (!reference) {
        showWarningToast('Please enter a reference name');
        return;
    }
    
    const summary = calculateCartSummary();
    const holdData = {
        hold_number: holdNumber, reference,
        customer_name: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.name : 'Walk-in Customer',
        customer_phone: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.contact : '',
        customer_gstin: CURRENT_CUSTOMER ? CURRENT_CUSTOMER.gstin : '',
        subtotal: summary.subtotal, total: summary.total,
        cart_items: CART.map(item => ({
            product_id: item.product_id, product_name: item.name, quantity: roundValue(item.quantity),
            price_type: item.price_type, unit_price: item.price, item_discount: item.discount_amount,
            item_discount_type: item.discount_type, total: item.total, is_secondary_unit: item.unit === item.secondary_unit,
            secondary_unit_qty: item.unit === item.secondary_unit ? roundValue(item.quantity) : null,
            secondary_unit: item.secondary_unit, unit: item.unit_of_measure,
            is_manual: item.is_manual || false,
            manual_product_data: item.is_manual ? item : null
        })),
        cart_json: CART
    };
    
    try {
        const response = await fetch(`${API_HOLDS}?action=save`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(holdData)
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('hold-modal').style.display = 'none';
            CART = []; OVERALL_DISCOUNT = 0;
            document.getElementById('overall-discount-input').value = '';
            saveCartToSession(); updateUI();
            showSuccessToast(`Invoice held! Hold #: ${holdNumber}`);
        } else {
            throw new Error(data.message || 'Failed to save hold');
        }
    } catch (error) {
        showErrorToast('Failed to save hold: ' + error.message);
    }
}

async function loadHoldsList() {
    try {
        const response = await fetch(`${API_HOLDS}?action=list`);
        const data = await response.json();
        const table = document.getElementById('holds-list-table');
        const noHoldsMessage = document.getElementById('no-holds-message');
        
        if (data.success && data.holds && data.holds.length > 0) {
            HOLDS = data.holds;
            table.innerHTML = '';
            
            data.holds.forEach(hold => {
                const createdDate = new Date(hold.created_at).toLocaleDateString();
                const expiryDate = new Date(hold.expiry_at).toLocaleDateString();
                const isExpired = new Date(hold.expiry_at) < new Date();
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${hold.hold_number}</strong>\\
                    <td>${hold.customer_name}\\
                    <td>${hold.item_count || 1}\\
                    <td>₹${roundValue(hold.total)}\\
                    <td>${createdDate}\\
                    <td><button class="btn btn-sm btn-primary retrieve-hold-btn" data-hold-id="${hold.id}" ${isExpired ? 'disabled' : ''}><i class="fas fa-shopping-cart"></i></button>
                    <button class="btn btn-sm btn-danger delete-hold-btn" data-hold-id="${hold.id}"><i class="fas fa-trash"></i></button>\\
                `;
                table.appendChild(row);
            });
            
            table.style.display = 'table';
            noHoldsMessage.style.display = 'none';
            
            document.querySelectorAll('.retrieve-hold-btn').forEach(btn => {
                btn.onclick = function() { openRetrieveHoldModal(this.getAttribute('data-hold-id')); };
            });
            document.querySelectorAll('.delete-hold-btn').forEach(btn => {
                btn.onclick = function() { deleteHold(this.getAttribute('data-hold-id')); };
            });
        } else {
            table.style.display = 'none';
            noHoldsMessage.style.display = 'block';
            HOLDS = [];
        }
    } catch (error) {
        console.error('Error loading holds:', error);
        showErrorToast('Failed to load held invoices');
    }
}

function openRetrieveHoldModal(holdId) {
    const hold = HOLDS.find(h => h.id == holdId);
    if (!hold) return;
    CURRENT_HOLD = hold;
    
    const modal = document.getElementById('retrieve-hold-modal');
    const infoDiv = document.getElementById('retrieve-hold-info');
    const createdDate = new Date(hold.created_at).toLocaleString();
    const expiryDate = new Date(hold.expiry_at).toLocaleString();
    const isExpired = new Date(hold.expiry_at) < new Date();
    
    infoDiv.innerHTML = `<div><strong>Hold #:</strong> ${hold.hold_number}<br><strong>Customer:</strong> ${hold.customer_name}<br><strong>Created:</strong> ${createdDate}<br><strong>Expires:</strong> ${expiryDate}<br><strong>Total:</strong> ₹${roundValue(hold.total)}</div>${isExpired ? '<div style="color: #f44336;">⚠️ Expired</div>' : ''}<div style="margin-top: 10px;">Loading will replace current cart.</div>`;
    
    modal.style.display = 'block';
}

function retrieveHold() {
    if (!CURRENT_HOLD) return;
    try {
        const cartJson = JSON.parse(CURRENT_HOLD.cart_json);
        CART = [];
        cartJson.forEach(item => {
            CART.push({
                id: item.id,
                product_id: item.product_id,
                name: item.name,
                code: item.code,
                price: item.price,
                price_type: item.price_type,
                quantity: item.quantity,
                unit: item.unit,
                discount_type: item.discount_type,
                discount_value: item.discount_value,
                discount_amount: item.discount_amount,
                total: item.total,
                mrp: item.mrp,
                cgst_rate: item.cgst_rate,
                sgst_rate: item.sgst_rate,
                igst_rate: item.igst_rate,
                hsn_code: item.hsn_code,
                shop_stock: item.shop_stock,
                unit_of_measure: item.unit_of_measure,
                secondary_unit: item.secondary_unit,
                sec_unit_conversion: item.sec_unit_conversion,
                stock_price: item.stock_price,
                is_secondary_unit: item.is_secondary_unit,
                quantity_in_primary: item.quantity_in_primary,
                is_manual: item.is_manual || false
            });
        });
        saveCartToSession(); updateUI();
        document.getElementById('retrieve-hold-modal').style.display = 'none';
        document.getElementById('holds-list-modal').style.display = 'none';
        showSuccessToast(`Hold #${CURRENT_HOLD.hold_number} loaded`);
        CURRENT_HOLD = null;
    } catch (error) {
        console.error('Error retrieving hold:', error);
        showErrorToast('Failed to retrieve hold');
    }
}

async function deleteHold(holdId) {
    const result = await showConfirmDialog('Delete Hold', 'Are you sure?', 'warning');
    if (!result.isConfirmed) return;
    
    try {
        const response = await fetch(`${API_HOLDS}?action=delete`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ hold_id: holdId })
        });
        const data = await response.json();
        if (data.success) {
            showSuccessToast('Hold deleted');
            loadHoldsList();
        } else {
            throw new Error(data.message || 'Failed to delete hold');
        }
    } catch (error) {
        showErrorToast('Failed to delete hold: ' + error.message);
    }
}

// ==================== QUOTATION FUNCTIONS ====================
async function openQuotationModal() {
    const modal = document.getElementById('quotation-modal');
    if (!modal) return;
    
    const summary = calculateCartSummary();
    const quotationNumber = await getNextQuotationNumber();
    document.getElementById('quotation-number-input').value = quotationNumber;
    document.getElementById('quotation-item-count').textContent = CART.length;
    document.getElementById('quotation-subtotal').textContent = summary.subtotal;
    document.getElementById('quotation-total').textContent = summary.total;
    
    if (CURRENT_CUSTOMER) {
        document.getElementById('quotation-customer-name').value = CURRENT_CUSTOMER.name;
        document.getElementById('quotation-customer-phone').value = CURRENT_CUSTOMER.contact || '';
        document.getElementById('quotation-customer-email').value = CURRENT_CUSTOMER.email || '';
        document.getElementById('quotation-customer-gstin').value = CURRENT_CUSTOMER.gstin || '';
        document.getElementById('quotation-customer-address').value = CURRENT_CUSTOMER.address || '';
    } else {
        document.getElementById('quotation-customer-name').value = 'Walk-in Customer';
        document.getElementById('quotation-customer-phone').value = '';
        document.getElementById('quotation-customer-email').value = '';
        document.getElementById('quotation-customer-gstin').value = '';
        document.getElementById('quotation-customer-address').value = '';
    }
    
    modal.style.display = 'block';
    document.getElementById('quotation-customer-name').focus();
}

async function getNextQuotationNumber() {
    try {
        const response = await fetch(`${API_QUOTATIONS}?action=get_next_number`);
        const data = await response.json();
        if (data.success) return data.quotation_number;
    } catch (error) {
        console.error('Error getting quotation number:', error);
    }
    const yearMonth = new Date().getFullYear().toString().slice(-2) + ('0' + (new Date().getMonth() + 1)).slice(-2);
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `QTN${yearMonth}-${random}`;
}

async function saveQuotation() {
    const customerName = document.getElementById('quotation-customer-name').value.trim();
    const customerPhone = document.getElementById('quotation-customer-phone').value.trim();
    const customerEmail = document.getElementById('quotation-customer-email').value.trim();
    const customerGstin = document.getElementById('quotation-customer-gstin').value.trim();
    const customerAddress = document.getElementById('quotation-customer-address').value.trim();
    const quotationNumber = document.getElementById('quotation-number-input').value;
    const validDays = parseInt(document.getElementById('quotation-valid-days').value) || 15;
    const notes = document.getElementById('quotation-notes').value.trim();
    
    if (!customerName) {
        showWarningToast('Customer name is required');
        return;
    }
    
    const summary = calculateCartSummary();
    const today = new Date();
    const validUntil = new Date(today);
    validUntil.setDate(today.getDate() + validDays);
    
    const quotationData = {
        quotation_number: quotationNumber,
        quotation_date: today.toISOString().split('T')[0],
        valid_until: validUntil.toISOString().split('T')[0],
        customer_name: customerName, customer_phone: customerPhone, customer_email: customerEmail,
        customer_gstin: customerGstin, customer_address: customerAddress,
        subtotal: summary.subtotal, total_discount: summary.total_discount,
        total_tax: summary.gst, grand_total: summary.total, notes: notes,
        items: CART.map(item => ({
            product_id: item.is_manual ? 0 : item.product_id,
            product_name: item.name, quantity: roundValue(item.quantity),
            unit_price: item.price, discount_amount: item.discount_amount,
            discount_type: item.discount_type, total_price: item.total,
            hsn_code: item.hsn_code, cgst_rate: item.cgst_rate, sgst_rate: item.sgst_rate,
            igst_rate: item.igst_rate, price_type: item.price_type,
            is_manual: item.is_manual || false,
            unit: item.unit
        }))
    };
    
    try {
        const response = await fetch(`${API_QUOTATIONS}?action=save`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(quotationData)
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('quotation-modal').style.display = 'none';
            CART = []; OVERALL_DISCOUNT = 0;
            document.getElementById('overall-discount-input').value = '';
            saveCartToSession(); updateUI();
            showSuccessToast(`Quotation #${quotationNumber} saved!`);
        } else {
            throw new Error(data.message || 'Failed to save quotation');
        }
    } catch (error) {
        showErrorToast('Failed to save quotation: ' + error.message);
    }
}

async function loadQuotationsList() {
    try {
        const response = await fetch(`${API_QUOTATIONS}?action=list`);
        const data = await response.json();
        const table = document.getElementById('quotations-list-table');
        const noQuotationsMessage = document.getElementById('no-quotations-message');
        
        if (data.success && data.quotations && data.quotations.length > 0) {
            QUOTATIONS = data.quotations;
            table.innerHTML = '';
            
            data.quotations.forEach(quotation => {
                const createdDate = new Date(quotation.quotation_date).toLocaleDateString();
                const validUntil = new Date(quotation.valid_until).toLocaleDateString();
                const today = new Date();
                const isExpired = new Date(quotation.valid_until) < today;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${quotation.quotation_number}</strong>\\
                    <td>${quotation.customer_name}\\
                    <td>${createdDate}\\
                    <td>${validUntil}\\
                    <td>₹${roundValue(quotation.grand_total)}\\
                    <td><span>${quotation.status || 'draft'}</span>\\
                    <td>
                        <button class="btn btn-sm btn-primary retrieve-quotation-btn" data-quotation-id="${quotation.id}"><i class="fas fa-shopping-cart"></i></button>
                        <button class="btn btn-sm btn-info view-quotation-btn" data-quotation-id="${quotation.id}"><i class="fas fa-print"></i></button>
                        <button class="btn btn-sm btn-danger delete-quotation-btn" data-quotation-id="${quotation.id}"><i class="fas fa-trash"></i></button>
                     \\
                `;
                table.appendChild(row);
            });
            
            table.style.display = 'table';
            noQuotationsMessage.style.display = 'none';
            
            document.querySelectorAll('.retrieve-quotation-btn').forEach(btn => {
                btn.onclick = function() { openRetrieveQuotationModal(this.getAttribute('data-quotation-id')); };
            });
            document.querySelectorAll('.view-quotation-btn').forEach(btn => {
                btn.onclick = function() { viewQuotation(this.getAttribute('data-quotation-id')); };
            });
            document.querySelectorAll('.delete-quotation-btn').forEach(btn => {
                btn.onclick = function() { deleteQuotation(this.getAttribute('data-quotation-id')); };
            });
        } else {
            table.innerHTML = '|<td colspan="7" style="text-align: center;">No quotations found</td>|';
            noQuotationsMessage.style.display = 'block';
            QUOTATIONS = [];
        }
    } catch (error) {
        console.error('Error loading quotations:', error);
        showErrorToast('Failed to load quotations');
    }
}

function openRetrieveQuotationModal(quotationId) {
    const quotation = QUOTATIONS.find(q => q.id == quotationId);
    if (!quotation) return;
    CURRENT_QUOTATION = quotation;
    
    const modal = document.getElementById('retrieve-quotation-modal');
    const infoDiv = document.getElementById('retrieve-quotation-info');
    const createdDate = new Date(quotation.quotation_date).toLocaleDateString();
    const validUntil = new Date(quotation.valid_until).toLocaleDateString();
    const today = new Date();
    const isExpired = new Date(quotation.valid_until) < today;
    
    infoDiv.innerHTML = `<div><strong>Quote #:</strong> ${quotation.quotation_number}<br><strong>Customer:</strong> ${quotation.customer_name}<br><strong>Date:</strong> ${createdDate}<br><strong>Valid Until:</strong> ${validUntil}<br><strong>Total:</strong> ₹${roundValue(quotation.grand_total)}</div>${isExpired ? '<div style="color: #f44336;">⚠️ Expired</div>' : ''}<div style="margin-top: 10px;">Loading will replace current cart.</div>`;
    
    modal.style.display = 'block';
}

function retrieveQuotation() {
    if (!CURRENT_QUOTATION) return;
    loadQuotationItems(CURRENT_QUOTATION.id)
        .then(items => {
            if (!items || items.length === 0) throw new Error('No items found');
            CART = [];
            items.forEach(item => {
                if (item.is_manual) {
                    // Manual product
                    CART.push({
                        id: `manual-${Date.now()}-${Math.random().toString(36).substr(2, 6)}`,
                        product_id: 0,
                        name: item.product_name,
                        code: item.product_code || `MAN-${Date.now()}`,
                        price: roundValue(parseFloat(item.unit_price)),
                        price_type: item.price_type || 'retail',
                        quantity: roundValue(parseFloat(item.quantity)),
                        unit: item.unit || 'PCS',
                        discount_type: item.discount_type,
                        discount_value: roundValue(parseFloat(item.discount_amount) || 0),
                        discount_amount: roundValue(parseFloat(item.discount_amount) || 0),
                        total: roundValue(parseFloat(item.total_price)),
                        mrp: roundValue(parseFloat(item.unit_price)),
                        cgst_rate: roundValue(parseFloat(item.cgst_rate) || 0),
                        sgst_rate: roundValue(parseFloat(item.sgst_rate) || 0),
                        igst_rate: roundValue(parseFloat(item.igst_rate) || 0),
                        hsn_code: item.hsn_code || '',
                        shop_stock: 999999,
                        unit_of_measure: item.unit || 'PCS',
                        secondary_unit: null,
                        sec_unit_conversion: null,
                        stock_price: roundValue(parseFloat(item.unit_price)),
                        is_secondary_unit: false,
                        quantity_in_primary: roundValue(parseFloat(item.quantity)),
                        is_manual: true
                    });
                } else {
                    const product = PRODUCTS.find(p => p.id == item.product_id);
                    if (product) {
                        CART.push({
                            id: `${item.product_id}-${item.unit || product.unit_of_measure}-${item.price_type || 'retail'}`,
                            product_id: item.product_id,
                            name: item.product_name,
                            code: product.code,
                            price: roundValue(parseFloat(item.unit_price)),
                            price_type: item.price_type || 'retail',
                            quantity: roundValue(parseFloat(item.quantity)),
                            unit: item.unit || product.unit_of_measure,
                            discount_type: item.discount_type,
                            discount_value: roundValue(parseFloat(item.discount_amount) || 0),
                            discount_amount: roundValue(parseFloat(item.discount_amount) || 0),
                            total: roundValue(parseFloat(item.total_price)),
                            mrp: product.mrp,
                            cgst_rate: roundValue(parseFloat(item.cgst_rate) || 0),
                            sgst_rate: roundValue(parseFloat(item.sgst_rate) || 0),
                            igst_rate: roundValue(parseFloat(item.igst_rate) || 0),
                            hsn_code: item.hsn_code,
                            shop_stock: product.shop_stock,
                            unit_of_measure: product.unit_of_measure,
                            secondary_unit: product.secondary_unit,
                            sec_unit_conversion: product.sec_unit_conversion,
                            stock_price: product.stock_price,
                            is_secondary_unit: false,
                            quantity_in_primary: roundValue(parseFloat(item.quantity)),
                            is_manual: false
                        });
                    }
                }
            });
            saveCartToSession(); updateUI();
            document.getElementById('retrieve-quotation-modal').style.display = 'none';
            document.getElementById('quotations-list-modal').style.display = 'none';
            showSuccessToast(`Quotation #${CURRENT_QUOTATION.quotation_number} loaded`);
            CURRENT_QUOTATION = null;
        })
        .catch(error => {
            console.error('Error retrieving quotation:', error);
            showErrorToast('Failed to retrieve quotation');
        });
}

async function loadQuotationItems(quotationId) {
    try {
        const response = await fetch(`${API_QUOTATIONS}?action=get_items&quotation_id=${quotationId}`);
        const data = await response.json();
        return data.success && data.items ? data.items : [];
    } catch (error) {
        console.error('Error loading quotation items:', error);
        return [];
    }
}

function viewQuotation(quotationId) {
    window.open(`quotation_print.php?id=${quotationId}`, '_blank');
}

async function deleteQuotation(quotationId) {
    const result = await showConfirmDialog('Delete Quotation', 'Are you sure?', 'warning');
    if (!result.isConfirmed) return;
    
    try {
        const response = await fetch(`${API_QUOTATIONS}?action=delete`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ quotation_id: quotationId })
        });
        const data = await response.json();
        if (data.success) {
            showSuccessToast('Quotation deleted');
            loadQuotationsList();
        } else {
            throw new Error(data.message || 'Failed to delete quotation');
        }
    } catch (error) {
        showErrorToast('Failed to delete quotation: ' + error.message);
    }
}

// ==================== UTILITY FUNCTIONS ====================
function updateUI() {
    renderCart();
    updateCartSummary();
    updateReferralDisplay();
}

async function getNextInvoiceNumber(invoiceType = 'gst') {
    try {
        const response = await fetch(`${API_INVOICES}?action=get_next_invoice_number`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_type: invoiceType })
        });
        const data = await response.json();
        if (data.success) return data.invoice_number;
    } catch (error) {
        console.error('Error getting invoice number:', error);
    }
    const prefix = invoiceType === 'gst' ? 'INV' : 'INVNG';
    const yearMonth = new Date().getFullYear().toString().slice(-2) + ('0' + (new Date().getMonth() + 1)).slice(-2);
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `${prefix}${yearMonth}-${random}`;
}

function saveCartToSession() {
    try {
        sessionStorage.setItem('as_electricals_cart', JSON.stringify({ items: CART, overall_discount: OVERALL_DISCOUNT }));
    } catch (error) {
        console.error('Error saving cart to session:', error);
    }
}

function loadCartFromSession() {
    try {
        const cartData = sessionStorage.getItem('as_electricals_cart');
        if (cartData) {
            const data = JSON.parse(cartData);
            CART = data.items || [];
            OVERALL_DISCOUNT = data.overall_discount || 0;
            const discountInput = document.getElementById('overall-discount-input');
            if (discountInput && OVERALL_DISCOUNT > 0) discountInput.value = OVERALL_DISCOUNT;
            updateUI();
        }
    } catch (error) {
        console.error('Error loading cart from session:', error);
    }
}

function setupSelect2() {
    $('#customer-type').select2({ placeholder: "Select Customer", allowClear: false, width: '160px' });
    $('#site-select').select2({ minimumResultsForSearch: -1, width: '160px' });
}

function setupPaymentSelect2() {
    $('.select2-site-payment').select2({ minimumResultsForSearch: -1, width: '100%', dropdownParent: $('#payment-modal') });
    $('.select2-engineer-payment').select2({ minimumResultsForSearch: -1, width: '100%', dropdownParent: $('#payment-modal') });
    $('#payment-site-select').val('').trigger('change');
}

// ==================== EVENT LISTENERS ====================
function setupEventListeners() {
    setupBarcodeScanner();
    
    $('#site-select').on('change', function() {
        SELECTED_SITE = this.value;
        document.getElementById('current-site').textContent = this.options[this.selectedIndex].text;
    });

    document.getElementById('gst-btn').onclick = () => {
        IS_GST_BILL = true;
        document.getElementById('gst-btn').classList.add('active');
        document.getElementById('non-gst-btn').classList.remove('active');
        document.getElementById('gst-row').style.display = 'flex';
        document.getElementById('bill-type-display').textContent = 'GST Bill';
        updateCartSummary();
    };

    document.getElementById('non-gst-btn').onclick = () => {
        IS_GST_BILL = false;
        document.getElementById('non-gst-btn').classList.add('active');
        document.getElementById('gst-btn').classList.remove('active');
        document.getElementById('gst-row').style.display = 'none';
        document.getElementById('bill-type-display').textContent = 'Non-GST Bill';
        updateCartSummary();
    };

    $('#customer-type').on('change', function() {
        const customerId = this.value;
        if (customerId === 'walk-in') {
            CURRENT_CUSTOMER = CUSTOMERS.find(c => c.id === 'walk-in');
            CUSTOMER_POINTS = 0;
            IS_WHOLESALE = false;
            showInfoToast('Walk-in customer selected');
        } else if (customerId) {
            CURRENT_CUSTOMER = CUSTOMERS.find(c => c.id == customerId);
            if (CURRENT_CUSTOMER) {
                CUSTOMER_POINTS = CURRENT_CUSTOMER.points || 0;
                IS_WHOLESALE = CURRENT_CUSTOMER.type === 'wholesale';
                showSuccessToast(`Customer selected: ${CURRENT_CUSTOMER.name}`);
            }
        } else {
            CURRENT_CUSTOMER = null;
            CUSTOMER_POINTS = 0;
            IS_WHOLESALE = false;
        }
        updateUI();
    });

    document.getElementById('new-customer-btn').onclick = openCustomerModal;

    const searchBox = document.getElementById('search-box');
    if (searchBox) {
        searchBox.oninput = function() {
            const term = this.value.trim();
            if (term.length >= 2) searchProducts(term);
            else if (term.length === 0) renderProductsByCategory(CURRENT_CATEGORY_ID, CATEGORIES.find(c => c.id === CURRENT_CATEGORY_ID)?.name || '');
        };
        searchBox.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && this.value.trim()) searchProducts(this.value.trim());
        });
    }

    document.getElementById('clear-cart').onclick = function() {
        if (CART.length > 0) {
            showConfirmDialog('Clear Cart', 'Clear all items?', 'question').then(result => {
                if (result.isConfirmed) {
                    CART = []; OVERALL_DISCOUNT = 0;
                    document.getElementById('overall-discount-input').value = '';
                    saveCartToSession(); updateUI();
                    showInfoToast('Cart cleared');
                }
            });
        } else {
            showInfoToast('Cart is empty');
        }
    };

    document.getElementById('checkout-btn').onclick = function() {
        if (CART.length === 0) {
            showWarningToast('Cart is empty');
            return;
        }
        showPaymentModal();
    };

    document.getElementById('cancel-btn').onclick = () => document.getElementById('quantity-modal').style.display = 'none';
    document.getElementById('add-to-cart-btn').onclick = addToCart;

    document.getElementById('discount-type-select').addEventListener('change', updatePricePreview);
    document.getElementById('discount-value-input').addEventListener('input', updatePricePreview);
    document.getElementById('selling-price-input').addEventListener('input', updatePricePreview);
    document.getElementById('quantity-input').addEventListener('input', updatePricePreview);

    document.getElementById('apply-overall-discount').onclick = applyOverallDiscount;
    document.getElementById('overall-discount-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyOverallDiscount();
    });

    document.getElementById('cancel-customer-btn').onclick = () => document.getElementById('customer-modal').style.display = 'none';
    document.getElementById('save-customer-btn').onclick = saveCustomerModal;

    document.getElementById('current-referral').onclick = openReferralModal;
    document.getElementById('cancel-referral-btn').onclick = () => document.getElementById('referral-modal').style.display = 'none';
    document.getElementById('confirm-referral-btn').onclick = function() {
        const selectedOption = document.querySelector('.referral-option.selected');
        if (!selectedOption) {
            showWarningToast('Please select a referral option');
            return;
        }
        SELECTED_REFERRAL_ID = selectedOption.getAttribute('data-referral-id');
        SELECTED_REFERRAL_CODE = selectedOption.getAttribute('data-referral-code') || '';
        SELECTED_REFERRAL_NAME = selectedOption.getAttribute('data-referral-name') || 'None';
        document.getElementById('referral-modal').style.display = 'none';
        updateReferralDisplay();
        if (SELECTED_REFERRAL_ID && SELECTED_REFERRAL_ID !== 'none') {
            showSuccessToast(`Referral applied: ${SELECTED_REFERRAL_NAME}`);
        } else {
            showInfoToast('No referral selected');
        }
    };

    document.getElementById('cancel-payment-btn').onclick = () => document.getElementById('payment-modal').style.display = 'none';
    document.getElementById('autofill-payment-btn').onclick = autofillPayment;
    
    document.getElementById('save-invoice-btn').onclick = async function() {
        try { await processPayment('save'); } catch (error) { showErrorToast('Payment failed: ' + error.message); }
    };
    document.getElementById('save-print-invoice-btn').onclick = async function() {
        try { await processPayment('print'); } catch (error) { showErrorToast('Payment failed: ' + error.message); }
    };
    document.getElementById('save-thermal-invoice-btn').onclick = async function() {
        try { await processPayment('thermal'); } catch (error) { showErrorToast('Payment failed: ' + error.message); }
    };

    document.getElementById('toggle-edit-btn').onclick = toggleCustomerEditMode;

    ['cash-payment', 'upi-payment', 'bank-payment', 'credit-payment'].forEach(id => {
        document.getElementById(id).addEventListener('input', updatePaymentTotals);
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.onclick = function(e) {
            if (e.target === this) this.style.display = 'none';
        };
    });

    document.getElementById('hold-btn').onclick = function() {
        if (CART.length === 0) { showWarningToast('Cart is empty'); return; }
        openHoldModal();
    };

    document.getElementById('quotation-btn').onclick = function() {
        if (CART.length === 0) { showWarningToast('Cart is empty'); return; }
        openQuotationModal();
    };

    document.getElementById('holds-list-btn').onclick = function() {
        loadHoldsList();
        document.getElementById('holds-list-modal').style.display = 'block';
    };

    document.getElementById('quotations-list-btn').onclick = function() {
        loadQuotationsList();
        document.getElementById('quotations-list-modal').style.display = 'block';
    };

    document.getElementById('cancel-hold-btn').onclick = () => document.getElementById('hold-modal').style.display = 'none';
    document.getElementById('confirm-hold-btn').onclick = saveHold;
    document.getElementById('close-holds-list-btn').onclick = () => document.getElementById('holds-list-modal').style.display = 'none';

    document.getElementById('cancel-quotation-btn').onclick = () => document.getElementById('quotation-modal').style.display = 'none';
    document.getElementById('confirm-quotation-btn').onclick = saveQuotation;
    document.getElementById('close-quotations-list-btn').onclick = () => document.getElementById('quotations-list-modal').style.display = 'none';

    document.getElementById('cancel-retrieve-hold-btn').onclick = function() {
        document.getElementById('retrieve-hold-modal').style.display = 'none';
        CURRENT_HOLD = null;
    };
    document.getElementById('confirm-retrieve-hold-btn').onclick = retrieveHold;

    document.getElementById('cancel-retrieve-quotation-btn').onclick = function() {
        document.getElementById('retrieve-quotation-modal').style.display = 'none';
        CURRENT_QUOTATION = null;
    };
    document.getElementById('confirm-retrieve-quotation-btn').onclick = retrieveQuotation;

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            if (CART.length > 0) showPaymentModal();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (CART.length > 0) showPaymentModal();
        }
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                if (modal.style.display === 'block') modal.style.display = 'none';
            });
        }
    });
}

window.updateCartItemQuantity = updateCartItemQuantity;
window.removeFromCart = removeFromCart;
</script>   
</body>

</html>