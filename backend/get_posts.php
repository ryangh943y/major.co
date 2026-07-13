<?php
// backend/get_posts.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

try {
    $current_user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.image_url, p.created_at, u.id as user_id, u.first_name, u.last_name, u.avatar_url,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comments_count,
               EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ?) as has_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$current_user_id]);
    
    $posts = [];
    while ($row = $stmt->fetch()) {
        $posts[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($row['first_name'] . ' ' . $row['last_name']) . '&background=random',
            'content' => $row['content'],
            'image_url' => $row['image_url'],
            'created_at' => $row['created_at'],
            'likes_count' => $row['likes_count'],
            'comments_count' => $row['comments_count'],
            'has_liked' => (bool)$row['has_liked']
        ];
    }
    
    echo json_encode(['success' => true, 'posts' => $posts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
