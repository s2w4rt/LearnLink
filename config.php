<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Database configuration (XAMPP)
|--------------------------------------------------------------------------
*/
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'allshs_elms');

/*
|--------------------------------------------------------------------------
| PHPMailer (official version)
| Folder structure expected:
|   capstone/PHPMailer/src/PHPMailer.php
|   capstone/PHPMailer/src/SMTP.php
|   capstone/PHPMailer/src/Exception.php
|--------------------------------------------------------------------------
*/
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/*
|--------------------------------------------------------------------------
| 2FA / OTP settings
|--------------------------------------------------------------------------
*/
define('OTP_EXPIRY_SECONDS', 300); // 5 minutes
define('OTP_LENGTH', 6);

/**
 * Generate a random numeric OTP.
 */
function generateOtpCode($length = OTP_LENGTH) {
    $min = pow(10, $length - 1);
    $max = pow(10, $length) - 1;
    return (string) random_int($min, $max);
}

/**
 * Send the OTP email via Gmail SMTP using PHPMailer.
 * This is used for LOGIN 2FA, not registration.
 */
function sendOtpEmail($toEmail, $otpCode) {
    if (empty($toEmail) || empty($otpCode)) {
        return;
    }

    $subject = 'Your ALLSHS eLMS login code';
    $message = "Your one-time password (OTP) is: {$otpCode}\n\n" .
               "This code will expire in " . (OTP_EXPIRY_SECONDS / 60) . " minutes.\n\n" .
               "If you did not request this login, you can ignore this email.";

    // ⚠️ CHANGE THESE LINES TO YOUR GMAIL + APP PASSWORD
    $gmailUser = 'acostaethang4@gmail.com';       // sender Gmail
    $gmailPass = 'lxts dqdm tafw emkx';    // 16-char app password

    $mail = new PHPMailer(true);

    try {
        // DEBUG (optional): send SMTP debug to php_error_log
        // $mail->SMTPDebug  = 2;
        // $mail->Debugoutput = 'error_log';

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailUser;
        $mail->Password   = $gmailPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($gmailUser, 'ALLSHS eLMS');
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(false); // plain text
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        error_log("OTP email sent to $toEmail with code $otpCode");
    } catch (Exception $e) {
        error_log('OTP email could not be sent. PHPMailer error: ' . $mail->ErrorInfo);
    }
}

/**
 * Enforce OTP-based two-factor authentication for LOGIN.
 * Called automatically from checkAuth() on first protected page load.
 */
function requireTwoFactorAuth() {
    // No logged-in user -> do nothing here
    if (!isset($_SESSION['user'])) {
        return;
    }

    // Already verified this session
    if (!empty($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true) {
        return;
    }

    // If user has no email, auto-approve 2FA to avoid lockout
    $email = $_SESSION['user']['email'] ?? null;
    if (!$email) {
        $_SESSION['2fa_verified'] = true;
        return;
    }

    $currentScript = basename($_SERVER['PHP_SELF'] ?? '');

    // Don't redirect when already on OTP pages
    if ($currentScript === 'verify-otp.php' || $currentScript === 'verify-otp-resend.php') {
        return;
    }

    // Remember where user originally wanted to go (admin-home, teacher-dashboard, etc.)
    if (empty($_SESSION['2fa_redirect'])) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'admin-home.php';
        $_SESSION['2fa_redirect'] = $requestUri;
    }

    $now       = time();
    $expiresAt = $_SESSION['otp_expires_at'] ?? 0;

    // Generate + send new OTP if none yet or expired
    if (empty($_SESSION['otp_code']) || $now >= $expiresAt) {
        $otp = generateOtpCode();
        $_SESSION['otp_code']       = $otp;
        $_SESSION['otp_expires_at'] = $now + OTP_EXPIRY_SECONDS;

        sendOtpEmail($email, $otp);
    }

    header('Location: verify-otp.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $db;
}

/*
|--------------------------------------------------------------------------
| JSON helper
|--------------------------------------------------------------------------
*/
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/*
|--------------------------------------------------------------------------
| Password helpers
|--------------------------------------------------------------------------
*/
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/*
|--------------------------------------------------------------------------
| Auth helpers (LOGIN + OTP)
|--------------------------------------------------------------------------
*/
function checkAuth($allowedRoles = []) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['user']['role'], $allowedRoles)) {
        header('Location: index.php');
        exit;
    }

    // 🔐 Enforce OTP 2FA after normal login
    requireTwoFactorAuth();
}

