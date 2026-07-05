<?php
session_start();
include('db_config.php');

// Receive parameters
$assignment_id = $_GET['assignment_id'] ?? '';
$year = $_GET['year'] ?? '2025/2026';
$stream = $_GET['stream'] ?? '';
$term = 'Annual'; // Imewekwa fixed kuwa Annual kwa ajili ya faili hili

// 1. Fetch class_name
$class_query = $conn->query("SELECT class_name FROM subject_assignments WHERE id = '$assignment_id'");
$class_data = $class_query->fetch_assoc();
$class_name = $class_data['class_name'] ?? '';

if (empty($class_name)) {
    echo "<div class='text-center mt-5'>Data not found. Please select a filter first.</div>";
    exit();
}

/** 1. PRE-CALCULATE RANKINGS (ANNUAL) **/
$rank_query = "SELECT id FROM students WHERE LOWER(TRIM(class_name)) = LOWER(TRIM('$class_name')) 
               AND LOWER(TRIM(stream)) = LOWER(TRIM('$stream')) AND status != 'deleted'";
$all_students_in_class = $conn->query($rank_query);

$rankings = [];
while($row = $all_students_in_class->fetch_assoc()) {
    $sid = $row['id'];
    // Annual Calculation: Special 1 (40%) + Special 2 (60%)
    $m_sql = "SELECT 
                AVG((CASE WHEN term = 'Special 1' THEN total_100 ELSE 0 END) * 0.4 + 
                    (CASE WHEN term = 'Special 2' THEN total_100 ELSE 0 END) * 0.6) as annual_avg 
              FROM marks WHERE student_id = '$sid' AND year = '$year'";
    
    $res = $conn->query($m_sql)->fetch_assoc();
    $rankings[$sid] = (float)($res['annual_avg'] ?? 0);
}
arsort($rankings); 
$total_students = count($rankings);

