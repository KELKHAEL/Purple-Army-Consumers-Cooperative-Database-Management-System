<?php 
session_start();
include 'db.php'; 

// Fetch active members and configurable payment types.
$members = [];
$mem_list = $conn->query("SELECT member_id, last_name, first_name, middle_name FROM members ORDER BY last_name ASC, first_name ASC");
if ($mem_list) {
    while ($m = $mem_list->fetch_assoc()) {
        $members[] = $m;
    }
}

$share_payment_types = function_exists('getSharePaymentTypes') ? getSharePaymentTypes($conn, true) : [];
if (empty($share_payment_types) && function_exists('getSharePaymentTypes')) {
    $share_payment_types = getSharePaymentTypes($conn, false);
}

function getInventoryReceiptSetting(mysqli $conn, string $setting_key, string $default = ''): string {
    $stmt = $conn->prepare("SELECT setting_value FROM config_inventory_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param("s", $setting_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $value = trim((string)($row['setting_value'] ?? ''));
    return $value !== '' ? $value : $default;
}

$receipt_treasurer_name = getInventoryReceiptSetting($conn, 'receipt_treasurer_name', 'HELENA GESTA');
$receipt_manager_name = getInventoryReceiptSetting($conn, 'receipt_manager_name', 'VRIAN ANDREW B. PORTUGUESE');

// Handle manual share entry.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_share_record'])) {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $share_type_id = (int)($_POST['share_payment_type_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $share_date = !empty($_POST['share_date']) ? $_POST['share_date'] : date('Y-m-d');
    $invoice_no = trim((string)($_POST['invoice_no'] ?? ''));

    $stmt_member = $conn->prepare("SELECT last_name, first_name, middle_name FROM members WHERE member_id = ? LIMIT 1");
    $stmt_member->bind_param("i", $member_id);
    $stmt_member->execute();
    $member_res = $stmt_member->get_result();
    $member_row = $member_res ? $member_res->fetch_assoc() : null;
    $stmt_member->close();

    $stmt_type = $conn->prepare("SELECT id, name FROM config_share_payment_types WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt_type->bind_param("i", $share_type_id);
    $stmt_type->execute();
    $type_res = $stmt_type->get_result();
    $type_row = $type_res ? $type_res->fetch_assoc() : null;
    $stmt_type->close();

    if (!$member_row || !$type_row || $amount <= 0 || empty($share_date) || $invoice_no === '') {
        $_SESSION['alert_title'] = "Invalid Entry";
        $_SESSION['alert_message'] = "Please select a member, payment type, enter a valid amount, and provide a Reference No. / Invoice No. / Receipt No.";
        $_SESSION['alert_type'] = "error";
        header("Location: member_shares.php");
        exit();
    }

    $member_name = trim($member_row['last_name'] . ', ' . $member_row['first_name'] . ' ' . ($member_row['middle_name'] ?? ''));
    $member_name = preg_replace('/\s+/', ' ', trim($member_name));
    $items_details = 'Manual payment entry for ' . $type_row['name'];
    $payment_status = 'COMPLETED';
    $share_payment_type_id = (int)$type_row['id'];
    $transaction_type = $type_row['name'];

    $stmt = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, share_payment_type_id, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
    $stmt->bind_param("sissidsss", $share_date, $member_id, $member_name, $transaction_type, $share_payment_type_id, $amount, $items_details, $invoice_no, $payment_status);

    if ($stmt->execute()) {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'ADD MEMBER SHARE',
                'TRANSACTION',
                $conn->insert_id,
                $member_name,
                'Payment Type: ' . $type_row['name'] . ', Amount: ' . number_format($amount, 2) . ', Date: ' . $share_date
            );
        }
        $_SESSION['alert_title'] = "Share Added";
        $_SESSION['alert_message'] = "The member share record was saved successfully.";
        $_SESSION['alert_type'] = "success";
        $_SESSION['show_share_receipt_print'] = 1;
        $_SESSION['share_receipt_transaction_id'] = (int)$conn->insert_id;
    } else {
        $_SESSION['alert_title'] = "Database Error";
        $_SESSION['alert_message'] = "Unable to save the member share record.";
        $_SESSION['alert_type'] = "error";
    }
    $stmt->close();

    header("Location: member_shares.php");
    exit();
}

