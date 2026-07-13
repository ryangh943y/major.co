<?php
session_start();
require_once __DIR__ . '/db.php';  // PDO connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// Helper to get POST safely
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$email = post('email');
$password = post('password');

if (!$email || !$password) {
    echo "Please fill all fields.";
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format.";
    exit;
}

try {
    // Get user from DB
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "No account found with this email.";
        exit;
    }

    // Verify hashed password
    if (!password_verify($password, $user['password_hash'])) {
        echo "Incorrect password.";
        exit;
    }

    // Start user session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email'] = $user['email'];

    // Login success
    header("Location: ../dashboard.php");
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Server error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
