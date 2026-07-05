<?php
session_start();
include('db_config.php');

$student_id = $_GET['student_id'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$student_id || !$year || !$term) { echo "Missing parameters."; exit; }

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
$student = $conn->query("SELECT * FROM students WHERE id = '$student_id'")->fetch_assoc();
$fname = $student['fullname'];

$is_midterm = (strpos(strtolower($term), 'midterm') !== false);
$is_mock = (strpos(strtolower($term), 'mock') !== false);
$is_terminal = (strpos(strtolower($term), 'terminal') !== false);
$score_column = ($is_mock || $is_terminal) ? 'total' : ($is_midterm ? 'monthly_1' : 'total_100');

function getOlevelGrade($score) {
    if ($score >= 80) return ['A', 'Excellent', '#27ae60'];
    if ($score >= 70) return ['B', 'Very Good', '#2980b9'];
    if ($score >= 60) return ['C', 'Good', '#f1c40f'];
    if ($score >= 50) return ['D', 'Satisfactory', '#e67e22'];
    return ['F', 'Fail', '#c0392b'];
}

$marks_sql = ($is_mock || $is_terminal) ? 
    "SELECT *, total as final_score FROM mock_results WHERE student_id = '$student_id' AND academic_year = '$year' ORDER BY subject_name ASC" :
    "SELECT m.*, s.subject_name, m.$score_column as final_score FROM marks m JOIN subjects s ON m.subject_id = s.id WHERE m.student_id = '$student_id' AND m.year = '$year' AND m.term = '$term' ORDER BY s.subject_name ASC";

$marks_res = $conn->query($marks_sql);
$marks_data = []; $total_marks = 0; $subject_count = 0; $all_points = []; $weak_subjects = [];

while ($row = $marks_res->fetch_assoc()) {
    $current_score = $row['final_score'];
    $grade_data = getOlevelGrade($current_score);
    if ($grade_data[0] == 'D' || $grade_data[0] == 'F') $weak_subjects[] = $row['subject_name'];

    $pt = ($current_score >= 80) ? 1 : (($current_score >= 70) ? 2 : (($current_score >= 60) ? 3 : (($current_score >= 50) ? 4 : 5)));
    $all_points[] = $pt;
    $row['grade_info'] = $grade_data;
    $marks_data[] = $row;
    $total_marks += $current_score;
    $subject_count++;
}

$average = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;
sort($all_points);
$total_points = array_sum(array_slice($all_points, 0, 7));

if ($subject_count < 7) { $division = "INCOMPLETE"; } 
else {
    if ($total_points <= 17) $division = "I";
    elseif ($total_points <= 21) $division = "II";
    elseif ($total_points <= 25) $division = "III";
    elseif ($total_points <= 33) $division = "IV";
    else $division = "0";
}

$avg_info = getOlevelGrade($average);
$avg_grade = $avg_info[0];

