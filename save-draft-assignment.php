<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

$assignment_id = $_POST['assignment_id'] ?? 0;
$student_id = $_SESSION['user']['id'];

try {
    $db = getDB();
    
    // Handle file uploads
    $uploaded_files = [];
    if (!empty($_FILES['files'])) {
        $upload_dir = 'uploads/assignments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $original_name = $_FILES['files']['name'][$key];
                $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $uploaded_files[] = [
                        'name' => $original_name,
                        'path' => $file_path,
                        'size' => $_FILES['files']['size'][$key]
                    ];
                }
            }
        }
    }
    
    // Check if submission already exists
    $stmt = $db->prepare("SELECT id FROM student_assignments WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignment_id, $student_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing submission
        $stmt = $db->prepare("
            UPDATE student_assignments 
            SET file_path = ?, original_file_name = ?, file_size = ?, status = 'draft', submitted_at = NOW()
            WHERE id = ?
        ");
        $file_data = json_encode($uploaded_files);
        $original_names = implode(', ', array_column($uploaded_files, 'name'));
        $total_size = array_sum(array_column($uploaded_files, 'size'));
        
        $stmt->execute([$file_data, $original_names, $total_size, $existing['id']]);
    } else {
        // Create new submission
        $stmt = $db->prepare("
            INSERT INTO student_assignments (assignment_id, student_id, file_path, original_file_name, file_size, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, 'draft', NOW())
        ");
        $file_data = json_encode($uploaded_files);
        $original_names = implode(', ', array_column($uploaded_files, 'name'));
        $total_size = array_sum(array_column($uploaded_files, 'size'));
        
        $stmt->execute([$assignment_id, $student_id, $file_data, $original_names, $total_size]);
    }
    
    header('Location: view-detail.php?type=assignment&id=' . $assignment_id . '&subject_id=' . ($_POST['subject_id'] ?? 0));
    exit;
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    header('Location: view-detail.php?type=assignment&id=' . $assignment_id . '&subject_id=' . ($_POST['subject_id'] ?? 0) . '&error=1');
    exit;
}
?>