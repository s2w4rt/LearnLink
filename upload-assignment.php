<?php
// upload-assignment.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is authenticated and is a student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['file'];
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

    if ($assignment_id <= 0 || $student_id <= 0 || $student_id != $user['id']) {
        throw new Exception('Invalid assignment or student ID');
    }

    // Validate file size (200MB limit)
    if ($file['size'] > 200 * 1024 * 1024) {
        throw new Exception('File size exceeds 200MB limit');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/assignments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'submission_' . $student_id . '_' . $assignment_id . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Store file info in session for submission
    if (!isset($_SESSION['uploaded_files'])) {
        $_SESSION['uploaded_files'] = [];
    }

    $_SESSION['uploaded_files'][$assignment_id] = [
        'path' => $filePath,
        'name' => $fileName,
        'original_name' => $file['name'],
        'size' => $file['size']
    ];

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file_path' => $filePath
    ]);

} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>