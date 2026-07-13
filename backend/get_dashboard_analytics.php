<?php
// backend/get_dashboard_analytics.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

try {
    // 1. Get Connections count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE (user_id = ? OR partner_id = ?) AND status = 'connected'");
    $stmt->execute([$user_id, $user_id]);
    $connections_count = $stmt->fetchColumn();

    // 2. Get Project Status stats (projects owned or joined by the user)
    // First, owned projects status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM projects 
        WHERE user_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$user_id]);
    $owned_project_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Second, joined projects status
    $stmt = $pdo->prepare("
        SELECT p.status, COUNT(*) as count 
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.user_id = ? AND pm.status = 'accepted'
        GROUP BY p.status
    ");
    $stmt->execute([$user_id]);
    $joined_project_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Combine owned and joined project stats
    $project_statuses = ['planning' => 0, 'in-progress' => 0, 'completed' => 0, 'on-hold' => 0];
    foreach ([$owned_project_stats, $joined_project_stats] as $stats) {
        foreach ($stats as $status => $count) {
            if (isset($project_statuses[$status])) {
                $project_statuses[$status] += $count;
            }
        }
    }

    // 3. Get Task stats (assigned to the user)
    // Check if the project_tasks table exists first. If the migration was not executed or failed, we default to 0 tasks.
    $tasks_statuses = ['todo' => 0, 'in_progress' => 0, 'completed' => 0];
    try {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM project_tasks 
            WHERE assigned_to = ? 
            GROUP BY status
        ");
        $stmt->execute([$user_id]);
        $task_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($task_stats as $status => $count) {
            if (isset($tasks_statuses[$status])) {
                $tasks_statuses[$status] = (int)$count;
            }
        }
    } catch (PDOException $e) {
        // Table probably doesn't exist yet, ignore and return empty task stats
    }

    // 4. Get Social Stats (Total posts published, total comments, and total likes)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $posts_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM post_likes pl
        JOIN posts p ON pl.post_id = p.id
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $post_likes_count = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'analytics' => [
            'connections' => (int)$connections_count,
            'projects' => $project_statuses,
            'tasks' => $tasks_statuses,
            'social' => [
                'posts' => (int)$posts_count,
                'likes' => (int)$post_likes_count
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
