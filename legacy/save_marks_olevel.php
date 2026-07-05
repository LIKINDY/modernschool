<?php
session_start();
include('db_config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Mapokezi ya data za msingi kutoka kwenye hidden fields
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['year']);
    $term          = mysqli_real_escape_string($conn, $_POST['term']);
    
    // 2. Tafuta Subject ID kwa kutumia Subject Name (Muhimu kwa Foreign Key)
    $sub_res = $conn->query("SELECT id FROM subjects WHERE subject_name = '$subject_name' LIMIT 1");
    if ($sub_res->num_rows > 0) {
        $sub_row = $sub_res->fetch_assoc();
        $subject_id = $sub_row['id'];
    } else {
        die("Error: Subject not found in database.");
    }

    // 3. Arrays za data za wanafunzi
    $student_ids = $_POST['student_id'] ?? [];
    $m1_marks    = $_POST['m1'] ?? [];
    $m2_marks    = $_POST['m2'] ?? [];
    $exam_marks  = $_POST['exam'] ?? [];

    $success_count = 0;

    foreach ($student_ids as $index => $student_id) {
        $student_id = mysqli_real_escape_string($conn, $student_id);
        
        // Tunachukua alama. Kama ni Midterm, M2 na Exam hazitakuwepo, tunazipa 0.
        $m1   = isset($m1_marks[$index]) ? (float)$m1_marks[$index] : 0;
        $m2   = isset($m2_marks[$index]) ? (float)$m2_marks[$index] : 0;
        $exam = isset($exam_marks[$index]) ? (float)$exam_marks[$index] : 0;

        // 4. LOGIC YA MAHASABU (Kulingana na aina ya Mtihani)
        if ($term == 'Midterm') {
            // Midterm inachukuliwa kama 100% moja kwa moja kutoka m1
            $test_avg_40 = 0;
            $exam_60     = 0;
            $total_100   = round($m1);
        } else {
            // Term 1 au 2 (NECTA Style)
            $test_avg_40 = (($m1 + $m2) / 2) * 0.4;
            $exam_60     = $exam * 0.6;
            $total_100   = round($test_avg_40 + $exam_60);
        }

        // 5. GRADING (NECTA O-Level)
        $grade = 'F'; $points = 5; $remark = 'Fail';
        if ($total_100 >= 75) { $grade = 'A'; $points = 1; $remark = 'Excellent'; }
        elseif ($total_100 >= 65) { $grade = 'B'; $points = 2; $remark = 'Very Good'; }
        elseif ($total_100 >= 45) { $grade = 'C'; $points = 3; $remark = 'Good'; }
        elseif ($total_100 >= 30) { $grade = 'D'; $points = 4; $remark = 'Satisfactory'; }
        else { $grade = 'F'; $points = 5; $remark = 'Fail'; }

        // 6. DATABASE OPERATION (Check if exists then Update or Insert)
        $check_query = "SELECT id FROM marks 
                        WHERE student_id = '$student_id' 
                        AND subject_id = '$subject_id' 
                        AND year = '$academic_year' 
                        AND term = '$term'";
        $check_result = $conn->query($check_query);

        if ($check_result && $check_result->num_rows > 0) {
            // UPDATE
            $sql = "UPDATE marks SET 
                    monthly_1 = '$m1', 
                    monthly_2 = '$m2', 
                    test_avg_40 = '$test_avg_40', 
                    exam_60 = '$exam_60', 
                    total_100 = '$total_100', 
                    grade = '$grade', 
                    points = '$points', 
                    remark = '$remark'
                    WHERE student_id = '$student_id' 
                    AND subject_id = '$subject_id' 
                    AND year = '$academic_year' 
                    AND term = '$term'";
        } else {
            // INSERT
            $sql = "INSERT INTO marks (student_id, subject_id, monthly_1, monthly_2, test_avg_40, exam_60, total_100, grade, points, remark, term, year) 
                    VALUES ('$student_id', '$subject_id', '$m1', '$m2', '$test_avg_40', '$exam_60', '$total_100', '$grade', '$points', '$remark', '$term', '$academic_year')";
        }

        if ($conn->query($sql)) {
            $success_count++;
        }
    }

    echo "<script>
            alert('Successfully saved marks for $success_count students!');
            window.location.href = '" . $_SERVER['HTTP_REFERER'] . "';
          </script>";
    exit();
}