<?php 
session_start(); // CRITICAL: Added this so the page can receive alerts!
include 'db.php'; 

$transaction_types = function_exists('getTransactionTypes') ? getTransactionTypes($conn, true) : [];
if (empty($transaction_types) && function_exists('getTransactionTypes')) {
    $transaction_types = getTransactionTypes($conn, false);
}

function getConfiguredUnitTypes(mysqli $conn): array {
    $units = [];
    $result = $conn->query("SELECT id, name FROM config_unit_types ORDER BY name ASC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
    }
    return $units;
}

function buildManualMemberName(string $first, string $second, string $middle, string $last): string {
    $first = trim($first);
    $second = trim($second);
    $middle = trim($middle);
    $last = trim($last);

    if ($first === '' && $second === '' && $middle === '' && $last === '') {
        return '';
    }

    $given = trim(preg_replace('/\s+/u', ' ', trim($first . ' ' . $second . ' ' . $middle)));
    if ($last !== '' && $given !== '') {
        return $last . ', ' . $given;
    }
    if ($last !== '') {
        return $last;
    }
    return $given;
}

function buildManualItemLine(string $qty, string $unit, string $name, float $cost, ?float $amount = null): string {
    $qty = trim($qty);
    $unit = trim($unit);
    $name = trim($name);
    $parts = [];

    if ($qty !== '') {
        $parts[] = $qty . ($unit !== '' ? ' ' . $unit : '');
    } elseif ($unit !== '') {
        $parts[] = $unit;
    }

    if ($name !== '') {
        $parts[] = $name;
    }

    $parts[] = '@ ₱' . number_format((float)$cost, 2);
    if ($amount !== null) {
        $parts[] = '= ₱' . number_format((float)$amount, 2);
    }

    return trim(implode(' ', $parts));
}

