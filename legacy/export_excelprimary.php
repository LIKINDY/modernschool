<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

// 1. Fetch School Information
$school_query = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_query->fetch_assoc();
$school_name = strtoupper($school['school_name'] ?? "LIKINDY DIGITAL SOLUTION");
$school_slogan = $school['slogan'] ?? "";
$school_address = $school['address'] ?? "";

// 2. Get Filters & Options
$class = isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : '';
$year = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : '';
$stream = isset($_GET['stream']) ? mysqli_real_escape_string($conn, $_GET['stream']) : '';
$exam = isset($_GET['exam_type']) ? mysqli_real_escape_string($conn, $_GET['exam_type']) : '';
$subject_filter = isset($_GET['subject_id']) ? mysqli_real_escape_string($conn, $_GET['subject_id']) : 'all';
$weighted_average = isset($_GET['weighted_avg']) ? true : false;

// Grading Logic
function getGrade($mark) {
    if ($mark >= 81) return 'A';
    if ($mark >= 70) return 'B';
    if ($mark >= 60) return 'C';
    if ($mark >= 40) return 'D';
    return 'E';
}

// Remark Logic
function getRemark($avg) {
    if ($avg >= 81) return 'Excellent';
    if ($avg >= 70) return 'Very Good';
    if ($avg >= 60) return 'Good';
    if ($avg >= 40) return 'Average';
    return 'Poor';
}

$student_data = [];
$subjects_found = [];

$sql = "SELECT m.*, s.fullname, s.gender, sub.subject_name 
        FROM primary_marks m
        JOIN students s ON m.student_id = s.id
        JOIN primary_subjects sub ON m.subject_id = sub.id
        WHERE m.class_name = '$class' 
        AND m.academic_year = '$year' 
        AND m.stream = '$stream' 
        AND m.exam_type = '$exam'";

if ($subject_filter !== 'all') {
    $sql .= " AND m.subject_id = '$subject_filter'";
}

$query = $conn->query($sql);

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $sid = $row['student_id'];
        $sname = $row['subject_name'];
        
        if (!isset($student_data[$sid])) {
            $student_data[$sid] = [
                'fullname' => $row['fullname'],
                'gender' => $row['gender'],
                'marks' => [],
                'total_for_avg' => 0,
                'count_for_avg' => 0
            ];
        }
        
        $m_raw = $row['monthly_mark'];
        $m_converted = ($m_raw > 0) ? ($m_raw / 40) * 100 : 0; 
        
        $e_raw = $row['exam_mark'];
        $e_converted = ($e_raw > 0) ? ($e_raw / 60) * 100 : 0;

        if ($weighted_average) {
            $total_display = ($m_converted * 0.4) + ($e_converted * 0.6);
        } else {
            $total_display = $m_converted; 
        }

        $student_data[$sid]['marks'][$sname] = [
            'm' => $m_converted,
            'e' => $e_converted,
            't' => $total_display,
            'g' => getGrade($total_display)
        ];
        
        $student_data[$sid]['total_for_avg'] += $total_display;
        $student_data[$sid]['count_for_avg']++;
        
        if (!in_array($sname, $subjects_found)) {
            $subjects_found[] = $sname;
        }
    }
}

foreach ($student_data as $sid => $data) {
    $avg = ($data['count_for_avg'] > 0) ? ($data['total_for_avg'] / $data['count_for_avg']) : 0;
    $student_data[$sid]['final_avg'] = $avg;
}

uasort($student_data, function($a, $b) {
    return $b['final_avg'] <=> $a['final_avg'];
});

$clean_class = str_replace(' ', '_', $class);
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Results_{$clean_class}_{$stream}.xls");

echo '<table border="1">';
echo '<thead>';

// 4 columns per subject (M, E, TOT, GRD)
$total_sub_cols = count($subjects_found) * 4;
$total_table_cols = 3 + $total_sub_cols + 4; // 3 start cols + subjects + 4 end cols (AVG, GRD, POS, REMARK)

echo '<tr><th colspan="' . $total_table_cols . '" style="background-color: #1a237e; color: white; font-size: 18px; height: 35px; text-align: center;">' . $school_name . '</th></tr>';
echo '<tr><th colspan="' . $total_table_cols . '" style="background-color: #1a237e; color: white; font-size: 12px; text-align: center;">' . $school_address . ' | ' . $school_slogan . '</th></tr>';
echo '<tr><th colspan="' . $total_table_cols . '" style="background-color: #f5f5f5; text-align: left;"><b> CLASS:</b> '.$class.' | <b>STREAM:</b> '.$stream.' | <b>YEAR:</b> '.$year.' | <b>MODE:</b> '.($weighted_average ? "40/60 Weighted" : "Monthly Only").'</th></tr>';

echo '<tr style="background-color: #0d47a1; color: white; font-weight: bold; text-align: center;">';
echo '<th rowspan="2">S/N</th><th rowspan="2">Student Full Name</th><th rowspan="2">Gender</th>';
foreach ($subjects_found as $sub) { echo '<th colspan="4">' . $sub . '</th>'; }
echo '<th rowspan="2">AVG (%)</th><th rowspan="2">GRD</th><th rowspan="2">POS</th><th rowspan="2">REMARK</th>';
echo '</tr>';

echo '<tr style="background-color: #1565c0; color: white; font-weight: bold; text-align: center;">';
foreach ($subjects_found as $sub) {
    echo '<th>M(100)</th><th>E(100)</th><th>TOT</th><th>GRD</th>';
}
echo '</tr></thead><tbody>';

if (!empty($student_data)) {
    $sn = 1; $rank = 1;
    foreach ($student_data as $data) {
        echo '<tr style="text-align: center;">';
        echo '<td>' . $sn++ . '</td>';
        echo '<td style="text-align: left;">' . ucwords(strtolower($data['fullname'])) . '</td>';
        echo '<td>' . $data['gender'] . '</td>';
        
        foreach ($subjects_found as $sub) {
            if (isset($data['marks'][$sub])) {
                $m = $data['marks'][$sub];
                echo '<td>' . number_format($m['m'], 1) . '</td>';
                echo '<td>' . number_format($m['e'], 1) . '</td>';
                echo '<td style="font-weight: bold; background-color: #e3f2fd;">' . number_format($m['t'], 1) . '</td>';
                echo '<td style="font-weight: bold;">' . $m['g'] . '</td>';
            } else {
                echo '<td>-</td><td>-</td><td>-</td><td>-</td>';
            }
        }
        $avg = $data['final_avg'];
        $f_grade = getGrade($avg);
        echo '<td style="font-weight: bold; background-color: #fff9c4;">' . number_format($avg, 1) . '</td>';
        echo '<td style="font-weight: bold;">' . $f_grade . '</td>';
        echo '<td style="font-weight: bold;">' . $rank++ . '</td>';
        echo '<td style="text-align: left;">' . getRemark($avg) . '</td>';
        echo '</tr>';
    }
}
echo '</tbody></table>';
exit();
?>