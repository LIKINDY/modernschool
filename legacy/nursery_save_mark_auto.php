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

$required = ['student_id', 'subject_id', 'class_name', 'stream', 'exam_type', 'academic_year'];
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
$exam_type = mysqli_real_escape_string($conn, (string) $data['exam_type']);
$academic_year = mysqli_real_escape_string($conn, (string) $data['academic_year']);

$ctx = build_marks_context([
    'level_name' => 'nursery',
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

$ca_base = isset($data['ca_base']) ? (int) $data['ca_base'] : 10;
$monthly_base = isset($data['monthly_base']) ? (int) $data['monthly_base'] : 30;
$exam_base = isset($data['exam_base']) ? (int) $data['exam_base'] : 60;

$ca = isset($data['ca_mark']) && $data['ca_mark'] !== '' ? (float) $data['ca_mark'] : 0;
$monthly = isset($data['monthly_mark']) && $data['monthly_mark'] !== '' ? (float) $data['monthly_mark'] : 0;
$exam = isset($data['exam_mark']) && $data['exam_mark'] !== '' ? (float) $data['exam_mark'] : 0;

$total = $ca + $monthly + $exam;
if ($total < 0) {
    $total = 0;
}

$recorded_by = (int) $_SESSION['user_id'];

$query = "INSERT INTO nursery_marks 
          (student_id, subject_id, academic_year, class_name, stream, exam_type, 
           ca_mark, ca_base, monthly_mark, monthly_base, exam_mark, exam_base, total_normalized, recorded_by)
          VALUES 
          ('$student_id', '$subject_id', '$academic_year', '$class_name', '$stream', '$exam_type',
           '$ca', '$ca_base', '$monthly', '$monthly_base', '$exam', '$exam_base', '$total', '$recorded_by')
          ON DUPLICATE KEY UPDATE
           ca_mark = '$ca',
           ca_base = '$ca_base',
           monthly_mark = '$monthly',
           monthly_base = '$monthly_base',
           exam_mark = '$exam',
           exam_base = '$exam_base',
           total_normalized = '$total',
           recorded_by = '$recorded_by'";

$conn->query($query);

$completionRow = $conn->query("SELECT
    COUNT(*) AS total_students,
    SUM(
        CASE
            WHEN exam_type = 'Annual' THEN (COALESCE(exam_mark,0) > 0)
            ELSE (COALESCE(ca_mark,0) > 0 OR COALESCE(monthly_mark,0) > 0 OR COALESCE(exam_mark,0) > 0)
        END
    ) AS completed_count
    FROM nursery_marks
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
    'total' => round($total, 2),
    'completion_percent' => $completionPercent,
    'locked' => $lockNow === 1,
    'saved_at' => date('H:i:s')
]);
