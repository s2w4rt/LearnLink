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

// Get pending assignments count per subject
$pendingCounts = [];
foreach ($subjects as $subject) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as pending_count 
        FROM assignments a 
        LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
        WHERE a.subject_id = ? AND a.status = 'active' 
        AND (sa.status IS NULL OR sa.status = 'assigned')
        AND a.due_date >= CURDATE()
    ");
    $stmt->execute([$user['id'], $subject['id']]);
    $pendingCounts[$subject['id']] = $stmt->fetchColumn();
}

// Get all pending assignments for the student
$stmt = $db->prepare("
    SELECT a.* 
    FROM assignments a 
    LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.student_id = ?
    WHERE a.strand = ? AND a.status = 'active' 
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .slider {
            transition: transform 0.5s ease-in-out;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .course-progress {
            transition: all 0.3s ease;
        }
        .course-progress:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <img src="" alt="Allshs" class="h-10">
                <h1 class="text-xl font-bold text-blue-800">Angelo Levardo SHS</h1>
            </div>
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                        <?php echo substr($student['full_name'], 0, 2); ?>
                    </div>
                    <div class="text-right">
                        <span class="font-medium block"><?php echo $student['full_name']; ?></span>
                        <span class="text-sm text-gray-600"><?php echo $student['strand']; ?> Student</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-6 flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-md rounded-lg mr-6 h-fit">
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="#" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-600 bg-blue-50 text-blue-600">
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
                            <?php if (count($pendingAssignments) > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                    <?php echo count($pendingAssignments); ?>
                                </span>
                            <?php endif; ?>
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
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Image Slider -->
            <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden relative">
                <div class="slider flex">
                    <div class="w-full flex-shrink-0 fade-in">
                        <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="ALLSYS Campus" class="w-full h-64 object-cover">
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                                        <?php echo $pendingCount > 0 ? $pendingCount . ' assignments due' : 'All caught up'; ?>
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
        <aside class="w-80 ml-6 space-y-6">
            <!-- Calendar -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-xl font-bold mb-4 text-blue-800">Calendar - <?php echo date('F Y'); ?></h2>
                <div class="grid grid-cols-7 gap-1 text-center text-sm">
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
                        if ($hasAssignment) echo "Assignments due";
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
                        <span>Assignments Due</span>
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
                        // Group by type for display
                        $groupedAssignments = [];
                        foreach ($pendingAssignments as $assignment) {
                            $type = $assignment['type'];
                            if (!isset($groupedAssignments[$type])) {
                                $groupedAssignments[$type] = [];
                            }
                            $groupedAssignments[$type][] = $assignment;
                        }
                        ?>
                        <?php foreach ($groupedAssignments as $type => $assignments): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div>
                                    <p class="font-medium"><?php echo $type; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo count($assignments); ?> assignment<?php echo count($assignments) > 1 ? 's' : ''; ?> pending</p>
                                </div>
                                <a href="student-assignments.php?type=<?php echo urlencode($type); ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                    View
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                            <p class="text-gray-500">All caught up!</p>
                            <p class="text-sm text-gray-400">No pending assignments</p>
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
                            <p class="text-gray-500">No assignments due today</p>
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
                                <div class="relative">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                        <?php echo substr($classmate['full_name'], 0, 2); ?>
                                    </div>
                                    <div class="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-green-500 border-2 border-white"></div>
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
        // Image Slider Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const slider = document.querySelector('.slider');
            const slides = document.querySelectorAll('.slider > div');
            const dots = document.querySelectorAll('.slider-btn');
            const prevBtn = document.querySelector('.slider-prev');
            const nextBtn = document.querySelector('.slider-next');
            let currentSlide = 0;
            
            function showSlide(index) {
                // Hide all slides
                slides.forEach(slide => {
                    slide.classList.add('hidden');
                    slide.classList.remove('fade-in');
                });
                
                // Remove active state from all dots
                dots.forEach(dot => {
                    dot.classList.remove('bg-white', 'opacity-100');
                    dot.classList.add('opacity-50');
                });
                
                // Show current slide
                slides[index].classList.remove('hidden');
                setTimeout(() => {
                    slides[index].classList.add('fade-in');
                }, 10);
                
                // Update active dot
                dots[index].classList.add('bg-white', 'opacity-100');
                dots[index].classList.remove('opacity-50');
                
                currentSlide = index;
            }
            
            // Initialize first slide
            showSlide(0);
            
            // Next slide
            nextBtn.addEventListener('click', function() {
                let nextIndex = (currentSlide + 1) % slides.length;
                showSlide(nextIndex);
            });
            
            // Previous slide
            prevBtn.addEventListener('click', function() {
                let prevIndex = (currentSlide - 1 + slides.length) % slides.length;
                showSlide(prevIndex);
            });
            
            // Dot navigation
            dots.forEach((dot, index) => {
                dot.addEventListener('click', function() {
                    showSlide(index);
                });
            });
            
            // Auto slide every 5 seconds
            setInterval(function() {
                let nextIndex = (currentSlide + 1) % slides.length;
                showSlide(nextIndex);
            }, 5000);
        });
    </script>
</body>
</html>