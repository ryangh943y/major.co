<?php
// backend/get_pending_connections.php - Get pending connection requests for current user
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
    // Get users who sent a connection request to the current user
    $stmt = $pdo->prepare("
        SELECT c.id as connection_id, u.id as user_id, u.first_name, u.last_name, u.avatar_url, u.skills, u.bio, c.created_at
        FROM connections c
        JOIN users u ON c.user_id = u.id
        WHERE c.partner_id = ? AND c.status = 'pending'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    
    $pending_requests = [];
    while ($row = $stmt->fetch()) {
        $skills = !empty($row['skills']) ? json_decode($row['skills'], true) : [];
        $pending_requests[] = [
            'connection_id' => $row['connection_id'],
            'user_id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? 'http://static.photos/people/200x200/1',
            'skills' => array_slice($skills, 0, 3), // max 3 skills
            'bio' => substr($row['bio'] ?? 'No bio added yet', 0, 60),
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'requests' => $pending_requests
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
