<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

// ==================== AUTHORIZATION ====================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$business_id = $_SESSION['business_id'] ?? 1;

// Check if user has permission
if (!in_array($user_role, ['admin', 'warehouse_manager','shop_manager','stock_manager'])) {
    $_SESSION['error'] = "Access denied. You don't have permission to export purchase requests.";
    header('Location: purchase_requests.php');
    exit();
}

// ==================== GET FILTERS FROM URL ====================
$where = ["pr.business_id = ?"];
$params = [$business_id];

$status = $_GET['status'] ?? '';
if ($status !== '' && in_array($status, ['draft','sent','quotation_received','approved','rejected'])) {
    $where[] = "pr.status = ?";
    $params[] = $status;
}

$manufacturer_id = (int)($_GET['manufacturer_id'] ?? 0);
if ($manufacturer_id > 0) {
    $where[] = "pr.manufacturer_id = ?";
    $params[] = $manufacturer_id;
}

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
if ($from_date) { $where[] = "DATE(pr.created_at) >= ?"; $params[] = $from_date; }
if ($to_date)   { $where[] = "DATE(pr.created_at) <= ?"; $params[] = $to_date; }

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $where[] = "(pr.request_number LIKE ? OR m.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm; $params[] = $searchTerm;
}

// ==================== FETCH ALL REQUESTS FOR EXPORT ====================
$sql = "
    SELECT 
        pr.id,
        pr.request_number,
        pr.status,
        pr.total_estimated_amount,
        pr.created_at,
        pr.updated_at,
        m.name AS manufacturer_name,
        m.email AS manufacturer_email,
        m.phone AS manufacturer_phone,
        u.full_name AS requested_by_name,
        u.email AS requested_by_email,
        (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS item_count,
        (SELECT SUM(quantity) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS total_quantity
    FROM purchase_requests pr
    LEFT JOIN manufacturers m ON pr.manufacturer_id = m.id AND m.business_id = ?
    LEFT JOIN users u ON pr.requested_by = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY pr.created_at DESC
";

// Add business_id for manufacturers join
array_unshift($params, $business_id);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== FETCH ITEMS FOR EACH REQUEST ====================
foreach ($requests as &$request) {
    $items_sql = "
        SELECT 
            pri.id,
            pri.product_id,
            p.name AS product_name,
            p.sku AS product_sku,
            p.barcode AS product_barcode,
            pri.quantity,
            pri.estimated_price,
            (pri.quantity * pri.estimated_price) AS total_price,
            pri.notes
        FROM purchase_request_items pri
        LEFT JOIN products p ON pri.product_id = p.id
        WHERE pri.purchase_request_id = ?
        ORDER BY pri.id ASC
    ";
    
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->execute([$request['id']]);
    $request['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== GENERATE FILENAME ====================
$filename = 'purchase_requests_export_' . date('Y-m-d_His') . '.csv';

// ==================== SET HEADERS FOR DOWNLOAD ====================
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ==================== CREATE OUTPUT STREAM ====================
$output = fopen('php://output', 'w');

// ==================== ADD UTF-8 BOM FOR EXCEL ====================
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ==================== WRITE HEADER ROWS ====================
// Main headers
$headers = [
    'Request ID',
    'Request Number',
    'Status',
    'Request Date',
    'Request Time',
    'Last Updated',
    'Supplier Name',
    'Supplier Email',
    'Supplier Phone',
    'Requested By',
    'Requestor Email',
    'Total Items',
    'Total Quantity',
    'Total Estimated Amount (₹)'
];
fputcsv($output, $headers);

// ==================== WRITE DATA ROWS ====================
$total_estimated_all = 0;
$total_items_all = 0;
$total_quantity_all = 0;

foreach ($requests as $request) {
    $status_text = ucfirst(str_replace('_', ' ', $request['status']));
    $created_date = date('Y-m-d', strtotime($request['created_at']));
    $created_time = date('H:i:s', strtotime($request['created_at']));
    $updated_date = $request['updated_at'] ? date('Y-m-d H:i:s', strtotime($request['updated_at'])) : 'Not updated';
    
    $row = [
        $request['id'],
        $request['request_number'],
        $status_text,
        $created_date,
        $created_time,
        $updated_date,
        $request['manufacturer_name'] ?? 'N/A',
        $request['manufacturer_email'] ?? 'N/A',
        $request['manufacturer_phone'] ?? 'N/A',
        $request['requested_by_name'] ?? 'N/A',
        $request['requested_by_email'] ?? 'N/A',
        $request['item_count'],
        $request['total_quantity'] ?? 0,
        number_format($request['total_estimated_amount'], 2)
    ];
    
    fputcsv($output, $row);
    
    $total_estimated_all += $request['total_estimated_amount'];
    $total_items_all += $request['item_count'];
    $total_quantity_all += ($request['total_quantity'] ?? 0);
}

// ==================== WRITE SUMMARY ROW ====================
fputcsv($output, []); // Empty row for spacing
$summary_headers = [
    'SUMMARY',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    'Total Requests: ' . count($requests),
    'Total Items: ' . $total_items_all,
    'Total Quantity: ' . $total_quantity_all,
    'Total Amount: ₹' . number_format($total_estimated_all, 2)
];
fputcsv($output, $summary_headers);

// ==================== STATUS BREAKDOWN ====================
$status_counts = [
    'draft' => 0,
    'sent' => 0,
    'quotation_received' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($requests as $request) {
    if (isset($status_counts[$request['status']])) {
        $status_counts[$request['status']]++;
    }
}

fputcsv($output, []); // Empty row
fputcsv($output, ['STATUS BREAKDOWN']);
foreach ($status_counts as $status => $count) {
    if ($count > 0) {
        $status_text = ucfirst(str_replace('_', ' ', $status));
        fputcsv($output, [$status_text . ':', $count]);
    }
}

// ==================== ITEMS DETAILS SECTION ====================
if (!empty($requests)) {
    fputcsv($output, []); // Empty row
    fputcsv($output, []); // Empty row
    fputcsv($output, ['DETAILED ITEMS LIST']);
    fputcsv($output, ['=' * 50]);
    
    foreach ($requests as $request) {
        if (!empty($request['items'])) {
            fputcsv($output, []); // Empty row
            fputcsv($output, [
                'Request: ' . $request['request_number'],
                'Supplier: ' . ($request['manufacturer_name'] ?? 'N/A'),
                'Date: ' . date('Y-m-d', strtotime($request['created_at'])),
                'Status: ' . ucfirst(str_replace('_', ' ', $request['status']))
            ]);
            
            // Items headers
            $item_headers = [
                'Product ID',
                'Product Name',
                'SKU',
                'Barcode',
                'Quantity',
                'Unit Price (₹)',
                'Total Price (₹)',
                'Notes'
            ];
            fputcsv($output, $item_headers);
            
            // Items data
            $request_total = 0;
            foreach ($request['items'] as $item) {
                $item_row = [
                    $item['product_id'],
                    $item['product_name'],
                    $item['product_sku'] ?? 'N/A',
                    $item['product_barcode'] ?? 'N/A',
                    $item['quantity'],
                    number_format($item['estimated_price'], 2),
                    number_format($item['total_price'], 2),
                    $item['notes'] ?? ''
                ];
                fputcsv($output, $item_row);
                $request_total += $item['total_price'];
            }
            
            // Request total
            fputcsv($output, [
                '', '', '', '',
                'Request Total:',
                '',
                '₹' . number_format($request_total, 2),
                ''
            ]);
            
            fputcsv($output, ['---']); // Separator
        }
    }
}

// ==================== EXPORT PARAMETERS ====================
fputcsv($output, []); // Empty row
fputcsv($output, []); // Empty row
fputcsv($output, ['EXPORT INFORMATION']);
fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Generated By:', $_SESSION['full_name'] ?? 'User ID: ' . $user_id]);
fputcsv($output, ['Business:', $_SESSION['current_business_name'] ?? 'Business ID: ' . $business_id]);

if ($status || $manufacturer_id || $from_date || $to_date || $search) {
    fputcsv($output, ['Applied Filters:']);
    if ($status) fputcsv($output, ['- Status:', ucfirst(str_replace('_', ' ', $status))]);
    if ($manufacturer_id) {
        $manu_name = $pdo->prepare("SELECT name FROM manufacturers WHERE id = ?");
        $manu_name->execute([$manufacturer_id]);
        $manu = $manu_name->fetchColumn();
        fputcsv($output, ['- Supplier:', $manu ?: 'ID: ' . $manufacturer_id]);
    }
    if ($from_date) fputcsv($output, ['- From Date:', $from_date]);
    if ($to_date) fputcsv($output, ['- To Date:', $to_date]);
    if ($search) fputcsv($output, ['- Search:', $search]);
}

// ==================== CLOSE OUTPUT STREAM ====================
fclose($output);

// ==================== LOG EXPORT ACTIVITY (Optional) ====================
try {
    $log_sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
                VALUES (?, 'export_purchase_requests', ?, ?, NOW())";
    $log_stmt = $pdo->prepare($log_sql);
    
    $filter_details = "Status: " . ($status ?: 'All') . 
                      ", Supplier: " . ($manufacturer_id ?: 'All') . 
                      ", Date Range: " . ($from_date ?: 'Any') . " to " . ($to_date ?: 'Any') .
                      ", Search: " . ($search ?: 'None');
    
    $log_stmt->execute([
        $user_id,
        "Exported " . count($requests) . " purchase requests. Filters: " . $filter_details,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
} catch (PDOException $e) {
    // Silently fail - logging shouldn't break the export
}

exit();