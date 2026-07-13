<?php
// backend/update_project_task.php
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
    $stmt = $pdo->prepare("SELECT * FROM project_tasks WHERE id = ?");
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

    // Build update dynamic fields
    $fields = [];
    $params = [];

    if (isset($data['status'])) {
        $status = $data['status'];
        if (in_array($status, ['todo', 'in_progress', 'completed'])) {
            $fields[] = "status = ?";
            $params[] = $status;
        }
    }
    if (isset($data['title'])) {
        $title = trim($data['title']);
        if (!empty($title)) {
            $fields[] = "title = ?";
            $params[] = $title;
        }
    }
    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $params[] = trim($data['description']);
    }
    if (isset($data['assigned_to'])) {
        $assigned_to = $data['assigned_to'] === '' ? null : (int)$data['assigned_to'];
        $fields[] = "assigned_to = ?";
        $params[] = $assigned_to;
        
        // Notify new assignee if changed
        if ($assigned_to && $assigned_to !== $task['assigned_to'] && $assigned_to !== $user_id) {
            $stmt = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $proj_title = $stmt->fetchColumn();

            $message = "You have been assigned a task: '" . ($data['title'] ?? $task['title']) . "' in project '$proj_title'.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, related_id, message) VALUES (?, 'project', ?, ?)");
            $stmt->execute([$assigned_to, $project_id, $message]);
        }
    }

    if (empty($fields)) {
        echo json_encode(['success' => true, 'message' => 'No fields updated']);
        exit();
    }

    $params[] = $task_id;
    $sql = "UPDATE project_tasks SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated task details
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.avatar_url 
        FROM project_tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $updated_task = $stmt->fetch();

    echo json_encode(['success' => true, 'task' => $updated_task]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
