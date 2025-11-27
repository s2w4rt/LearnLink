<?php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];

// Get student data
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// REMOVED: Pending assignments count query since we don't want the red notification

// Get selected semester from GET parameter or default to 'all'
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$viewType = isset($_GET['view']) ? $_GET['view'] : 'current'; // 'current' or 'archive'

// For current grades view (existing functionality)
if ($viewType === 'current') {
    // Get subjects for student's strand
    $stmt = $db->prepare("SELECT * FROM subjects WHERE strand = ? AND (grade_level = ? OR grade_level = 'ALL') ORDER BY subject_name");
    $stmt->execute([$student['strand'], $student['grade_level']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build query based on selected semester - FIXED VERSION
    $query = "
        SELECT a.quarter, a.subject_id, sa.score, a.max_score, s.subject_name, s.color, s.icon
        FROM assignments a
        LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
        LEFT JOIN subjects s ON a.subject_id = s.id
        WHERE a.strand = ? 
        AND sa.score IS NOT NULL
        AND sa.student_id = ?
    ";

    if ($selectedSemester !== 'all') {
        // 2 quarters per semester
        if ($selectedSemester === '1') {
            $query .= " AND a.quarter IN (1, 2)"; // 1st Semester: Q1, Q2
        } else {
            $query .= " AND a.quarter IN (3, 4)"; // 2nd Semester: Q3, Q4
        }
    }
    
    $query .= " ORDER BY a.subject_id, a.quarter"; // Added ordering

    $params = [$user['id'], $student['strand'], $user['id']];
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DEBUG: Check what data we're getting
    // error_log("Grades count: " . count($grades));
    // foreach ($grades as $g) {
    //     error_log("Subject: {$g['subject_name']}, Q{$g['quarter']}, Score: {$g['score']}/{$g['max_score']}");
    // }

    // Organize grades - COMPLETELY REVISED VERSION
    $subjectGrades = [];
    
    // First, initialize all subjects from the subjects table
    foreach ($subjects as $subject) {
        $subjectId = $subject['id'];
        $subjectGrades[$subjectId] = [
            'name' => $subject['subject_name'],
            'color' => $subject['color'],
            'icon' => $subject['icon'],
            'quarters' => [
                '1' => [], '2' => [], '3' => [], '4' => []
            ],
            'semesters' => [
                '1' => [], '2' => []
            ],
            'quarter_averages' => ['1' => null, '2' => null, '3' => null, '4' => null],
            'semester_averages' => ['1' => null, '2' => null],
            'overall' => null
        ];
    }

    // Then, populate with actual grades
    foreach ($grades as $grade) {
        $subjectId = $grade['subject_id'];
        $quarter = $grade['quarter'];
        
        // Only process if this subject exists in our initialized array
        if (isset($subjectGrades[$subjectId])) {
            $percentage = round(($grade['score'] / $grade['max_score']) * 100, 1);
            
            // Add to quarters array
            $subjectGrades[$subjectId]['quarters'][$quarter][] = $percentage;
            
            // Add to appropriate semester
            if ($quarter == 1 || $quarter == 2) {
                $subjectGrades[$subjectId]['semesters']['1'][] = $percentage;
            } else {
                $subjectGrades[$subjectId]['semesters']['2'][] = $percentage;
            }
        }
    }

    // Calculate averages - REVISED CALCULATION
    foreach ($subjectGrades as $subjectId => &$subject) {
        // Calculate quarter averages
        foreach (['1', '2', '3', '4'] as $quarter) {
            if (!empty($subject['quarters'][$quarter])) {
                $avg = array_sum($subject['quarters'][$quarter]) / count($subject['quarters'][$quarter]);
                $subject['quarter_averages'][$quarter] = round($avg, 1);
            }
        }
        
        // Calculate semester averages
        foreach (['1', '2'] as $semester) {
            if (!empty($subject['semesters'][$semester])) {
                $avg = array_sum($subject['semesters'][$semester]) / count($subject['semesters'][$semester]);
                $subject['semester_averages'][$semester] = round($avg, 1);
            }
        }
        
        // Calculate overall average based on selection
        if ($selectedSemester === 'all') {
            // Overall average from all available semesters
            $semesterGrades = [];
            foreach (['1', '2'] as $semester) {
                if ($subject['semester_averages'][$semester] !== null) {
                    $semesterGrades[] = $subject['semester_averages'][$semester];
                }
            }
            
            if (!empty($semesterGrades)) {
                $subject['overall'] = round(array_sum($semesterGrades) / count($semesterGrades), 1);
            }
        } else {
            // Average for selected semester only
            $quarterGrades = [];
            if ($selectedSemester === '1') {
                // Include Q1 and Q2 averages for 1st semester
                if ($subject['quarter_averages']['1'] !== null) $quarterGrades[] = $subject['quarter_averages']['1'];
                if ($subject['quarter_averages']['2'] !== null) $quarterGrades[] = $subject['quarter_averages']['2'];
            } else {
                // Include Q3 and Q4 averages for 2nd semester
                if ($subject['quarter_averages']['3'] !== null) $quarterGrades[] = $subject['quarter_averages']['3'];
                if ($subject['quarter_averages']['4'] !== null) $quarterGrades[] = $subject['quarter_averages']['4'];
            }
            
            if (!empty($quarterGrades)) {
                $subject['overall'] = round(array_sum($quarterGrades) / count($quarterGrades), 1);
            }
        }
    }
    unset($subject); // Break reference

    // Calculate GWA (General Weighted Average) for selected semester - FIXED
    $gwa = null;
    $gwaCount = 0;
    $gwaTotal = 0;

    foreach ($subjectGrades as $subject) {
        if ($subject['overall'] !== null) {
            if ($selectedSemester === 'all') {
                // For all semesters, use the overall average
                $gwaTotal += $subject['overall'];
                $gwaCount++;
            } else {
                // For specific semester, use the semester average
                $semesterAvg = $subject['semester_averages'][$selectedSemester];
                if ($semesterAvg !== null) {
                    $gwaTotal += $semesterAvg;
                    $gwaCount++;
                }
            }
        }
    }

    if ($gwaCount > 0) {
        $gwa = round($gwaTotal / $gwaCount, 2);
    }
} 
// For archive grades view (rest of your code remains the same...)
// For archive grades view
else {
    // Get archived grades
    $stmt = $db->prepare("
        SELECT ag.*, s.subject_name, s.color, s.icon 
        FROM student_archive_grades ag 
        LEFT JOIN subjects s ON ag.subject_id = s.id 
        WHERE ag.student_id = ? 
        ORDER BY ag.school_year DESC, ag.semester, s.subject_name
    ");
    $stmt->execute([$user['id']]);
    $archivedGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by school year and semester
    $archiveData = [];
    foreach ($archivedGrades as $grade) {
        $schoolYear = $grade['school_year'];
        $semester = $grade['semester'];
        $gradeLevel = $grade['grade_level'];
        
        if (!isset($archiveData[$schoolYear])) {
            $archiveData[$schoolYear] = [];
        }
        if (!isset($archiveData[$schoolYear][$semester])) {
            $archiveData[$schoolYear][$semester] = [
                'grade_level' => $gradeLevel,
                'subjects' => []
            ];
        }
        
        $archiveData[$schoolYear][$semester]['subjects'][] = $grade;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - ALLSHS</title>
    <script src="/JS/notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            .grades-table { overflow-x: auto; }
            .filters { flex-direction: column; gap: 1rem; }
            .mobile-search { display: none; }
            .mobile-user { display: none; }
            .page-header { flex-direction: column; gap: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
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
                            <a href="student-dashboard.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
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
                        <li class="nav-item">
                            <!-- REMOVED: Red notification badge from assignments link -->
                            <a href="student-assignments.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-300">
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
                            <a href="student-grades.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 page-header">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-blue-800">My Grades</h1>
                        <p class="text-gray-600">Academic performance overview</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-sm text-gray-500"></div>
                            <div class="font-semibold text-blue-600"><?php echo $student['strand']; ?></div>
                        </div>
                        
                        <!-- View Type Tabs -->
                        <div class="flex bg-gray-100 rounded-lg p-1">
                            <a href="?view=current" class="px-4 py-2 rounded-md text-sm font-medium <?php echo $viewType === 'current' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>">
                                Current Grades
                            </a>
                            <a href="?view=archive" class="px-4 py-2 rounded-md text-sm font-medium <?php echo $viewType === 'archive' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>">
                                Grade Archive
                            </a>
                        </div>
                        
                        <?php if ($viewType === 'current'): ?>
                        <!-- Semester Dropdown -->
                        <form method="GET" class="flex items-center space-x-2">
                            <input type="hidden" name="view" value="current">
                            <label class="text-sm text-gray-600">View:</label>
                            <select name="semester" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="all" <?php echo $selectedSemester === 'all' ? 'selected' : ''; ?>>All Semesters</option>
                                <option value="1" <?php echo $selectedSemester === '1' ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2" <?php echo $selectedSemester === '2' ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($viewType === 'current'): ?>
                <!-- Current Grades View -->
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 stats-grid">
                    <?php if ($selectedSemester === 'all'): ?>
                        <!-- All Semesters View -->
                        <div class="bg-white p-4 rounded-lg shadow-sm border text-center">
                            <div class="text-2xl font-bold text-blue-600">
                                <?php
                                $total = 0;
                                $count = 0;
                                foreach ($subjectGrades as $subject) {
                                    if ($subject['overall'] !== null) {
                                        $total += $subject['overall'];
                                        $count++;
                                    }
                                }
                                echo $count > 0 ? round($total / $count, 1) . '%' : 'N/A';
                                ?>
                            </div>
                            <div class="text-sm text-gray-600">Overall Average</div>
                        </div>
                        
                        <?php foreach (['1', '2'] as $semester): ?>
                            <div class="bg-white p-4 rounded-lg shadow-sm border text-center">
                                <div class="text-2xl font-bold text-blue-600">
                                    <?php
                                    $total = 0;
                                    $count = 0;
                                    foreach ($subjectGrades as $subject) {
                                        if ($subject['semester_averages'][$semester] !== null) {
                                            $total += $subject['semester_averages'][$semester];
                                            $count++;
                                        }
                                    }
                                    echo $count > 0 ? round($total / $count, 1) . '%' : 'N/A';
                                    ?>
                                </div>
                                <div class="text-sm text-gray-600">Semester <?php echo $semester; ?> Average</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Single Semester View -->
                        <div class="bg-blue-50 p-4 rounded-lg shadow-sm border text-center border-blue-200">
                            <div class="text-2xl font-bold text-blue-600">
                                <?php echo $gwa !== null ? $gwa . '%' : 'N/A'; ?>
                            </div>
                            <div class="text-sm text-blue-700 font-medium">Semester <?php echo $selectedSemester; ?> GWA</div>
                        </div>
                        
                        <?php 
                        // Calculate quarter averages for the selected semester
                        $quarters = $selectedSemester === '1' ? [1, 2] : [3, 4];
                        $quarterStats = [];
                        
                        foreach ($quarters as $quarter) {
                            $quarterStats[$quarter] = ['total' => 0, 'count' => 0];
                        }
                        
                        foreach ($subjectGrades as $subject) {
                            foreach ($quarters as $quarter) {
                                if ($subject['quarter_averages'][$quarter] !== null) {
                                    $quarterStats[$quarter]['total'] += $subject['quarter_averages'][$quarter];
                                    $quarterStats[$quarter]['count']++;
                                }
                            }
                        }
                        ?>
                        
                        <?php foreach ($quarters as $quarter): ?>
                            <div class="bg-white p-4 rounded-lg shadow-sm border text-center">
                                <div class="text-2xl font-bold text-blue-600">
                                    <?php echo $quarterStats[$quarter]['count'] > 0 ? round($quarterStats[$quarter]['total'] / $quarterStats[$quarter]['count'], 1) . '%' : 'N/A'; ?>
                                </div>
                                <div class="text-sm text-gray-600">Quarter <?php echo $quarter; ?> Average</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Grades Table -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-2">
                        <h2 class="text-xl font-bold text-blue-800">
                            <?php if ($selectedSemester === 'all'): ?>
                                Subject Grades - All Semesters
                            <?php else: ?>
                                Subject Grades - Semester <?php echo $selectedSemester; ?>
                            <?php endif; ?>
                        </h2>
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-gray-500">Legend:</span>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">90-100%</span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">80-89%</span>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">70-79%</span>
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded">Below 70%</span>
                        </div>
                    </div>

                    <div class="grades-table overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Subject</th>
                                    <?php if ($selectedSemester === 'all'): ?>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">1st Semester</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">2nd Semester</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Overall</th>
                                    <?php else: ?>
                                        <?php 
                                        $quarters = $selectedSemester === '1' ? ['Q1', 'Q2'] : ['Q3', 'Q4'];
                                        foreach ($quarters as $quarter): ?>
                                            <th class="px-4 py-3 text-center text-sm font-medium text-gray-700"><?php echo $quarter; ?></th>
                                        <?php endforeach; ?>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Semester GWA</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php if (!empty($subjectGrades)): ?>
                                    <?php foreach ($subjectGrades as $subjectId => $subject): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3" style="background-color: <?php echo $subject['color']; ?>20; color: <?php echo $subject['color']; ?>">
                                                        <i class="fas fa-<?php echo $subject['icon']; ?> text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?php echo $subject['name']; ?></div>
                                                        <?php if ($selectedSemester !== 'all'): ?>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo $selectedSemester === '1' ? 'Q1 & Q2' : 'Q3 & Q4'; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <?php if ($selectedSemester === 'all'): ?>
                                                <!-- All Semesters View -->
                                                <?php foreach (['1', '2'] as $semester): ?>
                                                    <td class="px-4 py-3 text-center">
                                                        <?php if ($subject['semester_averages'][$semester] !== null): ?>
                                                            <?php 
                                                            $grade = $subject['semester_averages'][$semester];
                                                            $bgColor = 'bg-gray-100 text-gray-800';
                                                            if ($grade >= 90) $bgColor = 'bg-green-100 text-green-800';
                                                            elseif ($grade >= 80) $bgColor = 'bg-blue-100 text-blue-800';
                                                            elseif ($grade >= 70) $bgColor = 'bg-yellow-100 text-yellow-800';
                                                            else $bgColor = 'bg-red-100 text-red-800';
                                                            ?>
                                                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $bgColor; ?>">
                                                                <?php echo $grade; ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <td class="px-4 py-3 text-center">
                                                    <?php if ($subject['overall'] !== null): ?>
                                                        <?php 
                                                        $overall = $subject['overall'];
                                                        $bgColor = 'bg-gray-100 text-gray-800';
                                                        if ($overall >= 90) $bgColor = 'bg-green-100 text-green-800';
                                                        elseif ($overall >= 80) $bgColor = 'bg-blue-100 text-blue-800';
                                                        elseif ($overall >= 70) $bgColor = 'bg-yellow-100 text-yellow-800';
                                                        else $bgColor = 'bg-red-100 text-red-800';
                                                        ?>
                                                        <span class="px-3 py-1 rounded-full text-sm font-bold <?php echo $bgColor; ?>">
                                                            <?php echo $overall; ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php else: ?>
                                                <!-- Single Semester View with 2 Quarterly Breakdown -->
                                                <?php 
                                                $quarters = $selectedSemester === '1' ? [1, 2] : [3, 4];
                                                $semesterGrades = [];
                                                ?>
                                                
                                                <?php foreach ($quarters as $quarter): ?>
                                                    <td class="px-4 py-3 text-center">
                                                        <?php if ($subject['quarter_averages'][$quarter] !== null): ?>
                                                            <?php 
                                                            $grade = $subject['quarter_averages'][$quarter];
                                                            $semesterGrades[] = $grade;
                                                            $bgColor = 'bg-gray-100 text-gray-800';
                                                            if ($grade >= 90) $bgColor = 'bg-green-100 text-green-800';
                                                            elseif ($grade >= 80) $bgColor = 'bg-blue-100 text-blue-800';
                                                            elseif ($grade >= 70) $bgColor = 'bg-yellow-100 text-yellow-800';
                                                            else $bgColor = 'bg-red-100 text-red-800';
                                                            ?>
                                                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $bgColor; ?>">
                                                                <?php echo $grade; ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                            <?php $semesterGrades[] = null; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <td class="px-4 py-3 text-center">
                                                    <?php 
                                                    $validGrades = array_filter($semesterGrades, function($grade) {
                                                        return $grade !== null;
                                                    });
                                                    
                                                    if (!empty($validGrades)): 
                                                        $semesterAverage = array_sum($validGrades) / count($validGrades);
                                                    ?>
                                                        <?php 
                                                        $bgColor = 'bg-gray-100 text-gray-800';
                                                        if ($semesterAverage >= 90) $bgColor = 'bg-green-100 text-green-800';
                                                        elseif ($semesterAverage >= 80) $bgColor = 'bg-blue-100 text-blue-800';
                                                        elseif ($semesterAverage >= 70) $bgColor = 'bg-yellow-100 text-yellow-800';
                                                        else $bgColor = 'bg-red-100 text-red-800';
                                                        ?>
                                                        <span class="px-3 py-1 rounded-full text-sm font-bold <?php echo $bgColor; ?>">
                                                            <?php echo round($semesterAverage, 1); ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $selectedSemester === 'all' ? 4 : 3; ?>" class="px-4 py-8 text-center text-gray-500">
                                            <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                            <p>No grades available for <?php echo $selectedSemester === 'all' ? 'any semester' : 'semester ' . $selectedSemester; ?></p>
                                            <p class="text-sm mt-1">Your grades will appear here once assignments are graded</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- Archive Grades View -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-blue-800 mb-6">Grade Archive</h2>
                    
                    <?php if (!empty($archiveData)): ?>
                        <?php foreach ($archiveData as $schoolYear => $semesters): ?>
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                                    School Year: <?php echo $schoolYear; ?>
                                </h3>
                                
                                <?php foreach ($semesters as $semester => $data): ?>
                                    <div class="mb-6">
                                        <h4 class="font-medium text-gray-700 mb-3">
                                            Grade <?php echo $data['grade_level']; ?> - 
                                            <?php echo $semester == 1 ? '1st Semester' : '2nd Semester'; ?>
                                        </h4>
                                        
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left font-medium text-gray-700">Subject</th>
                                                        <th class="px-4 py-2 text-center font-medium text-gray-700">Q1</th>
                                                        <th class="px-4 py-2 text-center font-medium text-gray-700">Q2</th>
                                                        <th class="px-4 py-2 text-center font-medium text-gray-700">Q3</th>
                                                        <th class="px-4 py-2 text-center font-medium text-gray-700">Q4</th>
                                                        <th class="px-4 py-2 text-center font-medium text-gray-700">Final</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y">
                                                    <?php foreach ($data['subjects'] as $subject): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center">
                                                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center mr-2" style="background-color: <?php echo $subject['color']; ?>20; color: <?php echo $subject['color']; ?>">
                                                                        <i class="fas fa-<?php echo $subject['icon']; ?> text-xs"></i>
                                                                    </div>
                                                                    <span class="font-medium"><?php echo $subject['subject_name']; ?></span>
                                                                </div>
                                                            </td>
                                                            <?php 
                                                            $quarters = [
                                                                'quarter1_grade', 
                                                                'quarter2_grade', 
                                                                'quarter3_grade', 
                                                                'quarter4_grade'
                                                            ];
                                                            ?>
                                                            <?php foreach ($quarters as $quarter): ?>
                                                                <td class="px-4 py-2 text-center">
                                                                    <?php if ($subject[$quarter] !== null): ?>
                                                                        <?php 
                                                                        $grade = $subject[$quarter];
                                                                        $bgColor = 'bg-gray-100 text-gray-800';
                                                                        if ($grade >= 90) $bgColor = 'bg-green-100 text-green-800';
                                                                        elseif ($grade >= 80) $bgColor = 'bg-blue-100 text-blue-800';
                                                                        elseif ($grade >= 70) $bgColor = 'bg-yellow-100 text-yellow-800';
                                                                        else $bgColor = 'bg-red-100 text-red-800';
                                                                        ?>
                                                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $bgColor; ?>">
                                                                            <?php echo $grade; ?>%
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-gray-400">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                            <td class="px-4 py-2 text-center">
                                                                <?php if ($subject['final_grade'] !== null): ?>
                                                                    <?php 
                                                                    $finalGrade = $subject['final_grade'];
                                                                    $bgColor = 'bg-gray-100 text-gray-800';
                                                                    if ($finalGrade >= 90) $bgColor = 'bg-green-100 text-green-800';
                                                                    elseif ($finalGrade >= 80) $bgColor = 'bg-blue-100 text-blue-800';
                                                                    elseif ($finalGrade >= 70) $bgColor = 'bg-yellow-100 text-yellow-800';
                                                                    else $bgColor = 'bg-red-100 text-red-800';
                                                                    ?>
                                                                    <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $bgColor; ?>">
                                                                        <?php echo $finalGrade; ?>%
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-gray-400">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Show empty table structure when no archive data exists -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                                School Year: 2023-2024
                            </h3>
                            
                            <div class="mb-6">
                                <h4 class="font-medium text-gray-700 mb-3">
                                    Grade 11 - 1st Semester
                                </h4>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-medium text-gray-700">Subject</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q1</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q2</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q3</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q4</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Final</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                    <i class="fas fa-archive text-4xl mb-3 text-gray-300"></i>
                                                    <p>No archived grades found for this semester</p>
                                                    <p class="text-sm mt-1">Your completed semester grades will appear here</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mb-6">
                                <h4 class="font-medium text-gray-700 mb-3">
                                    Grade 11 - 2nd Semester
                                </h4>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-medium text-gray-700">Subject</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q1</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q2</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q3</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q4</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Final</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                    <i class="fas fa-archive text-4xl mb-3 text-gray-300"></i>
                                                    <p>No archived grades found for this semester</p>
                                                    <p class="text-sm mt-1">Your completed semester grades will appear here</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Example for previous school year -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                                School Year: 2022-2023
                            </h3>
                            
                            <div class="mb-6">
                                <h4 class="font-medium text-gray-700 mb-3">
                                    Grade 10 - 1st Semester
                                </h4>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-medium text-gray-700">Subject</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q1</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q2</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q3</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q4</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Final</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                    <i class="fas fa-archive text-4xl mb-3 text-gray-300"></i>
                                                    <p>No archived grades found for this semester</p>
                                                    <p class="text-sm mt-1">Your completed semester grades will appear here</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mb-6">
                                <h4 class="font-medium text-gray-700 mb-3">
                                    Grade 10 - 2nd Semester
                                </h4>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-medium text-gray-700">Subject</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q1</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q2</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q3</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Q4</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-700">Final</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                    <i class="fas fa-archive text-4xl mb-3 text-gray-300"></i>
                                                    <p>No archived grades found for this semester</p>
                                                    <p class="text-sm mt-1">Your completed semester grades will appear here</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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