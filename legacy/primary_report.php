<?php
session_start();
include('db_config.php');

if (!isset($_GET['student_id'])) {
    die("Mwanafunzi hajapatikana!");
}

$student_id = $_GET['student_id'];
$year = date('Y'); // Unaweza kubadili iwe dynamic
$term = 'Terminal';

// 1. Vuta Taarifa za Shule
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Vuta Taarifa za Mwanafunzi
$student = $conn->query("SELECT * FROM students WHERE id = '$student_id'")->fetch_assoc();

// 3. Vuta Marks na Mahesabu ya Position in Subject
// Hii query inapanga marks za somo husika darasa zima ili kupata nafasi (Rank)
$marks_query = "SELECT m.*, s.subject_name, 
                (SELECT COUNT(*) + 1 FROM marks m2 
                 WHERE m2.subject_id = m.subject_id 
                 AND m2.total_100 > m.total_100 
                 AND m2.year = m.year) as subject_rank,
                (SELECT COUNT(*) FROM students WHERE class_name = '{$student['class_name']}') as total_students
                FROM marks m 
                JOIN subjects s ON m.subject_id = s.id 
                WHERE m.student_id = '$student_id' AND m.year = '$year'";
$marks_result = $conn->query($marks_query);

// 4. Hesabu Wastani wa Jumla na Nafasi Darasani (Overall Position)
$overall_rank_query = "SELECT student_id, SUM(total_100) as total_score 
                       FROM marks WHERE year = '$year' 
                       GROUP BY student_id ORDER BY total_score DESC";
$overall_rank_res = $conn->query($overall_rank_query);
$position_in_class = 0;
$rank = 1;
while($row = $overall_rank_res->fetch_assoc()){
    if($row['student_id'] == $student_id){
        $position_in_class = $rank;
        break;
    }
    $rank++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card - <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; font-family: 'Times New Roman', serif; }
        .report-paper { background: white; width: 210mm; min-height: 297mm; padding: 20px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .school-logo { width: 100px; height: 100px; object-fit: cover; }
        .student-photo { width: 110px; height: 120px; border: 2px solid #333; object-fit: cover; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; vertical-align: middle; font-size: 14px; }
        .header-title { border-bottom: 2px solid #000; margin-bottom: 20px; }
        @media print {
            body { background: none; }
            .report-paper { box-shadow: none; margin: 0; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container py-4 no-print text-center">
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report Card</button>
</div>

<div class="report-paper">
    <div class="header-title text-center pb-3">
        <div class="row align-items-center">
            <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
            <div class="col-8">
                <h2 class="fw-bold m-0"><?= strtoupper($school['school_name']) ?></h2>
                <p class="m-0"><?= $school['address'] ?> | PO BOX: <?= $school['pobox'] ?></p>
                <p class="m-0 fw-bold text-primary">Email: <?= $school['phone'] ?></p>
                <h4 class="mt-2 text-decoration-underline">ACADEMIC PROGRESS REPORT</h4>
            </div>
            <div class="col-2"><img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= $student['fullname'] ?>'"></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-7">
            <p><strong>NAME:</strong> <?= strtoupper($student['fullname']) ?></p>
            <p><strong>STUDENT ID:</strong> <?= $student['student_id'] ?></p>
            <p><strong>CLASS:</strong> <?= $student['class_name'] ?> <?= $student['stream'] ?></p>
        </div>
        <div class="col-5">
            <p><strong>TERM:</strong> <?= $term ?> - <?= $year ?></p>
            <p><strong>POSITION IN CLASS:</strong> <span class="badge bg-dark text-white"><?= $position_in_class ?> / <?= $rank ?></span></p>
        </div>
    </div>

    <table class="table table-bordered table-marks">
        <thead class="table-light">
            <tr>
                <th rowspan="2">SUBJECTS</th>
                <th colspan="2">TESTS (40%)</th>
                <th rowspan="2">TEST <br>AVG</th>
                <th colspan="2">EXAM (60%)</th>
                <th rowspan="2">TOTAL <br>(100%)</th>
                <th rowspan="2">GRADE</th>
                <th rowspan="2">RANK</th>
                <th rowspan="2">REMARK</th>
            </tr>
            <tr>
                <th>M1</th>
                <th>M2</th>
                <th>SCR</th>
                <th>60%</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_marks = 0; $count = 0;
            while($m = $marks_result->fetch_assoc()): 
                $total_marks += $m['total_100']; $count++;
            ?>
            <tr>
                <td class="text-start fw-bold"><?= $m['subject_name'] ?></td>
                <td><?= $m['monthly_1'] ?></td>
                <td><?= $m['monthly_2'] ?></td>
                <td><?= number_format($m['test_avg_40'], 1) ?></td>
                <td><?= number_format($m['exam_60'] / 0.6, 1) ?></td> <td><?= number_format($m['exam_60'], 1) ?></td>
                <td class="fw-bold"><?= number_format($m['total_100'], 1) ?></td>
                <td class="fw-bold"><?= $m['grade'] ?></td>
                <td><?= $m['subject_rank'] ?></td>
                <td class="small"><?= $m['remark'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-6">
            <table class="table table-sm table-bordered">
                <tr class="table-light"><th colspan="2">GRADE SUMMARY</th></tr>
                <tr><td>Average Score:</td><td><strong><?= ($count > 0) ? number_format($total_marks / $count, 1) : 0 ?></strong></td></tr>
                <tr><td>Overall Grade:</td><td><strong>A</strong></td></tr>
            </table>
        </div>
        <div class="col-6">
             <div class="border p-2" style="height: 100px;">
                <h6 class="fw-bold border-bottom">Class Teacher's Comments:</h6>
                <p class="small text-muted">He is a hard working student. Keep it up!</p>
             </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-4 text-center">
            <p class="mb-0">___________________</p>
            <p class="fw-bold">Class Teacher</p>
        </div>
        <div class="col-4 text-center">
            <p class="mb-0">___________________</p>
            <p class="fw-bold">Headmaster</p>
        </div>
        <div class="col-4 text-center">
             <div class="border" style="width: 100px; height: 100px; margin: auto;">School Stamp</div>
        </div>
    </div>
</div>

</body>
</html>