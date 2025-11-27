<?php
require_once 'config.php';
checkAdminAuth();

if ($_POST['action'] === 'archive_semester') {
    $db = getDB();
    
    $schoolYear = $_POST['school_year'];
    $semester = $_POST['semester'];
    $gradeLevel = $_POST['grade_level'];
    $strand = $_POST['strand'];
    
    // Get students for the specified criteria
    $stmt = $db->prepare("SELECT id FROM students WHERE grade_level = ? AND strand = ?");
    $stmt->execute([$gradeLevel, $strand]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($students as $student) {
        // Calculate and archive final grades for each subject
        // This would involve complex logic to calculate final grades from assignments
        // For simplicity, I'll show the structure:
        
        $stmt = $db->prepare("
            INSERT INTO student_archive_grades 
            (student_id, school_year, grade_level, strand, semester, subject_id, 
             quarter1_grade, quarter2_grade, quarter3_grade, quarter4_grade, final_grade) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // You would loop through each subject and calculate grades here
        // $stmt->execute([$student['id'], $schoolYear, $gradeLevel, $strand, $semester, $subjectId, $q1, $q2, $q3, $q4, $final]);
    }
    
    echo "Grades archived successfully!";
}
?>