<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || !isset($_GET['student_id'])) {
    die("Unauthorized access.");
}

$student_id = $conn->real_escape_string($_GET['student_id']);
$year = $_GET['year'] ?? '2025';
$term = $_GET['term'] ?? 'Term 1';

// 1. Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch Student Info
$student = $conn->query("SELECT * FROM students WHERE id = '$student_id'")->fetch_assoc();
if (!$student) die("Student not found.");
$class_name = $student['class_name'];

// 3. Fetch Marks & Calculations
$marks_sql = "SELECT m.*, s.subject_name 
              FROM marks m 
              JOIN subjects s ON m.subject_id = s.id 
              WHERE m.student_id = '$student_id' 
              AND m.year = '$year' 
              AND m.term = '$term'";
$marks_res = $conn->query($marks_sql);

$total_marks = 0; 
$subject_count = 0; 
$marks_data = [];
$weak_subjects = []; 

while ($row = $marks_res->fetch_assoc()) {
    $sub_id = $row['subject_id'];
    $my_score = $row['total_100'];

    if($my_score < 60) {
        $weak_subjects[] = strtoupper($row['subject_name']);
    }
    
    // Position in Subject
    $rank_sub_res = $conn->query("SELECT student_id, total_100 FROM marks WHERE subject_id = '$sub_id' AND year = '$year' AND term = '$term' ORDER BY total_100 DESC");
    $sub_pos = 1;
    while($r_sub = $rank_sub_res->fetch_assoc()){
        if($r_sub['student_id'] == $student_id) break;
        $sub_pos++;
    }
    
    // Highest Mark in Subject
    $high_res = $conn->query("SELECT MAX(total_100) as max_mark FROM marks WHERE subject_id = '$sub_id' AND year = '$year' AND term = '$term'");
    $high_row = $high_res->fetch_assoc();
    
    $row['subject_position'] = $sub_pos;
    $row['highest_in_class'] = $high_row['max_mark'];
    $marks_data[] = $row;
    $total_marks += $my_score;
    $subject_count++;
}

$average = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;

function getGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}
$final = getGrade($average);
$avg_grade = $final[0];

// Overall Class Position
$rank_res = $conn->query("SELECT m.student_id, SUM(m.total_100) as total_score FROM marks m JOIN students st ON m.student_id = st.id WHERE m.year = '$year' AND m.term = '$term' AND st.class_name = '$class_name' GROUP BY m.student_id ORDER BY total_score DESC");
$overall_pos = 0;
$total_students = $rank_res->num_rows;
$counter = 0;
while($r = $rank_res->fetch_assoc()){
    $counter++;
    if($r['student_id'] == $student_id){ $overall_pos = $counter; break; }
}

// Jina la kwanza
$fname = ucwords(strtolower(explode(' ', $student['fullname'])[0]));

/** COMMENT LOGIC **/
$teacher_comment = "";
$head_comment = "";

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

// Subject specific remarks
$subject_remark = "";
if(!empty($weak_subjects)){
    $list = implode(", ", $weak_subjects);
    $subject_remark = " You must specifically increase your efforts in $list to ensure better performance.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
        .report-paper { width: 210mm; margin: auto; padding: 25px; border: 1px solid #000; position: relative; min-height: 297mm; }
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
    <button onclick="window.print()" class="btn btn-dark btn-sm px-4">PRINT REPORT CARD</button>
</div>

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
        <p class="mb-0 fw-bold">P.O.BOX <?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?></p>
        <h5 class="mt-4 fw-bold text-uppercase text-decoration-underline"><?= strtoupper($term) ?> PROGRESS REPORT</h5>
        <h6 class="fw-bold">PRIMARY</h6>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">STUDENT'S NAME: <strong><?= $student['fullname'] ?></strong> &nbsp;&nbsp; ATTENDANCE: ________</p>
        <p class="mb-1">CLASS ACADEMIC YEAR: <strong><?= $year ?> <?= $student['class_name'] ?></strong>
           <span class="float-end">CLASS POSITION: <strong><?= $overall_pos ?></strong> OUT OF <strong><?= $total_students ?></strong></span>
        </p>
    </div>

    <table class="table table-marks w-100 mb-2">
        <thead class="text-uppercase" style="background: #f2f2f2;">
            <tr>
                <th class="text-start" width="25%">Subjects</th>
                <?php if($term !== 'Final'): ?>
                    <th>Test</th>
                    <th>Exam</th>
                <?php endif; ?>
                <th>Total 100%</th>
                <th>Grade</th>
                <th>Pos</th>
                <th>Remarks</th>
                <th>Highest</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): $g = getGrade($m['total_100']); ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <?php if($term !== 'Final'): ?>
                    <td><?= ($term == 'Terminal') ? $m['test_avg_40'] : $m['monthly_1'] ?></td>
                    <td><?= $m['exam_60'] ?></td>
                <?php endif; ?>
                <td class="fw-bold"><?= number_format($m['total_100'], 0) ?></td>
                <td><?= $g[0] ?></td>
                <td><?= $m['subject_position'] ?></td>
                <td class="small"><?= $g[1] ?></td>
                <td class="fw-bold"><?= $m['highest_in_class'] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold">
                <td class="text-start">TOTAL MARKS</td>
                <?php if($term !== 'Final'): ?> <td colspan="2"></td> <?php endif; ?>
                <td><?= $total_marks ?></td>
                <td class="text-end" colspan="3">OUT OF:</td>
                <td><?= $subject_count * 100 ?></td>
            </tr>
            <tr class="fw-bold bg-light">
                <td class="text-start">AVERAGE</td>
                <?php if($term !== 'Final'): ?> <td colspan="2"></td> <?php endif; ?>
                <td><?= number_format($average, 2) ?>%</td>
                <td><?= $final[0] ?></td>
                <td><?= $overall_pos ?></td>
                <td colspan="2"><?= $final[1] ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4" style="font-size: 13px;">
        <div class="col-6 pe-4">
            <div class="comment-title">Teacher's Comments</div>
            <div class="comment-box">
                <?= $teacher_comment . $subject_remark ?>
            </div>
            <p class="small mt-2">Class Teacher: <strong><?= $school['headmaster'] ?></strong></p>
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

</body>
</html>