<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY created_at DESC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>