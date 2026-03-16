<?php
// public_quotation.php - Public view of quotation without login
session_start();
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die("Invalid request");
}

// Decode token
$decoded = base64_decode($token);
$parts = explode('|', $decoded);
if (count($parts) !== 3) {
    die("Invalid token");
}

$quotation_id = (int)$parts[0];
$quotation_number = $parts[1];
$business_id = (int)$parts[2];

// Fetch quotation
$stmt = $pdo->prepare("
    SELECT q.*, s.shop_name, s.address as shop_address, s.phone as shop_phone, s.gstin as shop_gstin
    FROM quotations q
    LEFT JOIN shops s ON q.shop_id = s.id
    WHERE q.id = ? AND q.business_id = ? AND q.quotation_number = ?
");
$stmt->execute([$quotation_id, $business_id, $quotation_number]);
$quotation = $stmt->fetch();

if (!$quotation) {
    die("Quotation not found");
}

// Check if expired
$is_expired = strtotime($quotation['valid_until']) < time() && $quotation['status'] !== 'accepted' && $quotation['status'] !== 'rejected';

// Fetch items
$items_stmt = $pdo->prepare("
    SELECT qi.*, p.product_name, p.product_code, p.hsn_code,
           g.cgst_rate, g.sgst_rate, g.igst_rate
    FROM quotation_items qi
    JOIN products p ON qi.product_id = p.id
    LEFT JOIN gst_rates g ON p.gst_id = g.id
    WHERE qi.quotation_id = ?
    ORDER BY qi.id
");
$items_stmt->execute([$quotation_id]);
$items = $items_stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$total_tax = 0;
foreach ($items as $item) {
    $line_total = $item['unit_price'] * $item['quantity'];
    $subtotal += $line_total;
    
    $cgst_rate = $item['cgst_rate'] ?? 0;
    $sgst_rate = $item['sgst_rate'] ?? 0;
    $igst_rate = $item['igst_rate'] ?? 0;
    
    $taxable = $line_total - ($item['discount_amount'] ?? 0);
    $cgst = $taxable * ($cgst_rate / 100);
    $sgst = $taxable * ($sgst_rate / 100);
    $igst = $taxable * ($igst_rate / 100);
    $total_tax += ($cgst + $sgst + $igst);
}

// Fetch business details
$business_stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$business_stmt->execute([$business_id]);
$business = $business_stmt->fetch();

$company_name = $business['business_name'] ?? 'Company';
$company_address = $business['address'] ?? '';
$company_phone = $business['phone'] ?? '';
$company_gstin = $business['gstin'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?= htmlspecialchars($quotation['quotation_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .quotation-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .quotation-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .company-name {
            color: #007bff;
            font-weight: 600;
            font-size: 28px;
        }
        .quotation-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .quotation-number {
            font-size: 24px;
            font-weight: 600;
        }
        .status-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 14px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .info-card h6 {
            color: #007bff;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-item i {
            width: 20px;
            color: #6c757d;
        }
        .table thead th {
            background: #007bff;
            color: white;
            font-weight: 500;
            border: none;
            padding: 12px;
        }
        .table tbody td {
            padding: 12px;
            vertical-align: middle;
        }
        .amount-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #dee2e6;
        }
        .summary-row.total {
            border-bottom: none;
            font-size: 20px;
            font-weight: 700;
            color: #007bff;
            padding-top: 15px;
        }
        .footer-note {
            margin-top: 30px;
            padding: 20px;
            background: #e8f4fd;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        .download-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .download-btn:hover {
            background: #0056b3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .expired-badge {
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
            display: inline-block;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .quotation-container {
                box-shadow: none;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="quotation-container">
        <div class="text-end no-print mb-3">
            <a href="quotation_print.php?id=<?= $quotation['id'] ?>" target="_blank" class="btn btn-outline-primary me-2">
                <i class="bx bx-printer"></i> Print
            </a>
            <button onclick="window.print()" class="btn btn-outline-success me-2">
                <i class="bx bx-download"></i> Download PDF
            </button>
            <a href="https://wa.me/?text=<?= urlencode("Check my quotation: " . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>" target="_blank" class="btn btn-outline-success">
                <i class="bx bxl-whatsapp"></i> Share
            </a>
        </div>
        
        <div class="quotation-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="company-name"><?= htmlspecialchars($company_name) ?></h1>
                    <p class="text-muted mb-0">
                        <i class="bx bx-map"></i> <?= htmlspecialchars($company_address) ?><br>
                        <i class="bx bx-phone"></i> <?= htmlspecialchars($company_phone) ?><br>
                        <?php if ($company_gstin): ?>
                        <i class="bx bx-building"></i> GST: <?= htmlspecialchars($company_gstin) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="quotation-title d-inline-block">
                        <h4 class="mb-1">QUOTATION</h4>
                        <h2 class="quotation-number mb-2"><?= htmlspecialchars($quotation['quotation_number']) ?></h2>
                        <div class="status-badge">
                            Status: <?= ucfirst($quotation['status']) ?>
                            <?php if ($is_expired): ?>
                            <span class="expired-badge ms-2">Expired</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="bx bx-user"></i> Bill To:</h6>
                    <div class="info-item"><strong><?= htmlspecialchars($quotation['customer_name']) ?></strong></div>
                    <?php if ($quotation['customer_phone']): ?>
                    <div class="info-item"><i class="bx bx-phone"></i> <?= htmlspecialchars($quotation['customer_phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($quotation['customer_email']): ?>
                    <div class="info-item"><i class="bx bx-envelope"></i> <?= htmlspecialchars($quotation['customer_email']) ?></div>
                    <?php endif; ?>
                    <?php if ($quotation['customer_address']): ?>
                    <div class="info-item"><i class="bx bx-map"></i> <?= nl2br(htmlspecialchars($quotation['customer_address'])) ?></div>
                    <?php endif; ?>
                    <?php if ($quotation['customer_gstin']): ?>
                    <div class="info-item"><i class="bx bx-building"></i> GST: <?= htmlspecialchars($quotation['customer_gstin']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="bx bx-info-circle"></i> Quotation Details:</h6>
                    <div class="info-item"><i class="bx bx-calendar"></i> Date: <?= date('d M Y', strtotime($quotation['quotation_date'])) ?></div>
                    <div class="info-item"><i class="bx bx-time"></i> Valid Until: <strong class="<?= $is_expired ? 'text-danger' : 'text-success' ?>"><?= date('d M Y', strtotime($quotation['valid_until'])) ?></strong></div>
                    <div class="info-item"><i class="bx bx-package"></i> Items: <?= count($items) ?></div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th>HSN</th>
                        <th>GST(%)</th>
                        <th class="text-end">Rate (₹)</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Discount (₹)</th>
                        <th class="text-end">Tax (₹)</th>
                        <th class="text-end">Total (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sn = 1;
                    $total_discount = 0;
                    $total_tax = 0;
                    foreach ($items as $item):
                        $cgst_rate = $item['cgst_rate'] ?? 0;
                        $sgst_rate = $item['sgst_rate'] ?? 0;
                        $igst_rate = $item['igst_rate'] ?? 0;
                        $gst_rate = $cgst_rate + $sgst_rate + $igst_rate;
                        
                        $line_total = $item['unit_price'] * $item['quantity'];
                        $discount = $item['discount_amount'] ?? 0;
                        $total_discount += $discount;
                        
                        $taxable = $line_total - $discount;
                        $cgst = $taxable * ($cgst_rate / 100);
                        $sgst = $taxable * ($sgst_rate / 100);
                        $igst = $taxable * ($igst_rate / 100);
                        $tax = $cgst + $sgst + $igst;
                        $total_tax += $tax;
                        
                        $grand_line_total = $taxable + $tax;
                    ?>
                    <tr>
                        <td><?= $sn++ ?></td>
                        <td>
                            <?php if ($item['product_code']): ?>
                            <small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small><br>
                            <?php endif; ?>
                            <?= htmlspecialchars($item['product_name']) ?>
                        </td>
                        <td><?= htmlspecialchars($item['hsn_code'] ?? '-') ?></td>
                        <td class="text-center"><?= $gst_rate > 0 ? $gst_rate . '%' : '-' ?></td>
                        <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-end"><?= $discount > 0 ? '₹' . number_format($discount, 2) : '-' ?></td>
                        <td class="text-end"><?= $tax > 0 ? '₹' . number_format($tax, 2) : '-' ?></td>
                        <td class="text-end"><strong>₹<?= number_format($grand_line_total, 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <?php if ($quotation['notes']): ?>
                <div class="info-card">
                    <h6><i class="bx bx-note"></i> Notes:</h6>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="amount-summary">
                    <h6>Amount Summary</h6>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>₹<?= number_format($quotation['subtotal'], 2) ?></span>
                    </div>
                    <?php if ($total_discount > 0): ?>
                    <div class="summary-row">
                        <span>Total Discount:</span>
                        <span class="text-danger">- ₹<?= number_format($total_discount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($total_tax > 0): ?>
                    <div class="summary-row">
                        <span>Total Tax:</span>
                        <span>₹<?= number_format($total_tax, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span>Grand Total:</span>
                        <span>₹<?= number_format($quotation['grand_total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-note">
            <p class="mb-1">This is a computer generated quotation and does not require a signature.</p>
            <p class="mb-0">Valid until: <?= date('d M Y', strtotime($quotation['valid_until'])) ?></p>
        </div>
        
        <div class="text-center no-print mt-4">
            <a href="quotation_print.php?id=<?= $quotation['id'] ?>" target="_blank" class="download-btn me-2">
                <i class="bx bx-printer"></i> Print Quotation
            </a>
            <button onclick="window.print()" class="download-btn">
                <i class="bx bx-download"></i> Download PDF
            </button>
        </div>
    </div>
</body>
</html>