<?php
session_start();
require_once 'config.php';

// Check if user is authenticated and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get assignment ID from POST data
$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit;
}

try {
    $db = getDB();
    
    // First, verify the assignment exists and belongs to the teacher
    $stmt = $db->prepare("
        SELECT a.*, s.strand 
        FROM assignments a 
        LEFT JOIN subjects s ON a.subject_id = s.id 
        WHERE a.id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $_SESSION['user']['id']]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit;
    }
    
    // Check if assignment is already deployed (has student assignments)
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM student_assignments 
        WHERE assignment_id = ?
    ");
    $stmt->execute([$assignment_id]);
    $existing_deployments = $stmt->fetchColumn();
    
    // Get detailed submission information if assignment is already deployed
    $submission_details = [];
    if ($existing_deployments > 0) {
        $stmt = $db->prepare("
            SELECT 
                sa.id,
                s.full_name,
                s.student_id,
                sa.status,
                sa.submitted_at,
                sa.score,
                sa.file_path
            FROM student_assignments sa
            JOIN students s ON sa.student_id = s.id
            WHERE sa.assignment_id = ?
            ORDER BY s.full_name
        ");
        $stmt->execute([$assignment_id]);
        $submission_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count submissions by status
        $submitted_count = 0;
        $graded_count = 0;
        foreach ($submission_details as $submission) {
            if (in_array($submission['status'], ['submitted', 'graded', 'late'])) {
                $submitted_count++;
            }
            if ($submission['status'] === 'graded') {
                $graded_count++;
            }
        }
        
        echo json_encode([
            'success' => false, 
            'message' => 'This assignment has already been deployed to students and cannot be deployed again.',
            'deployment_info' => [
                'total_students' => $existing_deployments,
                'submitted_count' => $submitted_count,
                'graded_count' => $graded_count,
                'submissions' => $submission_details
            ]
        ]);
        exit;
    }
    
    // Get all students in the same strand and grade level (assuming Grade 11)
    $stmt = $db->prepare("
        SELECT id 
        FROM students 
        WHERE strand = ? AND grade_level = 11
    ");
    $stmt->execute([$assignment['strand']]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No students found for this strand']);
        exit;
    }
    
    // Create assignment entries for each student
    $deployed_count = 0;
    $stmt = $db->prepare("
        INSERT INTO student_assignments (assignment_id, student_id, status, created_at) 
        VALUES (?, ?, 'assigned', NOW())
    ");
    
    foreach ($students as $student_id) {
        try {
            $stmt->execute([$assignment_id, $student_id]);
            $deployed_count++;
        } catch (PDOException $e) {
            error_log("Error deploying to student $student_id: " . $e->getMessage());
            // Continue with other students even if one fails
        }
    }
    
    // Update assignment status to active AND set is_deployed = 1
    $stmt = $db->prepare("UPDATE assignments SET status = 'active', is_deployed = 1 WHERE id = ?");
    $stmt->execute([$assignment_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Assignment successfully deployed to $deployed_count students",
        'deployed_count' => $deployed_count
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in deploy-assignment: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>