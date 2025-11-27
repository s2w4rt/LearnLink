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

// Initialize variables to prevent undefined variable warnings
$totalPendingAssignments = 0;
$pendingGrading = 0;
$user_info = [];
$submission = null;
$submissions = [];
$student_info = [];

// Get item ID and type from URL with validation
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$view = isset($_GET['view']) ? $_GET['view'] : 'details'; // 'details' or 'submissions'

// Validate item ID and type
if ($item_id <= 0 || !in_array($type, ['assignment', 'material'])) {
    header('Location: ' . ($user['role'] === 'teacher' ? 'teacher-dashboard.php' : 'student-dashboard.php'));
    exit;
}

// Fetch assignment or material details based on type
if ($type === 'assignment') {
    // Get assignment details with subject info
    try {
        $stmt = $db->prepare("
            SELECT a.*, s.subject_name, s.subject_code, s.strand 
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE a.id = ?
        ");
        $stmt->execute([$item_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            header('Location: ' . ($user['role'] === 'teacher' ? 'teacher-dashboard.php' : 'student-dashboard.php'));
            exit;
        }
        
        // Ensure assignment has proper type field
        $assignment['type'] = 'Assignment';
        
    } catch (PDOException $e) {
        error_log('Database error fetching assignment: ' . $e->getMessage());
        header('Location: error.php');
        exit;
    }
} elseif ($type === 'material') {
    // Get learning material details
    try {
        $stmt = $db->prepare("
            SELECT * 
            FROM learning_materials 
            WHERE id = ?
        ");
        $stmt->execute([$item_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            header('Location: ' . ($user['role'] === 'teacher' ? 'teacher-dashboard.php' : 'student-dashboard.php'));
            exit;
        }

        // Standardize fields for a unified view
        $assignment['title'] = $assignment['title'];
        $assignment['instructions'] = $assignment['description'] ?? '';
        $assignment['subject_name'] = $assignment['subject'];
        $assignment['type'] = 'Material';
        $assignment['quarter'] = $assignment['quarter'];
        $assignment['strand'] = $assignment['strand'];
        $assignment['max_score'] = 0;
        $assignment['weight'] = 0;
        $assignment['due_date'] = null;
        $assignment['external_link'] = null;

        // Try to find subject_id for back URL
        try {
            $subStmt = $db->prepare("
                SELECT id 
                FROM subjects 
                WHERE subject_name = ? AND strand = ? 
                LIMIT 1
            ");
            $subStmt->execute([$assignment['subject'], $assignment['strand']]);
            $subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
            if ($subRow) {
                $assignment['subject_id'] = (int)$subRow['id'];
            } else {
                $assignment['subject_id'] = $subject_id;
            }
        } catch (PDOException $e2) {
            error_log('Error resolving subject_id for material: ' . $e2->getMessage());
            $assignment['subject_id'] = $subject_id;
        }
    } catch (PDOException $e) {
        error_log('Database error fetching material: ' . $e->getMessage());
        header('Location: error.php');
        exit;
    }
} else {
    header('Location: ' . ($user['role'] === 'teacher' ? 'teacher-dashboard.php' : 'student-dashboard.php'));
    exit;
}

// For teacher view: Get sections for filter
$sections = [];
if ($user['role'] === 'teacher' && $type === 'assignment') {
    $stmt = $db->prepare("
        SELECT DISTINCT section 
        FROM students 
        WHERE strand = ? AND grade_level = ?
        ORDER BY section
    ");
    $stmt->execute([$assignment['strand'], 11]); // Assuming Grade 11
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get selected section from filter
$selected_section = $_GET['section'] ?? 'all';

// Fetch submission details
try {
    if ($type === 'assignment') {
        if ($user['role'] === 'student') {
            // Get student submission - only show if submitted
            $stmt = $db->prepare("
                SELECT * 
                FROM student_assignments 
                WHERE assignment_id = ? AND student_id = ? AND status IN ('submitted', 'graded', 'late')
            ");
            $stmt->execute([$item_id, $user['id']]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get student info
            $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$user['id']]);
            $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Teacher view - get all submissions with file information (only submitted ones)
            $baseQuery = "
                SELECT sa.*, s.full_name, s.student_id, s.grade_level, s.section
                FROM student_assignments sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.assignment_id = ? AND sa.status IN ('submitted', 'graded', 'late')
            ";
            
            $params = [$item_id];
            
            // Add section filter if selected
            if ($selected_section !== 'all') {
                $baseQuery .= " AND s.section = ?";
                $params[] = $selected_section;
            }
            
            $baseQuery .= " ORDER BY sa.submitted_at DESC";
            
            $stmt = $db->prepare($baseQuery);
            $stmt->execute($params);
            $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log('Database error fetching submissions: ' . $e->getMessage());
}

// Calculate submission statistics for teachers
$total_submissions = 0;
$graded_count = 0;
$average_score = 0;

if ($user['role'] === 'teacher' && $type === 'assignment') {
    $total_submissions = count($submissions);
    $graded_count = 0;
    $total_score = 0;

    foreach ($submissions as $sub) {
        if ($sub['score'] !== null) {
            $graded_count++;
            $total_score += (float)$sub['score'];
        }
    }

    if ($graded_count > 0) {
        $average_score = round($total_score / $graded_count, 2);
    }
}

// Fetch pending assignments count for students
if ($user['role'] === 'student') {
    try {
        if ($student_info) {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM assignments a 
                LEFT JOIN student_assignments sa 
                ON a.id = sa.assignment_id 
                AND sa.student_id = ?
                WHERE a.strand = ? 
                AND a.status = 'active'
                AND (sa.status IS NULL OR sa.status = 'assigned')
                AND a.due_date >= CURDATE()
            ");
            $stmt->execute([$user['id'], $student_info['strand']]);
            $totalPendingAssignments = $stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log('Database error fetching pending assignments: ' . $e->getMessage());
    }
}

// Fetch teacher grading queue count
if ($user['role'] === 'teacher') {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT sa.id) 
            FROM student_assignments sa
            JOIN assignments a ON sa.assignment_id = a.id
            WHERE a.teacher_id = ?
            AND sa.status IN ('submitted', 'late')
            AND sa.score IS NULL
        ");
        $stmt->execute([$user['id']]);
        $pendingGrading = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Database error fetching grading queue: ' . $e->getMessage());
    }
}

// Fetch user information for sidebar
try {
    if ($user['role'] === 'student') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_info) {
            $user_info = [
                'id' => $user['id'],
                'name' => $user['name'] ?? 'Teacher',
                'strand' => $user['strand'] ?? 'N/A',
            ];
        }
    }
} catch (PDOException $e) {
    error_log('Database error fetching user info: ' . $e->getMessage());
    $user_info = [
        'id' => $user['id'],
        'name' => $user['name'] ?? 'User',
        'strand' => 'N/A',
    ];
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
        'webp' => ['icon' => 'file-image', 'color' => 'purple', 'type' => 'Image'],
        'bmp'  => ['icon' => 'file-image', 'color' => 'purple', 'type' => 'Image'],
        'mp4'  => ['icon' => 'file-video', 'color' => 'indigo', 'type' => 'Video'],
        'avi'  => ['icon' => 'file-video', 'color' => 'indigo', 'type' => 'Video'],
        'mov'  => ['icon' => 'file-video', 'color' => 'indigo', 'type' => 'Video'],
        'webm' => ['icon' => 'file-video', 'color' => 'indigo', 'type' => 'Video'],
        'mkv'  => ['icon' => 'file-video', 'color' => 'indigo', 'type' => 'Video'],
        'mp3'  => ['icon' => 'file-audio', 'color' => 'pink',   'type' => 'Audio'],
        'wav'  => ['icon' => 'file-audio', 'color' => 'pink',   'type' => 'Audio'],
        'ogg'  => ['icon' => 'file-audio', 'color' => 'pink',   'type' => 'Audio'],
        'm4a'  => ['icon' => 'file-audio', 'color' => 'pink',   'type' => 'Audio'],
        'zip'  => ['icon' => 'file-archive', 'color' => 'gray', 'type' => 'Archive'],
        'rar'  => ['icon' => 'file-archive', 'color' => 'gray', 'type' => 'Archive'],
        '7z'   => ['icon' => 'file-archive', 'color' => 'gray', 'type' => 'Archive'],
        'tar'  => ['icon' => 'file-archive', 'color' => 'gray', 'type' => 'Archive'],
        'gz'   => ['icon' => 'file-archive', 'color' => 'gray', 'type' => 'Archive'],
        'txt'  => ['icon' => 'file-alt',   'color' => 'gray',   'type' => 'Text File'],
        'csv'  => ['icon' => 'file-csv',   'color' => 'green',  'type' => 'CSV File'],
        'md'   => ['icon' => 'file-alt',   'color' => 'gray',   'type' => 'Markdown'],
        'html' => ['icon' => 'file-code',  'color' => 'orange', 'type' => 'HTML File'],
        'css'  => ['icon' => 'file-code',  'color' => 'blue',   'type' => 'CSS File'],
        'js'   => ['icon' => 'file-code',  'color' => 'yellow', 'type' => 'JavaScript File'],
        'php'  => ['icon' => 'file-code',  'color' => 'purple', 'type' => 'PHP File'],
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

// Function to validate file paths
function validateFilePath($path) {
    $path = normalizeFilePath($path);

    $allowed_dirs = ['uploads/', 'assignments/', 'materials/', 'assets/'];
    foreach ($allowed_dirs as $dir) {
        if (strpos($path, $dir) === 0) {
            return true;
        }
    }
    return false;
}

// Parse files
$assignment_files = [];
if (!empty($assignment['file_path'])) {
    $files_data = json_decode($assignment['file_path'], true);
    if (is_array($files_data)) {
        foreach ($files_data as $file) {
            if (!empty($file['path']) && validateFilePath($file['path'])) {
                $assignment_files[] = [
                    'name' => $file['name'] ?? basename($file['path']),
                    'path' => normalizeFilePath($file['path'])
                ];
            }
        }
    } else {
        if (validateFilePath($assignment['file_path'])) {
            $assignment_files[] = [
                'name' => basename($assignment['file_path']),
                'path' => normalizeFilePath($assignment['file_path'])
            ];
        }
    }
}

// Also check file_url as fallback
if (empty($assignment_files) && !empty($assignment['file_url'])) {
    if (validateFilePath($assignment['file_url'])) {
        $assignment_files[] = [
            'name' => basename($assignment['file_url']),
            'path' => normalizeFilePath($assignment['file_url'])
        ];
    }
}

// Parse submission files for student view
$submission_files = [];
if ($user['role'] === 'student' && !empty($submission['file_path'])) {
    $submission_files[] = [
        'name' => $submission['original_file_name'] ?? basename($submission['file_path']),
        'path' => normalizeFilePath($submission['file_path']),
        'size' => $submission['file_size'] ?? 0
    ];
}

// Determine back URL
$backUrl = $user['role'] === 'teacher' 
    ? 'teacher-course-detail.php?subject_id=' . urlencode($assignment['subject_id'] ?? $subject_id)
    : 'student-course-detail.php?subject_id=' . urlencode($assignment['subject_id'] ?? $subject_id);

// Calculate score percentage for students
$score_percentage = 0;
if ($user['role'] === 'student' && $submission && !empty($submission['score']) && !empty($assignment['max_score'])) {
    $score_percentage = round(($submission['score'] / $assignment['max_score']) * 100, 1);
}

// Calculate late submission info
$is_late = false;
$late_days = 0;
if ($user['role'] === 'student' && $submission && !empty($assignment['due_date']) && !empty($submission['submitted_at'])) {
    $due_date = new DateTime($assignment['due_date']);
    $submitted_date = new DateTime($submission['submitted_at']);
    if ($submitted_date > $due_date) {
        $is_late = true;
        $interval = $due_date->diff($submitted_date);
        $late_days = $interval->days;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($assignment['title']); ?> - ALLSHS</title>
    <script src="/JS/notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-sticky {
            position: sticky;
            top: 2rem;
        }
        .clickable-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .clickable-item:hover {
            background-color: #f9fafb;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .video-player {
            width: 100%;
            max-width: 600px;
            max-height: 340px;
            border-radius: 8px;
        }
        .file-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin: 8px 0;
        }
        .file-preview-container {
            transition: all 0.3s ease;
        }
        .file-preview-container:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .text-preview {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        .text-preview::-webkit-scrollbar {
            width: 6px;
        }
        .text-preview::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }
        .text-preview::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        .text-preview::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
        .cursor-zoom-in {
            cursor: zoom-in;
        }
        #imageModal {
            backdrop-filter: blur(4px);
        }
        .file-card {
            transition: all 0.3s ease;
        }
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto;
            border: 4px solid;
        }
        .score-excellent { 
            background-color: #10b981; 
            color: white; 
            border-color: #10b981;
        }
        .score-good { 
            background-color: #3b82f6; 
            color: white; 
            border-color: #3b82f6;
        }
        .score-average { 
            background-color: #f59e0b; 
            color: white; 
            border-color: #f59e0b;
        }
        .score-poor { 
            background-color: #ef4444; 
            color: white; 
            border-color: #ef4444;
        }
        .score-ungraded {
            background-color: #6b7280;
            color: white;
            border-color: #6b7280;
        }
        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .file-list-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            background-color: white;
        }
        .file-list-item:hover {
            background-color: #f9fafb;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        .progress-fill {
            height: 100%;
            background-color: #10b981;
            transition: width 0.3s ease;
        }
        .submission-file {
            border-left: 4px solid #3b82f6;
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; margin-right: 0; margin-bottom: 1rem; }
            .main-content { width: 100%; }
            .right-sidebar { width: 100%; margin-left: 0; }
            .file-card .flex { flex-direction: column; align-items: flex-start; }
            .file-card .flex > div { margin-bottom: 1rem; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
<!-- Header -->
<header class="bg-white shadow-md">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <img src="" alt="ALLSHS" class="h-10">
            <h1 class="text-xl font-bold text-blue-800">Angelo Levardo SHS</h1>
        </div>
        <div class="flex items-center space-x-6">
            <!-- Notification Bell -->
            <div class="relative">
                <button id="notificationBell" class="relative p-2 text-gray-600 hover:text-blue-600 transition-colors">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden" id="notificationCount">0</span>
                </button>
                <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl z-50 hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-800">Notifications</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div id="notificationList" class="p-2">
                            <!-- Notifications will be loaded here -->
                        </div>
                    </div>
                    <div class="p-2 border-t border-gray-200 text-center">
                        <a href="teacher-notifications.php" class="text-blue-600 hover:text-blue-800 text-sm">View All Notifications</a>
                    </div>
                </div>
            </div>
            
            <div class="relative">
                <input type="text"
                       placeholder="Search."
                       class="pl-10 pr-4 py-2 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
            <div class="flex items-center space-x-2">
                <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                    <?php echo substr($user['name'] ?? $user['full_name'] ?? 'US', 0, 2); ?>
                </div>
                <div class="text-right">
                    <span class="font-medium block">
                        <?php echo htmlspecialchars($user['name'] ?? $user['full_name'] ?? 'User'); ?>
                    </span>
                    <span class="text-sm text-gray-600"><?php echo ucfirst($user['role']); ?></span>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-4 py-6 flex flex-col md:flex-row">
    <!-- Left: nav + main content -->
    <div class="flex-1 flex flex-col md:flex-row">
        <?php if ($user['role'] === 'student'): ?>
        <!-- Student Sidebar -->
        <aside class="w-full md:w-64 bg-white shadow-md rounded-lg mr-0 md:mr-6 h-fit mb-6 md:mb-0 sidebar">
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="student-dashboard.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-home mr-3"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="student-courses.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
                            <i class="fas fa-book mr-3"></i>
                            <span>My Courses</span>
                        </a>
                    </li>
                    <li>
                        <a href="student-schedule.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            <span>Schedule</span>
                        </a>
                    </li>
                    <li>
                        <a href="student-assignments.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-tasks mr-3"></i>
                            <span>Assignments</span>
                        </a>
                    </li>
                    <li>
                        <a href="student-materials.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-file-alt mr-3"></i>
                            <span>Learning Materials</span>
                        </a>
                    </li>
                    <li>
                        <a href="student-grades.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-chart-line mr-3"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-red-50 hover:text-red-600">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Student Info Card -->
            <div class="mt-6 p-4 border-t border-gray-200">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800">
                        <?php echo htmlspecialchars($user_info['full_name'] ?? 'Student'); ?>
                    </h4>
                    <p class="text-sm text-gray-600">
                        <?php echo $user_info['student_id'] ?? 'Student'; ?>
                    </p>
                    <div class="mt-2 text-xs text-gray-500">
                        <div><?php echo htmlspecialchars($user_info['strand'] ?? 'N/A'); ?></div>
                        <div>
                            Grade <?php echo $user_info['grade_level'] ?? ''; ?>
                            <?php echo isset($user_info['section']) ? ' • ' . htmlspecialchars($user_info['section']) : ''; ?>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <?php else: ?>
        <!-- Teacher Sidebar -->
        <aside class="w-full md:w-64 bg-white shadow-md rounded-lg mr-0 md:mr-6 h-fit mb-6 md:mb-0 sidebar">
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="teacher-dashboard.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-home mr-3"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="teacher-courses.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
                            <i class="fas fa-book mr-3"></i>
                            <span>My Courses</span>
                        </a>
                    </li>
                    <li>
                        <a href="teacher-assignments.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-tasks mr-3"></i>
                            <span>Assignments</span>
                            <?php if ($pendingGrading > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                    <?php echo $pendingGrading; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="teacher-materials.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-file-alt mr-3"></i>
                            <span>Learning Materials</span>
                        </a>
                    </li>
                    <li>
                        <a href="teacher-grades.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                            <i class="fas fa-chart-line mr-3"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php"
                           class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-red-50 hover:text-red-600">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Teacher Info Card -->
            <div class="mt-6 p-4 border-t border-gray-200">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-chalkboard-teacher text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800">
                        <?php echo htmlspecialchars($user_info['name'] ?? 'Teacher'); ?>
                    </h4>
                    <p class="text-sm text-gray-600">
                        Teacher ID: <?php echo $user_info['id'] ?? ''; ?>
                    </p>
                    <div class="mt-2 text-xs text-gray-500">
                        <div><?php echo htmlspecialchars($user_info['strand'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </aside>
        <?php endif; ?>

        <!-- Main content -->
<main class="flex-1 space-y-6 main-content">
    <!-- Back link -->
    <div class="mb-4">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" 
           class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Course
        </a>
    </div>

    <!-- MAIN ASSIGNMENT PAGE CONTAINER (Default view for students) -->
    <div id="mainAssignmentPage" class="space-y-6 <?php echo (isset($_SESSION['show_draft_review']) && $_SESSION['show_draft_review'] === $item_id && $user['role'] === 'student' && $type === 'assignment') ? 'hidden' : ''; ?>">
        
        <!-- Header: Title + meta -->
        <section class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-col md:flex-row justify-between items-start mb-4 gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">
                        <?php echo htmlspecialchars($assignment['title']); ?>
                    </h2>
                    <div class="flex flex-wrap items-center text-sm text-gray-600 space-x-3">
                        <span class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">
                            <i class="fas fa-<?php echo $type === 'material' ? 'book' : 'clipboard-list'; ?> mr-1"></i>
                            <?php echo $type === 'material' ? 'Learning Material' : 'Assignment'; ?>
                        </span>
                        <?php if (!empty($assignment['subject_name'])): ?>
                            <span><i class="fas fa-book-open mr-1"></i><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($assignment['quarter'])): ?>
                            <span><i class="fas fa-layer-group mr-1"></i>Quarter <?php echo htmlspecialchars($assignment['quarter']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($assignment['strand'])): ?>
                            <span><i class="fas fa-stream mr-1"></i><?php echo htmlspecialchars($assignment['strand']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($assignment['created_at'])): ?>
                            <span><i class="far fa-clock mr-1"></i>
                                Created <?php echo date('M j, Y', strtotime($assignment['created_at'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right space-y-2">
                    <?php if ($type === 'assignment' && !empty($assignment['due_date'])): ?>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Due Date</div>
                            <div class="font-semibold text-gray-900" id="dueDateDisplay">
                                <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($assignment['instructions'])): ?>
                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">
                        <?php echo $type === 'material' ? 'Description' : 'Instructions'; ?>
                    </h3>
                    <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-line">
                        <?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Files attached -->
        <section class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-paperclip mr-2"></i>Files
            </h3>

            <?php if (!empty($assignment_files)): ?>
                <div class="space-y-3">
                    <?php foreach ($assignment_files as $file): ?>
                        <?php
                        $file_info = getFileInfo($file['name']);
                        $file_safe_path = htmlspecialchars($file['path']);
                        $file_safe_name = htmlspecialchars($file['name']);
                        ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-<?php echo $file_info['icon']; ?> text-<?php echo $file_info['color']; ?>-500 text-xl"></i>
                                <div>
                                    <div class="font-medium text-gray-800"><?php echo $file_safe_name; ?></div>
                                    <div class="text-sm text-gray-500"><?php echo $file_info['type']; ?></div>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <a href="<?php echo $file_safe_path; ?>" 
                                   target="_blank" 
                                   class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                                <a href="<?php echo $file_safe_path; ?>" 
                                   download 
                                   class="px-3 py-1 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-50 rounded-lg p-8 text-center">
                    <i class="fas fa-folder-open text-3xl text-gray-400 mb-3"></i>
                    <h4 class="font-medium text-gray-600 mb-2">No Files Available</h4>
                    <p class="text-sm text-gray-500">
                        There are no files attached to this <?php echo $type === 'material' ? 'material' : 'assignment'; ?>.
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Student: Your Work -->
        <?php if ($user['role'] === 'student' && $type === 'assignment'): ?>
        <section class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
                <h3 class="text-lg font-semibold text-gray-800">Your Work</h3>
                <?php if (!$submission || $submission['status'] === 'assigned'): ?>
                    <!-- Attachment Button -->
                    <button 
                        onclick="openAttachmentModal()"
                        class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i class="fas fa-paperclip mr-2"></i>Attach File
                    </button>
                <?php endif; ?>
            </div>

            <!-- Main Container - For attaching files -->
            <div id="studentMainContainer">
                <?php if ($submission && !empty($submission_files)): ?>
                    <!-- Existing submitted work display -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-3 gap-2">
                            <h4 class="font-medium text-gray-800">Submitted Files</h4>
                            <span class="text-xs px-2 py-1 rounded-full 
                                <?php echo $submission['status'] === 'graded' ? 'bg-green-100 text-green-700' : 
                                       ($submission['status'] === 'late' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); ?>">
                                <?php echo ucfirst($submission['status'] ?? 'submitted'); ?>
                            </span>
                        </div>
                        <div class="space-y-2">
                            <?php foreach ($submission_files as $file): ?>
                                <?php
                                $file_safe_path = htmlspecialchars($file['path']);
                                $file_safe_name = htmlspecialchars($file['name']);
                                $file_size = isset($file['size']) ? round($file['size'] / 1024, 1) : 0;
                                ?>
                                <div class="flex flex-col md:flex-row items-center justify-between text-sm p-3 bg-white rounded border submission-file gap-2">
                                    <span class="flex items-center w-full md:w-auto">
                                        <i class="fas fa-file mr-2 text-gray-500"></i>
                                        <div class="flex-1">
                                            <div class="font-medium"><?php echo $file_safe_name; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $file_size; ?> KB</div>
                                        </div>
                                    </span>
                                    <div class="space-x-2 w-full md:w-auto text-right">
                                        <a href="<?php echo $file_safe_path; ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:underline">Open</a>
                                        <a href="<?php echo $file_safe_path; ?>" 
                                           download 
                                           class="text-gray-600 hover:underline">Download</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($submission['submitted_at'])): ?>
                            <div class="mt-3 text-sm text-gray-700">
                                <span class="font-semibold">Submitted:</span>
                                <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                                <?php if ($is_late): ?>
                                    <span class="text-red-600 ml-2">(Late submission)</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($submission['score'])): ?>
                            <div class="mt-3 text-sm text-gray-700">
                                <span class="font-semibold">Score:</span>
                                <?php echo htmlspecialchars($submission['score']); ?> / <?php echo htmlspecialchars($assignment['max_score']); ?>
                                (<?php echo $score_percentage; ?>%)
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($submission['feedback'])): ?>
                            <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                <span class="font-semibold block mb-1">Teacher Feedback</span>
                                <?php echo htmlspecialchars($submission['feedback']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (!isset($_SESSION['draft_files'][$item_id])): ?>
                    <!-- No submission or draft message -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
                        <i class="fas fa-upload text-3xl text-blue-500 mb-3"></i>
                        <h4 class="font-medium text-blue-800 mb-1">No Submission Yet</h4>
                        <p class="text-sm text-blue-700 mb-4">
                            You have not submitted this assignment yet. Make sure to upload your work before the due date.
                        </p>
                        <button 
                            onclick="openAttachmentModal()"
                            class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors flex items-center mx-auto">
                            <i class="fas fa-paperclip mr-2"></i>Attach Your Work
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Draft Submission Display in Main Container -->
                <div id="draftSubmissionMain" class="<?php echo (isset($_SESSION['draft_files'][$item_id]) && !$submission) ? '' : 'hidden'; ?> bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-3 gap-2">
                        <h4 class="font-medium text-blue-800">Draft Submission</h4>
                        <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700">
                            Draft - Not Submitted
                        </span>
                    </div>
                    
                    <div id="draftFilesListMain" class="space-y-2 mb-4">
                        <?php if (isset($_SESSION['draft_files'][$item_id])): ?>
                            <?php foreach ($_SESSION['draft_files'][$item_id] as $draftFile): ?>
                                <div class="flex flex-col md:flex-row items-center justify-between text-sm p-3 bg-white rounded border border-blue-300 gap-2">
                                    <span class="flex items-center w-full md:w-auto">
                                        <i class="fas fa-file mr-2 text-blue-500"></i>
                                        <div class="flex-1">
                                            <div class="font-medium"><?php echo htmlspecialchars($draftFile['name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo round($draftFile['size'] / 1024, 1); ?> KB</div>
                                        </div>
                                    </span>
                                    <div class="space-x-2 w-full md:w-auto text-right">
                                        <button 
                                            onclick="removeDraftFile('<?php echo htmlspecialchars($draftFile['name']); ?>')"
                                            class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash mr-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-col md:flex-row gap-2 justify-end">
                        <button 
                            onclick="saveAsDraft()"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center">
                            <i class="fas fa-save mr-2"></i>Save but did not submit yet
                        </button>
                        <button 
                            onclick="submitDraft()"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Your Work
                        </button>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <!-- DRAFT REVIEW PAGE CONTAINER (Hidden by default, ONLY for students) -->
    <?php if ($user['role'] === 'student' && $type === 'assignment'): ?>
    <div id="draftReviewPage" class="<?php echo (isset($_SESSION['show_draft_review']) && $_SESSION['show_draft_review'] === $item_id) ? '' : 'hidden'; ?>">
        <!-- Draft Review Container -->
        <section class="bg-white rounded-lg shadow p-8">
            



            <!-- Your Draft Work -->
            <div class="mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Your Draft Work</h3>
                <div id="draftFilesReview" class="space-y-4">
                    <!-- Draft files will be displayed here -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-gray-50 rounded-lg p-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">What would you like to do next?</h3>
                <div class="flex flex-col md:flex-row gap-4 justify-center items-center">
                    <button 
                        onclick="switchToAssignmentPage()"
                        class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center min-w-[200px] font-medium">
                        <i class="fas fa-edit mr-3"></i>Edit Your Work
                    </button>
                    
                    <button 
                        onclick="submitDraftFromReview()"
                        class="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center min-w-[200px] font-medium">
                        <i class="fas fa-paper-plane mr-3"></i>Submit Your Work
                    </button>
                </div>
                
                <div class="mt-6 text-center text-gray-600">
                    <p><i class="fas fa-info-circle mr-2"></i>You can come back later to submit your work. Your draft will be saved.</p>
                </div>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <!-- Teacher: Student Submissions Page -->
    <?php if ($user['role'] === 'teacher' && $type === 'assignment'): ?>
        <section class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800">Student Submissions</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Manage and grade student submissions for this assignment
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Section Filter -->
                    <?php if (!empty($sections)): ?>
                    <div class="flex items-center space-x-2">
                        <label for="sectionFilter" class="text-sm font-medium text-gray-700">Filter by section:</label>
                        <select id="sectionFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all" <?php echo $selected_section === 'all' ? 'selected' : ''; ?>>All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section === $section ? 'selected' : ''; ?>>
                                    Section <?php echo htmlspecialchars($section); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-lg">
                        <span class="font-semibold"><?php echo $total_submissions; ?></span> submission(s) • 
                        <span class="font-semibold"><?php echo $graded_count; ?></span> graded
                    </div>
                </div>
            </div>

            <?php if (!empty($submissions)): ?>
                <div class="space-y-4">
                    <?php foreach ($submissions as $sub): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex flex-col md:flex-row items-start justify-between mb-3 gap-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800">
                                            <?php echo htmlspecialchars($sub['full_name'] ?? 'Student'); ?>
                                            <span class="text-xs text-gray-500 ml-2">
                                                (<?php echo htmlspecialchars($sub['student_id'] ?? ''); ?>)
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Grade <?php echo $sub['grade_level'] ?? ''; ?>
                                            <?php echo isset($sub['section']) ? ' • Section ' . htmlspecialchars($sub['section']) : ''; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php echo $sub['status'] === 'graded' ? 'bg-green-100 text-green-800' : 
                                               ($sub['status'] === 'late' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo ucfirst($sub['status'] ?? 'submitted'); ?>
                                    </span>
                                    <?php if ($sub['score'] !== null): ?>
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                            Score: <?php echo htmlspecialchars($sub['score']); ?>/<?php echo htmlspecialchars($assignment['max_score']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($sub['submitted_at'])): ?>
                                <div class="text-sm text-gray-600 mb-3">
                                    <i class="far fa-clock mr-1"></i>
                                    Submitted: <?php echo date('M j, Y g:i A', strtotime($sub['submitted_at'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($sub['file_path'])): ?>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="flex flex-col md:flex-row items-center justify-between gap-2">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-file text-gray-400"></i>
                                            <span class="text-sm font-medium text-gray-800">
                                                <?php echo htmlspecialchars($sub['original_file_name'] ?? basename($sub['file_path'])); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                (<?php echo isset($sub['file_size']) ? round($sub['file_size'] / 1024, 1) : 'Unknown'; ?> KB)
                                            </span>
                                        </div>
                                        <div class="flex space-x-2">
                                            <?php if (file_exists($sub['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" 
                                                   target="_blank" 
                                                   class="text-blue-600 hover:text-blue-800 text-sm px-3 py-1 border border-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                                <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" 
                                                   download 
                                                   class="text-gray-600 hover:text-gray-800 text-sm px-3 py-1 border border-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
                                                    <i class="fas fa-download mr-1"></i>Download
                                                </a>
                                            <?php else: ?>
                                                <span class="text-red-600 text-sm px-3 py-1 border border-red-300 rounded-lg bg-red-50">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>File not found
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($sub['score'] === null): ?>
                                                <button 
                                                    onclick="gradeSubmission(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['full_name']); ?>', <?php echo $assignment['max_score']; ?>)"
                                                    class="text-green-600 hover:text-green-800 text-sm px-3 py-1 border border-green-600 rounded-lg hover:bg-green-50 transition-colors">
                                                    <i class="fas fa-check mr-1"></i>Grade
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    onclick="gradeSubmission(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['full_name']); ?>', <?php echo $assignment['max_score']; ?>)"
                                                    class="text-blue-600 hover:text-blue-800 text-sm px-3 py-1 border border-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                                                    <i class="fas fa-edit mr-1"></i>Regrade
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    No file uploaded by student
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($sub['feedback'])): ?>
                                <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                    <span class="font-semibold block mb-1">Your Feedback:</span>
                                    <?php echo htmlspecialchars($sub['feedback']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-50 rounded-lg p-12 text-center">
                    <i class="fas fa-users-slash text-4xl text-gray-400 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">No Submissions Yet</h4>
                    <p class="text-sm text-gray-500 mb-4">
                        No students have submitted this assignment yet.
                    </p>
                    <div class="text-xs text-gray-400">
                        Students will appear here once they submit their work.
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
    </div>

    <!-- Right side: overview sidebar -->
    <aside class="w-full md:w-80 mt-6 md:mt-0 md:ml-6 sidebar-sticky space-y-4 right-sidebar">
        <!-- Assignment Info Card -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Assignment</h3>
            <dl class="space-y-3 text-sm text-gray-700">
                <div class="flex justify-between">
                    <dt>Type</dt>
                    <dd class="font-medium"><?php echo htmlspecialchars($assignment['type']); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt>Max score</dt>
                    <dd class="font-medium"><?php echo htmlspecialchars($assignment['max_score']); ?></dd>
                </div>
                <?php if (!empty($assignment['due_date'])): ?>
                    <div class="flex justify-between">
                        <dt>Due</dt>
                        <dd class="font-medium"><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Score Card (Student View Only) -->
        <?php if ($user['role'] === 'student' && $type === 'assignment'): ?>
            <div class="bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Score</h3>
                <div class="text-center mb-4">
                    <div class="text-xs text-gray-600 mb-2">Your latest submission is used</div>
                    <?php if ($submission && !empty($submission['score'])): ?>
                        <?php
                        $score_class = 'score-poor';
                        if ($score_percentage >= 90) $score_class = 'score-excellent';
                        elseif ($score_percentage >= 80) $score_class = 'score-good';
                        elseif ($score_percentage >= 70) $score_class = 'score-average';
                        ?>
                        <div class="score-circle <?php echo $score_class; ?> text-2xl mb-2">
                            <?php echo htmlspecialchars($submission['score']); ?>
                        </div>
                        <div class="text-sm font-medium text-gray-700">
                            <?php echo htmlspecialchars($submission['score']); ?>/<?php echo htmlspecialchars($assignment['max_score']); ?> 
                            (<?php echo $score_percentage; ?>%)
                        </div>
                    <?php else: ?>
                        <div class="score-circle score-ungraded text-2xl mb-2">
                            -
                        </div>
                        <div class="text-sm font-medium text-gray-700">Not graded yet</div>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-gray-600 text-center">
                    Your score was set by the teacher.
                </div>
            </div>

            <!-- Submission Details -->
            <div class="bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Submission</h3>
                <dl class="space-y-3 text-sm text-gray-700">
                    <?php if ($submission && !empty($submission['submitted_at'])): ?>
                        <div class="flex justify-between">
                            <dt>Submitted</dt>
                            <dd class="font-medium"><?php echo date('M j, g:i a', strtotime($submission['submitted_at'])); ?></dd>
                        </div>
                    <?php else: ?>
                        <div class="flex justify-between">
                            <dt>Submitted</dt>
                            <dd class="font-medium text-gray-500">Not submitted</dd>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_late): ?>
                        <div class="flex justify-between">
                            <dt>Late by</dt>
                            <dd class="font-medium text-red-600"><?php echo $late_days; ?> days</dd>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <dt>Attempts</dt>
                        <dd class="font-medium"><?php echo $submission ? '1' : '0'; ?></dd>
                    </div>
                    
                    <div class="flex justify-between">
                        <dt>Max. attempts</dt>
                        <dd class="font-medium">1</dd>
                    </div>
                    
                    <div class="flex justify-between">
                        <dt>Allow late submissions</dt>
                        <dd class="font-medium text-red-600">✗</dd>
                    </div>
                </dl>
            </div>

            <!-- Comments Summary -->
            <div class="bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Comments</h3>
                <div class="text-sm text-gray-700">
                    <?php if ($submission && !empty($submission['submitted_at'])): ?>
                        <div class="font-medium">Submission 1 @ <?php echo date('g:i a M j, Y', strtotime($submission['submitted_at'])); ?></div>
                    <?php else: ?>
                        <div class="text-gray-500">No submissions yet</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Teacher Actions -->
        <?php if ($user['role'] === 'teacher' && $type === 'assignment'): ?>
            <div class="bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Assignment Actions</h3>
                <div class="space-y-3">
                    <button
                        onclick="openEditDateModal()"
                        class="w-full px-4 py-2 bg-white border border-blue-500 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors text-left">
                        <i class="fas fa-calendar-edit mr-2"></i>Edit Due Date
                    </button>
                    <button
                        onclick="deployAssignment(<?php echo (int)$assignment['id']; ?>)"
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-left">
                        <i class="fas fa-paper-plane mr-2"></i>Deploy to Students
                    </button>
                </div>
            </div>

            <!-- Teacher Stats -->
            <div class="bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Submission Stats</h3>
                <dl class="space-y-3 text-sm text-gray-700">
                    <div class="flex justify-between">
                        <dt>Total Submissions</dt>
                        <dd class="font-medium"><?php echo $total_submissions; ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Graded</dt>
                        <dd class="font-medium"><?php echo $graded_count; ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Average Score</dt>
                        <dd class="font-medium">
                            <?php echo $graded_count > 0 ? $average_score : 'N/A'; ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Comments Link Card -->
            <div class="bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Comments</h3>
                <p class="text-sm text-gray-600 mb-4">
                    View and manage all comments and questions for this assignment.
                </p>
                <a href="view-comments.php?item_id=<?php echo $item_id; ?>&type=<?php echo $type; ?>&subject_id=<?php echo $subject_id; ?>" 
                   class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-center block">
                    <i class="fas fa-comments mr-2"></i>View All Comments
                </a>
            </div>
        <?php endif; ?>
    </aside>
</div>

<!-- Image preview modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50">
    <div class="relative max-w-4xl max-h-[90vh]">
        <button onclick="closeImageModal()" class="absolute -top-10 right-0 text-white text-2xl">&times;</button>
        <img id="modalImage" src="" alt="Preview" class="max-h-[90vh] mx-auto rounded-lg shadow-2xl">
    </div>
</div>

<!-- File Upload Modal (Student + Assignment only) -->
<?php if ($user['role'] === 'student' && $type === 'assignment'): ?>
<div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Upload Your Work</h3>
            <p class="text-sm text-gray-600 mt-1">Upload files for: <?php echo htmlspecialchars($assignment['title']); ?></p>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <!-- Upload Area -->
            <div 
                id="uploadArea" 
                class="upload-area"
                ondragover="handleDragOver(event)"
                ondragleave="handleDragLeave(event)"
                ondrop="handleDrop(event)"
            >
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <h4 class="text-lg font-medium text-gray-700 mb-2">Drag & Drop Files Here</h4>
                <p class="text-sm text-gray-500 mb-4">or click to browse your computer</p>
                <input 
                    type="file" 
                    id="fileInput" 
                    class="hidden"
                    onchange="handleFileSelect(event)"
                >
                <button 
                    onclick="document.getElementById('fileInput').click()"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <i class="fas fa-folder-open mr-2"></i>Browse Files
                </button>
                <p class="text-xs text-gray-400 mt-3">
                    Maximum file size: 200MB per file. All file types are allowed.
                </p>
            </div>

            <!-- Selected Files List -->
            <div id="fileList" class="mt-6 space-y-3 hidden">
                <h5 class="font-medium text-gray-700 mb-3">Selected File</h5>
                <div id="fileListItems" class="space-y-2"></div>
            </div>

            <!-- Upload Progress -->
            <div id="uploadProgress" class="mt-6 hidden">
                <h5 class="font-medium text-gray-700 mb-3">Upload Progress</h5>
                <div id="progressBars" class="space-y-4"></div>
            </div>

            <!-- Upload Message -->
            <div id="uploadMessage" class="mt-4 text-sm"></div>
        </div>

        <div class="p-6 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3">
            <button 
                onclick="closeUploadModal()"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
            >
                Cancel
            </button>
            <button 
                id="submitUploadBtn"
                onclick="submitFiles()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                disabled
            >
                <i class="fas fa-paper-plane mr-2"></i>Submit Assignment
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Due Date Modal (Teacher + Assignment only) -->
<?php if ($user['role'] === 'teacher' && $type === 'assignment'): ?>
<div id="editDateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Edit Due Date</h3>
        <p class="text-sm text-gray-600 mb-4">
            Update the due date and time for this assignment. Students will see the new deadline immediately.
        </p>
        <form id="editDateForm" class="space-y-4">
            <div>
                <label for="newDueDate" class="block text-sm font-medium text-gray-700 mb-1">New Due Date &amp; Time</label>
                <input
                    type="datetime-local"
                    id="newDueDate"
                    name="newDueDate"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="<?php echo !empty($assignment['due_date']) ? date('Y-m-d\TH:i', strtotime($assignment['due_date'])) : ''; ?>"
                    required
                >
            </div>
            <div id="editDateMessage" class="text-sm"></div>
            <div class="flex justify-end space-x-2 pt-2">
                <button type="button"
                        onclick="closeEditDateModal()"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Deploy Assignment Modal (Teacher + Assignment only) -->
<?php if ($user['role'] === 'teacher' && $type === 'assignment'): ?>
<div id="deployModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Deploy Assignment</h3>
        <p class="text-sm text-gray-600 mb-4">
            This assignment will be deployed to all Grade 11 ICT students.
        </p>
        <form id="deployForm" class="space-y-4">
            <input type="hidden" id="deployAssignmentId" name="assignment_id" value="">
            <div id="deployMessage" class="text-sm"></div>
            <div class="flex justify-end space-x-2 pt-2">
                <button type="button"
                        onclick="closeDeployModal()"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="px-3 py-1 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Deploy to Grade 11 ICT Students
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        const img = document.getElementById('modalImage');
        img.src = src;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Section filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sectionFilter = document.getElementById('sectionFilter');
        if (sectionFilter) {
            sectionFilter.addEventListener('change', function() {
                const section = this.value;
                const url = new URL(window.location);
                url.searchParams.set('section', section);
                window.location.href = url.toString();
            });
        }

        // Notification Bell Functionality
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (notificationBell && notificationDropdown) {
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('hidden');
                loadNotifications();
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                notificationDropdown.classList.add('hidden');
            });

            // Load notifications
            function loadNotifications() {
                fetch('get-notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const notificationList = document.getElementById('notificationList');
                        const notificationCount = document.getElementById('notificationCount');
                        
                        if (data.notifications && data.notifications.length > 0) {
                            notificationList.innerHTML = data.notifications.map(notification => `
                                <div class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="font-medium text-sm text-gray-800">${notification.title}</p>
                                            <p class="text-xs text-gray-600 mt-1">${notification.message}</p>
                                            <p class="text-xs text-gray-400 mt-1">${notification.time}</p>
                                        </div>
                                        ${!notification.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full ml-2 mt-1"></span>' : ''}
                                    </div>
                                </div>
                            `).join('');
                            
                            const unreadCount = data.notifications.filter(n => !n.is_read).length;
                            if (unreadCount > 0) {
                                notificationCount.textContent = unreadCount;
                                notificationCount.classList.remove('hidden');
                            } else {
                                notificationCount.classList.add('hidden');
                            }
                        } else {
                            notificationList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications</div>';
                            notificationCount.classList.add('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notifications:', error);
                    });
            }

            // Load initial notification count
            loadNotifications();
        }

        // Initialize page containers on page load (STUDENT ONLY)
        <?php if ($user['role'] === 'student' && $type === 'assignment'): ?>
        updateDraftDisplay();
        
        // Check if we should show review page by default
        const shouldShowReview = <?php echo (isset($_SESSION['show_draft_review']) && $_SESSION['show_draft_review'] === $item_id) ? 'true' : 'false'; ?>;
        if (shouldShowReview) {
            switchStudentPage('review');
        } else {
            switchStudentPage('assignment');
        }
        <?php endif; ?>
    });

    // =============================================
    // STUDENT-ONLY FUNCTIONALITY
    // =============================================

    // File upload functionality with draft support (Student only)
    let selectedFiles = [];
    let draftFiles = <?php echo isset($_SESSION['draft_files'][$item_id]) ? json_encode($_SESSION['draft_files'][$item_id]) : '[]'; ?>;

    // Full page container management (STUDENT ONLY)
    function switchStudentPage(action) {
        const mainAssignmentPage = document.getElementById('mainAssignmentPage');
        const draftReviewPage = document.getElementById('draftReviewPage');
        
        if (!mainAssignmentPage || !draftReviewPage) return;
        
        switch(action) {
            case 'review':
                // Show draft review page, hide main assignment page
                mainAssignmentPage.classList.add('hidden');
                draftReviewPage.classList.remove('hidden');
                updateDraftReviewDisplay();
                break;
                
            case 'assignment':
                // Show main assignment page, hide draft review page
                mainAssignmentPage.classList.remove('hidden');
                draftReviewPage.classList.add('hidden');
                break;
                
            default:
                // Default to main assignment page
                mainAssignmentPage.classList.remove('hidden');
                draftReviewPage.classList.add('hidden');
        }
    }

    function switchToAssignmentPage() {
        // Switch back to assignment page
        switchStudentPage('assignment');
        
        // Clear the session flag for showing draft review
        fetch('handle-draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_review_flag&assignment_id=' + <?php echo $item_id; ?>
        });
    }

    function updateDraftReviewDisplay() {
        const draftFilesReview = document.getElementById('draftFilesReview');
        if (!draftFilesReview) return;
        
        draftFilesReview.innerHTML = '';
        
        if (draftFiles.length > 0) {
            draftFiles.forEach((file, index) => {
                const fileSize = formatFileSize(file.size);
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center justify-between p-6 bg-blue-50 border border-blue-200 rounded-lg';
                fileItem.innerHTML = `
                    <div class="flex items-center flex-1">
                        <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mr-6">
                            <i class="fas fa-file text-blue-600 text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800 text-lg mb-2">${file.name}</div>
                            <div class="text-gray-600">
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-medium mr-3">
                                    <i class="fas fa-save mr-1"></i>Draft File
                                </span>
                                <span class="text-gray-500">${fileSize}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="previewDraftFile(${index})" class="text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-eye mr-1"></i>Preview
                        </button>
                    </div>
                `;
                draftFilesReview.appendChild(fileItem);
            });
        } else {
            draftFilesReview.innerHTML = `
                <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                    <i class="fas fa-file-exclamation text-4xl text-gray-400 mb-3"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">No Draft Files</h4>
                    <p class="text-gray-500">No draft files were found.</p>
                </div>
            `;
        }
    }

    function previewDraftFile(index) {
        const file = draftFiles[index];
        if (file.file) {
            // Create a temporary URL for the file
            const fileURL = URL.createObjectURL(file.file);
            window.open(fileURL, '_blank');
        } else {
            showDraftMessage('File preview not available. Please edit your work to see the file.', 'info');
        }
    }

    function openAttachmentModal() {
        const modal = document.getElementById('uploadModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        resetUploadModal();
    }

    function closeUploadModal() {
        const modal = document.getElementById('uploadModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        resetUploadModal();
    }

    function resetUploadModal() {
        selectedFiles = [];
        document.getElementById('fileList').classList.add('hidden');
        document.getElementById('uploadProgress').classList.add('hidden');
        document.getElementById('submitUploadBtn').disabled = true;
        document.getElementById('fileListItems').innerHTML = '';
        document.getElementById('progressBars').innerHTML = '';
        document.getElementById('uploadMessage').textContent = '';
        document.getElementById('uploadMessage').className = 'mt-4 text-sm';
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('uploadArea').classList.add('dragover');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('uploadArea').classList.remove('dragover');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('uploadArea').classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        handleFiles(files);
    }

    function handleFileSelect(e) {
        const files = e.target.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        if (files.length === 0) return;
        
        const file = files[0];
        
        // Check file size (200MB limit)
        if (file.size > 200 * 1024 * 1024) {
            showUploadMessage(`File "${file.name}" exceeds 200MB limit`, 'error');
            return;
        }
        
        // Store the actual File object for later upload
        draftFiles = [{
            name: file.name,
            size: file.size,
            file: file  // This is the actual File object that can be uploaded
        }];
        
        updateDraftDisplay();
        updateDraftSession();
        closeUploadModal();
    }

    function updateDraftDisplay() {
        const draftSection = document.getElementById('draftSubmissionMain');
        const draftFilesList = document.getElementById('draftFilesListMain');
        
        if (!draftSection || !draftFilesList) return;
        
        if (draftFiles.length > 0) {
            draftSection.classList.remove('hidden');
            draftFilesList.innerHTML = '';
            
            draftFiles.forEach((file, index) => {
                const fileSize = formatFileSize(file.size);
                const fileItem = document.createElement('div');
                fileItem.className = 'flex flex-col md:flex-row items-center justify-between text-sm p-3 bg-white rounded border border-blue-300 gap-2';
                fileItem.innerHTML = `
                    <span class="flex items-center w-full md:w-auto">
                        <i class="fas fa-file mr-2 text-blue-500"></i>
                        <div class="flex-1">
                            <div class="font-medium">${file.name}</div>
                            <div class="text-xs text-gray-500">${fileSize}</div>
                        </div>
                    </span>
                    <div class="space-x-2 w-full md:w-auto text-right">
                        <button 
                            onclick="removeDraftFile(${index})"
                            class="text-red-600 hover:text-red-800 text-sm">
                            <i class="fas fa-trash mr-1"></i>Remove
                        </button>
                    </div>
                `;
                draftFilesList.appendChild(fileItem);
            });
        } else {
            draftSection.classList.add('hidden');
        }
    }

    function removeDraftFile(index) {
        draftFiles.splice(index, 1);
        updateDraftDisplay();
        updateDraftSession();
        
        // If no more draft files, switch back to assignment page
        if (draftFiles.length === 0) {
            switchStudentPage('assignment');
        }
    }

    function saveAsDraft() {
        if (draftFiles.length === 0) {
            showDraftMessage('Please attach a file first.', 'error');
            return;
        }
        
        updateDraftSession();
        
        // Show success message and switch to review page
        showDraftMessage('Draft saved successfully! Redirecting to review...', 'success');
        setTimeout(() => {
            switchStudentPage('review');
            
            // Set session flag to show draft review on page reload
            fetch('handle-draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=set_review_flag&assignment_id=' + <?php echo $item_id; ?>
            });
        }, 1500);
    }

    function submitDraft() {
        if (draftFiles.length === 0) {
            alert('Please attach a file first.');
            return;
        }
        
        showDraftMessage('Submitting your work...', 'info');
        const file = draftFiles[0];
        uploadAndSubmitFile(file);
    }

    function submitDraftFromReview() {
        if (draftFiles.length === 0) {
            alert('No draft files found.');
            return;
        }
        
        showDraftMessage('Submitting your work...', 'info');
        const file = draftFiles[0];
        uploadAndSubmitFile(file);
    }

    function updateDraftSession() {
        // Prepare data for session storage (without the File object)
        const sessionData = draftFiles.map(file => ({
            name: file.name,
            size: file.size
            // Don't include the File object as it can't be stored in session
        }));
        
        const formData = new FormData();
        formData.append('assignment_id', <?php echo $item_id; ?>);
        formData.append('draft_files', JSON.stringify(sessionData));
        formData.append('action', 'save_draft');
        
        fetch('handle-draft.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to save draft:', data.message);
                showDraftMessage('Failed to save draft. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving draft:', error);
            showDraftMessage('Error saving draft. Please try again.', 'error');
        });
    }

    function uploadAndSubmitFile(file) {
        if (!file.file) {
            showDraftMessage('File data missing. Please re-select your file.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('file', file.file);
        formData.append('assignment_id', <?php echo $item_id; ?>);
        formData.append('student_id', <?php echo $user['id']; ?>);

        showDraftMessage('Uploading file...', 'info');

        fetch('submit-draft.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showDraftMessage('Assignment submitted successfully!', 'success');
                // Clear local draft files after successful submission
                draftFiles = [];
                
                // Clear the review flag since we've submitted
                fetch('handle-draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear_review_flag&assignment_id=' + <?php echo $item_id; ?>
                });
                
                // Switch back to assignment page and reload
                setTimeout(() => {
                    switchStudentPage('assignment');
                    location.reload();
                }, 2000);
            } else {
                showDraftMessage(`Failed: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showDraftMessage('Network error: ' + error.message, 'error');
        });
    }

    function showDraftMessage(message, type = 'info') {
        // Create a temporary message display
        const messageDiv = document.createElement('div');
        messageDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500 text-white' : 
            type === 'error' ? 'bg-red-500 text-white' : 
            'bg-blue-500 text-white'
        }`;
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(messageDiv);
        
        // Remove message after 3 seconds
        setTimeout(() => {
            if (document.body.contains(messageDiv)) {
                document.body.removeChild(messageDiv);
            }
        }, 3000);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function showUploadMessage(message, type = 'info') {
        const messageEl = document.getElementById('uploadMessage');
        messageEl.textContent = message;
        messageEl.className = `mt-4 text-sm ${type === 'error' ? 'text-red-600' : type === 'success' ? 'text-green-600' : 'text-gray-600'}`;
    }

    // =============================================
    // TEACHER-ONLY FUNCTIONALITY
    // =============================================
    <?php if ($user['role'] === 'teacher' && $type === 'assignment'): ?>
    const ASSIGNMENT_ID = <?php echo (int)$assignment['id']; ?>;

    function openEditDateModal() {
        const modal = document.getElementById('editDateModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditDateModal() {
        const modal = document.getElementById('editDateModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        const msg = document.getElementById('editDateMessage');
        if (msg) {
            msg.textContent = '';
            msg.className = 'text-sm';
        }
    }

    // Handle edit date form submit (AJAX)
    const editForm = document.getElementById('editDateForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const newDate = document.getElementById('newDueDate').value;
            const msg = document.getElementById('editDateMessage');
            msg.textContent = 'Saving...';
            msg.className = 'text-sm text-gray-600';

            fetch('update-assignment-date.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'assignment_id=' + encodeURIComponent(ASSIGNMENT_ID) +
                      '&due_date=' + encodeURIComponent(newDate)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msg.textContent = 'Due date updated successfully.';
                    msg.className = 'text-sm text-green-600';
                    // Update the displayed due date
                    const display = document.getElementById('dueDateDisplay');
                    if (display && data.formattedDate) {
                        display.textContent = data.formattedDate;
                    }
                    setTimeout(() => { closeEditDateModal(); }, 1500);
                } else {
                    msg.textContent = data.message || 'Failed to update due date.';
                    msg.className = 'text-sm text-red-600';
                }
            })
            .catch(() => {
                msg.textContent = 'Error updating due date.';
                msg.className = 'text-sm text-red-600';
            });
        });
    }

    // Deploy Assignment functionality
    function deployAssignment(assignmentId) {
        // Set the assignment ID in the modal
        document.getElementById('deployAssignmentId').value = assignmentId;
        
        // Show the deploy modal
        const modal = document.getElementById('deployModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDeployModal() {
        const modal = document.getElementById('deployModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        const msg = document.getElementById('deployMessage');
        if (msg) {
            msg.textContent = '';
            msg.className = 'text-sm';
        }
    }

    // Handle deploy form submission
    const deployForm = document.getElementById('deployForm');
    if (deployForm) {
        deployForm.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const assignmentId = document.getElementById('deployAssignmentId').value;
            const msg = document.getElementById('deployMessage');
            
            msg.textContent = 'Deploying assignment to Grade 11 ICT students...';
            msg.className = 'text-sm text-gray-600';

            fetch('deploy-assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'assignment_id=' + encodeURIComponent(assignmentId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    msg.textContent = data.message || 'Assignment deployed successfully!';
                    msg.className = 'text-sm text-green-600';
                    
                    // Close modal after success
                    setTimeout(() => {
                        closeDeployModal();
                        location.reload();
                    }, 1500);
                } else {
                    msg.textContent = data.message || 'Failed to deploy assignment.';
                    msg.className = 'text-sm text-red-600';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                msg.textContent = 'Network error occurred. Please try again.';
                msg.className = 'text-sm text-red-600';
            });
        });
    }

    // Enhanced grade submission function
    function gradeSubmission(submissionId, studentName, maxScore) {
        // Create a modal for grading
        const modalHtml = `
            <div id="gradeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Grade Submission</h3>
                    <p class="text-sm text-gray-600 mb-4">Grading submission from: <strong>${studentName}</strong></p>
                    
                    <form id="gradeForm" class="space-y-4">
                        <input type="hidden" name="submission_id" value="${submissionId}">
                        
                        <div>
                            <label for="score" class="block text-sm font-medium text-gray-700 mb-1">
                                Score (0-${maxScore})
                            </label>
                            <input
                                type="number"
                                id="score"
                                name="score"
                                min="0"
                                max="${maxScore}"
                                step="0.1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                        </div>
                        
                        <div>
                            <label for="feedback" class="block text-sm font-medium text-gray-700 mb-1">
                                Feedback (Optional)
                            </label>
                            <textarea
                                id="feedback"
                                name="feedback"
                                rows="3"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Provide feedback to the student..."
                            ></textarea>
                        </div>
                        
                        <div id="gradeMessage" class="text-sm"></div>
                        
                        <div class="flex justify-end space-x-2 pt-2">
                            <button type="button"
                                    onclick="closeGradeModal()"
                                    class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-3 py-1 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                                Submit Grade
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Handle form submission
        document.getElementById('gradeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitGrade(submissionId);
        });
    }

    function closeGradeModal() {
        const modal = document.getElementById('gradeModal');
        if (modal) {
            modal.remove();
        }
    }

    function submitGrade(submissionId) {
        const form = document.getElementById('gradeForm');
        const formData = new FormData(form);
        const messageEl = document.getElementById('gradeMessage');
        
        messageEl.textContent = 'Submitting grade...';
        messageEl.className = 'text-sm text-gray-600';

        fetch('grade-assignment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageEl.textContent = data.message || 'Grade submitted successfully!';
                messageEl.className = 'text-sm text-green-600';
                
                setTimeout(() => {
                    closeGradeModal();
                    location.reload();
                }, 1500);
            } else {
                messageEl.textContent = data.message || 'Failed to submit grade.';
                messageEl.className = 'text-sm text-red-600';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageEl.textContent = 'Network error occurred. Please try again.';
            messageEl.className = 'text-sm text-red-600';
        });
    }
    <?php endif; ?>
</script>
</body>
</html>