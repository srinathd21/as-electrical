<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit();
}

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['current_business_id'] ?? 1;
$shop_id = $_SESSION['current_shop_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$return_reason = $_POST['return_reason'] ?? '';
$refund_method = $_POST['refund_method'] ?? '';
$notes = $_POST['notes'] ?? '';
$selected_items = $_POST['selected_items'] ?? [];
$return_qty = $_POST['return_qty'] ?? [];

if (!$invoice_id || !$customer_id || empty($return_reason) || empty($refund_method) || empty($selected_items)) {
    echo json_encode(['success' => false, 'message' => 'Please select items and provide return reason and refund method']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get invoice details
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as customer_name 
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.id = ? AND i.business_id = ?
    ");
    $stmt->execute([$invoice_id, $business_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    // Calculate total return amount and validate quantities
    $total_return_amount = 0;
    $return_items = [];
    
    foreach ($selected_items as $item_id) {
        $qty = isset($return_qty[$item_id]) ? (int)$return_qty[$item_id] : 0;
        
        if ($qty <= 0) continue;
        
        // Get invoice item details
        $stmt = $pdo->prepare("
            SELECT ii.*, p.product_name, p.unit_of_measure, p.stock_price
            FROM invoice_items ii
            LEFT JOIN products p ON ii.product_id = p.id
            WHERE ii.id = ? AND ii.invoice_id = ?
        ");
        $stmt->execute([$item_id, $invoice_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        // Check if return quantity is valid
        $max_return = $item['quantity'] - ($item['return_qty'] ?? 0);
        if ($qty > $max_return) {
            throw new Exception('Return quantity exceeds maximum allowed for ' . $item['product_name']);
        }
        
        $item_return_amount = $qty * $item['unit_price'];
        $total_return_amount += $item_return_amount;
        
        $return_items[] = [
            'item_id' => $item_id,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => $qty,
            'unit_price' => $item['unit_price'],
            'total_amount' => $item_return_amount,
            'cgst_amount' => ($qty / $item['quantity']) * $item['cgst_amount'],
            'sgst_amount' => ($qty / $item['quantity']) * $item['sgst_amount'],
            'igst_amount' => ($qty / $item['quantity']) * $item['igst_amount'],
            'stock_price' => $item['stock_price'] ?? 0
        ];
    }
    
    if (empty($return_items)) {
        throw new Exception('No valid items selected for return');
    }
    
    // Generate return number
    $return_number = 'RTN-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // First, check the structure of the returns table
    $columns_stmt = $pdo->query("SHOW COLUMNS FROM returns");
    $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Prepare insert based on available columns
    if (in_array('refund_method', $columns)) {
        // Table has refund_method column
        $stmt = $pdo->prepare("
            INSERT INTO returns (
                invoice_id, customer_id, return_date, total_return_amount, 
                return_reason, notes, refund_method, processed_by, business_id
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_id,
            $customer_id,
            $total_return_amount,
            $return_reason,
            $notes,
            $refund_method,
            $user_id,
            $business_id
        ]);
    } elseif (in_array('refund_to_cash', $columns)) {
        // Table has refund_to_cash column (boolean)
        $refund_to_cash = ($refund_method == 'cash') ? 1 : 0;
        $stmt = $pdo->prepare("
            INSERT INTO returns (
                invoice_id, customer_id, return_date, total_return_amount, 
                return_reason, notes, refund_to_cash, processed_by, business_id
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_id,
            $customer_id,
            $total_return_amount,
            $return_reason,
            $notes,
            $refund_to_cash,
            $user_id,
            $business_id
        ]);
    } else {
        // Table doesn't have refund method column, insert without it
        $stmt = $pdo->prepare("
            INSERT INTO returns (
                invoice_id, customer_id, return_date, total_return_amount, 
                return_reason, notes, processed_by, business_id
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_id,
            $customer_id,
            $total_return_amount,
            $return_reason,
            $notes,
            $user_id,
            $business_id
        ]);
    }
    
    $return_id = $pdo->lastInsertId();
    
    // Process each return item
    foreach ($return_items as $item) {
        // Insert into return_items table
        $stmt = $pdo->prepare("
            INSERT INTO return_items (
                return_id, invoice_item_id, product_id, 
                quantity, unit_price, return_value
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $return_id,
            $item['item_id'],
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['total_amount']
        ]);
        
        // Update invoice_items return_qty
        $stmt = $pdo->prepare("
            UPDATE invoice_items 
            SET return_qty = return_qty + ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['item_id']]);
        
        // Restock the product
        // First check if product_stocks exists
        $stmt = $pdo->prepare("
            SELECT id, quantity FROM product_stocks 
            WHERE product_id = ? AND shop_id = ? AND business_id = ?
        ");
        $stmt->execute([$item['product_id'], $shop_id, $business_id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stock) {
            // Update existing stock
            $stmt = $pdo->prepare("
                UPDATE product_stocks 
                SET quantity = quantity + ?, last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $stock['id']]);
        } else {
            // Create new stock entry
            $stmt = $pdo->prepare("
                INSERT INTO product_stocks 
                (product_id, shop_id, business_id, quantity, last_updated)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$item['product_id'], $shop_id, $business_id, $item['quantity']]);
        }
        
        // Log stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements 
            (product_id, shop_id, business_id, movement_type, quantity, 
             reference_type, reference_id, notes, created_by, created_at)
            VALUES (?, ?, ?, 'return', ?, 'return', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $item['product_id'],
            $shop_id,
            $business_id,
            $item['quantity'],
            $return_id,
            'Return: ' . $return_reason . ' - Invoice #' . $invoice['invoice_number'],
            $user_id
        ]);
    }
    
    // Update invoice totals
    $new_total = $invoice['total'] - $total_return_amount;
    $new_pending = $invoice['pending_amount'] - $total_return_amount;
    if ($new_pending < 0) $new_pending = 0;
    
    // Determine new payment status
    if ($new_pending <= 0) {
        $payment_status = 'paid';
    } elseif ($new_pending < $new_total) {
        $payment_status = 'partial';
    } else {
        $payment_status = 'pending';
    }
    
    // Calculate new paid amount
    $new_paid = $invoice['paid_amount'];
    
    // Handle refund based on method
    if ($refund_method == 'cash') {
        // If cash refund, reduce paid amount
        $new_paid = $invoice['paid_amount'] - $total_return_amount;
        if ($new_paid < 0) $new_paid = 0;
        
        // Log refund payment
        $stmt = $pdo->prepare("
            INSERT INTO invoice_payments 
            (invoice_id, customer_id, business_id, payment_amount, payment_method, 
             reference_no, notes, created_by, created_at, payment_date)
            VALUES (?, ?, ?, ?, 'cash', ?, ?, ?, NOW(), CURDATE())
        ");
        $stmt->execute([
            $invoice_id,
            $customer_id,
            $business_id,
            -$total_return_amount,
            'REFUND-' . $return_number,
            'Cash refund for return #' . $return_number . ': ' . $return_reason,
            $user_id
        ]);
    } elseif ($refund_method == 'credit') {
        // Add to customer credit - check if table exists
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customer_credit_adjustments 
                (business_id, customer_id, adjustment_type, amount, adjustment_date, description, created_by)
                VALUES (?, ?, 'credit', ?, CURDATE(), ?, ?)
            ");
            $stmt->execute([
                $business_id,
                $customer_id,
                $total_return_amount,
                'Credit from return #' . $return_number . ': ' . $return_reason,
                $user_id
            ]);
        } catch (Exception $e) {
            // Table might not exist, log error but continue
            error_log("Credit adjustment table error: " . $e->getMessage());
        }
        
        // No change to paid amount for credit refund
        $new_paid = $invoice['paid_amount'];
    } elseif ($refund_method == 'bank') {
        // Log bank refund
        $stmt = $pdo->prepare("
            INSERT INTO invoice_payments 
            (invoice_id, customer_id, business_id, payment_amount, payment_method, 
             reference_no, notes, created_by, created_at, payment_date)
            VALUES (?, ?, ?, ?, 'bank', ?, ?, ?, NOW(), CURDATE())
        ");
        $stmt->execute([
            $invoice_id,
            $customer_id,
            $business_id,
            -$total_return_amount,
            'BANK-REFUND-' . $return_number,
            'Bank transfer refund for return #' . $return_number . ': ' . $return_reason,
            $user_id
        ]);
        
        $new_paid = $invoice['paid_amount'];
    } // For 'adjust' method, just reduce invoice total without payment changes
    
    $stmt = $pdo->prepare("
        UPDATE invoices 
        SET total = ?, 
            paid_amount = ?,
            pending_amount = ?,
            payment_status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$new_total, $new_paid, $new_pending, $payment_status, $invoice_id]);
    
    // Update invoice_credit if exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'invoice_credit'");
    if ($table_check->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT id FROM invoice_credit WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE invoice_credit 
                SET total_amount = ?, 
                    paid_amount = ?,
                    credit_amount = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE invoice_id = ?
            ");
            $stmt->execute([
                $new_total,
                $new_paid,
                $new_pending,
                $payment_status,
                $invoice_id
            ]);
        }
    }
    
    // Reverse loyalty points if earned - check if tables exist
    $points_table_check = $pdo->query("SHOW TABLES LIKE 'customer_points'");
    if ($points_table_check->rowCount() > 0 && ($invoice['loyalty_points_used'] ?? 0) > 0) {
        $stmt = $pdo->prepare("
            SELECT id, available_points FROM customer_points 
            WHERE customer_id = ? AND business_id = ?
        ");
        $stmt->execute([$customer_id, $business_id]);
        $points = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($points) {
            // Calculate points to reverse based on return amount
            $points_earned_ratio = $total_return_amount / $invoice['total'];
            $points_to_reverse = round(($invoice['loyalty_points_earned'] ?? 0) * $points_earned_ratio);
            
            if ($points_to_reverse > 0) {
                $new_available = $points['available_points'] - $points_to_reverse;
                if ($new_available < 0) $new_available = 0;
                
                $stmt = $pdo->prepare("
                    UPDATE customer_points 
                    SET available_points = ?,
                        total_points_earned = total_points_earned - ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_available, $points_to_reverse, $points['id']]);
                
                // Check if point_transactions table exists
                $trans_table_check = $pdo->query("SHOW TABLES LIKE 'point_transactions'");
                if ($trans_table_check->rowCount() > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO point_transactions 
                        (customer_id, business_id, transaction_type, points, notes, created_by, created_at)
                        VALUES (?, ?, 'adjustment', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $customer_id,
                        $business_id,
                        -$points_to_reverse,
                        'Points reversed for return #' . $return_number,
                        $user_id
                    ]);
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Return processed successfully. Return #' . $return_number . ' created. Total return amount: ₹' . number_format($total_return_amount, 2),
        'return_id' => $return_id,
        'return_number' => $return_number,
        'total_return_amount' => $total_return_amount,
        'new_invoice_total' => $new_total
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error processing return: ' . $e->getMessage()]);
}