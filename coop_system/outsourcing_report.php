<?php 
session_start();
include 'db.php'; 

// --- AUTO-UPGRADE DATABASE SCHEMA FOR RECONCILIATION ---
$check_col = $conn->query("SHOW COLUMNS FROM inventory_outsourcing LIKE 'status'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE inventory_outsourcing ADD COLUMN status VARCHAR(50) DEFAULT 'COMPLETED' AFTER buyer_contact");
    $conn->query("ALTER TABLE inventory_outsourcing ADD COLUMN quantity_returned INT(11) DEFAULT 0 AFTER status");
}
// Automatically catch any new "Others" (Bazaars) dispatched from POS and flag them as PENDING
$conn->query("UPDATE inventory_outsourcing SET status = 'PENDING' WHERE payment_method = 'Others' AND status = 'COMPLETED' AND quantity_returned = 0");

function salesReportDateLabel($date) {
    return date('F d, Y', strtotime($date));
}

function normalizeReferenceNumber($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    // invoice_no is varchar(100) in the DB.
    if (strlen($value) > 100) {
        $value = substr($value, 0, 100);
    }
    return $value;
}

function normalizePaymentAmount($value) {
    $amount = (float)trim((string)$value);
    if (!is_finite($amount) || $amount < 0) {
        return 0.0;
    }
    return $amount;
}

if (isset($_GET['cancel_ref']) && $_GET['cancel_ref'] === '1') {
    unset($_SESSION['show_ref_modal'], $_SESSION['ref_transaction_id'], $_SESSION['ref_event_name'], $_SESSION['ref_event_date']);
    $_SESSION['alert_title'] = "Not Finalized";
    $_SESSION['alert_message'] = "The outsourced transaction remains <strong>PENDING</strong> until you provide a reference/invoice number.";
    $_SESSION['alert_type'] = "info";
    header("Location: outsourcing_report.php");
    exit();
}

if (isset($_GET['cancel_paylater']) && $_GET['cancel_paylater'] === '1') {
    unset($_SESSION['show_paylater_modal'], $_SESSION['paylater_transaction_id'], $_SESSION['paylater_member_name'], $_SESSION['paylater_date']);
    $_SESSION['alert_title'] = "Not Paid";
    $_SESSION['alert_message'] = "This Pay Later transaction remains <strong>PENDING</strong> until payment is received.";
    $_SESSION['alert_type'] = "info";
    header("Location: outsourcing_report.php");
    exit();
}

if (isset($_GET['paylater_txn'])) {
    $transaction_id = (int)$_GET['paylater_txn'];
    if ($transaction_id > 0) {
        $stmt = $conn->prepare("SELECT transaction_id, transaction_date, member_name, payment_status, remaining_balance FROM transactions WHERE transaction_id = ? AND transaction_type = 'PURCHASE' LIMIT 1");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $t = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($t && (float)($t['remaining_balance'] ?? 0) > 0) {
            $_SESSION['show_paylater_modal'] = 1;
            $_SESSION['paylater_transaction_id'] = (int)$t['transaction_id'];
            $_SESSION['paylater_member_name'] = $t['member_name'];
            $_SESSION['paylater_date'] = $t['transaction_date'];
        } else {
            $_SESSION['alert_title'] = "Not Payable";
            $_SESSION['alert_message'] = "This transaction is already paid or cannot be found.";
            $_SESSION['alert_type'] = "info";
        }
    }
    header("Location: outsourcing_report.php");
    exit();
}

// --- FINALIZE OUTSOURCED TRANSACTION PAYMENT (REF/INVOICE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalize_outsource_payment'])) {
    $transaction_id = (int)($_POST['transaction_id'] ?? 0);
    $reference_no = normalizeReferenceNumber($_POST['reference_no'] ?? '');

    $expected_id = (int)($_SESSION['ref_transaction_id'] ?? 0);
    if ($transaction_id <= 0 || $expected_id <= 0 || $transaction_id !== $expected_id) {
        $_SESSION['alert_title'] = "Invalid Request";
        $_SESSION['alert_message'] = "Unable to finalize this outsourced transaction. Please reconcile again and try once more.";
        $_SESSION['alert_type'] = "error";
        header("Location: outsourcing_report.php");
        exit();
    }

    if ($reference_no === '') {
        $_SESSION['alert_title'] = "Missing Reference";
        $_SESSION['alert_message'] = "Please enter a Reference Number or Invoice Number to finalize the outsourced transaction.";
        $_SESSION['alert_type'] = "error";
        $_SESSION['show_ref_modal'] = 1;
        header("Location: outsourcing_report.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE transactions SET invoice_no = ?, payment_status = 'COMPLETED', downpayment = amount, remaining_balance = 0 WHERE transaction_id = ? AND payment_status = 'PENDING' AND invoice_no = 'OUTSOURCED'");
    $stmt->bind_param("si", $reference_no, $transaction_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        $_SESSION['alert_title'] = "Already Finalized";
        $_SESSION['alert_message'] = "This outsourced transaction was already finalized (or cannot be matched).";
        $_SESSION['alert_type'] = "info";
        unset($_SESSION['show_ref_modal'], $_SESSION['ref_transaction_id'], $_SESSION['ref_event_name'], $_SESSION['ref_event_date']);
        header("Location: outsourcing_report.php");
        exit();
    }

    $_SESSION['alert_title'] = "Transaction Completed";
    $_SESSION['alert_message'] = "The outsourced transaction has been marked as <strong>COMPLETED</strong> and the reference/invoice number was saved.";
    $_SESSION['alert_type'] = "success";
    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            'SALES',
            'FINALIZE OUTSOURCE PAYMENT',
            'TRANSACTION',
            $transaction_id,
            $_SESSION['ref_event_name'] ?? '',
            'Reference/Invoice #' . $reference_no . ' saved and transaction marked completed.'
        );
    }
    unset($_SESSION['show_ref_modal'], $_SESSION['ref_transaction_id'], $_SESSION['ref_event_name'], $_SESSION['ref_event_date']);
    header("Location: outsourcing_report.php");
    exit();
}

