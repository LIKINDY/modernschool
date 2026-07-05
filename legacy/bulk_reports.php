<?php
session_start();
include('db_config.php');

// Ensure class name exists in URL
if (!isset($_GET['class_name'])) {
    die("Error: Class name is required!");
}

$class_name = $conn->real_escape_string($_GET['class_name']);

// Fetch Year and Term from URL
$year = $_GET['year'] ?? '2025/2026'; 
$term = $_GET['term'] ?? 'Term 1';

// 1. Fetch school information
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch all students
$students_query = "SELECT * FROM students WHERE class_name = '$class_name' AND status != 'deleted' ORDER BY fullname ASC";
$students_result = $conn->query($students_query);

if ($students_result->num_rows == 0) {
    die("No students found in the class: " . htmlspecialchars($class_name));
}

// Function to get Grade and Remarks (Passing Mark is 50)
function getGrade($score) {
    if ($score >= 80) return ['A', 'Excellent'];
    if ($score >= 70) return ['B', 'Very Good'];
    if ($score >= 60) return ['C', 'Good'];
    if ($score >= 50) return ['D', 'Average'];
    return ['F', 'Fail'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Progress Reports - <?= $class_name ?></title>
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
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .school-name { font-size: 24px; font-weight: 900; text-transform: uppercase; margin-bottom: 0; }
        
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 6px; }
        .comment-box { min-height: 70px; border-bottom: 1px dotted #000; padding-top: 5px; font-style: italic; line-height: 1.5; font-size: 14px; }
        
        /* Styled Grade Summary Table */
        .grade-summary-container { float: right; margin-top: 15px; width: 350px; }
        .grade-summary-table { width: 100%; border-collapse: collapse; font-size: 11px; border: 1px solid #000; }
        .grade-summary-table th { background: #f2f2f2; border: 1px solid #000; padding: 4px; text-align: center; }
        .grade-summary-table td { border: 1px solid #000; padding: 3px 8px; font-weight: bold; }

        @media print { 
            .no-print { display: none; } 
            body { background: none; margin: 0; } 
            .report-paper { margin: 0; border: 1px solid #000; width: 100%; } 
        }
    </style>
</head>
<body>

<div class="text-center no-print py-3">
    <button onclick="window.print()" class="btn btn-primary btn-lg shadow-sm">PRINT ALL REPORTS NOW</button>
</div>

<?php 
while($student = $students_result->fetch_assoc()): 
    $student_id = $student['id'];
    
    // Fetch marks
    $marks_sql = "SELECT m.*, s.subject_name, (m.test_avg_40 + m.exam_60) as calculated_total 
                  FROM marks m 
                  JOIN subjects s ON m.subject_id = s.id 
                  WHERE m.student_id = '$student_id' AND m.year = '$year' AND m.term = '$term'";
    $marks_res = $conn->query($marks_sql);

    $total_sum = 0; $subject_count = 0; $marks_data = [];
    $weak_subjects = [];

    while ($row = $marks_res->fetch_assoc()) {
        $sub_id = $row['subject_id'];
        $my_total = $row['calculated_total'];

        if($my_total < 50) { $weak_subjects[] = strtoupper($row['subject_name']); }

        // Subject Position
        $rank_sub_res = $conn->query("SELECT (test_avg_40 + exam_60) as st_total FROM marks WHERE subject_id = '$sub_id' AND year = '$year' AND term = '$term' ORDER BY st_total DESC");
        $sub_pos = 1;
        while($r_sub = $rank_sub_res->fetch_assoc()){
            if($r_sub['st_total'] > $my_total) $sub_pos++;
        }
        
        $high = $conn->query("SELECT MAX(test_avg_40 + exam_60) as max_m FROM marks WHERE subject_id = '$sub_id' AND year = '$year' AND term = '$term'")->fetch_assoc();
        
        $row['subject_position'] = $sub_pos;
        $row['highest_in_class'] = $high['max_m'];
        $marks_data[] = $row;
        $total_sum += $my_total;
        $subject_count++;
    }

    $average = ($subject_count > 0) ? ($total_sum / $subject_count) : 0;
    $final_grade = getGrade($average);

    // Overall Position
    $rank_res = $conn->query("SELECT m.student_id, SUM(m.test_avg_40 + m.exam_60) as grand_total 
                             FROM marks m 
                             JOIN students st ON m.student_id = st.id 
                             WHERE m.year = '$year' AND m.term = '$term' AND st.class_name = '$class_name' 
                             GROUP BY m.student_id ORDER BY grand_total DESC");
    $overall_pos = 0;
    $total_students = $rank_res->num_rows;
    $counter = 0;
    while($r = $rank_res->fetch_assoc()){
        $counter++;
        if($r['student_id'] == $student_id){ $overall_pos = $counter; break; }
    }
    $fname = ucwords(strtolower(explode(' ', $student['fullname'])[0]));
    $weak_msg = !empty($weak_subjects) ? " More efforts are strictly required in " . implode(", ", $weak_subjects) . "." : " Maintain your consistency in all subjects.";
?>

<div class="report-paper">
    <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>&size=128'">

    <div class="header-section">
        <h2 class="school-name"><?= $school['school_name'] ?></h2>
        <p class="mb-0 fw-bold">P.O.BOX <?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
        <p class="mb-0 fw-bold text-uppercase"><?= $school['address'] ?></p>
        <h5 class="mt-4 fw-bold text-decoration-underline"><?= strtoupper($term) ?> ACADEMIC PROGRESS REPORT</h5>
    </div>

    <div class="mb-3 text-uppercase" style="margin-top: 30px;">
        <p class="mb-1">STUDENT NAME: <strong><?= $student['fullname'] ?></strong></p>
        <p class="mb-1">CLASS & YEAR: <strong><?= $student['class_name'] ?> (<?= $year ?>)</strong>
           <span class="float-end">POSITION: <strong><?= $overall_pos ?></strong> OUT OF <strong><?= $total_students ?></strong></span>
        </p>
    </div>

    <table class="table table-marks w-100">
        <thead class="text-uppercase" style="background: #f2f2f2;">
            <tr>
                <th class="text-start">Subjects</th>
                <th>Test (40%)</th>
                <th>Exam (60%)</th>
                <th>Total (100%)</th>
                <th>Grade</th>
                <th>Pos</th>
                <th>Remarks</th>
                <th>Highest</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): $g = getGrade($m['calculated_total']); ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td><?= number_format($m['test_avg_40'], 0) ?></td>
                <td><?= number_format($m['exam_60'], 0) ?></td>
                <td class="fw-bold bg-light"><?= number_format($m['calculated_total'], 0) ?></td>
                <td><?= $g[0] ?></td>
                <td><?= $m['subject_position'] ?></td>
                <td class="small"><?= $g[1] ?></td>
                <td><?= number_format($m['highest_in_class'], 0) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold bg-light" style="font-size: 14px;">
                <td class="text-start">AVERAGE SCORE</td>
                <td colspan="2"></td>
                <td class="text-primary"><?= number_format($average, 1) ?>%</td>
                <td><?= $final_grade[0] ?></td>
                <td><?= $overall_pos ?></td>
                <td colspan="2"><?= $final_grade[1] ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-6">
            <div class="fw-bold border-bottom mb-1 text-uppercase">Teacher's Comments</div>
            <div class="comment-box">
                <?= $fname ?> has shown <?= strtolower($final_grade[1]) ?> performance this term. However, there is significant room for improvement by increasing study hours.
            </div>
        </div>
        <div class="col-6">
            <div class="fw-bold border-bottom mb-1 text-uppercase">Head's Comments</div>
            <div class="comment-box">
                A fair performance overall. <?= $weak_msg ?> We expect much better results in the next assessment.
            </div>
        </div>
    </div>

    <div class="grade-summary-container">
        <table class="grade-summary-table">
            <thead>
                <tr>
                    <th>Grade</th>
                    <th>Score Range</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>A</td><td>80 - 100</td><td>Excellent</td></tr>
                <tr><td>B</td><td>70 - 79</td><td>Very Good</td></tr>
                <tr><td>C</td><td>60 - 69</td><td>Good</td></tr>
                <tr><td>D</td><td>50 - 59</td><td>Average (Pass)</td></tr>
                <tr><td>F</td><td>00 - 49</td><td>Fail</td></tr>
                <tr style="background: #f9f9f9;"><td colspan="3" class="text-center">PASSING MARK: 50%</td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="clearfix"></div>
    <div class="row mt-5 pt-4 text-center text-uppercase fw-bold" style="font-size: 11px;">
        <div class="col-4 border-top pt-2">Class Teacher's Signature</div>
        <div class="col-4 border-top pt-2">Headteacher's Signature</div>
        <div class="col-4 border-top pt-2">Parent's Signature</div>
    </div>
</div>
<?php endwhile; ?>

</body>
</html>