<?php
// api/send-ticket-reply.php

require_once __DIR__ . '/../config.php'; // Include config file with EmailJS setup

$data = json_decode(file_get_contents('php://input'), true);

// Validate input data
if (empty($data['ticket_id']) || empty($data['student_email']) || empty($data['reply_message'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$ticketId = $data['ticket_id'];
$studentEmail = $data['student_email'];
$replyMessage = $data['reply_message'];

// Prepare and send the email via EmailJS
$emailJsResponse = sendReplyEmail($studentEmail, $replyMessage);

if ($emailJsResponse['success']) {
    // Update the ticket status to "resolved"
    $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'resolved', admin_notes = :notes WHERE id = :ticket_id");
    $stmt->execute(['ticket_id' => $ticketId, 'notes' => $replyMessage]);

    echo json_encode(['success' => true, 'message' => 'Reply sent and ticket updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
}

// Function to send the email using EmailJS (similar to OTP)
function sendReplyEmail($toEmail, $message) {
    $templateParams = [
        'to_email' => $toEmail,
        'message'  => $message
    ];

    // EmailJS credentials
    $serviceId = 'your_service_id';
    $templateId = 'your_template_id';
    $userId = 'your_user_id';

    // Initialize cURL request to EmailJS
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'service_id'   => $serviceId,
        'template_id'  => $templateId,
        'user_id'      => $userId,
        'template_params' => $templateParams
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $result = curl_exec($ch);
    $response = json_decode($result, true);
    curl_close($ch);

    if (isset($response['status']) && $response['status'] === 'success') {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $response];
    }
}
