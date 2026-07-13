<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all'; // all, my, public

try {
    if ($filter === 'my') {
        // Get only current user's projects
        $stmt = $pdo->prepare("SELECT id, user_id, title, description, start_date, due_date, status, required_skills, visibility, image_url, created_at 
                              FROM projects WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
    } elseif ($filter === 'public') {
        // Get only public projects (excluding user's own)
        $stmt = $pdo->prepare("SELECT p.id, p.user_id, p.title, p.description, p.start_date, p.due_date, p.status, p.required_skills, p.visibility, p.image_url, p.created_at,
                                     u.first_name, u.last_name, u.avatar_url
                              FROM projects p
                              JOIN users u ON p.user_id = u.id
                              WHERE p.visibility = 'public' AND p.user_id != ? 
                              ORDER BY p.created_at DESC");
        $stmt->execute([$user_id]);
    } else {
        // Get all projects (user's own + public ones)
        $stmt = $pdo->prepare("SELECT p.id, p.user_id, p.title, p.description, p.start_date, p.due_date, p.status, p.required_skills, p.visibility, p.image_url, p.created_at,
                                     u.first_name, u.last_name, u.avatar_url
                              FROM projects p
                              JOIN users u ON p.user_id = u.id
                              WHERE p.user_id = ? OR p.visibility = 'public'
                              ORDER BY CASE WHEN p.user_id = ? THEN 0 ELSE 1 END, p.created_at DESC");
        $stmt->execute([$user_id, $user_id]);
    }

    $projects = [];
    while ($row = $stmt->fetch()) {
        $skills = !empty($row['required_skills']) ? json_decode($row['required_skills'], true) : [];
        
        $projects[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'start_date' => $row['start_date'],
            'due_date' => $row['due_date'],
            'status' => $row['status'],
            'required_skills' => $skills,
            'visibility' => $row['visibility'],
            'image_url' => $row['image_url'] ?? 'http://static.photos/projects/1',
            'created_at' => $row['created_at'],
            'owner_name' => ($row['first_name'] ?? 'Unknown') . ' ' . ($row['last_name'] ?? 'User'),
            'owner_avatar' => $row['avatar_url'] ?? 'http://static.photos/people/200x200/1',
            'is_owner' => $row['user_id'] == $user_id
        ];
    }

    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'total' => count($projects)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>
