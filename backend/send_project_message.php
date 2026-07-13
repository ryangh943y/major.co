<?php
// backend/send_project_message.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$project_id = $data['project_id'] ?? null;
$message = $data['message'] ?? '';

if (!$project_id || empty(trim($message))) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
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
    $stmt = $pdo->prepare("INSERT INTO project_messages (project_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$project_id, $user_id, htmlspecialchars($message)]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
