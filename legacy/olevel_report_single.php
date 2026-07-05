<?php
session_start();
include('db_config.php');

$report_ready = false;
$error_message = "";

// 1. Maelezo ya Shule
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = ($school_res) ? $school_res->fetch_assoc() : null;

// Pata walimu kwa ajili ya dropdown
$teachers_list = $conn->query("SELECT id, fullname FROM teachers WHERE status = 'active' ORDER BY fullname ASC");

if (isset($_POST['view_report'])) {
    $search_student = $conn->real_escape_string($_POST['student_search']);
    $year = $conn->real_escape_string($_POST['year']);
    $exam_type = $conn->real_escape_string($_POST['exam_type']);
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $selected_teacher_id = $_POST['class_teacher_id'] ?? '';

    // Pata jina na namba ya simu ya mwalimu
    $teacher_name = "__________________________";
    $teacher_phone = "__________";
    if(!empty($selected_teacher_id)){
        $t_info = $conn->query("SELECT fullname, phone FROM teachers WHERE id = '$selected_teacher_id'")->fetch_assoc();
        $teacher_name = $t_info['fullname'] ?? "__________________________";
        $teacher_phone = $t_info['phone'] ?? "__________";
    }

    // Mtafute Mwanafunzi
    $st_sql = "SELECT s.* FROM students s 
               WHERE (s.student_id = '$search_student' OR s.fullname LIKE '%$search_student%' OR s.id = '$search_student') 
               LIMIT 1";
    $st_res = $conn->query($st_sql);
    
    if ($st_res && $st_res->num_rows > 0) {
        $student = $st_res->fetch_assoc();
        $student_db_id = $student['id'];
        $student_name = $student['fullname'];
        $report_ready = true;

        // O-Level Grading Function (50 is passing)
        function getOlevelGrade($score) {
            if ($score >= 80) return ['A', 'Excellent', 1, '#000'];
            if ($score >= 70) return ['B', 'Very Good', 2, '#000'];
            if ($score >= 60) return ['C', 'Good', 3, '#000'];
            if ($score >= 50) return ['D', 'Pass', 4, '#000'];
            return ['F', 'Fail', 5, '#000'];
        }

        // Pata Marks na Normalization ya Position
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
        } else {
            $error_message = "No marks found for this student in the selected period.";
            $report_ready = false;
        }

        // Division & Average Logic
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
        
        // Comment System (Logic remains untouched as requested)
        $needs_effort = (!empty($weak_list)) ? " Put more effort in: " . rtrim($weak_list, ", ") : "";
        switch($division) {
            case 'I':
                $teacher_comment = "Outstanding performance, $student_name! You have shown great consistency and academic brilliance. Maintain this momentum to reach your highest potential.";
                $head_comment = "A highly disciplined and focused student. Their academic trajectory is promising; they should continue with this exemplary work ethic.";
                break;
            case 'II':
                $teacher_comment = "Very good work, $student_name. You have a solid foundation, but there is still room for improvement to reach Division I.$needs_effort";
                $head_comment = "A hardworking student with steady progress. With a bit more concentration on their weaker areas, they can easily achieve a top-tier division.";
                break;
            case 'III':
                $teacher_comment = "Good effort, $student_name, but your performance is currently at an average level. You need to double your study hours and focus on core concepts.$needs_effort";
                $head_comment = "The student’s performance is fair but requires more serious academic engagement. Consistent revision and consultation with teachers are highly recommended.";
                break;
            case 'IV':
                $teacher_comment = "You have passed, $student_name, but your results are quite weak. This is a critical time to reassess your study habits before it is too late.$needs_effort";
                $head_comment = "Academic performance is below expectations. The student requires strict supervision and a personalized study plan to improve their future outcomes.";
                break;
            default:
                $teacher_comment = "This is a disappointing result, $student_name. However, failing once is not the end. Use this as a wake-up call to change your attitude and work harder than ever.$needs_effort";
                $head_comment = "Performance is unsatisfactory. Immediate intervention from both parents and teachers is necessary to identify the root cause of this decline.";
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card | <?= $student['fullname'] ?? 'Student' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Arial', sans-serif; font-size: 13px; color: #000; }
        .report-paper { width: 210mm; min-height: 297mm; margin: 10px auto; padding: 15mm; background: #fff; border: 1px solid #000; position: relative; }
        
        /* Header Styling based on Image */
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

        /* Comments & Grade Summary Grid */
        .bottom-grid { display: flex; justify-content: space-between; margin-top: 20px; }
        .comment-area { width: 65%; }
        .grade-summary-area { width: 32%; border: 1px solid #000; padding: 10px; height: fit-content; }
        
        .comment-box { margin-bottom: 15px; }
        .comment-label { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 5px; }
        .comment-content { font-style: italic; line-height: 1.3; border-bottom: 1px dotted #000; display: block; min-height: 45px; }

        .grade-table { width: 100%; font-size: 11px; }
        .grade-table td { padding: 2px 0; }
        
        .signature-section { display: flex; justify-content: space-between; margin-top: 60px; text-align: center; font-weight: bold; }
        .sig-line { width: 30%; border-top: 1px solid #000; padding-top: 5px; font-size: 12px; }

        @media print { .no-print { display: none; } .report-paper { margin: 0; border: none; width: 100%; } }
    </style>
</head>
<body>

<!-- Filter Section (Haitaguswa kama ulivyoomba) -->
<div class="container py-4 no-print">
    <div class="card p-4 shadow-sm">
        <h4 class="mb-3 fw-bold text-primary">Generate Student Report</h4>
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <label class="fw-bold">Student Name/ID</label>
                <input type="text" name="student_search" class="form-control" placeholder="Name or ID" required>
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Class</label>
                <select name="class_name" class="form-select">
                    <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c'>$c</option>"; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Academic Year</label>
                <select name="year" class="form-select">
                    <?php for($y=2015;$y<=2035;$y++) echo "<option>$y/".($y+1)."</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="fw-bold">Class Teacher</label>
                <select name="class_teacher_id" class="form-select" required>
                    <option value="">-- Select Teacher --</option>
                    <?php 
                    $teachers_list->data_seek(0);
                    while($t = $teachers_list->fetch_assoc()) echo "<option value='".$t['id']."'>".$t['fullname']."</option>"; 
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Exam Type</label>
                <select name="exam_type" class="form-select">
                    <option>Term 1</option><option>Term 2</option><option>Terminal</option><option>Mock Exam</option><option>Annual</option>
                </select>
            </div>
            <div class="col-12 text-center mt-3">
                <button type="submit" name="view_report" class="btn btn-primary px-5 fw-bold">GENERATE REPORT</button>
            </div>
        </form>
    </div>
</div>

<?php if ($report_ready): ?>
<div class="report-paper">
    <!-- Header Section -->
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
        <h5 class="fw-bold text-uppercase"><?= $exam_type ?> PROGRESS REPORT</h5>
    </div>

    <!-- Student Bio Row -->
    <div class="row mb-2 fw-bold mt-3" style="font-size: 14px;">
        <div class="col-7">
            STUDENT NAME: <span class="text-uppercase"><?= $student['fullname'] ?></span> (<?= $student['student_id'] ?>)<br>
            CLASS ACADEMIC YEAR: <?= $class_name ?> <?= $year ?> <?= $exam_type ?>
        </div>
        <div class="col-5 text-end">
            DIVISION: <?= $division ?>
        </div>
    </div>

    <!-- Marks Table -->
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

    <!-- Bottom Layout: Comments and Grade Summary -->
    <div class="bottom-grid">
        <div class="comment-area">
            <div class="comment-box">
                <span class="comment-label">TEACHER'S COMMENTS</span>
                <span class="comment-content"><?= $teacher_comment ?></span>
            </div>
            <div class="comment-box">
                <span class="comment-label">HEAD'S COMMENTS</span>
                <span class="comment-content"><?= $head_comment ?></span>
            </div>
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
        <div class="sig-line">CLASS TEACHER'S SIGNATURE</div>
        <div class="sig-line">HEAD'S SIGNATURE</div>
        <div class="sig-line">PARENT'S SIGNATURE</div>
    </div>
</div>

<div class="text-center no-print py-4">
    <button onclick="window.print()" class="btn btn-success btn-lg px-5">PRINT REPORT CARD</button>
</div>
<?php endif; ?>

</body>
</html>