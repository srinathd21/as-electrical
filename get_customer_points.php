<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? 0;
$business_id = $_GET['business_id'] ?? $_SESSION['current_business_id'] ?? 0;

if (!$customer_id || !$business_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    // Check if customer exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ?");
    $stmt->execute([$customer_id, $business_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Get customer points
    $points_stmt = $pdo->prepare("
        SELECT * FROM customer_points 
        WHERE customer_id = ? AND business_id = ?
    ");
    $points_stmt->execute([$customer_id, $business_id]);
    $points = $points_stmt->fetch();
    
    if ($points) {
        echo json_encode([
            'success' => true,
            'points' => [
                'available_points' => (float)$points['available_points'],
                'total_points_earned' => (float)$points['total_points_earned'],
                'total_points_redeemed' => (float)$points['total_points_redeemed']
            ]
        ]);
    } else {
        // Create new points record
        $insert_stmt = $pdo->prepare("
            INSERT INTO customer_points (customer_id, business_id, 
                                         total_points_earned, total_points_redeemed, 
                                         available_points)
            VALUES (?, ?, 0.00, 0.00, 0.00)
        ");
        $insert_stmt->execute([$customer_id, $business_id]);
        
        echo json_encode([
            'success' => true,
            'points' => [
                'available_points' => 0,
                'total_points_earned' => 0,
                'total_points_redeemed' => 0
            ]
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>