$unit_types = getConfiguredUnitTypes($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual_transaction_multi'])) {
    $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
    $reference_no = trim((string)($_POST['reference_no'] ?? ''));
    $transaction_date = trim((string)($_POST['transaction_date'] ?? date('Y-m-d')));
    $member_first_name = trim((string)($_POST['member_first_name'] ?? ''));
    $member_second_name = trim((string)($_POST['member_second_name'] ?? ''));
    $member_middle_name = trim((string)($_POST['member_middle_name'] ?? ''));
    $member_last_name = trim((string)($_POST['member_last_name'] ?? ''));
    $payment_status = trim((string)($_POST['payment_status'] ?? 'COMPLETED'));
    $downpayment = (float)($_POST['downpayment'] ?? 0);
    $remaining_balance = (float)($_POST['remaining_balance'] ?? 0);
    $manual_total_amount = trim((string)($_POST['total_amount'] ?? ''));

    $type_row = null;
    foreach ($transaction_types as $type) {
        if ((int)$type['id'] === $transaction_type_id) {
            $type_row = $type;
            break;
        }
    }

    $item_names = $_POST['item_name'] ?? [];
    $item_units = $_POST['item_unit'] ?? [];
    $item_costs = $_POST['item_cost'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_amounts = $_POST['item_amount'] ?? [];

    if (!is_array($item_names)) $item_names = [$item_names];
    if (!is_array($item_units)) $item_units = [$item_units];
    if (!is_array($item_costs)) $item_costs = [$item_costs];
    if (!is_array($item_quantities)) $item_quantities = [$item_quantities];
    if (!is_array($item_amounts)) $item_amounts = [$item_amounts];

    if (!$type_row || $reference_no === '') {
        $_SESSION['alert_title'] = "Invalid Entry";
        $_SESSION['alert_message'] = "Please select a transaction type and enter a reference number.";
        $_SESSION['alert_type'] = "error";
        header("Location: transactions.php");
        exit();
    }

    $transaction_type = $type_row['name'];
    $item_lines = [];
    $computed_total = 0.0;
    $line_count = max(count($item_names), count($item_units), count($item_costs), count($item_quantities), count($item_amounts));

    for ($i = 0; $i < $line_count; $i++) {
        $item_name = trim((string)($item_names[$i] ?? ''));
        $item_unit = trim((string)($item_units[$i] ?? ''));
        $item_cost = (float)($item_costs[$i] ?? 0);
        $item_quantity = (float)($item_quantities[$i] ?? 0);
        $item_amount = trim((string)($item_amounts[$i] ?? ''));

        if ($item_name === '' && $item_unit === '' && $item_cost <= 0 && $item_quantity <= 0 && $item_amount === '') {
            continue;
        }

        if ($item_name === '' || $item_cost <= 0 || $item_quantity <= 0) {
            $_SESSION['alert_title'] = "Invalid Item Row";
            $_SESSION['alert_message'] = "Please complete the item name, quantity, and cost for each transaction item.";
            $_SESSION['alert_type'] = "error";
            header("Location: transactions.php");
            exit();
        }

        $resolved_amount = $item_amount !== '' ? (float)$item_amount : ($item_quantity * $item_cost);
        $computed_total += $resolved_amount;
        $item_lines[] = buildManualItemLine((string)$item_quantity, $item_unit, $item_name, $item_cost, $resolved_amount);
    }

    if (empty($item_lines)) {
        $_SESSION['alert_title'] = "Invalid Entry";
        $_SESSION['alert_message'] = "Please add at least one item row to the manual transaction.";
        $_SESSION['alert_type'] = "error";
        header("Location: transactions.php");
        exit();
    }

    $amount = $manual_total_amount !== '' ? (float)$manual_total_amount : $computed_total;
    if ($amount <= 0) {
        $amount = $computed_total;
    }
    if ($remaining_balance <= 0 && $amount > 0 && $downpayment > 0) {
        $remaining_balance = max($amount - $downpayment, 0);
    }

    $items_details = implode("\n", $item_lines);
    $member_id = null;
    $member_name = buildManualMemberName($member_first_name, $member_second_name, $member_middle_name, $member_last_name);
    if ($member_name === '') {
        $member_name = 'MANUAL ENTRY';
    }
    $payment_status = strtoupper($payment_status !== '' ? $payment_status : 'COMPLETED');

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

    $stmt = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissdsssdd", $transaction_date, $member_id, $member_name, $transaction_type, $amount, $items_details, $reference_no, $payment_status, $downpayment, $remaining_balance);
    if ($stmt->execute()) {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'ADD MANUAL TRANSACTION',
                'TRANSACTION',
                $conn->insert_id,
                $reference_no,
                'Type: ' . $transaction_type . ', Items: ' . count($item_lines) . ', Amount: ' . number_format($amount, 2)
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

    $sheet->mergeCells('A1:P1');
    $sheet->mergeCells('A2:P2');
    $sheet->mergeCells('A3:P3');
    $sheet->setCellValue('A1', 'Transactions Import Template');
    $sheet->setCellValue('A2', 'Accepted fields: date, transaction type, reference number, split member name fields, item name, quantity, item unit, item cost, item amount, total amount, downpayment, balance, and status.');
    $sheet->setCellValue('A3', 'Use multiple rows for multiple items in the same transaction. Leave the member and transaction columns blank on continuation rows.');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:P3')->getFont()->setName('Arial')->setSize(12);

    $headers = [
        'Date',
        'Transaction Type',
        'Reference No. / Invoice No. / Receipt No.',
        'Member First Name',
        'Member Second Name',
        'Member Middle Name',
        'Member Last Name',
        'Item Name',
        'Quantity',
        'Item Unit',
        'Item Cost',
        'Item Amount',
        'Total Amount',
        'Downpayment',
        'Balance',
        'Status'
    ];
    $sheet->fromArray($headers, null, 'A5');
    $sheet->getStyle('A5:P5')->getFont()->setBold(true);
    $sheet->getStyle('A5:P5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');

    $sheet->fromArray([
        [date('Y-m-d'), 'Sales', 'REF-0001', 'MILAGROSA', '', 'OTACAN', 'BATURIANO', 'Sample Item', 1, 'pcs', 100.00, 100.00, 100.00, 0.00, 0.00, 'COMPLETED']
    ], null, 'A6');
    $sheet->getStyle('K6:O6')->getNumberFormat()->setFormatCode('#,##0.00');

    foreach (range('A', 'P') as $column) {
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
                break-inside: auto !important;
                page-break-inside: auto !important;
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
                page-break-inside: auto !important;
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
            .tx-group thead {
                display: table-header-group !important;
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

    <div id="manualTransactionModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-2 sm:p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeManualModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] z-10 overflow-hidden transform transition-all flex flex-col">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800"><i class="fas fa-plus-circle text-primary mr-2"></i>Add Manual Transaction</h3>
                <button type="button" onclick="closeManualModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="transactions.php" method="POST" class="flex-1 overflow-y-auto p-6 space-y-5">
                <input type="hidden" name="add_manual_transaction_multi" value="1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date <span class="text-red-500">*</span></label>
                        <input type="date" name="transaction_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type <span class="text-red-500">*</span></label>
                        <select name="transaction_type_id" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="" selected disabled>Select Transaction Type</option>
                            <?php foreach ($transaction_types as $type): ?>
                                <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No. / Invoice No. / Receipt No. <span class="text-red-500">*</span></label>
                    <input type="text" name="reference_no" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Member Name</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input type="text" name="member_first_name" placeholder="Member First Name" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <input type="text" name="member_second_name" placeholder="Member Second Name" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <input type="text" name="member_middle_name" placeholder="Member Middle Name" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <input type="text" name="member_last_name" placeholder="Member Last Name" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Items</label>
                        <p class="text-xs text-gray-500 mt-1">Add one or more item rows for the same transaction.</p>
                    </div>
                    <button type="button" onclick="addManualItemRow()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">
                        <i class="fas fa-plus mr-1"></i>ADD ITEM
                    </button>
                </div>
                <div id="manualItemsContainer" class="space-y-3">
                    <div class="manual-item-row rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Item Row</span>
                            <button type="button" onclick="removeManualItemRow(this)" class="text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-5 gap-3">
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Item Name <span class="text-red-500">*</span></label>
                                <input type="text" name="item_name[]" required class="manual-item-input w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Quantity <span class="text-red-500">*</span></label>
                                <input type="number" name="item_quantity[]" step="0.01" min="0.01" required oninput="recalculateManualItem(this)" class="manual-item-input w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Item Unit</label>
                                <div class="relative">
                                    <select name="item_unit[]" class="manual-item-input w-full appearance-none rounded-md border border-teal-200 bg-gradient-to-b from-white to-teal-50 px-4 py-2 pr-10 text-sm font-medium text-teal-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-400">
                                        <option value="" selected>Choose a unit</option>
                                        <?php foreach ($unit_types as $unit): ?>
                                            <option value="<?= htmlspecialchars($unit['name']) ?>"><?= htmlspecialchars($unit['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-teal-700">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">Units come from Database Settings and can be extended anytime.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Item Cost <span class="text-red-500">*</span></label>
                                <input type="number" name="item_cost[]" step="0.01" min="0.01" required oninput="recalculateManualItem(this)" class="manual-item-input w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Item Amount</label>
                                <input type="number" name="item_amount[]" step="0.01" min="0" readonly class="manual-item-amount w-full rounded-md border border-gray-300 px-4 py-2 text-sm bg-gray-100 text-gray-700">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                        <input type="number" name="total_amount" step="0.01" min="0" readonly class="manual-total-amount w-full rounded-md border border-gray-300 px-4 py-2 text-sm bg-gray-100 text-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Downpayment</label>
                        <input type="number" name="downpayment" step="0.01" min="0" oninput="recalculateManualSummary()" class="manual-downpayment w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Balance</label>
                        <input type="number" name="remaining_balance" step="0.01" min="0" readonly class="manual-balance w-full rounded-md border border-gray-300 px-4 py-2 text-sm bg-gray-100 text-gray-700">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="payment_status" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="COMPLETED" selected>COMPLETED</option>
                            <option value="PAID">PAID</option>
                            <option value="PARTIALLY PAID">PARTIALLY PAID</option>
                            <option value="PENDING">PENDING</option>
                            <option value="CANCELLED">CANCELLED</option>
                        </select>
                    </div>
                    <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 flex items-center">
                        Item amounts are calculated from quantity and cost, then summed into the total amount and balance.
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2 sticky bottom-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 -mx-6 px-6 py-4 mt-2">
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
            document.body.classList.add('overflow-hidden');
            recalculateManualSummary();
        }

        function closeManualModal() {
            document.getElementById('manualTransactionModal').classList.add('hidden');
            document.getElementById('manualTransactionModal').classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function recalculateManualItem(input) {
            const row = input.closest('.manual-item-row');
            if (!row) {
                return;
            }

            const qtyInput = row.querySelector('input[name="item_quantity[]"]');
            const costInput = row.querySelector('input[name="item_cost[]"]');
            const amountInput = row.querySelector('input[name="item_amount[]"]');
            const quantity = parseFloat(qtyInput?.value || '0');
            const cost = parseFloat(costInput?.value || '0');
            const amount = (quantity > 0 && cost > 0) ? quantity * cost : 0;

            amountInput.value = amount > 0 ? amount.toFixed(2) : '';
            recalculateManualSummary();
        }

        function recalculateManualSummary() {
            const amountInputs = document.querySelectorAll('.manual-item-amount');
            let total = 0;

            amountInputs.forEach(input => {
                const value = parseFloat(input.value || '0');
                if (!Number.isNaN(value)) {
                    total += value;
                }
            });

            const totalField = document.querySelector('.manual-total-amount');
            const downpaymentField = document.querySelector('.manual-downpayment');
            const balanceField = document.querySelector('.manual-balance');
            const downpayment = parseFloat(downpaymentField?.value || '0') || 0;
            const balance = Math.max(total - downpayment, 0);

            if (totalField) {
                totalField.value = total > 0 ? total.toFixed(2) : '';
            }
            if (balanceField) {
                balanceField.value = total > 0 ? balance.toFixed(2) : '';
            }
        }

        function addManualItemRow() {
            const container = document.getElementById('manualItemsContainer');
            const template = container.querySelector('.manual-item-row');
            if (!container || !template) {
                return;
            }

            const clone = template.cloneNode(true);
            clone.querySelectorAll('input').forEach(input => {
                if (input.type === 'button') {
                    return;
                }
                input.value = '';
            });
            clone.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0;
            });
            container.appendChild(clone);
            recalculateManualSummary();
        }

        function removeManualItemRow(button) {
            const row = button.closest('.manual-item-row');
            const container = document.getElementById('manualItemsContainer');
            if (!row || !container) {
                return;
            }

            if (container.querySelectorAll('.manual-item-row').length <= 1) {
                row.querySelectorAll('input').forEach(input => {
                    if (input.type !== 'button') {
                        input.value = '';
                    }
                });
                recalculateManualSummary();
                return;
            }

            row.remove();
            recalculateManualSummary();
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

        document.addEventListener('DOMContentLoaded', () => {
            filterTransactionGroups();
            recalculateManualSummary();
        });

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
