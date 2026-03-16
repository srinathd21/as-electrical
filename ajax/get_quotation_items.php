<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$quotation_id = (int)$_GET['quotation_id'] ?? 0;

if ($quotation_id <= 0) {
    die(json_encode(['error' => 'Invalid quotation ID']));
}

// Get quotation items
$stmt = $pdo->prepare("
    SELECT 
        qi.*,
        p.name as product_name,
        p.sku as product_sku
    FROM quotation_items qi
    LEFT JOIN products p ON qi.product_id = p.id
    WHERE qi.quotation_id = ?
    ORDER BY qi.id
");
$stmt->execute([$quotation_id]);
$items = $stmt->fetchAll();

// Get quotation total
$total_stmt = $pdo->prepare("SELECT grand_total FROM quotations WHERE id = ?");
$total_stmt->execute([$quotation_id]);
$quotation = $total_stmt->fetch();

// Display items in a table
?>
<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-light">
            <tr>
                <th>Product</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <small class="text-muted"><?= htmlspecialchars($item['product_sku'] ?? 'N/A') ?></small><br>
                    <?= htmlspecialchars($item['product_name']) ?>
                </td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                <td class="text-end">₹<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                <td class="text-end"><strong>₹<?= number_format($quotation['grand_total'] ?? 0, 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>