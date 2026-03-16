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

// Fetch customer details
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

// Calculate manual outstanding
$manual_outstanding = ($customer['outstanding_type'] == 'debit') ? -$customer['outstanding_amount'] : $customer['outstanding_amount'];
$total_outstanding = $manual_outstanding + $customer['invoice_outstanding'];

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

// Calculate totals
$total_credit = array_sum(array_column($statement_data, 'credit'));
$total_debit = array_sum(array_column($statement_data, 'debit'));
$opening_balance = !empty($statement_data) ? $statement_data[0]['balance'] : 0;
$closing_balance = !empty($statement_data) ? end($statement_data)['balance'] : $opening_balance;

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="credit_statement_' . preg_replace('/[^a-zA-Z0-9]/', '_', $customer['name']) . '_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Create Excel/HTML table with proper structure
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; font-size: 12px; }';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #4CAF50; color: white; font-weight: bold; text-align: center; border: 1px solid #000; }';
echo 'td { border: 1px solid #ccc; padding: 5px; }';
echo '.header-title { font-size: 18px; font-weight: bold; text-align: center; background-color: #e7f3ff; }';
echo '.section-header { background-color: #f0f0f0; font-weight: bold; }';
echo '.text-right { text-align: right; }';
echo '.text-center { text-align: center; }';
echo '.text-success { color: #28a745; }';
echo '.text-danger { color: #dc3545; }';
echo '.bg-light { background-color: #f8f9fa; }';
echo '.fw-bold { font-weight: bold; }';
echo '.border-bottom { border-bottom: 2px solid #000; }';
echo '</style>';
echo '</head>';
echo '<body>';

// Company/Business Information
echo '<table cellpadding="5" cellspacing="0" border="1">';

// Title Row
echo '<tr>';
echo '<td colspan="7" class="header-title" style="font-size: 18px; padding: 15px;">';
echo 'CREDIT/DEBIT STATEMENT';
echo '</td>';
echo '</tr>';

// Customer Name
echo '<tr>';
echo '<td colspan="7" style="text-align: center; font-size: 14px; font-weight: bold; padding: 10px;">';
echo htmlspecialchars($customer['name']);
echo '</td>';
echo '</tr>';

// Generated Date
echo '<tr>';
echo '<td colspan="7" style="text-align: center; background-color: #f5f5f5;">';
echo 'Generated on: ' . date('d M Y h:i A');
echo '</td>';
echo '</tr>';

// Customer Details Section
echo '<tr class="section-header">';
echo '<th colspan="7" style="background-color: #e7f3ff; color: #000; text-align: left;">CUSTOMER DETAILS</th>';
echo '</tr>';

// Customer details in two columns
$details = [];
$details[] = ['Customer Name:', htmlspecialchars($customer['name'])];
if ($customer['phone']) $details[] = ['Phone:', htmlspecialchars($customer['phone'])];
if ($customer['email']) $details[] = ['Email:', htmlspecialchars($customer['email'])];
if ($customer['address']) $details[] = ['Address:', nl2br(htmlspecialchars($customer['address']))];
if ($customer['gstin']) $details[] = ['GSTIN:', htmlspecialchars($customer['gstin'])];

foreach ($details as $index => $detail) {
    echo '<tr>';
    echo '<td colspan="2" style="background-color: #f9f9f9; font-weight: bold;">' . $detail[0] . '</td>';
    echo '<td colspan="5">' . $detail[1] . '</td>';
    echo '</tr>';
}

// Summary Statistics Section
echo '<tr class="section-header">';
echo '<th colspan="7" style="background-color: #e7f3ff; color: #000; text-align: left;">SUMMARY STATISTICS</th>';
echo '</tr>';

// Summary header
echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<th style="text-align: center;">Opening Balance</th>';
echo '<th style="text-align: center;">Total Credit</th>';
echo '<th style="text-align: center;">Total Debit</th>';
echo '<th style="text-align: center;">Closing Balance</th>';
echo '<th style="text-align: center;">Credit Limit</th>';
echo '<th style="text-align: center;">Credit Used</th>';
echo '<th style="text-align: center;">Available</th>';
echo '</tr>';

// Summary values
$credit_used = min($total_outstanding, $customer['credit_limit'] ?? 0);
$available_credit = max(0, ($customer['credit_limit'] ?? 0) - $total_outstanding);

echo '<tr>';
echo '<td class="text-center" style="' . ($opening_balance >= 0 ? 'color: #dc3545;' : 'color: #28a745;') . ' font-weight: bold;">';
echo '₹' . number_format(abs($opening_balance), 2) . ' ' . ($opening_balance >= 0 ? '(Cr)' : '(Dr)');
echo '</td>';
echo '<td class="text-center" style="color: #28a745; font-weight: bold;">₹' . number_format($total_credit, 2) . '</td>';
echo '<td class="text-center" style="color: #dc3545; font-weight: bold;">₹' . number_format($total_debit, 2) . '</td>';
echo '<td class="text-center" style="' . ($closing_balance >= 0 ? 'color: #dc3545;' : 'color: #28a745;') . ' font-weight: bold;">';
echo '₹' . number_format(abs($closing_balance), 2) . ' ' . ($closing_balance >= 0 ? '(Cr)' : '(Dr)');
echo '</td>';
echo '<td class="text-center">₹' . number_format($customer['credit_limit'] ?? 0, 2) . '</td>';
echo '<td class="text-center">₹' . number_format($credit_used, 2) . '</td>';
echo '<td class="text-center">₹' . number_format($available_credit, 2) . '</td>';
echo '</tr>';

