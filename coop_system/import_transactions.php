<?php
session_start();
include 'db.php';
require 'vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date; 

// --- AUTO-UPGRADE DATABASE SCHEMA ---
$checkCols = $conn->query("SHOW COLUMNS FROM transactions LIKE 'member_id'");
if($checkCols->num_rows == 0) {
    $conn->query("ALTER TABLE transactions ADD COLUMN member_id INT(11) NULL AFTER transaction_id");
    $conn->query("ALTER TABLE transactions ADD COLUMN items_details TEXT NULL AFTER amount");
    $conn->query("ALTER TABLE transactions ADD COLUMN invoice_no VARCHAR(100) NULL AFTER items_details");
    $conn->query("ALTER TABLE transactions ADD COLUMN payment_status VARCHAR(50) NULL AFTER invoice_no");
    $conn->query("ALTER TABLE transactions ADD COLUMN downpayment DECIMAL(10,2) NULL AFTER payment_status");
    $conn->query("ALTER TABLE transactions ADD COLUMN remaining_balance DECIMAL(10,2) NULL AFTER downpayment");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file'])) {
    
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($fileTmpPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // 1. HEADER ALIASES
    $header_aliases = [
        'date'               => ['dateoftransaction', 'date', 'transactiondate'],
        'member_name'        => ['paccmembername', 'membername', 'name', 'customer'],
        'member_first_name'  => ['memberfirstname', 'firstname'],
        'member_second_name' => ['membersecondname', 'secondname'],
        'member_middle_name' => ['membermiddlename', 'middlename'],
        'member_last_name'   => ['memberlastname', 'lastname'],
        'qty'                => ['quantity', 'qty'],
        'item_desc'          => ['itemdescription', 'description', 'item', 'items'],
        'price'              => ['sellingprice', 'price', 'unitprice'],
        'item_amount'        => ['amountofitem', 'itemamount'],
        'total_amount'       => ['totalamount', 'total', 'amount'],
        'payment_date'       => ['dateofpayment', 'paymentdate'],
        'downpayment'        => ['downpaymentamount', 'downpayment', 'dp'],
        'invoice'            => ['invoice', 'invoiceno', 'receipt'],
        'balance'            => ['remainingbalance', 'balance', 'remaining'],
        'status'             => ['paymentstatus', 'status']
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

    function buildItemLine($qty, $desc, $price, $amt) {
        $q = trim((string)$qty);
        $d = trim((string)$desc);
        $p = trim((string)$price);
        $a = trim((string)$amt);
        
        if($q === '' && $d === '' && $p === '' && $a === '') return null;
        
        $part = [];
        if($q !== '') $part[] = $q . "x";
        if($d !== '') $part[] = $d;
        if($p !== '') $part[] = "@ ₱" . str_replace('₱', '', $p);
        if($a !== '') $part[] = "= ₱" . str_replace('₱', '', $a);
        
        return implode(' ', $part);
    }

    // STRICT NAME SPLITTER
    function splitNameStrict($fullName) {
        $cleanName = preg_replace('/\s+/', ' ', trim($fullName)); 
        $last = ''; $first = ''; $middle = '';
        
        if (strpos($cleanName, ',') !== false) {
            $parts = explode(',', $cleanName, 2);
            $last = trim($parts[0]); // Everything before the comma is the last name (handles "de guzman")
            
            $f_parts = explode(' ', trim($parts[1]));
            if (count($f_parts) >= 3) {
                // If 3 or more words after the comma, the very last word is the middle name
                $middle = array_pop($f_parts); 
                $first = implode(' ', $f_parts);
            } else {
                // 1 or 2 words means it is entirely the first name
                $first = implode(' ', $f_parts);
            }
        } else {
            // Failsafe if there is no comma
            $parts = explode(' ', $cleanName);
            $last = count($parts) > 1 ? array_pop($parts) : $cleanName;
            if (count($parts) >= 3) {
                $middle = array_pop($parts);
            }
            $first = implode(' ', $parts);
        }
        return [$last, $first, $middle];
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
                        if ($sys_field === 'member_name' || $sys_field === 'member_first_name' || $sys_field === 'member_last_name' || $sys_field === 'date') {
                            $is_header = true;
                        }
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

    // 4. MULTI-ROW GROUPING ENGINE
    $transactions_to_save = [];
    $current_idx = -1;

    for ($i = $start_row; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (!is_array($row)) continue;

        $cell_name = getVal($row, $excel_map, 'member_name');
        $cell_first = getVal($row, $excel_map, 'member_first_name');
        $cell_second = getVal($row, $excel_map, 'member_second_name');
        $cell_middle = getVal($row, $excel_map, 'member_middle_name');
        $cell_last = getVal($row, $excel_map, 'member_last_name');
        $cell_date = getVal($row, $excel_map, 'date');

        if (empty($cell_name) && (!empty($cell_first) || !empty($cell_last) || !empty($cell_middle) || !empty($cell_second))) {
            $parts = [];
            if ($cell_last !== '') $parts[] = $cell_last . ',';
            if ($cell_first !== '') $parts[] = $cell_first;
            if ($cell_second !== '') $parts[] = $cell_second;
            if ($cell_middle !== '') $parts[] = $cell_middle;
            $cell_name = trim(implode(' ', $parts));
        }

        // If the row has a name or date, it is a NEW transaction block
        if (!empty($cell_name) || !empty($cell_date)) {
            $current_idx++;
            $transactions_to_save[$current_idx] = [
                'date'         => parseDate($cell_date) ?: date('Y-m-d'),
                'member_name'  => $cell_name,
                'total_amount' => cleanNumber(getVal($row, $excel_map, 'total_amount')),
                'downpayment'  => cleanNumber(getVal($row, $excel_map, 'downpayment')),
                'invoice'      => getVal($row, $excel_map, 'invoice'),
                'balance'      => cleanNumber(getVal($row, $excel_map, 'balance')),
                'status'       => strtoupper(getVal($row, $excel_map, 'status')),
                'items'        => [] 
            ];
        }

        // Extract item details for the current block
        if ($current_idx >= 0) {
            $qty   = getVal($row, $excel_map, 'qty');
            $desc  = getVal($row, $excel_map, 'item_desc');
            $price = getVal($row, $excel_map, 'price');
            $amt   = getVal($row, $excel_map, 'item_amount');
            
            $item_line = buildItemLine($qty, $desc, $price, $amt);
            if ($item_line !== null) {
                $transactions_to_save[$current_idx]['items'][] = $item_line;
            }
        }
    }

    // 5. STRICT DB INSERTION
    $inserted_count = 0;
    $updated_count = 0;
    foreach ($transactions_to_save as $txn) {
        if (empty($txn['member_name'])) continue;

        $items_str = implode("\n", $txn['items']);

        // Strict parsing logic
        list($last, $first, $middle) = splitNameStrict($txn['member_name']);

        // STRICT EXACT MATCHING ONLY (No Fuzzy Search)
        $member_id = null;
        $stmt = $conn->prepare("SELECT member_id FROM members WHERE last_name = ? AND first_name = ? LIMIT 1");
        $stmt->bind_param("ss", $last, $first);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $member_id = $res->fetch_assoc()['member_id'];
        }
        $stmt->close();

        $t_type = "PURCHASE";
        if (stripos($items_str, 'share') !== false || stripos($txn['member_name'], 'share') !== false) {
            $t_type = "SHARE";
        }

        // Avoid Duplicates
        $check = $conn->prepare("SELECT transaction_id FROM transactions WHERE transaction_date = ? AND member_name = ? AND invoice_no = ?");
        $check->bind_param("sss", $txn['date'], $txn['member_name'], $txn['invoice']);
        $check->execute();
        $c_res = $check->get_result();

        if ($c_res->num_rows > 0) {
            $tid = $c_res->fetch_assoc()['transaction_id'];
            $upd = $conn->prepare("UPDATE transactions SET member_id=?, items_details=?, payment_status=?, downpayment=?, remaining_balance=?, amount=? WHERE transaction_id=?");
            $upd->bind_param("issdddi", $member_id, $items_str, $txn['status'], $txn['downpayment'], $txn['balance'], $txn['total_amount'], $tid);
            $upd->execute();
            $updated_count++;
        } else {
            $ins = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sissssssdd", $txn['date'], $member_id, $txn['member_name'], $t_type, $txn['total_amount'], $items_str, $txn['invoice'], $txn['status'], $txn['downpayment'], $txn['balance']);
            $ins->execute();
            $inserted_count++;
        }
    }

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            'ADMIN',
            'IMPORT TRANSACTIONS',
            'TRANSACTIONS',
            null,
            'Bulk Transaction Import',
            'Inserted ' . $inserted_count . ' transaction row(s) and updated ' . $updated_count . ' existing row(s) from Excel.'
        );
    }

    $_SESSION['alert_title'] = "Transactions Uploaded";
    $_SESSION['alert_message'] = "The Excel file was parsed and items are matched to members in the database!";
    $_SESSION['alert_type'] = "success";
    
    header("Location: transactions.php"); // Redirecting back to transactions.php to avoid showing the alert on index.php
    exit();
}
?>