$share_rows = [];
$share_sql = "SELECT t.*, COALESCE(pt.name, t.transaction_type) AS share_type_name
              FROM transactions t
              LEFT JOIN config_share_payment_types pt ON t.share_payment_type_id = pt.id
              WHERE t.share_payment_type_id IS NOT NULL
                 OR t.transaction_type IN ('Share Capital', 'Membership Fee', 'SHARE')
              ORDER BY t.transaction_date DESC, t.transaction_id DESC";
$share_result = $conn->query($share_sql);
if ($share_result) {
    while ($row = $share_result->fetch_assoc()) {
        $share_rows[] = $row;
    }
}

$share_receipt_payloads = [];
foreach ($share_rows as $row) {
    $share_receipt_payloads[(int)$row['transaction_id']] = [
        'transaction_date' => (string)($row['transaction_date'] ?? ''),
        'member_name' => (string)($row['member_name'] ?? ''),
        'transaction_type' => (string)($row['share_type_name'] ?? $row['transaction_type'] ?? ''),
        'invoice_no' => (string)($row['invoice_no'] ?? ''),
        'amount' => (float)($row['amount'] ?? 0),
        'payment_status' => (string)($row['payment_status'] ?? 'COMPLETED'),
        'treasurer_name' => $receipt_treasurer_name,
        'manager_name' => $receipt_manager_name,
    ];
}

// Fetch Dashboard Stats specifically for Shares and Membership Fees
$total_share_capital = 0;
$total_membership_fees = 0;
$contributors = [];
foreach ($share_rows as $row) {
    $display_type = strtolower((string)($row['share_type_name'] ?? $row['transaction_type'] ?? ''));
    $status = strtolower((string)($row['payment_status'] ?? ''));
    if ($status === 'completed' || strpos($status, 'paid') !== false) {
        if (strpos($display_type, 'share') !== false) {
            $total_share_capital += (float)$row['amount'];
        } elseif (strpos($display_type, 'fee') !== false) {
            $total_membership_fees += (float)$row['amount'];
        }
    }
    if (!empty($row['member_id'])) {
        $contributors[(int)$row['member_id']] = true;
    }
}

