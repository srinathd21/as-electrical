<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Invalid request method']));
}

$quotation_id = (int)$_POST['quotation_id'] ?? 0;

if ($quotation_id <= 0) {
    die(json_encode(['error' => 'Invalid quotation ID']));
}

// Check if user has permission
$user_role = $_SESSION['role'] ?? 'seller';
$permitted_roles = ['admin', 'shop_manager', 'seller'];
if (!in_array($user_role, $permitted_roles)) {
    die(json_encode(['error' => 'Permission denied']));
}

// Check if quotation exists and is in draft status
$check_stmt = $pdo->prepare("SELECT status FROM quotations WHERE id = ? AND business_id = ?");
$check_stmt->execute([$quotation_id, $_SESSION['current_business_id'] ?? 1]);
$quotation = $check_stmt->fetch();

if (!$quotation) {
    die(json_encode(['error' => 'Quotation not found']));
}

if ($quotation['status'] !== 'draft') {
    die(json_encode(['error' => 'Only draft quotations can be deleted']));
}

try {
    $pdo->beginTransaction();
    
    // Delete quotation items
    $delete_items = $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
    $delete_items->execute([$quotation_id]);
    
    // Delete quotation
    $delete_quotation = $pdo->prepare("DELETE FROM quotations WHERE id = ?");
    $delete_quotation->execute([$quotation_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Quotation deleted successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}