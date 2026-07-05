<?php
session_start();
include('db_config.php');

// 1. POKEA FILTERS
$class_name = $_GET['class_name'] ?? '';
$stream = $_GET['stream'] ?? '';
$term = $_GET['term'] ?? '';
$year = $_GET['year'] ?? '';

if (empty($class_name) || empty($term)) {
    die("Missing required filters.");
}

// 2. EXCEL EXPORT LOGIC
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Broadsheet_{$class_name}_{$term}.xls");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// 3. FUNCTIONS ZA GRADING NA DIVISION
function getGrade($mark) {
    if ($mark >= 75) return ['A', 'table-success', 1, '#d1e7dd'];
    if ($mark >= 65) return ['B', 'table-info', 2, '#cff4fc'];
    if ($mark >= 45) return ['C', 'table-warning', 3, '#fff3cd'];
    if ($mark >= 30) return ['D', 'table-light', 4, '#f8f9fa'];
    return ['F', 'table-danger', 5, '#f8d7da'];
}

function calculateDivision($points, $sub_count) {
    if ($sub_count < 7) return "INC";
    if ($points >= 7 && $points <= 17) return "I";
    if ($points >= 18 && $points <= 21) return "II";
    if ($points >= 22 && $points <= 25) return "III";
    if ($points >= 26 && $points <= 33) return "IV";
    return "0";
}

