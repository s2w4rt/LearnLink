<?php
// student-subjects.php
declare(strict_types=1);

// ❗ Important: keep this file saved as UTF-8 **without BOM**
ini_set('display_errors', '0');                  // don't print warnings/notices into JSON
error_reporting(E_ALL);                          // still log errors to PHP error log
header('Content-Type: application/json; charset=utf-8');

// If some other script echoed before us, clear it (avoid "invalid JSON" from stray output)
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
}

require_once __DIR__ . '/config.php';

/** Small helper to send JSON and stop execution */
function send($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit; // ← critical, prevents "Method Not Allowed" or PHP notices from appending
}

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $studentId = isset($_GET['studentId']) ? (int)$_GET['studentId'] : 0;
        if ($studentId <= 0) send(['error' => 'Missing studentId'], 400);

        $sql = "
          SELECT
            ss.id,
            ss.student_id,
            ss.subject_id,
            ss.subject_code,
            ss.subject_name,
            ss.quarter,
            ss.credits,
            ss.teacher_id,
            ss.schedule,
            ss.created_at,
            s.subject_code   AS s_code,
            s.subject_name   AS s_name,
            s.color          AS s_color,
            s.icon           AS s_icon,
            t.name           AS teacher_name
          FROM student_subjects ss
          LEFT JOIN subjects s ON s.id = ss.subject_id
          LEFT JOIN teachers t ON t.id = ss.teacher_id
          WHERE ss.student_id = ?
          ORDER BY COALESCE(s.subject_name, ss.subject_name) ASC, ss.id ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize for UI
        $out = array_map(function ($r) {
            return [
                'id'                    => (int)$r['id'],                     // ← use this for Remove
                'student_id'            => (int)$r['student_id'],
                'subject_id'            => $r['subject_id'] !== null ? (int)$r['subject_id'] : null,
                'subject_code'          => $r['subject_code'],
                'subject_name'          => $r['subject_name'],
                'display_subject_code'  => $r['s_code'] ?: $r['subject_code'],
                'display_subject_name'  => $r['s_name'] ?: $r['subject_name'],
                'quarter'               => (int)$r['quarter'],
                'credits'               => (int)$r['credits'],
                'schedule'              => $r['schedule'],
                'teacher_name'          => $r['teacher_name'],
                'color'                 => $r['s_color'] ?: '#DBEAFE',
                'icon'                  => $r['s_icon']  ?: 'book',
                'created_at'            => $r['created_at'],
            ];
        }, $rows);

        send($out);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // TODO: replace with your real admin/auth gate
        if (function_exists('checkAdminAuth')) { checkAdminAuth(); }

        $payloadRaw = file_get_contents('php://input');
        $payload = json_decode($payloadRaw ?: '[]', true);
        if (!is_array($payload) || !$payload) $payload = $_POST;

        $studentId = (int)($payload['studentId'] ?? 0);
        $quarter   = (int)($payload['quarter']   ?? 1);
        $credits   = (int)($payload['credits']   ?? 1);
        $teacherId = isset($payload['teacherId']) && $payload['teacherId'] !== '' ? (int)$payload['teacherId'] : null;
        $schedule  = trim((string)($payload['schedule'] ?? ''));

        $subjectId   = isset($payload['subjectId']) && $payload['subjectId'] !== '' ? (int)$payload['subjectId'] : null;
        $subjectCode = trim((string)($payload['subjectCode'] ?? ''));
        $subjectName = trim((string)($payload['subjectName'] ?? ''));

        if ($studentId <= 0 || $quarter <= 0 || $credits <= 0) {
            send(['error' => 'Missing required fields'], 400);
        }
        if ($subjectId === null && ($subjectCode === '' || $subjectName === '')) {
            send(['error' => 'Provide subjectId OR subjectCode+subjectName'], 400);
        }

        // If standard subject, fetch code/name
        if ($subjectId !== null) {
            $chk = $db->prepare("SELECT subject_code, subject_name FROM subjects WHERE id = ?");
            $chk->execute([$subjectId]);
            $s = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$s) send(['error' => 'Invalid subjectId'], 400);
            $subjectCode = $s['subject_code'];
            $subjectName = $s['subject_name'];
        }

        $ins = $db->prepare("
            INSERT INTO student_subjects
              (student_id, subject_id, subject_code, subject_name, quarter, credits, teacher_id, schedule)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $studentId,
            $subjectId,
            $subjectCode !== '' ? $subjectCode : null,
            $subjectName !== '' ? $subjectName : null,
            $quarter,
            $credits,
            $teacherId,
            $schedule !== '' ? $schedule : null,
        ]);

        send(['ok' => true, 'id' => (int)$db->lastInsertId()], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        if (function_exists('checkAdminAuth')) { checkAdminAuth(); }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) send(['error' => 'Missing id'], 400);

        $del = $db->prepare("DELETE FROM student_subjects WHERE id = ?");
        $del->execute([$id]);

        send(['ok' => true]);
    }

    // Any other method:
    send(['error' => 'Method Not Allowed'], 405);

} catch (Throwable $e) {
    // Log details; return a safe JSON error
    error_log('student-subjects.php error: ' . $e->getMessage());
    send(['error' => 'Server error'], 500);
}
