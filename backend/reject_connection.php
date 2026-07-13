<?php
// backend/reject_connection.php - Decline/Remove a pending connection request
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

if (!isset($data['connection_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'connection_id is required']);
    exit();
}

$connection_id = (int)$data['connection_id'];

require_once 'db.php';

try {
    // Delete the connection request if it belongs to the current user (either sender or receiver)
    $stmt = $pdo->prepare("DELETE FROM connections WHERE id = ? AND (user_id = ? OR partner_id = ?)");
    $stmt->execute([$connection_id, $user_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Connection request removed']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Connection request not found or unauthorized']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
