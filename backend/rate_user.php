<?php
// backend/rate_user.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$rater_id = $_SESSION['user_id'];
$project_id = $data['project_id'] ?? null;
$ratee_id = $data['ratee_id'] ?? null;
$rating = $data['rating'] ?? null;
$review = $data['review'] ?? '';

if (!$project_id || !$ratee_id || !$rating || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

require_once 'db.php';

try {
    // 1. Verify rater is the project owner
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project || $project['user_id'] != $rater_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only project owners can rate members']);
        exit();
    }

    // 2. Verify ratee is an accepted member of the project
    $stmt = $pdo->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ? AND status = 'accepted'");
    $stmt->execute([$project_id, $ratee_id]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User is not an active member of this project']);
        exit();
    }

    // 3. Insert or update rating
    $stmt = $pdo->prepare("
        INSERT INTO user_ratings (project_id, rater_id, ratee_id, rating, review) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)
    ");
    $stmt->execute([$project_id, $rater_id, $ratee_id, $rating, htmlspecialchars($review)]);

    echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
