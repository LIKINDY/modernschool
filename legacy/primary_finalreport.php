<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$report_ready = false;
$students_data = [];
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// Fetch teachers for the dropdown
$teachers_list = $conn->query("SELECT id, fullname FROM teachers WHERE status = 'active' ORDER BY fullname ASC");

if (isset($_POST['generate_final_report'])) {
    $year = $conn->real_escape_string($_POST['year']);
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $stream = $conn->real_escape_string($_POST['stream']);
    $selected_teacher_id = $_POST['class_teacher_id'] ?? '';
    $stream_filter = (!empty($stream)) ? "AND s.stream = '$stream'" : "";

    // Get Teacher Name
    $teacher_name = "__________________________";
    if(!empty($selected_teacher_id)){
        $t_info = $conn->query("SELECT fullname FROM teachers WHERE id = '$selected_teacher_id'")->fetch_assoc();
        $teacher_name = $t_info['fullname'];
    }

    // 1. COLLECT ALL MARKS FOR RANKING & HIGHEST MARK CALCULATION
    $all_subject_scores = []; 
    $rank_sql = "SELECT m.*, s.id as sid FROM primary_marks m 
                 JOIN students s ON m.student_id = s.id 
                 WHERE s.class_name = '$class_name' $stream_filter AND m.academic_year = '$year'";
    $rank_res = $conn->query($rank_sql);
    
    $raw_scores = [];
    while($r = $rank_res->fetch_assoc()){
        $raw_scores[$r['sid']][$r['subject_id']][$r['exam_type']] = $r;
    }

    foreach($raw_scores as $sid => $subjects){
        foreach($subjects as $subid => $exams){
            // Term 1 Calculation (40% weight if Term 2 exists)
            $t1_m = isset($exams['term1']) ? ($exams['term1']['monthly_mark'] / ($exams['term1']['monthly_base'] ?: 100)) * 100 : 0;
            $t1_e = isset($exams['term1']) ? ($exams['term1']['exam_mark'] / ($exams['term1']['exam_base'] ?: 100)) * 100 : 0;
            $t1_total = ($t1_m * 0.4) + ($t1_e * 0.6);

            // Term 2 Calculation (60% weight)
            $t2_m = isset($exams['term2']) ? ($exams['term2']['monthly_mark'] / ($exams['term2']['monthly_base'] ?: 100)) * 100 : 0;
            $t2_e = isset($exams['term2']) ? ($exams['term2']['exam_mark'] / ($exams['term2']['exam_base'] ?: 100)) * 100 : 0;
            $t2_total = ($t2_m * 0.4) + ($t2_e * 0.6);

            $annual = ($t2_total <= 0) ? $t1_total : ($t1_total * 0.4) + ($t2_total * 0.6);
            $all_subject_scores[$subid][] = $annual;
        }
    }

    // 2. PREPARE INDIVIDUAL STUDENT REPORTS
    $st_sql = "SELECT DISTINCT s.* FROM students s 
               JOIN primary_marks m ON s.id = m.student_id 
               WHERE s.class_name = '$class_name' $stream_filter AND m.academic_year = '$year' 
               ORDER BY s.fullname ASC";
    $st_res = $conn->query($st_sql);
    
    if ($st_res->num_rows > 0) {
        $report_ready = true;
        $overall_averages = [];

        while ($student = $st_res->fetch_assoc()) {
            $student_id = $student['id'];
            $student_marks = [];
            $total_annual_sum = 0;
            $weak_subjects = [];

            if(isset($raw_scores[$student_id])){
                foreach($raw_scores[$student_id] as $subid => $exams){
                    $sub_info = $conn->query("SELECT subject_name FROM primary_subjects WHERE id = $subid")->fetch_assoc();
                    
                    $t1_m = isset($exams['term1']) ? ($exams['term1']['monthly_mark'] / ($exams['term1']['monthly_base'] ?: 100)) * 100 : 0;
                    $t1_e = isset($exams['term1']) ? ($exams['term1']['exam_mark'] / ($exams['term1']['exam_base'] ?: 100)) * 100 : 0;
                    $t1_total = ($t1_m * 0.4) + ($t1_e * 0.6);

                    $t2_m = isset($exams['term2']) ? ($exams['term2']['monthly_mark'] / ($exams['term2']['monthly_base'] ?: 100)) * 100 : 0;
                    $t2_e = isset($exams['term2']) ? ($exams['term2']['exam_mark'] / ($exams['term2']['exam_base'] ?: 100)) * 100 : 0;
                    $t2_total = ($t2_m * 0.4) + ($t2_e * 0.6);

                    $annual = ($t2_total <= 0) ? $t1_total : ($t1_total * 0.4) + ($t2_total * 0.6);

                    if($annual < 50) $weak_subjects[] = $sub_info['subject_name'];

                    // Ranking within subject
                    $curr_scores = $all_subject_scores[$subid];
                    rsort($curr_scores);
                    $pos = array_search($annual, $curr_scores) + 1;
                    $high = max($curr_scores);

                    $student_marks[] = [
                        'subject_name' => $sub_info['subject_name'],
                        't1_display' => $t1_total,
                        't2_display' => $t2_total,
                        'annual_total' => $annual,
                        'subject_pos' => $pos,
                        'highest_mark' => $high
                    ];
                    $total_annual_sum += $annual;
                }
            }

            $avg = (count($student_marks) > 0) ? ($total_annual_sum / count($student_marks)) : 0;
            $students_data[$student_id] = [
                'info' => $student, 
                'marks' => $student_marks, 
                'average' => $avg,
                'class_teacher' => $teacher_name,
                'weak_subjects' => $weak_subjects
            ];
            $overall_averages[$student_id] = $avg;
        }

        arsort($overall_averages);
        $rank = 1;
        foreach($overall_averages as $sid => $avg) {
            $students_data[$sid]['position'] = $rank++;
            $students_data[$sid]['total_students'] = count($overall_averages);
        }
    }
}

