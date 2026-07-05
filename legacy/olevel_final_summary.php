<?php
session_start();
include('db_config.php');

$report_ready = false;
$error = "";

// 1. Get School Information
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = ($school_res) ? $school_res->fetch_assoc() : null;

// 2. Get Teachers for Dropdown
$teachers_list = $conn->query("SELECT id, fullname, phone FROM teachers WHERE status = 'active' ORDER BY fullname ASC");

if (isset($_POST['generate_final_report'])) {
    $year = $conn->real_escape_string($_POST['year']);
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $stream = $conn->real_escape_string($_POST['stream']);
    $exam_type = $conn->real_escape_string($_POST['exam_type']);
    $selected_teacher_id = $_POST['class_teacher_id'] ?? '';

    // Get selected teacher details
    $teacher_name = "__________________________";
    $teacher_phone = "__________";
    if(!empty($selected_teacher_id)){
        $t_info = $conn->query("SELECT fullname, phone FROM teachers WHERE id = '$selected_teacher_id'")->fetch_assoc();
        $teacher_name = $t_info['fullname'] ?? "__________________________";
        $teacher_phone = $t_info['phone'] ?? "__________";
    }

    $st_sql = "SELECT * FROM students WHERE class_name = '$class_name' AND stream = '$stream' ORDER BY fullname ASC";
    $students_res = $conn->query($st_sql);

    if ($students_res && $students_res->num_rows > 0) {
        $report_ready = true;
    } else {
        $error = "No students found for this selection.";
    }
}

function getOlevelGrade($score) {
    if ($score >= 80) return ['A', 'Excellent', 1, '#27ae60'];
    if ($score >= 70) return ['B', 'Very Good', 2, '#2980b9'];
    if ($score >= 60) return ['C', 'Good', 3, '#f1c40f'];
    if ($score >= 50) return ['D', 'Satisfactory', 4, '#e67e22'];
    return ['F', 'Fail', 5, '#c0392b'];
}

