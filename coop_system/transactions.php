<?php 
session_start(); // CRITICAL: Added this so the page can receive alerts!
include 'db.php'; 

$transaction_types = function_exists('getTransactionTypes') ? getTransactionTypes($conn, true) : [];
if (empty($transaction_types) && function_exists('getTransactionTypes')) {
    $transaction_types = getTransactionTypes($conn, false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual_transaction'])) {
    $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
    $item_name = trim((string)($_POST['item_name'] ?? ''));
    $item_cost = (float)($_POST['item_cost'] ?? 0);
    $item_quantity = (int)($_POST['item_quantity'] ?? 0);
    $reference_no = trim((string)($_POST['reference_no'] ?? ''));

    $type_row = null;
    foreach ($transaction_types as $type) {
        if ((int)$type['id'] === $transaction_type_id) {
            $type_row = $type;
            break;
        }
    }

    if (!$type_row || $item_name === '' || $item_cost <= 0 || $item_quantity <= 0 || $reference_no === '') {
        $_SESSION['alert_title'] = "Invalid Entry";
        $_SESSION['alert_message'] = "Please select a transaction type, enter item details, quantity, cost, and a reference number.";
        $_SESSION['alert_type'] = "error";
        header("Location: transactions.php");
        exit();
    }

    $transaction_date = date('Y-m-d');
    $transaction_type = $type_row['name'];
    $amount = $item_cost * $item_quantity;
    $items_details = $item_quantity . "x " . $item_name . " @ ₱" . number_format($item_cost, 2) . " = ₱" . number_format($amount, 2);
    $member_id = null;
    $member_name = 'MANUAL ENTRY';
    $payment_status = 'COMPLETED';

    $check = $conn->prepare("SELECT transaction_id FROM transactions WHERE invoice_no = ? LIMIT 1");
    if ($check) {
        $check->bind_param("s", $reference_no);
        $check->execute();
        $existing = $check->get_result();
        if ($existing && $existing->num_rows > 0) {
            $check->close();
            $_SESSION['alert_title'] = "Duplicate Reference";
            $_SESSION['alert_message'] = "The reference number already exists in the transaction records.";
            $_SESSION['alert_type'] = "error";
            header("Location: transactions.php");
            exit();
        }
        $check->close();
    }

    $stmt = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
    $stmt->bind_param("sissdsss", $transaction_date, $member_id, $member_name, $transaction_type, $amount, $items_details, $reference_no, $payment_status);
    if ($stmt->execute()) {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'ADD MANUAL TRANSACTION',
                'TRANSACTION',
                $conn->insert_id,
                $reference_no,
                'Type: ' . $transaction_type . ', Item: ' . $item_name . ', Qty: ' . $item_quantity . ', Amount: ' . number_format($amount, 2)
            );
        }
        $_SESSION['alert_title'] = "Transaction Saved";
        $_SESSION['alert_message'] = "Manual transaction saved successfully.";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['alert_title'] = "Database Error";
        $_SESSION['alert_message'] = "Unable to save the manual transaction.";
        $_SESSION['alert_type'] = "error";
    }
    $stmt->close();
    header("Location: transactions.php");
    exit();
}

