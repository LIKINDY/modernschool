<?php
session_start();
include('db_config.php');

$report_ready = false;
$error = "";

// 1. Maelezo ya Shule
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = ($school_res) ? $school_res->fetch_assoc() : null;

// Pata walimu kwa ajili ya dropdown
$teachers_list = $conn->query("SELECT id, fullname FROM teachers WHERE status = 'active' ORDER BY fullname ASC");

if (isset($_POST['generate_batch'])) {
    $year = $conn->real_escape_string($_POST['year']);
    $exam_type = $conn->real_escape_string($_POST['exam_type']);
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $stream = $conn->real_escape_string($_POST['stream']);
    $selected_teacher_id = $_POST['class_teacher_id'] ?? '';

    // Pata jina na namba ya simu ya mwalimu
    $teacher_name = "__________________________";
    $teacher_phone = "__________";
    if(!empty($selected_teacher_id)){
        $t_info = $conn->query("SELECT fullname, phone FROM teachers WHERE id = '$selected_teacher_id'")->fetch_assoc();
        $teacher_name = $t_info['fullname'] ?? "__________________________";
        $teacher_phone = $t_info['phone'] ?? "__________";
    }

    // Fetch students
    $st_sql = "SELECT * FROM students WHERE class_name = '$class_name' AND stream = '$stream' ORDER BY fullname ASC";
    $students_res = $conn->query($st_sql);

    if ($students_res && $students_res->num_rows > 0) {
        $report_ready = true;
    } else {
        $error = "No students found for $class_name $stream.";
    }
}

