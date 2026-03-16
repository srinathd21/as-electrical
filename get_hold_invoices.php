<?php
session_start();
require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$shop_id = $_GET['shop_id'] ?? $_SESSION['shop_id'] ?? 1;
$user_id = $_SESSION['user_id'];

try {
    // Clean up expired invoices first
    $pdo->exec("DELETE FROM held_invoices WHERE expiry_at < NOW()");
    
    $stmt = $pdo->prepare("
        SELECT hi.*, COUNT(hii.product_id) as item_count,
               GROUP_CONCAT(p.product_name SEPARATOR ', ') as product_names
        FROM held_invoices hi
        LEFT JOIN (
            SELECT id, JSON_UNQUOTE(JSON_EXTRACT(cart_items, '$[*].product_id')) as product_id
            FROM held_invoices
        ) hii ON hi.id = hii.id
        LEFT JOIN products p ON hii.product_id = p.id
        WHERE hi.shop_id = ? AND hi.seller_id = ?
        GROUP BY hi.id
        ORDER BY hi.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([$shop_id, $user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse cart items to get count
    foreach ($invoices as &$invoice) {
        $items = json_decode($invoice['cart_items'] ?? '[]', true);
        $invoice['item_count'] = count($items);
    }
    
    echo json_encode($invoices);
    
} catch (Exception $e) {
    echo json_encode([]);
}