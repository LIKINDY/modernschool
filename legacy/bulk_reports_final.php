<?php
session_start();
include('db_config.php');

// Ensure class_name exists in URL
if (!isset($_GET['class_name'])) {
    die("Error: Class name is required!");
}

$class_name = $conn->real_escape_string($_GET['class_name']);
$year = $_GET['year'] ?? '2025/2026';

// 1. Fetch school information
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch all students of the respective class
$students_query = "SELECT * FROM students WHERE class_name = '$class_name' AND status != 'deleted' ORDER BY fullname ASC";
$students_result = $conn->query($students_query);

if ($students_result->num_rows == 0) {
    die("No students found in the class: " . htmlspecialchars($class_name));
}

// Fixed Function to match Grade Summary
function getAnnualGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Satisfactory'];
    return ['F', 'Fail'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Annual Reports - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f0f0; font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
        .report-paper { 
            background: #fff; width: 210mm; min-height: 297mm; padding: 25px; 
            margin: 20px auto; border: 1px solid #000; position: relative;
            page-break-after: always;
        }
        .school-logo { width: 100px; height: 100px; object-fit: contain; position: absolute; left: 25px; top: 25px; }
        .student-photo { width: 100px; height: 110px; object-fit: cover; border: 1px solid #000; position: absolute; right: 25px; top: 25px; }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; margin-top: 10px; text-align: center; }
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
        
        @media print { 
            .no-print { display: none; } 
            body { background: none; margin: 0; } 
            .report-paper { margin: 0; border: 1px solid #000; width: 100%; } 
        }
    </style>
</head>
<body>

<div class="text-center no-print py-4">
    <button onclick="window.print()" class="btn btn-dark btn-sm px-5 shadow-sm fw-bold">
        PRINT ALL ANNUAL REPORTS (FINAL)
    </button>
</div>

<?php 
while($student = $students_result->fetch_assoc()): 
    $student_id = $student['id'];

    // 3. Calculate Overall Annual Rank
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
    $count_p = 0;
    while($r = $overall_rank_q->fetch_assoc()){
        $count_p++;
        if($r['student_id'] == $student_id) { $overall_pos = $count_p; break; }
    }
    $fname = explode(' ', $student['fullname'])[0];
    $weak_subjects = []; // Array ya kuhifadhi masomo dhaifu
?>

<div class="report-paper">
    <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128'">

    <div class="header-section">
        <h2 class="school-name"><?= $school['school_name'] ?></h2>
        <p class="mb-0 fw-bold"><?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?></p>
        <h5 class="mt-4 fw-bold text-uppercase text-decoration-underline">ANNUAL ACADEMIC PROGRESS REPORT (FINAL)</h5>
        <h6 class="fw-bold">PRIMARY</h6>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">STUDENT'S NAME: <strong><?= $student['fullname'] ?></strong> &nbsp;&nbsp; ATTENDANCE: ________</p>
        <p class="mb-1">CLASS ACADEMIC YEAR: <strong><?= $year ?> <?= $student['class_name'] ?></strong>
           <span class="float-end">ANNUAL POSITION: <strong><?= $overall_pos ?></strong> OUT OF <strong><?= $total_students ?></strong></span>
        </p>
    </div>

    <table class="table table-marks w-100 mb-2">
        <thead class="text-uppercase" style="background: #f2f2f2;">
            <tr>
                <th class="text-start" width="25%">Subjects</th>
                <th>Term 1 (40%)</th>
                <th>Term 2 (60%)</th>
                <th>Annual 100%</th>
                <th>Grade</th>
                <th>Pos</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");
            $grand_annual_total = 0; $sub_count = 0;
            
            while($sub = $subjects->fetch_assoc()):
                $sid = $sub['id'];
                
                $m1 = $conn->query("SELECT total_100 FROM marks WHERE student_id='$student_id' AND subject_id='$sid' AND term='Term 1' AND year='$year'")->fetch_assoc();
                $m2 = $conn->query("SELECT total_100 FROM marks WHERE student_id='$student_id' AND subject_id='$sid' AND term='Term 2' AND year='$year'")->fetch_assoc();
                
                $t1_val = $m1['total_100'] ?? 0;
                $t2_val = $m2['total_100'] ?? 0;
                
                $annual_score = ($t1_val * 0.4) + ($t2_val * 0.6);
                
                if($annual_score > 0):
                    $grand_annual_total += $annual_score;
                    $sub_count++;
                    $res = getAnnualGrade($annual_score);

                    // Collect weak subjects (Grade D or F)
                    if($annual_score < 60) {
                        $weak_subjects[] = strtoupper($sub['subject_name']);
                    }

                    // Subject Position Annual
                    $sub_rank_q = $conn->query("
                        SELECT m.student_id, 
                        (MAX(CASE WHEN m.term='Term 1' THEN m.total_100 ELSE 0 END) * 0.4 + 
                         MAX(CASE WHEN m.term='Term 2' THEN m.total_100 ELSE 0 END) * 0.6) as st_annual
                        FROM marks m
                        WHERE m.subject_id='$sid' AND m.year='$year'
                        GROUP BY m.student_id ORDER BY st_annual DESC
                    ");
                    $sub_pos = 0; $c_s = 0;
                    while($sr = $sub_rank_q->fetch_assoc()){
                        $c_s++;
                        if($sr['student_id'] == $student_id) { $sub_pos = $c_s; break; }
                    }
            ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($sub['subject_name']) ?></td>
                <td><?= number_format($t1_val * 0.4, 1) ?></td>
                <td><?= number_format($t2_val * 0.6, 1) ?></td>
                <td class="fw-bold bg-light"><?= number_format($annual_score, 0) ?></td>
                <td class="fw-bold"><?= $res[0] ?></td>
                <td><?= $sub_pos ?></td>
                <td class="small"><?= $res[1] ?></td>
            </tr>
            <?php endif; endwhile; ?>

            <?php 
            $final_avg = ($sub_count > 0) ? ($grand_annual_total / $sub_count) : 0;
            $f_grade = getAnnualGrade($final_avg);
            ?>
            <tr class="fw-bold bg-light">
                <td class="text-start">ANNUAL AVERAGE</td>
                <td colspan="2"></td>
                <td class="fs-6"><?= number_format($final_avg, 1) ?>%</td>
                <td><?= $f_grade[0] ?></td>
                <td><?= $overall_pos ?></td>
                <td><?= $f_grade[1] ?></td>
            </tr>
        </tbody>
    </table>

    <?php 
        // Build Weak Subjects Message
        $weak_msg = "";
        if(!empty($weak_subjects)){
            $weak_msg = " However, more effort is needed in " . implode(", ", $weak_subjects) . " to reach a better standard.";
        }
    ?>

    <div class="row mt-4" style="font-size: 13px;">
        <div class="col-6 pe-4">
            <div class="comment-title">Teacher's Comments</div>
            <div class="comment-box">
                The overall academic performance for <?= ucwords(strtolower($fname)) ?> in the year <?= $year ?> is <?= strtolower($f_grade[1]) ?>. 
                Keep working hard to maintain and improve these results in the next class.
            </div>
            <p class="small mt-2">Class Teacher's Name: <strong><?= $school['headmaster'] ?></strong></p>
        </div>
        <div class="col-6">
            <div class="comment-title">Head's Comments</div>
            <div class="comment-box">
                Congratulations to <?= ucwords(strtolower($fname)) ?> for completing this academic year. 
                A steady progress has been observed.<?= $weak_msg ?>
            </div>
        </div>
    </div>

    <table class="grade-summary-table text-uppercase">
        <tr><td>Grade</td><td>Percentage Range</td></tr>
        <tr><td>A</td><td>80 --- 100 ---> Excellent</td></tr>
        <tr><td>B</td><td>70 --- 79  ---> Very Good</td></tr>
        <tr><td>C</td><td>60 --- 69  ---> Good</td></tr>
        <tr><td>D</td><td>50 --- 59  ---> Satisfactory</td></tr>
        <tr><td>F</td><td>0  --- 49  ---> Fail</td></tr>
    </table>
    
    <div class="clearfix"></div>
    <p class="mt-2 fw-bold text-uppercase" style="font-size: 11px;">Annual Passing Average: 50</p>

    <div class="row mt-5 pt-4 text-center text-uppercase fw-bold" style="font-size: 11px;">
        <div class="col-4 border-top pt-2">Class Teacher's Signature</div>
        <div class="col-4 border-top pt-2">Head's Signature</div>
        <div class="col-4 border-top pt-2">Parent's Signature</div>
    </div>
</div>
<?php endwhile; ?>

</body>
</html>