// --- PROCESS RECONCILIATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reconcile_record_id'])) {
    $rec_id = (int)$_POST['reconcile_record_id'];
    $prod_id = (int)$_POST['product_id'];
    $qty_sold = (int)$_POST['qty_sold'];
    $qty_returned = (int)$_POST['qty_returned'];

    $log_row = null;
    $log_res = $conn->query("SELECT record_date, buyer_name, payment_method, status FROM inventory_outsourcing WHERE record_id = $rec_id LIMIT 1");
    if ($log_res && $log_res->num_rows > 0) {
        $log_row = $log_res->fetch_assoc();
    }

    if (!$log_row) {
        $_SESSION['alert_title'] = "Not Found";
        $_SESSION['alert_message'] = "Unable to locate the outsourcing record for reconciliation.";
        $_SESSION['alert_type'] = "error";
        header("Location: outsourcing_report.php");
        exit();
    }

    if ($log_row['status'] === 'RECONCILED') {
        $_SESSION['alert_title'] = "Already Reconciled";
        $_SESSION['alert_message'] = "This record is already reconciled.";
        $_SESSION['alert_type'] = "info";
        header("Location: outsourcing_report.php");
        exit();
    }

    $event_date = $log_row['record_date'];
    $event_name = $log_row['buyer_name'];
    $payment_method = $log_row['payment_method'];

    $conn->begin_transaction();
    try {
        // 1. Return unsold stock to the master inventory naturally!
        if ($qty_returned > 0) {
            $conn->query("UPDATE inventory SET current_quantity = current_quantity + $qty_returned WHERE product_id = $prod_id");
        }

        // 2. Update the log to RECONCILED with the true sold/returned numbers
        $conn->query("UPDATE inventory_outsourcing SET status = 'RECONCILED', quantity_out = $qty_sold, quantity_returned = $qty_returned WHERE record_id = $rec_id");

        // 3. If this outsourced event is fully reconciled, prompt for reference/invoice and update linked transaction.
        $should_prompt_reference = false;
        if ($payment_method === 'Others') {
            $stmt_pending = $conn->prepare("SELECT COUNT(*) as c FROM inventory_outsourcing WHERE payment_method = 'Others' AND buyer_name = ? AND record_date = ? AND status = 'PENDING'");
            $stmt_pending->bind_param("ss", $event_name, $event_date);
            $stmt_pending->execute();
            $pending_count = 0;
            $stmt_pending->bind_result($pending_count);
            $stmt_pending->fetch();
            $stmt_pending->close();

            if ((int)$pending_count === 0) {
                $stmt_trans = $conn->prepare("SELECT transaction_id FROM transactions WHERE transaction_type = 'OUTSOURCED' AND transaction_date = ? AND member_name = ? AND payment_status = 'PENDING' AND invoice_no = 'OUTSOURCED' ORDER BY transaction_id DESC LIMIT 1");
                $stmt_trans->bind_param("ss", $event_date, $event_name);
                $stmt_trans->execute();
                $stmt_trans->bind_result($found_transaction_id);
                if ($stmt_trans->fetch()) {
                    $_SESSION['show_ref_modal'] = 1;
                    $_SESSION['ref_transaction_id'] = (int)$found_transaction_id;
                    $_SESSION['ref_event_name'] = $event_name;
                    $_SESSION['ref_event_date'] = $event_date;
                    $should_prompt_reference = true;
                }
                $stmt_trans->close();
            }
        }

        $conn->commit();

        $_SESSION['alert_title'] = "Event Reconciled";
        $_SESSION['alert_message'] = "Stock has been successfully reconciled! <strong>{$qty_returned} items</strong> were returned to the master inventory.";
        $_SESSION['alert_type'] = "success";
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'INVENTORY',
                'RECONCILE OUTSOURCE',
                'OUTSOURCING RECORD',
                $rec_id,
                $event_name,
                'Product ID ' . $prod_id . ', Sold: ' . $qty_sold . ', Returned: ' . $qty_returned . ', Method: ' . $payment_method
            );
        }

        if ($payment_method === 'Others' && !$should_prompt_reference) {
            // We reconciled, but can't link to a pending outsourced transaction.
            $_SESSION['alert_message'] .= "<br><span class='text-xs text-gray-500'>Note: No matching pending outsourced transaction was found to finalize.</span>";
        }

        header("Location: outsourcing_report.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_title'] = "Reconciliation Failed";
        $_SESSION['alert_message'] = "An error occurred while reconciling this event. Please try again.";
        $_SESSION['alert_type'] = "error";
        header("Location: outsourcing_report.php");
        exit();
    }
}

