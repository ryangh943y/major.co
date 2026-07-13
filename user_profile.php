<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}
require_once 'backend/db.php';

$current_user_id = $_SESSION['user_id'];
$target_user_id = $_GET['id'] ?? null;

if (!$target_user_id) {
    header('Location: partners.php');
    exit();
}

// If user clicks their own profile from partners (unlikely but possible), let them see it or redirect
// Actually, it's fine to show them their own public profile, or we can redirect to profile.php
if ($target_user_id == $current_user_id) {
    header('Location: profile.php');
    exit();
}

// 1. Get Target User Basic Info
$stmt = $pdo->prepare("SELECT id, first_name, last_name, avatar_url, bio, skills FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    header('Location: partners.php?error=notfound');
    exit();
}

$name = $target_user['first_name'] . ' ' . $target_user['last_name'];
$avatar_url = $target_user['avatar_url'] ?? 'http://static.photos/people/200x200/10';
$skills = !empty($target_user['skills']) ? json_decode($target_user['skills'], true) : [];
$bio = $target_user['bio'] ?? 'No bio available.';

// 2. Get Connection Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE (user_id = ? OR partner_id = ?) AND status = 'connected'");
$stmt->execute([$target_user_id, $target_user_id]);
$connection_count = $stmt->fetchColumn();

// 3. Check connection status with current user
$stmt = $pdo->prepare("SELECT status FROM connections WHERE (user_id = ? AND partner_id = ?) OR (user_id = ? AND partner_id = ?)");
$stmt->execute([$current_user_id, $target_user_id, $target_user_id, $current_user_id]);
$conn_status_row = $stmt->fetch();
$connection_status = $conn_status_row ? $conn_status_row['status'] : 'none';

