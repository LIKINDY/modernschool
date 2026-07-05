<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class = isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : 'Form 1';
$stream = isset($_GET['stream']) ? mysqli_real_escape_string($conn, $_GET['stream']) : 'A';
$exam = isset($_GET['exam_type']) ? mysqli_real_escape_string($conn, $_GET['exam_type']) : 'Terminal';
$year = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : '2025/2026';

// 1. Fetch School Name Dynamically from school_info Table
$school_name = "LIKINDY DIGITAL SOLUTION SECONDARY SCHOOL"; // Fallback name
$school_query = $conn->query("SELECT school_name FROM school_info LIMIT 1");
if ($school_query && $school_query->num_rows > 0) {
    $s_row = $school_query->fetch_assoc();
    $school_name = $s_row['school_name'];
}

// 2. Load Only Subjects That Have Marks Entered for Selected Filter Criteria
$subjects = [];
$sub_query = "SELECT DISTINCT sub.id, sub.subject_name 
              FROM olevel_subjects sub
              INNER JOIN olevel_marks m ON sub.id = m.subject_id
              INNER JOIN students st ON m.student_id = st.id
              WHERE st.class_name='$class' 
                AND st.stream='$stream' 
                AND m.exam_type='$exam' 
                AND m.academic_year='$year'
              ORDER BY sub.subject_name ASC";

$sub_res = $conn->query($sub_query);
if ($sub_res) {
    while($s = $sub_res->fetch_assoc()) {
        $subjects[$s['id']] = $s['subject_name'];
    }
}

// Global counters for summary blocks
$div_summary = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0];
$grade_summary = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
$subject_totals = [];
$subject_counts = [];

foreach($subjects as $sub_id => $name) {
    $subject_totals[$sub_id] = 0;
    $subject_counts[$sub_id] = 0;
}

$student_matrix = [];
$stud_res = $conn->query("SELECT id, fullname, gender FROM students WHERE class_name='$class' AND stream='$stream' ORDER BY fullname ASC");

