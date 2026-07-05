<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

ensure_marks_lock_tables($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id    = $_POST['subject_id'];
    $class         = $_POST['class'];
    $stream        = $_POST['stream'];
    $exam_type     = $_POST['exam_type'];
    $academic_year = $_POST['academic_year'];

    $ctx = build_marks_context([
        'level_name' => 'nursery',
        'class_name' => $class,
        'stream' => $stream,
        'subject_id' => $subject_id,
        'exam_type' => $exam_type,
        'academic_year' => $academic_year,
    ]);
    $lockState = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    $isTeacher = in_array($role, ['teacher', 'class teacher'], true);
    if ($isTeacher && is_marks_context_locked($lockState)) {
        echo "<script>alert('Entry is locked. Request admin approval to edit.'); window.location.href='nursery_add_marks.php?subject=$subject_id&class=$class&stream=$stream&exam_type=$exam_type&year=$academic_year&fetch=1';</script>";
        exit();
    }
    
    // Base percentages used during entry
    $ca_base  = (int)$_POST['ca_base'];
    $mon_base = (int)$_POST['monthly_base'];
    $exm_base = (int)$_POST['exam_base'];

    $student_ids    = $_POST['student_ids'];
    $ca_marks       = $_POST['ca_marks'];
    $monthly_marks  = $_POST['monthly_marks'];
    $exam_marks     = $_POST['exam_marks'];

    $success_count = 0;

    foreach ($student_ids as $index => $student_id) {
        $ca  = (float)$ca_marks[$index];
        $mon = (float)$monthly_marks[$index];
        $exm = (float)$exam_marks[$index];
        
        // Calculate total (Normalization to 100%)
        // Marks zote zikijumlishwa zinaleta 100 kulingana na base
        $total = $ca + $mon + $exm;

        // Save using UPSERT (Update if exists, Insert if new)
        $query = "INSERT INTO nursery_marks 
                  (student_id, subject_id, academic_year, class_name, stream, exam_type, 
                   ca_mark, ca_base, monthly_mark, monthly_base, exam_mark, exam_base, total_normalized, recorded_by) 
                  VALUES 
                  ('$student_id', '$subject_id', '$academic_year', '$class', '$stream', '$exam_type', 
                   '$ca', '$ca_base', '$mon', '$mon_base', '$exm', '$exm_base', '$total', '{$_SESSION['user_id']}')
                  ON DUPLICATE KEY UPDATE 
                   ca_mark = '$ca', 
                   ca_base = '$ca_base', 
                   monthly_mark = '$mon', 
                   monthly_base = '$mon_base', 
                   exam_mark = '$exm', 
                   exam_base = '$exm_base', 
                   total_normalized = '$total', 
                   recorded_by = '{$_SESSION['user_id']}'";
        
        if ($conn->query($query)) {
            $success_count++;
        }
    }

    $completionRow = $conn->query("SELECT
        COUNT(*) AS total_students,
        SUM(
            CASE
                WHEN exam_type = 'Annual' THEN (COALESCE(exam_mark,0) > 0)
                ELSE (COALESCE(ca_mark,0) > 0 OR COALESCE(monthly_mark,0) > 0 OR COALESCE(exam_mark,0) > 0)
            END
        ) AS completed_count
        FROM nursery_marks
        WHERE subject_id = '" . mysqli_real_escape_string($conn, $subject_id) . "'
          AND class_name = '" . mysqli_real_escape_string($conn, $class) . "'
          AND stream = '" . mysqli_real_escape_string($conn, $stream) . "'
          AND academic_year = '" . mysqli_real_escape_string($conn, $academic_year) . "'
          AND exam_type = '" . mysqli_real_escape_string($conn, $exam_type) . "'")->fetch_assoc();

    $totalStudents = (int)($completionRow['total_students'] ?? 0);
    $completedCount = (int)($completionRow['completed_count'] ?? 0);
    $completionPercent = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100, 2) : 0;
    $lockNow = $completionPercent >= 100 ? 1 : 0;
    upsert_marks_lock_state($conn, $ctx, $completionPercent, $lockNow, (int)$_SESSION['user_id']);

    // Redirect back with success message
    echo "<script>
            alert('$success_count Marks saved successfully!');
            window.location.href = 'nursery_add_marks.php?subject=$subject_id&class=$class&stream=$stream&exam_type=$exam_type&year=$academic_year&fetch=1';
          </script>";
} else {
    header("Location: nursery_add_marks.php");
}
?>