<?php
// backend/delete_project_task.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$task_id = isset($data['task_id']) ? (int)$data['task_id'] : null;
if (!$task_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID is required']);
    exit();
}

try {
    // Get task details to check project authorization
    $stmt = $pdo->prepare("SELECT project_id FROM project_tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit();
    }

    $project_id = $task['project_id'];

    // Verify user is owner or accepted member of project
    $stmt = $pdo->prepare("
        SELECT p.id 
        FROM projects p
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ? AND pm.status = 'accepted'
        WHERE p.id = ? AND (p.user_id = ? OR pm.id IS NOT NULL)
    ");
    $stmt->execute([$user_id, $project_id, $user_id]);
    $project = $stmt->fetch();

    if (!$project) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to modify tasks in this project']);
        exit();
    }

    // Delete task
    $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE id = ?");
    $stmt->execute([$task_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
