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

try {
    // Get all users who have exchanged messages with current user or are connected
    // ordered by most recent message
    $query = "
        SELECT 
            u.id, 
            u.first_name, 
            u.last_name, 
            u.avatar_url,
            (SELECT message FROM messages 
             WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages 
             WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
             ORDER BY created_at DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages 
             WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM users u
        WHERE u.id != ?
        AND (
            EXISTS (SELECT 1 FROM messages m WHERE (m.sender_id = u.id AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.id))
            OR 
            EXISTS (SELECT 1 FROM connections c WHERE (c.user_id = u.id AND c.partner_id = ?) OR (c.user_id = ? AND c.partner_id = u.id) AND c.status = 'connected')
        )
        ORDER BY last_message_time DESC, u.first_name ASC
    ";
    
    // Check parameters count carefully:
    // 1. receiver_id = ? (current_user_id)
    // 2. sender_id = ? (current_user_id)
    // 3. receiver_id = ? (current_user_id)
    // 4. sender_id = ? (current_user_id)
    // 5. receiver_id = ? (current_user_id) - for unread count
    // 6. u.id != ? (current_user_id)
    // 7. receiver_id = ? (current_user_id) - for EXISTS messages
    // 8. sender_id = ? (current_user_id) - for EXISTS messages
    // 9. partner_id = ? (current_user_id) - for EXISTS connections
    // 10. user_id = ? (current_user_id) - for EXISTS connections

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $current_user_id, $current_user_id, 
        $current_user_id, $current_user_id, 
        $current_user_id, 
        $current_user_id,
        $current_user_id, $current_user_id,
        $current_user_id, $current_user_id
    ]);
    
    $conversations = $stmt->fetchAll();
    
    echo json_encode(['conversations' => $conversations]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
