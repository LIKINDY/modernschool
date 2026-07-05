<?php
session_start();
include('db_config.php');

// 1. Pokea Vigezo
$class = $_GET['class_name'] ?? '';
$combination = $_GET['combination'] ?? '';
$term = $_GET['term'] ?? '';
$year = $_GET['year'] ?? '';

if (!$class || !$combination || !$term || !$year) {
    die("Error: Missing parameters for export!");
}

// 2. Headers za Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Advanced_Professional_Broadsheet_" . $combination . "_" . $year . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 3. Functions za Logic
function getDivision($points, $is_complete) {
    if (!$is_complete) return "I-COM";
    if ($points >= 3 && $points <= 9) return "I";
    if ($points <= 12) return "II";
    if ($points <= 17) return "III";
    if ($points <= 19) return "IV";
    return "0";
}

function getGradeColor($grade) {
    switch(strtoupper($grade)) {
        case 'A': return '#198754'; // Dark Green
        case 'B': return '#0d6efd'; // Blue
        case 'C': return '#0dcaf0'; // Cyan
        case 'D': return '#ffc107'; // Yellow
        case 'E': return '#fd7e14'; // Orange
        case 'S': return '#6f42c1'; // Purple
        case 'F': return '#dc3545'; // Red
        default: return '#ffffff';
    }
}

// 4. Pata Taarifa za Shule
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// Pata Masomo 3 pekee
$subject_list = [];
$sub_query = "SELECT DISTINCT s.id, s.subject_name FROM subjects s 
              JOIN marks m ON s.id = m.subject_id 
              JOIN students st ON m.student_id = st.id 
              WHERE st.combination = '$combination' 
              AND s.subject_name NOT IN ('GENERAL STUDIES', 'GS', 'BAM')
              ORDER BY s.subject_name ASC LIMIT 3";
$sub_res = $conn->query($sub_query);
while($s_row = $sub_res->fetch_assoc()) $subject_list[] = $s_row;

// 5. Maandalizi ya Data ya Wanafunzi
$students_res = $conn->query("SELECT id, student_id, fullname FROM students WHERE class_name = '$class' AND combination = '$combination' ORDER BY fullname ASC");

$summary = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0, 'I-COM' => 0];
$rows = [];

while($st = $students_res->fetch_assoc()) {
    $pts = 0; $count = 0;
    foreach($subject_list as $sub) {
        $sid = $sub['id']; $stid = $st['id'];
        $m = $conn->query("SELECT total_100, points, grade FROM marks WHERE student_id = '$stid' AND subject_id = '$sid' AND term = '$term' AND year = '$year'")->fetch_assoc();
        $st['marks'][$sid] = $m;
        if($m) { $pts += $m['points'] ?? 0; $count++; }
    }
    $is_complete = ($count == 3);
    $div = getDivision($pts, $is_complete);
    $summary[$div]++;
    
    $st['final_pts'] = $is_complete ? $pts : '-';
    $st['final_div'] = $div;
    $rows[] = $st;
}

