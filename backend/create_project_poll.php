<?php
// backend/create_project_poll.php
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
$question = $data['question'] ?? '';
$options = $data['options'] ?? [];

if (!$project_id || empty(trim($question)) || count($options) < 2) {
    echo json_encode(['success' => false, 'error' => 'Invalid input. Please provide a question and at least two options.']);
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
    $pdo->beginTransaction();

    // 1. Create poll
    $stmt = $pdo->prepare("INSERT INTO project_polls (project_id, user_id, question) VALUES (?, ?, ?)");
    $stmt->execute([$project_id, $user_id, htmlspecialchars($question)]);
    $poll_id = $pdo->lastInsertId();

    // 2. Create options
    $stmtOpt = $pdo->prepare("INSERT INTO project_poll_options (poll_id, option_text) VALUES (?, ?)");
    foreach ($options as $opt) {
        if (!empty(trim($opt))) {
            $stmtOpt->execute([$poll_id, htmlspecialchars($opt)]);
        }
    }

    // 3. Insert message referring to poll
    $stmtMsg = $pdo->prepare("INSERT INTO project_messages (project_id, user_id, message, poll_id) VALUES (?, ?, ?, ?)");
    $stmtMsg->execute([$project_id, $user_id, "Created a poll: " . htmlspecialchars($question), $poll_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
