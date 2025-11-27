<?php
// api/support-tickets.php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

/**
 * Helper: send JSON and exit
 */
function respond($data, $statusCode = 200) {
    sendJsonResponse($data, $statusCode);
}

switch ($method) {
    case 'GET':
        // If ?id=... is provided, return a single ticket
        if (!empty($_GET['id'])) {
            $ticketId = (int) $_GET['id'];

            $sql = "
                SELECT
                    id,
                    ticket_number,
                    name        AS student_name,
                    email       AS student_email,
                    student_id,
                    strand,
                    issue_type  AS subject,
                    urgency,
                    message,
                    status,
                    admin_notes,
                    resolved_at,
                    created_at,
                    updated_at
                FROM support_tickets
                WHERE id = ?
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                respond(['error' => 'Ticket not found'], 404);
            }

            respond($ticket);
        }

        // Otherwise, return a list of tickets (with optional filters)
        $conditions = [];
        $params     = [];

        // Optional: filter by student_id (?student_id=2024-001)
        if (!empty($_GET['student_id'])) {
            $conditions[] = 'student_id = ?';
            $params[]     = $_GET['student_id'];
        }

        // Optional: filter by status (?status=pending / in_progress / resolved / closed)
        if (!empty($_GET['status']) && $_GET['status'] !== 'ALL') {
            $conditions[] = 'status = ?';
            $params[]     = $_GET['status'];
        }

        $sql = "
            SELECT
                id,
                ticket_number,
                name        AS student_name,
                email       AS student_email,
                student_id,
                strand,
                issue_type  AS subject,
                urgency,
                message,
                status,
                admin_notes,
                resolved_at,
                created_at,
                updated_at
            FROM support_tickets
        ";

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        respond([
            'tickets' => $tickets,
            'total'   => count($tickets),
        ]);
        break;

    case 'PUT':
    case 'PATCH':
        // Optional: update ticket status / notes from admin
        $ticketId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$ticketId) {
            respond(['error' => 'Ticket ID is required'], 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        if ($action === 'update_status') {
            $newStatus = $data['status'] ?? null;
            if (!$newStatus) {
                respond(['error' => 'Status is required'], 400);
            }

            $sql = "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$newStatus, $ticketId]);

            respond(['success' => true]);
        } elseif ($action === 'add_note') {
            $note = trim($data['note'] ?? '');
            if ($note === '') {
                respond(['error' => 'Note is required'], 400);
            }

            $sql = "UPDATE support_tickets SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) , updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute(["\n" . $note, $ticketId]);

            respond(['success' => true]);
        } else {
            respond(['error' => 'Unknown action'], 400);
        }
        break;

    default:
        respond(['error' => 'Method not allowed'], 405);
}
