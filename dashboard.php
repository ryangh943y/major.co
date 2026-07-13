<?php
// At the top of dashboard.php
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
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar_url, skills, bio FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    // User not found, redirect to login
    session_destroy();
    header('Location: login.html');
    exit();
}

$name = $user_data['first_name'] . ' ' . $user_data['last_name'];
$role = 'Team Member'; // Default role since column doesn't exist
$avatar_url = $user_data['avatar_url'] ?? 'http://static.photos/people/200x200/10';
$skills = !empty($user_data['skills']) ? json_decode($user_data['skills'], true) : [];

// Get connections count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM connections 
    WHERE (user_id = ? OR partner_id = ?) 
    AND status = 'connected'
");
$stmt->execute([$user_id, $user_id]);
$connections_data = $stmt->fetch();
$connections_count = $connections_data['total'] ?? 0;

// Get active projects count (in-progress or planning)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM projects 
    WHERE user_id = ? AND status IN ('planning', 'in-progress')
");
$stmt->execute([$user_id]);
$projects_data = $stmt->fetch();
$active_projects = $projects_data['total'] ?? 0;

// Get total projects count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects WHERE user_id = ?");
$stmt->execute([$user_id]);
$all_projects_data = $stmt->fetch();
$total_projects = $all_projects_data['total'] ?? 0;