$total_contributors = count($contributors);

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Member Shares');

    $sheet->mergeCells('A1:F1');
    $sheet->mergeCells('A2:F2');
    $sheet->setCellValue('A1', 'Member Shares & Fees');
    $sheet->setCellValue('A2', 'Exported: ' . date('F d, Y h:i A'));
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A:F')->getFont()->setName('Arial')->setSize(12);

    $headers = ['Date', 'Ref / Invoice', 'Member Name', 'Transaction Type', 'Amount (PHP)', 'Status'];
    $sheet->fromArray($headers, null, 'A4');
    $sheet->getStyle('A4:F4')->getFont()->setBold(true);
    $sheet->getStyle('A4:F4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3E8FF');

    $row_num = 5;
    foreach ($share_rows as $row) {
        $sheet->setCellValue("A{$row_num}", $row['transaction_date']);
        $sheet->setCellValue("B{$row_num}", $row['invoice_no'] ?: 'N/A');
        $sheet->setCellValue("C{$row_num}", $row['member_name']);
        $sheet->setCellValue("D{$row_num}", $row['share_type_name'] ?? $row['transaction_type']);
        $sheet->setCellValue("E{$row_num}", (float)$row['amount']);
        $sheet->setCellValue("F{$row_num}", $row['payment_status'] ?: 'COMPLETED');
        $row_num++;
    }

    $sheet->getStyle("E5:E{$row_num}")->getNumberFormat()->setFormatCode('#,##0.00');
    foreach (range('A', 'F') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Member_Shares_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

if (isset($_GET['template']) && $_GET['template'] === 'excel') {
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Share Import Template');

    $sheet->mergeCells('A1:H1');
    $sheet->mergeCells('A2:H2');
    $sheet->mergeCells('A3:H3');
    $sheet->setCellValue('A1', 'Member Shares Import Template');
    $sheet->setCellValue('A2', 'Required column: Reference No. / Invoice No. / Receipt No.');
    $sheet->setCellValue('A3', 'Replace the sample row with your own entries before importing.');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:H3')->getFont()->setName('Arial')->setSize(12);

    $headers = [
        'Date',
        'Reference No. / Invoice No. / Receipt No.',
        'First Name',
        'Second Name',
        'Middle Name',
        'Last Name',
        'Transaction Type',
        'Amount'
    ];
    $sheet->fromArray($headers, null, 'A5');
    $sheet->getStyle('A5:H5')->getFont()->setBold(true);
    $sheet->getStyle('A5:H5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3E8FF');

    $sheet->fromArray([
        [date('Y-m-d'), 'REF-0001', 'Juan', '', 'D.', 'Cruz', 'Share Capital', 100.00]
    ], null, 'A6');
    $sheet->getStyle('H6')->getNumberFormat()->setFormatCode('#,##0.00');

    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Member_Shares_Import_Template.xlsx';
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
    <title>Member Shares - Coop DBMS</title>
    
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
        .share-print-header { display: none; }
        .share-receipt-sheet {
            width: min(210mm, calc(100vw - 2rem));
            min-height: 0;
        }
        #shareModal input[type="text"],
        #shareModal select,
        #shareModal option,
        #shareTypeFilter,
        #shareTypeFilter option,
        #shareReportTable td {
            text-transform: uppercase;
        }
        @media print {
            @page { size: A4; margin: 10mm; }
            html, body {
                background: #ffffff !important;
                overflow: visible !important;
                height: auto !important;
                font-family: Arial, sans-serif !important;
                font-size: 11px !important;
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
            }
            .share-print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 16px;
                color: #111827;
            }
            .share-print-title {
                font-size: 20px !important;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .share-print-meta {
                font-size: 13px !important;
                margin-bottom: 6px;
            }
            #shareReportCard {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }
            #shareReportTable {
                width: 100% !important;
                border-collapse: collapse !important;
                white-space: normal !important;
                font-family: Arial, sans-serif !important;
                font-size: 12px !important;
            }
            #shareReportTable th,
            #shareReportTable td {
                border: 1px solid #d1d5db !important;
                padding: 5px 6px !important;
                font-size: 12px !important;
            }
            #shareReportTable thead th {
                background: #f3f4f6 !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            body.share-receipt-print-mode > *:not(#shareReceiptModal) {
                display: none !important;
            }
            body.share-receipt-print-mode #shareReceiptModal {
                display: block !important;
                position: static !important;
                inset: auto !important;
                padding: 0 !important;
                background: #fff !important;
            }
            body.share-receipt-print-mode #shareReceiptModal .share-receipt-shell {
                box-shadow: none !important;
                border: none !important;
                max-width: none !important;
                width: 100% !important;
                margin: 0 !important;
            }
            body.share-receipt-print-mode #shareReceiptModal .share-receipt-toolbar {
                display: none !important;
            }
            body.share-receipt-print-mode #shareReceiptModal .share-receipt-scrollwrap {
                padding: 0 !important;
                overflow: visible !important;
            }
            body.share-receipt-print-mode #shareReceiptSheet {
                width: 100% !important;
                min-height: 0 !important;
                padding: 6px 8px !important;
                margin: 0 auto !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.share-receipt-print-mode #shareReceiptSheet .share-receipt-signatories {
                margin-top: 2in !important;
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
            <div class="p-6 text-gray-600 text-sm leading-relaxed" id="customAlertMessage"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button id="customAlertBtn" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">OK</button>
            </div>
        </div>
    </div>

    <div id="shareReceiptModal" class="fixed inset-0 z-[1001] hidden items-start justify-center p-4 md:p-6 bg-gray-900/60 backdrop-blur-sm print:hidden overflow-y-auto">
        <div class="share-receipt-shell bg-white rounded-2xl shadow-2xl overflow-hidden mt-4 md:mt-8 w-full max-w-[calc(100vw-2rem)]">
            <div class="share-receipt-toolbar flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 bg-gray-50">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-gray-500">Printable Receipt</div>
                    <div class="font-bold text-gray-800">Member Share Receipt</div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printShareReceipt()" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                        <i class="fas fa-print mr-2"></i>PRINT
                    </button>
                    <button type="button" onclick="closeShareReceiptPrint()" class="text-gray-400 hover:text-gray-700">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            <div class="share-receipt-scrollwrap p-3 md:p-4 overflow-x-auto">
                <div id="shareReceiptSheet" class="share-receipt-sheet mx-auto bg-white text-gray-900 border border-gray-200 shadow-sm p-3 md:p-4">
                    <div class="text-center">
                        <div class="text-xl md:text-2xl font-black uppercase tracking-wide">PURPLE ARMY CONSUMERS COOPERATIVE</div>
                        <div class="text-lg md:text-xl font-black uppercase tracking-wide mt-1">MEMBER SHARE RECEIPT</div>
                        <div id="shareReceiptDate" class="text-sm font-bold mt-1.5"></div>
                    </div>

                    <div class="mt-3 grid gap-1.5 rounded-xl border border-gray-200 p-2.5 text-sm md:text-base">
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-gray-600">Member:</span>
                            <span id="shareReceiptMember" class="font-semibold text-right"></span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-gray-600">Reference No.:</span>
                            <span id="shareReceiptRef" class="font-semibold text-right"></span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-gray-600">Transaction Type:</span>
                            <span id="shareReceiptType" class="font-semibold text-right"></span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-gray-600">Status:</span>
                            <span id="shareReceiptStatus" class="font-semibold text-right"></span>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-t border-dashed border-gray-200 pt-2 mt-1">
                            <span class="font-black text-gray-700">Amount:</span>
                            <span id="shareReceiptAmount" class="font-black text-gray-900"></span>
                        </div>
                    </div>

                    <div class="share-receipt-signatories mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-center">
                        <div>
                            <div class="h-2"></div>
                            <div class="font-bold uppercase" id="shareReceiptTreasurer"></div>
                            <div class="text-xs uppercase text-gray-600 mt-1">Checked By / Treasurer</div>
                        </div>
                        <div>
                            <div class="h-2"></div>
                            <div class="font-bold uppercase" id="shareReceiptManager"></div>
                            <div class="text-xs uppercase text-gray-600 mt-1">Noted By / Manager</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="shareModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeShareModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden transform transition-all">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800"><i class="fas fa-plus-circle text-primary mr-2"></i>Add Member Share</h3>
                <button type="button" onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="member_shares.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="add_share_record" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Member <span class="text-red-500">*</span></label>
                    <select name="member_id" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <option value="" selected disabled>Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= (int)$member['member_id'] ?>"><?= htmlspecialchars(trim($member['last_name'] . ', ' . $member['first_name'] . ' ' . ($member['middle_name'] ?? ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type <span class="text-red-500">*</span></label>
                    <select name="share_payment_type_id" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <option value="" selected disabled>Select Payment Type</option>
                        <?php foreach ($share_payment_types as $type): ?>
                            <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" step="0.01" min="0.01" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="share_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No. / Invoice No. / Receipt No. <span class="text-red-500">*</span></label>
                    <input type="text" name="invoice_no" required placeholder="Enter reference number" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeShareModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md">SAVE SHARE</button>
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
                <a href="member_shares.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
                    <i class="fas fa-hand-holding-usd w-6"></i> MEMBER SHARES
                </a>
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-receipt w-6"></i> SALES & PURCHASE LOGS
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Membership Shares & Fees</h1>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 print:hidden">
                    <div class="bg-white rounded-xl shadow-sm border border-green-200 p-6 flex items-center justify-between border-l-4 border-l-green-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Total Share Capital</div>
                            <div class="text-3xl font-black text-gray-800">₱<?= number_format($total_share_capital, 2) ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl"><i class="fas fa-chart-pie"></i></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-6 flex items-center justify-between border-l-4 border-l-blue-500">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Membership Fees</div>
                            <div class="text-3xl font-black text-gray-800">₱<?= number_format($total_membership_fees, 2) ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl"><i class="fas fa-id-card"></i></div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-6 flex items-center justify-between border-l-4 border-l-primary">
                        <div>
                            <div class="text-sm font-semibold text-gray-500 uppercase mb-1">Active Contributors</div>
                            <div class="text-3xl font-black text-gray-800"><?= number_format($total_contributors) ?></div>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-primary text-xl"><i class="fas fa-users"></i></div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 mb-6 print:hidden">
                    
                    <div class="flex flex-col sm:flex-row gap-3 w-full items-stretch sm:items-center">
                        <div class="flex w-full lg:w-80 bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary shadow-sm">
                            <div class="px-3 py-2 text-gray-400 flex items-center justify-center"><i class="fas fa-search"></i></div>
                            <input type="text" id="shareSearch" placeholder="Search member, reference, or type..." class="w-full py-2 pr-4 outline-none text-sm text-gray-700 bg-transparent">
                        </div>
                        <select id="shareTypeFilter" class="bg-white border border-gray-300 rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary w-full sm:w-64">
                            <option value="ALL">All Share Types</option>
                            <?php foreach ($share_payment_types as $type): ?>
                                <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" id="shareDateStart" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary w-full sm:w-auto">
                        <input type="date" id="shareDateEnd" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary w-full sm:w-auto">
                        <button type="button" onclick="clearShareFilters()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300 w-full sm:w-auto whitespace-nowrap">
                            <i class="fas fa-filter-circle-xmark mr-2"></i>CLEAR FILTERS
                        </button>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row flex-wrap gap-3 w-full items-center">
                        <form action="import_shares.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm items-center">
                            <input type="file" name="excel_file" accept=".xls,.xlsx" required class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:font-semibold file:bg-purple-50 file:text-primary hover:file:bg-purple-100 transition cursor-pointer">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap"><i class="fas fa-upload mr-1"></i> UPLOAD SHARES</button>
                        </form>

                        <a href="member_shares.php?template=excel" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap text-center">
                            <i class="fas fa-download mr-2"></i>IMPORT TEMPLATE
                        </a>

                        <button type="button" onclick="openShareModal()" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap">
                            <i class="fas fa-plus mr-2"></i>ADD MEMBER SHARE
                        </button>

                        <a href="member_shares.php?export=excel" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap text-center">
                            <i class="fas fa-file-excel mr-2"></i>EXPORT
                        </a>

                        <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300 w-full sm:w-auto whitespace-nowrap">
                            <i class="fas fa-print mr-2"></i>PRINT REPORT
                        </button>
                    </div>
                </div>

                <div class="share-print-header">
                    <div class="share-print-title">Member Shares Report</div>
                    <div class="share-print-meta" id="sharePrintMetaType">Type: All Share Types</div>
                    <div class="share-print-meta" id="sharePrintMetaDate">Date Range: All Dates</div>
                    <div class="share-print-meta">Date Generated: <?= htmlspecialchars(date('F d, Y h:i A')) ?></div>
                </div>

                <div id="shareReportCard" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="font-bold text-gray-800"><i class="fas fa-list-ul text-primary mr-2"></i>Member Share Logs</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="shareReportTable" class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 font-bold tracking-wider">Date</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Ref / Invoice</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Member Name</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Transaction Type</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-right">Amount (PHP)</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-center">Status</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-center print:hidden">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="sharesTableBody">
                                <?php
                                try {
                                    if (!empty($share_rows)) {
                                        foreach ($share_rows as $row) {
                                            $date = date('M d, Y', strtotime($row['transaction_date']));
                                            $inv = !empty($row['invoice_no']) ? htmlspecialchars($row['invoice_no']) : 'N/A';
                                            $type = htmlspecialchars($row['share_type_name'] ?? $row['transaction_type']);

                                            if (stripos($type, 'share') !== false) {
                                                $type_badge = "<span class='text-green-700 font-bold'><i class='fas fa-chart-pie mr-1 opacity-50'></i> {$type}</span>";
                                            } elseif (stripos($type, 'fee') !== false) {
                                                $type_badge = "<span class='text-blue-700 font-bold'><i class='fas fa-id-card mr-1 opacity-50'></i> {$type}</span>";
                                            } else {
                                                $type_badge = "<span class='text-gray-700 font-bold'><i class='fas fa-tag mr-1 opacity-50'></i> {$type}</span>";
                                            }

                                            $status = !empty($row['payment_status']) ? htmlspecialchars($row['payment_status']) : 'COMPLETED';
                                            if (stripos($status, 'paid') !== false || stripos($status, 'completed') !== false) {
                                                $stat_badge = "<span class='bg-green-100 text-green-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-green-200'>$status</span>";
                                            } else {
                                                $stat_badge = "<span class='bg-red-100 text-red-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-red-200'>$status</span>";
                                            }

                                            $type_value = htmlspecialchars($row['share_type_name'] ?? $row['transaction_type']);
                                            echo "<tr class='share-row hover:bg-purple-50 transition-colors' data-date='" . htmlspecialchars($row['transaction_date']) . "' data-type='{$type_value}'>
                                                    <td class='px-6 py-4 font-medium text-gray-500'>{$date}</td>
                                                    <td class='px-6 py-4 font-mono text-gray-700'>{$inv}</td>
                                                    <td class='px-6 py-4 font-bold text-gray-900 uppercase'>" . htmlspecialchars($row['member_name']) . "</td>
                                                    <td class='px-6 py-4'>{$type_badge}</td>
                                                    <td class='px-6 py-4 font-black text-gray-900 text-right'>₱" . number_format($row['amount'], 2) . "</td>
                                                    <td class='px-6 py-4 text-center'>{$stat_badge}</td>
                                                    <td class='px-6 py-4 text-center print:hidden'>
                                                        <button type='button' onclick='openShareReceiptPrint(" . (int)$row['transaction_id'] . ")' class='inline-flex items-center gap-2 rounded-md border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-primary transition-colors hover:bg-primary hover:text-white'>
                                                            <i class='fas fa-receipt'></i> Print
                                                        </button>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='px-6 py-12 text-center text-gray-500'>No member shares or fees found. Upload an Excel file to begin.</td></tr>";
                                    }
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='7' class='px-6 py-12 text-center text-red-500 italic'><i class='fas fa-exclamation-triangle mr-2'></i>Database table 'transactions' error.</td></tr>";
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
        window.shareReceiptMap = <?= json_encode($share_receipt_payloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function openShareModal() {
            document.getElementById('shareModal').classList.remove('hidden');
            document.getElementById('shareModal').classList.add('flex');
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
            document.getElementById('shareModal').classList.remove('flex');
        }

        function formatShareReceiptDate(value) {
            if (!value) {
                return '';
            }

            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return value;
            }

            return parsed.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function openShareReceiptPrint(transactionId) {
            const data = window.shareReceiptMap?.[transactionId];
            if (!data) {
                return;
            }

            document.body.classList.add('share-receipt-print-mode');

            const modal = document.getElementById('shareReceiptModal');
            if (!modal) {
                return;
            }

            const dateEl = document.getElementById('shareReceiptDate');
            const memberEl = document.getElementById('shareReceiptMember');
            const refEl = document.getElementById('shareReceiptRef');
            const typeEl = document.getElementById('shareReceiptType');
            const statusEl = document.getElementById('shareReceiptStatus');
            const amountEl = document.getElementById('shareReceiptAmount');
            const treasurerEl = document.getElementById('shareReceiptTreasurer');
            const managerEl = document.getElementById('shareReceiptManager');

            if (dateEl) dateEl.textContent = formatShareReceiptDate(data.transaction_date || '');
            if (memberEl) memberEl.textContent = data.member_name || '';
            if (refEl) refEl.textContent = data.invoice_no || '';
            if (typeEl) typeEl.textContent = data.transaction_type || '';
            if (statusEl) statusEl.textContent = data.payment_status || '';
            if (amountEl) {
                amountEl.textContent = String.fromCharCode(8369) + Number(data.amount || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
            if (treasurerEl) treasurerEl.textContent = data.treasurer_name || '';
            if (managerEl) managerEl.textContent = data.manager_name || '';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeShareReceiptPrint() {
            const modal = document.getElementById('shareReceiptModal');
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('share-receipt-print-mode');
        }

        function printShareReceipt() {
            window.print();
        }

        function updateSharePrintMeta() {
            const typeFilter = document.getElementById('shareTypeFilter').value;
            const startDate = document.getElementById('shareDateStart').value;
            const endDate = document.getElementById('shareDateEnd').value;

            document.getElementById('sharePrintMetaType').innerText = 'Type: ' + (typeFilter === 'ALL' ? 'All Share Types' : typeFilter);
            document.getElementById('sharePrintMetaDate').innerText = (startDate || endDate)
                ? 'Date Range: ' + (startDate || 'Start') + ' to ' + (endDate || 'End')
                : 'Date Range: All Dates';
        }

        function filterShareRows() {
            const filter = document.getElementById('shareSearch').value.toLowerCase();
            const typeFilter = document.getElementById('shareTypeFilter').value;
            const startDate = document.getElementById('shareDateStart').value;
            const endDate = document.getElementById('shareDateEnd').value;
            const rows = document.querySelectorAll('.share-row');

            rows.forEach(row => {
                let matches = row.textContent.toLowerCase().includes(filter);
                const rowType = row.dataset.type || '';
                const rowDate = row.dataset.date || '';

                if (matches && typeFilter !== 'ALL' && rowType !== typeFilter) {
                    matches = false;
                }
                if (matches && startDate && rowDate && rowDate < startDate) {
                    matches = false;
                }
                if (matches && endDate && rowDate && rowDate > endDate) {
                    matches = false;
                }

                row.style.display = matches ? '' : 'none';
            });

            updateSharePrintMeta();
        }

        document.getElementById('shareSearch').addEventListener('keyup', filterShareRows);
        document.getElementById('shareTypeFilter').addEventListener('change', filterShareRows);
        document.getElementById('shareDateStart').addEventListener('change', filterShareRows);
        document.getElementById('shareDateEnd').addEventListener('change', filterShareRows);

        function clearShareFilters() {
            document.getElementById('shareSearch').value = '';
            document.getElementById('shareTypeFilter').value = 'ALL';
            document.getElementById('shareDateStart').value = '';
            document.getElementById('shareDateEnd').value = '';
            filterShareRows();
        }

        document.addEventListener('DOMContentLoaded', filterShareRows);

        <?php if (!empty($_SESSION['show_share_receipt_print']) && !empty($_SESSION['share_receipt_transaction_id'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                openShareReceiptPrint(<?= (int)$_SESSION['share_receipt_transaction_id'] ?>);
            });
            <?php unset($_SESSION['show_share_receipt_print'], $_SESSION['share_receipt_transaction_id']); ?>
        <?php endif; ?>

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
    </script>
</body>
</html>
