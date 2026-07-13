<?php
// backend/upload_project_file.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$project_id = $_POST['project_id'] ?? null;

if (!$project_id || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Missing project ID or file']);
    exit();
}

// Ensure user is authorized
$stmt = $pdo->prepare("
    SELECT 1 FROM projects p 
    LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ? AND pm.status = 'accepted'
    WHERE p.id = ? AND (p.user_id = ? OR pm.id IS NOT NULL)
");
$stmt->execute([$user_id, $project_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$file = $_FILES['file'];
$file_name = basename($file['name']);
$file_size = $file['size'];
$tmp_name = $file['tmp_name'];

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/project_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
$unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
$destination = $upload_dir . $unique_filename;
$public_path = 'uploads/project_files/' . $unique_filename;

if (move_uploaded_file($tmp_name, $destination)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO project_files (project_id, user_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$project_id, $user_id, htmlspecialchars($file_name), $public_path, $file_size]);
        
        echo json_encode(['success' => true, 'path' => $public_path]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
}
?>
