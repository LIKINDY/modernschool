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

$required = ['student_id', 'subject_id', 'class_name', 'stream', 'year', 'exam_type'];
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
$year = mysqli_real_escape_string($conn, (string) $data['year']);
$exam_type = mysqli_real_escape_string($conn, (string) $data['exam_type']);

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
    http_response_code(423);
    echo json_encode(['success' => false, 'message' => 'Context locked. Request admin unlock first.']);
    exit();
}

$m_base = isset($data['m_base']) ? (float) $data['m_base'] : 40;
$e_base = isset($data['e_base']) ? (float) $data['e_base'] : 60;

$m1 = isset($data['m1']) && $data['m1'] !== '' ? (float) $data['m1'] : 0;
$m2 = isset($data['m2']) && $data['m2'] !== '' ? (float) $data['m2'] : 0;
$p1 = isset($data['p1']) && $data['p1'] !== '' ? (float) $data['p1'] : 0;
$p2 = isset($data['p2']) && $data['p2'] !== '' ? (float) $data['p2'] : 0;

if ($m1 == 0 && $m2 == 0 && $p1 == 0 && $p2 == 0) {
    echo json_encode(['success' => true, 'message' => 'Skipped empty row']);
    exit();
}

$sub_res = $conn->query("SELECT subject_name FROM olevel_subjects WHERE id = '$subject_id' LIMIT 1");
$sub_row = $sub_res ? $sub_res->fetch_assoc() : null;
$sub_name = strtolower($sub_row['subject_name'] ?? '');

$final_total = 0;

if (strpos($exam_type, 'Term') !== false) {
    $final_total = $m1 + $p1;
} elseif ($exam_type === 'Special') {
    $final_total = $m1 + $m2 + $p1;
} elseif ($exam_type === 'Mock') {
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

if ($final_total > 100) {
    $final_total = 100;
}
if ($final_total < 0) {
    $final_total = 0;
}

$grade = 'F';
if ($final_total >= 80) {
    $grade = 'A';
} elseif ($final_total >= 70) {
    $grade = 'B';
} elseif ($final_total >= 60) {
    $grade = 'C';
} elseif ($final_total >= 50) {
    $grade = 'D';
}

$check = $conn->query("SELECT id FROM olevel_marks
                       WHERE student_id = '$student_id'
                       AND subject_id = '$subject_id'
                       AND exam_type = '$exam_type'
                       AND academic_year = '$year' LIMIT 1");

if ($check && $check->num_rows > 0) {
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
            WHERE student_id = '$student_id'
            AND subject_id = '$subject_id'
            AND exam_type = '$exam_type'
            AND academic_year = '$year'";
} else {
    $sql = "INSERT INTO olevel_marks
            (student_id, subject_id, monthly_mark, m2_mark, paper1_mark, paper2_mark, monthly_base, exam_base, exam_type, academic_year, class_name, stream, total_score, grade)
            VALUES
            ('$student_id', '$subject_id', '$m1', '$m2', '$p1', '$p2', '$m_base', '$e_base', '$exam_type', '$year', '$class_name', '$stream', '$final_total', '$grade')";
}

$conn->query($sql);

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

echo json_encode([
    'success' => true,
    'message' => 'Saved',
    'total' => round($final_total, 1),
    'grade' => $grade,
    'completion_percent' => $completionPercent,
    'locked' => $lockNow === 1,
    'saved_at' => date('H:i:s')
]);
