<?php
// backend/update_project.php
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

$project_id = isset($data['project_id']) ? (int)$data['project_id'] : null;
$title = isset($data['title']) ? trim($data['title']) : '';
$description = isset($data['description']) ? trim($data['description']) : '';
$status = isset($data['status']) ? trim($data['status']) : 'planning';

if (!$project_id || empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID and Title are required.']);
    exit();
}

// Validate status
$allowed_statuses = ['planning', 'in-progress', 'completed', 'on-hold'];
if (!in_array($status, $allowed_statuses)) {
    $status = 'planning';
}

try {
    // Verify user is owner of the project
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $owner_id = $stmt->fetchColumn();

    if ($owner_id != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the project owner can update details.']);
        exit();
    }

    // Perform update
    $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([$title, $description, $status, $project_id]);

    echo json_encode(['success' => true, 'message' => 'Project updated successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
