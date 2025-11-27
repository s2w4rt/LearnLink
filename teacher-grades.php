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

// Get selected subject and section from filters
$selected_subject = $_GET['subject'] ?? ($subjects[0]['id'] ?? 0);
$selected_section = $_GET['section'] ?? 'all';
$selected_quarter = $_GET['quarter'] ?? '1';

// Get sections for the teacher's strand
$stmt = $db->prepare("
    SELECT DISTINCT section 
    FROM students 
    WHERE strand = ? AND grade_level = 11
    ORDER BY section
");
$stmt->execute([$teacher['strand']]);
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get students for the selected subject and section
$students = [];
if ($selected_subject > 0) {
    $query = "
        SELECT s.* 
        FROM students s
        WHERE s.strand = ? AND s.grade_level = 11
    ";
    
    $params = [$teacher['strand']];
    
    if ($selected_section !== 'all') {
        $query .= " AND s.section = ?";
        $params[] = $selected_section;
    }
    
    $query .= " ORDER BY s.full_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get assignments for the selected subject and quarter
$assignments = [];
if ($selected_subject > 0) {
    $stmt = $db->prepare("
        SELECT a.*, 
               COUNT(sa.id) as total_submissions,
               COUNT(CASE WHEN sa.score IS NOT NULL THEN 1 END) as graded_count
        FROM assignments a
        LEFT JOIN student_assignments sa ON a.id = sa.assignment_id
        WHERE a.teacher_id = ? AND a.subject_id = ? AND a.quarter = ?
        GROUP BY a.id
        ORDER BY a.due_date ASC
    ");
    $stmt->execute([$teacherId, $selected_subject, $selected_quarter]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate student grades
$studentGrades = [];
foreach ($students as $student) {
    $studentGrades[$student['id']] = [
        'student' => $student,
        'assignments' => [],
        'total_score' => 0,
        'max_possible' => 0,
        'average' => 0
    ];
    
    foreach ($assignments as $assignment) {
        $stmt = $db->prepare("
            SELECT score, status, submitted_at
            FROM student_assignments
            WHERE assignment_id = ? AND student_id = ?
        ");
        $stmt->execute([$assignment['id'], $student['id']]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $score = $submission['score'] ?? null;
        $status = $submission['status'] ?? 'assigned';
        
        $studentGrades[$student['id']]['assignments'][$assignment['id']] = [
            'score' => $score,
            'status' => $status,
            'max_score' => $assignment['max_score']
        ];
        
        if ($score !== null) {
            $studentGrades[$student['id']]['total_score'] += $score;
            $studentGrades[$student['id']]['max_possible'] += $assignment['max_score'];
        }
    }
    
    // Calculate average
    if ($studentGrades[$student['id']]['max_possible'] > 0) {
        $studentGrades[$student['id']]['average'] = 
            round(($studentGrades[$student['id']]['total_score'] / $studentGrades[$student['id']]['max_possible']) * 100, 1);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - Teacher Grades</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grade-cell { transition: all 0.2s ease; }
        .grade-cell:hover { background-color: #f8fafc; }
        .submitted { background-color: #dbeafe; }
        .graded { background-color: #dcfce7; }
        .late { background-color: #fef3c7; }
        .missing { background-color: #fef2f2; }
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
                            <a href="teacher-grades.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-blue-800">Grade Management</h1>
                        <p class="text-gray-600">View and manage student grades for your subjects</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="exportGrades()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export Grades
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Filters</h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 filters">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select name="subject" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select name="section" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                            <option value="all" <?php echo $selected_section === 'all' ? 'selected' : ''; ?>>All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section === $section ? 'selected' : ''; ?>>
                                    Section <?php echo htmlspecialchars($section); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quarter</label>
                        <select name="quarter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                            <option value="1" <?php echo $selected_quarter === '1' ? 'selected' : ''; ?>>Quarter 1</option>
                            <option value="2" <?php echo $selected_quarter === '2' ? 'selected' : ''; ?>>Quarter 2</option>
                            <option value="3" <?php echo $selected_quarter === '3' ? 'selected' : ''; ?>>Quarter 3</option>
                            <option value="4" <?php echo $selected_quarter === '4' ? 'selected' : ''; ?>>Quarter 4</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Grades Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-blue-600"><?php echo count($students); ?></p>
                            <p class="text-blue-600 text-sm">Total Students</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-green-600"><?php echo count($assignments); ?></p>
                            <p class="text-green-600 text-sm">Assignments</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tasks text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $pendingGrading; ?></p>
                            <p class="text-orange-600 text-sm">Pending Grading</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-purple-600">
                                <?php 
                                $totalSubmissions = 0;
                                $gradedSubmissions = 0;
                                foreach ($assignments as $assignment) {
                                    $totalSubmissions += $assignment['total_submissions'];
                                    $gradedSubmissions += $assignment['graded_count'];
                                }
                                echo $gradedSubmissions . '/' . $totalSubmissions;
                                ?>
                            </p>
                            <p class="text-purple-600 text-sm">Graded/Total</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-purple-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grades Table -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
                    <h2 class="text-xl font-bold text-blue-800">Student Grades</h2>
                    <span class="text-sm text-gray-600">
                        Showing <?php echo count($students); ?> students, <?php echo count($assignments); ?> assignments
                    </span>
                </div>

                <?php if (!empty($students) && !empty($assignments)): ?>
                    <div class="grades-table overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-700 sticky left-0 bg-gray-50 z-10">
                                        Student
                                    </th>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <th class="border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700 min-w-32">
                                            <div class="flex flex-col items-center">
                                                <span class="font-medium"><?php echo htmlspecialchars($assignment['title']); ?></span>
                                                <span class="text-xs text-gray-500">(<?php echo $assignment['max_score']; ?> pts)</span>
                                                <span class="text-xs text-gray-400">
                                                    <?php echo $assignment['graded_count']; ?>/<?php echo $assignment['total_submissions']; ?> graded
                                                </span>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700 bg-blue-50">
                                        Average
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentGrades as $studentId => $gradeData): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 px-4 py-3 text-sm sticky left-0 bg-white z-10">
                                            <div class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($gradeData['student']['full_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($gradeData['student']['student_id']); ?>
                                                • Section <?php echo htmlspecialchars($gradeData['student']['section']); ?>
                                            </div>
                                        </td>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <?php
                                            $submission = $gradeData['assignments'][$assignment['id']] ?? null;
                                            $score = $submission['score'] ?? null;
                                            $status = $submission['status'] ?? 'assigned';
                                            $maxScore = $assignment['max_score'];
                                            
                                            $cellClass = 'grade-cell border border-gray-200 px-4 py-3 text-center text-sm';
                                            
                                            if ($score !== null) {
                                                $cellClass .= ' graded';
                                                $percentage = round(($score / $maxScore) * 100);
                                                $scoreColor = $percentage >= 90 ? 'text-green-600' : 
                                                             ($percentage >= 80 ? 'text-blue-600' : 
                                                             ($percentage >= 70 ? 'text-yellow-600' : 'text-red-600'));
                                            } else {
                                                if ($status === 'submitted' || $status === 'late') {
                                                    $cellClass .= ' submitted';
                                                    $scoreColor = 'text-blue-600';
                                                } elseif ($status === 'assigned') {
                                                    $cellClass .= ' missing';
                                                    $scoreColor = 'text-gray-400';
                                                }
                                            }
                                            ?>
                                            <td class="<?php echo $cellClass; ?>">
                                                <?php if ($score !== null): ?>
                                                    <span class="font-medium <?php echo $scoreColor; ?>">
                                                        <?php echo $score; ?>/<?php echo $maxScore; ?>
                                                    </span>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <?php echo round(($score / $maxScore) * 100); ?>%
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($status === 'submitted'): ?>
                                                        <span class="text-blue-600 text-xs">
                                                            <i class="fas fa-clock mr-1"></i>Pending
                                                        </span>
                                                    <?php elseif ($status === 'late'): ?>
                                                        <span class="text-orange-600 text-xs">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>Late
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs">
                                                            <i class="fas fa-minus mr-1"></i>Not Submitted
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="border border-gray-200 px-4 py-3 text-center text-sm font-medium bg-blue-50">
                                            <?php if ($gradeData['max_possible'] > 0): ?>
                                                <span class="text-blue-700">
                                                    <?php echo $gradeData['average']; ?>%
                                                </span>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    <?php echo $gradeData['total_score']; ?>/<?php echo $gradeData['max_possible']; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (empty($students)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500 mb-2">No Students Found</h3>
                        <p class="text-gray-400">No students match the current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500 mb-2">No Assignments Found</h3>
                        <p class="text-gray-400">No assignments created for this subject and quarter.</p>
                        <?php if (!empty($subjects)): ?>
                            <a href="teacher-course-detail.php?subject_id=<?php echo $selected_subject; ?>" class="text-blue-600 hover:text-blue-700 text-sm mt-2 inline-block">
                                Create assignments for this subject
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

        function exportGrades() {
            // Simple CSV export functionality
            const table = document.querySelector('table');
            let csv = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Get rows
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach(td => {
                    row.push(td.textContent.trim());
                });
                csv.push(row.join(','));
            });
            
            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'grades_export.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>