$total_subjects = count($subject_list);
$colspan_total = 5 + ($total_subjects * 2) + 2; 
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-type" content="text/html;charset=utf-8" />
    <style>
        .school-name { font-size: 22pt; font-weight: bold; text-align: center; color: #002060; }
        .main-title { font-size: 14pt; font-weight: bold; text-align: center; background-color: #f2f2f2; }
        .hdr-main { background-color: #002060; color: #ffffff; font-weight: bold; text-align: center; border: 1pt solid #000; }
        .hdr-sub { background-color: #d9e1f2; color: #000; font-weight: bold; text-align: center; border: 1pt solid #000; }
        .cell { border: 0.5pt solid #000; vertical-align: middle; }
        .sum-title { background-color: #202b38; color: #ffffff; font-weight: bold; font-size: 12pt; }
        .sum-cell { border: 1pt solid #000; font-weight: bold; font-size: 11pt; }
    </style>
</head>
<body>
    <table>
        <tr>
            <th colspan="<?= $colspan_total ?>" class="school-name"><?= strtoupper($school['school_name']) ?></th>
        </tr>
        <tr>
            <th colspan="<?= $colspan_total ?>" class="main-title">ADVANCED LEVEL ACADEMIC BROADSHEET - <?= $year ?></th>
        </tr>
        <tr>
            <th colspan="<?= $colspan_total ?>" style="text-align: center; font-weight: bold;">
                TERM: <?= strtoupper($term) ?> | CLASS: <?= $class ?> | COMBINATION: <?= $combination ?>
            </th>
        </tr>

        <tr><td colspan="<?= $colspan_total ?>"></td></tr>

        <tr>
            <td colspan="2"></td>
            <td colspan="6" class="sum-title" align="center">PERFORMANCE SUMMARY</td>
        </tr>
        <tr align="center">
            <td colspan="2" align="right"><b>DIVISION:</b></td>
            <td class="sum-cell" style="background-color: #198754; color: white;">I</td>
            <td class="sum-cell" style="background-color: #0d6efd; color: white;">II</td>
            <td class="sum-cell" style="background-color: #0dcaf0; color: white;">III</td>
            <td class="sum-cell" style="background-color: #ffc107; color: black;">IV</td>
            <td class="sum-cell" style="background-color: #dc3545; color: white;">0</td>
            <td class="sum-cell" style="background-color: #6f42c1; color: white;">I-COM</td>
        </tr>
        <tr align="center">
            <td colspan="2" align="right"><b>TOTAL STUDENTS: <?= count($rows) ?></b></td>
            <td class="sum-cell"><?= $summary['I'] ?></td>
            <td class="sum-cell"><?= $summary['II'] ?></td>
            <td class="sum-cell"><?= $summary['III'] ?></td>
            <td class="sum-cell"><?= $summary['IV'] ?></td>
            <td class="sum-cell"><?= $summary['0'] ?></td>
            <td class="sum-cell"><?= $summary['I-COM'] ?></td>
        </tr>

        <tr><td colspan="<?= $colspan_total ?>"></td></tr>

        <thead>
            <tr>
                <th rowspan="2" class="hdr-main" width="50">S/N</th>
                <th rowspan="2" class="hdr-main" width="150">STUDENT ID</th>
                <th rowspan="2" class="hdr-main" width="350">FULL NAME</th>
                <th rowspan="2" class="hdr-main" width="100">CLASS</th>
                <th rowspan="2" class="hdr-main" width="100">COMB</th>
                <?php foreach($subject_list as $sub): ?>
                    <th colspan="2" class="hdr-main" width="200"><?= strtoupper($sub['subject_name']) ?></th>
                <?php endforeach; ?>
                <th rowspan="2" class="hdr-main" width="80" style="background-color: #333;">POINTS</th>
                <th rowspan="2" class="hdr-main" width="80" style="background-color: #333;">DIV</th>
            </tr>
            <tr>
                <?php foreach($subject_list as $sub): ?>
                    <th class="hdr-sub">MARKS</th>
                    <th class="hdr-sub">GRADE</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $n=1; foreach($rows as $r): ?>
            <tr>
                <td class="cell" align="center"><?= $n++ ?></td>
                <td class="cell" align="center"><?= $r['student_id'] ?></td>
                <td class="cell" style="padding-left: 5px;"><?= strtoupper($r['fullname']) ?></td>
                <td class="cell" align="center"><?= $class ?></td>
                <td class="cell" align="center"><?= $combination ?></td>
                
                <?php foreach($subject_list as $sub): 
                    $m = $r['marks'][$sub['id']] ?? null;
                    $score = $m['total_100'] ?? '-';
                    $grade = $m['grade'] ?? '-';
                    $g_color = getGradeColor($grade);
                ?>
                    <td class="cell" align="center" style="font-weight: bold;"><?= $score ?></td>
                    <td class="cell" align="center" style="background-color: <?= $g_color ?>; color: #ffffff; font-weight: bold;">
                        <?= $grade ?>
                    </td>
                <?php endforeach; ?>

                <td class="cell" align="center" style="font-weight: bold; background-color: #f2f2f2;"><?= $r['final_pts'] ?></td>
                <td class="cell" align="center" style="font-weight: bold; background-color: #e2efda;"><?= $r['final_div'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>