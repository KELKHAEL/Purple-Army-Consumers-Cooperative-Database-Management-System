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

// Handle manual share entry.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_share_record'])) {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $share_type_id = (int)($_POST['share_payment_type_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $share_date = !empty($_POST['share_date']) ? $_POST['share_date'] : date('Y-m-d');

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

    if (!$member_row || !$type_row || $amount <= 0 || empty($share_date)) {
        $_SESSION['alert_title'] = "Invalid Entry";
        $_SESSION['alert_message'] = "Please select a member, payment type, and enter a valid amount.";
        $_SESSION['alert_type'] = "error";
        header("Location: member_shares.php");
        exit();
    }

    $member_name = trim($member_row['last_name'] . ', ' . $member_row['first_name'] . ' ' . ($member_row['middle_name'] ?? ''));
    $member_name = preg_replace('/\s+/', ' ', trim($member_name));
    $invoice_no = 'SHR-MAN-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
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

                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4 print:hidden">
                    
                    <div class="flex w-full lg:w-1/3 bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary shadow-sm">
                        <div class="px-3 py-2 text-gray-400 flex items-center justify-center"><i class="fas fa-search"></i></div>
                        <input type="text" id="shareSearch" placeholder="Search member, invoice, or type..." class="w-full py-2 pr-4 outline-none text-sm text-gray-700 bg-transparent">
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto items-center">
                        <form action="import_shares.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm items-center">
                            <input type="file" name="excel_file" accept=".xls,.xlsx" required class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:font-semibold file:bg-purple-50 file:text-primary hover:file:bg-purple-100 transition cursor-pointer">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap"><i class="fas fa-upload mr-1"></i> UPLOAD SHARES</button>
                        </form>

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

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="font-bold text-gray-800"><i class="fas fa-list-ul text-primary mr-2"></i>Member Share Logs</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 font-bold tracking-wider">Date</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Ref / Invoice</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Member Name</th>
                                    <th class="px-6 py-4 font-bold tracking-wider">Transaction Type</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-right">Amount (PHP)</th>
                                    <th class="px-6 py-4 font-bold tracking-wider text-center">Status</th>
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

                                            echo "<tr class='share-row hover:bg-purple-50 transition-colors'>
                                                    <td class='px-6 py-4 font-medium text-gray-500'>{$date}</td>
                                                    <td class='px-6 py-4 font-mono text-gray-700'>{$inv}</td>
                                                    <td class='px-6 py-4 font-bold text-gray-900 capitalize'>" . htmlspecialchars($row['member_name']) . "</td>
                                                    <td class='px-6 py-4'>{$type_badge}</td>
                                                    <td class='px-6 py-4 font-black text-gray-900 text-right'>₱" . number_format($row['amount'], 2) . "</td>
                                                    <td class='px-6 py-4 text-center'>{$stat_badge}</td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='px-6 py-12 text-center text-gray-500'>No member shares or fees found. Upload an Excel file to begin.</td></tr>";
                                    }
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='6' class='px-6 py-12 text-center text-red-500 italic'><i class='fas fa-exclamation-triangle mr-2'></i>Database table 'transactions' error.</td></tr>";
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

        function openShareModal() {
            document.getElementById('shareModal').classList.remove('hidden');
            document.getElementById('shareModal').classList.add('flex');
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
            document.getElementById('shareModal').classList.remove('flex');
        }

        // --- LIVE SEARCH LOGIC ---
        document.getElementById('shareSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.share-row');

            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
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
