<?php
// api/hold_invoices.php
require_once '../includes/auth.php';
require_once '../config/database.php';

checkAuth();
$user_id = $_SESSION['user_id'];
$business_id = getBusinessId();
$shop_id = getShopId();

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            getHoldInvoices();
            break;
        case 'POST':
            createHoldInvoice();
            break;
        case 'DELETE':
            deleteHoldInvoice();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createHoldInvoice() {
    global $pdo, $user_id, $business_id, $shop_id;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Generate hold number
    $hold_number = 'HOLD' . date('YmdHis') . rand(100, 999);
    
    // Calculate expiry date
    $expiry_hours = $data['expiry_hours'] ?? 48;
    $expiry_at = date('Y-m-d H:i:s', strtotime("+$expiry_hours hours"));
    
    $sql = "INSERT INTO hold_invoices (
                hold_number, business_id, shop_id, user_id,
                customer_name, customer_phone, customer_address, customer_gstin,
                reference, subtotal, total, cart_items, expiry_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $hold_number,
        $business_id,
        $shop_id,
        $user_id,
        $data['customer_name'],
        $data['customer_phone'] ?? '',
        $data['customer_address'] ?? '',
        $data['customer_gstin'] ?? '',
        $data['reference'] ?? '',
        $data['subtotal'],
        $data['total'],
        json_encode($data['cart_items']),
        $expiry_at
    ]);
    
    $hold_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'hold_id' => $hold_id,
        'hold_number' => $hold_number
    ]);
}

function getHoldInvoices() {
    global $pdo, $shop_id;
    
    $sql = "SELECT hi.*, COUNT(hi.id) as item_count
            FROM hold_invoices hi
            WHERE hi.shop_id = ? AND hi.expiry_at > NOW()
            GROUP BY hi.id
            ORDER BY hi.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$shop_id]);
    $invoices = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'invoices' => $invoices]);
}
?>