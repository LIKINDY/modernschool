<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 1. Fetch School Information from Database (REKKEBISHWA: school_info)
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1"); 
$school = $school_res ? $school_res->fetch_assoc() : null;

// 2. Capture Filters
$class      = $_GET['class'] ?? '';
$stream     = $_GET['stream'] ?? '';
$exam_type  = $_GET['exam_type'] ?? '';
$year       = $_GET['year'] ?? '';

// Headers to force download as Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Nursery_Mkeka_{$class}_{$stream}_{$exam_type}.xls");

// Fetch Subjects
$subjects_list = [];
$sub_query = $conn->query("SELECT id, subject_name FROM nursery_subjects ORDER BY subject_name ASC");
while($s = $sub_query->fetch_assoc()) {
    $subjects_list[$s['id']] = $s['subject_name'];
}

// 3. New Grading System Logic
function getGradeData($mark) {
    if ($mark >= 90) return ['grade' => 'A+', 'remark' => 'EXCELLENT', 'color' => '#008000', 'text' => '#ffffff']; // GREEN
    if ($mark >= 80) return ['grade' => 'A',  'remark' => 'VERY GOOD', 'color' => '#0000FF', 'text' => '#ffffff']; // BLUE
    if ($mark >= 60) return ['grade' => 'B',  'remark' => 'GOOD',      'color' => '#FFFF00', 'text' => '#000000']; // YELLOW
    if ($mark >= 50) return ['grade' => 'C',  'remark' => 'FAIRLY GOOD','color' => '#FFC0CB', 'text' => '#000000']; // PINK
    return ['grade' => 'F', 'remark' => 'FAIL', 'color' => '#000000', 'text' => '#ffffff']; // BLACK
}

// Main Query to fetch data
$sql = "SELECT s.id, s.fullname, s.gender, s.class_name, s.stream ";
foreach ($subjects_list as $id => $name) {
    $sql .= ", MAX(CASE WHEN m.subject_id = '$id' THEN (m.ca_mark + m.monthly_mark + m.exam_mark) END) AS score_$id ";
}
$sql .= " FROM students s 
         LEFT JOIN nursery_marks m ON s.id = m.student_id 
         AND m.exam_type = '$exam_type' 
         AND m.academic_year = '$year'
         WHERE s.class_name = '$class' 
         AND s.stream = '$stream' 
         AND s.status = 'active'
         GROUP BY s.id, s.fullname, s.gender, s.class_name, s.stream";

$res = $conn->query($sql);
$data = [];
if($res) {
    while($row = $res->fetch_assoc()) {
        $row['total'] = 0;
        $row['count'] = 0;
        foreach($subjects_list as $id => $name) {
            $val = $row['score_'.$id] ?? 0;
            $row['total'] += $val;
            if($val > 0) $row['count']++;
        }
        $row['average'] = ($row['count'] > 0) ? $row['total'] / $row['count'] : 0;
        $data[] = $row;
    }
}

// Sort for Position (Rank)
usort($data, function($a, $b) { return $b['total'] <=> $a['total']; });

