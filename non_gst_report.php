<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'warehouse_manager', 'shop_manager'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$current_business_id = $_SESSION['current_business_id'] ?? null;

if (!$current_business_id) {
    $_SESSION['error'] = "Please select a business first.";
    header('Location: select_shop.php');
    exit();
}

// Default date range (current month)
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Get filters
$filter_start_date = $_GET['start_date'] ?? $start_date;
$filter_end_date = $_GET['end_date'] ?? $end_date;
$shop_id = $_GET['shop_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'summary';
$export_type = $_GET['export'] ?? ''; // excel, json, csv

// Get shops for filter
$shops = $pdo->prepare("
    SELECT id, shop_name, shop_code 
    FROM shops 
    WHERE business_id = ? 
    AND is_active = 1 
    ORDER BY shop_name
");
$shops->execute([$current_business_id]);
$shops = $shops->fetchAll();

// Function to get Non-GST invoice summary
function getNonGSTSummary($pdo, $business_id, $start_date, $end_date, $shop_id) {
    $params = [$business_id, $start_date, $end_date];
    $shop_condition = '';
    
    if ($shop_id && $shop_id !== 'all') {
        $shop_condition = " AND i.shop_id = ?";
        $params[] = $shop_id;
    }
    
    $query = "
        SELECT 
            DATE(i.created_at) as invoice_date,
            i.id as invoice_id,
            i.invoice_number,
            i.customer_type,
            i.gst_status,
            s.shop_name,
            c.name as customer_name,
            c.gstin as customer_gstin,
            SUM(ii.total_price) as total_price,
            SUM(ii.discount_amount) as total_discount,
            SUM(ii.original_price * ii.quantity) as total_cost,
            SUM((ii.unit_price - ii.original_price) * ii.quantity) as total_profit,
            SUM(ii.unit_price * ii.quantity) as subtotal,
            i.total as invoice_total
        FROM invoices i
        INNER JOIN invoice_items ii ON i.id = ii.invoice_id
        INNER JOIN shops s ON i.shop_id = s.id
        INNER JOIN customers c ON i.customer_id = c.id
        WHERE i.business_id = ?
        AND DATE(i.created_at) BETWEEN ? AND ?
        AND i.gst_status = 0
        $shop_condition
        GROUP BY i.id, DATE(i.created_at)
        ORDER BY i.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get Non-GST monthly trend
function getNonGSTMonthlyTrend($pdo, $business_id, $year, $shop_id) {
    $params = [$business_id, $year . '-01-01', $year . '-12-31'];
    $shop_condition = '';
    
    if ($shop_id && $shop_id !== 'all') {
        $shop_condition = " AND i.shop_id = ?";
        $params[] = $shop_id;
    }
    
    $query = "
        SELECT 
            DATE_FORMAT(i.created_at, '%Y-%m') as month,
            DATE_FORMAT(i.created_at, '%M %Y') as month_name,
            COUNT(DISTINCT i.id) as invoice_count,
            SUM(ii.unit_price * ii.quantity) as total_sales,
            SUM(ii.original_price * ii.quantity) as total_cost,
            SUM((ii.unit_price - ii.original_price) * ii.quantity) as total_profit,
            ROUND(AVG(CASE WHEN ii.original_price > 0 
                THEN ((ii.unit_price - ii.original_price) / ii.original_price) * 100 
                ELSE 0 END), 2) as avg_margin_percent
        FROM invoices i
        INNER JOIN invoice_items ii ON i.id = ii.invoice_id
        WHERE i.business_id = ?
        AND DATE(i.created_at) BETWEEN ? AND ?
        AND i.gst_status = 0
        $shop_condition
        GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
        ORDER BY month
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get Non-GST customer summary
function getNonGSTCustomerSummary($pdo, $business_id, $start_date, $end_date, $shop_id) {
    $params = [$business_id, $start_date, $end_date];
    $shop_condition = '';
    
    if ($shop_id && $shop_id !== 'all') {
        $shop_condition = " AND i.shop_id = ?";
        $params[] = $shop_id;
    }
    
    $query = "
        SELECT 
            c.id as customer_id,
            c.name as customer_name,
            c.phone as customer_phone,
            c.customer_type,
            COUNT(DISTINCT i.id) as invoice_count,
            SUM(i.total) as total_purchases,
            SUM(i.pending_amount) as total_due,
            SUM(CASE WHEN i.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
            SUM(CASE WHEN i.payment_status != 'paid' THEN 1 ELSE 0 END) as pending_invoices
        FROM invoices i
        INNER JOIN customers c ON i.customer_id = c.id
        WHERE i.business_id = ?
        AND DATE(i.created_at) BETWEEN ? AND ?
        AND i.gst_status = 0
        $shop_condition
        GROUP BY c.id
        HAVING COUNT(DISTINCT i.id) > 0
        ORDER BY total_purchases DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get payment method summary for Non-GST invoices
function getPaymentMethodSummary($pdo, $business_id, $start_date, $end_date, $shop_id) {
    $params = [$business_id, $start_date, $end_date];
    $shop_condition = '';
    
    if ($shop_id && $shop_id !== 'all') {
        $shop_condition = " AND shop_id = ?";
        $params[] = $shop_id;
    }
    
    $query = "
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(cash_amount) as total_cash,
            SUM(upi_amount) as total_upi,
            SUM(bank_amount) as total_bank,
            SUM(cheque_amount) as total_cheque,
            SUM(cash_amount + upi_amount + bank_amount + cheque_amount) as total_collected
        FROM invoices
        WHERE business_id = ?
        AND DATE(created_at) BETWEEN ? AND ?
        AND gst_status = 0
        AND payment_status IN ('paid', 'partial')
        $shop_condition
        GROUP BY payment_method
        ORDER BY total_collected DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get product-wise Non-GST sales
function getProductWiseNonGST($pdo, $business_id, $start_date, $end_date, $shop_id) {
    $params = [$business_id, $start_date, $end_date];
    $shop_condition = '';
    
    if ($shop_id && $shop_id !== 'all') {
        $shop_condition = " AND i.shop_id = ?";
        $params[] = $shop_id;
    }
    
    $query = "
        SELECT 
            p.id as product_id,
            p.product_name,
            p.product_code,
            p.unit_of_measure,
            c.category_name,
            s.subcategory_name,
            SUM(ii.quantity) as total_quantity,
            SUM(ii.unit_price * ii.quantity) as total_sales,
            SUM(ii.original_price * ii.quantity) as total_cost,
            SUM((ii.unit_price - ii.original_price) * ii.quantity) as total_profit,
            ROUND(AVG(CASE WHEN ii.original_price > 0 
                THEN ((ii.unit_price - ii.original_price) / ii.original_price) * 100 
                ELSE 0 END), 2) as avg_margin_percent,
            COUNT(DISTINCT i.id) as invoice_count
        FROM invoices i
        INNER JOIN invoice_items ii ON i.id = ii.invoice_id
        INNER JOIN products p ON ii.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        WHERE i.business_id = ?
        AND DATE(i.created_at) BETWEEN ? AND ?
        AND i.gst_status = 0
        $shop_condition
        GROUP BY p.id
        HAVING SUM(ii.quantity) > 0
        ORDER BY total_sales DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get reports based on type
if ($report_type === 'summary') {
    $non_gst_summary = getNonGSTSummary($pdo, $current_business_id, $filter_start_date, $filter_end_date, $shop_id);
    $payment_summary = getPaymentMethodSummary($pdo, $current_business_id, $filter_start_date, $filter_end_date, $shop_id);
} elseif ($report_type === 'customers') {
    $customer_summary = getNonGSTCustomerSummary($pdo, $current_business_id, $filter_start_date, $filter_end_date, $shop_id);
} elseif ($report_type === 'products') {
    $product_summary = getProductWiseNonGST($pdo, $current_business_id, $filter_start_date, $filter_end_date, $shop_id);
} elseif ($report_type === 'monthly') {
    $year = $_GET['year'] ?? date('Y');
    $monthly_trend = getNonGSTMonthlyTrend($pdo, $current_business_id, $year, $shop_id);
}

// Calculate totals for summary
$total_sales = 0;
$total_cost = 0;
$total_profit = 0;
$total_invoices = 0;
$total_quantity = 0;

if ($report_type === 'summary' && isset($non_gst_summary)) {
    foreach ($non_gst_summary as $row) {
        $total_sales += $row['invoice_total'];
        $total_cost += $row['total_cost'];
        $total_profit += $row['total_profit'];
        $total_invoices++;
    }
}

if ($report_type === 'products' && isset($product_summary)) {
    foreach ($product_summary as $row) {
        $total_sales += $row['total_sales'];
        $total_cost += $row['total_cost'];
        $total_profit += $row['total_profit'];
        $total_quantity += $row['total_quantity'];
    }
}

if ($report_type === 'customers' && isset($customer_summary)) {
    foreach ($customer_summary as $row) {
        $total_sales += $row['total_purchases'];
    }
}

// Export functionality
if ($export_type === 'excel') {
    exportToExcel($report_type, $filter_start_date, $filter_end_date, $shop_id);
    exit();
} elseif ($export_type === 'json') {
    exportToJSON($report_type, $filter_start_date, $filter_end_date, $shop_id);
    exit();
} elseif ($export_type === 'csv') {
    exportToCSV($report_type, $filter_start_date, $filter_end_date, $shop_id);
    exit();
}

// Export to CSV function
function exportToCSV($report_type, $start_date, $end_date, $shop_id) {
    global $pdo, $current_business_id, $shops;
    
    $filename = "non_gst_report_" . date('Y_m_d_H_i_s') . ".csv";
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Get shop name
    $shop_name = "All Shops";
    if ($shop_id !== 'all') {
        foreach ($shops as $shop) {
            if ($shop['id'] == $shop_id) {
                $shop_name = $shop['shop_name'];
                break;
            }
        }
    }
    
    // Report header
    fputcsv($output, ['Non-GST Report - ' . ucfirst($report_type)]);
    fputcsv($output, ['Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date))]);
    fputcsv($output, ['Shop: ' . $shop_name]);
    fputcsv($output, ['Generated on: ' . date('d M Y h:i A')]);
    fputcsv($output, ['']); // Empty row
    
    if ($report_type === 'summary') {
        $data = getNonGSTSummary($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        // Table headers
        fputcsv($output, ['Date', 'Invoice No', 'Customer', 'Customer Type', 'Shop', 'Subtotal', 'Discount', 'Total', 'Cost', 'Profit', 'Margin %']);
        
        // Data rows
        $grand_total = 0;
        $grand_cost = 0;
        $grand_profit = 0;
        
        foreach ($data as $row) {
            $margin = $row['total_cost'] > 0 ? 
                ($row['total_profit'] / $row['total_cost']) * 100 : 0;
            
            fputcsv($output, [
                date('d M Y', strtotime($row['invoice_date'])),
                $row['invoice_number'],
                $row['customer_name'],
                $row['customer_type'],
                $row['shop_name'],
                number_format($row['subtotal'], 2, '.', ''),
                number_format($row['total_discount'], 2, '.', ''),
                number_format($row['invoice_total'], 2, '.', ''),
                number_format($row['total_cost'], 2, '.', ''),
                number_format($row['total_profit'], 2, '.', ''),
                number_format($margin, 2) . '%'
            ]);
            
            $grand_total += $row['invoice_total'];
            $grand_cost += $row['total_cost'];
            $grand_profit += $row['total_profit'];
        }
        
        // Empty row
        fputcsv($output, ['']);
        
        // Summary row
        $grand_margin = $grand_cost > 0 ? ($grand_profit / $grand_cost) * 100 : 0;
        fputcsv($output, ['GRAND TOTALS', '', '', '', '',
            number_format(array_sum(array_column($data, 'subtotal')), 2, '.', ''),
            number_format(array_sum(array_column($data, 'total_discount')), 2, '.', ''),
            number_format($grand_total, 2, '.', ''),
            number_format($grand_cost, 2, '.', ''),
            number_format($grand_profit, 2, '.', ''),
            number_format($grand_margin, 2) . '%'
        ]);
        
        // Overall summary
        fputcsv($output, ['']);
        fputcsv($output, ['OVERALL SUMMARY']);
        fputcsv($output, ['Total Invoices', count($data)]);
        fputcsv($output, ['Total Sales', '₹' . number_format($grand_total, 2, '.', '')]);
        fputcsv($output, ['Total Cost', '₹' . number_format($grand_cost, 2, '.', '')]);
        fputcsv($output, ['Total Profit', '₹' . number_format($grand_profit, 2, '.', '')]);
        fputcsv($output, ['Average Margin', number_format($grand_margin, 2) . '%']);
        
    } elseif ($report_type === 'products') {
        $data = getProductWiseNonGST($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        // Table headers
        fputcsv($output, ['Product', 'Category', 'Unit', 'Invoices', 'Quantity', 'Sales', 'Cost', 'Profit', 'Margin %']);
        
        // Data rows
        $grand_qty = 0;
        $grand_sales = 0;
        $grand_cost = 0;
        $grand_profit = 0;
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['product_name'] . ' (' . $row['product_code'] . ')',
                $row['category_name'],
                $row['unit_of_measure'],
                $row['invoice_count'],
                $row['total_quantity'],
                number_format($row['total_sales'], 2, '.', ''),
                number_format($row['total_cost'], 2, '.', ''),
                number_format($row['total_profit'], 2, '.', ''),
                number_format($row['avg_margin_percent'], 2) . '%'
            ]);
            
            $grand_qty += $row['total_quantity'];
            $grand_sales += $row['total_sales'];
            $grand_cost += $row['total_cost'];
            $grand_profit += $row['total_profit'];
        }
        
        // Empty row
        fputcsv($output, ['']);
        
        // Summary row
        $grand_margin = $grand_cost > 0 ? ($grand_profit / $grand_cost) * 100 : 0;
        fputcsv($output, ['GRAND TOTALS', '', '', '',
            $grand_qty,
            number_format($grand_sales, 2, '.', ''),
            number_format($grand_cost, 2, '.', ''),
            number_format($grand_profit, 2, '.', ''),
            number_format($grand_margin, 2) . '%'
        ]);
        
    } elseif ($report_type === 'customers') {
        $data = getNonGSTCustomerSummary($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        // Table headers
        fputcsv($output, ['Customer', 'Phone', 'Type', 'Invoices', 'Paid', 'Pending', 'Total Purchases', 'Due %']);
        
        // Data rows
        $grand_invoices = 0;
        $grand_purchases = 0;
        $grand_due = 0;
        
        foreach ($data as $row) {
            $due_percent = $row['total_purchases'] > 0 ? 
                ($row['total_due'] / $row['total_purchases']) * 100 : 0;
            
            fputcsv($output, [
                $row['customer_name'],
                $row['customer_phone'] ?: '-',
                $row['customer_type'],
                $row['invoice_count'],
                $row['paid_invoices'],
                $row['pending_invoices'],
                number_format($row['total_purchases'], 2, '.', ''),
                number_format($due_percent, 2) . '%'
            ]);
            
            $grand_invoices += $row['invoice_count'];
            $grand_purchases += $row['total_purchases'];
            $grand_due += $row['total_due'];
        }
        
        // Empty row
        fputcsv($output, ['']);
        
        // Summary row
        $grand_due_percent = $grand_purchases > 0 ? ($grand_due / $grand_purchases) * 100 : 0;
        fputcsv($output, ['GRAND TOTALS', '', '',
            $grand_invoices, '', '',
            number_format($grand_purchases, 2, '.', ''),
            number_format($grand_due_percent, 2) . '%'
        ]);
        
    } elseif ($report_type === 'monthly') {
        $data = getNonGSTMonthlyTrend($pdo, $current_business_id, $_GET['year'] ?? date('Y'), $shop_id);
        
        // Table headers
        fputcsv($output, ['Month', 'Invoices', 'Sales', 'Cost', 'Profit', 'Margin %']);
        
        // Data rows
        $grand_invoices = 0;
        $grand_sales = 0;
        $grand_cost = 0;
        $grand_profit = 0;
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['month_name'],
                $row['invoice_count'],
                number_format($row['total_sales'], 2, '.', ''),
                number_format($row['total_cost'], 2, '.', ''),
                number_format($row['total_profit'], 2, '.', ''),
                number_format($row['avg_margin_percent'], 2) . '%'
            ]);
            
            $grand_invoices += $row['invoice_count'];
            $grand_sales += $row['total_sales'];
            $grand_cost += $row['total_cost'];
            $grand_profit += $row['total_profit'];
        }
        
        // Empty row
        fputcsv($output, ['']);
        
        // Summary row
        $grand_margin = $grand_cost > 0 ? ($grand_profit / $grand_cost) * 100 : 0;
        fputcsv($output, ['YEARLY TOTALS',
            $grand_invoices,
            number_format($grand_sales, 2, '.', ''),
            number_format($grand_cost, 2, '.', ''),
            number_format($grand_profit, 2, '.', ''),
            number_format($grand_margin, 2) . '%'
        ]);
    }
    
    fclose($output);
    exit();
}

// Export to Excel function
function exportToExcel($report_type, $start_date, $end_date, $shop_id) {
    global $pdo, $current_business_id, $shops;
    
    $filename = "non_gst_report_" . date('Y_m_d') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    // Get shop name
    $shop_name = "All Shops";
    if ($shop_id !== 'all') {
        foreach ($shops as $shop) {
            if ($shop['id'] == $shop_id) {
                $shop_name = $shop['shop_name'];
                break;
            }
        }
    }
    
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "td { padding: 5px; border: 1px solid #ddd; }";
    echo "th { background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd; }";
    echo ".header { background-color: #6c757d; color: white; font-weight: bold; }";
    echo ".total { background-color: #e8f5e9; font-weight: bold; }";
    echo ".profit-positive { color: #198754; }";
    echo ".profit-negative { color: #dc3545; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<table border='1'>";
    
    // Report header
    echo "<tr><td colspan='10' class='header' style='text-align:center;'>";
    echo "<h2>Non-GST Report - " . ucfirst($report_type) . "</h2>";
    echo "<p>Period: " . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date)) . "</p>";
    echo "<p>Shop: " . $shop_name . "</p>";
    echo "<p>Generated on: " . date('d M Y h:i A') . "</p>";
    echo "</td></tr>";
    
    if ($report_type === 'summary') {
        $data = getNonGSTSummary($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        // Table headers
        echo "<tr>";
        echo "<th>Date</th>";
        echo "<th>Invoice No</th>";
        echo "<th>Customer</th>";
        echo "<th>Type</th>";
        echo "<th>Shop</th>";
        echo "<th>Subtotal</th>";
        echo "<th>Discount</th>";
        echo "<th>Total</th>";
        echo "<th>Cost</th>";
        echo "<th>Profit</th>";
        echo "<th>Margin %</th>";
        echo "</tr>";
        
        // Data rows
        $grand_total = 0;
        $grand_cost = 0;
        $grand_profit = 0;
        
        foreach ($data as $row) {
            $margin = $row['total_cost'] > 0 ? 
                ($row['total_profit'] / $row['total_cost']) * 100 : 0;
            $profit_class = $row['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
            
            echo "<tr>";
            echo "<td>" . date('d M Y', strtotime($row['invoice_date'])) . "</td>";
            echo "<td>" . $row['invoice_number'] . "</td>";
            echo "<td>" . $row['customer_name'] . "</td>";
            echo "<td>" . ucfirst($row['customer_type']) . "</td>";
            echo "<td>" . $row['shop_name'] . "</td>";
            echo "<td>" . number_format($row['subtotal'], 2) . "</td>";
            echo "<td>" . number_format($row['total_discount'], 2) . "</td>";
            echo "<td>" . number_format($row['invoice_total'], 2) . "</td>";
            echo "<td>" . number_format($row['total_cost'], 2) . "</td>";
            echo "<td class='$profit_class'>" . number_format($row['total_profit'], 2) . "</td>";
            echo "<td>" . number_format($margin, 2) . "%</td>";
            echo "</tr>";
            
            $grand_total += $row['invoice_total'];
            $grand_cost += $row['total_cost'];
            $grand_profit += $row['total_profit'];
        }
        
        // Totals row
        $grand_margin = $grand_cost > 0 ? ($grand_profit / $grand_cost) * 100 : 0;
        $grand_profit_class = $grand_profit >= 0 ? 'profit-positive' : 'profit-negative';
        
        echo "<tr class='total'>";
        echo "<td colspan='5'><strong>TOTALS</strong></td>";
        echo "<td><strong>" . number_format(array_sum(array_column($data, 'subtotal')), 2) . "</strong></td>";
        echo "<td><strong>" . number_format(array_sum(array_column($data, 'total_discount')), 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_total, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_cost, 2) . "</strong></td>";
        echo "<td class='$grand_profit_class'><strong>" . number_format($grand_profit, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_margin, 2) . "%</strong></td>";
        echo "</tr>";
        
    } elseif ($report_type === 'products') {
        $data = getProductWiseNonGST($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        // Table headers
        echo "<tr>";
        echo "<th>Product</th>";
        echo "<th>Category</th>";
        echo "<th>Unit</th>";
        echo "<th>Invoices</th>";
        echo "<th>Quantity</th>";
        echo "<th>Sales</th>";
        echo "<th>Cost</th>";
        echo "<th>Profit</th>";
        echo "<th>Margin %</th>";
        echo "</tr>";
        
        // Data rows
        $grand_qty = 0;
        $grand_sales = 0;
        $grand_cost = 0;
        $grand_profit = 0;
        
        foreach ($data as $row) {
            $profit_class = $row['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
            
            echo "<tr>";
            echo "<td>" . $row['product_name'] . "<br><small>" . $row['product_code'] . "</small></td>";
            echo "<td>" . $row['category_name'] . "</td>";
            echo "<td>" . $row['unit_of_measure'] . "</td>";
            echo "<td>" . $row['invoice_count'] . "</td>";
            echo "<td>" . number_format($row['total_quantity']) . "</td>";
            echo "<td>" . number_format($row['total_sales'], 2) . "</td>";
            echo "<td>" . number_format($row['total_cost'], 2) . "</td>";
            echo "<td class='$profit_class'>" . number_format($row['total_profit'], 2) . "</td>";
            echo "<td>" . number_format($row['avg_margin_percent'], 2) . "%</td>";
            echo "</tr>";
            
            $grand_qty += $row['total_quantity'];
            $grand_sales += $row['total_sales'];
            $grand_cost += $row['total_cost'];
            $grand_profit += $row['total_profit'];
        }
        
        // Totals row
        $grand_margin = $grand_cost > 0 ? ($grand_profit / $grand_cost) * 100 : 0;
        $grand_profit_class = $grand_profit >= 0 ? 'profit-positive' : 'profit-negative';
        
        echo "<tr class='total'>";
        echo "<td colspan='4'><strong>TOTALS</strong></td>";
        echo "<td><strong>" . number_format($grand_qty) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_sales, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_cost, 2) . "</strong></td>";
        echo "<td class='$grand_profit_class'><strong>" . number_format($grand_profit, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_margin, 2) . "%</strong></td>";
        echo "</tr>";
        
    } elseif ($report_type === 'customers') {
        $data = getNonGSTCustomerSummary($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        // Table headers
        echo "<tr>";
        echo "<th>Customer</th>";
        echo "<th>Phone</th>";
        echo "<th>Type</th>";
        echo "<th>Invoices</th>";
        echo "<th>Paid</th>";
        echo "<th>Pending</th>";
        echo "<th>Total Purchases</th>";
        echo "<th>Due %</th>";
        echo "</tr>";
        
        // Data rows
        $grand_invoices = 0;
        $grand_purchases = 0;
        $grand_due = 0;
        
        foreach ($data as $row) {
            $due_percent = $row['total_purchases'] > 0 ? 
                ($row['total_due'] / $row['total_purchases']) * 100 : 0;
            
            echo "<tr>";
            echo "<td>" . $row['customer_name'] . "</td>";
            echo "<td>" . ($row['customer_phone'] ?: '-') . "</td>";
            echo "<td>" . ucfirst($row['customer_type']) . "</td>";
            echo "<td>" . $row['invoice_count'] . "</td>";
            echo "<td>" . $row['paid_invoices'] . "</td>";
            echo "<td>" . $row['pending_invoices'] . "</td>";
            echo "<td>" . number_format($row['total_purchases'], 2) . "</td>";
            echo "<td>" . number_format($due_percent, 2) . "%</td>";
            echo "</tr>";
            
            $grand_invoices += $row['invoice_count'];
            $grand_purchases += $row['total_purchases'];
            $grand_due += $row['total_due'];
        }
        
        // Totals row
        $grand_due_percent = $grand_purchases > 0 ? ($grand_due / $grand_purchases) * 100 : 0;
        
        echo "<tr class='total'>";
        echo "<td colspan='3'><strong>TOTALS</strong></td>";
        echo "<td><strong>" . $grand_invoices . "</strong></td>";
        echo "<td></td>";
        echo "<td></td>";
        echo "<td><strong>" . number_format($grand_purchases, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_due_percent, 2) . "%</strong></td>";
        echo "</tr>";
        
    } elseif ($report_type === 'monthly') {
        $data = getNonGSTMonthlyTrend($pdo, $current_business_id, $_GET['year'] ?? date('Y'), $shop_id);
        
        // Table headers
        echo "<tr>";
        echo "<th>Month</th>";
        echo "<th>Invoices</th>";
        echo "<th>Sales</th>";
        echo "<th>Cost</th>";
        echo "<th>Profit</th>";
        echo "<th>Margin %</th>";
        echo "</tr>";
        
        // Data rows
        $grand_invoices = 0;
        $grand_sales = 0;
        $grand_cost = 0;
        $grand_profit = 0;
        
        foreach ($data as $row) {
            $profit_class = $row['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
            
            echo "<tr>";
            echo "<td>" . $row['month_name'] . "</td>";
            echo "<td>" . $row['invoice_count'] . "</td>";
            echo "<td>" . number_format($row['total_sales'], 2) . "</td>";
            echo "<td>" . number_format($row['total_cost'], 2) . "</td>";
            echo "<td class='$profit_class'>" . number_format($row['total_profit'], 2) . "</td>";
            echo "<td>" . number_format($row['avg_margin_percent'], 2) . "%</td>";
            echo "</tr>";
            
            $grand_invoices += $row['invoice_count'];
            $grand_sales += $row['total_sales'];
            $grand_cost += $row['total_cost'];
            $grand_profit += $row['total_profit'];
        }
        
        // Totals row
        $grand_margin = $grand_cost > 0 ? ($grand_profit / $grand_cost) * 100 : 0;
        $grand_profit_class = $grand_profit >= 0 ? 'profit-positive' : 'profit-negative';
        
        echo "<tr class='total'>";
        echo "<td><strong>YEARLY TOTALS</strong></td>";
        echo "<td><strong>" . $grand_invoices . "</strong></td>";
        echo "<td><strong>" . number_format($grand_sales, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_cost, 2) . "</strong></td>";
        echo "<td class='$grand_profit_class'><strong>" . number_format($grand_profit, 2) . "</strong></td>";
        echo "<td><strong>" . number_format($grand_margin, 2) . "%</strong></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</body></html>";
    exit();
}

// Export to JSON function
function exportToJSON($report_type, $start_date, $end_date, $shop_id) {
    global $pdo, $current_business_id, $shops;
    
    // Get shop name
    $shop_name = "All Shops";
    if ($shop_id !== 'all') {
        foreach ($shops as $shop) {
            if ($shop['id'] == $shop_id) {
                $shop_name = $shop['shop_name'];
                break;
            }
        }
    }
    
    $report_data = [
        'metadata' => [
            'report_type' => $report_type,
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'display_period' => date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date))
            ],
            'shop' => $shop_name,
            'generated_on' => date('Y-m-d H:i:s'),
            'format' => 'JSON'
        ],
        'data' => []
    ];
    
    if ($report_type === 'summary') {
        $data = getNonGSTSummary($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        $report_data['data'] = $data;
        $total_sales = array_sum(array_column($data, 'invoice_total'));
        $total_cost = array_sum(array_column($data, 'total_cost'));
        $total_profit = array_sum(array_column($data, 'total_profit'));
        
        $report_data['summary'] = [
            'total_invoices' => count($data),
            'total_sales' => $total_sales,
            'total_cost' => $total_cost,
            'total_profit' => $total_profit,
            'average_margin' => $total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0
        ];
        
    } elseif ($report_type === 'products') {
        $data = getProductWiseNonGST($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        $report_data['data'] = $data;
        $total_sales = array_sum(array_column($data, 'total_sales'));
        $total_cost = array_sum(array_column($data, 'total_cost'));
        $total_profit = array_sum(array_column($data, 'total_profit'));
        
        $report_data['summary'] = [
            'total_products' => count($data),
            'total_quantity_sold' => array_sum(array_column($data, 'total_quantity')),
            'total_sales' => $total_sales,
            'total_cost' => $total_cost,
            'total_profit' => $total_profit,
            'average_margin' => $total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0
        ];
        
    } elseif ($report_type === 'customers') {
        $data = getNonGSTCustomerSummary($pdo, $current_business_id, $start_date, $end_date, $shop_id);
        
        $report_data['data'] = $data;
        $total_purchases = array_sum(array_column($data, 'total_purchases'));
        $total_due = array_sum(array_column($data, 'total_due'));
        
        $report_data['summary'] = [
            'total_customers' => count($data),
            'total_invoices' => array_sum(array_column($data, 'invoice_count')),
            'total_purchases' => $total_purchases,
            'total_due' => $total_due,
            'collection_rate' => $total_purchases > 0 ? (($total_purchases - $total_due) / $total_purchases) * 100 : 100
        ];
        
    } elseif ($report_type === 'monthly') {
        $year = $_GET['year'] ?? date('Y');
        $data = getNonGSTMonthlyTrend($pdo, $current_business_id, $year, $shop_id);
        
        $report_data['metadata']['year'] = $year;
        $report_data['data'] = $data;
        $total_sales = array_sum(array_column($data, 'total_sales'));
        $total_cost = array_sum(array_column($data, 'total_cost'));
        $total_profit = array_sum(array_column($data, 'total_profit'));
        
        $report_data['summary'] = [
            'total_months' => count($data),
            'total_invoices' => array_sum(array_column($data, 'invoice_count')),
            'total_sales' => $total_sales,
            'total_cost' => $total_cost,
            'total_profit' => $total_profit,
            'average_margin' => $total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0
        ];
    }
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="non_gst_report_' . date('Y_m_d') . '.json"');
    echo json_encode($report_data, JSON_PRETTY_PRINT);
    exit();
}
?>

<!doctype html>
<html lang="en">
<?php 
$page_title = "Non-GST Reports";
include('includes/head.php') 
?>
<style>
.non-gst-card {
    border-left: 4px solid;
    transition: transform 0.2s;
}
.non-gst-card:hover {
    transform: translateY(-2px);
}
.non-gst-card.sales { border-left-color: #0d6efd; }
.non-gst-card.cost { border-left-color: #dc3545; }
.non-gst-card.profit { border-left-color: #198754; }
.non-gst-card.margin { border-left-color: #6f42c1; }

.summary-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.profit-positive {
    color: #198754 !important;
    font-weight: 600;
}
.profit-negative {
    color: #dc3545 !important;
    font-weight: 600;
}

.product-row {
    transition: background-color 0.2s;
}
.product-row:hover {
    background-color: rgba(13, 110, 253, 0.05) !important;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.export-options {
    position: relative;
}

.export-dropdown {
    position: absolute;
    right: 0;
    left: auto;
    min-width: 180px;
}

.overall-summary {
    background-color: #f8f9fa;
    border-left: 4px solid #6f42c1;
}

.table-total-row {
    background-color: #e8f5e9 !important;
    font-weight: bold;
}

.profit-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.profit-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 15px;
    font-weight: 600;
}

.profit-body {
    padding: 15px;
}

.distribution-item {
    margin-bottom: 15px;
}

.distribution-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.distribution-progress {
    height: 8px;
    border-radius: 4px;
    background-color: #e9ecef;
    overflow: hidden;
}

.progress-bar.profit-high {
    background-color: #198754;
}
.progress-bar.profit-medium {
    background-color: #ffc107;
}
.progress-bar.profit-low {
    background-color: #dc3545;
}

@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    table {
        font-size: 11px !important;
    }
}
</style>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php') ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php') ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">
                                <i class="bx bx-line-chart me-2"></i> Non-GST Reports & Analytics
                                <small class="text-muted ms-2">
                                    <i class="bx bx-store me-1"></i>
                                    <?= htmlspecialchars($_SESSION['current_business_name'] ?? 'Business') ?>
                                </small>
                            </h4>
                            <div class="export-options">
                                <div class="btn-group no-print">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="bx bx-download me-1"></i> Export
                                    </button>
                                    <div class="dropdown-menu export-dropdown">
                                       
                                        <div class="dropdown-divider"></div>
                                        <h6 class="dropdown-header">Download As</h6>
                                        <a class="dropdown-item export-link" href="#" data-type="csv">
                                            <i class="bx bx-file me-2 text-success"></i> CSV (.csv)
                                        </a>
                                        <a class="dropdown-item export-link" href="#" data-type="excel">
                                            <i class="bx bx-file me-2 text-success"></i> Excel (.xls)
                                        </a>
                                        <a class="dropdown-item export-link" href="#" data-type="json">
                                            <i class="bx bx-code me-2 text-info"></i> JSON (.json)
                                        </a>
                                    </div>
                                </div>
                                <a href="gst_report.php" class="btn btn-outline-warning ms-2">
                                    <i class="bx bx-gift me-1"></i> View GST Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-filter-alt me-2"></i> Report Filters
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" id="reportForm" class="row g-3">
                                    <input type="hidden" name="export" id="exportType">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" 
                                               value="<?= htmlspecialchars($filter_start_date) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" name="end_date" class="form-control" 
                                               value="<?= htmlspecialchars($filter_end_date) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Shop/Location</label>
                                        <select name="shop_id" class="form-select">
                                            <option value="all">All Shops</option>
                                            <?php foreach ($shops as $shop): ?>
                                            <option value="<?= $shop['id'] ?>" <?= $shop_id == $shop['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($shop['shop_name']) ?> (<?= $shop['shop_code'] ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Report Type</label>
                                        <select name="report_type" class="form-select" onchange="this.form.submit()">
                                            <option value="summary" <?= $report_type === 'summary' ? 'selected' : '' ?>>Invoice Summary</option>
                                            <option value="products" <?= $report_type === 'products' ? 'selected' : '' ?>>Product Wise</option>
                                            <option value="customers" <?= $report_type === 'customers' ? 'selected' : '' ?>>Customer Wise</option>
                                            <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly Trend</option>
                                        </select>
                                    </div>
                                    <?php if ($report_type === 'monthly'): ?>
                                    <div class="col-md-3">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                                            <option value="<?= $y ?>" <?= ($_GET['year'] ?? date('Y')) == $y ? 'selected' : '' ?>>
                                                <?= $y ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bx bx-refresh me-1"></i> Generate Report
                                            </button>
                                            <a href="non_gst_report.php" class="btn btn-outline-secondary">
                                                <i class="bx bx-reset me-1"></i> Reset Filters
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <?php if (in_array($report_type, ['summary', 'products', 'customers']) && $total_sales > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card non-gst-card sales shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Sales</h6>
                                        <h3 class="mb-0">₹<?= number_format($total_sales, 2) ?></h3>
                                        <small class="text-muted"><?= $total_invoices ?> invoices</small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-primary bg-opacity-10 rounded">
                                            <i class="bx bx-rupee text-primary font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card non-gst-card cost shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Cost</h6>
                                        <h3 class="mb-0">₹<?= number_format($total_cost, 2) ?></h3>
                                        <small class="text-muted">Purchase value</small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-danger bg-opacity-10 rounded">
                                            <i class="bx bx-package text-danger font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card non-gst-card profit shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Profit</h6>
                                        <h3 class="mb-0 <?= $total_profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                            ₹<?= number_format($total_profit, 2) ?>
                                        </h3>
                                        <small class="text-muted">Gross profit</small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-success bg-opacity-10 rounded">
                                            <i class="bx bx-trending-up text-success font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card non-gst-card margin shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Avg Margin</h6>
                                        <h3 class="mb-0 <?= ($total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0) >= 20 ? 'profit-positive' : 'profit-negative' ?>">
                                            <?= number_format($total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0, 2) ?>%
                                        </h3>
                                        <small class="text-muted">Return on cost</small>
                                    </div>
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-purple bg-opacity-10 rounded">
                                            <i class="bx bx-percentage text-purple font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Report Content -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="bx bx-file me-2"></i>
                                        <?php 
                                        $titles = [
                                            'summary' => 'Non-GST Invoice Summary',
                                            'products' => 'Product Wise Sales Report',
                                            'customers' => 'Customer Wise Report',
                                            'monthly' => 'Monthly Sales Trend'
                                        ];
                                        echo $titles[$report_type];
                                        ?>
                                    </h5>
                                    <div class="text-muted">
                                        Period: <?= date('d M Y', strtotime($filter_start_date)) ?> - <?= date('d M Y', strtotime($filter_end_date)) ?>
                                        <?php if ($shop_id !== 'all'): ?>
                                        | Shop: <?= htmlspecialchars($shops[array_search($shop_id, array_column($shops, 'id'))]['shop_name'] ?? 'All') ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                
                                <?php if ($report_type === 'summary'): ?>
                                
                                <!-- Invoice Summary Table -->
                                <div class="table-responsive">
                                    <table class="table table-hover summary-table" id="summaryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Invoice No.</th>
                                                <th>Customer</th>
                                                <th>Shop</th>
                                                <th class="text-end">Subtotal</th>
                                                <th class="text-end">Discount</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Cost</th>
                                                <th class="text-end">Profit</th>
                                                <th class="text-end">Margin %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($non_gst_summary)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <i class="bx bx-line-chart fs-1 text-muted mb-2 d-block"></i>
                                                    <h5>No Non-GST invoices found</h5>
                                                    <p class="text-muted">No non-GST invoices for the selected period.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($non_gst_summary as $invoice): 
                                                $margin = $invoice['total_cost'] > 0 ? 
                                                    ($invoice['total_profit'] / $invoice['total_cost']) * 100 : 0;
                                                $profit_class = $invoice['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
                                            ?>
                                            <tr>
                                                <td><?= date('d M Y', strtotime($invoice['invoice_date'])) ?></td>
                                                <td>
                                                    <a href="invoice_view.php?id=<?= $invoice['invoice_id'] ?>" 
                                                       class="text-decoration-none">
                                                        <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($invoice['customer_name']) ?></div>
                                                    <small class="text-muted"><?= $invoice['customer_type'] ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($invoice['shop_name']) ?></td>
                                                <td class="text-end">₹<?= number_format($invoice['subtotal'], 2) ?></td>
                                                <td class="text-end">₹<?= number_format($invoice['total_discount'], 2) ?></td>
                                                <td class="text-end"><strong>₹<?= number_format($invoice['invoice_total'], 2) ?></strong></td>
                                                <td class="text-end">₹<?= number_format($invoice['total_cost'], 2) ?></td>
                                                <td class="text-end <?= $profit_class ?>">
                                                    <strong>₹<?= number_format($invoice['total_profit'], 2) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $margin >= 20 ? 'success' : ($margin >= 10 ? 'warning' : 'danger') ?> bg-opacity-10 px-3 py-1">
                                                        <?= number_format($margin, 2) ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <!-- Overall Totals Row -->
                                            <?php 
                                            $grand_subtotal = array_sum(array_column($non_gst_summary, 'subtotal'));
                                            $grand_discount = array_sum(array_column($non_gst_summary, 'total_discount'));
                                            $grand_margin = $total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0;
                                            $grand_profit_class = $total_profit >= 0 ? 'profit-positive' : 'profit-negative';
                                            ?>
                                            <tr class="table-total-row">
                                                <td colspan="4" class="text-end"><strong>OVERALL TOTALS:</strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($grand_subtotal, 2) ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($grand_discount, 2) ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($total_sales, 2) ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($total_cost, 2) ?></strong></td>
                                                <td class="text-end <?= $grand_profit_class ?>">
                                                    <strong>₹<?= number_format($total_profit, 2) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $grand_margin >= 20 ? 'success' : ($grand_margin >= 10 ? 'warning' : 'danger') ?> bg-opacity-10 px-3 py-1">
                                                        <strong><?= number_format($grand_margin, 2) ?>%</strong>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Payment Method Summary -->
                                <?php if (!empty($payment_summary)): ?>
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="profit-card">
                                            <div class="profit-header">
                                                <i class="bx bx-credit-card me-2"></i> Payment Method Summary
                                            </div>
                                            <div class="profit-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Payment Method</th>
                                                                <th class="text-end">Transactions</th>
                                                                <th class="text-end">Amount</th>
                                                                <th class="text-end">% of Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($payment_summary as $payment): ?>
                                                            <tr>
                                                                <td>
                                                                    <?php 
                                                                    $method_icons = [
                                                                        'cash' => 'bx bx-money',
                                                                        'upi' => 'bx bxl-whatsapp',
                                                                        'bank' => 'bx bx-building',
                                                                        'cheque' => 'bx bx-receipt',
                                                                        'split' => 'bx bx-transfer'
                                                                    ];
                                                                    $icon = $method_icons[$payment['payment_method']] ?? 'bx bx-credit-card';
                                                                    ?>
                                                                    <i class="<?= $icon ?> me-2"></i>
                                                                    <?= ucfirst($payment['payment_method']) ?>
                                                                </td>
                                                                <td class="text-end"><?= $payment['transaction_count'] ?></td>
                                                                <td class="text-end">₹<?= number_format($payment['total_collected'], 2) ?></td>
                                                                <td class="text-end">
                                                                    <?= $total_sales > 0 ? number_format(($payment['total_collected'] / $total_sales) * 100, 1) : 0 ?>%
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="profit-card">
                                            <div class="profit-header">
                                                <i class="bx bx-pie-chart-alt me-2"></i> Sales Composition
                                            </div>
                                            <div class="profit-body">
                                                <div class="distribution-item">
                                                    <div class="distribution-label">
                                                        <small>Cost of Goods</small>
                                                        <small class="text-danger"><?= $total_sales > 0 ? number_format(($total_cost / $total_sales) * 100, 1) : 0 ?>%</small>
                                                    </div>
                                                    <div class="distribution-progress">
                                                        <div class="progress-bar bg-danger" role="progressbar" 
                                                             style="width: <?= $total_sales > 0 ? ($total_cost / $total_sales) * 100 : 0 ?>%;">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">₹<?= number_format($total_cost, 2) ?></small>
                                                </div>
                                                
                                                <div class="distribution-item">
                                                    <div class="distribution-label">
                                                        <small>Gross Profit</small>
                                                        <small class="<?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                                            <?= $total_sales > 0 ? number_format(($total_profit / $total_sales) * 100, 1) : 0 ?>%
                                                        </small>
                                                    </div>
                                                    <div class="distribution-progress">
                                                        <div class="progress-bar <?= $total_profit >= 0 ? 'bg-success' : 'bg-warning' ?>" 
                                                             role="progressbar" 
                                                             style="width: <?= $total_sales > 0 ? abs($total_profit / $total_sales) * 100 : 0 ?>%;">
                                                        </div>
                                                    </div>
                                                    <small class="<?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        ₹<?= number_format($total_profit, 2) ?>
                                                    </small>
                                                </div>
                                                
                                                <div class="row mt-4">
                                                    <div class="col-6">
                                                        <div class="p-3 border rounded text-center">
                                                            <small class="text-muted d-block">Avg. Invoice Value</small>
                                                            <h6 class="mb-0">
                                                                ₹<?= $total_invoices > 0 ? number_format($total_sales / $total_invoices, 2) : 0 ?>
                                                            </h6>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="p-3 border rounded text-center">
                                                            <small class="text-muted d-block">Profit per Invoice</small>
                                                            <h6 class="mb-0 <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                                                ₹<?= $total_invoices > 0 ? number_format($total_profit / $total_invoices, 2) : 0 ?>
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php elseif ($report_type === 'products'): ?>
                                
                                <!-- Product Wise Report -->
                                <div class="table-responsive">
                                    <table class="table table-hover" id="productTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Category</th>
                                                <th>Unit</th>
                                                <th class="text-center">Invoices</th>
                                                <th class="text-center">Qty Sold</th>
                                                <th class="text-end">Sales</th>
                                                <th class="text-end">Cost</th>
                                                <th class="text-end">Profit</th>
                                                <th class="text-end">Margin %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($product_summary)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="bx bx-package fs-1 text-muted mb-2 d-block"></i>
                                                    <h5>No product sales data found</h5>
                                                    <p class="text-muted">No products sold in non-GST invoices for the selected period.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($product_summary as $product): 
                                                $profit_class = $product['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
                                                $margin_badge = $product['avg_margin_percent'] >= 30 ? 'success' : 
                                                              ($product['avg_margin_percent'] >= 15 ? 'warning' : 'danger');
                                            ?>
                                            <tr class="product-row">
                                                <td>
                                                    <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($product['product_code'] ?: 'No Code') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?></td>
                                                <td><?= $product['unit_of_measure'] ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-info rounded-pill px-3">
                                                        <?= $product['invoice_count'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-3">
                                                        <?= number_format($product['total_quantity']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">₹<?= number_format($product['total_sales'], 2) ?></td>
                                                <td class="text-end">₹<?= number_format($product['total_cost'], 2) ?></td>
                                                <td class="text-end <?= $profit_class ?>">
                                                    <strong>₹<?= number_format($product['total_profit'], 2) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $margin_badge ?> bg-opacity-10 text-<?= $margin_badge ?> px-3 py-1">
                                                        <?= number_format($product['avg_margin_percent'], 2) ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <!-- Overall Totals Row -->
                                            <?php 
                                            $grand_margin = $total_cost > 0 ? ($total_profit / $total_cost) * 100 : 0;
                                            $grand_profit_class = $total_profit >= 0 ? 'profit-positive' : 'profit-negative';
                                            ?>
                                            <tr class="table-total-row">
                                                <td colspan="4" class="text-end"><strong>OVERALL TOTALS:</strong></td>
                                                <td class="text-center"><strong><?= number_format($total_quantity) ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($total_sales, 2) ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($total_cost, 2) ?></strong></td>
                                                <td class="text-end <?= $grand_profit_class ?>">
                                                    <strong>₹<?= number_format($total_profit, 2) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $grand_margin >= 30 ? 'success' : ($grand_margin >= 15 ? 'warning' : 'danger') ?> bg-opacity-10 px-3 py-1">
                                                        <strong><?= number_format($grand_margin, 2) ?>%</strong>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Top Products by Profit -->
                                <?php if (!empty($product_summary)): 
                                    $top_products = array_slice($product_summary, 0, 5);
                                ?>
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="profit-card">
                                            <div class="profit-header">
                                                <i class="bx bx-trophy me-2"></i> Top 5 Products by Profit
                                            </div>
                                            <div class="profit-body">
                                                <?php foreach ($top_products as $i => $product): 
                                                    $profit_percent = $product['total_sales'] > 0 ? 
                                                        ($product['total_profit'] / $product['total_sales']) * 100 : 0;
                                                ?>
                                                <div class="distribution-item">
                                                    <div class="distribution-label">
                                                        <small><?= ($i+1) ?>. <?= htmlspecialchars($product['product_name']) ?></small>
                                                        <small class="text-success">₹<?= number_format($product['total_profit'], 0) ?></small>
                                                    </div>
                                                    <div class="distribution-progress">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?= $total_profit > 0 ? ($product['total_profit'] / $total_profit) * 100 : 0 ?>%;">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        Sold: <?= $product['total_quantity'] ?> units | Margin: <?= number_format($product['avg_margin_percent'], 1) ?>%
                                                    </small>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="profit-card">
                                            <div class="profit-header">
                                                <i class="bx bx-category me-2"></i> Category Performance
                                            </div>
                                            <div class="profit-body">
                                                <?php 
                                                $category_totals = [];
                                                foreach ($product_summary as $product) {
                                                    $cat = $product['category_name'] ?: 'Uncategorized';
                                                    if (!isset($category_totals[$cat])) {
                                                        $category_totals[$cat] = [
                                                            'sales' => 0,
                                                            'profit' => 0,
                                                            'count' => 0
                                                        ];
                                                    }
                                                    $category_totals[$cat]['sales'] += $product['total_sales'];
                                                    $category_totals[$cat]['profit'] += $product['total_profit'];
                                                    $category_totals[$cat]['count']++;
                                                }
                                                arsort($category_totals);
                                                $top_categories = array_slice($category_totals, 0, 5, true);
                                                ?>
                                                <?php foreach ($top_categories as $category => $data): ?>
                                                <div class="distribution-item">
                                                    <div class="distribution-label">
                                                        <small><?= htmlspecialchars($category) ?> (<?= $data['count'] ?> products)</small>
                                                        <small class="<?= $data['profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                            ₹<?= number_format($data['profit'], 0) ?>
                                                        </small>
                                                    </div>
                                                    <div class="distribution-progress">
                                                        <div class="progress-bar <?= $data['profit'] >= 0 ? 'bg-success' : 'bg-warning' ?>" 
                                                             role="progressbar" 
                                                             style="width: <?= $total_profit > 0 ? abs($data['profit'] / $total_profit) * 100 : 0 ?>%;">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        Sales: ₹<?= number_format($data['sales'], 0) ?>
                                                    </small>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php elseif ($report_type === 'customers'): ?>
                                
                                <!-- Customer Wise Report -->
                                <div class="table-responsive">
                                    <table class="table table-hover" id="customerTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Customer</th>
                                                <th>Phone</th>
                                                <th>Type</th>
                                                <th class="text-center">Invoices</th>
                                                <th class="text-center">Paid</th>
                                                <th class="text-center">Pending</th>
                                                <th class="text-end">Total Purchases</th>
                                                <th class="text-end">Due %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($customer_summary)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="bx bx-user fs-1 text-muted mb-2 d-block"></i>
                                                    <h5>No customer data found</h5>
                                                    <p class="text-muted">No non-GST invoices for the selected period.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($customer_summary as $customer): 
                                                $due_percent = $customer['total_purchases'] > 0 ? 
                                                    ($customer['total_due'] / $customer['total_purchases']) * 100 : 0;
                                                $due_class = $due_percent > 50 ? 'danger' : ($due_percent > 20 ? 'warning' : 'success');
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($customer['customer_name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($customer['customer_phone'] ?: '-') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $customer['customer_type'] === 'wholesale' ? 'primary' : 'info' ?> bg-opacity-10 text-<?= $customer['customer_type'] === 'wholesale' ? 'primary' : 'info' ?>">
                                                        <?= ucfirst($customer['customer_type']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center"><?= $customer['invoice_count'] ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-success rounded-pill px-3">
                                                        <?= $customer['paid_invoices'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($customer['pending_invoices'] > 0): ?>
                                                    <span class="badge bg-warning text-dark rounded-pill px-3">
                                                        <?= $customer['pending_invoices'] ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <strong>₹<?= number_format($customer['total_purchases'], 2) ?></strong>
                                                    <?php if ($customer['total_due'] > 0): ?>
                                                    <br><small class="text-danger">Due: ₹<?= number_format($customer['total_due'], 2) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $due_class ?> bg-opacity-10 text-<?= $due_class ?> px-3 py-1">
                                                        <?= number_format($due_percent, 1) ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php elseif ($report_type === 'monthly'): ?>
                                
                                <!-- Monthly Trend -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card shadow-sm">
                                            <div class="card-body">
                                                <h6 class="mb-3">Monthly Sales & Profit Trend</h6>
                                                <div class="chart-container">
                                                    <canvas id="monthlyTrendChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover" id="monthlyTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Month</th>
                                                <th class="text-center">Invoices</th>
                                                <th class="text-end">Sales</th>
                                                <th class="text-end">Cost</th>
                                                <th class="text-end">Profit</th>
                                                <th class="text-end">Margin %</th>
                                                <th class="text-end">Avg. Invoice</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($monthly_trend)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="bx bx-calendar fs-1 text-muted mb-2 d-block"></i>
                                                    <h5>No monthly data found</h5>
                                                    <p class="text-muted">No non-GST data for the selected year.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php 
                                            $yearly_total_sales = 0;
                                            $yearly_total_cost = 0;
                                            $yearly_total_profit = 0;
                                            ?>
                                            <?php foreach ($monthly_trend as $month): 
                                            $yearly_total_sales += $month['total_sales'];
                                            $yearly_total_cost += $month['total_cost'];
                                            $yearly_total_profit += $month['total_profit'];
                                            $profit_class = $month['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($month['month_name']) ?></strong>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info rounded-pill px-3">
                                                        <?= $month['invoice_count'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">₹<?= number_format($month['total_sales'], 2) ?></td>
                                                <td class="text-end">₹<?= number_format($month['total_cost'], 2) ?></td>
                                                <td class="text-end <?= $profit_class ?>">
                                                    <strong>₹<?= number_format($month['total_profit'], 2) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $month['avg_margin_percent'] >= 20 ? 'success' : ($month['avg_margin_percent'] >= 10 ? 'warning' : 'danger') ?> bg-opacity-10 px-3 py-1">
                                                        <?= number_format($month['avg_margin_percent'], 2) ?>%
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    ₹<?= $month['invoice_count'] > 0 ? number_format($month['total_sales'] / $month['invoice_count'], 2) : 0 ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <!-- Overall Totals Row -->
                                            <?php 
                                            $yearly_margin = $yearly_total_cost > 0 ? 
                                                ($yearly_total_profit / $yearly_total_cost) * 100 : 0;
                                            $yearly_profit_class = $yearly_total_profit >= 0 ? 'profit-positive' : 'profit-negative';
                                            $total_invoices = array_sum(array_column($monthly_trend, 'invoice_count'));
                                            ?>
                                            <tr class="table-total-row">
                                                <td><strong>YEARLY TOTALS</strong></td>
                                                <td class="text-center"><strong><?= $total_invoices ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($yearly_total_sales, 2) ?></strong></td>
                                                <td class="text-end"><strong>₹<?= number_format($yearly_total_cost, 2) ?></strong></td>
                                                <td class="text-end <?= $yearly_profit_class ?>">
                                                    <strong>₹<?= number_format($yearly_total_profit, 2) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-<?= $yearly_margin >= 20 ? 'success' : ($yearly_margin >= 10 ? 'warning' : 'danger') ?> bg-opacity-10 px-3 py-1">
                                                        <strong><?= number_format($yearly_margin, 2) ?>%</strong>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <strong>₹<?= $total_invoices > 0 ? number_format($yearly_total_sales / $total_invoices, 2) : 0 ?></strong>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php') ?>
    </div>
</div>

<?php include('includes/rightbar.php') ?>
<?php include('includes/scripts.php') ?>
<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    <?php if ($report_type === 'monthly' && !empty($monthly_trend)): ?>
    // Monthly Trend Chart
    const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    const monthlyData = {
        labels: <?= json_encode(array_column($monthly_trend, 'month_name')) ?>,
        datasets: [
            {
                label: 'Sales',
                data: <?= json_encode(array_column($monthly_trend, 'total_sales')) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.5)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1,
                yAxisID: 'y'
            },
            {
                label: 'Cost',
                data: <?= json_encode(array_column($monthly_trend, 'total_cost')) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1,
                yAxisID: 'y'
            },
            {
                label: 'Profit',
                data: <?= json_encode(array_column($monthly_trend, 'total_profit')) ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                borderColor: 'rgba(25, 135, 84, 1)',
                borderWidth: 1,
                yAxisID: 'y'
            },
            {
                label: 'Margin %',
                data: <?= json_encode(array_column($monthly_trend, 'avg_margin_percent')) ?>,
                borderColor: 'rgba(111, 66, 193, 1)',
                backgroundColor: 'rgba(111, 66, 193, 0.1)',
                borderWidth: 2,
                type: 'line',
                fill: false,
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    };
    
    new Chart(monthlyCtx, {
        type: 'bar',
        data: monthlyData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Amount (₹)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Margin %'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.dataset.yAxisID === 'y') {
                                label += '₹' + context.parsed.y.toLocaleString();
                            } else {
                                label += context.parsed.y.toFixed(2) + '%';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Export functionality - SIMPLIFIED VERSION
    $('.export-link').click(function(e) {
        e.preventDefault();
        const exportType = $(this).data('type');
        
        // Direct download without notification
        let url = 'non_gst_report.php?';
        const params = new URLSearchParams();
        
        params.append('start_date', $('[name="start_date"]').val());
        params.append('end_date', $('[name="end_date"]').val());
        params.append('shop_id', $('[name="shop_id"]').val());
        params.append('report_type', $('[name="report_type"]').val());
        
        <?php if ($report_type === 'monthly'): ?>
        params.append('year', $('[name="year"]').val());
        <?php endif; ?>
        
        params.append('export', exportType);
        
        url += params.toString();
        
        // Direct download
        window.location.href = url;
    });

    // Print optimization
    $('[onclick="window.print()"]').click(function() {
        $('body').addClass('print-mode');
        setTimeout(function() {
            $('body').removeClass('print-mode');
        }, 1000);
    });

    // Initialize DataTables for better sorting and searching
    <?php if (!empty($product_summary)): ?>
    $('#productTable').DataTable({
        pageLength: 25,
        order: [[5, 'desc']], // Sort by sales descending
        language: {
            search: "Search products:",
            lengthMenu: "Show _MENU_ products",
            info: "Showing _START_ to _END_ of _TOTAL_ products"
        }
    });
    <?php endif; ?>

    <?php if (!empty($customer_summary)): ?>
    $('#customerTable').DataTable({
        pageLength: 25,
        order: [[6, 'desc']], // Sort by total purchases descending
        language: {
            search: "Search customers:",
            lengthMenu: "Show _MENU_ customers",
            info: "Showing _START_ to _END_ of _TOTAL_ customers"
        }
    });
    <?php endif; ?>
});
</script>
</body>
</html>