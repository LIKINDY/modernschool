<?php
session_start();
include('db_config.php');

$student_data = [];
$subjects_found = [];
$search_performed = false;

// Mode Selection Logic
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'default';

if (isset($_GET['filter'])) {
    $subject_filter = mysqli_real_escape_string($conn, $_GET['subject']);
    $class      = mysqli_real_escape_string($conn, $_GET['class']);
    $stream     = mysqli_real_escape_string($conn, $_GET['stream']);
    $exam_type  = mysqli_real_escape_string($conn, $_GET['exam_type']);
    $year       = mysqli_real_escape_string($conn, $_GET['year']);

    $sql = "SELECT m.*, s.fullname, s.gender, sub.subject_name 
            FROM olevel_marks m 
            JOIN students s ON m.student_id = s.id 
            JOIN olevel_subjects sub ON m.subject_id = sub.id
            WHERE m.class_name = '$class' 
            AND m.stream = '$stream' 
            AND m.exam_type = '$exam_type' 
            AND m.academic_year = '$year'
            AND (m.monthly_mark > 0 OR m.paper1_mark > 0)"; 

    if ($subject_filter !== 'all') {
        $sql .= " AND m.subject_id = '$subject_filter'";
    }

    $sql .= " ORDER BY s.fullname ASC, sub.subject_name ASC";
    $query = $conn->query($sql);

    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $sid = $row['student_id'];
            $sname = $row['subject_name'];
            
            if (!isset($student_data[$sid])) {
                $student_data[$sid] = [
                    'fullname' => $row['fullname'],
                    'gender' => $row['gender'],
                    'marks' => [],
                    'subject_grades' => []
                ];
            }

            $m_base = ($row['monthly_base'] > 0) ? $row['monthly_base'] : 40;
            $e_base = ($row['exam_base'] > 0) ? $row['exam_base'] : 60;
            
            $m_display = ($row['monthly_mark'] / $m_base) * 100;
            $e_display = ($row['paper1_mark'] / $e_base) * 100;
            
            // Logic for Display and Grade calculation based on Mode
            $current_grade = $row['grade'];
            $final_total = $row['total_score'];

            if ($view_mode == 'monthly_only') {
                $final_total = $m_display; // Grade based on monthly 100%
            } elseif ($view_mode == 'exam_only') {
                $final_total = $e_display; // Grade based on exam 100%
            }

            // Recalculate grade if not in default/full mode for accuracy
            if ($view_mode != 'default' && $view_mode != 'full_mode') {
                if ($final_total >= 80) $current_grade = 'A';
                elseif ($final_total >= 70) $current_grade = 'B';
                elseif ($final_total >= 60) $current_grade = 'C';
                elseif ($final_total >= 50) $current_grade = 'D';
                else $current_grade = 'F';
            }

            $student_data[$sid]['marks'][$sname] = [
                'monthly' => $m_display,
                'exam' => $e_display,
                'total' => $final_total,
                'grade' => $current_grade
            ];

            $grade_points = ['A'=>1, 'B'=>2, 'C'=>3, 'D'=>4, 'F'=>5];
            $student_data[$sid]['subject_grades'][] = $grade_points[$current_grade] ?? 5;
            
            if (!in_array($sname, $subjects_found)) {
                $subjects_found[] = $sname;
            }
        }
    }
    $search_performed = true;
}

