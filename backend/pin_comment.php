<?php
// backend/pin_comment.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
require_once 'db.php';

$comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
$action = isset($_POST['action']) && $_POST['action'] === 'unpin' ? 'unpin' : 'pin';

if ($comment_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid comment ID']);
    exit();
}

try {
    // Check if user is the author of the post for this comment
    $stmt = $pdo->prepare("
        SELECT p.user_id as post_author_id, c.post_id 
        FROM post_comments c
        JOIN posts p ON c.post_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment_data = $stmt->fetch();

    if (!$comment_data || $comment_data['post_author_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to pin comments for this post.']);
        exit();
    }

    $post_id = $comment_data['post_id'];

    if ($action === 'pin') {
         // Optionally, unpin all other comments first if we only allow 1 pinned comment
         $pdo->prepare("UPDATE post_comments SET is_pinned = FALSE WHERE post_id = ?")->execute([$post_id]);
         $pdo->prepare("UPDATE post_comments SET is_pinned = TRUE WHERE id = ?")->execute([$comment_id]);
    } else {
         $pdo->prepare("UPDATE post_comments SET is_pinned = FALSE WHERE id = ?")->execute([$comment_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
