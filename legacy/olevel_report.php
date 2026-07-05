<?php
session_start();
include('db_config.php');

// 1. TAARIFA ZA SHULE
$school_query = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_query->fetch_assoc();

// 2. POKEA VIGEZO
$class_param = $_GET['class_name'] ?? ''; 
$year = $_GET['academic_year'] ?? '';
$term = $_GET['term'] ?? '';

// 3. TAFUTA MASOMO (Tunaangalia masomo yaliyosajiliwa kwenye mfumo)
$student_query = "SELECT * FROM students WHERE class_name = ? AND status != 'deleted' ORDER BY fullname ASC";
$st_stmt = $conn->prepare($student_query);
$st_stmt->bind_param("s", $class_param);
$st_stmt->execute();
$students = $st_stmt->get_result();

// 5. O-LEVEL GRADING LOGIC (Tanzania Standard)
function getOLevelGrade($marks) {
    if ($marks >= 75) return ['grade' => 'A', 'point' => 1, 'remark' => 'Excellent'];
    if ($marks >= 65) return ['grade' => 'B', 'point' => 2, 'remark' => 'Very Good'];
    if ($marks >= 45) return ['grade' => 'C', 'point' => 3, 'remark' => 'Good'];
    if ($marks >= 30) return ['grade' => 'D', 'point' => 4, 'remark' => 'Satisfactory'];
    return ['grade' => 'F', 'point' => 5, 'remark' => 'Fail'];
}

