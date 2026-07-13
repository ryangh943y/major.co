<?php
// backend/vote_project_poll.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$poll_id = $data['poll_id'] ?? null;
$option_id = $data['option_id'] ?? null;

if (!$poll_id || !$option_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    // 1. Check authorization for the project this poll belongs to
    $stmt = $pdo->prepare("
        SELECT p.id as project_id, p.user_id as owner_id
        FROM project_polls pp
        JOIN projects p ON pp.project_id = p.id
        WHERE pp.id = ?
    ");
    $stmt->execute([$poll_id]);
    $poll_data = $stmt->fetch();

    if (!$poll_data) {
        echo json_encode(['success' => false, 'error' => 'Poll not found']);
        exit();
    }

    $project_id = $poll_data['project_id'];

    $stmtAuth = $pdo->prepare("
        SELECT 1 FROM projects p 
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ? AND pm.status = 'accepted'
        WHERE p.id = ? AND (p.user_id = ? OR pm.id IS NOT NULL)
    ");
    $stmtAuth->execute([$user_id, $project_id, $user_id]);
    if (!$stmtAuth->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    // 2. Check if user already voted. If yes, update it. If no, insert it.
    $stmtCheck = $pdo->prepare("SELECT id FROM project_poll_votes WHERE poll_id = ? AND user_id = ?");
    $stmtCheck->execute([$poll_id, $user_id]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUpdate = $pdo->prepare("UPDATE project_poll_votes SET option_id = ? WHERE id = ?");
        $stmtUpdate->execute([$option_id, $existing['id']]);
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO project_poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
        $stmtInsert->execute([$poll_id, $option_id, $user_id]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
