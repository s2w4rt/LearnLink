<?php
require_once 'config.php';
checkTeacherAuth();

$user = $_SESSION['user'] ?? null;

if (!$user) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Get teacher info
$teacher = $user;
$teacherId = $teacher['id'];

// Get teacher's subjects
$stmt = $db->prepare("
    SELECT s.*
    FROM assigned_subjects asg
    INNER JOIN subjects s ON asg.subject_id = s.id
    WHERE asg.teacher_id = ? AND s.is_active = 1
    ORDER BY s.subject_name
");
$stmt->execute([$teacherId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending assignments to grade (for sidebar badge)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT sa.id) as pending_count
    FROM student_assignments sa
    JOIN assignments a ON sa.assignment_id = a.id
    WHERE a.teacher_id = ? AND sa.status IN ('submitted', 'late') AND sa.score IS NULL
");
$stmt->execute([$teacherId]);
$pendingGrading = $stmt->fetchColumn();

// Get all assignments for this teacher
$stmt = $db->prepare("
    SELECT a.*, s.subject_name, s.subject_code,
           COUNT(sa.id) as submission_count,
           COUNT(CASE WHEN sa.status = 'graded' THEN 1 END) as graded_count
    FROM assignments a
    INNER JOIN assigned_subjects asg
        ON asg.subject_id = a.subject_id
       AND asg.teacher_id = a.teacher_id
    LEFT JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN student_assignments sa ON a.id = sa.assignment_id
    WHERE a.teacher_id = ?
    GROUP BY a.id
    ORDER BY a.due_date ASC, a.created_at DESC
");
$stmt->execute([$teacherId]);
$allAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter assignments by status if requested
$filter = $_GET['filter'] ?? 'all';
$filteredAssignments = [];

if ($filter === 'due') {
    foreach ($allAssignments as $assignment) {
        if (strtotime($assignment['due_date']) >= strtotime(date('Y-m-d'))) {
            $filteredAssignments[] = $assignment;
        }
    }
} elseif ($filter === 'overdue') {
    foreach ($allAssignments as $assignment) {
        if (strtotime($assignment['due_date']) < strtotime(date('Y-m-d'))) {
            $filteredAssignments[] = $assignment;
        }
    }
} else {
    $filteredAssignments = $allAssignments;
}

// Calculate statistics
$totalAssignments = count($allAssignments);
$dueAssignments = 0;
$overdueAssignments = 0;

foreach ($allAssignments as $assignment) {
    if (strtotime($assignment['due_date']) >= strtotime(date('Y-m-d'))) {
        $dueAssignments++;
    } else {
        $overdueAssignments++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - Teacher Assignments</title>
    <script src="/JS/teacher-notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .assignment-card { transition: all 0.3s ease; }
        .assignment-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .clickable-item { cursor: pointer; transition: all 0.2s ease; }
        .clickable-item:hover { background-color: #f9fafb; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filter-active { background-color: #3b82f6; color: white; }
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
            .stats-grid { grid-template-columns: 1fr; }
            .filter-tabs { flex-direction: column; gap: 0.5rem; }
            .filter-tabs a { text-align: center; }
            .mobile-search { display: none; }
            .school-name { display: none; }
            .mobile-user { display: none; }
            .page-header { flex-direction: column; gap: 1rem; }
            .page-header .flex { flex-direction: column; gap: 1rem; }
            .page-header a { width: 100%; }
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
                        <img src="all.png" alt="Allshs" class="h-10 w-10">
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
                            <?php echo substr($teacher['name'], 0, 2); ?>
                        </div>
                        <!-- User Name - Hidden on mobile -->
                        <div class="text-right hidden md:block">
                            <span class="font-medium block"><?php echo $teacher['name']; ?></span>
                            <span class="text-sm text-gray-600"><?php echo $teacher['strand']; ?> Teacher</span>
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
                            <a href="teacher-dashboard.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-home mr-3"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="teacher-courses.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-book mr-3"></i>
                                <span>My Courses</span>
                            </a>
                        </li>
                        <li>
                            <a href="teacher-assignments.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
                            <a href="teacher-materials.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-file-alt mr-3"></i>
                                <span>Learning Materials</span>
                            </a>
                        </li>
                        <li>
                            <a href="teacher-grades.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-chart-line mr-3"></i>
                                <span>Grades</span>
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-red-50 hover:text-red-600">
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
                        <h4 class="font-semibold text-gray-800"><?php echo $teacher['name']; ?></h4>
                        <p class="text-sm text-gray-600">Teacher ID: <?php echo $teacher['id']; ?></p>
                        <div class="mt-2 text-xs text-gray-500">
                            <div><?php echo $teacher['strand']; ?> Strand</div>
                            <div><?php echo count($subjects); ?> Subjects</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 main-content md:ml-0">
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 page-header">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-blue-800">Assignments</h1>
                        <p class="text-gray-600">Manage and track all your assignments</p>
                    </div>
                    <?php if (!empty($subjects)): ?>
                        <a href="teacher-course-detail.php?subject_id=<?php echo $subjects[0]['id']; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors w-full md:w-auto text-center">
                            <i class="fas fa-plus mr-2"></i>Create Assignment
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stats-grid">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold"><?php echo $totalAssignments; ?></p>
                            <p class="text-blue-100">Total Assignments</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tasks text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-sm text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold"><?php echo $dueAssignments; ?></p>
                            <p class="text-green-100">Due Assignments</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-sm text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold"><?php echo $overdueAssignments; ?></p>
                            <p class="text-orange-100">Overdue Assignments</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4 filter-tabs">
                    <a href="teacher-assignments.php?filter=all" 
                       class="px-4 py-2 rounded-lg <?php echo $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors text-center">
                        All Assignments
                    </a>
                    <a href="teacher-assignments.php?filter=due" 
                       class="px-4 py-2 rounded-lg <?php echo $filter === 'due' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors text-center">
                        Due Assignments
                    </a>
                    <a href="teacher-assignments.php?filter=overdue" 
                       class="px-4 py-2 rounded-lg <?php echo $filter === 'overdue' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors text-center">
                        Overdue Assignments
                    </a>
                </div>
            </div>

            <!-- Assignments List -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
                    <h2 class="text-xl font-bold text-blue-800">
                        <?php 
                        if ($filter === 'all') echo 'All Assignments';
                        elseif ($filter === 'due') echo 'Due Assignments';
                        else echo 'Overdue Assignments';
                        ?>
                    </h2>
                    <span class="text-sm text-gray-600"><?php echo count($filteredAssignments); ?> assignments</span>
                </div>

                <?php if (!empty($filteredAssignments)): ?>
                    <div class="space-y-4">
                        <?php foreach ($filteredAssignments as $assignment): ?>
                            <?php
                            $dueDate = new DateTime($assignment['due_date']);
                            $today = new DateTime();
                            $daysLeft = $today->diff($dueDate)->days;
                            $isOverdue = $dueDate < $today;
                            $isUrgent = !$isOverdue && $daysLeft <= 1;
                            
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
                            
                            // Calculate submission statistics
                            $totalSubs = (int)$assignment['submission_count'];
                            $gradedCount = (int)$assignment['graded_count'];
                            $submissionRate = $totalSubs > 0 ? round(($gradedCount / $totalSubs) * 100) : 0;
                            
                            $statusColor = $submissionRate >= 80 ? 'green' : ($submissionRate >= 50 ? 'yellow' : 'red');
                            $statusText = $submissionRate >= 80 ? 'Well Submitted' : ($submissionRate >= 50 ? 'Moderate' : 'Low Submission');
                            ?>
                            
                            <a href="view-detail.php?type=assignment&id=<?php echo $assignment['id']; ?>" class="block no-underline">
                                <div class="assignment-card border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex flex-col md:flex-row justify-between items-start mb-3 gap-3">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <div class="w-10 h-10 bg-<?php echo $typeColor; ?>-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-<?php echo $typeIcon; ?> text-<?php echo $typeColor; ?>-600"></i>
                                                </div>
                                                <div>
                                                    <h3 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                                    <p class="text-gray-600"><?php echo htmlspecialchars($assignment['subject_name'] ?? 'No Subject'); ?></p>
                                                </div>
                                            </div>
                                            <p class="text-gray-700 text-sm mt-2"><?php echo htmlspecialchars($assignment['description'] ?? 'No description available'); ?></p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="px-3 py-1 bg-gray-100 text-gray-800 text-sm rounded-full">
                                                <?php echo htmlspecialchars($assignment['type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center text-sm text-gray-500 gap-2">
                                        <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4">
                                            <div>
                                                <span class="font-medium <?php echo $isOverdue ? 'text-red-600' : ($isUrgent ? 'text-orange-600' : 'text-gray-600'); ?>">
                                                    Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                                </span>
                                                <?php if ($isOverdue): ?>
                                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Overdue</span>
                                                <?php elseif ($isUrgent): ?>
                                                    <span class="ml-2 px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">Due Soon</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="text-blue-600 font-medium">
                                                    <?php echo $gradedCount; ?>/<?php echo $totalSubs; ?> graded
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-2 py-1 bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800 text-xs rounded-full">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500 mb-2">No Assignments Found</h3>
                        <p class="text-gray-400">
                            <?php 
                            if ($filter === 'all') echo 'You haven\'t created any assignments yet.';
                            elseif ($filter === 'due') echo 'No assignments are currently due.';
                            else echo 'No assignments are overdue.';
                            ?>
                        </p>
                        <?php if ($filter !== 'all' && !empty($subjects)): ?>
                            <a href="teacher-assignments.php?filter=all" class="text-blue-600 hover:text-blue-700 text-sm mt-2 inline-block">
                                View all assignments
                            </a>
                        <?php elseif (!empty($subjects)): ?>
                            <a href="teacher-course-detail.php?subject_id=<?php echo $subjects[0]['id']; ?>" class="text-blue-600 hover:text-blue-700 text-sm mt-2 inline-block">
                                Create your first assignment
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
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