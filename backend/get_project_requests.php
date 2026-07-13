<?php
// backend/get_project_requests.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
require_once 'db.php';

try {
    $stmt = $pdo->prepare("
        SELECT pm.id as request_id, p.id as project_id, p.title as project_title, 
               u.id as user_id, u.first_name, u.last_name, u.avatar_url, pm.created_at
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        JOIN users u ON pm.user_id = u.id
        WHERE p.user_id = ? AND pm.status = 'pending'
        ORDER BY pm.created_at DESC
    ");
    $stmt->execute([$user_id]);
    
    $requests = [];
    while ($row = $stmt->fetch()) {
        $requests[] = [
            'request_id' => $row['request_id'],
            'project_id' => $row['project_id'],
            'project_title' => $row['project_title'],
            'user_id' => $row['user_id'],
            'user_name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? 'http://static.photos/people/200x200/10',
            'created_at' => $row['created_at']
        ];
    }

    echo json_encode(['success' => true, 'requests' => $requests]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
