<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$business_id = $_SESSION['current_business_id'] ?? 1;
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listEngineers();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listEngineers() {
    global $pdo, $business_id;
    
    // Note: engineers table doesn't have business_id column
    // So we'll get all active engineers
    $stmt = $pdo->prepare("
        SELECT engineer_id, first_name, last_name, email, phone, specialization, status
        FROM engineers
        WHERE status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'engineers' => $engineers
    ]);
}
?>