<?php
session_start();
require_once 'config/database.php';

// Get current user info
$business_id = $_SESSION['business_id'] ?? 1; // Default to business ID 1 if not set

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Prepare data
        $name = trim($_POST['name']);
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $account_holder_name = trim($_POST['account_holder_name'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $ifsc_code = trim($_POST['ifsc_code'] ?? '');
        $branch_name = trim($_POST['branch_name'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $shop_id = !empty($_POST['shop_id']) ? $_POST['shop_id'] : null;

        if (isset($_POST['id']) && $_POST['id']) {
            // Update existing manufacturer
            $stmt = $pdo->prepare("
                UPDATE manufacturers 
                SET name = ?, 
                    contact_person = ?, 
                    phone = ?, 
                    email = ?, 
                    address = ?, 
                    gstin = ?, 
                    account_holder_name = ?,
                    bank_name = ?,
                    account_number = ?,
                    ifsc_code = ?,
                    branch_name = ?,
                    is_active = ?, 
                    shop_id = ?
                WHERE id = ? AND business_id = ?
            ");
            
            $stmt->execute([
                $name,
                $contact_person,
                $phone,
                $email,
                $address,
                $gstin,
                $account_holder_name,
                $bank_name,
                $account_number,
                $ifsc_code,
                $branch_name,
                $is_active,
                $shop_id,
                $_POST['id'],
                $business_id
            ]);
            
            $_SESSION['success'] = "Supplier updated successfully!";
        } else {
            // Insert new manufacturer
            $stmt = $pdo->prepare("
                INSERT INTO manufacturers 
                (business_id, name, contact_person, phone, email, address, gstin, 
                 account_holder_name, bank_name, account_number, ifsc_code, branch_name,
                 is_active, shop_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $business_id,
                $name,
                $contact_person,
                $phone,
                $email,
                $address,
                $gstin,
                $account_holder_name,
                $bank_name,
                $account_number,
                $ifsc_code,
                $branch_name,
                $is_active,
                $shop_id
            ]);
            
            $_SESSION['success'] = "Supplier added successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
} elseif (isset($_GET['delete'])) {
    try {
        // Check if manufacturer has any purchases before deleting
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE manufacturer_id = ?");
        $check_stmt->execute([$_GET['delete']]);
        $purchase_count = $check_stmt->fetchColumn();
        
        if ($purchase_count > 0) {
            $_SESSION['error'] = "Cannot delete supplier. This supplier has $purchase_count purchase record(s). Please deactivate instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM manufacturers WHERE id = ? AND business_id = ?");
            $stmt->execute([$_GET['delete'], $business_id]);
            $_SESSION['success'] = "Supplier deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

header('Location: manufacturers.php');
exit();
?>