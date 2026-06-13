<?php
// db.php - Database connection setup for XAMPP
$servername = "localhost";
$username = "root";       // Default XAMPP username
$password = "";           // Default XAMPP password is blank
$database = "del_rosario_inventory"; 

// Create the connection
$conn = new mysqli($servername, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die("Connection to MySQL failed: " . $conn->connect_error);
}

// Keep application timestamps consistent across printable forms and log entries.
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}
if ($conn instanceof mysqli) {
    @$conn->query("SET time_zone = '+08:00'");
}

// Central activity log storage.
@$conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT(11) NOT NULL AUTO_INCREMENT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id VARCHAR(100) DEFAULT NULL,
    entity_name VARCHAR(255) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'SUCCESS',
    actor_name VARCHAR(100) NOT NULL DEFAULT 'SYSTEM',
    actor_role VARCHAR(50) NOT NULL DEFAULT 'SYSTEM',
    PRIMARY KEY (log_id),
    KEY idx_created_at (created_at),
    KEY idx_module (module),
    KEY idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Configurable member share payment types.
@$conn->query("CREATE TABLE IF NOT EXISTS config_share_payment_types (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_share_payment_type_name (name),
    KEY idx_share_payment_type_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

@$conn->query("INSERT IGNORE INTO config_share_payment_types (name, is_active) VALUES
    ('Membership Fee', 1),
    ('Share Capital', 1)");

// Configurable transaction types for manual transactions and reporting.
@$conn->query("CREATE TABLE IF NOT EXISTS config_transaction_types (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transaction_type_name (name),
    KEY idx_transaction_type_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

@$conn->query("INSERT IGNORE INTO config_transaction_types (name, is_active) VALUES
    ('Sales', 1),
    ('Outsourced', 1),
    ('Share Capital', 1),
    ('Membership Fee', 1),
    ('Miscellaneous', 1),
    ('Purchase', 1)");

$share_payment_type_column_exists = false;
$share_payment_type_column_check = @$conn->query("SHOW COLUMNS FROM transactions LIKE 'share_payment_type_id'");
if ($share_payment_type_column_check && $share_payment_type_column_check->num_rows > 0) {
    $share_payment_type_column_exists = true;
}
if (!$share_payment_type_column_exists) {
    @$conn->query("ALTER TABLE transactions ADD COLUMN share_payment_type_id INT(11) NULL AFTER transaction_type");
}

$share_payment_type_index_exists = false;
$share_payment_type_index_check = @$conn->query("SHOW INDEX FROM transactions WHERE Key_name = 'idx_share_payment_type_id'");
if ($share_payment_type_index_check && $share_payment_type_index_check->num_rows > 0) {
    $share_payment_type_index_exists = true;
}
if (!$share_payment_type_index_exists) {
    @$conn->query("ALTER TABLE transactions ADD KEY idx_share_payment_type_id (share_payment_type_id)");
}

// Backfill legacy rows so older share records stay linked to the config table.
@$conn->query("UPDATE transactions t
    JOIN config_share_payment_types c ON LOWER(TRIM(t.transaction_type)) = LOWER(TRIM(c.name))
    SET t.share_payment_type_id = c.id
    WHERE t.share_payment_type_id IS NULL");
@$conn->query("UPDATE transactions t
    JOIN config_share_payment_types c ON LOWER(TRIM(c.name)) = LOWER('Share Capital')
    SET t.share_payment_type_id = c.id
    WHERE t.share_payment_type_id IS NULL AND UPPER(TRIM(t.transaction_type)) = 'SHARE'");

if (!function_exists('getActivityActor')) {
    function getActivityActor(): array
    {
        $actor_name = 'SYSTEM';
        $actor_role = 'SYSTEM';

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION) && is_array($_SESSION)) {
            $name_keys = ['full_name', 'name', 'username', 'user_name', 'admin_name', 'member_name'];
            foreach ($name_keys as $key) {
                if (!empty($_SESSION[$key])) {
                    $actor_name = trim((string)$_SESSION[$key]);
                    break;
                }
            }

            $role_keys = ['role', 'user_role', 'admin_role'];
            foreach ($role_keys as $key) {
                if (!empty($_SESSION[$key])) {
                    $actor_role = strtoupper(trim((string)$_SESSION[$key]));
                    break;
                }
            }
        }

        return [$actor_name ?: 'SYSTEM', $actor_role ?: 'SYSTEM'];
    }
}

if (!function_exists('getSharePaymentTypes')) {
    function getSharePaymentTypes(mysqli $conn, bool $activeOnly = true): array
    {
        $types = [];
        $sql = "SELECT id, name, is_active FROM config_share_payment_types";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_active DESC, name ASC";

        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $types[] = $row;
            }
        }
        return $types;
    }
}

if (!function_exists('getTransactionTypes')) {
    function getTransactionTypes(mysqli $conn, bool $activeOnly = true): array
    {
        $types = [];
        $sql = "SELECT id, name, is_active FROM config_transaction_types";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_active DESC, name ASC";

        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $types[] = $row;
            }
        }
        return $types;
    }
}

