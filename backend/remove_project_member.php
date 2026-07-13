<?php
// backend/remove_project_member.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$member_id = $data['member_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$member_id) {
    echo json_encode(['success' => false, 'error' => 'Member ID required']);
    exit();
}

try {
    // Check if the current user owns the project this member belongs to
    $stmt = $pdo->prepare("
        SELECT p.user_id 
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.id = ?
    ");
    $stmt->execute([$member_id]);
    $project = $stmt->fetch();

    if (!$project || $project['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized or member not found']);
        exit();
    }

    // Delete the member
    $stmt = $pdo->prepare("DELETE FROM project_members WHERE id = ?");
    $stmt->execute([$member_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
