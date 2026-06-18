<?php 
session_start();
include 'db.php'; 

// Fetch dynamic dropdown data
$categories = [];
$res_cat = $conn->query("SELECT name FROM config_product_categories ORDER BY name ASC");
if($res_cat) { while($r = $res_cat->fetch_assoc()) { $categories[] = trim($r['name']); } }

$unit_types = [];
$res_units = $conn->query("SELECT name FROM config_unit_types ORDER BY name ASC");
if($res_units) { while($r = $res_units->fetch_assoc()) { $unit_types[] = trim($r['name']); } }

$today = date('Y-m-d');

function inventoryReportDate($value, $fallback, $today) {
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if ($date === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return $fallback;
    }

    $normalized = $date->format('Y-m-d');
    return ($normalized > $today) ? $today : $normalized;
}

function inventoryReportDateLabel($date) {
    return date('F d, Y', strtotime($date));
}

$report_date = inventoryReportDate($_GET['report_date'] ?? ($_GET['report_to'] ?? ($_GET['report_from'] ?? $today)), $today, $today);
$report_from = $report_date;
$report_to = $report_date;
$is_past_date = ($report_date < $today);

$sort_options = [
    'alpha' => 'Product Name (A-Z)',
    'qty_asc' => 'Quantity (Lowest to Highest)',
    'qty_desc' => 'Quantity (Highest to Lowest)'
];
$report_sort = $_GET['sort'] ?? 'alpha';
if (!isset($sort_options[$report_sort])) {
    $report_sort = 'alpha';
}

