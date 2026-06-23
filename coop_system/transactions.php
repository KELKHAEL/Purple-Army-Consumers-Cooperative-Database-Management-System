<?php 
session_start(); // CRITICAL: Added this so the page can receive alerts!
include 'db.php'; 

$transaction_types = function_exists('getTransactionTypes') ? getTransactionTypes($conn, true) : [];
if (empty($transaction_types) && function_exists('getTransactionTypes')) {
    $transaction_types = getTransactionTypes($conn, false);
}

function isExcludedSalesPurchaseType(string $label): bool {
    $normalized = function_exists('mb_strtoupper') ? mb_strtoupper(trim($label), 'UTF-8') : strtoupper(trim($label));
    return in_array($normalized, [
        'MEMBERSHIP FEE',
        'SHARE CAPITAL',
        'MEMBERSHIP SHARE CAPITAL',
        'SHARES CAPITAL',
        'SHARE',
    ], true);
}

$transaction_types = array_values(array_filter($transaction_types, function ($type) {
    return !isExcludedSalesPurchaseType((string)($type['name'] ?? ''));
}));

$transaction_type_names = array_map(static function (array $type): string {
    return trim((string)($type['name'] ?? ''));
}, $transaction_types);
$transaction_type_order = array_flip($transaction_type_names);

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

function normalizeTransactionMemberLookupName(string $value): string {
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9\s]/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function buildTransactionMemberDisplayName(string $last, string $first, string $middle = ''): string {
    $last = trim($last);
    $first = trim($first);
    $middle = trim($middle);

    $name = $last . ', ' . $first;
    if ($middle !== '') {
        $name .= ' ' . $middle;
    }

    return preg_replace('/\s+/', ' ', trim($name));
}

