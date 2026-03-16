<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['current_business_id'] ?? 1;
$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

if (!$invoice_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit();
}

try {
    // Get invoice details
    $stmt = $pdo->prepare("
        SELECT id, invoice_number, total, customer_id 
        FROM invoices 
        WHERE id = ? AND business_id = ?
    ");
    $stmt->execute([$invoice_id, $business_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit();
    }
    
    // Get invoice items with return quantities
    $stmt = $pdo->prepare("
        SELECT 
            ii.id,
            ii.product_id,
            p.product_name,
            ii.quantity,
            ii.return_qty,
            ii.unit_price,
            ii.hsn_code,
            ii.total_price,
            ii.discount_amount,
            ii.cgst_amount,
            ii.sgst_amount,
            ii.igst_amount
        FROM invoice_items ii
        LEFT JOIN products p ON ii.product_id = p.id
        WHERE ii.invoice_id = ?
        ORDER BY ii.id ASC
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'invoice' => $invoice,
        'invoice_total' => (float)$invoice['total'],
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}