// 4. FETCH SUBJECTS
$subjects_res = $conn->query("SELECT s.id, s.subject_name FROM subjects s 
                             JOIN subject_assignments sa ON s.id = sa.subject_id 
                             WHERE sa.class_name = '$class_name' GROUP BY s.id");
$subjects = [];
while($row = $subjects_res->fetch_assoc()) { $subjects[] = $row; }

// 5. FETCH STUDENTS & CALCULATE
$students_query = $conn->query("SELECT id, fullname, gender FROM students 
                               WHERE class_name = '$class_name' AND stream = '$stream' AND status != 'deleted' 
                               ORDER BY fullname ASC");

$summary_data = ['F' => ['I'=>0,'II'=>0,'III'=>0,'IV'=>0,'0'=>0, 'INC'=>0], 
                 'M' => ['I'=>0,'II'=>0,'III'=>0,'IV'=>0,'0'=>0, 'INC'=>0]];

$report_rows = [];
while($st = $students_query->fetch_assoc()) {
    $st_id = $st['id'];
    $st_marks = [];
    $all_points = [];
    $total_score = 0;
    $subjects_sat = 0;

    foreach($subjects as $sub) {
        $sub_id = $sub['id'];
        $m_query = $conn->query("SELECT total_100 FROM marks WHERE student_id = '$st_id' 
                                AND subject_id = '$sub_id' AND term = '$term' AND year = '$year' LIMIT 1");
        $score = ($m_query && $m_query->num_rows > 0) ? $m_query->fetch_assoc()['total_100'] : null;
        
        if($score !== null) {
            $g_info = getGrade($score);
            $st_marks[$sub_id] = ['score' => $score, 'grade' => $g_info[0], 'class' => $g_info[1], 'color' => $g_info[3]];
            $all_points[] = $g_info[2];
            $total_score += $score;
            $subjects_sat++;
        } else {
            $st_marks[$sub_id] = ['score' => '-', 'grade' => '-', 'class' => '', 'color' => 'transparent'];
        }
    }

    sort($all_points);
    $best_7 = array_slice($all_points, 0, 7);
    $points_sum = array_sum($best_7);
    $division = calculateDivision($points_sum, count($all_points));
    $average = ($subjects_sat > 0) ? ($total_score / $subjects_sat) : 0;

    // Update Summary Logic
    $gender_key = strtoupper(substr($st['gender'] ?? 'M', 0, 1));
    if(isset($summary_data[$gender_key][$division])) {
        $summary_data[$gender_key][$division]++;
    }

    $report_rows[] = [
        'info' => $st,
        'marks' => $st_marks,
        'total' => $total_score,
        'avg' => number_format($average, 1),
        'points' => $points_sum,
        'div' => $division
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Results Broad Sheet - <?= htmlspecialchars($class_name) ?></title>
    <?php if (!isset($_GET['export'])): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php endif; ?>
    <style>
        .table-main th { background: #1e293b !important; color: white; border: 1px solid #000; text-align: center; }
        .table-main td { border: 1px solid #000; text-align: center; vertical-align: middle; }
        .no-print { margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light p-4">

<div class="container-fluid bg-white p-4 shadow-sm rounded">
    <?php if (!isset($_GET['export'])): ?>
    <div class="d-flex justify-content-between align-items-center no-print">
        <h2 class="fw-bold text-primary">MKEKA WA MATOKEO (BROAD SHEET)</h2>
        <div>
            <a href="?<?= $_SERVER['QUERY_STRING'] ?>&export=excel" class="btn btn-success me-2">
                <i class="fas fa-file-excel"></i> Open in Excel
            </a>
            <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print"></i> Print PDF</button>
        </div>
    </div>
    <hr>
    <?php endif; ?>

    <h4 class="fw-bold">DIVISION PERFORMANCE SUMMARY</h4>
    <table border="1" class="table table-bordered text-center mb-5" style="width: 400px; border-collapse: collapse;">
        <thead class="<?= !isset($_GET['export']) ? 'table-dark' : '' ?>">
            <tr style="background-color: #1e293b; color: white;">
                <th>SEX</th><th>I</th><th>II</th><th>III</th><th>IV</th><th>0</th><th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grand_total = 0;
            $col_totals = ['I'=>0,'II'=>0,'III'=>0,'IV'=>0,'0'=>0];
            foreach(['F', 'M'] as $gender): 
                $row_total = $summary_data[$gender]['I'] + $summary_data[$gender]['II'] + $summary_data[$gender]['III'] + $summary_data[$gender]['IV'] + $summary_data[$gender]['0'];
                $grand_total += $row_total;
            ?>
            <tr>
                <td style="font-weight: bold;"><?= $gender ?></td>
                <?php foreach(['I','II','III','IV','0'] as $d): 
                    $col_totals[$d] += $summary_data[$gender][$d];
                ?>
                    <td><?= $summary_data[$gender][$d] ?></td>
                <?php endforeach; ?>
                <td style="background-color: #f1f5f9; font-weight: bold;"><?= $row_total ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background-color: #e2e8f0; font-weight: bold;">
                <td>TOTAL</td>
                <?php foreach($col_totals as $t): ?><td><?= $t ?></td><?php endforeach; ?>
                <td><?= $grand_total ?></td>
            </tr>
        </tbody>
    </table>

    <div class="table-responsive">
        <table border="1" class="table table-sm table-main" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr style="background-color: #1e293b; color: white;">
                    <th rowspan="2">ID</th>
                    <th rowspan="2">STUDENT NAME</th>
                    <th rowspan="2">SEX</th>
                    <?php foreach($subjects as $sub): ?>
                        <th colspan="2"><?= strtoupper($sub['subject_name']) ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2">TOTAL</th>
                    <th rowspan="2">AVG %</th>
                    <th rowspan="2">PTS</th>
                    <th rowspan="2">DIV</th>
                </tr>
                <tr style="background-color: #334155; color: white;">
                    <?php foreach($subjects as $sub): ?>
                        <th>Mks</th><th>Grd</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($report_rows as $row): ?>
                <tr>
                    <td><?= $row['info']['id'] ?></td>
                    <td style="text-align: left; padding-left: 5px; font-weight: bold;"><?= strtoupper($row['info']['fullname']) ?></td>
                    <td><?= strtoupper(substr($row['info']['gender'], 0, 1)) ?></td>
                    <?php foreach($subjects as $sub): 
                        $m_data = $row['marks'][$sub['id']];
                    ?>
                        <td><?= $m_data['score'] ?></td>
                        <td style="background-color: <?= $m_data['color'] ?>; font-weight: bold;"><?= $m_data['grade'] ?></td>
                    <?php endforeach; ?>
                    <td style="background-color: #f8fafc; font-weight: bold;"><?= $row['total'] ?></td>
                    <td><?= $row['avg'] ?></td>
                    <td style="font-weight: bold;"><?= $row['points'] ?></td>
                    <td style="background-color: #1e293b; color: white; font-weight: bold;"><?= $row['div'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>