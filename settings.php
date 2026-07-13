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
$stmt = $pdo->prepare("SELECT first_name, last_name, email, avatar_url FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
$avatar_url = htmlspecialchars($user_data['avatar_url'] ?? 'http://static.photos/people/200x200/10');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | ProjectCrew</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .toggle-switch-checkbox:checked + .toggle-switch-label {
            background-color: var(--primary);
        }
        .toggle-switch-checkbox:checked + .toggle-switch-label::after {
            transform: translateX(20px);
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
                    <input type="search" placeholder="Search settings..." aria-label="Search" disabled class="opacity-50 cursor-not-allowed">
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
                <div class="max-w-3xl mx-auto space-y-6">
                    <div>
                        <h1 class="page-title">Settings</h1>
                        <p class="page-subtitle">Configure security, notifications, and profile details</p>
                    </div>

                    <!-- Change Password Section -->
                    <div class="card p-6 reveal">
                        <div class="pb-4 border-b border-gray-100 mb-6">
                            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <i data-feather="key" class="w-4 h-4 text-blue-500"></i>
                                Update Account Password
                            </h2>
                        </div>
                        
                        <form id="passwordForm" class="space-y-4">
                            <div>
                                <label class="form-label" for="current_password">Current Password</label>
                                <input type="password" id="current_password" required class="form-input" placeholder="••••••••">
                            </div>
                            <div>
                                <label class="form-label" for="new_password">New Password</label>
                                <input type="password" id="new_password" required minlength="6" class="form-input" placeholder="Min. 6 characters">
                            </div>
                            <div>
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" required minlength="6" class="form-input" placeholder="Min. 6 characters">
                            </div>
                            <div id="pwdMessage" class="text-xs font-semibold mt-2 hidden"></div>
                            
                            <div class="pt-2">
                                <button type="submit" class="btn btn-primary w-full sm:w-auto">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="card p-6 reveal">
                        <div class="pb-4 border-b border-gray-100 mb-6">
                            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <i data-feather="bell" class="w-4 h-4 text-blue-500"></i>
                                Notification Preferences
                            </h2>
                        </div>
                        
                        <div class="space-y-5">
                            <div class="flex items-center justify-between py-1">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900">Email Notifications</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">Receive digests and reminders for connection updates and messages</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" checked class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900">Push Notifications</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">Show real-time notifications on your browser tab</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" checked class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Danger Zone (Deactivate Account) -->
                    <div class="card p-6 border-red-200 bg-red-50/10 reveal">
                        <div class="pb-4 border-b border-red-100 mb-6">
                            <h2 class="text-sm font-bold text-red-700 flex items-center gap-2">
                                <i data-feather="alert-triangle" class="w-4 h-4 text-red-500"></i>
                                Danger Zone
                            </h2>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold text-gray-900">Deactivate & Delete Account</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Permanently delete your profile, posts, connections, and workspace task milestones. This action is irreversible.</p>
                            </div>
                            <button onclick="deactivateAccount()" class="btn bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-2 px-4 rounded-lg flex-shrink-0 transition">
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="assets/js/global.js"></script>
    <script>
        feather.replace();
        
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const curr = document.getElementById('current_password').value;
            const newP = document.getElementById('new_password').value;
            const conf = document.getElementById('confirm_password').value;
            const msg = document.getElementById('pwdMessage');

            if(newP !== conf) {
                msg.textContent = 'New passwords do not match';
                msg.className = 'text-xs font-semibold mt-2 text-red-600 block';
                showToast('Passwords do not match.', 'warning');
                return;
            }

            try {
                const res = await fetch('backend/update_password.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({current_password: curr, new_password: newP})
                });
                const data = await res.json();
                
                if(data.success) {
                    msg.textContent = 'Password updated successfully!';
                    msg.className = 'text-xs font-semibold mt-2 text-green-600 block';
                    showToast('Password updated successfully!', 'success');
                    document.getElementById('passwordForm').reset();
                } else {
                    msg.textContent = data.error;
                    msg.className = 'text-xs font-semibold mt-2 text-red-600 block';
                    showToast(data.error || 'Failed to update password.', 'error');
                }
            } catch (err) {
                msg.textContent = 'Error updating password';
                msg.className = 'text-xs font-semibold mt-2 text-red-600 block';
                showToast('Error updating password', 'error');
            }
        });

        // Account Deactivation Helper (Necessary Feature #8)
        async function deactivateAccount() {
            if (!confirm("Warning: You are about to permanently delete your account, project files, and tasks. This cannot be undone. Are you absolutely sure?")) return;
            if (prompt("Please type DELETE to confirm account deactivation:") !== "DELETE") {
                showToast("Confirmation failed. Account not deleted.", "warning");
                return;
            }
            try {
                const res = await fetch('backend/deactivate_account.php', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Account deactivated successfully. Goodbye!', 'success');
                    setTimeout(() => window.location.href = 'login.html', 1500);
                } else {
                    showToast(data.error || 'Failed to deactivate account.', 'error');
                }
            } catch (err) {
                showToast('Error deactivating account.', 'error');
            }
        }
    </script>
</body>
</html>