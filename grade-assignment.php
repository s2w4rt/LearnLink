<?php
// grade-assignment.php
session_start();
require_once 'config.php';
require_once 'notification-helper.php'; // Add this line

header('Content-Type: application/json');

// Check if user is authenticated and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

try {
    // Get POST data
    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    $score = isset($_POST['score']) ? floatval($_POST['score']) : null;
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

    if ($submission_id <= 0) {
        throw new Exception('Invalid submission ID');
    }

    // Get submission details to verify teacher access
    $stmt = $db->prepare("
        SELECT sa.*, a.teacher_id, a.max_score, a.title as assignment_title, sa.student_id
        FROM student_assignments sa
        JOIN assignments a ON sa.assignment_id = a.id
        WHERE sa.id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        throw new Exception('Submission not found');
    }

    // Check if teacher owns this assignment
    if ($submission['teacher_id'] != $user['id']) {
        throw new Exception('You are not authorized to grade this submission');
    }

    // Validate score
    if ($score !== null && ($score < 0 || $score > $submission['max_score'])) {
        throw new Exception('Score must be between 0 and ' . $submission['max_score']);
    }

    // Update submission with grade
    $stmt = $db->prepare("
        UPDATE student_assignments 
        SET score = ?, feedback = ?, status = 'graded', updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $score,
        $feedback,
        $submission_id
    ]);

    if ($result) {
    // SEND NOTIFICATION TO STUDENT ABOUT GRADING
    $notificationSuccess = notifyStudentAboutGrading(
        $submission['student_id'],
        $submission['assignment_title'],
        $score,
        $submission['max_score']
    );

    $message = 'Grade submitted successfully';
    if ($notificationSuccess) {
        $message .= ' and student has been notified';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'score' => $score,
        'max_score' => $submission['max_score'],
        'student_notified' => $notificationSuccess
    ]);
} else {
    throw new Exception('Failed to update grade in database');
}

} catch (Exception $e) {
    error_log('Grading error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>