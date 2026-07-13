<?php
// backend/remove_connection.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$connection_id = $data['connection_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$connection_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Connection ID required']);
    exit();
}

require_once 'db.php';

try {
    // Delete connection only if it belongs to the current user
    $stmt = $pdo->prepare("DELETE FROM connections WHERE id = ? AND (user_id = ? OR partner_id = ?)");
    $stmt->execute([$connection_id, $user_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Connection not found or unauthorized']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