// Dynamic Comments
if ($division == "INCOMPLETE") {
    $teacher_comment = "Results are incomplete due to missing subjects ($subject_count/7).";
    $head_comment = "Ensure all subjects are recorded for final grading.";
} else {
    if($avg_grade == 'A' || $avg_grade == 'B'){
        $teacher_comment = "Congratulations $fname for your excellent results. Continue to work hard.";
        $head_comment = "Very satisfactory results. Stay focused and keep increasing your efforts.";
    } elseif($avg_grade == 'C'){
        $teacher_comment = "Dear $fname, your results are fair. Put in more effort to improve next term.";
        $head_comment = "Performance needs improvement. Close supervision is advised.";
    } else { 
        $teacher_comment = "You have failed to meet the standards $fname. Double your efforts immediately.";
        $head_comment = "Poor performance. Major academic shift and close supervision required.";
    }
    if (!empty($weak_subjects)) $head_comment .= " Focus on: " . implode(", ", $weak_subjects);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Times New Roman', serif; color: #000; }
        .report-paper { width: 210mm; margin: 10px auto; padding: 25px; background: #fff; border: 1px solid #000; position: relative; }
        .school-logo { width: 80px; height: 80px; object-fit: contain; }
        .student-photo { width: 90px; height: 100px; border: 1px solid #000; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 4px; font-size: 13px; }
        .comment-box { border-bottom: 1px dotted #000; min-height: 40px; font-style: italic; font-size: 13px; margin-bottom: 10px; }
        
        /* Grade Summary Table CSS */
        .grade-summary-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .grade-summary-table th, .grade-summary-table td { 
            border: 1px solid #000; font-size: 10px; padding: 2px 5px; text-align: left; 
        }
        .grade-summary-table th { background: #f2f2f2; text-align: center; font-weight: bold; }

        @media print { .no-print { display: none; } .report-paper { border: none; margin: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="text-center no-print py-3">
    <button onclick="window.print()" class="btn btn-dark btn-sm px-4">PRINT REPORT</button>
</div>

<div class="report-paper">
    <div class="text-center border-bottom border-2 mb-3 pb-2">
        <div class="row align-items-center">
            <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
            <div class="col-8">
                <h3 class="fw-bold text-uppercase mb-0"><?= $school['school_name'] ?></h3>
                <p class="mb-0 fw-bold small"><?= $school['address'] ?> | TEL: <?= $school['phone'] ?></p>
                <h6 class="mt-2 fw-bold text-decoration-underline text-uppercase"><?= $term ?> REPORT - <?= $year ?></h6>
            </div>
            <div class="col-2 text-end"><img src="uploads/students/<?= $student['photo'] ?>" class="student-photo"></div>
        </div>
    </div>

    <div class="row mb-2 text-uppercase fw-bold small">
        <div class="col-7">
            <p class="mb-1">NAME: <?= $fname ?></p>
            <p class="mb-1">ID: <?= $student['student_id'] ?></p>
        </div>
        <div class="col-5 text-end">
            <p class="mb-1">CLASS: <?= $student['class_name'] ?> - <?= $student['stream'] ?></p>
            <p class="mb-1">DIV: <span style="color: <?= ($division == 'INCOMPLETE') ? 'red' : 'blue' ?>;"><?= $division ?> <?= ($division != 'INCOMPLETE') ? "($total_points PTS)" : "" ?></span></p>
        </div>
    </div>

    <table class="table table-marks w-100">
        <thead class="table-light">
            <tr>
                <th class="text-start">SUBJECTS</th>
                <th>TEST</th><th>EXAM</th><th>TOTAL</th>
                <th>GRADE</th><th>REMARKS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td><?= $is_mock ? $m['p1'] : $m['test_avg_40'] ?></td>
                <td><?= $is_mock ? $m['p2'] : $m['exam_60'] ?></td>
                <td class="fw-bold"><?= $m['final_score'] ?></td>
                <td class="fw-bold" style="color: <?= $m['grade_info'][2] ?>;"><?= $m['grade_info'][0] ?></td>
                <td class="small"><?= $m['grade_info'][1] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold bg-light">
                <td class="text-start">SUMMARY</td>
                <td colspan="2">AVG: <?= number_format($average, 1) ?>%</td>
                <td>GD: <?= $avg_grade ?></td>
                <td colspan="2">DIV: <?= $division ?></td>
            </tr>
        </tbody>
    </table>

    <div class="mt-3">
        <p class="fw-bold mb-0 small text-uppercase border-bottom">Class Teacher's Comments:</p>
        <div class="comment-box"><?= $teacher_comment ?></div>
        
        <p class="fw-bold mb-0 small text-uppercase border-bottom">Head of School's Comments:</p>
        <div class="comment-box"><?= $head_comment ?></div>
    </div>

    <div class="row mt-3 align-items-end">
        <div class="col-5">
            <table class="grade-summary-table">
                <thead><tr><th colspan="2">GRADE SUMMARY</th></tr></thead>
                <tbody>
                    <tr><td>A (80 - 100) : Excellent</td><td>B (70 - 79) : Very Good</td></tr>
                    <tr><td>C (60 - 69) : Good</td><td>D (50 - 59) : Satisfactory</td></tr>
                    <tr><td>F (00 - 49) : Fail</td><td>Pass Mark: 50%</td></tr>
                </tbody>
            </table>
        </div>
        <div class="col-7">
            <div class="row text-center">
                <div class="col-6">
                    <p class="border-top border-dark pt-1 fw-bold small mb-0">CLASS TEACHER</p>
                </div>
                <div class="col-6">
                    <p class="border-top border-dark pt-1 fw-bold small mb-0">HEAD OF SCHOOL</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>