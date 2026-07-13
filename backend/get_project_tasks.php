<?php
// backend/get_project_tasks.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID is required']);
    exit();
}

try {
    // Check project membership or ownership
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
        echo json_encode(['error' => 'Not authorized to access this project workspace']);
        exit();
    }

    // Fetch tasks
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.avatar_url 
        FROM project_tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.project_id = ?
        ORDER BY t.created_at ASC
    ");
    $stmt->execute([$project_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tasks' => $tasks]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