while($st = $stud_res->fetch_assoc()) {
    $sid = $st['id'];
    $student_matrix[$sid] = [
        'name' => $st['fullname'],
        'gender' => $st['gender'],
        'marks' => [],
        'grades' => [],
        'points_array' => [],
        'total' => 0,
        'count' => 0,
        'avg' => 0,
        'division' => '0',
        'points' => 0,
        'rank' => 0
    ];
    
    foreach($subjects as $sub_id => $sub_name) {
        $m_res = $conn->query("SELECT monthly_mark, m2_mark, paper1_mark, paper2_mark FROM olevel_marks 
                               WHERE student_id='$sid' AND subject_id='$sub_id' AND exam_type='$exam' AND academic_year='$year' LIMIT 1");
        
        $final = 0;
        $has_mark = false;

        if($m_res && $m_res->num_rows > 0) {
            $m = $m_res->fetch_assoc();
            $has_mark = true;
            
            if ($exam === 'Terminal' || $exam === 'Annual') {
                $final = $m['paper1_mark'];
            } elseif(strpos($exam, 'Term') !== false) {
                $final = $m['monthly_mark'] + $m['paper1_mark'];
            } elseif($exam === 'Special') {
                $final = $m['monthly_mark'] + $m['m2_mark'] + $m['paper1_mark'];
            } elseif($exam === 'Mock') {
                $s_lower = strtolower($sub_name);
                if(strpos($s_lower,'bio')!==false || strpos($s_lower,'phy')!==false || strpos($s_lower,'chem')!==false) {
                    $final = ($m['paper1_mark'] + $m['paper2_mark']) / 1.5;
                } else {
                    $final = $m['paper1_mark'];
                }
            }
            if($final > 100) $final = 100;
        }

        if($has_mark) {
            $student_matrix[$sid]['marks'][$sub_id] = $final;
            $student_matrix[$sid]['total'] += $final;
            $student_matrix[$sid]['count']++;

            // Track Subject Totals for bottom summary lines
            $subject_totals[$sub_id] += $final;
            $subject_counts[$sub_id]++;
            
            // Assign single subject grades & points mapping
            if($final >= 80) { $g = 'A'; $p = 1; }
            elseif($final >= 70) { $g = 'B'; $p = 2; }
            elseif($final >= 60) { $g = 'C'; $p = 3; }
            elseif($final >= 50) { $g = 'D'; $p = 4; }
            else { $g = 'F'; $p = 5; }

            $student_matrix[$sid]['grades'][$sub_id] = $g;
            $student_matrix[$sid]['points_array'][] = $p;
            $grade_summary[$g]++;
        } else {
            $student_matrix[$sid]['marks'][$sub_id] = '-';
            $student_matrix[$sid]['grades'][$sub_id] = '-';
        }
    }
    
    // NECTA Division Point Calculation Logic (Top 7 Subjects)
    if($student_matrix[$sid]['count'] >= 1) {
        $student_matrix[$sid]['avg'] = $student_matrix[$sid]['total'] / $student_matrix[$sid]['count'];
        
        $pts = $student_matrix[$sid]['points_array'];
        sort($pts); // Sort ascending to extract lowest points values (Best scores)
        
        $top_seven = array_slice($pts, 0, 7);
        $total_points = array_sum($top_seven);
        $student_matrix[$sid]['points'] = $total_points;
        
        // Calculate Division structural rules based on 7 subjects points matrix
        if(count($top_seven) >= 7) {
            if($total_points >= 7 && $total_points <= 17) $div = 'I';
            elseif($total_points <= 21) $div = 'II';
            elseif($total_points <= 25) $div = 'III';
            elseif($total_points <= 33) $div = 'IV';
            else $div = '0';
        } else {
            $div = 'IV'; // Fallback if criteria criteria not fully achieved
            if($total_points > 33 || empty($total_points)) $div = '0';
        }
        
        $student_matrix[$sid]['division'] = $div;
        $div_summary[$div]++;
    }
}

// Compute Positions Sorting Descending Averages
uasort($student_matrix, function($a, $b) { return $b['avg'] <=> $a['avg']; });
$rnk = 1;
foreach($student_matrix as $sid => $data) {
    if($data['count'] > 0) $student_matrix[$sid]['rank'] = $rnk++;
}

// Sort alphabetically for finalized structured scoreboard view presentation
uksort($student_matrix, function($a, $b) use ($student_matrix) {
    return strcmp($student_matrix[$a]['name'], $student_matrix[$b]['name']);
});

// Configure Native Excel Headers Download Gateway
$clean_filename = "OLevel_Broadsheet_".str_replace(' ', '_', $class)."_".$stream.".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$clean_filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"><style>';
echo 'table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; }';
echo '.school-title { font-size: 16pt; font-weight: bold; color: #064e3b; text-align: center; }';
echo '.doc-title { font-size: 12pt; font-weight: bold; color: #475569; text-align: center; }';
echo '.summary-title { font-weight: bold; background-color: #0f172a; color: #ffffff; text-align: center; }';
echo 'th { background-color: #059669; color: #ffffff; font-weight: bold; border: 1px solid #047857; text-align: center; height: 32px; font-size: 10pt; }';
echo 'td { border: 1px solid #cbd5e1; height: 26px; padding: 5px; font-size: 10pt; }';
echo '.bold-center { font-weight: bold; text-align: center; }';
echo '.meta-label { font-weight: bold; background-color: #f1f5f9; color: #334155; }';
echo '.footer-row { font-weight: bold; background-color: #f8fafc; }';
echo '.footer-total { font-weight: bold; background-color: #e2e8f0; color: #0f172a; text-align: center; }';
echo '.footer-avg { font-weight: bold; background-color: #d1fae5; color: #065f46; text-align: center; }';

// Grade Score cell color styles
echo '.score-A { background-color: #d1fae5; color: #065f46; font-weight: bold; text-align: center; }';
echo '.score-B { background-color: #e0f2fe; color: #0369a1; font-weight: bold; text-align: center; }';
echo '.score-C { background-color: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }';
echo '.score-D { background-color: #ffedd5; color: #9a3412; font-weight: bold; text-align: center; }';
echo '.score-F { background-color: #ffe4e6; color: #991b1b; font-weight: bold; text-align: center; }';
echo '.score-none { color: #94a3b8; text-align: center; }';

// Division Card colors
echo '.div-I { background-color: #10b981; color: #ffffff; font-weight: bold; text-align: center; }';
echo '.div-II { background-color: #3b82f6; color: #ffffff; font-weight: bold; text-align: center; }';
echo '.div-III { background-color: #f59e0b; color: #ffffff; font-weight: bold; text-align: center; }';
echo '.div-IV { background-color: #a855f7; color: #ffffff; font-weight: bold; text-align: center; }';
echo '.div-0 { background-color: #ef4444; color: #ffffff; font-weight: bold; text-align: center; }';
echo '</style></head><body>';

$total_columns = count($subjects) + 8;

echo '<table>';
// Main System Header Banner
echo '<tr><td colspan="'.$total_columns.'" class="school-title">'.strtoupper($school_name).'</td></tr>';
echo '<tr><td colspan="'.$total_columns.'" class="doc-title">OFFICIAL O-LEVEL EXAM RESULTS BROAD SHEET</td></tr>';
echo '<tr><td colspan="2" class="meta-label">Class Level:</td><td colspan="2">'.strtoupper($class).' (Stream '.$stream.')</td></tr>';
echo '<tr><td colspan="2" class="meta-label">Examination:</td><td colspan="2">'.$exam.' (Academic Year: '.$year.')</td></tr>';
echo '<tr></tr>'; // Layout buffer spacer

// ======================== SUMMARY TABLES ZONE ========================
echo '<tr>';
echo '<td colspan="3" class="summary-title">DIVISION SUMMARY</td>';
echo '<td></td>'; // Spacer element
echo '<td colspan="3" class="summary-title">GRADE SUMMARY</td>';
echo '</tr>';

$divs = ['I', 'II', 'III', 'IV', '0'];
$grds = ['A', 'B', 'C', 'D', 'F'];

for ($i = 0; $i < 5; $i++) {
    echo '<tr>';
    // Division summary side
    echo '<td class="meta-label text-center">Div '.$divs[$i].'</td>';
    echo '<td class="bold-center div-'.$divs[$i].'">'.$div_summary[$divs[$i]].'</td>';
    echo '<td>Students</td>';
    
    echo '<td></td>'; // Divider cell
    
    // Grade summary side
    echo '<td class="meta-label text-center">Grade '.$grds[$i].'</td>';
    echo '<td class="bold-center score-'.$grds[$i].'">'.$grade_summary[$grds[$i]].'</td>';
    echo '<td>Subjects</td>';
    echo '</tr>';
}
echo '<tr></tr><tr></tr>'; // Layout buffer spacer rows before main score entries

// ======================== MAIN SCOREBOARD MATRIX TABLE ========================
echo '<thead><tr>';
echo '<th>SN</th>';
echo '<th style="text-align:left; width:260px;">Student Full Name</th>';
echo '<th>Sex</th>';

// Generate dynamic subject columns
foreach($subjects as $sub) {
    echo '<th>'.htmlspecialchars($sub).'</th>';
}

echo '<th style="background-color:#1e3a8a; color:white;">Total</th>';
echo '<th style="background-color:#1e3a8a; color:white;">Average</th>';
echo '<th style="background-color:#0f172a; color:white;">Points</th>';
echo '<th style="background-color:#0f172a; color:white;">Division</th>';
echo '<th style="background-color:#b91c1c; color:white;">Rank</th>';
echo '</tr></thead><tbody>';

$sn = 1;
foreach($student_matrix as $row) {
    echo '<tr>';
    echo '<td class="bold-center">'.$sn++.'</td>';
    echo '<td style="text-align:left; font-weight:bold;">'.strtoupper($row['name']).'</td>';
    echo '<td class="bold-center">'.$row['gender'].'</td>';
    
    foreach($subjects as $sub_id => $name) {
        $mark = isset($row['marks'][$sub_id]) ? $row['marks'][$sub_id] : '-';
        $grade = isset($row['grades'][$sub_id]) ? $row['grades'][$sub_id] : '-';
        
        if($mark !== '-') {
            // Apply background color highlights per subject grade score achieved
            echo '<td class="score-'.$grade.'">'.number_format($mark, 0).' ['.$grade.']</td>';
        } else {
            echo '<td class="score-none bold-center">-</td>';
        }
    }
    
    // Student aggregates totals columns
    echo '<td class="bold-center" style="background-color:#f8fafc;">'.$row['total'].'</td>';
    echo '<td class="bold-center" style="background-color:#f1f5f9; font-weight:bold;">'.number_format($row['avg'], 1).'</td>';
    echo '<td class="bold-center">'.$row['points'].'</td>';
    
    // Division Status colored badge mapping row indicator
    echo '<td class="div-'.$row['division'].'">Div '.$row['division'].'</td>';
    echo '<td class="bold-center" style="background-color:#f0fdf4; color:#16a34a; font-weight:bold;">'.($row['rank'] ?: '-').'</td>';
    echo '</tr>';
}

// ======================== BOTTOM SUBJECT TOTALS & AVERAGES ========================
if (!empty($subjects)) {
    // Subject Totals Row Line
    echo '<tr class="footer-row">';
    echo '<td colspan="3" class="footer-total">SUBJECT TOTAL MARKS</td>';
    foreach($subjects as $sub_id => $name) {
        echo '<td class="footer-total">'.$subject_totals[$sub_id].'</td>';
    }
    echo '<td colspan="5" style="background-color:#e2e8f0;"></td>'; // Block spacer ends
    echo '</tr>';

    // Subject Average Performance Row Line
    echo '<tr class="footer-row">';
    echo '<td colspan="3" class="footer-avg">SUBJECT MEAN AVERAGE</td>';
    foreach($subjects as $sub_id => $name) {
        $count = $subject_counts[$sub_id];
        $mean = ($count > 0) ? ($subject_totals[$sub_id] / $count) : 0;
        echo '<td class="footer-avg">'.number_format($mean, 1).'</td>';
    }
    echo '<td colspan="5" style="background-color:#d1fae5;"></td>'; // Block spacer ends
    echo '</tr>';
} else {
    echo '<tr><td colspan="8" class="bold-center">No marks found for the selected criteria.</td></tr>';
}

echo '</tbody></table></body></html>';
exit();
?>