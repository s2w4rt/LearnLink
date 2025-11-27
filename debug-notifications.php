<?php
// debug-notifications.php
require_once 'config.php';
checkStudentAuth();

$user = $_SESSION['user'];
echo "<h2>Debug Info for Student ID: " . $user['id'] . "</h2>";

$db = getDB();

// Test the query
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_role = 'student' ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Raw Database Results:</h3>";
echo "<pre>";
print_r($notifications);
echo "</pre>";

// Test the API endpoint
echo "<h3>API Response:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/get-student-notifications.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$result = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($result, true));
echo "</pre>";
?>