<?php
// backend/get_project_specific_requests.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit();
}

try {
    // Check if the current user is the owner of the project
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project || $project['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT pm.id as request_id, u.first_name, u.last_name, u.avatar_url, p.title as project_title
        FROM project_members pm
        JOIN users u ON pm.user_id = u.id
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.project_id = ? AND pm.status = 'pending'
    ");
    $stmt->execute([$project_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_requests = array_map(function($req) {
        return [
            'request_id' => $req['request_id'],
            'user_name' => ($req['first_name'] ?? 'Unknown') . ' ' . ($req['last_name'] ?? ''),
            'avatar_url' => $req['avatar_url'] ?? 'http://static.photos/people/200x200/10',
            'project_title' => $req['project_title']
        ];
    }, $requests);

    echo json_encode(['success' => true, 'requests' => $formatted_requests]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
