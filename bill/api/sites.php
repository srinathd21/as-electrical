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
            listSites();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listSites() {
    global $pdo, $business_id;
    
    // Note: sites table doesn't have business_id column
    // So we'll get all active sites
    $stmt = $pdo->prepare("
        SELECT s.*, 
               CONCAT(e.first_name, ' ', e.last_name) as engineer_name,
               e.specialization,
               e.phone as engineer_phone,
               e.email as engineer_email
        FROM sites s
        LEFT JOIN engineers e ON s.engineer_id = e.engineer_id
        WHERE s.status = 'active'
        ORDER BY s.site_name
    ");
    $stmt->execute();
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sites' => $sites
    ]);
}
?>