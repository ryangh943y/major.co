<?php
// backend/get_partners_paginated.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';
$my_id = $_SESSION['user_id'];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$skill_filter = isset($_GET['skill']) ? trim($_GET['skill']) : '';

$limit = 8;
$offset = ($page - 1) * $limit;

try {
    if (!empty($skill_filter)) {
        // Safe parameter binding for skill filter search
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.skills, u.bio,
                   c.status as connection_status
            FROM users u
            LEFT JOIN connections c ON (u.id = c.partner_id AND c.user_id = :my_id) 
                                    OR (u.id = c.user_id AND c.partner_id = :my_id)
            WHERE u.id != :my_id AND u.skills LIKE :skill
            ORDER BY u.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':my_id', $my_id, PDO::PARAM_INT);
        $stmt->bindValue(':skill', '%' . $skill_filter . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.skills, u.bio,
                   c.status as connection_status
            FROM users u
            LEFT JOIN connections c ON (u.id = c.partner_id AND c.user_id = :my_id) 
                                    OR (u.id = c.user_id AND c.partner_id = :my_id)
            WHERE u.id != :my_id 
            ORDER BY u.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':my_id', $my_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $partners = [];
    foreach ($rows as $row) {
        $skills = !empty($row['skills']) ? json_decode($row['skills'], true) : [];
        $partners[] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar_url' => $row['avatar_url'] ?? '',
            'bio' => $row['bio'] ?? 'No bio provided.',
            'skills' => $skills,
            'connection_status' => $row['connection_status']
        ];
    }

    echo json_encode(['success' => true, 'partners' => $partners]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
