<?php
// backend/serve_file.php
session_start();

// 1. Verify if the user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access. Please log in first.']);
    exit();
}

// 2. Validate and sanitize requested file path
$file = isset($_GET['file']) ? $_GET['file'] : '';

// Prevent directory traversal attacks (e.g. file=../../db.php)
$file = str_replace(['../', '..\\'], '', $file);

// The base directory is uploads/
$base_dir = realpath(__DIR__ . '/../uploads');
$full_path = realpath($base_dir . '/' . $file);

// Verify the file exists and is strictly inside the uploads directory
if (!$full_path || strpos($full_path, $base_dir) !== 0 || !is_file($full_path)) {
    http_response_code(404);
    exit('File not found');
}

// 3. For project workspace files, check project authorization
if (strpos($file, 'project_files/') === 0) {
    require_once 'db.php';
    $user_id = $_SESSION['user_id'];
    
    // Look up the project_id for this file in the database
    $search_path = 'uploads/' . $file;
    $stmt = $pdo->prepare("SELECT project_id FROM project_files WHERE file_path = ?");
    $stmt->execute([$search_path]);
    $project_id = $stmt->fetchColumn();
    
    if ($project_id) {
        // Verify user is owner or accepted member of project
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM projects p
            LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ? AND pm.status = 'accepted'
            WHERE p.id = ? AND (p.user_id = ? OR pm.id IS NOT NULL)
        ");
        $stmt->execute([$user_id, $project_id, $user_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            exit('Forbidden: You are not authorized to view files from this project workspace.');
        }
    }
}

// 4. Output the file with correct headers
$mime_type = mime_content_type($full_path);
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($full_path));

// Clear output buffer to prevent corrupted images
if (ob_get_level()) {
    ob_end_clean();
}

readfile($full_path);
exit();
?>
