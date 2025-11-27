<?php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];

// Get student data from database
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student's subjects with schedule information
$stmt = $db->prepare("
    SELECT ss.*, s.subject_name, s.subject_code, s.color, s.icon, t.name as teacher_name
    FROM student_subjects ss
    LEFT JOIN subjects s ON ss.subject_id = s.id
    LEFT JOIN teachers t ON ss.teacher_id = t.id
    WHERE ss.student_id = ?
    ORDER BY ss.quarter, ss.subject_name
");
$stmt->execute([$user['id']]);
$studentSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subjects by quarter
$subjectsByQuarter = [];
foreach ($studentSubjects as $subject) {
    $quarter = $subject['quarter'];
    if (!isset($subjectsByQuarter[$quarter])) {
        $subjectsByQuarter[$quarter] = [];
    }
    $subjectsByQuarter[$quarter][] = $subject;
}

// Get schedule for the current week
$currentWeekSchedule = [];
foreach ($studentSubjects as $subject) {
    if (!empty($subject['schedule'])) {
        $scheduleData = [
            'subject_name' => $subject['subject_name'],
            'subject_code' => $subject['subject_code'],
            'teacher_name' => $subject['teacher_name'],
            'schedule' => $subject['schedule'],
            'color' => $subject['color'] ?? '#DBEAFE',
            'icon' => $subject['icon'] ?? 'book'
        ];
        $currentWeekSchedule[] = $scheduleData;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - Student Schedule</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .schedule-day {
            transition: all 0.3s ease;
        }
        .schedule-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .subject-badge {
            background-color: var(--subject-color);
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

    <!-- Header (Copied from student-dashboard.php) -->
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
                                <a href="student-notifications.php" class="text-blue-600 hover:text-blue-800 text-sm">View All Notifications</a>
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
        <!-- Sidebar (Updated with mobile functionality) -->
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
                        <li>
                            <a href="student-schedule.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
                                <i class="fas fa-calendar-alt mr-3"></i>
                                <span>Schedule</span>
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
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-blue-800">My Schedule</h1>
                        <p class="text-gray-600">
                            <?php echo $student['strand']; ?> - Grade <?php echo $student['grade_level']; ?>
                            <?php echo $student['section'] ? 'Section ' . $student['section'] : ''; ?>
                        </p>
                    </div>
                    <div class="text-left md:text-right">
                        <p class="text-lg font-semibold"><?php echo date('F Y'); ?></p>
                        <p class="text-sm text-gray-500">Current Week</p>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Weekly Schedule</h2>
                
                <?php if (!empty($currentWeekSchedule)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                        foreach ($days as $day): 
                            $daySubjects = array_filter($currentWeekSchedule, function($subject) use ($day) {
                                return stripos($subject['schedule'], $day) !== false;
                            });
                        ?>
                            <div class="schedule-day border border-gray-200 rounded-lg p-4">
                                <h3 class="font-bold text-gray-800 mb-3 text-center"><?php echo $day; ?></h3>
                                
                                <?php if (!empty($daySubjects)): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($daySubjects as $subject): ?>
                                            <div class="p-3 rounded-lg border-l-4" style="border-left-color: <?php echo $subject['color']; ?>; background-color: <?php echo $subject['color']; ?>20;">
                                                <div class="flex items-start mb-2">
                                                    <i class="fas fa-<?php echo $subject['icon']; ?> mt-1 mr-2" style="color: <?php echo $subject['color']; ?>"></i>
                                                    <div class="flex-1">
                                                        <h4 class="font-semibold text-sm"><?php echo $subject['subject_name']; ?></h4>
                                                        <p class="text-xs text-gray-600"><?php echo $subject['subject_code']; ?></p>
                                                    </div>
                                                </div>
                                                <?php if ($subject['teacher_name']): ?>
                                                    <p class="text-xs text-gray-500">Teacher: <?php echo $subject['teacher_name']; ?></p>
                                                <?php endif; ?>
                                                <p class="text-xs text-gray-500 mt-1"><?php echo $subject['schedule']; ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-coffee text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-gray-500 text-sm">No classes</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500">No schedule available yet.</p>
                        <p class="text-sm text-gray-400">Your schedule will appear here once assigned.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subjects by Quarter -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Subjects by Quarter</h2>
                
                <?php if (!empty($subjectsByQuarter)): ?>
                    <div class="space-y-6">
                        <?php foreach ($subjectsByQuarter as $quarter => $subjects): ?>
                            <div>
                                <h3 class="text-lg font-semibold mb-3 text-gray-800">Quarter <?php echo $quarter; ?></h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($subjects as $subject): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-start mb-3">
                                                <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3" style="background-color: <?php echo $subject['color']; ?>20;">
                                                    <i class="fas fa-<?php echo $subject['icon']; ?> text-lg" style="color: <?php echo $subject['color']; ?>"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="font-bold text-gray-800"><?php echo $subject['subject_name']; ?></h4>
                                                    <p class="text-sm text-gray-600"><?php echo $subject['subject_code']; ?></p>
                                                    <?php if ($subject['teacher_name']): ?>
                                                        <p class="text-xs text-gray-500 mt-1">Teacher: <?php echo $subject['teacher_name']; ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($subject['schedule']): ?>
                                                <div class="mt-2 pt-2 border-t border-gray-100">
                                                    <p class="text-sm text-gray-700">
                                                        <i class="fas fa-clock text-gray-400 mr-1"></i>
                                                        <?php echo $subject['schedule']; ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3 flex justify-between items-center text-xs text-gray-500">
                                                <span><?php echo $subject['credits']; ?> credits</span>
                                                <span>Q<?php echo $quarter; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500">No subjects enrolled yet.</p>
                        <p class="text-sm text-gray-400">Your subjects will appear here once enrolled.</p>
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
                    fetch('get-student-notifications.php')
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