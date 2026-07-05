<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || !isset($_GET['student_id'])) {
    die("Unauthorized access.");
}

$student_id = $conn->real_escape_string($_GET['student_id']);
$year = $_GET['year'] ?? date('2025/2026');

// 1. Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch Student Info
$student = $conn->query("SELECT * FROM students WHERE id = '$student_id'")->fetch_assoc();
if (!$student) die("Student not found.");
$class_name = $student['class_name'];

// Function for Grading
function getGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}

// 3. Logic ya Kupata Nafasi za Masomo na Alama
$subjects_res = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");
$marks_data = [];
$total_final = 0;
$subject_count = 0;

while ($sub = $subjects_res->fetch_assoc()) {
    $sid = $sub['id'];

    // T1 (40%) Score
    $t1_q = $conn->query("SELECT total_100 FROM marks WHERE student_id='$student_id' AND subject_id='$sid' AND term='Term 1' AND year='$year'")->fetch_assoc();
    $t1_score = ($t1_q['total_100'] ?? 0) * 0.4;

    // T2 (60%) Score
    $t2_q = $conn->query("SELECT total_100 FROM marks WHERE student_id='$student_id' AND subject_id='$sid' AND term='Term 2' AND year='$year'")->fetch_assoc();
    $t2_score = ($t2_q['total_100'] ?? 0) * 0.6;

    $final_score = $t1_score + $t2_score;

    if ($final_score > 0) {
        // Tafuta nafasi ya somo (Subject Position) kwa mwaka mzima
        // Hapa tunatafuta wastani wa wanafunzi wote kwa somo hili
        $all_students_sub = $conn->query("
            SELECT student_id, 
            SUM(CASE WHEN term='Term 1' THEN total_100 * 0.4 ELSE 0 END + 
                CASE WHEN term='Term 2' THEN total_100 * 0.6 ELSE 0 END) as annual_sub_score
            FROM marks 
            WHERE subject_id='$sid' AND year='$year'
            GROUP BY student_id ORDER BY annual_sub_score DESC
        ");
        
        $sub_pos = 1;
        $highest_mark = 0;
        while($r = $all_students_sub->fetch_assoc()){
            if($sub_pos == 1) $highest_mark = $r['annual_sub_score'];
            if($r['student_id'] == $student_id) break;
            $sub_pos++;
        }

        $marks_data[] = [
            'name' => $sub['subject_name'],
            't1' => $t1_score,
            't2' => $t2_score,
            'final' => $final_score,
            'pos' => $sub_pos,
            'high' => $highest_mark
        ];
        $total_final += $final_score;
        $subject_count++;
    }
}

$average = ($subject_count > 0) ? ($total_final / $subject_count) : 0;
$final_grade = getGrade($average);

// 4. Overall Class Position (Annual)
// 4. Overall Class Position (Annual) - Fixed Ambiguous Column
$overall_rank_q = $conn->query("
    SELECT m.student_id, 
    AVG(CASE WHEN m.term='Term 1' THEN m.total_100 * 0.4 ELSE 0 END + 
        CASE WHEN m.term='Term 2' THEN m.total_100 * 0.6 ELSE 0 END) as annual_avg
    FROM marks m
    JOIN students s ON m.student_id = s.id
    WHERE s.class_name = '$class_name' AND m.year = '$year'
    GROUP BY m.student_id ORDER BY annual_avg DESC
");
$overall_pos = 0;
$total_students = $overall_rank_q->num_rows;
$pos_counter = 0;
while($rank = $overall_rank_q->fetch_assoc()){
    $pos_counter++;
    if($rank['student_id'] == $student_id){ $overall_pos = $pos_counter; break; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Annual Report - <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
        .report-paper { width: 210mm; margin: auto; padding: 25px; border: 1px solid #000; position: relative; min-height: 297mm; }
        .school-logo { width: 100px; height: 100px; object-fit: contain; position: absolute; left: 25px; top: 25px; }
        .student-photo { width: 100px; height: 110px; object-fit: cover; border: 1px solid #000; position: absolute; right: 25px; top: 25px; }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; margin-top: 10px; }
        .school-name { font-size: 24px; font-weight: 900; text-transform: uppercase; margin-bottom: 0; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 5px; }
        .comment-box { min-height: 60px; border-bottom: 1px dotted #000; padding-top: 5px; font-size: 14px; font-style: italic; }
        .comment-title { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; margin-bottom: 5px; }
        .grade-summary-table { width: 300px; float: right; border: 1px solid #000; margin-top: 15px; }
        .grade-summary-table td { border: 1px solid #000; padding: 3px 8px; font-size: 12px; font-weight: bold; }
        @media print { .no-print { display: none; } .report-paper { border: none; padding: 0; } }
    </style>
</head>
<body>

<div class="text-center no-print py-3">
    <button onclick="window.print()" class="btn btn-dark btn-sm px-4">PRINT ANNUAL REPORT</button>
</div>

<div class="report-paper">
    <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128'">

    <div class="header-section text-center">
        <h2 class="school-name"><?= $school['school_name'] ?></h2>
        <p class="mb-0 fw-bold"><?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?></p>
        <h5 class="mt-4 fw-bold text-uppercase text-decoration-underline">ANNUAL PROGRESS REPORT CARD</h5>
        <h6 class="fw-bold">PRIMARY SCHOOL</h6>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">STUDENT'S NAME: <strong><?= strtoupper($student['fullname']) ?></strong></p>
        <p class="mb-1">CLASS ACADEMIC YEAR: <strong><?= $year ?> <?= $student['class_name'] ?></strong>
           <span class="float-end">ANNUAL POSITION: <strong><?= $overall_pos ?></strong> OUT OF <strong><?= $total_students ?></strong></span>
        </p>
    </div>

    <table class="table table-marks w-100 mb-2">
        <thead class="text-uppercase" style="background: #f2f2f2;">
            <tr>
                <th class="text-start" width="25%">Subjects</th>
                <th>T1 (40%)</th>
                <th>T2 (60%)</th>
                <th>Final (100%)</th>
                <th>Grade</th>
                <th>Pos</th>
                <th>Remarks</th>
                <th>Highest</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): $g = getGrade($m['final']); ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['name']) ?></td>
                <td><?= number_format($m['t1'], 1) ?></td>
                <td><?= number_format($m['t2'], 1) ?></td>
                <td class="fw-bold"><?= number_format($m['final'], 0) ?></td>
                <td class="fw-bold"><?= $g[0] ?></td>
                <td><?= $m['pos'] ?></td>
                <td class="small"><?= $g[1] ?></td>
                <td class="fw-bold"><?= number_format($m['high'], 0) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold bg-light">
                <td class="text-start">ANNUAL AVERAGE</td>
                <td colspan="2"></td>
                <td class="fs-6"><?= number_format($average, 1) ?>%</td>
                <td><?= $final_grade[0] ?></td>
                <td><?= $overall_pos ?></td>
                <td colspan="2"><?= $final_grade[1] ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-6 pe-4">
            <div class="comment-title">Teacher's Comments (Annual)</div>
            <div class="comment-box">
                Overall performance for the year is <?= strtolower($final_grade[1]) ?>. 
                Promoted to next class. Keep up the effort.
            </div>
            <p class="small mt-2">Class Teacher: <strong><?= $school['headmaster'] ?></strong></p>
        </div>
        <div class="col-6">
            <div class="comment-title">Head's Comments</div>
            <div class="comment-box">
                A good annual record. Consistency is key for next year's challenges.
            </div>
        </div>
    </div>

    <table class="grade-summary-table text-uppercase">
        <tr><td>Grade</td><td>Range</td><td>Remarks</td></tr>
        <tr><td>A</td><td>80 - 100</td><td>Excellent</td></tr>
        <tr><td>B</td><td>70 - 79</td><td>Very Good</td></tr>
        <tr><td>C</td><td>60 - 69</td><td>Good</td></tr>
        <tr><td>D</td><td>50 - 59</td><td>Pass</td></tr>
        <tr><td>F</td><td>0 - 49</td><td>Fail</td></tr>
    </table>
    
    <div class="clearfix"></div>

    <div class="row mt-5 pt-4 text-center text-uppercase fw-bold" style="font-size: 11px;">
        <div class="col-4 border-top pt-2">Class Teacher</div>
        <div class="col-4 border-top pt-2">Headteacher</div>
        <div class="col-4 border-top pt-2">Parent/Guardian</div>
    </div>
</div>

</body>
</html>