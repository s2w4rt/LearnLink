<?php
// learning-teachers.php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['message' => 'Database connection failed']);
    exit;
}

// We only want teachers that have at least one assigned_subjects row
$sql = "
    SELECT DISTINCT t.id, t.name
    FROM teachers t
    INNER JOIN assigned_subjects a ON a.teacher_id = t.id
    ORDER BY t.name ASC
";

$result = $conn->query($sql);

$teachers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = [
            'id'   => (int)$row['id'],
            'name' => $row['name'],
        ];
    }
}

echo json_encode($teachers);
