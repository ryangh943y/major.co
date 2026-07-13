<?php
// backend/create_post.php
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
require_once 'db.php';

$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$image_url = null;

// Handle file upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($file_ext, $allowed_exts)) {
        $new_filename = uniqid('post_') . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $image_url = 'uploads/' . $new_filename;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file']);
            exit();
        }
    } else {
         http_response_code(400);
         echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.']);
         exit();
    }
}

if ($content === '' && $image_url === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Post cannot be empty']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image_url) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $content, $image_url]);
    
    echo json_encode(['success' => true, 'message' => 'Post created successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
