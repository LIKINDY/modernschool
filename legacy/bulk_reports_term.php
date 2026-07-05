<?php
session_start();
include('db_config.php');

// Ensure class name exists in URL
if (!isset($_GET['class_name'])) {
    die("Error: Class name is required!");
}

$class_name = $conn->real_escape_string($_GET['class_name']);
$year = $_GET['year'] ?? '2025/2026'; 
$term = $_GET['term'] ?? 'Term 1';

// 1. Fetch school information
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch all students of the respective class
$students_query = "SELECT * FROM students WHERE class_name = '$class_name' AND status != 'deleted' ORDER BY fullname ASC";
$students_result = $conn->query($students_query);

if ($students_result->num_rows == 0) {
    die("No students found in the class: " . htmlspecialchars($class_name));
}

// Function to get Grade and Remarks
function getGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk <?= $term ?> Reports - <?= $class_name ?></title>
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
        
        /* Comments Style */
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

        /* Grade Summary Style */
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
        PRINT ALL REPORT CARDS
    </button>
</div>

<?php 
while($student = $students_result->fetch_assoc()): 
    $student_id = $student['id'];
    
    // Fetch marks for each student
    $marks_sql = "SELECT m.*, s.subject_name 
                  FROM marks m 
                  JOIN subjects s ON m.subject_id = s.id 
                  WHERE m.student_id = '$student_id' AND m.year = '$year' AND m.term = '$term'
                  ORDER BY s.subject_name ASC";
    $marks_res = $conn->query($marks_sql);

    $total_sum = 0; $subject_count = 0; $marks_data = [];
    $weak_subjects = []; // Hapa tutahifadhi masomo yenye D au F
    
    while ($row = $marks_res->fetch_assoc()) {
        $sub_id = $row['subject_id'];
        $my_total = $row['total_100'];

        // Logic ya kutafuta masomo dhaifu (D au F)
        if($my_total < 60) { // Alama chini ya 60 ni D au F
            $weak_subjects[] = strtoupper($row['subject_name']);
        }

        // Subject Rank
        $rank_sub_res = $conn->query("SELECT total_100 FROM marks WHERE subject_id = '$sub_id' AND year = '$year' AND term = '$term' ORDER BY total_100 DESC");
        $sub_pos = 1;
        while($r_sub = $rank_sub_res->fetch_assoc()){
            if($r_sub['total_100'] > $my_total) $sub_pos++;
        }
        
        // Highest Mark
        $high = $conn->query("SELECT MAX(total_100) as max_m FROM marks WHERE subject_id = '$sub_id' AND year = '$year' AND term = '$term'")->fetch_assoc();
        
        $row['subject_position'] = $sub_pos;
        $row['highest_in_class'] = $high['max_m'];
        $marks_data[] = $row;
        $total_sum += $my_total;
        $subject_count++;
    }

    // Skip printing if student has no marks recorded
    if($total_sum <= 0) continue;

    $average = ($subject_count > 0) ? ($total_sum / $subject_count) : 0;
    $final_grade = getGrade($average);

    // Overall Position calculation
    $rank_res = $conn->query("SELECT m.student_id, SUM(m.total_100) as total_score 
                               FROM marks m 
                               JOIN students st ON m.student_id = st.id 
                               WHERE m.year = '$year' AND m.term = '$term' AND st.class_name = '$class_name' 
                               GROUP BY m.student_id ORDER BY total_score DESC");
    $overall_pos = 0;
    $total_students_in_class = $rank_res->num_rows;
    $counter = 0;
    while($r = $rank_res->fetch_assoc()){
        $counter++;
        if($r['student_id'] == $student_id){ $overall_pos = $counter; break; }
    }
    $fname = explode(' ', $student['fullname'])[0];

    // Tengeneza sentensi ya masomo dhaifu
    $weak_list = "";
    if(!empty($weak_subjects)){
        $weak_list = " You should increase efforts in subjects such as " . implode(", ", $weak_subjects) . " where you performed below average.";
    }
?>

<div class="report-paper">
    <?php if(!empty($school['logo'])): ?>
        <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <?php endif; ?>

    <?php if(!empty($student['photo'])): ?>
        <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128'">
    <?php endif; ?>

    <div class="header-section text-center">
        <h2 class="school-name"><?= $school['school_name'] ?></h2>
        <p class="mb-0 fw-bold"><?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?></p>
        <h5 class="mt-4 fw-bold text-uppercase text-decoration-underline"><?= strtoupper($term) ?> PROGRESS REPORT</h5>
        <h6 class="fw-bold">PRIMARY</h6>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">STUDENT'S NAME: <strong><?= $student['fullname'] ?></strong> &nbsp;&nbsp; ATTENDANCE: ________</p>
        <p class="mb-1">CLASS ACADEMIC YEAR: <strong><?= $year ?> <?= $student['class_name'] ?></strong>
           <span class="float-end">CLASS POSITION: <strong><?= $overall_pos ?></strong> OUT OF <strong><?= $total_students_in_class ?></strong></span>
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
            <?php if(count($marks_data) > 0): ?>
                <?php foreach ($marks_data as $m): $g = getGrade($m['total_100']); ?>
                <tr>
                    <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                    
                    <?php if($term !== 'Final'): ?>
                        <td><?= ($term == 'Terminal') ? number_format($m['test_avg_40'], 0) : number_format($m['monthly_1'], 0) ?></td>
                        <td><?= number_format($m['exam_60'], 0) ?></td>
                    <?php endif; ?>

                    <td class="fw-bold"><?= number_format($m['total_100'], 0) ?></td>
                    <td><?= $g[0] ?></td>
                    <td><?= $m['subject_position'] ?></td>
                    <td class="small"><?= $g[1] ?></td>
                    <td class="fw-bold"><?= number_format($m['highest_in_class'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold">
                    <td class="text-start">TOTAL MARKS</td>
                    <?php if($term !== 'Final'): ?>
                        <td colspan="2"></td>
                    <?php endif; ?>
                    <td><?= $total_sum ?></td>
                    <td class="text-end" colspan="3">OUT OF:</td>
                    <td><?= $subject_count * 100 ?></td>
                </tr>
                <tr class="fw-bold bg-light">
                    <td class="text-start">AVERAGE</td>
                    <?php if($term !== 'Final'): ?>
                        <td colspan="2"></td>
                    <?php endif; ?>
                    <td><?= number_format($average, 2) ?>%</td>
                    <td><?= $final_grade[0] ?></td>
                    <td><?= $overall_pos ?></td>
                    <td colspan="2"><?= $final_grade[1] ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="<?= ($term !== 'Final') ? '8' : '6' ?>" class="py-5 text-center text-muted">No marks recorded for this student.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-4" style="font-size: 13px;">
        <div class="col-6 pe-4">
            <div class="comment-title">Teacher's Comments</div>
            <div class="comment-box">
                The results are good <?= ucwords(strtolower($fname)) ?>, but more effort is needed. 
                Do not be satisfied with your results, increase your efforts in solving more questions.
            </div>
            <p class="small mt-2">Class Teacher's Name: <strong><?= $school['headmaster'] ?></strong></p>
        </div>
        <div class="col-6">
            <div class="comment-title">Head's Comments</div>
            <div class="comment-box">
                <?= ucwords(strtolower($fname)) ?> has produced <?= strtolower($final_grade[1]) ?> results. 
                <?= $weak_list ?>
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
<?php endwhile; ?>

</body>
</html>