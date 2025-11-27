<?php
require_once '../config.php';
checkStudentAuth();

$db = getDB();
$user = $_SESSION['user'];

try {
    $where = ["status = 'published'"];
    $params = [];
    
    // Get student's strand
    $stmt = $db->prepare("SELECT strand FROM students WHERE id = ?");
    $stmt->execute([$user['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        sendJsonResponse(['error' => 'Student not found'], 404);
    }
    
    $where[] = "(strand = ? OR grade_level = 'ALL')";
    $params[] = $student['strand'];
    
    if (isset($_GET['quarter']) && $_GET['quarter'] !== 'ALL') {
        $where[] = "quarter = ?";
        $params[] = $_GET['quarter'];
    }
    
    if (isset($_GET['type']) && $_GET['type'] !== 'ALL') {
        $where[] = "type = ?";
        $params[] = $_GET['type'];
    }
    
    $sql = "SELECT * FROM learning_materials";
    if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY quarter, created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendJsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch(PDOException $e) {
    sendJsonResponse(['error' => $e->getMessage()], 500);
}
?>