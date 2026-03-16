<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$business_id = $_SESSION['business_id'] ?? 1;
$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$for_return = isset($_GET['for_return']) && $_GET['for_return'] == 1;

if (!$invoice_id) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid invoice ID.</div>';
    exit();
}

// Fetch invoice info
$inv_stmt = $pdo->prepare("
    SELECT i.invoice_number, i.created_at, c.name as customer_name
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.id = ? AND i.business_id = ?
");
$inv_stmt->execute([$invoice_id, $business_id]);
$invoice = $inv_stmt->fetch();

if (!$invoice) {
    echo '<div class="alert alert-danger">Invoice not found or access denied.</div>';
    exit();
}

// Fetch original invoice items
$stmt = $pdo->prepare("
    SELECT ii.id, ii.product_id, ii.quantity, ii.unit_price, ii.total_price, ii.total_with_gst,
           p.product_name, p.product_code
    FROM invoice_items ii
    JOIN products p ON ii.product_id = p.id AND p.business_id = ?
    WHERE ii.invoice_id = ?
    ORDER BY ii.id ASC
");
$stmt->execute([$business_id, $invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo '<div class="alert alert-info text-center">No items found in this invoice.</div>';
    exit();
}

// Get already returned quantities per invoice_item
$returned_qty = [];
if ($for_return) {
    $ret_stmt = $pdo->prepare("
        SELECT invoice_item_id, SUM(quantity) as returned_qty
        FROM return_items
        WHERE invoice_item_id IN (SELECT id FROM invoice_items WHERE invoice_id = ?)
        GROUP BY invoice_item_id
    ");
    $ret_stmt->execute([$invoice_id]);
    foreach ($ret_stmt->fetchAll() as $ret) {
        $returned_qty[$ret['invoice_item_id']] = (int)$ret['returned_qty'];
    }
}
?>

<!-- Invoice Header -->
<div class="text-center mb-4 pb-3 border-bottom">
    <h5 class="fw-bold">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h5>
    <p class="text-muted mb-0">
        Customer: <strong><?= htmlspecialchars($invoice['customer_name'] ?? 'Walk-in') ?></strong><br>
        Date: <?= date('d M Y, h:i A', strtotime($invoice['created_at'])) ?>
    </p>
</div>

<!-- Items Table -->
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Product</th>
                <th class="text-center">Qty Sold</th>
                <th class="text-center">Returned</th>
                <th class="text-center">Available</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Line Total</th>
                <th class="text-center">Status</th>
                <?php if ($for_return): ?>
                <th class="text-center">Return Qty</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sr = 1;
            $grand_total_active = 0; // Only non-returned items
            foreach ($items as $item): 
                $sold_qty = $item['quantity'];
                $returned_qty_this = $returned_qty[$item['id']] ?? 0;
                $available_qty = $sold_qty - $returned_qty_this;
                $line_total = $item['total_with_gst'] ?: $item['total_price'];
                $active_line_total = ($available_qty / $sold_qty) * $line_total;
                $grand_total_active += $active_line_total;

                $is_fully_returned = $available_qty <= 0;
                $is_partially_returned = $returned_qty_this > 0 && $available_qty > 0;
            ?>
            <tr class="<?= $is_fully_returned ? 'table-danger' : ($is_partially_returned ? 'table-warning' : '') ?>">
                <td><?= $sr++ ?></td>
                <td>
                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                    <?php if ($item['product_code']): ?>
                    <br><small class="text-muted">Code: <?= htmlspecialchars($item['product_code']) ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold"><?= $sold_qty ?></td>
                <td class="text-center text-danger"><?= $returned_qty_this ?></td>
                <td class="text-center fw-bold text-success"><?= $available_qty ?></td>
                <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                <td class="text-end">₹<?= number_format($line_total, 2) ?></td>
                <td class="text-center">
                    <?php if ($is_fully_returned): ?>
                    <span class="badge bg-danger">
                        <i class="bx bx-refresh me-1"></i> Fully Returned
                    </span>
                    <?php elseif ($is_partially_returned): ?>
                    <span class="badge bg-warning">
                        <i class="bx bx-refresh me-1"></i> Partially Returned
                    </span>
                    <?php else: ?>
                    <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                </td>
                <?php if ($for_return): ?>
                <td class="text-center">
                    <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                    <input type="number"
                           name="return_qty[<?= $item['id'] ?>]"
                           class="form-control form-control-sm return-qty-input text-center"
                           min="0"
                           max="<?= $available_qty ?>"
                           value="0"
                           style="width: 90px;"
                           <?= $is_fully_returned ? 'disabled' : '' ?>
                           data-max="<?= $available_qty ?>">
                    <small class="text-muted d-block mt-1">
                        Max: <?= $available_qty ?>
                    </small>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <tr class="table-active fw-bold fs-6">
                <td colspan="<?= $for_return ? 8 : 7 ?>" class="text-end">Active Total (Excl. Returns):</td>
                <td class="text-end text-success">₹<?= number_format($grand_total_active, 2) ?></td>
                <?php if ($for_return): ?><td></td><?php endif; ?>
            </tr>
        </tbody>
    </table>
</div>

<?php if ($for_return): ?>
<!-- Return Options -->
<hr class="my-4">
<div class="row g-3">
    <div class="col-md-7">
        <label class="form-label fw-bold">Return Reason <span class="text-danger">*</span></label>
        <select name="return_reason" class="form-select" required>
            <option value="">-- Select Reason --</option>
            <option value="defective">Defective Product</option>
            <option value="wrong_item">Wrong Item Delivered</option>
            <option value="not_needed">Not Needed Anymore</option>
            <option value="size_issue">Size/Fit Issue</option>
            <option value="damaged">Damaged in Transit</option>
            <option value="other">Other</option>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label fw-bold">Refund Type</label>
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="refund_to_cash" id="refundCash" value="1">
            <label class="form-check-label" for="refundCash">
                <i class="bx bx-money me-1"></i> Refund in cash
            </label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label fw-bold">Additional Notes</label>
        <textarea name="return_notes" class="form-control" rows="3" placeholder="Any extra details..."></textarea>
    </div>
</div>
<?php endif; ?>