// Messages count
$messages_count = 0;
try {
    if (isset($user_id)) {
        $msg_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $msg_stmt->execute([$user_id]);
        $messages_count = $msg_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Table might not exist or other DB error, keep count as 0
    error_log("Error fetching message count: " . $e->getMessage());
}

// Skills count
$skills_count = count($skills);
?>
<!DOCTYPE html>
<?php
/**
 * Renders a single skill badge.
 *
 * @param string $skill The skill to display.
 * @param string $color_classes The Tailwind CSS color classes for the badge.
 * @return void
 */
function render_skill_badge(string $skill, string $color_classes = 'bg-blue-100 text-blue-800'): void {
    // Trim whitespace from the skill.
    $trimmed_skill = trim($skill);

    // Don't render a badge for empty skills.
    if (empty($trimmed_skill)) {
        return;
    }

    // Sanitize the output to prevent XSS attacks.
    $sanitized_skill = htmlspecialchars($trimmed_skill, ENT_QUOTES, 'UTF-8');
    $sanitized_colors = htmlspecialchars($color_classes, ENT_QUOTES, 'UTF-8');

    // Print the HTML for the badge.
    printf(
        '<span class="px-2 py-1 text-xs rounded-full %s">%s</span>',
        $sanitized_colors,
        $sanitized_skill
    );
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ProjectCrew</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        /* Specific adjustments for dashboard if any */
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
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
                    <span class="nav-badge">3 new</span>
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
                    <?php if ($messages_count > 0): ?>
                        <span class="nav-badge"><?php echo $messages_count; ?></span>
                    <?php endif; ?>
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
                    <?php if ($messages_count > 0): ?>
                        <span class="mobile-nav-badge"><?php echo $messages_count; ?></span>
                    <?php endif; ?>
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
                    <input type="search" placeholder="Search partners, skills, or projects..." aria-label="Search">
                </div>
                
                <div class="header-actions">
                    <!-- Notifications Dropdown -->
                    <div class="relative">
                        <button id="notificationBtn" class="header-icon-btn" aria-label="Notifications">
                            <i data-feather="bell"></i>
                            <span id="notificationBadge" class="hidden header-badge animate-pulse"></span>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div id="notificationDropdown" class="hidden notif-dropdown">
                            <div class="notif-header">
                                <span class="notif-header-title">Notifications</span>
                                <button onclick="markAllNotificationsRead()" class="notif-mark-read">Mark all read</button>
                            </div>
                            <div id="notificationList" class="notif-list">
                                <div class="px-4 py-6 text-sm text-gray-500 text-center">Loading...</div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="profile.php" class="w-9 h-9 rounded-full overflow-hidden border-2 border-white shadow-sm flex-shrink-0" style="outline: 2px solid var(--primary-light);">
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile avatar" class="w-full h-full object-cover" data-fallback-name="<?php echo htmlspecialchars($name); ?>">
                    </a>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="page-main animate-fadeInUp">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 class="page-title" id="welcome-greeting">Welcome back, <?php echo htmlspecialchars($user_data['first_name']); ?>! 👋</h1>
                        <p class="page-subtitle">Here is a quick look at your dashboard updates.</p>
                    </div>
                    <div>
                        <a href="projects.php?new=1" class="btn btn-primary">
                            <i data-feather="plus" class="w-4 h-4"></i>
                            Create New Project
                        </a>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div onclick="openManageConnectionsModal()" class="stat-card reveal">
                        <div class="stat-icon blue">
                            <i data-feather="users"></i>
                        </div>
                        <div>
                            <p class="stat-label">Connections</p>
                            <p class="stat-value" id="val-connections"><?php echo $connections_count; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card reveal delay-100" onclick="window.location.href='projects.php'">
                        <div class="stat-icon orange">
                            <i data-feather="folder"></i>
                        </div>
                        <div>
                            <p class="stat-label">Active Projects</p>
                            <p class="stat-value" id="val-projects"><?php echo $active_projects; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card reveal delay-150" onclick="window.location.href='messages.php'">
                        <div class="stat-icon green">
                            <i data-feather="mail"></i>
                        </div>
                        <div>
                            <p class="stat-label">New Messages</p>
                            <p class="stat-value" id="val-messages"><?php echo $messages_count; ?></p>
                        </div>
                    </div>
                    
                    <div onclick="openManageSkillsModal()" class="stat-card reveal delay-200">
                        <div class="stat-icon purple">
                            <i data-feather="star"></i>
                        </div>
                        <div>
                            <p class="stat-label">Your Skills</p>
                            <p class="stat-value" id="val-skills"><?php echo $skills_count; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Analytics Cards -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 reveal">
                    <!-- Project Status Chart -->
                    <div class="card p-6 shadow-sm border border-slate-100">
                        <div class="pb-3 border-b border-slate-100 mb-4">
                            <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                                <i data-feather="bar-chart-2" class="w-4 h-4 text-blue-500"></i>
                                Projects Statistics
                            </h3>
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="projectsChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Tasks Distribution Chart -->
                    <div class="card p-6 shadow-sm border border-slate-100">
                        <div class="pb-3 border-b border-slate-100 mb-4">
                            <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                                <i data-feather="activity" class="w-4 h-4 text-indigo-500"></i>
                                Task Progress Indexes
                            </h3>
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="tasksChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Pending Connections Section -->
                <section id="pendingConnectionsSection" class="mb-8 hidden reveal">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Pending Requests
                        </h2>
                    </div>
                    <div id="pendingConnectionsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Pending connections will be loaded here -->
                    </div>
                </section>
                
                <!-- Suggested Partners Section -->
                <section class="mb-8 reveal">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Suggested Partners</h2>
                        <button onclick="refreshSuggestions()" class="btn btn-outline btn-sm">
                            <i data-feather="refresh-cw" class="w-3.5 h-3.5"></i>
                            Refresh
                        </button>
                    </div>
                    
                    <!-- Suggestions Grid -->
                    <div id="suggestionsContainer" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Skeleton Loader -->
                        <div class="card p-6 flex flex-col items-center">
                            <div class="skeleton skeleton-circle w-16 h-16 mb-4"></div>
                            <div class="skeleton skeleton-title w-32 h-5 mb-2"></div>
                            <div class="skeleton skeleton-text w-full h-4 mb-1"></div>
                            <div class="skeleton skeleton-text w-2/3 h-4 mb-4"></div>
                            <div class="skeleton skeleton-text w-full h-8 rounded-lg"></div>
                        </div>
                        <div class="card p-6 flex flex-col items-center">
                            <div class="skeleton skeleton-circle w-16 h-16 mb-4"></div>
                            <div class="skeleton skeleton-title w-32 h-5 mb-2"></div>
                            <div class="skeleton skeleton-text w-full h-4 mb-1"></div>
                            <div class="skeleton skeleton-text w-2/3 h-4 mb-4"></div>
                            <div class="skeleton skeleton-text w-full h-8 rounded-lg"></div>
                        </div>
                        <div class="card p-6 flex flex-col items-center">
                            <div class="skeleton skeleton-circle w-16 h-16 mb-4"></div>
                            <div class="skeleton skeleton-title w-32 h-5 mb-2"></div>
                            <div class="skeleton skeleton-text w-full h-4 mb-1"></div>
                            <div class="skeleton skeleton-text w-2/3 h-4 mb-4"></div>
                            <div class="skeleton skeleton-text w-full h-8 rounded-lg"></div>
                        </div>
                    </div>
                </section>
                
                <!-- Projects Section -->
                <section class="reveal delay-100">
                    <div class="card overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-800">Your Projects</h2>
                            <a href="projects.php" class="text-sm font-medium text-blue-500 hover:text-blue-700">View All</a>
                        </div>
                        
                        <div id="dashboardProjects" class="divide-y divide-gray-100">
                            <!-- Skeleton project loader -->
                            <div class="p-6 flex items-start">
                                <div class="skeleton w-12 h-12 rounded-lg mr-4"></div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="skeleton skeleton-title w-48 h-5"></div>
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </div>
                                    <div class="skeleton skeleton-text w-full h-4"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 bg-gray-50 text-center border-t border-gray-100">
                            <a href="projects.php" class="text-sm font-medium text-blue-500 hover:text-blue-700 flex items-center justify-center gap-1">
                                <i data-feather="folder-plus" class="w-4 h-4"></i>
                                Manage Projects
                            </a>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Manage Connections Modal -->
    <div id="manageConnectionsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-40 backdrop-blur-sm" aria-hidden="true" onclick="closeManageConnectionsModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block px-6 pt-5 pb-6 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Manage Connections</h3>
                    <button type="button" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition" onclick="closeManageConnectionsModal()">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="mt-2">
                    <div id="activeConnectionsList" class="space-y-4 max-h-96 overflow-y-auto pr-2">
                        <div class="text-center py-4 text-gray-500">Loading connections...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Skills Modal -->
    <div id="manageSkillsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-40 backdrop-blur-sm" aria-hidden="true" onclick="closeManageSkillsModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block px-6 pt-5 pb-6 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Manage Your Skills</h3>
                    <button type="button" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition" onclick="closeManageSkillsModal()">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="mt-2">
                    <form onsubmit="addNewSkill(event)" class="flex gap-2 mb-4">
                        <input type="text" id="newSkillInput" class="form-input flex-1" placeholder="Add a new skill (e.g. Python)" required>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </form>
                    <div id="currentSkillsList" class="flex flex-wrap gap-2 max-h-64 overflow-y-auto p-4 bg-slate-50 rounded-xl border border-gray-100 min-h-[100px]">
                        <!-- Skills injected here -->
                    </div>
                </div>
                <div class="mt-6">
                    <button type="button" onclick="saveSkills()" class="btn btn-accent btn-full">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/global.js"></script>
    <script>
        feather.replace();
        
        // Mobile menu toggle helper
        document.addEventListener('DOMContentLoaded', () => {
            // Dynamic Time-of-day greeting (Creative Feature #12)
            const hrs = new Date().getHours();
            let greet = 'Welcome back';
            let emoji = '👋';
            if (hrs >= 5 && hrs < 12) { greet = 'Good morning'; emoji = '🌅'; }
            else if (hrs >= 12 && hrs < 17) { greet = 'Good afternoon'; emoji = '☀️'; }
            else if (hrs >= 17 && hrs < 21) { greet = 'Good evening'; emoji = '🌆'; }
            else { greet = 'Hope you are having a productive night'; emoji = '🌌'; }
            
            const greetEl = document.getElementById('welcome-greeting');
            if (greetEl) {
                greetEl.innerHTML = `${greet}, <?php echo htmlspecialchars(addslashes($user_data['first_name'])); ?>! ${emoji}`;
            }

            const counts = {
                connections: <?php echo $connections_count; ?>,
                projects: <?php echo $active_projects; ?>,
                messages: <?php echo $messages_count; ?>,
                skills: <?php echo $skills_count; ?>
            };
            
            // Trigger count up animations after page load
            setTimeout(() => {
                animateCount(document.getElementById('val-connections'), counts.connections);
                animateCount(document.getElementById('val-projects'), counts.projects);
                animateCount(document.getElementById('val-messages'), counts.messages);
                animateCount(document.getElementById('val-skills'), counts.skills);
            }, 300);

            // Fetch and render analytics charts
            renderAnalyticsCharts();
        });

        async function renderAnalyticsCharts() {
            try {
                const res = await fetch('backend/get_dashboard_analytics.php');
                const data = await res.json();
                if (data.success && data.analytics) {
                    const stats = data.analytics;

                    // 1. Projects Bar Chart
                    const projCtx = document.getElementById('projectsChart').getContext('2d');
                    new Chart(projCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Planning', 'In Progress', 'Completed', 'On Hold'],
                            datasets: [{
                                label: 'Projects count',
                                data: [
                                    stats.projects['planning'] || 0,
                                    stats.projects['in-progress'] || 0,
                                    stats.projects['completed'] || 0,
                                    stats.projects['on-hold'] || 0
                                ],
                                backgroundColor: ['#4A90E2', '#6C5CE7', '#00B894', '#F59E0B'],
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1, precision: 0 }
                                }
                            }
                        }
                    });

                    // 2. Tasks Doughnut Chart
                    const taskCtx = document.getElementById('tasksChart').getContext('2d');
                    const totalTasks = (stats.tasks['todo'] || 0) + (stats.tasks['in_progress'] || 0) + (stats.tasks['completed'] || 0);

                    if (totalTasks === 0) {
                        // Display message if no tasks are assigned
                        document.getElementById('tasksChart').parentElement.innerHTML = `
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 text-xs">
                                <i data-feather="clipboard" class="w-8 h-8 mb-2"></i>
                                <p>No tasks assigned to you yet.</p>
                            </div>
                        `;
                        feather.replace();
                        return;
                    }

                    new Chart(taskCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['To Do', 'In Progress', 'Completed'],
                            datasets: [{
                                data: [
                                    stats.tasks['todo'] || 0,
                                    stats.tasks['in_progress'] || 0,
                                    stats.tasks['completed'] || 0
                                ],
                                backgroundColor: ['#3b82f6', '#6366f1', '#10b981'],
                                borderWidth: 2,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, font: { size: 10 } }
                                }
                            }
                        }
                    });
                }
            } catch (err) {
                console.error("Error rendering charts:", err);
            }
        }

        // Color schemes for suggestion cards matching our design system
        const cardDesigns = [
            { gradient: 'from-blue-500 to-indigo-500', badge: 'badge-blue' },
            { gradient: 'from-purple-500 to-accent', badge: 'badge-purple' },
            { gradient: 'from-teal-500 to-success', badge: 'badge-green' }
        ];

        async function loadSuggestions() {
            try {
                const response = await fetch('backend/get_suggested_partners.php');
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to load suggestions');
                }

                const container = document.getElementById('suggestionsContainer');
                container.innerHTML = '';

                if (data.suggestions.length === 0) {
                    container.innerHTML = '<div class="col-span-full empty-state"><div class="empty-state-icon"><i data-feather="users"></i></div><div class="empty-state-title">No Suggestions Yet</div><div class="empty-state-desc">Expand your bio or skills to get matches.</div></div>';
                    feather.replace();
                    return;
                }

                data.suggestions.forEach((partner, index) => {
                    const design = cardDesigns[index % cardDesigns.length];
                    const skillBadges = partner.skills.slice(0, 3).map(skill => 
                        `<span class="badge ${design.badge}">${escapeHtml(skill)}</span>`
                    ).join('');

                    const card = document.createElement('div');
                    card.className = 'card reveal';
                    card.innerHTML = `
                        <div class="relative">
                            <div class="h-20 bg-gradient-to-r ${design.gradient}"></div>
                            <div class="absolute top-10 left-1/2 transform -translate-x-1/2">
                                <div class="h-16 w-16 rounded-full border-4 border-white overflow-hidden shadow-md">
                                    <img src="${escapeHtml(partner.avatar_url)}" alt="${escapeHtml(partner.name)}" class="h-full w-full object-cover" data-fallback-name="${escapeHtml(partner.name)}">
                                </div>
                            </div>
                        </div>
                        <div class="p-6 mt-6 flex flex-col items-center">
                            <h3 class="text-base font-bold text-gray-900">${escapeHtml(partner.name)}</h3>
                            <p class="text-xs text-gray-500 mb-2">${partner.matching_skills} matching skill${partner.matching_skills !== 1 ? 's' : ''}</p>
                            <p class="text-sm text-gray-500 text-center line-clamp-2 mb-4 h-10">${escapeHtml(partner.bio || 'No bio provided.')}</p>
                            <div class="flex flex-wrap justify-center gap-1.5 mb-5 h-7 overflow-hidden">
                                ${skillBadges}
                            </div>
                            <button onclick="connectPartnerFromDashboard(${partner.id})" class="btn btn-outline btn-sm btn-full">
                                <i data-feather="user-plus" class="w-4 h-4"></i>
                                Connect
                            </button>
                        </div>
                    `;
                    container.appendChild(card);
                });

                // Attach fallbacks to newly rendered images
                document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                    img.addEventListener('error', function () {
                        const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                        this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                    });
                    if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                });

                feather.replace();
            } catch (error) {
                console.error('Error:', error);
                const container = document.getElementById('suggestionsContainer');
                container.innerHTML = '<div class="col-span-full text-center py-8 text-red-500"><p>Failed to load suggestions</p></div>';
            }
        }

        function refreshSuggestions() {
            const container = document.getElementById('suggestionsContainer');
            container.innerHTML = `
                <div class="col-span-full text-center py-12 text-gray-500">
                    <i data-feather="loader" class="w-8 h-8 mx-auto mb-2 animate-spin text-blue-500"></i>
                    <p>Fetching new partner recommendations...</p>
                </div>`;
            feather.replace();
            loadSuggestions();
        }

        async function connectPartnerFromDashboard(partnerId) {
            try {
                const response = await fetch('backend/add_connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner_id: partnerId })
                });

                const data = await response.json();

                if (!response.ok) {
                    showToast(data.error || 'Failed to send request', 'error');
                    return;
                }

                showToast(data.message || 'Connection request sent!', 'success');
                refreshSuggestions();
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to send connection request', 'error');
            }
        }

        async function loadPendingConnections() {
            try {
                const response = await fetch('backend/get_pending_connections.php');
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to load pending connections');
                }

                const section = document.getElementById('pendingConnectionsSection');
                const container = document.getElementById('pendingConnectionsContainer');
                
                if (data.requests.length === 0) {
                    section.classList.add('hidden');
                    return;
                }
                
                section.classList.remove('hidden');
                container.innerHTML = '';

                data.requests.forEach((req) => {
                    const skillBadges = req.skills.slice(0, 2).map(skill => 
                        `<span class="badge badge-gray">${escapeHtml(skill)}</span>`
                    ).join('');

                    const card = document.createElement('div');
                    card.className = 'card p-5';
                    card.innerHTML = `
                        <div class="flex items-start">
                            <img class="h-11 w-11 rounded-full border border-gray-100 shadow-sm mr-4" src="${escapeHtml(req.avatar_url)}" alt="${escapeHtml(req.name)}" data-fallback-name="${escapeHtml(req.name)}">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(req.name)}</h3>
                                <p class="text-xs text-gray-500 line-clamp-1 mb-2">${escapeHtml(req.bio || 'No bio shared.')}</p>
                                <div class="flex flex-wrap gap-1 mb-4 h-6 overflow-hidden">
                                    ${skillBadges}
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="acceptConnection(${req.connection_id}, ${req.user_id})" class="flex-1 btn btn-primary btn-sm justify-center">
                                        Accept
                                    </button>
                                    <button onclick="rejectConnection(${req.connection_id})" class="flex-1 btn btn-outline btn-sm justify-center">
                                        Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    container.appendChild(card);
                });

                document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                    img.addEventListener('error', function () {
                        const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                        this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                    });
                    if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                });
            } catch (error) {
                console.error('Error loading pending connections:', error);
            }
        }

        async function acceptConnection(connectionId, partnerId) {
            try {
                const response = await fetch('backend/add_connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner_id: partnerId })
                });

                const data = await response.json();
                if (!response.ok) {
                    showToast(data.error || 'Failed to accept connection', 'error');
                    return;
                }
                showToast('Connection request approved!', 'success');
                loadPendingConnections();
                // Increment connection count visually
                const countEl = document.getElementById('val-connections');
                if (countEl) countEl.innerText = parseInt(countEl.innerText) + 1;
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to accept connection', 'error');
            }
        }

        async function rejectConnection(connectionId) {
            if (!confirm("Are you sure you want to decline this request?")) return;
            try {
                const response = await fetch('backend/reject_connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ connection_id: connectionId })
                });

                const data = await response.json();
                if (!response.ok) {
                    showToast(data.error || 'Failed to decline connection', 'error');
                    return;
                }
                showToast('Connection request declined.', 'info');
                loadPendingConnections();
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to decline connection', 'error');
            }
        }

        // Notifications Dropdown Handler
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        notificationBtn.addEventListener('click', () => {
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });

        document.addEventListener('click', (e) => {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });

        async function loadNotifications() {
            try {
                const res = await fetch('backend/get_notifications.php');
                const data = await res.json();
                if(!res.ok || !data.success) throw new Error(data.error);

                const list = document.getElementById('notificationList');
                list.innerHTML = '';
                
                const badge = document.getElementById('notificationBadge');
                if (data.unread_count > 0) {
                    badge.classList.remove('hidden');
                    badge.textContent = data.unread_count;
                } else {
                    badge.classList.add('hidden');
                }

                if (data.notifications.length === 0) {
                    list.innerHTML = '<div class="px-4 py-8 text-sm text-gray-500 text-center">No new notifications</div>';
                    return;
                }

                data.notifications.forEach(n => {
                    const isUnread = n.is_read == 0 ? 'unread' : '';
                    
                    const item = document.createElement('div');
                    item.className = `notif-item ${isUnread}`;
                    item.onclick = async () => {
                        if (n.is_read == 0) {
                            await fetch('backend/mark_notification_read.php', {
                                method: 'POST',
                                headers: {'Content-Type':'application/json'},
                                body: JSON.stringify({notification_id: n.id})
                            });
                        }
                        if (n.type === 'message') window.location.href = 'messages.php';
                        if (n.type === 'connection') window.location.href = 'dashboard.php';
                    };
                    item.innerHTML = `
                        <div class="notif-dot"></div>
                        <div class="flex-1">
                            <p class="notif-text">${escapeHtml(n.message)}</p>
                            <p class="notif-time">${new Date(n.created_at).toLocaleDateString()} at ${new Date(n.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                        </div>
                    `;
                    list.appendChild(item);
                });
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }

        async function markAllNotificationsRead() {
            try {
                await fetch('backend/mark_notification_read.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({})
                });
                showToast('All notifications marked as read', 'success');
                loadNotifications();
            } catch (error) {
                console.error(error);
            }
        }

        async function loadDashboardProjects() {
            try {
                const response = await fetch('backend/get_projects.php');
                const data = await response.json();
                const container = document.getElementById('dashboardProjects');
                
                if (!response.ok || !data.success || data.projects.length === 0) {
                    container.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i data-feather="folder"></i></div><div class="empty-state-title">No Projects Found</div><div class="empty-state-desc">You are not a member of any projects.</div><a href="projects.php?new=1" class="btn btn-primary btn-sm mt-2">Create One</a></div>';
                    feather.replace();
                    return;
                }
                
                container.innerHTML = '';
                // Show up to 3 most recent projects
                data.projects.slice(0, 3).forEach(p => {
                    const statusBadges = {
                        'planning': 'badge-blue',
                        'in-progress': 'badge-orange',
                        'completed': 'badge-green',
                        'on-hold': 'badge-red'
                    };
                    const statusClass = statusBadges[p.status] || 'badge-gray';
                    
                    const el = document.createElement('div');
                    el.className = 'p-6 hover:bg-slate-50/50 transition flex items-start gap-4 cursor-pointer';
                    el.onclick = () => window.location.href = `project_workspace.php?id=${p.id}`;
                    el.innerHTML = `
                        <div class="w-11 h-11 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0 text-blue-500">
                            <i data-feather="folder"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <h3 class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(p.title)}</h3>
                                <span class="badge ${statusClass}">${escapeHtml(p.status)}</span>
                            </div>
                            <p class="text-xs text-gray-500 line-clamp-1 mb-2">${escapeHtml(p.description || 'No description.')}</p>
                            <div class="flex items-center text-[11px] text-gray-400 gap-3">
                                <span class="flex items-center gap-1"><i data-feather="clock" class="w-3 h-3"></i> Updated ${new Date(p.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    `;
                    container.appendChild(el);
                });
                feather.replace();
            } catch (e) {
                console.error(e);
                document.getElementById('dashboardProjects').innerHTML = '<div class="p-8 text-center text-red-500"><p>Failed to load projects</p></div>';
            }
        }

        // Load dashboard elements
        document.addEventListener('DOMContentLoaded', () => {
            loadSuggestions();
            loadPendingConnections();
            loadDashboardProjects();
            loadNotifications().then(() => {
                notificationDropdown.classList.add('hidden'); // hide it initially
            });
        });

        // Skills state
        let userSkills = <?php echo json_encode($skills); ?>;

        // Connections Modal Logic
        function openManageConnectionsModal() {
            document.getElementById('manageConnectionsModal').classList.remove('hidden');
            loadActiveConnections();
        }

        function closeManageConnectionsModal() {
            document.getElementById('manageConnectionsModal').classList.add('hidden');
        }

        async function loadActiveConnections() {
            try {
                const res = await fetch('backend/get_active_connections.php');
                const data = await res.json();
                const container = document.getElementById('activeConnectionsList');
                
                if(!res.ok || !data.success) {
                    container.innerHTML = '<div class="text-center py-4 text-red-500">Failed to load connections.</div>';
                    return;
                }
                
                if(data.connections.length === 0) {
                    container.innerHTML = '<div class="text-center py-4 text-gray-500">You have no connections yet.</div>';
                    return;
                }
                
                container.innerHTML = data.connections.map(conn => {
                    const skills = conn.skills.map(s => `<span class="badge badge-blue text-[10px]">${escapeHtml(s)}</span>`).join('');
                    return `
                    <div class="flex items-center justify-between p-4 bg-slate-50/50 rounded-xl border border-gray-100 hover:border-blue-200 transition">
                        <div class="flex items-center space-x-3">
                            <img src="${escapeHtml(conn.avatar_url)}" class="w-10 h-10 rounded-full object-cover shadow-sm" data-fallback-name="${escapeHtml(conn.name)}">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">${escapeHtml(conn.name)}</h4>
                                <div class="flex flex-wrap gap-1 mt-1">${skills}</div>
                            </div>
                        </div>
                        <button onclick="removeConnection(${conn.connection_id})" class="btn btn-outline btn-sm text-red-600 border-red-100 hover:border-red-300 hover:bg-red-50">
                            <i data-feather="user-x" class="w-3.5 h-3.5"></i> Remove
                        </button>
                    </div>
                    `;
                }).join('');

                document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                    img.addEventListener('error', function () {
                        const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                        this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                    });
                    if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                });

                feather.replace();
            } catch(e) { console.error(e); }
        }

        async function removeConnection(connectionId) {
            if(!confirm("Are you sure you want to remove this connection?")) return;
            try {
                const res = await fetch('backend/remove_connection.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({connection_id: connectionId})
                });
                const data = await res.json();
                if(data.success) {
                    showToast('Connection removed successfully', 'info');
                    const countEl = document.getElementById('val-connections');
                    if (countEl) countEl.innerText = Math.max(0, parseInt(countEl.innerText) - 1);
                    loadActiveConnections();
                } else {
                    showToast(data.error || "Error removing connection", 'error');
                }
            } catch(e) { console.error(e); }
        }

        // Skills Modal Logic
        function openManageSkillsModal() {
            document.getElementById('manageSkillsModal').classList.remove('hidden');
            renderSkillsModal();
        }

        function closeManageSkillsModal() {
            document.getElementById('manageSkillsModal').classList.add('hidden');
        }

        function renderSkillsModal() {
            const container = document.getElementById('currentSkillsList');
            if(userSkills.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-500 w-full text-center py-4">No skills added yet.</p>';
                return;
            }
            container.innerHTML = userSkills.map((skill, idx) => `
                <span class="badge badge-blue px-3 py-1 flex items-center gap-1.5 text-sm">
                    ${escapeHtml(skill)}
                    <button type="button" onclick="removeSkill(${idx})" class="w-4 h-4 rounded-full flex items-center justify-center text-blue-400 hover:bg-blue-200 hover:text-blue-600 transition">
                        <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8"><path stroke-linecap="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" /></svg>
                    </button>
                </span>
            `).join('');
        }

        function addNewSkill(e) {
            e.preventDefault();
            const input = document.getElementById('newSkillInput');
            const skill = input.value.trim();
            if(skill && !userSkills.includes(skill)) {
                userSkills.push(skill);
                input.value = '';
                renderSkillsModal();
            }
        }

        function removeSkill(index) {
            userSkills.splice(index, 1);
            renderSkillsModal();
        }

        async function saveSkills() {
            try {
                const res = await fetch('backend/update_skills.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({skills: userSkills})
                });
                const data = await res.json();
                if(data.success) {
                    showToast('Skills updated successfully!', 'success');
                    closeManageSkillsModal();
                    // update skill count visually
                    const countEl = document.getElementById('val-skills');
                    if (countEl) countEl.innerText = userSkills.length;
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || "Error saving skills", 'error');
                }
            } catch(e) { console.error(e); }
        }
    </script>
</body>
</html>
