<?php
// backend/get_matchmaking_recommendations.php
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
    // 1. Get current user's skills
    $stmt = $pdo->prepare("SELECT skills FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $my_skills_json = $stmt->fetchColumn();
    $my_skills = !empty($my_skills_json) ? json_decode($my_skills_json, true) : [];

    if (empty($my_skills)) {
        // Return success with empty recommendations since user has no skills listed
        echo json_encode(['success' => true, 'recommendations' => []]);
        exit();
    }

    // Convert my skills to normalized list (lowercase, trimmed)
    $my_skills_norm = array_map(function($s) { return strtolower(trim($s)); }, $my_skills);

    // 2. Get list of user IDs that are already connected or pending
    $stmt = $pdo->prepare("
        SELECT CASE WHEN user_id = ? THEN partner_id ELSE user_id END as relative_id 
        FROM connections 
        WHERE user_id = ? OR partner_id = ?
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $connected_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Add current user ID to the list to exclude self
    $connected_ids[] = $user_id;

    // Convert to placeholders for SQL
    $in_placeholders = implode(',', array_fill(0, count($connected_ids), '?'));

    // 3. Query potential matches (who have skills listed)
    $sql = "SELECT id, first_name, last_name, avatar_url, bio, skills FROM users WHERE id NOT IN ($in_placeholders) AND skills IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($connected_ids);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recommendations = [];

    // 4. Calculate Jaccard similarity score for each candidate
    foreach ($candidates as $cand) {
        $cand_skills = json_decode($cand['skills'], true);
        if (empty($cand_skills)) {
            continue;
        }

        $cand_skills_norm = array_map(function($s) { return strtolower(trim($s)); }, $cand_skills);

        $intersection = array_intersect($my_skills_norm, $cand_skills_norm);
        $union = array_unique(array_merge($my_skills_norm, $cand_skills_norm));

        if (count($union) === 0) {
            continue;
        }

        $score = count($intersection) / count($union);
        $match_percentage = round($score * 100);

        if ($match_percentage > 0) {
            $recommendations[] = [
                'id' => $cand['id'],
                'first_name' => $cand['first_name'],
                'last_name' => $cand['last_name'],
                'avatar_url' => $cand['avatar_url'],
                'bio' => $cand['bio'] ?? 'No bio listed.',
                'skills' => $cand_skills,
                'match_percentage' => $match_percentage
            ];
        }
    }

    // 5. Sort recommendations by match_percentage DESC
    usort($recommendations, function($a, $b) {
        return $b['match_percentage'] <=> $a['match_percentage'];
    });

    // Take top 6 recommendations
    $top_recommendations = array_slice($recommendations, 0, 6);

    echo json_encode(['success' => true, 'recommendations' => $top_recommendations]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
