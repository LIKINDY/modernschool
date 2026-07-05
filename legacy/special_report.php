<?php
session_start();
include('db_config.php');

// Receive parameters
$assignment_id = $_GET['assignment_id'] ?? '';
$year = $_GET['year'] ?? '2025/2026';
$stream = $_GET['stream'] ?? '';
$term = $_GET['term'] ?? 'Special 1'; 

// 1. Fetch class_name
$class_query = $conn->query("SELECT class_name FROM subject_assignments WHERE id = '$assignment_id'");
$class_data = $class_query->fetch_assoc();
$class_name = $class_data['class_name'] ?? '';

if (empty($class_name)) {
    echo "<div class='text-center mt-5'>Data not found. Please select a filter first.</div>";
    exit();
}

/** * PRE-CALCULATE RANKINGS
 * Tunatafuta wastani wa kila mwanafunzi aliyemo kwenye darasa hili na stream hii
 */
$rank_query = "SELECT id FROM students WHERE LOWER(TRIM(class_name)) = LOWER(TRIM('$class_name')) 
               AND LOWER(TRIM(stream)) = LOWER(TRIM('$stream')) AND status != 'deleted'";
$all_students_in_class = $conn->query($rank_query);

$rankings = [];
while($row = $all_students_in_class->fetch_assoc()) {
    $sid = $row['id'];
    if(strpos(strtolower($term), 'annual') !== false) {
        // Kwa Annual tunatafuta wastani wa Special 1 na Special 2
        $m_sql = "SELECT AVG(total_100) as s_avg FROM marks WHERE student_id = '$sid' AND year = '$year' AND (term = 'Special 1' OR term = 'Special 2')";
    } else {
        $m_sql = "SELECT AVG(total_100) as s_avg FROM marks WHERE student_id = '$sid' AND year = '$year' AND term = '$term'";
    }
    $res = $conn->query($m_sql)->fetch_assoc();
    $rankings[$sid] = (float)($res['s_avg'] ?? 0);
}
arsort($rankings); // Panga kuanzia mwenye wastani mkubwa kwenda mdogo
$total_students = count($rankings);

// 2. Fetch students for display
$students_query = "SELECT * FROM students WHERE LOWER(TRIM(class_name)) = LOWER(TRIM('$class_name')) 
                   AND LOWER(TRIM(stream)) = LOWER(TRIM('$stream')) 
                   AND status != 'deleted' ORDER BY fullname ASC";
$students_res = $conn->query($students_query);

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

function getGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}

