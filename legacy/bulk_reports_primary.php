<?php
session_start();
include('db_config.php');

$class_name = $_GET['class_name'];
$year = $_GET['year'];
$selected_term = $_GET['term']; // Hii itakuwa "Final Result"

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 1. Vuta wanafunzi wote wa darasa husika
$students_query = $conn->query("SELECT * FROM students WHERE class_name = '$class_name' AND status != 'deleted' ORDER BY fullname ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Annual Reports - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', serif; background: #f4f4f4; }
        .report-paper { 
            width: 210mm; margin: 10mm auto; padding: 20px; 
            border: 1px solid #000; background: #fff; page-break-after: always; 
            position: relative; min-height: 280mm;
        }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 4px; }
        @media print { 
            body { background: none; }
            .no-print { display: none; }
            .report-paper { margin: 0; border: none; }
        }
        .student-photo { width: 90px; height: 100px; border: 1px solid #000; position: absolute; right: 20px; top: 20px; }
    </style>
</head>
<body>

<div class="text-center no-print py-4">
    <button onclick="window.print()" class="btn btn-primary px-5 fw-bold">PRINT ALL REPORTS NOW</button>
</div>

<?php 
while ($student = $students_query->fetch_assoc()): 
    $student_id = $student['id'];

    // --- HESABU ZA POSITION (ANNUAL) ---
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
?>

<div class="report-paper">
    <div class="text-center border-bottom pb-2 mb-3">
        <img src="uploads/logo/<?= $school['logo'] ?>" width="80">
        <h3 class="fw-bold text-uppercase m-0"><?= $school['school_name'] ?></h3>
        <p class="m-0 small"><?= $school['address'] ?> | TEL: <?= $school['phone'] ?></p>
        <h5 class="mt-3 fw-bold text-decoration-underline">ANNUAL PROGRESS REPORT CARD</h5>
    </div>

    <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>'">

    <div class="mb-3 small">
        <p class="m-0 text-uppercase">NAME: <strong><?= $student['fullname'] ?></strong></p>
        <p class="m-0 text-uppercase">CLASS: <strong><?= $student['class_name'] ?></strong> <span class="ms-4 text-lowercase">Year:</span> <strong><?= $year ?></strong></p>
        <p class="m-0 text-uppercase">ANNUAL POSITION: <strong><?= $overall_pos ?> / <?= $total_students ?></strong></p>
    </div>

    <table class="table table-marks w-100">
        <thead class="bg-light small">
            <tr>
                <th class="text-start">SUBJECT</th>
                <th>T1 (40%)</th>
                <th>T2 (60%)</th>
                <th>FINAL</th>
                <th>GRADE</th>
                <th>REMARKS</th>
            </tr>
        </thead>
        <tbody class="small">
            <?php 
            $subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");
            $grand_total = 0; $sub_count = 0;
            while($sub = $subjects->fetch_assoc()):
                $sid = $sub['id'];
                $t1 = $conn->query("SELECT total_100 FROM marks WHERE student_id='$student_id' AND subject_id='$sid' AND term='Term 1' AND year='$year'")->fetch_assoc();
                $t2 = $conn->query("SELECT total_100 FROM marks WHERE student_id='$student_id' AND subject_id='$sid' AND term='Term 2' AND year='$year'")->fetch_assoc();
                
                $f_score = (($t1['total_100'] ?? 0) * 0.4) + (($t2['total_100'] ?? 0) * 0.6);
                if($f_score > 0):
                    $grand_total += $f_score; $sub_count++;
                    $grade = ($f_score >= 81) ? 'A' : (($f_score >= 61) ? 'B' : (($f_score >= 41) ? 'C' : (($f_score >= 21) ? 'D' : 'F')));
            ?>
            <tr>
                <td class="text-start"><?= $sub['subject_name'] ?></td>
                <td><?= number_format(($t1['total_100']??0)*0.4, 1) ?></td>
                <td><?= number_format(($t2['total_100']??0)*0.6, 1) ?></td>
                <td class="fw-bold"><?= number_format($f_score, 0) ?></td>
                <td class="fw-bold"><?= $grade ?></td>
                <td class="small">---</td>
            </tr>
            <?php endif; endwhile; ?>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-12 border p-2 small italic">
            <strong>Class Teacher's Comments:</strong> Excellent year, keep it up!
        </div>
    </div>

    <div class="row mt-5 text-center fw-bold small">
        <div class="col-4 border-top pt-1">CLASS TEACHER</div>
        <div class="col-4 border-top pt-1">HEADMASTER</div>
        <div class="col-4 border-top pt-1">PARENT</div>
    </div>
</div>

<?php endwhile; ?>

</body>
</html>