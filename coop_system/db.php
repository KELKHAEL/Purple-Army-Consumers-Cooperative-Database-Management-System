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
