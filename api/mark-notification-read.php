<?php
require_once '../config.php';

// Check if user is authenticated
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;

if ($notification_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

try {
    // Mark as read only if it belongs to the current user
    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE id = ? AND user_id = ? AND user_role = ?
    ");
    $stmt->execute([$notification_id, $user['id'], $user['role']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
    }

} catch (PDOException $e) {
    error_log('Error marking notification as read: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update notification']);
}
