<?php
session_start();
include('db_config.php');

$student_id = $_GET['student_id'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$student_id || !$year || !$term) { echo "Missing parameters."; exit; }

// 1. Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch Student Info
$student_query = "SELECT * FROM students WHERE id = '$student_id'";
$student = $conn->query($student_query)->fetch_assoc();
$class_name = $student['class_name'];
$stream = $student['stream'] ?? '-';
$fname = $student['fullname'];

// 3. Determine Report Type
$is_midterm = (strpos(strtolower($term), 'midterm') !== false);
$score_column = $is_midterm ? 'monthly_1' : 'total_100';

function getOlevelGrade($score) {
    if ($score >= 80) return ['A', 'Excellent', '#27ae60'];
    if ($score >= 70) return ['B', 'Very Good', '#2980b9'];
    if ($score >= 60) return ['C', 'Good', '#f1c40f'];
    if ($score >= 50) return ['D', 'Satisfactory', '#e67e22'];
    return ['F', 'Fail', '#c0392b'];
}

// 4. Fetch Marks and Calculations
$marks_sql = "SELECT m.*, s.subject_name 
              FROM marks m 
              JOIN subjects s ON m.subject_id = s.id 
              WHERE m.student_id = '$student_id' AND m.year = '$year' AND m.term = '$term'
              ORDER BY s.subject_name ASC";
$marks_res = $conn->query($marks_sql);

$marks_data = [];
$total_marks = 0;
$subject_count = 0;
$all_points = []; 
$weak_subjects = []; // Array ya kuhifadhi masomo dhaifu (D au F)

while ($row = $marks_res->fetch_assoc()) {
    $sub_id = $row['subject_id'];
    
    // Highest Mark Logic
    $high_sql = "SELECT MAX(m.$score_column) as max_mark FROM marks m 
                 JOIN students st ON m.student_id = st.id 
                 WHERE m.subject_id = '$sub_id' AND m.year = '$year' AND m.term = '$term' AND st.class_name = '$class_name'";
    $high_res = $conn->query($high_sql);
    $high_row = $high_res->fetch_assoc();
    
    $row['highest_in_class'] = $high_row['max_mark'];
    $current_score = $is_midterm ? $row['monthly_1'] : $row['total_100'];
    
    // Grade check for specific subject
    $grade_data = getOlevelGrade($current_score);
    if ($grade_data[0] == 'D' || $grade_data[0] == 'F') {
        $weak_subjects[] = $row['subject_name']; // Ongeza jina la somo kwenye list
    }

    // Points calculation (Best 7)
    if ($current_score >= 75) $pts = 1;
    elseif ($current_score >= 65) $pts = 2;
    elseif ($current_score >= 50) $pts = 3; 
    elseif ($current_score >= 35) $pts = 4;
    else $pts = 5;
    $all_points[] = $pts;

    $marks_data[] = $row;
    $total_marks += $current_score;
    $subject_count++;
}

$average = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;

// Division Calculation
sort($all_points);
$best_seven = array_slice($all_points, 0, 7);
$total_points = array_sum($best_seven);

if ($total_points <= 17) $division = "I";
elseif ($total_points <= 21) $division = "II";
elseif ($total_points <= 25) $division = "III";
elseif ($total_points <= 33) $division = "IV";
else $division = "0";

$avg_info = getOlevelGrade($average);
$avg_grade = $avg_info[0];

// --- COMMENT LOGIC ---
$teacher_comment = "";
$head_comment = "";

// Maoni ya mwalimu wa darasa
if($avg_grade == 'A' || $avg_grade == 'B'){
    $teacher_comment = "Congratulations $fname for your excellent results. You have shown great effort in your studies; continue to work hard to achieve your goals.";
    $head_comment = "These results are very satisfactory. The school management congratulates you; stay focused and keep increasing your efforts every day.";
} 
elseif($avg_grade == 'C'){
    $teacher_comment = "Dear $fname, your results are fair but not very strong. You must ensure you put in more effort next term to improve your average.";
    $head_comment = "Your average performance needs improvement. Parents are advised to supervise your studies more closely to enhance efficiency.";
}
else { 
    $teacher_comment = "You have failed to meet the required performance standards $fname. Your efforts must double starting now to rescue your academic progress.";
    $head_comment = "This is a poor performance. The student requires a major academic shift and very close supervision from both parents and teachers.";
}

// NYONGEZA: Kama ana masomo ya D au F, ongeza sentensi kwenye komenti ya Head
if (!empty($weak_subjects)) {
    $subject_list = implode(", ", $weak_subjects);
    $head_comment .= " Specifically, you must put more effort into: **" . $subject_list . "** to improve your overall performance.";
}

// Overall Rank
$overall_rank_sql = "SELECT m.student_id, SUM(m.$score_column) as total_score 
                     FROM marks m 
                     JOIN students st ON m.student_id = st.id 
                     WHERE m.year = '$year' AND m.term = '$term' AND st.class_name = '$class_name' 
                     GROUP BY m.student_id ORDER BY total_score DESC";
