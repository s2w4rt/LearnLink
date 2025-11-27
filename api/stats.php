<?php
require_once '../config.php';
checkAdminAuth();

try {
    $db = getDB();
    
    // Student counts by strand
    $stmt = $db->query("SELECT strand, COUNT(*) as count FROM students GROUP BY strand");
    $strandCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total students
    $stmt = $db->query("SELECT COUNT(*) as total FROM students");
    $total = $stmt->fetchColumn();
    
    sendJsonResponse([
        'total' => $total,
        'HUMSS' => $strandCounts['HUMSS'] ?? 0,
        'ICT' => $strandCounts['ICT'] ?? 0,
        'STEM' => $strandCounts['STEM'] ?? 0,
        'TVL' => $strandCounts['TVL'] ?? 0,
        'TVL-HE' => $strandCounts['TVL-HE'] ?? 0
    ]);
} catch(PDOException $e) {
    sendJsonResponse(['error' => $e->getMessage()], 500);
}
?>