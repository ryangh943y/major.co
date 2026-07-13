<?php
// backend/get_post_comments.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.content, c.created_at, c.parent_id, c.is_pinned, u.id as comment_user_id, u.first_name, u.last_name, u.avatar_url, p.user_id as post_author_id
        FROM post_comments c
        JOIN users u ON c.user_id = u.id
        JOIN posts p ON c.post_id = p.id
        WHERE c.post_id = ?
        ORDER BY c.is_pinned DESC, c.created_at ASC
    ");
    $stmt->execute([$post_id]);
    
    $comments = [];
    while ($row = $stmt->fetch()) {
        $comments[] = [
            'id' => $row['id'],
            'parent_id' => $row['parent_id'],
            'is_author' => ($row['comment_user_id'] == $row['post_author_id']),
            'is_pinned' => (bool)$row['is_pinned'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($row['first_name'] . ' ' . $row['last_name']) . '&background=random',
            'content' => $row['content'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
