<?php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];
$db = getDB();

try {
    // Get unread notifications count - FIXED: Removed user_role filter since students don't have role in session
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unread_count = (int)$stmt->fetchColumn();

    // Get recent notifications with proper formatting - FIXED query
    $stmt = $db->prepare("
        SELECT 
            n.id,
            n.title,
            n.message,
            n.type,
            n.link,
            n.is_read,
            n.created_at,
            DATE_FORMAT(n.created_at, '%b %d, %Y %h:%i %p') as time,
            n.assignment_id
        FROM notifications n
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error loading notifications: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'unread_count' => 0,
        'notifications' => []
    ]);
}
?>