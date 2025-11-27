<?php
require_once 'config.php';
checkStudentAuth();
$currentUser = getCurrentUser();

$user = $_SESSION['user'];

// Get student data from database
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all notifications for the student
$stmt = $db->prepare("
    SELECT n.*, a.title, a.due_date, s.subject_name, s.subject_code,
           CASE 
               WHEN n.type = 'new_assignment' THEN CONCAT('New Assignment: ', a.title)
               WHEN n.type = 'due_soon' THEN CONCAT('Assignment Due Soon: ', a.title)
               WHEN n.type = 'graded' THEN CONCAT('Assignment Graded: ', a.title)
               ELSE 'Notification'
           END as notification_title,
           CASE 
               WHEN n.type = 'new_assignment' THEN CONCAT('Due: ', DATE_FORMAT(a.due_date, '%M %e, %Y'))
               WHEN n.type = 'due_soon' THEN 'Due tomorrow'
               WHEN n.type = 'graded' THEN 'Check your grades'
               ELSE 'Notification'
           END as notification_message
    FROM student_notifications n
    LEFT JOIN assignments a ON n.assignment_id = a.id
    LEFT JOIN subjects s ON a.subject_id = s.id
    WHERE n.student_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all as read when viewing notifications page
$stmt = $db->prepare("
    UPDATE student_notifications 
    SET is_read = TRUE 
    WHERE student_id = ? AND is_read = FALSE
");
$stmt->execute([$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - Notifications</title>
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
    .notification-item {
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }
    .notification-item:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .notification-new {
        border-left-color: #3b82f6;
        background-color: #f0f9ff;
    }
    @media (max-width: 768px) {
        .mobile-search { display: none; }
        .school-name { display: none; }
        .mobile-user { display: none; }
        .notification-header { flex-direction: column; gap: 1rem; }
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
                        <a href="student-notifications.php" class="relative p-2 text-gray-600 hover:text-blue-600 transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                        </a>
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
                        <li>
                            <a href="student-schedule.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600">
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
                <div class="flex justify-between items-center notification-header">
                    <div>
                        <h1 class="text-2xl font-bold text-blue-800">Notifications</h1>
                        <p class="text-gray-600">Stay updated with your academic activities</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">
                            <?php echo count($notifications); ?> notification<?php echo count($notifications) !== 1 ? 's' : ''; ?>
                        </span>
                        <a href="student-dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if (!empty($notifications)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php 
                        $currentDate = null;
                        foreach ($notifications as $notification): 
                            $notificationDate = date('M j', strtotime($notification['created_at']));
                            $isNew = !$notification['is_read'];
                        ?>
                            <?php if ($currentDate !== $notificationDate): ?>
                                <?php $currentDate = $notificationDate; ?>
                                <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                                    <h3 class="text-sm font-semibold text-gray-700"><?php echo $notificationDate; ?></h3>
                                </div>
                            <?php endif; ?>
                            
                            <div class="notification-item p-6 hover:bg-gray-50 cursor-pointer <?php echo $isNew ? 'notification-new' : ''; ?>"
                                 onclick="window.location.href='view-detail.php?id=<?php echo $notification['assignment_id']; ?>&type=assignment'">
                                <div class="flex items-start space-x-4">
                                    <!-- Notification Icon -->
                                    <div class="flex-shrink-0">
                                        <?php if ($notification['type'] === 'graded'): ?>
                                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                            </div>
                                        <?php elseif ($notification['type'] === 'due_soon'): ?>
                                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-clock text-orange-600 text-xl"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-bell text-blue-600 text-xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Notification Content -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="text-lg font-semibold text-gray-900 mb-1">
                                                    <?php echo $notification['notification_title']; ?>
                                                </h4>
                                                
                                                <!-- Subject and Course Info -->
                                                <div class="flex items-center space-x-4 mb-2">
                                                    <?php if ($notification['subject_name']): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <i class="fas fa-book mr-1"></i>
                                                            <?php echo $notification['subject_name']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($notification['subject_code']): ?>
                                                        <span class="text-sm text-gray-500">
                                                            <?php echo $notification['subject_code']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Notification Message -->
                                                <p class="text-gray-700 mb-2">
                                                    <?php echo $notification['notification_message']; ?>
                                                </p>
                                                
                                                <!-- Additional Info -->
                                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                                    <span>
                                                        <i class="far fa-clock mr-1"></i>
                                                        <?php echo date('g:i A', strtotime($notification['created_at'])); ?>
                                                    </span>
                                                    <?php if ($notification['due_date']): ?>
                                                        <span>
                                                            <i class="far fa-calendar-alt mr-1"></i>
                                                            Due: <?php echo date('M j, Y', strtotime($notification['due_date'])); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Status Indicator -->
                                            <?php if ($isNew): ?>
                                                <span class="flex-shrink-0 ml-4">
                                                    <span class="w-3 h-3 bg-blue-500 rounded-full block"></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Notes Section (if any) -->
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <div class="flex items-center text-sm text-gray-500">
                                        <i class="fas fa-sticky-note mr-2"></i>
                                        <span>Notes</span>
                                        <span class="mx-2">•</span>
                                        <span class="text-gray-400">Click to view details</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-16">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-bell-slash text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No notifications yet</h3>
                        <p class="text-gray-500 mb-6">You're all caught up! New notifications will appear here.</p>
                        <a href="student-dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-home mr-2"></i>
                            Go to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Load More Button (if needed) -->
            <?php if (count($notifications) > 10): ?>
                <div class="mt-6 text-center">
                    <button class="bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg border border-gray-300 transition-colors">
                        <i class="fas fa-arrow-down mr-2"></i>
                        Load More Notifications
                    </button>
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

            // Notification item click handling
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function() {
                    const assignmentId = this.getAttribute('onclick').match(/id=(\d+)/)[1];
                    // The navigation is already handled by the onclick attribute
                });
            });
        });
    </script>
</body>
</html>