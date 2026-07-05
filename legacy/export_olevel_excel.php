<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

// 1. Fetch School Information from Database
$school_query = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_query->fetch_assoc();
$school_name = strtoupper($school['school_name'] ?? "LIKINDY DIGITAL SOLUTION");
$school_slogan = $school['slogan'] ?? "";
$school_address = $school['address'] ?? "";

// 2. Get Filters
$subject_filter = mysqli_real_escape_string($conn, $_GET['subject'] ?? 'all');
$class      = mysqli_real_escape_string($conn, $_GET['class'] ?? '');
$stream     = mysqli_real_escape_string($conn, $_GET['stream'] ?? '');
$exam_type  = mysqli_real_escape_string($conn, $_GET['exam_type'] ?? '');
$year       = mysqli_real_escape_string($conn, $_GET['year'] ?? '');
$full_mode  = (isset($_GET['full_mode']) && ($_GET['full_mode'] == 'on' || $_GET['full_mode'] == '1')) ? true : false;

// Logic ya Division
function getDivision($pts_array) {
    sort($pts_array);
    $best_seven = array_slice($pts_array, 0, 7);
    $sum = array_sum($best_seven);
    if (count($pts_array) < 7) return ['div' => 'INC', 'pts' => $sum];
    if ($sum <= 17) return ['div' => 'I', 'pts' => $sum];
    if ($sum <= 21) return ['div' => 'II', 'pts' => $sum];
    if ($sum <= 25) return ['div' => 'III', 'pts' => $sum];
    if ($sum <= 33) return ['div' => 'IV', 'pts' => $sum];
    return ['div' => '0', 'pts' => $sum];
}

// 3. Fetch Student Marks
$sql = "SELECT m.*, s.fullname, s.gender, sub.subject_name 
        FROM olevel_marks m 
        JOIN students s ON m.student_id = s.id 
        JOIN olevel_subjects sub ON m.subject_id = sub.id
        WHERE m.class_name = '$class' 
        AND m.stream = '$stream' 
        AND m.exam_type = '$exam_type' 
        AND m.academic_year = '$year'";

if ($subject_filter !== 'all') {
    $sql .= " AND m.subject_id = '$subject_filter'";
}
$sql .= " ORDER BY s.fullname ASC";

$query = $conn->query($sql);
$student_data = [];
$subjects_found = [];
$summary = ['I'=>0, 'II'=>0, 'III'=>0, 'IV'=>0, '0'=>0, 'INC'=>0];

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $sid = $row['student_id'];
        $sname = $row['subject_name'];
        
        if (!isset($student_data[$sid])) {
            $student_data[$sid] = [
                'fullname' => $row['fullname'],
                'gender' => $row['gender'],
                'marks' => [],
                'subject_grades' => []
            ];
        }

        $m_base = ($row['monthly_base'] > 0) ? $row['monthly_base'] : 40;
        $e_base = ($row['exam_base'] > 0) ? $row['exam_base'] : 60;
        
        $m_conv = ($row['monthly_mark'] / $m_base) * 100;
        $e_conv = ($row['paper1_mark'] / $e_base) * 100;

        $student_data[$sid]['marks'][$sname] = [
            'm' => number_format($m_conv, 1),
            'e' => number_format($e_conv, 1),
            't' => number_format($row['total_score'], 1),
            'g' => $row['grade']
        ];

        $grade_points = ['A'=>1, 'B'=>2, 'C'=>3, 'D'=>4, 'F'=>5];
        $student_data[$sid]['subject_grades'][] = $grade_points[$row['grade']] ?? 5;
        
        if (!in_array($sname, $subjects_found)) { $subjects_found[] = $sname; }
    }
}

// 4. Excel Headers
$filename = "OLevel_Results_".str_replace(' ', '_', $class)."_{$stream}.xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// 5. Build Table
$col_per_sub = $full_mode ? 4 : 2;
$total_cols = 3 + (count($subjects_found) * $col_per_sub) + 2;

echo '<table border="1">';
// School Header
echo '<tr><th colspan="'.$total_cols.'" style="background-color: #1b5e20; color: white; font-size: 18px; height: 35px;">'.$school_name.'</th></tr>';
echo '<tr><th colspan="'.$total_cols.'" style="background-color: #1b5e20; color: white;">'.$school_address.' | '.$school_slogan.'</th></tr>';
echo '<tr><th colspan="'.$total_cols.'" style="background-color: #e8f5e9;"><b>CLASS:</b> '.$class.' | <b>STREAM:</b> '.$stream.' | <b>YEAR:</b> '.$year.' | <b>EXAM:</b> '.$exam_type.'</th></tr>';

// --- DIVISION SUMMARY SECTION ---
echo '<tr><th colspan="'.$total_cols.'" style="text-align: left; background-color: #f1f8e9;"><b>DIVISION SUMMARY</b></th></tr>';
echo '<tr>';
echo '<th colspan="2" style="background-color: #f1f8e9;">DIVISION</th>';
foreach(['I', 'II', 'III', 'IV', '0', 'INC'] as $d) echo '<th style="background-color: #fff;">'.$d.'</th>';
echo '<th colspan="'.($total_cols - 8).'" style="background-color: #f1f8e9;"></th>';
echo '</tr>';

// Calculate Summary
foreach($student_data as $sd) {
    $res = getDivision($sd['subject_grades']);
    $summary[$res['div']]++;
}

echo '<tr>';
echo '<th colspan="2">TOTAL STUDENTS</th>';
foreach($summary as $count) echo '<td>'.$count.'</td>';
echo '<th colspan="'.($total_cols - 8).'"></th>';
echo '</tr>';
// --- END SUMMARY ---

// Main Table Headers
echo '<tr style="background-color: #2e7d32; color: white; font-weight: bold;">';
echo '<th rowspan="2">S/N</th><th rowspan="2">Student Full Name</th><th rowspan="2">Sex</th>';
foreach ($subjects_found as $sub) { echo '<th colspan="'.$col_per_sub.'">'.$sub.'</th>'; }
echo '<th rowspan="2">Points</th><th rowspan="2">Division</th>';
echo '</tr>';

echo '<tr style="background-color: #388e3c; color: white; font-weight: bold;">';
foreach ($subjects_found as $sub) {
    echo '<th>M(100)</th>';
    if($full_mode) echo '<th>E(100)</th><th>TOT</th>';
    echo '<th>GR</th>';
}
echo '</tr>';

// Data Rows
$sn = 1;
foreach ($student_data as $data) {
    $div_info = getDivision($data['subject_grades']);
    echo '<tr style="text-align: center;">';
    echo '<td>'.$sn++.'</td>';
    echo '<td style="text-align: left;">'.ucwords(strtolower($data['fullname'])).'</td>';
    echo '<td>'.$data['gender'].'</td>';
    
    foreach ($subjects_found as $sub) {
        if (isset($data['marks'][$sub])) {
            $m = $data['marks'][$sub];
            echo '<td>'.$m['m'].'</td>';
            if($full_mode) {
                echo '<td>'.$m['e'].'</td>';
                echo '<td style="background-color: #f1f8e9; font-weight: bold;">'.$m['t'].'</td>';
            }
            echo '<td style="font-weight: bold;">'.$m['g'].'</td>';
        } else {
            echo '<td colspan="'.$col_per_sub.'">-</td>';
        }
    }
    echo '<td style="font-weight: bold;">'.$div_info['pts'].'</td>';
    echo '<td style="font-weight: bold; background-color: #e8f5e9;">'.$div_info['div'].'</td>';
    echo '</tr>';
}
echo '</table>';
exit();
?>