<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$report_ready = false;
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
$teachers_list = $conn->query("SELECT id, fullname FROM teachers WHERE status = 'active' ORDER BY fullname ASC");

if (isset($_POST['view_report'])) {
    $search_student = $conn->real_escape_string($_POST['student_search']);
    $year = $conn->real_escape_string($_POST['year']);
    $exam_type = $conn->real_escape_string($_POST['exam_type']);
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $stream = $conn->real_escape_string($_POST['stream']);
    $selected_teacher_id = $_POST['class_teacher_id'] ?? '';

    // Teacher Info from DB
    $teacher_name = "__________________________";
    $teacher_phone = "";
    if(!empty($selected_teacher_id)){
        $t_info = $conn->query("SELECT fullname, phone FROM teachers WHERE id = '$selected_teacher_id'")->fetch_assoc();
        $teacher_name = $t_info['fullname'];
        $teacher_phone = $t_info['phone'];
    }

    $st_sql = "SELECT * FROM students WHERE (student_id = '$search_student' OR fullname LIKE '%$search_student%') 
               AND class_name = '$class_name' AND stream = '$stream' LIMIT 1";
    $st_res = $conn->query($st_sql);
    
    if ($st_res->num_rows > 0) {
        $student = $st_res->fetch_assoc();
        $student_id = $student['id'];
        $report_ready = true;
        $fname = $student['fullname'];
        
        // Dynamic Title Logic
        $titles = [
            'term1' => 'FIRST TERM PROGRESS REPORT', 
            'term2' => 'SECOND TERM PROGRESS REPORT',
            'special' => 'SPECIAL EXAM PROGRESS REPORT', 
            'terminal' => 'TERMINAL PROGRESS REPORT', 
            'annual' => 'ANNUAL PROGRESS REPORT'
        ];
        $report_title = $titles[$exam_type] ?? strtoupper($exam_type) . " PROGRESS REPORT";

        $marks_sql = "SELECT m.*, s.subject_name, 
                      (SELECT COUNT(*) + 1 FROM primary_marks m2 
                       WHERE m2.subject_id = m.subject_id AND m2.academic_year = '$year' 
                       AND m2.exam_type = '$exam_type' AND m2.class_name = '$class_name' 
                       AND m2.total_mark > m.total_mark) as subject_rank
                      FROM primary_marks m 
                      JOIN primary_subjects s ON m.subject_id = s.id 
                      WHERE m.student_id = '$student_id' AND m.academic_year = '$year' 
                      AND m.exam_type = '$exam_type'";
        
        $marks_res = $conn->query($marks_sql);
        $marks_data = []; $total_sum = 0; $subject_count = 0;
        $weak_subjects = [];

        while ($row = $marks_res->fetch_assoc()) {
            $m_base = ($row['monthly_base'] > 0) ? $row['monthly_base'] : 100;
            $e_base = ($row['exam_base'] > 0) ? $row['exam_base'] : 100;

            $row['t_display'] = ($row['monthly_mark'] / $m_base) * 40;
            $row['e_display'] = ($row['exam_mark'] / $e_base) * 60;
            $row['calculated_total'] = $row['t_display'] + $row['e_display'];

            if($row['calculated_total'] < 50) { $weak_subjects[] = $row['subject_name']; }

            $h_sql = "SELECT MAX(((monthly_mark / IF(monthly_base > 0, monthly_base, 100)) * 40) + ((exam_mark / IF(exam_base > 0, exam_base, 100)) * 60)) as max_m 
                      FROM primary_marks WHERE subject_id='{$row['subject_id']}' AND academic_year='$year' AND exam_type='$exam_type' AND class_name='$class_name'";
            
            $h_res = $conn->query($h_sql)->fetch_assoc();
            $row['highest'] = $h_res['max_m'];

            $marks_data[] = $row;
            if($row['calculated_total'] > 0) { $total_sum += $row['calculated_total']; $subject_count++; }
        }
        $average = ($subject_count > 0) ? ($total_sum / $subject_count) : 0;

        $pos_sql = "SELECT student_id, SUM(total_mark) as total_marks FROM primary_marks 
                    WHERE academic_year = '$year' AND exam_type = '$exam_type' AND class_name = '$class_name' 
                    GROUP BY student_id ORDER BY total_marks DESC";
        $pos_res = $conn->query($pos_sql);
        $total_students = $pos_res->num_rows;
        $overall_pos = 0; $rank = 1;
        while($p = $pos_res->fetch_assoc()){
            if($p['student_id'] == $student_id){ $overall_pos = $rank; break; }
            $rank++;
        }

        $g_info = getGradeInfo($average);
        
        if($average >= 75) {
            $teacher_comment = "Excellent performance, " . strtoupper($fname) . ". You have shown great potential and outstanding effort. Keep up this standard to achieve your academic goals.";
            $head_comment = "These results are a reflection of your high level of discipline and obedience. The school is proud of your progress. Maintain this spirit.";
        } elseif($average >= 50) {
            $teacher_comment = "Good effort, but there is still plenty of room for improvement. Focus more on the subjects where you scored average marks to reach the top.";
            $head_comment = "The student has the potential to perform much better with increased concentration in class. We recommend close supervision at home.";
        } else {
            $teacher_comment = strtoupper($fname) . ", your results are unsatisfactory. You must double your efforts and focus more on your studies to succeed.";
            $head_comment = "Academic standing is poor. Close cooperation between parent and teacher is urgently required to help improve performance, especially in: " . implode(", ", $weak_subjects);
        }
    }
}

