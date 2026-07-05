<?php
session_start();
include('db_config.php');

// Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class = isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : 'Form 1';
$stream = isset($_GET['stream']) ? mysqli_real_escape_string($conn, $_GET['stream']) : 'A';
$exam = isset($_GET['exam_type']) ? mysqli_real_escape_string($conn, $_GET['exam_type']) : 'Terminal';
$year = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : date('Y')."/".(date('Y')+1);

// 1. Fetch only registered subjects that contain saved marks for the selected filter criteria
$subjects = [];
$sub_query = "SELECT DISTINCT sub.id, sub.subject_name 
              FROM olevel_subjects sub
              INNER JOIN olevel_marks m ON sub.id = m.subject_id
              INNER JOIN students st ON m.student_id = st.id
              WHERE st.class_name='$class' 
                AND st.stream='$stream' 
                AND m.exam_type='$exam' 
                AND m.academic_year='$year'
              ORDER BY sub.subject_name ASC";

$sub_res = $conn->query($sub_query);
if ($sub_res) {
    while($s = $sub_res->fetch_assoc()) {
        $subjects[$s['id']] = $s['subject_name'];
    }
}

// Compute Complete Performance Aggregations
$student_matrix = [];
$stud_res = $conn->query("SELECT id, fullname, gender FROM students WHERE class_name='$class' AND stream='$stream' ORDER BY fullname ASC");

