<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}
require_once 'backend/db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user data
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, avatar_url, skills, bio FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    session_destroy();
    header('Location: login.html');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : $user_data['first_name'];
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : $user_data['last_name'];
    $bio = isset($_POST['bio']) ? trim($_POST['bio']) : $user_data['bio'];
    $skills_raw = isset($_POST['skills']) ? trim($_POST['skills']) : '';

    // Validate input
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } else {
        $avatar_url = $user_data['avatar_url'];

        // Handle file upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                $error = 'File size exceeds 5MB limit.';
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = 'uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Generate unique filename
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Store the file path in the database
                    $avatar_url = $file_path;
                } else {
                    $error = 'Failed to upload file.';
                }
            }
        }

        if (empty($error)) {
            // Process skills
            $skills = [];
            if (!empty($skills_raw)) {
                $skills = array_filter(array_map('trim', explode(',', $skills_raw)));
            }

            // Update user data
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, bio = ?, avatar_url = ?, skills = ? WHERE id = ?");
                $stmt->execute([
                    $first_name,
                    $last_name,
                    $bio,
                    $avatar_url,
                    json_encode($skills),
                    $user_id
                ]);

                $message = 'Profile updated successfully!';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, avatar_url, skills, bio FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

$name = $user_data['first_name'] . ' ' . $user_data['last_name'];
$avatar_url = $user_data['avatar_url'] ?? 'http://static.photos/people/200x200/10';
$skills = !empty($user_data['skills']) ? json_decode($user_data['skills'], true) : [];
$bio = $user_data['bio'] ?? '';

// Fetch user's posts
$stmtPost = $pdo->prepare("SELECT id, image_url, content, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$stmtPost->execute([$user_id]);
$user_posts = $stmtPost->fetchAll();

// Count only posts that have images to accurately reflect the grid count
$valid_grid_posts = array_filter($user_posts, function($p) { return !empty($p['image_url']); });
$posts_count = count($valid_grid_posts);

// Fetch connection count
$stmtConn = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE (user_id = ? OR partner_id = ?) AND status = 'accepted'");
$stmtConn->execute([$user_id, $user_id]);
$connections_count = $stmtConn->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | ProjectCrew</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .profile-stat-card {
            background: white;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .profile-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border-color: var(--primary-light);
        }
        .post-grid-img {
            transition: transform 0.4s ease;
        }
        .post-grid-card:hover .post-grid-img {
            transform: scale(1.05);
        }
        .avatar-upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.3);
        }
        .avatar-upload-overlay:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex">
        <!-- Sidebar (Desktop) -->
        <aside class="sidebar hidden md:flex" role="navigation" aria-label="Sidebar Navigation">
            <a href="dashboard.php" class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <i data-feather="users"></i>
                </div>
                <span class="sidebar-logo-text">Project<span>Crew</span></span>
            </a>
            
            <div class="sidebar-user">
                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="User Avatar" class="sidebar-avatar" data-fallback-name="<?php echo htmlspecialchars($name); ?>">
                <div class="min-w-0">
                    <p class="sidebar-user-name" title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></p>
                    <p class="sidebar-user-role">Team Member</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">
                    <i data-feather="home"></i>
                    <span>Home</span>
                </a>
                <a href="partners.php" class="nav-link">
                    <i data-feather="users"></i>
                    <span>Find Partners</span>
                </a>
                <a href="projects.php" class="nav-link">
                    <i data-feather="folder"></i>
                    <span>Projects</span>
                </a>
                <a href="posts.php" class="nav-link">
                    <i data-feather="message-square"></i>
                    <span>Posts</span>
                </a>
                <a href="messages.php" class="nav-link">
                    <i data-feather="mail"></i>
                    <span>Messages</span>
                </a>
                <a href="profile.php" class="nav-link">
                    <i data-feather="user"></i>
                    <span>Profile</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="settings.php" class="nav-link">
                    <i data-feather="settings"></i>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="nav-link hover:bg-red-50 hover:text-red-600">
                    <i data-feather="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Mobile Bottom Nav -->
        <nav class="mobile-nav" role="navigation" aria-label="Mobile Navigation">
            <div class="mobile-nav-inner">
                <a href="dashboard.php" class="mobile-nav-item">
                    <i data-feather="home"></i>
                    <span>Home</span>
                </a>
                <a href="partners.php" class="mobile-nav-item">
                    <i data-feather="users"></i>
                    <span>Partners</span>
                </a>
                <a href="projects.php" class="mobile-nav-item">
                    <i data-feather="folder"></i>
                    <span>Projects</span>
                </a>
                <a href="posts.php" class="mobile-nav-item">
                    <i data-feather="message-square"></i>
                    <span>Posts</span>
                </a>
                <a href="messages.php" class="mobile-nav-item">
                    <i data-feather="mail"></i>
                    <span>Messages</span>
                </a>
                <a href="profile.php" class="mobile-nav-item">
                    <i data-feather="user"></i>
                    <span>Profile</span>
                </a>
            </div>
        </nav>
        
        <!-- Main Wrapper -->
        <div class="main-wrapper flex-1">
            <!-- Header -->
            <header class="top-header" role="banner">
                <div class="header-search">
                    <i data-feather="search" class="header-search-icon"></i>
                    <input type="search" placeholder="Search profile..." aria-label="Search" disabled class="opacity-50 cursor-not-allowed">
                </div>
                
                <div class="header-actions">
                    <button class="header-icon-btn" aria-label="Notifications" onclick="window.location.href='dashboard.php'">
                        <i data-feather="bell"></i>
                    </button>
                    <a href="profile.php" class="w-9 h-9 rounded-full overflow-hidden border-2 border-white shadow-sm flex-shrink-0" style="outline: 2px solid var(--primary-light);">
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile avatar" class="w-full h-full object-cover" data-fallback-name="<?php echo htmlspecialchars($name); ?>">
                    </a>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="page-main animate-fadeInUp">
                <div class="max-w-4xl mx-auto">
                    <!-- Profile Card -->
                    <div class="card p-6 md:p-8 mb-8 reveal">
                        <div class="flex flex-col md:flex-row items-center md:items-start gap-8">
                            <!-- Avatar container -->
                            <div class="relative w-28 h-28 md:w-32 md:h-32 rounded-full overflow-hidden border-4 border-white shadow-md flex-shrink-0" style="outline: 2px solid var(--primary-light);">
                                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile picture" class="w-full h-full object-cover" data-fallback-name="<?php echo htmlspecialchars($name); ?>">
                            </div>
                            
                            <!-- Profile details -->
                            <div class="flex-1 text-center md:text-left min-w-0">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                                    <div>
                                        <h1 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($name); ?></h1>
                                        <p class="text-xs text-gray-400 font-semibold mt-0.5"><?php echo htmlspecialchars(strtolower($user_data['first_name'] . '_' . $user_data['last_name'])); ?></p>
                                    </div>
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="openEditModal()" class="btn btn-primary btn-sm px-4">
                                            Edit Profile
                                        </button>
                                        <button onclick="window.location.href='settings.php'" class="w-8 h-8 rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-slate-50 transition border border-gray-100" title="Settings">
                                            <i data-feather="settings" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Stats row -->
                                <div class="flex justify-center md:justify-start gap-6 mb-6">
                                    <div class="profile-stat-card py-2 px-4 rounded-xl text-center">
                                        <p class="text-sm font-bold text-gray-900"><?php echo $posts_count; ?></p>
                                        <p class="text-[10px] text-gray-400 font-medium">Grid Posts</p>
                                    </div>
                                    <div class="profile-stat-card py-2 px-4 rounded-xl text-center">
                                        <p class="text-sm font-bold text-gray-900"><?php echo $connections_count; ?></p>
                                        <p class="text-[10px] text-gray-400 font-medium">Connections</p>
                                    </div>
                                    <div class="profile-stat-card py-2 px-4 rounded-xl text-center font-medium text-gray-400">
                                        <p class="text-sm font-bold text-gray-900">0</p>
                                        <p class="text-[10px] text-gray-400 font-medium">Projects</p>
                                    </div>
                                </div>
                                
                                <!-- Bio & Skills -->
                                <div class="text-xs text-gray-700 leading-relaxed border-t border-gray-50 pt-4">
                                    <p class="font-bold text-gray-800 mb-1">About Me</p>
                                    <p class="text-gray-500 italic mb-4"><?php echo !empty($bio) ? htmlspecialchars($bio) : 'No bio added yet.'; ?></p>
                                    
                                    <p class="font-bold text-gray-800 mb-2">Technical Skills</p>
                                    <div class="flex flex-wrap gap-1">
                                        <?php if (!empty($skills)): ?>
                                            <?php foreach ($skills as $skill): ?>
                                                <span class="badge badge-blue text-[10px] py-1 px-2.5"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-[11px] text-gray-400">No skills added yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs Grid/Saved -->
                    <div class="mb-6 flex justify-center border-b border-gray-200">
                        <button class="pb-3 px-6 border-b-2 border-blue-500 font-semibold text-blue-600 text-xs tracking-wider uppercase flex items-center gap-1.5 transition">
                            <i data-feather="grid" class="w-3.5 h-3.5"></i>
                            Posts
                        </button>
                    </div>

                    <!-- Image Gallery Grid -->
                    <div class="grid grid-cols-3 gap-1 md:gap-3 mb-8">
                        <?php foreach ($user_posts as $post): ?>
                            <?php if ($post['image_url']): ?>
                                <div class="post-grid-card aspect-square bg-slate-100 rounded-xl relative overflow-hidden group border border-slate-100 shadow-sm cursor-pointer reveal">
                                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="post-grid-img w-full h-full object-cover" alt="Post picture" data-fallback-name="Upload">
                                    <div class="absolute inset-0 bg-slate-900 bg-opacity-0 group-hover:bg-opacity-40 transition duration-300 flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        <div class="text-white flex items-center font-semibold text-xs">
                                            <i data-feather="heart" class="w-4 h-4 mr-1.5 fill-current"></i> Like
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if ($posts_count == 0): ?>
                            <div class="col-span-full empty-state py-12">
                                <div class="empty-state-icon"><i data-feather="camera"></i></div>
                                <div class="empty-state-title">No Photos Yet</div>
                                <div class="empty-state-desc">Your published photos and visual updates will appear here.</div>
                                <button onclick="window.location.href='posts.php'" class="btn btn-primary btn-sm mt-3">Go to Feed</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="fixed z-50 inset-0 overflow-y-auto hidden animate-fade-in">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-40 backdrop-blur-sm transition-opacity" onclick="closeEditModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">Edit Profile</h3>
                    <button onclick="closeEditModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <!-- Avatar section -->
                    <div class="flex flex-col items-center py-2">
                        <div class="relative">
                            <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-white shadow-md" style="outline: 2px solid var(--primary-light);">
                                <img id="avatarPreview" src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile preview" class="w-full h-full object-cover" data-fallback-name="<?php echo htmlspecialchars($name); ?>">
                            </div>
                            <label for="avatar" class="avatar-upload-overlay">
                                <i data-feather="camera" class="w-4 h-4 text-white"></i>
                            </label>
                            <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(event)">
                        </div>
                        <span class="text-[10px] text-gray-400 mt-2">Allowed: JPG, PNG, GIF, WebP (Max 5MB)</span>
                    </div>

                    <!-- Names -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" class="form-input" required>
                        </div>
                        <div>
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" class="form-input" required>
                        </div>
                    </div>

                    <!-- Bio -->
                    <div>
                        <label for="bio" class="form-label">Bio</label>
                        <textarea id="bio" name="bio" rows="3" class="form-input" placeholder="Tell the community about yourself..."><?php echo htmlspecialchars($bio); ?></textarea>
                    </div>

                    <!-- Skills -->
                    <div>
                        <label for="skills" class="form-label">Skills (separated by commas)</label>
                        <input type="text" id="skills" name="skills" value="<?php echo htmlspecialchars(implode(', ', $skills)); ?>" class="form-input" placeholder="e.g. Python, SQL, Product Design">
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-4 border-t border-gray-100">
                        <button type="submit" class="btn btn-primary flex-1">
                            Save Changes
                        </button>
                        <button type="button" onclick="closeEditModal()" class="btn btn-outline">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/global.js"></script>
    <script>
        feather.replace();

        function openEditModal() {
            document.getElementById('editProfileModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editProfileModal').classList.add('hidden');
        }

        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Show Flash Messages as Toasts
        document.addEventListener('DOMContentLoaded', () => {
            <?php if (!empty($message)): ?>
                showToast("<?php echo htmlspecialchars($message); ?>", "success");
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                showToast("<?php echo htmlspecialchars($error); ?>", "error");
            <?php endif; ?>
        });
    </script>
</body>
</html>