function getTransactionRows(mysqli $conn): array {
    $rows = [];
    $sql = "SELECT t.*, COALESCE(ct.name, t.transaction_type) AS transaction_type_label
            FROM transactions t
            LEFT JOIN config_transaction_types ct ON LOWER(TRIM(t.transaction_type)) = LOWER(TRIM(ct.name))
            ORDER BY COALESCE(ct.name, t.transaction_type) ASC, t.transaction_date DESC, t.transaction_id DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function groupTransactionRows(array $rows): array {
    $grouped = [];
    foreach ($rows as $row) {
        $label = trim((string)($row['transaction_type_label'] ?? $row['transaction_type'] ?? 'Other'));
        if ($label === '') {
            $label = 'Other';
        }
        if (!isset($grouped[$label])) {
            $grouped[$label] = [];
        }
        $grouped[$label][] = $row;
    }
    ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
    return $grouped;
}

$transaction_rows = getTransactionRows($conn);
$transactions_by_type = groupTransactionRows($transaction_rows);

if (isset($_GET['template']) && $_GET['template'] === 'excel') {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transactions Template');

    $sheet->mergeCells('A1:Q1');
    $sheet->mergeCells('A2:Q2');
    $sheet->mergeCells('A3:Q3');
    $sheet->setCellValue('A1', 'Transactions Import Template');
    $sheet->setCellValue('A2', 'Accepted fields: transaction type, reference number, member ID / form ID / split member name fields, item fields, and status.');
    $sheet->setCellValue('A3', 'Replace the sample row with your own data before importing.');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:Q3')->getFont()->setName('Arial')->setSize(12);

    $headers = [
        'Date',
        'Transaction Type',
        'Reference No. / Invoice No. / Receipt No.',
        'Member ID',
        'Form ID',
        'Member First Name',
        'Member Second Name',
        'Member Middle Name',
        'Member Last Name',
        'Item Name',
        'Quantity',
        'Item Cost',
        'Item Amount',
        'Total Amount',
        'Downpayment',
        'Balance',
        'Status'
    ];
    $sheet->fromArray($headers, null, 'A5');
    $sheet->getStyle('A5:Q5')->getFont()->setBold(true);
    $sheet->getStyle('A5:Q5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');

    $sheet->fromArray([
        [date('Y-m-d'), 'Sales', 'REF-0001', 647, '26-067', 'MILAGROSA', '', 'OTACAN', 'BATURIANO', 'Sample Item', 1, 100.00, 100.00, 100.00, 0.00, 0.00, 'COMPLETED']
    ], null, 'A6');
    $sheet->getStyle('L6:P6')->getNumberFormat()->setFormatCode('#,##0.00');

    foreach (range('A', 'Q') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Transactions_Import_Template.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transactions');
    $sheet->mergeCells('A1:G1');
    $sheet->mergeCells('A2:G2');
    $sheet->setCellValue('A1', 'Transaction Records');
    $sheet->setCellValue('A2', 'Exported: ' . date('F d, Y h:i A'));
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:G2')->getFont()->setName('Arial')->setSize(12);

    $headers = ['Date', 'Reference / Invoice', 'Transaction Type', 'Member Name', 'Items Details', 'Amount (PHP)', 'Status'];
    $row_num = 4;
    foreach ($transactions_by_type as $type_name => $rows) {
        $sheet->mergeCells("A{$row_num}:G{$row_num}");
        $sheet->setCellValue("A{$row_num}", $type_name);
        $sheet->getStyle("A{$row_num}")->getFont()->setBold(true)->setSize(13);
        $row_num++;
        $sheet->fromArray($headers, null, "A{$row_num}");
        $sheet->getStyle("A{$row_num}:G{$row_num}")->getFont()->setBold(true);
        $row_num++;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$row_num}", $row['transaction_date']);
            $sheet->setCellValue("B{$row_num}", $row['invoice_no'] ?: 'N/A');
            $sheet->setCellValue("C{$row_num}", $row['transaction_type_label'] ?? $row['transaction_type']);
            $sheet->setCellValue("D{$row_num}", $row['member_name']);
            $sheet->setCellValue("E{$row_num}", $row['items_details'] ?? '');
            $sheet->setCellValue("F{$row_num}", (float)$row['amount']);
            $sheet->setCellValue("G{$row_num}", $row['payment_status'] ?: 'COMPLETED');
            $row_num++;
        }
        $row_num++;
    }

    $sheet->getStyle("F4:F{$row_num}")->getNumberFormat()->setFormatCode('#,##0.00');
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Transactions_' . date('Y-m-d') . '.xlsx';
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
    <title>Transactions - Coop DBMS</title>
    
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
        .transactions-print-header { display: none; }
        @media print {
            @page { margin: 14mm; }
            html, body {
                background: #ffffff !important;
                overflow: visible !important;
                height: auto !important;
                width: 100% !important;
                font-family: Arial, sans-serif !important;
                font-size: 12px !important;
            }
            body {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .tx-group {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .print-force-show {
                display: block !important;
            }
            .print\:hidden { display: none !important; }
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
                margin: 0 !important;
            }
            body > .flex.h-screen.w-full {
                display: block !important;
                height: auto !important;
                min-height: 0 !important;
                width: 100% !important;
                overflow: visible !important;
            }
            main {
                margin: 0 !important;
                padding: 0 !important;
            }
            main > div {
                margin: 0 !important;
            }
            .transactions-print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 16px;
                color: #111827;
            }
            .transactions-print-title {
                font-size: 20px !important;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .transactions-print-meta {
                font-size: 13px !important;
                margin-bottom: 6px;
            }
            #transactionsReportCard {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
                margin-top: 0 !important;
            }
            .tx-group {
                box-shadow: none !important;
                border-color: #d1d5db !important;
            }
            .tx-group table {
                width: 100% !important;
                border-collapse: collapse !important;
                white-space: normal !important;
                font-family: Arial, sans-serif !important;
                font-size: 12px !important;
            }
            .tx-group th,
            .tx-group td {
                border: 1px solid #d1d5db !important;
                padding: 5px 6px !important;
                font-size: 12px !important;
            }
            .tx-group thead th {
                background: #f3f4f6 !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-hidden">

    <?php include 'cover_page.php'; ?>

    <div id="customAlertModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="customAlertBox">
            <div id="customAlertHeader" class="px-6 py-4 flex items-center gap-3 border-b">
                <i id="customAlertIcon" class="fas fa-exclamation-circle text-2xl"></i>
                <h3 id="customAlertTitle" class="text-lg font-bold tracking-tight">Alert</h3>
            </div>
            <div class="p-6 text-gray-600 text-sm leading-relaxed" id="customAlertMessage">
                </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button id="customAlertBtn" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">OK</button>
            </div>
        </div>
    </div>

    <div id="manualTransactionModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeManualModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl z-10 overflow-hidden transform transition-all">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800"><i class="fas fa-plus-circle text-primary mr-2"></i>Add Manual Transaction</h3>
                <button type="button" onclick="closeManualModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="transactions.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="add_manual_transaction" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type <span class="text-red-500">*</span></label>
                    <select name="transaction_type_id" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <option value="" selected disabled>Select Transaction Type</option>
                        <?php foreach ($transaction_types as $type): ?>
                            <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Name <span class="text-red-500">*</span></label>
                        <input type="text" name="item_name" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference No. <span class="text-red-500">*</span></label>
                        <input type="text" name="reference_no" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Cost <span class="text-red-500">*</span></label>
                        <input type="number" name="item_cost" step="0.01" min="0.01" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="item_quantity" step="1" min="1" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div class="flex items-end">
                        <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500">
                            Total amount is computed automatically from item cost and quantity.
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeManualModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md">SAVE TRANSACTION</button>
                </div>
            </form>
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
                <a href="transactions.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
                    <i class="fas fa-receipt w-6"></i> TRANSACTIONS
                </a>
                <a href="inventory.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Transaction Records</h1>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4 print:hidden">
                    
                    <div class="flex flex-col xl:flex-row items-stretch xl:items-center gap-3 w-full xl:w-auto">
                        <form action="import_transactions.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm items-center">
                            <input type="file" name="excel_file" accept=".xls,.xlsx" required class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:font-semibold file:bg-purple-50 file:text-primary hover:file:bg-purple-100 transition cursor-pointer">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1.5 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap"><i class="fas fa-upload mr-1"></i> UPLOAD</button>
                        </form>
                        <a href="transactions.php?template=excel" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                            <i class="fas fa-download mr-2"></i>IMPORT TEMPLATE
                        </a>
                    </div>

                    <div class="flex flex-col xl:flex-row items-stretch xl:items-center gap-3 w-full xl:w-auto">
                        <select id="transactionTypeFilter" class="bg-white border border-gray-300 rounded-md px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary w-full xl:w-56" onchange="filterTransactionGroups()">
                            <option value="ALL">All Transaction Types</option>
                            <?php foreach (array_keys($transactions_by_type) as $type_name): ?>
                                <option value="<?= htmlspecialchars($type_name) ?>"><?= htmlspecialchars($type_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="openManualModal()" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                            <i class="fas fa-plus mr-2"></i>ADD MANUAL
                        </button>
                        <a href="transactions.php?export=excel" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                            <i class="fas fa-file-excel mr-2"></i>EXPORT
                        </a>
                        <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center border border-gray-300 whitespace-nowrap">
                            <i class="fas fa-print mr-2"></i>PRINT REPORT
                        </button>
                    </div>
                </div>

                <div class="transactions-print-header">
                    <div class="transactions-print-title">Transaction Records</div>
                    <div class="transactions-print-meta" id="txPrintMetaType">Type: All Transaction Types</div>
                    <div class="transactions-print-meta">Date Generated: <?= htmlspecialchars(date('F d, Y h:i A')) ?></div>
                </div>

                <div id="transactionsReportCard" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="font-bold text-gray-800"><i class="fas fa-list-ul text-primary mr-2"></i>All Financial Transactions</h4>
                    </div>

                    <div class="space-y-5 p-4 md:p-6">
                        <?php if (!empty($transactions_by_type)): ?>
                            <?php foreach ($transactions_by_type as $type_name => $rows): ?>
                                <section class="tx-group bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" data-tx-type="<?= htmlspecialchars($type_name) ?>">
                                    <div class="bg-gray-50 px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
                                        <h4 class="font-bold text-gray-800"><i class="fas fa-layer-group text-primary mr-2"></i><?= htmlspecialchars($type_name) ?></h4>
                                        <span class="text-xs text-gray-500"><?= count($rows) ?> record(s)</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                                <tr>
                                                    <th class="px-6 py-4 font-bold tracking-wider">Date</th>
                                                    <th class="px-6 py-4 font-bold tracking-wider">Invoice / Ref</th>
                                                    <th class="px-6 py-4 font-bold tracking-wider">Member Name</th>
                                                    <th class="px-6 py-4 font-bold tracking-wider">Item Details</th>
                                                    <th class="px-6 py-4 font-bold tracking-wider text-right">Amount (PHP)</th>
                                                    <th class="px-6 py-4 font-bold tracking-wider text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <?php foreach ($rows as $row): ?>
                                                    <?php
                                                        $date = date('M d, Y', strtotime($row['transaction_date']));
                                                        $inv = !empty($row['invoice_no']) ? htmlspecialchars($row['invoice_no']) : 'N/A';
                                                        $status = !empty($row['payment_status']) ? htmlspecialchars($row['payment_status']) : 'COMPLETED';
                                                        if (stripos($status, 'paid') !== false || stripos($status, 'completed') !== false) {
                                                            $stat_badge = "<span class='bg-green-100 text-green-800 px-2.5 py-1 rounded text-xs font-bold uppercase'>$status</span>";
                                                        } else {
                                                            $stat_badge = "<span class='bg-red-100 text-red-800 px-2.5 py-1 rounded text-xs font-bold uppercase'>$status</span>";
                                                        }
                                                    ?>
                                                    <tr class="hover:bg-purple-50 transition-colors tx-row" data-date="<?= htmlspecialchars($row['transaction_date']) ?>">
                                                        <td class="px-6 py-4 font-medium text-gray-500"><?= $date ?></td>
                                                        <td class="px-6 py-4 font-mono text-gray-700"><?= $inv ?></td>
                                                        <td class="px-6 py-4 font-bold text-gray-900 capitalize"><?= htmlspecialchars($row['member_name']) ?></td>
                                                        <td class="px-6 py-4 text-gray-700 whitespace-normal max-w-xl"><?= htmlspecialchars($row['items_details'] ?? '-') ?></td>
                                                        <td class="px-6 py-4 font-bold text-gray-900 text-right">₱<?= number_format((float)$row['amount'], 2) ?></td>
                                                        <td class="px-6 py-4 text-center"><?= $stat_badge ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="px-6 py-12 text-center text-gray-500 bg-white rounded-xl border border-gray-200">No transactions found. Upload an Excel file or add manually to begin.</div>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto hidden">
                        <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 font-bold tracking-wider">Date</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Invoice / Ref</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Member Name</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-right">Amount (PHP)</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                try {
                                    $sql = "SELECT * FROM transactions ORDER BY transaction_date DESC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            
                                            $date = date('M d, Y', strtotime($row['transaction_date']));
                                            $inv = !empty($row['invoice_no']) ? htmlspecialchars($row['invoice_no']) : 'N/A';
                                            
                                            $status = !empty($row['payment_status']) ? htmlspecialchars($row['payment_status']) : 'COMPLETED';
                                            if (stripos($status, 'paid') !== false || stripos($status, 'completed') !== false) {
                                                $stat_badge = "<span class='bg-green-100 text-green-800 px-2.5 py-1 rounded text-xs font-bold uppercase'>$status</span>";
                                            } else {
                                                $stat_badge = "<span class='bg-red-100 text-red-800 px-2.5 py-1 rounded text-xs font-bold uppercase'>$status</span>";
                                            }

                                            echo "<tr class='hover:bg-purple-50 transition-colors tx-row' data-date='" . htmlspecialchars($row['transaction_date']) . "'>
                                                    <td class='px-6 py-4 font-medium text-gray-500'>{$date}</td>
                                                    <td class='px-6 py-4 font-mono text-gray-700'>{$inv}</td>
                                                    <td class='px-6 py-4 font-bold text-gray-900 capitalize'>" . htmlspecialchars($row['member_name']) . "</td>
                                                    <td class='px-6 py-4 font-bold text-gray-900 text-right'>₱" . number_format($row['amount'], 2) . "</td>
                                                    <td class='px-6 py-4 text-center'>{$stat_badge}</td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='px-6 py-12 text-center text-gray-500'>No transactions found. Upload an Excel file or add manually to begin.</td></tr>";
                                    }
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='5' class='px-6 py-12 text-center text-red-500 italic'><i class='fas fa-exclamation-triangle mr-2'></i>Database table 'transactions' not yet configured. Upload a file to auto-configure.</td></tr>";
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

        function openManualModal() {
            document.getElementById('manualTransactionModal').classList.remove('hidden');
            document.getElementById('manualTransactionModal').classList.add('flex');
        }

        function closeManualModal() {
            document.getElementById('manualTransactionModal').classList.add('hidden');
            document.getElementById('manualTransactionModal').classList.remove('flex');
        }

        function updateTransactionPrintMeta() {
            const typeFilter = document.getElementById('transactionTypeFilter').value;

            document.getElementById('txPrintMetaType').innerText = 'Type: ' + (typeFilter === 'ALL' ? 'All Transaction Types' : typeFilter);
        }

        function filterTransactionGroups() {
            const typeFilter = document.getElementById('transactionTypeFilter').value;

            document.querySelectorAll('.tx-group').forEach(group => {
                const matchesType = typeFilter === 'ALL' || group.dataset.txType === typeFilter;
                let visibleRows = 0;

                group.querySelectorAll('.tx-row').forEach(row => {
                    const rowVisible = matchesType;
                    row.style.display = rowVisible ? '' : 'none';
                    if (rowVisible) visibleRows++;
                });

                group.style.display = visibleRows > 0 ? '' : 'none';
            });

            updateTransactionPrintMeta();
        }

        document.addEventListener('DOMContentLoaded', filterTransactionGroups);

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

            // Style based on type (success, info, or error)
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
            
            // Trigger animation
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

        // --- CATCH PHP SESSION ALERTS ---
        <?php if (isset($_SESSION['alert_message'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showCustomAlert(
                    "<?= addslashes($_SESSION['alert_title']) ?>", 
                    "<?= addslashes($_SESSION['alert_message']) ?>", 
                    "<?= addslashes($_SESSION['alert_type']) ?>"
                );
            });
            <?php 
            // Destroy the session variables so the alert doesn't show again on refresh
            unset($_SESSION['alert_title']);
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>
    </script>
</body>
</html>
