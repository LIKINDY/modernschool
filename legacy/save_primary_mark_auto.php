<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ensure_marks_lock_tables($conn);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$required = ['student_id', 'subject_id', 'class_name', 'stream', 'academic_year', 'exam_type'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing field: ' . $field]);
        exit();
    }
}

$student_id = mysqli_real_escape_string($conn, (string) $data['student_id']);
$subject_id = mysqli_real_escape_string($conn, (string) $data['subject_id']);
$class_name = mysqli_real_escape_string($conn, (string) $data['class_name']);
$stream = mysqli_real_escape_string($conn, (string) $data['stream']);
$academic_year = mysqli_real_escape_string($conn, (string) $data['academic_year']);
$exam_type = mysqli_real_escape_string($conn, (string) $data['exam_type']);

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
    http_response_code(423);
    echo json_encode(['success' => false, 'message' => 'Context locked. Request admin unlock first.']);
    exit();
}

$m_mark_raw = $data['m_mark'] ?? '';
$m2_mark_raw = $data['m2_mark'] ?? '';
$e_mark_raw = $data['e_mark'] ?? '';

if ($m_mark_raw === '' && $m2_mark_raw === '' && $e_mark_raw === '') {
    echo json_encode(['success' => true, 'message' => 'No marks to save']);
    exit();
}

$m_weight = isset($data['m_weight']) ? floatval($data['m_weight']) : 40;
$e_weight = isset($data['e_weight']) ? floatval($data['e_weight']) : 60;

$m_mark = floatval($m_mark_raw);
$m2_mark = floatval($m2_mark_raw);
$e_mark = floatval($e_mark_raw);

if ($exam_type === 'special') {
    $total = $m_mark + $m2_mark + ($e_mark * 0.6);
} elseif ($exam_type === 'terminal' || $exam_type === 'annual') {
    $total = $e_mark;
} else {
    $total = $m_mark + ($e_mark * ($e_weight / 100));
}

if ($total > 100) {
    $total = 100;
}
if ($total < 0) {
    $total = 0;
}

if ($total >= 81) {
    $grade = 'A';
    $remarks = 'Excellent';
} elseif ($total >= 70) {
    $grade = 'B';
    $remarks = 'Very Good';
} elseif ($total >= 60) {
    $grade = 'C';
    $remarks = 'Good';
} elseif ($total >= 40) {
    $grade = 'D';
    $remarks = 'Satisfactory';
} else {
    $grade = 'F';
    $remarks = 'Fail';
}

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

$conn->query($sql);

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

echo json_encode([
    'success' => true,
    'message' => 'Saved',
    'total' => round($total, 1),
    'grade' => $grade,
    'completion_percent' => $completionPercent,
    'locked' => $lockNow === 1,
    'saved_at' => date('H:i:s')
]);
