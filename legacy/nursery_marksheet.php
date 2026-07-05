<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 1. Fetch School Information
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1"); 
$school = $school_res ? $school_res->fetch_assoc() : null;

// 2. Capture Filters
$class      = $_GET['class'] ?? '';
$stream     = $_GET['stream'] ?? '';
$exam_type  = $_GET['exam_type'] ?? '';
$year       = $_GET['year'] ?? '';

// Fetch Subjects
$subjects_list = [];
$sub_query = $conn->query("SELECT id, subject_name FROM nursery_subjects ORDER BY subject_name ASC");
while($s = $sub_query->fetch_assoc()) {
    $subjects_list[$s['id']] = $s['subject_name'];
}

// 3. New Grading System Logic
function getGradeData($mark) {
    if ($mark >= 90) return ['grade' => 'A+', 'remark' => 'EXCELLENT', 'color' => '#008000', 'text' => '#ffffff']; 
    if ($mark >= 80) return ['grade' => 'A',  'remark' => 'VERY GOOD', 'color' => '#0000FF', 'text' => '#ffffff']; 
    if ($mark >= 60) return ['grade' => 'B',  'remark' => 'GOOD',      'color' => '#FFFF00', 'text' => '#000000']; 
    if ($mark >= 50) return ['grade' => 'C',  'remark' => 'FAIRLY GOOD','color' => '#FFC0CB', 'text' => '#000000']; 
    return ['grade' => 'F', 'remark' => 'FAIL', 'color' => '#000000', 'text' => '#ffffff']; 
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

// Initialize subject totals
$subject_totals = [];
$subject_counts = [];
foreach($subjects_list as $sid => $sname) {
    $subject_totals[$sid] = 0;
    $subject_counts[$sid] = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mkeka wa Matokeo - <?= $class ?> <?= $stream ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .mkeka-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: fit-content; min-width: 100%; margin: auto; }
        
        .header-section { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .school-name { font-size: 22pt; font-weight: bold; color: #1a237e; margin: 0; }
        
        .table-main { border-collapse: collapse; width: 100%; font-size: 10pt; }
        .table-main th, .table-main td { border: 1px solid #000; padding: 6px; text-align: center; }
        .table-main th { background-color: #1e293b; color: #ffffff; }
        .bg-gray { background-color: #e2e8f0; color: #000; }
        
        .summary-table { border-collapse: collapse; margin-bottom: 20px; float: left; margin-right: 50px; }
        .summary-table td, .summary-table th { border: 1px solid #000; padding: 5px 15px; text-align: left; }
        
        .no-print { margin-bottom: 20px; text-align: right; }
        .btn { padding: 8px 16px; cursor: pointer; background: #1a237e; color: #fff; border: none; border-radius: 4px; text-decoration: none; font-weight: bold; }

        @media print {
            .no-print { display: none; }
            body { background: #fff; padding: 0; }
            .mkeka-container { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">Print Mkeka</button>
    <a href="nursery_enter_result.php" class="btn" style="background:#4b5563;">Rudi Kwenye Filter</a>
</div>

<div class="mkeka-container">
    <div class="header-section">
        <div class="school-name"><?= strtoupper($school['school_name'] ?? 'LIKINDY DIGITAL SOLUTION') ?></div>
        <div><?= $school['address'] ?? '' ?> | <?= $school['phone'] ?? '' ?></div>
        <div><i>"<?= $school['slogan'] ?? '' ?>"</i></div>
        <h2 style="margin: 10px 0;">NURSERY PROGRESS MKEKA (<?= strtoupper($exam_type) ?>)</h2>
        <div><b>CLASS:</b> <?= $class ?> | <b>STREAM:</b> <?= $stream ?> | <b>YEAR:</b> <?= $year ?></div>
    </div>

    <!-- Summary Section -->
    <table class="summary-table">
        <tr style="background-color: #d1d5db;">
            <th colspan="2">GRADE SUMMARY</th>
        </tr>
        <?php 
        foreach($data as $r) { 
            $g = getGradeData($r['average'])['grade'];
            if(isset($summary[$g])) $summary[$g]++; 
        }
        foreach($summary as $grade => $count): 
            $dummy_mark = ($grade == 'A+' ? 95 : ($grade == 'A' ? 85 : ($grade == 'B' ? 70 : ($grade == 'C' ? 55 : 0))));
            $gStyles = getGradeData($dummy_mark);
        ?>
        <tr>
            <td style="background-color: <?= $gStyles['color'] ?>; color: <?= $gStyles['text'] ?>; font-weight: bold;">Grade <?= $grade ?></td>
            <td style="font-weight: bold;"><?= $count ?> Students</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div style="clear: both;"></div>

    <!-- Main Results Table -->
    <table class="table-main">
        <thead>
            <tr>
                <th rowspan="2">Rank</th>
                <th rowspan="2">ID</th>
                <th rowspan="2" style="min-width: 200px; text-align: left;">Student Name</th>
                <th rowspan="2">Sex</th>
                <?php foreach($subjects_list as $name): ?>
                    <th colspan="2"><?= strtoupper($name) ?></th>
                <?php endforeach; ?>
                <th rowspan="2">TOTAL</th>
                <th rowspan="2">AVG (%)</th>
                <th rowspan="2">GRADE</th>
                <th rowspan="2">REMARKS</th>
            </tr>
            <tr class="bg-gray">
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
                <td style="font-weight: bold;"><?= $index + 1 ?></td>
                <td><?= $row['id'] ?></td>
                <td style="text-align: left;"><?= strtoupper($row['fullname']) ?></td>
                <td><?= $row['gender'] ?></td>
                
                <?php foreach($subjects_list as $sid => $sname): 
                    $score = $row['score_'.$sid] ?? 0;
                    $subject_totals[$sid] += $score;
                    if($score > 0) $subject_counts[$sid]++;
                    $sData = getGradeData($score);
                ?>
                    <td><?= ($score > 0) ? $score : '-' ?></td>
                    <td style="background-color: <?= $sData['color'] ?>; color: <?= $sData['text'] ?>; font-weight: bold;">
                        <?= ($score > 0) ? $sData['grade'] : '-' ?>
                    </td>
                <?php endforeach; ?>

                <td style="font-weight: bold;"><?= number_format($row['total'], 1) ?></td>
                <td style="font-weight: bold; background-color: <?= $avgData['color'] ?>; color: <?= $avgData['text'] ?>;">
                    <?= number_format($row['average'], 1) ?>
                </td>
                <td style="font-weight: bold;"><?= $avgData['grade'] ?></td>
                <td style="font-size: 8pt;"><?= $avgData['remark'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        
        <tfoot style="background-color: #f8fafc; font-weight: bold;">
            <tr>
                <td colspan="4">SUBJECT TOTAL MARKS</td>
                <?php foreach($subjects_list as $sid => $sname): ?>
                    <td colspan="2"><?= number_format($subject_totals[$sid], 1) ?></td>
                <?php endforeach; ?>
                <td colspan="4"></td>
            </tr>
            <tr>
                <td colspan="4">SUBJECT AVERAGE (%)</td>
                <?php foreach($subjects_list as $sid => $sname): 
                    $sub_avg = ($subject_counts[$sid] > 0) ? $subject_totals[$sid] / $subject_counts[$sid] : 0;
                ?>
                    <td colspan="2"><?= number_format($sub_avg, 1) ?>%</td>
                <?php endforeach; ?>
                <td colspan="4"></td>
            </tr>
            <tr>
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

    <div style="margin-top: 30px;">
        <table style="width: 100%;">
            <tr>
                <td><b>Headmaster:</b> <?= $school['headmaster'] ?? '____________________' ?></td>
                <td style="text-align: right;"><b>Date:</b> <?= date('d-M-Y') ?></td>
            </tr>
        </table>
        <p style="font-size: 9pt; text-align: center; margin-top: 20px;">Generated by Likindy Digital Solution (LDS)</p>
    </div>
</div>

</body>
</html>