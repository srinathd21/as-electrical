<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$business_id = $_SESSION['business_id'] ?? 1;
$customer_id = (int)($_GET['customer_id'] ?? 0);

if (!$customer_id) {
    $_SESSION['error'] = "Invalid customer.";
    header('Location: customers.php');
    exit();
}

// Fetch customer details with proper outstanding calculation
$stmt = $pdo->prepare("
    SELECT c.*, 
           COALESCE(SUM(i.pending_amount), 0) as invoice_outstanding
    FROM customers c
    LEFT JOIN invoices i ON i.customer_id = c.id AND i.business_id = ? AND i.pending_amount > 0
    WHERE c.id = ? AND c.business_id = ?
    GROUP BY c.id
");
$stmt->execute([$business_id, $customer_id, $business_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error'] = "Customer not found.";
    header('Location: customers.php');
    exit();
}

// Calculate total outstanding (manual + invoice)
$manual_outstanding = ($customer['outstanding_type'] == 'debit') ? -$customer['outstanding_amount'] : $customer['outstanding_amount'];
$total_outstanding = $manual_outstanding + $customer['invoice_outstanding'];

// Calculate credit limit usage
$credit_limit = $customer['credit_limit'] ?? 0;
$available_credit = max(0, $credit_limit - $total_outstanding);
$credit_used = min($total_outstanding, $credit_limit);
$credit_utilization = $credit_limit > 0 ? ($credit_used / $credit_limit) * 100 : 0;

// =============================
// FETCH COMPLETE CREDIT/DEBIT STATEMENT
// =============================
$statement_data = [];

// 1. Initial outstanding from customer record (if any)
if ($customer['outstanding_amount'] > 0) {
    $statement_data[] = [
        'date' => $customer['created_at'],
        'type' => 'opening_balance',
        'description' => 'Opening Balance',
        'credit' => $customer['outstanding_type'] == 'credit' ? $customer['outstanding_amount'] : 0,
        'debit' => $customer['outstanding_type'] == 'debit' ? $customer['outstanding_amount'] : 0,
        'balance' => $customer['outstanding_type'] == 'credit' ? $customer['outstanding_amount'] : -$customer['outstanding_amount'],
        'reference' => 'SYSTEM',
        'invoice_id' => null,
        'payment_id' => null
    ];
}

// 2. All invoices (credit transactions)
$invoices_stmt = $pdo->prepare("
    SELECT id, invoice_number, created_at, total, pending_amount, 
           paid_amount, payment_status, cash_received, change_given
    FROM invoices
    WHERE customer_id = ? AND business_id = ?
    ORDER BY created_at ASC
");
$invoices_stmt->execute([$customer_id, $business_id]);
$invoices = $invoices_stmt->fetchAll();

foreach ($invoices as $inv) {
    $statement_data[] = [
        'date' => $inv['created_at'],
        'type' => 'invoice',
        'description' => 'Invoice: ' . $inv['invoice_number'],
        'credit' => $inv['total'],
        'debit' => 0,
        'balance' => 0,
        'reference' => $inv['invoice_number'],
        'invoice_id' => $inv['id'],
        'payment_id' => null
    ];
    
    // If invoice was partially paid on creation, add that payment immediately
    if ($inv['paid_amount'] > 0) {
        $statement_data[] = [
            'date' => $inv['created_at'],
            'type' => 'payment',
            'description' => 'Payment against Invoice: ' . $inv['invoice_number'] . ' (initial)',
            'credit' => 0,
            'debit' => $inv['paid_amount'],
            'balance' => 0,
            'reference' => $inv['invoice_number'],
            'invoice_id' => $inv['id'],
            'payment_id' => null
        ];
    }
}

// 3. All invoice payments (debit transactions)
$payments_stmt = $pdo->prepare("
    SELECT ip.*, i.invoice_number, u.full_name as recorded_by
    FROM invoice_payments ip
    LEFT JOIN invoices i ON ip.invoice_id = i.id
    LEFT JOIN users u ON ip.created_by = u.id
    WHERE ip.customer_id = ? AND ip.business_id = ?
    ORDER BY ip.payment_date ASC, ip.created_at ASC
");
$payments_stmt->execute([$customer_id, $business_id]);
$payments = $payments_stmt->fetchAll();

foreach ($payments as $pay) {
    $statement_data[] = [
        'date' => $pay['payment_date'] . ' ' . date('H:i:s', strtotime($pay['created_at'])),
        'type' => 'payment',
        'description' => 'Payment - ' . ($pay['notes'] ?? 'Against Invoice: ' . $pay['invoice_number']),
        'credit' => 0,
        'debit' => $pay['payment_amount'],
        'balance' => 0,
        'reference' => $pay['invoice_number'] ?? $pay['reference_no'] ?? 'PAY-' . $pay['id'],
        'invoice_id' => $pay['invoice_id'],
        'payment_id' => $pay['id']
    ];
}

// 4. All manual credit adjustments
$adjustments_stmt = $pdo->prepare("
    SELECT * FROM customer_credit_adjustments 
    WHERE customer_id = ? AND business_id = ?
    ORDER BY adjustment_date ASC, created_at ASC
");
$adjustments_stmt->execute([$customer_id, $business_id]);
$adjustments = $adjustments_stmt->fetchAll();

foreach ($adjustments as $adj) {
    if ($adj['adjustment_type'] == 'credit') {
        $statement_data[] = [
            'date' => $adj['adjustment_date'] . ' ' . date('H:i:s', strtotime($adj['created_at'])),
            'type' => 'adjustment_credit',
            'description' => 'Credit Adjustment: ' . ($adj['description'] ?? 'Manual adjustment'),
            'credit' => $adj['amount'],
            'debit' => 0,
            'balance' => 0,
            'reference' => 'ADJ-CR-' . $adj['id'],
            'invoice_id' => null,
            'payment_id' => null
        ];
    } else {
        $statement_data[] = [
            'date' => $adj['adjustment_date'] . ' ' . date('H:i:s', strtotime($adj['created_at'])),
            'type' => 'adjustment_debit',
            'description' => 'Debit Adjustment: ' . ($adj['description'] ?? 'Manual adjustment'),
            'credit' => 0,
            'debit' => $adj['amount'],
            'balance' => 0,
            'reference' => 'ADJ-DR-' . $adj['id'],
            'invoice_id' => null,
            'payment_id' => null
        ];
    }
}

// Sort all transactions by date
usort($statement_data, function($a, $b) {
    return strtotime($a['date']) <=> strtotime($b['date']);
});

// Calculate running balance
$running_balance = 0;
foreach ($statement_data as &$transaction) {
    if ($transaction['type'] == 'opening_balance') {
        $running_balance = $transaction['balance'];
        $transaction['balance'] = $running_balance;
    } else {
        $running_balance += $transaction['credit'];
        $running_balance -= $transaction['debit'];
        $transaction['balance'] = $running_balance;
    }
}

// Calculate totals for summary
$total_credit = array_sum(array_column($statement_data, 'credit'));
$total_debit = array_sum(array_column($statement_data, 'debit'));
$opening_balance = $statement_data[0]['balance'] ?? 0;
$closing_balance = end($statement_data)['balance'] ?? $opening_balance;

// Fetch business details for header
$business_stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$business_stmt->execute([$business_id]);
$business = $business_stmt->fetch();

// Get current user
$user_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Statement - <?= htmlspecialchars($customer['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @media print {
            body { 
                margin: 0;
                padding: 10px;
                font-size: 11px;
                line-height: 1.3;
            }
            .no-print { display: none !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .table { border-collapse: collapse; width: 100%; }
            .table th, .table td { 
                border: 1px solid #000; 
                padding: 5px 6px; 
                text-align: left;
            }
            .table th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-weight: bold;
            }
            .statement-container { 
                margin: 0; 
                padding: 15px;
                width: 100%;
                background: white;
            }
            .header-section { 
                border-bottom: 2px solid #333;
                margin-bottom: 15px;
                padding-bottom: 10px;
            }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .fw-bold { font-weight: bold; }
            .border-bottom { border-bottom: 1px solid #333; }
            .border-top { border-top: 1px solid #333; }
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f8f9fa;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .statement-container {
            max-width: 210mm;
            margin: 20px auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .header-section {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .statement-title {
            font-weight: bold;
            font-size: 20px;
            border-left: 3px solid #333;
            padding-left: 10px;
        }
        
        .customer-info-box {
            border: 1px solid #333;
            padding: 12px;
            margin-bottom: 20px;
            background-color: #fff;
        }
        
        .summary-box {
            border: 1px solid #333;
            padding: 10px;
            text-align: center;
            background-color: #fff;
        }
        
        .summary-box h4, .summary-box h6 {
            margin: 5px 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .table th {
            border: 1px solid #000;
            padding: 8px 6px;
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .table td {
            border: 1px solid #000;
            padding: 6px 6px;
            vertical-align: top;
        }
        
        .table tfoot th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .footer-section {
            border-top: 1px solid #333;
            margin-top: 25px;
            padding-top: 15px;
            font-size: 10px;
        }
        
        .badge {
            padding: 2px 5px;
            border: 1px solid #333;
            font-size: 10px;
            background-color: #fff;
            color: #000;
            font-weight: normal;
        }
        
        .progress {
            height: 8px;
            background-color: #f0f0f0;
            border: 1px solid #333;
            margin: 5px 0;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #333;
        }
        
        .border-left {
            border-left: 1px solid #333;
        }
        
        .border-right {
            border-right: 1px solid #333;
        }
        
        .border-top {
            border-top: 2px solid #333;
        }
        
        .border-bottom {
            border-bottom: 1px solid #333;
        }
        
        hr {
            border-top: 1px solid #333;
        }
        
        .alert-light {
            background-color: #f9f9f9;
            border: 1px solid #333;
            padding: 8px 12px;
        }
        
        /* Remove all colors */
        * {
            color: #000 !important;
            background-color: #fff !important;
        }
        
        .text-success, .text-danger, .text-warning, .text-info, .text-primary {
            color: #000 !important;
        }
        
        .bg-success, .bg-danger, .bg-warning, .bg-info, .bg-primary {
            background-color: transparent !important;
            color: #000 !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row no-print mb-4">
            <div class="col-12">
                <div class="alert alert-secondary d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-info-circle me-2"></i>
                        Click the button below to download or print this statement as PDF
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-dark" onclick="downloadPDF()">
                            <i class="bi bi-download me-2"></i>Download PDF
                        </button>
                        <button class="btn btn-secondary" onclick="printPDF()">
                            <i class="bi bi-printer me-2"></i>Print PDF
                        </button>
                        <a href="customer_credit_statement.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-dark">
                            <i class="bi bi-arrow-left me-2"></i>Back to Statement
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statement Content (This will be converted to PDF) -->
        <div id="pdf-content" class="statement-container">
            <!-- Header Section -->
            <div class="header-section">
                <div class="row">
                    <div class="col-8">
                        <div class="company-name">
                            <?= htmlspecialchars($business['name'] ?? 'YOUR BUSINESS NAME') ?>
                        </div>
                        <?php if (!empty($business['address'])): ?>
                        <div class="mb-1"><?= nl2br(htmlspecialchars($business['address'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($business['phone'])): ?>
                        <div class="mb-1">Phone: <?= htmlspecialchars($business['phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($business['email'])): ?>
                        <div class="mb-1">Email: <?= htmlspecialchars($business['email']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($business['gstin'])): ?>
                        <div class="mb-0">GSTIN: <?= htmlspecialchars($business['gstin']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-4 text-end">
                        <div class="statement-title">CREDIT STATEMENT</div>
                        <div class="mt-2">
                            <div><strong>Statement Date:</strong> <?= date('d M Y') ?></div>
                            <div><strong>Statement No:</strong> STMT-<?= date('Ymd') . '-' . $customer_id ?></div>
                            <div><strong>Generated By:</strong> <?= htmlspecialchars($current_user['full_name'] ?? 'System') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="customer-info-box">
                <div class="row">
                    <div class="col-7">
                        <h4 class="mb-2 fw-bold"><?= htmlspecialchars($customer['name']) ?></h4>
                        <?php if ($customer['phone']): ?>
                        <div class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?></div>
                        <?php endif; ?>
                        <?php if ($customer['email']): ?>
                        <div class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></div>
                        <?php endif; ?>
                        <?php if ($customer['address']): ?>
                        <div class="mb-1"><strong>Address:</strong> <?= nl2br(htmlspecialchars($customer['address'])) ?></div>
                        <?php endif; ?>
                        <?php if ($customer['gstin']): ?>
                        <div class="mb-0"><strong>GSTIN:</strong> <?= htmlspecialchars($customer['gstin']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-5 text-end">
                        <div class="p-2 border">
                            <div><strong>Current Outstanding</strong></div>
                            <div class="fw-bold" style="font-size: 24px;">₹<?= number_format(abs($total_outstanding), 2) ?></div>
                            <div class="small">
                                <?php if ($total_outstanding > 0): ?>
                                <span class="badge">Customer Owes You</span>
                                <?php elseif ($total_outstanding < 0): ?>
                                <span class="badge">You Owe Customer</span>
                                <?php else: ?>
                                <span class="badge">No Dues</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($credit_limit > 0): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><strong>Credit Limit Information</strong></div>
                            <div class="badge">Utilization: <?= number_format($credit_utilization, 1) ?>%</div>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar" style="width: <?= min($credit_utilization, 100) ?>%;"></div>
                        </div>
                        <div class="row mt-2 text-center">
                            <div class="col-3 border-right">
                                <div class="small">Limit</div>
                                <div class="fw-bold">₹<?= number_format($credit_limit, 2) ?></div>
                            </div>
                            <div class="col-3 border-right">
                                <div class="small">Used</div>
                                <div class="fw-bold">₹<?= number_format($credit_used, 2) ?></div>
                            </div>
                            <div class="col-3 border-right">
                                <div class="small">Available</div>
                                <div class="fw-bold">₹<?= number_format($available_credit, 2) ?></div>
                            </div>
                            <div class="col-3">
                                <div class="small">Status</div>
                                <div class="fw-bold"><?= $available_credit > 0 ? 'Within Limit' : 'Over Limit' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Statement Summary -->
            <div class="row mb-4">
                <div class="col-3">
                    <div class="summary-box">
                        <div>Opening Balance</div>
                        <div class="fw-bold" style="font-size: 18px;">₹<?= number_format(abs($opening_balance), 2) ?></div>
                        <div class="small"><?= $opening_balance >= 0 ? 'Credit' : 'Debit' ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="summary-box">
                        <div>Total Credit</div>
                        <div class="fw-bold" style="font-size: 18px;">₹<?= number_format($total_credit, 2) ?></div>
                        <div class="small">Added to Account</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="summary-box">
                        <div>Total Debit</div>
                        <div class="fw-bold" style="font-size: 18px;">₹<?= number_format($total_debit, 2) ?></div>
                        <div class="small">Received from Customer</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="summary-box">
                        <div>Closing Balance</div>
                        <div class="fw-bold" style="font-size: 18px;">₹<?= number_format(abs($closing_balance), 2) ?></div>
                        <div class="small"><?= $closing_balance >= 0 ? 'Credit (Customer Owes)' : 'Debit (You Owe)' ?></div>
                    </div>
                </div>
            </div>

            <!-- Statement Period -->
            <div class="alert alert-light mb-3">
                <div class="row">
                    <div class="col-6">
                        <strong>Statement Period:</strong> 
                        <?php if (!empty($statement_data)): ?>
                        <?= date('d M Y', strtotime($statement_data[0]['date'])) ?> to <?= date('d M Y', strtotime(end($statement_data)['date'])) ?>
                        <?php else: ?>
                        N/A
                        <?php endif; ?>
                    </div>
                    <div class="col-6 text-end">
                        <strong>Total Transactions:</strong> <?= count($statement_data) ?>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="10%">Date</th>
                            <th width="10%">Type</th>
                            <th width="25%">Description</th>
                            <th width="15%">Reference</th>
                            <th width="12%" class="text-end">Credit (₹)</th>
                            <th width="12%" class="text-end">Debit (₹)</th>
                            <th width="16%" class="text-end">Balance (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($statement_data)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                                <div>No transactions found for this customer</div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($statement_data as $transaction): 
                            $type_text = '';
                            switch ($transaction['type']) {
                                case 'invoice': $type_text = 'INVOICE'; break;
                                case 'payment': $type_text = 'PAYMENT'; break;
                                case 'adjustment_credit': $type_text = 'CREDIT ADJ'; break;
                                case 'adjustment_debit': $type_text = 'DEBIT ADJ'; break;
                                case 'opening_balance': $type_text = 'OPENING'; break;
                            }
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($transaction['date'])) ?></td>
                            <td><?= $type_text ?></td>
                            <td><?= htmlspecialchars($transaction['description']) ?></td>
                            <td><?= htmlspecialchars($transaction['reference']) ?></td>
                            <td class="text-end"><?= $transaction['credit'] > 0 ? '₹' . number_format($transaction['credit'], 2) : '-' ?></td>
                            <td class="text-end"><?= $transaction['debit'] > 0 ? '₹' . number_format($transaction['debit'], 2) : '-' ?></td>
                            <td class="text-end fw-bold">
                                ₹<?= number_format(abs($transaction['balance']), 2) ?>
                                <br>
                                <small><?= $transaction['balance'] >= 0 ? 'Cr' : 'Dr' ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($statement_data)): ?>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">TOTALS</th>
                            <th class="text-end border-top">₹<?= number_format($total_credit, 2) ?></th>
                            <th class="text-end border-top">₹<?= number_format($total_debit, 2) ?></th>
                            <th class="text-end border-top">
                                ₹<?= number_format(abs($closing_balance), 2) ?>
                                <br>
                                <small><?= $closing_balance >= 0 ? 'Credit' : 'Debit' ?></small>
                            </th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Notes Section -->
            <div class="row mt-4">
                <div class="col-6">
                    <div class="border p-2">
                        <div class="fw-bold mb-2">Notes</div>
                        <ul class="mb-0 small" style="padding-left: 15px;">
                            <li>All amounts are in Indian Rupees (₹)</li>
                            <li>Credit (Cr) - Customer owes you money</li>
                            <li>Debit (Dr) - You owe customer money</li>
                            <li>Credit adjustments increase customer's outstanding</li>
                            <li>Debit adjustments reduce customer's outstanding</li>
                        </ul>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border p-2">
                        <div class="fw-bold mb-2">Important Dates</div>
                        <ul class="mb-0 small" style="padding-left: 15px;">
                            <li><strong>Statement Date:</strong> <?= date('d M Y') ?></li>
                            <li><strong>Next Statement:</strong> <?= date('d M Y', strtotime('+1 month')) ?></li>
                            <li><strong>Payment Due:</strong> 
                                <?php if ($total_outstanding > 0): ?>
                                Immediate
                                <?php else: ?>
                                N/A
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer-section">
                <div class="row">
                    <div class="col-6">
                        <div><strong>Generated On:</strong> <?= date('d M Y, h:i A') ?></div>
                        <div><strong>Generated By:</strong> <?= htmlspecialchars($current_user['full_name'] ?? 'System') ?></div>
                        <div><strong>Page:</strong> 1 of 1</div>
                    </div>
                    <div class="col-6 text-end">
                        <div><strong>Signature:</strong> _________________________</div>
                        <div class="mt-2"><strong>Stamp:</strong></div>
                        <div class="mt-2 border-bottom" style="width: 150px; margin-left: auto;"></div>
                    </div>
                </div>
                <hr>
                <div class="text-center small">
                    <div><strong><?= htmlspecialchars($business['name'] ?? 'YOUR BUSINESS NAME') ?></strong></div>
                    <div>This is a computer-generated statement. No signature required.</div>
                    <?php if (!empty($business['phone'])): ?>
                    <div>For any queries, contact: <?= htmlspecialchars($business['phone']) ?> | <?= htmlspecialchars($business['email'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to download PDF
        function downloadPDF() {
            const element = document.getElementById('pdf-content');
            const options = {
                margin: [10, 10, 10, 10],
                filename: 'Credit_Statement_<?= preg_replace('/[^A-Za-z0-9]/', '_', $customer['name']) ?>_<?= date('Ymd') ?>.pdf',
                image: { type: 'jpeg', quality: 1 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true,
                    backgroundColor: '#ffffff'
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait',
                    compress: true
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };

            // Show loading indicator
            showLoading('Generating PDF...');

            // Generate and download PDF
            html2pdf()
                .set(options)
                .from(element)
                .save()
                .then(() => {
                    hideLoading();
                })
                .catch(err => {
                    hideLoading();
                    alert('Error generating PDF: ' + err.message);
                    console.error(err);
                });
        }

        // Function to print PDF
        function printPDF() {
            const element = document.getElementById('pdf-content');
            const options = {
                margin: [10, 10, 10, 10],
                filename: 'Credit_Statement_<?= preg_replace('/[^A-Za-z0-9]/', '_', $customer['name']) ?>_<?= date('Ymd') ?>.pdf',
                image: { type: 'jpeg', quality: 1 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true,
                    backgroundColor: '#ffffff'
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait'
                }
            };

            // Show loading indicator
            showLoading('Preparing for print...');

            // Generate PDF and open in new window for printing
            html2pdf()
                .set(options)
                .from(element)
                .toPdf()
                .get('pdf')
                .then(pdf => {
                    hideLoading();
                    window.open(pdf.output('bloburl'), '_blank');
                })
                .catch(err => {
                    hideLoading();
                    alert('Error preparing PDF for print: ' + err.message);
                    console.error(err);
                });
        }

        // Function to show loading indicator
        function showLoading(message) {
            // Create loading overlay
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                color: white;
                font-size: 1.2rem;
            `;
            
            const spinner = document.createElement('div');
            spinner.style.cssText = `
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 15px auto;
            `;
            
            const text = document.createElement('div');
            text.textContent = message || 'Processing...';
            text.style.textAlign = 'center';
            
            const container = document.createElement('div');
            container.style.cssText = 'text-align: center; background: transparent;';
            container.appendChild(spinner);
            container.appendChild(text);
            
            overlay.appendChild(container);
            document.body.appendChild(overlay);
            
            // Add CSS for spinner animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                #loading-overlay * {
                    background: transparent !important;
                    color: white !important;
                }
            `;
            document.head.appendChild(style);
        }

        // Function to hide loading indicator
        function hideLoading() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }

        // Ensure proper rendering before PDF generation
        window.addEventListener('load', function() {
            // Force background colors to be white
            document.querySelectorAll('*').forEach(el => {
                if (el.classList) {
                    el.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-primary');
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>