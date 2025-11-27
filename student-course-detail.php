<?php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];

// Get student data from database
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get subject ID from URL
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($subject_id <= 0) {
    header('Location: student-dashboard.php');
    exit;
}

// Get subject details
$stmt = $db->prepare("SELECT * FROM subjects WHERE id = ? AND strand = ? AND grade_level IN (?, 'ALL') AND is_active = 1");
$stmt->execute([$subject_id, $student['strand'], $student['grade_level']]);
$subject = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subject) {
    header('Location: student-dashboard.php');
    exit;
}

// DEBUG: Check if we have assignments
error_log("Subject ID: " . $subject_id);
error_log("Student ID: " . $user['id']);

// Get assignments for this subject - MODIFIED QUERY to only show assignments that are NOT submitted/graded
$stmt = $db->prepare("
    SELECT a.*, sa.status as student_status, sa.score, sa.submitted_at, sa.feedback 
    FROM assignments a 
    LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
    WHERE a.subject_id = ? AND a.status = 'active' AND a.is_deployed = 1
    AND (sa.status IS NULL OR sa.status = 'assigned')
    ORDER BY a.quarter ASC, a.due_date ASC
");
$stmt->execute([$user['id'], $subject_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DEBUG: Log assignments count
error_log("Assignments found: " . count($assignments));

// Group assignments by quarter
$assignmentsByQuarter = [];
foreach ($assignments as $assignment) {
    $quarter = $assignment['quarter'];
    if (!isset($assignmentsByQuarter[$quarter])) {
        $assignmentsByQuarter[$quarter] = [];
    }
    $assignmentsByQuarter[$quarter][] = $assignment;
}

// DEBUG: Log grouped assignments
error_log("Assignments by quarter: " . print_r(array_keys($assignmentsByQuarter), true));

// Get learning materials for this subject grouped by quarter
$stmt = $db->prepare("
    SELECT * FROM learning_materials 
    WHERE subject = ? AND strand = ? AND status = 'published'
    ORDER BY quarter ASC, created_at DESC
");
$stmt->execute([$subject['subject_name'], $student['strand']]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group materials by quarter
$materialsByQuarter = [];
foreach ($materials as $material) {
    $quarter = $material['quarter'];
    if (!isset($materialsByQuarter[$quarter])) {
        $materialsByQuarter[$quarter] = [];
    }
    $materialsByQuarter[$quarter][] = $material;
}

// Get pending assignments count for sidebar
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM assignments a 
    LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
    WHERE a.strand = ? AND a.status = 'active' AND a.is_deployed = 1
    AND (sa.status IS NULL OR sa.status = 'assigned')
    AND a.due_date >= CURDATE()
");
$stmt->execute([$user['id'], $student['strand']]);
$totalPendingAssignments = $stmt->fetchColumn();

// Calculate subject statistics
$totalAssignments = count($assignments);
$completedAssignments = 0;
$pendingAssignments = 0;
$overdueAssignments = 0;
$averageScore = 0;
$totalScore = 0;
$scoredAssignments = 0;

foreach ($assignments as $assignment) {
    if ($assignment['student_status'] === 'graded' || $assignment['student_status'] === 'submitted') {
        $completedAssignments++;
    } elseif ($assignment['student_status'] === 'assigned' || !$assignment['student_status']) {
        $pendingAssignments++;
        // Check if overdue
        if (strtotime($assignment['due_date']) < time()) {
            $overdueAssignments++;
        }
    }
    
    if ($assignment['score'] !== null) {
        $totalScore += $assignment['score'];
        $scoredAssignments++;
    }
}

if ($scoredAssignments > 0) {
    $averageScore = round($totalScore / $scoredAssignments, 2);
}

$completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100) : 0;

// Get all quarters that have either assignments or materials
$allQuarters = array_unique(array_merge(array_keys($assignmentsByQuarter), array_keys($materialsByQuarter)));
sort($allQuarters);

// DEBUG: Final check
error_log("All quarters to display: " . print_r($allQuarters, true));
error_log("Assignments by quarter count: " . count($assignmentsByQuarter));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $subject['subject_name']; ?> - ALLSHS</title>
    <script src="/JS/notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .progress-ring {
        transition: stroke-dashoffset 0.5s ease;
    }
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .assignment-card {
        transition: all 0.3s ease;
    }
    .assignment-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .quarter-section {
        scroll-margin-top: 2rem;
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
    .sidebar-mobile {
        transform: translateX(-100%);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 40;
        background-color: white;
        overflow-y: auto;
        box-shadow: none;
        opacity: 0;
    }
    .sidebar-mobile.open {
        transform: translateX(0);
        opacity: 1;
        box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
    }
    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 30;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .overlay.active {
        display: block;
        opacity: 1;
    }
    .sidebar-content {
        transform: translateX(-20px);
        opacity: 0;
        transition: all 0.3s ease 0.1s;
    }
    .sidebar-mobile.open .sidebar-content {
        transform: translateX(0);
        opacity: 1;
    }
    .nav-item {
        transform: translateX(-10px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    .sidebar-mobile.open .nav-item:nth-child(1) { transition-delay: 0.1s; }
    .sidebar-mobile.open .nav-item:nth-child(2) { transition-delay: 0.15s; }
    .sidebar-mobile.open .nav-item:nth-child(3) { transition-delay: 0.2s; }
    .sidebar-mobile.open .nav-item:nth-child(4) { transition-delay: 0.25s; }
    .sidebar-mobile.open .nav-item:nth-child(5) { transition-delay: 0.3s; }
    .sidebar-mobile.open .nav-item:nth-child(6) { transition-delay: 0.35s; }
    .sidebar-mobile.open .nav-item {
        transform: translateX(0);
        opacity: 1;
    }
    .student-info-card {
        transform: translateY(10px);
        opacity: 0;
        transition: all 0.4s ease 0.4s;
    }
    .sidebar-mobile.open .student-info-card {
        transform: translateY(0);
        opacity: 1;
    }
    @media (max-width: 768px) {
        .sidebar-mobile:not(.open) {
            display: none;
        }
        .container { flex-direction: column; }
        .sidebar { width: 100%; margin-right: 0; margin-bottom: 1rem; }
        .main-content { width: 100%; }
        .quarter-navigation { flex-wrap: wrap; }
        .quarter-navigation a { flex: 1; min-width: 120px; text-align: center; }
        .mobile-search { display: none; }
        .mobile-user { display: none; }
        .page-header { flex-direction: column; gap: 1rem; }
        .course-header { flex-direction: column; gap: 1rem; }
    }
    @media (min-width: 769px) {
        .sidebar-mobile { 
            transform: translateX(0);
            position: relative;
            width: 16rem;
            height: fit-content;
            max-height: none;
            overflow-y: visible;
            display: block !important;
            opacity: 1;
            box-shadow: none;
        }
        .sidebar-content {
            transform: translateX(0);
            opacity: 1;
        }
        .nav-item {
            transform: translateX(0);
            opacity: 1;
        }
        .student-info-card {
            transform: translateY(0);
            opacity: 1;
        }
        .mobile-menu-btn { display: none; }
        .overlay { display: none !important; }
    }
</style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Mobile Overlay -->
    <div id="overlay" class="overlay"></div>

    <!-- Header -->
    <header class="bg-white shadow-md relative z-20">
        <div class="container mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Left Section: Menu Button + ALLSHS Icon + School Name -->
                <div class="flex items-center space-x-4">
                    <!-- Mobile Menu Button - Visible on mobile only -->
                    <button id="mobileMenuBtn" class="md:hidden p-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <!-- ALLSHS Icon + School Name -->
                    <div class="flex items-center space-x-3">
                        <img src="a.png" alt="Allshs" class="h-10 w-10">
                        <!-- School Name - Show on both mobile and desktop -->
                        <h1 class="text-lg md:text-xl font-bold text-blue-800">Angelo Levardo SHS</h1>
                    </div>
                </div>

                <!-- Right Section: Notification + Search + User -->
                <div class="flex items-center space-x-4">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notificationBell" class="relative p-2 text-gray-600 hover:text-blue-600 transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden" id="notificationCount">0</span>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl z-50 hidden border border-gray-200">
                            <div class="p-4 border-b border-gray-200 bg-blue-50 rounded-t-lg">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-semibold text-gray-800">Notifications</h3>
                                    <button id="markAllRead" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Mark all read</button>
                                </div>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <div id="notificationList">
                                    <div class="text-center py-8">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                                        <p class="text-gray-500 text-sm">Loading notifications...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3 border-t border-gray-200 text-center bg-gray-50 rounded-b-lg">
                                <a href="student-notifications.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Bar - Hidden on mobile -->
                    <div class="relative hidden md:block">
                        <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    
                    <!-- User Icon Only - No name on mobile -->
                    <div class="flex items-center space-x-2">
                        <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                            <?php echo substr($student['full_name'], 0, 2); ?>
                        </div>
                        <!-- User Name - Hidden on mobile -->
                        <div class="text-right hidden md:block">
                            <span class="font-medium block"><?php echo $student['full_name']; ?></span>
                            <span class="text-sm text-gray-600"><?php echo $student['strand']; ?> Student</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-6 flex flex-col md:flex-row">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-white shadow-md rounded-lg mr-0 md:mr-6 mb-6 md:mb-0 sidebar sidebar-mobile fixed md:relative top-0 left-0 z-40 md:z-auto">
            <div class="p-4 md:p-0 h-full overflow-y-auto sidebar-content">
                <div class="flex justify-between items-center md:hidden p-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold">Menu</h2>
                    <button id="closeMobileMenu" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
                <nav class="p-4">
                    <ul class="space-y-2">
                        <li class="nav-item">
                            <a href="student-dashboard.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-300">
                                <i class="fas fa-home mr-3"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student-courses.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600 transition-all duration-300">
                                <i class="fas fa-book mr-3"></i>
                                <span>My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student-assignments.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-300">
                                <i class="fas fa-tasks mr-3"></i>
                                <span>Assignments</span>
                                <?php if ($totalPendingAssignments > 0): ?>
                                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">
                                        <?php echo $totalPendingAssignments; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student-materials.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-300">
                                <i class="fas fa-file-alt mr-3"></i>
                                <span>Learning Materials</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student-grades.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-300">
                                <i class="fas fa-chart-line mr-3"></i>
                                <span>Grades</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all duration-300">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Student Info Card -->
                <div class="mt-6 p-4 border-t border-gray-200 student-info-card">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3 transition-transform duration-300 hover:scale-105">
                            <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800"><?php echo $student['full_name']; ?></h4>
                        <p class="text-sm text-gray-600"><?php echo $student['student_id']; ?></p>
                        <div class="mt-2 text-xs text-gray-500">
                            <div><?php echo $student['strand']; ?></div>
                            <div>Grade <?php echo $student['grade_level']; ?><?php echo $student['section'] ? ' • ' . $student['section'] : ''; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 main-content md:ml-0">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="student-dashboard.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Dashboard
                </a>
            </div>

            <!-- Course Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row items-start justify-between gap-4 course-header">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-lg flex items-center justify-center text-white text-2xl" style="background-color: <?php echo $subject['color']; ?>">
                            <i class="fas fa-<?php echo $subject['icon']; ?>"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800"><?php echo $subject['subject_name']; ?></h1>
                            <p class="text-gray-600"><?php echo $subject['subject_code']; ?> • <?php echo $student['strand']; ?> Strand</p>
                            <p class="text-sm text-gray-500 mt-1"><?php echo $subject['description']; ?></p>
                        </div>
                    </div>
                    <div class="text-left md:text-right">
                        <div class="text-sm text-gray-500">Completion Rate</div>
                        <div class="text-2xl font-bold text-blue-600"><?php echo $completionRate; ?>%</div>
                        <div class="text-sm text-gray-500 mt-1">Assignments: <?php echo $totalAssignments; ?></div>
                    </div>
                </div>
            </div>

            <!-- Quarter Navigation -->
            <?php if (!empty($allQuarters)): ?>
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-3">Jump to Quarter</h2>
                <div class="flex flex-wrap gap-2 quarter-navigation">
                    <?php foreach ($allQuarters as $quarter): ?>
                        <a href="#quarter-<?php echo $quarter; ?>" class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium">
                            Quarter <?php echo $quarter; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="space-y-8">
                <?php if (!empty($allQuarters)): ?>
                    <?php foreach ($allQuarters as $quarter): ?>
                        <!-- Quarter Section -->
                        <div id="quarter-<?php echo $quarter; ?>" class="quarter-section">
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-r-lg">
                                <h2 class="text-xl font-bold text-blue-800">Quarter <?php echo $quarter; ?></h2>
                                <p class="text-blue-600 text-sm">Learning materials and assignments for this quarter</p>
                            </div>

                            <div class="bg-white rounded-lg shadow-md p-6">
                                <h3 class="text-lg font-bold text-gray-800 mb-4">
                                    <i class="fas fa-file-alt text-green-600 mr-2"></i>
                                    Learning Materials & Activities - Quarter <?php echo $quarter; ?>
                                </h3>
                                
                                <!-- Learning Materials -->
                                <?php if (isset($materialsByQuarter[$quarter]) && !empty($materialsByQuarter[$quarter])): ?>
                                <div class="mb-6">
                                    <h4 class="text-md font-semibold text-gray-700 mb-3">
                                        <i class="fas fa-book mr-2"></i>Learning Resources
                                    </h4>
                                    <div class="space-y-3">
                                        <?php foreach ($materialsByQuarter[$quarter] as $material): ?>
                                            <?php
                                            $typeIcon = match($material['type']) {
                                                'Handout' => 'file-pdf',
                                                'PPT' => 'file-powerpoint',
                                                'Video' => 'video',
                                                'Audio' => 'music',
                                                'Link' => 'link',
                                                default => 'file'
                                            };
                                            $typeColor = match($material['type']) {
                                                'Handout' => 'red',
                                                'PPT' => 'orange',
                                                'Video' => 'purple',
                                                'Audio' => 'blue',
                                                'Link' => 'green',
                                                default => 'gray'
                                            };
                                            ?>
                                            <a href="view-detail.php?type=material&id=<?php echo $material['id']; ?>&subject_id=<?php echo $subject_id; ?>" class="block no-underline">
                                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg clickable-item">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 bg-<?php echo $typeColor; ?>-100 rounded-lg flex items-center justify-center">
                                                            <i class="fas fa-<?php echo $typeIcon; ?> text-<?php echo $typeColor; ?>-600"></i>
                                                        </div>
                                                        <div>
                                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($material['title']); ?></p>
                                                            <p class="text-sm text-gray-500"><?php echo $material['type']; ?> • <?php echo $material['description'] ? htmlspecialchars($material['description']) : 'No description'; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="text-sm text-gray-400">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Assignments/Activities -->
                                <?php if (isset($assignmentsByQuarter[$quarter]) && !empty($assignmentsByQuarter[$quarter])): ?>
                                <div>
                                    <h4 class="text-md font-semibold text-gray-700 mb-3">
                                        <i class="fas fa-tasks mr-2"></i>Activities & Assignments
                                        <span class="text-sm font-normal text-gray-500">
                                            (<?php echo count($assignmentsByQuarter[$quarter]); ?> assignments)
                                        </span>
                                    </h4>
                                    <div class="space-y-3">
                                        <?php foreach ($assignmentsByQuarter[$quarter] as $assignment): ?>
                                            <?php
                                            $status = $assignment['student_status'] ?? 'assigned';
                                            $isOverdue = strtotime($assignment['due_date']) < time() && $status === 'assigned';
                                            $statusColor = match($status) {
                                                'graded' => 'green',
                                                'submitted' => 'blue',
                                                'late' => 'red',
                                                default => $isOverdue ? 'red' : 'yellow'
                                            };
                                            $statusText = match($status) {
                                                'graded' => 'Graded',
                                                'submitted' => 'Submitted',
                                                'late' => 'Late',
                                                default => $isOverdue ? 'Overdue' : 'Assigned'
                                            };
                                            ?>
                                            <a href="view-detail.php?type=assignment&id=<?php echo $assignment['id']; ?>&subject_id=<?php echo $subject_id; ?>" class="block no-underline">
                                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg clickable-item">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 bg-<?php echo $statusColor; ?>-100 rounded-lg flex items-center justify-center">
                                                            <i class="fas fa-tasks text-<?php echo $statusColor; ?>-600"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <p class="font-medium text-gray-800"><?php echo $assignment['title']; ?></p>
                                                            <p class="text-sm text-gray-500">
                                                                <?php echo $assignment['type']; ?>
                                                                • 
                                                                Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                                                <?php if ($assignment['score'] !== null): ?>
                                                                    • Score: <?php echo $assignment['score']; ?>/<?php echo $assignment['max_score']; ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center space-x-3">
                                                        <!-- Status badge -->
                                                        <span class="px-2 py-1 bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800 text-xs rounded-full">
                                                            <?php echo $statusText; ?>
                                                        </span>
                                                        <span class="text-sm text-gray-400">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ((!isset($materialsByQuarter[$quarter]) || empty($materialsByQuarter[$quarter])) && (!isset($assignmentsByQuarter[$quarter]) || empty($assignmentsByQuarter[$quarter]))): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-folder-open text-3xl mb-3"></i>
                                        <p>No learning materials or assignments available for this quarter.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- No Content Available -->
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">No Content Available</h3>
                        <p class="text-gray-600">There are no learning materials or assignments for this subject yet.</p>
                        <p class="text-sm text-gray-500 mt-1">Please check back later or contact your teacher.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const sidebar = document.querySelector('.sidebar-mobile');
            const overlay = document.getElementById('overlay');
            const mainContent = document.querySelector('.main-content');

            function openSidebar() {
                sidebar.style.display = 'block';
                // Force reflow
                sidebar.offsetHeight;
                sidebar.classList.add('open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                
                // Hide sidebar on mobile after closing animation
                setTimeout(() => {
                    if (!sidebar.classList.contains('open')) {
                        sidebar.style.display = 'none';
                    }
                }, 400);
            }

            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', openSidebar);
            }

            if (closeMobileMenu) {
                closeMobileMenu.addEventListener('click', closeSidebar);
            }

            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }

            // Close sidebar when clicking on main content on mobile
            if (mainContent) {
                mainContent.addEventListener('click', function() {
                    if (window.innerWidth < 768 && sidebar.classList.contains('open')) {
                        closeSidebar();
                    }
                });
            }

            // Close sidebar when window is resized to desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    // Show sidebar on desktop
                    sidebar.style.display = 'block';
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else {
                    // Hide sidebar on mobile if not open
                    if (!sidebar.classList.contains('open')) {
                        sidebar.style.display = 'none';
                    }
                }
            });

            // Initialize sidebar state on page load
            if (window.innerWidth < 768) {
                sidebar.style.display = 'none';
            }

            // Notification Bell Functionality - CONSISTENT VERSION
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (notificationBell && notificationDropdown) {
                let notificationsLoaded = false;
                
                // Toggle dropdown on bell click
                notificationBell.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Toggle dropdown visibility
                    if (notificationDropdown.classList.contains('hidden')) {
                        notificationDropdown.classList.remove('hidden');
                        if (!notificationsLoaded) {
                            loadNotifications();
                            notificationsLoaded = true;
                        }
                    } else {
                        notificationDropdown.classList.add('hidden');
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!notificationDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                        notificationDropdown.classList.add('hidden');
                    }
                });

                // Prevent dropdown from closing when clicking inside it
                notificationDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                // Mark all as read functionality
                const markAllReadBtn = document.getElementById('markAllRead');
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        markAllAsRead();
                    });
                }

                // Load notifications function
                function loadNotifications() {
                    const notificationList = document.getElementById('notificationList');
                    
                    // Show loading state
                    notificationList.innerHTML = `
                        <div class="text-center py-4">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
                            <p class="text-gray-500 text-sm">Loading notifications...</p>
                        </div>
                    `;

                    fetch('get-student-notifications.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Notifications data:', data);
                            
                            if (data.notifications && data.notifications.length > 0) {
                                notificationList.innerHTML = data.notifications.map(notification => `
                                    <div class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer notification-item ${notification.is_read ? '' : 'bg-blue-50'}" 
                                         data-notification-id="${notification.id}" 
                                         data-assignment-id="${notification.assignment_id || '0'}" 
                                         data-notification-type="${notification.type || 'system'}">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <p class="font-medium text-sm text-gray-800">${notification.title || 'Notification'}</p>
                                                <p class="text-xs text-gray-600 mt-1">${notification.message || 'New update'}</p>
                                                <p class="text-xs text-gray-400 mt-1">${notification.time || 'Recently'}</p>
                                            </div>
                                            ${!notification.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full ml-2 mt-1 flex-shrink-0"></span>' : ''}
                                        </div>
                                    </div>
                                `).join('');
                                
                                // Add click event to notifications
                                document.querySelectorAll('.notification-item').forEach(item => {
                                    item.addEventListener('click', function() {
                                        const notificationId = this.getAttribute('data-notification-id');
                                        const assignmentId = this.getAttribute('data-assignment-id');
                                        const type = this.getAttribute('data-notification-type');
                                        
                                        // Mark as read if unread
                                        if (this.classList.contains('bg-blue-50')) {
                                            markAsRead(notificationId);
                                            this.classList.remove('bg-blue-50');
                                            const unreadDot = this.querySelector('.bg-blue-500');
                                            if (unreadDot) {
                                                unreadDot.remove();
                                            }
                                            // Update counter
                                            updateNotificationCounter(data.unread_count - 1);
                                        }
                                        
                                        // Redirect if it's an assignment notification
                                        if (assignmentId && assignmentId !== '0' && type === 'assignment') {
                                            window.location.href = `view-detail.php?id=${assignmentId}&type=assignment`;
                                        }
                                        
                                        // Close dropdown
                                        notificationDropdown.classList.add('hidden');
                                    });
                                });
                                
                                updateNotificationCounter(data.unread_count);
                            } else {
                                notificationList.innerHTML = `
                                    <div class="text-center py-8">
                                        <i class="fas fa-bell-slash text-2xl text-gray-400 mb-3"></i>
                                        <p class="text-gray-500">No notifications</p>
                                        <p class="text-sm text-gray-400 mt-1">You're all caught up!</p>
                                    </div>
                                `;
                                updateNotificationCounter(0);
                            }
                        })
                        .catch(error => {
                            console.error('Error loading notifications:', error);
                            notificationList.innerHTML = `
                                <div class="text-center py-4">
                                    <i class="fas fa-exclamation-triangle text-2xl text-red-400 mb-2"></i>
                                    <p class="text-gray-500">Failed to load notifications</p>
                                    <p class="text-sm text-gray-400 mt-1">Please try again later</p>
                                    <button onclick="loadNotifications()" class="mt-2 text-blue-600 text-sm hover:text-blue-800">
                                        Retry
                                    </button>
                                </div>
                            `;
                            updateNotificationCounter(0);
                        });
                }

                function markAsRead(notificationId) {
                    if (!notificationId) return;
                    
                    const formData = new URLSearchParams();
                    formData.append('notification_id', notificationId);

                    fetch('mark-notification-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to mark as read:', data);
                        }
                    })
                    .catch(error => console.error('Error marking as read:', error));
                }

                function markAllAsRead() {
                    fetch('mark-all-notifications-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI - Remove all unread indicators
                            document.querySelectorAll('.notification-item').forEach(item => {
                                item.classList.remove('bg-blue-50');
                                const unreadDot = item.querySelector('.bg-blue-500');
                                if (unreadDot) {
                                    unreadDot.remove();
                                }
                            });
                            
                            // Hide the notification counter permanently
                            updateNotificationCounter(0);
                            
                            // Show success message
                            const notificationList = document.getElementById('notificationList');
                            const originalContent = notificationList.innerHTML;
                            
                            // Create success message
                            const successMessage = document.createElement('div');
                            successMessage.className = 'p-3 bg-green-50 text-green-700 text-sm text-center border-b border-green-200';
                            successMessage.textContent = 'All notifications marked as read';
                            
                            // Insert success message at the top
                            notificationList.insertBefore(successMessage, notificationList.firstChild);
                            
                            // Remove success message after 3 seconds
                            setTimeout(() => {
                                if (successMessage.parentNode) {
                                    successMessage.remove();
                                }
                            }, 3000);
                            
                            console.log('All notifications marked as read successfully');
                        } else {
                            console.error('Failed to mark all notifications as read');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking all as read:', error);
                    });
                }

                function updateNotificationCounter(count) {
                    const notificationCount = document.getElementById('notificationCount');
                    
                    if (count > 0) {
                        notificationCount.textContent = count > 9 ? '9+' : count;
                        notificationCount.classList.remove('hidden');
                        
                        // Store in sessionStorage for consistency across pages
                        sessionStorage.setItem('unreadNotifications', count);
                    } else {
                        notificationCount.classList.add('hidden');
                        notificationCount.textContent = '0';
                        sessionStorage.setItem('unreadNotifications', '0');
                    }
                }

                // Load initial notification count on page load
                function loadInitialNotificationCount() {
                    // Check if we have a cached count in sessionStorage
                    const cachedCount = sessionStorage.getItem('unreadNotifications');
                    
                    if (cachedCount !== null) {
                        updateNotificationCounter(parseInt(cachedCount));
                    }
                    
                    // Then refresh from server
                    fetch('get-student-notifications.php')
                        .then(response => response.json())
                        .then(data => {
                            updateNotificationCounter(data.unread_count);
                        })
                        .catch(error => {
                            console.error('Error loading notification count:', error);
                            // Keep cached count if server fails
                        });
                }

                // Initialize notification count
                loadInitialNotificationCount();
                
                // Refresh notification count every 30 seconds
                setInterval(loadInitialNotificationCount, 30000);
            } else {
                console.error('Notification elements not found');
            }

            // Smooth scrolling for quarter navigation
            document.querySelectorAll('a[href^="#quarter-"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>