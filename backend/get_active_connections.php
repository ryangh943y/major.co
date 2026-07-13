<?php
// backend/get_active_connections.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
require_once 'db.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id as connection_id,
            u.id as user_id, 
            u.first_name, 
            u.last_name, 
            u.avatar_url, 
            u.bio, 
            u.skills 
        FROM connections c
        JOIN users u ON (c.user_id = u.id OR c.partner_id = u.id)
        WHERE (c.user_id = ? OR c.partner_id = ?) 
        AND c.status = 'connected'
        AND u.id != ?
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format skills
    foreach ($connections as &$conn) {
        $conn['skills'] = $conn['skills'] ? json_decode($conn['skills'], true) : [];
        $conn['name'] = $conn['first_name'] . ' ' . $conn['last_name'];
    }

    echo json_encode(['success' => true, 'connections' => $connections]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
