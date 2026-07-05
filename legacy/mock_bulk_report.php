<?php
/**
 * PAGE: Mock Student Report Generation
 * SOURCE TABLE: mock_results
 * COLUMNS: id, student_id, subject_name, class_name, stream, academic_year, p1, p2, total, grade, points, created_at
 */

session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get Parameters from URL
$class_name = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? ''; // e.g., 2024/2025
$term = $_GET['term'] ?? ''; 

if (!$class_name || !$year || !$term) {
    echo "<script>alert('Error: Missing Required Parameters'); window.history.back();</script>";
    exit;
}

// Fetch School Information
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// Fetch Headmaster/Admin Name
$head_res = $conn->query("SELECT fullname FROM users WHERE role = 'admin' LIMIT 1");
$head_name = ($head_res && $head_res->num_rows > 0) ? $head_res->fetch_assoc()['fullname'] : "__________________________";

// Fetch Active Students in the specified class
$students_res = $conn->query("SELECT * FROM students WHERE class_name = '$class_name' AND status = 'active' ORDER BY fullname ASC");

/**
 * Helper function to map Grade to Points
 */
function getGradePoints($grade) {
    $grade = strtoupper(trim($grade));
    switch ($grade) {
        case 'A': return 1;
        case 'B': return 2;
        case 'C': return 3;
        case 'D': return 4;
        case 'F': return 5;
        default: return 5;
    }
}

/**
 * Calculate Division based on Total Points (Best 7 Subjects)
 */
function calculateDivision($total_points, $subject_count) {
    if ($subject_count < 7) return "INC"; // Incomplete if less than 7 subjects
    if ($total_points <= 17) return "I";
    if ($total_points <= 21) return "II";
    if ($total_points <= 25) return "III";
    if ($total_points <= 33) return "IV";
    return "0";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACADEMIC REPORT - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #525659; padding: 30px 0; font-family: 'Times New Roman', serif; }
        .report-page {
            background: white; width: 210mm; min-height: 297mm; padding: 20mm;
            margin: 0 auto 30px auto; box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative; page-break-after: always; border: 1px solid #000;
        }
        .school-logo { width: 85px; height: 85px; object-fit: contain; }
        .student-photo { width: 90px; height: 100px; object-fit: cover; border: 1px solid #000; }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 5px; font-size: 14px; }
        .summary-box { border: 2px solid #000; padding: 10px; background: #f9f9f9; font-weight: bold; }
        .comment-box { border-bottom: 1px dashed #000; padding: 5px; min-height: 40px; }
        
        @media print {
            body { background: none; padding: 0; }
            .no-print { display: none; }
            .report-page { margin: 0; box-shadow: none; width: 100%; border: 1px solid #000; }
        }
    </style>
</head>
<body>

<div class="text-center no-print mb-4">
    <button onclick="window.print()" class="btn btn-primary btn-lg px-5 shadow">
        <i class="fas fa-print"></i> PRINT ALL REPORTS
    </button>
</div>

<?php 
if($students_res && $students_res->num_rows > 0):
    while($st = $students_res->fetch_assoc()):
        $st_id_from_table = $st['student_id']; 
        
        // QUERY: Fetch results from 'mock_results' table for this student
        $marks_sql = "SELECT * FROM mock_results WHERE student_id = '$st_id_from_table' AND academic_year = '$year' ORDER BY total DESC";
        $marks_res = $conn->query($marks_sql);

        $marks_data = [];
        $points_array = [];
        $total_sum = 0;

        if($marks_res) {
            while ($row = $marks_res->fetch_assoc()) {
                $marks_data[] = $row;
                // Add points based on grade for Division calculation
                $points_array[] = getGradePoints($row['grade']);
                $total_sum += (int)$row['total'];
            }
        }

        // Calculate Points (Best 7)
        sort($points_array);
        $best_seven = array_slice($points_array, 0, 7);
        $sum_points = array_sum($best_seven);
        $subject_count = count($points_array);
        $division = calculateDivision($sum_points, $subject_count);
?>

<div class="report-page">
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-2 text-start">
                <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo" onerror="this.src='https://via.placeholder.com/85'">
            </div>
            <div class="col-8">
                <h2 class="fw-bold text-uppercase mb-0"><?= $school['school_name'] ?></h2>
                <p class="mb-0 fw-bold small"><?= $school['address'] ?> | TEL: <?= $school['phone'] ?></p>
                <h5 class="mt-3 fw-bold text-decoration-underline text-uppercase"><?= $term ?> TERMINAL REPORT - <?= $year ?></h5>
            </div>
            <div class="col-2 text-end">
                <img src="uploads/students/<?= $st['photo'] ?>" class="student-photo" onerror="this.src='https://via.placeholder.com/90x100'">
            </div>
        </div>
    </div>

    <div class="row mb-3 fw-bold text-uppercase small">
        <div class="col-7">
            STUDENT NAME: <span class="border-bottom border-dark px-2"><?= $st['fullname'] ?></span><br>
            ADMISSION NO: <span class="border-bottom border-dark px-2"><?= $st['student_id'] ?></span>
        </div>
        <div class="col-5 text-end">
            CLASS: <span class="border-bottom border-dark px-2"><?= $st['class_name'] ?> - <?= $st['stream'] ?></span><br>
            GENDER: <span class="border-bottom border-dark px-2"><?= $st['gender'] ?></span>
        </div>
    </div>

    <table class="table table-marks w-100">
        <thead class="bg-light">
            <tr>
                <th class="text-start">SUBJECTS</th>
                <th>P1 (40%)</th>
                <th>P2 (60%)</th>
                <th>TOTAL</th>
                <th>GRADE</th>
                <th>POINTS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td><?= $m['p1'] ?></td>
                <td><?= $m['p2'] ?></td>
                <td class="fw-bold"><?= $m['total'] ?></td>
                <td class="fw-bold"><?= $m['grade'] ?></td>
                <td><?= getGradePoints($m['grade']) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(empty($marks_data)): ?>
                <tr><td colspan="6" class="py-4 text-danger">No examination records found for this academic period.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-12">
            <div class="summary-box d-flex justify-content-around align-items-center text-center">
                <div>AGGREGATE SCORE<br><span class="h3"><?= $total_sum ?></span></div>
                <div>POINTS (BEST 7)<br><span class="h3"><?= ($subject_count >= 7) ? $sum_points : 'N/A' ?></span></div>
                <div class="px-4 py-2 bg-dark text-white rounded">DIVISION<br><span class="h1 mb-0"><?= $division ?></span></div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-6">
            <p class="fw-bold mb-1 border-bottom">Class Teacher's Remarks:</p>
            <div class="comment-box mt-1">
                Excellent performance! Maintain this consistency for your final examinations.
            </div>
            
            <p class="fw-bold mb-1 mt-3 border-bottom">Head of School Remarks:</p>
            <div class="comment-box mt-1">
                Approved and Certified by the Board of Examinations.
            </div>
        </div>
        <div class="col-6 text-center">
            <br><br><br>
            <p class="mb-0">__________________________</p>
            <p class="fw-bold small">School Official Stamp & Signature</p>
            <p class="small"><?= $head_name ?></p>
        </div>
    </div>

    <div class="text-center mt-5 small text-muted border-top pt-2">
        Generated on <?= date('d-M-Y H:i') ?> | Academic Excellence Management System
    </div>
</div>

<?php 
    endwhile; 
else:
    echo "<div class='alert alert-warning m-5'>No active students found for class: $class_name</div>";
endif; 
?>

</body>
</html>