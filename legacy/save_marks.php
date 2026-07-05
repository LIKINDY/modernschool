<?php
session_start();
include('db_config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Pokea taarifa za msingi na kusafisha data
    $subject_id    = $conn->real_escape_string($_POST['subject_id']);
    $year          = $conn->real_escape_string($_POST['year']);
    $term          = $conn->real_escape_string($_POST['term']);
    $assignment_id = $conn->real_escape_string($_POST['assignment_id']); 
    
    // Muhimu: Tunapokea stream ili turudi nayo kwenye marks_entry.php
    $stream        = isset($_POST['stream']) ? $conn->real_escape_string($_POST['stream']) : '';

    // 2. Pokea arrays za alama kutoka kwenye fomu
    $student_ids = $_POST['student_id'];
    $m1_list     = $_POST['m1'] ?? [];
    $m2_list     = $_POST['m2'] ?? [];
    $exam_list   = $_POST['exam'] ?? [];

    $success_count = 0;

    foreach ($student_ids as $index => $student_id) {
        $student_id = $conn->real_escape_string($student_id);
        
        // Usalama wa data (Kama alama haijaingizwa iwe 0)
        $m1   = (isset($m1_list[$index]) && $m1_list[$index] !== "") ? (float)$m1_list[$index] : 0;
        $m2   = (isset($m2_list[$index]) && $m2_list[$index] !== "") ? (float)$m2_list[$index] : 0;
        $exam_raw = (isset($exam_list[$index]) && $exam_list[$index] !== "") ? (float)$exam_list[$index] : 0;

        // 3. Mahesabu ya Kitaaluma kulingana na muundo wa database yako
        $test_avg_40 = 0;
        $exam_60     = 0;
        $total_100   = 0;

        if ($term == 'Terminal') {
            // TERMINAL: M1(40%) + EXAM(60%)
            $test_avg_40 = $m1 * 0.4;
            $exam_60     = $exam_raw * 0.6;
            $total_100   = $test_avg_40 + $exam_60;
        } 
        elseif ($term == 'Final') {
            // FINAL: EXAM is 100%
            $test_avg_40 = 0; 
            $exam_60     = 0; 
            $total_100   = $exam_raw; 
        } 
        else {
            // TERM 1 au TERM 2: (M1*0.2 + M2*0.2) + EXAM*0.6
            $test_avg_40 = ($m1 * 0.2) + ($m2 * 0.2);
            $exam_60     = $exam_raw * 0.6;
            $total_100   = $test_avg_40 + $exam_60;
        }

        // 4. Grading System (Kulingana na vigezo vyako vya SQL)
        if ($total_100 >= 81) { 
            $grade = 'A'; $remark = 'Excellent'; 
        } elseif ($total_100 >= 61) { 
            $grade = 'B'; $remark = 'Very Good'; 
        } elseif ($total_100 >= 41) { 
            $grade = 'C'; $remark = 'Good'; 
        } elseif ($total_100 >= 21) { 
            $grade = 'D'; $remark = 'Satisfactory'; 
        } else { 
            $grade = 'F'; $remark = 'Fail'; 
        }

        // 5. Angalia kama record ipo (Check if exists)
        $check_query = "SELECT id FROM marks 
                        WHERE student_id = '$student_id' 
                        AND subject_id = '$subject_id' 
                        AND year = '$year' 
                        AND term = '$term'";
        
        $check_res = $conn->query($check_query);

        if ($check_res->num_rows > 0) {
            // UPDATE record iliyopo
            $sql = "UPDATE marks SET 
                    monthly_1 = '$m1', 
                    monthly_2 = '$m2', 
                    test_avg_40 = '$test_avg_40', 
                    exam_60 = '$exam_60', 
                    total_100 = '$total_100', 
                    grade = '$grade', 
                    remark = '$remark' 
                    WHERE student_id = '$student_id' 
                    AND subject_id = '$subject_id' 
                    AND year = '$year' 
                    AND term = '$term'";
        } else {
            // INSERT record mpya
            $sql = "INSERT INTO marks (student_id, subject_id, year, term, monthly_1, monthly_2, test_avg_40, exam_60, total_100, grade, remark) 
                    VALUES ('$student_id', '$subject_id', '$year', '$term', '$m1', '$m2', '$test_avg_40', '$exam_60', '$total_100', '$grade', '$remark')";
        }

        if ($conn->query($sql)) {
            $success_count++;
        }
    }

    // 6. Rudisha mtumiaji kule kule huku ukibeba vigezo vyote (ikiwemo Stream)
    header("Location: marks_entry.php?assignment_id=$assignment_id&year=$year&term=$term&stream=$stream&msg=success");
    exit();
} else {
    header("Location: marks_entry.php");
    exit();
}