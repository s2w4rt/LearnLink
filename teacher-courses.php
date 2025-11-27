<?php
require_once 'config.php';
checkTeacherAuth();

$user = $_SESSION['user'];
$db = getDB();

// Get teacher data
$stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Teacher not found.");
}

// Get teacher's subjects
$stmt = $db->prepare("
    SELECT DISTINCT s.*
    FROM assigned_subjects asg
    INNER JOIN subjects s ON asg.subject_id = s.id
    WHERE asg.teacher_id = ?
      AND s.is_active = 1
    ORDER BY s.subject_name
");
$stmt->execute([$teacher['id']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get student counts
$studentCounts = [];
$totalStudents = 0;
foreach ($subjects as $subject) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE strand = ? AND (grade_level = ? OR grade_level = 'ALL')");
    $stmt->execute([$subject['strand'], $subject['grade_level']]);
    $count = $stmt->fetchColumn();
    $studentCounts[$subject['id']] = $count;
    $totalStudents += $count;
}

// Get pending grading count
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT sa.id) 
    FROM student_assignments sa
    JOIN assignments a ON sa.assignment_id = a.id
    WHERE a.teacher_id = ? AND sa.status IN ('submitted', 'late') AND sa.score IS NULL
");
$stmt->execute([$teacher['id']]);
$pendingGrading = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - ALLSHS Teacher</title>
    <script src="/JS/teacher-notifications.js"></script>
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
        .course-card {
            transition: all 0.3s ease;
        }
        .course-card:hover {
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
        @media (max-width: 640px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; margin-right: 0; margin-bottom: 1rem; }
            .main-content { width: 100%; margin-left: 0; }
            .stats-cards { flex-direction: column; width: 100%; gap: 0.5rem; }
            .stats-cards > div { width: 100%; text-align: center; }
            .course-grid { grid-template-columns: 1fr; }
            .course-card { margin-bottom: 1rem; }
            .course-header { flex-direction: column; gap: 0.5rem; }
            .course-meta { flex-direction: column; gap: 0.5rem; text-align: left; }
            .course-stats { flex-direction: column; gap: 0.5rem; }
            .course-stats > div { justify-content: space-between; }
            .mobile-search { display: none; }
            .school-name { display: none; }
            .mobile-user { display: none; }
            .page-header { flex-direction: column; gap: 1rem; }
            .page-header .flex { flex-direction: column; gap: 1rem; }
            .page-header a { width: 100%; }
        }
        @media (min-width: 641px) and (max-width: 768px) {
            .course-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-cards { flex-direction: row; }
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
            .course-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .course-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 1280px) {
            .course-grid { grid-template-columns: repeat(3, 1fr); }
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
                            <a href="teacher-dashboard.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
                                <i class="fas fa-home mr-3"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="teacher-courses.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
        <main class="flex-1 main-content md:ml-0">
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 page-header">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold text-gray-800 truncate">My Courses</h1>
                        <p class="text-gray-600 mt-1">Subjects assigned to you for this school year.</p>
                    </div>
                    <div class="flex items-center space-x-4 stats-cards flex-shrink-0">
                        <div class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg text-sm whitespace-nowrap">
                            <i class="fas fa-users mr-2"></i>
                            Total Students: <span class="font-semibold"><?php echo $totalStudents; ?></span>
                        </div>
                        <div class="px-4 py-2 bg-green-100 text-green-800 rounded-lg text-sm whitespace-nowrap">
                            <i class="fas fa-book mr-2"></i>
                            Subjects: <span class="font-semibold"><?php echo count($subjects); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Stats Cards -->
            <div class="md:hidden grid grid-cols-2 gap-4 mb-6">
                <div class="bg-blue-100 text-blue-800 rounded-lg p-4 text-center">
                    <div class="text-lg font-bold"><?php echo $totalStudents; ?></div>
                    <div class="text-xs">Total Students</div>
                </div>
                <div class="bg-green-100 text-green-800 rounded-lg p-4 text-center">
                    <div class="text-lg font-bold"><?php echo count($subjects); ?></div>
                    <div class="text-xs">Subjects</div>
                </div>
            </div>

            <?php if (!empty($subjects)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 course-grid">
                    <?php foreach ($subjects as $subject): ?>
                        <?php
                        $color = $subject['color'] ?? '#2563EB';
                        $icon  = $subject['icon'] ?? 'book';

                        // Get assignment count for this subject
                        $stmt = $db->prepare("
                            SELECT COUNT(*) 
                            FROM assignments 
                            WHERE subject_id = ? AND teacher_id = ?
                        ");
                        $stmt->execute([$subject['id'], $teacher['id']]);
                        $assignmentCount = $stmt->fetchColumn();

                        // Get active assignments count
                        $stmt = $db->prepare("
                            SELECT COUNT(*) 
                            FROM assignments 
                            WHERE subject_id = ? AND teacher_id = ? AND status = 'active'
                        ");
                        $stmt->execute([$subject['id'], $teacher['id']]);
                        $activeAssignments = $stmt->fetchColumn();

                        $studentsInSubject = $studentCounts[$subject['id']] ?? 0;
                        ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden course-card clickable-item">
                            <div class="h-2" style="background-color: <?php echo htmlspecialchars($color); ?>"></div>
                            <div class="p-4">
                                <div class="flex items-start justify-between mb-3 course-header">
                                    <div class="flex items-center space-x-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0"
                                             style="background-color: <?php echo htmlspecialchars($color); ?>">
                                            <i class="fas fa-<?php echo htmlspecialchars($icon); ?>"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-semibold text-gray-800 truncate">
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-500 truncate">
                                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-gray-500 course-meta flex-shrink-0 ml-2">
                                        <div class="truncate"><?php echo htmlspecialchars($subject['strand']); ?></div>
                                        <div>Grade <?php echo htmlspecialchars($subject['grade_level']); ?></div>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars($subject['description'] ?? 'No description available'); ?>
                                </p>
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-3 course-stats">
                                    <div class="flex items-center space-x-2 flex-1">
                                        <span class="inline-flex items-center whitespace-nowrap">
                                            <i class="fas fa-users mr-1"></i>
                                            <?php echo $studentsInSubject; ?> Students
                                        </span>
                                        <span class="inline-flex items-center whitespace-nowrap">
                                            <i class="fas fa-tasks mr-1"></i>
                                            <?php echo $assignmentCount; ?> Assignments
                                        </span>
                                    </div>
                                    <div class="flex-shrink-0 ml-2">
                                        <span class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 rounded-full whitespace-nowrap">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <?php echo $activeAssignments; ?> Active
                                        </span>
                                    </div>
                                </div>
                                <a href="teacher-course-detail.php?subject_id=<?php echo $subject['id']; ?>"
                                   class="block w-full text-center py-2 text-sm font-medium text-white rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap"
                                   style="background-color: <?php echo htmlspecialchars($color); ?>">
                                    View Course
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-6 text-center text-gray-500">
                    <i class="fas fa-folder-open text-3xl mb-3"></i>
                    <p class="text-lg font-medium mb-2">No courses assigned to you yet.</p>
                    <p class="text-sm">Please contact the administrator if you believe this is an error.</p>
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

            // Add line-clamp utility for text truncation
            const style = document.createElement('style');
            style.textContent = `
                .line-clamp-2 {
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                .line-clamp-3 {
                    display: -webkit-box;
                    -webkit-line-clamp: 3;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>