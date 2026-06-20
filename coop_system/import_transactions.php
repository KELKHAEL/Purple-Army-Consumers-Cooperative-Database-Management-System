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

function normalizeTxnImportText($input): string {
    $value = html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    if ($value === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function normalizeTxnImportIdentifier($input): string {
    $value = html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = preg_replace('/\s+/u', '', trim($value));
    if ($value === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function normalizeTxnImportReferenceNumber($input): string {
    $value = html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = preg_replace('/\s+/u', '', trim($value));
    if ($value === '') {
        return '';
    }
    if (ctype_digit($value)) {
        $value = ltrim($value, '0');
        if ($value === '') {
            $value = '0';
        }
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function normalizeTxnImportComparableText($input): string {
    $value = html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    if ($value === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function normalizeTxnImportMoney($input): string {
    $value = preg_replace('/[^0-9\.\-]/', '', (string)$input);
    if ($value === '' || !is_numeric($value)) {
        return number_format(0, 2, '.', '');
    }
    return number_format((float)$value, 2, '.', '');
}

function isExcludedSalesPurchaseImportType(string $type): bool {
    $normalized = function_exists('mb_strtoupper') ? mb_strtoupper(trim($type), 'UTF-8') : strtoupper(trim($type));
    return in_array($normalized, [
        'MEMBERSHIP FEE',
        'SHARE CAPITAL',
        'MEMBERSHIP SHARE CAPITAL',
        'SHARES CAPITAL',
        'SHARE',
    ], true);
}

function normalizeTxnImportItemsDetails($input): string {
    $lines = preg_split("/\r\n|\n|\r/", (string)$input) ?: [];
    $normalized = [];
    foreach ($lines as $line) {
        $line = normalizeTxnImportComparableText($line);
        if ($line !== '') {
            $normalized[] = $line;
        }
    }
    return implode("\n", $normalized);
}

function buildTxnImportFingerprint(array $data): string {
    return sha1(json_encode([
        'transaction_date' => normalizeTxnImportIdentifier($data['transaction_date'] ?? ''),
        'member_id' => (int)($data['member_id'] ?? 0),
        'member_name' => normalizeTxnImportComparableText($data['member_name'] ?? ''),
        'transaction_type' => normalizeTxnImportComparableText($data['transaction_type'] ?? ''),
        'invoice_no' => normalizeTxnImportReferenceNumber($data['invoice_no'] ?? ''),
        'items_details' => normalizeTxnImportItemsDetails($data['items_details'] ?? ''),
        'payment_status' => normalizeTxnImportComparableText($data['payment_status'] ?? ''),
        'downpayment' => normalizeTxnImportMoney($data['downpayment'] ?? 0),
        'remaining_balance' => normalizeTxnImportMoney($data['remaining_balance'] ?? 0),
        'amount' => normalizeTxnImportMoney($data['amount'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function buildTxnImportDuplicateKey(array $txn): string {
    return sha1(json_encode([
        'transaction_date' => normalizeTxnImportIdentifier($txn['date'] ?? ''),
        'reference_no' => normalizeTxnImportReferenceNumber($txn['reference_no'] ?? $txn['invoice'] ?? ''),
        'transaction_type' => normalizeTxnImportComparableText($txn['transaction_type'] ?? ''),
        'amount' => normalizeTxnImportMoney($txn['total_amount'] ?? 0),
        'items_details' => normalizeTxnImportItemsDetails(implode("\n", $txn['items'] ?? [])),
        'payment_status' => normalizeTxnImportComparableText($txn['status'] ?? 'COMPLETED'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function renderTxnImportDuplicateConfirmation(array $duplicate_groups, array $payload_rows): void {
    $payload = base64_encode(json_encode($payload_rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $duplicate_count = 0;
    foreach ($duplicate_groups as $group) {
        $duplicate_count += max(0, count($group) - 1);
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirm Duplicate Import</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<style>body{font-family:Inter,sans-serif;}</style></head><body class="bg-gray-100 text-gray-800 min-h-screen flex items-center justify-center p-4">';
    echo '<div class="w-full max-w-4xl bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">';
    echo '<div class="bg-primary text-white px-6 py-4">';
    echo '<h1 class="text-xl font-bold"><i class="fas fa-clone mr-2"></i>Duplicate Rows Detected</h1>';
    echo '<p class="text-white/90 text-sm mt-1 font-medium">Choose how the system should handle repeated rows before finishing the import.</p>';
    echo '</div>';
    echo '<div class="p-6 space-y-4">';
    echo '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">';
    echo '<div class="rounded-xl border border-gray-200 bg-gray-50 p-4"><div class="text-xs uppercase text-gray-500 font-bold">Duplicate Groups</div><div class="text-3xl font-black text-gray-800 mt-1">' . count($duplicate_groups) . '</div></div>';
    echo '<div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs uppercase text-amber-700 font-bold">Extra Duplicate Rows</div><div class="text-3xl font-black text-amber-700 mt-1">' . $duplicate_count . '</div></div>';
    echo '<div class="rounded-xl border border-blue-200 bg-blue-50 p-4"><div class="text-xs uppercase text-blue-700 font-bold">Rows Ready</div><div class="text-3xl font-black text-blue-700 mt-1">' . count($payload_rows) . '</div></div>';
    echo '</div>';
    echo '<div class="rounded-xl border border-gray-200 overflow-hidden">';
    echo '<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 font-semibold">Duplicate row preview</div>';
    echo '<div class="max-h-[420px] overflow-y-auto">';
    foreach ($duplicate_groups as $group_index => $group) {
        $group_members = [];
        $group_types = [];
        $group_refs = [];
        $group_dates = [];
        $group_amount_total = 0.0;
        foreach ($group as $row) {
            $member_label = trim((string)($row['member_name'] ?? ''));
            if ($member_label !== '') {
                $group_members[$member_label] = true;
            }
            $type_label = trim((string)($row['transaction_type'] ?? ''));
            if ($type_label !== '') {
                $group_types[$type_label] = true;
            }
            $ref_label = trim((string)($row['reference_no'] ?? $row['invoice'] ?? ''));
            if ($ref_label !== '') {
                $group_refs[$ref_label] = true;
            }
            $date_label = trim((string)($row['date'] ?? ''));
            if ($date_label !== '') {
                $group_dates[$date_label] = true;
            }
            $group_amount_total += (float)($row['total_amount'] ?? 0);
        }

        $group_member_text = !empty($group_members) ? implode(', ', array_slice(array_keys($group_members), 0, 3)) : 'N/A';
        $group_type_text = !empty($group_types) ? implode(', ', array_slice(array_keys($group_types), 0, 3)) : 'N/A';
        $group_ref_text = !empty($group_refs) ? implode(', ', array_slice(array_keys($group_refs), 0, 3)) : 'N/A';
        $group_date_text = !empty($group_dates) ? implode(', ', array_slice(array_keys($group_dates), 0, 3)) : 'N/A';

        echo '<div class="border-b border-gray-100 p-4">';
        echo '<div class="font-bold text-gray-800 mb-2">Duplicate Group ' . ($group_index + 1) . ' (' . count($group) . ' rows)</div>';
        echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs text-gray-600 mb-3">';
        echo '<div class="rounded-lg bg-gray-50 border border-gray-200 p-2"><strong class="block text-gray-500 uppercase mb-1">Member(s)</strong>' . htmlspecialchars($group_member_text) . '</div>';
        echo '<div class="rounded-lg bg-gray-50 border border-gray-200 p-2"><strong class="block text-gray-500 uppercase mb-1">Date(s)</strong>' . htmlspecialchars($group_date_text) . '</div>';
        echo '<div class="rounded-lg bg-gray-50 border border-gray-200 p-2"><strong class="block text-gray-500 uppercase mb-1">Ref(s)</strong>' . htmlspecialchars($group_ref_text) . '</div>';
        echo '<div class="rounded-lg bg-gray-50 border border-gray-200 p-2"><strong class="block text-gray-500 uppercase mb-1">Type(s)</strong>' . htmlspecialchars($group_type_text) . '</div>';
        echo '</div>';
        echo '<div class="text-xs font-semibold text-gray-500 mb-3">Group amount total: ₱' . number_format($group_amount_total, 2) . '</div>';
        echo '<ul class="space-y-2 text-sm text-gray-600">';
        foreach ($group as $row) {
            $ref = htmlspecialchars((string)($row['reference_no'] ?? $row['invoice'] ?? ''));
            $date = htmlspecialchars((string)($row['date'] ?? ''));
            $type = htmlspecialchars((string)($row['transaction_type'] ?? ''));
            $amount = number_format((float)($row['total_amount'] ?? 0), 2);
            $items = htmlspecialchars(implode(' | ', $row['items'] ?? []));
            $member = htmlspecialchars((string)($row['member_name'] ?? 'N/A'));
            echo '<li class="rounded-lg bg-gray-50 border border-gray-200 p-3">';
            echo '<div><strong>Row:</strong> ' . (int)($row['row_number'] ?? 0) . '</div>';
            echo '<div><strong>Member:</strong> ' . $member . '</div>';
            echo '<div><strong>Date:</strong> ' . $date . '</div>';
            echo '<div><strong>Ref:</strong> ' . $ref . '</div>';
            echo '<div><strong>Type:</strong> ' . $type . '</div>';
            echo '<div><strong>Amount:</strong> ₱' . $amount . '</div>';
            if ($items !== '') {
                echo '<div><strong>Items:</strong> ' . $items . '</div>';
            }
            echo '</li>';
        }
        echo '</ul></div>';
    }
    echo '</div></div>';
    echo '<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">';
    echo '<div class="font-bold mb-1"><i class="fas fa-triangle-exclamation mr-2"></i>Canceling will not save any duplicated rows.</div>';
    echo '<div class="text-red-700">Choose the red option if you want to stop the import and keep the duplicated data out of the system.</div>';
    echo '</div>';
    echo '<div class="flex flex-col sm:flex-row gap-3 mt-6">';
    echo '<form method="POST" class="flex-1">';
    echo '<input type="hidden" name="duplicate_action" value="overwrite">';
    echo '<input type="hidden" name="pending_payload" value="' . htmlspecialchars($payload, ENT_QUOTES) . '">';
    echo '<button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-semibold py-3 px-4 rounded-lg shadow-sm"><i class="fas fa-rotate mr-2"></i>Overwrite Duplicates</button>';
    echo '</form>';
    echo '<form method="POST" class="flex-1">';
    echo '<input type="hidden" name="duplicate_action" value="append">';
    echo '<input type="hidden" name="pending_payload" value="' . htmlspecialchars($payload, ENT_QUOTES) . '">';
    echo '<button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg shadow-sm"><i class="fas fa-plus mr-2"></i>Continue Adding Duplicates</button>';
    echo '</form>';
    echo '<form method="POST" class="flex-1">';
    echo '<input type="hidden" name="duplicate_action" value="abort">';
    echo '<input type="hidden" name="pending_payload" value="' . htmlspecialchars($payload, ENT_QUOTES) . '">';
    echo '<button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg shadow-sm"><i class="fas fa-ban mr-2"></i>Do Not Import Duplicates</button>';
    echo '</form>';
    echo '</div>';
    echo '</div></div></body></html>';
    exit();
}

function parseTxnImportDate($input) {
    $input = trim((string)$input);
    if (empty($input) || $input == '-') return null;
    if (is_numeric($input)) return date('Y-m-d', Date::excelToTimestamp((float)$input));
    $time = strtotime($input);
    if ($time !== false) return date('Y-m-d', $time);
    return null;
}

function cleanTxnImportNumber($input) {
    $val = preg_replace('/[^0-9\.\-]/', '', (string)$input);
    return $val === '' ? 0 : (float)$val;
}

function buildTxnImportItemLine($qty, $desc, $price, $amt) {
    $q = trim((string)$qty);
    $d = trim((string)$desc);
    $p = trim((string)$price);
    $a = trim((string)$amt);

    if ($q === '' && $d === '' && $p === '' && $a === '') return null;

    $part = [];
    if ($q !== '') $part[] = $q . "x";
    if ($d !== '') $part[] = $d;
    if ($p !== '') $part[] = "@ â‚±" . str_replace('â‚±', '', $p);
    if ($a !== '') $part[] = "= â‚±" . str_replace('â‚±', '', $a);

    return implode(' ', $part);
}

function buildTxnImportItemLineWithUnit($qty, $unit, $desc, $price, $amt) {
    $q = trim((string)$qty);
    $u = trim((string)$unit);
    $d = trim((string)$desc);
    $p = trim((string)$price);
    $a = trim((string)$amt);

    if ($a === '' && $q !== '' && $p !== '') {
        $a = (string)((float)$q * (float)$p);
    }

    if ($q === '' && $u === '' && $d === '' && $p === '' && $a === '') {
        return null;
    }

    $part = [];
    if ($q !== '') {
        $part[] = trim($q . ' ' . $u);
    } elseif ($u !== '') {
        $part[] = $u;
    }
    if ($d !== '') {
        $part[] = $d;
    }
    if ($p !== '') {
        $part[] = "@ â‚±" . preg_replace('/[^0-9\.\-]/', '', $p);
    }
    if ($a !== '') {
        $part[] = "= â‚±" . preg_replace('/[^0-9\.\-]/', '', $a);
    }

    return implode(' ', $part);
}

function splitTxnImportNameStrict($fullName) {
    $cleanName = preg_replace('/\s+/', ' ', trim($fullName));
    $last = ''; $first = ''; $middle = '';

    if (strpos($cleanName, ',') !== false) {
        $parts = explode(',', $cleanName, 2);
        $last = trim($parts[0]);
        $f_parts = explode(' ', trim($parts[1]));
        if (count($f_parts) >= 3) {
            $middle = array_pop($f_parts);
            $first = implode(' ', $f_parts);
        } else {
            $first = implode(' ', $f_parts);
        }
    } else {
        $parts = explode(' ', $cleanName);
        $last = count($parts) > 1 ? array_pop($parts) : $cleanName;
        if (count($parts) >= 3) {
            $middle = array_pop($parts);
        }
        $first = implode(' ', $parts);
    }

    return [$last, $first, $middle];
}

function getTxnImportValue($row, $map, $field) {
    return isset($map[$field]) && isset($row[$map[$field]]) ? trim((string)$row[$map[$field]]) : '';
}

function buildTxnMemberDisplayName($last, $first, $second, $middle) {
    $last = trim((string)$last);
    $first = trim(preg_replace('/\s+/u', ' ', trim((string)$first . ' ' . (string)$second)));
    $middle = trim((string)$middle);

    if ($last === '' && $first === '' && $middle === '') {
        return '';
    }

    $pieces = [];
    if ($last !== '') {
        $pieces[] = $last . ',';
    }
    if ($first !== '') {
        $pieces[] = $first;
    }
    if ($middle !== '') {
        $pieces[] = $middle;
    }

    return trim(preg_replace('/\s+/u', ' ', implode(' ', $pieces)));
}

$member_rows = [];
$member_query = $conn->query("SELECT member_id, form_id, first_name, middle_name, last_name FROM members");
if ($member_query) {
    while ($member = $member_query->fetch_assoc()) {
        $member_rows[] = $member;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $duplicate_action = strtolower(trim((string)($_POST['duplicate_action'] ?? '')));
    $from_pending_payload = !empty($_POST['pending_payload']);
    $transactions_to_save = [];

    if ($duplicate_action === 'abort') {
        $_SESSION['alert_title'] = "Import Cancelled";
        $_SESSION['alert_message'] = "The duplicated rows were not imported. No changes were saved.";
        $_SESSION['alert_type'] = "info";
        header("Location: transactions.php");
        exit();
    }

    if ($from_pending_payload) {
        $decoded_payload = json_decode(base64_decode((string)$_POST['pending_payload'], true), true);
        if (!is_array($decoded_payload)) {
            $_SESSION['alert_title'] = "Import Error";
            $_SESSION['alert_message'] = "The pending import data could not be read. Please upload the Excel file again.";
            $_SESSION['alert_type'] = "error";
            header("Location: transactions.php");
            exit();
        }
        $transactions_to_save = $decoded_payload;
    } else {
        if (!isset($_FILES['excel_file'])) {
            $_SESSION['alert_title'] = "Import Error";
            $_SESSION['alert_message'] = "Please upload an Excel file first.";
            $_SESSION['alert_type'] = "error";
            header("Location: transactions.php");
            exit();
        }

        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($fileTmpPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

    // 1. HEADER ALIASES
    $header_aliases = [
        'date'               => ['dateoftransaction', 'date', 'transactiondate'],
        'transaction_type'   => ['transactiontype', 'type', 'category'],
        'reference_no'       => ['referencenoinvoicenoreceiptno', 'referencenoinvoice', 'referenceno', 'invoiceno', 'receiptono', 'refno', 'invoice', 'receipt'],
        'member_id'          => ['memberid', 'id'],
        'form_id'            => ['formid', 'formno'],
        'member_first_name'  => ['memberfirstname', 'firstname'],
        'member_second_name' => ['membersecondname', 'secondname'],
        'member_middle_name' => ['membermiddlename', 'middlename'],
        'member_last_name'   => ['memberlastname', 'lastname'],
        'qty'                => ['quantity', 'qty'],
        'item_unit'          => ['itemunit', 'unit', 'itemmeasurement', 'measurement'],
        'item_desc'          => ['itemdescription', 'description', 'item', 'items', 'itemname'],
        'price'              => ['sellingprice', 'price', 'unitprice', 'itemcost'],
        'item_amount'        => ['amountofitem', 'itemamount'],
        'total_amount'       => ['totalamount', 'total', 'amount'],
        'payment_date'       => ['dateofpayment', 'paymentdate'],
        'downpayment'        => ['downpaymentamount', 'downpayment', 'dp'],
        'invoice'            => ['invoice', 'invoiceno', 'receipt', 'referenceno', 'reference'],
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

    function buildItemLineWithUnit($qty, $unit, $desc, $price, $amt) {
        $q = trim((string)$qty);
        $u = trim((string)$unit);
        $d = trim((string)$desc);
        $p = trim((string)$price);
        $a = trim((string)$amt);

        if ($a === '' && $q !== '' && $p !== '') {
            $a = (string)((float)$q * (float)$p);
        }

        if ($q === '' && $u === '' && $d === '' && $p === '' && $a === '') {
            return null;
        }

        $part = [];
        if ($q !== '') {
            $part[] = trim($q . ' ' . $u);
        } elseif ($u !== '') {
            $part[] = $u;
        }
        if ($d !== '') {
            $part[] = $d;
        }
        if ($p !== '') {
            $part[] = "@ ₱" . preg_replace('/[^0-9\.\-]/', '', $p);
        }
        if ($a !== '') {
            $part[] = "= ₱" . preg_replace('/[^0-9\.\-]/', '', $a);
        }

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
                        if (in_array($sys_field, ['member_first_name', 'member_second_name', 'member_middle_name', 'member_last_name', 'date', 'reference_no', 'member_id', 'form_id', 'transaction_type'], true)) {
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

    if (!function_exists('buildTxnMemberDisplayName')) {
        function buildTxnMemberDisplayName($last, $first, $second, $middle) {
            $last = trim((string)$last);
            $first = trim(preg_replace('/\s+/u', ' ', trim((string)$first . ' ' . (string)$second)));
            $middle = trim((string)$middle);

            if ($last === '' && $first === '' && $middle === '') {
                return '';
            }

            $pieces = [];
            if ($last !== '') {
                $pieces[] = $last . ',';
            }
            if ($first !== '') {
                $pieces[] = $first;
            }
            if ($middle !== '') {
                $pieces[] = $middle;
            }

            return trim(preg_replace('/\s+/u', ' ', implode(' ', $pieces)));
        }
    }

    $insert_date = '';
    $insert_member_id = 0;
    $insert_member_name = '';
    $insert_type = '';
    $insert_amount = 0.0;
    $insert_items = '';
    $insert_reference = '';
    $insert_status = '';
    $insert_downpayment = 0.0;
    $insert_balance = 0.0;
    $update_date = '';
    $update_member_id = 0;
    $update_member_name = '';
    $update_type = '';
    $update_amount = 0.0;
    $update_items = '';
    $update_reference = '';
    $update_status = '';
    $update_downpayment = 0.0;
    $update_balance = 0.0;
    $update_tid = 0;

    // 4. MULTI-ROW GROUPING ENGINE
    $transactions_to_save = [];
    $current_idx = -1;
    $unreadable_count = 0;

    for ($i = $start_row; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (!is_array($row)) continue;

        $cell_first = getVal($row, $excel_map, 'member_first_name');
        $cell_second = getVal($row, $excel_map, 'member_second_name');
        $cell_middle = getVal($row, $excel_map, 'member_middle_name');
        $cell_last = getVal($row, $excel_map, 'member_last_name');
        $cell_member_id = preg_replace('/\D+/', '', getVal($row, $excel_map, 'member_id'));
        $cell_form_id = normalizeTxnImportIdentifier(getVal($row, $excel_map, 'form_id'));
        $cell_date = getVal($row, $excel_map, 'date');
        $cell_type = getVal($row, $excel_map, 'transaction_type');
        $cell_reference = normalizeTxnImportReferenceNumber(getVal($row, $excel_map, 'reference_no'));
        $cell_unit = getVal($row, $excel_map, 'item_unit');

        $has_member_identity = $cell_member_id !== '' || $cell_form_id !== '' || $cell_first !== '' || $cell_second !== '' || $cell_middle !== '' || $cell_last !== '';

        // If the row has a name or date, it is a NEW transaction block
        if ($has_member_identity || !empty($cell_date) || !empty($cell_type) || !empty($cell_reference)) {
            $current_idx++;
            $transactions_to_save[$current_idx] = [
                'date'         => parseDate($cell_date) ?: date('Y-m-d'),
                'total_amount' => cleanNumber(getVal($row, $excel_map, 'total_amount')),
                'downpayment'  => cleanNumber(getVal($row, $excel_map, 'downpayment')),
                'invoice'      => getVal($row, $excel_map, 'invoice'),
                'balance'      => cleanNumber(getVal($row, $excel_map, 'balance')),
                'transaction_type' => $cell_type,
                'member_id'    => $cell_member_id,
                'form_id'      => $cell_form_id,
                'member_first_name' => $cell_first,
                'member_second_name' => $cell_second,
                'member_middle_name' => $cell_middle,
                'member_last_name' => $cell_last,
                'reference_no' => $cell_reference,
                'item_unit'    => $cell_unit,
                'status'       => strtoupper(getVal($row, $excel_map, 'status')),
                'row_number'   => $i + 1,
                'has_item'     => false,
                'items'        => [] 
            ];
        }

        // Extract item details for the current block
        if ($current_idx >= 0) {
            $qty   = getVal($row, $excel_map, 'qty');
            $unit  = getVal($row, $excel_map, 'item_unit');
            $desc  = getVal($row, $excel_map, 'item_desc');
            $price = getVal($row, $excel_map, 'price');
            $amt   = getVal($row, $excel_map, 'item_amount');
            
            $item_line = buildItemLineWithUnit($qty, $unit, $desc, $price, $amt);
            if ($item_line !== null) {
                $transactions_to_save[$current_idx]['items'][] = $item_line;
                $transactions_to_save[$current_idx]['has_item'] = true;
            }
        }
    }

    }

    $duplicate_groups = [];
    if (!$from_pending_payload) {
        $duplicate_map = [];
        foreach ($transactions_to_save as $txn) {
            $dup_key = buildTxnImportDuplicateKey($txn);
            if (!isset($duplicate_map[$dup_key])) {
                $duplicate_map[$dup_key] = [];
            }
            $duplicate_map[$dup_key][] = $txn;
        }

        foreach ($duplicate_map as $group) {
            if (count($group) > 1) {
                $duplicate_groups[] = $group;
            }
        }

        if (!empty($duplicate_groups) && $duplicate_action === '') {
            renderTxnImportDuplicateConfirmation($duplicate_groups, $transactions_to_save);
        }
    }

    // 5. STRICT DB INSERTION
    $inserted_count = 0;
    $updated_count = 0;
    $overwritten_rows = [];
    $unreadable_rows = [];
    foreach ($transactions_to_save as $txn) {
        $items_str = implode("\n", $txn['items']);
        $row_number = (int)($txn['row_number'] ?? 0);

        $member_id = null;
        $resolved_member = null;
        $txn_name_display = buildTxnMemberDisplayName(
            $txn['member_last_name'] ?? '',
            $txn['member_first_name'] ?? '',
            $txn['member_second_name'] ?? '',
            $txn['member_middle_name'] ?? ''
        );

        if ($txn['member_id'] !== '') {
            $member_id_int = (int)$txn['member_id'];
            foreach ($member_rows as $member_candidate) {
                if ((int)$member_candidate['member_id'] === $member_id_int) {
                    $resolved_member = $member_candidate;
                    break;
                }
            }
        }

        if ($resolved_member === null && $txn['form_id'] !== '') {
            foreach ($member_rows as $member_candidate) {
                if (normalizeTxnImportIdentifier($member_candidate['form_id'] ?? '') === $txn['form_id']) {
                    $resolved_member = $member_candidate;
                    break;
                }
            }
        }

        if ($resolved_member === null) {
            $normalized_first = normalizeTxnImportText(trim((string)$txn['member_first_name'] . ' ' . (string)$txn['member_second_name']));
            $normalized_middle = normalizeTxnImportText($txn['member_middle_name'] ?? '');
            $normalized_last = normalizeTxnImportText($txn['member_last_name'] ?? '');

            $matches = [];
            foreach ($member_rows as $member_candidate) {
                $candidate_first = normalizeTxnImportText($member_candidate['first_name'] ?? '');
                $candidate_middle = normalizeTxnImportText($member_candidate['middle_name'] ?? '');
                $candidate_last = normalizeTxnImportText($member_candidate['last_name'] ?? '');

                if ($normalized_last !== '' && $candidate_last !== $normalized_last) {
                    continue;
                }
                if ($normalized_first !== '' && $candidate_first !== $normalized_first) {
                    continue;
                }
                if ($normalized_middle !== '' && $candidate_middle !== $normalized_middle) {
                    continue;
                }
                $matches[] = $member_candidate;
            }

            if (count($matches) === 1) {
                $resolved_member = $matches[0];
            }
        }

        if ($resolved_member !== null) {
            $member_id = (int)$resolved_member['member_id'];
            $member_name_parts = [
                trim((string)($resolved_member['last_name'] ?? '')),
                trim((string)($resolved_member['first_name'] ?? '')),
                trim((string)($resolved_member['middle_name'] ?? ''))
            ];
            $txn_name_display = trim(preg_replace('/\s+/', ' ', $member_name_parts[0] . ', ' . $member_name_parts[1] . ' ' . $member_name_parts[2]));
        }

        $resolved_type = function_exists('resolveTransactionType') ? resolveTransactionType($conn, $txn['transaction_type']) : null;
        $t_type = $resolved_type['name'] ?? '';
        if ($t_type === '') {
            $t_type = "PURCHASE";
            if (stripos($items_str, 'share') !== false) {
                $t_type = "SHARE";
            }
        }

        if (isExcludedSalesPurchaseImportType($t_type)) {
            $unreadable_count++;
            $unreadable_rows[] = [
                'row_number' => $row_number,
                'reason' => 'Excluded transaction type: ' . $t_type,
                'reference_no' => (string)($txn['reference_no'] ?? ''),
                'member_name' => trim((string)($txn['member_last_name'] ?? '') . ', ' . (string)($txn['member_first_name'] ?? '') . ' ' . (string)($txn['member_second_name'] ?? '') . ' ' . (string)($txn['member_middle_name'] ?? '')),
                'transaction_type' => $t_type,
            ];
            continue;
        }

        $reference_value = normalizeTxnImportReferenceNumber($txn['reference_no'] !== '' ? $txn['reference_no'] : $txn['invoice']);
        if ($txn_name_display === '' || $reference_value === '' || !$txn['has_item']) {
            $unreadable_count++;
            $missing_bits = [];
            if ($txn_name_display === '') $missing_bits[] = 'missing member name';
            if ($reference_value === '') $missing_bits[] = 'missing reference number';
            if (!$txn['has_item']) $missing_bits[] = 'missing item rows';
            $unreadable_rows[] = [
                'row_number' => $row_number,
                'reason' => 'Unreadable row: ' . implode(', ', $missing_bits),
                'reference_no' => (string)($txn['reference_no'] ?? $txn['invoice'] ?? ''),
                'member_name' => trim((string)($txn['member_last_name'] ?? '') . ', ' . (string)($txn['member_first_name'] ?? '') . ' ' . (string)($txn['member_second_name'] ?? '') . ' ' . (string)($txn['member_middle_name'] ?? '')),
                'transaction_type' => (string)($txn['transaction_type'] ?? ''),
            ];
            continue;
        }

        $incoming_row = [
            'transaction_date' => $txn['date'],
            'member_id' => $member_id ?? 0,
            'member_name' => $txn_name_display,
            'transaction_type' => $t_type,
            'invoice_no' => $reference_value,
            'items_details' => $items_str,
            'payment_status' => $txn['status'] !== '' ? $txn['status'] : 'COMPLETED',
            'downpayment' => $txn['downpayment'],
            'remaining_balance' => $txn['balance'],
            'amount' => $txn['total_amount'],
        ];
        $incoming_fingerprint = buildTxnImportFingerprint($incoming_row);

        $matched_tid = null;
        if ($duplicate_action !== 'append') {
            $check = $conn->prepare("SELECT transaction_id, transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance FROM transactions WHERE invoice_no = ? OR COALESCE(NULLIF(TRIM(LEADING '0' FROM invoice_no), ''), '0') = ?");
            $check_reference_original = ($txn['reference_no'] !== '' ? $txn['reference_no'] : $txn['invoice']);
            $check_reference_normalized = $reference_value;
            $check->bind_param("ss", $check_reference_original, $check_reference_normalized);
            $check->execute();
            $c_res = $check->get_result();

            if ($c_res && $c_res->num_rows > 0) {
                while ($existing_row = $c_res->fetch_assoc()) {
                    $existing_fingerprint = buildTxnImportFingerprint($existing_row);
                    if ($existing_fingerprint === $incoming_fingerprint) {
                        $matched_tid = (int)$existing_row['transaction_id'];
                        break;
                    }
                }
            }
        }

        if ($matched_tid !== null) {
            $update_date = $txn['date'];
            $update_member_id = $member_id;
            $update_member_name = $txn_name_display;
            $update_type = $t_type;
            $update_amount = (float)$txn['total_amount'];
            $update_items = $items_str;
            $update_reference = $reference_value;
            $update_status = $incoming_row['payment_status'];
            $update_downpayment = (float)$txn['downpayment'];
            $update_balance = (float)$txn['balance'];
            $update_tid = $matched_tid;
            $upd = $conn->prepare("UPDATE transactions SET transaction_date=?, member_id=?, member_name=?, transaction_type=?, amount=?, items_details=?, invoice_no=?, payment_status=?, downpayment=?, remaining_balance=? WHERE transaction_id=?");
            $upd->bind_param("sissdsssddi", $update_date, $update_member_id, $update_member_name, $update_type, $update_amount, $update_items, $update_reference, $update_status, $update_downpayment, $update_balance, $update_tid);
            $upd->execute();
            $updated_count++;
            $overwritten_rows[] = [
                'transaction_id' => $matched_tid,
                'date' => $txn['date'],
                'reference_no' => $reference_value,
                'member_name' => $txn_name_display,
                'transaction_type' => $t_type,
                'amount' => $txn['total_amount'],
            ];
        } else {
            $ins = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_date = $txn['date'];
            $insert_member_id = $member_id;
            $insert_member_name = $txn_name_display;
            $insert_type = $t_type;
            $insert_amount = (float)$txn['total_amount'];
            $insert_items = $items_str;
            $insert_reference = $reference_value;
            $insert_status = $incoming_row['payment_status'];
            $insert_downpayment = (float)$txn['downpayment'];
            $insert_balance = (float)$txn['balance'];
            $ins->bind_param("sissdsssdd", $insert_date, $insert_member_id, $insert_member_name, $insert_type, $insert_amount, $insert_items, $insert_reference, $insert_status, $insert_downpayment, $insert_balance);
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
            'Inserted ' . $inserted_count . ' transaction row(s), overwritten ' . $updated_count . ' row(s), and skipped ' . $unreadable_count . ' unreadable row(s) from Excel.'
        );
    }

    $_SESSION['alert_title'] = "Transactions Uploaded";
    $overwritten_detail_html = '';
    if (!empty($overwritten_rows)) {
        $overwritten_detail_html .= '<details class="mt-3 rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-left">';
        $overwritten_detail_html .= '<summary class="cursor-pointer list-none font-semibold text-amber-800">Overwritten Rows (' . count($overwritten_rows) . ')</summary>';
        $overwritten_detail_html .= '<ul class="mt-2 list-disc pl-5 space-y-1 text-amber-900">';
        foreach ($overwritten_rows as $row) {
            $overwritten_detail_html .= '<li>';
            $overwritten_detail_html .= '<span class="font-semibold">ID #' . (int)$row['transaction_id'] . '</span>';
            $overwritten_detail_html .= ' | ' . htmlspecialchars((string)$row['date']);
            $overwritten_detail_html .= ' | ' . htmlspecialchars((string)$row['reference_no']);
            $overwritten_detail_html .= ' | ' . htmlspecialchars((string)$row['member_name']);
            $overwritten_detail_html .= ' | ' . htmlspecialchars((string)$row['transaction_type']);
            $overwritten_detail_html .= ' | ₱' . number_format((float)$row['amount'], 2);
            $overwritten_detail_html .= '</li>';
        }
        $overwritten_detail_html .= '</ul></details>';
    }
    $unreadable_detail_html = '';
    if (!empty($unreadable_rows)) {
        $unreadable_detail_html .= '<details class="mt-3 rounded-xl border border-red-200 bg-red-50/80 p-3 text-left">';
        $unreadable_detail_html .= '<summary class="cursor-pointer list-none font-semibold text-red-800">Unreadable Rows (' . count($unreadable_rows) . ')</summary>';
        $unreadable_detail_html .= '<ul class="mt-2 list-disc pl-5 space-y-1 text-red-900">';
        foreach ($unreadable_rows as $row) {
            $unreadable_detail_html .= '<li>';
            $unreadable_detail_html .= '<span class="font-semibold">Row #' . (int)$row['row_number'] . '</span>';
            if (!empty($row['reference_no'])) {
                $unreadable_detail_html .= ' | ' . htmlspecialchars((string)$row['reference_no']);
            }
            if (!empty($row['member_name'])) {
                $unreadable_detail_html .= ' | ' . htmlspecialchars((string)$row['member_name']);
            }
            if (!empty($row['transaction_type'])) {
                $unreadable_detail_html .= ' | ' . htmlspecialchars((string)$row['transaction_type']);
            }
            $unreadable_detail_html .= ' | ' . htmlspecialchars((string)$row['reason']);
            $unreadable_detail_html .= '</li>';
        }
        $unreadable_detail_html .= '</ul></details>';
    }
    $_SESSION['alert_message'] = "Added: <strong>{$inserted_count}</strong><br>Overwritten: <strong>{$updated_count}</strong><br>Unreadable: <strong>{$unreadable_count}</strong>" . $overwritten_detail_html . $unreadable_detail_html;
    $_SESSION['alert_type'] = ($inserted_count === 0 && $updated_count === 0) ? "error" : (($unreadable_count > 0) ? "info" : "success");
    
    header("Location: transactions.php"); // Redirecting back to transactions.php to avoid showing the alert on index.php
    exit();
}
?>
