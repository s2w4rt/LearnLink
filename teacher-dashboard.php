<?php
require_once 'config.php';
checkTeacherAuth();
$currentUser = getCurrentUser();

$user = $_SESSION['user'] ?? null;

if (!$user) {
    // Just in case, redirect if no user in session
    header('Location: login.php');
    exit;
}

$db = getDB();

/**
 * OPTION 1: If your $_SESSION['user'] already contains teacher fields
 * like name, strand, id, etc., we can just reuse it:
 */
$teacher = $user;

// If you PREFER to always load fresh from DB, you can use this instead:
// $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
// $stmt->execute([$user['id']]);
// $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Make sure we have an ID to use in queries:
$teacherId = $teacher['id'];

// Get teacher's subjects based on assigned_subjects (only subjects assigned to this teacher)
$stmt = $db->prepare("
    SELECT s.*
    FROM assigned_subjects asg
    INNER JOIN subjects s ON asg.subject_id = s.id
    WHERE asg.teacher_id = ? AND s.is_active = 1
    ORDER BY s.subject_name
");
$stmt->execute([$teacherId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending assignments to grade
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT sa.id) as pending_count
    FROM student_assignments sa
    JOIN assignments a ON sa.assignment_id = a.id
    WHERE a.teacher_id = ? AND sa.status IN ('submitted', 'late') AND sa.score IS NULL
");
$stmt->execute([$teacherId]);
$pendingGrading = $stmt->fetchColumn();

// Get total students across all subjects
$totalStudents = 0;
$studentCounts = [];
foreach ($subjects as $subject) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as student_count 
        FROM students 
        WHERE strand = ? AND (grade_level = ? OR grade_level = 'ALL')
    ");
    $stmt->execute([$subject['strand'], $subject['grade_level']]);
    $count = $stmt->fetchColumn();
    $studentCounts[$subject['id']] = $count;
    $totalStudents += $count;
}

// Get recent assignments (only from subjects assigned to this teacher)
$stmt = $db->prepare("
    SELECT a.*, s.subject_name,
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
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$teacherId]);
$recentAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming assignment deadlines (only from subjects assigned to this teacher)
$stmt = $db->prepare("
    SELECT a.*, s.subject_name
    FROM assignments a
    INNER JOIN assigned_subjects asg
        ON asg.subject_id = a.subject_id
       AND asg.teacher_id = a.teacher_id
    LEFT JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = ? AND a.due_date >= CURDATE() AND a.status = 'active'
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->execute([$teacherId]);
$upcomingDeadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current month calendar data
$currentMonth = date('n');
$currentYear  = date('Y');
$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDay     = date('N', strtotime("$currentYear-$currentMonth-01"));

// Get assignment deadlines for calendar highlighting (only from subjects assigned to this teacher)
$stmt = $db->prepare("
    SELECT DISTINCT DAY(a.due_date) as day 
    FROM assignments a
    INNER JOIN assigned_subjects asg
        ON asg.subject_id = a.subject_id
       AND asg.teacher_id = a.teacher_id
    WHERE a.teacher_id = ? 
      AND MONTH(a.due_date) = ? 
      AND YEAR(a.due_date) = ? 
      AND a.status = 'active'
");
$stmt->execute([$teacherId, $currentMonth, $currentYear]);
$assignmentDays = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - Teacher Dashboard</title>
    <script src="/JS/teacher-notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .slider { transition: transform 0.5s ease-in-out; }
    .fade-in { animation: fadeIn 0.5s ease-in; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .course-progress { transition: all 0.3s ease; }
    .course-progress:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
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
    .clickable-task {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .clickable-task:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    @media (max-width: 768px) {
        .container { flex-direction: column; }
        .sidebar { width: 100%; margin-right: 0; margin-bottom: 1rem; }
        .main-content { width: 100%; }
        .right-sidebar { width: 100%; margin-left: 0; }
        .stats-grid { grid-template-columns: 1fr; }
        .courses-grid { grid-template-columns: 1fr; }
        .mobile-search { display: none; }
        .school-name { display: none; }
        .mobile-user { display: none; }
        .page-header { flex-direction: column; gap: 1rem; }
        .calendar-grid { grid-template-columns: repeat(7, 1fr); }
        .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (min-width: 769px) {
        .sidebar-mobile { 
            transform: translateX(0);
            position: relative;
            width: 16rem;
            height: fit-content; /* Changed from 'auto' to 'fit-content' */
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
                            <a href="teacher-dashboard.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
                            <a href="teacher-assignments.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
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
                            <div><?php echo count($subjects); ?> Subjects • <?php echo $totalStudents; ?> Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 main-content md:ml-0">
            <!-- Image Slider -->
            <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden relative">
                <div class="slider flex">
                    <div class="w-full flex-shrink-0 fade-in">
                        <img src="1920-x-1080-hd-1qq8r4pnn8cmcew4.jpg" alt="ALLSYS Campus" class="w-full h-64 object-cover">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 text-white">
                            <h2 class="text-xl font-bold">Welcome to ALLSHS, <?php echo explode(' ', $teacher['name'])[0]; ?>!</h2>
                            <p>Your teaching dashboard - <?php echo $teacher['strand']; ?> Strand</p>
                        </div>
                    </div>
                    <div class="w-full flex-shrink-0 fade-in hidden">
                        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1471&q=80" alt="Online Teaching" class="w-full h-64 object-cover">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 text-white">
                            <h2 class="text-xl font-bold">Manage Your Classes</h2>
                            <p>Create assignments and track student progress</p>
                        </div>
                    </div>
                    <div class="w-full flex-shrink-0 fade-in hidden">
                        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Teaching Resources" class="w-full h-64 object-cover">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 text-white">
                            <h2 class="text-xl font-bold">Teaching Resources</h2>
                            <p>Access educational materials and tools</p>
                        </div>
                    </div>
                </div>
                <div class="absolute bottom-4 right-4 flex space-x-2">
                    <button class="slider-btn w-3 h-3 rounded-full bg-white opacity-50 hover:opacity-100"></button>
                    <button class="slider-btn w-3 h-3 rounded-full bg-white opacity-50 hover:opacity-100"></button>
                    <button class="slider-btn w-3 h-3 rounded-full bg-white opacity-50 hover:opacity-100"></button>
                </div>
                <button class="slider-prev absolute left-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-100 rounded-full p-2">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="slider-next absolute right-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-100 rounded-full p-2">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stats-grid">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold"><?php echo count($subjects); ?></p>
                            <p class="text-blue-100">Total Subjects</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-sm text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold"><?php echo $totalStudents; ?></p>
                            <p class="text-green-100">Total Students</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-sm text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold"><?php echo $pendingGrading; ?></p>
                            <p class="text-orange-100">Pending Grading</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tasks text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Courses Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-blue-800">My Courses</h2>
                    <span class="text-sm text-gray-600"><?php echo $teacher['strand']; ?> Strand</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 courses-grid">
                    <?php if (!empty($subjects)): ?>
                        <?php 
                        $colors = ['blue', 'green', 'purple', 'orange', 'red', 'indigo'];
                        $colorIndex = 0;
                        ?>
                        <?php foreach ($subjects as $subject): ?>
                            <?php
                            $color = $colors[$colorIndex % count($colors)];
                            $studentCount = $studentCounts[$subject['id']] ?? 0;
                            $colorIndex++;
                            ?>
                            <div class="course-progress border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="h-32 bg-<?php echo $color; ?>-100 rounded-lg mb-3 flex items-center justify-center relative" style="background-color: <?php echo $subject['color'] ?? '#DBEAFE'; ?>">
                                    <i class="fas fa-<?php echo $subject['icon'] ?? 'book'; ?> text-4xl" style="color: <?php echo $subject['color'] ?? '#2563EB'; ?>"></i>
                                    <span class="absolute top-2 right-2 bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                                        <?php echo $studentCount; ?> students
                                    </span>
                                </div>
                                <h3 class="font-bold text-gray-800"><?php echo $subject['subject_name']; ?></h3>
                                <p class="text-sm text-gray-500"><?php echo $subject['subject_code']; ?></p>
                                <div class="mt-2 flex justify-between items-center">
                                    <span class="text-xs text-gray-500">
                                        Grade <?php echo $subject['grade_level']; ?>
                                    </span>
                                    <a href="teacher-course-detail.php?subject_id=<?php echo $subject['id']; ?>" class="text-<?php echo $color; ?>-600 hover:text-<?php echo $color; ?>-800 text-sm font-medium">
                                        Manage Course →
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">No courses assigned yet.</p>
                            <p class="text-sm text-gray-400">Contact administrator to get assigned to subjects.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Assignments -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-blue-800">Recent Assignments</h2>
                    <a href="teacher-assignments.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View All →
                    </a>
                </div>
                <div class="space-y-4">
                    <?php if (!empty($recentAssignments)): ?>
                        <?php foreach ($recentAssignments as $assignment): ?>
                            <a href="view-detail.php?type=assignment&id=<?php echo $assignment['id']; ?>" class="block no-underline">
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow clickable-task">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-gray-800 text-lg"><?php echo $assignment['title']; ?></h3>
                                            <p class="text-gray-600 mt-1"><?php echo $assignment['subject_name'] ?? 'No Subject'; ?></p>
                                        </div>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-sm rounded-full">
                                            <?php echo $assignment['type']; ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm text-gray-500">
                                        <div>
                                            <span class="font-medium">Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-blue-600 font-medium">
                                                <?php echo $assignment['graded_count']; ?>/<?php echo $assignment['submission_count']; ?> graded
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-tasks text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No assignments created yet.</p>
                            <?php if (!empty($subjects)): ?>
                                <a href="teacher-course-detail.php?subject_id=<?php echo $subjects[0]['id']; ?>" class="text-blue-600 hover:text-blue-700 text-sm mt-1 inline-block">
                                    Create your first assignment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="w-full md:w-80 mt-6 md:mt-0 md:ml-6 space-y-6 right-sidebar">
            <!-- Calendar -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Calendar - <?php echo date('F Y'); ?></h2>
                <div class="grid grid-cols-7 gap-1 text-center text-sm calendar-grid">
                    <div class="font-medium text-gray-500">S</div>
                    <div class="font-medium text-gray-500">M</div>
                    <div class="font-medium text-gray-500">T</div>
                    <div class="font-medium text-gray-500">W</div>
                    <div class="font-medium text-gray-500">T</div>
                    <div class="font-medium text-gray-500">F</div>
                    <div class="font-medium text-gray-500">S</div>
                    
                    <!-- Calendar days -->
                    <?php
                    for ($i = 1; $i < $firstDay; $i++) {
                        echo '<div class="text-gray-400 p-2">' . (cal_days_in_month(CAL_GREGORIAN, $currentMonth - 1, $currentYear) - $firstDay + $i + 1) . '</div>';
                    }
                    
                    $today = date('j');
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $isToday = ($day == $today);
                        $hasAssignment = in_array($day, $assignmentDays);
                        
                        $class = "p-2 rounded-full cursor-pointer";
                        if ($isToday) {
                            $class .= " bg-blue-500 text-white font-bold";
                        } elseif ($hasAssignment) {
                            $class .= " bg-red-100 text-red-600";
                        } else {
                            $class .= " hover:bg-gray-100";
                        }
                        
                        echo "<div class=\"$class\" title=\"";
                        if ($hasAssignment) echo "Assignment deadlines";
                        echo "\">$day</div>";
                    }
                    
                    $totalCells = 42;
                    $remainingCells = $totalCells - ($daysInMonth + $firstDay - 1);
                    for ($i = 1; $i <= $remainingCells; $i++) {
                        echo '<div class="text-gray-400 p-2">' . $i . '</div>';
                    }
                    ?>
                </div>
                
                <!-- Calendar Legend -->
                <div class="mt-4 text-xs text-gray-600 space-y-1">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        <span>Today</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-red-100 rounded-full border border-red-300"></div>
                        <span>Assignment Deadlines</span>
                    </div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-blue-800">Pending Tasks</h2>
                    <span class="text-sm text-gray-600">Due Soon: <?php echo count($upcomingDeadlines); ?></span>
                </div>
                <div class="space-y-3">
                    <?php if (!empty($upcomingDeadlines)): ?>
                        <?php foreach ($upcomingDeadlines as $assignment): ?>
                            <?php
                            $dueDate = new DateTime($assignment['due_date']);
                            $today = new DateTime();
                            $daysLeft = $today->diff($dueDate)->days;
                            $isUrgent = $daysLeft <= 1;
                            ?>
                            <a href="view-detail.php?type=assignment&id=<?php echo $assignment['id']; ?>" class="block no-underline">
                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg clickable-task <?php echo $isUrgent ? 'bg-red-50 border-red-200' : 'hover:bg-gray-50'; ?>">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-sm truncate"><?php echo $assignment['title']; ?></p>
                                        <p class="text-xs text-gray-500 truncate"><?php echo $assignment['subject_name'] ?? 'No Subject'; ?></p>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-3">
                                        <p class="text-sm font-medium <?php echo $isUrgent ? 'text-red-600' : 'text-gray-600'; ?>">
                                            <?php echo date('M j', strtotime($assignment['due_date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo $daysLeft == 0 ? 'Today' : ($daysLeft == 1 ? '1 day' : $daysLeft . ' days'); ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                            <p class="text-gray-500">All caught up!</p>
                            <p class="text-sm text-gray-400">No pending deadlines</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-4 quick-actions-grid">
                    <?php if (!empty($subjects)): ?>
                        <a href="teacher-course-detail.php?subject_id=<?php echo $subjects[0]['id']; ?>" class="bg-blue-50 hover:bg-blue-100 text-blue-600 p-4 rounded-lg text-center transition-colors">
                            <i class="fas fa-plus text-lg mb-2 block"></i>
                            <span class="text-sm font-medium">Create Assignment</span>
                        </a>
                    <?php endif; ?>
                    <a href="teacher-assignments.php" class="bg-green-50 hover:bg-green-100 text-green-600 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-tasks text-lg mb-2 block"></i>
                        <span class="text-sm font-medium">Grade Assignments</span>
                    </a>
                    <a href="teacher-materials.php" class="bg-purple-50 hover:bg-purple-100 text-purple-600 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-file-alt text-lg mb-2 block"></i>
                        <span class="text-sm font-medium">Upload Materials</span>
                    </a>
                    <a href="teacher-grades.php" class="bg-orange-50 hover:bg-orange-100 text-orange-600 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-chart-line text-lg mb-2 block"></i>
                        <span class="text-sm font-medium">View Grades</span>
                    </a>
                </div>
            </div>
        </aside>
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

            // Image Slider Functionality
            const slider = document.querySelector('.slider');
            if (slider) {
                const slides = document.querySelectorAll('.slider > div');
                const dots = document.querySelectorAll('.slider-btn');
                const prevBtn = document.querySelector('.slider-prev');
                const nextBtn = document.querySelector('.slider-next');
                let currentSlide = 0;
                
                function showSlide(index) {
                    slides.forEach(slide => {
                        slide.classList.add('hidden');
                        slide.classList.remove('fade-in');
                    });
                    
                    dots.forEach(dot => {
                        dot.classList.remove('bg-white', 'opacity-100');
                        dot.classList.add('opacity-50');
                    });
                    
                    slides[index].classList.remove('hidden');
                    setTimeout(() => {
                        slides[index].classList.add('fade-in');
                    }, 10);
                    
                    dots[index].classList.add('bg-white', 'opacity-100');
                    dots[index].classList.remove('opacity-50');
                    
                    currentSlide = index;
                }
                
                showSlide(0);
                
                nextBtn.addEventListener('click', function() {
                    let nextIndex = (currentSlide + 1) % slides.length;
                    showSlide(nextIndex);
                });
                
                prevBtn.addEventListener('click', function() {
                    let prevIndex = (currentSlide - 1 + slides.length) % slides.length;
                    showSlide(prevIndex);
                });
                
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', function() {
                        showSlide(index);
                    });
                });
                
                setInterval(function() {
                    let nextIndex = (currentSlide + 1) % slides.length;
                    showSlide(nextIndex);
                }, 5000);
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
        });
    </script>
</body>
</html>