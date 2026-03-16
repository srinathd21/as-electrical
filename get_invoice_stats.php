<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$business_id = $_SESSION['current_business_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'seller';
$current_shop_id = $_SESSION['current_shop_id'] ?? null;

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$customer_id_filter = (int)($_GET['customer_id'] ?? 0);

// Build WHERE clause
$where = "WHERE i.business_id = ? AND DATE(i.created_at) BETWEEN ? AND ?";
$params = [$business_id, $start_date, $end_date];

if ($user_role !== 'admin') {
    $where .= " AND i.shop_id = ?";
    $params[] = $current_shop_id;
}

if ($customer_id_filter > 0) {
    $where .= " AND i.customer_id = ?";
    $params[] = $customer_id_filter;
}

// Get stats
$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*) as count,
        COALESCE(SUM(i.total), 0) as total,
        COALESCE(SUM(i.total - i.pending_amount), 0) as collected,
        COALESCE(SUM(i.pending_amount), 0) as pending
    FROM invoices i
    $where
");
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

header('Content-Type: application/json');
echo json_encode($stats);
?>