<?php
session_start();
include('db_config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $conn->real_escape_string($_POST['subject']);
    $class_full = $_POST['class']; 
    $academic_year = $conn->real_escape_string($_POST['year']);
    $exam_type = $conn->real_escape_string($_POST['exam_type']); // Imepokelewa hapa (Mock au Terminal)
    
    $student_ids = $_POST['student_id'];
    $p1_marks = $_POST['p1'];
    $p2_marks = $_POST['p2'];

    // Tenga Class na Stream
    $parts = explode('-', $class_full);
    $class_name = $conn->real_escape_string($parts[0]);
    $stream = isset($parts[1]) ? $conn->real_escape_string($parts[1]) : '';

    // Science detection
    $is_science = false;
    $lower_sub = strtolower($subject);
    if (strpos($lower_sub, 'physic') !== false || strpos($lower_sub, 'chem') !== false || strpos($lower_sub, 'biol') !== false) {
        $is_science = true;
    }

    $success_count = 0;

    foreach ($student_ids as $index => $student_id) {
        $s_id = $conn->real_escape_string($student_id);
        $p1 = floatval($p1_marks[$index]);
        $p2 = floatval($p2_marks[$index]);
        
        // --- HESABU ZA KITAALAMU (SERVER-SIDE) ---
        $final_total = 0;
        
        if ($exam_type === 'Terminal') {
            // Terminal: (M1 * 40%) + (Final * 60%)
            $final_total = round(($p1 * 0.4) + ($p2 * 0.6));
        } else {
            // Mock: Science (P1+P2)/1.5, Others P1
            if ($is_science) {
                $final_total = round(($p1 + $p2) / 1.5);
            } else {
                $final_total = round($p1);
            }
        }

        // NECTA Grading System
        $g = 'F'; $p = 5;
        if ($final_total >= 75) { $g = 'A'; $p = 1; }
        elseif ($final_total >= 65) { $g = 'B'; $p = 2; }
        elseif ($final_total >= 45) { $g = 'C'; $p = 3; }
        elseif ($final_total >= 30) { $g = 'D'; $p = 4; }
        else { $g = 'F'; $p = 5; }

        // Query imeboreshwa kuongeza exam_type
        $sql = "INSERT INTO mock_results 
                (student_id, subject_name, class_name, stream, academic_year, exam_type, p1, p2, total, grade, points) 
                VALUES 
                ('$s_id', '$subject', '$class_name', '$stream', '$academic_year', '$exam_type', '$p1', '$p2', '$final_total', '$g', '$p')
                ON DUPLICATE KEY UPDATE 
                p1 = VALUES(p1), 
                p2 = VALUES(p2), 
                total = VALUES(total), 
                grade = VALUES(grade), 
                points = VALUES(points)";

        if ($conn->query($sql)) {
            $success_count++;
        }
    }

    $_SESSION['msg'] = "Hongera! Alama $success_count za $exam_type zimehifadhiwa.";
    header("Location: mock_handler.php?assignment_data=" . urlencode($_POST['assignment_data'] ?? ''));
    exit();
} else {
    header("Location: mock_handler.php");
    exit();
}
?>