function getGradeInfo($s) {
    if ($s >= 81) return ['A', 'EXCELLENT'];
    if ($s >= 70) return ['B', 'VERY GOOD'];
    if ($s >= 60) return ['C', 'GOOD'];
    if ($s >= 50) return ['D', 'AVERAGE'];
    return ['F', 'FAIL'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Annual Progress Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Arial', sans-serif; }
        .report-paper { 
            width: 210mm; min-height: 296mm; margin: 10px auto; 
            background: #fff; padding: 10mm; position: relative; border: 1px solid #000;
            page-break-after: always; 
        }
        .school-logo { position: absolute; top: 10mm; left: 15mm; width: 100px; }
        .student-photo { position: absolute; top: 10mm; right: 15mm; width: 100px; height: 100px; border: 2px solid #000; object-fit: cover; }
        .header-text { text-align: center; margin-top: 5px; }
        .header-text h1 { font-size: 24pt; font-weight: 900; margin: 0; }
        .header-text p { font-size: 11pt; font-weight: bold; margin: 0; }
        .report-title { text-align: center; margin: 25px 0; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 0; }
        
        .table-report { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .table-report th, .table-report td { border: 1px solid #000; padding: 6px; text-align: center; font-size: 10pt; }
        .table-report th { background: #f2f2f2; }

        /* Layout for Bottom Section (Comments + Grade Summary) */
        .bottom-section { display: flex; justify-content: space-between; margin-top: 20px; }
        .comments-side { width: 63%; }
        .grade-summary-side { width: 35%; border: 1px solid #000; padding: 10px; font-size: 9pt; height: fit-content; }
        
        .comment-box { margin-bottom: 15px; }
        .comment-label { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 5px; font-size: 10pt; }
        .comment-content { font-style: italic; font-size: 10pt; line-height: 1.4; display: block; border-bottom: 1px dotted #000; min-height: 20px; }
        
        .signature-area { display: flex; justify-content: space-between; margin-top: 45px; }
        .sig-box { width: 30%; border-top: 2px solid #000; text-align: center; padding-top: 5px; font-weight: bold; font-size: 10pt; }

        @media print { 
            .no-print { display: none; } 
            body { background: none; }
            .report-paper { border: none; margin: 0; width: 100%; padding: 10mm; } 
        }
    </style>
</head>
<body>

<!-- Filter Section -->
<div class="no-print container py-4 bg-white shadow-sm mb-4">
    <h4 class="text-center mb-3 text-uppercase fw-bold">Annual Bulk Report Generator</h4>
    <form method="POST" class="row g-2 justify-content-center align-items-end">
        <div class="col-md-2">
            <label class="small fw-bold">Academic Year</label>
            <select name="year" class="form-select">
                <?php for($y=2025; $y<=2036; $y++) { 
                    $yr = "$y/".($y+1); 
                    echo "<option value='$yr'>$yr</option>"; 
                } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold">Class</label>
            <select name="class_name" class="form-select">
                <option>KG 1</option><option>KG 2</option>
                <?php for($i=1; $i<=7; $i++) echo "<option value='Standard $i'>Standard $i</option>"; ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="small fw-bold">Stream</label>
            <select name="stream" class="form-select">
                <option value="">ALL</option>
                <?php foreach(range('A','M') as $l) echo "<option>$l</option>"; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold">Class Teacher</label>
            <select name="class_teacher_id" class="form-select" required>
                <option value="">-- Select Teacher --</option>
                <?php $teachers_list->data_seek(0); while($t = $teachers_list->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>"><?= $t['fullname'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" name="generate_final_report" class="btn btn-primary px-4 fw-bold">GENERATE</button>
        </div>
    </form>
</div>

<?php if($report_ready): ?>
    <div class="text-center no-print mb-4">
        <button onclick="window.print()" class="btn btn-success btn-lg">PRINT ALL REPORTS (<?= count($students_data) ?>)</button>
    </div>

    <?php foreach($students_data as $data): 
        $st = $data['info'];
        $gi_main = getGradeInfo($data['average']);
        $fname = explode(' ', trim($st['fullname']))[0];
        $avg_grade = $gi_main[0];

        // Comment Logic
        if($avg_grade == 'A' || $avg_grade == 'B'){
            $teacher_comment = "Congratulations $fname for your excellent annual results. You have shown great effort throughout the year; keep this standard.";
            $head_comment = "Very satisfactory performance. The school is proud of your academic excellence. Maintain this focus next year.";
        } elseif($avg_grade == 'C'){
            $teacher_comment = "Good performance $fname, but you have the potential to score higher. Focus more on your weak areas next year.";
            $head_comment = "A fair result. We encourage parents to provide more supervision to help the student reach an 'A' grade.";
        } else { 
            $teacher_comment = "Unsatisfactory results $fname. You must double your efforts and focus more on your studies to succeed.";
            $head_comment = "Poor performance. Urgent cooperation between parents and teachers is required, especially in: " . implode(", ", $data['weak_subjects']);
        }
    ?>
    <div class="report-paper">
        <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($st['fullname']) ?>&size=128" class="student-photo">

        <div class="header-text">
            <h1><?= strtoupper($school['school_name']) ?></h1>
            <p>P.O.BOX <?= $school['pobox'] ?>, PHONE <?= $school['phone'] ?></p>
            <p><?= strtoupper($school['address']) ?></p>
        </div>

        <div class="report-title">
            <h5 class="m-0 fw-bold">ANNUAL ACADEMIC PROGRESS REPORT</h5>
            <p class="m-0 small">PRIMARY LEVEL SUMMARY</p>
        </div>

        <div class="row px-2 fw-bold small mb-3">
            <div class="col-7">STUDENT'S NAME: <?= strtoupper($st['fullname']) ?></div>
            <div class="col-5 text-end">CLASS: <?= $st['class_name'] ?> <?= $st['stream'] ?></div>
            <div class="col-7 mt-1">ACADEMIC YEAR: <?= $year ?></div>
            <div class="col-5 text-end mt-1">CLASS POSITION: <?= $data['position'] ?> / <?= $data['total_students'] ?></div>
        </div>

        <table class="table-report">
            <thead>
                <tr>
                    <th class="text-start">SUBJECTS</th>
                    <th>TERM 1 (%)</th>
                    <th>TERM 2 (%)</th>
                    <th>ANNUAL (%)</th>
                    <th>GRADE</th>
                    <th>POS</th>
                    <th>HIGH</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['marks'] as $m): $gi = getGradeInfo($m['annual_total']); ?>
                <tr>
                    <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                    <td><?= number_format($m['t1_display'], 0) ?></td>
                    <td><?= number_format($m['t2_display'], 0) ?></td>
                    <td class="fw-bold bg-light"><?= number_format($m['annual_total'], 0) ?></td>
                    <td><?= $gi[0] ?></td>
                    <td><?= $m['subject_pos'] ?></td>
                    <td><?= number_format($m['highest_mark'], 0) ?></td>
                    <td class="small"><?= $gi[1] ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">OVERALL ANNUAL AVERAGE:</td>
                    <td class="bg-dark text-white"><?= number_format($data['average'], 1) ?>%</td>
                    <td><?= $gi_main[0] ?></td>
                    <td colspan="2">RANK: <?= $data['position'] ?></td>
                    <td><?= $gi_main[1] ?></td>
                </tr>
            </tbody>
        </table>

        <div class="bottom-section">
            <div class="comments-side">
                <div class="comment-box">
                    <span class="comment-label">CLASS TEACHER'S COMMENTS (<?= $data['class_teacher'] ?>)</span>
                    <span class="comment-content"><?= $teacher_comment ?></span>
                </div>
                <div class="comment-box">
                    <span class="comment-label">HEAD TEACHER'S COMMENTS</span>
                    <span class="comment-content"><?= $head_comment ?></span>
                </div>
            </div>

            <div class="grade-summary-side">
                <table width="100%">
                    <tr class="fw-bold text-center"><td colspan="2" style="border-bottom:1px solid #000;">GRADE SUMMARY</td></tr>
                    <tr><td>A</td><td>: 81 --- 100 (Excellent)</td></tr>
                    <tr><td>B</td><td>: 70 --- 80 (Very Good)</td></tr>
                    <tr><td>C</td><td>: 60 --- 69 (Good)</td></tr>
                    <tr><td>D</td><td>: 50 --- 59 (Average)</td></tr>
                    <tr><td>F</td><td>: 0 --- 49 (Fail)</td></tr>
                </table>
                <p class="mt-2 fw-bold border-top pt-1 text-center">PASSING MARKS: 50</p>
            </div>
        </div>

        <div class="signature-area">
            <div class="sig-box text-uppercase">Teacher's Sig</div>
            <div class="sig-box text-uppercase">Parent's Sig</div>
            <div class="sig-box text-uppercase" style="position:relative;">
                Head Teacher
                <?php if(!empty($school['stamp'])): ?>
                    <img src="uploads/stamps/<?= $school['stamp'] ?>" style="height: 60px; position: absolute; top: -35px; left: 50%; transform: translateX(-50%); opacity: 0.4;">
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>