function findTransactionMemberMatch(array $members, string $displayName): ?array {
    $needle = normalizeTransactionMemberLookupName($displayName);
    if ($needle === '') {
        return null;
    }

    foreach ($members as $member) {
        $candidateDisplay = buildTransactionMemberDisplayName(
            (string)($member['last_name'] ?? ''),
            (string)($member['first_name'] ?? ''),
            (string)($member['middle_name'] ?? '')
        );

        $variants = [
            normalizeTransactionMemberLookupName($candidateDisplay),
            normalizeTransactionMemberLookupName(trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['middle_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''))),
            normalizeTransactionMemberLookupName(trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''))),
            normalizeTransactionMemberLookupName(trim((string)($member['last_name'] ?? '') . ' ' . (string)($member['first_name'] ?? '') . ' ' . (string)($member['middle_name'] ?? ''))),
        ];

        $variants = array_values(array_filter(array_unique($variants), static fn($value) => $value !== ''));
        if (in_array($needle, $variants, true)) {
            return $member;
        }
    }

    return null;
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

function normalizeManualTxnText(string $input): string {
    $value = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    if ($value === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function normalizeManualTxnMoney($input): string {
    $value = preg_replace('/[^0-9\.\-]/', '', (string)$input);
    if ($value === '' || !is_numeric($value)) {
        return number_format(0, 2, '.', '');
    }
    return number_format((float)$value, 2, '.', '');
}

function normalizeManualTxnItems(string $itemsDetails): string {
    $lines = preg_split("/\r\n|\n|\r/", $itemsDetails) ?: [];
    $normalized = [];
    foreach ($lines as $line) {
        $line = normalizeManualTxnText($line);
        if ($line !== '') {
            $normalized[] = $line;
        }
    }
    return implode("\n", $normalized);
}

function buildManualTxnFingerprint(array $record): string {
    return sha1(json_encode([
        'transaction_date' => normalizeTxnImportIdentifier((string)($record['transaction_date'] ?? '')),
        'member_id' => (int)($record['member_id'] ?? 0),
        'member_name' => normalizeManualTxnText((string)($record['member_name'] ?? '')),
        'transaction_type' => normalizeManualTxnText((string)($record['transaction_type'] ?? '')),
        'items_details' => normalizeManualTxnItems((string)($record['items_details'] ?? '')),
        'invoice_no' => normalizeTxnImportIdentifier((string)($record['invoice_no'] ?? '')),
        'payment_status' => normalizeManualTxnText((string)($record['payment_status'] ?? '')),
        'downpayment' => normalizeManualTxnMoney($record['downpayment'] ?? 0),
        'remaining_balance' => normalizeManualTxnMoney($record['remaining_balance'] ?? 0),
        'amount' => normalizeManualTxnMoney($record['amount'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function saveManualTxnRecord(mysqli $conn, array $record): array {
    $reference_no = trim((string)($record['invoice_no'] ?? ''));
    if ($reference_no === '') {
        return ['status' => 'error', 'title' => 'Invalid Entry', 'message' => 'Please enter a reference number.'];
    }

    $incoming_date = trim((string)($record['transaction_date'] ?? date('Y-m-d')));
    $incoming_fingerprint = buildManualTxnFingerprint($record);
    $incoming_member_id = isset($record['member_id']) && $record['member_id'] !== '' ? (int)$record['member_id'] : null;
    $allow_conflict = !empty($record['allow_conflict_proceed']);

    $check = $conn->prepare("SELECT transaction_id, transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance FROM transactions WHERE invoice_no = ?");
    if (!$check) {
        return ['status' => 'error', 'title' => 'Database Error', 'message' => 'Unable to read transaction records.'];
    }

    $check->bind_param("s", $reference_no);
    $check->execute();
    $existing = $check->get_result();

    $exact_match_id = null;
    $conflict_dates = [];
    if ($existing && $existing->num_rows > 0) {
        while ($existing_row = $existing->fetch_assoc()) {
            $existing_date = trim((string)($existing_row['transaction_date'] ?? ''));
            if (normalizeTxnImportIdentifier($existing_date) !== normalizeTxnImportIdentifier($incoming_date)) {
                $conflict_dates[] = $existing_date;
                continue;
            }

            $existing_fingerprint = buildManualTxnFingerprint($existing_row);
            if ($existing_fingerprint === $incoming_fingerprint) {
                $exact_match_id = (int)$existing_row['transaction_id'];
                break;
            }
        }
    }
    $check->close();

    if (!empty($conflict_dates) && !$allow_conflict) {
        $conflict_dates = array_values(array_unique(array_filter($conflict_dates)));
        return [
            'status' => 'conflict',
            'title' => 'Receipt Conflict',
            'message' => 'Receipt number <strong>' . htmlspecialchars($reference_no) . '</strong> already exists on a different date: <strong>' . htmlspecialchars(implode(', ', $conflict_dates)) . '</strong>.<br>Do you want to proceed anyway?',
            'conflict_dates' => $conflict_dates,
        ];
    }

    $member_id = $incoming_member_id;
    $stmt = null;

    if ($exact_match_id !== null) {
        $stmt = $conn->prepare("UPDATE transactions SET transaction_date = ?, member_id = ?, member_name = ?, transaction_type = ?, amount = ?, items_details = ?, invoice_no = ?, payment_status = ?, downpayment = ?, remaining_balance = ? WHERE transaction_id = ?");
        if (!$stmt) {
            return ['status' => 'error', 'title' => 'Database Error', 'message' => 'Unable to update the matching transaction.'];
        }
        $stmt->bind_param(
            "sissdsssddi",
            $incoming_date,
            $member_id,
            $record['member_name'],
            $record['transaction_type'],
            $record['amount'],
            $record['items_details'],
            $reference_no,
            $record['payment_status'],
            $record['downpayment'],
            $record['remaining_balance'],
            $exact_match_id
        );
        if (!$stmt->execute()) {
            $stmt->close();
            return ['status' => 'error', 'title' => 'Database Error', 'message' => 'Unable to overwrite the matching transaction.'];
        }
        $stmt->close();
        return ['status' => 'updated', 'transaction_id' => $exact_match_id];
    }

    $stmt = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return ['status' => 'error', 'title' => 'Database Error', 'message' => 'Unable to prepare the transaction save.'];
    }
    $stmt->bind_param(
        "sissdsssdd",
        $incoming_date,
        $member_id,
        $record['member_name'],
        $record['transaction_type'],
        $record['amount'],
        $record['items_details'],
        $reference_no,
        $record['payment_status'],
        $record['downpayment'],
        $record['remaining_balance']
    );
    if (!$stmt->execute()) {
        $stmt->close();
        return ['status' => 'error', 'title' => 'Database Error', 'message' => 'Unable to save the manual transaction.'];
    }
    $transaction_id = (int)$conn->insert_id;
    $stmt->close();

    return ['status' => 'inserted', 'transaction_id' => $transaction_id];
}

$unit_types = getConfiguredUnitTypes($conn);
$members = [];
$member_result = $conn->query("SELECT member_id, last_name, first_name, middle_name FROM members ORDER BY last_name ASC, first_name ASC");
if ($member_result && $member_result->num_rows > 0) {
    while ($member_row = $member_result->fetch_assoc()) {
        $members[] = $member_row;
    }
}

if (isset($_GET['cancel_manual_conflict']) && $_GET['cancel_manual_conflict'] === '1') {
    unset($_SESSION['pending_manual_transaction'], $_SESSION['pending_manual_conflict_message']);
    header("Location: transactions.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_same_receipt_conflict']) && !empty($_SESSION['pending_manual_transaction']) && is_array($_SESSION['pending_manual_transaction'])) {
    $pending_txn = $_SESSION['pending_manual_transaction'];
    $pending_txn['allow_conflict_proceed'] = true;
    $save_result = saveManualTxnRecord($conn, $pending_txn);
    unset($_SESSION['pending_manual_transaction'], $_SESSION['pending_manual_conflict_message']);

    if (($save_result['status'] ?? '') === 'inserted' || ($save_result['status'] ?? '') === 'updated') {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'ADD MANUAL TRANSACTION',
                'TRANSACTION',
                (int)($save_result['transaction_id'] ?? 0),
                (string)($pending_txn['invoice_no'] ?? ''),
                'Type: ' . ($pending_txn['transaction_type'] ?? '') . ', Items: ' . (int)($pending_txn['item_count'] ?? 0) . ', Amount: ' . number_format((float)($pending_txn['amount'] ?? 0), 2)
            );
        }

        $_SESSION['alert_title'] = "Transaction Saved";
        $_SESSION['alert_message'] = "The transaction was saved after confirmation.";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['alert_title'] = $save_result['title'] ?? 'Database Error';
        $_SESSION['alert_message'] = $save_result['message'] ?? 'Unable to save the manual transaction.';
        $_SESSION['alert_type'] = "error";
    }

    header("Location: transactions.php");
    exit();
}

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

    $record = [
        'transaction_date' => $transaction_date,
        'member_id' => $member_id,
        'member_name' => $member_name,
        'transaction_type' => $transaction_type,
        'amount' => $amount,
        'items_details' => $items_details,
        'invoice_no' => $reference_no,
        'payment_status' => $payment_status,
        'downpayment' => $downpayment,
        'remaining_balance' => $remaining_balance,
        'item_count' => count($item_lines),
    ];

    $save_result = saveManualTxnRecord($conn, $record);
    if (($save_result['status'] ?? '') === 'conflict') {
        $_SESSION['pending_manual_transaction'] = $record;
        $_SESSION['pending_manual_conflict_message'] = $save_result['message'] ?? '';
        $_SESSION['manual_conflict_title'] = $save_result['title'] ?? 'Receipt Conflict';
        $_SESSION['show_manual_conflict_confirm'] = 1;
        header("Location: transactions.php");
        exit();
    }

    if (($save_result['status'] ?? '') === 'inserted' || ($save_result['status'] ?? '') === 'updated') {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'ADD MANUAL TRANSACTION',
                'TRANSACTION',
                (int)($save_result['transaction_id'] ?? 0),
                $reference_no,
                'Type: ' . $transaction_type . ', Items: ' . count($item_lines) . ', Amount: ' . number_format($amount, 2)
            );
        }
        $_SESSION['alert_title'] = "Transaction Saved";
        $_SESSION['alert_message'] = (($save_result['status'] ?? '') === 'updated')
            ? "Manual transaction matched an existing exact record and was overwritten."
            : "Manual transaction saved successfully.";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['alert_title'] = $save_result['title'] ?? "Database Error";
        $_SESSION['alert_message'] = $save_result['message'] ?? "Unable to save the manual transaction.";
        $_SESSION['alert_type'] = "error";
    }
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

    $record = [
        'transaction_date' => $transaction_date,
        'member_id' => $member_id,
        'member_name' => $member_name,
        'transaction_type' => $transaction_type,
        'amount' => $amount,
        'items_details' => $items_details,
        'invoice_no' => $reference_no,
        'payment_status' => $payment_status,
        'downpayment' => 0,
        'remaining_balance' => 0,
        'item_count' => 1,
    ];

    $save_result = saveManualTxnRecord($conn, $record);
    if (($save_result['status'] ?? '') === 'conflict') {
        $_SESSION['pending_manual_transaction'] = $record;
        $_SESSION['pending_manual_conflict_message'] = $save_result['message'] ?? '';
        $_SESSION['manual_conflict_title'] = $save_result['title'] ?? 'Receipt Conflict';
        $_SESSION['show_manual_conflict_confirm'] = 1;
        header("Location: transactions.php");
        exit();
    }

    if (($save_result['status'] ?? '') === 'inserted' || ($save_result['status'] ?? '') === 'updated') {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'ADD MANUAL TRANSACTION',
                'TRANSACTION',
                (int)($save_result['transaction_id'] ?? 0),
                $reference_no,
                'Type: ' . $transaction_type . ', Item: ' . $item_name . ', Qty: ' . $item_quantity . ', Amount: ' . number_format($amount, 2)
            );
        }
        $_SESSION['alert_title'] = "Transaction Saved";
        $_SESSION['alert_message'] = (($save_result['status'] ?? '') === 'updated')
            ? "Manual transaction matched an existing exact record and was overwritten."
            : "Manual transaction saved successfully.";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['alert_title'] = $save_result['title'] ?? "Database Error";
        $_SESSION['alert_message'] = $save_result['message'] ?? "Unable to save the manual transaction.";
        $_SESSION['alert_type'] = "error";
    }
    header("Location: transactions.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction_record'])) {
    $transaction_id = (int)($_POST['transaction_id'] ?? 0);
    $transaction_date = trim((string)($_POST['transaction_date'] ?? ''));
    $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
    $member_name_input = trim((string)($_POST['member_name'] ?? ''));
    $reference_no = trim((string)($_POST['reference_no'] ?? ''));
    $items_details = trim((string)($_POST['items_details'] ?? ''));
    $payment_status = strtoupper(trim((string)($_POST['payment_status'] ?? 'COMPLETED')));
    $amount = (float)($_POST['amount'] ?? 0);
    $downpayment = (float)($_POST['downpayment'] ?? 0);
    $remaining_balance = (float)($_POST['remaining_balance'] ?? 0);

    $existing_row = null;
    $stmt_existing = $conn->prepare("SELECT * FROM transactions WHERE transaction_id = ? LIMIT 1");
    if ($stmt_existing) {
        $stmt_existing->bind_param("i", $transaction_id);
        $stmt_existing->execute();
        $existing_res = $stmt_existing->get_result();
        $existing_row = $existing_res ? $existing_res->fetch_assoc() : null;
        $stmt_existing->close();
    }

    $type_row = null;
    foreach ($transaction_types as $type) {
        if ((int)$type['id'] === $transaction_type_id) {
            $type_row = $type;
            break;
        }
    }

    if ($transaction_id <= 0 || !$existing_row || !$type_row || $transaction_date === '' || $amount <= 0) {
        $_SESSION['alert_title'] = "Invalid Entry";
        $_SESSION['alert_message'] = "Please provide a valid transaction, type, date, and amount.";
        $_SESSION['alert_type'] = "error";
        header("Location: transactions.php");
        exit();
    }

    $resolved_member = null;
    $member_id = (int)($existing_row['member_id'] ?? 0);

    if ($member_name_input !== '') {
        $resolved_member = findTransactionMemberMatch($members, $member_name_input);
    }

    if ($resolved_member !== null) {
        $member_id = (int)($resolved_member['member_id'] ?? 0);
        $member_name_input = buildTransactionMemberDisplayName(
            (string)($resolved_member['last_name'] ?? ''),
            (string)($resolved_member['first_name'] ?? ''),
            (string)($resolved_member['middle_name'] ?? '')
        );
    } elseif ($member_name_input === '') {
        $member_name_input = (string)($existing_row['member_name'] ?? '');
    }

    if ($items_details === '') {
        $items_details = (string)($existing_row['items_details'] ?? '');
    }
    if ($reference_no === '') {
        $reference_no = (string)($existing_row['invoice_no'] ?? '');
    }
    if ($payment_status === '') {
        $payment_status = strtoupper((string)($existing_row['payment_status'] ?? 'COMPLETED'));
    }
    if ($downpayment <= 0 && (float)($existing_row['downpayment'] ?? 0) > 0) {
        $downpayment = (float)($existing_row['downpayment'] ?? 0);
    }
    if ($remaining_balance <= 0 && (float)($existing_row['remaining_balance'] ?? 0) > 0) {
        $remaining_balance = (float)($existing_row['remaining_balance'] ?? 0);
    }

    $transaction_type = (string)$type_row['name'];

    $stmt_update = $conn->prepare("UPDATE transactions SET transaction_date = ?, member_id = ?, member_name = ?, transaction_type = ?, amount = ?, items_details = ?, invoice_no = ?, payment_status = ?, downpayment = ?, remaining_balance = ? WHERE transaction_id = ?");
    if (!$stmt_update) {
        $_SESSION['alert_title'] = "Database Error";
        $_SESSION['alert_message'] = "Unable to prepare the transaction update statement.";
        $_SESSION['alert_type'] = "error";
        header("Location: transactions.php");
        exit();
    }

    $stmt_update->bind_param("sissdsssddi", $transaction_date, $member_id, $member_name_input, $transaction_type, $amount, $items_details, $reference_no, $payment_status, $downpayment, $remaining_balance, $transaction_id);

    if ($stmt_update->execute()) {
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'SALES',
                'UPDATE TRANSACTION',
                'TRANSACTION',
                $transaction_id,
                $reference_no !== '' ? $reference_no : (string)$transaction_id,
                'Type: ' . $transaction_type . ', Member: ' . $member_name_input . ', Amount: ' . number_format($amount, 2)
            );
        }

        $_SESSION['alert_title'] = "Transaction Updated";
        $_SESSION['alert_message'] = "The transaction record was updated successfully.";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['alert_title'] = "Database Error";
        $_SESSION['alert_message'] = "Unable to update the transaction record.";
        $_SESSION['alert_type'] = "error";
    }

    $stmt_update->close();
    header("Location: transactions.php");
    exit();
}

function getTransactionRows(mysqli $conn): array {
    $rows = [];
    $sql = "SELECT t.*, COALESCE(ct.name, t.transaction_type) AS transaction_type_label
            FROM transactions t
            LEFT JOIN config_transaction_types ct ON LOWER(TRIM(t.transaction_type)) = LOWER(TRIM(ct.name))
            ORDER BY t.transaction_date DESC, t.invoice_no DESC, COALESCE(ct.name, t.transaction_type) ASC, t.transaction_id DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $label = trim((string)($row['transaction_type_label'] ?? $row['transaction_type'] ?? ''));
            if ($label !== '' && isExcludedSalesPurchaseType($label)) {
                continue;
            }
            $rows[] = $row;
        }
    }
    return $rows;
}

