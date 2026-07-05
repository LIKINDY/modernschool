<?php
session_start();
include('db_config.php');

// 1. Get parameters from URL
$class = $_GET['class_name'] ?? '';
$comb = $_GET['combination'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class || !$comb || !$year || !$term) {
    echo "<script>alert('Missing parameters!'); window.history.back();</script>";
    exit;
}

// 2. School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 3. Fetch active students
$students_res = $conn->query("SELECT * FROM students WHERE class_name = '$class' AND combination = '$comb' AND status = 'active' ORDER BY fullname ASC");

// --- LOGIC YA NECTA A-LEVEL ---
function calculateDivision($total_points, $subjects_count) {
    if ($subjects_count < 3) return "N/A"; 
    
    if ($total_points >= 3 && $total_points <= 9) return "I";
    if ($total_points <= 12) return "II";
    if ($total_points <= 17) return "III";
    if ($total_points <= 19) return "IV";
    return "0";
}

// Hapa nimeondoa ile logic ya GS kusoma S na F tu
function getALevelGrade($marks) {
    if ($marks >= 79.5) return ['grade' => 'A', 'points' => 1];
    if ($marks >= 69.5) return ['grade' => 'B', 'points' => 2];
    if ($marks >= 59.5) return ['grade' => 'C', 'points' => 3];
    if ($marks >= 49.5) return ['grade' => 'D', 'points' => 4];
    if ($marks >= 39.5) return ['grade' => 'E', 'points' => 5];
    if ($marks >= 34.5) return ['grade' => 'S', 'points' => 6];
    return ['grade' => 'F', 'points' => 7];
}

