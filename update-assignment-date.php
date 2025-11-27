<?php
session_start();
require_once 'config.php';

// Check if user is authenticated as teacher
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

header('Content-Type: application/json');

try {
    // Get POST data
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';

    // Validate input
    if ($assignment_id <= 0 || empty($due_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }

    // Check if assignment belongs to teacher
    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$assignment_id, $user['id']]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit;
    }

    // Update due date
    $updateStmt = $db->prepare("UPDATE assignments SET due_date = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$due_date, $assignment_id]);

    // Format date for display
    $formatted_date = date('M j, Y g:i A', strtotime($due_date));

    echo json_encode([
        'success' => true, 
        'message' => 'Due date updated successfully!',
        'formattedDate' => $formatted_date
    ]);

} catch (PDOException $e) {
    error_log('Database error in update-assignment-date.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
} catch (Exception $e) {
    error_log('General error in update-assignment-date.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>