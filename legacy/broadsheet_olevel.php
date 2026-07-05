<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class_name = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '2024/2025';
$term = $_GET['term'] ?? 'Term 1';

if (!$class_name) {
    echo "<div class='alert alert-danger'>Please select a class.</div>";
    exit;
}

// 1. Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 2. Fetch O-Level Subjects
$subjects_query = $conn->query("SELECT id, subject_name FROM subjects WHERE level = 'o-level' ORDER BY id ASC");
$subjects = [];
while ($sub = $subjects_query->fetch_assoc()) {
    $subjects[$sub['id']] = $sub['subject_name'];
}

// 3. Process Data for BroadSheet (Pre-calculation for Summary)
$students_res = $conn->query("SELECT id, student_id, fullname, stream FROM students WHERE class_name = '$class_name' AND status = 'active' ORDER BY fullname ASC");

$broadsheet_data = [];
$summary = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
$div_summary = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0];

while ($st = $students_res->fetch_assoc()) {
    $std_id = $st['id'];
    $points_array = [];
    $total_marks = 0;
    $count_sub = 0;
    $student_marks = [];

    foreach ($subjects as $sub_id => $name) {
        $m_query = $conn->query("SELECT total_100, grade FROM marks WHERE student_id = '$std_id' AND subject_id = '$sub_id' AND year = '$year' AND term = '$term' LIMIT 1");
        $m = $m_query->fetch_assoc();
        $score = $m['total_100'] ?? '';
        $grade = $m['grade'] ?? '-';

        if ($grade != '-') {
            $total_marks += $score;
            $count_sub++;
            $summary[$grade]++;
            
            // Point conversion
            $pts = 5; // Default for F
            if($grade == 'A') $pts = 1;
            elseif($grade == 'B') $pts = 2;
            elseif($grade == 'C') $pts = 3;
            elseif($grade == 'D') $pts = 4;
            $points_array[] = $pts;
        }
        $student_marks[$sub_id] = ['score' => $score, 'grade' => $grade];
    }

    // Average, Points & Division logic
    $avg = ($count_sub > 0) ? round($total_marks / $count_sub, 1) : 0;
    sort($points_array);
    $best_seven = array_slice($points_array, 0, 7);
    $total_p = (count($best_seven) >= 7) ? array_sum($best_seven) : '-';
    
    $div = "-";
    if ($total_p !== '-') {
        if ($total_p <= 17) $div = "I";
        elseif ($total_p <= 21) $div = "II";
        elseif ($total_p <= 25) $div = "III";
        elseif ($total_p <= 33) $div = "IV";
        else $div = "0";
        $div_summary[$div]++;
    }

    $broadsheet_data[] = [
        'info' => $st,
        'marks' => $student_marks,
        'avg' => $avg,
        'points' => $total_p,
        'division' => $div,
        'total_score' => $total_marks // used for ranking
    ];
}