function checkAdminAuth() {
    checkAuth(['admin', 'superadmin']);
}

function checkTeacherAuth() {
    checkAuth(['teacher']);
}

function checkStudentAuth() {
    checkAuth(['student']);
}

/*
|--------------------------------------------------------------------------
| Redirect helpers
|--------------------------------------------------------------------------
*/
function redirectByRole($role) {
    switch ($role) {
        case 'admin':
        case 'superadmin':
            header('Location: admin-home.php');
            break;
        case 'teacher':
            header('Location: teacher-dashboard.php');
            break;
        case 'student':
            header('Location: student-dashboard.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}

// For existing code that calls redirectToDashboard()
function redirectToDashboard() {
    if (empty($_SESSION['user']) || empty($_SESSION['user']['role'])) {
        header('Location: index.php');
        exit;
    }

    redirectByRole($_SESSION['user']['role']);
}

// Helper to get current logged-in user
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

// 🔐 LOG LOGIN ATTEMPTS (admin / teacher / student)
function log_login_attempt($conn, $username, $context, $ip, $success) {
    if (!$conn) return;

    // context must be: admin | teacher | student
    if (!in_array($context, ['admin','teacher','student'], true)) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO login_attempts (username, context, ip_address, success)
        VALUES (?, ?, ?, ?)
    ");
    if ($stmt) {
        $s = $success ? 1 : 0;
        $stmt->bind_param("sssi", $username, $context, $ip, $s);
        $stmt->execute();
        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Login function (admin, teacher, student)
|--------------------------------------------------------------------------
*/
function loginUser($username, $password, $role) {
    $db = getDB();
    
    try {
        switch ($role) {
            case 'student':
                // Allow login by username OR legacy student_id
                $stmt = $db->prepare(
                    "SELECT * FROM students WHERE username = ? OR student_id = ? LIMIT 1"
                );
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $valid = false;
                    
                    // Preferred: verify hashed password if present
                    if (!empty($user['password_hash'])) {
                        $valid = password_verify($password, $user['password_hash']);
                    } else {
                        // Legacy accounts: student_id as password
                        $valid = ($password === $user['student_id']);
                    }
                    
                    if ($valid) {
                        $_SESSION['user'] = [
                            'id'          => $user['id'],
                            'username'    => $user['username'] ?: $user['student_id'],
                            'student_id'  => $user['student_id'],
                            'name'        => $user['full_name'],
                            'role'        => 'student',
                            'email'       => $user['email'] ?? null,
                            'strand'      => $user['strand'],
                            'grade_level' => $user['grade_level'],
                            'section'     => $user['section']
                        ];
                        // Reset OTP flag on new login
                        unset($_SESSION['2fa_verified'], $_SESSION['otp_code'], $_SESSION['otp_expires_at'], $_SESSION['2fa_redirect']);
                        return true;
                    }
                }
                break;
            
            case 'teacher':
                $stmt = $db->prepare("SELECT * FROM teachers WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && !empty($user['password_hash']) &&
                    password_verify($password, $user['password_hash'])) {
                    $_SESSION['user'] = [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'name'     => $user['name'],
                        'role'     => 'teacher',
                        'strand'   => $user['strand'],
                        'email'    => $user['email'] ?? null
                    ];
                    unset($_SESSION['2fa_verified'], $_SESSION['otp_code'], $_SESSION['otp_expires_at'], $_SESSION['2fa_redirect']);
                    return true;
                }
                break;
            
            case 'admin':
                $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Simple demo: allow username == password
                    if ($password === $user['username']) {
                        $_SESSION['user'] = [
                            'id'       => $user['id'],
                            'username' => $user['username'],
                            'name'     => $user['username'],
                            'role'     => $user['role'],
                            'email'    => $user['email'] ?? null
                        ];
                        unset($_SESSION['2fa_verified'], $_SESSION['otp_code'], $_SESSION['otp_expires_at'], $_SESSION['2fa_redirect']);
                        return true;
                    }

                    // Normal: check hashed password from DB
                    if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user'] = [
                            'id'       => $user['id'],
                            'username' => $user['username'],
                            'name'     => $user['username'],
                            'role'     => $user['role'],
                            'email'    => $user['email'] ?? null
                        ];
                        unset($_SESSION['2fa_verified'], $_SESSION['otp_code'], $_SESSION['otp_expires_at'], $_SESSION['2fa_redirect']);
                        return true;
                    }
                }
                break;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

?>
