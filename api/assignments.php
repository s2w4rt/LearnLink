<?php
// api/assignments.php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(bool $success, $data = null, string $message = '') {
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ]);
    exit;
}

// Ensure $conn is available
global $conn;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'allshs_elms');
    if ($conn->connect_error) {
        http_response_code(500);
        jsonResponse(false, null, 'Database connection failed: ' . $conn->connect_error);
    }
}
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];

/* =========== CREATE ASSIGNMENT – POST =========== */
if ($method === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $quarter      = (int)($_POST['quarter'] ?? 0);
    $type         = trim($_POST['type'] ?? '');
    $strand       = trim($_POST['strand'] ?? '');
    $teacherId    = (int)($_POST['teacherId'] ?? 0);

    // subject is now expected to be a numeric subjects.id coming from the form
    $subjectId    = isset($_POST['subject']) ? (int)$_POST['subject'] : 0;

    // 🔍 Try to ensure $subjectId is a valid subjects.id.
    // If it's not, it might actually be an assigned_subjects.id, so we map it.
    if ($subjectId > 0) {
        // 1) Check if such subject exists directly in subjects
        $stmtCheckSub = $conn->prepare("SELECT id FROM subjects WHERE id = ? LIMIT 1");
        if ($stmtCheckSub) {
            $stmtCheckSub->bind_param('i', $subjectId);
            $stmtCheckSub->execute();
            $stmtCheckSub->store_result();

            if ($stmtCheckSub->num_rows === 0) {
                // 2) Maybe the posted value is an assigned_subjects.id
                $stmtMap = $conn->prepare("SELECT subject_id FROM assigned_subjects WHERE id = ? LIMIT 1");
                if ($stmtMap) {
                    $stmtMap->bind_param('i', $subjectId);
                    $stmtMap->execute();
                    $resMap = $stmtMap->get_result();
                    if ($rowMap = $resMap->fetch_assoc()) {
                        // ✅ use the real subjects.id
                        $subjectId = (int)$rowMap['subject_id'];
                    } else {
                        // not found anywhere, treat as invalid
                        $subjectId = 0;
                    }
                    $stmtMap->close();
                } else {
                    // can't prepare mapping statement; treat as invalid
                    $subjectId = 0;
                }
            }

            $stmtCheckSub->close();
        }
    }

    $dueDate      = trim($_POST['dueDate'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $maxScore     = (int)($_POST['maxScore'] ?? 0);
    $weight       = isset($_POST['weight']) && $_POST['weight'] !== ''
        ? (float)$_POST['weight']
        : 100.0;

    $distributionType = trim($_POST['distributionType'] ?? 'teacher'); // from JS
    $status           = trim($_POST['status'] ?? 'active');            // from JS

    // ✅ Basic validation (now using the corrected $subjectId)
    if (
        $title === '' || !$quarter || $type === '' || $strand === '' ||
        !$teacherId || $subjectId <= 0 || $dueDate === '' ||
        $instructions === '' || !$maxScore
    ) {
        http_response_code(400);
        jsonResponse(false, null, 'Missing required fields.');
    }

    // Handle assignment file (optional)
    $fileUrl = null;

    // Detect which file field name was used in the form
    $fileField = null;
    if (isset($_FILES['file'])) {
        $fileField = 'file';
    } elseif (isset($_FILES['assignmentFile'])) {
        // if your input is name="assignmentFile"
        $fileField = 'assignmentFile';
    } elseif (isset($_FILES['assignment_file'])) {
        // just in case you used this style anywhere
        $fileField = 'assignment_file';
    }

    if ($fileField !== null) {
        $file = $_FILES[$fileField];

        // No file selected
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            // leave $fileUrl = null; (assignment can still be created without a file)
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            // Some upload error from PHP
            http_response_code(400);
            jsonResponse(false, null, 'Error uploading assignment file (code ' . $file['error'] . ').');
        } else {
            // Max 200MB (same as label)
            if ($file['size'] > 200 * 1024 * 1024) {
                http_response_code(400);
                jsonResponse(false, null, 'Assignment file exceeds 200MB limit.');
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['pdf','doc','docx','ppt','pptx','txt','zip','mp4','mp3','jpg','jpeg','png'];
            if (!in_array($ext, $allowedExt, true)) {
                http_response_code(400);
                jsonResponse(false, null, 'Unsupported assignment file type.');
            }

            // Use project root/assignments directory
            $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assignments' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    http_response_code(500);
                    jsonResponse(false, null, 'Failed to create assignments directory.');
                }
            }

            $fileName   = 'ass_' . uniqid('', true) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            // First try the normal safe move
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Fallback: try rename (sometimes needed on Windows/XAMPP)
                if (!@rename($file['tmp_name'], $targetPath)) {
                    http_response_code(500);
                    jsonResponse(false, null, 'Failed to move assignment file.');
                }
            }

            // This is what will be stored in DB and used by frontend
            $fileUrl = '/assignments/' . $fileName;
        }
    }

    // school_year is nullable; you can set a value if you want
    $schoolYear = null;

    $stmt = $conn->prepare("
        INSERT INTO assignments
            (title, type, instructions, strand, quarter,
             teacher_id, due_date, max_score, weight,
             file_url, distribution_type, status, subject_id, school_year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        http_response_code(500);
        jsonResponse(false, null, 'Prepare failed: ' . $conn->error);
    }

    // 14 params: ss s s i i s i d s s s i s → "ssssiisidsssis"
    $stmt->bind_param(
        'ssssiisidsssis',
        $title,
        $type,
        $instructions,
        $strand,
        $quarter,
        $teacherId,
        $dueDate,
        $maxScore,
        $weight,
        $fileUrl,
        $distributionType,
        $status,
        $subjectId,
        $schoolYear
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        jsonResponse(false, null, 'Insert failed: ' . $stmt->error);
    }

    $insertId = $stmt->insert_id;
    $stmt->close();

    // Fetch with teacher_name for consistency with frontend
    $sql = "
        SELECT a.*, t.name AS teacher_name
        FROM assignments a
        LEFT JOIN teachers t ON t.id = a.teacher_id
        WHERE a.id = {$insertId}
        LIMIT 1
    ";
    $res = $conn->query($sql);
    $assignment = $res ? $res->fetch_assoc() : null;

    jsonResponse(true, $assignment, 'Assignment created.');
}