// Sort by total score for ranking
usort($broadsheet_data, function($a, $b) {
    return $b['total_score'] <=> $a['total_score'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Broadsheet - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; font-size: 10px; color: #333; }
        .broadsheet-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin: 10px; }
        .table-broadsheet th, .table-broadsheet td { border: 1px solid #000 !important; vertical-align: middle; padding: 4px !important; }
        .school-title { color: #1a237e; font-size: 24px; font-weight: 800; }
        
        /* Grade Colors */
        .g-A { background-color: #28a745 !important; color: #fff; font-weight: bold; }
        .g-B { background-color: #d1fae5 !important; color: #065f46; font-weight: bold; }
        .g-C { background-color: #fef3c7 !important; color: #92400e; font-weight: bold; }
        .g-D { background-color: #f3f4f6 !important; color: #374151; }
        .g-F { background-color: #fee2e2 !important; color: #b91c1c; font-weight: bold; }
        
        @media print { 
            .no-print { display: none; } 
            body { background: white; padding: 0; }
            .broadsheet-card { box-shadow: none; border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>

<div class="container-fluid mt-3">
    <div class="broadsheet-card">
        
        <div class="text-center mb-4">
            <h1 class="school-title mb-1"><?= strtoupper($school['school_name']) ?></h1>
            <h5 class="text-secondary mb-0">ACADEMIC BROADSHEET - <?= strtoupper($class_name) ?></h5>
            <p class="fw-bold"><?= strtoupper($term) ?> | ACADEMIC YEAR: <?= $year ?></p>
            
            <div class="no-print mt-2">
                <button onclick="window.print()" class="btn btn-dark btn-sm shadow-sm px-4"><i class="fas fa-print me-1"></i> Print</button>
                <a href="export_excel.php?class_name=<?= $class_name ?>&year=<?= $year ?>&term=<?= $term ?>" class="btn btn-success btn-sm shadow-sm px-4">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </a>
            </div>
        </div>

        <div class="row mb-4 justify-content-center">
            <div class="col-md-5">
                <table class="table table-bordered table-sm text-center">
                    <tr class="table-dark">
                        <th colspan="6" style="font-size: 12px;">GRADE DISTRIBUTION SUMMARY</th>
                    </tr>
                    <tr>
                        <th class="g-A">A</th><th class="g-B">B</th><th class="g-C">C</th><th class="g-D">D</th><th class="g-F">F</th><th class="bg-light">TOTAL</th>
                    </tr>
                    <tr class="fw-bold">
                        <td><?= $summary['A'] ?></td><td><?= $summary['B'] ?></td><td><?= $summary['C'] ?></td><td><?= $summary['D'] ?></td><td><?= $summary['F'] ?></td>
                        <td><?= array_sum($summary) ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-5">
                <table class="table table-bordered table-sm text-center">
                    <tr class="table-dark">
                        <th colspan="6" style="font-size: 12px;">DIVISION PERFORMANCE SUMMARY</th>
                    </tr>
                    <tr>
                        <th>DIV I</th><th>DIV II</th><th>DIV III</th><th>DIV IV</th><th>DIV 0</th><th class="bg-light">TOTAL</th>
                    </tr>
                    <tr class="fw-bold">
                        <td><?= $div_summary['I'] ?></td><td><?= $div_summary['II'] ?></td><td><?= $div_summary['III'] ?></td><td><?= $div_summary['IV'] ?></td><td><?= $div_summary['0'] ?></td>
                        <td><?= array_sum($div_summary) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-broadsheet text-center">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2">RNK</th>
                        <th rowspan="2">ID</th>
                        <th rowspan="2" class="text-start">FULL NAME</th>
                        <th rowspan="2">STR</th>
                        <?php foreach ($subjects as $name): ?>
                            <th colspan="2"><?= strtoupper(substr($name, 0, 3)) ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2">AVG</th>
                        <th rowspan="2">PTS</th>
                        <th rowspan="2">DIV</th>
                    </tr>
                    <tr class="table-secondary text-dark">
                        <?php foreach ($subjects as $name): ?>
                            <th>MK</th><th>GD</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($broadsheet_data as $data): 
                    ?>
                    <tr>
                        <td class="fw-bold"><?= $rank++ ?></td>
                        <td class="text-muted"><?= $data['info']['student_id'] ?></td>
                        <td class="text-start fw-bold"><?= strtoupper($data['info']['fullname']) ?></td>
                        <td><?= $data['info']['stream'] ?></td>
                        
                        <?php foreach ($subjects as $sub_id => $name): ?>
                            <td><?= $data['marks'][$sub_id]['score'] ?></td>
                            <td class="g-<?= $data['marks'][$sub_id]['grade'] ?>"><?= $data['marks'][$sub_id]['grade'] ?></td>
                        <?php endforeach; ?>

                        <td class="fw-bold bg-light"><?= $data['avg'] ?>%</td>
                        <td class="fw-bold bg-light"><?= $data['points'] ?></td>
                        <td class="fw-bold <?= ($data['division'] == 'I' ? 'text-success' : ($data['division'] == '0' ? 'text-danger' : 'text-primary')) ?>">
                            <?= $data['division'] ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-5 text-center fw-bold d-none d-print-flex">
            <div class="col-4">
                <p>_______________________<br>Class Teacher</p>
            </div>
            <div class="col-4">
                <p>_______________________<br>Academic Master</p>
            </div>
            <div class="col-4">
                <p>_______________________<br>Head of School</p>
            </div>
        </div>

    </div>
</div>

</body>
</html>