// 4. Get Posts
$stmt = $pdo->prepare("
    SELECT p.id, p.content, p.image_url, p.created_at,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count
    FROM posts p 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$target_user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$post_count = count($posts);

// 5. Get Projects (Owned or Accepted Member)
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id, p.title, p.description, p.status, p.image_url, p.created_at,
           CASE WHEN p.user_id = ? THEN 'Owner' ELSE 'Member' END as role
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'accepted'
    WHERE p.user_id = ? OR (pm.user_id = ? AND pm.status = 'accepted')
    ORDER BY p.created_at DESC
");
$stmt->execute([$target_user_id, $target_user_id, $target_user_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Get User Ratings and Reviews
$stmt = $pdo->prepare("
    SELECT r.rating, r.review, r.created_at, p.title as project_title, u.first_name, u.last_name, u.avatar_url
    FROM user_ratings r
    JOIN projects p ON r.project_id = p.id
    JOIN users u ON r.rater_id = u.id
    WHERE r.ratee_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$target_user_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avg_rating = 0;
if (count($reviews) > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / count($reviews), 1);
}

$stars_distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $rev) {
    $rating = (int)$rev['rating'];
    if ($rating >= 1 && $rating <= 5) {
        $stars_distribution[$rating]++;
    }
}
$total_reviews = count($reviews);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($name); ?>'s Profile | ProjectCrew</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .gradient-header { background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%); }
        .post-card, .project-card { transition: all 0.2s ease; }
        .post-card:hover, .project-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="min-h-screen pb-10">

    <!-- Top Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <a href="partners.php" class="flex items-center text-gray-500 hover:text-gray-900 transition">
                        <i data-feather="arrow-left" class="w-5 h-5 mr-2"></i>
                        Back to Partners
                    </a>
                </div>
                <div class="flex items-center">
                    <span class="text-xl font-semibold text-gray-800 tracking-tight ml-4 md:ml-0">TeamSync</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        
        <!-- Profile Header -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-8">
            <div class="h-48 gradient-header relative"></div>
            <div class="px-8 pb-8 relative">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between -mt-16 sm:-mt-20 relative z-10">
                    <div class="flex items-end">
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="w-32 h-32 sm:w-40 sm:h-40 rounded-full border-4 border-white object-cover shadow-md bg-white">
                        <div class="ml-6 pb-2 hidden sm:block">
                            <div class="flex items-center gap-3">
                                <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($name); ?></h1>
                                <?php if ($avg_rating > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                        <i data-feather="star" class="w-4 h-4 mr-1 fill-current text-yellow-500"></i>
                                        <?php echo $avg_rating; ?> (<?php echo count($reviews); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-500 text-sm mt-1"><?php echo htmlspecialchars(substr($bio, 0, 100)); ?><?php echo strlen($bio) > 100 ? '...' : ''; ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-6 sm:mt-0 flex gap-3 pb-2">
                        <?php if ($connection_status === 'connected'): ?>
                            <button disabled class="bg-green-50 text-green-600 border border-green-200 px-6 py-2.5 rounded-lg text-sm font-medium flex items-center cursor-default">
                                <i data-feather="check" class="w-4 h-4 mr-2"></i> Connected
                            </button>
                        <?php elseif ($connection_status === 'pending'): ?>
                            <button disabled class="bg-yellow-50 text-yellow-600 border border-yellow-200 px-6 py-2.5 rounded-lg text-sm font-medium flex items-center cursor-default">
                                <i data-feather="clock" class="w-4 h-4 mr-2"></i> Pending
                            </button>
                        <?php else: ?>
                            <button onclick="connectUser(<?php echo $target_user_id; ?>)" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium flex items-center transition">
                                <i data-feather="user-plus" class="w-4 h-4 mr-2"></i> Connect
                            </button>
                        <?php endif; ?>
                        
                        <a href="messages.php?partner_id=<?php echo $target_user_id; ?>" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2.5 rounded-lg text-sm font-medium flex items-center transition shadow-sm">
                            <i data-feather="message-circle" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>

                <!-- Mobile Info -->
                <div class="mt-4 sm:hidden">
                    <div class="flex items-center gap-2 mb-1">
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($name); ?></h1>
                        <?php if ($avg_rating > 0): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i data-feather="star" class="w-3 h-3 mr-1 fill-current"></i> <?php echo $avg_rating; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-500 text-sm mt-1"><?php echo htmlspecialchars($bio); ?></p>
                </div>

                <!-- Stats Bar -->
                <div class="flex items-center gap-8 mt-8 pt-6 border-t border-gray-100">
                    <div class="text-center">
                        <span class="block text-2xl font-bold text-gray-900"><?php echo $connection_count; ?></span>
                        <span class="text-xs text-gray-500 uppercase tracking-wide font-medium">Connections</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-2xl font-bold text-gray-900"><?php echo $post_count; ?></span>
                        <span class="text-xs text-gray-500 uppercase tracking-wide font-medium">Posts</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-2xl font-bold text-gray-900"><?php echo count($projects); ?></span>
                        <span class="text-xs text-gray-500 uppercase tracking-wide font-medium">Projects</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Sidebar (Skills & Bio) -->
            <div class="lg:col-span-1 space-y-6">
                <!-- About -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                        <i data-feather="info" class="w-5 h-5 mr-2 text-blue-500"></i> About
                    </h3>
                    <p class="text-sm text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($bio)); ?></p>
                </div>

                <!-- Skills -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i data-feather="star" class="w-5 h-5 mr-2 text-orange-500"></i> Skills
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php if(empty($skills)): ?>
                            <p class="text-sm text-gray-500">No skills listed.</p>
                        <?php else: ?>
                            <?php foreach($skills as $skill): ?>
                                <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-full border border-blue-100">
                                    <?php echo htmlspecialchars($skill); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reviews & Ratings -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i data-feather="award" class="w-5 h-5 mr-2 text-yellow-500"></i> Reviews
                        </h3>
                        <?php if ($avg_rating > 0): ?>
                            <span class="text-sm font-bold text-gray-900 flex items-center">
                                <i data-feather="star" class="w-4 h-4 mr-1 text-yellow-500 fill-current"></i>
                                <?php echo $avg_rating; ?> / 5.0
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Rating distribution chart (Creative Feature #14) -->
                    <?php if ($total_reviews > 0): ?>
                        <div class="mb-5 pb-4 border-b border-gray-100 space-y-2">
                            <?php foreach ([5, 4, 3, 2, 1] as $star): 
                                $count = $stars_distribution[$star];
                                $pct = ($count / $total_reviews) * 100;
                            ?>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-10 text-gray-500 font-semibold flex items-center gap-0.5">
                                        <?php echo $star; ?> <i data-feather="star" class="w-3 h-3 text-yellow-400 fill-current"></i>
                                    </span>
                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-400 rounded-full transition-all duration-500" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span class="w-6 text-right text-gray-400 font-semibold"><?php echo $count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-4 max-h-80 overflow-y-auto pr-2">
                        <?php if(empty($reviews)): ?>
                            <p class="text-sm text-gray-500 text-center py-4 bg-gray-50 rounded-lg">No reviews yet.</p>
                        <?php else: ?>
                            <?php foreach($reviews as $rev): ?>
                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center">
                                            <img src="<?php echo htmlspecialchars($rev['avatar_url']); ?>" class="w-6 h-6 rounded-full mr-2">
                                            <span class="text-xs font-semibold text-gray-900"><?php echo htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']); ?></span>
                                        </div>
                                        <div class="flex text-yellow-400">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i data-feather="star" class="w-3 h-3 <?php echo $i <= $rev['rating'] ? 'fill-current' : 'text-gray-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mb-1 uppercase tracking-wider font-semibold"><?php echo htmlspecialchars($rev['project_title']); ?></p>
                                    <?php if(!empty($rev['review'])): ?>
                                        <p class="text-xs text-gray-700 italic">"<?php echo htmlspecialchars($rev['review']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Content (Projects & Posts) -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Projects Section -->
                <div>
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i data-feather="folder" class="w-5 h-5 mr-2 text-gray-500"></i>
                        Projects (<?php echo count($projects); ?>)
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php if(empty($projects)): ?>
                            <p class="text-sm text-gray-500 col-span-2 bg-white p-6 rounded-xl border border-gray-100 text-center">Not involved in any projects yet.</p>
                        <?php else: ?>
                            <?php foreach($projects as $proj): ?>
                                <div class="project-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex flex-col h-full">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-bold text-gray-900 leading-tight"><?php echo htmlspecialchars($proj['title']); ?></h3>
                                        <span class="text-[10px] uppercase tracking-wider font-semibold px-2 py-1 bg-gray-100 text-gray-600 rounded">
                                            <?php echo htmlspecialchars($proj['role']); ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mb-4 flex-grow"><?php echo htmlspecialchars(substr($proj['description'], 0, 80)); ?>...</p>
                                    <div class="flex justify-between items-center mt-auto pt-4 border-t border-gray-50">
                                        <span class="text-xs text-gray-400"><?php echo date('M j, Y', strtotime($proj['created_at'])); ?></span>
                                        <span class="text-xs font-medium <?php echo $proj['status'] == 'completed' ? 'text-green-600' : 'text-blue-600'; ?>">
                                            <?php echo ucfirst($proj['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Posts Section -->
                <div>
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i data-feather="message-square" class="w-5 h-5 mr-2 text-gray-500"></i>
                        Recent Posts
                    </h2>
                    
                    <div class="space-y-4">
                        <?php if(empty($posts)): ?>
                            <p class="text-sm text-gray-500 bg-white p-6 rounded-xl border border-gray-100 text-center">No posts yet.</p>
                        <?php else: ?>
                            <?php foreach($posts as $post): ?>
                                <div class="post-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                    <div class="p-4 flex items-center space-x-3 border-b border-gray-50">
                                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="w-10 h-10 rounded-full object-cover">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($name); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo date('M j, g:i a', strtotime($post['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <p class="text-sm text-gray-800 mb-3"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                        <?php if($post['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="w-full rounded-lg object-cover max-h-96">
                                        <?php endif; ?>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 flex items-center space-x-6">
                                        <span class="flex items-center text-gray-500 text-sm">
                                            <i data-feather="heart" class="w-4 h-4 mr-1.5 text-red-500"></i> <?php echo $post['like_count']; ?>
                                        </span>
                                        <span class="flex items-center text-gray-500 text-sm">
                                            <i data-feather="message-circle" class="w-4 h-4 mr-1.5 text-blue-500"></i> <?php echo $post['comment_count']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        feather.replace();

        async function connectUser(partnerId) {
            try {
                const response = await fetch('backend/add_connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner_id: partnerId })
                });

                const data = await response.json();
                if (!response.ok) {
                    showToast('Error: ' + (data.error || 'Failed to send connection request'), 'error');
                    return;
                }
                showToast(data.message || 'Connection request sent!', 'success');
                setTimeout(() => window.location.reload(), 800);
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to send connection request', 'error');
            }
        }
    </script>
    <script src="assets/js/global.js"></script>
</body>
</html>
