<?php
// api/subjects.php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// Use $conn from config.php; fallback if needed
global $conn;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'allshs_elms');
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['message' => 'Database connection failed']);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

function send_json($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($method !== 'GET') {
    send_json(['message' => 'Method not allowed'], 405);
}

// Optional filters
$strand     = isset($_GET['strand']) && $_GET['strand'] !== '' ? trim($_GET['strand']) : null;
$gradeLevel = isset($_GET['gradeLevel']) && $_GET['gradeLevel'] !== '' ? trim($_GET['gradeLevel']) : null;
$teacherId  = (isset($_GET['teacherId']) && ctype_digit($_GET['teacherId']))
    ? (int)$_GET['teacherId']
    : 0;

// If teacherId is provided → exclude subjects assigned to other teachers
if ($teacherId > 0) {
    $sql = "
        SELECT 
            s.id,
            s.subject_code,
            s.subject_name,
            s.grade_level,
            s.strand,
            -- how many times this subject is assigned to other teachers
            SUM(
                CASE 
                    WHEN asg.teacher_id IS NOT NULL 
                         AND asg.teacher_id <> ? 
                    THEN 1 
                    ELSE 0 
                END
            ) AS assigned_to_other
        FROM subjects s
        LEFT JOIN assigned_subjects asg
            ON asg.subject_id = s.id
    ";

    $params = [$teacherId];
    $types  = 'i';

    $where = [];
    if ($strand !== null) {
        $where[]  = 's.strand = ?';
        $params[] = $strand;
        $types   .= 's';
    }
    if ($gradeLevel !== null) {
        $where[]  = 's.grade_level = ?';
        $params[] = $gradeLevel;
        $types   .= 's';
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= "
        GROUP BY
            s.id,
            s.subject_code,
            s.subject_name,
            s.grade_level,
            s.strand
        HAVING assigned_to_other = 0
        ORDER BY s.grade_level, s.subject_code
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json(['message' => 'Prepare failed: ' . $conn->error], 500);
    }

    $stmt->bind_param($types, ...$params);
} else {
    // No teacherId → just return all subjects matching strand / grade
    $sql = "
        SELECT 
            id,
            subject_code,
            subject_name,
            grade_level,
            strand
        FROM subjects
        WHERE 1=1
    ";

    $params = [];
    $types  = '';

    if ($strand !== null) {
        $sql     .= ' AND strand = ?';
        $params[] = $strand;
        $types   .= 's';
    }
    if ($gradeLevel !== null) {
        $sql     .= ' AND grade_level = ?';
        $params[] = $gradeLevel;
        $types   .= 's';
    }

    $sql .= ' ORDER BY grade_level, subject_code';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json(['message' => 'Prepare failed: ' . $conn->error], 500);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
}

$stmt->execute();
$res = $stmt->get_result();
$rows = [];

while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'id'           => (int)$row['id'],
        'subject_code' => $row['subject_code'],
        'subject_name' => $row['subject_name'],
        'grade_level'  => $row['grade_level'],
        'strand'       => $row['strand'],
    ];
}

$stmt->close();

send_json($rows);
