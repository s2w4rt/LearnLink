<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
checkAdminAuth();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    switch ($method) {
        // ========================
        // LIST / GET SINGLE
        // ========================
        case 'GET':
            // If specific student id is provided
            if (isset($_GET['id'])) {
                $stmt = $db->prepare('SELECT * FROM students WHERE id = ?');
                $stmt->execute([$_GET['id']]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$student) {
                    sendJsonResponse(['error' => 'Student not found'], 404);
                }
                sendJsonResponse($student);
            }

            // List all students (dashboard + table)
            $stmt = $db->query('SELECT * FROM students ORDER BY created_at DESC');
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse($students);
            break;

        // ========================
        // CREATE
        // ========================
        case 'POST':
            $input = getJsonInput();

            $studentId  = trim($input['studentId']  ?? '');
            $firstName  = trim($input['firstName']  ?? '');
            $middleName = trim($input['middleName'] ?? '');
            $lastName   = trim($input['lastName']   ?? '');
            $fullName   = trim(
                $input['fullName'] ??
                ($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName)
            );
            $gradeLevel = (int)($input['gradeLevel'] ?? 0);
            $section    = trim($input['section']     ?? '');
            $strand     = $input['strand']           ?? '';
            $username   = trim($input['username']    ?? '');
            $password   = $input['password']         ?? '';
            $email      = trim($input['email']       ?? '');

            if ($studentId === '' || $fullName === '' || !$gradeLevel ||
                $strand === '' || $username === '' || $password === '' || $email === '') {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Missing required fields.'
                ], 400);
            }

            // Enforce unique username
            $check = $db->prepare('SELECT id FROM students WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Username already taken.'
                ], 409);
            }

            $passwordHash = hashPassword($password);

            $stmt = $db->prepare('
                INSERT INTO students (
                    student_id, full_name, grade_level, section, strand, username, password_hash, email
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $studentId,
                $fullName,
                $gradeLevel,
                $section,
                $strand,
                $username,
                $passwordHash,
                $email
            ]);

            $id = $db->lastInsertId();
            $stmt = $db->prepare('SELECT * FROM students WHERE id = ?');
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'success' => true,
                'student' => $student
            ]);
            break;

        // ========================
        // UPDATE
        // ========================
        case 'PUT':
        case 'PATCH':
            if (!isset($_GET['id'])) {
                sendJsonResponse(['success' => false, 'message' => 'Missing student id'], 400);
            }
            $id    = (int)$_GET['id'];
            $input = getJsonInput();

            $studentId  = trim($input['studentId']  ?? '');
            $firstName  = trim($input['firstName']  ?? '');
            $middleName = trim($input['middleName'] ?? '');
            $lastName   = trim($input['lastName']   ?? '');
            $fullName   = trim(
                $input['fullName'] ??
                ($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName)
            );
            $gradeLevel = isset($input['gradeLevel']) ? (int)$input['gradeLevel'] : null;
            $section    = array_key_exists('section', $input) ? trim($input['section']) : null;
            $strand     = $input['strand']   ?? null;
            $username   = array_key_exists('username', $input) ? trim($input['username']) : null;
            $password   = $input['password'] ?? '';
            $email      = array_key_exists('email', $input) ? trim($input['email']) : null;

            $fields = [];
            $params = [];

            if ($studentId !== '') {
                $fields[] = 'student_id = ?';
                $params[] = $studentId;
            }
            if ($fullName !== '') {
                $fields[] = 'full_name = ?';
                $params[] = $fullName;
            }
            if ($gradeLevel !== null) {
                $fields[] = 'grade_level = ?';
                $params[] = $gradeLevel;
            }
            if ($section !== null) {
                $fields[] = 'section = ?';
                $params[] = $section;
            }
            if ($strand !== null) {
                $fields[] = 'strand = ?';
                $params[] = $strand;
            }
            if ($email !== null) {
                $fields[] = 'email = ?';
                $params[] = $email;
            }
            if ($username !== null && $username !== '') {
                // Check username is unique for other students
                $check = $db->prepare('SELECT id FROM students WHERE username = ? AND id != ?');
                $check->execute([$username, $id]);
                if ($check->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Username already taken.'
                    ], 409);
                }
                $fields[] = 'username = ?';
                $params[] = $username;
            }
            if ($password !== '') {
                $fields[] = 'password_hash = ?';
                $params[] = hashPassword($password);
            }

            if (empty($fields)) {
                sendJsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }

            $params[] = $id;
            $sql = 'UPDATE students SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $stmt = $db->prepare('SELECT * FROM students WHERE id = ?');
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'success' => true,
                'student' => $student
            ]);
            break;

        // ========================
        // DELETE
        // ========================
        case 'DELETE':
            if (!isset($_GET['id'])) {
                sendJsonResponse(['success' => false, 'message' => 'Missing student id'], 400);
            }
            $id = (int)$_GET['id'];

            $stmt = $db->prepare('DELETE FROM students WHERE id = ?');
            $stmt->execute([$id]);

            sendJsonResponse(['success' => true]);
            break;

        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    sendJsonResponse([
        'error'   => 'Server error',
        'details' => $e->getMessage()
    ], 500);
}