function groupTransactionRows(array $rows, array $typeOrder = []): array {
    $grouped = [];
    foreach ($rows as $row) {
        $label = trim((string)($row['transaction_type_label'] ?? $row['transaction_type'] ?? 'Other'));
        if ($label === '') {
            $label = 'Other';
        }
        if (isExcludedSalesPurchaseType($label)) {
            continue;
        }
        if (!isset($grouped[$label])) {
            $grouped[$label] = [];
        }
        $grouped[$label][] = $row;
    }

    if (!empty($typeOrder)) {
        uksort($grouped, function ($a, $b) use ($typeOrder) {
            $aRank = array_key_exists($a, $typeOrder) ? (int)$typeOrder[$a] : PHP_INT_MAX;
            $bRank = array_key_exists($b, $typeOrder) ? (int)$typeOrder[$b] : PHP_INT_MAX;
            if ($aRank === $bRank) {
                return strnatcasecmp($a, $b);
            }
            return $aRank <=> $bRank;
        });
    } else {
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
    }
    return $grouped;
}

function getInventorySettingValue(mysqli $conn, string $settingKey, string $default = ''): string {
    $stmt = $conn->prepare("SELECT setting_value FROM config_inventory_settings WHERE setting_key = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $settingKey);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $value = (string)($result->fetch_assoc()['setting_value'] ?? '');
            $stmt->close();
            return $value !== '' ? $value : $default;
        }
        $stmt->close();
    }
    return $default;
}

function normalizeReceiptUnitKey(string $unit): string {
    $unit = function_exists('mb_strtoupper') ? mb_strtoupper(trim($unit), 'UTF-8') : strtoupper(trim($unit));
    $unit = preg_replace('/\s+/u', ' ', $unit);
    return trim($unit);
}