// 2. Fetch students for display
$students_res = $conn->query("SELECT * FROM students WHERE LOWER(TRIM(class_name)) = LOWER(TRIM('$class_name')) 
                              AND LOWER(TRIM(stream)) = LOWER(TRIM('$stream')) 
                              AND status != 'deleted' ORDER BY fullname ASC");

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

function getGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}

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
    <title>Annual Report - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
        .report-paper { width: 210mm; margin: auto; padding: 25px; border: 1px solid #000; position: relative; min-height: 297mm; page-break-after: always; }
        .school-logo { width: 100px; height: 100px; object-fit: contain; position: absolute; left: 25px; top: 25px; }
        .student-photo { width: 100px; height: 110px; object-fit: cover; border: 1px solid #000; position: absolute; right: 25px; top: 25px; }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; margin-top: 10px; }
        .school-name { font-size: 24px; font-weight: 900; text-transform: uppercase; margin-bottom: 0; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 5px; }
        .comment-box { min-height: 65px; border-bottom: 1px dotted #000; padding-top: 5px; font-size: 14px; font-style: italic; line-height: 1.4; }
        .comment-title { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; margin-bottom: 5px; }
        .grade-summary-table { width: 300px; float: right; border: 1px solid #000; margin-top: 15px; }
        .grade-summary-table td { border: 1px solid #000; padding: 3px 8px; font-size: 12px; font-weight: bold; }
        @media print { .no-print { display: none; } .report-paper { border: none; padding: 0; } }
    </style>
</head>
<body>

<div class="text-center no-print py-3">
    <button onclick="window.print()" class="btn btn-dark btn-sm px-4">PRINT ANNUAL REPORTS</button>
</div>

<?php 
if($students_res->num_rows > 0):
    while($student = $students_res->fetch_assoc()): 
        $student_id = $student['id'];
        $fname = ucwords(strtolower(explode(' ', $student['fullname'])[0]));

        // Current Student Position
        $student_avg = $rankings[$student_id] ?? 0;
        $temp_ranks = array_values($rankings);
        $position = array_search($student_avg, $temp_ranks) + 1;
        
        // Fetch Subject Marks for Annual
        $marks_sql = "SELECT s.subject_name, s.id as sub_id,
                      (SELECT total_100 FROM marks WHERE student_id = '$student_id' AND subject_id = s.id AND term = 'Special 1' AND year = '$year') as s1_val,
                      (SELECT total_100 FROM marks WHERE student_id = '$student_id' AND subject_id = s.id AND term = 'Special 2' AND year = '$year') as s2_val
                      FROM subjects s
                      JOIN marks m ON s.id = m.subject_id
                      WHERE m.student_id = '$student_id' AND m.year = '$year'
                      GROUP BY s.id HAVING (s1_val > 0 OR s2_val > 0)";

        $marks_res = $conn->query($marks_sql);
        $total_marks = 0; $subject_count = 0;
        $marks_data = [];
        $low_subjects = []; 

        while($row = $marks_res->fetch_assoc()){
            $score = (($row['s1_val'] ?? 0) * 0.4) + (($row['s2_val'] ?? 0) * 0.6);
            $row['calculated_score'] = $score;
            $marks_data[] = $row;
            $total_marks += $score;
            $subject_count++;
            if($score < 60) { $low_subjects[] = strtoupper($row['subject_name']); }
        }

        $avg = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;
        $final_res = getGrade($avg);
        $avg_grade = $final_res[0];

        // Comment Logic (English with Name)
        $teacher_comment = ""; $head_comment = "";
        if($avg_grade == 'A' || $avg_grade == 'B'){
            $teacher_comment = "Congratulations $fname for your excellent results. You have shown great effort throughout the year; continue working hard.";
            $head_comment = "These annual results are very satisfactory. The school management congratulates you; stay focused on your goals.";
        } elseif($avg_grade == 'C'){
            $teacher_comment = "Dear $fname, your performance is fair but requires more consistency. Put in more effort next academic year.";
            $head_comment = "A fair performance. We advise parents to provide closer academic supervision during the holidays.";
        } else { 
            $teacher_comment = "You have failed to meet the required standards $fname. You must double your efforts to improve your academic progress.";
            $head_comment = "Poor performance. A major academic shift and close monitoring from both parents and teachers is required.";
        }

        $subject_remark = "";
        if(!empty($low_subjects)){
            $subject_remark = " Focus more on " . implode(", ", $low_subjects) . " next year.";
        }
?>

<div class="report-paper">
    <?php if(!empty($school['logo'])): ?>
        <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <?php endif; ?>

    <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128" class="student-photo">

    <div class="header-section text-center">
        <h2 class="school-name"><?= $school['school_name'] ?></h2>
        <p class="mb-0 fw-bold">P.O.BOX <?= $school['pobox'] ?? '34' ?>, PHONE <?= $school['phone'] ?? '0625415484' ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?? 'Zanzibar' ?></p>
        <h5 class="mt-4 fw-bold text-uppercase text-decoration-underline">ANNUAL PROGRESS REPORT (<?= $year ?>)</h5>
        <h6 class="fw-bold">PRIMARY SCHOOL</h6>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">NAME: <strong><?= $student['fullname'] ?></strong> &nbsp;&nbsp; ID: <strong><?= $student['id'] ?></strong></p>
        <p class="mb-1">CLASS: <strong><?= $class_name ?> <?= $stream ?></strong>
           <span class="float-end">POSITION: <strong><?= getOrdinal($position) ?></strong> OUT OF <strong><?= $total_students ?></strong></span>
        </p>
    </div>

    <table class="table table-marks w-100 mb-2">
        <thead class="text-uppercase" style="background: #f2f2f2;">
            <tr>
                <th class="text-start" width="30%">Subjects</th>
                <th>Special 1 (40%)</th>
                <th>Special 2 (60%)</th>
                <th>Annual (100%)</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($marks_data as $m): $g = getGrade($m['calculated_score']); ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td><?= number_format(($m['s1_val'] ?? 0) * 0.4, 1) ?></td>
                <td><?= number_format(($m['s2_val'] ?? 0) * 0.6, 1) ?></td>
                <td class="fw-bold"><?= number_format($m['calculated_score'], 0) ?></td>
                <td><?= $g[0] ?></td>
                <td class="small"><?= $g[1] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold bg-light">
                <td class="text-start">ANNUAL AVERAGE</td>
                <td colspan="2"></td>
                <td><?= number_format($avg, 1) ?>%</td>
                <td>GRADE:</td>
                <td><?= $avg_grade ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4" style="font-size: 13px;">
        <div class="col-6 pe-4">
            <div class="comment-title">Teacher's Comments</div>
            <div class="comment-box"><?= $teacher_comment . $subject_remark ?></div>
            <p class="small mt-2">Class Teacher: <strong>LIKINDY ISMAIL</strong></p>
        </div>
        <div class="col-6">
            <div class="comment-title">Head's Comments</div>
            <div class="comment-box"><?= $head_comment ?></div>
        </div>
    </div>

    <table class="grade-summary-table text-uppercase">
        <tr><td>Grade</td><td>Range</td><td>Remark</td></tr>
        <tr><td>A</td><td>80 - 100</td><td>Excellent</td></tr>
        <tr><td>B</td><td>70 - 79</td><td>Very Good</td></tr>
        <tr><td>C</td><td>60 - 69</td><td>Good</td></tr>
        <tr><td>D</td><td>50 - 59</td><td>Pass</td></tr>
        <tr><td>F</td><td>0 - 49</td><td>Fail</td></tr>
    </table>
    
    <div class="clearfix"></div>
    <div class="row mt-5 pt-4 text-center text-uppercase fw-bold" style="font-size: 11px;">
        <div class="col-4 border-top pt-2">Teacher's Sign</div>
        <div class="col-4 border-top pt-2">Head's Sign</div>
        <div class="col-4 border-top pt-2">Parent's Sign</div>
    </div>
</div>

<?php endwhile; endif; ?>

</body>
</html>