<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 1. Fetch School Information from Database
$school_query = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_query->fetch_assoc();
$school_name = strtoupper($school['school_name'] ?? "LIKINDY DIGITAL SOLUTION");
$school_address = $school['address'] ?? "ZANZIBAR";

$student_data = [];
$subjects_found = [];
$search_performed = false;
$summary = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0, 'INC' => 0];

// Mode Selection Logic
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'default';

/**
 * Custom Grading Function based on user request:
 * A: 80 - 100, B: 70 - 79, C: 60 - 69, D: 50 - 59, F: Below 50
 */
function getOLevelGrade($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'F';
}

if (isset($_GET['view_broadsheet'])) {
    $class  = mysqli_real_escape_string($conn, $_GET['class']);
    $stream = mysqli_real_escape_string($conn, $_GET['stream']);
    $exam   = mysqli_real_escape_string($conn, $_GET['exam_type']);
    $year   = mysqli_real_escape_string($conn, $_GET['year']);

    // Filter to exclude students with no marks
    $sql = "SELECT m.*, s.fullname, s.gender, sub.subject_name 
            FROM olevel_marks m 
            JOIN students s ON m.student_id = s.id 
            JOIN olevel_subjects sub ON m.subject_id = sub.id
            WHERE m.class_name = '$class' 
            AND m.stream = '$stream' 
            AND m.exam_type = '$exam' 
            AND m.academic_year = '$year'
            AND (m.monthly_mark > 0 OR m.paper1_mark > 0)
            ORDER BY s.fullname ASC, sub.subject_name ASC";

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

            // Calculation Logic
            $m_base = ($row['monthly_base'] > 0) ? $row['monthly_base'] : 40;
            $e_base = ($row['exam_base'] > 0) ? $row['exam_base'] : 60;
            
            $m_display = ($row['monthly_mark'] / $m_base) * 100;
            $e_display = ($row['paper1_mark'] / $e_base) * 100;
            
            // Determine score to grade based on View Mode
            if($view_mode == 'monthly_only') {
                $final_score = $m_display;
            } elseif($view_mode == 'exam_only') {
                $final_score = $e_display;
            } else {
                $final_score = $row['total_score'];
            }

            // Apply manual grading logic
            $current_grade = getOLevelGrade($final_score);

            $student_data[$sid]['marks'][$sname] = [
                'monthly' => $m_display,
                'exam' => $e_display,
                'total' => $row['total_score'],
                'grade' => $current_grade
            ];

            // Map points for Division calculation
            $grade_pts = ['A'=>1, 'B'=>2, 'C'=>3, 'D'=>4, 'F'=>5];
            $student_data[$sid]['subject_grades'][] = $grade_pts[$current_grade] ?? 5;
            
            if (!in_array($sname, $subjects_found)) {
                $subjects_found[] = $sname;
            }
        }
    }
    $search_performed = true;
}

