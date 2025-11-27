<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch($method) {
        case 'GET':
            // Get all teachers or specific teacher
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($teacher) {
                    // Remove password hash from response
                    unset($teacher['password_hash']);
                    sendJsonResponse($teacher);
                } else {
                    sendJsonResponse(['error' => 'Teacher not found'], 404);
                }
            } else {
                $strandFilter = isset($_GET['strand']) && $_GET['strand'] !== 'ALL' ? $_GET['strand'] : null;
                
                if ($strandFilter) {
                    $stmt = $db->prepare("SELECT id, name, email, strand, username, created_at FROM teachers WHERE strand = ? ORDER BY name");
                    $stmt->execute([$strandFilter]);
                } else {
                    $stmt = $db->prepare("SELECT id, name, email, strand, username, created_at FROM teachers ORDER BY name");
                    $stmt->execute();
                }
                
                $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse($teachers);
            }
            break;
            
        case 'POST':
            // Create new teacher
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['name']) || !isset($input['strand']) || !isset($input['username']) || !isset($input['password'])) {
                sendJsonResponse(['error' => 'Missing required fields'], 400);
            }
            
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM teachers WHERE username = ?");
            $stmt->execute([$input['username']]);
            if ($stmt->fetch()) {
                sendJsonResponse(['error' => 'Username already exists'], 400);
            }
            
            $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO teachers (name, email, strand, username, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['name'],
                $input['email'] ?? '',
                $input['strand'],
                $input['username'],
                $passwordHash
            ]);
            
            $teacherId = $db->lastInsertId();
            
            // Return the created teacher (without password)
            $stmt = $db->prepare("SELECT id, name, email, strand, username, created_at FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $newTeacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendJsonResponse(['message' => 'Teacher created successfully', 'teacher' => $newTeacher], 201);
            break;
            
        case 'PUT':
            // Update teacher
            $input = json_decode(file_get_contents('php://input'), true);
            $teacherId = $_GET['id'] ?? null;
            
            if (!$teacherId) {
                sendJsonResponse(['error' => 'Teacher ID required'], 400);
            }
            
            // Build update query dynamically based on provided fields
            $updateFields = [];
            $params = [];
            
            if (isset($input['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $input['name'];
            }
            
            if (isset($input['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $input['email'];
            }
            
            if (isset($input['strand'])) {
                $updateFields[] = "strand = ?";
                $params[] = $input['strand'];
            }
            
            if (isset($input['username'])) {
                // Check if new username is not taken by other teachers
                $stmt = $db->prepare("SELECT id FROM teachers WHERE username = ? AND id != ?");
                $stmt->execute([$input['username'], $teacherId]);
                if ($stmt->fetch()) {
                    sendJsonResponse(['error' => 'Username already taken'], 400);
                }
                
                $updateFields[] = "username = ?";
                $params[] = $input['username'];
            }
            
            if (isset($input['password']) && !empty($input['password'])) {
                $updateFields[] = "password_hash = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                sendJsonResponse(['error' => 'No fields to update'], 400);
            }
            
            $params[] = $teacherId;
            
            $sql = "UPDATE teachers SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            sendJsonResponse(['message' => 'Teacher updated successfully']);
            break;
            
        case 'DELETE':
            // Delete teacher
            $teacherId = $_GET['id'] ?? null;
            
            if (!$teacherId) {
                sendJsonResponse(['error' => 'Teacher ID required'], 400);
            }
            
            // Check if teacher exists
            $stmt = $db->prepare("SELECT name FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();
            
            if (!$teacher) {
                sendJsonResponse(['error' => 'Teacher not found'], 404);
            }
            
            // Delete the teacher
            $stmt = $db->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            
            sendJsonResponse(['message' => 'Teacher deleted successfully: ' . $teacher['name']]);
            break;
            
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch(PDOException $e) {
    error_log("Teachers API Error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch(Exception $e) {
    sendJsonResponse(['error' => $e->getMessage()], 500);
}


?>