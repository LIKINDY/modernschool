<?php
session_start();
include('db_config.php');

$class = $_GET['class_name'] ?? '';
$combination = $_GET['combination'] ?? '';
$term = $_GET['term'] ?? '';
$year = $_GET['year'] ?? '';

if (!$class || !$combination || !$term || !$year) {
    echo "<script>alert('Please fill all fields'); window.history.back();</script>";
    exit;
}

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// Function to calculate Division for A-Level
function getDivision($points, $is_complete) {
    if (!$is_complete) return "I-COM"; // Iweke I-COM kama masomo hayajakamilika
    if ($points >= 3 && $points <= 9) return "I";
    if ($points <= 12) return "II";
    if ($points <= 17) return "III";
    if ($points <= 19) return "IV";
    return "F";
}

// Function to get CSS class for Grade Colors
function getGradeStyle($grade) {
    switch(strtoupper($grade)) {
        case 'A': return 'background-color: #28a745; color: white; border: 1px solid #1e7e34;'; 
        case 'B': return 'background-color: #20c997; color: white; border: 1px solid #17a2b8;'; 
        case 'C': return 'background-color: #0dcaf0; color: black; border: 1px solid #0baccc;'; 
        case 'D': return 'background-color: #ffc107; color: black; border: 1px solid #e0a800;'; 
        case 'E': return 'background-color: #fd7e14; color: white; border: 1px solid #d36611;'; 
        case 'S': return 'background-color: #6610f2; color: white; border: 1px solid #520dc2;'; 
        case 'F': return 'background-color: #dc3545; color: white; border: 1px solid #bd2130;'; 
        default: return 'background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;';
    }
}

// 1. Logic to get all subjects (Core + Subsidiaries)
$subject_list = [];
$sub_query = "SELECT DISTINCT s.id, s.subject_name FROM subjects s 
              JOIN marks m ON s.id = m.subject_id 
              JOIN students st ON m.student_id = st.id 
              WHERE st.combination = '$combination' 
              AND s.subject_name NOT IN ('GENERAL STUDIES', 'GS', 'BAM', 'BASIC APPLIED MATHEMATICS')
              ORDER BY s.subject_name ASC LIMIT 3";
$sub_res = $conn->query($sub_query);
while($s_row = $sub_res->fetch_assoc()) {
    $s_row['is_subsidiary'] = false;
    $subject_list[] = $s_row;
}

$subs_query = "SELECT DISTINCT s.id, s.subject_name FROM subjects s 
               JOIN marks m ON s.id = m.subject_id 
               JOIN students st ON m.student_id = st.id 
               WHERE st.combination = '$combination' 
               AND (s.subject_name IN ('GENERAL STUDIES', 'GS', 'BAM', 'BASIC APPLIED MATHEMATICS'))";
$subs_res = $conn->query($subs_query);
while($subs_row = $subs_res->fetch_assoc()) {
    $subs_row['is_subsidiary'] = true;
    $subject_list[] = $subs_row;
}

// 2. Prepare Data
$students_output = [];
$grade_counts = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'E'=>0, 'S'=>0, 'F'=>0];
$div_counts = ['I'=>0, 'II'=>0, 'III'=>0, 'IV'=>0, 'F'=>0, 'I-COM'=>0];

$sql = "SELECT st.id, st.student_id, st.fullname FROM students st 
        WHERE st.class_name = '$class' AND st.combination = '$combination' ORDER BY st.fullname ASC";
$res = $conn->query($sql);

