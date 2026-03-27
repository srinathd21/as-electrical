<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$business_id = $_SESSION['business_id'] ?? 1;
$allowed_roles = ['admin', 'seller', 'staff', 'warehouse_manager', 'field_executive', 'shop_manager'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit();
}

// === FILTERS ===
$store_filter = (int)($_GET['store'] ?? 0);
$executive_filter = (int)($_GET['executive'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$where = "WHERE 1=1";
$params = [];

if ($user_role === 'field_executive') {
    $where .= " AND sv.field_executive_id = ?";
    $params[] = $user_id;
}

if ($store_filter > 0) { 
    $where .= " AND sv.store_id = ?"; 
    $params[] = $store_filter; 
}

if ($user_role !== 'field_executive' && $executive_filter > 0) { 
    $where .= " AND sv.field_executive_id = ?"; 
    $params[] = $executive_filter; 
}

if ($date_from) { 
    $where .= " AND DATE(sv.visit_date) >= ?"; 
    $params[] = $date_from; 
}

if ($date_to) { 
    $where .= " AND DATE(sv.visit_date) <= ?"; 
    $params[] = $date_to; 
}

if ($status_filter) {
    $status_conditions = [
        'pending' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'pending')",
        'approved' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'approved')",
        'packed' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'packed')",
        'shipped' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'shipped')",
        'delivered' => "AND EXISTS (SELECT 1 FROM store_requirements sr2 WHERE sr2.store_visit_id = sv.id AND sr2.requirement_status = 'delivered')"
    ];
    $where .= $status_conditions[$status_filter] ?? '';
}