// Division Logic
function calculateOLevelDiv($pts_array) {
    sort($pts_array);
    $best_seven = array_slice($pts_array, 0, 7);
    $sum = array_sum($best_seven);
    
    if (count($pts_array) < 7) return ['div' => 'INC', 'pts' => $sum];
    
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
    <title>O-Level Broadsheet | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .header-box { text-align: center; margin-bottom: 30px; border-bottom: 3px double #000; padding-bottom: 15px; }
        .summary-table { width: auto; margin-bottom: 20px; }
        .summary-table th { background: #f1f5f9; text-align: center; padding: 5px 15px; }
        .broadsheet-table { font-size: 0.8rem; background: white; }
        .broadsheet-table thead { background: #064e3b; color: white; }
        .total-highlight { background: #ecfdf5; font-weight: bold; }
        input[type="radio"]:checked + label { background: currentColor !important; color: #fff !important; font-weight: bold; }
        @media print { .no-print { display: none !important; } body { background: white; } }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">CLASS</label>
                    <select name="class" class="form-select border-2">
                        <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c' ".(@$_GET['class']==$c?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold">STREAM</label>
                    <select name="stream" class="form-select border-2">
                        <?php foreach(range('A','M') as $l) echo "<option value='$l' ".(@$_GET['stream']==$l?'selected':'').">$l</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">EXAM TYPE</label>
                    <select name="exam_type" class="form-select border-2">
                        <?php foreach(['Term 1','Term 2','Special','Terminal','Mock Exam'] as $et) echo "<option value='$et' ".(@$_GET['exam_type']==$et?'selected':'').">$et</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">YEAR</label>
                    <select name="year" class="form-select border-2">
                        <?php for($y=2015; $y<=2036; $y++){ $v="$y/".($y+1); echo "<option value='$v' ".(@$_GET['year']==$v?'selected':'').">$v</option>"; } ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold d-block">DISPLAY MODE</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="view_mode" id="m_only" value="monthly_only" <?= $view_mode=='monthly_only'?'checked':'' ?>>
                        <label class="btn btn-outline-success btn-sm fw-bold" for="m_only">MONTHLY</label>
                        <input type="radio" class="btn-check" name="view_mode" id="e_only" value="exam_only" <?= $view_mode=='exam_only'?'checked':'' ?>>
                        <label class="btn btn-outline-primary btn-sm fw-bold" for="e_only">EXAM</label>
                        <input type="radio" class="btn-check" name="view_mode" id="full" value="full_mode" <?= ($view_mode=='full_mode' || $view_mode=='default')?'checked':'' ?>>
                        <label class="btn btn-outline-warning btn-sm fw-bold text-dark" for="full">FULL VIEW</label>
                    </div>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="view_broadsheet" class="btn btn-success w-100 fw-bold shadow-sm">LOAD DATA</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search_performed): ?>
    <div class="header-box">
        <h2 class="fw-bold mb-1"><?= $school_name ?></h2>
        <h5 class="text-secondary"><?= $school_address ?></h5>
        <h4 class="mt-3 fw-bold text-decoration-underline">O-LEVEL ACADEMIC BROADSHEET</h4>
    </div>

    <div class="mb-3">
        <table class="table table-sm table-bordered summary-table shadow-sm">
            <thead>
                <tr>
                    <th>DIVISION</th>
                    <?php 
                    foreach($student_data as $sd) {
                        $res = calculateOLevelDiv($sd['subject_grades']);
                        $summary[$res['div']]++;
                    }
                    foreach(['I','II','III','IV','0','INC'] as $d) echo "<th>$d</th>"; 
                    ?>
                    <th class="bg-dark text-white">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr class="text-center fw-bold">
                    <td class="text-start px-3">NO. OF STUDENTS</td>
                    <?php foreach($summary as $val) echo "<td>$val</td>"; ?>
                    <td class="bg-light"><?= count($student_data) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered broadsheet-table align-middle text-center">
            <thead>
                <tr>
                    <th rowspan="2">#</th>
                    <th rowspan="2" class="text-start">STUDENT NAME</th>
                    <th rowspan="2">SEX</th>
                    <?php foreach ($subjects_found as $sub): ?>
                        <th colspan="<?= ($view_mode=='full_mode' || $view_mode=='default') ? 4 : 2 ?>"><?= strtoupper(substr($sub,0,3)) ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2">PTS</th>
                    <th rowspan="2">DIV</th>
                </tr>
                <tr class="small">
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
                <?php $n=1; foreach ($student_data as $data): $div = calculateOLevelDiv($data['subject_grades']); ?>
                <tr>
                    <td><?= $n++ ?></td>
                    <td class="text-start fw-bold"><?= $data['fullname'] ?></td>
                    <td><?= $data['gender'] ?></td>
                    <?php foreach ($subjects_found as $sub): ?>
                        <?php if (isset($data['marks'][$sub])): $m = $data['marks'][$sub]; ?>
                            <?php if($view_mode == 'monthly_only'): ?> <td><?= number_format($m['monthly'],0) ?></td> <?php endif; ?>
                            <?php if($view_mode == 'exam_only'): ?> <td><?= number_format($m['exam'],0) ?></td> <?php endif; ?>
                            <?php if($view_mode == 'full_mode' || $view_mode == 'default'): ?>
                                <td class="small text-muted"><?= number_format($m['monthly'],0) ?></td>
                                <td class="small text-muted"><?= number_format($m['exam'],0) ?></td>
                                <td class="total-highlight"><?= number_format($m['total'],0) ?></td>
                            <?php endif; ?>
                            <td class="fw-bold"><?= $m['grade'] ?></td>
                        <?php else: ?>
                            <td colspan="<?= ($view_mode=='full_mode' || $view_mode=='default') ? 4 : 2 ?>">-</td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <td class="fw-bold"><?= $div['pts'] ?></td>
                    <td class="fw-bold bg-light"><?= $div['div'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 no-print d-flex gap-2">
        <button onclick="window.print()" class="btn btn-dark fw-bold"><i class="fas fa-print me-2"></i> PRINT BROADSHEET</button>
        <a href="export_olevel_excel.php?<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-success fw-bold"><i class="fas fa-file-excel me-2"></i> DOWNLOAD EXCEL</a>
    </div>
    <?php endif; ?>
</div>

</body>
</html>