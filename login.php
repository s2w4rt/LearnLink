<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    // Basic validation
    if ($username === '' || $password === '' || $role === '') {
        $_SESSION['error'] = "Please fill in all fields.";
        header('Location: index.php');
        exit;
    }

    // Only allow valid roles
    if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
        $_SESSION['error'] = "Invalid role selected.";
        header('Location: index.php');
        exit;
    }

    // Try to log the user in (sets $_SESSION['user'] on success)
    if (loginUser($username, $password, $role)) {
        // ✅ Login OK
        // OTP will be enforced on the first protected page
        // via checkAdminAuth/checkTeacherAuth/checkStudentAuth -> checkAuth -> requireTwoFactorAuth
        redirectToDashboard();
        exit;
    } else {
        // ❌ Login failed
        $_SESSION['error'] = "Invalid credentials. Please check your username, password, and role.";
        header('Location: index.php');
        exit;
    }
} else {
    // Invalid request method, go back to login page
    header('Location: index.php');
    exit;
}
?>