// Fetch detailed visits data for export - REMOVED invoice_date
$stmt = $pdo->prepare("
    SELECT
        sv.id as visit_id,
        sv.visit_date,
        sv.visit_type,
        sv.next_followup_date,
        sv.created_at as visit_created_at,
        s.store_code,
        s.store_name,
        s.city as store_city,
        s.phone as store_phone,
        s.owner_name as store_owner,
        u.full_name AS executive_name,
        u.phone AS executive_phone,
        COUNT(DISTINCT sr.id) AS total_items,
        SUM(CASE WHEN sr.requirement_status = 'pending' THEN 1 ELSE 0 END) AS pending_items,
        SUM(CASE WHEN sr.requirement_status = 'approved' THEN 1 ELSE 0 END) AS approved_items,
        SUM(CASE WHEN sr.requirement_status = 'packed' THEN 1 ELSE 0 END) AS packed_items,
        SUM(CASE WHEN sr.requirement_status = 'shipped' THEN 1 ELSE 0 END) AS shipped_items,
        SUM(CASE WHEN sr.requirement_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_items,
        MAX(CASE WHEN sr.invoice_id IS NOT NULL THEN 1 ELSE 0 END) AS has_invoice,
        MAX(packer.full_name) AS packed_by_name,
        MAX(sr.packed_at) AS packed_date,
        MAX(shipper.full_name) AS shipped_by_name,
        MAX(sr.shipped_at) AS shipped_date,
        MAX(sr.tracking_number) AS tracking_number,
        MAX(approver.full_name) AS approved_by_name,
        MAX(sr.approved_at) AS approved_date,
        MAX(deliverer.full_name) AS delivered_by_name,
        MAX(sr.delivered_at) AS delivered_date,
        inv.invoice_number,
        inv.created_at as invoice_created_at,
        inv.total_amount as invoice_amount
    FROM store_visits sv
    JOIN stores s ON sv.store_id = s.id
    JOIN users u ON sv.field_executive_id = u.id
    LEFT JOIN store_requirements sr ON sr.store_visit_id = sv.id
    LEFT JOIN users packer ON sr.packed_by = packer.id
    LEFT JOIN users shipper ON sr.shipped_by = shipper.id
    LEFT JOIN users approver ON sr.approved_by = approver.id
    LEFT JOIN users deliverer ON sr.delivered_by = deliverer.id
    LEFT JOIN invoices inv ON sr.invoice_id = inv.id
    $where
    GROUP BY sv.id
    ORDER BY sv.visit_date DESC, sv.created_at DESC
");
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
$filename = 'visits_export_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Add headers - UPDATED header name
fputcsv($output, [
    'Visit ID',
    'Visit Date',
    'Visit Type',
    'Next Follow-up Date',
    'Visit Created At',
    'Store Code',
    'Store Name',
    'Store City',
    'Store Phone',
    'Store Owner',
    'Field Executive',
    'Executive Phone',
    'Total Items',
    'Pending Items',
    'Approved Items',
    'Packed Items',
    'Shipped Items',
    'Delivered Items',
    'Current Status',
    'Approved By',
    'Approved Date',
    'Packed By',
    'Packed Date',
    'Shipped By',
    'Shipped Date',
    'Tracking Number',
    'Delivered By',
    'Delivered Date',
    'Has Invoice',
    'Invoice Number',
    'Invoice Created Date',  // Changed from Invoice Date
    'Invoice Amount'
]);

// Add data rows
foreach ($visits as $visit) {
    // Determine current status
    $current_status = 'pending';
    if ($visit['total_items'] > 0) {
        if ($visit['delivered_items'] == $visit['total_items']) $current_status = 'delivered';
        elseif ($visit['shipped_items'] > 0) $current_status = 'shipped';
        elseif ($visit['packed_items'] > 0) $current_status = 'packed';
        elseif ($visit['approved_items'] == $visit['total_items']) $current_status = 'approved';
    }
    
    // Format dates
    $visit_date = !empty($visit['visit_date']) ? date('d-m-Y', strtotime($visit['visit_date'])) : '';
    $next_followup = !empty($visit['next_followup_date']) ? date('d-m-Y', strtotime($visit['next_followup_date'])) : '';
    $created_at = !empty($visit['visit_created_at']) ? date('d-m-Y H:i', strtotime($visit['visit_created_at'])) : '';
    $approved_date = !empty($visit['approved_date']) ? date('d-m-Y H:i', strtotime($visit['approved_date'])) : '';
    $packed_date = !empty($visit['packed_date']) ? date('d-m-Y H:i', strtotime($visit['packed_date'])) : '';
    $shipped_date = !empty($visit['shipped_date']) ? date('d-m-Y H:i', strtotime($visit['shipped_date'])) : '';
    $delivered_date = !empty($visit['delivered_date']) ? date('d-m-Y H:i', strtotime($visit['delivered_date'])) : '';
    $invoice_created = !empty($visit['invoice_created_at']) ? date('d-m-Y H:i', strtotime($visit['invoice_created_at'])) : '';
    
    fputcsv($output, [
        $visit['visit_id'],
        $visit_date,
        ucfirst($visit['visit_type'] ?? 'Regular'),
        $next_followup,
        $created_at,
        $visit['store_code'],
        $visit['store_name'],
        $visit['store_city'] ?? '',
        $visit['store_phone'],
        $visit['store_owner'] ?? '',
        $visit['executive_name'],
        $visit['executive_phone'] ?? '',
        $visit['total_items'] ?? 0,
        $visit['pending_items'] ?? 0,
        $visit['approved_items'] ?? 0,
        $visit['packed_items'] ?? 0,
        $visit['shipped_items'] ?? 0,
        $visit['delivered_items'] ?? 0,
        ucfirst($current_status),
        $visit['approved_by_name'] ?? '',
        $approved_date,
        $visit['packed_by_name'] ?? '',
        $packed_date,
        $visit['shipped_by_name'] ?? '',
        $shipped_date,
        $visit['tracking_number'] ?? '',
        $visit['delivered_by_name'] ?? '',
        $delivered_date,
        $visit['has_invoice'] ? 'Yes' : 'No',
        $visit['invoice_number'] ?? '',
        $invoice_created,  // Using created_at instead of invoice_date
        $visit['invoice_amount'] ? number_format($visit['invoice_amount'], 2) : ''
    ]);
}

fclose($output);
exit();