function getStandardComment($points) {
    if ($points >= 3 && $points <= 9) {
        return "Such excellent results can only be produced by very bright students, you surely deserve a very BIG present from your parents to award your efforts.";
    } 
    elseif ($points >= 10 && $points <= 12) {
        return "With such results, it can not be denied that you are among our very bright students, keep it up.";
    } 
    elseif ($points >= 13 && $points <= 17) {
        return "Your result is not good. You should work hard. Lots of encouragement and reassurance is needed from parents to improve your studies.";
    } 
    else {
        return "Your result is very disappointing. You are not going to make it to the required standard in future without greater self motivation. You must learn to work hard and follow instructions and concentrate in class. There must be a strict parental follow up to help you improve your studies.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Reports - <?= $class ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #555; font-family: 'Times New Roman', serif; padding: 20px; }
        .report-paper { 
            background: white; width: 210mm; margin: 20px auto; padding: 40px; 
            border: 1px solid #ddd; position: relative; page-break-after: always;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .school-logo { width: 90px; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; padding: 8px; text-align: center; }
        .info-table td { border: none !important; padding: 3px 0; }
        .comment-box { border: none; padding: 5px 0; min-height: 60px; font-style: italic; text-align: justify; }
        
        @media print { 
            body { background: none; padding: 0; }
            .no-print { display: none; } 
            .report-paper { border: none; width: 100%; margin: 0; box-shadow: none; padding: 20px; } 
        }
    </style>
</head>
<body>

<div class="text-center mb-4 no-print">
    <button onclick="window.print()" class="btn btn-warning btn-lg fw-bold">PRINT ALL REPORTS (PDF)</button>
    <button onclick="window.history.back()" class="btn btn-dark btn-lg ms-2">Back</button>
</div>

<?php 
if ($students_res && $students_res->num_rows > 0):
    while($student = $students_res->fetch_assoc()): 
        $student_db_id = $student['id'];

        $marks_query = "SELECT m.*, s.subject_name 
                        FROM marks m 
                        JOIN subjects s ON m.subject_id = s.id 
                        WHERE m.student_id = '$student_db_id' 
                        AND m.year = '$year' 
                        AND m.term = '$term'";
        $marks_res = $conn->query($marks_query);
?>

<div class="report-paper">
    <div class="row align-items-center mb-3">
        <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
        <div class="col-8 text-center">
            <h2 class="fw-bold m-0"><?= strtoupper($school['school_name']) ?></h2>
            <p class="m-0"><?= $school['address'] ?><br>Phone: <?= $school['phone'] ?></p>
            <h5 class="mt-2 text-decoration-underline fw-bold">STUDENT PROGRESS REPORT (<?= strtoupper($term) ?>)</h5>
        </div>
        <div class="col-2 text-end">
            <img src="<?= !empty($student['photo']) ? 'uploads/students/'.$student['photo'] : 'https://via.placeholder.com/110' ?>" width="100" style="border:1px solid #000">
        </div>
    </div>

    <table class="table info-table mb-3">
        <tr>
            <td width="15%"><strong>NAME:</strong></td>
            <td width="45%" class="border-bottom border-dark"><?= strtoupper($student['fullname']) ?></td>
            <td width="15%" class="ps-3"><strong>GENDER:</strong></td>
            <td class="border-bottom border-dark"><?= $student['gender'] ?></td>
        </tr>
        <tr>
            <td><strong>REG NO:</strong></td>
            <td class="border-bottom border-dark"><?= $student['student_id'] ?></td>
            <td><strong>CLASS:</strong></td>
            <td class="border-bottom border-dark"><?= $student['class_name'] ?> (<?= $student['combination'] ?>)</td>
        </tr>
    </table>

    <table class="table table-marks w-100">
        <thead class="bg-light">
            <tr>
                <th class="text-start">SUBJECT</th>
                <th>PERCENTAGE</th>
                <th>GRADE</th>
                <th>POINTS</th>
                <th>REMARK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $comb_points = 0; 
            $comb_subjects_count = 0;
            $excluded = ['GENERAL STUDIES', 'GS', 'BAM', 'BASIC APPLIED MATHEMATICS', 'COMMUNICATION SKILLS'];

            if ($marks_res && $marks_res->num_rows > 0):
                while($mark = $marks_res->fetch_assoc()): 
                    $sub_name = trim(strtoupper($mark['subject_name']));
                    $is_comb_sub = !in_array($sub_name, $excluded);

                    // Sasa tunatumia function moja kwa masomo yote
                    $grade_data = getALevelGrade($mark['total_100']);
                    
                    if ($is_comb_sub) {
                        $comb_points += $grade_data['points'];
                        $comb_subjects_count++;
                    }
            ?>
            <tr>
                <td class="text-start fw-bold"><?= $mark['subject_name'] ?></td>
                <td><?= number_format($mark['total_100'], 1) ?>%</td>
                <td class="fw-bold"><?= $grade_data['grade'] ?></td>
                <td><?= $is_comb_sub ? $grade_data['points'] : '-' ?></td>
                <td class="small italic"><?= $mark['remark'] ?></td>
            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>

    <div class="row mt-3">
        <div class="col-7">
            <table class="table table-bordered border-dark text-center fw-bold">
                <tr class="bg-light">
                    <td width="50%">COMB. POINTS</td>
                    <td width="50%">DIVISION</td>
                </tr>
                <tr>
                    <td class="fs-5"><?= $comb_points ?></td>
                    <td class="text-danger fs-5"><?= calculateDivision($comb_points, $comb_subjects_count) ?></td>
                </tr>
            </table>
            
            <div class="mt-2">
                <strong>TEACHER'S COMMENT:</strong>
                <div class="comment-box small"><?= getStandardComment($comb_points) ?></div>
            </div>
        </div>
        
        <div class="col-5">
            <p class="fw-bold mb-1 small text-center">GRADING SUMMARY</p>
            <table class="table table-bordered border-dark text-center" style="font-size: 10px;">
                <tr class="bg-light fw-bold">
                    <td>GRADE</td><td>PERCENTAGE</td><td>POINTS</td>
                </tr>
                <tr><td>A</td><td>79.5 - 100</td><td>1</td></tr>
                <tr><td>B</td><td>69.5 - 79.4</td><td>2</td></tr>
                <tr><td>C</td><td>59.5 - 69.4</td><td>3</td></tr>
                <tr><td>D</td><td>49.5 - 59.4</td><td>4</td></tr>
                <tr><td>E</td><td>39.5 - 49.4</td><td>5</td></tr>
                <tr><td>S</td><td>34.5 - 39.4</td><td>6</td></tr>
                <tr><td>F</td><td>0 - 34.4</td><td>7</td></tr>
            </table>
        </div>
    </div>

    <div class="row mt-5 pt-3 text-center">
        <div class="col-4"><div class="border-top border-dark pt-1 fw-bold small">PARENT'S SIGNATURE</div></div>
        <div class="col-4"><div class="border-top border-dark pt-1 fw-bold small">CLASS TEACHER</div></div>
        <div class="col-4"><div class="border-top border-dark pt-1 fw-bold small">HEAD OF SCHOOL</div></div>
    </div>
</div>

<?php 
    endwhile;
else:
    echo "<div class='alert alert-danger text-center'>No active students found.</div>";
endif; 
?>

</body>
</html>