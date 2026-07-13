<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Get search filter (skill)
$skill_filter = isset($_GET['skill']) ? trim($_GET['skill']) : '';

try {
    if (empty($skill_filter)) {
        // Get all users except current user
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, avatar_url, skills, bio FROM users WHERE id != ? LIMIT 20");
        $stmt->execute([$current_user_id]);
    } else {
        // Search users by skill
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, avatar_url, skills, bio FROM users WHERE id != ? LIMIT 100");
        $stmt->execute([$current_user_id]);
    }

    $partners = [];
    while ($row = $stmt->fetch()) {
        $skills = !empty($row['skills']) ? json_decode($row['skills'], true) : [];
        
        // Filter by skill if search is active
        if (!empty($skill_filter)) {
            $skill_found = false;
            foreach ($skills as $skill) {
                if (stripos($skill, $skill_filter) !== false) {
                    $skill_found = true;
                    break;
                }
            }
            if (!$skill_found) {
                continue;
            }
        }

        $partners[] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? 'http://static.photos/people/200x200/1',
            'skills' => $skills,
            'bio' => $row['bio'] ?? 'No bio added yet'
        ];

        if (count($partners) >= 20) {
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'partners' => $partners,
        'total' => count($partners)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>
