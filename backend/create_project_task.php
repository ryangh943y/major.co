<?php
// backend/create_project_task.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

// Get POST data (accept both JSON and standard POST form)
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$project_id = isset($data['project_id']) ? (int)$data['project_id'] : null;
$title = isset($data['title']) ? trim($data['title']) : '';
$description = isset($data['description']) ? trim($data['description']) : null;
$assigned_to = isset($data['assigned_to']) && $data['assigned_to'] !== '' ? (int)$data['assigned_to'] : null;
$priority = isset($data['priority']) ? trim($data['priority']) : 'medium';
$due_date = isset($data['due_date']) && $data['due_date'] !== '' ? trim($data['due_date']) : null;

if (!$project_id || empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID and Task Title are required']);
    exit();
}

try {
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
        echo json_encode(['error' => 'Not authorized to create tasks in this project']);
        exit();
    }

    // Insert task
    $stmt = $pdo->prepare("
        INSERT INTO project_tasks (project_id, title, description, status, assigned_to, priority, due_date) 
        VALUES (?, ?, ?, 'todo', ?, ?, ?)
    ");
    $stmt->execute([$project_id, $title, $description, $assigned_to, $priority, $due_date]);
    $task_id = $pdo->lastInsertId();

    // Fetch the created task details (with assignee info)
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.avatar_url 
        FROM project_tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    // Trigger Notification to assignee if assigned to someone else
    if ($assigned_to && $assigned_to !== $user_id) {
        $stmt = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $proj_title = $stmt->fetchColumn();

        $message = "You have been assigned a new task: '$title' in project '$proj_title'.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, related_id, message) VALUES (?, 'project', ?, ?)");
        $stmt->execute([$assigned_to, $project_id, $message]);
    }

    echo json_encode(['success' => true, 'task' => $task]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
