<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}
require_once 'backend/db.php';

$current_user_id = $_SESSION['user_id'];
$skill_filter = isset($_GET['skill']) ? trim($_GET['skill']) : '';

// Get current user data
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar_url FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_user = $stmt->fetch();

// Pagination & filters handled via dynamic AJAX calls
$partners = []; // placeholder to prevent count crashes

$name = $current_user['first_name'] . ' ' . $current_user['last_name'];
$avatar_url = $current_user['avatar_url'] ?? 'http://static.photos/people/200x200/10';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Partners | ProjectCrew</title>
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
                    <span class="nav-badge" id="navConnectionCount">...</span>
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
                    <input id="skillSearch" type="search" placeholder="Search by skill..." value="<?php echo htmlspecialchars($skill_filter); ?>" aria-label="Search" onkeyup="searchPartners()">
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
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 class="page-title">Find Partners</h1>
                        <p class="page-subtitle">Find your next collaborator by matching technical skills.</p>
                    </div>
                </div>

                <!-- Search Filter Info -->
                <?php if (!empty($skill_filter)): ?>
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-between animate-fadeIn">
                        <div class="flex items-center">
                            <i data-feather="filter" class="w-4 h-4 text-blue-500 mr-2.5"></i>
                            <p class="text-sm text-blue-800">Filtering by skill: <strong><?php echo htmlspecialchars($skill_filter); ?></strong></p>
                        </div>
                        <a href="partners.php" class="btn btn-outline btn-sm">Clear Filter</a>
                    </div>
                <?php endif; ?>
                
                <!-- Smart Matches Carousel/Grid -->
                <div id="smartMatchesSection" class="mb-10 hidden reveal">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-slate-200">
                        <div class="flex items-center gap-2">
                            <i data-feather="cpu" class="w-5 h-5 text-indigo-500"></i>
                            <h2 class="text-sm font-bold text-gray-900">Recommended Matches</h2>
                        </div>
                        <span class="text-[9px] bg-indigo-50 text-indigo-600 py-0.5 px-2 rounded-full font-bold uppercase tracking-wider">Smart matchmaking</span>
                    </div>
                    <div id="smartMatchesContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Loaded dynamically via JS -->
                    </div>
                </div>

                <!-- Partner Grid -->
                <div id="partnersContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div class="col-span-full text-center py-12" id="partnersLoading">
                        <i data-feather="loader" class="w-8 h-8 animate-spin text-blue-500 mx-auto"></i>
                    </div>
                </div>

                <!-- Load More pagination button -->
                <div class="flex justify-center mt-10 mb-6 hidden" id="loadMoreContainer">
                    <button id="loadMoreBtn" onclick="loadMorePartners()" class="btn btn-outline flex items-center gap-2 py-2.5 px-6 font-semibold shadow-sm transition">
                        <span>Load More Partners</span>
                        <i data-feather="chevron-down" class="w-4 h-4"></i>
                    </button>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/global.js"></script>
    <script>
        feather.replace();

        let searchTimeout;

        function searchPartners() {
            const skillInput = document.getElementById('skillSearch').value.trim();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                if (skillInput === '') {
                    window.location.href = 'partners.php';
                } else {
                    window.location.href = 'partners.php?skill=' + encodeURIComponent(skillInput);
                }
            }, 500); 
        }

        async function connectPartner(partnerId) {
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

                const button = document.querySelector(`button[data-partner-id="${partnerId}"]`);
                if (button) {
                    if (data.status === 'connected') {
                        button.outerHTML = `<button class="btn btn-success flex-1 justify-center btn-sm" disabled><i data-feather="user-plus" class="w-3.5 h-3.5"></i> <span>Connected</span></button>`;
                    } else if (data.status === 'pending') {
                        button.outerHTML = `<button class="btn flex-1 justify-center btn-sm bg-amber-50 border border-amber-200 text-amber-600" disabled><i data-feather="user-plus" class="w-3.5 h-3.5"></i> <span>Pending</span></button>`;
                    }
                    feather.replace();
                }

                updateConnectionCount();
                showToast(data.message || 'Connection request sent!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to send connection request', 'error');
            }
        }

        async function updateConnectionCount() {
            try {
                const response = await fetch('backend/get_connections.php');
                const data = await response.json();

                if (response.ok) {
                    const countElement = document.getElementById('navConnectionCount');
                    if (countElement) {
                        countElement.textContent = data.connected;
                        countElement.classList.add('nav-badge');
                    }
                }
            } catch (error) {
                console.error('Error updating connection count:', error);
            }
        }

        // Matchmaker JS Integration
        async function loadSmartMatches() {
            try {
                const res = await fetch('backend/get_matchmaking_recommendations.php');
                const data = await res.json();
                if (data.success && data.recommendations && data.recommendations.length > 0) {
                    const section = document.getElementById('smartMatchesSection');
                    const container = document.getElementById('smartMatchesContainer');
                    container.innerHTML = '';
                    
                    data.recommendations.forEach((item, index) => {
                        const card = document.createElement('div');
                        card.className = 'card relative hover:-translate-y-1 transition duration-300 reveal visible';
                        
                        const gradients = [
                            'from-blue-500 to-indigo-500',
                            'from-purple-500 to-accent',
                            'from-teal-500 to-success'
                        ];
                        const gradient = gradients[index % gradients.length];
                        
                        const skillsHtml = item.skills.slice(0, 3).map(skill => 
                            `<span class="badge badge-blue text-[10px] py-1 px-2.5">${escapeHTML(skill)}</span>`
                        ).join('');

                        card.innerHTML = `
                            <div class="relative">
                                <div class="h-20 bg-gradient-to-r ${gradient} rounded-t-2xl"></div>
                                <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm text-[9px] font-bold text-indigo-600 py-1 px-2 rounded-full border border-indigo-100 flex items-center shadow-sm uppercase tracking-wider">
                                    <i data-feather="zap" class="w-2.5 h-2.5 mr-0.5 fill-current text-indigo-500"></i> ${item.match_percentage}% Match
                                </div>
                                <div class="absolute top-10 left-1/2 transform -translate-x-1/2">
                                    <a href="user_profile.php?id=${item.id}" class="block h-16 w-16 rounded-full border-4 border-white overflow-hidden shadow-sm hover:scale-105 transition">
                                        <img src="${escapeHTML(item.avatar_url)}" alt="Avatar" class="h-full w-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(item.first_name + ' ' + item.last_name)}&background=4A90E2&color=fff&size=80'">
                                    </a>
                                </div>
                            </div>
                            <div class="p-5 mt-6 flex flex-col items-center text-center">
                                <h3 class="text-xs font-bold text-gray-900 truncate max-w-full">
                                    <a href="user_profile.php?id=${item.id}" class="hover:text-blue-500 transition">${escapeHTML(item.first_name + ' ' + item.last_name)}</a>
                                </h3>
                                <p class="text-[11px] text-gray-500 line-clamp-2 mt-1 mb-4 h-8">${escapeHTML(item.bio)}</p>
                                
                                <div class="flex flex-wrap justify-center gap-1 mb-5 h-6 overflow-hidden">
                                    ${skillsHtml}
                                </div>
                                
                                <button onclick="sendMatchRequest(${item.id}, this)" class="btn btn-primary w-full btn-sm text-xs font-semibold py-1.5 rounded-lg shadow-sm">
                                    Connect
                                </button>
                            </div>
                        `;
                        container.appendChild(card);
                    });
                    
                    section.classList.remove('hidden');
                    feather.replace();
                }
            } catch (err) {
                console.error("Matchmaking error:", err);
            }
        }

        async function sendMatchRequest(partnerId, buttonEl) {
            try {
                const response = await fetch('backend/add_connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner_id: partnerId })
                });
                const data = await response.json();
                if (response.ok) {
                    buttonEl.disabled = true;
                    buttonEl.textContent = 'Pending';
                    buttonEl.className = 'btn btn-outline w-full btn-sm text-xs py-1.5 opacity-60 pointer-events-none';
                    showToast('Connection request sent!', 'success');
                    updateConnectionCount();
                } else {
                    showToast(data.error || 'Failed to send request.', 'error');
                }
            } catch (error) {
                showToast('Failed to send request.', 'error');
            }
        }

        function escapeHTML(str) {
            if (!str) return '';
            return str.replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        let currentPage = 1;
        const skillFilter = '<?php echo htmlspecialchars($skill_filter); ?>';
        
        async function loadPartnersPaginated(page = 1, append = false) {
            const limit = 8;
            try {
                const url = `backend/get_partners_paginated.php?page=${page}&skill=${encodeURIComponent(skillFilter)}`;
                const res = await fetch(url);
                const data = await res.json();
                
                const container = document.getElementById('partnersContainer');
                const loadMoreBox = document.getElementById('loadMoreContainer');
                const loadingIndicator = document.getElementById('partnersLoading');
                
                if (loadingIndicator) loadingIndicator.remove();
                
                if (data.success) {
                    if (!append) {
                        container.innerHTML = '';
                    }
                    
                    if (data.partners.length === 0 && !append) {
                        container.innerHTML = `
                            <div class="col-span-full empty-state">
                                <div class="empty-state-icon"><i data-feather="users"></i></div>
                                <div class="empty-state-title">No Partners Found</div>
                                <div class="empty-state-desc">Try searching for other skills or clear the filter.</div>
                            </div>
                        `;
                        loadMoreBox.classList.add('hidden');
                        feather.replace();
                        return;
                    }
                    
                    const designs = [
                        { gradient: 'from-blue-500 to-indigo-500', badge: 'badge-blue' },
                        { gradient: 'from-purple-500 to-accent', badge: 'badge-purple' },
                        { gradient: 'from-teal-500 to-success', badge: 'badge-green' },
                        { gradient: 'from-amber-500 to-warning', badge: 'badge-orange' }
                    ];
                    
                    data.partners.forEach((partner, index) => {
                        const design = designs[index % designs.length];
                        const card = document.createElement('div');
                        card.className = 'card reveal visible';
                        card.style.animationDelay = `${index * 50}ms`;
                        
                        const skillsHtml = partner.skills.slice(0, 3).map(skill => 
                            `<span class="badge ${design.badge}">${escapeHTML(skill)}</span>`
                        ).join('');
                        
                        let btnText = 'Connect';
                        let btnDisabled = false;
                        let btnClass = 'btn btn-primary';
                        
                        if (partner.connection_status === 'connected') {
                            btnText = 'Connected';
                            btnDisabled = true;
                            btnClass = 'btn btn-success';
                        } else if (partner.connection_status === 'pending') {
                            btnText = 'Pending';
                            btnDisabled = true;
                            btnClass = 'btn';
                        }
                        
                        card.innerHTML = `
                            <div class="relative">
                                <div class="h-24 bg-gradient-to-r ${design.gradient} rounded-t-2xl"></div>
                                <div class="absolute top-12 left-1/2 transform -translate-x-1/2">
                                    <a href="user_profile.php?id=${partner.id}" class="block h-16 w-16 rounded-full border-4 border-white overflow-hidden shadow-sm hover:scale-105 transition">
                                        <img src="${escapeHTML(partner.avatar_url)}" alt="Avatar" class="h-full w-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(partner.name)}&background=4A90E2&color=fff&size=80'">
                                    </a>
                                </div>
                            </div>
                            <div class="p-5 mt-6 flex flex-col items-center">
                                <h3 class="text-sm font-bold text-gray-900 truncate max-w-full text-center">
                                    <a href="user_profile.php?id=${partner.id}" class="hover:text-blue-500 transition">${escapeHTML(partner.name)}</a>
                                </h3>
                                <p class="text-xs text-gray-500 text-center line-clamp-2 mt-1 mb-4 h-8">${escapeHTML(partner.bio)}</p>
                                
                                <div class="flex flex-wrap justify-center gap-1 mb-5 h-6 overflow-hidden">
                                    ${skillsHtml}
                                </div>
                                
                                <div class="flex items-center justify-center gap-2 w-full">
                                    <button class="${btnClass} flex-1 justify-center btn-sm text-xs font-semibold py-1.5 rounded-lg shadow-sm" 
                                        data-partner-id="${partner.id}"
                                        onclick="connectPartner(${partner.id})"
                                        ${btnDisabled ? 'disabled' : ''}>
                                        <i data-feather="user-plus" class="w-3.5 h-3.5 mr-1"></i>
                                        <span>${btnText}</span>
                                    </button>
                                    <a href="messages.php?partner_id=${partner.id}" class="btn btn-outline btn-sm flex items-center justify-center p-2 rounded-lg" aria-label="Message">
                                        <i data-feather="message-circle" class="w-4 h-4 text-slate-500"></i>
                                    </a>
                                </div>
                            </div>
                        `;
                        container.appendChild(card);
                    });
                    
                    if (data.partners.length < limit) {
                        loadMoreBox.classList.add('hidden');
                    } else {
                        loadMoreBox.classList.remove('hidden');
                    }
                    
                    feather.replace();
                }
            } catch (err) {
                console.error("Pagination error:", err);
            }
        }
        
        function loadMorePartners() {
            currentPage++;
            loadPartnersPaginated(currentPage, true);
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateConnectionCount();
            loadSmartMatches();
            loadPartnersPaginated(1, false);
        });
    </script>
</body>
</html>
