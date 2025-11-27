<?php
require_once 'config.php';

// Check if user is logged in (either student or teacher)
if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $user_id = $_SESSION['user']['id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $success = $stmt->execute([$user_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>