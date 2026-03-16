<?php
session_start();
require_once 'config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$business_id = $_SESSION['business_id'] ?? 1;

// Invoice ID validation
if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id']) || $_GET['invoice_id'] <= 0) {
    die("Valid Invoice ID is required");
}
$invoice_id = (int)$_GET['invoice_id'];

// Fetch invoice with joined details
$stmt = $pdo->prepare("
    SELECT i.*,
           c.name as customer_name, c.phone as customer_phone, c.gstin as customer_gstin,
           c.address as customer_address,
           u.full_name as seller_name,
           s.shop_name, s.address as shop_address, s.phone as shop_phone, s.gstin as shop_gstin,
           s.id as shop_id,
           e.first_name as engineer_first_name, e.last_name as engineer_last_name,
           e.specialization as engineer_specialization
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN users u ON i.seller_id = u.id
    LEFT JOIN shops s ON i.shop_id = s.id
    LEFT JOIN engineers e ON i.engineer_id = e.engineer_id
    WHERE i.id = ? AND i.business_id = ?
");
$stmt->execute([$invoice_id, $business_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found or access denied");
}

// Fetch items with GST
$items_stmt = $pdo->prepare("
    SELECT ii.*,
           p.product_name, p.product_code, p.hsn_code,
           g.cgst_rate, g.sgst_rate, g.igst_rate
    FROM invoice_items ii
    JOIN products p ON ii.product_id = p.id
    LEFT JOIN gst_rates g ON p.gst_id = g.id
    WHERE ii.invoice_id = ?
    ORDER BY ii.id
");
$items_stmt->execute([$invoice_id]);
$items = $items_stmt->fetchAll();

// ----------------------------------------------------
// FETCH SHOP DETAILS FROM invoice_settings TABLE
// ----------------------------------------------------
$shop_details = null;
if (!empty($invoice['shop_id'])) {
    // Fetch shop-specific settings
    $shop_stmt = $pdo->prepare("
        SELECT * FROM invoice_settings 
        WHERE business_id = ? AND shop_id = ?
    ");
    $shop_stmt->execute([$business_id, $invoice['shop_id']]);
    $shop_details = $shop_stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$shop_details) {
    // Fallback to business-level settings (shop_id = NULL)
    $business_stmt = $pdo->prepare("
        SELECT * FROM invoice_settings 
        WHERE business_id = ? AND shop_id IS NULL
    ");
    $business_stmt->execute([$business_id]);
    $shop_details = $business_stmt->fetch(PDO::FETCH_ASSOC);
}

// Set default values if no settings found
if (!$shop_details) {
    $shop_details = [
        'company_name' => 'AS ELECTRICALS',
        'company_address' => '111-J, SALEM MAIN ROAD, DHARMAPURI - 636705',
        'company_phone' => '9943701430',
        'gst_number' => '33AKDPY5436F1Z2',
        'invoice_terms' => '1. Goods Once Sold will not be taken back or exchanged.',
        'invoice_footer' => 'Thank you for your business! Visit Again.',
        'logo_path' => null,
        'qr_code_path' => null
    ];
}

// Parse address for better formatting (if needed)
$address_lines = explode("\n", trim($shop_details['company_address'] ?? ''));
$address_line1 = $address_lines[0] ?? '';
$address_line2 = $address_lines[1] ?? '';

// ────────────────────────────────────────────────
// ESC/POS RAW COMMANDS FOR RawBT (mobile/tablet only)
// ────────────────────────────────────────────────

function esc_init()     { return "\x1B\x40"; }
function esc_bold_on()  { return "\x1B\x45\x01"; }
function esc_bold_off() { return "\x1B\x45\x00"; }
function esc_center()   { return "\x1B\x61\x01"; }
function esc_left()     { return "\x1B\x61\x00"; }
function esc_double()   { return "\x1D\x21\x11"; }
function esc_normal()   { return "\x1D\x21\x00"; }
function esc_cut()      { return "\x1D\x56\x41\x00"; }

function center($text, $w=48) {
    $text = trim($text);
    $pad = ($w - strlen($text)) / 2;
    return str_repeat(' ', max(0, (int)$pad)) . $text . "\n";
}

function left_right($l, $r, $w=48) {
    $l = substr(trim($l), 0, 24);
    $r = substr(trim($r), 0, 24);
    $sp = $w - strlen($l) - strlen($r);
    return $l . str_repeat(' ', max(1, $sp)) . $r . "\n";
}

function hr($char='-', $w=48) { return str_repeat($char, $w) . "\n"; }

// Build raw ESC/POS payload using dynamic shop details
$raw = "";
$raw .= esc_init();
$raw .= esc_center();
$raw .= esc_bold_on();
$raw .= center($shop_details['company_name'] ?? 'AS ELECTRICALS');
$raw .= esc_bold_off();

// Format address - split into multiple lines if needed
$full_address = $shop_details['company_address'] ?? '';
$address_parts = explode("\n", trim($full_address));
foreach ($address_parts as $addr_line) {
    if (!empty(trim($addr_line))) {
        $raw .= center(trim($addr_line));
    }
}

// Add phone
if (!empty($shop_details['company_phone'])) {
    $raw .= center("PH: " . $shop_details['company_phone']);
}

// Add GST
if (!empty($shop_details['gst_number'])) {
    $raw .= center("GST: " . $shop_details['gst_number']);
}

$raw .= hr('=');
$raw .= center("TAX INVOICE");
$raw .= hr('=');
$raw .= esc_left();
$raw .= left_right("Bill No:", $invoice['invoice_number'] ?? '---');
$raw .= left_right("Date:", date('d-m-Y H:i', strtotime($invoice['created_at'] ?? 'now')));
$raw .= hr('-');
$raw .= "Customer: " . ($invoice['customer_name'] ?? 'Walk-in Customer') . "\n";
if (!empty($invoice['customer_phone'])) {
    $raw .= "Ph: " . $invoice['customer_phone'] . "\n";
}
if (!empty($invoice['customer_gstin'])) {
    $raw .= "GST: " . $invoice['customer_gstin'] . "\n";
}
$raw .= hr('-');
$raw .= "Item                  Qty   Rate     Total\n";
$raw .= hr('-');

$subtotal = $total_discount = $total_gst = 0;

foreach ($items as $item) {
    $qty = $item['quantity'];
    $rate = number_format($item['unit_price'], 2, '.', '');
    $line_total = $item['unit_price'] * $qty;
    $gst_amt = ($item['cgst_amount']??0) + ($item['sgst_amount']??0) + ($item['igst_amount']??0);
    $disc = $item['discount_amount']??0;
    $final = $line_total + $gst_amt - $disc;

    $subtotal += $line_total;
    $total_discount += $disc;
    $total_gst += $gst_amt;

    $name = substr(trim($item['product_name']), 0, 22);
    $raw .= str_pad($name, 22) .
            str_pad($qty, 5, ' ', STR_PAD_LEFT) .
            str_pad($rate, 9, ' ', STR_PAD_LEFT) .
            str_pad(number_format($final, 2), 11, ' ', STR_PAD_LEFT) . "\n";
}

$raw .= hr('-');
$raw .= left_right("Subtotal", number_format($subtotal, 2));
if ($total_discount > 0) {
    $raw .= left_right("Discount", "-" . number_format($total_discount, 2));
}
if ($total_gst > 0) {
    $raw .= left_right("GST", number_format($total_gst, 2));
}
$raw .= hr('=');
$raw .= esc_double();
$raw .= left_right("TOTAL", "Rs " . number_format($invoice['total'], 2));
$raw .= esc_normal();
$raw .= hr('-');
$raw .= left_right("Payment", strtoupper($invoice['payment_method'] ?? 'CASH'));
$raw .= left_right("Status", strtoupper($invoice['payment_status'] ?? 'PAID'));
$raw .= hr('=');

// Add invoice terms if available
if (!empty($shop_details['invoice_terms'])) {
    $terms = explode("\n", trim($shop_details['invoice_terms']));
    $raw .= esc_left();
    $raw .= esc_bold_on();
    $raw .= "Terms & Conditions:\n";
    $raw .= esc_bold_off();
    foreach ($terms as $term) {
        if (!empty(trim($term))) {
            $raw .= trim($term) . "\n";
        }
    }
    $raw .= hr('-');
}

// Add footer
if (!empty($shop_details['invoice_footer'])) {
    $raw .= esc_center();
    $raw .= $shop_details['invoice_footer'] . "\n";
} else {
    $raw .= esc_center();
    $raw .= "THANK YOU VISIT AGAIN\n";
}

$raw .= center("Printed: " . date('d-m-Y H:i'));
$raw .= "\n\n\n\n";
$raw .= esc_cut();

$base64 = base64_encode($raw);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=576, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($invoice['invoice_number'] ?? $invoice_id); ?></title>

    <!-- Roboto Mono Thin (200) + fallback -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@200;400&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            width: 80mm;
            font-family: 'Roboto Mono', 'Courier New', Courier, monospace;
            font-weight: 400;
            font-size: 13px;
            color: #000;
            background: #fff;
            line-height: 1.3;
        }
        .receipt {
            width: 76mm;
            padding: 2mm;
            margin: 0 auto;
        }
        .center { text-align: center; }
        .left   { text-align: left; }
        .right  { text-align: right; }
        .row {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin: 1px 0;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .title      { font-size: 18px; font-weight: 400; }
        .subtitle   { font-size: 14px; font-weight: 400; }
        .normal     { font-size: 13px; }
        .small      { font-size: 12px; }
        .total-big  { font-size: 18px; font-weight: 400; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            font-size: 13px;
            font-weight: 400;
            border-bottom: 1px solid #000;
            padding: 3px 0;
        }
        td {
            font-size: 13px;
            padding: 2px 0;
        }
        .col-item  { width: 44%; }
        .col-qty   { width: 14%; text-align: center; }
        .col-rate  { width: 20%; text-align: right; }
        .col-total { width: 22%; text-align: right; }
        #mobilePrint {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        #mobilePrint button {
            font-size: 22px;
            padding: 15px 40px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: system-ui, sans-serif;
        }
        #desktopNote {
            display: none;
            color: #555;
            font-size: 14px;
            margin: 15px 0;
            text-align: center;
        }
        @media print {
            body { width: 80mm; }
            #mobilePrint, #desktopNote { display: none !important; }
        }
    </style>
</head>
<body>

<div class="receipt">
    <?php if (!empty($shop_details['logo_path']) && file_exists($shop_details['logo_path'])): ?>
    <div class="center">
        <img src="<?php echo htmlspecialchars($shop_details['logo_path']); ?>" alt="Logo" style="max-width: 50mm; max-height: 15mm;">
    </div>
    <?php endif; ?>
    <!-- Company Header - Dynamically loaded from invoice_settings -->
    <div class="center title"><?php echo htmlspecialchars($shop_details['company_name'] ?? 'AS ELECTRICALS'); ?></div>
    <div class="center small">
        <?php 
        $address = htmlspecialchars($shop_details['company_address'] ?? '');
        $address_lines = explode("\n", $address);
        foreach ($address_lines as $line) {
            if (!empty(trim($line))) {
                echo trim($line) . "<br>";
            }
        }
        ?>
        <?php if (!empty($shop_details['company_phone'])): ?>
            PH: <?php echo htmlspecialchars($shop_details['company_phone']); ?><br>
        <?php endif; ?>
        <?php if (!empty($shop_details['company_email'])): ?>
            Email: <?php echo htmlspecialchars($shop_details['company_email']); ?><br>
        <?php endif; ?>
        <?php if (!empty($shop_details['gst_number'])): ?>
            GST: <?php echo htmlspecialchars($shop_details['gst_number']); ?>
        <?php endif; ?>
    </div>
    
    
    
    <div class="divider"></div>
    <div class="center subtitle">TAX INVOICE</div>
    <div class="divider"></div>

    <!-- Invoice Info -->
    <div class="row normal"><span><b>Bill No:</b></span><span><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></div>
    <div class="row normal"><span><b>Date:</b></span><span><?php echo date('d-m-Y H:i', strtotime($invoice['created_at'])); ?></span></div>
    <div class="divider"></div>

    <!-- Customer -->
    <div class="normal"><b>Customer:</b></div>
    <div class="small"><?php echo htmlspecialchars($invoice['customer_name'] ?? 'Walk-in Customer'); ?></div>
    <?php if (!empty($invoice['customer_phone'])): ?>
        <div class="small">Ph: <?php echo htmlspecialchars($invoice['customer_phone']); ?></div>
    <?php endif; ?>
    <?php if (!empty($invoice['customer_gstin'])): ?>
        <div class="small">GST: <?php echo htmlspecialchars($invoice['customer_gstin']); ?></div>
    <?php endif; ?>
    <div class="divider"></div>

    <!-- Items Table -->
    <table>
        <tr>
            <th class="col-item">Item</th>
            <th class="col-qty">Qty</th>
            <th class="col-rate">Rate</th>
            <th class="col-total">Total</th>
        </tr>
        <?php
        $subtotal = $total_discount = $total_gst = 0;
        foreach ($items as $item):
            $line_total = $item['unit_price'] * $item['quantity'];
            $gst = ($item['cgst_amount']??0) + ($item['sgst_amount']??0) + ($item['igst_amount']??0);
            $discount = $item['discount_amount']??0;
            $final = $line_total + $gst - $discount;
            $subtotal += $line_total;
            $total_discount += $discount;
            $total_gst += $gst;
        ?>
        <tr>
            <td class="col-item"><?php echo htmlspecialchars(substr($item['product_name'], 0, 22)); ?></td>
            <td class="col-qty"><?php echo $item['quantity']; ?></td>
            <td class="col-rate"><?php echo number_format($item['unit_price']); ?></td>
            <td class="col-total"><?php echo number_format($final); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="divider"></div>

    <!-- Totals -->
    <div class="row normal"><span>Subtotal</span><span><?php echo number_format($subtotal, 2); ?></span></div>
    <?php if ($total_discount > 0): ?>
        <div class="row normal"><span>Discount</span><span>-<?php echo number_format($total_discount, 2); ?></span></div>
    <?php endif; ?>
    <?php if ($total_gst > 0): ?>
        <div class="row normal"><span>GST</span><span><?php echo number_format($total_gst, 2); ?></span></div>
    <?php endif; ?>
    <div class="divider"></div>
    <div class="row total-big"><span>TOTAL</span><span>₹<?php echo number_format($invoice['total'], 2); ?></span></div>
    <div class="divider"></div>

    <!-- Payment & Status -->
    <div class="row normal"><span>Payment</span><span><?php echo strtoupper($invoice['payment_method'] ?? 'CASH'); ?></span></div>
    <div class="row normal"><span>Status</span><span><?php echo strtoupper($invoice['payment_status'] ?? 'PAID'); ?></span></div>
    <div class="divider"></div>

    <!-- Footer from invoice_settings -->
    <div class="center normal">
        <b><?php echo htmlspecialchars($shop_details['invoice_footer'] ?? 'THANK YOU VISIT AGAIN'); ?></b>
    </div>
    <div class="center small">Printed: <?php echo date('d-m-Y H:i'); ?></div>
    
    <!--<?php if (!empty($shop_details['qr_code_path']) && file_exists($shop_details['qr_code_path'])): ?>-->
    <!--<div class="center">-->
    <!--    <img src="<?php echo htmlspecialchars($shop_details['qr_code_path']); ?>" alt="QR Code" style="max-width: 40mm; max-height: 40mm;">-->
    <!--</div>-->
    <!--<?php endif; ?>-->
</div>

<!-- Mobile-only RawBT section -->
<div id="mobilePrint">
    <button onclick="printWithRawBT()">PRINT VIA RawBT</button>
    <p style="margin-top:15px; font-size:14px; color:#555;">
        RawBT must be installed • Printer paired • Bluetooth ON
    </p>
</div>

<div id="desktopNote">
    <p>Use Ctrl+P (or browser print menu) to print to your thermal printer.</p>
</div>

<script>
// ────────────────────────────────────────────────
// Device detection
// ────────────────────────────────────────────────
function isMobileOrTablet() {
    const ua = (navigator.userAgent || navigator.vendor || window.opera || '').toLowerCase();
    const isMobileUA = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobi|tablet|silk|kindle|playbook/i.test(ua);
    const hasTouch = 'maxTouchPoints' in navigator && navigator.maxTouchPoints > 1 ||
                     ('msMaxTouchPoints' in navigator && navigator.msMaxTouchPoints > 1);
    const smallScreen = window.innerWidth <= 1024;
    return isMobileUA || (hasTouch && smallScreen);
}

const isMobile = isMobileOrTablet();

// Show correct UI
if (isMobile) {
    document.getElementById('mobilePrint').style.display = 'block';
} else {
    document.getElementById('desktopNote').style.display = 'block';
    window.onload = function() {
        if (!isMobile) window.print();
    };
}

function printWithRawBT() {
    const uri = 'rawbt:base64,<?php echo $base64; ?>';
    window.location.href = uri;
}

// Auto-redirect back to invoices list after 8 seconds
setTimeout(() => {
    window.location.href = 'invoices.php';
}, 8000);
</script>

</body>
</html>