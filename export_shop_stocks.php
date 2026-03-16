<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

$user_role = $_SESSION['role'] ?? '';
$business_id = $_SESSION['business_id'] ?? 1;
$current_shop_id = $_SESSION['current_shop_id'] ?? null;
$is_admin = ($user_role === 'admin');

// Force collation
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

// === FILTERS & SEARCH (same as shop_stocks.php) ===
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? 'all';
$selected_shop_id = $_GET['shop_id'] ?? ($is_admin ? 'all' : $current_shop_id);

// Build WHERE conditions
$where = "WHERE p.business_id = $business_id AND p.is_active = 1";
$params = [];

// Shop condition
if ($selected_shop_id !== 'all') {
    $shop_condition = "AND ps.shop_id = " . (int)$selected_shop_id;
} else {
    $shop_condition = "";
}

if ($search !== '') {
    $where .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR p.hsn_code LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($category !== '') {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
}

// Main query for export (no pagination)
if ($selected_shop_id !== 'all') {
    $sql = "
        SELECT 
            p.id,
            p.product_name, 
            p.product_code, 
            p.barcode, 
            p.retail_price, 
            p.wholesale_price,
            p.stock_price,
            p.min_stock_level, 
            p.hsn_code,
            p.description,
            c.category_name,
            CONCAT(COALESCE(gr.cgst_rate + gr.sgst_rate + gr.igst_rate, 0), '%') AS tax_rate,
            COALESCE(ps.quantity, 0) AS current_stock,
            s.shop_name,
            s.id as shop_id,
            (COALESCE(ps.quantity, 0) * p.stock_price) AS stock_value
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN gst_rates gr ON p.gst_id = gr.id
        LEFT JOIN product_stocks ps ON ps.product_id = p.id AND ps.shop_id = ?
        LEFT JOIN shops s ON ps.shop_id = s.id
        $where
        ORDER BY p.product_name
    ";
    $main_params = array_merge([(int)$selected_shop_id], $params);
} else {
    $sql = "
        SELECT 
            p.id,
            p.product_name, 
            p.product_code, 
            p.barcode, 
            p.retail_price, 
            p.wholesale_price,
            p.stock_price,
            p.min_stock_level, 
            p.hsn_code,
            p.description,
            c.category_name,
            CONCAT(COALESCE(gr.cgst_rate + gr.sgst_rate + gr.igst_rate, 0), '%') AS tax_rate,
            COALESCE(ps_total.total_qty, 0) AS current_stock,
            'All Shops' as shop_name,
            '' as shop_id,
            COALESCE(ps_total.total_value, 0) AS stock_value,
            GROUP_CONCAT(DISTINCT CONCAT(s.shop_name, ':', COALESCE(ps.quantity, 0)) SEPARATOR ' | ') as shop_stocks_detail
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN gst_rates gr ON p.gst_id = gr.id
        LEFT JOIN product_stocks ps ON ps.product_id = p.id
        LEFT JOIN shops s ON ps.shop_id = s.id
        LEFT JOIN (
            SELECT 
                product_id, 
                SUM(quantity) as total_qty,
                SUM(quantity * stock_price) as total_value
            FROM product_stocks ps2
            LEFT JOIN products p2 ON ps2.product_id = p2.id
            GROUP BY product_id
        ) ps_total ON p.id = ps_total.product_id
        $where
        GROUP BY p.id
        ORDER BY p.product_name
    ";
    $main_params = $params;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($main_params);
$products = $stmt->fetchAll();

// Apply stock filter after fetching (same logic as shop_stocks.php)
if ($stock_filter !== 'all') {
    $filtered_products = [];
    foreach ($products as $p) {
        $current_stock = $p['current_stock'];
        $min_stock = $p['min_stock_level'] ?: 10;
        
        if ($stock_filter === 'in' && $current_stock >= $min_stock) {
            $filtered_products[] = $p;
        } elseif ($stock_filter === 'low' && $current_stock > 0 && $current_stock < $min_stock) {
            $filtered_products[] = $p;
        } elseif ($stock_filter === 'out' && $current_stock == 0) {
            $filtered_products[] = $p;
        } elseif ($stock_filter === 'critical' && $current_stock < ceil($min_stock * 0.25)) {
            $filtered_products[] = $p;
        }
    }
    $products = $filtered_products;
}

// Get shop name for filename
$shop_name = 'All_Shops';
if ($selected_shop_id !== 'all') {
    $stmt = $pdo->prepare("SELECT shop_name FROM shops WHERE id = ?");
    $stmt->execute([$selected_shop_id]);
    $shop = $stmt->fetch();
    $shop_name = $shop ? preg_replace('/[^a-zA-Z0-9]/', '_', $shop['shop_name']) : 'Shop_' . $selected_shop_id;
}

// Set filename
$filename = 'Stock_Report_' . $shop_name . '_' . date('Y-m-d_H-i-s') . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Create Excel/HTML table
echo '<html>';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; }';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #4CAF50; color: white; font-weight: bold; padding: 8px; text-align: center; }';
echo 'td { border: 1px solid #ddd; padding: 6px; }';
echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
echo '.in-stock { color: green; font-weight: bold; }';
echo '.low-stock { color: orange; font-weight: bold; }';
echo '.out-stock { color: red; font-weight: bold; }';
echo '.critical { color: darkred; font-weight: bold; }';
echo '</style>';
echo '</head>';
echo '<body>';

// Report Header
echo '<h2>Stock Report - ' . htmlspecialchars($shop_name) . '</h2>';
echo '<p>Generated on: ' . date('d-m-Y H:i:s') . '</p>';

// Filter Information
echo '<p><strong>Filters Applied:</strong> ';
$filter_info = [];
if ($search) $filter_info[] = 'Search: ' . $search;
if ($category) {
    $cat_stmt = $pdo->prepare("SELECT category_name FROM categories WHERE id = ?");
    $cat_stmt->execute([$category]);
    $cat_name = $cat_stmt->fetchColumn();
    $filter_info[] = 'Category: ' . ($cat_name ?: $category);
}
if ($stock_filter !== 'all') {
    $filter_labels = ['in' => 'In Stock', 'low' => 'Low Stock', 'out' => 'Out of Stock', 'critical' => 'Critical Stock'];
    $filter_info[] = 'Stock Status: ' . ($filter_labels[$stock_filter] ?? $stock_filter);
}
echo $filter_info ? implode(' | ', $filter_info) : 'None';
echo '</p>';

// Summary Statistics
$total_products = count($products);
$total_stock_value = 0;
$total_retail_value = 0;
$low_stock_count = 0;
$out_stock_count = 0;
$critical_count = 0;

foreach ($products as $p) {
    $stock = $p['current_stock'];
    $min_stock = $p['min_stock_level'] ?: 10;
    $total_stock_value += $p['stock_value'] ?? 0;
    $total_retail_value += $stock * $p['retail_price'];
    
    if ($stock == 0) {
        $out_stock_count++;
    } elseif ($stock < ceil($min_stock * 0.25)) {
        $critical_count++;
    } elseif ($stock < $min_stock) {
        $low_stock_count++;
    }
}

echo '<table style="margin-bottom: 20px; background-color: #e8f5e8;">';
echo '<tr><th colspan="2">Summary Statistics</th></tr>';
echo '<tr><td><strong>Total Products:</strong></td><td>' . number_format($total_products) . '</td></tr>';
echo '<tr><td><strong>Total Stock Value (Cost):</strong></td><td>₹' . number_format($total_stock_value, 2) . '</td></tr>';
echo '<tr><td><strong>Total Retail Value:</strong></td><td>₹' . number_format($total_retail_value, 2) . '</td></tr>';
echo '<tr><td><strong>Out of Stock:</strong></td><td>' . number_format($out_stock_count) . '</td></tr>';
echo '<tr><td><strong>Critical Stock:</strong></td><td>' . number_format($critical_count) . '</td></tr>';
echo '<tr><td><strong>Low Stock:</strong></td><td>' . number_format($low_stock_count) . '</td></tr>';
echo '</table>';

// Main Stock Table
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>S.No</th>';
echo '<th>Product Code</th>';
echo '<th>Barcode</th>';
echo '<th>Product Name</th>';
echo '<th>Category</th>';
echo '<th>HSN Code</th>';
echo '<th>GST Rate</th>';
echo '<th>Current Stock</th>';
echo '<th>Min Stock</th>';
echo '<th>Stock Status</th>';
echo '<th>Stock %</th>';
echo '<th>Stock Value (Cost)</th>';
echo '<th>Retail Price</th>';
echo '<th>Wholesale Price</th>';
echo '<th>Cost Price</th>';
echo '<th>Retail Value</th>';

if ($selected_shop_id === 'all') {
    echo '<th>Shop-wise Stock</th>';
} else {
    echo '<th>Shop</th>';
}

echo '</tr>';
echo '</thead>';
echo '<tbody>';

$counter = 1;
foreach ($products as $p) {
    $stock = $p['current_stock'];
    $min_stock = $p['min_stock_level'] ?: 10;
    $stock_percentage = $min_stock > 0 ? ($stock / $min_stock) * 100 : 0;
    $retail_value = $stock * $p['retail_price'];
    $stock_value = $p['stock_value'] ?? ($stock * ($p['stock_price'] ?: 0));
    
    // Determine stock status
    if ($stock == 0) {
        $stock_class = 'out-stock';
        $stock_status = 'Out of Stock';
    } elseif ($stock_percentage < 25) {
        $stock_class = 'critical';
        $stock_status = 'Critical';
    } elseif ($stock_percentage < 50) {
        $stock_class = 'low-stock';
        $stock_status = 'Low';
    } elseif ($stock_percentage < 100) {
        $stock_class = 'low-stock';
        $stock_status = 'Below Min';
    } else {
        $stock_class = 'in-stock';
        $stock_status = 'In Stock';
    }
    
    echo '<tr>';
    echo '<td align="center">' . $counter++ . '</td>';
    echo '<td>' . htmlspecialchars($p['product_code']) . '</td>';
    echo '<td>' . htmlspecialchars($p['barcode'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($p['product_name']) . '</td>';
    echo '<td>' . htmlspecialchars($p['category_name'] ?? 'Uncategorized') . '</td>';
    echo '<td>' . htmlspecialchars($p['hsn_code'] ?? '') . '</td>';
    echo '<td align="center">' . htmlspecialchars($p['tax_rate'] ?? '0%') . '</td>';
    echo '<td align="center" class="' . $stock_class . '">' . number_format($stock) . '</td>';
    echo '<td align="center">' . number_format($min_stock) . '</td>';
    echo '<td align="center" class="' . $stock_class . '">' . $stock_status . '</td>';
    echo '<td align="center">' . number_format($stock_percentage, 1) . '%</td>';
    echo '<td align="right">₹' . number_format($stock_value, 2) . '</td>';
    echo '<td align="right">₹' . number_format($p['retail_price'], 2) . '</td>';
    echo '<td align="right">₹' . number_format($p['wholesale_price'] ?? 0, 2) . '</td>';
    echo '<td align="right">₹' . number_format($p['stock_price'] ?? 0, 2) . '</td>';
    echo '<td align="right">₹' . number_format($retail_value, 2) . '</td>';
    
    if ($selected_shop_id === 'all') {
        echo '<td>' . htmlspecialchars($p['shop_stocks_detail'] ?? 'No stock in any shop') . '</td>';
    } else {
        echo '<td>' . htmlspecialchars($p['shop_name'] ?? 'No Shop') . '</td>';
    }
    
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Footer
echo '<p style="margin-top: 20px; font-size: 12px; color: #666;">';
echo 'Generated by Stock Management System | Total Products: ' . number_format($total_products);
echo '</p>';

echo '</body>';
echo '</html>';
?>