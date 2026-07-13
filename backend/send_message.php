<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['receiver_id']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $data['receiver_id'];
$message = trim($data['message']);

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$sender_id, $receiver_id, $message]);
    
    // Add notification
    require_once 'add_notification.php';
    addNotification($pdo, $receiver_id, 'message', 'Sent you a message', $sender_id);

    // After successful insert, return the new message back to the client
    $new_id = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'message_id' => $new_id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
