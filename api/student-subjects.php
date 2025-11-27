<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch($method) {
        case 'GET':
            if (isset($_GET['studentId'])) {
                $studentId = $_GET['studentId'];
                
                $query = "
                    SELECT ss.*, 
                           s.subject_code as display_subject_code,
                           s.subject_name as display_subject_name,
                           s.color,
                           s.icon,
                           t.name as teacher_name
                    FROM student_subjects ss
                    LEFT JOIN subjects s ON ss.subject_id = s.id
                    LEFT JOIN teachers t ON ss.teacher_id = t.id
                    WHERE ss.student_id = ?
                    ORDER BY ss.quarter, ss.created_at DESC
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$studentId]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($subjects);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Student ID is required']);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['studentId']) || !isset($input['quarter'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }
            
            // Check if using existing subject or custom subject
            if (isset($input['subjectId']) && !empty($input['subjectId'])) {
                // Using existing subject
                $query = "INSERT INTO student_subjects (student_id, subject_id, quarter, credits, teacher_id, schedule) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $input['studentId'],
                    $input['subjectId'],
                    $input['quarter'],
                    $input['credits'] ?? 1,
                    $input['teacherId'] ?? null,
                    $input['schedule'] ?? ''
                ]);
            } else {
                // Using custom subject
                if (!isset($input['subjectCode']) || !isset($input['subjectName'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Subject code and name are required for custom subjects']);
                    break;
                }
                
                $query = "INSERT INTO student_subjects (student_id, subject_code, subject_name, quarter, credits, teacher_id, schedule) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $input['studentId'],
                    $input['subjectCode'],
                    $input['subjectName'],
                    $input['quarter'],
                    $input['credits'] ?? 1,
                    $input['teacherId'] ?? null,
                    $input['schedule'] ?? ''
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Subject added successfully']);
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                $query = "DELETE FROM student_subjects WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_GET['id']]);
                
                echo json_encode(['success' => true, 'message' => 'Subject removed successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Subject ID is required']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>