<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class_name = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '2025/2026';
$term = $_GET['term'] ?? 'Term 1';

if (!$class_name) {
    echo "<div class='alert alert-danger'>Please select a class first.</div>";
    exit;
}

// Function to get Grade and Color
function getGrade($score) {
    if ($score >= 80) return ['grade' => 'A', 'remark' => 'Excellent', 'color' => '#27ae60']; // Green
    if ($score >= 70) return ['grade' => 'B', 'remark' => 'Very Good', 'color' => '#2980b9']; // Blue
    if ($score >= 60) return ['grade' => 'C', 'remark' => 'Good', 'color' => '#f1c40f']; // Yellow
    if ($score >= 50) return ['grade' => 'D', 'remark' => 'Pass', 'color' => '#e67e22']; // Orange
    return ['grade' => 'F', 'remark' => 'Fail', 'color' => '#c0392b']; // Red
}

// 1. Fetch Primary subjects
$subjects_query = $conn->query("SELECT id, subject_name FROM subjects WHERE level = 'primary' ORDER BY id ASC");
$subjects = [];
while ($sub = $subjects_query->fetch_assoc()) {
    $subjects[$sub['id']] = $sub['subject_name'];
}

// 2. Fetch students and calculate ranking
$students_data = [];
$students_res = $conn->query("SELECT id, fullname FROM students WHERE class_name = '$class_name' AND status = 'active'");

while($st = $students_res->fetch_assoc()) {
    $std_id = $st['id'];
    $marks_query = $conn->query("SELECT total_100 FROM marks WHERE student_id = '$std_id' AND year = '$year' AND term = '$term'");
    
    $total_marks = 0;
    $subject_count = 0;

    while($m = $marks_query->fetch_assoc()) {
        $total_marks += $m['total_100'];
        $subject_count++;
    }

    $average = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;
    
    $students_data[] = [
        'id' => $std_id,
        'fullname' => $st['fullname'],
        'total' => $total_marks,
        'avg' => $average
    ];
}