function calculateDivision($points_array) {
    sort($points_array);
    $best_seven = array_slice($points_array, 0, 7);
    $sum = array_sum($best_seven);
    $count = count($points_array);
    if ($count < 7) return ['div' => 'INC', 'pts' => $sum];
    if ($sum <= 17) return ['div' => 'I', 'pts' => $sum];
    if ($sum <= 21) return ['div' => 'II', 'pts' => $sum];
    if ($sum <= 25) return ['div' => 'III', 'pts' => $sum];
    if ($sum <= 33) return ['div' => 'IV', 'pts' => $sum];
    return ['div' => '0', 'pts' => $sum];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>O-Level Review | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #059669; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .table-primary { background: var(--primary) !important; color: white; }
        .grade-A { color: #059669; font-weight: bold; }
        .grade-B { color: #10b981; font-weight: bold; }
        .grade-C { color: #f59e0b; font-weight: bold; }
        .grade-D { color: #64748b; font-weight: bold; }
        .grade-F { color: #ef4444; font-weight: bold; }
        .div-cell { background: #f0fdf4; font-weight: bold; color: #065f46; }
        
        /* High Visibility Switches */
        .mode-indicator { padding: 10px; border-radius: 8px; border: 2px solid #ddd; transition: 0.3s; cursor: pointer; }
        .mode-monthly { border-color: #10b981; color: #065f46; }
        .mode-exam { border-color: #3b82f6; color: #1e3a8a; }
        .mode-full { border-color: #f59e0b; color: #7c2d12; }
        input[type="radio"]:checked + label { background: currentColor; color: #fff !important; font-weight: bold; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-chart-line text-success me-2"></i> O-Level Review Center</h3>
        <a href="olevel_result.php" class="btn btn-outline-success rounded-pill fw-bold">ENTRY PAGE</a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3" id="filterForm">
                <div class="col-md-2">
                    <label class="form-label fw-bold small">SUBJECT</label>
                    <select name="subject" class="form-select">
                        <option value="all">-- All --</option>
                        <?php 
                        $subs = $conn->query("SELECT * FROM olevel_subjects ORDER BY subject_name ASC");
                        while($s = $subs->fetch_assoc()) echo "<option value='{$s['id']}' ".(@$_GET['subject']==$s['id']?'selected':'').">{$s['subject_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">CLASS</label>
                    <select name="class" class="form-select">
                        <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c' ".(@$_GET['class']==$c?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold small">STREAM</label>
                    <select name="stream" class="form-select">
                        <?php foreach(range('A','M') as $l) echo "<option value='$l' ".(@$_GET['stream']==$l?'selected':'').">$l</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">EXAM TYPE</label>
                    <select name="exam_type" class="form-select">
                        <?php foreach(['Term 1','Term 2','Special','Terminal','Mock Exam'] as $et) echo "<option value='$et' ".(@$_GET['exam_type']==$et?'selected':'').">$et</option>"; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold small">YEAR</label>
                    <select name="year" class="form-select">
                        <?php for($y=2015;$y<=2036;$y++){ $v="$y/".($y+1); echo "<option value='$v' ".(@$_GET['year']==$v?'selected':'').">$v</option>"; } ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small d-block">DISPLAY MODE INDICATOR</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="view_mode" id="m_only" value="monthly_only" <?= $view_mode=='monthly_only'?'checked':'' ?>>
                        <label class="btn btn-outline-success fw-bold" for="m_only">MONTHLY</label>

                        <input type="radio" class="btn-check" name="view_mode" id="e_only" value="exam_only" <?= $view_mode=='exam_only'?'checked':'' ?>>
                        <label class="btn btn-outline-primary fw-bold" for="e_only">EXAM</label>

                        <input type="radio" class="btn-check" name="view_mode" id="full" value="full_mode" <?= ($view_mode=='full_mode' || $view_mode=='default')?'checked':'' ?>>
                        <label class="btn btn-outline-warning fw-bold text-dark" for="full">FULL VIEW</label>
                    </div>
                </div>

                <div class="col-md-12 text-end">
                    <button type="submit" name="filter" class="btn btn-success px-5 fw-bold shadow-sm">VIEW RESULTS</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search_performed): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Result Table: <span class="text-uppercase text-primary"><?= str_replace('_', ' ', $view_mode) ?></span></h5>
            <a href="export_olevel_excel.php?<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-sm btn-success fw-bold">
                <i class="fas fa-file-excel me-2"></i> EXCEL EXPORT
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center mb-0">
                <thead class="table-primary small">
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2" class="text-start">Student Name</th>
                        <?php foreach ($subjects_found as $sub): ?>
                            <th colspan="<?= ($view_mode=='full_mode' || $view_mode=='default') ? 3 : 2 ?>"><?= $sub ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2">Points</th>
                        <th rowspan="2">Division</th>
                    </tr>
                    <tr>
                        <?php foreach ($subjects_found as $sub): ?>
                            <?php if($view_mode == 'monthly_only'): ?> <th>M(100)</th> <?php endif; ?>
                            <?php if($view_mode == 'exam_only'): ?> <th>E(100)</th> <?php endif; ?>
                            <?php if($view_mode == 'full_mode' || $view_mode == 'default'): ?> 
                                <th>M</th> <th>E</th> <th>TOT</th> 
                            <?php endif; ?>
                            <th>GR</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $n = 1;
                    foreach ($student_data as $sid => $data): 
                        $div_info = calculateDivision($data['subject_grades']);
                    ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td class="text-start fw-bold"><?= $data['fullname'] ?></td>
                        <?php foreach ($subjects_found as $sub): ?>
                            <?php if (isset($data['marks'][$sub])): 
                                $m = $data['marks'][$sub];
                            ?>
                                <?php if($view_mode == 'monthly_only'): ?> <td><?= number_format($m['monthly'], 1) ?></td> <?php endif; ?>
                                <?php if($view_mode == 'exam_only'): ?> <td><?= number_format($m['exam'], 1) ?></td> <?php endif; ?>
                                <?php if($view_mode == 'full_mode' || $view_mode == 'default'): ?>
                                    <td class="small text-muted"><?= number_format($m['monthly'], 0) ?></td>
                                    <td class="small text-muted"><?= number_format($m['exam'], 0) ?></td>
                                    <td class="fw-bold"><?= number_format($m['total'], 1) ?></td>
                                <?php endif; ?>
                                <td class="grade-<?= $m['grade'] ?>"><?= $m['grade'] ?></td>
                            <?php else: ?>
                                <td colspan="<?= ($view_mode=='full_mode' || $view_mode=='default') ? 4 : 2 ?>">-</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <td class="fw-bold"><?= $div_info['pts'] ?></td>
                        <td class="div-cell"><?= $div_info['div'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>