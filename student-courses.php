<?php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];

// Get student data from database
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get subjects for the student's strand and grade level
$stmt = $db->prepare("
    SELECT * FROM subjects 
    WHERE strand = ? AND (grade_level = ? OR grade_level = 'ALL') 
    AND is_active = 1
    ORDER BY subject_code
");
$stmt->execute([$student['strand'], $student['grade_level']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending assignments count per subject - FIXED
$pendingCounts = [];
if (!empty($subjects)) {
    $subjectIds = array_column($subjects, 'id');
    $placeholders = str_repeat('?,', count($subjectIds) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT s.id, COUNT(a.id) as pending_count 
        FROM subjects s
        LEFT JOIN assignments a ON s.id = a.subject_id 
        LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
        WHERE s.id IN ($placeholders) 
        AND a.strand = ? 
        AND a.status = 'active' 
        AND a.is_deployed = 1
        AND (sa.status IS NULL OR sa.status = 'assigned')
        AND a.due_date >= CURDATE()
        GROUP BY s.id
    ");
    
    $params = array_merge([$user['id']], $subjectIds, [$student['strand']]);
    $stmt->execute($params);
    $pendingResults = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($subjects as $subject) {
        $pendingCounts[$subject['id']] = $pendingResults[$subject['id']] ?? 0;
    }
}

// Get learning materials count per subject - FIXED (no changes needed here as it doesn't use $subject_id)
$materialCounts = [];
if (!empty($subjects)) {
    $subjectNames = array_column($subjects, 'subject_name');
    $placeholders = str_repeat('?,', count($subjectNames) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT subject, COUNT(*) as material_count 
        FROM learning_materials 
        WHERE strand = ? 
        AND subject IN ($placeholders)
        AND status = 'published'
        GROUP BY subject
    ");
    
    $params = array_merge([$student['strand']], $subjectNames);
    $stmt->execute($params);
    $materialResults = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Convert to subject_name => material_count mapping
    foreach ($subjects as $subject) {
        $materialCounts[$subject['id']] = $materialResults[$subject['subject_name']] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS - My Courses</title>
    <script src="/JS/notifications.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .course-card {
        transition: all 0.3s ease;
    }
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .progress-bar {
        transition: width 0.5s ease-in-out;
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
                                <a href="student-notifications.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Bar - Hidden on mobile -->
                    <div class="relative hidden md:block">
                        <input type="text" placeholder="Search courses..." class="pl-10 pr-4 py-2 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                            <a href="student-courses.php" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 page-header">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold text-gray-800 truncate">My Courses</h1>
                        <p class="text-gray-600 mt-1">Subjects for <?php echo $student['strand']; ?> Strand - Grade <?php echo $student['grade_level']; ?></p>
                    </div>
                    <div class="flex items-center space-x-4 stats-cards flex-shrink-0">
                        <div class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg text-sm whitespace-nowrap">
                            <i class="fas fa-book mr-2"></i>
                            Subjects: <span class="font-semibold"><?php echo count($subjects); ?></span>
                        </div>
                        <div class="px-4 py-2 bg-green-100 text-green-800 rounded-lg text-sm whitespace-nowrap">
                            <i class="fas fa-file-alt mr-2"></i>
                            Materials: <span class="font-semibold"><?php echo array_sum($materialCounts); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Stats Cards -->
            <div class="md:hidden grid grid-cols-2 gap-4 mb-6">
                <div class="bg-blue-100 text-blue-800 rounded-lg p-4 text-center">
                    <div class="text-lg font-bold"><?php echo count($subjects); ?></div>
                    <div class="text-xs">Subjects</div>
                </div>
                <div class="bg-green-100 text-green-800 rounded-lg p-4 text-center">
                    <div class="text-lg font-bold"><?php echo array_sum($materialCounts); ?></div>
                    <div class="text-xs">Materials</div>
                </div>
            </div>

            <?php if (!empty($subjects)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 course-grid">
                    <?php foreach ($subjects as $subject): ?>
                        <?php
                        $color = $subject['color'] ?? '#2563EB';
                        $icon  = $subject['icon'] ?? 'book';
                        $pendingCount = $pendingCounts[$subject['id']] ?? 0;
                        $materialCount = $materialCounts[$subject['id']] ?? 0;
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
                                        <div class="truncate"><?php echo htmlspecialchars($student['strand']); ?></div>
                                        <div>Grade <?php echo htmlspecialchars($student['grade_level']); ?></div>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars($subject['description'] ?? 'No description available'); ?>
                                </p>
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-3 course-stats">
                                    <div class="flex items-center space-x-2 flex-1">
                                        <span class="inline-flex items-center whitespace-nowrap">
                                            <i class="fas fa-file-alt mr-1"></i>
                                            <?php echo $materialCount; ?> Materials
                                        </span>
                                        <span class="inline-flex items-center whitespace-nowrap">
                                            <i class="fas fa-tasks mr-1"></i>
                                            <?php echo $pendingCount; ?> Pending
                                        </span>
                                    </div>
                                    <div class="flex-shrink-0 ml-2">
                                        <span class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 rounded-full whitespace-nowrap">
                                            <i class="fas fa-<?php echo htmlspecialchars($icon); ?> mr-1"></i>
                                            View
                                        </span>
                                    </div>
                                </div>
                                <a href="student-course-detail.php?subject_id=<?php echo $subject['id']; ?>"
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
                    <p class="text-lg font-medium mb-2">No courses available for you yet.</p>
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