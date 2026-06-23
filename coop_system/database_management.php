<?php 
session_start();
include 'db.php'; 

function dmAuditDetails(array $payload): string {
    return 'JSON:' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// --- HANDLE FORM SUBMISSIONS (ADD / DELETE / UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $msg = "";
        
        try {
            // Handle Additions
            if ($action === 'add_occ' && !empty($_POST['new_occupation'])) {
                $stmt = $conn->prepare("INSERT INTO config_occupations (name) VALUES (?)");
                $new_name = trim($_POST['new_occupation']);
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Occupation successfully added.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD OCCUPATION', 'CONFIG', $conn->insert_id, $new_name, 'Added new occupation setting.');
                }
            } elseif ($action === 'add_inc' && !empty($_POST['new_income'])) {
                $stmt = $conn->prepare("INSERT INTO config_monthly_income (name) VALUES (?)");
                $new_name = trim($_POST['new_income']);
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Income bracket successfully added.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD INCOME', 'CONFIG', $conn->insert_id, $new_name, 'Added new monthly income setting.');
                }
            } elseif ($action === 'add_civ' && !empty($_POST['new_civil'])) {
                $stmt = $conn->prepare("INSERT INTO config_civil_status (name) VALUES (?)");
                $new_name = trim($_POST['new_civil']);
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Civil status successfully added.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD CIVIL STATUS', 'CONFIG', $conn->insert_id, $new_name, 'Added new civil status setting.');
                }
            } elseif ($action === 'add_cat' && !empty($_POST['new_cat'])) {
                $stmt = $conn->prepare("INSERT INTO config_product_categories (name) VALUES (?)");
                $new_name = trim($_POST['new_cat']);
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Product category successfully added.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD CATEGORY', 'CONFIG', $conn->insert_id, $new_name, 'Added new product category setting.');
                }
            } elseif ($action === 'add_unit' && !empty($_POST['new_unit'])) {
                $stmt = $conn->prepare("INSERT INTO config_unit_types (name) VALUES (?)");
                $new_name = trim($_POST['new_unit']);
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Unit type successfully added.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD UNIT', 'CONFIG', $conn->insert_id, $new_name, 'Added new unit type setting.');
                }
            }
            
            // Handle Deletions
            elseif ($action === 'del_occ' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_occupations WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("DELETE FROM config_occupations WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Occupation removed.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE OCCUPATION', 'CONFIG', $id, $row['name'] ?? '', 'Removed occupation setting.');
                }
            } elseif ($action === 'del_inc' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_monthly_income WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("DELETE FROM config_monthly_income WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Income bracket removed.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE INCOME', 'CONFIG', $id, $row['name'] ?? '', 'Removed monthly income setting.');
                }
            } elseif ($action === 'del_civ' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_civil_status WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("DELETE FROM config_civil_status WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Civil status removed.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE CIVIL STATUS', 'CONFIG', $id, $row['name'] ?? '', 'Removed civil status setting.');
                }
            } elseif ($action === 'del_cat' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_product_categories WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("DELETE FROM config_product_categories WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Product category removed.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE CATEGORY', 'CONFIG', $id, $row['name'] ?? '', 'Removed product category setting.');
                }
            } elseif ($action === 'del_unit' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_unit_types WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("DELETE FROM config_unit_types WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Unit type removed.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE UNIT', 'CONFIG', $id, $row['name'] ?? '', 'Removed unit type setting.');
                }
            }

            // Handle Edits (Updates)
            elseif ($action === 'edit_occ' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_occupations WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_occupations SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Occupation updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT OCCUPATION', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_occupations', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'edit_inc' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_monthly_income WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_monthly_income SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Income bracket updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT INCOME', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_monthly_income', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'edit_civ' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_civil_status WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_civil_status SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Civil status updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT CIVIL STATUS', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_civil_status', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'edit_cat' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_product_categories WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_product_categories SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Product category updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT CATEGORY', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_product_categories', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'edit_unit' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_unit_types WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_unit_types SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Unit type updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT UNIT', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_unit_types', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            }
            
            // Handle Inventory Settings Toggle
            elseif ($action === 'update_inv_settings') {
                $before_allow = $conn->query("SELECT setting_value FROM config_inventory_settings WHERE setting_key = 'allow_negative_stock'")->fetch_assoc();
                $before_report = $conn->query("SELECT setting_value FROM config_inventory_settings WHERE setting_key = 'inventory_report_manager_name'")->fetch_assoc();
                $allow_neg = isset($_POST['allow_negative']) ? '1' : '0';
                $inventory_report_manager_name = trim((string)($_POST['inventory_report_manager_name'] ?? ''));
                $stmt = $conn->prepare("UPDATE config_inventory_settings SET setting_value = ? WHERE setting_key = 'allow_negative_stock'");
                $stmt->bind_param("s", $allow_neg);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO config_inventory_settings (setting_key, setting_value) VALUES ('inventory_report_manager_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->bind_param("s", $inventory_report_manager_name);
                $stmt->execute();
                $stmt->close();
                $msg = "Inventory permissions updated successfully.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'UPDATE INVENTORY SETTINGS', 'CONFIG', 'allow_negative_stock', 'allow_negative_stock', dmAuditDetails(['table' => 'config_inventory_settings', 'before' => ['allow_negative_stock' => (string)($before_allow['setting_value'] ?? '0'), 'inventory_report_manager_name' => (string)($before_report['setting_value'] ?? 'VRIAN ANDREW B. PORTUGUESE')], 'after' => ['allow_negative_stock' => $allow_neg, 'inventory_report_manager_name' => $inventory_report_manager_name]]));
                }
            } elseif ($action === 'update_receipt_signatories') {
                $before_rows = [];
                $receipt_setting_res = $conn->query("SELECT setting_key, setting_value FROM config_inventory_settings WHERE setting_key IN ('receipt_treasurer_name', 'receipt_manager_name')");
                if ($receipt_setting_res && $receipt_setting_res->num_rows > 0) {
                    while ($row = $receipt_setting_res->fetch_assoc()) {
                        $before_rows[$row['setting_key']] = (string)($row['setting_value'] ?? '');
                    }
                }
                $receipt_treasurer_name = trim((string)($_POST['receipt_treasurer_name'] ?? ''));
                $receipt_manager_name = trim((string)($_POST['receipt_manager_name'] ?? ''));

                $receipt_settings = [
                    'receipt_treasurer_name' => $receipt_treasurer_name,
                    'receipt_manager_name' => $receipt_manager_name,
                ];

                foreach ($receipt_settings as $setting_key => $setting_value) {
                    $stmt = $conn->prepare("UPDATE config_inventory_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->bind_param("ss", $setting_value, $setting_key);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        $insert = $conn->prepare("INSERT INTO config_inventory_settings (setting_key, setting_value) VALUES (?, ?)");
                        $insert->bind_param("ss", $setting_key, $setting_value);
                        $insert->execute();
                        $insert->close();
                    }
                    $stmt->close();
                }

                $msg = "Receipt signatories updated successfully.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'UPDATE RECEIPT SIGNATORIES', 'CONFIG', null, 'Receipt Signatories', dmAuditDetails(['table' => 'config_inventory_settings', 'before' => $before_rows, 'after' => $receipt_settings]));
                }
            } elseif ($action === 'add_share_type' && !empty($_POST['new_share_type'])) {
                $new_name = trim($_POST['new_share_type']);
                $stmt = $conn->prepare("INSERT INTO config_share_payment_types (name, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Share payment type saved successfully.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD SHARE TYPE', 'CONFIG', $conn->insert_id, $new_name, 'Added or reactivated a share payment type.');
                }
            } elseif ($action === 'add_share_method' && !empty($_POST['new_share_method'])) {
                $new_name = trim($_POST['new_share_method']);
                $stmt = $conn->prepare("INSERT INTO config_share_payment_methods (name, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Member payment method saved successfully.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD PAYMENT METHOD', 'CONFIG', $conn->insert_id, $new_name, 'Added or reactivated a member payment method.');
                }
            } elseif ($action === 'edit_share_method' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_share_payment_methods WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_share_payment_methods SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Member payment method updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT PAYMENT METHOD', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_share_payment_methods', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'del_share_method' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_share_payment_methods WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("UPDATE config_share_payment_methods SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Member payment method deleted.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE PAYMENT METHOD', 'CONFIG', $id, $row['name'] ?? '', 'Deactivated a member payment method.');
                }
            } elseif ($action === 'edit_share_type' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_share_payment_types WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_share_payment_types SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Share payment type updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT SHARE TYPE', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_share_payment_types', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'del_share_type' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_share_payment_types WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("UPDATE config_share_payment_types SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Share payment type deleted.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE SHARE TYPE', 'CONFIG', $id, $row['name'] ?? '', 'Deactivated a share payment type.');
                }
            } elseif ($action === 'add_trans_type' && !empty($_POST['new_trans_type'])) {
                $new_name = trim($_POST['new_trans_type']);
                $stmt = $conn->prepare("INSERT INTO config_transaction_types (name, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
                $stmt->bind_param("s", $new_name);
                $stmt->execute();
                $msg = "Transaction type saved successfully.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'ADD TRANSACTION TYPE', 'CONFIG', $conn->insert_id, $new_name, 'Added or reactivated a transaction type.');
                }
            } elseif ($action === 'edit_trans_type' && isset($_POST['id']) && !empty($_POST['edit_name'])) {
                $id = (int)$_POST['id'];
                $before = $conn->query("SELECT name FROM config_transaction_types WHERE id = $id")->fetch_assoc();
                $new_name = trim($_POST['edit_name']);
                $stmt = $conn->prepare("UPDATE config_transaction_types SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $id);
                $stmt->execute();
                $msg = "Transaction type updated.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'EDIT TRANSACTION TYPE', 'CONFIG', $id, $new_name, dmAuditDetails(['table' => 'config_transaction_types', 'before' => ['name' => $before['name'] ?? ''], 'after' => ['name' => $new_name]]));
                }
            } elseif ($action === 'del_trans_type' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $row = $conn->query("SELECT name FROM config_transaction_types WHERE id = $id")->fetch_assoc();
                $stmt = $conn->prepare("UPDATE config_transaction_types SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Transaction type deleted.";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'SETTINGS', 'DELETE TRANSACTION TYPE', 'CONFIG', $id, $row['name'] ?? '', 'Deactivated a transaction type.');
                }
            }

            if ($msg !== "") {
                $_SESSION['alert_title'] = "Success";
                $_SESSION['alert_message'] = $msg;
                $_SESSION['alert_type'] = "success";
            }
            
        } catch (Exception $e) {
            $_SESSION['alert_title'] = "Database Error";
            $_SESSION['alert_message'] = "An error occurred: " . $e->getMessage();
            $_SESSION['alert_type'] = "error";
        }

        header("Location: database_management.php");
        exit();
    }
}

