<?php
// invoice_preview.php - Preview invoice before printing
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$invoice_id = $_GET['invoice_id'] ?? 0;

if (!$invoice_id) {
    die('Invalid invoice ID');
}

// Get invoice details
$stmt = $pdo->prepare("
    SELECT i.*, s.shop_name, u.full_name as seller_name,
           c.name as customer_name, c.phone as customer_phone,
           c.gstin as customer_gstin
    FROM invoices i
    LEFT JOIN shops s ON i.shop_id = s.id
    LEFT JOIN users u ON i.seller_id = u.id
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.id = ? AND i.business_id = ?
");
$stmt->execute([$invoice_id, $_SESSION['current_business_id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

// Get invoice items
$items_stmt = $pdo->prepare("
    SELECT ii.*, p.product_name, p.product_code
    FROM invoice_items ii
    LEFT JOIN products p ON ii.product_id = p.id
    WHERE ii.invoice_id = ?
    ORDER BY ii.id
");
$items_stmt->execute([$invoice_id]);
$items = $items_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Preview - <?= $invoice['invoice_number'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .invoice-title {
            font-size: 20px;
            margin: 10px 0;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .details-left, .details-right {
            width: 48%;
        }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th {
            background: #333;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .summary-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .total-row {
            border-top: 2px solid #333;
            padding-top: 10px;
            font-weight: bold;
            font-size: 18px;
        }
        .print-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            border-radius: 4px;
        }
        .print-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-name">AS ELECTRICALS</div>
            <div class="invoice-title">TAX INVOICE</div>
            <div>Invoice No: <?= htmlspecialchars($invoice['invoice_number']) ?></div>
            <div>Date: <?= date('d/m/Y H:i:s', strtotime($invoice['created_at'])) ?></div>
        </div>
        
        <div class="invoice-details">
            <div class="details-left">
                <div class="detail-row">
                    <span class="detail-label">Shop:</span> 
                    <?= htmlspecialchars($invoice['shop_name']) ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Seller:</span> 
                    <?= htmlspecialchars($invoice['seller_name']) ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer:</span> 
                    <?= htmlspecialchars($invoice['customer_name']) ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span> 
                    <?= htmlspecialchars($invoice['customer_phone']) ?>
                </div>
            </div>
            <div class="details-right">
                <div class="detail-row">
                    <span class="detail-label">Type:</span> 
                    <?= $invoice['customer_type'] === 'wholesale' ? 'Wholesale' : 'Retail' ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">GST:</span> 
                    <?= $invoice['gst_type'] === 'gst' ? 'GST Bill' : 'Non-GST Bill' ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment:</span> 
                    <?= htmlspecialchars($invoice['payment_method']) ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span> 
                    <?= htmlspecialchars($invoice['payment_status']) ?>
                </div>
            </div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>HSN</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Discount</th>
                    <th>GST</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><?= htmlspecialchars($item['hsn_code']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>₹<?= number_format($item['unit_price'], 2) ?></td>
                        <td>-₹<?= number_format($item['discount_amount'], 2) ?></td>
                        <td>₹<?= number_format($item['cgst_amount'] + $item['sgst_amount'] + $item['igst_amount'], 2) ?></td>
                        <td>₹<?= number_format($item['total_with_gst'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary-section">
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>₹<?= number_format($invoice['subtotal'], 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Discount:</span>
                <span>-₹<?= number_format($invoice['overall_discount'], 2) ?></span>
            </div>
            <?php if ($invoice['gst_type'] === 'gst'): ?>
                <div class="summary-row">
                    <span>GST:</span>
                    <span>₹<?= number_format(($invoice['total'] - $invoice['subtotal'] + $invoice['overall_discount']), 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row total-row">
                <span>Total:</span>
                <span>₹<?= number_format($invoice['total'], 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Cash:</span>
                <span>₹<?= number_format($invoice['cash_amount'], 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Change:</span>
                <span>₹<?= number_format($invoice['change_given'], 2) ?></span>
            </div>
        </div>
        
        <div style="clear: both;"></div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button class="print-btn" onclick="window.close()" style="background: #666; margin-left: 10px;">
                Close Preview
            </button>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            // Auto-print option (commented out)
            // setTimeout(() => window.print(), 500);
        }
    </script>
</body>
</html>