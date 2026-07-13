<?php
// backend/delete_project.php
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

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID is required.']);
    exit();
}

try {
    // Verify user is owner of the project
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $owner_id = $stmt->fetchColumn();

    if ($owner_id != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the project owner can delete this project.']);
        exit();
    }

    // Begin Transaction to ensure atomic deletes (all or nothing)
    $pdo->beginTransaction();

    // 1. Fetch file paths in project to delete the physical files from uploads directory (Optimize storage!)
    $stmt = $pdo->prepare("SELECT file_path FROM project_files WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($files as $file_path) {
        $full_path = realpath(__DIR__ . '/../' . $file_path);
        if ($full_path && is_file($full_path)) {
            unlink($full_path); // Physically delete file from server (preventing storage leaks!)
        }
    }

    // 2. Delete tasks
    $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE project_id = ?");
    $stmt->execute([$project_id]);

    // 3. Delete files from DB
    $stmt = $pdo->prepare("DELETE FROM project_files WHERE project_id = ?");
    $stmt->execute([$project_id]);

    // 4. Delete members
    $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = ?");
    $stmt->execute([$project_id]);

    // 5. Delete project
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Project and all related assets deleted successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
