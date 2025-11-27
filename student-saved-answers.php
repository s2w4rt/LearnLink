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

// Initialize variables
$user_info = [];
$saved_assignments = [];

// Fetch user information
try {
    if ($user['role'] === 'student') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Redirect teachers to their dashboard
        header('Location: teacher-dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Database error fetching user info: ' . $e->getMessage());
    $user_info = [
        'id' => $user['id'],
        'name' => $user['name'] ?? 'User',
        'strand' => 'N/A',
    ];
}

// Fetch saved assignments (drafts)
try {
    $stmt = $db->prepare("
        SELECT sa.*, a.title, a.subject_id, a.due_date, a.max_score, a.instructions,
               s.subject_name, s.subject_code
        FROM student_assignments sa
        JOIN assignments a ON sa.assignment_id = a.id
        LEFT JOIN subjects s ON a.subject_id = s.id
        WHERE sa.student_id = ? AND sa.status = 'draft'
        ORDER BY sa.submitted_at DESC
    ");
    $stmt->execute([$user['id']]);
    $saved_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database error fetching saved assignments: ' . $e->getMessage());
}

// Get file icon and type based on extension
function getFileInfo($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $fileTypes = [
        'pdf'  => ['icon' => 'file-pdf',   'color' => 'red',    'type' => 'PDF Document'],
        'doc'  => ['icon' => 'file-word',  'color' => 'blue',   'type' => 'Word Document'],
        'docx' => ['icon' => 'file-word',  'color' => 'blue',   'type' => 'Word Document'],
        'ppt'  => ['icon' => 'file-powerpoint', 'color' => 'orange', 'type' => 'PowerPoint'],
        'pptx' => ['icon' => 'file-powerpoint', 'color' => 'orange', 'type' => 'PowerPoint'],
        'xls'  => ['icon' => 'file-excel', 'color' => 'green',  'type' => 'Excel Spreadsheet'],
        'xlsx' => ['icon' => 'file-excel', 'color' => 'green',  'type' => 'Excel Spreadsheet'],
        'jpg'  => ['icon' => 'file-image', 'color' => 'purple', 'type' => 'Image'],
        'jpeg' => ['icon' => 'file-image', 'color' => 'purple', 'type' => 'Image'],
        'png'  => ['icon' => 'file-image', 'color' => 'purple', 'type' => 'Image'],
        'gif'  => ['icon' => 'file-image', 'color' => 'purple', 'type' => 'Image'],
        'zip'  => ['icon' => 'file-archive', 'color' => 'gray', 'type' => 'Archive'],
        'txt'  => ['icon' => 'file-alt',   'color' => 'gray',   'type' => 'Text File'],
    ];
    
    return $fileTypes[$extension] ?? ['icon' => 'file', 'color' => 'gray', 'type' => 'File'];
}

// Normalize file path
function normalizeFilePath($path) {
    if (empty($path)) {
        return '';
    }
    if ($path[0] === '/') {
        $path = ltrim($path, '/');
    }
    return $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Your Answers - ALLSHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .clickable-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .clickable-item:hover {
            background-color: #f9fafb;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .file-card {
            transition: all 0.3s ease;
        }
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .submission-file {
            border-left: 4px solid #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
<!-- Simple Header -->
<header class="bg-white shadow-md">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <img src="" alt="ALLSHS" class="h-10">
            <h1 class="text-xl font-bold text-blue-800">Angelo Levardo SHS</h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
                <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                    <?php echo substr($user['name'] ?? $user['full_name'] ?? 'US', 0, 2); ?>
                </div>
                <div class="text-right">
                    <span class="font-medium block">
                        <?php echo htmlspecialchars($user['name'] ?? $user['full_name'] ?? 'User'); ?>
                    </span>
                    <span class="text-sm text-gray-600">Student</span>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-4 py-6">
    <!-- Main content -->
    <main class="space-y-6">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">Review Your Answers</h2>
                    <p class="text-gray-600">
                        Take a moment to review your saved answers before final submission
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="student-dashboard.php" 
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-home mr-2"></i>
                        Back to Dashboard
                    </a>
                    <a href="student-assignments.php" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                        <i class="fas fa-tasks mr-2"></i>
                        View Assignments
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($saved_assignments)): ?>
            <!-- Saved Answers for Review -->
            <div class="space-y-6">
                <?php foreach ($saved_assignments as $assignment): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <!-- Assignment Header -->
                        <div class="flex flex-col md:flex-row justify-between items-start mb-6 gap-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </h3>
                                <div class="flex flex-wrap items-center text-sm text-gray-600 space-x-3">
                                    <?php if (!empty($assignment['subject_name'])): ?>
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-book-open mr-1"></i>
                                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($assignment['due_date'])): ?>
                                        <span class="inline-flex items-center">
                                            <i class="far fa-clock mr-1"></i>
                                            Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                        <i class="fas fa-save mr-1"></i>
                                        Saved Draft
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Assignment Instructions -->
                        <?php if (!empty($assignment['instructions'])): ?>
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <h4 class="font-semibold text-gray-800 mb-2">Assignment Instructions</h4>
                                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                                    <?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Your Saved Answer -->
                        <div class="mb-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-3">Your Saved Answer</h4>
                            
                            <!-- Saved Files -->
                            <?php if (!empty($assignment['file_path'])): ?>
                                <div class="space-y-3">
                                    <?php
                                    $file_path = normalizeFilePath($assignment['file_path']);
                                    $file_name = $assignment['original_file_name'] ?? basename($assignment['file_path']);
                                    $file_size = isset($assignment['file_size']) ? round($assignment['file_size'] / 1024, 1) : 0;
                                    $fileInfo = getFileInfo($file_path);
                                    ?>
                                    <div class="flex flex-col md:flex-row items-center justify-between text-sm p-4 bg-blue-50 rounded-lg border border-blue-200 gap-2">
                                        <div class="flex items-center w-full md:w-auto">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="fas fa-<?php echo $fileInfo['icon']; ?> text-blue-600"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($file_name); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $fileInfo['type']; ?> • <?php echo $file_size; ?> KB</div>
                                            </div>
                                        </div>
                                        <div class="space-x-2 w-full md:w-auto text-right">
                                            <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                               target="_blank" 
                                               class="px-3 py-1 bg-white border border-blue-500 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors text-sm">
                                                <i class="fas fa-eye mr-1"></i>Preview
                                            </a>
                                            <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                               download 
                                               class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                                <i class="fas fa-download mr-1"></i>Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    No files attached to this answer yet.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Submission Info -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <h5 class="font-semibold text-gray-800 mb-2">Submission Details</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="far fa-save mr-3 text-gray-400"></i>
                                    <div>
                                        <div class="font-medium">Last Saved</div>
                                        <div><?php echo date('M j, Y g:i A', strtotime($assignment['submitted_at'])); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($assignment['max_score'])): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-star mr-3 text-gray-400"></i>
                                        <div>
                                            <div class="font-medium">Maximum Score</div>
                                            <div><?php echo htmlspecialchars($assignment['max_score']); ?> points</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                            <a href="view-detail.php?type=assignment&id=<?php echo $assignment['assignment_id']; ?>&subject_id=<?php echo $assignment['subject_id']; ?>"
                               class="flex-1 px-4 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center justify-center text-center">
                                <i class="fas fa-edit mr-2"></i>
                                Edit Answer
                            </a>
                            <button 
                                onclick="submitSavedAssignment(<?php echo $assignment['id']; ?>)"
                                class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Submit Answer Now
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- No Saved Answers -->
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-save text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No Answers to Review</h3>
                <p class="text-gray-600 mb-6">
                    You haven't saved any answers for review yet. <br>
                    When you're unsure about an answer, use "Save but don't submit yet" to review it here later.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="student-assignments.php" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-tasks mr-2"></i>
                        View Assignments
                    </a>
                    <a href="student-dashboard.php" 
                       class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-home mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Submit Confirmation Modal -->
