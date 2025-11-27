<?php
session_start();
require_once 'config.php';

// Check if user is authenticated and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit;
}

try {
    $db = getDB();
    
    // Check if assignment is already deployed
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM student_assignments 
        WHERE assignment_id = ?
    ");
    $stmt->execute([$assignment_id]);
    $existing_deployments = $stmt->fetchColumn();
    
    $response = [
        'already_deployed' => $existing_deployments > 0,
        'total_students' => $existing_deployments
    ];
    
    // Get submission details if already deployed
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
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count submissions by status
        $submitted_count = 0;
        foreach ($submissions as $submission) {
            if (in_array($submission['status'], ['submitted', 'graded', 'late'])) {
                $submitted_count++;
            }
        }
        
        $response['submitted_count'] = $submitted_count;
        $response['submissions'] = $submissions;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Database error in check-deployment: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>