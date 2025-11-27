<?php
// api/teacher-subjects.php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0); // don't break JSON with warnings/notices

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
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];

function send_json($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 *  GET  /api/teacher-subjects.php
 *
 *  - Default:   ?teacherId=88
 *      → currently ASSIGNED subjects for this teacher (for the Teacher Subjects list)
 *
 *  - Available: ?mode=available&teacherId=88[&strand=...]
 *      → ALL subjects across ALL strands that are:
 *          • not assigned to any teacher, OR
 *          • assigned only to THIS teacher
 *        + flag is_assigned_to_teacher (0/1)
 *        (used inside the “Assign Subjects” modal)
 */
if ($method === 'GET') {
    if (!isset($_GET['teacherId']) || !ctype_digit($_GET['teacherId'])) {
        send_json(['message' => 'Missing or invalid teacherId'], 400);
    }

    $teacherId = (int)$_GET['teacherId'];
    $mode      = isset($_GET['mode']) ? $_GET['mode'] : '';

    // 1) AVAILABLE SUBJECTS (for Assign-Subjects modal)
    if ($mode === 'available') {
        // NOTE: we do NOT filter by strand here because you said:
        //  “fetch all available subjects in every strand,
        //   do not tie a teacher to one strand”
        //
        // We also exclude subjects already used by OTHER teachers,
        // so each subject can belong to only one teacher at a time.
        $sql = "
            SELECT
                s.id,
                s.subject_code,
                s.subject_name,
                s.strand,
                s.grade_level,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM assigned_subjects asg2
                        WHERE asg2.teacher_id = ?
                          AND asg2.subject_id = s.id
                    )
                    THEN 1
                    ELSE 0
                END AS is_assigned_to_teacher
            FROM subjects s
            WHERE s.is_active = 1
              AND NOT EXISTS (
                  SELECT 1
                  FROM assigned_subjects other
                  WHERE other.subject_id = s.id
                    AND other.teacher_id <> ?
              )
            ORDER BY s.grade_level, s.subject_code
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            send_json(['message' => 'Prepare failed: ' . $conn->error], 500);
        }
        $stmt->bind_param('ii', $teacherId, $teacherId);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $row['is_assigned_to_teacher'] = (int)($row['is_assigned_to_teacher'] ?? 0);
            $rows[] = $row;
        }
        $stmt->close();

        send_json($rows);
    }

    // 2) CURRENTLY ASSIGNED SUBJECTS (for Teacher Subjects list)
    $sql = "
        SELECT 
            asg.id,
            asg.quarter,
            s.subject_code,
            s.subject_name,
            s.grade_level,
            COUNT(DISTINCT ss.student_id) AS student_count,
            COUNT(DISTINCT a.id)          AS assignment_count
        FROM assigned_subjects asg
        INNER JOIN subjects s 
            ON s.id = asg.subject_id
        LEFT JOIN student_subjects ss 
            ON ss.teacher_id = asg.teacher_id
           AND ss.subject_id = asg.subject_id
           AND ss.quarter    = asg.quarter
        LEFT JOIN assignments a
            ON a.teacher_id  = asg.teacher_id
           AND a.subject_id  = asg.subject_id
           AND a.quarter     = asg.quarter
        WHERE asg.teacher_id = ?
        GROUP BY 
            asg.id,
            asg.quarter,
            s.subject_code,
            s.subject_name,
            s.grade_level
        ORDER BY s.grade_level, s.subject_code
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json(['message' => 'Prepare failed: ' . $conn->error], 500);
    }
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['quarter']          = (int)($row['quarter'] ?? 1);
        $row['student_count']    = (int)($row['student_count'] ?? 0);
        $row['assignment_count'] = (int)($row['assignment_count'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();

    send_json($rows);
}

/**
 *  POST /api/teacher-subjects.php
 *  Body JSON: { "teacherId": 88, "subjectIds": [1,2,3] }
 *  → save assigned subjects into assigned_subjects
 */
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        send_json(['message' => 'Invalid JSON body'], 400);
    }

    $teacherId  = isset($data['teacherId']) ? (int)$data['teacherId'] : 0;
    $subjectIds = isset($data['subjectIds']) && is_array($data['subjectIds'])
        ? $data['subjectIds'] : [];

    if (!$teacherId || empty($subjectIds)) {
        send_json(['message' => 'teacherId and subjectIds are required'], 400);
    }

    // For now we assign everything to Quarter 1
    $quarter = 1;

    $conn->begin_transaction();
    try {
        // Clear previous assignments for this teacher
        $del = $conn->prepare("DELETE FROM assigned_subjects WHERE teacher_id = ?");
        $del->bind_param('i', $teacherId);
        $del->execute();
        $del->close();

        $ins = $conn->prepare("
            INSERT INTO assigned_subjects (teacher_id, subject_id, quarter)
            VALUES (?, ?, ?)
        ");
        if (!$ins) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        foreach ($subjectIds as $sid) {
            $sid = (int)$sid;
            if (!$sid) continue;

            $ins->bind_param('iii', $teacherId, $sid, $quarter);
            $ins->execute();
        }
        $ins->close();

        $conn->commit();
        send_json([
            'success'   => true,
            'message'   => 'Subjects assigned to teacher',
            'teacherId' => $teacherId,
            'count'     => count($subjectIds)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json(['message' => 'Failed to assign subjects: ' . $e->getMessage()], 500);
    }
}

/**
 *  DELETE /api/teacher-subjects.php?id=123
 *  → removes a single subject assignment row
 */
if ($method === 'DELETE') {
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
        send_json(['message' => 'Missing or invalid id'], 400);
    }

    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("DELETE FROM assigned_subjects WHERE id = ?");
    if (!$stmt) {
        send_json(['message' => 'Prepare failed: ' . $conn->error], 500);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    send_json([
        'success' => $affected > 0,
        'message' => $affected > 0 ? 'Subject removed from teacher' : 'Nothing to delete'
    ]);
}

// Anything else
send_json(['message' => 'Method not allowed'], 405);
