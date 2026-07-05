<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 1. Fetch School Info
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_res ? $school_res->fetch_assoc() : null;

// 2. Grading Logic with Colors & Expanded Automatic Comments
function getAssessment($mark, $subject_name = null) {
    if ($mark >= 90) return [
        'grade' => 'A+', 
        'remark' => 'Excellent', 
        'color' => '#008000', 
        'text' => '#ffffff', 
        'comment' => "Outstanding performance! The student has shown exceptional mastery of concepts. Keep up this brilliant work."
    ];
    if ($mark >= 80) return [
        'grade' => 'A',  
        'remark' => 'Very Good', 
        'color' => '#0000FF', 
        'text' => '#ffffff', 
        'comment' => "Very good progress. The student is performing consistently well. Aim for the top position next term."
    ];
    if ($mark >= 60) return [
        'grade' => 'B',  
        'remark' => 'Good',      
        'color' => '#FFFF00', 
        'text' => '#000000', 
        'comment' => "A good effort overall. With more focus and consistent practice, a higher grade is well within reach."
    ];
    if ($mark >= 50) return [
        'grade' => 'C',  
        'remark' => 'Fairy Good',
        'color' => '#FFC0CB', 
        'text' => '#000000', 
        'comment' => "Fair performance. More practice and concentration are needed in $subject_name to improve the results."
    ];
    return [
        'grade' => 'F', 
        'remark' => 'Fail', 
        'color' => '#000000', 
        'text' => '#ffffff', 
        'comment' => "Poor performance. The student needs urgent academic support. Parents must closely supervise $subject_name at home."
    ];
}