// O-Level Grading Function (Passing mark 50)
function getOlevelGrade($score) {
    if ($score >= 80) return ['A', 'Excellent', 1, '#000'];
    if ($score >= 70) return ['B', 'Very Good', 2, '#000'];
    if ($score >= 60) return ['C', 'Good', 3, '#000'];
    if ($score >= 50) return ['D', 'Pass', 4, '#000'];
    return ['F', 'Fail', 5, '#000'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Report | O-Level</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Arial', sans-serif; font-size: 13px; color: #000; }
        .filter-section { background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        
        .report-paper { 
            width: 210mm; min-height: 297mm; margin: 10px auto; padding: 15mm; 
            background: #fff; border: 1px solid #000; page-break-after: always; position: relative;
        }

        /* Header Styling matching High View School layout */
        .school-name { font-size: 26px; font-weight: 900; letter-spacing: 1px; margin-bottom: 0; }
        .school-info-text { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .header-line { border-bottom: 2px solid #000; margin: 10px 0; }
        .school-logo { width: 90px; height: 90px; object-fit: contain; }
        .student-photo { width: 100px; height: 110px; border: 1px solid #000; object-fit: cover; }

        /* Tables */
        .table-marks { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; padding: 4px 8px; text-align: center; }
        .table-marks th { background: #f2f2f2; font-weight: bold; font-size: 12px; }
        .text-left { text-align: left !important; }

        /* Comments area matching the requested image layout */
        .bottom-grid { display: flex; justify-content: space-between; margin-top: 20px; }
        .comment-area { width: 65%; }
        .grade-summary-area { width: 32%; border: 1px solid #000; padding: 10px; height: fit-content; }
        
        .comment-box-title { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 5px; }
        .comment-content { font-style: italic; line-height: 1.3; border-bottom: 1px dotted #000; display: block; min-height: 45px; margin-bottom: 15px; }

        .grade-table { width: 100%; font-size: 11px; }
        .grade-table td { padding: 2px 0; }
        
        .signature-section { display: flex; justify-content: space-between; margin-top: 60px; text-align: center; font-weight: bold; }
        .sig-line { width: 30%; border-top: 1px solid #000; padding-top: 5px; font-size: 12px; }

        @media print { 
            .no-print { display: none; } 
            body { background: none; padding: 0; margin: 0; } 
            .report-paper { margin: 0; border: none; width: 100%; padding: 10mm; } 
        }
    </style>
</head>
<body>

<div class="container py-4 no-print">
    <div class="filter-section">
        <h4 class="fw-bold text-primary mb-3">O-Level Batch Report Generator</h4>
        <form method="POST" class="row g-3">
            <div class="col-md-2">
                <label class="fw-bold small">CLASS</label>
                <select name="class_name" class="form-select" required>
                    <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c'>$c</option>"; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-bold small">STREAM</label>
                <select name="stream" class="form-select" required>
                    <?php foreach(range('A','F') as $s) echo "<option value='$s'>$s</option>"; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-bold small">ACADEMIC YEAR</label>
                <select name="year" class="form-select" required>
                    <?php for($y=2015;$y<=2035;$y++) { $p = "$y/".($y+1); echo "<option value='$p'>$p</option>"; } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="fw-bold small">CLASS TEACHER</label>
                <select name="class_teacher_id" class="form-select" required>
                    <option value="">-- Select Teacher --</option>
                    <?php 
                    $teachers_list->data_seek(0);
                    while($t = $teachers_list->fetch_assoc()) echo "<option value='".$t['id']."'>".$t['fullname']."</option>"; 
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="fw-bold small">EXAM TYPE</label>
                <select name="exam_type" class="form-select" required>
                    <option>Term 1</option><option>Term 2</option><option>Terminal</option><option>Mock Exam</option><option>Annual</option>
                </select>
            </div>
            <div class="col-12 text-center mt-3">
                <button type="submit" name="generate_batch" class="btn btn-primary btn-lg px-5 fw-bold shadow">GENERATE BATCH REPORTS</button>
            </div>
        </form>
    </div>
</div>

<?php if ($report_ready): ?>
    <div class="text-center no-print mb-4">
        <button onclick="window.print()" class="btn btn-success btn-lg px-5 shadow">PRINT ALL REPORTS</button>
    </div>

    <?php while($student = $students_res->fetch_assoc()): 
        $student_db_id = $student['id'];
        $student_name = $student['fullname'];

        // Marks query with Position/Rank logic
        $marks_sql = "SELECT m.*, sub.subject_name,
                      (SELECT MAX((monthly_mark/(CASE WHEN monthly_base > 0 THEN monthly_base ELSE 100 END)*40) + (paper1_mark/(CASE WHEN exam_base > 0 THEN exam_base ELSE 100 END)*60)) 
                       FROM olevel_marks m2 
                       WHERE m2.subject_id = m.subject_id AND m2.academic_year = '$year' 
                       AND m2.exam_type = '$exam_type' AND m2.class_name = '$class_name') as highest_in_class,
                      (SELECT COUNT(*) + 1 
                       FROM olevel_marks m3 
                       WHERE m3.subject_id = m.subject_id AND m3.academic_year = '$year' 
                       AND m3.exam_type = '$exam_type' AND m3.class_name = '$class_name'
                       AND ((m3.monthly_mark/(CASE WHEN m3.monthly_base > 0 THEN m3.monthly_base ELSE 100 END)*40) + (m3.paper1_mark/(CASE WHEN m3.exam_base > 0 THEN m3.exam_base ELSE 100 END)*60)) > 
                           ((m.monthly_mark/(CASE WHEN m.monthly_base > 0 THEN m.monthly_base ELSE 100 END)*40) + (m.paper1_mark/(CASE WHEN m.exam_base > 0 THEN m.exam_base ELSE 100 END)*60))
                      ) as subject_rank
                      FROM olevel_marks m 
                      JOIN olevel_subjects sub ON m.subject_id = sub.id 
                      WHERE m.student_id = '$student_db_id' AND m.academic_year = '$year' AND m.exam_type = '$exam_type'";
        
        $marks_res = $conn->query($marks_sql);
        $marks_data = []; $total_pts_array = []; $grand_total = 0; $sub_count = 0; $weak_list = "";

        if ($marks_res && $marks_res->num_rows > 0) {
            while ($row = $marks_res->fetch_assoc()) {
                $m_base = ($row['monthly_base'] > 0) ? $row['monthly_base'] : 100;
                $e_base = ($row['exam_base'] > 0) ? $row['exam_base'] : 100;

                $row['test_100'] = ($row['monthly_mark'] / $m_base) * 100;
                $row['exam_100'] = ($row['paper1_mark'] / $e_base) * 100;
                $row['final_total'] = ($row['test_100'] * 0.4) + ($row['exam_100'] * 0.6);

                $gi = getOlevelGrade($row['final_total']);
                $row['grade'] = $gi[0];
                $row['remark'] = $gi[1];
                $row['point'] = $gi[2];
                $row['color'] = $gi[3];
                
                if($gi[0] == 'F') $weak_list .= $row['subject_name'] . ", ";
                $total_pts_array[] = $gi[2];
                $marks_data[] = $row;
                $grand_total += $row['final_total'];
                $sub_count++;
            }
        }

        // Division & Points
        $average = ($sub_count > 0) ? ($grand_total / $sub_count) : 0;
        $total_points = 0;
        $division = "N/A";

        if (!empty($total_pts_array)) {
            sort($total_pts_array);
            $best_7 = array_slice($total_pts_array, 0, 7);
            $total_points = array_sum($best_7);
            if(count($total_pts_array) < 7) { $division = "INC"; }
            else {
                if ($total_points <= 17) $division = "I";
                elseif ($total_points <= 21) $division = "II";
                elseif ($total_points <= 25) $division = "III";
                elseif ($total_points <= 33) $division = "IV";
                else $division = "0";
            }
        }

        // Comment Switch
        $needs_effort = (!empty($weak_list)) ? " Improve on: " . rtrim($weak_list, ", ") : "";
        switch($division) {
            case 'I':
                $t_comment = "Outstanding performance, $student_name! You have shown brilliance. Maintain this momentum.";
                $h_comment = "A highly disciplined student. Their academic trajectory is promising.";
                break;
            case 'II':
                $t_comment = "Very good work, $student_name. Solid foundation, but room for improvement to reach Div I.$needs_effort";
                $h_comment = "Hardworking with steady progress. Focus on weak areas to achieve top-tier results.";
                break;
            case 'III':
                $t_comment = "Good effort, $student_name, but performance is at average level. Double your study hours.$needs_effort";
                $h_comment = "Performance is fair but requires more serious academic engagement and revision.";
                break;
            case 'IV':
                $t_comment = "You have passed, $student_name, but results are weak. Reassess your study habits.$needs_effort";
                $h_comment = "Academic performance is below expectations. Requires strict supervision and a study plan.";
                break;
            default:
                $t_comment = "This is a disappointing result, $student_name. Use this as a wake-up call to work harder.$needs_effort";
                $h_comment = "Performance is unsatisfactory. Immediate intervention from parents is necessary.";
                break;
        }
    ?>

    <div class="report-paper">
        <!-- Header -->
        <div class="text-center position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
                <div class="flex-grow-1">
                    <h1 class="school-name"><?= strtoupper($school['school_name']) ?></h1>
                    <p class="school-info-text">P.O.BOX <?= $school['pobox'] ?? '141' ?>, PHONE <?= $school['phone'] ?? '+255 77393999' ?></p>
                    <p class="school-info-text"><?= strtoupper($school['address']) ?></p>
                </div>
                <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student_name) ?>&size=100'">
            </div>
            <div class="header-line"></div>
            <h5 class="fw-bold text-uppercase"><?= strtoupper($exam_type) ?> PROGRESS REPORT</h5>
        </div>

        <!-- Student Info -->
        <div class="row mb-2 fw-bold mt-3" style="font-size: 14px;">
            <div class="col-7">
                STUDENT NAME: <span class="text-uppercase"><?= $student['fullname'] ?></span> (<?= $student['student_id'] ?>)<br>
                CLASS ACADEMIC YEAR: <?= $class_name ?> <?= $year ?> <?= $exam_type ?>
            </div>
            <div class="col-5 text-end">
                DIVISION: <?= $division ?>
            </div>
        </div>

        <!-- Table -->
        <table class="table-marks">
            <thead>
                <tr>
                    <th class="text-left">SUBJECTS</th>
                    <th width="12%">EXAM 100%</th>
                    <th width="10%">POINTS</th>
                    <th width="10%">GRADE</th>
                    <th width="15%">POSITION IN SUBJECT</th>
                    <th width="15%">REMARKS</th>
                    <th width="15%">HIGHEST MARKS IN CLASS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marks_data as $m): ?>
                <tr>
                    <td class="text-left fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                    <td><?= number_format($m['final_total'], 0) ?></td>
                    <td><?= $m['point'] ?></td>
                    <td><?= $m['grade'] ?></td>
                    <td><?= $m['subject_rank'] ?></td>
                    <td><?= $m['remark'] ?></td>
                    <td><?= number_format($m['highest_in_class'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold">
                    <td class="text-left">TOTAL MARKS</td>
                    <td><?= number_format($grand_total, 0) ?></td>
                    <td colspan="1">OUT OF:</td>
                    <td><?= $sub_count * 100 ?></td>
                    <td colspan="3"></td>
                </tr>
                <tr class="fw-bold bg-light">
                    <td class="text-left">AVERAGE</td>
                    <td><?= number_format($average, 2) ?>%</td>
                    <td><?= $total_points ?></td>
                    <td><?= getOlevelGrade($average)[0] ?></td>
                    <td></td>
                    <td><?= getOlevelGrade($average)[1] ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <p class="mt-2 fw-bold">CLASS TEACHER'S NAME: <?= strtoupper($teacher_name) ?></p>

        <!-- Comments & Grades Grid -->
        <div class="bottom-grid">
            <div class="comment-area">
                <span class="comment-box-title">TEACHER'S COMMENTS</span>
                <span class="comment-content"><?= $t_comment ?></span>

                <span class="comment-box-title">HEAD'S COMMENTS</span>
                <span class="comment-content"><?= $h_comment ?></span>
            </div>

            <div class="grade-summary-area">
                <table class="grade-table">
                    <tr class="fw-bold border-bottom"><td width="30%">GRADE</td><td>PERCENTAGE</td></tr>
                    <tr><td><strong>A</strong></td><td>: 80 -- 100 ---> Excellent</td></tr>
                    <tr><td><strong>B</strong></td><td>: 70 -- 79 ---> Very Good</td></tr>
                    <tr><td><strong>C</strong></td><td>: 60 -- 69 ---> Good</td></tr>
                    <tr><td><strong>D</strong></td><td>: 50 -- 59 ---> Pass</td></tr>
                    <tr><td><strong>F</strong></td><td>: 0 -- 49 ---> Fail</td></tr>
                </table>
                <p class="text-center fw-bold mt-2 pt-2 border-top mb-0">PASSING MARKS 50</p>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="sig-line">
                CLASS TEACHER'S SIGNATURE<br>
                <small>Ph: <?= $teacher_phone ?></small>
            </div>
            <div class="sig-line">HEAD'S SIGNATURE</div>
            <div class="sig-line">PARENT'S SIGNATURE</div>
        </div>
    </div>
    <?php endwhile; ?>

<?php elseif(!empty($error)): ?>
    <div class="container mt-5"><div class="alert alert-danger text-center shadow-sm"><?= $error ?></div></div>
<?php endif; ?>

</body>
</html>