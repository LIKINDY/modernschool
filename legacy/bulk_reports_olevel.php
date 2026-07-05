<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class_name = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class_name || !$year || !$term) {
    echo "<script>alert('Missing Parameters'); window.history.back();</script>";
    exit;
}

// 1. Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch Head of School Name
$head_res = $conn->query("SELECT fullname FROM users WHERE role = 'admin' LIMIT 1");
$head_name = ($head_res && $head_res->num_rows > 0) ? $head_res->fetch_assoc()['fullname'] : "__________________________";

// 3. Fetch Class Teacher Name
$ct_res = $conn->query("SELECT t.fullname FROM subject_assignments sa JOIN teachers t ON sa.teacher_id = t.id WHERE sa.class_name = '$class_name' LIMIT 1");
$class_teacher_name = ($ct_res && $ct_res->num_rows > 0) ? $ct_res->fetch_assoc()['fullname'] : "__________________________";

// 4. Fetch All Active Students
$students_query = "SELECT * FROM students WHERE class_name = '$class_name' AND status = 'active' ORDER BY fullname ASC";
$students_res = $conn->query($students_query);

// 5. Determine Report Type
$is_midterm = (strpos(strtolower($term), 'midterm') !== false);
$score_column = $is_midterm ? 'monthly_1' : 'total_100';

