<?php
// ajax/send_quotation_whatsapp.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$quotation_id = isset($_POST['quotation_id']) ? (int)$_POST['quotation_id'] : 0;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if ($quotation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quotation ID']);
    exit();
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit();
}

// Fetch quotation details
$stmt = $pdo->prepare("
    SELECT q.*, s.shop_name, s.business_id 
    FROM quotations q
    LEFT JOIN shops s ON q.shop_id = s.id
    WHERE q.id = ?
");
$stmt->execute([$quotation_id]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    echo json_encode(['success' => false, 'message' => 'Quotation not found']);
    exit();
}

// Check if token exists, if not generate one
if (empty($quotation['public_token'])) {
    $token = hash('sha256', $quotation_id . $quotation['quotation_number'] . time() . 'quotation_secret_key');
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $update_stmt = $pdo->prepare("
        UPDATE quotations 
        SET public_token = ?, token_expiry = ?, token_created_at = NOW() 
        WHERE id = ?
    ");
    $update_stmt->execute([$token, $expiry, $quotation_id]);
} else {
    $token = $quotation['public_token'];
}

// Generate public link - UPDATED WITH CORRECT PATH
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$base_url = str_replace('/ajax', '', $base_url); // Remove /ajax from path
$public_link = $base_url . '/as_electrical/quotation_public_view.php?token=' . $token;

// Format phone number (remove any non-numeric characters)
$phone = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone, 0, 1) == '0') {
    $phone = substr($phone, 1);
}
if (substr($phone, 0, 2) != '91' && strlen($phone) == 10) {
    $phone = '91' . $phone;
}

// Get company name
$company_name = $quotation['shop_name'] ?? 'AS Electricals';

// Prepare message
$message = "Dear " . ($quotation['customer_name'] ?? 'Customer') . ",\n\n";
$message .= "Thank you for your interest in AS Electricals. Please find your quotation below:\n\n";
$message .= "📄 Quotation No: " . $quotation['quotation_number'] . "\n";
$message .= "📅 Date: " . date('d-m-Y', strtotime($quotation['quotation_date'])) . "\n";
$message .= "⏰ Valid Until: " . date('d-m-Y', strtotime($quotation['valid_until'])) . "\n";
$message .= "💰 Total Amount: ₹" . number_format($quotation['grand_total'], 2) . "\n\n";
$message .= "🔗 View your quotation online:\n" . $public_link . "\n\n";
$message .= "You can view, print, or download the PDF from the link above.\n\n";
$message .= "Thank you for your business!\n";
$message .= "AS Electricals";

// Create WhatsApp URL
$whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);

echo json_encode([
    'success' => true,
    'whatsapp_url' => $whatsapp_url,
    'message' => 'WhatsApp link generated successfully'
]);
exit();