// Transaction Statement Header
echo '<tr class="section-header">';
echo '<th colspan="7" style="background-color: #e7f3ff; color: #000; text-align: left;">TRANSACTION STATEMENT</th>';
echo '</tr>';

// Main Statement Table Headers
echo '<thead>';
echo '<tr style="background-color: #4CAF50; color: white;">';
echo '<th style="text-align: center; width: 10%;">Date</th>';
echo '<th style="text-align: center; width: 8%;">Type</th>';
echo '<th style="text-align: center; width: 25%;">Description</th>';
echo '<th style="text-align: center; width: 12%;">Reference</th>';
echo '<th style="text-align: center; width: 12%;">Credit (₹)</th>';
echo '<th style="text-align: center; width: 12%;">Debit (₹)</th>';
echo '<th style="text-align: center; width: 13%;">Balance (₹)</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (empty($statement_data)) {
    echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">No transactions found</td></tr>';
} else {
    foreach ($statement_data as $transaction) {
        // Determine type text
        $type_text = '';
        switch ($transaction['type']) {
            case 'invoice': $type_text = 'Invoice'; break;
            case 'payment': $type_text = 'Payment'; break;
            case 'adjustment_credit': $type_text = 'Credit Adj'; break;
            case 'adjustment_debit': $type_text = 'Debit Adj'; break;
            case 'opening_balance': $type_text = 'Opening'; break;
            default: $type_text = $transaction['type'];
        }
        
        echo '<tr>';
        echo '<td style="text-align: center;">' . date('d-m-Y', strtotime($transaction['date'])) . '</td>';
        echo '<td style="text-align: center;">' . $type_text . '</td>';
        echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
        echo '<td style="text-align: center;">' . htmlspecialchars($transaction['reference']) . '</td>';
        echo '<td style="text-align: right; ' . ($transaction['credit'] > 0 ? 'color: #28a745; font-weight: bold;' : '') . '">';
        echo $transaction['credit'] > 0 ? '₹' . number_format($transaction['credit'], 2) : '-';
        echo '</td>';
        echo '<td style="text-align: right; ' . ($transaction['debit'] > 0 ? 'color: #dc3545; font-weight: bold;' : '') . '">';
        echo $transaction['debit'] > 0 ? '₹' . number_format($transaction['debit'], 2) : '-';
        echo '</td>';
        echo '<td style="text-align: right; ' . ($transaction['balance'] >= 0 ? 'color: #dc3545;' : 'color: #28a745;') . ' font-weight: bold;">';
        echo '₹' . number_format(abs($transaction['balance']), 2) . ' ' . ($transaction['balance'] >= 0 ? 'Cr' : 'Dr');
        echo '</td>';
        echo '</tr>';
    }
    
    // Totals row
    echo '<tr style="background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #000;">';
    echo '<td colspan="4" style="text-align: right;">TOTALS:</td>';
    echo '<td style="text-align: right; color: #28a745;">₹' . number_format($total_credit, 2) . '</td>';
    echo '<td style="text-align: right; color: #dc3545;">₹' . number_format($total_debit, 2) . '</td>';
    echo '<td style="text-align: right; ' . ($closing_balance >= 0 ? 'color: #dc3545;' : 'color: #28a745;') . '">';
    echo '₹' . number_format(abs($closing_balance), 2) . ' ' . ($closing_balance >= 0 ? 'Cr' : 'Dr');
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';

// Notes Section
echo '<tr class="section-header">';
echo '<th colspan="7" style="background-color: #e7f3ff; color: #000; text-align: left;">NOTES</th>';
echo '</tr>';
echo '<tr>';
echo '<td colspan="7">';
echo '<ul style="margin: 5px 0;">';
echo '<li><span style="color: #28a745;">Credit (Cr)</span> - Customer owes you money (Receivable)</li>';
echo '<li><span style="color: #dc3545;">Debit (Dr)</span> - You owe customer money (Payable)</li>';
echo '<li><strong>Opening Balance:</strong> ₹' . number_format(abs($opening_balance), 2) . ' ' . ($opening_balance >= 0 ? 'Cr (Customer Owes)' : 'Dr (You Owe)') . '</li>';
echo '<li><strong>Closing Balance:</strong> ₹' . number_format(abs($closing_balance), 2) . ' ' . ($closing_balance >= 0 ? 'Cr (Customer Owes)' : 'Dr (You Owe)') . '</li>';
if ($customer['credit_limit'] > 0) {
    $credit_utilization = $customer['credit_limit'] > 0 ? ($credit_used / $customer['credit_limit']) * 100 : 0;
    echo '<li><strong>Credit Limit:</strong> ₹' . number_format($customer['credit_limit'], 2) . '</li>';
    echo '<li><strong>Credit Utilization:</strong> ' . number_format($credit_utilization, 1) . '%</li>';
}
echo '</ul>';
echo '</td>';
echo '</tr>';

// Footer
echo '<tr>';
echo '<td colspan="7" style="text-align: center; padding: 10px; font-style: italic; background-color: #f5f5f5;">';
echo 'This is a computer generated statement - valid without signature';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</body>';
echo '</html>';
exit();
?>