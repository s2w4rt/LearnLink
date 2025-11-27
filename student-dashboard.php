<?php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];

// Get student data from database
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student's subjects from subjects table
$stmt = $db->prepare("SELECT * FROM subjects WHERE strand = ? AND grade_level IN (?, 'ALL') AND is_active = 1 ORDER BY subject_name");
$stmt->execute([$student['strand'], $student['grade_level']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending assignments count per subject - FIXED
$pendingCounts = [];
foreach ($subjects as $subject) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as pending_count 
        FROM assignments a 
        LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
        WHERE a.subject_id = ? AND a.status = 'active' AND a.is_deployed = 1
        AND (sa.status IS NULL OR sa.status = 'assigned')
        AND a.due_date >= CURDATE()
    ");
    $stmt->execute([$user['id'], $subject['id']]); // FIX: Use $subject['id']
    $pendingCounts[$subject['id']] = $stmt->fetchColumn();
}

// Get all pending assignments for the student - MODIFIED
$stmt = $db->prepare("
    SELECT a.*, s.subject_name, s.subject_code
    FROM assignments a 
    LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
    INNER JOIN subjects s ON a.subject_id = s.id
    WHERE a.strand = ? AND a.status = 'active' AND a.is_deployed = 1
    AND (sa.status IS NULL OR sa.status = 'assigned')
    AND a.due_date >= CURDATE()
");
$stmt->execute([$user['id'], $student['strand']]);
$pendingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's assignments
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT * FROM assignments 
    WHERE strand = ? AND due_date = ? AND status = 'active'
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$student['strand'], $today]);
$todaysEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get online classmates (students from same strand)
$stmt = $db->prepare("
    SELECT s.* FROM students s 
    WHERE s.strand = ? AND s.id != ? 
    ORDER BY RAND() LIMIT 3
");
$stmt->execute([$student['strand'], $user['id']]);
$onlineClassmates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current month calendar data
$currentMonth = date('n');
$currentYear = date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDay = date('N', strtotime("$currentYear-$currentMonth-01"));

// Get assignments for calendar highlighting
$stmt = $db->prepare("
    SELECT DAY(due_date) as day FROM assignments 
    WHERE strand = ? AND MONTH(due_date) = ? AND YEAR(due_date) = ? AND status = 'active'
");
$stmt->execute([$student['strand'], $currentMonth, $currentYear]);
$assignmentDays = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - Student Dashboard</title>
    <script src="/JS/notifications.js"></script>
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
    .assignment-dropdown {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }
    .assignment-dropdown.open {
        max-height: 500px;
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
                            <a href="student-dashboard.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
                                <i class="fas fa-home mr-3"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="student-courses.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-book mr-3"></i>
                                <span>My Courses</span>
                            </a>
                        </li>
                        
                        <li>
                            <a href="student-assignments.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-tasks mr-3"></i>
                                <span>Assignments</span>
                            </a>
                        </li>
                        <li>
                            <a href="student-materials.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-file-alt mr-3"></i>
                                <span>Learning Materials</span>
                            </a>
                        </li>
                        <li>
                            <a href="student-grades.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
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

                <!-- Student Info Card -->
                <div class="mt-6 p-4 border-t border-gray-200">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
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
        <main class="flex-1 main-content md:ml-0">
            <!-- Image Slider -->
            <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden relative">
                <div class="slider flex">
                    <div class="w-full flex-shrink-0 fade-in">
                        <img src="1920-x-1080-hd-1qq8r4pnn8cmcew4.jpg" alt="ALLSYS Campus" class="w-full h-64 object-cover">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 text-white">
                            <h2 class="text-xl font-bold">Welcome to ALLSHS, <?php echo explode(' ', $student['full_name'])[0]; ?>!</h2>
                            <p>Your gateway to online learning - <?php echo $student['strand']; ?> Strand</p>
                        </div>
                    </div>
                    <div class="w-full flex-shrink-0 fade-in hidden">
                        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1471&q=80" alt="Online Learning" class="w-full h-64 object-cover">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 text-white">
                            <h2 class="text-xl font-bold">Enhance Your Skills</h2>
                            <p>Access courses anytime, anywhere</p>
                        </div>
                    </div>
                    <div class="w-full flex-shrink-0 fade-in hidden">
                        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="Library" class="w-full h-64 object-cover">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 text-white">
                            <h2 class="text-xl font-bold">Academic Resources</h2>
                            <p>Access library and learning materials</p>
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

            <!-- Courses Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-blue-800">My Courses</h2>
                    <span class="text-sm text-gray-600"><?php echo $student['strand']; ?> Strand</span>
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
                            $pendingCount = $pendingCounts[$subject['id']] ?? 0;
                            $colorIndex++;
                            ?>
                            <div class="course-progress border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="h-32 bg-<?php echo $color; ?>-100 rounded-lg mb-3 flex items-center justify-center relative" style="background-color: <?php echo $subject['color'] ?? '#DBEAFE'; ?>">
                                    <i class="fas fa-<?php echo $subject['icon'] ?? 'book'; ?> text-4xl" style="color: <?php echo $subject['color'] ?? '#2563EB'; ?>"></i>
                                    <?php if ($pendingCount > 0): ?>
                                        <span class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                            <?php echo $pendingCount; ?> pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-bold text-gray-800"><?php echo $subject['subject_name']; ?></h3>
                                <p class="text-sm text-gray-500"><?php echo $subject['subject_code']; ?></p>
                                <div class="mt-2 flex justify-between items-center">
                                    <span class="text-xs text-gray-500">
                                        <?php echo $pendingCount > 0 ? $pendingCount . ' tasks due' : 'All caught up'; ?>
                                    </span>
                                    <a href="student-course-detail.php?subject_id=<?php echo $subject['id']; ?>" class="text-<?php echo $color; ?>-600 hover:text-<?php echo $color; ?>-800 text-sm font-medium">
                                        View Course →
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">No courses available for your strand yet.</p>
                            <p class="text-sm text-gray-400">Please check back later.</p>
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
                    // Empty cells for days before the first day of month
                    for ($i = 1; $i < $firstDay; $i++) {
                        echo '<div class="text-gray-400 p-2">' . (cal_days_in_month(CAL_GREGORIAN, $currentMonth - 1, $currentYear) - $firstDay + $i + 1) . '</div>';
                    }
                    
                    // Current month days
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
                        if ($hasAssignment) echo "Tasks due";
                        echo "\">$day</div>";
                    }
                    
                    // Empty cells for days after the last day of month
                    $totalCells = 42; // 6 rows x 7 columns
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
                        <span>Tasks Due</span>
                    </div>
                </div>
            </div>

            <!-- To-Do List -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-blue-800">To-Do List</h2>
                    <span class="text-sm text-gray-600">Pending: <?php echo count($pendingAssignments); ?></span>
                </div>
                <div class="space-y-3">
                    <?php if (!empty($pendingAssignments)): ?>
                        <?php 
                        // Group by subject for display
                        $groupedAssignments = [];
                        foreach ($pendingAssignments as $assignment) {
                            $subjectName = $assignment['subject_name'];
                            if (!isset($groupedAssignments[$subjectName])) {
                                $groupedAssignments[$subjectName] = [];
                            }
                            $groupedAssignments[$subjectName][] = $assignment;
                        }
                        ?>
                        <?php foreach ($groupedAssignments as $subjectName => $assignments): ?>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="flex items-center justify-between p-3 bg-gray-50 cursor-pointer subject-header">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo $subjectName; ?></p>
                                        <p class="text-sm text-gray-500"><?php echo count($assignments); ?> task<?php echo count($assignments) > 1 ? 's' : ''; ?> pending</p>
                                    </div>
                                    <i class="fas fa-chevron-down text-gray-400 transition-transform"></i>
                                </div>
                                <div class="assignment-dropdown">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <a href="view-detail.php?id=<?php echo $assignment['id']; ?>&type=assignment&subject_id=<?php echo $assignment['subject_id']; ?>" 
                                           class="block no-underline">
                                            <div class="p-3 border-t border-gray-200 hover:bg-gray-50">
                                                <p class="font-medium text-sm"><?php echo $assignment['title']; ?></p>
                                                <p class="text-xs text-gray-500">Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></p>
                                                <p class="text-xs text-gray-400"><?php echo $assignment['max_score']; ?> points</p>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                            <p class="text-gray-500">All caught up!</p>
                            <p class="text-sm text-gray-400">No pending tasks</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Today - <?php echo date('F j, Y'); ?></h2>
                <div class="space-y-3">
                    <?php if (!empty($todaysEvents)): ?>
                        <?php foreach ($todaysEvents as $event): ?>
                            <div class="border-l-4 border-blue-500 pl-3 py-2">
                                <p class="font-medium text-sm"><?php echo $event['title']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $event['type']; ?> • Due Today</p>
                                <p class="text-xs text-gray-400"><?php echo $event['max_score']; ?> points</p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check text-2xl text-gray-400 mb-2"></i>
                            <p class="text-gray-500">No tasks due today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Online Classmates -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Online Classmates</h2>
                <div class="space-y-3">
                    <?php if (!empty($onlineClassmates)): ?>
                        <?php foreach ($onlineClassmates as $classmate): ?>
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                    <?php echo substr($classmate['full_name'], 0, 2); ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-sm"><?php echo $classmate['full_name']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $classmate['strand']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users text-2xl text-gray-400 mb-2"></i>
                            <p class="text-gray-500">No classmates online</p>
                        </div>
                    <?php endif; ?>
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

        // To-Do List Dropdown Functionality
        document.querySelectorAll('.subject-header').forEach(header => {
            header.addEventListener('click', function() {
                const dropdown = this.nextElementSibling;
                const icon = this.querySelector('i');
                
                dropdown.classList.toggle('open');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            });
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
            }, 10000);
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
    });
    </script>
</body>
</html>