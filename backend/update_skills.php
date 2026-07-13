<?php
// backend/update_skills.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$skills = $data['skills'] ?? [];
$user_id = $_SESSION['user_id'];

require_once 'db.php';

try {
    $skills_json = json_encode($skills);
    $stmt = $pdo->prepare("UPDATE users SET skills = ? WHERE id = ?");
    $stmt->execute([$skills_json, $user_id]);

    echo json_encode(['success' => true, 'skills' => $skills]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
