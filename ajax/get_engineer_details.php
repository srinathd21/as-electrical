<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['engineer_id'])) {
    exit('Invalid request');
}

$engineer_id = (int)$_GET['engineer_id'];

// Get engineer details
$stmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM sites WHERE engineer_id = e.engineer_id) as site_count,
           (SELECT COUNT(*) FROM invoices WHERE engineer_id = e.engineer_id) as invoice_count
    FROM engineers e
    WHERE e.engineer_id = ?
");
$stmt->execute([$engineer_id]);
$engineer = $stmt->fetch();

if (!$engineer) {
    echo '<div class="alert alert-danger">Engineer not found.</div>';
    exit();
}

// Get assigned sites
$sites_stmt = $pdo->prepare("
    SELECT site_id, site_name, site_address, city, status, start_date
    FROM sites
    WHERE engineer_id = ?
    ORDER BY site_name
");
$sites_stmt->execute([$engineer_id]);
$sites = $sites_stmt->fetchAll();

// Get recent invoices
$invoices_stmt = $pdo->prepare("
    SELECT i.id, i.invoice_number, i.total, i.created_at, i.payment_status,
           c.name as customer_name
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.engineer_id = ?
    ORDER BY i.created_at DESC
    LIMIT 10
");
$invoices_stmt->execute([$engineer_id]);
$invoices = $invoices_stmt->fetchAll();

$status_class = [
    'active' => 'success',
    'inactive' => 'danger',
    'on_leave' => 'warning'
][$engineer['status']] ?? 'secondary';
?>

<div class="p-3">
    <!-- Basic Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h4 class="mb-3"><?= htmlspecialchars($engineer['first_name'] . ' ' . $engineer['last_name']) ?></h4>
            <table class="table table-sm table-borderless">
                <tr>
                    <td style="width: 120px;"><strong>Engineer ID:</strong></td>
                    <td>#<?= $engineer['engineer_id'] ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><a href="mailto:<?= htmlspecialchars($engineer['email']) ?>"><?= htmlspecialchars($engineer['email']) ?></a></td>
                </tr>
                <?php if (!empty($engineer['phone'])): ?>
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td><a href="tel:<?= htmlspecialchars($engineer['phone']) ?>"><?= htmlspecialchars($engineer['phone']) ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($engineer['specialization'])): ?>
                <tr>
                    <td><strong>Specialization:</strong></td>
                    <td><?= htmlspecialchars($engineer['specialization']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($engineer['hire_date'])): ?>
                <tr>
                    <td><strong>Hire Date:</strong></td>
                    <td><?= date('d M Y', strtotime($engineer['hire_date'])) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>
                        <span class="badge bg-<?= $status_class ?> bg-opacity-10 text-<?= $status_class ?> px-3 py-2">
                            <?= ucfirst(str_replace('_', ' ', $engineer['status'])) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Added on:</strong></td>
                    <td><?= date('d M Y, h:i A', strtotime($engineer['created_at'])) ?></td>
                </tr>
                <tr>
                    <td><strong>Last updated:</strong></td>
                    <td><?= date('d M Y, h:i A', strtotime($engineer['updated_at'])) ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="mb-3">Statistics</h5>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 bg-primary bg-opacity-10 rounded">
                                <h3 class="mb-0 text-primary"><?= $engineer['site_count'] ?></h3>
                                <small class="text-muted">Sites</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-info bg-opacity-10 rounded">
                                <h3 class="mb-0 text-info"><?= $engineer['invoice_count'] ?></h3>
                                <small class="text-muted">Invoices</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Sites -->
    <div class="mb-4">
        <h5 class="mb-3">
            <i class="bx bx-map me-2"></i> Assigned Sites (<?= count($sites) ?>)
        </h5>
        <?php if (empty($sites)): ?>
        <p class="text-muted">No sites assigned to this engineer.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Site Name</th>
                        <th>Address</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Start Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                    <tr>
                        <td>
                            <a href="sites.php?view=<?= $site['site_id'] ?>">
                                <?= htmlspecialchars($site['site_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($site['site_address'] ?? '') ?></td>
                        <td><?= htmlspecialchars($site['city'] ?? '') ?></td>
                        <td>
                            <?php 
                            $site_status_class = [
                                'active' => 'success',
                                'inactive' => 'danger',
                                'completed' => 'info',
                                'on_hold' => 'warning'
                            ][$site['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $site_status_class ?>"><?= ucfirst($site['status'] ?? 'active') ?></span>
                        </td>
                        <td><?= $site['start_date'] ? date('d M Y', strtotime($site['start_date'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Invoices -->
    <div>
        <h5 class="mb-3">
            <i class="bx bx-receipt me-2"></i> Recent Invoices (<?= count($invoices) ?>)
        </h5>
        <?php if (empty($invoices)): ?>
        <p class="text-muted">No invoices associated with this engineer.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
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