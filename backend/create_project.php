<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Helper function
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$title = post('title');
$description = post('description');
$start_date = post('start_date');
$due_date = post('due_date');
$required_skills = post('required_skills');
$visibility = post('visibility') ?? 'public';

$errors = [];

// Validation
if (empty($title)) $errors[] = 'Project title is required.';
if (empty($description)) $errors[] = 'Project description is required.';
if (!empty($start_date) && !strtotime($start_date)) $errors[] = 'Invalid start date.';
if (!empty($due_date) && !strtotime($due_date)) $errors[] = 'Invalid due date.';
if (!in_array($visibility, ['public', 'private'])) $errors[] = 'Invalid visibility setting.';

// If there are errors, return them
if (count($errors) > 0) {
    http_response_code(400);
    echo json_encode(['error' => $errors[0]]);
    exit;
}

try {
    // Process skills
    $skills = [];
    if (!empty($required_skills)) {
        $skills = array_filter(array_map('trim', explode(',', $required_skills)));
    }

    // Insert project
    $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, description, start_date, due_date, required_skills, visibility) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $user_id,
        $title,
        $description,
        !empty($start_date) ? $start_date : null,
        !empty($due_date) ? $due_date : null,
        !empty($skills) ? json_encode($skills) : null,
        $visibility
    ]);

    $project_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Project created successfully!',
        'project_id' => $project_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>
