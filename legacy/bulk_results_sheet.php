<?php
session_start();
include('db_config.php');

// 1. Get Filters from URL
$class = $_GET['class_name'] ?? '';
$comb = $_GET['combination'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class || !$comb || !$year || !$term) {
    die("Missing parameters. Please go back to the filter page.");
}

// 2. Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 3. Setup Division Counters for Summary Table
$summary = [
    'F' => ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0],
    'M' => ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0]
];

// Function to calculate Division (A-Level)
function getDivision($points) {
    if ($points >= 3 && $points <= 9) return "I";
    if ($points <= 12) return "II";
    if ($points <= 17) return "III";
    if ($points <= 19) return "IV";
    return "0";
}

$excluded = ['GENERAL STUDIES', 'GS', 'BAM', 'BASIC APPLIED MATHEMATICS', 'COMMUNICATION SKILLS'];

// 4. Fetch Students and their Marks
$students_res = $conn->query("SELECT * FROM students WHERE class_name='$class' AND combination='$comb' AND status='active' ORDER BY fullname ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Results Broad Sheet | <?= $class ?> - <?= $comb ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; background-color: #f4f4f4; padding: 20px; }
        .sheet-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .necta-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #000 !important; }
        th { background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        td { padding: 6px; }
        .summary-table { width: auto; min-width: 300px; margin: 0 auto 30px auto; }
        .text-center { text-align: center; }
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .sheet-container { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<div class="no-print text-center mb-4">
    <button onclick="window.print()" class="btn btn-primary btn-lg">Print Sheet (PDF)</button>
    <button onclick="window.history.back()" class="btn btn-secondary btn-lg">Back</button>
</div>

<div class="sheet-container">
    <div class="necta-header">
        <h2 class="m-0"><?= strtoupper($school['school_name']) ?></h2>
        <h4><?= strtoupper($class) ?> (<?= strtoupper($comb) ?>) EXAMINATION RESULTS</h4>
        <h5>TERM: <?= strtoupper($term) ?> | YEAR: <?= $year ?></h5>
    </div>

    <h5 class="fw-bold">DIVISION PERFORMANCE SUMMARY</h5>
    <table class="summary-table text-center">
        <thead>
            <tr>
                <th>SEX</th>
                <th>I</th>
                <th>II</th>
                <th>III</th>
                <th>IV</th>
                <th>0</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // We will populate this table after the main loop logic, but for PHP we process data first
            $list_data = [];
            while($st = $students_res->fetch_assoc()) {
                $st_id = $st['id'];
                $marks_q = $conn->query("SELECT m.*, s.subject_name FROM marks m JOIN subjects s ON m.subject_id = s.id WHERE m.student_id = '$st_id' AND m.year = '$year' AND m.term = '$term'");
                
                $points = 0;
                $details = [];
                while($m = $marks_q->fetch_assoc()) {
                    $details[] = strtoupper($m['subject_name']) . " - '" . $m['grade'] . "'";
                    if (!in_array(strtoupper($m['subject_name']), $excluded)) {
                        $points += $m['points'];
                    }
                }
                
                $div = getDivision($points);
                $gender = ($st['gender'] == 'Female') ? 'F' : 'M';
                $summary[$gender][$div]++;
                
                $list_data[] = [
                    'cno' => $st['student_id'],
                    'sex' => $gender,
                    'aggt' => $points,
                    'div' => $div,
                    'details' => implode(" ", $details)
                ];
            }
            
            foreach(['F', 'M'] as $s): ?>
            <tr>
                <td><?= $s ?></td>
                <td><?= $summary[$s]['I'] ?></td>
                <td><?= $summary[$s]['II'] ?></td>
                <td><?= $summary[$s]['III'] ?></td>
                <td><?= $summary[$s]['IV'] ?></td>
                <td><?= $summary[$s]['0'] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold">
                <td>T</td>
                <td><?= $summary['F']['I'] + $summary['M']['I'] ?></td>
                <td><?= $summary['F']['II'] + $summary['M']['II'] ?></td>
                <td><?= $summary['F']['III'] + $summary['M']['III'] ?></td>
                <td><?= $summary['F']['IV'] + $summary['M']['IV'] ?></td>
                <td><?= $summary['F']['0'] + $summary['M']['0'] ?></td>
            </tr>
        </tbody>
    </table>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th width="15%">CNO</th>
                <th width="5%">SEX</th>
                <th width="5%">AGGT</th>
                <th width="5%">DIV</th>
                <th>DETAILED SUBJECTS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($list_data as $row): ?>
            <tr>
                <td class="text-center"><?= $row['cno'] ?></td>
                <td class="text-center"><?= $row['sex'] ?></td>
                <td class="text-center"><?= $row['aggt'] ?></td>
                <td class="text-center fw-bold"><?= $row['div'] ?></td>
                <td><?= $row['details'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list_data)) echo "<tr><td colspan='5' class='text-center'>No records found.</td></tr>"; ?>
        </tbody>
    </table>

    <div class="row mt-5">
        <div class="col-6">
            <p>Printed on: <?= date('d-M-Y H:i:s') ?></p>
        </div>
        <div class="col-6 text-end">
            <p class="border-top d-inline-block border-dark px-5">Signature of Head of School</p>
        </div>
    </div>
</div>

</body>
</html>