// Function ya kuweka 1st, 2nd, 3rd n.k
function getOrdinal($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) return $number. 'th';
    else return $number. $ends[$number % 10];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
        .report-paper { width: 210mm; margin: auto; padding: 25px; border: 1px solid #000; position: relative; min-height: 297mm; page-break-after: always; }
        .school-logo { width: 100px; height: 100px; object-fit: contain; position: absolute; left: 25px; top: 25px; }
        .student-photo { width: 100px; height: 110px; object-fit: cover; border: 1px solid #000; position: absolute; right: 25px; top: 25px; }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; margin-top: 10px; }
        .school-name { font-size: 24px; font-weight: 900; text-transform: uppercase; margin-bottom: 0; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 5px; }
        
        .comment-box { 
            min-height: 65px; 
            border-bottom: 1px dotted #000; 
            padding-top: 5px; 
            font-size: 14px;
            font-style: italic;
            text-transform: none; 
            line-height: 1.4;
        }
        .comment-title { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; margin-bottom: 5px; }
        .grade-summary-table { width: 300px; float: right; border: 1px solid #000; margin-top: 15px; }
        .grade-summary-table td { border: 1px solid #000; padding: 3px 8px; font-size: 12px; font-weight: bold; }
        
        @media print { .no-print { display: none; } .report-paper { border: none; padding: 0; } }
    </style>
</head>
<body>

<div class="text-center no-print py-3">
    <button onclick="window.print()" class="btn btn-dark btn-sm px-4">PRINT ALL REPORTS</button>
</div>

<?php 
if($students_res->num_rows > 0):
    while($student = $students_res->fetch_assoc()): 
        $student_id = $student['id'];
        $fname = ucwords(strtolower(explode(' ', $student['fullname'])[0]));

        // Tafuta Position ya mwanafunzi huyu
        $student_avg = $rankings[$student_id] ?? 0;
        $temp_ranks = array_values($rankings);
        $position = array_search($student_avg, $temp_ranks) + 1;
        
        // Subject Logic
        if(strpos(strtolower($term), 'annual') !== false) {
            $marks_sql = "SELECT s.subject_name, s.id as sub_id,
                         (SELECT total_100 FROM marks WHERE student_id = '$student_id' AND subject_id = s.id AND term = 'Special 1' AND year = '$year') as s1_val,
                         (SELECT total_100 FROM marks WHERE student_id = '$student_id' AND subject_id = s.id AND term = 'Special 2' AND year = '$year') as s2_val
                         FROM subjects s
                         JOIN marks m ON s.id = m.subject_id
                         WHERE m.student_id = '$student_id' AND m.year = '$year'
                         GROUP BY s.id HAVING (s1_val > 0 OR s2_val > 0)";
        } else {
            $marks_sql = "SELECT s.subject_name, m.monthly_1, m.exam_60, m.total_100, m.grade
                          FROM marks m 
                          JOIN subjects s ON m.subject_id = s.id 
                          WHERE m.student_id = '$student_id' 
                          AND m.year = '$year' 
                          AND m.term = '$term'
                          AND (m.total_100 > 0 OR m.total_100 IS NOT NULL)";
        }

        $marks_res = $conn->query($marks_sql);
        $total_marks = 0; $subject_count = 0;
        $marks_data = [];
        $low_subjects = []; 

        while($row = $marks_res->fetch_assoc()){
            if(strpos(strtolower($term), 'annual') !== false) {
                $score = (($row['s1_val'] ?? 0) * 0.4) + (($row['s2_val'] ?? 0) * 0.6);
            } else {
                $score = $row['total_100'];
            }
            $row['calculated_score'] = $score;
            $marks_data[] = $row;
            $total_marks += $score;
            $subject_count++;

            if($score < 60){
                $low_subjects[] = strtoupper($row['subject_name']);
            }
        }

        $avg = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;
        $avg_grade = getGrade($avg)[0];

        // Zile Comment zako ulizotaka zibaki
        $teacher_comment = "";
        $head_comment = "";

        if($avg_grade == 'A' || $avg_grade == 'B'){
            $teacher_comment = "Congratulations $fname for your excellent results. You have shown great effort in your studies; continue to work hard to achieve your goals.";
            $head_comment = "These results are very satisfactory. The school management congratulates you; stay focused and keep increasing your efforts every day.";
        } 
        elseif($avg_grade == 'C'){
            $teacher_comment = "Your results are fair but not very strong. You must ensure you put in more effort next term to improve your average.";
            $head_comment = "Your average performance needs improvement. Parents are advised to supervise your studies more closely to enhance your efficiency.";
        }
        else { 
            $teacher_comment = "You have failed to meet the required performance standards. Your efforts must double starting now to rescue your academic progress.";
            $head_comment = "This is a poor performance. The student requires a major academic shift and very close supervision from both parents and teachers.";
        }

        $subject_remark = "";
        if(!empty($low_subjects)){
            $list = implode(", ", $low_subjects);
            $subject_remark = " You must specifically increase your efforts in $list to ensure better performance in those subjects.";
        }
?>

<div class="report-paper">
    <?php if(!empty($school['logo'])): ?>
        <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <?php endif; ?>

    <?php if(!empty($student['photo'])): ?>
        <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128'">
    <?php else: ?>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128" class="student-photo">
    <?php endif; ?>

    <div class="header-section text-center">
        <h2 class="school-name"><?= $school['school_name'] ?></h2>
        <p class="mb-0 fw-bold">P.O.BOX <?= $school['pobox'] ?? '34' ?>, PHONE <?= $school['phone'] ?? '0625415484' ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?? 'Zanzibar' ?></p>
        <h5 class="mt-4 fw-bold text-uppercase text-decoration-underline"><?= strtoupper($term) ?> PROGRESS REPORT</h5>
        <h6 class="fw-bold">PRIMARY</h6>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">STUDENT'S NAME: <strong><?= $student['fullname'] ?></strong> &nbsp;&nbsp; ATTENDANCE: ________</p>
        <p class="mb-1">CLASS ACADEMIC YEAR: <strong><?= $year ?> <?= $class_name ?> <?= $stream ?></strong>
            <span class="float-end">STUDENT ID: <strong><?= $student['id'] ?></strong></span>
        </p>
        <p class="mb-1">POSITION IN CLASS: <strong><?= getOrdinal($position) ?></strong> OUT OF <strong><?= $total_students ?></strong></p>
    </div>

    <table class="table table-marks w-100 mb-2">
        <thead class="text-uppercase" style="background: #f2f2f2;">
            <tr>
                <th class="text-start" width="25%">Subjects</th>
                <?php if(strpos(strtolower($term), 'annual') !== false): ?>
                    <th>Special 1 (40%)</th>
                    <th>Special 2 (60%)</th>
                <?php else: ?>
                    <th>Test (M1)</th>
                    <th>Exam</th>
                <?php endif; ?>
                <th>Total 100%</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($marks_data as $m): $g = getGrade($m['calculated_score']); ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <?php if(strpos(strtolower($term), 'annual') !== false): ?>
                    <td><?= number_format(($m['s1_val'] ?? 0) * 0.4, 1) ?></td>
                    <td><?= number_format(($m['s2_val'] ?? 0) * 0.6, 1) ?></td>
                <?php else: ?>
                    <td><?= $m['monthly_1'] ?? '0' ?></td>
                    <td><?= $m['exam_60'] ?? '0' ?></td>
                <?php endif; ?>
                <td class="fw-bold"><?= number_format($m['calculated_score'], 0) ?></td>
                <td><?= $g[0] ?></td>
                <td class="small"><?= $g[1] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold">
                <td class="text-start">TOTAL MARKS</td>
                <td colspan="2"></td>
                <td><?= number_format($total_marks, 0) ?></td>
                <td class="text-end">OUT OF:</td>
                <td><?= $subject_count * 100 ?></td>
            </tr>
            <tr class="fw-bold bg-light">
                <td class="text-start">AVERAGE</td>
                <td colspan="2"></td>
                <td><?= number_format($avg, 2) ?>%</td>
                <td>GRADE:</td>
                <td><?= $avg_grade ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4" style="font-size: 13px;">
        <div class="col-6 pe-4">
            <div class="comment-title">Teacher's Comments</div>
            <div class="comment-box">
                <?= $teacher_comment . $subject_remark ?>
            </div>
            <p class="small mt-2">Class Teacher: <strong>LIKINDY ISMAIL</strong></p>
        </div>
        <div class="col-6">
            <div class="comment-title">Head's Comments</div>
            <div class="comment-box">
                <?= $head_comment ?>
            </div>
        </div>
    </div>

    <table class="grade-summary-table text-uppercase">
        <tr><td>Grade</td><td>Percentage Range</td></tr>
        <tr><td>A</td><td>80 --- 100 ---> Excellent</td></tr>
        <tr><td>B</td><td>70 --- 79  ---> Very Good</td></tr>
        <tr><td>C</td><td>60 --- 69  ---> Good</td></tr>
        <tr><td>D</td><td>50 --- 59  ---> Pass</td></tr>
        <tr><td>F</td><td>0  --- 49  ---> Fail</td></tr>
    </table>
    
    <div class="clearfix"></div>
    <p class="mt-2 fw-bold text-uppercase" style="font-size: 11px;">Passing Marks: 50</p>

    <div class="row mt-5 pt-4 text-center text-uppercase fw-bold" style="font-size: 11px;">
        <div class="col-4 border-top pt-2">Class Teacher's Signature</div>
        <div class="col-4 border-top pt-2">Head's Signature</div>
        <div class="col-4 border-top pt-2">Parent's Signature</div>
    </div>
</div>

<?php endwhile; endif; ?>

</body>
</html>