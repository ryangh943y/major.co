<?php
// backend/delete_chat.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$partner_id = isset($data['partner_id']) ? intval($data['partner_id']) : 0;

if ($partner_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

try {
    // Delete all messages between the current user and the partner
    $stmt = $pdo->prepare("
        DELETE FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
    ");
    $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
