<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$report_ready = false;
$students_scores = [];
$active_subjects = []; 
$grade_summary = []; 
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

function getGrade($s) {
    if ($s >= 81) return 'A';
    if ($s >= 70) return 'B';
    if ($s >= 60) return 'C';
    if ($s >= 50) return 'D';
    return 'F';
}

if (isset($_POST['view_broadsheet'])) {
    $year = $conn->real_escape_string($_POST['year']);
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $stream = $conn->real_escape_string($_POST['stream']);
    $stream_filter = (!empty($stream)) ? "AND s.stream = '$stream'" : "";

    // 1. FETCH DATA WITH STUDENT ID
    $sql = "SELECT s.id as sid, s.fullname, s.stream, m.subject_id, m.exam_type, 
                   m.monthly_mark, m.monthly_base, m.exam_mark, m.exam_base, sub.subject_name
            FROM students s
            JOIN primary_marks m ON s.id = m.student_id
            JOIN primary_subjects sub ON m.subject_id = sub.id
            WHERE s.class_name = '$class_name' $stream_filter AND m.academic_year = '$year'
            ORDER BY s.fullname ASC";
    
    $res = $conn->query($sql);
    $raw_data = [];

    while($row = $res->fetch_assoc()) {
        $sid = $row['sid'];
        $subid = $row['subject_id'];
        $type = $row['exam_type'];
        $active_subjects[$subid] = $row['subject_name'];

        $m_mark = ($row['monthly_mark'] / ($row['monthly_base'] ?: 40)) * 100;
        $e_mark = ($row['exam_mark'] / ($row['exam_base'] ?: 60)) * 100;
        $total_100 = ($m_mark * 0.4) + ($e_mark * 0.6);

        $raw_data[$sid]['info'] = ['name' => $row['fullname'], 'stream' => $row['stream'], 'id' => $sid];
        $raw_data[$sid]['marks'][$subid][$type] = $total_100;
    }

    foreach($raw_data as $sid => $data) {
        $total_annual_sum = 0;
        $subject_count = 0;
        $processed_marks = [];

        foreach($active_subjects as $subid => $name) {
            $t1 = $data['marks'][$subid]['term1'] ?? 0;
            $t2 = $data['marks'][$subid]['term2'] ?? 0;
            $annual = ($t2 <= 0) ? $t1 : ($t1 * 0.4) + ($t2 * 0.6);
            
            if($annual > 0) {
                $total_annual_sum += $annual;
                $subject_count++;
                $grade = getGrade($annual);
                $grade_summary[$subid][$grade] = ($grade_summary[$subid][$grade] ?? 0) + 1;
            }
            $processed_marks[$subid] = $annual;
        }

        $avg = ($subject_count > 0) ? ($total_annual_sum / $subject_count) : 0;
        $students_scores[$sid] = [
            'id' => $data['info']['id'],
            'name' => $data['info']['name'],
            'stream' => $data['info']['stream'],
            'marks' => $processed_marks,
            'average' => $avg
        ];
    }
    uasort($students_scores, function($a, $b) { return $b['average'] <=> $a['average']; });
    $report_ready = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Broadsheet | Mkeka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .table-container { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .table-mkeka { font-size: 11px; vertical-align: middle; border-collapse: collapse; }
        .table-mkeka th { background: #0d6efd !important; color: white; text-align: center; border: 1px solid #dee2e6; }
        .sticky-col { position: sticky; left: 0; background: white; z-index: 5; border-right: 2px solid #ddd !important; }
        .signature-box { border-bottom: 1px solid #000; width: 80%; margin: 40px auto 10px; }
        @media print { .no-print { display: none; } .table-container { box-shadow: none; } }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body bg-light">
            <h6 class="fw-bold text-primary mb-3">BROADSHEET FILTERS</h6>
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Academic Year</label>
                    <select name="year" class="form-select form-select-sm">
                        <?php 
                        for($y=2015; $y<=2035; $y++) {
                            $yr = "$y/".($y+1);
                            $sel = (isset($_POST['year']) && $_POST['year'] == $yr) ? 'selected' : '';
                            echo "<option value='$yr' $sel>$yr</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Class</label>
                    <select name="class_name" class="form-select form-select-sm">
                        <option value="KG 1">KG 1</option>
                        <option value="KG 2">KG 2</option>
                        <?php for($i=1; $i<=7; $i++) echo "<option value='Standard $i'>Standard $i</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Stream</label>
                    <select name="stream" class="form-select form-select-sm">
                        <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>$char</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="view_broadsheet" class="btn btn-primary btn-sm w-100 fw-bold">GENERATE</button>
                </div>
            </form>
        </div>
    </div>

    <?php if($report_ready): ?>
    <div class="table-container">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-uppercase m-0"><?= $school['school_name'] ?></h2>
            <h5 class="text-primary">ANNUAL PROGRESS BROADSHEET - <?= $year ?></h5>
            <div class="fw-bold">CLASS: <?= $class_name ?> | STREAM: <?= $stream ?></div>
        </div>

        <div class="d-flex justify-content-end mb-3 no-print">
            <button onclick="exportToExcel()" class="btn btn-success btn-sm me-2 fw-bold">DOWNLOAD EXCEL</button>
            <button onclick="window.print()" class="btn btn-secondary btn-sm fw-bold">PRINT MKEKA</button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm table-mkeka" id="broadsheetTable">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col">ID</th>
                        <th rowspan="2" class="sticky-col">STUDENT FULL NAME</th>
                        <?php foreach($active_subjects as $name): ?>
                            <th colspan="2"><?= strtoupper($name) ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2" class="bg-warning text-dark">AVG</th>
                        <th rowspan="2" class="bg-dark text-white">POS</th>
                    </tr>
                    <tr>
                        <?php foreach($active_subjects as $name): ?>
                            <th class="bg-light text-dark">MRK</th><th class="bg-light text-dark">GRD</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $sn = 1; foreach($students_scores as $sid => $data): ?>
                    <tr>
                        <td class="text-center sticky-col"><?= $data['id'] ?></td>
                        <td class="fw-bold sticky-col"><?= strtoupper($data['name']) ?></td>
                        <?php foreach($active_subjects as $subid => $name): 
                            $val = $data['marks'][$subid] ?? 0;
                            $grd = ($val > 0) ? getGrade($val) : '-';
                        ?>
                            <td class="text-center <?= ($val < 50 && $val > 0) ? 'text-danger fw-bold' : '' ?>">
                                <?= $val > 0 ? number_format($val, 0) : '-' ?>
                            </td>
                            <td class="text-center fw-bold text-primary"><?= $grd ?></td>
                        <?php endforeach; ?>
                        <td class="text-center fw-bold bg-light"><?= number_format($data['average'], 1) ?></td>
                        <td class="text-center fw-bold"><?= $sn++ ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach(['A', 'B', 'C', 'D', 'F'] as $g): ?>
                    <tr class="summary-row fw-bold bg-light">
                        <td colspan="2" class="text-end">TOTAL <?= $g ?>'s:</td>
                        <?php foreach($active_subjects as $subid => $name): ?>
                            <td colspan="2" class="text-center text-primary"><?= $grade_summary[$subid][$g] ?? 0 ?></td>
                        <?php endforeach; ?>
                        <td colspan="2"></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="table-info fw-bold">
                        <td colspan="2" class="text-end">TOTAL PASSED (>=50):</td>
                        <?php foreach($active_subjects as $subid => $name): 
                            $passed = 0;
                            foreach(['A', 'B', 'C', 'D'] as $g) { $passed += ($grade_summary[$subid][$g] ?? 0); }
                        ?>
                            <td colspan="2" class="text-center text-success"><?= $passed ?></td>
                        <?php endforeach; ?>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="table-danger fw-bold">
                        <td colspan="2" class="text-end">TOTAL FAILED (<50):</td>
                        <?php foreach($active_subjects as $subid => $name): 
                            $failed = $grade_summary[$subid]['F'] ?? 0;
                        ?>
                            <td colspan="2" class="text-center text-danger"><?= $failed ?></td>
                        <?php endforeach; ?>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="row mt-5 text-center fw-bold" style="font-size: 12px;">
            <div class="col-4"><div class="signature-box"></div>CLASS TEACHER</div>
            <div class="col-4"><div class="signature-box"></div>ACADEMIC MASTER</div>
            <div class="col-4"><div class="signature-box"></div>HEAD TEACHER & STAMP</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportToExcel() {
    let table = document.getElementById("broadsheetTable");
    let wb = XLSX.utils.book_new();
    let ws_data = [
        ["<?= strtoupper($school['school_name']) ?>"],
        ["ANNUAL PROGRESS BROADSHEET - <?= $year ?>"],
        ["CLASS: <?= $class_name ?> | STREAM: <?= $stream ?>"],
        [] 
    ];
    let ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.sheet_add_dom(ws, table, {origin: "A5"});
    XLSX.utils.book_append_sheet(wb, ws, "Broadsheet");
    XLSX.writeFile(wb, "Broadsheet_<?= $class_name ?>_<?= $year ?>.xlsx");
}
</script>
</body>
</html>