<?php
// quotation_public_view.php - Public view for quotations (no login required)
require_once 'config/database.php';

// Get token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    die("<div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h2>Access Denied</h2>
        <p>No token provided. Please use the link sent to your WhatsApp.</p>
        <p><a href='https://whitesmoke-leopard-558579.hostingersite.com/as_electrical/'>Go to Homepage</a></p>
    </div>");
}

// Fetch quotation by token
$stmt = $pdo->prepare("
    SELECT q.*,
           s.shop_name, s.address as shop_address, s.phone as shop_phone, s.gstin as shop_gstin,
           s.id as shop_id
    FROM quotations q
    LEFT JOIN shops s ON q.shop_id = s.id
    WHERE q.public_token = ?
");
$stmt->execute([$token]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    die("<div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h2>Invalid Link</h2>
        <p>The quotation you're looking for doesn't exist or the link has expired.</p>
        <p>Please contact AS Electricals for assistance.</p>
        <p><strong>Phone:</strong> 9943701430, 8489755755</p>
        <p><a href='https://whitesmoke-leopard-558579.hostingersite.com/as_electrical/'>Go to Homepage</a></p>
    </div>");
}

// Check if token is expired
if (!empty($quotation['token_expiry']) && strtotime($quotation['token_expiry']) < time()) {
    die("<div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h2>Link Expired</h2>
        <p>This quotation link has expired. Quotations are valid for 30 days.</p>
        <p>Please contact AS Electricals for a new quotation.</p>
        <p><strong>Phone:</strong> 9943701430, 8489755755</p>
        <p><a href='https://whitesmoke-leopard-558579.hostingersite.com/as_electrical/'>Go to Homepage</a></p>
    </div>");
}

$business_id = $quotation['business_id'];
$shop_id = $quotation['shop_id'] ?? null;

// Fetch quotation settings
$settings_stmt = $pdo->prepare("
    SELECT * FROM invoice_settings
    WHERE business_id = ? AND (shop_id = ? OR shop_id IS NULL)
    ORDER BY shop_id DESC
    LIMIT 1
");
$settings_stmt->execute([$business_id, $shop_id]);
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

// Fallback to business table if no settings
if (!$settings) {
    $business_stmt = $pdo->prepare("
        SELECT business_name, phone, address, gstin
        FROM businesses
        WHERE id = ?
        LIMIT 1
    ");
    $business_stmt->execute([$business_id]);
    $business = $business_stmt->fetch(PDO::FETCH_ASSOC);

    $settings = [
        'company_name' => $business['business_name'] ?? 'AS ELECTRICALS',
        'company_address' => $business['address'] ?? '111-J, SALEM MAIN ROAD, DHARMAPURI-636705',
        'company_phone' => $business['phone'] ?? '9943701430, 8489755755',
        'company_email' => 'aselectricals@gmail.com',
        'company_website' => '',
        'gst_number' => $business['gstin'] ?? '33AKDPY5436F1Z2',
        'pan_number' => '',
        'logo_path' => $settings['logo_path'] ?? '',
        'qr_code_path' => '',
        'qr_code_data' => '',
        'invoice_terms' => "1. This quotation is valid for 30 days from the date of issue.\n2. Prices are subject to change without prior notice.\n3. Delivery subject to stock availability.\n4. Taxes extra as applicable.\n5. Payment terms: 50% advance, balance before delivery.",
        'invoice_footer' => 'Thank you for choosing AS Electricals! We look forward to serving you.',
        'invoice_prefix' => 'QT'
    ];
}

// Fetch default active bank accounts
if ($shop_id) {
    $bank_account_sql = "SELECT * FROM bank_accounts 
                        WHERE business_id = ? AND shop_id = ? AND is_active = 1
                        ORDER BY is_default DESC, id ASC
                        LIMIT 2";
    $bank_account_stmt = $pdo->prepare($bank_account_sql);
    $bank_account_stmt->execute([$business_id, $shop_id]);
} else {
    $bank_account_sql = "SELECT * FROM bank_accounts 
                        WHERE business_id = ? AND shop_id IS NULL AND is_active = 1
                        ORDER BY is_default DESC, id ASC
                        LIMIT 2";
    $bank_account_stmt = $pdo->prepare($bank_account_sql);
    $bank_account_stmt->execute([$business_id]);
}
$bank_accounts = $bank_account_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch quotation items
$items_stmt = $pdo->prepare("
    SELECT qi.*, 
           p.product_name, p.product_code, p.hsn_code, p.mrp, p.gst_id,
           g.cgst_rate, g.sgst_rate, g.igst_rate,
           p.unit_of_measure as product_unit
    FROM quotation_items qi
    JOIN products p ON qi.product_id = p.id
    LEFT JOIN gst_rates g ON p.gst_id = g.id
    WHERE qi.quotation_id = ?
    ORDER BY qi.id
");
$items_stmt->execute([$quotation['id']]);
$items = $items_stmt->fetchAll();

// Calculate totals
$subtotal = $total_discount = 0;
$total_taxable = $total_cgst = $total_sgst = $total_igst = 0;

foreach ($items as $item) {
    $line_total = $item['unit_price'] * $item['quantity'];
    $discount = $item['discount_amount'] ?? 0;
    $net = $line_total - $discount;
    
    $subtotal += $line_total;
    $total_discount += $discount;
    
    $taxable = $net;
    $cgst_rate = $item['cgst_rate'] ?? 0;
    $sgst_rate = $item['sgst_rate'] ?? 0;
    $igst_rate = $item['igst_rate'] ?? 0;
    
    $cgst_amount = $taxable * ($cgst_rate / 100);
    $sgst_amount = $taxable * ($sgst_rate / 100);
    $igst_amount = $taxable * ($igst_rate / 100);
    
    $total_taxable += $taxable;
    $total_cgst += $cgst_amount;
    $total_sgst += $sgst_amount;
    $total_igst += $igst_amount;
}

$grand_total = $quotation['grand_total'];
$quotation_date = date('d-m-Y', strtotime($quotation['quotation_date']));
$valid_until = date('d-m-Y', strtotime($quotation['valid_until']));

// Customer details
$customer_name = $quotation['customer_name'] ?? 'Customer';
$customer_phone = $quotation['customer_phone'] ?? '';
$customer_email = $quotation['customer_email'] ?? '';
$customer_address = $quotation['customer_address'] ?? '';
$customer_gstin = $quotation['customer_gstin'] ?? '';

// Place of supply
$place_of_supply = 'Tamil Nadu (33)';

// Check if expired
$is_expired = strtotime($quotation['valid_until']) < time() && $quotation['status'] !== 'accepted' && $quotation['status'] !== 'rejected';

// Helper function for money formatting
function money($v) { 
    return number_format((float)$v, 2, '.', ','); 
}

function format_quantity($v) {
    $v = (float)$v;
    if (floor($v) == $v) return number_format($v, 0, '.', '');
    return number_format($v, 2, '.', '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?= htmlspecialchars($quotation['quotation_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Inter', Arial, sans-serif;
            padding: 20px;
        }
        
        .quotation-paper {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 15px;
            position: relative;
            font-family: 'Inter', Arial, sans-serif;
        }
        
        /* Header Section - Matching PDF */
        .header {
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
        }
        
        .company-title {
            font-size: 24px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        
        .page-number {
            font-size: 11px;
            color: #666;
            font-weight: 400;
        }
        
        .company-info-wrapper {
            display: flex;
            gap: 15px;
        }
        
        .company-logo {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .company-details {
            font-size: 12px;
            line-height: 1.5;
            margin-top: 0;
            flex: 1;
        }
        
        .company-details .company-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 3px;
        }
        
        .quotation-info {
            font-size: 12px;
            text-align: right;
            line-height: 1.6;
        }
        
        .quotation-info div {
            margin-bottom: 3px;
        }
        
        .gst-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin: 10px 0;
            padding: 5px 0;
            border-top: 1px dashed #999;
            border-bottom: 1px dashed #999;
        }
        
        /* Customer Section */
        .customer-section {
            margin: 15px 0;
        }
        
        .customer-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .customer-details {
            font-size: 12px;
            line-height: 1.6;
        }
        
        /* Table Styles - Exactly matching PDF */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 15px 0;
            font-family: 'Inter', Arial, sans-serif;
        }
        
        .items-table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 8px 4px;
            font-weight: 700;
            text-align: center;
            font-size: 11px;
        }
        
        .items-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            vertical-align: top;
        }
        
        .items-table td:first-child {
            text-align: center;
            width: 5%;
        }
        
        .items-table td:nth-child(2) {
            width: 27%;
        }
        
        .items-table td:nth-child(3) {
            width: 8%;
            text-align: center;
        }
        
        .items-table td:nth-child(4) {
            width: 9%;
            text-align: center;
        }
        
        .items-table td:nth-child(5) {
            width: 11%;
            text-align: right;
        }
        
        .items-table td:nth-child(6) {
            width: 6%;
            text-align: center;
        }
        
        .items-table td:nth-child(7) {
            width: 10%;
            text-align: center;
        }
        
        .items-table td:nth-child(8) {
            width: 10%;
            text-align: right;
        }
        
        .items-table td:nth-child(9) {
            width: 14%;
            text-align: right;
            font-weight: 600;
        }
        
        .item-description {
            font-weight: 500;
            line-height: 1.4;
        }
        
        /* Summary Section */
        .summary-section {
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .summary-left {
            width: 45%;
        }
        
        .summary-right {
            width: 45%;
            text-align: right;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-total {
            font-size: 16px;
            font-weight: 700;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #333;
        }
        
        .summary-total div:last-child {
            font-size: 18px;
            color: #28a745;
        }
        
        /* Payment Details */
        .payment-details {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #333;
            background-color: #f9f9f9;
        }
        
        .payment-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            font-size: 12px;
        }
        
        /* Notes and Terms */
        .notes-section {
            margin: 20px 0;
        }
        
        .notes-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .notes-content {
            font-size: 11px;
            line-height: 1.5;
            white-space: pre-line;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            font-size: 11px;
            font-style: italic;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .authorized-signatory {
            text-align: right;
            margin-top: 20px;
        }
        
        .signatory-line {
            margin-top: 30px;
            padding-top: 5px;
            border-top: 1px solid #333;
            width: 250px;
            margin-left: auto;
            font-size: 12px;
            text-align: center;
        }
        
        /* Action Buttons */
        .action-buttons {
            margin: 30px 0 20px;
            text-align: center;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .btn-success {
            background-color: #25D366;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #128C7E;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 10px;
        }
        
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-sent { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #343a40; color: white; }
        
        /* Print styles - Exactly matching PDF */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .quotation-paper {
                box-shadow: none;
                padding: 8mm;
                max-width: 100%;
            }
            
            .action-buttons,
            .no-print,
            .page-number {
                display: none !important;
            }
            
            .items-table th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .status-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .company-logo {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4;
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="quotation-paper">
        <!-- Header Section - Exactly matching PDF -->
        <div class="header">
            <div class="header-top">
                <div class="company-title">QUOTATION</div>
                <div class="page-number no-print">Page 1 of 1</div>
            </div>
            
            <div style="display: flex; justify-content: space-between;">
                <div class="company-info-wrapper">
                    <?php if (!empty($settings['logo_path']) && file_exists($settings['logo_path'])): ?>
                    <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Company Logo" class="company-logo">
                    <?php endif; ?>
                    <div class="company-details">
                        <div class="company-name"><?= htmlspecialchars($settings['company_name'] ?? 'AS ELECTRICALS') ?></div>
                        <div><?= nl2br(htmlspecialchars($settings['company_address'] ?? '111-J, SALEM MAIN ROAD, DHARMAPURI-636705')) ?></div>
                        <?php if (!empty($settings['company_phone'])): ?>
                        <div>Phone: <?= htmlspecialchars($settings['company_phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($settings['company_email'])): ?>
                        <div>Email: <?= htmlspecialchars($settings['company_email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="quotation-info">
                    <div><strong>Quotation No :</strong> <?= htmlspecialchars($quotation['quotation_number']) ?></div>
                    <div><strong>Quotation Date :</strong> <?= $quotation_date ?></div>
                    <div><strong>Valid Until :</strong> <?= $valid_until ?></div>
                    <div><strong>Status :</strong> <?= ucfirst($quotation['status']) ?><?php if ($is_expired && $quotation['status'] !== 'expired'): ?> (Expired)<?php endif; ?></div>
                    <div class="no-print"><strong>Printed On :</strong> <?= date('d-m-Y H:i:s') ?></div>
                </div>
            </div>
            
            <div class="gst-row">
                <span><strong>GSTIN :</strong> <?= htmlspecialchars($settings['gst_number'] ?? $quotation['shop_gstin'] ?? '33AKDPY5436F1Z2') ?></span>
                <span><strong>Place of Supply :</strong> <?= $place_of_supply ?></span>
            </div>
        </div>
        
        <!-- Customer Section -->
        <div class="customer-section">
            <div class="customer-title">Quotation To:</div>
            <div class="customer-details">
                <?php if (!empty($customer_name)): ?>
                <div><strong>Name :</strong> <?= htmlspecialchars($customer_name) ?></div>
                <?php endif; ?>
                <?php if (!empty($customer_phone)): ?>
                <div><strong>Mobile :</strong> <?= htmlspecialchars($customer_phone) ?></div>
                <?php endif; ?>
                <?php if (!empty($customer_email)): ?>
                <div><strong>Email :</strong> <?= htmlspecialchars($customer_email) ?></div>
                <?php endif; ?>
                <?php if (!empty($customer_gstin)): ?>
                <div><strong>GSTIN :</strong> <?= htmlspecialchars($customer_gstin) ?></div>
                <?php endif; ?>
                <?php if (!empty($customer_address)): ?>
                <div><strong>Address :</strong> <?= nl2br(htmlspecialchars($customer_address)) ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Items Table - Exactly matching PDF -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>SN</th>
                    <th>Item Description</th>
                    <th>HSN</th>
                    <th>GST(%)</th>
                    <th>Rate</th>
                    <th>Qty</th>
                    <th>Disc</th>
                    <th>GST Amt</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($items as $item): 
                    $cgst_rate = $item['cgst_rate'] ?? 0;
                    $sgst_rate = $item['sgst_rate'] ?? 0;
                    $igst_rate = $item['igst_rate'] ?? 0;
                    $total_gst_rate = $cgst_rate + $sgst_rate + $igst_rate;
                    
                    $line_total = $item['unit_price'] * $item['quantity'];
                    $discount_amount = $item['discount_amount'] ?? 0;
                    $discount_rate = $item['discount_rate'] ?? 0;
                    $net = $line_total - $discount_amount;
                    
                    $taxable = $net;
                    $cgst_amount = $taxable * ($cgst_rate / 100);
                    $sgst_amount = $taxable * ($sgst_rate / 100);
                    $igst_amount = $taxable * ($igst_rate / 100);
                    $total_gst_amount = $cgst_amount + $sgst_amount + $igst_amount;
                    $total_with_gst = $taxable + $total_gst_amount;
                    
                    $unit = !empty($item['product_unit']) ? $item['product_unit'] : (empty($item['unit']) ? 'PCS' : $item['unit']);
                ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td class="item-description">
                        <?php if (!empty($item['product_code'])): ?>
                        <?= htmlspecialchars($item['product_code']) ?> - <?= htmlspecialchars($item['product_name']) ?>
                        <?php else: ?>
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                    <td><?= $total_gst_rate > 0 ? number_format($total_gst_rate, 1) . '%' : '0%' ?></td>
                    <td>₹<?= money($item['unit_price']) ?></td>
                    <td><?= format_quantity($item['quantity']) ?> <?= htmlspecialchars($unit) ?></td>
                    <td>
                        <?php if ($discount_amount > 0): ?>
                            ₹<?= money($discount_amount) ?><br><span style="font-size: 9px;">(<?= $discount_rate ?>%)</span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= $total_gst_amount > 0 ? '₹' . money($total_gst_amount) : '-' ?></td>
                    <td><strong>₹<?= money($total_with_gst) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Summary Section - Exactly matching PDF -->
        <div class="summary-section">
            <div class="summary-left">
                <?php if ($total_discount > 0): ?>
                <div class="summary-row">
                    <span>Discount</span>
                    <span>₹<?= money($total_discount) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($total_taxable > 0): ?>
                <div class="summary-row">
                    <span>Taxable Amount</span>
                    <span>₹<?= money($total_taxable) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($total_cgst > 0): ?>
                <div class="summary-row">
                    <span>CGST</span>
                    <span>₹<?= money($total_cgst) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($total_sgst > 0): ?>
                <div class="summary-row">
                    <span>SGST</span>
                    <span>₹<?= money($total_sgst) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($total_igst > 0): ?>
                <div class="summary-row">
                    <span>IGST</span>
                    <span>₹<?= money($total_igst) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (($total_cgst + $total_sgst + $total_igst) > 0): ?>
                <div class="summary-row">
                    <span>Total GST</span>
                    <span>₹<?= money($total_cgst + $total_sgst + $total_igst) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="summary-right">
                <div class="summary-total">
                    <div>QUOTATION TOTAL</div>
                    <div>₹<?= money($grand_total) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Payment Details -->
        <?php if (!empty($bank_accounts)): ?>
        <div class="payment-details">
            <div class="payment-title">Payment Details</div>
            <div class="payment-grid">
                <?php foreach ($bank_accounts as $bank): ?>
                <div>
                    <?php if (!empty($bank['account_holder_name'])): ?>
                    <div><strong>A/C Name:</strong> <?= htmlspecialchars($bank['account_holder_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($bank['bank_name'])): ?>
                    <div><strong>Bank:</strong> <?= htmlspecialchars($bank['bank_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($bank['account_number'])): ?>
                    <div><strong>A/C No:</strong> <?= htmlspecialchars($bank['account_number']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($bank['ifsc_code'])): ?>
                    <div><strong>IFSC:</strong> <?= htmlspecialchars($bank['ifsc_code']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($bank['branch_name'])): ?>
                    <div><strong>Branch:</strong> <?= htmlspecialchars($bank['branch_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($bank['upi_id'])): ?>
                    <div><strong>UPI:</strong> <?= htmlspecialchars($bank['upi_id']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notes -->
        <?php if (!empty($quotation['notes'])): ?>
        <div class="notes-section">
            <div class="notes-title">Notes:</div>
            <div class="notes-content"><?= nl2br(htmlspecialchars($quotation['notes'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Terms and Conditions -->
        <?php if (!empty($settings['invoice_terms'])): ?>
        <div class="notes-section">
            <div class="notes-title">Terms & Conditions:</div>
            <div class="notes-content"><?= nl2br(htmlspecialchars($settings['invoice_terms'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Authorized Signatory - Exactly matching PDF -->
        <div class="authorized-signatory">
            <div class="signatory-line">
                For <?= htmlspecialchars($settings['company_name'] ?? 'AS ELECTRICALS') ?>
                <br>
                Authorized Signatory
            </div>
        </div>
        
        <!-- Footer - Exactly matching PDF -->
        <div class="footer">
            <div>This is a computer generated quotation.</div>
            <div class="no-print">Printed On - <?= date('d-m-Y H:i:s') ?></div>
        </div>
        
        <?php if (!empty($settings['invoice_footer'])): ?>
        <div style="text-align: center; font-size: 11px; font-style: italic; margin-top: 15px;">
            <?= htmlspecialchars($settings['invoice_footer']) ?>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons - Enhanced with Download and Close -->
        <div class="action-buttons no-print">
            <button onclick="downloadQuotation()" class="btn btn-primary">
                <span>📥</span> Download PDF
            </button>
            <button onclick="printQuotation()" class="btn btn-primary">
                <span>🖨️</span> Print Preview
            </button>
            <button onclick="shareOnWhatsApp()" class="btn btn-success">
                <span>📱</span> Share on WhatsApp
            </button>
            <a href="https://whitesmoke-leopard-558579.hostingersite.com/as_electrical/" class="btn btn-secondary">
                <span>🏠</span> Visit Website
            </a>
            <button onclick="closeWindow()" class="btn btn-danger">
                <span>✖️</span> Close
            </button>
        </div>
    </div>
    
    <!-- Include html2pdf library for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
    // Print function with preview
    function printQuotation() {
        window.print();
    }
    
    // Download as PDF function
    function downloadQuotation() {
        const element = document.querySelector('.quotation-paper');
        const opt = {
            margin:        [0.5, 0.5, 0.5, 0.5],
            filename:     '<?= htmlspecialchars($quotation['quotation_number']) ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, letterRendering: true, useCORS: true, logging: false },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span>⏳</span> Generating PDF...';
        btn.disabled = true;
        
        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    // Share on WhatsApp function
    function shareOnWhatsApp() {
        // Get customer phone from the page
        const phoneElement = document.querySelector('.customer-details');
        let phone = '';
        if (phoneElement) {
            const phoneMatch = phoneElement.innerText.match(/Mobile\s*:\s*([0-9,\s]+)/);
            if (phoneMatch) {
                phone = phoneMatch[1].replace(/[^0-9]/g, '');
                if (phone.startsWith('0')) phone = phone.substring(1);
                if (phone.length === 10) phone = '91' + phone;
            }
        }
        
        const url = window.location.href;
        const quotationNo = "<?= htmlspecialchars($quotation['quotation_number']) ?>";
        const customerName = "<?= htmlspecialchars($customer_name) ?>";
        const amount = "₹<?= money($grand_total) ?>";
        const validUntil = "<?= $valid_until ?>";
        
        const text = `*Quotation from <?= htmlspecialchars($settings['company_name'] ?? 'AS ELECTRICALS') ?>*\n\n` +
            `Quotation No: ${quotationNo}\n` +
            `Customer: ${customerName}\n` +
            `Amount: ${amount}\n` +
            `Valid until: ${validUntil}\n\n` +
            `View online: ${url}`;
        
        if (phone) {
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
        } else {
            window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
        }
    }
    
    // Close window function
    function closeWindow() {
        // Check if window was opened by script
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.close();
            // Fallback if window.close is blocked
            setTimeout(function() {
                window.location.href = 'https://whitesmoke-leopard-558579.hostingersite.com/as_electrical/';
            }, 500);
        }
    }
    
    // Update page number for print
    window.onbeforeprint = function() {
        document.querySelector('.page-number').style.display = 'none';
    };
    
    window.onafterprint = function() {
        document.querySelector('.page-number').style.display = 'block';
    };
    
    // Handle keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+P for print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printQuotation();
        }
        // Ctrl+S for download
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            downloadQuotation();
        }
        // Esc to close
        if (e.key === 'Escape') {
            closeWindow();
        }
    });
    </script>
</body>
</html>