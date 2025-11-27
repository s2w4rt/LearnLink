<?php
// submit_support_simple.php - Simplified version
session_start();
header('Content-Type: application/json');

// Simple test - remove this after testing
if (isset($_POST['test'])) {
    echo json_encode(['success' => true, 'message' => 'PHP is working!']);
    exit();
}

try {
    // Very basic database connection
    $pdo = new PDO('mysql:host=localhost;dbname=allshs_elms;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ATTR_ERRMODE_EXCEPTION);
    
    // Get form data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $issue_type = $_POST['issue_type'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if (empty($name) || empty($email) || empty($issue_type) || empty($message)) {
        throw new Exception('All fields are required');
    }
    
    $ticket_number = 'TKT' . date('YmdHis') . rand(100, 999);
    
    // Simple insert
    $stmt = $pdo->prepare("
        INSERT INTO support_tickets 
        (ticket_number, name, email, issue_type, message, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([$ticket_number, $name, $email, $issue_type, $message]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Ticket submitted! Number: $ticket_number"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>