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

$required = ['student_id', 'subject_id', 'term', 'year'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing field: ' . $field]);
        exit();
    }
}

$st_id = mysqli_real_escape_string($conn, (string) $data['student_id']);
$sub_id = mysqli_real_escape_string($conn, (string) $data['subject_id']);
$term = mysqli_real_escape_string($conn, (string) $data['term']);
$yr = mysqli_real_escape_string($conn, (string) $data['year']);

$classRow = $conn->query("SELECT class_name, stream, combination FROM students WHERE id = '$st_id' LIMIT 1")->fetch_assoc();
$class_name = $classRow['class_name'] ?? '';
$stream = $classRow['stream'] ?? '';
$combination = $classRow['combination'] ?? '';

$ctx = build_marks_context([
    'level_name' => 'alevel',
    'class_name' => $class_name,
    'stream' => $stream,
    'combination' => $combination,
    'subject_id' => $sub_id,
    'exam_type' => $term,
    'academic_year' => $yr,
]);
$lockState = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
$role = strtolower((string)($_SESSION['role'] ?? ''));
$isTeacher = in_array($role, ['teacher', 'class teacher'], true);
if ($isTeacher && is_marks_context_locked($lockState)) {
    http_response_code(423);
    echo json_encode(['success' => false, 'message' => 'Context locked. Request admin unlock first.']);
    exit();
}

$sub_res = $conn->query("SELECT subject_name FROM subjects WHERE id = '$sub_id' LIMIT 1")->fetch_assoc();
$sub_name = strtoupper($sub_res['subject_name'] ?? '');
$single_paper_subjects = ['BAM', 'BASIC APPLIED MATHEMATICS', 'GENERAL STUDIES', 'GS', 'COMMUNICATION SKILLS', 'HISTORIA YA TANZANIA NA MAADILI', 'ACCOUNTANCY'];
$is_single_paper = in_array($sub_name, $single_paper_subjects, true);

$test = 0;
$exam = 0;
$p1 = 0;
$p2 = 0;
$p3 = 0;
$total = 0;

if ($is_single_paper) {
    $total = (float) ($data['p1'] ?? $data['annual'] ?? $data['test'] ?? 0);
    $total = min($total, 100);
} else {
    if ($term === 'Annual') {
        $total = min((float) ($data['p1'] ?? $data['annual'] ?? 0), 100);
    } elseif ($term === 'Monthly 1' || $term === 'Monthly 2') {
        $test = (float) ($data['test'] ?? 0);
        $exam = (float) ($data['exam'] ?? 0);
        $total = min($test + $exam, 100);
    } elseif ($term === 'Term 1') {
        $p1 = min((float) ($data['p1'] ?? 0), 100);
        $p2 = min((float) ($data['p2'] ?? 0), 100);
        $total = ($p1 + $p2) / 2;
    } elseif ($term === 'Term 2') {
        $p1 = min((float) ($data['p1'] ?? 0), 100);
        $p2 = min((float) ($data['p2'] ?? 0), 100);
        $p3 = min((float) ($data['p3'] ?? 0), 50);
        $total = (($p1 + $p2 + $p3) / 250) * 100;
    } elseif ($term === 'Term 3') {
        $p1 = min((float) ($data['p1'] ?? 0), 100);
        $p2 = min((float) ($data['p2'] ?? 0), 50);
        $total = (($p1 + $p2) / 150) * 100;
    } else {
        $test = (float) ($data['test'] ?? 0);
        $exam = (float) ($data['exam'] ?? 0);
        $total = min($test + $exam, 100);
    }
}

if ($total >= 80) { $grade = 'A'; $pts = 1; $rem = 'Excellent'; }
elseif ($total >= 70) { $grade = 'B'; $pts = 2; $rem = 'Very Good'; }
elseif ($total >= 60) { $grade = 'C'; $pts = 3; $rem = 'Good'; }
elseif ($total >= 50) { $grade = 'D'; $pts = 4; $rem = 'Satisfactory'; }
elseif ($total >= 40) { $grade = 'E'; $pts = 5; $rem = 'Pass'; }
elseif ($total >= 35) { $grade = 'S'; $pts = 6; $rem = 'Subsidiary'; }
else { $grade = 'F'; $pts = 7; $rem = 'Fail'; }

$check = $conn->query("SELECT id FROM marks WHERE student_id='$st_id' AND subject_id='$sub_id' AND term='$term' AND year='$yr'");
if ($check && $check->num_rows > 0) {
    $sql = "UPDATE marks SET test_avg_40='$test', exam_60='$exam', total_100='$total', grade='$grade', points='$pts', remark='$rem'
            WHERE student_id='$st_id' AND subject_id='$sub_id' AND term='$term' AND year='$yr'";
} else {
    $sql = "INSERT INTO marks (student_id, subject_id, year, term, test_avg_40, exam_60, total_100, grade, points, remark)
            VALUES ('$st_id', '$sub_id', '$yr', '$term', '$test', '$exam', '$total', '$grade', '$pts', '$rem')";
}

$conn->query($sql);

$completionRow = $conn->query("SELECT
    COUNT(*) AS total_students,
    SUM(
        CASE
            WHEN term = 'Annual' THEN (COALESCE(total_100,0) > 0)
            WHEN term IN ('Monthly 1','Monthly 2') THEN (COALESCE(test_avg_40,0) > 0 OR COALESCE(exam_60,0) > 0)
            ELSE (COALESCE(total_100,0) > 0)
        END
    ) AS completed_count
    FROM marks
    WHERE subject_id = '$sub_id'
      AND year = '$yr'
      AND term = '$term'
      AND student_id IN (
          SELECT id FROM students
          WHERE class_name = '" . mysqli_real_escape_string($conn, $class_name) . "'
            AND stream = '" . mysqli_real_escape_string($conn, $stream) . "'
            AND (combination = '" . mysqli_real_escape_string($conn, $combination) . "' OR '" . mysqli_real_escape_string($conn, $combination) . "' = '' OR combination IS NULL OR combination = '')
      )")->fetch_assoc();

$totalStudents = (int)($completionRow['total_students'] ?? 0);
$completedCount = (int)($completionRow['completed_count'] ?? 0);
$completionPercent = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100, 2) : 0;
$lockNow = $completionPercent >= 100 ? 1 : 0;
upsert_marks_lock_state($conn, $ctx, $completionPercent, $lockNow, (int)$_SESSION['user_id']);

echo json_encode([
    'success' => true,
    'total' => round($total, 1),
    'grade' => $grade,
    'completion_percent' => $completionPercent,
    'locked' => $lockNow === 1,
    'saved_at' => date('H:i:s')
]);
