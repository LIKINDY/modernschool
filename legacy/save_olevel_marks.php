<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

ensure_marks_lock_tables($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Receive Context Data
    $subject_id  = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $class_name  = mysqli_real_escape_string($conn, $_POST['class_name']);
    $stream      = mysqli_real_escape_string($conn, $_POST['stream']);
    $year        = mysqli_real_escape_string($conn, $_POST['year']);
    $exam_type   = mysqli_real_escape_string($conn, $_POST['exam_type']);

    $ctx = build_marks_context([
        'level_name' => 'olevel',
        'class_name' => $class_name,
        'stream' => $stream,
        'subject_id' => $subject_id,
        'exam_type' => $exam_type,
        'academic_year' => $year,
    ]);
    $lockState = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    $isTeacher = in_array($role, ['teacher', 'class teacher'], true);
    if ($isTeacher && is_marks_context_locked($lockState)) {
        echo "<script>alert('Entry is locked. Request admin approval to edit.'); window.location.href='olevel_enter_result.php?subject=$subject_id&class=$class_name&stream=$stream&year=$year&exam_type=$exam_type&load_list=1';</script>";
        exit();
    }
    
    // Weights (Bases)
    $m_base = floatval($_POST['m_base'] ?? 40);
    $e_base = floatval($_POST['e_base'] ?? 60);

    // Marks Arrays from Form
    $student_ids = $_POST['std_id'];
    $m1_marks    = $_POST['m1'];
    $m2_marks    = $_POST['m2'] ?? array_fill(0, count($student_ids), 0);
    $p1_marks    = $_POST['p1'];
    $p2_marks    = $_POST['p2'] ?? array_fill(0, count($student_ids), 0);

    // Get subject name for Mock Logic
    $sub_res = $conn->query("SELECT subject_name FROM olevel_subjects WHERE id = '$subject_id'");
    $sub_row = $sub_res->fetch_assoc();
    $sub_name = strtolower($sub_row['subject_name'] ?? '');

    $success_count = 0;

    foreach ($student_ids as $index => $std_id) {
        $std_id = mysqli_real_escape_string($conn, $std_id);
        $m1 = floatval($m1_marks[$index]);
        $m2 = floatval($m2_marks[$index]);
        $p1 = floatval($p1_marks[$index]);
        $p2 = floatval($p2_marks[$index]);

        // --- CRITICAL: Skip saving if all marks are 0 or empty to save database space ---
        if ($m1 == 0 && $m2 == 0 && $p1 == 0 && $p2 == 0) {
            continue; 
        }

        $final_total = 0;

        // --- SERVER SIDE CALCULATION LOGIC ---
        if (strpos($exam_type, 'Term') !== false) {
            $final_total = $m1 + $p1; 
        } 
        elseif ($exam_type === 'Special') {
            $final_total = $m1 + $m2 + $p1; 
        }
        elseif ($exam_type === 'Mock') {
            if (strpos($sub_name, 'bio') !== false || strpos($sub_name, 'phy') !== false || strpos($sub_name, 'chem') !== false) {
                $final_total = ($p1 + $p2) / 1.5;
            } elseif (strpos($sub_name, 'edk') !== false) {
                $final_total = ($p1 + $p2) / 2;
            } else {
                $final_total = $p1;
            }
        } else {
            $final_total = $p1; 
        }

        if ($final_total > 100) $final_total = 100;

        // --- UPDATED GRADING LOGIC (A: 80-100) ---
        $grade = 'F';
        if ($final_total >= 80) $grade = 'A';
        elseif ($final_total >= 70) $grade = 'B';
        elseif ($final_total >= 60) $grade = 'C';
        elseif ($final_total >= 50) $grade = 'D';
        else $grade = 'F';

        // --- DATABASE OPERATIONS ---
        // Check if record exists
        $check = $conn->query("SELECT id FROM olevel_marks 
                               WHERE student_id = '$std_id' 
                               AND subject_id = '$subject_id' 
                               AND exam_type = '$exam_type' 
                               AND academic_year = '$year'");

        if ($check->num_rows > 0) {
            // UPDATE existing record
            $sql = "UPDATE olevel_marks SET 
                    monthly_mark = '$m1', 
                    m2_mark = '$m2', 
                    paper1_mark = '$p1', 
                    paper2_mark = '$p2', 
                    monthly_base = '$m_base',
                    exam_base = '$e_base',
                    total_score = '$final_total', 
                    grade = '$grade',
                    class_name = '$class_name',
                    stream = '$stream'
                    WHERE student_id = '$std_id' 
                    AND subject_id = '$subject_id' 
                    AND exam_type = '$exam_type' 
                    AND academic_year = '$year'";
        } else {
            // INSERT new record
            $sql = "INSERT INTO olevel_marks 
                    (student_id, subject_id, monthly_mark, m2_mark, paper1_mark, paper2_mark, monthly_base, exam_base, exam_type, academic_year, class_name, stream, total_score, grade) 
                    VALUES 
                    ('$std_id', '$subject_id', '$m1', '$m2', '$p1', '$p2', '$m_base', '$e_base', '$exam_type', '$year', '$class_name', '$stream', '$final_total', '$grade')";
        }

        if ($conn->query($sql)) {
            $success_count++;
        }
    }

    $completionRow = $conn->query("SELECT
        COUNT(*) AS total_students,
        SUM(
            CASE
                WHEN exam_type = 'Special' THEN (COALESCE(monthly_mark,0) > 0 OR COALESCE(m2_mark,0) > 0 OR COALESCE(paper1_mark,0) > 0)
                WHEN exam_type = 'Mock' THEN (COALESCE(paper1_mark,0) > 0 OR COALESCE(paper2_mark,0) > 0)
                WHEN exam_type IN ('Terminal','Term 1','Term 2') THEN (COALESCE(monthly_mark,0) > 0 OR COALESCE(paper1_mark,0) > 0)
                ELSE (COALESCE(paper1_mark,0) > 0)
            END
        ) AS completed_count
        FROM olevel_marks
        WHERE subject_id = '$subject_id'
          AND class_name = '$class_name'
          AND stream = '$stream'
          AND academic_year = '$year'
          AND exam_type = '$exam_type'")->fetch_assoc();

    $totalStudents = (int)($completionRow['total_students'] ?? 0);
    $completedCount = (int)($completionRow['completed_count'] ?? 0);
    $completionPercent = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100, 2) : 0;
    $lockNow = $completionPercent >= 100 ? 1 : 0;
    upsert_marks_lock_state($conn, $ctx, $completionPercent, $lockNow, (int)$_SESSION['user_id']);

    echo "<script>
            alert('Umefanikiwa kuhifadhi marks za wanafunzi $success_count !');
            window.location.href = 'olevel_enter_result.php?subject=$subject_id&class=$class_name&stream=$stream&year=$year&exam_type=$exam_type&load_list=1';
          </script>";
}
?>