// --- FETCH CURRENT DATA ---
function fetchTable($conn, $table) {
    $data = [];
    try {
        $res = $conn->query("SELECT * FROM $table ORDER BY id ASC");
        if ($res && $res->num_rows > 0) {
            while($row = $res->fetch_assoc()) { $data[] = $row; }
        }
    } catch (Exception $e) { /* Table might not exist yet */ }
    return $data;
}

$occupations = fetchTable($conn, 'config_occupations');
$incomes = fetchTable($conn, 'config_monthly_income');
$civil_statuses = fetchTable($conn, 'config_civil_status');
$categories = fetchTable($conn, 'config_product_categories');
$unit_types = fetchTable($conn, 'config_unit_types');
$excel_headers = fetchTable($conn, 'config_excel_headers');
$share_payment_types = [];
$share_type_res = $conn->query("SELECT id, name, is_active, created_at FROM config_share_payment_types ORDER BY is_active DESC, name ASC");
if ($share_type_res && $share_type_res->num_rows > 0) {
    while ($row = $share_type_res->fetch_assoc()) {
        $share_payment_types[] = $row;
    }
}
$share_payment_methods = [];
$share_method_res = $conn->query("SELECT id, name, is_active, created_at FROM config_share_payment_methods ORDER BY is_active DESC, name ASC");
if ($share_method_res && $share_method_res->num_rows > 0) {
    while ($row = $share_method_res->fetch_assoc()) {
        $share_payment_methods[] = $row;
    }
}
$transaction_types = function_exists('getTransactionTypes') ? getTransactionTypes($conn, false) : [];

