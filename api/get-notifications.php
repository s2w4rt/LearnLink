<?php
require_once '../config.php';

// Check if user is authenticated
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$db = getDB();

try {
    // Get notifications for current user
    $stmt = $db->prepare("
        SELECT id, type, title, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND user_role = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user['id'], $user['role']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $stmt = $db->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = ? AND user_role = ? AND is_read = FALSE
    ");
    $stmt->execute([$user['id'], $user['role']]);
    $unreadCount = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);

} catch (PDOException $e) {
    error_log('Error fetching notifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch notifications']);
}
