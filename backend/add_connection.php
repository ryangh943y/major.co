<?php
// backend/add_connection.php - Add or confirm a connection
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['partner_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'partner_id is required']);
    exit();
}

$partner_id = (int)$data['partner_id'];

if ($user_id === $partner_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot connect with yourself']);
    exit();
}

require_once 'db.php';
require_once 'add_notification.php';

try {
    // Check if connection already exists
    $stmt = $pdo->prepare("
        SELECT id, status FROM connections 
        WHERE (user_id = ? AND partner_id = ?) 
           OR (user_id = ? AND partner_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'connected') {
            http_response_code(400);
            echo json_encode(['error' => 'Already connected', 'status' => 'connected']);
            exit();
        } else {
            // Update pending to connected
            $stmt = $pdo->prepare("UPDATE connections SET status = 'connected', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$existing['id']]);
            
            // Generate notifications
            addNotification($pdo, $partner_id, 'connection', 'Accepted your connection request', $user_id);
            addNotification($pdo, $user_id, 'connection', 'You are now connected', $partner_id);

            echo json_encode(['success' => true, 'message' => 'Connection confirmed', 'status' => 'connected']);
            exit();
        }
    }

    // Create new connection (pending by default)
    $stmt = $pdo->prepare("
        INSERT INTO connections (user_id, partner_id, status) 
        VALUES (?, ?, 'pending')
    ");
    $stmt->execute([$user_id, $partner_id]);

    addNotification($pdo, $partner_id, 'connection', 'Sent you a connection request', $user_id);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Connection request sent', 'status' => 'pending']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
