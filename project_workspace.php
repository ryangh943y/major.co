<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}
require_once 'backend/db.php';

$user_id = $_SESSION['user_id'];
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    header('Location: projects.php');
    exit();
}

// Get user data
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar_url FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Check if user is owner or accepted member
$stmt = $pdo->prepare("
    SELECT p.*, 
           CASE WHEN p.user_id = ? THEN 'owner' 
                WHEN pm.status = 'accepted' THEN 'member' 
                ELSE 'none' END as user_role
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ?
    WHERE p.id = ?
");
$stmt->execute([$user_id, $user_id, $project_id]);
$project = $stmt->fetch();

if (!$project || $project['user_role'] === 'none') {
    // Not authorized to view workspace
    header('Location: projects.php?error=unauthorized');
    exit();
}

$name = $current_user['first_name'] . ' ' . $current_user['last_name'];
$avatar_url = $current_user['avatar_url'] ?? 'http://static.photos/people/200x200/10';

// Get project members for sidebar
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.avatar_url, 'member' as role
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ? AND pm.status = 'accepted'
    UNION
    SELECT u.id, u.first_name, u.last_name, u.avatar_url, 'owner' as role
    FROM projects p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$project_id, $project_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - Workspace | ProjectCrew</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .chat-container { height: calc(100vh - 200px); }
        .chat-messages { height: calc(100% - 70px); overflow-y: auto; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="h-screen overflow-hidden flex flex-col">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 flex-shrink-0">
        <div class="flex items-center justify-between px-6 py-3">
            <div class="flex items-center">
                <a href="projects.php" class="text-gray-500 hover:text-gray-800 mr-4">
                    <i data-feather="arrow-left"></i>
                </a>
                <div class="w-10 h-10 rounded bg-blue-100 flex items-center justify-center text-blue-600 font-bold mr-3 overflow-hidden">
                    <?php if($project['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($project['image_url']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?php echo substr($project['title'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($project['title']); ?></h1>
                    <p class="text-xs text-gray-500">Workspace &bull; <?php echo count($members); ?> members</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex -space-x-2">
                    <?php foreach(array_slice($members, 0, 3) as $m): ?>
                        <img class="w-8 h-8 rounded-full border-2 border-white object-cover" src="<?php echo htmlspecialchars($m['avatar_url'] ?? 'http://static.photos/people/200x200/1'); ?>" alt="Member">
                    <?php endforeach; ?>
                    <?php if(count($members) > 3): ?>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-xs font-medium text-gray-600">+<?php echo count($members)-3; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Navigation -->
        <div class="w-20 md:w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
            <nav class="p-4 space-y-2">
                <button onclick="switchTab('chat')" id="nav-chat" class="w-full flex items-center px-4 py-3 bg-blue-50 text-blue-600 rounded-lg font-medium transition">
                    <i data-feather="message-circle" class="w-5 h-5 md:mr-3"></i>
                    <span class="hidden md:inline">Group Chat</span>
                </button>
                <button onclick="switchTab('files')" id="nav-files" class="w-full flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg font-medium transition">
                    <i data-feather="file" class="w-5 h-5 md:mr-3"></i>
                    <span class="hidden md:inline">Files & Docs</span>
                </button>
                <button onclick="switchTab('tasks')" id="nav-tasks" class="w-full flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg font-medium transition">
                    <i data-feather="trello" class="w-5 h-5 md:mr-3"></i>
                    <span class="hidden md:inline">Task Board</span>
                </button>
            </nav>
            
            <div class="mt-auto p-4 hidden md:block">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Members</h3>
                <div class="space-y-3 overflow-y-auto max-h-48 hide-scrollbar">
                    <?php foreach($members as $m): ?>
                    <div class="flex items-center">
                        <img src="<?php echo htmlspecialchars($m['avatar_url'] ?? 'http://static.photos/people/200x200/1'); ?>" class="w-8 h-8 rounded-full mr-3 object-cover">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></p>
                            <p class="text-xs text-gray-500 truncate"><?php echo ucfirst($m['role']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 bg-[#F5F7FA] relative flex flex-col">
            
            <!-- CHAT VIEW -->
            <div id="view-chat" class="flex-1 flex flex-col relative p-4 h-full">
                <div class="bg-white rounded-xl shadow-sm flex-1 flex flex-col overflow-hidden">
                    <div class="p-4 border-b border-gray-100 flex items-center">
                        <i data-feather="hash" class="w-5 h-5 text-gray-400 mr-2"></i>
                        <h2 class="font-medium text-gray-800">general-chat</h2>
                    </div>
                    
                    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-[#F8FAFC]">
                        <div class="text-center py-4">
                            <p class="text-xs text-gray-400 bg-gray-100 inline-block px-3 py-1 rounded-full">Start of conversation</p>
                        </div>
                        <!-- Messages will be loaded here -->
                    </div>
                    
                    <div class="p-4 bg-white border-t border-gray-100">
                        <form id="chatForm" onsubmit="sendMessage(event)" class="flex items-center gap-2">
                            <button type="button" onclick="openPollModal()" class="text-gray-400 hover:text-blue-600 transition p-2">
                                <i data-feather="bar-chart-2" class="w-5 h-5"></i>
                            </button>
                            <input type="text" id="messageInput" class="flex-1 border-gray-300 rounded-full pl-4 pr-4 py-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 border text-sm" placeholder="Message group..." required>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-2 w-10 h-10 flex items-center justify-center transition shadow-sm">
                                <i data-feather="send" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- FILES VIEW -->
            <div id="view-files" class="flex-1 flex flex-col hidden p-4 h-full overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Project Files</h2>
                    <label class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium cursor-pointer shadow-sm flex items-center">
                        <i data-feather="upload" class="w-4 h-4 mr-2"></i> Upload File
                        <input type="file" class="hidden" id="fileUpload" onchange="uploadFile(event)">
                    </label>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="relative px-6 py-3"><span class="sr-only">Download</span></th>
                            </tr>
                        </thead>
                        <tbody id="filesList" class="bg-white divide-y divide-gray-200">
                            <!-- Files will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TASKS KANBAN VIEW -->
            <div id="view-tasks" class="flex-1 flex flex-col hidden p-4 h-full overflow-y-auto">
                <!-- Tasks Progress Indicator -->
                <div class="bg-white rounded-xl p-4 border border-slate-200 mb-6 shadow-sm flex items-center justify-between reveal">
                    <div class="flex items-center gap-3">
                        <i data-feather="trending-up" class="w-5 h-5 text-green-500"></i>
                        <div>
                            <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Workspace Completion Progress</h3>
                            <p class="text-[10px] text-gray-400">Completion rate of project task milestones</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 w-1/2">
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-200">
                            <div id="project-progress-fill" class="bg-green-500 h-full rounded-full transition-all duration-500" style="width: 0%;"></div>
                        </div>
                        <span id="project-progress-percent" class="text-xs font-bold text-slate-700 min-w-[35px] text-right">0%</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Task Board</h2>
                        <p class="text-xs text-gray-500">Track milestones and team tasks</p>
                    </div>
                    <button onclick="openAddTaskModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm flex items-center transition">
                        <i data-feather="plus" class="w-4 h-4 mr-2"></i> Add Task
                    </button>
                </div>
                
                <!-- Kanban Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1 items-start min-h-[500px]">
                    <!-- Todo Column -->
                    <div class="bg-slate-100 rounded-2xl p-4 flex flex-col border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-200">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                <h3 class="text-sm font-bold text-gray-900">To Do</h3>
                            </div>
                            <span id="count-todo" class="bg-white px-2 py-0.5 rounded-full text-xs font-semibold text-slate-500 border border-slate-200">0</span>
                        </div>
                        <div id="column-todo" class="space-y-3 min-h-[350px] pb-6">
                            <!-- Todo cards -->
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="bg-slate-100 rounded-2xl p-4 flex flex-col border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-200">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                <h3 class="text-sm font-bold text-gray-900">In Progress</h3>
                            </div>
                            <span id="count-in_progress" class="bg-white px-2 py-0.5 rounded-full text-xs font-semibold text-slate-500 border border-slate-200">0</span>
                        </div>
                        <div id="column-in_progress" class="space-y-3 min-h-[350px] pb-6">
                            <!-- In Progress cards -->
                        </div>
                    </div>

                    <!-- Completed Column -->
                    <div class="bg-slate-100 rounded-2xl p-4 flex flex-col border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-200">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                                <h3 class="text-sm font-bold text-gray-900">Completed</h3>
                            </div>
                            <span id="count-completed" class="bg-white px-2 py-0.5 rounded-full text-xs font-semibold text-slate-500 border border-slate-200">0</span>
                        </div>
                        <div id="column-completed" class="space-y-3 min-h-[350px] pb-6">
                            <!-- Completed cards -->
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Task Modal -->
    <div id="taskModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="absolute w-full h-full bg-gray-900 opacity-40 backdrop-blur-sm" onclick="closeAddTaskModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Add Project Task</h3>
                    <button onclick="closeAddTaskModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form id="taskForm" class="space-y-4" onsubmit="createTask(event)">
                    <div>
                        <label class="form-label" for="taskTitle">Task Title</label>
                        <input type="text" id="taskTitle" class="form-input" placeholder="e.g. Build API Schema" required>
                    </div>
                    <div>
                        <label class="form-label" for="taskDesc">Description</label>
                        <textarea id="taskDesc" rows="3" class="form-input" placeholder="Describe the deliverables..."></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="taskAssignee">Assign To</label>
                        <select id="taskAssignee" class="form-input">
                            <option value="">Unassigned</option>
                            <?php foreach($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="taskPriority">Priority</label>
                            <select id="taskPriority" class="form-input">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="taskDueDate">Due Date</label>
                            <input type="date" id="taskDueDate" class="form-input">
                        </div>
                    </div>
                    
                    <div class="flex gap-3 pt-4 border-t border-gray-100">
                        <button type="submit" class="btn btn-primary flex-1">Create Task</button>
                        <button type="button" onclick="closeAddTaskModal()" class="btn btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div id="previewModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="absolute w-full h-full bg-gray-900 opacity-40 backdrop-blur-sm" onclick="closePreviewModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900" id="previewModalTitle">File Preview</h3>
                    <button onclick="closePreviewModal()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div id="previewModalContent" class="mt-2 min-h-[200px]">
                    <!-- Preview content loaded here dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Poll Modal -->
    <div id="pollModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="absolute w-full h-full bg-gray-900 opacity-50" onclick="closePollModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Create a Poll</h3>
                        <button onclick="closePollModal()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                            <i data-feather="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <form id="pollForm" class="space-y-4" onsubmit="submitPoll(event)">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                            <input type="text" id="pollQuestion" class="block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Ask a question..." required>
                        </div>
                        <div id="pollOptionsContainer" class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
                            <input type="text" class="poll-option block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Option 1" required>
                            <input type="text" class="poll-option block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Option 2" required>
                        </div>
                        <button type="button" onclick="addPollOption()" class="text-blue-600 text-sm font-medium hover:text-blue-800">+ Add Option</button>
                        
                        <div class="flex gap-3 pt-4">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white flex-1 py-2 rounded-lg text-sm font-medium">Create Poll</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
        const projectId = <?php echo $project_id; ?>;
        const currentUserId = <?php echo $user_id; ?>;

        function switchTab(tab) {
            document.getElementById('view-chat').classList.toggle('hidden', tab !== 'chat');
            document.getElementById('view-files').classList.toggle('hidden', tab !== 'files');
            document.getElementById('view-tasks').classList.toggle('hidden', tab !== 'tasks');
            
            document.getElementById('nav-chat').className = tab === 'chat' 
                ? 'w-full flex items-center px-4 py-3 bg-blue-50 text-blue-600 rounded-lg font-medium transition'
                : 'w-full flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg font-medium transition';
                
            document.getElementById('nav-files').className = tab === 'files'
                ? 'w-full flex items-center px-4 py-3 bg-blue-50 text-blue-600 rounded-lg font-medium transition'
                : 'w-full flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg font-medium transition';

            document.getElementById('nav-tasks').className = tab === 'tasks'
                ? 'w-full flex items-center px-4 py-3 bg-blue-50 text-blue-600 rounded-lg font-medium transition'
                : 'w-full flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg font-medium transition';
                
            if(tab === 'files') loadFiles();
            if(tab === 'tasks') loadTasks();
        }

        // Chat Logic
        async function loadMessages() {
            try {
                const res = await fetch(`backend/get_project_messages.php?project_id=${projectId}`);
                const data = await res.json();
                if(data.success) {
                    const container = document.getElementById('chat-messages');
                    const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 50;
                    
                    let html = `<div class="text-center py-4"><p class="text-xs text-gray-400 bg-gray-100 inline-block px-3 py-1 rounded-full">Start of conversation</p></div>`;
                    
                    data.messages.forEach(msg => {
                        const isMe = msg.user_id == currentUserId;
                        const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        let messageContent = `<p class="text-sm">${msg.message}</p>`;
                        
                        // If it's a poll, render poll UI
                        if (msg.poll_id && msg.poll_data) {
                            const poll = msg.poll_data;
                            let optionsHtml = '';
                            poll.options.forEach(opt => {
                                const percent = poll.total_votes > 0 ? Math.round((opt.vote_count / poll.total_votes) * 100) : 0;
                                const isVotedClass = opt.user_voted ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50';
                                optionsHtml += `
                                    <div onclick="votePoll(${msg.poll_id}, ${opt.id})" class="relative mt-2 border rounded-lg p-2 cursor-pointer transition ${isVotedClass}">
                                        <div class="absolute left-0 top-0 bottom-0 bg-blue-100 rounded-lg transition-all z-0" style="width: ${percent}%; opacity: 0.5;"></div>
                                        <div class="relative z-10 flex justify-between items-center">
                                            <span class="text-sm font-medium ${opt.user_voted ? 'text-blue-800' : 'text-gray-700'}">${opt.text}</span>
                                            <span class="text-xs text-gray-500">${percent}%</span>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            messageContent = `
                                <div class="w-full">
                                    <div class="flex items-center mb-2">
                                        <i data-feather="bar-chart-2" class="w-4 h-4 mr-1 text-blue-500"></i>
                                        <span class="text-xs font-semibold uppercase text-blue-500">Poll</span>
                                    </div>
                                    <p class="font-medium text-gray-900 mb-2">${poll.question}</p>
                                    <div class="space-y-1">
                                        ${optionsHtml}
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 text-right">${poll.total_votes} votes</p>
                                </div>
                            `;
                        }
                        
                        if(isMe) {
                            html += `
                            <div class="flex justify-end items-end space-x-2">
                                <div class="${msg.poll_id ? 'bg-white border border-gray-200 text-gray-800' : 'bg-blue-600 text-white'} p-3 rounded-2xl rounded-tr-none min-w-[200px] max-w-md shadow-sm">
                                    ${messageContent}
                                    <p class="text-[10px] ${msg.poll_id ? 'text-gray-400' : 'text-blue-200'} mt-1 text-right">${time}</p>
                                </div>
                            </div>`;
                        } else {
                            html += `
                            <div class="flex justify-start items-end space-x-2">
                                <img src="${msg.avatar_url || 'http://static.photos/people/200x200/10'}" class="w-8 h-8 rounded-full object-cover">
                                <div class="bg-white border border-gray-100 text-gray-800 p-3 rounded-2xl rounded-tl-none min-w-[200px] max-w-md shadow-sm">
                                    <p class="text-[11px] font-semibold text-gray-500 mb-1">${msg.first_name} ${msg.last_name}</p>
                                    ${messageContent}
                                    <p class="text-[10px] text-gray-400 mt-1">${time}</p>
                                </div>
                            </div>`;
                        }
                    });
                    
                    container.innerHTML = html;
                    feather.replace();
                    if (isScrolledToBottom) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            } catch(e) { console.error(e); }
        }

        async function sendMessage(e) {
            e.preventDefault();
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if(!message) return;
            
            input.value = '';
            
            try {
                const res = await fetch('backend/send_project_message.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({project_id: projectId, message: message})
                });
                const data = await res.json();
                if(data.success) {
                    loadMessages();
                }
            } catch(err) { console.error(err); }
        }

        // File Logic
        async function loadFiles() {
            try {
                const res = await fetch(`backend/get_project_files.php?project_id=${projectId}`);
                const data = await res.json();
                if(data.success) {
                    const list = document.getElementById('filesList');
                    if(data.files.length === 0) {
                        list.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No files uploaded yet.</td></tr>`;
                        return;
                    }
                    
                    list.innerHTML = data.files.map(f => {
                        const date = new Date(f.uploaded_at).toLocaleDateString();
                        const size = (f.file_size / 1024).toFixed(1) + ' KB';
                        return `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i data-feather="file" class="w-5 h-5 text-gray-400 mr-3"></i>
                                    <div class="text-sm font-medium text-gray-900">${f.file_name}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${f.first_name} ${f.last_name}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${date} (${size})</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                                <button onclick="previewFile('${escapeHTML(f.file_path)}', '${escapeHTML(f.file_name)}')" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-3 py-1 rounded text-xs font-semibold">Preview</button>
                                <a href="${f.file_path}" target="_blank" download class="text-blue-600 hover:text-blue-900 bg-blue-50 px-3 py-1 rounded text-xs font-semibold">Download</a>
                            </td>
                        </tr>
                        `;
                    }).join('');
                    feather.replace();
                }
            } catch(e) { console.error(e); }
        }

        async function uploadFile(e) {
            const file = e.target.files[0];
            if(!file) return;
            
            const formData = new FormData();
            formData.append('project_id', projectId);
            formData.append('file', file);
            
            try {
                // We'll need a backend script for this. If it's just dummy for now, let's pretend.
                const res = await fetch('backend/upload_project_file.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if(data.success) {
                    loadFiles();
                } else {
                    showToast('Upload failed: ' + data.error, 'error');
                }
            } catch(err) { console.error(err); }
        }

        // Poll Logic
        function openPollModal() {
            document.getElementById('pollModal').classList.remove('hidden');
        }

        function closePollModal() {
            document.getElementById('pollModal').classList.add('hidden');
            document.getElementById('pollForm').reset();
            const container = document.getElementById('pollOptionsContainer');
            container.innerHTML = `
                <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
                <input type="text" class="poll-option block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Option 1" required>
                <input type="text" class="poll-option block w-full border border-gray-300 rounded-md py-2 px-3 mt-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Option 2" required>
            `;
        }

        function addPollOption() {
            const container = document.getElementById('pollOptionsContainer');
            const inputs = container.querySelectorAll('.poll-option');
            const newIndex = inputs.length + 1;
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'poll-option block w-full border border-gray-300 rounded-md py-2 px-3 mt-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm';
            input.placeholder = `Option ${newIndex}`;
            input.required = true;
            container.appendChild(input);
        }

        async function submitPoll(e) {
            e.preventDefault();
            const question = document.getElementById('pollQuestion').value.trim();
            const optionInputs = document.querySelectorAll('.poll-option');
            const options = Array.from(optionInputs).map(i => i.value.trim()).filter(v => v !== '');

            if (options.length < 2) {
                showToast("Please provide at least 2 options.", "warning");
                return;
            }

            try {
                const res = await fetch('backend/create_project_poll.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ project_id: projectId, question: question, options: options })
                });
                const data = await res.json();
                if(data.success) {
                    closePollModal();
                    loadMessages();
                } else {
                    showToast('Error creating poll: ' + data.error, 'error');
                }
            } catch(err) { console.error(err); }
        }

        async function votePoll(pollId, optionId) {
            try {
                const res = await fetch('backend/vote_project_poll.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ poll_id: pollId, option_id: optionId })
                });
                const data = await res.json();
                if(data.success) {
                    loadMessages();
                }
            } catch(err) { console.error(err); }
        }

        // Kanban Tasks Logic
        function openAddTaskModal() {
            document.getElementById('taskModal').classList.remove('hidden');
        }

        function closeAddTaskModal() {
            document.getElementById('taskModal').classList.add('hidden');
            document.getElementById('taskForm').reset();
        }

        async function loadTasks() {
            try {
                const res = await fetch(`backend/get_project_tasks.php?project_id=${projectId}`);
                const data = await res.json();
                if (data.success) {
                    const columns = {
                        todo: document.getElementById('column-todo'),
                        in_progress: document.getElementById('column-in_progress'),
                        completed: document.getElementById('column-completed')
                    };
                    const counts = { todo: 0, in_progress: 0, completed: 0 };
                    
                    // Clear columns
                    Object.values(columns).forEach(col => col.innerHTML = '');

                    // Calculate and render progress rate
                    const totalTasksCount = data.tasks.length;
                    const completedTasksCount = data.tasks.filter(t => t.status === 'completed').length;
                    const progressPercent = totalTasksCount > 0 ? Math.round((completedTasksCount / totalTasksCount) * 100) : 0;
                    
                    document.getElementById('project-progress-fill').style.width = `${progressPercent}%`;
                    document.getElementById('project-progress-percent').textContent = `${progressPercent}%`;

                    data.tasks.forEach(task => {
                        counts[task.status]++;
                        const card = document.createElement('div');
                        card.className = 'bg-white rounded-xl p-4 border border-slate-200 shadow-sm relative group hover:border-blue-400 transition animate-fadeInUp';
                        
                        const assigneeName = task.assigned_to 
                            ? `${task.first_name} ${task.last_name}` 
                            : 'Unassigned';
                            
                        const assigneeInitial = task.assigned_to 
                            ? task.first_name[0] 
                            : '?';

                        const avatarHtml = task.assigned_to
                            ? `<img src="${escapeHTML(task.avatar_url)}" class="w-6 h-6 rounded-full object-cover" title="Assigned to: ${escapeHTML(assigneeName)}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(assigneeName)}&background=4A90E2&color=fff&size=50'">`
                            : `<div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-400 border border-dashed border-slate-300" title="Unassigned">?</div>`;

                        // Priority badges
                        let prioClass = 'bg-slate-100 text-slate-600';
                        if (task.priority === 'high') prioClass = 'bg-red-50 text-red-600 border border-red-100';
                        else if (task.priority === 'medium') prioClass = 'bg-indigo-50 text-indigo-600 border border-indigo-100';
                        else if (task.priority === 'low') prioClass = 'bg-green-50 text-green-600 border border-green-100';
                        
                        const prioBadge = `<span class="text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${prioClass}">${escapeHTML(task.priority || 'medium')}</span>`;

                        // Due Date badge
                        const dueDateHtml = task.due_date
                            ? `<div class="flex items-center text-[9px] font-medium text-slate-400 mt-2"><i data-feather="calendar" class="w-3 h-3 mr-1"></i> Due: ${escapeHTML(task.due_date)}</div>`
                            : '';

                        let moveButtonsHtml = '';
                        if (task.status === 'todo') {
                            moveButtonsHtml = `
                                <button onclick="moveTask(${task.id}, 'in_progress')" class="text-xs font-semibold text-blue-500 hover:text-blue-600 transition flex items-center">
                                    Start <i data-feather="chevron-right" class="w-3.5 h-3.5 ml-0.5"></i>
                                </button>`;
                        } else if (task.status === 'in_progress') {
                            moveButtonsHtml = `
                                <button onclick="moveTask(${task.id}, 'todo')" class="text-xs font-semibold text-slate-500 hover:text-slate-600 transition flex items-center mr-3">
                                    <i data-feather="chevron-left" class="w-3.5 h-3.5 mr-0.5"></i> Back
                                </button>
                                <button onclick="moveTask(${task.id}, 'completed')" class="text-xs font-semibold text-green-500 hover:text-green-600 transition flex items-center">
                                    Done <i data-feather="check" class="w-3.5 h-3.5 ml-0.5"></i>
                                </button>`;
                        } else if (task.status === 'completed') {
                            moveButtonsHtml = `
                                <button onclick="moveTask(${task.id}, 'in_progress')" class="text-xs font-semibold text-slate-500 hover:text-slate-600 transition flex items-center">
                                    <i data-feather="chevron-left" class="w-3.5 h-3.5 mr-0.5"></i> Reopen
                                </button>`;
                        }

                        card.innerHTML = `
                            <div class="flex justify-between items-start gap-4 mb-2">
                                <div class="flex flex-col gap-1.5 min-w-0">
                                    ${prioBadge}
                                    <h4 class="text-xs font-bold text-gray-800 leading-tight">${escapeHTML(task.title)}</h4>
                                </div>
                                <button onclick="deleteTask(${task.id})" class="text-gray-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100" title="Delete Task">
                                    <i data-feather="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-500 leading-relaxed mb-1">${escapeHTML(task.description || 'No description provided.')}</p>
                            ${dueDateHtml}
                            <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    ${avatarHtml}
                                    <span class="text-[10px] text-gray-400 font-medium truncate" style="max-width: 80px;">${escapeHTML(assigneeName)}</span>
                                </div>
                                <div class="flex items-center">
                                    ${moveButtonsHtml}
                                </div>
                            </div>
                        `;
                        
                        columns[task.status].appendChild(card);
                    });

                    // Update counts
                    Object.keys(counts).forEach(status => {
                        document.getElementById(`count-${status}`).textContent = counts[status];
                    });
                    
                    feather.replace();
                }
            } catch (err) {
                console.error("Failed to load tasks", err);
            }
        }

        async function createTask(e) {
            e.preventDefault();
            const title = document.getElementById('taskTitle').value.trim();
            const description = document.getElementById('taskDesc').value.trim();
            const assigned_to = document.getElementById('taskAssignee').value;
            const priority = document.getElementById('taskPriority').value;
            const due_date = document.getElementById('taskDueDate').value;

            try {
                const res = await fetch('backend/create_project_task.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ project_id: projectId, title, description, assigned_to, priority, due_date })
                });
                const data = await res.json();
                if (data.success) {
                    closeAddTaskModal();
                    loadTasks();
                    showToast('Task created successfully.', 'success');
                } else {
                    showToast(data.error || 'Failed to create task.', 'error');
                }
            } catch (err) {
                showToast('Error creating task.', 'error');
            }
        }

        async function moveTask(taskId, newStatus) {
            try {
                const res = await fetch('backend/update_project_task.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ task_id: taskId, status: newStatus })
                });
                const data = await res.json();
                if (data.success) {
                    loadTasks();
                } else {
                    showToast(data.error || 'Failed to update task status.', 'error');
                }
            } catch (err) {
                showToast('Error updating task.', 'error');
            }
        }

        async function deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) return;
            try {
                const res = await fetch('backend/delete_project_task.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ task_id: taskId })
                });
                const data = await res.json();
                if (data.success) {
                    loadTasks();
                    showToast('Task deleted successfully.', 'success');
                } else {
                    showToast(data.error || 'Failed to delete task.', 'error');
                }
            } catch (err) {
                showToast('Error deleting task.', 'error');
            }
        }

        function escapeHTML(str) {
            if (!str) return '';
            return str.replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        // File Preview Helpers (Necessary Feature #1)
        function previewFile(path, name) {
            const extension = name.split('.').pop().toLowerCase();
            const container = document.getElementById('previewModalContent');
            const title = document.getElementById('previewModalTitle');
            title.textContent = name;
            
            container.innerHTML = '';
            
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension)) {
                container.innerHTML = `<img src="${path}" class="max-w-full max-h-[70vh] object-contain rounded-lg mx-auto shadow">`;
            } else if (extension === 'pdf') {
                container.innerHTML = `<iframe src="${path}" class="w-full h-[65vh] border-0 rounded-lg"></iframe>`;
            } else if (['txt', 'js', 'css', 'php', 'html', 'json', 'py', 'md'].includes(extension)) {
                container.innerHTML = `<div class="p-4 bg-slate-50 border border-slate-100 rounded-xl overflow-auto text-left text-xs font-mono max-h-[60vh] text-slate-800" id="previewTextContent">Loading content...</div>`;
                fetch(path)
                    .then(res => {
                        if (!res.ok) throw new Error('Could not load file');
                        return res.text();
                    })
                    .then(text => {
                        document.getElementById('previewTextContent').textContent = text;
                    })
                    .catch(err => {
                        document.getElementById('previewTextContent').textContent = 'Error loading file content: ' + err.message;
                    });
            } else {
                container.innerHTML = `
                    <div class="text-center py-10 text-gray-500">
                        <i data-feather="alert-circle" class="w-12 h-12 mx-auto mb-2 text-gray-400"></i>
                        <p class="text-sm font-semibold">Preview not supported for this file type.</p>
                        <a href="${path}" download class="btn btn-primary btn-sm mt-4 inline-flex">Download instead</a>
                    </div>
                `;
                feather.replace();
            }
            
            document.getElementById('previewModal').classList.remove('hidden');
        }
        
        function closePreviewModal() {
            document.getElementById('previewModal').classList.add('hidden');
            document.getElementById('previewModalContent').innerHTML = '';
        }

        // Initialize
        loadMessages();
        // Poll for new messages every 3 seconds
        setInterval(() => {
            if(!document.getElementById('view-chat').classList.contains('hidden')) {
                loadMessages();
            }
        }, 3000);
    </script>
    <script src="assets/js/global.js"></script>
</body>
</html>