// Rank students by Average (Descending)
usort($students_data, function($a, $b) {
    return $b['avg'] <=> $a['avg'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Primary Broadsheet | <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; font-size: 12px; }
        .broadsheet-card { background: white; border: none; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .table-primary-sheet { border-collapse: collapse; width: 100%; }
        .table-primary-sheet th { background-color: #2c3e50 !important; color: white !important; padding: 10px 5px; border: 1px solid #444 !important; }
        .table-primary-sheet td { border: 1px solid #ddd !important; vertical-align: middle; padding: 8px 4px; }
        .rank-column { background-color: #f8f9fa; font-weight: bold; width: 40px; }
        .name-column { text-align: left !important; padding-left: 15px !important; width: 250px; }
        
        /* Grade Badge Styling */
        .grade-badge {
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            display: inline-block;
            min-width: 25px;
        }

        /* Summary Table */
        .summary-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            background: #fff;
        }
        .summary-table th { background: #f8f9fa; border-bottom: 2px solid #2c3e50; }

        @media print {
            .no-print { display: none !important; }
            @page { size: landscape; margin: 10mm; }
            body { background: white; }
            .broadsheet-card { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h4 class="fw-bold text-dark"><i class="fas fa- chalkboard me-2 text-primary"></i>PRIMARY CLASS BROADSHEET</h4>
        <div>
            <button onclick="window.print()" class="btn btn-primary shadow-sm me-2"><i class="fas fa-print me-2"></i> Print Broadsheet</button>
            <a href="view_results.php" class="btn btn-outline-secondary shadow-sm">Back</a>
        </div>
    </div>

    <div class="broadsheet-card">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-uppercase mb-1" style="letter-spacing: 2px; color: #2c3e50;"><?= $school['school_name'] ?? 'SMART SCHOOL ACADEMY' ?></h2>
            <p class="text-muted mb-3">OFFICIAL ACADEMIC PERFORMANCE BROADSHEET</p>
            <div class="d-inline-block border rounded-pill px-4 py-2 bg-light shadow-sm">
                <span class="me-3"><strong>CLASS:</strong> <?= strtoupper($class_name) ?></span>
                <span class="me-3"><strong>YEAR:</strong> <?= $year ?></span>
                <span><strong>TERM:</strong> <?= strtoupper($term) ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-primary-sheet text-center">
                <thead>
                    <tr>
                        <th class="rank-column">POS</th>
                        <th class="name-column">STUDENT NAME</th>
                        <?php foreach ($subjects as $name): ?>
                            <th title="<?= $name ?>"><?= strtoupper(substr($name, 0, 3)) ?></th>
                        <?php endforeach; ?>
                        <th style="background: #f39c12 !important;">TOTAL</th>
                        <th style="background: #3498db !important;">AVG (%)</th>
                        <th style="background: #2c3e50 !important;">GRADE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach($students_data as $data): 
                        $std_id = $data['id'];
                        $grade_info = getGrade($data['avg']);
                    ?>
                    <tr>
                        <td class="rank-column"><?= $rank++ ?></td>
                        <td class="name-column fw-bold"><?= strtoupper($data['fullname']) ?></td>
                        
                        <?php foreach ($subjects as $sub_id => $name): 
                            $m_query = $conn->query("SELECT total_100 FROM marks WHERE student_id = '$std_id' AND subject_id = '$sub_id' AND year = '$year' AND term = '$term' LIMIT 1");
                            $m = $m_query->fetch_assoc();
                            $score = $m['total_100'] ?? 0;
                            // Color low marks in red
                            $style = ($score < 50 && $score > 0) ? "color: #c0392b; font-weight: bold;" : "";
                            echo "<td style='$style'>" . ($score > 0 ? $score : '-') . "</td>";
                        endforeach; ?>

                        <td class="fw-bold bg-light"><?= $data['total'] ?></td>
                        <td class="fw-bold bg-light text-primary"><?= number_format($data['avg'], 1) ?>%</td>
                        <td>
                            <span class="grade-badge" style="background-color: <?= $grade_info['color'] ?>;">
                                <?= $grade_info['grade'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-5">
            <div class="col-md-5">
                <div class="summary-card shadow-sm">
                    <h6 class="fw-bold border-bottom pb-2 mb-3 text-uppercase"><i class="fas fa-info-circle me-2"></i> Grading Scale & Remarks</h6>
                    <table class="table table-sm summary-table mb-0">
                        <thead>
                            <tr>
                                <th>Range</th>
                                <th>Grade</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>80 - 100</td><td><span class="grade-badge" style="background:#27ae60">A</span></td><td class="text-success fw-bold">Excellent</td></tr>
                            <tr><td>70 - 79</td><td><span class="grade-badge" style="background:#2980b9">B</span></td><td class="text-primary fw-bold">Very Good</td></tr>
                            <tr><td>60 - 69</td><td><span class="grade-badge" style="background:#f1c40f">C</span></td><td class="text-warning fw-bold">Good</td></tr>
                            <tr><td>50 - 59</td><td><span class="grade-badge" style="background:#e67e22">D</span></td><td class="text-warning fw-bold">Pass</td></tr>
                            <tr><td>00 - 49</td><td><span class="grade-badge" style="background:#c0392b">F</span></td><td class="text-danger fw-bold">Fail</td></tr>
                        </tbody>
                    </table>
                    <div class="mt-2 text-center small text-muted font-italic">Passing Mark: 50%</div>
                </div>
            </div>
            
            <div class="col-md-7 text-end d-flex align-items-end justify-content-end">
                <div class="text-center px-4">
                    <div style="width: 200px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
                    <p class="fw-bold text-uppercase mb-0">Academic Officer</p>
                    <small class="text-muted">Date: <?= date('d M, Y') ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>