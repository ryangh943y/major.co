<?php
// backend/request_project_join.php
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
$project_id = isset($data['project_id']) ? (int)$data['project_id'] : null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID is required']);
    exit();
}

require_once 'db.php';
require_once 'add_notification.php';

try {
    // Check if project exists and isn't owned by the user
    $stmt = $pdo->prepare("SELECT user_id, title FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit();
    }

    if ($project['user_id'] == $user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot join your own project']);
        exit();
    }

    // Insert request
    $stmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$project_id, $user_id]);

    // Send notification to project owner
    addNotification($pdo, $project['user_id'], 'project', "Requested to join your project: " . $project['title'], $user_id);

    echo json_encode(['success' => true, 'message' => 'Join request sent']);
} catch (PDOException $e) {
    // Handle duplicate requests (1062 is MySQL duplicate entry error)
    if ($e->getCode() == 23000) {
        error_log("Duplicate Request: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'You have already sent a request for this project']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
?>
