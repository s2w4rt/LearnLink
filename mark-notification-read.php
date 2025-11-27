<?php
require_once 'config.php';

// Check if user is logged in (either student or teacher)
if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = $_POST['notification_id'] ?? null;
    $user_id = $_SESSION['user']['id'];
    
    if ($notification_id) {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $success = $stmt->execute([$notification_id, $user_id]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>