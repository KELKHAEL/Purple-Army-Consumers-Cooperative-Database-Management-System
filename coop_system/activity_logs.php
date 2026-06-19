<?php
session_start();
include 'db.php';

function normalizeActivityDate($value, $fallback)
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if ($date === false) {
        return $fallback;
    }

    return $date->format('Y-m-d');
}

function activityDateLabel($value)
{
    return date('F d, Y', strtotime($value));
}

function activityTimeLabel($value)
{
    return date('h:i A', strtotime($value));
}

function moduleBadgeClass($module)
{
    $module = strtoupper((string)$module);
    return match ($module) {
        'INVENTORY' => 'bg-blue-100 text-blue-700 border-blue-200',
        'SALES' => 'bg-green-100 text-green-700 border-green-200',
        'MEMBERS' => 'bg-purple-100 text-purple-700 border-purple-200',
        'SETTINGS' => 'bg-orange-100 text-orange-700 border-orange-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };
}

function statusBadgeClass($status)
{
    $status = strtoupper((string)$status);
    return match ($status) {
        'SUCCESS' => 'bg-green-100 text-green-700 border-green-200',
        'INFO' => 'bg-blue-100 text-blue-700 border-blue-200',
        'ERROR' => 'bg-red-100 text-red-700 border-red-200',
        'REVERTED' => 'bg-purple-100 text-purple-700 border-purple-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };
}

function parseActivityLogPayload(string $details): ?array
{
    $details = trim($details);
    if ($details === '') {
        return null;
    }

    if (strpos($details, 'JSON:') === 0) {
        $decoded = json_decode(substr($details, 5), true);
        return is_array($decoded) ? $decoded : null;
    }

    return null;
}

function activityLogExtractNumber(string $details, string $pattern): ?float
{
    if (!preg_match($pattern, $details, $matches)) {
        return null;
    }
    $number = preg_replace('/[^0-9.\-]/', '', (string)($matches[1] ?? ''));
    return $number === '' ? null : (float)$number;
}

function revertActivityLogEntry(mysqli $conn, array $log_row): array
{
    $module = strtoupper(trim((string)($log_row['module'] ?? '')));
    $action = strtoupper(trim((string)($log_row['action'] ?? '')));
    $entity_type = strtoupper(trim((string)($log_row['entity_type'] ?? '')));
    $entity_id = trim((string)($log_row['entity_id'] ?? ''));
    $entity_name = trim((string)($log_row['entity_name'] ?? ''));
    $details = (string)($log_row['details'] ?? '');
    $payload = parseActivityLogPayload($details);

    $conn->begin_transaction();

    try {
        $handled = false;

        if ($module === 'SETTINGS' && $entity_type === 'CONFIG') {
            $table = (string)($payload['table'] ?? '');
            $before = is_array($payload['before'] ?? null) ? $payload['before'] : [];
            $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
            $row_id = (int)$entity_id;
            $table_map = [
                'ADD OCCUPATION' => 'config_occupations',
                'DELETE OCCUPATION' => 'config_occupations',
                'EDIT OCCUPATION' => 'config_occupations',
                'ADD INCOME' => 'config_monthly_income',
                'DELETE INCOME' => 'config_monthly_income',
                'EDIT INCOME' => 'config_monthly_income',
                'ADD CIVIL STATUS' => 'config_civil_status',
                'DELETE CIVIL STATUS' => 'config_civil_status',
                'EDIT CIVIL STATUS' => 'config_civil_status',
                'ADD CATEGORY' => 'config_product_categories',
                'DELETE CATEGORY' => 'config_product_categories',
                'EDIT CATEGORY' => 'config_product_categories',
                'ADD UNIT' => 'config_unit_types',
                'DELETE UNIT' => 'config_unit_types',
                'EDIT UNIT' => 'config_unit_types',
                'ADD SHARE TYPE' => 'config_share_payment_types',
                'DELETE SHARE TYPE' => 'config_share_payment_types',
                'EDIT SHARE TYPE' => 'config_share_payment_types',
                'ADD TRANSACTION TYPE' => 'config_transaction_types',
                'DELETE TRANSACTION TYPE' => 'config_transaction_types',
                'EDIT TRANSACTION TYPE' => 'config_transaction_types',
            ];
            if ($table === '' && isset($table_map[$action])) {
                $table = $table_map[$action];
            }

            if ($action === 'UPDATE RECEIPT SIGNATORIES') {
                $treasurer = (string)($before['receipt_treasurer_name'] ?? '');
                $manager = (string)($before['receipt_manager_name'] ?? '');
                $pairs = [
                    'receipt_treasurer_name' => $treasurer,
                    'receipt_manager_name' => $manager,
                ];
                foreach ($pairs as $setting_key => $setting_value) {
                    $stmt = $conn->prepare("UPDATE config_inventory_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->bind_param('ss', $setting_value, $setting_key);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        $insert = $conn->prepare("INSERT INTO config_inventory_settings (setting_key, setting_value) VALUES (?, ?)");
                        $insert->bind_param('ss', $setting_key, $setting_value);
                        $insert->execute();
                        $insert->close();
                    }
                    $stmt->close();
                }
                $handled = true;
            } elseif ($action === 'UPDATE INVENTORY SETTINGS') {
                $allow = (string)($before['allow_negative_stock'] ?? '0');
                $stmt = $conn->prepare("UPDATE config_inventory_settings SET setting_value = ? WHERE setting_key = 'allow_negative_stock'");
                $stmt->bind_param('s', $allow);
                $stmt->execute();
                $stmt->close();
                $handled = true;
            } elseif (strpos($action, 'ADD ') === 0) {
                if ($table !== '' && $row_id > 0) {
                    if (in_array($table, ['config_share_payment_types', 'config_transaction_types'], true)) {
                        $stmt = $conn->prepare("UPDATE {$table} SET is_active = 0 WHERE id = ?");
                        $stmt->bind_param('i', $row_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
                        $stmt->bind_param('i', $row_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $handled = true;
                }
            } elseif (strpos($action, 'DELETE ') === 0) {
                if ($table !== '' && $row_id > 0) {
                    $restore_name = (string)($before['name'] ?? $entity_name);
                    if (in_array($table, ['config_share_payment_types', 'config_transaction_types'], true)) {
                        $stmt = $conn->prepare("UPDATE {$table} SET is_active = 1, name = ? WHERE id = ?");
                        $stmt->bind_param('si', $restore_name, $row_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM {$table} WHERE id = ?");
                        $stmt->bind_param('i', $row_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $exists = $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
                        $stmt->close();
                        if ($exists <= 0) {
                            $stmt = $conn->prepare("INSERT INTO {$table} (id, name) VALUES (?, ?)");
                            $stmt->bind_param('is', $row_id, $restore_name);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                    $handled = true;
                }
            } elseif (strpos($action, 'EDIT ') === 0) {
                if ($table !== '' && $row_id > 0 && isset($before['name'])) {
                    $old_name = (string)$before['name'];
                    $stmt = $conn->prepare("UPDATE {$table} SET name = ? WHERE id = ?");
                    $stmt->bind_param('si', $old_name, $row_id);
                    $stmt->execute();
                    $stmt->close();
                    $handled = true;
                }
            }
        }

        if (!$handled && $module === 'SALES' && $entity_type === 'TRANSACTION') {
            $transaction_id = (int)$entity_id;
            if ($transaction_id > 0) {
                $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_id = ? LIMIT 1");
                $stmt->bind_param('i', $transaction_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $txn = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if ($txn) {
                    if (in_array($action, ['SALE CHECKOUT', 'OUTSOURCE CHECKOUT'], true)) {
                        $payment_method = 'Cash';
                        if (preg_match('/Payment Method:\s*([^,]+)/i', $details, $m)) {
                            $payment_method = trim((string)$m[1]);
                        }
                        $receipt_no = (string)($txn['invoice_no'] ?? '');
                        $buyer_name = (string)($txn['member_name'] ?? '');

                        $stmt = $conn->prepare("SELECT record_id, product_id, quantity_out FROM inventory_outsourcing WHERE receipt_no = ? AND buyer_name = ? AND payment_method = ?");
                        $stmt->bind_param('sss', $receipt_no, $buyer_name, $payment_method);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $outs = [];
                        while ($res && ($row = $res->fetch_assoc())) {
                            $outs[] = $row;
                        }
                        $stmt->close();

                        foreach ($outs as $out) {
                            $product_id = (int)($out['product_id'] ?? 0);
                            $quantity_out = (int)($out['quantity_out'] ?? 0);
                            if ($product_id > 0 && $quantity_out > 0) {
                                $stmt = $conn->prepare("UPDATE inventory SET current_quantity = current_quantity + ? WHERE product_id = ?");
                                $stmt->bind_param('ii', $quantity_out, $product_id);
                                $stmt->execute();
                                $stmt->close();
                            }

                            $record_id = (int)($out['record_id'] ?? 0);
                            if ($record_id > 0) {
                                $stmt = $conn->prepare("DELETE FROM inventory_outsourcing WHERE record_id = ?");
                                $stmt->bind_param('i', $record_id);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }

                        $stmt = $conn->prepare("DELETE FROM transactions WHERE transaction_id = ?");
                        $stmt->bind_param('i', $transaction_id);
                        $stmt->execute();
                        $stmt->close();
                        $handled = true;
                    } elseif (in_array($action, ['ADD MANUAL TRANSACTION', 'ADD MEMBER SHARE'], true)) {
                        $stmt = $conn->prepare("DELETE FROM transactions WHERE transaction_id = ?");
                        $stmt->bind_param('i', $transaction_id);
                        $stmt->execute();
                        $stmt->close();
                        $handled = true;
                    } elseif ($action === 'FINALIZE OUTSOURCE PAYMENT') {
                        $amount = (float)($txn['amount'] ?? 0);
                        $stmt = $conn->prepare("UPDATE transactions SET invoice_no = 'OUTSOURCED', payment_status = 'PENDING', downpayment = 0, remaining_balance = ? WHERE transaction_id = ?");
                        $stmt->bind_param('di', $amount, $transaction_id);
                        $stmt->execute();
                        $stmt->close();
                        $handled = true;
                    } elseif ($action === 'RECORD PAY LATER DOWNPAYMENT') {
                        $apply = activityLogExtractNumber($details, '/Payment received:\s*([0-9.,-]+)/i') ?? 0.0;
                        $stmt = $conn->prepare("UPDATE transactions SET downpayment = GREATEST(downpayment - ?, 0), remaining_balance = remaining_balance + ?, payment_status = CASE WHEN (downpayment - ?) <= 0 THEN 'PENDING' ELSE 'DOWNPAYMENT' END WHERE transaction_id = ?");
                        $stmt->bind_param('dddi', $apply, $apply, $apply, $transaction_id);
                        $stmt->execute();
                        $stmt->close();
                        $handled = true;
                    } elseif ($action === 'COMPLETE PAY LATER PAYMENT') {
                        $apply = activityLogExtractNumber($details, '/Payment received:\s*([0-9.,-]+)/i') ?? 0.0;
                        $new_remaining = $apply;
                        $current_downpayment = (float)($txn['downpayment'] ?? 0);
                        $new_downpayment = max($current_downpayment - $apply, 0);
                        $new_status = $new_downpayment > 0 ? 'DOWNPAYMENT' : 'PENDING';
                        $stmt = $conn->prepare("UPDATE transactions SET invoice_no = '', downpayment = ?, remaining_balance = ?, payment_status = ? WHERE transaction_id = ?");
                        $stmt->bind_param('ddsi', $new_downpayment, $new_remaining, $new_status, $transaction_id);
                        $stmt->execute();
                        $stmt->close();
                        $handled = true;
                    }
                }
            }
        }

        if (!$handled && $module === 'INVENTORY' && $entity_type === 'PRODUCT') {
            $operation = strtoupper((string)($payload['operation'] ?? ''));
            $before = is_array($payload['before'] ?? null) ? $payload['before'] : [];
            $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
            $product_id = (int)$entity_id;

            if ($operation === 'ADD') {
                $stmt = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
                $stmt->bind_param('i', $product_id);
                $stmt->execute();
                $stmt->close();
                $handled = true;
            } elseif ($operation === 'DELETE') {
                if (!empty($before)) {
                    $existing = $conn->query("SELECT COUNT(*) AS c FROM inventory WHERE product_id = {$product_id}");
                    $exists = $existing ? (int)($existing->fetch_assoc()['c'] ?? 0) : 0;
                    if ($exists <= 0) {
                        $stmt = $conn->prepare("INSERT INTO inventory (product_id, product_name, product_type, quantity_type, current_quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
                        $qty = (int)($before['current_quantity'] ?? 0);
                        $price = (float)($before['price'] ?? 0);
                        $stmt->bind_param('isssid', $product_id, $before['product_name'], $before['product_type'], $before['quantity_type'], $qty, $price);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $handled = true;
                }
            } elseif ($operation === 'UPDATE') {
                if (!empty($before)) {
                    $stmt = $conn->prepare("UPDATE inventory SET product_name = ?, product_type = ?, quantity_type = ?, current_quantity = ?, price = ? WHERE product_id = ?");
                    $qty = (int)($before['current_quantity'] ?? 0);
                    $price = (float)($before['price'] ?? 0);
                    $stmt->bind_param('sssddi', $before['product_name'], $before['product_type'], $before['quantity_type'], $qty, $price, $product_id);
                    $stmt->execute();
                    $stmt->close();
                    $handled = true;
                }
            } elseif ($operation === 'ADJUST') {
                if (array_key_exists('before', $payload) && is_array($before) && isset($before['current_quantity'])) {
                    $qty = (int)$before['current_quantity'];
                    $stmt = $conn->prepare("UPDATE inventory SET current_quantity = ? WHERE product_id = ?");
                    $stmt->bind_param('ii', $qty, $product_id);
                    $stmt->execute();
                    $stmt->close();
                    $handled = true;
                }
            }
        }

        if (!$handled && $module === 'INVENTORY' && $action === 'RECONCILE OUTSOURCE' && $entity_type === 'OUTSOURCING RECORD') {
            $record_id = (int)$entity_id;
            $qty_returned = (int)(activityLogExtractNumber($details, '/Returned:\s*([0-9.,-]+)/i') ?? 0);
            $prod_id = (int)(activityLogExtractNumber($details, '/Product ID\s*([0-9.,-]+)/i') ?? 0);
            if ($prod_id > 0 && $qty_returned > 0) {
                $stmt = $conn->prepare("UPDATE inventory SET current_quantity = current_quantity - ? WHERE product_id = ?");
                $stmt->bind_param('ii', $qty_returned, $prod_id);
                $stmt->execute();
                $stmt->close();
            }
            if ($record_id > 0) {
                $stmt = $conn->prepare("UPDATE inventory_outsourcing SET quantity_returned = 0, status = 'PENDING' WHERE record_id = ?");
                $stmt->bind_param('i', $record_id);
                $stmt->execute();
                $stmt->close();
            }
            $handled = true;
        }

        if (!$handled) {
            throw new Exception('This log entry cannot be reverted automatically.');
        }

        $conn->commit();
        return [
            'status' => 'success',
            'title' => 'Revert Completed',
            'message' => 'The selected action was reverted successfully and a new revert audit entry can be recorded.',
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return [
            'status' => 'error',
            'title' => 'Revert Failed',
            'message' => $e->getMessage(),
        ];
    }
}

$flash_message = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revert_log_id'])) {
    $revert_log_id = (int)($_POST['revert_log_id'] ?? 0);
    $redirect_query = [];
    $redirect_date = trim((string)($_POST['redirect_date'] ?? ''));
    $redirect_module = trim((string)($_POST['redirect_module'] ?? 'ALL'));
    $redirect_q = trim((string)($_POST['redirect_q'] ?? ''));
    if ($redirect_date !== '') {
        $redirect_query['date'] = $redirect_date;
    }
    if ($redirect_module !== '') {
        $redirect_query['module'] = $redirect_module;
    }
    if ($redirect_q !== '') {
        $redirect_query['q'] = $redirect_q;
    }

    if ($revert_log_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM activity_logs WHERE log_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $revert_log_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $log_row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($log_row) {
                if (strtoupper((string)($log_row['status'] ?? '')) === 'REVERTED') {
                    $flash_message = 'This log entry is already marked as reverted.';
                    $flash_type = 'info';
                } else {
                    $revert_result = revertActivityLogEntry($conn, $log_row);
                    if (($revert_result['status'] ?? '') === 'success') {
                        $updated_details = trim((string)($log_row['details'] ?? ''));
                        if ($updated_details !== '') {
                            $updated_details .= "\n";
                        }
                        $updated_details .= 'Reverted on ' . date('F d, Y h:i A') . ' from activity log #' . $revert_log_id . '.';

                        $upd = $conn->prepare("UPDATE activity_logs SET status = 'REVERTED', details = ? WHERE log_id = ?");
                        if ($upd) {
                            $upd->bind_param('si', $updated_details, $revert_log_id);
                            $upd->execute();
                            $upd->close();
                        }

                        logActivity(
                            $conn,
                            (string)($log_row['module'] ?? 'SYSTEM'),
                            'REVERT ACTION',
                            (string)($log_row['entity_type'] ?? 'LOG'),
                            $log_row['entity_id'] ?? null,
                            (string)($log_row['entity_name'] ?? ''),
                            'Reverted activity log #' . $revert_log_id . ' (' . (string)($log_row['action'] ?? 'ACTION') . ').',
                            'SUCCESS'
                        );

                        $flash_message = (string)($revert_result['message'] ?? 'The selected log entry was reverted successfully.');
                        $flash_type = 'success';
                    } else {
                        $flash_message = (string)($revert_result['message'] ?? 'Unable to revert the selected log entry.');
                        $flash_type = 'error';
                    }
                }
            } else {
                $flash_message = 'Unable to find the selected activity log.';
                $flash_type = 'error';
            }
        } else {
            $flash_message = 'Unable to prepare the revert request.';
            $flash_type = 'error';
        }
    } else {
        $flash_message = 'Invalid activity log selected for revert.';
        $flash_type = 'error';
    }

    $redirect_url = 'activity_logs.php';
    if (!empty($redirect_query)) {
        $redirect_url .= '?' . http_build_query($redirect_query);
    }
    $_SESSION['alert_title'] = $flash_type === 'success' ? 'Revert Completed' : ($flash_type === 'info' ? 'Revert Notice' : 'Revert Failed');
    $_SESSION['alert_message'] = $flash_message;
    $_SESSION['alert_type'] = $flash_type;
    header('Location: ' . $redirect_url);
    exit();
}

$today = date('Y-m-d');
$selected_date = normalizeActivityDate($_GET['date'] ?? $today, $today);
$module_filter = strtoupper(trim((string)($_GET['module'] ?? 'ALL')));
$search = trim((string)($_GET['q'] ?? ''));

$filters = [];
$filter_values = [];
$filter_types = '';

if ($selected_date !== '') {
    $filters[] = 'DATE(created_at) = ?';
    $filter_values[] = $selected_date;
    $filter_types .= 's';
}

if ($module_filter !== '' && $module_filter !== 'ALL') {
    $filters[] = 'module = ?';
    $filter_values[] = $module_filter;
    $filter_types .= 's';
}

if ($search !== '') {
    $filters[] = '(module LIKE ? OR action LIKE ? OR entity_name LIKE ? OR details LIKE ? OR actor_name LIKE ?)';
    $like = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) {
        $filter_values[] = $like;
        $filter_types .= 's';
    }
}

$where_sql = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';

$logs = [];
$stmt = $conn->prepare("SELECT * FROM activity_logs {$where_sql} ORDER BY created_at DESC, log_id DESC");
if ($stmt) {
    if ($filter_values) {
        $stmt->bind_param($filter_types, ...$filter_values);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $logs[] = $row;
    }
    $stmt->close();
}

$summary_counts = [
    'TOTAL' => count($logs),
    'SUCCESS' => 0,
    'INFO' => 0,
    'ERROR' => 0,
    'REVERTED' => 0,
];
$module_counts = [
    'INVENTORY' => 0,
    'SALES' => 0,
    'MEMBERS' => 0,
    'SETTINGS' => 0,
];
foreach ($logs as $log) {
    $status_key = strtoupper((string)($log['status'] ?? ''));
    if (isset($summary_counts[$status_key])) {
        $summary_counts[$status_key]++;
    }

    $module_key = strtoupper((string)($log['module'] ?? ''));
    if (isset($module_counts[$module_key])) {
        $module_counts[$module_key]++;
    }
}

$prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
if ($next_date > $today) {
    $next_date = $today;
}

$grouped_logs = [];
foreach ($logs as $log) {
    $day = date('Y-m-d', strtotime($log['created_at']));
    if (!isset($grouped_logs[$day])) {
        $grouped_logs[$day] = [];
    }
    $grouped_logs[$day][] = $log;
}

$group_keys = array_keys($grouped_logs);
rsort($group_keys);

$page_alert_title = $_SESSION['alert_title'] ?? '';
$page_alert_message = $_SESSION['alert_message'] ?? '';
$page_alert_type = strtolower((string)($_SESSION['alert_type'] ?? ''));
unset($_SESSION['alert_title'], $_SESSION['alert_message'], $_SESSION['alert_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Coop DBMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#6a1b9a', primaryDark: '#570591' }
                }
            }
        }
    </script>
    <style>
        details summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-hidden">
    <?php include 'cover_page.php'; ?>

    <div class="flex h-screen w-full">
        <div id="mobile-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden transition-opacity print:hidden" onclick="toggleSidebar()"></div>
        <aside id="sidebar" class="bg-white w-72 border-r border-gray-200 flex flex-col transition-transform transform -translate-x-full md:translate-x-0 fixed md:relative z-50 h-full shadow-lg md:shadow-none print:hidden">
            <div class="p-6 flex items-center justify-center border-b border-gray-100 relative">
                <a href="index.php" class="block">
                    <img src="img/purplearmy_logo-removebg.png" alt="Coop Logo" class="w-40 md:w-52 h-auto object-contain py-2 drop-shadow-sm transition-transform hover:scale-105">
                </a>
                <button class="absolute top-4 right-4 md:hidden text-gray-400 hover:text-gray-800" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4 flex flex-col gap-1">
                <a href="index.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-users w-6"></i> MEMBERSHIP DIRECTORY</a>
                <a href="member_shares.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-hand-holding-usd w-6"></i> MEMBER SHARES</a>
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-receipt w-6"></i> SALES & PURCHASE LOGS</a>
                <a href="inventory.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-boxes w-6"></i> INVENTORY</a>
                <a href="pos.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-shopping-cart w-6"></i> SELL / OUTSOURCE</a>
                <a href="outsourcing_report.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-chart-line w-6"></i> OUTSOURCING LOGS</a>
                <a href="database_management.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-database w-6"></i> DATABASE SETTINGS</a>
                <a href="activity_logs.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark"><i class="fas fa-clock-rotate-left w-6"></i> ACTIVITY LOGS</a>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10 print:hidden">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Activity Logs</h1>
                        <p class="text-xs text-gray-500 mt-1">Central audit trail for inventory, sales, members, and settings.</p>
                    </div>
                </div>
                <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300 whitespace-nowrap"><i class="fas fa-print mr-2"></i>PRINT</button>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Logs Shown</div>
                        <div class="mt-2 text-2xl font-bold text-gray-900"><?= number_format($summary_counts['TOTAL']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Success</div>
                        <div class="mt-2 text-2xl font-bold text-green-700"><?= number_format($summary_counts['SUCCESS']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Info</div>
                        <div class="mt-2 text-2xl font-bold text-blue-700"><?= number_format($summary_counts['INFO']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Error</div>
                        <div class="mt-2 text-2xl font-bold text-red-700"><?= number_format($summary_counts['ERROR']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Selected Date</div>
                        <div class="mt-2 text-sm font-bold text-gray-900"><?= htmlspecialchars(activityDateLabel($selected_date)) ?></div>
                    </div>
                </div>

                <form method="GET" action="activity_logs.php" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm mb-6 print:hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                            <div class="flex gap-2">
                                <a href="?date=<?= htmlspecialchars($prev_date) ?>&module=<?= urlencode($module_filter) ?>&q=<?= urlencode($search) ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100"><i class="fas fa-chevron-left"></i></a>
                                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" max="<?= htmlspecialchars($today) ?>" class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <a href="?date=<?= htmlspecialchars($next_date) ?>&module=<?= urlencode($module_filter) ?>&q=<?= urlencode($search) ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100"><i class="fas fa-chevron-right"></i></a>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Module</label>
                            <select name="module" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <option value="ALL" <?= $module_filter === 'ALL' ? 'selected' : '' ?>>All Modules</option>
                                <option value="INVENTORY" <?= $module_filter === 'INVENTORY' ? 'selected' : '' ?>>Inventory</option>
                                <option value="SALES" <?= $module_filter === 'SALES' ? 'selected' : '' ?>>Sales</option>
                                <option value="MEMBERS" <?= $module_filter === 'MEMBERS' ? 'selected' : '' ?>>Members</option>
                                <option value="SETTINGS" <?= $module_filter === 'SETTINGS' ? 'selected' : '' ?>>Settings</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Search</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search logs..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm"><i class="fas fa-filter mr-2"></i>FILTER</button>
                            <a href="activity_logs.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300"><i class="fas fa-rotate-right mr-2"></i>RESET</a>
                        </div>
                    </div>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Inventory</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['INVENTORY']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Sales</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['SALES']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Members</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['MEMBERS']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Settings</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['SETTINGS']) ?></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h4 class="font-bold text-gray-800"><i class="fas fa-stream text-primary mr-2"></i>Activity Feed</h4>
                        <span class="text-xs font-bold text-gray-500 uppercase">Auto refresh every 30 seconds</span>
                    </div>

                    <div class="max-h-[calc(100vh-24rem)] overflow-y-auto">
                        <?php if (!empty($group_keys)): ?>
                            <div class="p-4 space-y-3">
                                <?php foreach ($group_keys as $index => $day): ?>
                                    <details class="group rounded-xl border border-gray-200 overflow-hidden bg-gray-50/60" <?= $index === 0 ? 'open' : '' ?>>
                                        <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary text-white text-xs font-bold shrink-0"><i class="fas fa-chevron-down group-open:rotate-180 transition-transform"></i></span>
                                                <div>
                                                    <div class="font-bold text-gray-800"><?= htmlspecialchars(activityDateLabel($day)) ?></div>
                                                    <div class="text-xs text-gray-500"><?= number_format(count($grouped_logs[$day])) ?> log item(s)</div>
                                                </div>
                                            </div>
                                            <div class="text-xs font-bold text-gray-500 uppercase">Day Snapshot</div>
                                        </summary>
                                        <div class="border-t border-gray-200 bg-white">
                                            <?php foreach ($grouped_logs[$day] as $log): ?>
                                                <div class="px-4 py-3 border-b border-gray-100 last:border-b-0">
                                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase border <?= htmlspecialchars(moduleBadgeClass($log['module'] ?? '')) ?>"><?= htmlspecialchars($log['module'] ?? 'SYSTEM') ?></span>
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase border <?= htmlspecialchars(statusBadgeClass($log['status'] ?? '')) ?>"><?= htmlspecialchars($log['status'] ?? 'SUCCESS') ?></span>
                                                                <span class="text-xs text-gray-500 font-mono"><?= htmlspecialchars(activityTimeLabel($log['created_at'])) ?></span>
                                                            </div>
                                                            <div class="font-bold text-gray-800"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                                                            <div class="text-sm text-gray-600 mt-1">
                                                                <?= htmlspecialchars($log['entity_type'] ?? 'Event') ?>
                                                                <?php if (!empty($log['entity_name'])): ?>
                                                                    <span class="font-semibold text-gray-800">- <?= htmlspecialchars($log['entity_name']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($log['details'])): ?>
                                                                <div class="text-sm text-gray-500 mt-2 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($log['details']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 lg:text-right shrink-0">
                                                            <div class="font-bold uppercase text-gray-400">Actor</div>
                                                            <div class="font-semibold text-gray-700"><?= htmlspecialchars($log['actor_name'] ?? 'SYSTEM') ?></div>
                                                            <div class="text-[11px] uppercase tracking-wide"><?= htmlspecialchars($log['actor_role'] ?? 'SYSTEM') ?></div>
                                                            <?php if (strtoupper((string)($log['status'] ?? '')) !== 'REVERTED'): ?>
                                                                <form method="POST" action="activity_logs.php" class="mt-3">
                                                                    <input type="hidden" name="revert_log_id" value="<?= (int)($log['log_id'] ?? 0) ?>">
                                                                    <input type="hidden" name="redirect_date" value="<?= htmlspecialchars($selected_date) ?>">
                                                                    <input type="hidden" name="redirect_module" value="<?= htmlspecialchars($module_filter) ?>">
                                                                    <input type="hidden" name="redirect_q" value="<?= htmlspecialchars($search) ?>">
                                                                    <button type="submit" onclick="return openPageConfirmModal('Revert this activity log entry? This will mark the log as reverted and create a new revert audit entry.', this.form);" class="inline-flex items-center gap-2 rounded-md border border-purple-200 bg-purple-100 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-purple-800 transition-colors hover:bg-purple-600 hover:text-white">
                                                                        <i class="fas fa-rotate-left"></i> Revert
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <div class="mt-3 inline-flex items-center gap-2 rounded-md border border-purple-200 bg-purple-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-purple-700">
                                                                    <i class="fas fa-rotate-left"></i> Reverted
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="px-6 py-16 text-center text-gray-500">
                                <i class="fas fa-clock-rotate-left text-3xl mb-3 text-gray-300"></i>
                                <div class="font-bold text-gray-700">No activity logs found.</div>
                                <div class="text-sm mt-1">Try changing the date, module, or search filters.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="pageAlertModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="pageAlertBox">
            <div id="pageAlertHeader" class="px-6 py-4 flex items-center gap-3 border-b">
                <i id="pageAlertIcon" class="fas fa-info-circle text-2xl"></i>
                <h3 id="pageAlertTitle" class="text-lg font-bold tracking-tight">Notice</h3>
            </div>
            <div class="p-6 text-gray-600 text-sm leading-relaxed whitespace-pre-line" id="pageAlertMessage"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button id="pageAlertBtn" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">OK</button>
            </div>
        </div>
    </div>

    <div id="pageConfirmModal" class="fixed inset-0 z-[1001] hidden items-center justify-center p-4 print:hidden">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="pageConfirmBox">
            <div id="pageConfirmHeader" class="px-6 py-4 flex items-center gap-3 border-b bg-amber-50 border-amber-100">
                <i id="pageConfirmIcon" class="fas fa-triangle-exclamation text-2xl text-amber-500"></i>
                <h3 id="pageConfirmTitle" class="text-lg font-bold tracking-tight text-amber-800">Confirm Revert</h3>
            </div>
            <div class="p-6 text-gray-700 text-sm leading-relaxed" id="pageConfirmMessage"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button type="button" id="pageConfirmCancelBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg transition-colors shadow-sm">Cancel</button>
                <button type="button" id="pageConfirmProceedBtn" class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-4 rounded-lg transition-colors shadow-md">Proceed</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        const filterDate = document.querySelector('input[name="date"]');
        if (filterDate) {
            filterDate.addEventListener('change', function () {
                this.form.submit();
            });
        }

        let liveRefresh = null;
        if (!document.hidden) {
            liveRefresh = setInterval(() => {
                const params = new URLSearchParams(window.location.search);
                params.set('date', document.querySelector('input[name="date"]').value);
                params.set('module', document.querySelector('select[name="module"]').value);
                params.set('q', document.querySelector('input[name="q"]').value);
                window.location.search = params.toString();
            }, 30000);
        }

        const pageAlertTitle = <?= json_encode($page_alert_title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const pageAlertMessage = <?= json_encode($page_alert_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const pageAlertType = <?= json_encode($page_alert_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function openPageAlertModal() {
            const modal = document.getElementById('pageAlertModal');
            const box = document.getElementById('pageAlertBox');
            const icon = document.getElementById('pageAlertIcon');
            const header = document.getElementById('pageAlertHeader');
            const title = document.getElementById('pageAlertTitle');
            const message = document.getElementById('pageAlertMessage');

            if (!modal || !box || !icon || !header || !title || !message) {
                return;
            }

            title.textContent = pageAlertTitle || 'Notice';
            message.textContent = pageAlertMessage || '';

            if (pageAlertType === 'error') {
                icon.className = 'fas fa-exclamation-circle text-2xl text-red-500';
                header.className = 'px-6 py-4 flex items-center gap-3 border-b bg-red-50 border-red-100';
            } else if (pageAlertType === 'info') {
                icon.className = 'fas fa-info-circle text-2xl text-blue-500';
                header.className = 'px-6 py-4 flex items-center gap-3 border-b bg-blue-50 border-blue-100';
            } else {
                icon.className = 'fas fa-check-circle text-2xl text-green-500';
                header.className = 'px-6 py-4 flex items-center gap-3 border-b bg-green-50 border-green-100';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            setTimeout(() => {
                box.classList.remove('translate-y-4', 'opacity-0');
                box.classList.add('translate-y-0', 'opacity-100');
            }, 10);
        }

        function closePageAlertModal() {
            const modal = document.getElementById('pageAlertModal');
            const box = document.getElementById('pageAlertBox');
            if (!modal || !box) {
                return;
            }
            box.classList.remove('translate-y-0', 'opacity-100');
            box.classList.add('translate-y-4', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 250);
        }

        document.getElementById('pageAlertBtn')?.addEventListener('click', closePageAlertModal);

        if (pageAlertMessage) {
            document.addEventListener('DOMContentLoaded', openPageAlertModal);
        }

        let pendingPageConfirmForm = null;

        function openPageConfirmModal(message, formEl = null) {
            const modal = document.getElementById('pageConfirmModal');
            const box = document.getElementById('pageConfirmBox');
            const messageEl = document.getElementById('pageConfirmMessage');
            if (!modal || !box || !messageEl) {
                return true;
            }

            pendingPageConfirmForm = formEl || null;
            messageEl.textContent = message || '';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                box.classList.remove('translate-y-4', 'opacity-0');
                box.classList.add('translate-y-0', 'opacity-100');
            }, 10);
            return false;
        }

        function closePageConfirmModal() {
            const modal = document.getElementById('pageConfirmModal');
            const box = document.getElementById('pageConfirmBox');
            if (!modal || !box) {
                return;
            }
            box.classList.remove('translate-y-0', 'opacity-100');
            box.classList.add('translate-y-4', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 250);
        }

        document.getElementById('pageConfirmCancelBtn')?.addEventListener('click', function () {
            pendingPageConfirmForm = null;
            closePageConfirmModal();
        });

        document.getElementById('pageConfirmProceedBtn')?.addEventListener('click', function () {
            const form = pendingPageConfirmForm;
            pendingPageConfirmForm = null;
            closePageConfirmModal();
            if (form) {
                setTimeout(() => form.submit(), 50);
            }
        });
    </script>
</body>
</html>
