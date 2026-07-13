<?php
// backend/delete_post.php
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

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

try {
    // Check if user owns the post
    $stmt = $pdo->prepare("SELECT id, image_url FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to delete this post']);
        exit();
    }

    // Delete post image file if exists
    if (!empty($post['image_url'])) {
        $file_path = '../' . $post['image_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete the post (cascading will delete comments and likes if set up, otherwise we can implicitly delete, but let's assume cascade or it just deletes)
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