if (!function_exists('resolveTransactionType')) {
    function resolveTransactionType(mysqli $conn, string $label): ?array
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $stmt = $conn->prepare("SELECT id, name, is_active FROM config_transaction_types WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $label);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $stmt->close();
                return $row;
            }
            $stmt->close();
        }

        $fallback_keyword = null;
        if (stripos($label, 'sale') !== false) {
            $fallback_keyword = 'sale';
        } elseif (stripos($label, 'outsource') !== false) {
            $fallback_keyword = 'outsource';
        } elseif (stripos($label, 'share') !== false) {
            $fallback_keyword = 'share';
        } elseif (stripos($label, 'member') !== false) {
            $fallback_keyword = 'member';
        } elseif (stripos($label, 'misc') !== false) {
            $fallback_keyword = 'misc';
        } elseif (stripos($label, 'purchase') !== false) {
            $fallback_keyword = 'purchase';
        }

        if ($fallback_keyword !== null) {
            $like = '%' . $fallback_keyword . '%';
            $stmt = $conn->prepare("SELECT id, name, is_active FROM config_transaction_types WHERE LOWER(name) LIKE ? ORDER BY is_active DESC, name ASC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $like);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $stmt->close();
                    return $row;
                }
                $stmt->close();
            }
        }

        $res = $conn->query("SELECT id, name, is_active FROM config_transaction_types WHERE is_active = 1 ORDER BY name ASC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        $res = $conn->query("SELECT id, name, is_active FROM config_transaction_types ORDER BY is_active DESC, name ASC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        return null;
    }
}

if (!function_exists('resolveSharePaymentType')) {
    function resolveSharePaymentType(mysqli $conn, string $label): ?array
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $stmt = $conn->prepare("SELECT id, name, is_active FROM config_share_payment_types WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $label);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $stmt->close();
                return $row;
            }
            $stmt->close();
        }

        $fallback_keyword = null;
        if (stripos($label, 'fee') !== false) {
            $fallback_keyword = 'fee';
        } elseif (stripos($label, 'share') !== false) {
            $fallback_keyword = 'share';
        }

        if ($fallback_keyword !== null) {
            $sql = "SELECT id, name, is_active FROM config_share_payment_types WHERE LOWER(name) LIKE ? ORDER BY is_active DESC, name ASC LIMIT 1";
            $like = '%' . $fallback_keyword . '%';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $like);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $stmt->close();
                    return $row;
                }
                $stmt->close();
            }
        }

        $res = $conn->query("SELECT id, name, is_active FROM config_share_payment_types WHERE is_active = 1 ORDER BY name ASC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        $res = $conn->query("SELECT id, name, is_active FROM config_share_payment_types ORDER BY is_active DESC, name ASC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        return null;
    }
}

if (!function_exists('logActivity')) {
    function logActivity(mysqli $conn, string $module, string $action, string $entity_type = '', $entity_id = null, string $entity_name = '', string $details = '', string $status = 'SUCCESS'): bool
    {
        [$actor_name, $actor_role] = getActivityActor();

        $module = strtoupper(trim($module)) ?: 'SYSTEM';
        $action = strtoupper(trim($action)) ?: 'ACTION';
        $entity_type = trim($entity_type);
        $entity_id = ($entity_id === null || $entity_id === '') ? null : (string)$entity_id;
        $entity_name = trim($entity_name);
        $details = trim($details);
        $status = strtoupper(trim($status)) ?: 'SUCCESS';

        $stmt = $conn->prepare("INSERT INTO activity_logs (module, action, entity_type, entity_id, entity_name, details, status, actor_name, actor_role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            "sssssssss",
            $module,
            $action,
            $entity_type,
            $entity_id,
            $entity_name,
            $details,
            $status,
            $actor_name,
            $actor_role
        );

        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
// If it connects successfully, this file will quietly run in the background.
?>
