<?php
// backend/get_project_messages.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit();
}

// Ensure user is authorized
$stmt = $pdo->prepare("
    SELECT 1 FROM projects p 
    LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ? AND pm.status = 'accepted'
    WHERE p.id = ? AND (p.user_id = ? OR pm.id IS NOT NULL)
");
$stmt->execute([$user_id, $project_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT m.id as message_id, m.message, m.poll_id, m.created_at, u.id as user_id, u.first_name, u.last_name, u.avatar_url
        FROM project_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.project_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$project_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If there are polls, fetch their details
    $poll_ids = array_filter(array_column($messages, 'poll_id'));
    $polls_data = [];
    if (!empty($poll_ids)) {
        $in = str_repeat('?,', count($poll_ids) - 1) . '?';
        
        // Fetch polls
        $stmtPolls = $pdo->prepare("SELECT id, question FROM project_polls WHERE id IN ($in)");
        $stmtPolls->execute(array_values($poll_ids));
        $polls = $stmtPolls->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch options
        $stmtOptions = $pdo->prepare("
            SELECT o.id, o.poll_id, o.option_text, 
                   COUNT(v.id) as vote_count,
                   SUM(CASE WHEN v.user_id = ? THEN 1 ELSE 0 END) as user_voted
            FROM project_poll_options o
            LEFT JOIN project_poll_votes v ON o.id = v.option_id
            WHERE o.poll_id IN ($in)
            GROUP BY o.id
        ");
        $params = array_merge([$user_id], array_values($poll_ids));
        $stmtOptions->execute($params);
        $options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($polls as $p) {
            $polls_data[$p['id']] = [
                'question' => $p['question'],
                'options' => []
            ];
        }
        
        foreach ($options as $o) {
            $polls_data[$o['poll_id']]['options'][] = [
                'id' => $o['id'],
                'text' => $o['option_text'],
                'vote_count' => (int)$o['vote_count'],
                'user_voted' => (bool)$o['user_voted']
            ];
        }
    }

    foreach ($messages as &$msg) {
        if ($msg['poll_id']) {
            $msg['poll_data'] = $polls_data[$msg['poll_id']] ?? null;
            // Calculate total votes for percentages
            $total_votes = 0;
            if ($msg['poll_data']) {
                foreach ($msg['poll_data']['options'] as $opt) {
                    $total_votes += $opt['vote_count'];
                }
                $msg['poll_data']['total_votes'] = $total_votes;
            }
        }
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
