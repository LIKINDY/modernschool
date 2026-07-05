<?php
session_start();
include('db_config.php');

$class = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class) exit("Class name is required.");

// Force download Excel file
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=BroadSheet_" . str_replace(' ', '_', $class) . "_" . $year . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 1. Pata masomo yote husika
$subject_list = [];
$sub_q = $conn->query("SELECT DISTINCT s.id, s.subject_name 
                        FROM subjects s 
                        JOIN marks m ON s.id = m.subject_id 
                        JOIN students st ON m.student_id = st.id
                        WHERE st.class_name = '$class' AND m.year = '$year' AND m.term = '$term'
                        ORDER BY s.subject_name ASC");
while($row = $sub_q->fetch_assoc()){
    $subject_list[$row['id']] = $row['subject_name'];
}

// 2. Tayarisha Data za Wanafunzi na Summary
$students_res = $conn->query("SELECT * FROM students WHERE class_name='$class' AND status='active' ORDER BY fullname ASC");

$summary = [
    'F' => ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0],
    'M' => ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0]
];

$processed_students = [];
while($st = $students_res->fetch_assoc()) {
    $st_id = $st['id'];
    $marks_res = $conn->query("SELECT subject_id, grade FROM marks WHERE student_id = '$st_id' AND year = '$year' AND term = '$term'");
    
    $st_marks = [];
    $points_array = [];
    while($m = $marks_res->fetch_assoc()) {
        $st_marks[$m['subject_id']] = strtoupper($m['grade']);
        $g = strtoupper($m['grade']);
        $p = ($g == 'A') ? 1 : (($g == 'B') ? 2 : (($g == 'C') ? 3 : (($g == 'D') ? 4 : 5)));
        $points_array[] = $p;
    }

    sort($points_array);
    $best_seven = array_slice($points_array, 0, 7);
    $total_points = (count($points_array) >= 7) ? array_sum($best_seven) : array_sum($points_array);
    
    $div = "0";
    if(count($points_array) > 0) {
        if($total_points >= 7 && $total_points <= 17) $div = "I";
        elseif($total_points <= 21) $div = "II";
        elseif($total_points <= 25) $div = "III";
        elseif($total_points <= 33) $div = "IV";
        else $div = "0";
    }

    $gender = (strtoupper($st['gender'][0]) == 'F') ? 'F' : 'M';
    if(count($points_array) > 0) $summary[$gender][$div]++;

    $processed_students[] = [
        'id_no' => $st['student_id'] ?? $st_id,
        'name' => strtoupper($st['fullname']),
        'sex' => $gender,
        'marks' => $st_marks,
        'pts' => (count($points_array) > 0) ? $total_points : '--',
        'div' => $div
    ];
}
?>

<style>
    .title { font-size: 16pt; font-weight: bold; text-align: center; color: #1a237e; }
    .header { background-color: #1e293b; color: #ffffff; font-weight: bold; text-align: center; border: 0.5pt solid #000; }
    .summary-header { background-color: #0f172a; color: #ffffff; font-weight: bold; text-align: center; }
    .sub-title { background-color: #cbd5e1; font-weight: bold; text-align: center; }
    .even { background-color: #f8fafc; }
    .odd { background-color: #ffffff; }
    .div-I { color: #15803d; font-weight: bold; }
    .div-0 { color: #b91c1c; font-weight: bold; }
    .points { font-weight: bold; background-color: #f1f5f9; }
    td { border: 0.5pt solid #e2e8f0; padding: 5px; }
</style>

<table>
    <tr>
        <td colspan="<?= count($subject_list) + 6 ?>" class="title">
            <?= strtoupper($school['school_name'] ?? 'SMART SECONDARY SCHOOL') ?>
        </td>
    </tr>
    <tr>
        <td colspan="<?= count($subject_list) + 6 ?>" style="text-align: center; font-weight: bold;">
            O-LEVEL EXAMINATION BROADSHEET - <?= strtoupper($class) ?> (<?= $term ?> <?= $year ?>)
        </td>
    </tr>
</table>

<br>

<table border="1">
    <thead>
        <tr><th colspan="7" class="summary-header">PERFORMANCE SUMMARY</th></tr>
        <tr class="sub-title">
            <th width="100">SEX</th>
            <th width="50">I</th>
            <th width="50">II</th>
            <th width="50">III</th>
            <th width="50">IV</th>
            <th width="50">0</th>
            <th width="80">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach(['F' => 'Girls', 'M' => 'Boys'] as $key => $label): 
            $row_total = array_sum($summary[$key]); ?>
        <tr>
            <td style="background-color: #f1f5f9;"><b><?= $label ?></b></td>
            <td align="center" style="color: #15803d;"><b><?= $summary[$key]['I'] ?></b></td>
            <td align="center"><?= $summary[$key]['II'] ?></td>
            <td align="center"><?= $summary[$key]['III'] ?></td>
            <td align="center"><?= $summary[$key]['IV'] ?></td>
            <td align="center" style="color: #b91c1c;"><b><?= $summary[$key]['0'] ?></b></td>
            <td align="center" style="background-color: #f1f5f9;"><b><?= $row_total ?></b></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<br>

<table border="1">
    <thead>
        <tr class="header">
            <th width="40">SN</th>
            <th width="120">ID NUMBER</th>
            <th width="250">STUDENT NAME</th>
            <th width="50">SEX</th>
            <?php foreach($subject_list as $sub_name): ?>
                <th width="60"><?= strtoupper(substr($sub_name, 0, 4)) ?></th>
            <?php endforeach; ?>
            <th width="60">PTS</th>
            <th width="60">DIV</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $n = 1; 
        foreach($processed_students as $row): 
            $bg_class = ($n % 2 == 0) ? 'even' : 'odd';
            $div_style = ($row['div'] == 'I') ? 'div-I' : (($row['div'] == '0') ? 'div-0' : '');
        ?>
        <tr class="<?= $bg_class ?>">
            <td align="center"><?= $n++ ?></td>
            <td align="left"><?= $row['id_no'] ?></td>
            <td align="left"><?= $row['name'] ?></td>
            <td align="center"><?= $row['sex'] ?></td>
            
            <?php foreach($subject_list as $sub_id => $name): 
                $grade = $row['marks'][$sub_id] ?? '-';
                $color = "";
                if($grade == 'A') $color = "color: #15803d; font-weight: bold;";
                if($grade == 'F') $color = "color: #b91c1c;";
            ?>
                <td align="center" style="<?= $color ?>"><?= $grade ?></td>
            <?php endforeach; ?>

            <td align="center" class="points"><?= $row['pts'] ?></td>
            <td align="center" class="<?= $div_style ?>"><b><?= $row['div'] ?></b></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table>
    <tr></tr>
    <tr>
        <td colspan="3" style="font-style: italic; color: #64748b;">Generated via Smart School System on <?= date('d-m-Y') ?></td>
    </tr>
</table>