// Daily snapshots ensure historical dates remain accessible later.
$conn->query("CREATE TABLE IF NOT EXISTS inventory_daily_snapshot (
    snapshot_date DATE NOT NULL,
    product_id INT(11) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_type VARCHAR(100) NOT NULL,
    quantity_type VARCHAR(100) NOT NULL,
    quantity INT(11) NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (snapshot_date, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Process POST Actions Cleanly Separated (blocked for past dates)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($is_past_date) {
        $_SESSION['alert_title'] = "View Only Mode";
        $_SESSION['alert_message'] = "Inventory for past dates is read-only. Switch to today's date to make changes.";
        $_SESSION['alert_type'] = "info";
        header("Location: inventory.php?report_date=" . urlencode($report_date) . "&sort=" . urlencode($report_sort));
        exit();
    }

    // ACTION 1: DELETE PRODUCT
    if (isset($_POST['delete_product_id'])) {
        $del_id = (int)$_POST['delete_product_id'];
        $name_row = $conn->query("SELECT product_id, product_name, product_type, quantity_type, current_quantity, price FROM inventory WHERE product_id = $del_id LIMIT 1");
        $deleted_product = $name_row ? $name_row->fetch_assoc() : null;
        $conn->query("DELETE FROM inventory WHERE product_id=$del_id");
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'INVENTORY',
                'DELETE PRODUCT',
                'PRODUCT',
                $del_id,
                $deleted_product['product_name'] ?? '',
                'JSON:' . json_encode([
                    'table' => 'inventory',
                    'operation' => 'delete',
                    'before' => $deleted_product,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        $_SESSION['alert_title'] = "Item Deleted";
        $_SESSION['alert_message'] = "The product has been permanently removed from the master inventory.";
        $_SESSION['alert_type'] = "success";
        header("Location: inventory.php");
        exit();
    }

    // ACTION 2: ADJUST STOCK LEVEL
    if (isset($_POST['adjust_stock_id'])) {
        $adj_id = (int)$_POST['adjust_stock_id'];
        $adj_amount = (int)$_POST['adjust_amount'];
        $name_row = $conn->query("SELECT product_name, current_quantity FROM inventory WHERE product_id = $adj_id LIMIT 1");
        $product_row = $name_row ? $name_row->fetch_assoc() : null;
        $conn->query("UPDATE inventory SET current_quantity = current_quantity + $adj_amount WHERE product_id=$adj_id");
        if (function_exists('logActivity')) {
            $before_qty = isset($product_row['current_quantity']) ? (int)$product_row['current_quantity'] : null;
            $after_qty = ($before_qty !== null) ? ($before_qty + $adj_amount) : null;
            logActivity(
                $conn,
                'INVENTORY',
                'ADJUST STOCK',
                'PRODUCT',
                $adj_id,
                $product_row['product_name'] ?? '',
                'JSON:' . json_encode([
                    'table' => 'inventory',
                    'operation' => 'adjust',
                    'before' => [
                        'current_quantity' => $before_qty,
                    ],
                    'after' => [
                        'current_quantity' => $after_qty,
                    ],
                    'delta' => $adj_amount,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        $_SESSION['alert_title'] = "Stock Adjusted";
        $_SESSION['alert_message'] = "The inventory levels have been updated successfully.";
        $_SESSION['alert_type'] = "success";
        header("Location: inventory.php");
        exit();
    }

    // ACTION 3: ADD OR EDIT PRODUCT DETAILS
    if (isset($_POST['product_name'])) {
        $product_name = $conn->real_escape_string(trim($_POST['product_name']));
        $product_type = $conn->real_escape_string(trim($_POST['product_type']));
        $quantity_type = $conn->real_escape_string(trim($_POST['quantity_type']));
        $price = (float)$_POST['price'];

        if (!empty($_POST['product_id'])) {
            // Edit existing product
            $id = (int)$_POST['product_id'];
            $before_row_res = $conn->query("SELECT product_id, product_name, product_type, quantity_type, current_quantity, price FROM inventory WHERE product_id = $id LIMIT 1");
            $before_product_row = $before_row_res ? $before_row_res->fetch_assoc() : null;
            $sql = "UPDATE inventory SET product_name='$product_name', product_type='$product_type', quantity_type='$quantity_type', price='$price' WHERE product_id=$id";
            $conn->query($sql);
            if (function_exists('logActivity')) {
                logActivity(
                    $conn,
                    'INVENTORY',
                    'UPDATE PRODUCT',
                    'PRODUCT',
                    $id,
                    $product_name,
                    'JSON:' . json_encode([
                        'table' => 'inventory',
                        'operation' => 'update',
                        'before' => $before_product_row,
                        'after' => [
                            'product_name' => $product_name,
                            'product_type' => $product_type,
                            'quantity_type' => $quantity_type,
                            'price' => $price,
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
            $_SESSION['alert_title'] = "Item Updated";
            $_SESSION['alert_message'] = "The product information has been successfully updated.";
            $_SESSION['alert_type'] = "success";
        } else {
            // Add completely new product
            $current_quantity = (int)$_POST['current_quantity'];
            $sql = "INSERT INTO inventory (product_name, product_type, quantity_type, current_quantity, price)
                    VALUES ('$product_name', '$product_type', '$quantity_type', $current_quantity, '$price')";
            $conn->query($sql);
            if (function_exists('logActivity')) {
                logActivity(
                    $conn,
                    'INVENTORY',
                    'ADD PRODUCT',
                    'PRODUCT',
                    $conn->insert_id,
                    $product_name,
                    'JSON:' . json_encode([
                        'table' => 'inventory',
                        'operation' => 'add',
                        'after' => [
                            'product_name' => $product_name,
                            'product_type' => $product_type,
                            'quantity_type' => $quantity_type,
                            'current_quantity' => $current_quantity,
                            'price' => $price,
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
            $_SESSION['alert_title'] = "Item Added";
            $_SESSION['alert_message'] = "A new product has been successfully added to the master inventory.";
            $_SESSION['alert_type'] = "success";
        }
        header("Location: inventory.php");
        exit();
    }
}

if ($report_sort === 'qty_asc') {
    $order_by_live = "product_type ASC, current_quantity ASC, product_name ASC";
    $order_by_snap = "product_type ASC, quantity ASC, product_name ASC";
} elseif ($report_sort === 'qty_desc') {
    $order_by_live = "product_type ASC, current_quantity DESC, product_name ASC";
    $order_by_snap = "product_type ASC, quantity DESC, product_name ASC";
} else {
    $order_by_live = "product_type ASC, product_name ASC";
    $order_by_snap = "product_type ASC, product_name ASC";
}

$inventory_rows = [];
$snapshot_missing = false;
if ($is_past_date) {
    $check = $conn->query("SELECT COUNT(*) as c FROM inventory_daily_snapshot WHERE snapshot_date = '" . $conn->real_escape_string($report_date) . "'");
    $count = ($check && ($r = $check->fetch_assoc())) ? (int)$r['c'] : 0;
    if ($count <= 0) {
        $snapshot_missing = true;
    } else {
        $inventory_result = $conn->query("SELECT product_id, product_name, product_type, quantity_type, quantity AS current_quantity, price FROM inventory_daily_snapshot WHERE snapshot_date = '" . $conn->real_escape_string($report_date) . "' ORDER BY $order_by_snap");
    }
} else {
    // Live inventory
    $inventory_result = $conn->query("SELECT * FROM inventory ORDER BY $order_by_live");

    // Save today's snapshot automatically so historical reports remain available later.
    $snap_date = $conn->real_escape_string($report_date);
    $conn->query("INSERT INTO inventory_daily_snapshot (snapshot_date, product_id, product_name, product_type, quantity_type, quantity, price)
                  SELECT '$snap_date', product_id, product_name, product_type, quantity_type, current_quantity, price FROM inventory
                  ON DUPLICATE KEY UPDATE
                    product_name = VALUES(product_name),
                    product_type = VALUES(product_type),
                    quantity_type = VALUES(quantity_type),
                    quantity = VALUES(quantity),
                    price = VALUES(price)");
}
if ($inventory_result && $inventory_result->num_rows > 0) {
    while ($row = $inventory_result->fetch_assoc()) {
        $inventory_rows[] = $row;
    }
}

$stat_total = count($inventory_rows);
$stat_low = 0;
$stat_val = 0;
$report_total_qty = 0;
foreach ($inventory_rows as $row) {
    $qty = (int)$row['current_quantity'];
    $price = (float)$row['price'];
    if ($qty < 5) {
        $stat_low++;
    }
    if ($qty > 0) {
        $stat_val += $qty * $price;
    }
    $report_total_qty += $qty;
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Daily Inventory Report');

    $report_date_label = inventoryReportDateLabel($report_date);

    $sheet->mergeCells('A1:G1');
    $sheet->mergeCells('A2:G2');
    $sheet->setCellValue('A1', 'Daily Inventory Report');
    $sheet->setCellValue('A2', 'Date: ' . $report_date_label);

    $sheet->getStyle('A:G')->getFont()->setName('Arial')->setSize(12);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $row_num = 4;
    $current_category = null;

    foreach ($inventory_rows as $row) {
        if ($current_category !== $row['product_type']) {
            $current_category = $row['product_type'];
            $sheet->mergeCells("A{$row_num}:G{$row_num}");
            $sheet->setCellValue("A{$row_num}", strtoupper($current_category));
            $sheet->getStyle("A{$row_num}:G{$row_num}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row_num}:G{$row_num}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFE7F7');
            $row_num++;

            $sheet->fromArray(['Product Name', 'Category', 'Unit', 'Quantity', 'Status', 'Price', 'Inventory Value'], null, "A{$row_num}");
            $sheet->getStyle("A{$row_num}:G{$row_num}")->getFont()->setBold(true);
            $row_num++;
        }

        $qty = (int)$row['current_quantity'];
        $price = (float)$row['price'];
        $status = ($qty <= 0) ? 'OUT OF STOCK' : (($qty < 5) ? 'LOW STOCK' : 'IN STOCK');
        $sheet->setCellValue("A{$row_num}", $row['product_name']);
        $sheet->setCellValue("B{$row_num}", $row['product_type']);
        $sheet->setCellValue("C{$row_num}", $row['quantity_type']);
        $sheet->setCellValue("D{$row_num}", $qty);
        $sheet->setCellValue("E{$row_num}", $status);
        $sheet->setCellValue("F{$row_num}", $price);
        $sheet->setCellValue("G{$row_num}", $qty * $price);
        $row_num++;
    }

    $row_num++;
    $sheet->setCellValue("C{$row_num}", 'Total Quantity');
    $sheet->setCellValue("D{$row_num}", $report_total_qty);
    $sheet->setCellValue("F{$row_num}", 'Total Value');
    $sheet->setCellValue("G{$row_num}", $stat_val);
    $sheet->getStyle("C{$row_num}:G{$row_num}")->getFont()->setBold(true);

    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    $sheet->getStyle('F:G')->getNumberFormat()->setFormatCode('#,##0.00');

    $filename = 'Daily_Inventory_Report_' . $report_date . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Coop DBMS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    colors: { primary: '#6a1b9a', primaryDark: '#570591', }
                }
            }
        }
    </script>
    <style>
        .report-print-header { display: none; }
        #productModal input[type="text"],
        #productModal select,
        #productModal option,
        #inventoryReportTable td,
        #inventoryReportTable .product-name-cell,
        #inventoryReportTable .category-header td,
        #products-container .product-card h4,
        #products-container .product-card .text-xs.text-gray-600 {
            text-transform: uppercase;
        }
        @media print {
            @page {
                margin: 14mm;
            }
            html,
            body {
                background: #ffffff !important;
                overflow: visible !important;
                height: auto !important;
                font-family: Arial, sans-serif !important;
                font-size: 12px !important;
            }
            .print\:hidden {
                display: none !important;
            }
            .h-screen,
            .overflow-hidden,
            .overflow-y-auto,
            .overflow-x-auto {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
            main,
            main > div {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
                width: 100% !important;
                padding: 0 !important;
            }
            .report-print-header {
                display: block !important;
                margin-bottom: 12px;
                color: #111827;
                text-align: center;
                font-family: Arial, sans-serif !important;
            }
            .report-print-title {
                font-size: 20px !important;
                font-weight: 700;
                margin: 0 0 12px 0;
            }
            .report-print-date {
                font-size: 15px !important;
                font-weight: 300;
                margin: 0 0 5px 0;
            }
            #inventoryReportCard {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }
            #inventoryReportTable {
                width: 100% !important;
                border-collapse: collapse !important;
                white-space: normal !important;
                font-family: Arial, sans-serif !important;
                font-size: 12px !important;
            }
            #inventoryReportTable th,
            #inventoryReportTable td {
                border: 1px solid #d1d5db !important;
                padding: 5px 6px !important;
                font-size: 12px !important;
            }
            #inventoryReportTable thead th {
                background: #f3f4f6 !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #inventoryReportTable .category-header td {
                background: #e5e7eb !important;
                color: #111827 !important;
                border-color: #9ca3af !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #inventoryReportTable .inventory-row {
                background: #ffffff !important;
            }
            .stock-badge-print {
                background: transparent !important;
                border: none !important;
                color: #111827 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-hidden">
    
    <?php include 'cover_page.php'; ?>

    <div id="customAlertModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="customAlertBox">
            <div id="customAlertHeader" class="px-6 py-4 flex items-center gap-3 border-b">
                <i id="customAlertIcon" class="fas fa-exclamation-circle text-2xl"></i>
                <h3 id="customAlertTitle" class="text-lg font-bold tracking-tight">Alert</h3>
            </div>
            <div class="p-6 text-gray-600 text-sm leading-relaxed" id="customAlertMessage"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button id="customAlertBtn" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">OK</button>
            </div>
        </div>
    </div>

    <div id="adjustModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeAdjustModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden transform transition-all">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800"><i class="fas fa-boxes text-primary mr-2"></i>Adjust Stock Level</h3>
                <button onclick="closeAdjustModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="inventory.php" method="POST" class="p-6">
                <input type="hidden" name="adjust_stock_id" id="adj_product_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1" id="adj_product_name_display"></label>
                    <div class="text-xs text-gray-500 mb-4">Current Stock: <span id="adj_current_stock" class="font-bold text-gray-800"></span></div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adjustment Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="adjust_amount" placeholder="e.g. 5 or -3" required class="w-full rounded-md border border-gray-300 px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                    <p class="text-xs text-gray-400 mt-1 italic">Use a positive number to add stock, and a negative number (e.g. -5) to subtract.</p>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeAdjustModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md">UPDATE STOCK</button>
                </div>
            </form>
        </div>
    </div>

    <div id="productModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg z-10 overflow-hidden transform transition-all">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800" id="modalTitle"><i class="fas fa-plus-circle text-primary mr-2"></i>Add New Product</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            
            <form action="inventory.php" method="POST" class="p-6">
                <input type="hidden" name="product_id" id="product_id">
                
                <div class="grid grid-cols-1 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                        <input type="text" name="product_name" id="product_name" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category / Type <span class="text-red-500">*</span></label>
                            <select name="product_type" id="product_type" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all bg-white">
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure <span class="text-red-500">*</span></label>
                            <select name="quantity_type" id="quantity_type" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all bg-white">
                                <option value="">Select Unit</option>
                                <?php foreach($unit_types as $ut): ?>
                                    <option value="<?= htmlspecialchars($ut) ?>"><?= htmlspecialchars($ut) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (PHP) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="price" id="price" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                        <div id="initial_qty_group">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Initial Stock <span class="text-red-500">*</span></label>
                            <input type="number" name="current_quantity" id="current_quantity" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <button type="button" onclick="closeModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md"><i class="fas fa-save mr-1"></i> SAVE PRODUCT</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden transform transition-all">
            <div class="bg-red-50 px-6 py-4 border-b border-red-100 flex justify-between items-center">
                <h3 class="font-bold text-red-700"><i class="fas fa-trash-alt mr-2"></i>Delete Product</h3>
                <button type="button" onclick="closeDeleteModal()" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6">
                <div class="text-gray-700 font-medium" id="deleteProductName">Are you sure?</div>
                <p class="text-sm text-gray-500 mt-2 leading-relaxed">This will permanently remove the product from the master inventory. This action cannot be undone.</p>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeDeleteModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                <form id="deleteProductForm" action="inventory.php" method="POST" class="m-0">
                    <input type="hidden" name="delete_product_id" id="deleteProductId">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md text-sm transition-colors shadow-md">
                        DELETE
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="flex h-screen w-full">

        <div id="mobile-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden transition-opacity print:hidden" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="bg-white w-72 border-r border-gray-200 flex flex-col transition-transform transform -translate-x-full md:translate-x-0 fixed md:relative z-50 h-full shadow-lg md:shadow-none print:hidden">
            <div class="p-6 flex items-center justify-center border-b border-gray-100 relative">
                <a href="#" onclick="showSplashScreen(); return false;" class="block">
                    <img src="img/purplearmy_logo-removebg.png" alt="Coop Logo" class="w-40 md:w-52 h-auto object-contain py-2 drop-shadow-sm transition-transform hover:scale-105">
                </a>
                <button class="absolute top-4 right-4 md:hidden text-gray-400 hover:text-gray-800" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <nav class="flex-1 overflow-y-auto py-4 flex flex-col gap-1">
                <a href="index.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-users w-6"></i> MEMBERSHIP DIRECTORY
                </a>
                <a href="member_shares.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-hand-holding-usd w-6"></i> MEMBER SHARES
                </a>
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-receipt w-6"></i> SALES & PURCHASE LOGS
                </a>
                <a href="inventory.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
                    <i class="fas fa-boxes w-6"></i> INVENTORY
                </a>
                <a href="pos.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-shopping-cart w-6"></i> SELL / OUTSOURCE
                </a>
                <a href="outsourcing_report.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-chart-line w-6"></i> OUTSOURCING LOGS
                </a>
                <a href="database_management.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-database w-6"></i> DATABASE SETTINGS
                </a>
                <a href="activity_logs.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-clock-rotate-left w-6"></i> ACTIVITY LOGS
                </a>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
            
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10 print:hidden">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Master Inventory</h1>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 print:hidden">
                    <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-6 flex items-center justify-between border-l-4 border-l-primary">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Total Products</div>
                            <div class="text-3xl font-bold text-gray-800"><?= $stat_total ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-primary text-xl"><i class="fas fa-box"></i></div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6 flex items-center justify-between border-l-4 border-l-red-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Low / Out of Stock</div>
                            <div class="text-3xl font-bold <?= $stat_low > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?= $stat_low ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-500 text-xl"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-green-200 p-6 flex items-center justify-between border-l-4 border-l-green-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Est. Inventory Value</div>
                            <div class="text-3xl font-bold text-gray-800">₱<?= number_format($stat_val, 2) ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                </div>

                <form id="inventoryReportForm" method="GET" action="inventory.php" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 print:hidden">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="min-w-[220px] flex items-center">
                            <h2 class="font-bold text-gray-800 text-base flex items-center"><i class="fas fa-clipboard-list text-primary mr-2"></i>Daily Inventory Report</h2>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-end gap-3 w-full lg:w-auto">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Date</label>
                                <input type="date" name="report_date" value="<?= htmlspecialchars($report_date) ?>" max="<?= htmlspecialchars($today) ?>" onchange="this.form.submit()" class="w-full sm:w-34 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Sort</label>
                                <select name="sort" onchange="this.form.submit()" class="w-full sm:w-64 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                    <?php foreach ($sort_options as $sort_key => $sort_label): ?>
                                        <option value="<?= htmlspecialchars($sort_key) ?>" <?= $report_sort === $sort_key ? 'selected' : '' ?>><?= htmlspecialchars($sort_label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($is_past_date): ?>
                                <div class="flex items-center h-[38px] px-3 rounded-md border border-amber-200 bg-amber-50 text-amber-800 text-xs font-bold uppercase tracking-wider whitespace-nowrap">
                                    <i class="fas fa-eye mr-2"></i>View Only
                                </div>
                            <?php endif; ?>
                            <div class="flex gap-2">
                                <button type="submit" name="print" value="1" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300 whitespace-nowrap">
                                    <i class="fas fa-print mr-1"></i>PRINT
                                </button>
                                <button type="submit" name="export" value="excel" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm whitespace-nowrap">
                                    <i class="fas fa-file-excel mr-1"></i>EXCEL
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if ($is_past_date && $snapshot_missing): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 mb-6 print:hidden">
                        <div class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>No Snapshot For <?= inventoryReportDateLabel($report_date) ?></div>
                        <div class="text-sm text-amber-800 mt-1">
                            This system can only show historical inventory reports if a snapshot was saved for that date. Open the inventory page on the day itself (or before changes) to automatically save the snapshot.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4 print:hidden">
                    
                    <div class="flex w-full lg:w-1/3 bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary focus-within:border-primary transition-all shadow-sm">
                        <div class="px-3 py-2 text-gray-400 flex items-center justify-center"><i class="fas fa-search"></i></div>
                        <input type="text" id="liveSearch" placeholder="Search Products..." class="w-full py-2 pr-4 outline-none text-sm text-gray-700 bg-transparent">
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                        <?php if (!$is_past_date): ?>
                            <button onclick="openModal()" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                                <i class="fas fa-plus mr-2"></i>ADD PRODUCT
                            </button>
                        <?php else: ?>
                            <div class="bg-gray-100 text-gray-500 font-semibold py-2 px-4 rounded-md text-sm shadow-sm w-full sm:w-auto text-center border border-gray-200 whitespace-nowrap cursor-not-allowed select-none">
                                <i class="fas fa-lock mr-2"></i>ADD PRODUCT
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="report-print-header">
                    <div class="report-print-title">Daily Inventory Report</div>
                    <div class="report-print-date">Date: <?= inventoryReportDateLabel($report_date) ?></div>
                </div>

                <div id="inventoryReportCard" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="inventoryReportTable" class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 font-bold tracking-wider">Product Name</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Category</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Price (PHP)</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Quantity</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Status</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-right print:hidden">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="inventoryTableBody">
                                <?php
                                if (!empty($inventory_rows)) {
                                    $current_category = null;
                                    
                                    foreach ($inventory_rows as $row) {
                                        
                                        // Dynamic Category Header Row Generation
                                        if ($current_category !== $row['product_type']) {
                                            $current_category = $row['product_type'];
                                            echo "<tr class='category-header bg-purple-100/60'>
                                                    <td colspan='6' class='px-6 py-2.5 font-black text-primaryDark uppercase text-sm tracking-widest border-y border-purple-200'>
                                                        <i class='fas fa-tags mr-2 opacity-50'></i>" . htmlspecialchars($current_category) . "
                                                    </td>
                                                  </tr>";
                                        }

                                        $stock = (int)$row['current_quantity'];
                                        $quantityText = number_format($stock) . " " . htmlspecialchars($row['quantity_type']) . "(s)";
                                        if ($stock <= 0) {
                                            $stockBadge = "<span class='stock-badge-print inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-red-100 text-red-800 border border-red-200'><i class='fas fa-times-circle mr-1'></i> OUT OF STOCK</span>";
                                            $rowBg = "bg-red-50/30"; 
                                        } elseif ($stock < 5) {
                                            $stockBadge = "<span class='stock-badge-print inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-200'><i class='fas fa-exclamation-triangle mr-1'></i> LOW STOCK</span>";
                                            $rowBg = "";
                                        } else {
                                            $stockBadge = "<span class='stock-badge-print inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-green-100 text-green-800 border border-green-200'><i class='fas fa-check-circle mr-1'></i> IN STOCK</span>";
                                            $rowBg = "";
                                        }

                                        echo "<tr class='inventory-row {$rowBg} hover:bg-purple-50 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-gray-900 uppercase product-name-cell'>" . htmlspecialchars($row['product_name']) . "</td>
                                                <td class='px-6 py-4 text-xs font-semibold tracking-wider text-gray-500 uppercase'>" . htmlspecialchars($row['product_type']) . "</td>
                                                <td class='px-6 py-4 font-semibold text-gray-700'>₱" . number_format($row['price'], 2) . "</td>
                                                <td class='px-6 py-4 font-bold text-gray-800'>{$quantityText}</td>
                                                <td class='px-6 py-4'>{$stockBadge}</td>
                                                <td class='px-6 py-4 text-right print:hidden'>
                                                    
                                                    " . ($is_past_date
                                                        ? "<div class='text-gray-400 text-xs font-semibold inline-flex items-center gap-2'><i class='fas fa-lock'></i> VIEW ONLY</div>"
                                                        : "<div class='flex justify-end gap-2'>
                                                            <button type='button' onclick='openAdjustModal(this)' data-id='{$row['product_id']}' data-name='" . htmlspecialchars($row['product_name'], ENT_QUOTES) . "' data-stock='{$stock}' class='bg-white hover:bg-green-50 text-green-600 border border-green-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors' title='Adjust Stock'>
                                                                <i class='fas fa-plus-minus'></i> STOCK
                                                            </button>

                                                            <button type='button' onclick='editProduct(this)' data-id='{$row['product_id']}' data-name='" . htmlspecialchars($row['product_name'], ENT_QUOTES) . "' data-type='" . htmlspecialchars($row['product_type'], ENT_QUOTES) . "' data-qty-type='" . htmlspecialchars($row['quantity_type'], ENT_QUOTES) . "' data-price='" . htmlspecialchars($row['price'], ENT_QUOTES) . "' class='bg-white hover:bg-blue-50 text-blue-600 border border-blue-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors' title='Edit Details'>
                                                                <i class='fas fa-edit'></i> EDIT
                                                            </button>

                                                            <button type='button' onclick='openDeleteModal(this)' data-id='{$row['product_id']}' data-name='" . htmlspecialchars($row['product_name'], ENT_QUOTES) . "' class='bg-white hover:bg-red-50 text-red-600 border border-red-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors' title='Delete Product'>
                                                                <i class='fas fa-trash-alt'></i>
                                                            </button>
                                                        </div>") . "

                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='px-6 py-12 text-center text-gray-500'>Inventory is empty. Click 'Add Product' to begin.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        <?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
                const params = new URLSearchParams(window.location.search);
                params.delete('print');
                const query = params.toString();
                const cleanUrl = window.location.pathname + (query ? '?' + query : '');
                window.history.replaceState({}, document.title, cleanUrl);
            }, 350);
        });
        <?php endif; ?>

        // --- UPGRADED LIVE SEARCH ---
        // Dynamically hides/shows the new Category Headers if they are empty
        document.getElementById('liveSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let allRows = document.querySelectorAll('#inventoryTableBody tr');
            
            let currentHeader = null;
            let visibleItemsUnderHeader = 0;

            allRows.forEach(row => {
                if (row.classList.contains('category-header')) {
                    if (currentHeader !== null) {
                        currentHeader.style.display = visibleItemsUnderHeader > 0 ? '' : 'none';
                    }
                    currentHeader = row;
                    visibleItemsUnderHeader = 0;
                    row.style.display = ''; 
                } else if (row.classList.contains('inventory-row')) {
                    let text = row.querySelector('.product-name-cell').textContent.toLowerCase();
                    if (text.includes(filter)) {
                        row.style.display = '';
                        visibleItemsUnderHeader++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
            // Final catch for the very last category in the loop
            if (currentHeader !== null) {
                currentHeader.style.display = visibleItemsUnderHeader > 0 ? '' : 'none';
            }
        });

        // --- SMART SELECTOR LOGIC ---
        // Forces the dropdown to select an option even if there are weird spaces in the database
        function selectOptionSmartly(selectId, valueToFind) {
            let select = document.getElementById(selectId);
            let targetVal = valueToFind.trim().toLowerCase();
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value.trim().toLowerCase() === targetVal || select.options[i].text.trim().toLowerCase() === targetVal) {
                    select.selectedIndex = i;
                    return;
                }
            }
            select.value = valueToFind; // Fallback
        }

        // --- PRODUCT MODAL LOGIC ---
        function openModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle text-primary mr-2"></i>Add New Product';
            document.getElementById('product_id').value = '';
            document.getElementById('product_name').value = '';
            document.getElementById('product_type').selectedIndex = 0;
            document.getElementById('quantity_type').selectedIndex = 0;
            document.getElementById('price').value = '';
            
            // Show initial stock input since it's a new product
            const qtyGroup = document.getElementById('initial_qty_group');
            qtyGroup.style.display = 'block';
            document.getElementById('current_quantity').setAttribute('required', 'required');
            document.getElementById('current_quantity').value = '';

            document.getElementById('productModal').classList.remove('hidden');
            document.getElementById('productModal').classList.add('flex');
        }

        function editProduct(triggerOrId, name, type, qtyType, price) {
            let id = triggerOrId;
            if (triggerOrId && triggerOrId.dataset) {
                id = triggerOrId.dataset.id;
                name = triggerOrId.dataset.name;
                type = triggerOrId.dataset.type;
                qtyType = triggerOrId.dataset.qtyType;
                price = triggerOrId.dataset.price;
            }
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit text-blue-600 mr-2"></i>Edit Product Details';
            document.getElementById('product_id').value = id;
            document.getElementById('product_name').value = name;
            document.getElementById('price').value = price;
            
            // Smart select Category and Unit Dropdowns
            selectOptionSmartly('product_type', type);
            selectOptionSmartly('quantity_type', qtyType);

            // Hide initial stock input (stock must be edited via the Adjust Stock button)
            const qtyGroup = document.getElementById('initial_qty_group');
            qtyGroup.style.display = 'none';
            document.getElementById('current_quantity').removeAttribute('required');

            document.getElementById('productModal').classList.remove('hidden');
            document.getElementById('productModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
            document.getElementById('productModal').classList.remove('flex');
        }

        // --- STOCK ADJUSTMENT MODAL LOGIC ---
        function openAdjustModal(triggerOrId, name, currentStock) {
            let id = triggerOrId;
            if (triggerOrId && triggerOrId.dataset) {
                id = triggerOrId.dataset.id;
                name = triggerOrId.dataset.name;
                currentStock = triggerOrId.dataset.stock;
            }
            document.getElementById('adj_product_id').value = id;
            document.getElementById('adj_product_name_display').innerText = name.toUpperCase();
            document.getElementById('adj_current_stock').innerText = currentStock;
            
            // Reset input
            document.querySelector('input[name="adjust_amount"]').value = '';

            document.getElementById('adjustModal').classList.remove('hidden');
            document.getElementById('adjustModal').classList.add('flex');
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
            document.getElementById('adjustModal').classList.remove('flex');
        }

        // --- DELETE CONFIRMATION MODAL LOGIC ---
        function openDeleteModal(triggerOrId, name) {
            let id = triggerOrId;
            if (triggerOrId && triggerOrId.dataset) {
                id = triggerOrId.dataset.id;
                name = triggerOrId.dataset.name;
            }
            document.getElementById('deleteProductId').value = id;
            document.getElementById('deleteProductName').innerText = 'Delete "' + name + '"?';
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // --- CUSTOM ALERT LOGIC ---
        let alertRedirectUrl = null;

        function showCustomAlert(title, message, type = 'error', redirectUrl = null) {
            const modal = document.getElementById('customAlertModal');
            const box = document.getElementById('customAlertBox');
            const titleEl = document.getElementById('customAlertTitle');
            const msgEl = document.getElementById('customAlertMessage');
            const iconEl = document.getElementById('customAlertIcon');
            const headerEl = document.getElementById('customAlertHeader');
            const btnEl = document.getElementById('customAlertBtn');

            titleEl.innerText = title;
            msgEl.innerHTML = message;
            alertRedirectUrl = redirectUrl;

            if (type === 'success') {
                iconEl.className = 'fas fa-check-circle text-2xl text-green-500';
                headerEl.className = 'px-6 py-4 flex items-center gap-3 border-b bg-green-50 border-green-100';
                btnEl.className = 'bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md';
            } else if (type === 'info') {
                iconEl.className = 'fas fa-info-circle text-2xl text-blue-500';
                headerEl.className = 'px-6 py-4 flex items-center gap-3 border-b bg-blue-50 border-blue-100';
                btnEl.className = 'bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md';
            } else {
                iconEl.className = 'fas fa-exclamation-circle text-2xl text-red-500';
                headerEl.className = 'px-6 py-4 flex items-center gap-3 border-b bg-red-50 border-red-100';
                btnEl.className = 'bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                box.classList.remove('translate-y-4', 'opacity-0');
                box.classList.add('translate-y-0', 'opacity-100');
            }, 10);
        }

        document.getElementById('customAlertBtn').addEventListener('click', function() {
            const modal = document.getElementById('customAlertModal');
            const box = document.getElementById('customAlertBox');
            
            box.classList.remove('translate-y-0', 'opacity-100');
            box.classList.add('translate-y-4', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (alertRedirectUrl) {
                    window.location.href = alertRedirectUrl;
                }
            }, 300);
        });

        // Catch Session Alerts
        <?php if (isset($_SESSION['alert_message'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showCustomAlert(
                    "<?= addslashes($_SESSION['alert_title']) ?>", 
                    "<?= addslashes($_SESSION['alert_message']) ?>", 
                    "<?= addslashes($_SESSION['alert_type']) ?>"
                );
            });
            <?php 
            unset($_SESSION['alert_title']);
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>
    </script>
</body>
</html>
