<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'field_executive', 'seller', 'shop_manager'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'] ?? 1;

// === FILTERS (same as stores.php) ===
$city_filter = $_GET['city'] ?? '';
$executive_filter = $_GET['executive'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build WHERE conditions for filters
$where_conditions = ["s.business_id = ?"];
$params = [$business_id];

// City filter
if (!empty($city_filter) && $city_filter !== 'all') {
    $where_conditions[] = "s.city = ?";
    $params[] = $city_filter;
}

// Executive filter
if (!empty($executive_filter) && $executive_filter !== 'all') {
    $where_conditions[] = "s.field_executive_id = ?";
    $params[] = $executive_filter;
}

// Status filter
if ($status_filter === 'active') {
    $where_conditions[] = "s.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "s.is_active = 0";
}

// Search filter
if (!empty($search_term)) {
    $where_conditions[] = "(s.store_code LIKE ? OR s.store_name LIKE ? OR s.phone LIKE ? OR s.city LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// Fetch stores with stats for export
$stmt = $pdo->prepare("
    SELECT 
        s.store_code,
        s.store_name,
        s.owner_name,
        s.phone,
        s.whatsapp_number,
        s.email,
        s.city,
        s.address,
        s.gstin,
        u.full_name AS executive_name,
        CASE WHEN s.is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
        s.created_at,
        (SELECT COUNT(*) FROM store_visits WHERE store_id = s.id) as visit_count,
        (SELECT COUNT(*) FROM store_requirements sr
         JOIN store_visits sv ON sv.id = sr.store_visit_id
         WHERE sv.store_id = s.id) as requirement_count,
        (SELECT MAX(visit_date) FROM store_visits WHERE store_id = s.id) as last_visit_date
    FROM stores s
    LEFT JOIN users u ON s.field_executive_id = u.id
    WHERE $where_clause
    ORDER BY s.store_name
");
$stmt->execute($params);
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
$filename = 'stores_export_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel to handle special characters
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Store Code',
    'Store Name',
    'Owner Name',
    'Phone',
    'WhatsApp',
    'Email',
    'City',
    'Address',
    'GSTIN',
    'Field Executive',
    'Status',
    'Created Date',
    'Total Visits',
    'Total Requirements',
    'Last Visit Date'
]);

// Add data rows
foreach ($stores as $store) {
    // Format dates
    $created_date = !empty($store['created_at']) ? date('d-m-Y', strtotime($store['created_at'])) : '';
    $last_visit = !empty($store['last_visit_date']) ? date('d-m-Y', strtotime($store['last_visit_date'])) : 'No visits yet';
    
    fputcsv($output, [
        $store['store_code'],
        $store['store_name'],
        $store['owner_name'] ?? '',
        $store['phone'],
        $store['whatsapp_number'] ?? '',
        $store['email'] ?? '',
        $store['city'] ?? '',
        $store['address'],
        $store['gstin'] ?? '',
        $store['executive_name'] ?? 'Unassigned',
        $store['status'],
        $created_date,
        $store['visit_count'],
        $store['requirement_count'],
        $last_visit
    ]);
}

fclose($output);
exit();