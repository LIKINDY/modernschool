<?php
session_start();
include('db_config.php');

// 1. POKEA VIGEZO KUTOKA KWENYE FILTER
$year = $_GET['academic_year'] ?? $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';
$class_name = $_GET['class_name'] ?? '';
$stream = $_GET['stream'] ?? '';

if (empty($class_name) || empty($year) || empty($term)) {
    echo "<div style='text-align:center; margin-top:50px;'>
            <h3>Please select all filters!</h3>
            <a href='javascript:history.back()'>Go Back</a>
          </div>";
    exit();
}

// 2. Function ya Grading Scale
function getSecondaryGrade($mark) {
    if ($mark >= 80) return ['A', 1, 'EXCELLENT'];
    if ($mark >= 70) return ['B', 2, 'VERY GOOD'];
    if ($mark >= 60) return ['C', 3, 'GOOD (PASS)'];
    if ($mark >= 35) return ['D', 4, 'SATISFACTORY'];
    return ['F', 5, 'FAIL'];
}

// 3. Function ya Division (Best 7)
function getDivision($points, $sub_count) {
    if ($sub_count < 7) return "INCOMPLETE"; 
    if ($points >= 7 && $points <= 17) return "I";
    if ($points >= 18 && $points <= 21) return "II";
    if ($points >= 22 && $points <= 25) return "III";
    if ($points >= 26 && $points <= 33) return "IV";
    return "0";
}

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
$students = $conn->query("SELECT * FROM students WHERE class_name = '$class_name' AND stream = '$stream' AND status != 'deleted' ORDER BY fullname ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secondary Report - <?= htmlspecialchars($class_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Times New Roman', serif; }
        .report-card { 
            width: 210mm; min-height: 290mm; padding: 15mm; margin: 20px auto; 
            background: white; border: 1px solid #000; position: relative;
            page-break-after: always;
        }
        .header-box { border-bottom: 4px double #000; margin-bottom: 20px; padding-bottom: 10px; }
        .school-logo { max-width: 90px; }
        .table-results { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-results th, .table-results td { border: 1px solid #000; padding: 6px; text-align: center; }
        .summary-table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        .summary-table td { border: 2px solid #000; padding: 8px; font-weight: bold; text-align: center; }
        .grade-summary-mini { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 10px; }
        .grade-summary-mini th, .grade-summary-mini td { border: 1px solid #000; padding: 2px 5px; text-align: center; }
        .bg-grey { background-color: #e9ecef !important; }
        .comment-area { border-bottom: 1px dotted #000; min-height: 35px; margin-top: 5px; font-style: italic; font-size: 13px; }
        @media print { .no-print { display: none; } .report-card { margin: 0; border: none; } }
    </style>
</head>
<body>

<div class="no-print text-center py-4">
    <button onclick="window.print()" class="btn btn-primary btn-lg shadow">PRINT ALL REPORTS</button>
</div>

<?php 
if($students && $students->num_rows > 0):
    while($st = $students->fetch_assoc()):
        $student_db_id = $st['id'];
        $total_marks = 0;
        $all_points = [];
        
        $marks_sql = "SELECT s.subject_name, m.total_100 
                      FROM subjects s
                      JOIN subject_assignments sa ON s.id = sa.subject_id
                      LEFT JOIN marks m ON s.id = m.subject_id 
                        AND m.student_id = '$student_db_id' 
                        AND m.term = '$term' 
                        AND m.year = '$year'
                      WHERE sa.class_name = '$class_name'
                      GROUP BY s.id";
        $marks_query = $conn->query($marks_sql);
?>

<div class="report-card">
    <div class="header-box">
        <div class="row align-items-center">
            <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo" onerror="this.src='https://via.placeholder.com/90'"></div>
            <div class="col-8 text-center">
                <h1 class="fw-bold mb-0"><?= strtoupper($school['school_name']) ?></h1>
                <p class="mb-0 fw-bold"><?= $school['address'] ?> | P.O.BOX <?= $school['pobox'] ?></p>
                <h4 class="mt-3 text-decoration-underline">STUDENT PROGRESS REPORT CARD</h4>
            </div>
            <div class="col-2 text-end">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($st['fullname']) ?>&size=80" style="border: 1px solid #000">
            </div>
        </div>
    </div>

    <div class="row mb-3 mt-4">
        <div class="col-7">
            NAME: <strong><?= strtoupper($st['fullname']) ?></strong><br>
            CLASS: <strong><?= $class_name ?> - <?= $stream ?></strong>
        </div>
        <div class="col-5 text-end">
            TERM: <strong><?= strtoupper($term) ?></strong><br>
            YEAR: <strong><?= $year ?></strong>
        </div>
    </div>

    <table class="table-results">
        <thead>
            <tr class="bg-grey">
                <th class="text-start">SUBJECTS</th>
                <th width="15%">SCORE (%)</th>
                <th width="12%">GRADE</th>
                <th width="12%">POINTS</th>
                <th width="25%">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if($marks_query && $marks_query->num_rows > 0):
                while($m = $marks_query->fetch_assoc()):
                    $score = ($m['total_100'] !== null) ? (float)$m['total_100'] : -1;
                    if($score >= 0) {
                        $grade_info = getSecondaryGrade($score);
                        $total_marks += $score;
                        $all_points[] = $grade_info[1];
                        $disp_score = number_format($score, 0);
                        $disp_grade = $grade_info[0];
                        $disp_point = $grade_info[1];
                        $disp_remark = $grade_info[2];
                    } else {
                        $disp_score = '-'; $disp_grade = '-'; $disp_point = '-'; $disp_remark = 'ABSENT';
                    }
            ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td><?= $disp_score ?></td>
                <td class="fw-bold"><?= $disp_grade ?></td>
                <td><?= $disp_point ?></td>
                <td style="font-size: 11px;"><?= $disp_remark ?></td>
            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>

    <?php 
        sort($all_points); 
        $best_7 = array_slice($all_points, 0, 7); 
        $sum_points = array_sum($best_7);
        $count_subjects = count($all_points);
        $average = ($count_subjects > 0) ? ($total_marks / $count_subjects) : 0;
        $div = getDivision($sum_points, $count_subjects);

        // HAPA NDIPO COMMENT ZAKO NILIZOZIWEKA KWENYE SWITCH
        $teacher_comment = "";
        $head_comment = "";

        switch($div) {
            case "I":
                $teacher_comment = "Excellent performance! Congratulations on achieving Division I. Keep up the hard work.";
                $head_comment = "Top-tier results. Maintain this consistency and aim for even greater heights.";
                break;
            case "II":
                $teacher_comment = "A very good performance, but there is still room for improvement to reach Division I.";
                $head_comment = "Good job. Put in more effort in your weak subjects to secure a better position.";
                break;
            case "III":
                $teacher_comment = "Fair performance. You need to focus more on your studies to improve your grades.";
                $head_comment = "Average results. Significant effort is required in the next term to avoid a further drop.";
                break;
            default: // IV na 0
                $teacher_comment = "Poor performance. You have not met the required academic standards. Urgent change is needed.";
                $head_comment = "Unsatisfactory. You must double your efforts and seek academic assistance immediately.";
                break;
        }
    ?>

    <table class="summary-table">
        <tr class="bg-grey">
            <td>TOTAL MARKS</td>
            <td>AVERAGE %</td>
            <td>BEST 7 POINTS</td>
            <td style="background: #000; color: #fff;">DIVISION</td>
        </tr>
        <tr>
            <td><?= number_format($total_marks, 0) ?></td>
            <td><?= number_format($average, 1) ?>%</td>
            <td><?= ($count_subjects >= 7) ? $sum_points : 'N/A' ?></td>
            <td style="font-size: 22px;"><?= $div ?></td>
        </tr>
    </table>

    <div class="row mt-4">
        <div class="col-4">
            <h6 class="fw-bold small mb-1">GRADE SUMMARY:</h6>
            <table class="grade-summary-mini">
                <tr class="bg-grey"><th>Grade</th><th>Range</th><th>Remarks</th></tr>
                <tr><td>A</td><td>80 - 100</td><td>Excellent</td></tr>
                <tr><td>B</td><td>70 - 79</td><td>Very Good</td></tr>
                <tr><td>C</td><td>60 - 69</td><td>Good (PASS)</td></tr>
                <tr><td>D</td><td>35 - 49</td><td>Satisfactory</td></tr>
                <tr><td>F</td><td>00 - 34</td><td>Fail</td></tr>
            </table>
        </div>
        
        <div class="col-8">
            <div class="mb-2">
                <h6 class="fw-bold mb-0 small">CLASS TEACHER'S COMMENTS:</h6>
                <div class="comment-area"><?= $teacher_comment ?></div>
            </div>
            <div>
                <h6 class="fw-bold mb-0 small">HEAD OF SCHOOL'S COMMENTS:</h6>
                <div class="comment-area"><?= $head_comment ?></div>
            </div>
        </div>
    </div>

    <div class="row mt-5 text-center fw-bold small">
        <div class="col-4"><p class="border-top border-dark pt-1">Class Teacher Signature</p></div>
        <div class="col-4">
            <div class="mb-4">Official School Stamp</div>
            <p class="border-top border-dark pt-1">Head of School Signature</p>
        </div>
        <div class="col-4"><p class="border-top border-dark pt-1">Parent's Signature</p></div>
    </div>

    <div class="text-center mt-4 small text-muted italic">
        passing marsk 50
    </div>
</div>

<?php endwhile; 
else: ?>
    <div class="alert alert-warning m-5 text-center">No students found.</div>
<?php endif; ?>

</body>
</html>