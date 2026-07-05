<?php
session_start();
include('db_config.php');

$class = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class || !$year || !$term) {
    echo "<script>alert('Missing filters!'); window.location.href='filter_broadsheet_olevel.php';</script>";
    exit;
}

// Fetch School Info
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_res ? $school_res->fetch_assoc() : null;

// Performance Summary Structure
$summary = [
    'F' => ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0],
    'M' => ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0, '0' => 0]
];

// NECTA O-Level Division Logic
function calculateOLevelDivision($points, $subjectCount) {
    if ($subjectCount < 1) return "N/A";
    if ($points >= 7 && $points <= 17) return "I";
    if ($points >= 18 && $points <= 21) return "II";
    if ($points >= 22 && $points <= 25) return "III";
    if ($points >= 26 && $points <= 33) return "IV";
    return "0";
}

// Logic ya kuangalia kama ni Mock au Terminal (English Support)
$is_mock_or_terminal = (strpos(strtolower($term), 'mock') !== false || strpos(strtolower($term), 'terminal') !== false);

$students_res = $conn->query("SELECT * FROM students WHERE class_name='$class' AND status='active' ORDER BY fullname ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-Level Broad Sheet | <?= htmlspecialchars($class) ?></title>
    
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2991/2991148.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { 
            --primary: #1e293b; 
            --accent: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f8fafc; 
            color: #1e293b; 
            font-size: 13px; 
            margin: 0;
        }
        
        .main-container { padding: 40px 20px; }
        
        .sheet-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            padding: 50px;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .header-section {
            text-align: center;
            border-bottom: 2px dashed #e2e8f0;
            margin-bottom: 35px;
            padding-bottom: 25px;
        }

        .school-name { 
            font-weight: 800; 
            color: var(--primary); 
            letter-spacing: -0.5px;
            font-size: 2.2rem;
        }
        
        /* Stats Cards */
        .stat-box {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .stat-value { font-size: 18px; font-weight: 800; color: var(--primary); }

        /* Table Design */
        .table { border-collapse: separate; border-spacing: 0; }
        .table thead th {
            background-color: #f8fafc !important;
            color: #64748b !important;
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 700;
            padding: 15px;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9 !important; }

        /* Grade Ranks */
        .grade-A { color: #059669; font-weight: 800; }
        .grade-B { color: #2563eb; font-weight: 800; }
        .grade-C { color: #d97706; font-weight: 800; }
        .grade-D { color: #ea580c; font-weight: 800; }
        .grade-F { color: #dc2626; font-weight: 800; }

        /* Division Badges */
        .div-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 11px;
            min-width: 45px;
        }
        .div-I { background: #dcfce7; color: #166534; }
        .div-II { background: #dbeafe; color: #1e40af; }
        .div-III { background: #fef3c7; color: #92400e; }
        .div-IV { background: #f1f5f9; color: #475569; }
        .div-0 { background: #fee2e2; color: #991b1b; }

        .btn-action {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.2s;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .main-container { padding: 0; }
            .sheet-card { box-shadow: none; border: none; padding: 20px; }
            .div-badge { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

<div class="container-fluid main-container">
    
    <div class="no-print d-flex justify-content-between align-items-center mb-4 container">
        <a href="filter_broadsheet_olevel.php" class="btn btn-white border shadow-sm btn-action">
            <i class="fas fa-arrow-left me-2"></i>Back to Filters
        </a>
        <div class="d-flex gap-2">
            <a href="export_excel_olevel.php?class_name=<?= $class ?>&year=<?= $year ?>&term=<?= $term ?>" class="btn btn-success shadow-sm btn-action">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-primary shadow-sm btn-action">
                <i class="fas fa-print me-2"></i>Generate Report
            </button>
        </div>
    </div>

    <div class="sheet-card container">
        <div class="header-section">
            <h2 class="school-name mb-1"><?= strtoupper($school['school_name'] ?? 'SMART SECONDARY SCHOOL') ?></h2>
            <div class="text-muted fw-bold mb-3" style="letter-spacing: 2px;">
                <i class="fas fa-graduation-cap me-2"></i>OFFICIAL <?= strtoupper($term) ?> EXAMINATION BROAD SHEET
            </div>
            <div class="d-flex justify-content-center gap-2 mt-4">
                <div class="stat-box">
                    <div class="stat-label">Class</div>
                    <div class="stat-value"><?= strtoupper($class) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Term</div>
                    <div class="stat-value"><?= strtoupper($term) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Academic Year</div>
                    <div class="stat-value"><?= $year ?></div>
                </div>
            </div>
        </div>

        <?php
        $list_data = [];
        $total_students = 0;
        if ($students_res && $students_res->num_rows > 0) {
            while($st = $students_res->fetch_assoc()) {
                $total_students++;
                $st_id = $st['id'];
                
                // Hapa tunaangalia kama ni Mock/Terminal au Marks za kawaida
                if ($is_mock_or_terminal) {
                    $marks_q = $conn->query("SELECT grade, subject_name FROM mock_results WHERE student_id = '$st_id' AND academic_year = '$year'");
                } else {
                    $marks_q = $conn->query("SELECT m.grade, s.subject_name 
                                        FROM marks m 
                                        JOIN subjects s ON m.subject_id = s.id 
                                        WHERE m.student_id = '$st_id' 
                                        AND m.year = '$year' 
                                        AND m.term = '$term'");
                }
                
                $points_array = [];
                $details = [];
                
                if($marks_q) {
                    while($m = $marks_q->fetch_assoc()) {
                        $grade = strtoupper($m['grade']);
                        $details[] = $m['subject_name'] . " <span class='grade-$grade'>$grade</span>";
                        
                        $p = 5; 
                        if($grade == 'A') $p = 1;
                        elseif($grade == 'B') $p = 2;
                        elseif($grade == 'C') $p = 3;
                        elseif($grade == 'D') $p = 4;
                        
                        $points_array[] = $p;
                    }
                }

                sort($points_array);
                $best_seven = array_slice($points_array, 0, 7);
                $total_points = array_sum($best_seven);
                $subject_count = count($points_array);
                
                $div = calculateOLevelDivision($total_points, $subject_count);
                if($subject_count > 0 && $subject_count < 7 && $div != "0") { $div = "IV"; }

                $gender = (strtoupper($st['gender'])[0] == 'F') ? 'F' : 'M';
                if($div != "N/A") { $summary[$gender][$div]++; }
                
                $list_data[] = [
                    'fullname' => $st['fullname'],
                    'sex' => $gender,
                    'points' => ($subject_count > 0) ? $total_points : '--',
                    'div' => $div,
                    'details' => !empty($details) ? implode(" • ", $details) : "<span class='text-danger'>Pending Marks</span>"
                ];
            }
        }
        ?>

        <div class="row mb-5 g-4">
            <div class="col-md-7">
                <h6 class="fw-bold mb-3 text-uppercase" style="font-size: 11px; color: var(--accent);">
                    <i class="fas fa-chart-pie me-2"></i>Results Summary
                </h6>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm text-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-start">Category</th>
                                <th>Div I</th><th>Div II</th><th>Div III</th><th>Div IV</th><th>Div 0</th><th class="bg-light">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach(['F', 'M'] as $s): $row_t = array_sum($summary[$s]); ?>
                            <tr>
                                <td class="text-start fw-bold"><?= ($s == 'F') ? 'Female Students' : 'Male Students' ?></td>
                                <td><span class="badge rounded-pill bg-success bg-opacity-10 text-success"><?= $summary[$s]['I'] ?></span></td>
                                <td><?= $summary[$s]['II'] ?></td>
                                <td><?= $summary[$s]['III'] ?></td>
                                <td><?= $summary[$s]['IV'] ?></td>
                                <td><span class="text-danger fw-bold"><?= $summary[$s]['0'] ?></span></td>
                                <td class="fw-bold bg-light text-primary"><?= $row_t ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-5 d-flex align-items-end">
                <div class="alert alert-info w-100 border-0 shadow-sm rounded-4 p-4 mb-0">
                    <div class="small fw-bold text-uppercase opacity-75 mb-1">Total Enrollment</div>
                    <div class="h3 fw-800 mb-0"><?= $total_students ?> <span class="fs-6 fw-normal">Active Students</span></div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold mb-3 text-uppercase" style="font-size: 11px; color: var(--accent);">
            <i class="fas fa-list-ol me-2"></i>Student Ledger
        </h6>
        <div class="table-responsive border rounded-4 shadow-sm">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="50">#</th>
                        <th>Student Name</th>
                        <th class="text-center">Gender</th>
                        <th class="text-center">Points</th>
                        <th class="text-center">Division</th>
                        <th>Subject Breakdown</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($list_data)): $n=1; foreach($list_data as $row): ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?= str_pad($n++, 2, "0", STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= strtoupper($row['fullname']) ?></div>
                        </td>
                        <td class="text-center">
                           <span class="badge <?= $row['sex'] == 'F' ? 'bg-danger' : 'bg-primary' ?> bg-opacity-10 <?= $row['sex'] == 'F' ? 'text-danger' : 'text-primary' ?> rounded-pill">
                               <?= $row['sex'] ?>
                           </span>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold h6 mb-0"><?= $row['points'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="div-badge div-<?= $row['div'] ?>"><?= $row['div'] ?></span>
                        </td>
                        <td class="details-text" style="font-size: 10.5px; color: #64748b; line-height: 1.6;">
                            <?= $row['details'] ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center p-5 text-muted">No assessment data available for this criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-5 pt-5 text-center signature-section">
            <div class="col-4">
                <div style="height: 60px;"></div>
                <div class="border-top pt-2 mx-4 fw-bold text-muted small">Academic Master</div>
            </div>
            <div class="col-4">
                <div style="height: 60px;"></div>
                <div class="border-top pt-2 mx-4 fw-bold text-muted small">School Stamp</div>
            </div>
            <div class="col-4">
                <div style="height: 60px;"></div>
                <div class="border-top pt-2 mx-4 fw-bold text-muted small">Head of School</div>
            </div>
        </div>
        
        <div class="mt-5 text-center no-print opacity-50 small">
            Generated on <?= date('d M, Y \a\t H:i A') ?> • Smart School Management System
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>