function getGradeInfo($s) {
    if ($s >= 80) return ['A', 'Excellent'];
    if ($s >= 70) return ['B', 'Very Good'];
    if ($s >= 60) return ['C', 'Good'];
    if ($s >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card - <?= $fname ?? '' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Arial', sans-serif; }
        .report-paper { width: 210mm; min-height: 296mm; margin: 5px auto; background: #fff; padding: 10mm; position: relative; border: 1px solid #000; }
        .school-logo { position: absolute; top: 10mm; left: 15mm; width: 100px; }
        .student-photo { position: absolute; top: 10mm; right: 15mm; width: 100px; height: 100px; border: 2px solid #000; object-fit: cover; }
        .header-text { text-align: center; margin-top: 5px; }
        .header-text h1 { font-size: 24pt; font-weight: 900; margin: 0; }
        .header-text p { font-size: 11pt; font-weight: bold; margin: 0; }
        .report-title { text-align: center; margin: 25px 0; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 0; }
        .table-report { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .table-report th, .table-report td { border: 1px solid #000; padding: 6px; text-align: center; font-size: 10pt; }
        .table-report th { background: #f2f2f2; }
        .bottom-section { display: flex; justify-content: space-between; margin-top: 20px; }
        .comments-side { width: 63%; }
        .grade-summary-side { width: 35%; border: 1px solid #000; padding: 10px; font-size: 9pt; }
        .comment-box { margin-bottom: 15px; }
        .comment-label { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 5px; }
        .comment-content { font-style: italic; font-size: 10pt; line-height: 1.4; display: block; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 45px; border-top: 0px; }
        .sig-box { width: 30%; border-top: 2px solid #000; text-align: center; padding-top: 5px; font-weight: bold; font-size: 10pt; }
        @media print { .no-print { display: none; } body { background: none; } .report-paper { border: none; margin: 0; width: 100%; padding: 10mm; } }
    </style>
</head>
<body>

<div class="no-print container-fluid py-3 bg-dark text-white mb-3 shadow">
    <form method="POST" class="row g-2 justify-content-center align-items-end">
        <div class="col-md-2">
            <label class="small fw-bold">Student Search</label>
            <input type="text" name="student_search" placeholder="Name or ID" class="form-control form-control-sm" required>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold">Academic Year</label>
            <select name="year" class="form-select form-select-sm">
                <?php for($y=2015; $y<=2035; $y++) { $period = "$y/".($y+1); echo "<option value='$period'>$period</option>"; } ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold">Class & Stream</label>
            <div class="input-group input-group-sm">
                <select name="class_name" class="form-select">
                    <option value="KG 1">KG 1</option>
                    <option value="KG 2">KG 2</option>
                    <option value="Standard 1">Standard 1</option>
                    <option value="Standard 2">Standard 2</option>
                    <option value="Standard 3">Standard 3</option>
                    <option value="Standard 4">Standard 4</option>
                    <option value="Standard 5">Standard 5</option>
                    <option value="Standard 6">Standard 6</option>
                    <option value="Standard 7">Standard 7</option>
                </select>
                <select name="stream" class="form-select">
                    <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>$char</option>"; ?>
                </select>
            </div>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold">Class Teacher</label>
            <select name="class_teacher_id" class="form-select form-select-sm" required>
                <option value="">-- Choose Teacher --</option>
                <?php while($t = $teachers_list->fetch_assoc()) echo "<option value='{$t['id']}'>{$t['fullname']}</option>"; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold">Exam Type</label>
            <select name="exam_type" class="form-select form-select-sm">
                <option value="term1">Term 1 (M1 + Exam)</option>
                <option value="term2">Term 2 (M2 + Exam)</option>
                <option value="special">Special (M1+M2+Exam)</option>
                <option value="terminal">Terminal (100%)</option>
                <option value="annual">Annual (100%)</option>
            </select>
        </div>

        <div class="col-auto">
            <button type="submit" name="view_report" class="btn btn-primary btn-sm px-4 fw-bold">GENERATE</button>
        </div>
    </form>
</div>

<?php if($report_ready): ?>
<div class="report-paper">
    <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
    <?php 
        $pic_url = (!empty($student['photo']) && file_exists("uploads/students/".$student['photo'])) 
                   ? "uploads/students/".$student['photo'] 
                   : "https://ui-avatars.com/api/?name=".urlencode($fname)."&size=128&background=random";
    ?>
    <img src="<?= $pic_url ?>" class="student-photo">

    <div class="header-text">
        <h1><?= strtoupper($school['school_name']) ?></h1>
        <p>P.O.BOX <?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
        <p><?= strtoupper($school['address']) ?></p>
    </div>

    <div class="report-title">
        <h5 class="m-0 fw-bold"><?= $report_title ?></h5>
        <p class="m-0 small">PRIMARY LEVEL</p>
    </div>

    <div class="row px-2 fw-bold small mb-3">
        <div class="col-8">STUDENT'S NAME: <?= strtoupper($fname) ?> (<?= $student['student_id'] ?>)</div>
        <div class="col-4">ATTENDANCE: _________</div>
        <div class="col-8 mt-1">ACADEMIC YEAR: <?= $year ?> | CLASS: <?= $student['class_name'] ?> <?= $student['stream'] ?></div>
        <div class="col-4 mt-1">CLASS POSITION: <?= $overall_pos ?> OUT OF <?= $total_students ?></div>
    </div>

    <table class="table-report">
        <thead>
            <tr>
                <th class="text-start">SUBJECTS</th>
                <th>TEST 40%</th>
                <th>EXAM 60%</th>
                <th>TOTAL 100%</th>
                <th>GRADE</th>
                <th>POSITION</th>
                <th>REMARKS</th>
                <th>HIGHEST</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($marks_data as $m): $gi = getGradeInfo($m['calculated_total']); ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td><?= number_format($m['t_display'], 0) ?></td>
                <td><?= number_format($m['e_display'], 0) ?></td>
                <td class="fw-bold"><?= number_format($m['calculated_total'], 0) ?></td>
                <td><?= $gi[0] ?></td>
                <td><?= $m['subject_rank'] ?></td>
                <td><?= $gi[1] ?></td>
                <td><?= number_format($m['highest'], 1) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold">
                <td class="text-start">TOTAL MARKS</td>
                <td colspan="2"></td>
                <td><?= number_format($total_sum, 0) ?></td>
                <td>OUT OF:</td>
                <td><?= $subject_count * 100 ?></td>
                <td colspan="2"></td>
            </tr>
            <tr class="fw-bold">
                <td class="text-start">AVERAGE</td>
                <td colspan="2"></td>
                <td><?= number_format($average, 1) ?>%</td>
                <td><?= $g_info[0] ?></td>
                <td><?= $overall_pos ?></td>
                <td><?= $g_info[1] ?></td>
                <td>---</td>
            </tr>
        </tbody>
    </table>

    <p class="fw-bold mt-2 mb-3 small">CLASS TEACHER: <?= strtoupper($teacher_name) ?> (<?= $teacher_phone ?>)</p>

    <div class="bottom-section">
        <div class="comments-side">
            <div class="comment-box">
                <span class="comment-label">CLASS TEACHER'S COMMENTS</span>
                <span class="comment-content"><?= $teacher_comment ?></span>
            </div>
            <div class="comment-box">
                <span class="comment-label">HEAD TEACHER'S COMMENTS</span>
                <span class="comment-content"><?= $head_comment ?></span>
            </div>
        </div>

        <div class="grade-summary-side">
            <table width="100%">
                <tr class="fw-bold"><td>GRADE</td><td>PERCENTAGE</td></tr>
                <tr><td>A</td><td>: 80 --- 100 ---> Excellent</td></tr>
                <tr><td>B</td><td>: 70 --- 79 ---> Very Good</td></tr>
                <tr><td>C</td><td>: 60 --- 69 ---> Good</td></tr>
                <tr><td>D</td><td>: 50 --- 59 ---> Pass</td></tr>
                <tr><td>F</td><td>: 0 --- 49 ---> Fail</td></tr>
            </table>
            <p class="mt-2 fw-bold border-top pt-1">PASSING MARKS: 50</p>
        </div>
    </div>

    <div class="signature-area">
        <div class="sig-box text-uppercase">Class Teacher's Signature</div>
        <div class="sig-box text-uppercase">Head's Signature</div>
        <div class="sig-box text-uppercase">Parent's Signature</div>
    </div>
</div>

<div class="text-center no-print my-4">
    <button onclick="window.print()" class="btn btn-success px-5 shadow fw-bold">PRINT PROGRESS REPORT</button>
</div>
<?php endif; ?>

</body>
</html>