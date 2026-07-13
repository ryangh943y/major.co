<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}
require_once 'backend/db.php';

$current_user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar_url FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_user = $stmt->fetch();
$name = $current_user['first_name'] . ' ' . $current_user['last_name'];
$avatar_url = $current_user['avatar_url'] ?? 'http://static.photos/people/200x200/10';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | ProjectCrew</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .conversation-item {
            transition: all 0.2s ease;
        }
        .conversation-item:hover {
            background-color: var(--primary-light);
        }
        .conversation-item.active {
            background-color: rgba(74, 144, 226, 0.12);
            border-left: 3px solid var(--primary);
        }
        .message-bubble {
            max-width: 70%;
            padding: 10px 16px;
            font-size: 0.875rem;
            line-height: 1.4;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .message-in {
            background-color: white;
            color: #1e293b;
            border-radius: 4px 16px 16px 16px;
            border: 1px solid #f1f5f9;
        }
        .message-out {
            background-color: var(--primary);
            color: white;
            border-radius: 16px 16px 4px 16px;
        }
        /* Custom scroll heights */
        .chat-area-container {
            height: calc(100vh - 140px);
        }
        @media (max-width: 767px) {
            .chat-area-container {
                height: calc(100vh - 180px);
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50/50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar (Desktop) -->
        <aside class="sidebar hidden md:flex flex-shrink-0" role="navigation" aria-label="Sidebar Navigation">
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
        <div class="main-wrapper flex-1 flex flex-col h-full overflow-hidden pb-16 md:pb-0">
            <!-- Header -->
            <header class="top-header flex-shrink-0" role="banner">
                <div class="flex items-center gap-3">
                    <button class="md:hidden text-gray-500 hover:text-gray-700 transition" id="backToConversationsBtn" onclick="showConversations()" style="display: none;">
                        <i data-feather="arrow-left" class="w-5 h-5"></i>
                    </button>
                    <h1 class="page-title mb-0">Messages</h1>
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
            
            <!-- Message Panel Layout -->
            <main class="flex-1 overflow-hidden bg-slate-50 flex">
                <div class="flex w-full h-full">
                    <!-- Conversations list -->
                    <div class="w-full md:w-80 bg-white border-r border-gray-200 flex flex-col h-full flex-shrink-0" id="conversationsPanel">
                        <div class="p-4 border-b border-gray-100 flex-shrink-0">
                            <div class="relative">
                                <i data-feather="search" class="absolute left-3 top-2.5 w-4 h-4 text-gray-400"></i>
                                <input id="conversationSearch" type="search" placeholder="Search chats..." class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-blue-400 focus:bg-white transition" onkeyup="searchConversations()">
                            </div>
                        </div>
                        
                        <div id="conversationsList" class="overflow-y-auto flex-1 divide-y divide-gray-100">
                            <div class="p-8 text-center text-gray-400 flex flex-col items-center">
                                <i data-feather="loader" class="w-6 h-6 animate-spin text-blue-500 mb-2"></i>
                                <span class="text-xs">Loading conversations...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat area -->
                    <div class="flex-1 flex flex-col bg-slate-50 h-full hidden md:flex relative" id="chatArea">
                        <!-- Chat header -->
                        <div id="chatHeader" class="bg-white border-b border-gray-100 p-4 flex items-center justify-between flex-shrink-0 hidden">
                            <div class="flex items-center min-w-0">
                                <div class="flex-shrink-0 relative">
                                    <img id="chatHeaderAvatar" class="h-9 w-9 rounded-full object-cover border border-gray-100 shadow-sm" src="" alt="Partner avatar" data-fallback-name="User">
                                </div>
                                <div class="ml-3 min-w-0">
                                    <p id="chatHeaderName" class="text-sm font-bold text-gray-900 truncate"></p>
                                     <div class="flex items-center gap-1" id="chatHeaderStatus">
                                         <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                         <span class="text-[10px] text-gray-400 font-medium">Online</span>
                                     </div>
                                </div>
                            </div>
                            <div>
                                <button onclick="deleteChat()" title="Delete Chat" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition">
                                    <i data-feather="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Messages scroll area -->
                        <div id="messagesContainer" class="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50/50 chat-container">
                            <div class="h-full flex flex-col items-center justify-center text-gray-400 text-center px-4">
                                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center mb-3">
                                    <i data-feather="mail" class="w-6 h-6"></i>
                                </div>
                                <h3 class="text-sm font-bold text-gray-800">Your Inbox</h3>
                                <p class="text-xs text-gray-400 mt-1 max-w-[200px]">Select a connection from the list to start collaborating.</p>
                            </div>
                        </div>
                        
                        <!-- Message input bar -->
                        <div id="messageInputArea" class="bg-white border-t border-gray-100 p-4 flex-shrink-0 hidden">
                            <form id="messageForm" class="flex items-center gap-2" onsubmit="sendMessage(event)">
                                <input type="hidden" id="currentPartnerId" value="">
                                <input id="messageInput" class="form-input flex-1 py-2 text-xs rounded-full" placeholder="Type a message..." type="text" autocomplete="off">
                                <button type="submit" class="btn btn-primary w-9 h-9 p-0 flex items-center justify-center rounded-full flex-shrink-0">
                                    <i data-feather="send" class="w-4 h-4 text-white"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/global.js"></script>
    <script>
        feather.replace();

        let currentPartnerId = null;
        let lastMessageId = 0;
        let pollInterval = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
            
            // Check for partner_id in URL
            const urlParams = new URLSearchParams(window.location.search);
            const partnerId = urlParams.get('partner_id');
            if (partnerId) {
                startNewConversation(partnerId);
            }
            
            setInterval(loadConversations, 8000);
        });

        async function startNewConversation(partnerId) {
             try {
                const response = await fetch(`backend/get_user_info.php?id=${partnerId}`);
                const data = await response.json();
                
                if (data.user) {
                     openChat(partnerId, data.user.first_name + ' ' + data.user.last_name, data.user.avatar_url);
                }
             } catch(e) { 
                 console.error('Error starting conversation:', e); 
             }
        }

        function showConversations() {
            document.getElementById('conversationsPanel').classList.remove('hidden');
            document.getElementById('chatArea').classList.add('hidden');
            document.getElementById('backToConversationsBtn').style.display = 'none';
            if (window.innerWidth >= 768) {
                document.getElementById('chatArea').classList.remove('hidden');
            }
        }

        async function loadConversations() {
            try {
                const response = await fetch('backend/get_conversations.php');
                const data = await response.json();
                
                if (data.conversations) {
                    const list = document.getElementById('conversationsList');
                    
                    if (data.conversations.length === 0) {
                        list.innerHTML = '<div class="p-8 text-center text-gray-400 text-xs"><div class="empty-state-icon"><i data-feather="users" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i></div>No chats yet.<br>Go to <a href="partners.php" class="text-blue-500 font-bold hover:underline">Find Partners</a> to connect!</div>';
                        feather.replace();
                        return;
                    }

                    let html = '';
                    data.conversations.forEach(conv => {
                        const isActive = currentPartnerId == conv.id ? 'active' : '';
                        const lastMsg = conv.last_message ? conv.last_message : 'Tap to chat...';
                        const time = conv.last_message_time ? new Date(conv.last_message_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                        const unreadBadge = conv.unread_count > 0 ? `<span class="badge badge-blue text-[9px] py-0.5 ml-2 font-bold">${conv.unread_count}</span>` : '';
                        
                        html += `
                            <div class="conversation-item ${isActive} p-4 flex items-center cursor-pointer border-b border-slate-50" onclick="openChat(${conv.id}, '${conv.first_name} ${conv.last_name}', '${conv.avatar_url}')" data-name="${conv.first_name.toLowerCase()} ${conv.last_name.toLowerCase()}">
                                <div class="flex-shrink-0 relative">
                                    <img class="h-10 w-10 rounded-full object-cover border border-slate-100 shadow-sm" src="${conv.avatar_url || ''}" alt="" data-fallback-name="${conv.first_name}">
                                </div>
                                <div class="ml-3 flex-1 overflow-hidden min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold text-gray-900 truncate">${conv.first_name} ${conv.last_name}</p>
                                        <p class="text-[10px] text-gray-400 font-medium">${time}</p>
                                    </div>
                                    <div class="flex justify-between items-center mt-0.5">
                                        <p class="text-xs text-gray-500 truncate w-36 font-medium">${lastMsg}</p>
                                        ${unreadBadge}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    const scrollPos = list.scrollTop;
                    list.innerHTML = html;
                    list.scrollTop = scrollPos;

                    document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                        img.addEventListener('error', function () {
                            const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                            this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                        });
                        if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                    });
                }
            } catch (e) {
                console.error('Error loading conversations:', e);
            }
        }

        function searchConversations() {
            const query = document.getElementById('conversationSearch').value.toLowerCase().trim();
            const items = document.querySelectorAll('.conversation-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name && name.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function openChat(partnerId, name, avatar) {
            currentPartnerId = partnerId;
            document.getElementById('currentPartnerId').value = partnerId;
            
            // UI Updates
            document.getElementById('chatHeader').classList.remove('hidden');
            document.getElementById('messageInputArea').classList.remove('hidden');
            document.getElementById('chatHeaderName').textContent = name;
            
            const headerAvatar = document.getElementById('chatHeaderAvatar');
            headerAvatar.src = avatar || '';
            headerAvatar.setAttribute('data-fallback-name', name);
            
            // Mobile handling
            if (window.innerWidth < 768) {
                document.getElementById('conversationsPanel').classList.add('hidden');
                document.getElementById('chatArea').classList.remove('hidden');
                document.getElementById('backToConversationsBtn').style.display = 'block';
            }
            
            document.getElementById('messagesContainer').innerHTML = '<div class="h-full flex items-center justify-center"><i data-feather="loader" class="w-6 h-6 animate-spin text-blue-500"></i></div>';
            feather.replace();
            lastMessageId = 0;
            
            if (pollInterval) clearInterval(pollInterval);
            
            loadMessages();
            pollInterval = setInterval(loadMessages, 3000);
            
            loadConversations();
        }

        async function loadMessages() {
            if (!currentPartnerId) return;
            
            try {
                const url = `backend/get_messages.php?partner_id=${currentPartnerId}` + (lastMessageId > 0 ? `&last_id=${lastMessageId}` : '');
                
                const response = await fetch(url);
                const data = await response.json();
                const container = document.getElementById('messagesContainer');
                
                // Update online status badge (Creative Feature #13)
                const statusBox = document.getElementById('chatHeaderStatus');
                if (statusBox) {
                    if (data.is_online) {
                        statusBox.innerHTML = `
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            <span class="text-[10px] text-gray-400 font-medium">Online</span>
                        `;
                    } else {
                        statusBox.innerHTML = `
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                            <span class="text-[10px] text-gray-400 font-medium">Offline</span>
                        `;
                    }
                }
                
                if (data.messages && data.messages.length > 0) {
                    if (lastMessageId === 0) {
                        container.innerHTML = '';
                    }
                    
                    let html = '';
                    const myId = <?php echo $current_user_id; ?>;
                    
                    data.messages.forEach(msg => {
                        const isMe = msg.sender_id == myId;
                        const divClass = isMe ? 'justify-end' : 'justify-start';
                        const bubbleClass = isMe ? 'message-out' : 'message-in';
                        const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        html += `
                            <div class="flex w-full ${divClass} mb-3.5 group animate-fadeInUp" id="msg-${msg.id}">
                                ${!isMe ? `<div class="flex-shrink-0 mr-2.5"><img class="h-7 w-7 rounded-full object-cover border border-slate-100" src="${msg.avatar_url || ''}" data-fallback-name="${msg.sender_name || 'U'}" alt=""></div>` : ''}
                                <div class="max-w-[70%] flex flex-col items-${isMe ? 'end' : 'start'}">
                                    <div class="flex items-center">
                                        ${isMe ? `<button onclick="unsendMessage(${msg.id})" title="Unsend Message" class="mr-2 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition focus:outline-none"><i data-feather="x-circle" class="w-3.5 h-3.5"></i></button>` : ''}
                                        <div class="message-bubble ${bubbleClass}">
                                            <p class="text-xs leading-relaxed break-words">${escapeHtml(msg.message)}</p>
                                        </div>
                                    </div>
                                    <p class="text-[9px] text-gray-400 mt-1 font-medium ${isMe ? 'text-right' : ''}">${time}${isMe ? `<span class="msg-status ml-1" data-id="${msg.id}">${msg.id <= data.last_read_id ? '· Seen' : '· Sent'}</span>` : ''}</p>
                                </div>
                            </div>
                        `;
                        
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                    });
                    
                    container.insertAdjacentHTML('beforeend', html);

                    document.querySelectorAll('img[data-fallback-name]').forEach(img => {
                        img.addEventListener('error', function () {
                            const name = encodeURIComponent(this.dataset.fallbackName || 'U');
                            this.src = `https://ui-avatars.com/api/?name=${name}&background=4A90E2&color=fff&size=200&bold=true`;
                        });
                        if (img.complete && img.naturalWidth === 0) img.dispatchEvent(new Event('error'));
                    });

                    feather.replace();
                    container.scrollTop = container.scrollHeight;
                } else if (lastMessageId === 0) {
                    container.innerHTML = '<div class="h-full flex flex-col items-center justify-center text-gray-400 text-xs py-12"><i data-feather="message-circle" class="w-8 h-8 mb-2 text-slate-300"></i>No messages yet. Say hello!</div>';
                    feather.replace();
                }
                
                if (data.last_read_id > 0) {
                    document.querySelectorAll('.msg-status').forEach(el => {
                        const id = parseInt(el.getAttribute('data-id'));
                        if (id <= data.last_read_id && el.innerText.includes('Sent')) {
                            el.innerText = '· Seen';
                        }
                    });
                }
                
            } catch (e) {
                console.error('Error loading messages:', e);
            }
        }

        async function sendMessage(e) {
            e.preventDefault();
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentPartnerId) return;
            input.value = '';
            
            try {
                const response = await fetch('backend/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        receiver_id: currentPartnerId,
                        message: message
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    loadMessages();
                    loadConversations();
                } else {
                    showToast('Failed to send message: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                console.error('Error sending message:', e);
                showToast('Error sending message', 'error');
            }
        }

        async function unsendMessage(messageId) {
            if (!confirm('Are you sure you want to unsend this message?')) return;
            
            try {
                const response = await fetch('backend/unsend_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: messageId })
                });
                const result = await response.json();
                
                if (result.success) {
                    const msgEl = document.getElementById('msg-' + messageId);
                    if (msgEl) msgEl.remove();
                    showToast('Message unsent.', 'info');
                    loadConversations();
                } else {
                    showToast(result.error || 'Failed to unsend message', 'error');
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function deleteChat() {
            if (!currentPartnerId) return;
            if (!confirm('Are you sure you want to delete this entire conversation? This action cannot be undone.')) return;
            
            try {
                const response = await fetch('backend/delete_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner_id: currentPartnerId })
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('Conversation deleted.', 'info');
                    document.getElementById('chatHeader').classList.add('hidden');
                    document.getElementById('messageInputArea').classList.add('hidden');
                    document.getElementById('messagesContainer').innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-gray-400 text-center px-4">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center mb-3">
                                <i data-feather="mail" class="w-6 h-6"></i>
                            </div>
                            <h3 class="text-sm font-bold text-gray-800">Your Inbox</h3>
                            <p class="text-xs text-gray-400 mt-1 max-w-[200px]">Select a connection from the list to start collaborating.</p>
                        </div>
                    `;
                    feather.replace();
                    if (pollInterval) clearInterval(pollInterval);
                    currentPartnerId = null;
                    if (window.innerWidth < 768) {
                        showConversations();
                    }
                    loadConversations();
                } else {
                    showToast(result.error || 'Failed to delete chat', 'error');
                }
            } catch (e) {
                console.error(e);
            }
        }

        function searchConversations() {
            const query = document.getElementById('conversationSearch').value.toLowerCase().trim();
            const items = document.querySelectorAll('#conversationsList > div[onclick]');
            
            items.forEach(item => {
                const nameEl = item.querySelector('p.text-sm.font-semibold') || item.querySelector('p.text-sm.font-medium') || item.querySelector('p');
                if (nameEl) {
                    const name = nameEl.textContent.toLowerCase();
                    if (name.includes(query)) {
                        item.style.setProperty('display', 'flex', 'important');
                    } else {
                        item.style.setProperty('display', 'none', 'important');
                    }
                }
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
    </script>
</body>
</html>
