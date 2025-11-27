<?php
session_start();
require_once 'config.php';

// Check if user is authenticated
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

// Get item ID and type from URL with validation
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Validate item ID and type
if ($item_id <= 0 || !in_array($type, ['assignment', 'material'])) {
    header('Location: ' . ($user['role'] === 'teacher' ? 'teacher-dashboard.php' : 'student-dashboard.php'));
    exit;
}

// Fetch item details
if ($type === 'assignment') {
    $stmt = $db->prepare("SELECT a.*, s.subject_name FROM assignments a LEFT JOIN subjects s ON a.subject_id = s.id WHERE a.id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    $item_title = $item['title'] ?? 'Assignment';
} else {
    $stmt = $db->prepare("SELECT * FROM learning_materials WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    $item_title = $item['title'] ?? 'Material';
}

if (!$item) {
    header('Location: ' . ($user['role'] === 'teacher' ? 'teacher-dashboard.php' : 'student-dashboard.php'));
    exit;
}

// Fetch all comments for this item
$comments = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               CASE 
                   WHEN c.user_role = 'student' THEN s.full_name
                   WHEN c.user_role = 'teacher' THEN t.full_name
                   ELSE 'Unknown User'
               END as user_name,
               CASE 
                   WHEN c.user_role = 'student' THEN s.student_id
                   WHEN c.user_role = 'teacher' THEN t.id
                   ELSE ''
               END as user_identifier
        FROM comments c
        LEFT JOIN students s ON c.user_id = s.id AND c.user_role = 'student'
        LEFT JOIN teachers t ON c.user_id = t.id AND c.user_role = 'teacher'
        WHERE c.item_id = ? AND c.item_type = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$item_id, $type]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database error fetching comments: ' . $e->getMessage());
}

// Determine back URL
$backUrl = "view-detail.php?id=$item_id&type=$type&subject_id=$subject_id";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comments - <?php echo htmlspecialchars($item_title); ?> - ALLSHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
<header class="bg-white shadow-md">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <img src="" alt="ALLSHS" class="h-10">
            <h1 class="text-xl font-bold text-blue-800">Angelo Levardo SHS</h1>
        </div>
        <div class="flex items-center space-x-2">
            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                <?php echo substr($user['name'] ?? $user['full_name'] ?? 'US', 0, 2); ?>
            </div>
            <div class="text-right">
                <span class="font-medium block"><?php echo htmlspecialchars($user['name'] ?? $user['full_name'] ?? 'User'); ?></span>
                <span class="text-sm text-gray-600"><?php echo ucfirst($user['role']); ?></span>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-4 py-6">
    <!-- Back link -->
    <div class="mb-6">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" 
           class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to <?php echo $type === 'material' ? 'Material' : 'Assignment'; ?>
        </a>
    </div>

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                Comments - <?php echo htmlspecialchars($item_title); ?>
            </h1>
            <p class="text-gray-600">
                <?php echo $type === 'material' ? 'Learning Material' : 'Assignment'; ?> • 
                <?php echo count($comments); ?> comment<?php echo count($comments) !== 1 ? 's' : ''; ?>
            </p>
        </div>

        <!-- Add Comment Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Add a Comment</h3>
            <form id="commentForm">
                <div class="mb-4">
                    <textarea 
                        id="commentText" 
                        name="comment"
                        rows="4"
                        placeholder="Share your thoughts, ask questions, or provide feedback..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    ></textarea>
                </div>
                <div class="flex justify-end">
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <i class="fas fa-paper-plane mr-2"></i>Post Comment
                    </button>
                </div>
            </form>
        </div>

        <!-- Comments List -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    All Comments (<?php echo count($comments); ?>)
                </h3>
            </div>

            <div id="commentsList" class="p-6 space-y-6">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="border-b border-gray-200 pb-6 last:border-b-0">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-<?php echo $comment['user_role'] === 'teacher' ? 'chalkboard-teacher' : 'user-graduate'; ?> text-blue-600"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800">
                                            <?php echo htmlspecialchars($comment['user_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo ucfirst($comment['user_role']); ?>
                                            <?php if ($comment['user_identifier']): ?>
                                                • <?php echo htmlspecialchars($comment['user_identifier']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                </div>
                            </div>
                            <p class="text-gray-700 text-sm leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-comments text-4xl mb-4"></i>
                        <h4 class="font-medium text-gray-600 mb-2">No Comments Yet</h4>
                        <p class="text-sm">Be the first to start a discussion about this <?php echo $type === 'material' ? 'material' : 'assignment'; ?>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle comment form submission
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const commentText = document.getElementById('commentText').value.trim();
            
            if (!commentText) {
                alert('Please enter a comment.');
                return;
            }

            fetch('add-comment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'item_id=' + encodeURIComponent(<?php echo $item_id; ?>) +
                      '&type=' + encodeURIComponent('<?php echo $type; ?>') +
                      '&comment=' + encodeURIComponent(commentText)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('commentText').value = '';
                    location.reload(); // Reload to show new comment
                } else {
                    alert(data.message || 'Failed to post comment.');
                }
            })
            .catch(() => {
                alert('Error posting comment.');
            });
        });
    }
</script>
</body>
</html>