function parseReceiptItemLine(string $line, array $unitLookup = []): array {
    $line = trim(preg_replace('/\s+/u', ' ', $line));
    if ($line === '') {
        return [];
    }

    $result = [
        'qty' => '',
        'unit' => '',
        'name' => $line,
        'cost' => '',
        'amount' => '',
    ];

    $left = $line;
    $right = '';
    if (strpos($line, '@') !== false) {
        [$left, $right] = array_pad(explode('@', $line, 2), 2, '');
    }

    $left = trim($left);
    $right = trim($right);

    if ($left !== '') {
        $tokens = preg_split('/\s+/u', $left) ?: [];
        if (!empty($tokens) && preg_match('/^(\d+(?:\.\d+)?)(?:x)?$/i', $tokens[0], $qtyMatch)) {
            $result['qty'] = $qtyMatch[1];
            array_shift($tokens);

            if (!empty($tokens)) {
                $candidate_unit = normalizeReceiptUnitKey($tokens[0]);
                if ($candidate_unit !== '' && in_array($candidate_unit, $unitLookup, true)) {
                    $result['unit'] = array_shift($tokens);
                }
            }

            $result['name'] = trim(implode(' ', $tokens));
        } else {
            $result['name'] = $left;
        }
    }

    if ($right !== '') {
        $right = preg_replace('/[^\d\.\-\=,\s]/u', ' ', $right);
        preg_match_all('/\d[\d,]*(?:\.\d+)?/u', $right, $numberMatches);
        $numbers = $numberMatches[0] ?? [];

        if (!empty($numbers)) {
            $result['cost'] = str_replace(',', '', $numbers[0]);
        }
        if (count($numbers) > 1) {
            $result['amount'] = str_replace(',', '', $numbers[1]);
        }
        if ($result['amount'] === '' && $result['qty'] !== '' && $result['cost'] !== '') {
            $result['amount'] = (string)(((float)str_replace(',', '', $result['qty'])) * (float)$result['cost']);
        }
    }

    if ($result['amount'] === '' && $result['qty'] !== '' && $result['cost'] !== '') {
        $result['amount'] = (string)(((float)str_replace(',', '', $result['qty'])) * (float)$result['cost']);
    }

    return $result;
}

function buildReceiptPayload(array $row, array $unitLookup, string $treasurerName, string $managerName): array {
    $items = [];
    $rawLines = preg_split("/\r\n|\n|\r/", (string)($row['items_details'] ?? '')) ?: [];
    foreach ($rawLines as $rawLine) {
        $parsed = parseReceiptItemLine((string)$rawLine, $unitLookup);
        if (!empty($parsed)) {
            $items[] = $parsed;
        }
    }

    $transaction_amount = (float)($row['amount'] ?? 0);
    if ($transaction_amount > 0 && count($items) === 1) {
        $onlyItem = $items[0];
        $parsedCost = (float)str_replace(',', '', (string)($onlyItem['cost'] ?? '0'));
        $parsedAmount = (float)str_replace(',', '', (string)($onlyItem['amount'] ?? '0'));
        if ($parsedCost <= 0 && $parsedAmount <= 0) {
            $items[0]['qty'] = $onlyItem['qty'] !== '' ? $onlyItem['qty'] : '1';
            $items[0]['cost'] = number_format($transaction_amount, 2, '.', '');
            $items[0]['amount'] = number_format($transaction_amount, 2, '.', '');
        }
    }

    return [
        'transaction_id' => (int)($row['transaction_id'] ?? 0),
        'transaction_date' => (string)($row['transaction_date'] ?? ''),
        'member_name' => (string)($row['member_name'] ?? ''),
        'transaction_type' => (string)($row['transaction_type_label'] ?? $row['transaction_type'] ?? ''),
        'invoice_no' => (string)($row['invoice_no'] ?? ''),
        'payment_status' => (string)($row['payment_status'] ?? 'COMPLETED'),
        'downpayment' => (float)($row['downpayment'] ?? 0),
        'remaining_balance' => (float)($row['remaining_balance'] ?? 0),
        'amount' => (float)($row['amount'] ?? 0),
        'items' => $items,
        'treasurer_name' => $treasurerName,
        'manager_name' => $managerName,
    ];
}

$transaction_rows = getTransactionRows($conn);
$transactions_by_type = groupTransactionRows($transaction_rows, $transaction_type_order);
$receipt_unit_lookup = [];
foreach ($unit_types as $unitRow) {
    $receipt_unit_lookup[] = normalizeReceiptUnitKey((string)($unitRow['name'] ?? ''));
}
$receipt_treasurer_name = getInventorySettingValue($conn, 'receipt_treasurer_name', 'HELENA GESTA');
$receipt_manager_name = getInventorySettingValue($conn, 'receipt_manager_name', 'VRIAN ANDREW B. PORTUGUESE');
$receipt_payloads = [];
$transaction_edit_payloads = [];
foreach ($transaction_rows as $receipt_row) {
    $receipt_payloads[(int)$receipt_row['transaction_id']] = buildReceiptPayload($receipt_row, $receipt_unit_lookup, $receipt_treasurer_name, $receipt_manager_name);
    $label = trim((string)($receipt_row['transaction_type_label'] ?? $receipt_row['transaction_type'] ?? ''));
    $type_id = 0;
    foreach ($transaction_types as $type) {
        if (normalizeTransactionMemberLookupName((string)($type['name'] ?? '')) === normalizeTransactionMemberLookupName($label)) {
            $type_id = (int)($type['id'] ?? 0);
            break;
        }
    }
    $transaction_edit_payloads[(int)$receipt_row['transaction_id']] = [
        'transaction_id' => (int)($receipt_row['transaction_id'] ?? 0),
        'transaction_date' => (string)($receipt_row['transaction_date'] ?? ''),
        'member_id' => (int)($receipt_row['member_id'] ?? 0),
        'member_name' => (string)($receipt_row['member_name'] ?? ''),
        'transaction_type' => $label,
        'transaction_type_id' => $type_id,
        'invoice_no' => (string)($receipt_row['invoice_no'] ?? ''),
        'payment_status' => (string)($receipt_row['payment_status'] ?? 'COMPLETED'),
        'amount' => (float)($receipt_row['amount'] ?? 0),
        'items_details' => (string)($receipt_row['items_details'] ?? ''),
        'downpayment' => (float)($receipt_row['downpayment'] ?? 0),
        'remaining_balance' => (float)($receipt_row['remaining_balance'] ?? 0),
    ];
}

