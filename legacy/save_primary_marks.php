<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

ensure_marks_lock_tables($conn);

$subject_id    = mysqli_real_escape_string($conn, $_POST['subject_id']);
$class_name    = mysqli_real_escape_string($conn, $_POST['class_name']);
$stream        = mysqli_real_escape_string($conn, $_POST['stream']);
$academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
$exam_type     = mysqli_real_escape_string($conn, $_POST['exam_type']);

$ctx = build_marks_context([
    'level_name' => 'primary',
    'class_name' => $class_name,
    'stream' => $stream,
    'subject_id' => $subject_id,
    'exam_type' => $exam_type,
    'academic_year' => $academic_year,
]);
$lockState = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
$role = strtolower((string)($_SESSION['role'] ?? ''));
$isTeacher = in_array($role, ['teacher', 'class teacher'], true);
if ($isTeacher && is_marks_context_locked($lockState)) {
    echo "<script>alert('Entry is locked. Request admin approval to edit.'); window.location.href='" . $_SERVER['HTTP_REFERER'] . "';</script>";
    exit();
}

// Capture the weights from the form
$m_weight = isset($_POST['m_weight']) ? floatval($_POST['m_weight']) : 40;
$e_weight = isset($_POST['e_weight']) ? floatval($_POST['e_weight']) : 60;

$student_ids = $_POST['student_id'];
$m_marks     = isset($_POST['m_marks']) ? $_POST['m_marks'] : [];
$m2_marks    = isset($_POST['m2_marks']) ? $_POST['m2_marks'] : [];
$e_marks     = $_POST['e_marks'];

$success_count = 0;

foreach ($student_ids as $index => $student_id) {
    $m_val_raw  = isset($m_marks[$index]) ? $m_marks[$index] : '';
    $m2_val_raw = isset($m2_marks[$index]) ? $m2_marks[$index] : '';
    $e_val_raw  = isset($e_marks[$index]) ? $e_marks[$index] : '';

    if ($m_val_raw === '' && $e_val_raw === '' && $m2_val_raw === '') {
        continue; 
    }

    $student_id = mysqli_real_escape_string($conn, $student_id);
    $m_mark  = floatval($m_val_raw);
    $m2_mark = floatval($m2_val_raw);
    $e_mark  = floatval($e_val_raw);

    // DYNAMIC TOTAL CALCULATION
    if ($exam_type == 'special') {
        // Special logic: M1(20) + M2(20) + Exam(60%)
        $total = $m_mark + $m2_mark + ($e_mark * 0.6);
    } elseif ($exam_type == 'terminal' || $exam_type == 'annual') {
        $total = $e_mark; 
    } else {
        // Standard Term logic using dynamic weights from the form
        $total = $m_mark + ($e_mark * ($e_weight / 100));
    }

    if($total > 100) $total = 100;

    // TANZANIA PRIMARY GRADING
    if ($total >= 81) { $grade = 'A'; $remarks = 'Excellent'; }
    elseif ($total >= 70) { $grade = 'B'; $remarks = 'Very Good'; }
    elseif ($total >= 60) { $grade = 'C'; $remarks = 'Good'; }
    elseif ($total >= 40) { $grade = 'D'; $remarks = 'Satisfactory'; }
    else { $grade = 'F'; $remarks = 'Fail'; }

    // CRITICAL: Added monthly_base and exam_base to the query so the DB remembers the setting
    $sql = "INSERT INTO primary_marks 
            (student_id, subject_id, class_name, stream, academic_year, exam_type, monthly_mark, m2_mark, exam_mark, total_mark, grade, remarks, monthly_base, exam_base) 
            VALUES 
            ('$student_id', '$subject_id', '$class_name', '$stream', '$academic_year', '$exam_type', '$m_mark', '$m2_mark', '$e_mark', '$total', '$grade', '$remarks', '$m_weight', '$e_weight')
            ON DUPLICATE KEY UPDATE 
            monthly_mark = VALUES(monthly_mark), 
            m2_mark = VALUES(m2_mark),
            exam_mark = VALUES(exam_mark), 
            total_mark = VALUES(total_mark), 
            grade = VALUES(grade), 
            remarks = VALUES(remarks),
            monthly_base = VALUES(monthly_base),
            exam_base = VALUES(exam_base)";

    if ($conn->query($sql)) { $success_count++; }
}

$completionRow = $conn->query("SELECT
    COUNT(*) AS total_students,
    SUM(
        CASE
            WHEN exam_type = 'special' THEN (COALESCE(monthly_mark,0) > 0 OR COALESCE(m2_mark,0) > 0 OR COALESCE(exam_mark,0) > 0)
            WHEN exam_type IN ('term1','term2') THEN (COALESCE(monthly_mark,0) > 0 OR COALESCE(exam_mark,0) > 0)
            ELSE (COALESCE(exam_mark,0) > 0)
        END
    ) AS completed_count
    FROM primary_marks
    WHERE subject_id = '$subject_id'
      AND class_name = '$class_name'
      AND stream = '$stream'
      AND academic_year = '$academic_year'
      AND exam_type = '$exam_type'")->fetch_assoc();

$totalStudents = (int)($completionRow['total_students'] ?? 0);
$completedCount = (int)($completionRow['completed_count'] ?? 0);
$completionPercent = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100, 2) : 0;
$lockNow = $completionPercent >= 100 ? 1 : 0;
upsert_marks_lock_state($conn, $ctx, $completionPercent, $lockNow, (int)$_SESSION['user_id']);

// SUCCESS MESSAGE WITH PERCENTAGE CONFIRMATION
$msg = "Successfully saved marks for $success_count students using Monthly: $m_weight% and Exam: $e_weight% weight settings.";
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo $msg; ?>',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?php echo $_SERVER['HTTP_REFERER']; ?>';
            }
        });
    </script>
</body>
</html>