function getFinalComments($division, $student_name, $weak_subjects = []) {
    $needs_effort = !empty($weak_subjects) ? " Specifically, you need to put more effort into " . implode(", ", $weak_subjects) . " to secure a better grade next time." : "";
    
    switch($division) {
        case 'I':
            return [
                't' => "Outstanding performance, $student_name! You have shown brilliance. Maintain this momentum.",
                'h' => "A highly disciplined student. Their academic trajectory is promising."
            ];
        case 'II':
            return [
                't' => "Very good work, $student_name. Solid foundation, but room for improvement to reach Div I.$needs_effort",
                'h' => "Hardworking with steady progress. Focus on weak areas to achieve top-tier results."
            ];
        case 'III':
            return [
                't' => "Good effort, $student_name, but performance is at average level. Double your study hours.$needs_effort",
                'h' => "Performance is fair but requires more serious academic engagement and revision."
            ];
        case 'IV':
            return [
                't' => "You have passed, $student_name, but results are weak. Reassess your study habits.$needs_effort",
                'h' => "Academic performance is below expectations. Requires strict supervision and a study plan."
            ];
        default:
            return [
                't' => "This is a disappointing result, $student_name. Use this as a wake-up call to work harder.$needs_effort",
                'h' => "Performance is unsatisfactory. Immediate intervention from parents is necessary."
            ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Report | O-Level</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Times New Roman', serif; }
        .no-print { padding: 25px; background: #fff; margin: 20px auto; max-width: 1000px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .report-paper { width: 210mm; min-height: 297mm; margin: 15px auto; padding: 15mm; background: #fff; border: 2px solid #000; page-break-after: always; position: relative; }
        .header-section { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 15px; }
        .school-logo { width: 80px; height: 80px; object-fit: contain; }
        .student-photo { width: 95px; height: 105px; border: 1px solid #000; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; padding: 5px; text-align: center; font-size: 14px; }
        .comment-box { border-bottom: 1px dotted #000; min-height: 38px; margin-bottom: 8px; font-size: 14px; font-style: italic; }
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .footer-table td { border: 1px solid #000; padding: 4px; text-align: center; }
        @media print { .no-print { display: none; } body { background: none; } .report-paper { border: none; margin: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="no-print">
    <h4 class="fw-bold mb-3 text-center">ACADEMIC REPORT FILTER</h4>
    <form method="POST" class="row g-3">
        <div class="col-md-2">
            <label class="small fw-bold">CLASS</label>
            <select name="class_name" class="form-select"><?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c'>$c</option>"; ?></select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold">STREAM</label>
            <select name="stream" class="form-select"><?php foreach(range('A','M') as $s) echo "<option value='$s'>$s</option>"; ?></select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold">YEAR</label>
            <select name="year" class="form-select"><?php for($y=2015;$y<=2036;$y++) { $p="$y/".($y+1); echo "<option value='$p'>$p</option>"; } ?></select>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold">CLASS TEACHER</label>
            <select name="class_teacher_id" class="form-select" required>
                <option value="">-- Select Teacher --</option>
                <?php while($t = $teachers_list->fetch_assoc()) echo "<option value='".$t['id']."'>".$t['fullname']."</option>"; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold">EXAM TYPE</label>
            <select name="exam_type" class="form-select">
                <option>Annual</option><option>Term 1</option><option>Term 2</option><option>Terminal</option><option>Mock Exam</option>
            </select>
        </div>
        <div class="col-12 mt-3 text-center">
            <button type="submit" name="generate_final_report" class="btn btn-primary px-5 fw-bold shadow">GENERATE REPORTS</button>
        </div>
    </form>
</div>

<?php if ($report_ready): ?>
    <div class="text-center no-print mb-4"><button onclick="window.print()" class="btn btn-success btn-lg shadow">PRINT ALL REPORTS</button></div>
    
    <?php while($student = $students_res->fetch_assoc()): 
        $st_id = $student['id'];
        $marks_data = []; $pts_arr = []; $weak_sub = []; $total_m = 0; $cnt = 0;
        $g_count = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'F'=>0];

        $sub_res = $conn->query("SELECT id, subject_name FROM olevel_subjects");
        while($s = $sub_res->fetch_assoc()) {
            $sid = $s['id'];
            if($exam_type == 'Annual') {
                $t1 = $conn->query("SELECT total_score FROM olevel_marks WHERE student_id='$st_id' AND subject_id='$sid' AND exam_type='Term 1' AND academic_year='$year'")->fetch_assoc();
                $t2 = $conn->query("SELECT total_score FROM olevel_marks WHERE student_id='$st_id' AND subject_id='$sid' AND exam_type='Term 2' AND academic_year='$year'")->fetch_assoc();
                $m1 = $t1['total_score'] ?? 0;
                $m2 = $t2['total_score'] ?? 0;
                $final = ($m1 * 0.4) + ($m2 * 0.6);
            } else {
                $mq = $conn->query("SELECT total_score FROM olevel_marks WHERE student_id='$st_id' AND subject_id='$sid' AND exam_type='$exam_type' AND academic_year='$year'")->fetch_assoc();
                $final = $mq['total_score'] ?? 0;
            }

            if($final > 0) {
                $gi = getOlevelGrade($final);
                $g_count[$gi[0]]++;
                $pts_arr[] = $gi[2];
                if($gi[0] == 'D' || $gi[0] == 'F') $weak_sub[] = $s['subject_name'];
                
                $h_q = $conn->query("SELECT MAX(total_score) as h FROM olevel_marks WHERE subject_id='$sid' AND academic_year='$year' AND exam_type='$exam_type'")->fetch_assoc();
                $marks_data[] = ['name'=>$s['subject_name'], 'score'=>$final, 'grade'=>$gi[0], 'remark'=>$gi[1], 'color'=>$gi[3], 'high'=>$h_q['h'] ?? 0];
                $total_m += $final; $cnt++;
            }
        }
        sort($pts_arr);
        $pts = (count($pts_arr) >= 7) ? array_sum(array_slice($pts_arr, 0, 7)) : 0;
        $div = ($pts > 0 && $pts <= 17) ? "I" : (($pts <= 21) ? "II" : (($pts <= 25) ? "III" : (($pts <= 33) ? "IV" : "0")));
        $avg = ($cnt > 0) ? $total_m / $cnt : 0;
        $comm = getFinalComments($div, $student['fullname'], $weak_sub);
    ?>

    <div class="report-paper">
        <div class="header-section text-center">
            <div class="row align-items-center">
                <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo"></div>
                <div class="col-8">
                    <h3 class="fw-bold mb-0"><?= strtoupper($school['school_name']) ?></h3>
                    <p class="mb-0 small"><?= $school['address'] ?> | <?= $school['phone'] ?></p>
                    <h5 class="mt-2 fw-bold text-decoration-underline"><?= strtoupper($exam_type) ?> REPORT - <?= $year ?></h5>
                </div>
                <div class="col-2"><img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://via.placeholder.com/95x105'"></div>
            </div>
        </div>

        <div class="row mb-3 fw-bold small">
            <div class="col-7">NAME: <?= strtoupper($student['fullname']) ?><br>STUDENT ID: <?= $student['student_id'] ?></div>
            <div class="col-5 text-end">CLASS: <?= $class_name ?> | STREAM: <?= $stream ?><br>DIVISION: <?= $div ?> | POINTS: <?= $pts ?></div>
        </div>

        <table class="table-marks w-100">
            <thead style="background:#f2f2f2;">
                <tr><th class="text-start">SUBJECTS</th><th>SCORE</th><th>GRADE</th><th>REMARK</th><th>HIGH</th></tr>
            </thead>
            <tbody>
                <?php foreach($marks_data as $m): ?>
                <tr>
                    <td class="text-start fw-bold"><?= strtoupper($m['name']) ?></td>
                    <td class="fw-bold"><?= number_format($m['score'], 1) ?></td>
                    <td style="color:<?= $m['color'] ?>; font-weight:bold;"><?= $m['grade'] ?></td>
                    <td><?= $m['remark'] ?></td>
                    <td><?= number_format($m['high'], 1) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-3">
            <div class="comment-box"><strong>Class Teacher:</strong> <?= $comm['t'] ?></div>
            <div class="comment-box"><strong>Head of School:</strong> <?= $comm['h'] ?></div>
        </div>

        <div class="row align-items-end">
            <div class="col-7">
                <table class="footer-table">
                    <tr class="fw-bold bg-light"><td>GRADE RANGE</td><td>A: 80-100</td><td>B: 70-79</td><td>C: 60-69</td><td>D: 50-59</td><td>F: 0-49</td></tr>
                    <tr><td class="fw-bold">SUMMARY</td><td>A: <?= $g_count['A'] ?></td><td>B: <?= $g_count['B'] ?></td><td>C: <?= $g_count['C'] ?></td><td>D: <?= $g_count['D'] ?></td><td>F: <?= $g_count['F'] ?></td></tr>
                </table>
            </div>
            <div class="col-5 text-center">
                <div class="border border-dark p-2 fw-bold" style="font-size:14px;">AVERAGE: <?= number_format($avg, 1) ?>% | GRADE: <?= getOlevelGrade($avg)[0] ?></div>
            </div>
        </div>

        <div class="row mt-5 text-center fw-bold small">
            <div class="col-4">
                <div style="border-top: 1px solid #000; width: 180px; margin: 0 auto;"><?= $teacher_name ?></div>
                <p>Class Teacher (<?= $teacher_phone ?>)</p>
            </div>
            <div class="col-4">
                <div style="border-top: 1px solid #000; width: 180px; margin: 0 auto; min-height: 20px;"></div>
                <p>Parent's Signature</p>
            </div>
            <div class="col-4">
                <div style="border-top: 1px solid #000; width: 180px; margin: 0 auto; min-height: 20px;"></div>
                <p>School Stamp</p>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php endif; ?>

</body>
</html>