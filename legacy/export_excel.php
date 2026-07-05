<?php
include('db_config.php');

$class_name = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

// Fetch School Info
$school_query = $conn->query("SELECT school_name FROM school_info LIMIT 1");
$school = $school_query->fetch_assoc();
$school_name = $school['school_name'] ?? 'SCHOOL BROADSHEET';

// Force download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Broadsheet_".$class_name."_".$year.".xls");

// 1. Fetch Subjects
$subjects_query = $conn->query("SELECT id, subject_name FROM subjects WHERE level = 'o-level' ORDER BY id ASC");
$subjects = [];
while ($sub = $subjects_query->fetch_assoc()) {
    $subjects[$sub['id']] = $sub['subject_name'];
}

// 2. Initial Data Processing for Summary
$students_res = $conn->query("SELECT id, student_id, fullname, stream FROM students WHERE class_name = '$class_name' AND status = 'active' ORDER BY fullname ASC");

$div_summary = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0, 'INCO' => 0];
$student_data = [];

while($st = $students_res->fetch_assoc()) {
    $std_id = $st['id'];
    $points_array = []; 
    $total_marks = 0; 
    $count_sub = 0;
    $marks_list = [];

    foreach ($subjects as $sub_id => $name) {
        $m_query = $conn->query("SELECT total_100, grade FROM marks WHERE student_id = '$std_id' AND subject_id = '$sub_id' AND year = '$year' AND term = '$term' LIMIT 1");
        $m = $m_query->fetch_assoc();
        $score = $m['total_100'] ?? '';
        $grade = $m['grade'] ?? '-';

        $marks_list[$sub_id] = ['score' => $score, 'grade' => $grade];

        if($grade != '-' && $grade != '') {
            $total_marks += (float)$score; 
            $count_sub++;
            $pts = ($grade == 'A') ? 1 : (($grade == 'B') ? 2 : (($grade == 'C') ? 3 : (($grade == 'D') ? 4 : 5)));
            $points_array[] = $pts;
        }
    }

    // Points & Division Calculation
    $avg = ($count_sub > 0) ? round($total_marks / $count_sub, 1) : 0;
    sort($points_array);
    $best_seven = array_slice($points_array, 0, 7);
    $total_p = (count($best_seven) > 0) ? array_sum($best_seven) : 0;
    
    $div = "0";
    if ($count_sub >= 7) {
        // Full calculation for 7 subjects and above
        if ($total_p <= 17) $div = "I";
        elseif ($total_p <= 21) $div = "II";
        elseif ($total_p <= 25) $div = "III";
        elseif ($total_p <= 33) $div = "IV";
        else $div = "0";
    } elseif ($count_sub > 0) {
        // Less than 7 subjects
        $div = "INCO";
        $total_p = "0";
    } else {
        $div = "-";
        $total_p = "-";
    }

    if ($div != "-") {
        $div_summary[$div]++;
    }

    $student_data[] = [
        'info' => $st,
        'marks' => $marks_list,
        'avg' => $avg,
        'points' => $total_p,
        'div' => $div
    ];
}

// 3. START PRINTING TO EXCEL
echo "<table border='1'>";

// Main Headers
$total_cols = (count($subjects) * 2) + 7;
echo "<tr><th colspan='$total_cols' style='font-size:16pt; background:#1a237e; color:white;'>".strtoupper($school_name)."</th></tr>";
echo "<tr><th colspan='$total_cols' style='font-size:12pt;'>EXAMINATION BROADSHEET: ".strtoupper($class_name)." | $term | $year</th></tr>";
echo "<tr><th colspan='$total_cols'></th></tr>";

// DIVISION SUMMARY TABLE
echo "<tr>
        <th colspan='2' style='background:#333; color:white;'>DIVISION</th>
        <th style='background:#333; color:white;'>I</th>
        <th style='background:#333; color:white;'>II</th>
        <th style='background:#333; color:white;'>III</th>
        <th style='background:#333; color:white;'>IV</th>
        <th style='background:#333; color:white;'>0</th>
        <th style='background:#333; color:white;'>INCO</th>
        <th style='background:#333; color:white;'>TOTAL</th>
      </tr>";
echo "<tr>
        <th colspan='2'>COUNT</th>
        <td>".$div_summary['I']."</td>
        <td>".$div_summary['II']."</td>
        <td>".$div_summary['III']."</td>
        <td>".$div_summary['IV']."</td>
        <td>".$div_summary['0']."</td>
        <td style='color:red;'>".$div_summary['INCO']."</td>
        <th style='background:#eee;'>".array_sum($div_summary)."</th>
      </tr>";

echo "<tr><th colspan='$total_cols'></th></tr>";

// MAIN TABLE HEADERS
echo "<tr style='background:#000; color:#fff;'>
        <th rowspan='2'>SN</th>
        <th rowspan='2'>STUDENT ID</th>
        <th rowspan='2'>FULL NAME</th>
        <th rowspan='2'>STR</th>";
        foreach ($subjects as $name) {
            echo "<th colspan='2'>".strtoupper(substr($name,0,3))."</th>";
        }
echo "  <th rowspan='2' style='background:#0d47a1;'>AVG</th>
        <th rowspan='2' style='background:#0d47a1;'>PTS</th>
        <th rowspan='2' style='background:#0d47a1;'>DIV</th>
      </tr>";

echo "<tr style='background:#cccccc;'>";
        foreach ($subjects as $name) {
            echo "<th>MK</th><th>GD</th>";
        }
echo "</tr>";

// 4. STUDENT DATA ROWS
$sn = 1;
foreach ($student_data as $row) {
    echo "<tr>
            <td>".$sn++."</td>
            <td>".$row['info']['student_id']."</td>
            <td style='text-align:left;'>".strtoupper($row['info']['fullname'])."</td>
            <td>".$row['info']['stream']."</td>";

    foreach ($subjects as $sub_id => $name) {
        $score = $row['marks'][$sub_id]['score'];
        $grade = $row['marks'][$sub_id]['grade'];

        $style = "";
        if($grade == 'A') $style = "style='background:#28a745; color:white;'";
        elseif($grade == 'B') $style = "style='background:#a7f3d0;'";
        elseif($grade == 'C') $style = "style='background:#fef3c7;'";
        elseif($grade == 'F') $style = "style='background:#fee2e2; color:red;'";

        echo "<td>$score</td><td $style>$grade</td>";
    }

    // Incomplete status styling
    $div_style = ($row['div'] == "INCO") ? "style='background:#fee2e2; color:red; font-weight:bold;'" : "style='background:#bbdefb; font-weight:bold;'";

    echo "<td style='background:#e3f2fd; font-weight:bold;'>".$row['avg']."%</td>
          <td style='background:#e3f2fd; font-weight:bold;'>".$row['points']."</td>
          <td $div_style>".$row['div']."</td>
        </tr>";
}

echo "</table>";
?>