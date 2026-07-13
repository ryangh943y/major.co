<?php
// backend/handle_project_join.php
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

$request_id = isset($data['request_id']) ? (int)$data['request_id'] : null;
$action = isset($data['action']) ? $data['action'] : null; // 'accept' or 'reject'

if (!$request_id || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

require_once 'db.php';
require_once 'add_notification.php';

try {
    // Determine if the current user has the right to accept/reject
    $stmt = $pdo->prepare("
        SELECT pm.id, pm.project_id, pm.user_id as requester_id, p.user_id as owner_id, p.title 
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.id = ? AND pm.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();

    if (!$req || $req['owner_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized or request not found']);
        exit();
    }

    if ($action === 'accept') {
        $stmt = $pdo->prepare("UPDATE project_members SET status = 'accepted' WHERE id = ?");
        $stmt->execute([$request_id]);
        addNotification($pdo, $req['requester_id'], 'project', "Your request to join " . $req['title'] . " was accepted", $user_id);
        echo json_encode(['success' => true, 'message' => 'Request accepted']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM project_members WHERE id = ?");
        $stmt->execute([$request_id]);
        echo json_encode(['success' => true, 'message' => 'Request declined']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