// Improved Grading Function
function getOlevelGrade($score) {
    if ($score >= 75) return ['A', 'Excellent', 1];
    if ($score >= 65) return ['B', 'Very Good', 2];
    if ($score >= 50) return ['C', 'Good', 3]; 
    if ($score >= 35) return ['D', 'Satisfactory', 4];
    return ['F', 'Fail', 5];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Reports - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #525659; padding: 0; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* PAGE SETUP */
        .report-page {
            background: white; 
            width: 210mm; 
            min-height: 297mm; /* Standard A4 Height */
            padding: 15mm;
            margin: 20px auto; 
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
            border: 1px solid #333;
            position: relative;
        }

        /* HEADER STYLES */
        .school-logo { width: 90px; height: 90px; object-fit: contain; }
        .student-photo { width: 95px; height: 105px; border: 1px solid #000; border-radius: 5px; }
        .header-section { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        
        /* TABLE STYLES */
        .table-marks th { background: #f2f2f2 !important; color: #000; font-weight: bold; text-transform: uppercase; font-size: 13px; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 6px; font-size: 14px; }
        
        /* COMMENTS STYLE */
        .comment-label { font-weight: bold; text-decoration: underline; text-transform: uppercase; font-size: 13px; color: #222; }
        .comment-text { 
            padding: 5px 0; 
            min-height: 40px; 
            font-style: italic; 
            font-size: 14px; 
            border-bottom: 1px dotted #000; 
            margin-bottom: 15px;
        }

        /* GRADE SUMMARY */
        .grade-summary-card { border: 1px solid #000; font-size: 11px; width: 100%; }
        .grade-summary-card thead { background: #eee; font-weight: bold; }
        .grade-summary-card td { border: 1px solid #000; padding: 4px; }

        /* PRINT SETTINGS */
        @media print {
            body { background: none; padding: 0; margin: 0; }
            .no-print { display: none; }
            .report-page { 
                margin: 0; 
                box-shadow: none; 
                width: 100%; 
                border: none;
                page-break-after: always; /* Forces each report to start on a new page */
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="text-center no-print my-4">
    <button onclick="window.print()" class="btn btn-primary btn-lg px-5 shadow">PRINT ALL REPORTS</button>
</div>

<?php 
if($students_res->num_rows > 0):
    while($st = $students_res->fetch_assoc()):
        $student_id = $st['id'];
        $first_name = explode(' ', trim($st['fullname']))[0];

        // Fetch Marks
        $marks_sql = "SELECT m.*, s.subject_name 
                      FROM marks m 
                      JOIN subjects s ON m.subject_id = s.id 
                      WHERE m.student_id = '$student_id' AND m.year = '$year' AND m.term = '$term'
                      ORDER BY s.subject_name ASC";
        $marks_res = $conn->query($marks_sql);

        $marks_data = [];
        $total_marks = 0; $subject_count = 0; $point_list = [];

        while ($row = $marks_res->fetch_assoc()) {
            $score = $is_midterm ? $row['monthly_1'] : $row['total_100'];
            $g_data = getOlevelGrade($score);
            $point_list[] = $g_data[2];

            // Position per Subject
            $sub_id = $row['subject_id'];
            $rank_sub_sql = "SELECT m.student_id FROM marks m 
                             JOIN students s2 ON m.student_id = s2.id 
                             WHERE m.subject_id = '$sub_id' AND m.year = '$year' AND m.term = '$term' 
                             AND s2.class_name = '$class_name' ORDER BY m.$score_column DESC";
            $rank_res_sub = $conn->query($rank_sub_sql);
            $sub_pos = 1;
            while($r_sub = $rank_res_sub->fetch_assoc()){
                if($r_sub['student_id'] == $student_id) break;
                $sub_pos++;
            }
            
            $row['pos'] = $sub_pos;
            $row['grade'] = $g_data[0];
            $row['remark'] = $g_data[1];
            $marks_data[] = $row;
            $total_marks += $score;
            $subject_count++;
        }

        // Division & Points (Best 7)
        sort($point_list);
        $best_seven = array_slice($point_list, 0, 7);
        $total_points = array_sum($best_seven);
        
        $division = "IV";
        if ($total_points <= 17) $division = "I";
        elseif ($total_points <= 21) $division = "II";
        elseif ($total_points <= 25) $division = "III";
        elseif ($total_points > 33) $division = "0";

        $average = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;
        $avg_grade = getOlevelGrade($average);

        // Overall Position
        $overall_rank_sql = "SELECT m.student_id, SUM(m.$score_column) as total_score FROM marks m 
                             JOIN students s4 ON m.student_id = s4.id 
                             WHERE m.year = '$year' AND m.term = '$term' AND s4.class_name = '$class_name' 
                             GROUP BY m.student_id ORDER BY total_score DESC";
        $overall_res = $conn->query($overall_rank_sql);
        $total_in_class = $overall_res->num_rows;
        $overall_pos = 0; $counter = 0;
        while($r = $overall_res->fetch_assoc()){
            $counter++;
            if($r['student_id'] == $student_id){ $overall_pos = $counter; break; }
        }

        // Comments
        if ($division == "I") {
            $t_comment = "Excellent academic performance! $first_name, keep up the outstanding effort.";
            $h_comment = "Highly commendable results. Maintain this high standard of discipline and performance.";
        } elseif ($division == "II") {
            $t_comment = "Very good performance. Aim for Division I in the next assessment.";
            $h_comment = "A solid performance. Consistency is key to reaching the top grade.";
        } elseif ($division == "III") {
            $t_comment = "Fairly good performance, but there is room for significant improvement.";
            $h_comment = "Average results. You must double your efforts in all subjects.";
        } else {
            $t_comment = "Poor results. You need a more serious approach to your studies.";
            $h_comment = "Unsatisfactory. Serious academic improvement is required immediately.";
        }
?>

<div class="report-page">
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-2 text-start"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
            <div class="col-8">
                <h2 class="fw-bold text-uppercase mb-0" style="color: #1a237e;"><?= $school['school_name'] ?></h2>
                <p class="mb-0 fw-bold"><?= $school['address'] ?> | TEL: <?= $school['phone'] ?></p>
                <h5 class="mt-2 fw-bold text-uppercase border-top border-bottom py-1">
                    <?= $term ?> - <?= $year ?> PROGRESS REPORT
                </h5>
            </div>
            <div class="col-2 text-end">
                <img src="uploads/students/<?= $st['photo'] ?>" class="student-photo" onerror="this.src='https://via.placeholder.com/95x105?text=Photo'">
            </div>
        </div>
    </div>

    <div class="row mb-3 text-uppercase fw-bold small">
        <div class="col-7">
            <p class="mb-1">Student Name: <span class="text-primary"><?= $st['fullname'] ?></span></p>
            <p class="mb-1">Student ID: <span><?= $st['student_id'] ?></span></p>
        </div>
        <div class="col-5 text-end">
            <p class="mb-1">Class: <span><?= $st['class_name'] ?> <?= $st['stream'] ?></span></p>
            <p class="mb-1">Division: <span class="badge bg-dark">DIV <?= $division ?> (PTS: <?= $total_points ?>)</span></p>
        </div>
    </div>

    <table class="table table-marks w-100">
        <thead>
            <tr>
                <th class="text-start">Subject</th>
                <?php if($is_midterm): ?>
                    <th>Score (%)</th>
                <?php else: ?>
                    <th>Test (40)</th>
                    <th>Exam (60)</th>
                    <th>Total (100)</th>
                <?php endif; ?>
                <th>Grade</th>
                <th>Rank</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <?php if($is_midterm): ?>
                    <td><?= $m['monthly_1'] ?></td>
                <?php else: ?>
                    <td><?= $m['test_avg_40'] ?></td>
                    <td><?= $m['exam_60'] ?></td>
                    <td class="fw-bold"><?= $m['total_100'] ?></td>
                <?php endif; ?>
                <td class="fw-bold"><?= $m['grade'] ?></td>
                <td><?= $m['pos'] ?></td>
                <td style="font-size: 12px;"><?= $m['remark'] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold" style="background: #f8f9fa;">
                <td class="text-start">SUMMARY</td>
                <td colspan="<?= $is_midterm ? '1' : '3' ?>">AVERAGE: <?= number_format($average, 1) ?>%</td>
                <td style="color: blue;">GRADE: <?= $avg_grade[0] ?></td>
                <td colspan="2">POSITION: <?= $overall_pos ?> / <?= $total_in_class ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-12">
            <label class="comment-label">Class Teacher's Comments:</label>
            <div class="comment-text"><?= $t_comment ?></div>
        </div>
        <div class="col-12">
            <label class="comment-label">Head of School's Comments:</label>
            <div class="comment-text"><?= $h_comment ?></div>
        </div>
    </div>

    <div class="row mt-auto align-items-end">
        <div class="col-5">
            <table class="grade-summary-card text-center">
                <thead><tr><td colspan="2">GRADING CRITERIA</td></tr></thead>
                <tbody>
                    <tr><td>A : 75 - 100 (Excellent)</td><td>D : 35 - 49 (Satisfactory)</td></tr>
                    <tr><td>B : 65 - 74 (Very Good)</td><td>F : 00 - 34 (Fail)</td></tr>
                    <tr><td>C : 50 - 64 (Good)</td><td><b>PASS MARK: 50%</b></td></tr>
                </tbody>
            </table>
        </div>
        <div class="col-7">
            <div class="row text-center fw-bold" style="font-size: 12px;">
                <div class="col-6">
                    <br><br>
                    <div class="border-top border-dark pt-1">Class Teacher<br>(<?= $class_teacher_name ?>)</div>
                </div>
                <div class="col-6">
                    <br><br>
                    <div class="border-top border-dark pt-1">Head of School<br>(<?= $head_name ?>)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4 pt-2 border-top" style="color: #777; font-size: 10px;">
        Report Printed on <?= date('d M Y') ?> | Likindy Digital Systems
    </div>
</div>

<?php 
    endwhile; 
else:
    echo "<div class='alert alert-danger container mt-5'>No active students found.</div>";
endif; 
?>

</body>
</html>