// Summary Counters
$summary = ['A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'F' => 0];

// Initialize subject totals and counts to avoid "Undefined array key" warning
$subject_totals = [];
$subject_counts = [];
foreach($subjects_list as $sid => $sname) {
    $subject_totals[$sid] = 0;
    $subject_counts[$sid] = 0;
}
?>

<style>
    .header-title { font-size: 18pt; font-weight: bold; text-align: center; }
    .header-sub { font-size: 12pt; text-align: center; }
    .table-main { border-collapse: collapse; width: 100%; }
    .table-main th, .table-main td { border: 1px solid #000000; padding: 4px; text-align: center; vertical-align: middle; }
    .bold { font-weight: bold; }
    .text-left { text-align: left !important; }
</style>

<!-- Title Section from Database -->
<table>
    <tr><td colspan="<?= count($subjects_list) * 2 + 8 ?>" class="header-title"><?= strtoupper($school['school_name'] ?? 'LIKINDY DIGITAL SOLUTION') ?></td></tr>
    <tr><td colspan="<?= count($subjects_list) * 2 + 8 ?>" class="header-sub"><?= $school['address'] ?? '' ?> | <?= $school['phone'] ?? '' ?></td></tr>
    <tr><td colspan="<?= count($subjects_list) * 2 + 8 ?>" class="header-sub"><i>"<?= $school['slogan'] ?? '' ?>"</i></td></tr>
    <tr><td colspan="<?= count($subjects_list) * 2 + 8 ?>" class="header-title">NURSERY PROGRESS MKEKA (<?= strtoupper($exam_type) ?>)</td></tr>
    <tr><td colspan="<?= count($subjects_list) * 2 + 8 ?>"><b>CLASS:</b> <?= $class ?> | <b>STREAM:</b> <?= $stream ?> | <b>YEAR:</b> <?= $year ?></td></tr>
</table>

<br>

<!-- Summary Section -->
<table border="1">
    <tr style="background-color: #d1d5db;">
        <th colspan="2">GRADE SUMMARY</th>
    </tr>
    <?php 
    foreach($data as $r) { 
        $g = getGradeData($r['average'])['grade'];
        if(isset($summary[$g])) $summary[$g]++; 
    }
    foreach($summary as $grade => $count): 
        $gData = getGradeData($grade == 'A+' ? 95 : ($grade == 'A' ? 85 : ($grade == 'B' ? 70 : ($grade == 'C' ? 55 : 0))));
    ?>
    <tr>
        <td style="background-color: <?= $gData['color'] ?>; color: <?= $gData['text'] ?>;"><b>Grade <?= $grade ?></b></td>
        <td><b><?= $count ?></b> Students</td>
    </tr>
    <?php endforeach; ?>
</table>

<br>

<!-- Main Results Table -->
<table class="table-main">
    <thead>
        <tr style="background-color: #1e293b; color: #ffffff;">
            <th rowspan="2">Rank</th>
            <th rowspan="2">ID</th>
            <th rowspan="2">Student Name</th>
            <th rowspan="2">Sex</th>
            <?php foreach($subjects_list as $name): ?>
                <th colspan="2"><?= strtoupper($name) ?></th>
            <?php endforeach; ?>
            <th rowspan="2">TOTAL</th>
            <th rowspan="2">AVG (%)</th>
            <th rowspan="2">GRADE</th>
            <th rowspan="2">REMARKS</th>
        </tr>
        <tr style="background-color: #e2e8f0;">
            <?php foreach($subjects_list as $name): ?>
                <th>Mrk</th><th>Grd</th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        foreach ($data as $index => $row): 
            $avgData = getGradeData($row['average']);
        ?>
        <tr>
            <td class="bold"><?= $index + 1 ?></td>
            <td><?= $row['id'] ?></td>
            <td class="text-left"><?= strtoupper($row['fullname']) ?></td>
            <td><?= $row['gender'] ?></td>
            
            <?php foreach($subjects_list as $sid => $sname): 
                $score = $row['score_'.$sid] ?? 0;
                $subject_totals[$sid] += $score;
                if($score > 0) $subject_counts[$sid]++;
                $sData = getGradeData($score);
            ?>
                <td><?= $score ?></td>
                <td style="background-color: <?= $sData['color'] ?>; color: <?= $sData['text'] ?>;" class="bold">
                    <?= $sData['grade'] ?>
                </td>
            <?php endforeach; ?>

            <td class="bold"><?= number_format($row['total'], 1) ?></td>
            <td class="bold" style="background-color: <?= $avgData['color'] ?>; color: <?= $avgData['text'] ?>;">
                <?= number_format($row['average'], 1) ?>
            </td>
            <td class="bold"><?= $avgData['grade'] ?></td>
            <td><?= $avgData['remark'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    
    <!-- Vertical Totals & Average -->
    <tfoot style="background-color: #f8fafc;">
        <tr class="bold">
            <td colspan="4">SUBJECT TOTAL MARKS</td>
            <?php foreach($subjects_list as $sid => $sname): ?>
                <td colspan="2"><?= number_format($subject_totals[$sid], 1) ?></td>
            <?php endforeach; ?>
            <td colspan="4"></td>
        </tr>
        <tr class="bold">
            <td colspan="4">SUBJECT AVERAGE (%)</td>
            <?php foreach($subjects_list as $sid => $sname): 
                $sub_avg = ($subject_counts[$sid] > 0) ? $subject_totals[$sid] / $subject_counts[$sid] : 0;
            ?>
                <td colspan="2"><?= number_format($sub_avg, 1) ?>%</td>
            <?php endforeach; ?>
            <td colspan="4"></td>
        </tr>
        <tr class="bold">
            <td colspan="4">SUBJECT GRADE</td>
            <?php foreach($subjects_list as $sid => $sname): 
                $sub_avg = ($subject_counts[$sid] > 0) ? $subject_totals[$sid] / $subject_counts[$sid] : 0;
                $sfData = getGradeData($sub_avg);
            ?>
                <td colspan="2" style="background-color: <?= $sfData['color'] ?>; color: <?= $sfData['text'] ?>;">
                    <?= $sfData['grade'] ?>
                </td>
            <?php endforeach; ?>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>

<br>
<table>
    <tr><td colspan="3"><b>Headmaster:</b> <?= $school['headmaster'] ?? '____________________' ?></td></tr>
    <tr><td colspan="3"><b>Date:</b> <?= date('d-M-Y') ?></td></tr>
    <tr><td colspan="3" style="font-size: 9pt;">Generated by Likindy Digital Solution (LDS)</td></tr>
</table>