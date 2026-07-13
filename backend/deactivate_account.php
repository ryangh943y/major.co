<?php
// backend/deactivate_account.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // 1. Delete physical avatar file from server if it is custom
    $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $avatar_url = $stmt->fetchColumn();

    if ($avatar_url && strpos($avatar_url, 'uploads/avatars/') === 0) {
        $full_path = realpath(__DIR__ . '/../' . $avatar_url);
        if ($full_path && is_file($full_path)) {
            unlink($full_path); // Physically delete user avatar (Optimize storage!)
        }
    }

    // 2. Fetch and delete all physical project files uploaded by this user
    $stmt = $pdo->prepare("SELECT file_path FROM project_files WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($files as $file_path) {
        $full_path = realpath(__DIR__ . '/../' . $file_path);
        if ($full_path && is_file($full_path)) {
            unlink($full_path); // Physically delete file (Optimize storage!)
        }
    }

    // 3. Delete database references of files, tasks, members, notifications, posts, comments, likes, connections
    $stmt = $pdo->prepare("DELETE FROM project_files WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE assigned_to = ?");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("DELETE FROM project_members WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("DELETE FROM post_comments WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("DELETE FROM post_likes WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Handle project deletion where this user is the owner
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $owned_projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($owned_projects as $proj_id) {
        // Delete tasks, files, members of owned projects
        $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE project_id = ?");
        $stmt->execute([$proj_id]);

        $stmt = $pdo->prepare("DELETE FROM project_files WHERE project_id = ?");
        $stmt->execute([$proj_id]);

        $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = ?");
        $stmt->execute([$proj_id]);

        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$proj_id]);
    }

    // Delete posts owned by user
    $stmt = $pdo->prepare("DELETE FROM posts WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Delete connections
    $stmt = $pdo->prepare("DELETE FROM connections WHERE user_id = ? OR partner_id = ?");
    $stmt->execute([$user_id, $user_id]);

    // Delete ratings
    $stmt = $pdo->prepare("DELETE FROM ratings WHERE rater_id = ? OR ratee_id = ?");
    $stmt->execute([$user_id, $user_id]);

    // 4. Finally, delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    $pdo->commit();

    // Destroy session
    session_destroy();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