// --- RECEIVE PAYMENT FOR PAY LATER TRANSACTION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receive_pay_later_payment'])) {
    $transaction_id = (int)($_POST['transaction_id'] ?? 0);
    $payment_amount = normalizePaymentAmount($_POST['payment_amount'] ?? 0);
    $reference_no = normalizeReferenceNumber($_POST['reference_no'] ?? '');

    $expected_id = (int)($_SESSION['paylater_transaction_id'] ?? 0);
    if ($transaction_id <= 0 || $expected_id <= 0 || $transaction_id !== $expected_id) {
        $_SESSION['alert_title'] = "Invalid Request";
        $_SESSION['alert_message'] = "Unable to receive payment for this transaction. Please try again from the payment button.";
        $_SESSION['alert_type'] = "error";
        header("Location: outsourcing_report.php");
        exit();
    }

    if ($payment_amount <= 0) {
        $_SESSION['alert_title'] = "Invalid Amount";
        $_SESSION['alert_message'] = "Please enter a valid payment amount.";
        $_SESSION['alert_type'] = "error";
        $_SESSION['show_paylater_modal'] = 1;
        header("Location: outsourcing_report.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT amount, downpayment, remaining_balance, payment_status FROM transactions WHERE transaction_id = ? LIMIT 1");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            throw new Exception("Transaction not found");
        }

        $current_status = strtoupper((string)($row['payment_status'] ?? ''));
        $remaining = (float)($row['remaining_balance'] ?? 0);
        $downpayment = (float)($row['downpayment'] ?? 0);
        $amount = (float)($row['amount'] ?? 0);

        if ($remaining <= 0 || $current_status === 'COMPLETED' || $current_status === 'PAID') {
            $_SESSION['alert_title'] = "Already Paid";
            $_SESSION['alert_message'] = "This transaction is already fully paid.";
            $_SESSION['alert_type'] = "info";
            unset($_SESSION['show_paylater_modal'], $_SESSION['paylater_transaction_id'], $_SESSION['paylater_member_name'], $_SESSION['paylater_date']);
            $conn->commit();
            header("Location: outsourcing_report.php");
            exit();
        }

        $apply = min($payment_amount, $remaining);
        $new_downpayment = $downpayment + $apply;
        $new_remaining = max(0, $remaining - $apply);

        if ($new_remaining > 0) {
            $stmt_upd = $conn->prepare("UPDATE transactions SET downpayment = ?, remaining_balance = ?, payment_status = 'DOWNPAYMENT' WHERE transaction_id = ? AND remaining_balance > 0");
            $stmt_upd->bind_param("ddi", $new_downpayment, $new_remaining, $transaction_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            $_SESSION['alert_title'] = "Downpayment Recorded";
            $_SESSION['alert_message'] = "This transaction will be marked as a downpayment. The invoice number or reference number will be added once the remaining balance has been fully paid.";
            $_SESSION['alert_type'] = "info";
            if (function_exists('logActivity')) {
                logActivity(
                    $conn,
                    'SALES',
                    'RECORD PAY LATER DOWNPAYMENT',
                    'TRANSACTION',
                    $transaction_id,
                    $row['member_name'] ?? ($_SESSION['paylater_member_name'] ?? ''),
                    'Payment received: ' . number_format($apply, 2) . ', Remaining balance: ' . number_format($new_remaining, 2)
                );
            }
        } else {
            if ($reference_no === '') {
                $_SESSION['alert_title'] = "Missing Reference";
                $_SESSION['alert_message'] = "Please enter a Reference Number or Invoice Number to complete this transaction.";
                $_SESSION['alert_type'] = "error";
                $_SESSION['show_paylater_modal'] = 1;
                $conn->commit();
                header("Location: outsourcing_report.php");
                exit();
            }

            $stmt_upd = $conn->prepare("UPDATE transactions SET invoice_no = ?, downpayment = ?, remaining_balance = 0, payment_status = 'COMPLETED' WHERE transaction_id = ? AND remaining_balance > 0");
            $stmt_upd->bind_param("sdi", $reference_no, $new_downpayment, $transaction_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            $_SESSION['alert_title'] = "Payment Completed";
            $_SESSION['alert_message'] = "Transaction marked as <strong>COMPLETED</strong> and reference/invoice saved.";
            $_SESSION['alert_type'] = "success";
            if (function_exists('logActivity')) {
                logActivity(
                    $conn,
                    'SALES',
                    'COMPLETE PAY LATER PAYMENT',
                    'TRANSACTION',
                    $transaction_id,
                    $row['member_name'] ?? ($_SESSION['paylater_member_name'] ?? ''),
                    'Payment received: ' . number_format($apply, 2) . ', Reference/Invoice #' . $reference_no . ' saved.'
                );
            }
        }

        $conn->commit();
        unset($_SESSION['show_paylater_modal'], $_SESSION['paylater_transaction_id'], $_SESSION['paylater_member_name'], $_SESSION['paylater_date']);
        header("Location: outsourcing_report.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_title'] = "Payment Failed";
        $_SESSION['alert_message'] = "An error occurred while receiving payment. Please try again.";
        $_SESSION['alert_type'] = "error";
        header("Location: outsourcing_report.php");
        exit();
    }
}

function salesReportQuantityText($row, $plain = false) {
    $status = $row['status'];

    if ($status === 'PENDING') {
        return $plain
            ? $row['quantity_out'] . ' taken'
            : "<span class='font-bold text-gray-800'>{$row['quantity_out']}</span> taken";
    }

    if ($status === 'RECONCILED') {
        $total_taken = (int)$row['quantity_out'] + (int)$row['quantity_returned'];
        return $plain
            ? "{$total_taken} Total | {$row['quantity_out']} Sold | {$row['quantity_returned']} Returned"
            : "<span class='text-gray-500'>{$total_taken} Total</span> | <span class='font-bold text-green-600'>{$row['quantity_out']} Sold</span> | <span class='font-bold text-blue-500'>{$row['quantity_returned']} Returned</span>";
    }

    return $plain
        ? $row['quantity_out'] . ' Sold'
        : "<span class='font-bold text-gray-800'>{$row['quantity_out']}</span> Sold";
}

$generated_at = date('F d, Y h:i A');
$report_rows = [];
$sql = "SELECT io.*, i.product_name 
        FROM inventory_outsourcing io 
        LEFT JOIN inventory i ON io.product_id = i.product_id 
        ORDER BY io.record_date DESC, io.record_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $report_rows[] = $row;
    }
}