$view = $_GET['view'] ?? 'filter';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nursery Progress Report</title>
    <style>
        body { font-family: 'Times New Roman', serif; background: #f0f0f0; margin: 0; padding: 10px; }
        .no-print { background: white; padding: 20px; max-width: 500px; margin: 20px auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .report-paper { background: white; width: 210mm; min-height: 297mm; padding: 15mm; margin: 10px auto; border: 1px solid #ccc; box-sizing: border-box; page-break-after: always; position: relative; }
        .header-table { width: 100%; border: none; margin-bottom: 10px; }
        .school-logo { width: 100px; height: 100px; object-fit: contain; }
        .student-photo { width: 100px; height: 110px; border: 1px solid #000; object-fit: cover; }
        .school-info { text-align: center; }
        .school-info h1 { margin: 0; font-size: 20pt; color: #1a237e; }
        .student-meta { width: 100%; margin-bottom: 15px; font-size: 11pt; line-height: 1.6; }
        .line { border-bottom: 1px solid #000; display: inline-block; min-width: 120px; padding-left: 5px; margin-right: 10px; }
        .marks-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .marks-table th, .marks-table td { border: 1px solid #000; padding: 5px; text-align: center; font-size: 10pt; }
        .marks-table th { background: #f2f2f2; }
        .grade-box { display: inline-block; width: 35px; height: 20px; border: 1px solid #000; vertical-align: middle; margin-left: 10px; }
        .assessment-key { width: 100%; margin-top: 15px; border-collapse: collapse; font-size: 8.5pt; }
        .assessment-key td { border: 1px solid #000; padding: 4px; }
        .comment-section { margin-top: 10px; border-bottom: 1px solid #000; min-height: 45px; font-style: italic; font-size: 10pt; }
        
        @media print {
            .no-print { display: none; }
            body { background: none; padding: 0; }
            .report-paper { border: none; margin: 0; width: 100%; padding: 10mm; }
        }
    </style>
</head>
<body>

<?php if ($view == 'filter'): ?>
    <div class="no-print">
        <h2 style="text-align:center;">Nursery Report Filter</h2>
        <form method="GET">
            <input type="hidden" name="view" value="report">
            <label>Class:</label><br>
            <select name="class" required style="width:100%; padding:8px; margin-bottom:10px;">
                <option value="P.Group">P.Group</option><option value="KG1">KG1</option><option value="KG2">KG2</option>
            </select>
            
            <label>Stream:</label><br>
            <select name="stream" required style="width:100%; padding:8px; margin-bottom:10px;">
                <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>$char</option>"; ?>
            </select>

            <label>Exam Type:</label><br>
            <select name="exam_type" required style="width:100%; padding:8px; margin-bottom:10px;">
                <option>Term 1</option><option>Term 2</option><option>Annual</option><option>Special</option>
            </select>

            <label>Academic Year:</label><br>
            <select name="year" required style="width:100%; padding:8px; margin-bottom:10px;">
                <?php for($y=2015; $y<=2036; $y++) echo "<option value='$y/".($y+1)."'>$y/".($y+1)."</option>"; ?>
            </select>

            <label>Class Teacher:</label><br>
            <select name="teacher_id" required style="width:100%; padding:8px; margin-bottom:15px;">
                <option value="">-- Select Teacher --</option>
                <?php 
                $teachers = $conn->query("SELECT id, fullname FROM teachers ORDER BY fullname ASC");
                while($t = $teachers->fetch_assoc()) echo "<option value='{$t['id']}'>{$t['fullname']}</option>";
                ?>
            </select>

            <button type="submit" style="width:100%; padding:12px; background:#1a237e; color:white; border:none; cursor:pointer; font-weight:bold;">GENERATE REPORTS</button>
        </form>
    </div>

<?php elseif ($view == 'report' && isset($_GET['teacher_id'])): 
    $class = $_GET['class']; $stream = $_GET['stream']; $exam_type = $_GET['exam_type']; $year = $_GET['year']; $t_id = $_GET['teacher_id'];
    
    $teacher_query = $conn->query("SELECT fullname, phone FROM teachers WHERE id = '$t_id'");
    $teacher = ($teacher_query) ? $teacher_query->fetch_assoc() : ['fullname' => 'N/A', 'phone' => 'N/A'];

    $students = $conn->query("SELECT * FROM students WHERE class_name='$class' AND stream='$stream' AND status='active'");

    if($students->num_rows == 0) echo "<div class='no-print'>No students found for this selection.</div>";

    while($stu = $students->fetch_assoc()):
        $stu_id = $stu['id'];
        $marks_res = $conn->query("SELECT m.*, s.subject_name FROM nursery_marks m JOIN nursery_subjects s ON m.subject_id = s.id WHERE m.student_id='$stu_id' AND m.exam_type='$exam_type' AND m.academic_year='$year'");
?>
    <div class="report-paper">
        <table class="header-table">
            <tr>
                <td width="20%">
                    <!-- Updated Logo to pull from school_info table -->
                    <img src="uploads/<?= $school['logo'] ?>" class="school-logo" alt="Logo" onerror="this.src='uploads/logo.png'">
                </td>
                <td class="school-info">
                    <h1><?= strtoupper($school['school_name'] ?? 'HIGH-VIEW INTERNATIONAL SCHOOL') ?></h1>
                    <p>P.O.BOX <?= $school['pobox'] ?? '141' ?>, TEL: <?= $school['phone'] ?? '+255...' ?><br>
                    <strong>PROGRESS REPORT (NURSERY)</strong></p>
                    <h3 style="text-decoration: underline; margin-top:5px;"><?= strtoupper($exam_type) ?> PROGRESS REPORT</h3>
                </td>
                <td width="20%" align="right">
                    <img src="uploads/students/<?= !empty($stu['photo']) ? $stu['photo'] : 'default.png' ?>" class="student-photo" alt="Student">
                </td>
            </tr>
        </table>

        <div class="student-meta">
            <strong>STUDENT'S NAME:</strong> <span class="line"><?= strtoupper($stu['fullname']) ?></span>
            <strong>CLASS:</strong> <span class="line"><?= $class ?> <?= $stream ?></span>
            <strong>YEAR:</strong> <span class="line"><?= $year ?></span><br><br>
            <strong>ATTENDANCE:</strong> <span class="line">__________</span>
            <strong>BEHAVIOUR:</strong> <span class="line">__________</span>
            <strong>CLEANLINESS:</strong> <span class="line">__________</span>
        </div>

        <table class="marks-table">
            <thead>
                <tr>
                    <th width="30%">SUBJECTS</th>
                    <th>C/WORK 10%</th>
                    <th>TEST 30%</th>
                    <th>EXAM 60%</th>
                    <th>TOTAL 100%</th>
                    <th>GRADE</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grand_total = 0; $count = 0; $weak_subjects = [];
                // Re-run the marks query for each student loop
                $marks_res = $conn->query("SELECT m.*, s.subject_name FROM nursery_marks m JOIN nursery_subjects s ON m.subject_id = s.id WHERE m.student_id='$stu_id' AND m.exam_type='$exam_type' AND m.academic_year='$year'");
                
                while($m = $marks_res->fetch_assoc()): 
                    $total = $m['ca_mark'] + $m['monthly_mark'] + $m['exam_mark'];
                    $gi = getAssessment($total, $m['subject_name']);
                    $grand_total += $total; $count++;
                    if($total < 50) $weak_subjects[] = $m['subject_name'];
                ?>
                <tr>
                    <td style="text-align:left; padding-left:10px;"><?= strtoupper($m['subject_name']) ?></td>
                    <td><?= $m['ca_mark'] ?></td>
                    <td><?= $m['monthly_mark'] ?></td>
                    <td><?= $m['exam_mark'] ?></td>
                    <td><?= $total ?></td>
                    <td><?= $gi['grade'] ?></td>
                    <td><?= $gi['remark'] ?></td>
                </tr>
                <?php endwhile; ?>
                <?php 
                    $avg = ($count > 0) ? $grand_total / $count : 0; 
                    $final = getAssessment($avg);
                    // Total possible marks based on number of subjects
                    $max_possible = $count * 100;
                ?>
                <tr style="font-weight: bold; background:#f9f9f9;">
                    <td colspan="4" style="text-align:left; padding-left:10px;">TOTAL MARKS / AVERAGE</td>
                    <td><?= $grand_total ?> / <?= $max_possible ?></td>
                    <td><?= $final['grade'] ?></td>
                    <td><?= number_format($avg, 1) ?>%</td>
                </tr>
            </tbody>
        </table>

        <div style="font-weight: bold; margin-bottom: 10px; font-size: 11pt;">
            GRADE OBTAINED IN GENERAL: <?= $final['grade'] ?> 
            <div class="grade-box" style="background: <?= $final['color'] ?>;"></div>
        </div>

        <div class="comment-section">
            <strong>TEACHER'S COMMENTS:</strong> 
            <?php 
                echo $final['comment']; 
                if(!empty($weak_subjects)) {
                    echo " Special attention is needed in: " . implode(", ", $weak_subjects) . ". Please encourage the student to focus more on these areas.";
                } else {
                    echo " The student maintains a very balanced understanding of all subjects. Encourage them to keep this consistency.";
                }
            ?>
        </div>

        <table class="assessment-key">
            <tr style="font-weight: bold; background:#eee; text-align:center;">
                <td width="40%">REPRESENTED ARTICLES</td>
                <td width="20%">GRADE</td>
                <td>GRADE IN GENERAL</td>
            </tr>
            <tr><td>90% - 100% : Excellent</td><td align="center">A+</td><td style="background:#008000; color:white;">GREEN COLOUR</td></tr>
            <tr><td>80% - 89% : Very Good</td><td align="center">A</td><td style="background:#0000FF; color:white;">BLUE COLOUR</td></tr>
            <tr><td>60% - 79% : Good</td><td align="center">B</td><td style="background:#FFFF00;">YELLOW COLOUR</td></tr>
            <tr><td>50% - 59% : Fairy Good</td><td align="center">C</td><td style="background:#FFC0CB;">PINK COLOUR</td></tr>
            <tr><td>0% - 49% : Fail</td><td align="center">F</td><td style="background:#000; color:white;">BLACK COLOUR</td></tr>
        </table>

        <div style="margin-top: 35px; display: flex; justify-content: space-between; font-size: 10pt;">
            <div style="width: 45%;">
                <strong>CLASS TEACHER'S SIGNATURE:</strong><br><br>
                __________________________<br>
                <span><?= $teacher['fullname'] ?> (<?= $teacher['phone'] ?>)</span>
            </div>
            <div style="width: 45%; text-align: right;">
                <strong>HEAD'S SIGNATURE:</strong><br><br>
                __________________________<br>
                <span>OFFICIAL STAMP</span>
            </div>
        </div>
        
        <div style="margin-top: 25px; font-size: 10pt;">
            <strong>PARENT'S SIGNATURE:</strong> ____________________ 
            <span style="margin-left: 30px;"><strong>TERM COMMENCES ON:</strong> __________</span>
        </div>
    </div>
<?php endwhile; ?>

<div class="no-print" style="text-align:center;">
    <button onclick="window.print()" style="padding:15px 30px; background:green; color:white; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">PRINT ALL REPORTS</button>
</div>

<?php endif; ?>

</body>
</html>