while($row = $res->fetch_assoc()){
    $st_id = $row['id'];
    $st_marks = [];
    $total_pts = 0;
    $core_marks_sum = 0;
    $core_found = 0; // Tunahesabu masomo 3 ya combination
    
    foreach($subject_list as $sub){
        $sid = $sub['id'];
        $m = $conn->query("SELECT total_100, grade, points FROM marks WHERE student_id = '$st_id' AND subject_id = '$sid' AND term = '$term' AND year = '$year'")->fetch_assoc();
        
        $st_marks[$sid] = $m;
        
        if(!$sub['is_subsidiary']) {
            if ($m) {
                $total_pts += $m['points'] ?? 0;
                $core_marks_sum += $m['total_100'] ?? 0;
                $core_found++;
            }
        }
        
        if($m && isset($m['grade'])) {
            $g = strtoupper($m['grade']);
            if(isset($grade_counts[$g])) $grade_counts[$g]++;
        }
    }
    
    // Angalia kama amefanya masomo yote 3 ya combination
    $is_complete = ($core_found == 3);
    
    $division = getDivision($total_pts, $is_complete);
    if(isset($div_counts[$division])) $div_counts[$division]++;
    
    $row['marks'] = $st_marks;
    $row['pts'] = $is_complete ? $total_pts : '-';
    $row['avg'] = $is_complete ? round($core_marks_sum / 3, 1) : '-';
    $row['div'] = $division;
    $students_output[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Broadsheet - <?= $combination ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; color: #334155; }
        .main-card { background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: none; padding: 2rem; margin: 2rem 0; }
        .school-header { border-bottom: 2px solid #f1f5f9; margin-bottom: 2rem; padding-bottom: 1rem; }
        .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
        .stat-label { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b; }
        .stat-value { font-size: 1.25rem; font-weight: 800; }
        .table-container { border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
        .table thead { background-color: #1e293b; color: white; }
        .table thead th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 1rem; }
        .mark-display { font-size: 1rem; font-weight: 700; display: block; }
        .grade-badge { font-size: 0.7rem; font-weight: 800; padding: 2px 10px; border-radius: 6px; }
        
        .div-pill { font-weight: 800; font-size: 0.85rem; padding: 0.5rem 1rem; color: white !important; }
        .bg-div-i { background-color: #198754 !important; }
        .bg-div-ii { background-color: #0d6efd !important; }
        .bg-div-iii { background-color: #6c757d !important; }
        .bg-div-iv { background-color: #fd7e14 !important; }
        .bg-div-f { background-color: #dc3545 !important; }
        .bg-div-i-com { background-color: #6f42c1 !important; } /* Rangi ya I-COM */
        .sub-bg { background-color: #fcfcfc; }

        @media print {
            .no-print { display: none !important; }
            .main-card { box-shadow: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="container-fluid px-4">
    <div class="main-card">
        <div class="school-header d-flex justify-content-between align-items-center">
            <div>
                <a href="bulk_filter_alevel.php" class="btn btn-sm btn-outline-secondary no-print mb-2">
                    <i class="bi bi-arrow-left"></i> Back to Filter
                </a>
                <h2 class="fw-800 mb-1"><?= strtoupper($school['school_name']) ?></h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $class ?></span>
                    <span class="badge bg-secondary"><?= $combination ?></span>
                    <span class="text-muted small ms-2"><i class="bi bi-calendar3"></i> <?= $term ?> | <?= $year ?></span>
                </div>
            </div>
            <div class="no-print d-flex gap-2">
                <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer-fill me-2"></i>Print</button>
                <a href="export_advanced_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel-fill me-2"></i>Excel Export
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-7">
                <div class="card border-0 bg-light p-3 h-100">
                    <h6 class="stat-label mb-3">Division Distribution</h6>
                    <div class="row text-center g-2">
                        <?php foreach($div_counts as $d => $count): 
                            $bg_class = "bg-div-" . strtolower($d); ?>
                            <div class="col">
                                <div class="stat-box">
                                    <div class="stat-label">DIV <?= $d ?></div>
                                    <div class="stat-value badge <?= $bg_class ?> w-100"><?= $count ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card border-0 bg-light p-3 h-100">
                    <h6 class="stat-label mb-3">Grade Summary</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach($grade_counts as $g => $c): ?>
                            <div class="p-2 bg-white border rounded text-center" style="min-width: 50px;">
                                <span class="fw-bold d-block text-muted small"><?= $g ?></span>
                                <span class="fw-800"><?= $c ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive table-container">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-center">
                        <th>S/N</th>
                        <th class="text-start">Student Name</th>
                        <?php foreach($subject_list as $sub): ?>
                            <th class="<?= $sub['is_subsidiary'] ? 'sub-bg' : '' ?>"><?= strtoupper($sub['subject_name']) ?></th>
                        <?php endforeach; ?>
                        <th class="bg-primary bg-opacity-10">AVG</th>
                        <th class="bg-primary bg-opacity-10">PTS</th>
                        <th class="bg-primary bg-opacity-10">DIV</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n=1; foreach($students_output as $st): 
                        $div_color_class = "bg-div-" . strtolower($st['div']);
                    ?>
                    <tr class="text-center">
                        <td><?= $n++ ?></td>
                        <td class="text-start">
                            <div class="fw-bold"><?= strtoupper($st['fullname']) ?></div>
                            <small class="text-muted"><?= $st['student_id'] ?></small>
                        </td>
                        <?php foreach($subject_list as $sub): 
                            $m = $st['marks'][$sub['id']] ?? null;
                            $grade = $m['grade'] ?? '-';
                            $score = $m['total_100'] ?? '-';
                        ?>
                            <td class="<?= $sub['is_subsidiary'] ? 'sub-bg' : '' ?>">
                                <span class="mark-display"><?= $score ?></span>
                                <span class="grade-badge" style="<?= getGradeStyle($grade) ?>"><?= $grade ?></span>
                            </td>
                        <?php endforeach; ?>
                        <td class="fw-bold text-primary"><?= $st['avg'] ?><?= is_numeric($st['avg']) ? '%' : '' ?></td>
                        <td class="fw-800"><?= $st['pts'] ?></td>
                        <td>
                            <span class="badge rounded-pill <?= $div_color_class ?> div-pill">
                                <?= ($st['div'] == "I-COM") ? "I-COM" : "DIV " . $st['div'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-5 text-center small text-muted">
            <p>Powered by Sir Likindy</p>
        </div>
    </div>
</div>
</body>
</html>