$member_lookup_payloads = [];
foreach ($members as $member) {
    $display_name = buildTransactionMemberDisplayName(
        (string)($member['last_name'] ?? ''),
        (string)($member['first_name'] ?? ''),
        (string)($member['middle_name'] ?? '')
    );
    $member_lookup_payloads[] = [
        'member_id' => (int)($member['member_id'] ?? 0),
        'display_name' => $display_name,
        'normalized_name' => normalizeTransactionMemberLookupName($display_name),
    ];
}

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
        #transactionsReportCard .tx-group h4,
        #transactionsReportCard .tx-group td,
        #transactionsReportCard td,
        #transactionsReportCard .tx-group .text-gray-700,
        #transactionsReportCard .tx-group .text-gray-900 {
            text-transform: uppercase;
        }
        #manualTransactionModal input[type="text"],
        #manualTransactionModal select,
        #manualTransactionModal option,
        #transactionTypeFilter,
        #transactionTypeFilter option {
            text-transform: uppercase;
        }
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

    <div id="customAlertModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 overflow-y-auto">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm max-h-[calc(100vh-2rem)] overflow-y-auto overflow-x-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="customAlertBox">
            <div id="customAlertHeader" class="px-6 py-4 flex items-center gap-3 border-b">
                <i id="customAlertIcon" class="fas fa-exclamation-circle text-2xl"></i>
                <h3 id="customAlertTitle" class="text-lg font-bold tracking-tight">Alert</h3>
            </div>
            <div class="p-6 text-gray-600 text-sm leading-relaxed break-words" id="customAlertMessage">
                </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button id="customAlertBtn" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">OK</button>
            </div>
        </div>
    </div>

    <div id="manualConflictModal" class="fixed inset-0 z-[1001] hidden items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="manualConflictBox">
            <div class="px-6 py-4 flex items-center gap-3 border-b bg-amber-50 border-amber-100">
                <i class="fas fa-triangle-exclamation text-2xl text-amber-500"></i>
                <h3 class="text-lg font-bold tracking-tight text-amber-800"><?= htmlspecialchars($_SESSION['manual_conflict_title'] ?? 'Receipt Conflict') ?></h3>
            </div>
            <div class="p-6 text-gray-700 text-sm leading-relaxed" id="manualConflictMessage">
                <?= $_SESSION['pending_manual_conflict_message'] ?? 'The receipt number conflicts with another transaction on a different date.' ?>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <a href="transactions.php?cancel_manual_conflict=1" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg transition-colors shadow-sm">CANCEL</a>
                <form action="transactions.php" method="POST" class="m-0">
                    <input type="hidden" name="confirm_same_receipt_conflict" value="1">
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-4 rounded-lg transition-colors shadow-md">PROCEED ANYWAY</button>
                </form>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['manual_conflict_title'], $_SESSION['pending_manual_conflict_message']); ?>

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

    <datalist id="transactionMemberOptions">
        <?php foreach ($members as $member): ?>
            <option value="<?= htmlspecialchars(buildTransactionMemberDisplayName((string)($member['last_name'] ?? ''), (string)($member['first_name'] ?? ''), (string)($member['middle_name'] ?? ''))) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div id="transactionEditModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-2 sm:p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm" onclick="closeTransactionEditModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] z-10 overflow-hidden transform transition-all flex flex-col">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-gray-800"><i class="fas fa-pen-to-square text-primary mr-2"></i>Edit Transaction</h3>
                    <p class="text-xs text-gray-500 mt-1">Update the transaction details and match the member name in real time.</p>
                </div>
                <button type="button" onclick="closeTransactionEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-6">
                <form action="transactions.php" method="POST" class="space-y-4">
                    <input type="hidden" name="update_transaction_record" value="1">
                    <input type="hidden" name="transaction_id" id="editTransactionId" value="">
                    <input type="hidden" name="member_id" id="editTransactionMemberId" value="">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date <span class="text-red-500">*</span></label>
                            <input type="date" name="transaction_date" id="editTransactionDate" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type <span class="text-red-500">*</span></label>
                            <select name="transaction_type_id" id="editTransactionTypeId" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <option value="" disabled>Select Transaction Type</option>
                                <?php foreach ($transaction_types as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Member Name</label>
                        <input type="text" name="member_name" id="editTransactionMemberName" list="transactionMemberOptions" autocomplete="off" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        <p id="editTransactionMemberMatch" class="mt-1 text-xs font-semibold text-gray-500">Type a name to match an existing member.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference No. / Invoice No. / Receipt No.</label>
                            <input type="text" name="reference_no" id="editTransactionReferenceNo" placeholder="Optional" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="payment_status" id="editTransactionStatus" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <option value="COMPLETED">COMPLETED</option>
                                <option value="PAID">PAID</option>
                                <option value="PARTIALLY PAID">PARTIALLY PAID</option>
                                <option value="PENDING">PENDING</option>
                                <option value="CANCELLED">CANCELLED</option>
                                <option value="WAITING">WAITING</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Details</label>
                        <textarea name="items_details" id="editTransactionItemsDetails" rows="4" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" id="editTransactionAmount" step="0.01" min="0.01" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Downpayment</label>
                            <input type="number" name="downpayment" id="editTransactionDownpayment" step="0.01" min="0" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Balance</label>
                            <input type="number" name="remaining_balance" id="editTransactionBalance" step="0.01" min="0" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="closeTransactionEditModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors">CANCEL</button>
                        <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-md text-sm transition-colors shadow-md">SAVE CHANGES</button>
                    </div>
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
                <a href="transactions.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
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

        <main class="flex-1 flex flex-col h-screen min-h-0 overflow-hidden relative w-full">
            
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10 print:hidden">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Transaction Records</h1>
                </div>
            </header>

            <div class="flex-1 min-h-0 overflow-y-auto p-4 md:p-8">
                <div class="mb-6 space-y-4 print:hidden">
                    <div class="flex flex-col xl:flex-row items-stretch xl:items-center gap-3">
                        <div class="relative w-full sm:w-80 bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary shadow-sm">
                            <div class="px-3 py-2 text-gray-400 flex items-center justify-center absolute pointer-events-none">
                                <i class="fas fa-search"></i>
                            </div>
                            <input type="text" id="transactionSearch" placeholder="Search receipt, member, item..." oninput="filterTransactionGroups()" class="w-full py-2 pl-10 pr-4 outline-none text-sm text-gray-700 bg-transparent">
                        </div>
                        <select id="transactionSortOrder" class="bg-white border border-gray-300 rounded-md px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary w-full sm:w-56" onchange="filterTransactionGroups()">
                            <option value="DESC">Later Dates First</option>
                            <option value="ASC">Earlier Dates First</option>
                        </select>
                        <select id="transactionTypeFilter" class="bg-white border border-gray-300 rounded-md px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary w-full xl:w-56" onchange="filterTransactionGroups()">
                            <option value="ALL">All Transaction Types</option>
                            <?php foreach ($transaction_type_names as $type_name): ?>
                                <option value="<?= htmlspecialchars($type_name) ?>"><?= htmlspecialchars($type_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="openManualModal()" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                            <i class="fas fa-plus mr-2"></i>ADD MANUAL
                        </button>
                        <a href="transactions.php?export=excel" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                            <i class="fas fa-file-excel mr-2"></i>EXPORT
                        </a>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full xl:w-auto">
                            <div class="bg-white border border-gray-300 rounded-md px-3 py-2 shadow-sm">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1">From</div>
                                <input type="date" id="transactionDateFrom" onchange="filterTransactionGroups()" class="w-full bg-transparent outline-none text-sm text-gray-700">
                            </div>
                            <div class="bg-white border border-gray-300 rounded-md px-3 py-2 shadow-sm">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1">To</div>
                                <input type="date" id="transactionDateTo" onchange="filterTransactionGroups()" class="w-full bg-transparent outline-none text-sm text-gray-700">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <form action="import_transactions.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm items-center">
                            <input type="file" name="excel_file" accept=".xls,.xlsx" required class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:font-semibold file:bg-purple-50 file:text-primary hover:file:bg-purple-100 transition cursor-pointer">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1.5 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto whitespace-nowrap"><i class="fas fa-upload mr-1"></i> UPLOAD</button>
                        </form>
                        <a href="transactions.php?template=excel" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center whitespace-nowrap">
                            <i class="fas fa-download mr-2"></i>IMPORT TEMPLATE
                        </a>
                        <button onclick="updateTransactionPrintMeta(); window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm w-full sm:w-auto text-center border border-gray-300 whitespace-nowrap">
                            <i class="fas fa-print mr-2"></i>PRINT REPORT
                        </button>
                    </div>
                </div>
                <div class="transactions-print-header">
                    <div class="transactions-print-title">Transaction Records</div>
                    <div class="transactions-print-meta" id="txPrintMetaType">Type: All Transaction Types</div>
                    <div class="transactions-print-meta" id="txPrintMetaDate">Date Range: All Dates</div>
                    <div class="transactions-print-meta">Date Generated: <?= htmlspecialchars(date('F d, Y h:i A')) ?></div>
                </div>

                <div id="transactionsReportCard" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="font-bold text-gray-800"><i class="fas fa-list-ul text-primary mr-2"></i>All Financial Transactions</h4>
                    </div>

                    <div class="space-y-5 p-4 md:p-6">
                        <?php if (!empty($transactions_by_type)): ?>
                            <?php foreach ($transactions_by_type as $type_name => $rows): ?>
                                <?php
                                    $type_total_amount = 0.0;
                                    foreach ($rows as $type_row) {
                                        $type_total_amount += (float)($type_row['amount'] ?? 0);
                                    }
                                ?>
                                <section class="tx-group bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" data-tx-type="<?= htmlspecialchars($type_name) ?>">
                                    <div class="bg-gray-50 px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-gray-800 truncate"><i class="fas fa-layer-group text-primary mr-2"></i><?= htmlspecialchars($type_name) ?></h4>
                                            <div class="mt-1 text-xs text-gray-500">
                                                <?= count($rows) ?> record(s)
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Amount</div>
                                            <div class="text-sm font-extrabold text-gray-900">₱<?= number_format($type_total_amount, 2) ?></div>
                                        </div>
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
                                                    <th class="px-6 py-4 font-bold tracking-wider text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <?php foreach ($rows as $row): ?>
                                                    <?php
                                                        $date = date('M d, Y', strtotime($row['transaction_date']));
                                                        $inv = !empty($row['invoice_no']) ? htmlspecialchars($row['invoice_no']) : 'N/A';
                                                        $status = !empty($row['payment_status']) ? htmlspecialchars($row['payment_status']) : 'COMPLETED';
                                                        $remaining_balance = (float)($row['remaining_balance'] ?? 0);
                                                        $txn_type_label = strtoupper(trim((string)($row['transaction_type_label'] ?? $row['transaction_type'] ?? '')));
                                                        $needs_payment_link = $remaining_balance > 0 && (
                                                            stripos($status, 'pending') !== false ||
                                                            stripos($status, 'downpayment') !== false ||
                                                            stripos($txn_type_label, 'sale') !== false ||
                                                            stripos($txn_type_label, 'outsource') !== false
                                                        );
                                                        if (stripos($status, 'paid') !== false || stripos($status, 'completed') !== false) {
                                                            $stat_badge = "<span class='bg-green-100 text-green-800 px-2.5 py-1 rounded text-xs font-bold uppercase'>$status</span>";
                                                        } else {
                                                            $stat_badge = "<span class='bg-red-100 text-red-800 px-2.5 py-1 rounded text-xs font-bold uppercase'>$status</span>";
                                                        }
                                                    ?>
                                                    <tr class="hover:bg-purple-50 transition-colors tx-row" data-date="<?= htmlspecialchars($row['transaction_date']) ?>" data-invoice="<?= htmlspecialchars($row['invoice_no'] ?? '') ?>">
                                                        <td class="px-6 py-4 font-medium text-gray-500"><?= $date ?></td>
                                                        <td class="px-6 py-4 font-mono text-gray-700"><?= $inv ?></td>
                                                        <td class="px-6 py-4 font-bold text-gray-900 uppercase"><?= htmlspecialchars($row['member_name']) ?></td>
                                                        <td class="px-6 py-4 text-gray-700 whitespace-normal max-w-xl"><?= htmlspecialchars($row['items_details'] ?? '-') ?></td>
                                                        <td class="px-6 py-4 font-bold text-gray-900 text-right">₱<?= number_format((float)$row['amount'], 2) ?></td>
                                                        <td class="px-6 py-4 text-center"><?= $stat_badge ?></td>
                                                        <td class="px-6 py-4 text-center">
                                                            <div class="flex flex-col items-center gap-2">
                                                                <button type="button" onclick="openReceiptPrint(<?= (int)$row['transaction_id'] ?>)" class="inline-flex items-center gap-2 rounded-md border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-primary transition-colors hover:bg-primary hover:text-white">
                                                                    <i class="fas fa-receipt"></i> Print
                                                                </button>
                                                                <button type="button" onclick="openTransactionEditModal(<?= (int)$row['transaction_id'] ?>)" class="inline-flex items-center gap-2 rounded-md border border-amber-200 bg-amber-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-amber-800 transition-colors hover:bg-amber-500 hover:text-white">
                                                                    <i class="fas fa-pen-to-square"></i> Edit
                                                                </button>
                                                                <?php if ($needs_payment_link): ?>
                                                                    <a href="outsourcing_report.php?paylater_txn=<?= (int)$row['transaction_id'] ?>" class="inline-flex items-center gap-2 rounded-md border border-amber-200 bg-amber-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-amber-800 transition-colors hover:bg-amber-500 hover:text-white">
                                                                        <i class="fas fa-hand-holding-dollar"></i> Update Payment
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
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
                                    $sql = "SELECT * FROM transactions ORDER BY transaction_date DESC, invoice_no DESC, transaction_id DESC";
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

                                            echo "<tr class='hover:bg-purple-50 transition-colors tx-row' data-date='" . htmlspecialchars($row['transaction_date']) . "' data-invoice='" . htmlspecialchars($row['invoice_no'] ?? '') . "'>
                                                    <td class='px-6 py-4 font-medium text-gray-500'>{$date}</td>
                                                    <td class='px-6 py-4 font-mono text-gray-700'>{$inv}</td>
                                                    <td class='px-6 py-4 font-bold text-gray-900 uppercase'>" . htmlspecialchars($row['member_name']) . "</td>
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
        window.transactionReceiptMap = <?= json_encode($receipt_payloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.transactionEditMap = <?= json_encode($transaction_edit_payloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.transactionMemberLookup = <?= json_encode($member_lookup_payloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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

        function normalizeTransactionMemberName(value) {
            return String(value ?? '')
                .toUpperCase()
                .replace(/[^A-Z0-9\s]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function findTransactionMemberMatchInJs(value) {
            const normalized = normalizeTransactionMemberName(value);
            if (!normalized) {
                return null;
            }

            return (window.transactionMemberLookup || []).find(member => {
                const variants = [
                    member.normalized_name || '',
                    normalizeTransactionMemberName(member.display_name || ''),
                ].filter(Boolean);
                return variants.includes(normalized);
            }) || null;
        }

        function updateTransactionMemberMatch() {
            const nameInput = document.getElementById('editTransactionMemberName');
            const memberIdInput = document.getElementById('editTransactionMemberId');
            const matchEl = document.getElementById('editTransactionMemberMatch');
            if (!nameInput || !memberIdInput || !matchEl) {
                return;
            }

            const matched = findTransactionMemberMatchInJs(nameInput.value);
            if (matched) {
                memberIdInput.value = matched.member_id || '';
                matchEl.textContent = 'Matched member: ' + (matched.display_name || '') + ' (#' + (matched.member_id || '') + ')';
                matchEl.className = 'mt-1 text-xs font-semibold text-green-600';
            } else {
                memberIdInput.value = '';
                matchEl.textContent = nameInput.value.trim()
                    ? 'No exact match found yet. The record can still be saved.'
                    : 'Type a name to match an existing member.';
                matchEl.className = 'mt-1 text-xs font-semibold text-amber-600';
            }
        }

        function openTransactionEditModal(transactionId) {
            const data = window.transactionEditMap?.[transactionId];
            if (!data) {
                return;
            }

            const modal = document.getElementById('transactionEditModal');
            if (!modal) {
                return;
            }

            const transactionTypeId = data.transaction_type_id || '';
            document.getElementById('editTransactionId').value = data.transaction_id || transactionId || '';
            document.getElementById('editTransactionDate').value = data.transaction_date || '';
            document.getElementById('editTransactionTypeId').value = transactionTypeId;
            document.getElementById('editTransactionMemberId').value = data.member_id || '';
            document.getElementById('editTransactionMemberName').value = data.member_name || '';
            document.getElementById('editTransactionReferenceNo').value = data.invoice_no || '';
            document.getElementById('editTransactionStatus').value = (data.payment_status || 'COMPLETED').toUpperCase();
            document.getElementById('editTransactionItemsDetails').value = data.items_details || '';
            document.getElementById('editTransactionAmount').value = Number(data.amount || 0).toFixed(2);
            document.getElementById('editTransactionDownpayment').value = Number(data.downpayment || 0).toFixed(2);
            document.getElementById('editTransactionBalance').value = Number(data.remaining_balance || 0).toFixed(2);

            updateTransactionMemberMatch();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeTransactionEditModal() {
            const modal = document.getElementById('transactionEditModal');
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatMoney(value) {
            const amount = Number(value || 0);
            return amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatReceiptDate(value) {
            if (!value) {
                return '';
            }

            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return String(value);
            }

            return parsed.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function openReceiptPrint(transactionId) {
            const data = window.transactionReceiptMap?.[transactionId];
            if (!data) {
                return;
            }

            const receiptItems = (data.items || []).map(item => `
                <tr>
                    <td>${escapeHtml(item.name || '')}</td>
                    <td style="text-align:center;">${escapeHtml(item.qty || '')}</td>
                    <td style="text-align:center;">${escapeHtml(item.unit || '')}</td>
                    <td style="text-align:right;">${formatMoney(item.cost || 0)}</td>
                    <td style="text-align:right;">${formatMoney(item.amount || 0)}</td>
                </tr>
            `).join('');

            const receiptHtml = `
                <!doctype html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>Sales Receipt</title>
                    <style>
                        :root { --purple: #4b3b86; --ink: #111827; --muted: #6b7280; }
                        * { box-sizing: border-box; }
                        body {
                            margin: 0;
                            font-family: Arial, Helvetica, sans-serif;
                            background: #f3f4f6;
                            color: var(--ink);
                        }
                        .sheet {
                            width: 8.5in;
                            min-height: 11in;
                            margin: 0 auto;
                            background: #fff;
                            padding: 0.45in 0.55in;
                            text-transform: uppercase;
                        }
                        .title {
                            text-align: center;
                            margin-bottom: 16px;
                            font-weight: 700;
                            line-height: 1.1;
                            letter-spacing: 0.04em;
                        }
                        .title .brand { font-size: 20px; text-transform: uppercase; }
                        .title .record { font-size: 18px; text-transform: uppercase; margin-top: 4px; }
                        .title .date { margin-top: 8px; font-size: 13px; color: var(--muted); }
                        .meta {
                            display: flex;
                            justify-content: space-between;
                            gap: 16px;
                            margin: 12px 0 14px;
                            padding: 10px 12px;
                            border: 1px solid #d1d5db;
                            border-radius: 10px;
                            font-size: 13px;
                        }
                        .meta strong { display: inline-block; min-width: 90px; }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 12px;
                        }
                        thead th {
                            background: var(--purple);
                            color: #000000;
                            padding: 8px 6px;
                            text-transform: uppercase;
                            font-size: 11px;
                            letter-spacing: 0.06em;
                            border: 1px solid #372b66;
                        }
                        tbody td {
                            border: 1px solid #1f2937;
                            padding: 7px 6px;
                            vertical-align: top;
                        }
                        .summary {
                            display: grid;
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                            gap: 10px 28px;
                            margin-top: 12px;
                            font-size: 13px;
                        }
                        .summary-row {
                            display: flex;
                            align-items: baseline;
                            justify-content: space-between;
                            gap: 10px;
                            white-space: nowrap;
                        }
                        .summary-label { font-weight: 700; }
                        .summary-value { min-width: 90px; text-align: right; }
                        .summary div {
                            display: flex;
                            justify-content: space-between;
                            align-items: baseline;
                            gap: 10px;
                        }
                        .summary strong {
                            min-width: 110px;
                            text-align: left;
                        }
                        .signatures {
                            display: flex;
                            justify-content: space-between;
                            gap: 24px;
                            margin-top: 36px;
                        }
                        .sig {
                            width: 42%;
                            text-align: center;
                            font-size: 13px;
                        }
                        .sig .label {
                            margin-top: 8px;
                            font-size: 12px;
                            color: var(--muted);
                            text-transform: uppercase;
                        }
                        .sig .name {
                            margin-top: 42px;
                            font-weight: 700;
                            text-transform: uppercase;
                        }
                        @media print {
                            body { background: #fff; }
                            .sheet { width: 100%; min-height: auto; margin: 0; padding: 0.3in 0.4in; }
                            @page { size: letter; margin: 0.35in; }
                        }
                    </style>
                </head>
                <body onload="window.print(); setTimeout(() => window.close(), 250);">
                    <div class="sheet">
                        <div class="title">
                            <div class="brand">PURPLE ARMY CONSUMERS COOPERATIVE</div>
                            <div class="record">SALES RECORD</div>
                            <div class="date">${escapeHtml(formatReceiptDate(data.transaction_date || ''))}</div>
                        </div>

                        <div class="meta">
                            <div><strong>Member:</strong> ${escapeHtml(data.member_name || '')}</div>
                            <div><strong>Ref No.:</strong> ${escapeHtml(data.invoice_no || '')}</div>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40%;">Items</th>
                                    <th style="width:10%;">Qty</th>
                                    <th style="width:10%;">Unit</th>
                                    <th style="width:20%;">Price</th>
                                    <th style="width:20%;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${receiptItems || '<tr><td colspan="5" style="text-align:center;">No item details available.</td></tr>'}
                            </tbody>
                        </table>

                        <div class="summary">
                            <div><strong>Total Sales:</strong> ₱${formatMoney(data.amount)}</div>
                            <div><strong>Downpayment:</strong> ₱${formatMoney(data.downpayment)}</div>
                            <div><strong>Balance:</strong> ₱${formatMoney(data.remaining_balance)}</div>
                            <div><strong>Status:</strong> ${escapeHtml(data.payment_status || '')}</div>
                        </div>

                        <div class="signatures">
                            <div class="sig">
                                <div>Checked by:</div>
                                <div class="line"></div>
                                <div class="name">${escapeHtml(data.treasurer_name || '')}</div>
                                <div class="label">Treasurer</div>
                            </div>
                            <div class="sig">
                                <div>Noted by:</div>
                                <div class="line"></div>
                                <div class="name">${escapeHtml(data.manager_name || '')}</div>
                                <div class="label">Manager</div>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `;

            const receiptWindow = window.open('', '_blank', 'width=900,height=1100');
            if (!receiptWindow) {
                return;
            }
            receiptWindow.document.open();
            receiptWindow.document.write(receiptHtml);
            receiptWindow.document.close();
            receiptWindow.focus();
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
            const sortOrder = document.getElementById('transactionSortOrder').value;
            const searchValue = document.getElementById('transactionSearch').value.trim();
            const dateFrom = document.getElementById('transactionDateFrom').value;
            const dateTo = document.getElementById('transactionDateTo').value;

            let metaText = 'Type: ' + (typeFilter === 'ALL' ? 'All Transaction Types' : typeFilter);
            metaText += ' | Sort: ' + (sortOrder === 'ASC' ? 'Earlier Dates First' : 'Later Dates First');
            if (searchValue !== '') {
                metaText += ' | Search: ' + searchValue;
            }
            const dateText = (dateFrom || dateTo)
                ? 'Date Range: ' + (dateFrom || 'Start') + ' to ' + (dateTo || 'End')
                : 'Date Range: All Dates';

            document.getElementById('txPrintMetaType').innerText = metaText;
            const dateMetaEl = document.getElementById('txPrintMetaDate');
            if (dateMetaEl) {
                dateMetaEl.innerText = dateText;
            }
        }

        function filterTransactionGroups() {
            const searchValue = document.getElementById('transactionSearch').value.trim().toLowerCase();
            const typeFilter = document.getElementById('transactionTypeFilter').value;
            const sortOrder = document.getElementById('transactionSortOrder').value;
            const dateFrom = document.getElementById('transactionDateFrom').value;
            const dateTo = document.getElementById('transactionDateTo').value;
            const groupContainer = document.querySelector('#transactionsReportCard .space-y-5');
            const groups = Array.from(document.querySelectorAll('.tx-group'));

            const compareTransactions = (a, b) => {
                const aDate = a.dataset.date || '';
                const bDate = b.dataset.date || '';
                const aTime = new Date((aDate || '1970-01-01') + 'T00:00:00').getTime();
                const bTime = new Date((bDate || '1970-01-01') + 'T00:00:00').getTime();
                if (aTime !== bTime) {
                    return sortOrder === 'ASC' ? aTime - bTime : bTime - aTime;
                }

                const aInvoice = (a.dataset.invoice || '').toString();
                const bInvoice = (b.dataset.invoice || '').toString();
                return sortOrder === 'ASC'
                    ? aInvoice.localeCompare(bInvoice, undefined, { numeric: true, sensitivity: 'base' })
                    : bInvoice.localeCompare(aInvoice, undefined, { numeric: true, sensitivity: 'base' });
            };

            groups.forEach(group => {
                const rows = Array.from(group.querySelectorAll('.tx-row'));
                rows.sort(compareTransactions);
                rows.forEach(row => group.querySelector('tbody').appendChild(row));

                const groupRows = rows.filter(row => {
                    const matchesType = typeFilter === 'ALL' || group.dataset.txType === typeFilter;
                    const rowText = row.textContent.toLowerCase();
                    const rowDate = row.dataset.date || '';
                    const matchesSearch = searchValue === '' || rowText.includes(searchValue);
                    const matchesFrom = !dateFrom || !rowDate || rowDate >= dateFrom;
                    const matchesTo = !dateTo || !rowDate || rowDate <= dateTo;
                    return matchesType && matchesSearch && matchesFrom && matchesTo;
                });

                rows.forEach(row => {
                    const matchesType = typeFilter === 'ALL' || group.dataset.txType === typeFilter;
                    const rowText = row.textContent.toLowerCase();
                    const rowDate = row.dataset.date || '';
                    const matchesSearch = searchValue === '' || rowText.includes(searchValue);
                    const matchesFrom = !dateFrom || !rowDate || rowDate >= dateFrom;
                    const matchesTo = !dateTo || !rowDate || rowDate <= dateTo;
                    const rowVisible = matchesType && matchesSearch && matchesFrom && matchesTo;
                    row.style.display = rowVisible ? '' : 'none';
                });

                group.style.display = groupRows.length > 0 ? '' : 'none';
            });

            if (groupContainer) {
                groups.sort((a, b) => {
                    const aFirst = a.querySelector('.tx-row:not([style*="display: none"])') || a.querySelector('.tx-row');
                    const bFirst = b.querySelector('.tx-row:not([style*="display: none"])') || b.querySelector('.tx-row');
                    const aDate = aFirst?.dataset.date || '';
                    const bDate = bFirst?.dataset.date || '';
                    const aTime = new Date((aDate || '1970-01-01') + 'T00:00:00').getTime();
                    const bTime = new Date((bDate || '1970-01-01') + 'T00:00:00').getTime();
                    if (aTime !== bTime) {
                        return sortOrder === 'ASC' ? aTime - bTime : bTime - aTime;
                    }
                    const aInvoice = aFirst?.dataset.invoice || '';
                    const bInvoice = bFirst?.dataset.invoice || '';
                    const invoiceCompare = sortOrder === 'ASC'
                        ? aInvoice.localeCompare(bInvoice, undefined, { numeric: true, sensitivity: 'base' })
                        : bInvoice.localeCompare(aInvoice, undefined, { numeric: true, sensitivity: 'base' });
                    if (invoiceCompare !== 0) {
                        return invoiceCompare;
                    }
                    return a.dataset.txType.localeCompare(b.dataset.txType, undefined, { sensitivity: 'base' });
                });

                groups.forEach(group => groupContainer.appendChild(group));
            }

            updateTransactionPrintMeta();
        }

        function refreshTransactionFilters() {
            filterTransactionGroups();
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

        function showManualConflictModal() {
            const modal = document.getElementById('manualConflictModal');
            const box = document.getElementById('manualConflictBox');
            if (!modal || !box) return;

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            setTimeout(() => {
                box.classList.remove('translate-y-4', 'opacity-0');
                box.classList.add('translate-y-0', 'opacity-100');
            }, 10);
        }

        // --- CATCH PHP SESSION ALERTS ---
        <?php if (!empty($_SESSION['show_manual_conflict_confirm'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showManualConflictModal();
            });
            <?php unset($_SESSION['show_manual_conflict_confirm']); ?>
        <?php endif; ?>
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
