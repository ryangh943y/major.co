<?php
// backend/get_connections.php - Get connection count and status for current user
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

require_once 'db.php';

try {
    // Get total connected count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM connections 
        WHERE (user_id = ? OR partner_id = ?) 
        AND status = 'connected'
    ");
    $stmt->execute([$user_id, $user_id]);
    $result = $stmt->fetch();
    $connected_count = $result['total'] ?? 0;

    // Get pending requests sent
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM connections 
        WHERE user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $pending_sent = $result['total'] ?? 0;

    // Get pending requests received
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM connections 
        WHERE partner_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $pending_received = $result['total'] ?? 0;

    echo json_encode([
        'connected' => $connected_count,
        'pending_sent' => $pending_sent,
        'pending_received' => $pending_received
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