$rank_res = $conn->query($overall_rank_sql);
$total_students_in_class = $rank_res->num_rows;
$overall_pos = 0; $counter = 0;
while($r = $rank_res->fetch_assoc()){
    $counter++;
    if($r['student_id'] == $student_id){ $overall_pos = $counter; break; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card - <?= $fname ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f4f4; font-family: 'Times New Roman', serif; font-size: 14px; }
        .report-paper { width: 210mm; min-height: 297mm; margin: 10px auto; padding: 25px; background: #fff; border: 2px solid #000; }
        .header-section { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 15px; }
        .school-logo { width: 85px; height: 85px; }
        .student-photo { width: 95px; height: 105px; border: 1px solid #000; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; padding: 5px; text-align: center; }
        .division-box { border: 2px solid #000; padding: 8px; background: #f9f9f9; font-size: 18px; display: inline-block; font-weight: bold; }
        .comment-area { min-height: 50px; border-bottom: 1px dotted #000; font-style: italic; margin-bottom: 10px; padding-top: 5px; line-height: 1.4; }
        .grade-summary-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .grade-summary-table td, .grade-summary-table th { border: 1px solid #000; padding: 4px; font-size: 12px; text-align: center; }
        @media print { .no-print { display: none; } .report-paper { border: none; margin: 0; width: 100%; } @page { size: A4; margin: 10mm; } }
    </style>
</head>
<body>

<div class="text-center no-print py-2">
    <button onclick="window.print()" class="btn btn-primary">PRINT REPORT CARD</button>
</div>

<div class="report-paper">
    <div class="header-section text-center">
        <div class="row align-items-center">
            <div class="col-2 text-start"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
            <div class="col-8">
                <h2 class="fw-bold text-uppercase mb-0"><?= $school['school_name'] ?></h2>
                <p class="mb-0 fw-bold"><?= $school['address'] ?> | P.O.BOX <?= $school['pobox'] ?></p>
                <h5 class="mt-2 fw-bold text-decoration-underline"><?= strtoupper($term) ?> ACADEMIC REPORT CARD - <?= $year ?></h5>
            </div>
            <div class="col-2 text-end"><img src="uploads/students/<?= $student['photo'] ?>" class="student-photo"></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-7">
            <h6 class="mb-1">NAME: <strong><?= strtoupper($fname) ?></strong></h6>
            <h6 class="mb-1">CLASS: <strong><?= $class_name ?></strong> &nbsp; STREAM: <strong><?= $stream ?></strong></h6>
            <h6 class="mb-1">STUDENT ID: <strong><?= $student['student_id'] ?? $student['id'] ?></strong></h6>
        </div>
        <div class="col-5 text-end">
            <div class="division-box">
                DIVISION: <span class="text-primary"><?= $division ?> . <?= $total_points ?></span>
            </div>
            <p class="mt-1 fw-bold mb-0">POSITION: <?= $overall_pos ?> / <?= $total_students_in_class ?></p>
        </div>
    </div>

    <table class="table table-marks w-100 mb-3">
        <thead style="background: #eee;">
            <tr>
                <th class="text-start">SUBJECTS</th>
                <?php if($is_midterm): ?>
                    <th>SCORE (100%)</th>
                <?php else: ?>
                    <th>TEST (40)</th>
                    <th>EXAM (60)</th>
                    <th>TOTAL (100)</th>
                <?php endif; ?>
                <th>GRADE</th>
                <th>REMARKS</th>
                <th>HIGHEST</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): 
                $score = $is_midterm ? $m['monthly_1'] : $m['total_100'];
                $grade = getOlevelGrade($score);
            ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <?php if($is_midterm): ?>
                    <td class="fw-bold"><?= $score ?></td>
                <?php else: ?>
                    <td><?= $m['test_avg_40'] ?></td>
                    <td><?= $m['exam_60'] ?></td>
                    <td class="fw-bold"><?= $score ?></td>
                <?php endif; ?>
                <td class="fw-bold" style="color: <?= $grade[2] ?>;"><?= $grade[0] ?></td>
                <td style="font-size: 11px;"><?= $grade[1] ?></td>
                <td><?= $m['highest_in_class'] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td class="text-start">TOTALS & AVERAGE</td>
                <td colspan="<?= $is_midterm ? '1' : '3' ?>"><?= $total_marks ?></td>
                <td><?= number_format($average, 1) ?>%</td>
                <td colspan="2">AVG GRADE: <?= $avg_grade ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row">
        <div class="col-12">
            <h6 class="fw-bold text-uppercase border-bottom mb-1">Class Teacher's Comments:</h6>
            <div class="comment-area"><?= $teacher_comment ?></div>
            
            <h6 class="fw-bold text-uppercase border-bottom mb-1">Head of School's Comments:</h6>
            <div class="comment-area"><?= $head_comment ?></div>
        </div>
    </div>

    <div class="row mt-3 align-items-center">
        <div class="col-5">
            <table class="grade-summary-table">
                <thead><tr style="background:#eee;"><th colspan="2">GRADE SUMMARY</th></tr></thead>
                <tbody>
                    <tr><td>A (80 - 100) : Excellent</td><td>B (70 - 79) : Very Good</td></tr>
                    <tr><td>C (60 - 69) : Good (Pass)</td><td>D (50 - 59) : Satisfactory</td></tr>
                    <tr><td colspan="2">F (00 - 49) : Fail</td></tr>
                    <tr><td colspan="2">Passing Marks 50</td></tr>
                </tbody>
            </table>
        </div>
        <div class="col-7">
            <div class="row text-center fw-bold" style="margin-top: 15px;">
                <div class="col-6"><p class="border-top pt-2">Class Teacher Signature</p></div>
                <div class="col-6"><p class="border-top pt-2">Head of School Signature</p></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>