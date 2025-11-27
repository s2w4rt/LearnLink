<?php
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
    // Get POST data
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if ($assignment_id <= 0 || $student_id <= 0 || $student_id != $user['id']) {
        throw new Exception('Invalid assignment or student ID');
    }

    // Check if draft files exist in session
    if (!isset($_SESSION['draft_files'][$assignment_id]) || empty($_SESSION['draft_files'][$assignment_id])) {
        throw new Exception('No files to submit. Please attach a file first.');
    }

    $draftFiles = $_SESSION['draft_files'][$assignment_id];
    
    // For now, we'll use the first file (you can modify this for multiple files)
    $fileData = $draftFiles[0];

    // Check if assignment exists and get details
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ? AND status = 'active'");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception('Assignment not found or inactive');
    }

    // Check if assignment is overdue
    $currentDateTime = new DateTime();
    $dueDateTime = new DateTime($assignment['due_date'] . ' 23:59:59');
    $isLate = $currentDateTime > $dueDateTime;
    $status = $isLate ? 'late' : 'submitted';

    // Check if student already submitted
    $stmt = $db->prepare("SELECT * FROM student_assignments WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignment_id, $student_id]);
    $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);

    // Handle file upload
    $uploadDir = 'uploads/assignments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $fileExtension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . $student_id . '_' . $assignment_id . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    // For draft files, we need to handle the actual file upload
    // Since we're getting the file from the draft, we need to process it
    if (isset($_FILES['file'])) {
        // File was uploaded via form
        $uploadedFile = $_FILES['file'];
        
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $uploadedFile['error']);
        }
        
        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        $fileSize = $uploadedFile['size'];
        $originalFileName = $uploadedFile['name'];
    } else {
        // For draft submissions, we need to get the actual file content
        // This is a simplified approach - you might need to adjust based on how you store draft files
        throw new Exception('File data not available. Please re-upload your file.');
    }

    if ($existingSubmission) {
        // Remove old file if exists
        if (!empty($existingSubmission['file_path']) && file_exists($existingSubmission['file_path'])) {
            unlink($existingSubmission['file_path']);
        }
        
        // Update existing submission
        $stmt = $db->prepare("
            UPDATE student_assignments 
            SET file_path = ?, file_name = ?, original_file_name = ?, file_size = ?, 
                submitted_at = NOW(), status = ?, updated_at = NOW()
            WHERE assignment_id = ? AND student_id = ?
        ");
        
        $result = $stmt->execute([
            $filePath,
            $fileName,
            $originalFileName,
            $fileSize,
            $status,
            $assignment_id,
            $student_id
        ]);
    } else {
        // Create new submission
        $stmt = $db->prepare("
            INSERT INTO student_assignments 
            (assignment_id, student_id, file_path, file_name, original_file_name, file_size, submitted_at, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $assignment_id,
            $student_id,
            $filePath,
            $fileName,
            $originalFileName,
            $fileSize,
            $status
        ]);
    }

    if ($result) {
        // Clear draft files from session after successful submission
        unset($_SESSION['draft_files'][$assignment_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'is_late' => $isLate
        ]);
    } else {
        // Clean up uploaded file if database operation failed
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        throw new Exception('Failed to save submission to database');
    }

} catch (Exception $e) {
    // Clean up uploaded file on error
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    error_log('Submission error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>