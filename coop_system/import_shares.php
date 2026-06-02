<?php
session_start();
include 'db.php';
require 'vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file'])) {
    
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($fileTmpPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // 1. STRICT HEADER ALIASES (Matches your exact instructions)
    $header_aliases = [
        'date'        => ['dateoftransaction', 'date'],
        'first_name'  => ['memberfirstname', 'firstname'],
        'second_name' => ['membersecondname', 'secondname'],
        'middle_name' => ['membermiddlename', 'middlename'],
        'last_name'   => ['memberlastname', 'lastname'],
        'type'        => ['transactiontype', 'type'],
        'amount'      => ['paymentamount', 'payment', 'amount', 'total']
    ];

    // 2. HELPER FUNCTIONS
    function parseDate($input) {
        $input = trim((string)$input);
        if (empty($input) || $input == '-') return null;
        if (is_numeric($input)) return date('Y-m-d', Date::excelToTimestamp((float)$input));
        $time = strtotime($input);
        if ($time !== false) return date('Y-m-d', $time);
        return null;
    }

    function cleanNumber($input) {
        $val = preg_replace('/[^0-9\.\-]/', '', (string)$input);
        return $val === '' ? 0 : (float)$val;
    }

    // 3. DETECT HEADERS
    $excel_map = [];
    $start_row = 1;
    for ($i = 0; $i < min(10, count($rows)); $i++) {
        $is_header = false;
        foreach($rows[$i] as $col_idx => $col_name) {
            $clean_col = preg_replace('/[^a-z0-9]/', '', strtolower((string)$col_name));
            if (!empty($clean_col)) {
                foreach ($header_aliases as $sys_field => $aliases) {
                    if (in_array($clean_col, $aliases)) {
                        $excel_map[$sys_field] = $col_idx;
                        // If we find the crucial columns, mark as header row
                        if ($sys_field === 'last_name' || $sys_field === 'first_name') $is_header = true;
                        break;
                    }
                }
            }
        }
        if ($is_header) {
            $start_row = $i + 1; break;
        }
    }

    function getVal($row, $map, $field) {
        return isset($map[$field]) && isset($row[$map[$field]]) ? trim((string)$row[$map[$field]]) : '';
    }

    // 4. PARSE ROWS & STRICTLY INSERT INTO DB
    $imported_count = 0;
    for ($i = $start_row; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (!is_array($row)) continue;

        $raw_date = getVal($row, $excel_map, 'date');
        $first = getVal($row, $excel_map, 'first_name');
        $second = getVal($row, $excel_map, 'second_name');
        $middle = getVal($row, $excel_map, 'middle_name');
        $last = getVal($row, $excel_map, 'last_name');
        
        $type = getVal($row, $excel_map, 'type');
        $amount = cleanNumber(getVal($row, $excel_map, 'amount'));

        // Skip entirely blank rows or rows without a name
        if (empty($last) && empty($first)) continue;

        $t_date = parseDate($raw_date) ?: date('Y-m-d');
        
        // Construct the full display name for the transaction log
        $full_name_parts = [$last . ','];
        if (!empty($first)) $full_name_parts[] = $first;
        if (!empty($second)) $full_name_parts[] = $second;
        if (!empty($middle)) $full_name_parts[] = $middle;
        $display_name = implode(' ', $full_name_parts);

        // --- STRICT EXACT MATCHING ---
        // Searches the DB for a 100% exact match of Last Name and First Name
        $member_id = null;
        $stmt = $conn->prepare("SELECT member_id FROM members WHERE last_name = ? AND first_name = ? LIMIT 1");
        $stmt->bind_param("ss", $last, $first);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $member_id = $res->fetch_assoc()['member_id'];
        }
        $stmt->close();

        // Format the transaction
        $t_type = (stripos($type, 'share') !== false) ? 'Share Capital' : 'Membership Fee';
        $invoice = 'SHR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)); // Auto-generate a receipt code
        $status = 'COMPLETED';
        $items_details = "Payment for " . $t_type;

        // Check for duplicates (Same Date, Same Member, Same Amount, Same Type)
        $check = $conn->prepare("SELECT transaction_id FROM transactions WHERE transaction_date = ? AND member_name = ? AND amount = ? AND transaction_type = ?");
        $check->bind_param("ssds", $t_date, $display_name, $amount, $t_type);
        $check->execute();
        $c_res = $check->get_result();

        if ($c_res->num_rows == 0) {
            // Insert New Share/Fee Transaction
            $ins = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
            $ins->bind_param("sissssss", $t_date, $member_id, $display_name, $t_type, $amount, $items_details, $invoice, $status);
            $ins->execute();
            $imported_count++;
        }
    }

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            'ADMIN',
            'IMPORT SHARES',
            'TRANSACTIONS',
            null,
            'Bulk Share / Fee Import',
            'Imported ' . $imported_count . ' share/fee transaction row(s) from Excel.'
        );
    }

    $_SESSION['alert_title'] = "Shares Uploaded";
    $_SESSION['alert_message'] = "The Excel file was parsed and Member Shares/Fees were STRICTLY matched to existing members in the database!";
    $_SESSION['alert_type'] = "success";
    
    header("Location: member_shares.php");
    exit();
}
?>