if ($stud_res) {
    while($st = $stud_res->fetch_assoc()) {
        $sid = $st['id'];
        $student_matrix[$sid] = [
            'name' => $st['fullname'],
            'gender' => $st['gender'],
            'marks' => [],
            'total' => 0,
            'count' => 0,
            'avg' => 0,
            'grade' => 'F',
            'rank' => 0
        ];
        
        // Fetch marks matching calculation engine matrix rules
        foreach($subjects as $sub_id => $sub_name) {
            $m_res = $conn->query("SELECT monthly_mark, m2_mark, paper1_mark, paper2_mark FROM olevel_marks 
                                   WHERE student_id='$sid' AND subject_id='$sub_id' AND exam_type='$exam' AND academic_year='$year' LIMIT 1");
            if($m_res && $m_res->num_rows > 0) {
                $m = $m_res->fetch_assoc();
                $final = 0;
                
                if ($exam === 'Terminal' || $exam === 'Annual') {
                    $final = $m['paper1_mark'];
                } elseif(strpos($exam, 'Term') !== false) {
                    $final = $m['monthly_mark'] + $m['paper1_mark'];
                } elseif($exam === 'Special') {
                    $final = $m['monthly_mark'] + $m['m2_mark'] + $m['paper1_mark'];
                } elseif($exam === 'Mock') {
                    $s_lower = strtolower($sub_name);
                    if(strpos($s_lower,'bio')!==false || strpos($s_lower,'phy')!==false || strpos($s_lower,'chem')!==false) {
                        $final = ($m['paper1_mark'] + $m['paper2_mark']) / 1.5;
                    } else {
                        $final = $m['paper1_mark'];
                    }
                }
                if($final > 100) $final = 100;
                
                $student_matrix[$sid]['marks'][$sub_id] = number_format($final, 0);
                $student_matrix[$sid]['total'] += $final;
                $student_matrix[$sid]['count']++;
            } else {
                $student_matrix[$sid]['marks'][$sub_id] = '-';
            }
        }
        
        if($student_matrix[$sid]['count'] > 0) {
            $student_matrix[$sid]['avg'] = $student_matrix[$sid]['total'] / $student_matrix[$sid]['count'];
            $avg = $student_matrix[$sid]['avg'];
            if($avg >= 80) $student_matrix[$sid]['grade'] = 'A';
            elseif($avg >= 70) $student_matrix[$sid]['grade'] = 'B';
            elseif($avg >= 60) $student_matrix[$sid]['grade'] = 'C';
            elseif($avg >= 50) $student_matrix[$sid]['grade'] = 'D';
            else $student_matrix[$sid]['grade'] = 'F';
        }
    }
}

// Rank computation logic sorting descending averages
uasort($student_matrix, function($a, $b) { return $b['avg'] <=> $a['avg']; });
$rnk = 1;
foreach($student_matrix as $sid => $data) {
    if($data['count'] > 0) {
        $student_matrix[$sid]['rank'] = $rnk++;
    }
}

// Restore Alphabetical order for presentation layout
uksort($student_matrix, function($a, $b) use ($student_matrix) {
    return strcmp($student_matrix[$a]['name'], $student_matrix[$b]['name']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Scoreboard Overview | LDS Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: #f8fafc; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #1e293b;
        }
        .page-header {
            background: linear-gradient(135deg, #064e3b, #10b981);
            color: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(5, 150, 105, 0.15);
        }
        .filter-card {
            border: none;
            border-radius: 20px;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .table-card {
            border: none;
            border-radius: 20px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .table th { 
            background-color: #064e3b !important; 
            color: white !important; 
            font-size: 11px; 
            text-transform: uppercase; 
            text-align: center;
            letter-spacing: 0.5px;
            padding: 15px 10px;
        }
        .table td {
            padding: 12px 10px;
        }
        .badge-grade {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        .grade-A { background-color: #d1fae5; color: #065f46; }
        .grade-B { background-color: #e0f2fe; color: #0369a1; }
        .grade-C { background-color: #fef3c7; color: #92400e; }
        .grade-D { background-color: #ffedd5; color: #9a3412; }
        .grade-F { background-color: #ffe4e6; color: #991b1b; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-4">
    
    <div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h2 class="fw-bold m-0"><i class="fas fa-chart-bar me-2"></i> Class Sheets Performance Overview 📊</h2>
            <p class="m-0 opacity-80 mt-1">Live synchronized multi-subject dashboard scoreboard</p>
        </div>
        <span class="badge bg-white text-dark fw-bold px-3 py-2 rounded-pill"><?= $class ?> - Stream <?= $stream ?></span>
    </div>

    <div class="card filter-card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Select Class 🏫</label>
                <select name="class" class="form-select rounded-3">
                    <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c' ".($class==$c?'selected':'').">$c</option>"; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Select Stream 📍</label>
                <select name="stream" class="form-select rounded-3">
                    <?php foreach(range('A','M') as $l) echo "<option value='$l' ".($stream==$l?'selected':'').">$l</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">Assessment Structure 📝</label>
                <select name="exam_type" class="form-select rounded-3">
                    <option value="Terminal" <?= ($exam=='Terminal'?'selected':'') ?>>Terminal (100%)</option>
                    <option value="Annual" <?= ($exam=='Annual'?'selected':'') ?>>Annual (100%)</option>
                    <option value="Term 1" <?= ($exam=='Term 1'?'selected':'') ?>>Term 1</option>
                    <option value="Term 2" <?= ($exam=='Term 2'?'selected':'') ?>>Term 2</option>
                    <option value="Special" <?= ($exam=='Special'?'selected':'') ?>>Special Case</option>
                    <option value="Mock" <?= ($exam=='Mock'?'selected':'') ?>>Mock Exam Matrix</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Academic Year 📅</label>
                <select name="year" class="form-select rounded-3">
                    <?php for($y=2015;$y<=2036;$y++){ $v="$y/".($y+1); echo "<option value='$v' ".($year==$v?'selected':'').">$v</option>"; } ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2 align-self-end">
                <button type="submit" class="btn btn-dark w-100 fw-bold py-2 rounded-3"><i class="fas fa-filter me-1"></i> FILTER VIEW</button>
                <a href="olevel_export_excel.php?class=<?= $class ?>&stream=<?= $stream ?>&exam_type=<?= $exam ?>&year=<?= $year ?>" class="btn btn-success w-100 fw-bold py-2 rounded-3"><i class="fas fa-file-excel me-1"></i> EXPORT</a>
            </div>
        </form>
    </div>

    <div class="card table-card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th width="50">SN</th>
                        <th style="text-align:left; min-width: 220px;">Student Full Name 🧑‍🎓</th>
                        <th width="70">Sex</th>
                        <?php foreach($subjects as $sub_name): ?>
                            <th><?= htmlspecialchars(substr($sub_name, 0, 4)) ?>.</th>
                        <?php endforeach; ?>
                        <th class="bg-dark text-white">Avg ⭐</th>
                        <th class="bg-dark text-white">Grade</th>
                        <th class="bg-dark text-white">Rank 🏆</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($student_matrix)): ?>
                        <tr>
                            <td colspan="<?= count($subjects) + 6 ?>" class="text-center py-5 text-muted fw-semibold">
                                <i class="fas fa-folder-open display-6 d-block mb-2 text-secondary"></i> No student logs found matching current configuration criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $n=1; foreach($student_matrix as $sid => $row): ?>
                        <tr>
                            <td class="text-center fw-bold text-secondary"><?= $n++ ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="text-center fw-semibold text-muted"><?= htmlspecialchars($row['gender']) ?></td>
                            
                            <?php foreach($subjects as $sub_id => $sub_name): ?>
                                <td class="text-center fw-semibold">
                                    <?= isset($row['marks'][$sub_id]) ? $row['marks'][$sub_id] : '-' ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <td class="text-center fw-bold text-primary bg-light"><?= number_format($row['avg'],1) ?></td>
                            <td class="text-center">
                                <span class="badge-grade grade-<?= $row['grade'] ?>"><?= $row['grade'] ?></span>
                            </td>
                            <td class="text-center fw-bold text-success bg-light"><?= $row['rank'] ?: '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>