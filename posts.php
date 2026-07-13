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
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar_url, skills, bio FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    session_destroy();
    header('Location: login.html');
    exit();
}

$name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
$avatar_url = htmlspecialchars($user_data['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user_data['first_name'] . ' ' . $user_data['last_name']) . '&background=random');
$roles = 'Team Member';

$messages_count = 0;
try {
    $msg_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $msg_stmt->execute([$user_id]);
    $messages_count = $msg_stmt->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts | ProjectCrew</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .post-content-text {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        @keyframes floatHeart {
            0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
            15% { opacity: 0.9; }
            100% { transform: translate(var(--x), var(--y)) scale(1.2); opacity: 0; }
        }
        .heart-particle {
            position: fixed;
            pointer-events: none;
            color: #EF4444;
            font-size: 16px;
            animation: floatHeart 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            z-index: 100;
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
                    <input type="search" placeholder="Search posts feed..." aria-label="Search" disabled class="opacity-50 cursor-not-allowed">
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
                <div class="max-w-3xl mx-auto">
                    <!-- Create Post -->
                    <div class="card p-6 mb-8 reveal">
                        <div class="flex items-start">
                            <img class="h-10 w-10 rounded-full object-cover border border-gray-100 shadow-sm mr-4" src="<?php echo $avatar_url; ?>" alt="Your avatar" data-fallback-name="<?php echo htmlspecialchars($name); ?>">
                            <div class="flex-1">
                                <textarea id="postContent" class="w-full border-0 bg-transparent focus:ring-0 resize-none text-gray-900 placeholder-gray-400 p-0 text-base outline-none h-12" placeholder="Share your updates, questions, or project highlights..."></textarea>
                                
                                <!-- Image Preview Container -->
                                <div id="imagePreviewContainer" class="hidden mt-3 relative rounded-2xl overflow-hidden border border-gray-100 group shadow-sm max-w-full">
                                    <img id="imagePreview" src="" class="w-full max-h-64 object-cover">
                                    <button onclick="removeImage()" class="absolute top-3 right-3 bg-slate-900 bg-opacity-75 text-white rounded-full p-2 hover:bg-slate-950 transition flex items-center justify-center shadow-md">
                                        <i data-feather="x" class="w-4 h-4"></i>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                                    <div class="flex items-center">
                                        <label class="cursor-pointer text-gray-500 hover:text-blue-500 transition flex items-center text-xs font-semibold bg-slate-50 hover:bg-blue-50/50 py-2 px-3 rounded-lg border border-slate-100">
                                            <i data-feather="image" class="w-4 h-4 mr-2 text-blue-500"></i>
                                            <span>Add Photo</span>
                                            <input type="file" id="postImage" accept="image/*" class="hidden" onchange="previewImage(event)">
                                        </label>
                                    </div>
                                    <button onclick="createPost()" class="btn btn-primary px-5 py-2">
                                        Publish Post
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Posts Feed -->
                    <div id="postsFeed" class="space-y-6">
                        <!-- Loaded via JS -->
                        <div class="card p-6 flex flex-col items-center">
                            <i data-feather="loader" class="w-8 h-8 animate-spin text-blue-500 mb-2"></i>
                            <p class="text-sm text-gray-500">Loading your feed...</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/global.js"></script>
    <script>
        feather.replace();

        const textarea = document.getElementById('postContent');
        textarea.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        let selectedFile = null;

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                selectedFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function removeImage() {
            selectedFile = null;
            const fileInput = document.getElementById('postImage');
            if (fileInput) fileInput.value = '';
            document.getElementById('imagePreviewContainer').classList.add('hidden');
            document.getElementById('imagePreview').src = '';
        }

        async function loadPosts() {
            try {
                const res = await fetch('backend/get_posts.php');
                const data = await res.json();
                const feed = document.getElementById('postsFeed');
                feed.innerHTML = '';
                
                if (data.posts.length === 0) {
                    feed.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i data-feather="message-square"></i></div><div class="empty-state-title">Your Feed is Empty</div><div class="empty-state-desc">Publish a post or update to get the conversation started!</div></div>';
                    feather.replace();
                    return;
                }

                data.posts.forEach((post, index) => {
                    const date = new Date(post.created_at).toLocaleString([], {month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit'});
                    const card = document.createElement('div');
                    card.className = 'card overflow-hidden reveal';
                    card.style.animationDelay = `${index * 50}ms`;
                    card.id = `post-${post.id}`;
                    
                    let imageHtml = post.image_url ? `
                        <div class="w-full border-b border-gray-100 overflow-hidden bg-slate-50">
                            <img class="w-full object-cover max-h-[480px]" src="${escapeHtml(post.image_url)}" alt="Post illustration" data-fallback-name="Upload">
                        </div>` : '';

                    const currentUserId = <?php echo $user_id; ?>;
                    const deleteBtnHtml = post.user_id == currentUserId ? `
                            <button onclick="deletePost(${post.id})" class="text-gray-400 hover:text-red-500 transition p-1" title="Delete Post">
                                <i data-feather="trash-2" class="w-4 h-4"></i>
                            </button>` : '';

                    const likeHeartClass = post.has_liked ? 'text-red-500 fill-current' : 'text-gray-400 hover:text-red-500';
                    
                    card.innerHTML = `
                        <!-- Post Header -->
                        <div class="flex items-center justify-between p-5 border-b border-gray-50 bg-slate-50/20">
                            <div class="flex items-center min-w-0">
                                <img class="h-9 w-9 rounded-full object-cover mr-3 border border-gray-100 shadow-sm" src="${escapeHtml(post.avatar_url)}" alt="Author avatar" data-fallback-name="${escapeHtml(post.name)}">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900 truncate">${escapeHtml(post.name)}</p>
                                    <p class="text-[10px] text-gray-400 font-medium">${date}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                ${deleteBtnHtml}
                            </div>
                        </div>

                        <!-- Post Content -->
                        <div class="p-5 pb-3">
                            <p class="text-sm text-gray-800 post-content-text leading-relaxed">${escapeHtml(post.content)}</p>
                        </div>

                        <!-- Post Image -->
                        ${imageHtml}
                        
                        <!-- Post Actions -->
                        <div class="px-5 py-3 border-b border-gray-50 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <button onclick="toggleLike(${post.id})" class="flex items-center gap-1.5 text-xs text-gray-500 font-semibold transition" title="Like">
                                    <i data-feather="heart" id="like-icon-${post.id}" class="w-5 h-5 ${likeHeartClass}"></i>
                                    <span id="likes-count-${post.id}">${post.likes_count}</span>
                                </button>
                                <button onclick="toggleComments(${post.id}, ${post.user_id})" class="flex items-center gap-1.5 text-xs text-gray-500 font-semibold hover:text-blue-500 transition" title="Comment">
                                    <i data-feather="message-circle" class="w-5 h-5"></i>
                                    <span>Comments (${post.comments_count})</span>
                                </button>
                                <button onclick="copyPostLink(${post.id})" class="flex items-center gap-1.5 text-xs text-gray-500 font-semibold hover:text-indigo-500 transition" title="Share Post">
                                    <i data-feather="share-2" class="w-5 h-5"></i>
                                    <span>Share</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Comments Section (Collapsed by default) -->
                        <div id="comments-section-${post.id}" class="hidden p-5 bg-slate-50/50 border-b border-gray-50">
                            <div id="comments-list-${post.id}" class="space-y-3 max-h-60 overflow-y-auto mb-4 pr-1">
                                <!-- Loaded via JS -->
                            </div>
                            
                            <!-- Add Comment Input -->
                            <div class="flex items-center gap-3">
                                <input type="text" id="comment-input-${post.id}" placeholder="Write a comment..." class="form-input flex-1 py-2 text-xs" onkeydown="if(event.key==='Enter') addComment(${post.id}, ${post.user_id})">
                                <button onclick="addComment(${post.id}, ${post.user_id})" class="btn btn-primary btn-sm px-4">Post</button>
                            </div>
                        </div>
                    `;
                    feed.appendChild(card);
                });

                document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                    img.addEventListener('error', function () {
                        const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                        this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                    });
                    if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                });

                feather.replace();

                // Observe newly created cards for scroll reveal (Fixing blank cards issue!)
                if (window.scrollRevealObserver) {
                    document.querySelectorAll('#postsFeed .reveal').forEach(el => {
                        window.scrollRevealObserver.observe(el);
                    });
                }

                // Highlight/Scroll target post if present in URL query
                const urlParams = new URLSearchParams(window.location.search);
                const highlightId = urlParams.get('post_id');
                if (highlightId) {
                    const targetEl = document.getElementById(`post-${highlightId}`);
                    if (targetEl) {
                        setTimeout(() => {
                            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            targetEl.style.outline = '2px solid var(--accent)';
                            targetEl.style.borderRadius = '16px';
                            setTimeout(() => {
                                targetEl.style.transition = 'outline 1.5s ease';
                                targetEl.style.outline = '2px solid transparent';
                            }, 3000);
                        }, 500);
                    }
                }
            } catch (err) {
                console.error(err);
                document.getElementById('postsFeed').innerHTML = '<div class="text-center py-8 text-red-500">Failed to load posts</div>';
            }
        }

        async function createPost() {
            const content = textarea.value.trim();
            if (!content && !selectedFile) return;

            const formData = new FormData();
            formData.append('content', content);
            if (selectedFile) {
                formData.append('image', selectedFile);
            }

            try {
                const res = await fetch('backend/create_post.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (res.ok) {
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    removeImage();
                    showToast('Post published successfully!', 'success');
                    loadPosts();
                } else {
                    showToast(data.error || 'Failed to create post', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Failed to create post', 'error');
            }
        }

        async function toggleLike(postId) {
            const fd = new FormData();
            fd.append('post_id', postId);
            try {
                const res = await fetch('backend/like_post.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    document.getElementById(`likes-count-${postId}`).innerText = data.likes_count;
                    const icon = document.getElementById(`like-icon-${postId}`);
                    if (data.action === 'liked') {
                        icon.className = 'w-5 h-5 text-red-500 fill-current';
                        spawnLikeParticles(postId);
                    } else {
                        icon.className = 'w-5 h-5 text-gray-400 hover:text-red-500';
                    }
                }
            } catch (e) { console.error(e); }
        }

        function spawnLikeParticles(postId) {
            const btn = document.getElementById(`like-icon-${postId}`);
            if (!btn) return;
            
            const rect = btn.getBoundingClientRect();
            const count = 6;
            
            for (let i = 0; i < count; i++) {
                const heart = document.createElement('span');
                heart.className = 'heart-particle';
                heart.innerHTML = '❤️';
                
                heart.style.left = `${rect.left + rect.width / 2}px`;
                heart.style.top = `${rect.top + rect.height / 2}px`;
                
                const x = (Math.random() - 0.5) * 80;
                const y = -(Math.random() * 80 + 40);
                
                heart.style.setProperty('--x', `${x}px`);
                heart.style.setProperty('--y', `${y}px`);
                heart.style.animationDelay = `${Math.random() * 0.15}s`;
                
                document.body.appendChild(heart);
                setTimeout(() => heart.remove(), 1000);
            }
        }

        let activeReplyParent = {};

        async function toggleComments(postId, postAuthorId) {
            const section = document.getElementById(`comments-section-${postId}`);
            if (section.classList.contains('hidden')) {
                section.classList.remove('hidden');
                loadComments(postId, postAuthorId);
            } else {
                section.classList.add('hidden');
            }
        }

        async function loadComments(postId, postAuthorId) {
            try {
                const res = await fetch(`backend/get_post_comments.php?post_id=${postId}`);
                const data = await res.json();
                const list = document.getElementById(`comments-list-${postId}`);
                list.innerHTML = '';
                
                if (data.comments && data.comments.length > 0) {
                    const roots = data.comments.filter(c => !c.parent_id);
                    const replies = data.comments.filter(c => c.parent_id);
                    
                    const renderComment = (c, isReply = false) => {
                        const authorBadge = c.is_author ? '<span class="badge badge-blue text-[9px] py-0.5 ml-1">Author</span>' : '';
                        const pinnedBadge = c.is_pinned && !isReply ? '<span class="text-[10px] text-blue-500 font-semibold flex items-center ml-2 gap-0.5"><i data-feather="anchor" class="w-3 h-3"></i> Pinned</span>' : '';
                        const currentUserId = <?php echo $user_id; ?>;
                        
                        let pinBtn = '';
                        if (!isReply && postAuthorId == currentUserId) {
                            const action = c.is_pinned ? 'unpin' : 'pin';
                            pinBtn = `<button onclick="pinComment(${c.id}, '${action}', ${postId}, ${postAuthorId})" class="text-[10px] text-blue-500 ml-2 hover:underline font-semibold">${c.is_pinned ? 'Unpin' : 'Pin'}</button>`;
                        }

                        let replyBtn = '';
                        if (!isReply) {
                            replyBtn = `<button onclick="prepareReply(${postId}, ${c.id}, '${escapeHtml(c.name)}')" class="text-[10px] text-gray-400 font-semibold ml-2 hover:text-gray-600">Reply</button>`;
                        }

                        const leftMargin = isReply ? 'ml-8 mt-2 bg-slate-100/50 p-2.5 rounded-xl border border-gray-100' : 'mt-3 p-3 bg-white rounded-xl border border-gray-50 shadow-sm';
                        return `
                            <div class="flex items-start ${leftMargin}">
                                <img src="${escapeHtml(c.avatar_url)}" class="w-7 h-7 rounded-full mr-2.5 object-cover" data-fallback-name="${escapeHtml(c.name)}">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-900">${escapeHtml(c.name)}</span>
                                        ${authorBadge}
                                        <p class="text-gray-700 mt-1 comment-text-box">${escapeHtml(c.content)}</p>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        ${pinnedBadge}
                                        ${pinBtn}
                                        ${replyBtn}
                                    </div>
                                </div>
                            </div>
                        `;
                    };

                    roots.forEach(r => {
                        list.innerHTML += renderComment(r);
                        const rReplies = replies.filter(rep => rep.parent_id == r.id);
                        rReplies.forEach(rep => {
                            list.innerHTML += renderComment(rep, true);
                        });
                    });
                    
                    document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                        img.addEventListener('error', function () {
                            const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                            this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                        });
                        if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                    });

                    feather.replace();
                } else {
                    list.innerHTML = '<p class="text-xs text-gray-400 py-2">No comments yet.</p>';
                }
            } catch (e) {
                console.error(e);
            }
        }

        function prepareReply(postId, commentId, authorName) {
            activeReplyParent[postId] = commentId;
            const input = document.getElementById(`comment-input-${postId}`);
            input.value = `@${authorName} `;
            input.focus();
        }

        async function addComment(postId, postAuthorId) {
            const input = document.getElementById(`comment-input-${postId}`);
            const content = input.value.trim();
            if (!content) return;

            const fd = new FormData();
            fd.append('post_id', postId);
            fd.append('content', content);
            if (activeReplyParent[postId]) {
                fd.append('parent_id', activeReplyParent[postId]);
                delete activeReplyParent[postId];
            }

            try {
                const res = await fetch('backend/add_comment.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    input.value = '';
                    const section = document.getElementById(`comments-section-${postId}`);
                    section.classList.remove('hidden');
                    loadComments(postId, postAuthorId);
                }
            } catch(e) { console.error(e); }
        }

        async function pinComment(commentId, action, postId, postAuthorId) {
            const fd = new FormData();
            fd.append('comment_id', commentId);
            fd.append('action', action);
            try {
                const res = await fetch('backend/pin_comment.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(action === 'pin' ? 'Comment pinned!' : 'Comment unpinned.', 'info');
                    loadComments(postId, postAuthorId);
                } else {
                    showToast(data.error || 'Failed to pin comment', 'error');
                }
            } catch(e) { console.error(e); }
        }

        async function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post?')) return;
            const fd = new FormData();
            fd.append('post_id', postId);
            try {
                const res = await fetch('backend/delete_post.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast('Post deleted successfully.', 'info');
                    document.getElementById(`post-${postId}`).remove();
                } else {
                    showToast(data.error || 'Failed to delete post', 'error');
                }
            } catch(e) { console.error(e); }
        }

        // Post Link Copier Helper (Necessary Feature #6)
        function copyPostLink(postId) {
            const shareUrl = window.location.origin + window.location.pathname + '?post_id=' + postId;
            navigator.clipboard.writeText(shareUrl)
                .then(() => {
                    showToast('Permalink copied to clipboard!', 'success');
                })
                .catch(err => {
                    showToast('Could not copy link.', 'error');
                });
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        document.addEventListener('DOMContentLoaded', loadPosts);
    </script>
</body>
</html>
