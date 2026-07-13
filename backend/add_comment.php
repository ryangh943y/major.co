<?php
// backend/add_comment.php
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

require_once 'db.php';
$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$parent_id = isset($_POST['parent_id']) && intval($_POST['parent_id']) > 0 ? intval($_POST['parent_id']) : null;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($post_id <= 0 || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO post_comments (post_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $parent_id, $content]);
    
    // Fetch inserted comment details to return, also returning post's user_id to know if this user is the author
    $comment_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("
        SELECT c.id, c.content, c.created_at, c.parent_id, c.is_pinned, u.id as comment_user_id, u.first_name, u.last_name, u.avatar_url, p.user_id as post_author_id
        FROM post_comments c 
        JOIN users u ON c.user_id = u.id 
        JOIN posts p ON c.post_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $comment['id'],
            'parent_id' => $comment['parent_id'],
            'is_author' => ($comment['comment_user_id'] == $comment['post_author_id']),
            'is_pinned' => (bool)$comment['is_pinned'],
            'name' => $comment['first_name'] . ' ' . $comment['last_name'],
            'avatar_url' => $comment['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($comment['first_name'] . ' ' . $comment['last_name']) . '&background=random',
            'content' => $comment['content'],
            'created_at' => $comment['created_at']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
