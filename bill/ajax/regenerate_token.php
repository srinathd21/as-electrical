<?php
// ajax/regenerate_token.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$quotation_id = isset($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : 0;

if ($quotation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quotation ID']);
    exit();
}

// Generate new token
$token = hash('sha256', $quotation_id . time() . rand(1000, 9999) . 'quotation_secret_key');
$expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

$stmt = $pdo->prepare("
    UPDATE quotations 
    SET public_token = ?, token_expiry = ?, token_created_at = NOW() 
    WHERE id = ?
");
$success = $stmt->execute([$token, $expiry, $quotation_id]);

if ($success) {
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $base_url = str_replace('/ajax', '', $base_url);
    $public_link = $base_url . 'as_electrical/quotation_public_view.php?token=' . $token;
    
    echo json_encode([
        'success' => true,
        'message' => 'Token regenerated successfully',
        'token' => $token,
        'public_link' => $public_link
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to regenerate token']);
}
exit();