/* =========== READ – LIST / GET ONE =========== */
if ($method === 'GET') {
    // Single assignment (used before delete)
    if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
        $id = (int)$_GET['id'];

        $sql = "
            SELECT a.*, t.name AS teacher_name
            FROM assignments a
            LEFT JOIN teachers t ON t.id = a.teacher_id
            WHERE a.id = {$id}
            LIMIT 1
        ";
        $res = $conn->query($sql);
        if (!$res) {
            http_response_code(500);
            jsonResponse(false, null, 'Query failed: ' . $conn->error);
        }
        $assignment = $res->fetch_assoc();
        jsonResponse(true, $assignment, '');
    }

    // List with filters
    $where = '1=1';

    if (!empty($_GET['quarter']) && $_GET['quarter'] !== 'ALL') {
        $q = (int)$_GET['quarter'];
        $where .= ' AND a.quarter = ' . $q;
    }
    if (!empty($_GET['strand']) && $_GET['strand'] !== 'ALL') {
        $strand = $conn->real_escape_string($_GET['strand']);
        $where .= " AND a.strand = '{$strand}'";
    }
    if (!empty($_GET['status']) && $_GET['status'] !== 'ALL') {
        $status = $conn->real_escape_string($_GET['status']);
        $where .= " AND a.status = '{$status}'";
    }

    $sql = "
        SELECT a.*, t.name AS teacher_name
        FROM assignments a
        LEFT JOIN teachers t ON t.id = a.teacher_id
        WHERE {$where}
        ORDER BY a.due_date ASC, a.created_at DESC
    ";

    $res = $conn->query($sql);
    if (!$res) {
        http_response_code(500);
        jsonResponse(false, null, 'Query failed: ' . $conn->error);
    }

    $assignments = [];
    while ($row = $res->fetch_assoc()) {
        $assignments[] = $row;
    }

    jsonResponse(true, $assignments, '');
}

/* =========== DELETE =========== */
if ($method === 'DELETE') {
    if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
        http_response_code(400);
        jsonResponse(false, null, 'Missing or invalid id.');
    }

    $id = (int)$_GET['id'];

    // Optional: delete assignment file from disk
    $sql = "SELECT file_url FROM assignments WHERE id = {$id} LIMIT 1";
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        if ($row && $row['file_url'] && str_starts_with($row['file_url'], '/assignments/')) {
            $path = dirname(__DIR__) . $row['file_url'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        http_response_code(500);
        jsonResponse(false, null, 'Delete failed: ' . $stmt->error);
    }
    $stmt->close();

    jsonResponse(true, null, 'Assignment deleted.');
}

/* =========== FALLBACK =========== */
http_response_code(405);
jsonResponse(false, null, 'Method not allowed.');
