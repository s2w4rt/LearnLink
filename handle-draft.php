<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';
$assignment_id = intval($_POST['assignment_id'] ?? 0);

if ($action === 'save_draft') {
    $draft_files = json_decode($_POST['draft_files'] ?? '[]', true);
    
    // Initialize draft files session array if not exists
    if (!isset($_SESSION['draft_files'])) {
        $_SESSION['draft_files'] = [];
    }
    
    // Validate and sanitize draft files data
    $sanitized_files = [];
    foreach ($draft_files as $file) {
        $sanitized_files[] = [
            'name' => htmlspecialchars($file['name'] ?? ''),
            'size' => intval($file['size'] ?? 0),
            // Note: We can't store the actual File object in session, just metadata
        ];
    }
    
    // Save draft files for this assignment
    $_SESSION['draft_files'][$assignment_id] = $sanitized_files;
    
    error_log("Draft saved for assignment $assignment_id: " . json_encode($sanitized_files));
    
    echo json_encode([
        'success' => true, 
        'message' => 'Draft saved successfully',
        'saved_files' => $sanitized_files
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>