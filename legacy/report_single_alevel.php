<?php
session_start();
include('db_config.php');

$student_db_id = $_GET['id'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$student_db_id || !$year || !$term) {
    echo "<script>alert('Missing parameters!'); window.history.back();</script>";
    exit;
}

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
$student = $conn->query("SELECT * FROM students WHERE id = '$student_db_id'")->fetch_assoc();

$marks_query = "SELECT m.*, s.subject_name 
                FROM marks m 
                JOIN subjects s ON m.subject_id = s.id 
                WHERE m.student_id = '$student_db_id' 
                AND m.year = '$year' 
                AND m.term = '$term'";
$marks_res = $conn->query($marks_query);

/**
 * Division Logic - Inatumia pointi ulizozipata
 */
function calculateDivision($total_points, $subject_count) {
    if ($subject_count < 3) return "N/A";
    if ($total_points >= 3 && $total_points <= 9) return "I";
    if ($total_points <= 12) return "II";
    if ($total_points <= 17) return "III";
    if ($total_points <= 19) return "IV";
    return "0";
}

/**
 * Comments kulingana na fomu uliyotuma
 */
function getStandardComment($points) {
    if ($points >= 3 && $points <= 9) {
        return "Such excellent results can only be produced by very bright students. You surely deserve a very BIG present from your parents to award your efforts. Keep it up!";
    } 
    elseif ($points >= 10 && $points <= 12) {
        return "With such results, it cannot be denied that you are among our very bright students. Maintain this consistency and aim for Division One next time.";
    } 
    elseif ($points >= 13 && $points <= 17) {
        return "Your result is not satisfactory. You should work much harder. Lots of encouragement and reassurance is needed from parents to improve your studies.";
    } 
    else {
        return "Your result is very disappointing. You are not going to make it to the required standard in the future without greater self-motivation. You must learn to work hard and follow instructions. Strict parental follow-up is necessary.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Times New Roman', serif; padding: 20px; color: #333; }
        .report-paper { background: white; width: 210mm; margin: auto; padding: 40px; border: 1px solid #ddd; position: relative; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .school-logo { width: 100px; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; padding: 8px; text-align: center; font-size: 14px; }
        .info-table td { border: none !important; padding: 5px 0; font-size: 15px; }
        .comment-box { border: 1px solid #000; font-style: italic; padding: 10px; min-height: 60px; margin-top: 5px; font-size: 14px; }
        .summary-table td { font-size: 12px; border: 1px solid #000; padding: 4px; }
        @media print { 
            .no-print { display: none; } 
            body { background: none; padding: 0; }
            .report-paper { border: none; width: 100%; padding: 20px; box-shadow: none; } 
        }
    </style>
</head>
<body>

<div class="report-paper">
    <div class="row align-items-center mb-4">
        <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
        <div class="col-8 text-center">
            <h2 class="fw-bold m-0"><?= strtoupper($school['school_name']) ?></h2>
            <p class="m-0"><?= $school['address'] ?><br>Phone: <?= $school['phone'] ?></p>
            <h4 class="mt-3 text-decoration-underline fw-bold">STUDENT PROGRESS REPORT</h4>
        </div>
        <div class="col-2 text-end">
            <img src="<?= !empty($student['photo']) ? 'uploads/students/'.$student['photo'] : 'https://via.placeholder.com/110' ?>" width="110" style="border:1px solid #000">
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
            <td class="border-bottom border-dark"><?= $student['class_name'] ?></td>
        </tr>
        <tr>
            <td><strong>COMBINATION:</strong></td>
            <td class="border-bottom border-dark"><?= $student['combination'] ?></td>
            <td><strong>TERM/YEAR:</strong></td>
            <td class="border-bottom border-dark"><?= strtoupper($term) ?> - <?= $year ?></td>
        </tr>
    </table>

    <table class="table table-marks w-100 mb-4">
        <thead class="bg-light">
            <tr>
                <th class="text-start">SUBJECT</th>
                <th>SCORE (%)</th>
                <th>GRADE</th>
                <th>POINTS</th>
                <th>REMARK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_points = 0;
            $comb_subjects_count = 0;
            $excluded = ['GENERAL STUDIES', 'GS', 'BAM', 'BASIC APPLIED MATHEMATICS', 'COMMUNICATION SKILLS'];

            if ($marks_res && $marks_res->num_rows > 0):
                while($mark = $marks_res->fetch_assoc()): 
                    $sub_name = trim(strtoupper($mark['subject_name']));
                    $is_comb_sub = !in_array($sub_name, $excluded);

                    if ($is_comb_sub) {
                        $total_points += $mark['points'];
                        $comb_subjects_count++;
                    }
            ?>
            <tr>
                <td class="text-start fw-bold"><?= $mark['subject_name'] ?></td>
                <td><?= number_format($mark['total_100'], 1) ?></td>
                <td class="fw-bold"><?= $mark['grade'] ?></td>
                <td><?= $is_comb_sub ? $mark['points'] : '-' ?></td>
                <td class="small italic"><?= $mark['remark'] ?></td>
            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>

    <div class="row">
        <div class="col-7">
            <table class="table table-bordered border-dark text-center fw-bold mb-3">
                <tr class="bg-light">
                    <td>TOTAL COMBINATION POINTS</td>
                    <td>DIVISION</td>
                </tr>
                <tr>
                    <td class="fs-4"><?= $total_points ?></td>
                    <td class="text-danger fs-4"><?= calculateDivision($total_points, $comb_subjects_count) ?></td>
                </tr>
            </table>

            <strong>TEACHER'S & HEAD OF SCHOOL REMARKS:</strong>
            <div class="comment-box">
                <?= getStandardComment($total_points) ?>
            </div>
        </div>

        <div class="col-5">
            <p class="text-center fw-bold mb-1" style="font-size: 13px;">GRADING SUMMARY</p>
            <table class="table summary-table text-center w-100">
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

    <div class="row mt-5 pt-4 text-center">
        <div class="col-4"><div class="border-top border-dark pt-2 fw-bold small">PARENT'S SIGNATURE</div></div>
        <div class="col-4"><div class="border-top border-dark pt-2 fw-bold small">CLASS TEACHER</div></div>
        <div class="col-4"><div class="border-top border-dark pt-2 fw-bold small">HEAD OF SCHOOL</div></div>
    </div>
</div>

<div class="text-center mt-4 no-print">
    <button onclick="window.print()" class="btn btn-primary btn-lg px-5 shadow">Print Progress Report</button>
    <button onclick="window.history.back()" class="btn btn-secondary btn-lg px-5 shadow">Go Back</button>
</div>

</body>
</html>