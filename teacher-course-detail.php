<?php
require_once 'config.php';
checkTeacherAuth();

$db   = getDB();
$user = $_SESSION['user'];
$teacherId = $user['id'] ?? 0;

// --- Basic teacher info ---
$stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallbacks so the page still works even if teacher row is missing
if (!$teacher) {
    $teacher = [
        'id'     => $teacherId,
        'name'   => $user['name'] ?? 'Teacher',
        'strand' => $user['strand'] ?? 'N/A',
    ];
}

// For the small "X Subjects • Y Students" text we'll keep simple,
// to avoid depending on other join tables.
$subjects      = [];
$totalStudents = 0;

// --- Subject to manage (from query string) ---
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
if ($subject_id <= 0) {
    die('Subject not specified.');
}

$stmt = $db->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$subject_id]);
$currentSubject = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentSubject) {
    die('Subject not found.');
}

// --- Assignments for this teacher AND current subject (with submission/grading stats) ---
// In teacher-course-detail.php - show all assignments (deployed and undeployed)
$stmt = $db->prepare("
    SELECT 
        a.*,
        COUNT(DISTINCT sa.id) AS total_submissions,
        SUM(CASE WHEN sa.score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
    FROM assignments a
    LEFT JOIN student_assignments sa ON sa.assignment_id = a.id
    WHERE a.teacher_id = ? AND a.subject_id = ?
    GROUP BY a.id
    ORDER BY a.quarter ASC, a.due_date ASC
");
$stmt->execute([$teacherId, $subject_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group assignments by quarter
$assignmentsByQuarter = [];
foreach ($assignments as $assignment) {
    $quarter = (int)($assignment['quarter'] ?? 0);
    if (!isset($assignmentsByQuarter[$quarter])) {
        $assignmentsByQuarter[$quarter] = [];
    }
    $assignmentsByQuarter[$quarter][] = $assignment;
}

// --- Learning materials grouped by quarter ---
$stmt = $db->prepare("
    SELECT * 
    FROM learning_materials 
    WHERE subject = ? 
      AND strand  = ? 
      AND status  = 'published'
    ORDER BY quarter ASC, created_at DESC
");
$stmt->execute([$currentSubject['subject_name'], $currentSubject['strand']]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$materialsByQuarter = [];
foreach ($materials as $material) {
    $quarter = (int)($material['quarter'] ?? 0);
    if (!isset($materialsByQuarter[$quarter])) {
        $materialsByQuarter[$quarter] = [];
    }
    $materialsByQuarter[$quarter][] = $material;
}

// --- Quarters that have at least something ---
$allQuarters = array_unique(
    array_merge(
        array_keys($assignmentsByQuarter),
        array_keys($materialsByQuarter)
    )
);
sort($allQuarters);

// --- Pending grading count (for sidebar badge) ---
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT sa.id) 
    FROM student_assignments sa
    JOIN assignments a ON sa.assignment_id = a.id
    WHERE a.teacher_id = ?
      AND sa.status IN ('submitted', 'late')
      AND sa.score IS NULL
");
$stmt->execute([$teacherId]);
$pendingGrading = (int)$stmt->fetchColumn();

// --- Simple subject statistics for the header ---
$totalAssignments    = count($assignments);
$completedAssignments = 0;
$pendingAssignments   = 0;

foreach ($assignments as $assignment) {
    if ($assignment['graded_count'] > 0) {
        $completedAssignments++;
    } else {
        $pendingAssignments++;
    }
}

$completionRate = $totalAssignments > 0
    ? round(($completedAssignments / $totalAssignments) * 100)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentSubject['subject_name']); ?> - ALLSHS Teacher</title>
    <script src="/JS/teacher-notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .assignment-card {
            transition: all 0.3s ease;
        }
        .assignment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            transition: transform 0.3s ease-in-out;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 40;
        }
        .sidebar-mobile.open {
            transform: translateX(0);
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
        }
        .overlay.active {
            display: block;
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; margin-right: 0; margin-bottom: 1rem; }
            .main-content { width: 100%; }
            .quarter-navigation { flex-wrap: wrap; }
            .quarter-navigation a { flex: 1; min-width: 120px; text-align: center; }
            .mobile-search { display: none; }
            .mobile-user { display: none; }
        }
        @media (min-width: 769px) {
            .sidebar-mobile { 
                transform: translateX(0);
                position: relative;
                width: 16rem;
                height: fit-content;
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
                    <button id="mobileMenuBtn" class="md:hidden p-2 text-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <!-- ALLSHS Icon + School Name -->
                    <div class="flex items-center space-x-3">
                        <img src="assets/images/allsys-logo.png" alt="Allshs" class="h-10 w-10">
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
            <a href="teacher-notifications.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Notifications</a>
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
                            <?php echo strtoupper(substr($teacher['name'], 0, 2)); ?>
                        </div>
                        <!-- User Name - Hidden on mobile -->
                        <div class="text-right hidden md:block">
                            <span class="font-medium block"><?php echo htmlspecialchars($teacher['name']); ?></span>
                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['strand']); ?> Teacher</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-6 flex flex-col md:flex-row">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-white shadow-md rounded-lg mr-0 md:mr-6 mb-6 md:mb-0 sidebar sidebar-mobile fixed md:relative top-0 left-0 z-40 md:z-auto">
            <div class="p-4 md:p-0 h-full overflow-y-auto">
                <div class="flex justify-between items-center md:hidden p-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold">Menu</h2>
                    <button id="closeMobileMenu" class="p-2">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
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
                            <?php echo htmlspecialchars($teacher['name']); ?>
                        </h4>
                        <p class="text-sm text-gray-600">
                            Teacher ID: <?php echo htmlspecialchars($teacher['id']); ?>
                        </p>
                        <div class="mt-2 text-xs text-gray-500">
                            <div><?php echo htmlspecialchars($teacher['strand']); ?> Strand</div>
                            <div><?php echo count($subjects); ?> Subjects • <?php echo $totalStudents; ?> Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 main-content md:ml-0">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="teacher-courses.php"
                   class="inline-flex items-center text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Courses
                </a>
            </div>

            <!-- Course Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row items-start justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-lg flex items-center justify-center text-white text-2xl"
                             style="background-color: <?php echo $currentSubject['color'] ?? '#2563EB'; ?>">
                            <i class="fas fa-<?php echo htmlspecialchars($currentSubject['icon'] ?? 'book'); ?>"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($currentSubject['subject_name']); ?>
                            </h1>
                            <p class="text-gray-600">
                                <?php echo htmlspecialchars($currentSubject['subject_code']); ?>
                                • <?php echo htmlspecialchars($currentSubject['strand']); ?> Strand
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                <?php echo htmlspecialchars($currentSubject['description'] ?? ''); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Completion Rate</div>
                        <div class="text-2xl font-bold text-blue-600"><?php echo $completionRate; ?>%</div>
                        <div class="text-sm text-gray-500 mt-1">
                            Assignments: <?php echo $totalAssignments; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quarter Navigation -->
            <?php if (!empty($allQuarters)): ?>
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">Jump to Quarter</h2>
                    <div class="flex flex-wrap gap-2 quarter-navigation">
                        <?php foreach ($allQuarters as $quarter): ?>
                            <a href="#quarter-<?php echo $quarter; ?>"
                               class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium">
                                Quarter <?php echo $quarter; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quarter Sections -->
            <div class="space-y-8">
                <?php if (!empty($allQuarters)): ?>
                    <?php foreach ($allQuarters as $quarter): ?>
                        <div id="quarter-<?php echo $quarter; ?>" class="quarter-section">
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-r-lg">
                                <h2 class="text-xl font-bold text-blue-800">
                                    Quarter <?php echo $quarter; ?>
                                </h2>
                                <p class="text-blue-600 text-sm">
                                    Learning materials and assignments for this quarter
                                </p>
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
                                                    'PPT'     => 'file-powerpoint',
                                                    'Video'   => 'video',
                                                    'Audio'   => 'music',
                                                    'Link'    => 'link',
                                                    default   => 'file'
                                                };
                                                $typeColor = match($material['type']) {
                                                    'Handout' => 'red',
                                                    'PPT'     => 'orange',
                                                    'Video'   => 'purple',
                                                    'Audio'   => 'blue',
                                                    'Link'    => 'green',
                                                    default   => 'gray'
                                                };
                                                ?>
                                                <!-- FIXED: Ensure the link is correct -->
                                                <a href="view-detail.php?type=material&id=<?php echo $material['id']; ?>" class="block no-underline">
                                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg clickable-item">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="w-10 h-10 bg-<?php echo $typeColor; ?>-100 rounded-lg flex items-center justify-center">
                                                                <i class="fas fa-<?php echo $typeIcon; ?> text-<?php echo $typeColor; ?>-600"></i>
                                                            </div>
                                                            <div class="flex-1">
                                                                <p class="font-medium text-gray-800">
                                                                    <?php echo htmlspecialchars($material['title']); ?>
                                                                </p>
                                                                <p class="text-sm text-gray-500">
                                                                    <?php echo htmlspecialchars($material['type']); ?>
                                                                    •
                                                                    <?php echo $material['description']
                                                                        ? htmlspecialchars($material['description'])
                                                                        : 'No description'; ?>
                                                                </p>
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

                                <!-- Assignments / Activities -->
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
                                                $totalSubs   = (int)$assignment['total_submissions'];
                                                $gradedCount = (int)$assignment['graded_count'];
                                                $submissionRate = $totalSubs > 0
                                                    ? round(($gradedCount / $totalSubs) * 100)
                                                    : 0;

                                                $statusColor = $submissionRate >= 80
                                                    ? 'green'
                                                    : ($submissionRate >= 50 ? 'yellow' : 'red');

                                                $statusText = $submissionRate >= 80
                                                    ? 'Well Submitted'
                                                    : ($submissionRate >= 50 ? 'Moderate' : 'Low Submission');
                                                
                                                // Determine assignment type icon and color
                                                $typeIcon = match($assignment['type']) {
                                                    'Quiz' => 'question-circle',
                                                    'Laboratory' => 'flask',
                                                    'Presentation' => 'presentation',
                                                    'Handout' => 'file-alt',
                                                    default => 'tasks'
                                                };
                                                $typeColor = match($assignment['type']) {
                                                    'Quiz' => 'purple',
                                                    'Laboratory' => 'green',
                                                    'Presentation' => 'blue',
                                                    'Handout' => 'orange',
                                                    default => 'gray'
                                                };
                                                ?>
                                                <a href="view-detail.php?type=assignment&id=<?php echo $assignment['id']; ?>" class="block no-underline">
                                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg clickable-item">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="w-10 h-10 bg-<?php echo $typeColor; ?>-100 rounded-lg flex items-center justify-center">
                                                                <i class="fas fa-<?php echo $typeIcon; ?> text-<?php echo $typeColor; ?>-600"></i>
                                                            </div>
                                                            <div class="flex-1">
                                                                <p class="font-medium text-gray-800">
                                                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                                                </p>
                                                                <p class="text-sm text-gray-500">
                                                                    <?php echo htmlspecialchars($assignment['type']); ?>
                                                                    • 
                                                                    Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                                                    • 
                                                                    <?php echo $gradedCount; ?>/<?php echo $totalSubs; ?> graded
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

                                <!-- If nothing in this quarter -->
                                <?php if (
                                    (!isset($materialsByQuarter[$quarter]) || empty($materialsByQuarter[$quarter])) &&
                                    (!isset($assignmentsByQuarter[$quarter]) || empty($assignmentsByQuarter[$quarter]))
                                ): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-folder-open text-3xl mb-3"></i>
                                        <p>No learning materials or assignments available for this quarter.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-6 text-center text-gray-500">
                        <i class="fas fa-folder-open text-3xl mb-3"></i>
                        <p>No learning materials or assignments created yet for this subject.</p>
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

            function toggleSidebar() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
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
                    closeSidebar();
                }
            });

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
        });
    </script>
</body>
</html>