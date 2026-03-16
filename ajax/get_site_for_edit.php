<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['site_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$site_id = (int)$_GET['site_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE site_id = ?");
    $stmt->execute([$site_id]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($site) {
        echo json_encode(['success' => true, 'data' => $site]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Site not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}