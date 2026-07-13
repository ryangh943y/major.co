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

// Get current user data
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar_url FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Get all projects (user's own + public ones)
$stmt = $pdo->prepare("SELECT p.id, p.user_id, p.title, p.description, p.start_date, p.due_date, p.status, p.required_skills, p.visibility, p.image_url, p.created_at,
                             u.first_name, u.last_name, u.avatar_url,
                             pm.status as member_status
                      FROM projects p
                      JOIN users u ON p.user_id = u.id
                      LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ?
                      WHERE p.user_id = ? OR p.visibility = 'public'
                      ORDER BY CASE WHEN p.user_id = ? THEN 0 ELSE 1 END, p.created_at DESC");
$stmt->execute([$user_id, $user_id, $user_id]);

$projects = [];
while ($row = $stmt->fetch()) {
    $skills = !empty($row['required_skills']) ? json_decode($row['required_skills'], true) : [];
    
    $projects[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'start_date' => $row['start_date'],
        'due_date' => $row['due_date'],
        'status' => $row['status'],
        'required_skills' => $skills,
        'visibility' => $row['visibility'],
        'image_url' => $row['image_url'] ?? 'http://static.photos/projects/1',
        'created_at' => $row['created_at'],
        'owner_name' => ($row['first_name'] ?? 'Unknown') . ' ' . ($row['last_name'] ?? 'User'),
        'owner_avatar' => $row['avatar_url'] ?? 'http://static.photos/people/200x200/1',
        'is_owner' => $row['user_id'] == $user_id,
        'member_status' => $row['member_status']
    ];
}

$name = $current_user['first_name'] . ' ' . $current_user['last_name'];
$avatar_url = $current_user['avatar_url'] ?? 'http://static.photos/people/200x200/10';

// Helper function to get status color
function getStatusColor($status) {
    $colors = [
        'planning' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'clock'],
        'in-progress' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'play-circle'],
        'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-circle'],
        'on-hold' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'pause-circle']
    ];
    return $colors[$status] ?? $colors['planning'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects | ProjectCrew</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="assets/css/global.css">
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
                    <input id="projectSearch" type="search" placeholder="Search projects by title, description, or skills..." aria-label="Search" onkeyup="searchProjects()">
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
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="page-title">Projects</h1>
                        <p class="page-subtitle">Discover and manage workspace collaborations</p>
                    </div>
                    <div>
                        <button onclick="openModal()" class="btn btn-primary">
                            <i data-feather="plus" class="w-4 h-4"></i>
                            New Project
                        </button>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-8 flex space-x-6 border-b border-gray-200">
                    <button onclick="filterProjects('all')" class="filter-tab pb-3 px-1 border-b-2 border-blue-500 font-semibold text-blue-600 text-sm transition">
                        All Projects <span class="ml-1.5 badge badge-blue text-[10px]"><?php echo count($projects); ?></span>
                    </button>
                    <button onclick="filterProjects('my')" class="filter-tab pb-3 px-1 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 text-sm transition">
                        My Projects <span class="ml-1.5 badge badge-gray text-[10px]"><?php echo count(array_filter($projects, fn($p) => $p['is_owner'])); ?></span>
                    </button>
                </div>

                <!-- Pending Project Requests Section -->
                <section id="projectRequestsSection" class="mb-8 hidden reveal">
                    <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                        Project Join Requests
                    </h2>
                    <div id="projectRequestsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Populated via JS -->
                    </div>
                </section>
                
                <!-- Projects Grid -->
                <div id="projectsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($projects)): ?>
                        <div class="col-span-full empty-state">
                            <div class="empty-state-icon"><i data-feather="folder-open"></i></div>
                            <div class="empty-state-title">No Projects Found</div>
                            <div class="empty-state-desc">Get the ball rolling by starting your own project.</div>
                            <button onclick="openModal()" class="btn btn-primary mt-2">Create Your First Project</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $index => $project): ?>
                            <?php $statusColor = getStatusColor($project['status']); ?>
                            <div class="card reveal" style="animation-delay: <?php echo $index * 50; ?>ms;" data-owner="<?php echo $project['is_owner'] ? 'true' : 'false'; ?>">
                                <div class="relative h-40 bg-gradient-to-r from-blue-400 to-indigo-500 overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($project['image_url']); ?>" alt="Project illustration" class="w-full h-full object-cover opacity-60 transition duration-500 hover:scale-105" data-fallback-name="Project">
                                </div>
                                
                                <div class="p-6">
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <h3 class="text-base font-bold text-gray-900 truncate flex-1"><?php echo htmlspecialchars($project['title']); ?></h3>
                                        <?php if ($project['is_owner']): ?>
                                            <div class="flex items-center gap-1">
                                                <button onclick="openEditProjectModal(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars(addslashes($project['title'])); ?>', '<?php echo htmlspecialchars(addslashes($project['description'])); ?>', '<?php echo htmlspecialchars($project['status']); ?>')" class="text-gray-400 hover:text-blue-500 transition p-1" title="Edit Project">
                                                    <i data-feather="edit-2" class="w-4 h-4"></i>
                                                </button>
                                                <button onclick="deleteProject(<?php echo $project['id']; ?>)" class="text-gray-400 hover:text-red-500 transition p-1" title="Delete Project">
                                                    <i data-feather="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="text-xs text-gray-500 line-clamp-2 mb-4 h-8"><?php echo htmlspecialchars($project['description']); ?></p>
                                    
                                    <!-- Required Skills -->
                                    <div class="flex flex-wrap gap-1 mb-4 h-6 overflow-hidden">
                                        <?php if (!empty($project['required_skills'])): ?>
                                            <?php foreach (array_slice($project['required_skills'], 0, 3) as $skill): ?>
                                                <span class="badge badge-blue text-[10px]"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">No specific skills listed</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Status -->
                                    <div class="flex items-center justify-between mb-4 pt-2">
                                        <span class="badge <?php 
                                            $badgeClasses = [
                                                'planning' => 'badge-blue',
                                                'in-progress' => 'badge-orange',
                                                'completed' => 'badge-green',
                                                'on-hold' => 'badge-red'
                                            ];
                                            echo $badgeClasses[$project['status']] ?? 'badge-gray';
                                        ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $project['status'])); ?>
                                        </span>
                                        <?php if ($project['due_date']): ?>
                                            <span class="text-[11px] text-gray-400 font-medium">Due: <?php echo date('M d, Y', strtotime($project['due_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Owner Info -->
                                    <div class="flex items-center justify-between pt-4 border-t border-gray-100 mt-4">
                                        <div class="flex items-center min-w-0 mr-2">
                                            <img src="<?php echo htmlspecialchars($project['owner_avatar']); ?>" alt="Owner avatar" class="w-7 h-7 rounded-full mr-2 object-cover border border-gray-100" data-fallback-name="<?php echo htmlspecialchars($project['owner_name']); ?>">
                                            <span class="text-xs text-gray-600 truncate font-medium"><?php echo htmlspecialchars($project['owner_name']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                            <?php if ($project['is_owner']): ?>
                                                <a href="project_workspace.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm px-2.5"> Workspace </a>
                                                <button onclick="openManageMembersModal(<?php echo $project['id']; ?>)" class="btn btn-outline btn-sm px-2"> Manage </button>
                                            <?php elseif ($project['member_status'] === 'pending'): ?>
                                                <span class="badge badge-orange py-1.5 px-3"> Requested </span>
                                            <?php elseif ($project['member_status'] === 'accepted'): ?>
                                                <a href="project_workspace.php?id=<?php echo $project['id']; ?>" class="btn btn-success btn-sm"> Workspace </a>
                                            <?php else: ?>
                                                <button onclick="requestJoin(<?php echo $project['id']; ?>)" class="btn btn-primary btn-sm"> Join </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- New Project Modal -->
    <div id="projectModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-40 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Create New Project</h3>
                    <button onclick="closeModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form id="projectForm" class="space-y-4" onsubmit="submitProject(event)">
                    <div>
                        <label for="project-title" class="form-label">Project Title</label>
                        <input type="text" id="project-title" name="title" class="form-input" placeholder="e.g. E-commerce Platform" required>
                    </div>
                    
                    <div>
                        <label for="project-description" class="form-label">Description</label>
                        <textarea id="project-description" name="description" rows="3" class="form-input" placeholder="Brief description of your project" required></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start-date" class="form-label">Start Date</label>
                            <input type="date" id="start-date" name="start_date" class="form-input">
                        </div>
                        <div>
                            <label for="due-date" class="form-label">Due Date</label>
                            <input type="date" id="due-date" name="due_date" class="form-input">
                        </div>
                    </div>
                    
                    <div>
                        <label for="required-skills" class="form-label">Required Skills</label>
                        <textarea id="required-skills" name="required_skills" rows="2" class="form-input" placeholder="Separate skills with commas (e.g., React, Node.js, Python)"></textarea>
                    </div>
                    
                    <div>
                        <label for="project-visibility" class="form-label">Project Visibility</label>
                        <select id="project-visibility" name="visibility" class="form-input">
                            <option value="public">Public (Anyone can view and join)</option>
                            <option value="private">Private (Only you can view)</option>
                        </select>
                    </div>

                    <div id="formMessage" class="text-sm text-red-600 hidden"></div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="btn btn-primary flex-1">
                            Create Project
                        </button>
                        <button type="button" onclick="closeModal()" class="btn btn-outline">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Members Modal -->
    <div id="manageMembersModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-40 backdrop-blur-sm transition-opacity" onclick="closeManageMembersModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Manage Project Members</h3>
                    <button onclick="closeManageMembersModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-4">
                    <nav class="-mb-px flex space-x-6">
                        <button onclick="switchMemberTab('members')" id="tab-members" class="border-blue-500 text-blue-600 whitespace-nowrap pb-4 px-1 border-b-2 font-semibold text-sm transition">
                            Current Members
                        </button>
                        <button onclick="switchMemberTab('requests')" id="tab-requests" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition">
                            Pending Requests
                        </button>
                    </nav>
                </div>

                <!-- Members Content -->
                <div id="content-members" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    <div class="text-center text-gray-500 py-4">Loading members...</div>
                </div>

                <!-- Requests Content -->
                <div id="content-requests" class="space-y-3 max-h-96 overflow-y-auto hidden pr-1">
                    <div class="text-center text-gray-500 py-4">Loading requests...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div id="editProjectModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="absolute w-full h-full bg-gray-900 opacity-40 backdrop-blur-sm" onclick="closeEditProjectModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Edit Project Details</h3>
                    <button onclick="closeEditProjectModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form id="editProjectForm" class="space-y-4" onsubmit="updateProjectDetails(event)">
                    <input type="hidden" id="editProjectId">
                    <div>
                        <label class="form-label" for="editProjectTitle">Project Title</label>
                        <input type="text" id="editProjectTitle" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label" for="editProjectDesc">Description</label>
                        <textarea id="editProjectDesc" rows="3" class="form-input" required></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="editProjectStatus">Project Status</label>
                        <select id="editProjectStatus" class="form-input">
                            <option value="planning">Planning</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on-hold">On Hold</option>
                        </select>
                    </div>
                    
                    <div class="flex gap-3 pt-4 border-t border-gray-100">
                        <button type="submit" class="btn btn-primary flex-1">Save Changes</button>
                        <button type="button" onclick="closeEditProjectModal()" class="btn btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rate Member Modal -->
    <div id="rateMemberModal" class="fixed z-[60] inset-0 overflow-y-auto hidden">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-40 backdrop-blur-sm transition-opacity" onclick="closeRateModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Rate Member</h3>
                    <button onclick="closeRateModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form id="rateForm" onsubmit="submitRating(event)" class="space-y-4">
                    <input type="hidden" id="rateeId">
                    
                    <!-- Star Rating -->
                    <div class="flex flex-col items-center">
                        <label class="form-label mb-2">Performance Rating</label>
                        <div class="flex space-x-2" id="starContainer">
                            <!-- 5 Stars -->
                            <button type="button" class="star-btn text-gray-300 hover:text-yellow-400 focus:outline-none transition" data-value="1">
                                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </button>
                            <button type="button" class="star-btn text-gray-300 hover:text-yellow-400 focus:outline-none transition" data-value="2">
                                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </button>
                            <button type="button" class="star-btn text-gray-300 hover:text-yellow-400 focus:outline-none transition" data-value="3">
                                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </button>
                            <button type="button" class="star-btn text-gray-300 hover:text-yellow-400 focus:outline-none transition" data-value="4">
                                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </button>
                            <button type="button" class="star-btn text-gray-300 hover:text-yellow-400 focus:outline-none transition" data-value="5">
                                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </button>
                        </div>
                        <input type="hidden" id="selectedRating" required>
                    </div>

                    <div>
                        <label for="reviewText" class="form-label">Review (Optional)</label>
                        <textarea id="reviewText" rows="3" class="form-input" placeholder="Write a brief review about their contributions..."></textarea>
                    </div>
                    
                    <div class="pt-2 flex gap-3">
                        <button type="submit" class="btn btn-primary flex-1">
                            Submit Rating
                        </button>
                        <button type="button" onclick="closeRateModal()" class="btn btn-outline">
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

        // Redirect check to show create modal instantly if query param new=1 is present
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('new') === '1') {
                openModal();
            }
        });

        function openModal() {
            document.getElementById('projectModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('projectModal').classList.add('hidden');
            document.getElementById('projectForm').reset();
            document.getElementById('formMessage').classList.add('hidden');
        }

        function submitProject(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('projectForm'));
            
            fetch('backend/create_project.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Project created successfully!', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 800);
                } else {
                    document.getElementById('formMessage').textContent = data.error;
                    document.getElementById('formMessage').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('formMessage').textContent = 'An error occurred. Please try again.';
                document.getElementById('formMessage').classList.remove('hidden');
            });
        }

        function deleteProject(projectId) {
            if (confirm('Are you sure you want to delete this project?')) {
                const formData = new FormData();
                formData.append('project_id', projectId);
                
                fetch('backend/delete_project.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Project deleted successfully!', 'info');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast('Error: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred. Please try again.', 'error');
                });
            }
        }

        function filterProjects(filter) {
            const container = document.getElementById('projectsContainer');
            const cards = container.querySelectorAll('[data-owner]');
            
            if (filter === 'my') {
                cards.forEach(card => {
                    if (card.dataset.owner === 'true') {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            } else {
                cards.forEach(card => card.style.display = '');
            }

            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-600');
            });
            event.target.closest('.filter-tab').classList.add('border-blue-500', 'text-blue-600');
            event.target.closest('.filter-tab').classList.remove('border-transparent', 'text-gray-600');
        }

        function searchProjects() {
            const searchInput = document.getElementById('projectSearch').value.toLowerCase().trim();
            const container = document.getElementById('projectsContainer');
            const cards = container.querySelectorAll('[data-owner]');

            if (searchInput === '') {
                // Show all projects if search is empty
                cards.forEach(card => card.style.display = '');
                return;
            }

            cards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('p').textContent.toLowerCase();
                const skillElements = card.querySelectorAll('[class*="bg-blue-100"], [class*="bg-purple-100"], [class*="bg-green-100"], [class*="bg-pink-100"], [class*="bg-indigo-100"], [class*="bg-cyan-100"], [class*="bg-teal-100"]');
                
                let skillsText = '';
                skillElements.forEach(el => {
                    skillsText += el.textContent.toLowerCase() + ' ';
                });

                // Show card if search term matches title, description, or skills
                if (title.includes(searchInput) || description.includes(searchInput) || skillsText.includes(searchInput)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        async function requestJoin(projectId) {
            try {
                const res = await fetch('backend/request_project_join.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({project_id: projectId})
                });
                const data = await res.json();
                if (data.success) {
                    alert('Request sent!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                alert('Failed to send join request');
            }
        }

        async function loadProjectRequests() {
            try {
                const res = await fetch('backend/get_project_requests.php');
                const data = await res.json();
                if(!res.ok || !data.success) return;

                const section = document.getElementById('projectRequestsSection');
                const container = document.getElementById('projectRequestsContainer');
                
                if (data.requests.length === 0) {
                    section.classList.add('hidden');
                    return;
                }
                
                section.classList.remove('hidden');
                container.innerHTML = '';

                data.requests.forEach(req => {
                    const el = document.createElement('div');
                    el.className = 'bg-white rounded-xl shadow-sm overflow-hidden p-6 border-l-4 border-blue-500';
                    el.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <img class="h-10 w-10 rounded-full" src="${req.avatar_url}" alt="User">
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-sm font-medium text-gray-900">${req.user_name}</h3>
                                <p class="text-xs text-gray-500 mb-2">Requested to join <b>${req.project_title}</b></p>
                                <div class="flex space-x-2 mt-3">
                                    <button onclick="handleJoinRequest(${req.request_id}, 'accept')" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-1.5 rounded-lg text-xs font-medium transition">Accept</button>
                                    <button onclick="handleJoinRequest(${req.request_id}, 'reject')" class="flex-1 bg-red-100 hover:bg-red-200 text-red-700 py-1.5 rounded-lg text-xs font-medium transition">Decline</button>
                                </div>
                            </div>
                        </div>
                    `;
                    container.appendChild(el);
                });
            } catch (err) {
                console.error('Error loading project requests', err);
            }
        }

        async function handleJoinRequest(requestId, action) {
            try {
                const res = await fetch('backend/handle_project_join.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({request_id: requestId, action: action})
                });
                const data = await res.json();
                if(data.success) {
                    loadProjectRequests();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                console.error(err);
            }
        }

        let currentManageProjectId = null;

        function openManageMembersModal(projectId) {
            currentManageProjectId = projectId;
            document.getElementById('manageMembersModal').classList.remove('hidden');
            switchMemberTab('members');
            loadManageMembers(projectId);
            loadManageRequests(projectId);
        }

        function closeManageMembersModal() {
            document.getElementById('manageMembersModal').classList.add('hidden');
            currentManageProjectId = null;
        }

        function switchMemberTab(tab) {
            document.getElementById('tab-members').classList.replace(tab === 'members' ? 'border-transparent' : 'border-blue-500', tab === 'members' ? 'border-blue-500' : 'border-transparent');
            document.getElementById('tab-members').classList.replace(tab === 'members' ? 'text-gray-500' : 'text-blue-600', tab === 'members' ? 'text-blue-600' : 'text-gray-500');
            
            document.getElementById('tab-requests').classList.replace(tab === 'requests' ? 'border-transparent' : 'border-blue-500', tab === 'requests' ? 'border-blue-500' : 'border-transparent');
            document.getElementById('tab-requests').classList.replace(tab === 'requests' ? 'text-gray-500' : 'text-blue-600', tab === 'requests' ? 'text-blue-600' : 'text-gray-500');

            document.getElementById('content-members').classList.toggle('hidden', tab !== 'members');
            document.getElementById('content-requests').classList.toggle('hidden', tab !== 'requests');
        }

        async function loadManageMembers(projectId) {
            try {
                const res = await fetch(`backend/get_project_members.php?project_id=${projectId}`);
                const data = await res.json();
                const container = document.getElementById('content-members');
                if (!data.success) {
                    container.innerHTML = `<div class="text-red-500 py-2">Error loading members</div>`;
                    return;
                }
                
                if (data.members.length === 0) {
                    container.innerHTML = `<div class="text-gray-500 py-4 text-center">No active members in this project.</div>`;
                    return;
                }

                container.innerHTML = data.members.map(m => `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <img src="${m.avatar_url || 'http://static.photos/people/200x200/10'}" class="w-8 h-8 rounded-full mr-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">${m.first_name} ${m.last_name}</p>
                                <p class="text-xs text-gray-500">${m.role}</p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="openRateModal(${m.user_id})" class="text-blue-600 hover:text-blue-800 text-sm font-medium bg-blue-50 px-3 py-1.5 rounded transition">Rate</button>
                            <button onclick="removeMember(${m.id})" class="text-red-600 hover:text-red-800 text-sm font-medium bg-red-50 px-3 py-1.5 rounded transition">Remove</button>
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                console.error(err);
            }
        }

        async function loadManageRequests(projectId) {
            try {
                const res = await fetch(`backend/get_project_specific_requests.php?project_id=${projectId}`);
                const data = await res.json();
                const container = document.getElementById('content-requests');
                if (!data.success) {
                    container.innerHTML = `<div class="text-red-500 py-2">Error loading requests</div>`;
                    return;
                }
                
                if (data.requests.length === 0) {
                    container.innerHTML = `<div class="text-gray-500 py-4 text-center">No pending requests.</div>`;
                    return;
                }

                container.innerHTML = data.requests.map(req => `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="flex items-center">
                            <img src="${req.avatar_url || 'http://static.photos/people/200x200/10'}" class="w-8 h-8 rounded-full mr-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">${req.user_name}</p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="handleSpecificJoinRequest(${req.request_id}, 'accept')" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition">Accept</button>
                            <button onclick="handleSpecificJoinRequest(${req.request_id}, 'reject')" class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">Reject</button>
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                console.error(err);
            }
        }

        async function handleSpecificJoinRequest(requestId, action) {
            try {
                const res = await fetch('backend/handle_project_join.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({request_id: requestId, action: action})
                });
                const data = await res.json();
                if(data.success) {
                    showToast(action === 'accept' ? 'User accepted into project!' : 'Request rejected.', 'info');
                    loadManageMembers(currentManageProjectId);
                    loadManageRequests(currentManageProjectId);
                    loadProjectRequests();
                } else {
                    showToast(data.error || 'Failed to handle request', 'error');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function removeMember(memberId) {
            if(!confirm("Are you sure you want to remove this member from the project?")) return;
            try {
                const res = await fetch('backend/remove_project_member.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({member_id: memberId})
                });
                const data = await res.json();
                if(data.success) {
                    showToast('Member removed from project.', 'info');
                    loadManageMembers(currentManageProjectId);
                } else {
                    showToast(data.error || 'Failed to remove member', 'error');
                }
            } catch (err) {
                console.error(err);
            }
        }

        document.addEventListener('DOMContentLoaded', loadProjectRequests);

        // Rating Modal Logic
        let currentRating = 0;

        document.querySelectorAll('.star-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const val = parseInt(this.getAttribute('data-value'));
                currentRating = val;
                document.getElementById('selectedRating').value = val;
                
                // update UI
                document.querySelectorAll('.star-btn').forEach(star => {
                    if (parseInt(star.getAttribute('data-value')) <= val) {
                        star.classList.replace('text-gray-300', 'text-yellow-400');
                    } else {
                        star.classList.replace('text-yellow-400', 'text-gray-300');
                    }
                });
            });
        });

        function openRateModal(rateeId) {
            document.getElementById('rateeId').value = rateeId;
            document.getElementById('selectedRating').value = '';
            document.getElementById('reviewText').value = '';
            currentRating = 0;
            document.querySelectorAll('.star-btn').forEach(star => {
                star.classList.replace('text-yellow-400', 'text-gray-300');
            });
            document.getElementById('rateMemberModal').classList.remove('hidden');
        }

        function closeRateModal() {
            document.getElementById('rateMemberModal').classList.add('hidden');
        }

        async function submitRating(e) {
            e.preventDefault();
            const rating = document.getElementById('selectedRating').value;
            const review = document.getElementById('reviewText').value;
            const rateeId = document.getElementById('rateeId').value;

            if (!rating) {
                showToast("Please select a star rating", 'warning');
                return;
            }

            try {
                const res = await fetch('backend/rate_user.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        project_id: currentManageProjectId,
                        ratee_id: rateeId,
                        rating: parseInt(rating),
                        review: review
                    })
                });
                const data = await res.json();
                if(data.success) {
                    showToast('Rating submitted successfully!', 'success');
                    closeRateModal();
                } else {
                    showToast(data.error || 'Failed to submit rating', 'error');
                }
            } catch(err) {
                console.error(err);
                showToast("Failed to submit rating.", 'error');
            }
        }

        // Edit/Delete Project Helpers (Necessary Feature #4)
        function openEditProjectModal(id, title, desc, status) {
            document.getElementById('editProjectId').value = id;
            document.getElementById('editProjectTitle').value = title;
            document.getElementById('editProjectDesc').value = desc;
            document.getElementById('editProjectStatus').value = status;
            document.getElementById('editProjectModal').classList.remove('hidden');
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').classList.add('hidden');
            document.getElementById('editProjectForm').reset();
        }

        async function updateProjectDetails(e) {
            e.preventDefault();
            const projectId = document.getElementById('editProjectId').value;
            const title = document.getElementById('editProjectTitle').value.trim();
            const description = document.getElementById('editProjectDesc').value.trim();
            const status = document.getElementById('editProjectStatus').value;

            try {
                const res = await fetch('backend/update_project.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ project_id: projectId, title, description, status })
                });
                const data = await res.json();
                if (data.success) {
                    closeEditProjectModal();
                    showToast('Project details updated successfully.', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to update project.', 'error');
                }
            } catch (err) {
                showToast('Error updating project details.', 'error');
            }
        }

        async function deleteProject(projectId) {
            if (!confirm("Caution: This will permanently delete the project, all team members, task milestones, and uploaded documents. Are you absolutely sure?")) return;
            try {
                const res = await fetch('backend/delete_project.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ project_id: projectId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Project deleted successfully.', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to delete project.', 'error');
                }
            } catch (err) {
                showToast('Error deleting project.', 'error');
            }
        }

        // Edit/Delete Project Helpers (Necessary Feature #4)
        function openEditProjectModal(id, title, desc, status) {
            document.getElementById('editProjectId').value = id;
            document.getElementById('editProjectTitle').value = title;
            document.getElementById('editProjectDesc').value = desc;
            document.getElementById('editProjectStatus').value = status;
            document.getElementById('editProjectModal').classList.remove('hidden');
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').classList.add('hidden');
            document.getElementById('editProjectForm').reset();
        }

        async function updateProjectDetails(e) {
            e.preventDefault();
            const projectId = document.getElementById('editProjectId').value;
            const title = document.getElementById('editProjectTitle').value.trim();
            const description = document.getElementById('editProjectDesc').value.trim();
            const status = document.getElementById('editProjectStatus').value;

            try {
                const res = await fetch('backend/update_project.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ project_id: projectId, title, description, status })
                });
                const data = await res.json();
                if (data.success) {
                    closeEditProjectModal();
                    showToast('Project details updated successfully.', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to update project.', 'error');
                }
            } catch (err) {
                showToast('Error updating project details.', 'error');
            }
        }

        async function deleteProject(projectId) {
            if (!confirm("Caution: This will permanently delete the project, all team members, task milestones, and uploaded documents. Are you absolutely sure?")) return;
            try {
                const res = await fetch('backend/delete_project.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ project_id: projectId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Project deleted successfully.', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to delete project.', 'error');
                }
            } catch (err) {
                showToast('Error deleting project.', 'error');
            }
        }
    </script>
</body>
</html>