$setting_res = $conn->query("SELECT setting_value FROM config_inventory_settings WHERE setting_key = 'allow_negative_stock'");
$allow_negative = 0;
if ($setting_res && $setting_res->num_rows > 0) {
    $allow_negative = (int)$setting_res->fetch_assoc()['setting_value'];
}

$inventory_report_manager_name = 'VRIAN ANDREW B. PORTUGUESE';
$inventory_report_setting_res = $conn->query("SELECT setting_value FROM config_inventory_settings WHERE setting_key = 'inventory_report_manager_name' LIMIT 1");
if ($inventory_report_setting_res && $inventory_report_setting_res->num_rows > 0) {
    $inventory_report_manager_name = trim((string)($inventory_report_setting_res->fetch_assoc()['setting_value'] ?? ''));
    if ($inventory_report_manager_name === '') {
        $inventory_report_manager_name = 'VRIAN ANDREW B. PORTUGUESE';
    }
}

$receipt_treasurer_name = 'HELENA GESTA';
$receipt_manager_name = 'VRIAN ANDREW B. PORTUGUESE';
$receipt_setting_res = $conn->query("SELECT setting_key, setting_value FROM config_inventory_settings WHERE setting_key IN ('receipt_treasurer_name', 'receipt_manager_name')");
if ($receipt_setting_res && $receipt_setting_res->num_rows > 0) {
    while ($row = $receipt_setting_res->fetch_assoc()) {
        if ($row['setting_key'] === 'receipt_treasurer_name' && trim((string)$row['setting_value']) !== '') {
            $receipt_treasurer_name = trim((string)$row['setting_value']);
        }
        if ($row['setting_key'] === 'receipt_manager_name' && trim((string)$row['setting_value']) !== '') {
            $receipt_manager_name = trim((string)$row['setting_value']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Management - Coop DBMS</title>
    
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
        input:checked ~ .toggle-bg { background-color: #6a1b9a; }
        input:checked ~ .toggle-dot { transform: translateX(100%); }
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

    <div id="customConfirmModal" class="fixed inset-0 z-[1001] hidden items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="customConfirmBox">
            <div id="customConfirmHeader" class="px-6 py-4 flex items-center gap-3 border-b bg-amber-50 border-amber-100">
                <i id="customConfirmIcon" class="fas fa-triangle-exclamation text-2xl text-amber-500"></i>
                <h3 id="customConfirmTitle" class="text-lg font-bold tracking-tight text-amber-800">Confirm Action</h3>
            </div>
            <div class="p-6 text-gray-700 text-sm leading-relaxed" id="customConfirmMessage"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button type="button" id="customConfirmCancelBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg transition-colors shadow-sm">Cancel</button>
                <button type="button" id="customConfirmProceedBtn" class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-4 rounded-lg transition-colors shadow-md">Proceed</button>
            </div>
        </div>
    </div>

    <div class="flex h-screen w-full">

        <div id="mobile-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden transition-opacity" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="bg-white w-72 border-r border-gray-200 flex flex-col transition-transform transform -translate-x-full md:translate-x-0 fixed md:relative z-50 h-full shadow-lg md:shadow-none">
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
                <a href="database_management.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
                    <i class="fas fa-database w-6"></i> DATABASE SETTINGS
                </a>
                <a href="activity_logs.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-clock-rotate-left w-6"></i> ACTIVITY LOGS
                </a>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
            
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Database Settings</h1>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                <div class="max-w-7xl mx-auto">
                    
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
                            <button onclick="switchTab('membership')" id="btn-membership" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-primary text-primary">
                                <i class="fas fa-user-edit mr-2"></i>Membership Form Settings
                            </button>
                            <button onclick="switchTab('inventory')" id="btn-inventory" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-boxes mr-2"></i>Inventory Settings
                            </button>
                            <button onclick="switchTab('sharetypes')" id="btn-sharetypes" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-tags mr-2"></i>Member Payment Settings
                            </button>
                            <button onclick="switchTab('transtypes')" id="btn-transtypes" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-receipt mr-2"></i>Transaction Settings
                            </button>
                            <button onclick="switchTab('excel')" id="btn-excel" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-file-excel mr-2"></i>Membership Imports
                            </button>
                            <button onclick="switchTab('transac')" id="btn-transac" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-file-invoice-dollar mr-2"></i>Transactions Imports
                            </button>
                            <button onclick="switchTab('shares')" id="btn-shares" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-hand-holding-usd mr-2"></i>Member Payment Imports
                            </button>
                        </nav>
                    </div>

                    <div id="tab-membership" class="tab-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-[400px]">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-briefcase text-primary mr-2"></i>Occupations</h3>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach($occupations as $occ): ?>
                                        <li class="py-2 group">
                                            <div id="view_occ_<?= $occ['id'] ?>" class="flex justify-between items-center w-full">
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($occ['name']) ?></span>
                                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button type="button" onclick="toggleEdit('occ_<?= $occ['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                    <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this occupation?');">
                                                        <input type="hidden" name="action" value="del_occ">
                                                        <input type="hidden" name="id" value="<?= $occ['id'] ?>">
                                                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form method="POST" id="edit_occ_<?= $occ['id'] ?>" class="hidden flex gap-2 w-full mt-1">
                                                <input type="hidden" name="action" value="edit_occ">
                                                <input type="hidden" name="id" value="<?= $occ['id'] ?>">
                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($occ['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                                                <button type="submit" class="text-green-600 hover:text-green-800 transition-colors"><i class="fas fa-check"></i></button>
                                                <button type="button" onclick="toggleEdit('occ_<?= $occ['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="action" value="add_occ">
                                    <input type="text" name="new_occupation" placeholder="New Occupation..." required class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"><i class="fas fa-plus"></i></button>
                                </form>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-[400px]">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-wallet text-green-600 mr-2"></i>Monthly Incomes</h3>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach($incomes as $inc): ?>
                                        <li class="py-2 group">
                                            <div id="view_inc_<?= $inc['id'] ?>" class="flex justify-between items-center w-full">
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($inc['name']) ?></span>
                                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button type="button" onclick="toggleEdit('inc_<?= $inc['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                    <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this income bracket?');">
                                                        <input type="hidden" name="action" value="del_inc">
                                                        <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                                                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form method="POST" id="edit_inc_<?= $inc['id'] ?>" class="hidden flex gap-2 w-full mt-1">
                                                <input type="hidden" name="action" value="edit_inc">
                                                <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($inc['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-green-600">
                                                <button type="submit" class="text-green-600 hover:text-green-800 transition-colors"><i class="fas fa-check"></i></button>
                                                <button type="button" onclick="toggleEdit('inc_<?= $inc['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="action" value="add_inc">
                                    <input type="text" name="new_income" placeholder="e.g. 15,000 - 20,000" required class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"><i class="fas fa-plus"></i></button>
                                </form>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-[400px]">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-ring text-blue-500 mr-2"></i>Civil Status</h3>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach($civil_statuses as $civ): ?>
                                        <li class="py-2 group">
                                            <div id="view_civ_<?= $civ['id'] ?>" class="flex justify-between items-center w-full">
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($civ['name']) ?></span>
                                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button type="button" onclick="toggleEdit('civ_<?= $civ['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                    <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this status?');">
                                                        <input type="hidden" name="action" value="del_civ">
                                                        <input type="hidden" name="id" value="<?= $civ['id'] ?>">
                                                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form method="POST" id="edit_civ_<?= $civ['id'] ?>" class="hidden flex gap-2 w-full mt-1">
                                                <input type="hidden" name="action" value="edit_civ">
                                                <input type="hidden" name="id" value="<?= $civ['id'] ?>">
                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($civ['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                                                <button type="submit" class="text-green-600 hover:text-green-800 transition-colors"><i class="fas fa-check"></i></button>
                                                <button type="button" onclick="toggleEdit('civ_<?= $civ['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="action" value="add_civ">
                                    <input type="text" name="new_civil" placeholder="New Status..." required class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"><i class="fas fa-plus"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="tab-inventory" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-[400px]">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-tags text-orange-500 mr-2"></i>Product Categories</h3>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach($categories as $cat): ?>
                                        <li class="py-2 group">
                                            <div id="view_cat_<?= $cat['id'] ?>" class="flex justify-between items-center w-full">
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($cat['name']) ?></span>
                                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button type="button" onclick="toggleEdit('cat_<?= $cat['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                    <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this category?');">
                                                        <input type="hidden" name="action" value="del_cat">
                                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form method="POST" id="edit_cat_<?= $cat['id'] ?>" class="hidden flex gap-2 w-full mt-1">
                                                <input type="hidden" name="action" value="edit_cat">
                                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($cat['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                                                <button type="submit" class="text-green-600 hover:text-green-800 transition-colors"><i class="fas fa-check"></i></button>
                                                <button type="button" onclick="toggleEdit('cat_<?= $cat['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="action" value="add_cat">
                                    <input type="text" name="new_cat" placeholder="e.g. Canned Goods" required class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"><i class="fas fa-plus"></i></button>
                                </form>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-[400px]">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-weight-hanging text-teal-500 mr-2"></i>Inventory Units</h3>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach($unit_types as $unit): ?>
                                        <li class="py-2 group">
                                            <div id="view_unit_<?= $unit['id'] ?>" class="flex justify-between items-center w-full">
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($unit['name']) ?></span>
                                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button type="button" onclick="toggleEdit('unit_<?= $unit['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                    <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this unit type?');">
                                                        <input type="hidden" name="action" value="del_unit">
                                                        <input type="hidden" name="id" value="<?= $unit['id'] ?>">
                                                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form method="POST" id="edit_unit_<?= $unit['id'] ?>" class="hidden flex gap-2 w-full mt-1">
                                                <input type="hidden" name="action" value="edit_unit">
                                                <input type="hidden" name="id" value="<?= $unit['id'] ?>">
                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($unit['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500">
                                                <button type="submit" class="text-green-600 hover:text-green-800 transition-colors"><i class="fas fa-check"></i></button>
                                                <button type="button" onclick="toggleEdit('unit_<?= $unit['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="action" value="add_unit">
                                    <input type="text" name="new_unit" placeholder="e.g. Box, Liter" required class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="submit" class="bg-teal-500 hover:bg-teal-600 text-white px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"><i class="fas fa-plus"></i></button>
                                </form>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-red-200 flex flex-col h-[400px]">
                            <div class="p-4 border-b border-gray-100 bg-red-50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-cogs text-red-500 mr-2"></i>System Controls</h3>
                            </div>
                            <div class="flex-1 p-6">
                                <form method="POST" class="flex flex-col gap-5 h-full">
                                    <input type="hidden" name="action" value="update_inv_settings">
                                    
                                    <div>
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" name="allow_negative" value="1" class="sr-only" <?= $allow_negative === 1 ? 'checked' : '' ?>>
                                                <div class="block bg-gray-300 w-12 h-7 rounded-full transition-colors toggle-bg"></div>
                                                <div class="toggle-dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full transition-transform"></div>
                                            </div>
                                            <div class="text-sm font-bold text-gray-800">Allow Negative Stock</div>
                                        </label>
                                        <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                            If enabled, the POS will allow you to check out and outsource items even if the current master inventory is at 0. This creates discrepancies that you must review later.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-800 mb-1">Inventory Report Signatory</label>
                                        <input type="text" name="inventory_report_manager_name" value="<?= htmlspecialchars($inventory_report_manager_name) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                        <p class="text-xs text-gray-500 mt-2 leading-relaxed">This name appears on the printed daily inventory report.</p>
                                    </div>

                                    <button type="submit" class="mt-auto bg-gray-800 hover:bg-gray-900 text-white py-2 px-4 rounded-md text-sm font-semibold transition-colors w-full shadow-md"><i class="fas fa-save mr-2"></i>SAVE CONTROLS</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="tab-excel" class="tab-content hidden">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-file-excel text-green-700 mr-2"></i>Excel Import Mapping Definitions</h3>
                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded font-semibold border border-yellow-200">Advanced Config</span>
                            </div>
                            <div class="p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-4">The Membership Importer uses split name fields and maps them into the members table. Full-name headers are not supported in the import template.</p>
                                
                                <div class="mb-6 bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-sm font-semibold text-gray-800 mb-3">Membership Import Format</p>
                                    <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                        <thead class="text-xs text-gray-500 uppercase bg-white border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-2 font-semibold">Data Required</th>
                                                <th class="px-4 py-2 font-semibold">Accepted Excel Column Names</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Form ID</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Form ID, ID, Form No</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Member First Name</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Member First Name, Firstname</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Member Second Name (Optional)</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Member Second Name, Secondname</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Member Middle Name</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Member Middle Name, Middlename</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Member Last Name</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Member Last Name, Lastname</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Separated Member Name Fields</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Member First Name, Member Second Name, Member Middle Name, Member Last Name</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Date of Birth</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Date of Birth, DOB, Birth Date</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Birth Place</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Birth Place, Place of Birth</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Civil Status</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Civil Status, Status</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Religion</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Religion</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Sex</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Sex, Gender</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Tribe</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Tribe</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">SSS / GSIS No.</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">SSS/GSIS No., SSS No, GSIS No, SSS</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">TIN No.</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">TIN No., TIN</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Postal Code</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Postal Code, Zip Code</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Address</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Address, Home Address</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Business / Office Address</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Business - Office Address, Business Address, Office Address</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Educational Attainment</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Educational Attainment, Education, Attainment</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Present Employment / Business Activities</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Present Employment/Business Activities, Present Employment, Business Activities, Employment</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Occupation</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Occupation, Job</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Monthly Income</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Monthly Income, Income</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Beneficiaries Name</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Beneficiaries Name, Beneficiary Name, Beneficiary, Ben Name</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Beneficiaries Date of Birth</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Beneficiaries Date of Birth, Beneficiary Date of Birth, Ben DOB</td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-bold text-gray-800">Relationship to the Member</td>
                                                <td class="px-4 py-2 font-mono text-xs text-primary">Relationship to the Member, Relationship, Rel</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-transac" class="tab-content hidden">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i>Transactions Import Format Guide</h3>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded font-semibold border border-blue-200">System Aliases</span>
                            </div>
                            <div class="p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-4">The Transactions Importer uses a Smart Engine. Use the reference and transaction type fields when available. It ignores capitalization and spaces, and it matches members using the separated member name fields. Repeat rows for additional items in the same transaction.</p>
                                
                                <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="px-4 py-2 font-semibold">Data Required</th>
                                            <th class="px-4 py-2 font-semibold">Accepted Excel Column Names</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Date</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Date of Transaction, Date, Transaction Date</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Reference No. / Invoice No. / Receipt No.</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Invoice, Invoice No, Receipt, Reference No, Ref No</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Transaction Type</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Transaction Type, Type, Category</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Member First Name</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Member First Name, Firstname</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Member Second Name (Optional)</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Member Second Name, Second Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Member Middle Name</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Member Middle Name, Middle Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Member Last Name</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Member Last Name, Last Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Separated Member Name Fields</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Member First Name, Member Second Name, Member Middle Name, Member Last Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Quantity</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Quantity, Qty</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Item Unit</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Item Unit, Unit, Measurement</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Item Name / Description</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Item Description, Description, Item, Items, Item Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Item Cost / Selling Price</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Selling Price, Price, Unit Price, Item Cost</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Amount of Item</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Amount of Item, Item Amount</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Total Amount</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Total Amount, Total, Amount</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Downpayment</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Downpayment Amount, Downpayment, DP</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Balance</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Remaining Balance, Balance, Remaining</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Status</td>
                                            <td class="px-4 py-2 font-mono text-xs text-blue-600">Payment Status, Status</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="tab-shares" class="tab-content hidden">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-hand-holding-usd text-green-600 mr-2"></i>Membership Shares Import Format</h3>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-semibold border border-green-200">System Aliases</span>
                            </div>
                            <div class="p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-4">When importing Member Shares, use Member ID or Form ID when available. Otherwise the importer will match the separated name fields using normalized exact comparison. Every row must include a Reference No. / Invoice No. / Receipt No.</p>
                                
                                <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="px-4 py-2 font-semibold">Data Required</th>
                                            <th class="px-4 py-2 font-semibold">Accepted Excel Column Names</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Date</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Date of Transaction, Date</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Member ID</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Member ID, ID</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Form ID</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Form ID, Form No.</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Reference No. / Invoice No. / Receipt No.</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Reference No., Invoice No., Receipt No., Ref No.</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">First Name</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Member First Name, Firstname</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Second Name (Optional)</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Member Second Name, Second Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Middle Name</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Member Middle Name, Middle Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Last Name</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Member Last Name, Last Name</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Transaction Type</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Transaction Type, Type</td>
                                        </tr>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-bold text-gray-800">Amount</td>
                                            <td class="px-4 py-2 font-mono text-xs text-green-600">Payment Amount, Payment, Amount</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="tab-sharetypes" class="tab-content hidden">
                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-tags text-primary mr-2"></i>Share Payment Types</h3>
                                    <span class="text-xs bg-purple-100 text-primary px-2 py-1 rounded font-semibold border border-purple-200">Configurable</span>
                                </div>
                                <form method="POST" class="p-4 space-y-4">
                                    <input type="hidden" name="action" value="add_share_type">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">New Payment Type</label>
                                        <input type="text" name="new_share_type" required placeholder="e.g. Savings Contribution" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                                        <i class="fas fa-plus mr-2"></i>ADD TYPE
                                    </button>
                                </form>
                            </div>

                            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-list-ul text-primary mr-2"></i>Existing Payment Types</h3>
                                    <span class="text-xs text-gray-500"><?= count($share_payment_types) ?> item(s)</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-3 font-bold tracking-wider">Name</th>
                                                <th class="px-4 py-3 font-bold tracking-wider">Status</th>
                                                <th class="px-4 py-3 font-bold tracking-wider text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php if (!empty($share_payment_types)): ?>
                                                <?php foreach ($share_payment_types as $type): ?>
                                                    <tr class="hover:bg-purple-50 transition-colors">
                                                        <td class="px-4 py-3 font-semibold text-gray-800">
                                                            <div id="view_share_<?= (int)$type['id'] ?>" class="flex items-center justify-between gap-3">
                                                                <span><?= htmlspecialchars($type['name']) ?></span>
                                                                <button type="button" onclick="toggleEdit('share_<?= (int)$type['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                            </div>
                                                            <form method="POST" id="edit_share_<?= (int)$type['id'] ?>" class="hidden flex gap-2 mt-2">
                                                                <input type="hidden" name="action" value="edit_share_type">
                                                                <input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
                                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($type['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                                                                <button type="button" onclick="toggleEdit('share_<?= (int)$type['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                                                <button type="submit" class="bg-primary hover:bg-primaryDark text-white text-xs font-bold px-3 rounded">SAVE</button>
                                                            </form>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <?php if ((int)$type['is_active'] === 1): ?>
                                                                <span class="bg-green-100 text-green-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-green-200">ACTIVE</span>
                                                            <?php else: ?>
                                                                <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-gray-200">INACTIVE</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-4 py-3 text-right">
                                                            <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this share payment type? Existing records will remain saved.');">
                                                                <input type="hidden" name="action" value="del_share_type">
                                                                <input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
                                                                <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors">DELETE</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">No share payment types configured yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-signature text-purple-600 mr-2"></i>Receipt Signatories</h3>
                                    <span class="text-xs text-gray-500">Printable receipt names</span>
                                </div>
                                <form method="POST" class="p-4 space-y-4">
                                    <input type="hidden" name="action" value="update_receipt_signatories">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Checked By / Treasurer</label>
                                        <input type="text" name="receipt_treasurer_name" value="<?= htmlspecialchars($receipt_treasurer_name) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Noted By / Manager</label>
                                        <input type="text" name="receipt_manager_name" value="<?= htmlspecialchars($receipt_manager_name) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <p class="text-xs text-gray-500">These names appear on the printed member share receipt.</p>
                                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                                        <i class="fas fa-save mr-2"></i>SAVE RECEIPT NAMES
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                <h3 class="font-bold text-gray-800"><i class="fas fa-wallet text-primary mr-2"></i>Member Payment Methods</h3>
                                <span class="text-xs text-gray-500"><?= count($share_payment_methods) ?> item(s)</span>
                            </div>
                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 p-4">
                                <div class="bg-white rounded-xl border border-gray-200">
                                    <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                        <h3 class="font-bold text-gray-800"><i class="fas fa-plus text-primary mr-2"></i>Add Payment Method</h3>
                                        <span class="text-xs bg-purple-100 text-primary px-2 py-1 rounded font-semibold border border-purple-200">Configurable</span>
                                    </div>
                                    <form method="POST" class="p-4 space-y-4">
                                        <input type="hidden" name="action" value="add_share_method">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">New Method</label>
                                            <input type="text" name="new_share_method" required placeholder="e.g. Cash, GCash, Downpayment" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                                            <i class="fas fa-plus mr-2"></i>ADD METHOD
                                        </button>
                                    </form>
                                </div>

                                <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                        <h3 class="font-bold text-gray-800"><i class="fas fa-list-ul text-primary mr-2"></i>Existing Payment Methods</h3>
                                        <span class="text-xs text-gray-500"><?= count($share_payment_methods) ?> item(s)</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                                <tr>
                                                    <th class="px-4 py-3 font-bold tracking-wider">Name</th>
                                                    <th class="px-4 py-3 font-bold tracking-wider">Status</th>
                                                    <th class="px-4 py-3 font-bold tracking-wider text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <?php if (!empty($share_payment_methods)): ?>
                                                    <?php foreach ($share_payment_methods as $method): ?>
                                                        <tr class="hover:bg-purple-50 transition-colors">
                                                            <td class="px-4 py-3 font-semibold text-gray-800">
                                                                <div id="view_method_<?= (int)$method['id'] ?>" class="flex items-center justify-between gap-3">
                                                                    <span><?= htmlspecialchars($method['name']) ?></span>
                                                                    <button type="button" onclick="toggleEdit('method_<?= (int)$method['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                                </div>
                                                                <form method="POST" id="edit_method_<?= (int)$method['id'] ?>" class="hidden flex gap-2 mt-2">
                                                                    <input type="hidden" name="action" value="edit_share_method">
                                                                    <input type="hidden" name="id" value="<?= (int)$method['id'] ?>">
                                                                    <input type="text" name="edit_name" value="<?= htmlspecialchars($method['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                                                                    <button type="button" onclick="toggleEdit('method_<?= (int)$method['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                                                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white text-xs font-bold px-3 rounded">SAVE</button>
                                                                </form>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <?php if ((int)$method['is_active'] === 1): ?>
                                                                    <span class="bg-green-100 text-green-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-green-200">ACTIVE</span>
                                                                <?php else: ?>
                                                                    <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-gray-200">INACTIVE</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-right">
                                                                <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this payment method? Existing records will remain saved.');">
                                                                    <input type="hidden" name="action" value="del_share_method">
                                                                    <input type="hidden" name="id" value="<?= (int)$method['id'] ?>">
                                                                    <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors">DELETE</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">No member payment methods configured yet.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-transtypes" class="tab-content hidden">
                        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-receipt text-primary mr-2"></i>Transaction Types</h3>
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded font-semibold border border-blue-200">Configurable</span>
                                </div>
                                <form method="POST" class="p-4 space-y-4">
                                    <input type="hidden" name="action" value="add_trans_type">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">New Transaction Type</label>
                                        <input type="text" name="new_trans_type" required placeholder="e.g. Sales, Outsourced, Miscellaneous" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                                        <i class="fas fa-plus mr-2"></i>ADD TYPE
                                    </button>
                                </form>
                            </div>

                            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-boxes-stacked text-teal-500 mr-2"></i>Item Units</h3>
                                    <span class="text-xs bg-teal-100 text-teal-800 px-2 py-1 rounded font-semibold border border-teal-200">Configurable</span>
                                </div>
                                <form method="POST" class="p-4 space-y-4">
                                    <input type="hidden" name="action" value="add_unit">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">New Item Unit</label>
                                        <input type="text" name="new_unit" required placeholder="e.g. pcs, pieces, tray, kg, bag" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <button type="submit" class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                                        <i class="fas fa-plus mr-2"></i>ADD UNIT
                                    </button>
                                </form>
                            </div>

                            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-layer-group text-primary mr-2"></i>Existing Transaction Types</h3>
                                    <span class="text-xs text-gray-500"><?= count($transaction_types) ?> item(s)</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-3 font-bold tracking-wider">Name</th>
                                                <th class="px-4 py-3 font-bold tracking-wider">Status</th>
                                                <th class="px-4 py-3 font-bold tracking-wider text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php if (!empty($transaction_types)): ?>
                                                <?php foreach ($transaction_types as $type): ?>
                                                    <tr class="hover:bg-blue-50 transition-colors">
                                                        <td class="px-4 py-3 font-semibold text-gray-800">
                                                            <div id="view_trans_<?= (int)$type['id'] ?>" class="flex items-center justify-between gap-3">
                                                                <span><?= htmlspecialchars($type['name']) ?></span>
                                                                <button type="button" onclick="toggleEdit('trans_<?= (int)$type['id'] ?>')" class="text-blue-500 hover:text-blue-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                            </div>
                                                            <form method="POST" id="edit_trans_<?= (int)$type['id'] ?>" class="hidden flex gap-2 mt-2">
                                                                <input type="hidden" name="action" value="edit_trans_type">
                                                                <input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
                                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($type['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                                                                <button type="button" onclick="toggleEdit('trans_<?= (int)$type['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                                                <button type="submit" class="bg-primary hover:bg-primaryDark text-white text-xs font-bold px-3 rounded">SAVE</button>
                                                            </form>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <?php if ((int)$type['is_active'] === 1): ?>
                                                                <span class="bg-green-100 text-green-800 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-green-200">ACTIVE</span>
                                                            <?php else: ?>
                                                                <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded text-[10px] font-bold uppercase border border-gray-200">INACTIVE</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-4 py-3 text-right">
                                                            <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this transaction type? Existing records will remain saved.');">
                                                                <input type="hidden" name="action" value="del_trans_type">
                                                                <input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
                                                                <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors">DELETE</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">No transaction types configured yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-weight-hanging text-teal-500 mr-2"></i>Existing Item Units</h3>
                                    <span class="text-xs text-gray-500"><?= count($unit_types) ?> item(s)</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap">
                                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-3 font-bold tracking-wider">Name</th>
                                                <th class="px-4 py-3 font-bold tracking-wider text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php if (!empty($unit_types)): ?>
                                                <?php foreach ($unit_types as $unit): ?>
                                                    <tr class="hover:bg-teal-50 transition-colors">
                                                        <td class="px-4 py-3 font-semibold text-gray-800">
                                                            <div id="view_unit_<?= (int)$unit['id'] ?>" class="flex items-center justify-between gap-3">
                                                                <span><?= htmlspecialchars($unit['name']) ?></span>
                                                                <button type="button" onclick="toggleEdit('unit_<?= (int)$unit['id'] ?>')" class="text-teal-600 hover:text-teal-700 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                                            </div>
                                                            <form method="POST" id="edit_unit_<?= (int)$unit['id'] ?>" class="hidden flex gap-2 mt-2">
                                                                <input type="hidden" name="action" value="edit_unit">
                                                                <input type="hidden" name="id" value="<?= (int)$unit['id'] ?>">
                                                                <input type="text" name="edit_name" value="<?= htmlspecialchars($unit['name']) ?>" required class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500">
                                                                <button type="button" onclick="toggleEdit('unit_<?= (int)$unit['id'] ?>')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times"></i></button>
                                                                <button type="submit" class="bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold px-3 rounded">SAVE</button>
                                                            </form>
                                                        </td>
                                                        <td class="px-4 py-3 text-right">
                                                            <form method="POST" class="inline m-0" onsubmit="return openConfirmModal('Delete this unit type? Existing records will remain saved.');">
                                                                <input type="hidden" name="action" value="del_unit">
                                                                <input type="hidden" name="id" value="<?= (int)$unit['id'] ?>">
                                                                <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 font-semibold py-1 px-3 rounded shadow-sm text-xs transition-colors">DELETE</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="2" class="px-4 py-8 text-center text-gray-500">No item units configured yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><i class="fas fa-signature text-purple-600 mr-2"></i>Receipt Signatories</h3>
                                    <span class="text-xs text-gray-500">Printable receipt names</span>
                                </div>
                                <form method="POST" class="p-4 space-y-4">
                                    <input type="hidden" name="action" value="update_receipt_signatories">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Checked By / Treasurer</label>
                                        <input type="text" name="receipt_treasurer_name" value="<?= htmlspecialchars($receipt_treasurer_name) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Noted By / Manager</label>
                                        <input type="text" name="receipt_manager_name" value="<?= htmlspecialchars($receipt_manager_name) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <p class="text-xs text-gray-500">These names appear on the printed transaction receipt.</p>
                                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm">
                                        <i class="fas fa-save mr-2"></i>SAVE RECEIPT NAMES
                                    </button>
                                </form>
                            </div>
                                </div>
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

        // --- INLINE EDIT TOGGLE LOGIC ---
        function toggleEdit(id) {
            const viewDiv = document.getElementById('view_' + id);
            const editForm = document.getElementById('edit_' + id);
            
            if (viewDiv.classList.contains('hidden')) {
                viewDiv.classList.remove('hidden');
                editForm.classList.add('hidden');
            } else {
                viewDiv.classList.add('hidden');
                editForm.classList.remove('hidden');
                // Focus the input field automatically when opening edit mode
                editForm.querySelector('input[name="edit_name"]').focus();
            }
        }

        // --- TAB LOGIC ---
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.className = "tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300";
            });

            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.className = "tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors border-primary text-primary";
            localStorage.setItem('activeDbTab', tabId);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const activeTab = localStorage.getItem('activeDbTab') || 'membership';
            switchTab(activeTab);
        });

        // --- CUSTOM ALERT LOGIC ---
        let alertRedirectUrl = null;
        let pendingConfirmForm = null;

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

        function openConfirmModal(message, formEl = null) {
            const modal = document.getElementById('customConfirmModal');
            const box = document.getElementById('customConfirmBox');
            const msgEl = document.getElementById('customConfirmMessage');
            const btnProceed = document.getElementById('customConfirmProceedBtn');

            if (!modal || !box || !msgEl || !btnProceed) {
                return true;
            }

            pendingConfirmForm = formEl || null;
            msgEl.innerHTML = message;

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            setTimeout(() => {
                box.classList.remove('translate-y-4', 'opacity-0');
                box.classList.add('translate-y-0', 'opacity-100');
            }, 10);

            return false;
        }

        function closeConfirmModal() {
            const modal = document.getElementById('customConfirmModal');
            const box = document.getElementById('customConfirmBox');
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

        document.getElementById('customConfirmCancelBtn')?.addEventListener('click', function() {
            pendingConfirmForm = null;
            closeConfirmModal();
        });

        document.getElementById('customConfirmProceedBtn')?.addEventListener('click', function() {
            const form = pendingConfirmForm;
            pendingConfirmForm = null;
            closeConfirmModal();
            if (form) {
                setTimeout(() => form.submit(), 50);
            }
        });

        // Catch Session Alerts
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
