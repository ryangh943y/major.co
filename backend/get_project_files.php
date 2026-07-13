<?php
// backend/get_project_files.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit();
}

// Ensure user is authorized
$stmt = $pdo->prepare("
    SELECT 1 FROM projects p 
    LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ? AND pm.status = 'accepted'
    WHERE p.id = ? AND (p.user_id = ? OR pm.id IS NOT NULL)
");
$stmt->execute([$user_id, $project_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT f.file_name, f.file_path, f.file_size, f.uploaded_at, u.first_name, u.last_name
        FROM project_files f
        JOIN users u ON f.user_id = u.id
        WHERE f.project_id = ?
        ORDER BY f.uploaded_at DESC
    ");
    $stmt->execute([$project_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'files' => $files]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