<div id="submitModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Ready to Submit?</h3>
        <p class="text-sm text-gray-600 mb-4">
            Are you sure you want to submit this assignment? Once submitted, you won't be able to make changes.
        </p>
        <div id="submitMessage" class="text-sm mb-4"></div>
        <div class="flex justify-end space-x-2 pt-2">
            <button type="button"
                    onclick="closeSubmitModal()"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancel
            </button>
            <button type="button"
                    id="confirmSubmitBtn"
                    onclick="confirmSubmit()"
                    class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                Yes, Submit Now
            </button>
        </div>
    </div>
</div>

<script>
    // Submit saved assignment functionality
    let currentSubmissionId = null;

    function submitSavedAssignment(submissionId) {
        currentSubmissionId = submissionId;
        const modal = document.getElementById('submitModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Clear any previous messages
        document.getElementById('submitMessage').textContent = '';
        document.getElementById('submitMessage').className = 'text-sm mb-4';
    }

    function closeSubmitModal() {
        const modal = document.getElementById('submitModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentSubmissionId = null;
    }

    function confirmSubmit() {
        if (!currentSubmissionId) return;
        
        const messageEl = document.getElementById('submitMessage');
        const submitBtn = document.getElementById('confirmSubmitBtn');
        
        messageEl.textContent = 'Submitting assignment...';
        messageEl.className = 'text-sm text-gray-600 mb-4';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        fetch('submit-saved-assignment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'submission_id=' + encodeURIComponent(currentSubmissionId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageEl.textContent = data.message || 'Assignment submitted successfully!';
                messageEl.className = 'text-sm text-green-600 mb-4';
                
                setTimeout(() => {
                    closeSubmitModal();
                    location.reload(); // Refresh to show updated list
                }, 1500);
            } else {
                messageEl.textContent = data.message || 'Failed to submit assignment.';
                messageEl.className = 'text-sm text-red-600 mb-4';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Yes, Submit Now';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageEl.textContent = 'Network error occurred. Please try again.';
            messageEl.className = 'text-sm text-red-600 mb-4';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Yes, Submit Now';
        });
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('submitModal');
        if (e.target === modal) {
            closeSubmitModal();
        }
    });
</script>
</body>
</html>