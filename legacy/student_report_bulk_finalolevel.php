<?php
session_start();
include('db_config.php');

// Tunachukua vigezo vya darasa, mwaka na term
$class_name = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? 'Annual';

if (!$class_name || !$year) { echo "Missing parameters (Class or Year)."; exit; }

// 1. Fetch School Information
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch All Active Students in that class
$students_query = $conn->query("SELECT * FROM students WHERE class_name = '$class_name' AND status = 'active' ORDER BY fullname ASC");

// Grading Functions
function getOlevelGrade80($s) {
    if ($s >= 80) return ['A', 'Excellent'];
    if ($s >= 70) return ['B', 'Very Good'];
    if ($s >= 60) return ['C', 'Good'];
    if ($s >= 50) return ['D', 'Pass'];
    return ['F', 'Fail'];
}
function getGradePoints($g) {
    $p = ['A'=>1, 'B'=>2, 'C'=>3, 'D'=>4, 'F'=>5];
    return $p[$g] ?? 5;
}
function calculateDivision($p, $count) {
    if($count < 7) return "N/A";
    if($p <= 17) return "I"; if($p <= 21) return "II";
    if($p <= 25) return "III"; if($p <= 33) return "IV";
    return "0";

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Annual Report - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Times New Roman', serif; }
        .report-paper { 
            width: 210mm; 
            margin: 20px auto; 
            padding: 40px; 
            background: #fff; 
            border: 1px solid #ddd; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            page-break-after: always; /* Hii inatenganisha karatasi wakati wa ku-print */
        }
        .header-box { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .student-photo { width: 100px; height: 110px; border: 1px solid #000; object-fit: cover; }
        .table-marks th, .table-marks td { border: 1px solid #000 !important; text-align: center; padding: 6px; font-size: 14px; }
        .summary-box { border: 2px solid #000; background: #f2f2f2; margin-bottom: 15px; display: flex; text-align: center; }
        .summary-item { flex: 1; padding: 8px; border-right: 1px solid #000; }
        .summary-item:last-child { border-right: none; }
        .comment-area { border-bottom: 1px dotted #000; min-height: 35px; margin-bottom: 10px; padding-top: 5px; font-style: italic; }
        .grading-table { font-size: 12px; width: 100%; border: 1px solid #000; }
        .grading-table td { border: 1px solid #000; padding: 2px 5px; }
        @media print { 
            .no-print { display: none; } 
            body { background: #fff; margin: 0; } 
            .report-paper { border: none; margin: 0; box-shadow: none; width: 100%; } 
        }
    </style>
</head>
<body>

<div class="text-center no-print my-3">
    <button onclick="window.print()" class="btn btn-success btn-lg px-5 shadow-sm">PRINT ALL REPORTS</button>
</div>

<?php 
while ($student = $students_query->fetch_assoc()): 
    $student_id = $student['id'];

    // Logic ya kupata marks za mwanafunzi huyu
    $marks_sql = "SELECT s.subject_name, s.id as sub_id,
                  MAX(CASE WHEN m.term = 'Term 1' THEN m.total_100 END) as t1_score,
                  MAX(CASE WHEN m.term = 'Term 2' THEN m.total_100 END) as t2_score
                  FROM subjects s
                  LEFT JOIN marks m ON s.id = m.subject_id AND m.student_id = '$student_id' AND m.year = '$year'
                  GROUP BY s.id
                  HAVING t1_score IS NOT NULL OR t2_score IS NOT NULL
                  ORDER BY s.subject_name ASC";
    $marks_res = $conn->query($marks_sql);
    
    $marks_data = [];
    $points_array = [];
    $total_final_marks = 0;
    $subject_count = 0;

    while ($row = $marks_res->fetch_assoc()) {
        $sub_id = $row['sub_id'];
        $t1 = $row['t1_score'] ?? 0;
        $t2 = $row['t2_score'] ?? 0;
        $final_100 = round(($t1 * 0.4) + ($t2 * 0.6));
        $row['final_score'] = $final_100;

        // Subject Position Logic
        $all_sub_sql = "SELECT m.student_id, 
                        ROUND((MAX(CASE WHEN m.term = 'Term 1' THEN m.total_100 END) * 0.4) + 
                              (MAX(CASE WHEN m.term = 'Term 2' THEN m.total_100 END) * 0.6)) as annual_sub_total
                        FROM marks m JOIN students st ON m.student_id = st.id
                        WHERE m.subject_id = '$sub_id' AND m.year = '$year' AND st.class_name = '$class_name'
                        GROUP BY m.student_id ORDER BY annual_sub_total DESC";
        $all_sub_res = $conn->query($all_sub_sql);
        $sub_pos = 1; $highest = 0; $found = false;
        while($r = $all_sub_res->fetch_assoc()){
            if($r['annual_sub_total'] > $highest) $highest = $r['annual_sub_total'];
            if(!$found){ if($r['student_id'] == $student_id) $found=true; else $sub_pos++; }
        }
        $row['sub_pos'] = $sub_pos; $row['high'] = $highest;
        
        $grade_info = getOlevelGrade80($final_100);
        $row['grade'] = $grade_info[0];
        $row['remark'] = $grade_info[1];
        $points_array[] = getGradePoints($grade_info[0]);
        $marks_data[] = $row;
        $total_final_marks += $final_100;
        $subject_count++;
    }

    // Overall Position in Class
    $overall_rank_sql = "SELECT m.student_id, 
                        AVG((CASE WHEN m.term = 'Term 1' THEN m.total_100 ELSE 0 END * 0.4) + 
                            (CASE WHEN m.term = 'Term 2' THEN m.total_100 ELSE 0 END * 0.6)) as annual_avg
                        FROM marks m JOIN students st ON m.student_id = st.id
                        WHERE m.year = '$year' AND st.class_name = '$class_name'
                        GROUP BY m.student_id ORDER BY annual_avg DESC";
    $overall_res = $conn->query($overall_rank_sql);
    $total_students = $overall_res->num_rows;
    $overall_pos = 0; $rank = 0;
    while($r = $overall_res->fetch_assoc()){ $rank++; if($r['student_id'] == $student_id){ $overall_pos = $rank; break; }}

    $average = ($subject_count > 0) ? ($total_final_marks / $subject_count) : 0;
    sort($points_array);
    $total_points = array_sum(array_slice($points_array, 0, 7));
    $division = calculateDivision($total_points, count($points_array));
?>

<div class="report-paper">
    <div class="header-box">
        <div class="row align-items-center">
            <div class="col-2"><img src="uploads/logo/<?= $school['logo'] ?>" style="width: 90px;"></div>
            <div class="col-8 text-center text-uppercase">
                <h2 class="fw-bold mb-0"><?= $school['school_name'] ?></h2>
                <p class="mb-0 fw-bold"><?= $school['address'] ?> | TEL: <?= $school['phone'] ?></p>
                <h5 class="mt-2 text-decoration-underline fw-bold">ANNUAL ACADEMIC PROGRESS REPORT</h5>
            </div>
            <div class="col-2 text-end">
                <img src="uploads/students/<?= $student['photo'] ?>" class="student-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['fullname']) ?>'">
            </div>
        </div>
    </div>

    <div class="row mb-3 fw-bold text-uppercase small">
        <div class="col-7">
            STUDENT NAME: <span class="border-bottom border-dark px-2"><?= $student['fullname'] ?></span><br>
            STUDENT ID: <?= $student['student_id'] ?>
        </div>
        <div class="col-5 text-end">
            CLASS: <?= $student['class_name'] ?> - <?= $student['stream'] ?><br>
            POSITION: <?= $overall_pos ?> / <?= $total_students ?> | YEAR: <?= $year ?>
        </div>
    </div>

    <table class="table table-marks w-100 mb-0">
        <thead>
            <tr>
                <th class="text-start">SUBJECTS</th>
                <th>ANNUAL (100%)</th>
                <th>GRADE</th>
                <th>POS</th>
                <th>REMARK</th>
                <th>HIGH</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($marks_data as $m): ?>
            <tr>
                <td class="text-start fw-bold"><?= strtoupper($m['subject_name']) ?></td>
                <td class="fw-bold"><?= $m['final_score'] ?></td>
                <td class="fw-bold"><?= $m['grade'] ?></td>
                <td><?= $m['sub_pos'] ?></td>
                <td class="small"><?= $m['remark'] ?></td>
                <td class="fw-bold"><?= $m['high'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-item">TOTAL: <strong><?= $total_final_marks ?></strong></div>
        <div class="summary-item">AVG: <strong><?= number_format($average, 1) ?>%</strong></div>
        <div class="summary-item">DIV: <strong><?= $division ?></strong></div>
        <div class="summary-item">POINTS: <strong><?= $total_points ?></strong></div>
    </div>

    <div class="mt-3">
        <p class="fw-bold text-uppercase mb-1 small">Teacher's Comments:</p>
        <div class="comment-area">Vema sana <?= explode(' ', $student['fullname'])[0] ?>, ongeza bidii.</div>
        <p class="fw-bold text-uppercase mb-1 mt-3 small">Head of School's Comments:</p>
        <div class="comment-area">Matokeo mazuri, ongeza jitihada kwenye masomo ya sayansi na hesabu.</div>
    </div>

    <div class="row mt-4">
        <div class="col-5">
            <table class="grading-table">
                <tr class="fw-bold bg-light"><td>Grade</td><td>Range</td><td>Remark</td></tr>
                <tr><td>A</td><td>80-100</td><td>Excellent</td></tr>
                <tr><td>B</td><td>70-79</td><td>V.Good</td></tr>
                <tr><td>C</td><td>60-69</td><td>Good</td></tr>
                <tr><td>D</td><td>50-59</td><td>Pass</td></tr>
                <tr><td>F</td><td>0-49</td><td>Fail</td></tr>
            </table>
        </div>
        <div class="col-7">
            <div class="row text-center fw-bold small mt-4">
                <div class="col-4"><div class="border-top border-dark pt-1">Class Teacher</div></div>
                <div class="col-4"><div class="border-top border-dark pt-1">Headmaster</div></div>
                <div class="col-4"><div class="border-top border-dark pt-1">Parent</div></div>
            </div>
        </div>
    </div>
</div>
<?php endwhile; ?>

</body>
</html>