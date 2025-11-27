<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

try {
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if ($assignment_id <= 0 || $student_id <= 0 || $student_id != $user['id']) {
        throw new Exception('Invalid assignment or student ID');
    }

    // Process the actual file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $uploadedFile = $_FILES['file'];
    
    // Validate file
    if ($uploadedFile['size'] > 200 * 1024 * 1024) {
        throw new Exception('File size exceeds 200MB limit');
    }

    $uploadDir = 'uploads/assignments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . $student_id . '_' . $assignment_id . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Get assignment details
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ? AND status = 'active'");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        unlink($filePath);
        throw new Exception('Assignment not found or inactive');
    }

    // Check if overdue
    $currentDateTime = new DateTime();
    $dueDateTime = new DateTime($assignment['due_date'] . ' 23:59:59');
    $isLate = $currentDateTime > $dueDateTime;
    $status = $isLate ? 'late' : 'submitted';

    // Check for existing submission
    $stmt = $db->prepare("SELECT * FROM student_assignments WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignment_id, $student_id]);
    $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSubmission) {
        // Remove old file
        if (!empty($existingSubmission['file_path']) && file_exists($existingSubmission['file_path'])) {
            unlink($existingSubmission['file_path']);
        }
        
        $stmt = $db->prepare("
            UPDATE student_assignments 
            SET file_path = ?, file_name = ?, original_file_name = ?, file_size = ?, 
                submitted_at = NOW(), status = ?, updated_at = NOW()
            WHERE assignment_id = ? AND student_id = ?
        ");
        
        $result = $stmt->execute([
            $filePath,
            $fileName,
            $uploadedFile['name'],
            $uploadedFile['size'],
            $status,
            $assignment_id,
            $student_id
        ]);
    } else {
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
            $uploadedFile['name'],
            $uploadedFile['size'],
            $status
        ]);
    }

    if ($result) {
        // Clear draft files from session
        if (isset($_SESSION['draft_files'][$assignment_id])) {
            unset($_SESSION['draft_files'][$assignment_id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'is_late' => $isLate
        ]);
    } else {
        unlink($filePath);
        throw new Exception('Database error');
    }

} catch (Exception $e) {
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    error_log('Draft submission error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>