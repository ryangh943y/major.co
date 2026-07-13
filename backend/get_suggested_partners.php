<?php
// backend/get_suggested_partners.php - Get top 3 suggested partners ranked by skill match
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
    // Get current user's skills
    $stmt = $pdo->prepare("SELECT skills FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_skills = !empty($user['skills']) ? json_decode($user['skills'], true) : [];
    
    // Normalize user skills to lowercase for comparison
    $user_skills_lower = array_map('strtolower', $user_skills);

    // Get all other users (excluding current user and already connected)
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.skills, u.bio
        FROM users u
        WHERE u.id != ?
        AND u.id NOT IN (
            SELECT partner_id FROM connections WHERE user_id = ?
            UNION
            SELECT user_id FROM connections WHERE partner_id = ?
        )
        ORDER BY u.id DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);

    $partners = [];
    while ($row = $stmt->fetch()) {
        $partner_skills = !empty($row['skills']) ? json_decode($row['skills'], true) : [];
        $partner_skills_lower = array_map('strtolower', $partner_skills);

        // Calculate skill match count
        $matching_skills = 0;
        foreach ($user_skills_lower as $skill) {
            if (in_array($skill, $partner_skills_lower)) {
                $matching_skills++;
            }
        }

        $partners[] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? 'http://static.photos/people/200x200/1',
            'skills' => array_slice($partner_skills, 0, 3),
            'bio' => substr($row['bio'] ?? 'No bio added yet', 0, 60),
            'matching_skills' => $matching_skills
        ];
    }

    // Sort by matching skills (descending)
    usort($partners, function($a, $b) {
        return $b['matching_skills'] - $a['matching_skills'];
    });

    // Return top 3
    $suggested = array_slice($partners, 0, 3);

    echo json_encode([
        'success' => true,
        'suggestions' => $suggested
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
