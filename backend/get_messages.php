<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
$last_message_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if ($partner_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid partner ID']);
    exit;
}

try {
    // Fetch messages between current user and partner
    $query = "
        SELECT m.*, u.first_name, u.avatar_url 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?))
    ";
    
    $params = [$current_user_id, $partner_id, $partner_id, $current_user_id];
    
    if ($last_message_id > 0) {
        $query .= " AND m.id > ?";
        $params[] = $last_message_id;
    }
    
    $query .= " ORDER BY m.created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Mark received messages as read
    if (!empty($messages)) {
        $updateStmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $updateStmt->execute([$partner_id, $current_user_id]);
    }
    
    // Fetch the maximum ID of a message sent by the current user that has been read by the partner
    $readStmt = $pdo->prepare("SELECT MAX(id) as max_read_id FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 1");
    $readStmt->execute([$current_user_id, $partner_id]);
    $last_read_id = $readStmt->fetchColumn() ?: 0;
    
    // Fetch partner activity status (Creative Feature #13)
    $partnerStmt = $pdo->prepare("SELECT last_seen FROM users WHERE id = ?");
    $partnerStmt->execute([$partner_id]);
    $last_seen = $partnerStmt->fetchColumn();
    $is_online = false;
    if ($last_seen) {
        $is_online = (time() - strtotime($last_seen)) < 300;
    }
    
    echo json_encode(['messages' => $messages, 'last_read_id' => $last_read_id, 'is_online' => $is_online]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