$paylater_groups = [];
$paylater_transaction_map = [];
foreach ($report_rows as $row) {
    if (($row['payment_method'] ?? '') !== 'Pay Later') {
        continue;
    }
    $group_key = ($row['record_date'] ?? '') . '||' . ($row['buyer_name'] ?? '');
    if (!isset($paylater_groups[$group_key])) {
        $paylater_groups[$group_key] = [
            'record_date' => $row['record_date'],
            'buyer_name' => $row['buyer_name'],
            'items' => [],
        ];
    }
    $paylater_groups[$group_key]['items'][] = $row;
}

if (!empty($paylater_groups)) {
    foreach ($paylater_groups as $group_key => $g) {
        $date_key = $g['record_date'];
        $name_key = $g['buyer_name'];
        $stmt_t = $conn->prepare("SELECT transaction_id, amount, downpayment, remaining_balance, payment_status, invoice_no FROM transactions WHERE transaction_type = 'PURCHASE' AND transaction_date = ? AND member_name = ? ORDER BY transaction_id DESC LIMIT 1");
        $stmt_t->bind_param("ss", $date_key, $name_key);
        $stmt_t->execute();
        $res_t = $stmt_t->get_result();
        if ($res_t && ($trow = $res_t->fetch_assoc())) {
            $paylater_transaction_map[$group_key] = $trow;
        }
        $stmt_t->close();
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sales Report');

    $sheet->mergeCells('A1:E1');
    $sheet->mergeCells('A2:E2');
    $sheet->setCellValue('A1', 'Sales Report');
    $sheet->setCellValue('A2', 'Date Generated: ' . $generated_at);
    $sheet->getStyle('A:E')->getFont()->setName('Arial')->setSize(12);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $row_num = 4;
    $current_date = null;
    foreach ($report_rows as $row) {
        if ($current_date !== $row['record_date']) {
            $current_date = $row['record_date'];
            $sheet->mergeCells("A{$row_num}:E{$row_num}");
            $sheet->setCellValue("A{$row_num}", salesReportDateLabel($current_date));
            $sheet->getStyle("A{$row_num}:E{$row_num}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row_num}:E{$row_num}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFE7F7');
            $row_num++;

            $sheet->fromArray(['Event / Buyer Name', 'Product', 'Quantity Details', 'Status', 'Receipt No.'], null, "A{$row_num}");
            $sheet->getStyle("A{$row_num}:E{$row_num}")->getFont()->setBold(true);
            $row_num++;
        }

        $sheet->setCellValue("A{$row_num}", $row['buyer_name']);
        $sheet->setCellValue("B{$row_num}", $row['product_name']);
        $sheet->setCellValue("C{$row_num}", salesReportQuantityText($row, true));
        $sheet->setCellValue("D{$row_num}", $row['status']);
        $sheet->setCellValue("E{$row_num}", $row['receipt_no']);
        $row_num++;
    }

    foreach (range('A', 'E') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Sales_Report_' . date('Y-m-d') . '.xlsx';
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
    <title>Outsourcing & Events - Coop DBMS</title>
    
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
        .sales-print-header { display: none; }
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
            .sales-print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 16px;
                color: #111827;
            }
            .sales-print-title {
                font-size: 20px !important;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .sales-print-date {
                font-size: 14px !important;
                margin-bottom: 14px;
            }
            #salesReportCard {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }
            #salesReportTable {
                width: 100% !important;
                border-collapse: collapse !important;
                white-space: normal !important;
                font-family: Arial, sans-serif !important;
                font-size: 12px !important;
            }
            #salesReportTable th,
            #salesReportTable td {
                border: 1px solid #d1d5db !important;
                padding: 5px 6px !important;
                font-size: 12px !important;
            }
            #salesReportTable thead th {
                background: #f3f4f6 !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #salesReportTable .date-header td {
                background: #e5e7eb !important;
                color: #111827 !important;
                border-color: #9ca3af !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .status-badge-print {
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

    <div id="reconcileModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeReconcileModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden transform transition-all">
            <div class="bg-blue-50 px-6 py-4 border-b border-blue-100 flex justify-between items-center">
                <h3 class="font-bold text-blue-800"><i class="fas fa-clipboard-check mr-2"></i>Reconcile Event Stock</h3>
                <button onclick="closeReconcileModal()" class="text-blue-400 hover:text-blue-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="outsourcing_report.php" method="POST" class="p-6">
                <input type="hidden" name="reconcile_record_id" id="rec_record_id">
                <input type="hidden" name="product_id" id="rec_product_id">
                <input type="hidden" id="rec_total_val">
                
                <div class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Product Dispatched</div>
                    <div class="font-bold text-gray-800 text-lg capitalize" id="rec_product_name"></div>
                    <div class="mt-2 text-sm text-gray-600">Total Items Taken: <span id="rec_total_qty" class="font-black text-primary"></span></div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-green-700 mb-1">Items Sold</label>
                        <input type="number" name="qty_sold" id="qty_sold" required min="0" oninput="calculateReturn()" class="w-full rounded-md border-2 border-green-300 px-4 py-3 text-xl font-bold focus:outline-none focus:border-green-500 text-center bg-green-50 text-green-900 shadow-inner">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-blue-700 mb-1">Items Returned</label>
                        <input type="number" name="qty_returned" id="qty_returned" required readonly class="w-full rounded-md border-2 border-blue-300 px-4 py-3 text-xl font-bold focus:outline-none bg-blue-50 text-blue-900 text-center cursor-not-allowed shadow-inner">
                    </div>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeReconcileModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md"><i class="fas fa-check mr-1"></i> FINALIZE RETURN</button>
                </div>
            </form>
        </div>
    </div>

    <div id="referenceModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="cancelReferenceModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden transform transition-all">
            <div class="bg-green-50 px-6 py-4 border-b border-green-100 flex justify-between items-center">
                <h3 class="font-bold text-green-800"><i class="fas fa-receipt mr-2"></i>Finalize Outsourced Transaction</h3>
                <button type="button" onclick="cancelReferenceModal()" class="text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
            </div>

            <form action="outsourcing_report.php" method="POST" class="p-6">
                <input type="hidden" name="finalize_outsource_payment" value="1">
                <input type="hidden" name="transaction_id" value="<?= (int)($_SESSION['ref_transaction_id'] ?? 0) ?>">

                <div class="mb-5 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Outsourced Event</div>
                    <div class="font-bold text-gray-800 text-lg capitalize"><?= htmlspecialchars($_SESSION['ref_event_name'] ?? 'N/A') ?></div>
                    <div class="mt-1 text-xs text-gray-500">Date: <?= htmlspecialchars($_SESSION['ref_event_date'] ?? '') ?></div>
                </div>

                <div class="mb-7">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Reference Number / Invoice Number <span class="text-red-500">*</span></label>
                    <input type="text" name="reference_no" required maxlength="100" placeholder="Enter reference or invoice number" class="w-full rounded-md border border-gray-300 px-4 py-3 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                    <p class="text-xs text-gray-400 mt-1 italic">This will be saved to the transaction and marked as COMPLETED.</p>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="cancelReferenceModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md"><i class="fas fa-check mr-1"></i> SAVE & COMPLETE</button>
                </div>
            </form>
        </div>
    </div>

    <div id="payLaterPaymentModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="cancelPayLaterModal()"></div>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden transform transition-all">
            <div class="bg-purple-50 px-6 py-4 border-b border-purple-100 flex justify-between items-center">
                <h3 class="font-bold text-primaryDark"><i class="fas fa-money-bill-wave mr-2"></i>Receive Payment (Pay Later)</h3>
                <button type="button" onclick="cancelPayLaterModal()" class="text-purple-400 hover:text-purple-600"><i class="fas fa-times"></i></button>
            </div>

            <form action="outsourcing_report.php" method="POST" class="p-6">
                <input type="hidden" name="receive_pay_later_payment" value="1">
                <input type="hidden" name="transaction_id" value="<?= (int)($_SESSION['paylater_transaction_id'] ?? 0) ?>">

                <div class="mb-5 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Member</div>
                    <div class="font-bold text-gray-800 text-lg capitalize"><?= htmlspecialchars($_SESSION['paylater_member_name'] ?? 'N/A') ?></div>
                    <div class="mt-1 text-xs text-gray-500">Date: <?= htmlspecialchars($_SESSION['paylater_date'] ?? '') ?></div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Payment Amount</label>
                        <input type="number" step="0.01" min="0" name="payment_amount" required class="w-full rounded-md border border-gray-300 px-4 py-3 text-base font-bold focus:outline-none focus:ring-2 focus:ring-primary bg-white text-gray-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Invoice / Ref No.</label>
                        <input type="text" name="reference_no" maxlength="100" placeholder="Required if fully paid" class="w-full rounded-md border border-gray-300 px-4 py-3 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-primary bg-white text-gray-900">
                    </div>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="cancelPayLaterModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md"><i class="fas fa-check mr-1"></i> APPLY PAYMENT</button>
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
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-receipt w-6"></i> TRANSACTIONS
                </a>
                <a href="inventory.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-boxes w-6"></i> INVENTORY
                </a>
                <a href="pos.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-shopping-cart w-6"></i> SELL / OUTSOURCE
                </a>
                <a href="outsourcing_report.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight"> Outsourcing and Sales Logs</h1>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">

                <?php
                // Fetch Dashboard Stats
                $stat_pending = $conn->query("SELECT COUNT(*) as c FROM inventory_outsourcing WHERE status = 'PENDING'")->fetch_assoc()['c'];
                $stat_reconciled = $conn->query("SELECT COUNT(*) as c FROM inventory_outsourcing WHERE status = 'RECONCILED'")->fetch_assoc()['c'];
                $stat_total = $conn->query("SELECT SUM(quantity_out) as c FROM inventory_outsourcing WHERE status != 'PENDING'")->fetch_assoc()['c'];
                if (!$stat_total) $stat_total = 0;
                ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 print:hidden">
                    <div class="bg-white rounded-xl shadow-sm border border-orange-200 p-6 flex items-center justify-between border-l-4 border-l-orange-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Pending Returns</div>
                            <div class="text-3xl font-black <?= $stat_pending > 0 ? 'text-orange-600' : 'text-gray-800' ?>"><?= $stat_pending ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-500 text-xl"><i class="fas fa-clock"></i></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-6 flex items-center justify-between border-l-4 border-l-blue-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Reconciled Events</div>
                            <div class="text-3xl font-black text-gray-800"><?= $stat_reconciled ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl"><i class="fas fa-clipboard-check"></i></div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm border border-green-200 p-6 flex items-center justify-between border-l-4 border-l-green-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Total Sold Items</div>
                            <div class="text-3xl font-black text-gray-800"><?= number_format($stat_total) ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl"><i class="fas fa-boxes"></i></div>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4 print:hidden">
                    
                    <div class="flex w-full lg:w-1/3 bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary shadow-sm">
                        <div class="px-3 py-2 text-gray-400 flex items-center justify-center"><i class="fas fa-search"></i></div>
                        <input type="text" id="logSearch" placeholder="Search events, products, buyers..." class="w-full py-2 pr-4 outline-none text-sm text-gray-700 bg-transparent">
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto items-center">
                        <div class="flex items-center gap-2 w-full sm:w-auto bg-white border border-gray-300 rounded-lg px-3 py-1 shadow-sm">
                            <i class="fas fa-calendar-alt text-gray-400"></i>
                            <input type="date" id="dateFilterStart" class="outline-none text-sm text-gray-700 bg-transparent cursor-pointer">
                            <span class="text-gray-400 text-xs">to</span>
                            <input type="date" id="dateFilterEnd" class="outline-none text-sm text-gray-700 bg-transparent cursor-pointer">
                            <button onclick="clearDateFilter()" class="text-gray-400 hover:text-red-500 transition-colors ml-1" title="Clear Date Filter"><i class="fas fa-times-circle"></i></button>
                        </div>
                        
                        <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300 w-full sm:w-auto whitespace-nowrap">
                            <i class="fas fa-print mr-2"></i>PRINT REPORT
                        </button>
                        <a href="outsourcing_report.php?export=excel" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap text-center">
                            <i class="fas fa-file-excel mr-2"></i>EXCEL
                        </a>
                    </div>
                </div>

                <div class="sales-print-header">
                    <div class="sales-print-title">Sales Report</div>
                    <div class="sales-print-date">Date Generated: <?= htmlspecialchars($generated_at) ?></div>
                </div>

                <div id="salesReportCard" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="salesReportTable" class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 font-bold tracking-wider">Date</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Event / Buyer Name</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Product Taken</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-center">Status</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-right print:hidden">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="logTableBody">
                                <?php
                                if (!empty($report_rows)) {
                                    $current_date = null;
                                    $printed_paylater = [];
                                    foreach ($report_rows as $row) {
                                         
                                        $raw_date = $row['record_date'];
                                        $date = date('M d, Y', strtotime($raw_date));
                                        $name = htmlspecialchars($row['buyer_name']);
                                        $product = htmlspecialchars($row['product_name']);
                                        $status = $row['status'];
                                        $method = $row['payment_method'] ?? '';

                                        if ($method === 'Pay Later') {
                                            $group_key = $raw_date . '||' . ($row['buyer_name'] ?? '');
                                            if (isset($printed_paylater[$group_key])) {
                                                continue;
                                            }
                                            $printed_paylater[$group_key] = true;

                                            $trow = $paylater_transaction_map[$group_key] ?? null;
                                            $t_status = strtoupper((string)($trow['payment_status'] ?? 'PENDING'));
                                            $t_amount = (float)($trow['amount'] ?? 0);
                                            $t_down = (float)($trow['downpayment'] ?? 0);
                                            $t_bal = (float)($trow['remaining_balance'] ?? $t_amount);
                                            $t_ref = $trow['invoice_no'] ?? '';
                                            $t_id = (int)($trow['transaction_id'] ?? 0);

                                            if ($t_bal > 0 && $t_down > 0) {
                                                $t_status = 'DOWNPAYMENT';
                                            } elseif ($t_bal <= 0 && ($t_status === '' || $t_status === 'PENDING' || $t_status === 'DOWNPAYMENT')) {
                                                $t_status = 'COMPLETED';
                                            } elseif ($t_status === '') {
                                                $t_status = 'PENDING';
                                            }

                                            if ($t_status === 'COMPLETED' || $t_status === 'PAID') {
                                                $badge = "<span class='status-badge-print bg-green-100 text-green-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-green-200'>PAID</span>";
                                                $action_btn = "<span class='text-gray-300 text-xs'><i class='fas fa-check'></i></span>";
                                            } elseif ($t_status === 'DOWNPAYMENT') {
                                                $badge = "<span class='status-badge-print bg-yellow-100 text-yellow-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-yellow-200'>DOWNPAYMENT</span>";
                                                $action_btn = ($t_id > 0)
                                                    ? "<a href='outsourcing_report.php?paylater_txn={$t_id}' class='bg-primary text-white hover:bg-primaryDark py-1 px-3 rounded text-xs font-bold transition-colors shadow-sm inline-block'><i class='fas fa-hand-holding-usd mr-1'></i> PAY</a>"
                                                    : "<span class='text-gray-300 text-xs'><i class='fas fa-ban'></i></span>";
                                            } else {
                                                $badge = "<span class='status-badge-print bg-red-100 text-red-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-red-200'>PENDING</span>";
                                                $action_btn = ($t_id > 0)
                                                    ? "<a href='outsourcing_report.php?paylater_txn={$t_id}' class='bg-primary text-white hover:bg-primaryDark py-1 px-3 rounded text-xs font-bold transition-colors shadow-sm inline-block'><i class='fas fa-hand-holding-usd mr-1'></i> PAY</a>"
                                                    : "<span class='text-gray-300 text-xs'><i class='fas fa-ban'></i></span>";
                                            }

                                            $items = $paylater_groups[$group_key]['items'] ?? [];
                                            $items_lines = [];
                                            foreach ($items as $it) {
                                                $pn = htmlspecialchars($it['product_name']);
                                                $qt = (int)$it['quantity_out'];
                                                $items_lines[] = "{$qt}x {$pn}";
                                            }
                                            $items_summary = !empty($items_lines) ? implode("<br>", $items_lines) : "Pay Later Purchase";

                                            $pay_summary = "<span class='text-gray-500'>Total: </span><span class='font-bold text-gray-900'>PHP " . number_format($t_amount, 2) . "</span>"
                                                . "<span class='text-gray-300 mx-1'>|</span><span class='text-gray-500'>Downpayment: </span><span class='font-bold text-gray-900'>PHP " . number_format($t_down, 2) . "</span>"
                                                . "<span class='text-gray-300 mx-1'>|</span><span class='text-gray-500'>Balance: </span><span class='font-bold text-red-600'>PHP " . number_format($t_bal, 2) . "</span>";

                                            $ref_line = ($t_ref && $t_ref !== 'N/A' && $t_ref !== 'OUTSOURCED') ? "<div class='text-[10px] font-mono text-gray-500 mt-1'>REF: " . htmlspecialchars($t_ref) . "</div>" : "";

                                            echo "<tr class='log-row hover:bg-purple-50 transition-colors' data-date='{$raw_date}'>
                                                    <td class='px-6 py-4 font-medium text-gray-500'>{$date}</td>
                                                    <td class='px-6 py-4 font-bold text-gray-900 capitalize'>{$name}</td>
                                                    <td class='px-6 py-4 text-gray-700'>
                                                        <div class='font-bold text-primary'>PAY LATER</div>
                                                        <div class='text-xs mt-0.5'>{$items_summary}</div>
                                                        <div class='text-xs mt-2'>{$pay_summary}</div>
                                                        {$ref_line}
                                                    </td>
                                                    <td class='px-6 py-4 text-center'>{$badge}</td>
                                                    <td class='px-6 py-4 text-right print:hidden'>{$action_btn}</td>
                                                  </tr>";
                                            continue;
                                        }

                                        if ($current_date !== $raw_date) {
                                            $current_date = $raw_date;
                                            echo "<tr class='date-header bg-purple-100/60' data-date='{$raw_date}'>
                                                    <td colspan='5' class='px-6 py-2.5 font-black text-primaryDark uppercase text-sm tracking-widest border-y border-purple-200'>
                                                        <i class='fas fa-calendar-day mr-2 opacity-50'></i>" . salesReportDateLabel($raw_date) . "
                                                    </td>
                                                  </tr>";
                                        }
                                        
                                        if ($method === 'Pay Later') {
                                            // Pay Later rows are rendered once per group above.
                                            continue;
                                        }
 
                                        if ($status === 'PENDING') {
                                            $badge = "<span class='status-badge-print bg-orange-100 text-orange-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-orange-200'>PENDING RETURN</span>";
                                            $qty_text = salesReportQuantityText($row);
                                            $action_btn = "<button onclick='openReconcileModal({$row['record_id']}, {$row['product_id']}, \"" . addslashes($product) . "\", {$row['quantity_out']})' class='bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-600 hover:text-white py-1 px-3 rounded text-xs font-bold transition-colors shadow-sm'><i class='fas fa-clipboard-check mr-1'></i> RECONCILE</button>";
                                            $row_bg = "bg-orange-50/20";
                                        } else if ($status === 'RECONCILED') {
                                            $badge = "<span class='status-badge-print bg-blue-100 text-blue-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-blue-200'>RECONCILED</span>";
                                            $qty_text = salesReportQuantityText($row);
                                            $action_btn = "<span class='text-gray-300 text-xs'><i class='fas fa-check'></i></span>";
                                            $row_bg = "";
                                        } else {
                                            $badge = "<span class='status-badge-print bg-green-100 text-green-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-green-200'>COMPLETED</span>";
                                            $qty_text = salesReportQuantityText($row);
                                            $action_btn = "<span class='text-gray-300 text-xs'><i class='fas fa-check'></i></span>";
                                            $row_bg = "";
                                        }

                                        echo "<tr class='log-row hover:bg-purple-50 transition-colors {$row_bg}' data-date='{$raw_date}'>
                                                <td class='px-6 py-4 font-medium text-gray-500'>{$date}</td>
                                                <td class='px-6 py-4 font-bold text-gray-900 capitalize'>{$name}</td>
                                                <td class='px-6 py-4 text-gray-700'>
                                                    <div class='font-bold text-primary'>{$product}</div>
                                                    <div class='text-xs mt-0.5'>{$qty_text}</div>
                                                </td>
                                                <td class='px-6 py-4 text-center'>{$badge}</td>
                                                <td class='px-6 py-4 text-right print:hidden'>{$action_btn}</td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='px-6 py-12 text-center text-gray-500'>No outsourcing or event logs found.</td></tr>";
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

        function openReferenceModal() {
            const modal = document.getElementById('referenceModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            const input = modal.querySelector('input[name="reference_no"]');
            if (input) input.focus();
        }

function cancelReferenceModal() {
    window.location.href = 'outsourcing_report.php?cancel_ref=1';
}

function openPayLaterModal() {
    const modal = document.getElementById('payLaterPaymentModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const input = modal.querySelector('input[name="payment_amount"]');
    if (input) input.focus();
}

function cancelPayLaterModal() {
    window.location.href = 'outsourcing_report.php?cancel_paylater=1';
}

        // --- UNIFIED SEARCH & DATE FILTER LOGIC ---
        function filterTable() {
            let searchText = document.getElementById('logSearch').value.toLowerCase();
            let startDate = document.getElementById('dateFilterStart').value;
            let endDate = document.getElementById('dateFilterEnd').value;
            let rows = document.querySelectorAll('#logTableBody tr');
            let currentHeader = null;
            let visibleItemsUnderHeader = 0;

            rows.forEach(row => {
                if (row.classList.contains('date-header')) {
                    if (currentHeader !== null) {
                        currentHeader.style.display = visibleItemsUnderHeader > 0 ? '' : 'none';
                    }
                    currentHeader = row;
                    visibleItemsUnderHeader = 0;
                    row.style.display = '';
                    return;
                }

                if (!row.classList.contains('log-row')) {
                    return;
                }

                let textMatch = row.textContent.toLowerCase().includes(searchText);
                let dateMatch = true;

                if (startDate || endDate) {
                    let rowDateStr = row.dataset.date; // e.g. "2024-05-20"
                    
                    if (rowDateStr) {
                        let rowDate = new Date(rowDateStr);
                        rowDate.setHours(0,0,0,0);
                        
                        if (startDate) {
                            let sDate = new Date(startDate);
                            sDate.setHours(0,0,0,0);
                            if (rowDate < sDate) dateMatch = false;
                        }
                        
                        if (endDate) {
                            let eDate = new Date(endDate);
                            eDate.setHours(0,0,0,0);
                            if (rowDate > eDate) dateMatch = false;
                        }
                    }
                }

                if (textMatch && dateMatch) {
                    row.style.display = '';
                    visibleItemsUnderHeader++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (currentHeader !== null) {
                currentHeader.style.display = visibleItemsUnderHeader > 0 ? '' : 'none';
            }
        }

        document.getElementById('logSearch').addEventListener('keyup', filterTable);
        document.getElementById('dateFilterStart').addEventListener('change', filterTable);
        document.getElementById('dateFilterEnd').addEventListener('change', filterTable);

        function clearDateFilter() {
            document.getElementById('dateFilterStart').value = '';
            document.getElementById('dateFilterEnd').value = '';
            filterTable();
        }

        // --- RECONCILE MODAL LOGIC ---
        function openReconcileModal(recordId, productId, productName, totalQty) {
            document.getElementById('rec_record_id').value = recordId;
            document.getElementById('rec_product_id').value = productId;
            document.getElementById('rec_product_name').innerText = productName;
            document.getElementById('rec_total_qty').innerText = totalQty;
            document.getElementById('rec_total_val').value = totalQty;
            
            document.getElementById('qty_sold').value = totalQty;
            document.getElementById('qty_returned').value = 0;
            
            document.getElementById('reconcileModal').classList.remove('hidden');
            document.getElementById('reconcileModal').classList.add('flex');
        }

        function closeReconcileModal() {
            document.getElementById('reconcileModal').classList.add('hidden');
            document.getElementById('reconcileModal').classList.remove('flex');
        }

        function calculateReturn() {
            let total = parseInt(document.getElementById('rec_total_val').value);
            let soldInput = document.getElementById('qty_sold');
            let sold = parseInt(soldInput.value) || 0;
            
            if(sold > total) {
                soldInput.value = total;
                sold = total;
            }
            if(sold < 0) {
                soldInput.value = 0;
                sold = 0;
            }
            document.getElementById('qty_returned').value = total - sold;
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

        <?php if (!empty($_SESSION['show_ref_modal']) && !empty($_SESSION['ref_transaction_id'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                openReferenceModal();
            });
        <?php endif; ?>

        <?php if (!empty($_SESSION['show_paylater_modal']) && !empty($_SESSION['paylater_transaction_id'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                openPayLaterModal();
            });
        <?php endif; ?>
    </script>
</body>
</html>
