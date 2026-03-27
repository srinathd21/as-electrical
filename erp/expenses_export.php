<?php
// expenses_export.php - Fixed version for Excel export only
session_start();
require_once 'config/database.php';

// ==================== LOGIN & ROLE CHECK ====================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$business_id = $_SESSION['business_id'] ?? 1;
$user_id = $_SESSION['user_id'];

// Allowed to view expenses
$can_view_expenses = in_array($user_role, ['admin', 'shop_manager', 'cashier']);
if (!$can_view_expenses) {
    $_SESSION['error'] = "Access denied. You don't have permission to export expenses.";
    header('Location: dashboard.php');
    exit();
}

// Shop selection logic
$current_shop_id = $_SESSION['current_shop_id'] ?? null;
if ($user_role !== 'admin' && !$current_shop_id) {
    header('Location: select_shop.php');
    exit();
}

// ==================== GET FILTERS FROM URL ====================
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$payment_method = $_GET['payment_method'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// ==================== BUILD QUERY WITH FILTERS ====================
$where = ["e.business_id = ?"];
$params = [$business_id];

if ($user_role !== 'admin' && $current_shop_id) {
    $where[] = "e.shop_id = ?";
    $params[] = $current_shop_id;
}

if ($search !== '') {
    $where[] = "(e.description LIKE ? OR e.reference LIKE ? OR e.payment_reference LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($category !== 'all') {
    $where[] = "e.category = ?";
    $params[] = $category;
}

if ($status !== 'all') {
    $where[] = "e.status = ?";
    $params[] = $status;
}

if ($payment_method !== 'all') {
    $where[] = "e.payment_method = ?";
    $params[] = $payment_method;
}

if ($date_from && $date_to) {
    $where[] = "e.date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$where_clause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ==================== FETCH EXPENSES ====================
$sql = "
    SELECT 
        e.date,
        e.description,
        e.amount,
        e.category,
        e.payment_method,
        e.payment_reference,
        e.reference as internal_reference,
        e.status,
        s.shop_name,
        u.full_name as added_by,
        e.created_at,
        e.notes
    FROM expenses e
    LEFT JOIN shops s ON e.shop_id = s.id AND s.business_id = ?
    LEFT JOIN users u ON e.added_by = u.id
    $where_clause
    ORDER BY e.date DESC, e.created_at DESC
";
array_unshift($params, $business_id);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== SIMPLE EXCEL EXPORT ====================
// This creates a proper Excel file with formatting

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="expenses_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Start Excel file creation
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:html="http://www.w3.org/TR/REC-html40">
    <Styles>
        <Style ss:ID="1">
            <Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/>
            <Interior ss:Color="#4CAF50" ss:Pattern="Solid"/>
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="2">
            <Font ss:Size="10"/>
            <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="3">
            <Font ss:Bold="1" ss:Size="11"/>
            <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
            <NumberFormat ss:Format="#,##0.00"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="4">
            <Font ss:Bold="1" ss:Size="12" ss:Color="#FF0000"/>
            <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
            <NumberFormat ss:Format="#,##0.00"/>
        </Style>
        <Style ss:ID="5">
            <Font ss:Bold="1" ss:Size="11"/>
            <Interior ss:Color="#E8F5E9" ss:Pattern="Solid"/>
        </Style>
    </Styles>
    
    <Worksheet ss:Name="Expenses">
        <Table>
            <Column ss:Width="100"/>
            <Column ss:Width="250"/>
            <Column ss:Width="100"/>
            <Column ss:Width="120"/>
            <Column ss:Width="120"/>
            <Column ss:Width="150"/>
            <Column ss:Width="150"/>
            <Column ss:Width="100"/>
            <Column ss:Width="150"/>
            <Column ss:Width="150"/>
            <Column ss:Width="150"/>
            <Column ss:Width="200"/>
            
            <!-- Header Row -->
            <Row>
                <Cell ss:StyleID="1"><Data ss:Type="String">Date</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Description</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Amount (₹)</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Category</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Payment Method</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Payment Reference</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Internal Reference</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Status</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Shop</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Added By</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Created At</Data></Cell>
                <Cell ss:StyleID="1"><Data ss:Type="String">Notes</Data></Cell>
            </Row>
            
            <?php 
            $total_amount = 0;
            foreach ($expenses as $expense): 
                $total_amount += $expense['amount'];
            ?>
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= date('d/m/Y', strtotime($expense['date'])) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['description']) ?></Data></Cell>
                <Cell ss:StyleID="3"><Data ss:Type="Number"><?= $expense['amount'] ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['category']) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= ucfirst(str_replace('_', ' ', $expense['payment_method'])) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['payment_reference'] ?? '') ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['internal_reference'] ?? '') ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= ucfirst($expense['status']) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['shop_name']) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['added_by']) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= date('d/m/Y H:i', strtotime($expense['created_at'])) ?></Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($expense['notes'] ?? '') ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
            
            <!-- Empty row for spacing -->
            <Row></Row>
            
            <!-- Summary Section -->
            <Row>
                <Cell ss:StyleID="5"><Data ss:Type="String">SUMMARY</Data></Cell>
            </Row>
            
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Total Expenses:</Data></Cell>
                <Cell ss:StyleID="3"><Data ss:Type="Number"><?= count($expenses) ?></Data></Cell>
            </Row>
            
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Total Amount:</Data></Cell>
                <Cell ss:StyleID="4"><Data ss:Type="Number"><?= $total_amount ?></Data></Cell>
            </Row>
            
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Date Range:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= date('d/m/Y', strtotime($date_from)) ?> to <?= date('d/m/Y', strtotime($date_to)) ?></Data></Cell>
            </Row>
            
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Exported On:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= date('d/m/Y H:i:s') ?></Data></Cell>
            </Row>
            
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Exported By:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></Data></Cell>
            </Row>
            
            <?php if ($current_shop_id && $user_role !== 'admin'): 
                $shop_stmt = $pdo->prepare("SELECT shop_name FROM shops WHERE id = ?");
                $shop_stmt->execute([$current_shop_id]);
                $shop = $shop_stmt->fetch();
            ?>
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Shop:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($shop['shop_name'] ?? 'Selected Shop') ?></Data></Cell>
            </Row>
            <?php endif; ?>
            
            <?php if ($search): ?>
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Search Filter:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($search) ?></Data></Cell>
            </Row>
            <?php endif; ?>
            
            <?php if ($category !== 'all'): ?>
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Category Filter:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($category) ?></Data></Cell>
            </Row>
            <?php endif; ?>
            
            <?php if ($payment_method !== 'all'): ?>
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Payment Method Filter:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($payment_method) ?></Data></Cell>
            </Row>
            <?php endif; ?>
            
            <?php if ($status !== 'all'): ?>
            <Row>
                <Cell ss:StyleID="2"><Data ss:Type="String">Status Filter:</Data></Cell>
                <Cell ss:StyleID="2"><Data ss:Type="String"><?= htmlspecialchars($status) ?></Data></Cell>
            </Row>
            <?php endif; ?>
            
        </Table>
        
        <!-- Worksheet Options -->
        <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
            <PageSetup>
                <Layout x:CenterHorizontal="1"/>
                <Header x:Margin="0.3"/>
                <Footer x:Margin="0.3"/>
                <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
            </PageSetup>
            <FitToPage/>
            <Print>
                <FitHeight>0</FitHeight>
                <ValidPrinterInfo/>
                <PaperSizeIndex>9</PaperSizeIndex>
                <Scale>80</Scale>
                <HorizontalResolution>600</HorizontalResolution>
                <VerticalResolution>600</VerticalResolution>
            </Print>
            <Selected/>
            <ProtectObjects>False</ProtectObjects>
            <ProtectScenarios>False</ProtectScenarios>
        </WorksheetOptions>
    </Worksheet>
</Workbook>
<?php
exit();
?>