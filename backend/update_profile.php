<?php
// backend/update_profile.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$bio = $_POST['bio'] ?? '';
$skills = $_POST['skills'] ?? ''; // should be comma separated string

if (empty($first_name) || empty($last_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'First name and last name are required']);
    exit();
}

$skills_array = array_filter(array_map('trim', explode(',', $skills)));
$skills_json = json_encode(array_values($skills_array));

require_once 'db.php';

try {
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, bio = ?, skills = ? WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $bio, $skills_json, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
