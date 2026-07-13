<?php
// Simple PDO MySQL connection for XAMPP
// Put your database credentials in this file (or better: copy to db.local.php and keep out of repo)
// Usage: require_once __DIR__ . '/db.php';

$db_host = '127.0.0.1';
$db_name = 'majorco';
$db_user = 'root';
$db_pass = ''; // default XAMPP MySQL password is empty

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // In production, avoid echoing the exception message
    http_response_code(500);
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

// Track User Activity (Creative Feature #13)
// Limit database writes to once per 60 seconds using session cache
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['last_seen_update']) || (time() - $_SESSION['last_seen_update']) > 60) {
        try {
            $stmt_seen = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
            $stmt_seen->execute([$_SESSION['user_id']]);
            $_SESSION['last_seen_update'] = time();
        } catch (Exception $e) {
            // Silently ignore to prevent database errors from breaking core flow
        }
    }
}

?>
