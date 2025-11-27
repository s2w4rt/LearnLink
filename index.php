<?php
// index.php

// Database connection (adjust your settings)
$host = 'localhost';
$dbname = 'allshs_elms';
$username = 'root';
$password = '';  // Add your database password here

// Initialize the PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_number = generateTicketNumber();  // Function to generate a unique ticket number
    $name = $_POST['name'];
    $email = $_POST['email'];
    $issue_type = $_POST['issue_type'];
    $message = $_POST['message'];
    $status = 'pending';  // Set initial status to 'pending'

    // Prepare SQL query to insert ticket into the database
    $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_number, name, email, issue_type, message, status) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ticket_number, $name, $email, $issue_type, $message, $status]);

    // Optionally send confirmation email
    sendConfirmationEmail($email, $ticket_number);

    // Success message
    $confirmation_message = "Ticket submitted successfully. Your ticket number is: $ticket_number.";
}

function generateTicketNumber() {
    // Logic to generate a unique ticket number (e.g., TKT + timestamp + random number)
    return 'TKT' . date('YmdHis') . rand(1000, 9999);
}

function sendConfirmationEmail($email, $ticket_number) {
    // Logic to send an email confirmation
    $subject = "Support Ticket Confirmation";
    $message = "Thank you for reaching out! Your support ticket number is: $ticket_number";
    $headers = "From: no-reply@yourdomain.com";
    
    // Send email
    mail($email, $subject, $message, $headers);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALLSHS eLMS - Senior High Learning Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .hero-pattern {
            background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 0%, transparent 55%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .login-modal, .support-modal {
            transition: all 0.3s ease;
        }
        .login-modal.hidden, .support-modal.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .login-modal:not(.hidden), .support-modal:not(.hidden) {
            opacity: 1;
        }
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }
        .success-message {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .input-error {
            border-color: #dc2626 !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">LearnLink</h1>
                        <p class="text-xs text-gray-600">Senior High Learning System</p>
                    </div>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-700 hover:text-indigo-600 font-medium">Features</a>
                    <a href="#strands" class="text-gray-700 hover:text-indigo-600 font-medium">Strands</a>
                    <a href="#about" class="text-gray-700 hover:text-indigo-600 font-medium">About</a>
                    <button onclick="openSupportModal()" class="text-gray-700 hover:text-indigo-600 font-medium">Support</button>
                    <button onclick="openLoginModal()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </div>

                <!-- Mobile menu button -->
                <button class="md:hidden text-gray-700" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="md:hidden hidden pb-4 border-t border-gray-200">
                <div class="flex flex-col space-y-4 mt-4">
                    <a href="#features" class="text-gray-700 hover:text-indigo-600">Features</a>
                    <a href="#strands" class="text-gray-700 hover:text-indigo-600">Strands</a>
                    <a href="#about" class="text-gray-700 hover:text-indigo-600">About</a>
                    <button onclick="openSupportModal()" class="text-gray-700 hover:text-indigo-600 text-left">Support</button>
                    <button onclick="openLoginModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-left">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Error Alert -->
    <?php if (isset($_GET['error'])): ?>
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="error-message" id="errorAlert">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span>
                        <?php
                        $error = $_GET['error'];
                        switch($error) {
                            case 'invalid_credentials':
                                echo 'Invalid username or password. Please try again.';
                                break;
                            case 'empty_fields':
                                echo 'Please fill in all fields.';
                                break;
                            case 'invalid_email':
                                echo 'Please enter a valid email address.';
                                break;
                            case 'support_error':
                                echo 'There was an error submitting your support ticket. Please try again.';
                                break;
                            default:
                                echo 'An error occurred. Please try again.';
                        }
                        ?>
                    </span>
                </div>
                <button onclick="document.getElementById('errorAlert').style.display='none'" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Success Alert -->
    <?php if (isset($_GET['success'])): ?>
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="success-message" id="successAlert">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span>
                        <?php
                        $success = $_GET['success'];
                        switch($success) {
                            case 'support_submitted':
                                echo 'Your support ticket has been submitted successfully! We will contact you soon.';
                                break;
                            default:
                                echo 'Action completed successfully.';
                        }
                        ?>
                    </span>
                </div>
                <button onclick="document.getElementById('successAlert').style.display='none'" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="gradient-bg text-white">
        <div class="hero-pattern">
            <div class="max-w-7xl mx-auto px-4 py-20">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 class="text-5xl font-bold mb-6 leading-tight">
                            Welcome to 
                            <span class="text-yellow-300">LearnLink</span>
                        </h1>
                        <p class="text-xl mb-8 text-blue-100 leading-relaxed">
                            Advanced Learning Management System for Senior High School students, teachers, and administrators. 
                            Access your courses, materials, and assignments anytime, anywhere.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <button onclick="openLoginModal()" class="bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                                <i class="fas fa-rocket mr-2"></i>Get Started
                            </button>
                            <button onclick="scrollToFeatures()" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-indigo-600 transition-colors">
                                <i class="fas fa-play-circle mr-2"></i>Learn More
                            </button>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="text-center p-6 bg-white/10 rounded-xl hover:bg-white/20 transition-colors">
                                    <i class="fas fa-user-graduate text-4xl text-yellow-300 mb-4"></i>
                                    <h3 class="font-bold text-lg">Students</h3>
                                    <p class="text-blue-100 text-sm mt-2">Access learning materials and submit assignments</p>
                                </div>
                                <div class="text-center p-6 bg-white/10 rounded-xl hover:bg-white/20 transition-colors">
                                    <i class="fas fa-chalkboard-teacher text-4xl text-green-300 mb-4"></i>
                                    <h3 class="font-bold text-lg">Teachers</h3>
                                    <p class="text-blue-100 text-sm mt-2">Manage classes and track student progress</p>
                                </div>
                                <div class="text-center p-6 bg-white/10 rounded-xl hover:bg-white/20 transition-colors">
                                    <i class="fas fa-user-shield text-4xl text-red-300 mb-4"></i>
                                    <h3 class="font-bold text-lg">Admin</h3>
                                    <p class="text-blue-100 text-sm mt-2">System management and analytics</p>
                                </div>
                                <div class="text-center p-6 bg-white/10 rounded-xl hover:bg-white/20 transition-colors">
                                    <i class="fas fa-laptop-code text-4xl text-purple-300 mb-4"></i>
                                    <h3 class="font-bold text-lg">Online</h3>
                                    <p class="text-blue-100 text-sm mt-2">24/7 access to learning resources</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Powerful Features</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Everything you need for effective teaching and learning in one platform
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="card-hover bg-gray-50 p-8 rounded-2xl border border-gray-200">
                    <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-book-open text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Learning Materials</h3>
                    <p class="text-gray-600 mb-4">
                        Access handouts, presentations, videos, and other resources organized by quarter and subject.
                    </p>
                    <ul class="text-gray-600 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Quarter-based organization
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Multiple file formats
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Mobile-friendly access
                        </li>
                    </ul>
                </div>

                <div class="card-hover bg-gray-50 p-8 rounded-2xl border border-gray-200">
                    <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-tasks text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Assignments & Tasks</h3>
                    <p class="text-gray-600 mb-4">
                        Submit assignments, track deadlines, and receive feedback from teachers seamlessly.
                    </p>
                    <ul class="text-gray-600 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Automated deadline tracking
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Real-time submission status
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Teacher feedback system
                        </li>
                    </ul>
                </div>

                <div class="card-hover bg-gray-50 p-8 rounded-2xl border border-gray-200">
                    <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-chart-line text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Progress Tracking</h3>
                    <p class="text-gray-600 mb-4">
                        Monitor academic progress with detailed analytics and performance reports.
                    </p>
                    <ul class="text-gray-600 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Grade visualization
                        </li>
                        
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Performance analytics
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Strands Section -->
    <section id="strands" class="py-20 bg-gray-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Academic Strands</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Specialized tracks designed to prepare students for their chosen career paths
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-hover bg-white p-8 rounded-2xl border border-gray-200 text-center">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-users text-purple-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">HUMSS</h3>
                    <p class="text-gray-600 mb-6">
                        Humanities and Social Sciences - Focuses on human behavior, literature, politics, and social systems.
                    </p>
                    <div class="bg-purple-50 text-purple-700 px-4 py-2 rounded-lg inline-block">
                        <i class="fas fa-book-reader mr-2"></i>Humanities Focus
                    </div>
                </div>

                <div class="card-hover bg-white p-8 rounded-2xl border border-gray-200 text-center">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-laptop-code text-blue-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">ICT</h3>
                    <p class="text-gray-600 mb-6">
                        Information and Communication Technology - Covers programming, web development, and digital systems.
                    </p>
                    <div class="bg-blue-50 text-blue-700 px-4 py-2 rounded-lg inline-block">
                        <i class="fas fa-code mr-2"></i>Technology Focus
                    </div>
                </div>

                <div class="card-hover bg-white p-8 rounded-2xl border border-gray-200 text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-flask text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">STEM</h3>
                    <p class="text-gray-600 mb-6">
                        Science, Technology, Engineering, and Mathematics - Emphasizes scientific inquiry and innovation.
                    </p>
                    <div class="bg-green-50 text-green-700 px-4 py-2 rounded-lg inline-block">
                        <i class="fas fa-microscope mr-2"></i>Science Focus
                    </div>
                </div>

                <div class="card-hover bg-white p-8 rounded-2xl border border-gray-200 text-center">
                    <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-tools text-yellow-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">TVL</h3>
                    <p class="text-gray-600 mb-6">
                        Technical-Vocational Livelihood - Provides technical skills for various industries and trades.
                    </p>
                    <div class="bg-yellow-50 text-yellow-700 px-4 py-2 rounded-lg inline-block">
                        <i class="fas fa-wrench mr-2"></i>Technical Focus
                    </div>
                </div>

                <div class="card-hover bg-white p-8 rounded-2xl border border-gray-200 text-center">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-utensils text-red-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">TVL-HE</h3>
                    <p class="text-gray-600 mb-6">
                        TVL - Home Economics - Focuses on culinary arts, hospitality, and home management skills.
                    </p>
                    <div class="bg-red-50 text-red-700 px-4 py-2 rounded-lg inline-block">
                        <i class="fas fa-chef-hat mr-2"></i>Culinary Focus
                    </div>
                </div>

                <div class="card-hover bg-white p-8 rounded-2xl border border-gray-200 text-center flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-graduation-cap text-indigo-600 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Need Help?</h3>
                        <p class="text-gray-600 mb-6">
                            Forgot your password or username? We're here to help!
                        </p>
                        <button onclick="openSupportModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition-colors">
                            Get Support
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-16 bg-indigo-600 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-4xl font-bold mb-2">2,500+</div>
                    <div class="text-indigo-200">Active Students</div>
                </div>
                <div>
                    <div class="text-4xl font-bold mb-2">150+</div>
                    <div class="text-indigo-200">Expert Teachers</div>
                </div>
                <div>
                    <div class="text-4xl font-bold mb-2">5,000+</div>
                    <div class="text-indigo-200">Learning Materials</div>
                </div>
                <div>
                    <div class="text-4xl font-bold mb-2">24/7</div>
                    <div class="text-indigo-200">Support Available</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-bold text-gray-800 mb-6">About ALLSHS eLMS</h2>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        ALLSHS eLMS is a comprehensive Learning Management System specifically designed for Senior High School 
                        education. Our platform bridges the gap between traditional classroom learning and modern digital education.
                    </p>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        With features tailored for students, teachers, and administrators, we provide a seamless educational 
                        experience that enhances learning outcomes and simplifies academic management.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-shield-alt text-green-500 text-xl mr-3"></i>
                            <span>Secure & Reliable</span>
                        </div>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-mobile-alt text-blue-500 text-xl mr-3"></i>
                            <span>Mobile Friendly</span>
                        </div>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-headset text-purple-500 text-xl mr-3"></i>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-8 text-white">
                        <h3 class="text-2xl font-bold mb-6">Need Assistance?</h3>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <i class="fas fa-key text-yellow-300 text-xl mr-4 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold mb-1">Forgot Password?</h4>
                                    <p class="text-indigo-100 text-sm">Submit a support ticket to reset your password</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-user text-yellow-300 text-xl mr-4 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold mb-1">Forgot Username?</h4>
                                    <p class="text-indigo-100 text-sm">We can help you recover your account access</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-lock text-yellow-300 text-xl mr-4 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold mb-1">Account Issues?</h4>
                                    <p class="text-indigo-100 text-sm">Get help with login problems and account access</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-question-circle text-yellow-300 text-xl mr-4 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold mb-1">Other Problems?</h4>
                                    <p class="text-indigo-100 text-sm">We're here to help with any platform issues</p>
                                </div>
                            </div>
                        </div>
                        <button onclick="openSupportModal()" class="w-full mt-6 bg-white text-indigo-600 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                            <i class="fas fa-headset mr-2"></i>Get Help Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">ALLSHS eLMS</h3>
                            <p class="text-gray-400 text-sm">Senior High Learning System</p>
                        </div>
                    </div>
                    <p class="text-gray-400 mb-4">
                        Advanced Learning Management System for modern education needs.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-semibold mb-6">Quick Links</h4>
                    <ul class="space-y-3">
                        <li><a href="#features" class="text-gray-400 hover:text-white transition-colors">Features</a></li>
                        <li><a href="#strands" class="text-gray-400 hover:text-white transition-colors">Academic Strands</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                        <li><button onclick="openSupportModal()" class="text-gray-400 hover:text-white transition-colors text-left">Support</button></li>
                        <li><button onclick="openLoginModal()" class="text-gray-400 hover:text-white transition-colors text-left">Login</button></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-lg font-semibold mb-6">Support</h4>
                    <ul class="space-y-3">
                        <li><button onclick="openSupportModal('password')" class="text-gray-400 hover:text-white transition-colors text-left">Password Reset</button></li>
                        <li><button onclick="openSupportModal('username')" class="text-gray-400 hover:text-white transition-colors text-left">Username Recovery</button></li>
                        <li><button onclick="openSupportModal('account')" class="text-gray-400 hover:text-white transition-colors text-left">Account Issues</button></li>
                        <li><button onclick="openSupportModal('technical')" class="text-gray-400 hover:text-white transition-colors text-left">Technical Support</button></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-lg font-semibold mb-6">Contact Info</h4>
                    <div class="space-y-3 text-gray-400">
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-3 text-indigo-400"></i>
                            <span>123 Education St., Learning City</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone mr-3 text-indigo-400"></i>
                            <span>+1 (555) 123-4567</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-envelope mr-3 text-indigo-400"></i>
                            <span>support@allshs-elms.edu</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-12 pt-8 text-center text-gray-400">
                <p>&copy; 2024 ALLSHS eLMS. All rights reserved. | Designed for Senior High Education</p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="login-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 <?php echo (isset($_GET['error']) && $_GET['error'] == 'invalid_credentials') ? '' : 'hidden'; ?>">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h2>
                <p class="text-gray-600">Sign in to your account</p>
            </div>

            <!-- Login Error Message inside Modal -->
            <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_credentials'): ?>
            <div class="error-message mb-4" id="loginError" style="display: block;">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span>Invalid username or password. Please try again.</span>
                </div>
            </div>
            <?php else: ?>
            <div class="error-message mb-4" id="loginError">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span>Invalid username or password. Please try again.</span>
                </div>
            </div>
            <?php endif; ?>

            <form id="loginForm" action="login.php" method="POST">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="username">
                        Username or Student ID
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                        placeholder="Enter your username or student ID"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="password">
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                        placeholder="Enter your password"
                    >
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="role">
                        Login As
                    </label>
                    <select 
                        id="role" 
                        name="role"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                    >
                        <option value="">Select your role</option>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>

                <button 
                    type="submit"
                    class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition-colors mb-4"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>

                <div class="text-center">
                    <button type="button" onclick="closeLoginModal(); openSupportModal('login_help');" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        <i class="fas fa-question-circle mr-1"></i>Forgot your password or username?
                    </button>
                </div>
            </form>

            <button 
                onclick="closeLoginModal()"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors"
            >
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Support Modal -->
    <div id="supportModal" class="support-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-headset text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Get Help & Support</h2>
                <p class="text-gray-600">We're here to assist you with any issues</p>
            </div>

            <!-- Support Form Messages -->
            <div id="supportFormMessage" class="hidden mb-4 p-4 rounded-lg"></div>

            <form id="supportForm" class="space-y-6">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportName">
                            Full Name *
                        </label>
                        <input 
                            type="text" 
                            id="supportName" 
                            name="name"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                            placeholder="Enter your full name"
                        >
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportEmail">
                            Email Address *
                        </label>
                        <input 
                            type="email" 
                            id="supportEmail" 
                            name="email"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                            placeholder="Enter your email address"
                        >
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportStudentId">
                            Student ID
                        </label>
                        <input 
                            type="text" 
                            id="supportStudentId" 
                            name="student_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                            placeholder="Enter your student ID (if applicable)"
                        >
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportStrand">
                            Strand
                        </label>
                        <select 
                            id="supportStrand" 
                            name="strand"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                        >
                            <option value="">Select your strand (optional)</option>
                            <option value="HUMSS">HUMSS</option>
                            <option value="ICT">ICT</option>
                            <option value="STEM">STEM</option>
                            <option value="TVL">TVL</option>
                            <option value="TVL-HE">TVL-HE</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportIssue">
                        Issue Type *
                    </label>
                    <select 
                        id="supportIssue" 
                        name="issue_type"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                    >
                        <option value="">Select the type of issue</option>
                        <option value="forgot_password">Forgot Password</option>
                        <option value="forgot_username">Forgot Username</option>
                        <option value="account_locked">Account Locked</option>
                        <option value="login_issues">Login Issues</option>
                        <option value="material_access">Learning Material Access</option>
                        <option value="assignment_issues">Assignment Submission</option>
                        <option value="technical_issues">Technical Issues</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportUrgency">
                        Urgency Level
                    </label>
                    <select 
                        id="supportUrgency" 
                        name="urgency"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                    >
                        <option value="low">Low - Not urgent</option>
                        <option value="medium" selected>Medium - Need help soon</option>
                        <option value="high">High - Urgent, blocking my work</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="supportMessage">
                        Detailed Description *
                    </label>
                    <textarea 
                        id="supportMessage" 
                        name="message"
                        rows="6"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                        placeholder="Please describe your issue in detail. Include any error messages you're seeing, steps to reproduce the issue, and what you were trying to accomplish. The more details you provide, the better we can help you."
                    ></textarea>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 text-lg mr-3 mt-0.5"></i>
                        <div>
                            <h4 class="font-semibold text-blue-800 mb-1">What happens next?</h4>
                            <p class="text-blue-700 text-sm">
                                After submitting this form, our admin team will review your request and contact you via email 
                                within 24 hours (during school days). For password and username issues, we'll verify your identity 
                                and help you regain access to your account.
                            </p>
                        </div>
                    </div>
                </div>

                <button 
                    type="submit"
                    class="w-full bg-indigo-600 text-white py-4 rounded-lg font-semibold hover:bg-indigo-700 transition-colors text-lg"
                    id="supportSubmitBtn"
                >
                    <i class="fas fa-paper-plane mr-2"></i>Submit Support Request
                </button>

                <p class="text-xs text-gray-500 text-center">
                    By submitting this form, you agree to our privacy policy and terms of service. 
                    We respect your privacy and will only use your information to assist with your support request.
                </p>
            </form>

            <button 
                onclick="closeSupportModal()"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors"
            >
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
    </div>

    <script>
    // Mobile menu toggle
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }

    // Login modal functions
    function openLoginModal() {
        const modal = document.getElementById('loginModal');
        const error = document.getElementById('loginError');
        if (modal) modal.classList.remove('hidden');
        if (error) error.style.display = 'none';
        document.body.style.overflow = 'hidden';
    }

    function closeLoginModal() {
        const modal = document.getElementById('loginModal');
        const error = document.getElementById('loginError');
        if (modal) modal.classList.add('hidden');
        if (error) error.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Support modal functions
    function openSupportModal(issueType = '') {
        const modal = document.getElementById('supportModal');
        const issueSelect = document.getElementById('supportIssue');
        const messageField = document.getElementById('supportMessage');
        
        if (modal) modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Clear any previous messages
        const messageDiv = document.getElementById('supportFormMessage');
        if (messageDiv) {
            messageDiv.classList.add('hidden');
        }
        
        // Set issue type if provided
        if (issueType && issueSelect && messageField) {
            let issueValue = '';
            let prefillMessage = '';
            
            switch(issueType) {
                case 'password':
                    issueValue = 'forgot_password';
                    prefillMessage = 'I forgot my password and cannot log in to my account. Please help me reset my password.\n\nAdditional details: ';
                    break;
                case 'username':
                    issueValue = 'forgot_username';
                    prefillMessage = 'I forgot my username and cannot log in to my account. Please help me recover my username.\n\nAdditional details: ';
                    break;
                case 'account':
                    issueValue = 'account_locked';
                    prefillMessage = 'My account appears to be locked or I cannot access it. Please help me regain access.\n\nAdditional details: ';
                    break;
                case 'technical':
                    issueValue = 'technical_issues';
                    prefillMessage = 'I am experiencing technical issues with the platform.\n\nIssue description: ';
                    break;
                case 'login_help':
                    issueValue = 'login_issues';
                    prefillMessage = 'I need help with logging in to my account.\n\nWhat happens when I try to login: ';
                    break;
                default:
                    prefillMessage = '';
            }
            
            if (issueValue) {
                issueSelect.value = issueValue;
            }
            messageField.value = prefillMessage;
        }
    }

    function closeSupportModal() {
        const modal = document.getElementById('supportModal');
        if (modal) modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        
        // Reset form
        const form = document.getElementById('supportForm');
        if (form) form.reset();
        
        // Clear messages
        const messageDiv = document.getElementById('supportFormMessage');
        if (messageDiv) {
            messageDiv.classList.add('hidden');
        }
    }

    // Smooth scrolling
    function scrollToFeatures() {
        const features = document.getElementById('features');
        if (features) {
            features.scrollIntoView({ 
                behavior: 'smooth' 
            });
        }
    }

    // AJAX support form submission
    document.getElementById('supportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('supportSubmitBtn');
        const originalText = submitBtn.innerHTML;
        const messageDiv = document.getElementById('supportFormMessage');
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
        submitBtn.disabled = true;
        
        // Hide any previous messages
        messageDiv.classList.add('hidden');
        
        // Get form data
        const formData = new FormData(this);
        
        // Submit via AJAX
        fetch('submit_support.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showSupportMessage(data.message, 'success');
                // Reset form
                this.reset();
                // Auto-close modal after 3 seconds
                setTimeout(() => {
                    closeSupportModal();
                }, 3000);
            } else {
                // Show error message
                showSupportMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showSupportMessage('There was an error submitting your support ticket. Please try again.', 'error');
        })
        .finally(() => {
            // Reset button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    function showSupportMessage(message, type) {
        const messageDiv = document.getElementById('supportFormMessage');
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'} mr-3 text-lg"></i>
                <span>${message}</span>
            </div>
        `;
        messageDiv.className = `p-4 rounded-lg mb-4 ${type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'}`;
        messageDiv.classList.remove('hidden');
    }

    // Initialize everything when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Setup modal close handlers
        setupModalCloseHandlers();
        
        // Auto-close messages after 5 seconds
        setTimeout(function() {
            const errorAlert = document.getElementById('errorAlert');
            const successAlert = document.getElementById('successAlert');
            if (errorAlert) errorAlert.style.display = 'none';
            if (successAlert) successAlert.style.display = 'none';
        }, 5000);

        // Add animation to cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.card-hover').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    });

    // Safe modal close handlers
    function setupModalCloseHandlers() {
        const loginModal = document.getElementById('loginModal');
        const supportModal = document.getElementById('supportModal');
        
        if (loginModal) {
            loginModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeLoginModal();
                }
            });
        }
        
        if (supportModal) {
            supportModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeSupportModal();
                }
            });
        }
    }
</script>
</body>
</html>