// 6. DIVISION LOGIC (Masomo lazima yawe 7 au zaidi)
function calculateDivision($total_points, $subjects_count) {
    if ($subjects_count < 7) return "INCOMPLETE"; // Hapa ndipo tuliporekebisha
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
    <title>Academic Report | <?= $school['school_name']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; color: #333; }
        .report-page { 
            background: white; width: 210mm; min-height: 297mm; 
            margin: 20px auto; padding: 15mm; border: 1px solid #d1d5db;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 15px; position: relative; }
        .school-logo { width: 90px; position: absolute; left: 0; top: 0; }
        .student-photo { width: 90px; height: 100px; border: 2px solid #1e293b; position: absolute; right: 0; top: 0; object-fit: cover; }
        
        .results-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
        .results-table th { background: #1e293b; color: white; padding: 10px; font-size: 13px; text-transform: uppercase; }
        .results-table td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; font-size: 14px; }
        .results-table tr:nth-child(even) { background: #f8fafc; }

        .summary-container { display: flex; width: 100%; margin-top: 20px; border: 2px solid #1e293b; }
        .summary-item { flex: 1; padding: 12px; text-align: center; border-right: 1px solid #1e293b; }
        .summary-item:last-child { border-right: none; background: #1e293b; color: white; }
        .label { display: block; font-size: 10px; font-weight: bold; margin-bottom: 5px; opacity: 0.8; }
        .value { font-size: 18px; font-weight: 800; }

        @media print { 
            body { background: white; } 
            .no-print { display: none; } 
            .report-page { margin: 0; border: none; box-shadow: none; }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body>

<div class="no-print" style="position: sticky; top: 0; background: #fff; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align:center; z-index: 1000;">
    <button onclick="window.print()" style="padding: 10px 40px; background:#2563eb; color:white; border:none; border-radius:30px; cursor:pointer; font-weight: bold; font-size: 16px;">
        <i class="fas fa-print me-2"></i> PRINT ALL REPORTS
    </button>
</div>

<?php while($student = $students->fetch_assoc()): 
    $sid = $student['id']; // ID ya mwanafunzi kwenye database
?>
<div class="report-page page-break">
    <div class="header">
        <img src="uploads/logo/<?php echo $school['logo']; ?>" class="school-logo" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2942/2942257.png'">
        <img src="uploads/students/<?php echo $student['photo']; ?>" class="student-photo" onerror="this.src='https://via.placeholder.com/90?text=PHOTO'">
        <h2 style="margin:0; color: #1e293b;"><?php echo strtoupper($school['school_name']); ?></h2>
        <p style="margin:5px; font-size: 14px;"><?php echo $school['address']; ?> | P.O.BOX <?php echo $school['pobox']; ?></p>
        <div style="background: #1e293b; color: white; display: inline-block; padding: 5px 20px; border-radius: 20px; margin-top: 10px; font-weight: bold;">
            STUDENT PROGRESS REPORT
        </div>
    </div>

    <table style="width:100%; margin-top:30px; font-size: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
        <tr>
            <td width="60%"><b>STUDENT NAME:</b> <span style="color: #2563eb;"><?php echo strtoupper($student['fullname']); ?></span></td>
            <td align="right"><b>STUDENT ID:</b> <?php echo $student['student_id']; ?></td>
        </tr>
        <tr>
            <td><b>CLASS:</b> <?php echo $class_param; ?></td>
            <td align="right"><b>TERM:</b> <?php echo $term; ?> | <b>YEAR:</b> <?php echo $year; ?></td>
        </tr>
    </table>

    <table class="results-table">
        <thead>
            <tr>
                <th align="left">Subject Name</th>
                <th>Test (M1)</th>
                <th>Exam</th>
                <th>Total</th>
                <th>Grade</th>
                <th>Points</th>
                <th width="20%">Remark</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Tunachukua alama (total_100 ni M1, exam_score ni Exam)
            $m_sql = "SELECT m.*, s.subject_name 
                      FROM marks m 
                      JOIN subjects s ON m.subject_id = s.id 
                      WHERE m.student_id = ? AND m.year = ? AND m.term = ?";
            $m_stmt = $conn->prepare($m_sql);
            $m_stmt->bind_param("iss", $sid, $year, $term);
            $m_stmt->execute();
            $marks_res = $m_stmt->get_result();

            $total_marks = 0; $count = 0; $points_list = [];

            while($m = $marks_res->fetch_assoc()):
                $m1 = $m['total_100'];
                $ex = $m['exam_score'];
                
                // Mfano: 40% Test na 60% Exam
                $final_total = ($m1 * 0.4) + ($ex * 0.6);
                $gr = getOLevelGrade($final_total);
                
                $total_marks += $final_total;
                $points_list[] = $gr['point'];
                $count++;
            ?>
            <tr>
                <td align="left"><b><?php echo strtoupper($m['subject_name']); ?></b></td>
                <td><?php echo number_format($m1, 1); ?></td>
                <td><?php echo number_format($ex, 1); ?></td>
                <td style="font-weight:bold; color: #2563eb;"><?php echo number_format($final_total, 1); ?></td>
                <td style="font-weight:bold;"><?php echo $gr['grade']; ?></td>
                <td><?php echo $gr['point']; ?></td>
                <td style="font-style: italic; font-size: 12px;"><?php echo $gr['remark']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php 
        sort($points_list);
        $best_seven = array_slice($points_list, 0, 7);
        $sum_points = ($count >= 7) ? array_sum($best_seven) : 0;
        $div = calculateDivision($sum_points, $count);
        $avg = ($count > 0) ? $total_marks / $count : 0;
    ?>

    <div class="summary-container">
        <div class="summary-item">
            <span class="label">TOTAL MARKS</span>
            <span class="value"><?php echo number_format($total_marks, 1); ?></span>
        </div>
        <div class="summary-item">
            <span class="label">AVERAGE</span>
            <span class="value"><?php echo number_format($avg, 1); ?></span>
        </div>
        <div class="summary-item">
            <span class="label">GPA POINTS</span>
            <span class="value"><?php echo ($count >= 7) ? $sum_points : 'N/A'; ?></span>
        </div>
        <div class="summary-item">
            <span class="label">DIVISION</span>
            <span class="value"><?php echo $div; ?></span>
        </div>
    </div>

    <div style="margin-top: 40px;">
        <div style="border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <p style="margin: 0 0 10px 0;"><b>Teacher's Comments:</b></p>
            <div style="height: 20px; border-bottom: 1px dotted #999;"></div>
        </div>
        
        <div style="display: flex; justify-content: space-between; margin-top: 60px; text-align: center;">
            <div style="width: 220px;">
                <div style="border-top: 2px solid #1e293b; padding-top: 5px;">Class Teacher</div>
            </div>
            <div style="width: 220px;">
                <div style="font-weight: bold;"><?php echo strtoupper($school['headmaster']); ?></div>
                <div style="border-top: 2px solid #1e293b; padding-top: 5px;">Head of School</div>
            </div>
            <div style="width: 220px;">
                <div style="border-top: 2px solid #1e293b; padding-top: 5px;">Parent Signature</div>
            </div>
        </div>
    </div>
</div>
<?php endwhile; ?>
</body>
</html>