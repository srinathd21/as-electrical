<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['site_id'])) {
    exit('Invalid request');
}

$site_id = (int)$_GET['site_id'];

// Get site details with engineer info
$stmt = $pdo->prepare("
    SELECT s.*, 
           e.first_name, e.last_name, e.email as engineer_email, 
           e.phone as engineer_phone, e.specialization as engineer_specialization,
           (SELECT COUNT(*) FROM invoices WHERE site_id = s.site_id) as invoice_count
    FROM sites s
    LEFT JOIN engineers e ON s.engineer_id = e.engineer_id
    WHERE s.site_id = ?
");
$stmt->execute([$site_id]);
$site = $stmt->fetch();

if (!$site) {
    echo '<div class="alert alert-danger">Site not found.</div>';
    exit();
}

// Get recent invoices for this site
$invoices_stmt = $pdo->prepare("
    SELECT i.id, i.invoice_number, i.total, i.created_at, i.payment_status,
           c.name as customer_name
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.site_id = ?
    ORDER BY i.created_at DESC
    LIMIT 10
");
$invoices_stmt->execute([$site_id]);
$invoices = $invoices_stmt->fetchAll();

$status_class = [
    'active' => 'success',
    'completed' => 'info',
    'on_hold' => 'warning',
    'inactive' => 'danger'
][$site['status']] ?? 'secondary';
?>

<div class="p-3">
    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-3"><?= htmlspecialchars($site['site_name']) ?></h4>
            
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Location Information</h6>
                    <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($site['site_address'])) ?></p>
                    <?php if (!empty($site['city']) || !empty($site['state']) || !empty($site['postal_code'])): ?>
                    <p><strong>City/State:</strong> 
                        <?= htmlspecialchars($site['city'] ?? '') ?>
                        <?php if (!empty($site['state'])): ?>, <?= htmlspecialchars($site['state']) ?><?php endif; ?>
                        <?php if (!empty($site['postal_code'])): ?> - <?= htmlspecialchars($site['postal_code']) ?><?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($site['country'])): ?>
                    <p><strong>Country:</strong> <?= htmlspecialchars($site['country']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Project Details</h6>
                    <?php if (!empty($site['project_type'])): ?>
                    <p><strong>Project Type:</strong> <?= htmlspecialchars($site['project_type']) ?></p>
                    <?php endif; ?>
                    <p><strong>Timeline:</strong> 
                        <?= $site['start_date'] ? date('d M Y', strtotime($site['start_date'])) : 'Not started' ?>
                        → 
                        <?= $site['expected_end_date'] ? date('d M Y', strtotime($site['expected_end_date'])) : 'No end date' ?>
                    </p>
                    <p>
                        <strong>Status:</strong>
                        <span class="badge bg-<?= $status_class ?> ms-2"><?= ucfirst(str_replace('_', ' ', $site['status'])) ?></span>
                    </p>
                </div>
            </div>
            
            <!-- Recent Invoices -->
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Recent Invoices (<?= count($invoices) ?>)</h6>
                    <?php if (empty($invoices)): ?>
                    <p class="text-muted">No invoices associated with this site.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th class="text-end">Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td>
                                        <a href="invoice_view.php?invoice_id=<?= $inv['id'] ?>" target="_blank">
                                            <?= htmlspecialchars($inv['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($inv['customer_name'] ?? 'Walk-in') ?></td>
                                    <td class="text-end">₹<?= number_format($inv['total'], 2) ?></td>
                                    <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                                    <td>
                                        <?php 
                                        $payment_class = [
                                            'paid' => 'success',
                                            'partial' => 'warning',
                                            'pending' => 'danger'
                                        ][$inv['payment_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $payment_class ?>"><?= ucfirst($inv['payment_status']) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Assigned Engineer</h6>
                    <?php if ($site['engineer_id']): ?>
                    <div class="text-center mb-3">
                        <div class="avatar-lg mx-auto mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center"
                                 style="width: 80px; height: 80px; margin: 0 auto;">
                                <i class="bx bx-user fs-1"></i>
                            </div>
                        </div>
                        <h5><?= htmlspecialchars($site['first_name'] . ' ' . $site['last_name']) ?></h5>
                        <?php if (!empty($site['engineer_specialization'])): ?>
                        <p class="text-muted"><?= htmlspecialchars($site['engineer_specialization']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <p><i class="bx bx-envelope me-2"></i> <?= htmlspecialchars($site['engineer_email']) ?></p>
                        <?php if (!empty($site['engineer_phone'])): ?>
                        <p><i class="bx bx-phone me-2"></i> <?= htmlspecialchars($site['engineer_phone']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="d-grid mt-3">
                        <a href="engineers.php?view=<?= $site['engineer_id'] ?>" class="btn btn-outline-primary btn-sm">
                            View Engineer Profile
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center">No engineer assigned to this site.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card bg-light mt-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Metadata</h6>
                    <p><small><strong>Created:</strong> <?= date('d M Y, h:i A', strtotime($site['created_at'])) ?></small></p>
                    <p><small><strong>Last Updated:</strong> <?= date('d M Y, h:i A', strtotime($site['updated_at'])) ?></small></p>
                    <p><small><strong>Total Invoices:</strong> <?= $site['invoice_count'] ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>