<?php
require_once __DIR__ . '/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Simple helper to get POST value
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$first_name = post('first_name');
$last_name = post('last_name');
$email = post('email');
$password = post('password');
$confirm_password = post('confirm_password');
$skills_raw = post('skills'); // comma separated
$security_question = post('security_question');
$security_answer = post('security_answer');
$terms = isset($_POST['terms']) ? true : false;

$errors = [];

// Validation
if (!$first_name) $errors[] = 'First name is required.';
if (!$last_name) $errors[] = 'Last name is required.';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if (!$password || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
if (!$terms) $errors[] = 'You must accept terms.';
if (!$security_question) $errors[] = 'Security question is required.';
if (!$security_answer) $errors[] = 'Security answer is required.';

$skills = [];
if ($skills_raw) {
    $skills = array_filter(array_map('trim', explode(',', $skills_raw)));
}
if (count($skills) < 1) $errors[] = 'Please select at least 1 skill.';

if (count($errors) > 0) {
    // For simplicity, send back the first error. In production, return JSON or re-render form with errors.
    http_response_code(400);
    echo htmlspecialchars($errors[0]);
    exit;
}

// Check if email already exists
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo 'Email already registered.';
        exit;
    }

    // Insert user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $security_answer_hash = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash, skills, security_question, security_answer_hash, created_at) VALUES (:first_name, :last_name, :email, :password_hash, :skills, :security_question, :security_answer_hash, NOW())');
    $stmt->execute([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'password_hash' => $password_hash,
        'skills' => json_encode(array_values($skills)),
        'security_question' => $security_question,
        'security_answer_hash' => $security_answer_hash
    ]);

    // Redirect to login page on success
    header('Location: ../login.html');
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo 'Server error: ' . htmlspecialchars($e->getMessage());
    exit;
}

?>
