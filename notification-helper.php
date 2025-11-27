<?php
require_once 'config.php';

function createNotification($userId, $title, $message, $type = 'system', $assignmentId = null) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, assignment_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId, 
            $title, 
            $message, 
            $type, 
            $assignmentId
        ]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

// Function to notify all students in a strand about a new assignment
function notifyStudentsAboutAssignment($assignmentId, $assignmentTitle, $strand, $gradeLevel = 11) {
    $db = getDB();
    
    try {
        // Get all students in the strand and grade level
        $stmt = $db->prepare("
            SELECT id FROM students 
            WHERE strand = ? AND grade_level = ? AND status = 'active'
        ");
        $stmt->execute([$strand, $gradeLevel]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notificationCount = 0;
        foreach ($students as $studentId) {
            $success = createNotification(
                $studentId,
                "New Assignment Available",
                "A new assignment '{$assignmentTitle}' has been deployed. Check your assignments page.",
                'assignment',
                $assignmentId
            );
            
            if ($success) $notificationCount++;
        }
        
        return $notificationCount;
    } catch (PDOException $e) {
        error_log("Bulk notification failed: " . $e->getMessage());
        return false;
    }
}

// Function to notify a student about grading
function notifyStudentAboutGrading($studentId, $assignmentTitle, $score, $maxScore) {
    return createNotification(
        $studentId,
        "Assignment Graded",
        "Your assignment '{$assignmentTitle}' has been graded. Score: {$score}/{$maxScore}",
        'grading',
        null
    );
}

// Function to get unread notification count for a user
function getUnreadNotificationCount($userId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Unread count error: " . $e->getMessage());
        return 0;
    }
}
?>