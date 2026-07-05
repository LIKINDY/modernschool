<?php
session_start();
include('db_config.php');

$class = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class || !$year || !$term) {
    echo "<script>alert('Please select all fields!'); window.location.href='filter_broadsheet_primary.php';</script>";
    exit;
}

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// Function ya Grade za Primary (Tanzania Standard)
function getPrimaryGrade($avg) {
    if ($avg >= 81) return 'A';
    if ($avg >= 61) return 'B';
    if ($avg >= 41) return 'C';
    if ($avg >= 21) return 'D';
    return 'E';
}

// Rangi za Grade
function getGradeColor($grade) {
    switch ($grade) {
        case 'A': return '#198754'; // Green
        case 'B': return '#0d6efd'; // Blue
        case 'C': return '#ffc107'; // Yellow
        case 'D': return '#fd7e14'; // Orange
        case 'E': return '#dc3545'; // Red
        default: return '#6c757d';
    }
}

// Query ya kupata wanafunzi na wastani wao
if ($term == 'Annual') {
    $rank_sql = "SELECT st.id, st.student_id, st.fullname, st.gender, 
                AVG(CASE WHEN m.term='Term 1' THEN m.total_100 * 0.4 ELSE 0 END + 
                    CASE WHEN m.term='Term 2' THEN m.total_100 * 0.6 ELSE 0 END) as grand_avg,
                SUM(CASE WHEN m.term='Term 1' THEN m.total_100 * 0.4 ELSE 0 END + 
                    CASE WHEN m.term='Term 2' THEN m.total_100 * 0.6 ELSE 0 END) as grand_total
                FROM students st 
                JOIN marks m ON st.id = m.student_id 
                WHERE st.class_name = '$class' AND m.year = '$year' 
                GROUP BY st.id 
                ORDER BY grand_avg DESC";
} else {
    $rank_sql = "SELECT st.id, st.student_id, st.fullname, st.gender, 
                SUM(m.total_100) as grand_total, AVG(m.total_100) as grand_avg 
                FROM students st 
                JOIN marks m ON st.id = m.student_id 
                WHERE st.class_name = '$class' AND m.year = '$year' AND m.term = '$term' 
                GROUP BY st.id 
                ORDER BY grand_total DESC";
}

$rank_res = $conn->query($rank_sql);
$grade_summary = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
$list_data = [];
$pos = 1;

if($rank_res && $rank_res->num_rows > 0){
    while($row = $rank_res->fetch_assoc()){
        $st_id = $row['id'];
        if ($term == 'Annual') {
            $marks_q = $conn->query("SELECT s.subject_name, 
                        (MAX(CASE WHEN m.term='Term 1' THEN m.total_100 ELSE 0 END)*0.4 + 
                         MAX(CASE WHEN m.term='Term 2' THEN m.total_100 ELSE 0 END)*0.6) as annual_sub 
                        FROM marks m JOIN subjects s ON m.subject_id = s.id 
                        WHERE m.student_id = '$st_id' AND m.year = '$year' GROUP BY s.id");
        } else {
            $marks_q = $conn->query("SELECT m.total_100 as annual_sub, s.subject_name FROM marks m JOIN subjects s ON m.subject_id = s.id WHERE m.student_id = '$st_id' AND m.year = '$year' AND m.term = '$term'");
        }

        $details = [];
        while($m = $marks_q->fetch_assoc()){
            $details[] = "<span class='badge border text-dark fw-normal'>".$m['subject_name'] . ": <b>" . number_format($m['annual_sub'],0) . "</b></span>";
        }

        $avg_grade = getPrimaryGrade($row['grand_avg']);
        $grade_summary[$avg_grade]++;

        $list_data[] = [
            'id' => $row['student_id'],
            'name' => $row['fullname'],
            'sex' => (strtoupper($row['gender'][0]) == 'F') ? 'F' : 'M',
            'total' => number_format($row['grand_total'], 0),
            'avg' => number_format($row['grand_avg'], 1),
            'grade' => $avg_grade,
            'pos' => $pos++,
            'details' => implode(" ", $details)
        ];
    }
}
$total_students = count($list_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Broad Sheet - <?= $class ?> (<?= $term ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; font-size: 13px; }
        .sheet-container { 
            background: #fff; padding: 30px; margin: 20px auto;
            border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            max-width: 95%;
        }
        .header-box { border-bottom: 3px solid #0d6efd; padding-bottom: 20px; margin-bottom: 25px; text-align: center; }
        .school-title { font-size: 28px; font-weight: 900; color: #0d6efd; text-transform: uppercase; }
        .table thead th { background-color: #0d6efd; color: #fff; text-align: center; border: 1px solid #dee2e6; vertical-align: middle; }
        .table td { border: 1px solid #dee2e6; vertical-align: middle; text-align: center; }
        .grade-box { font-weight: bold; color: #fff; padding: 4px 8px; border-radius: 5px; min-width: 30px; display: inline-block; }
        .summary-card { background: #eef3f7; border-radius: 10px; padding: 15px; }
        @media print {
            .no-print { display: none; }
            body { background: #fff; padding: 0; }
            .sheet-container { box-shadow: none; border: none; width: 100%; max-width: 100%; margin: 0; }
        }
        .footer-likindy { text-align: center; margin-top: 30px; font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="no-print py-3 text-center">
        <a href="export_primary_excel.php?class_name=<?= urlencode($class) ?>&year=<?= urlencode($year) ?>&term=<?= urlencode($term) ?>" class="btn btn-success px-4 fw-bold shadow me-2">
            <i class="fas fa-file-excel me-2"></i> Download Excel
        </a>
        
        <button onclick="window.print()" class="btn btn-primary px-4 fw-bold shadow me-2">
            <i class="fas fa-print me-2"></i> Print PDF
        </button>
        
        <a href="filter_broadsheet_primary.php" class="btn btn-outline-dark px-4 fw-bold shadow">Back</a>
    </div>

    <div class="sheet-container">
        <div class="header-box">
            <h1 class="school-title"><?= $school['school_name'] ?></h1>
            <h4 class="fw-bold text-dark">PRIMARY ACADEMIC BROAD SHEET - <?= strtoupper($term) ?></h4>
            <div class="mt-2 fw-bold text-muted">
                CLASS: <?= strtoupper($class) ?> | YEAR: <?= $year ?> | TOTAL STUDENTS: <?= $total_students ?>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="summary-card">
                    <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2"></i>GRADE DISTRIBUTION</h6>
                    <div class="d-flex gap-2">
                        <?php foreach($grade_summary as $g => $count): ?>
                        <div class="text-center p-2 rounded border bg-white flex-fill">
                            <div class="fw-bold" style="color: <?= getGradeColor($g) ?>"><?= $g ?></div>
                            <div class="small"><?= $count ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-0">Date Generated: <b><?= date('d/m/Y H:i') ?></b></p>
                <p class="text-muted small italic">Note: Annual results are calculated as (Term 1: 40% + Term 2: 60%)</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="40">POS</th>
                        <th width="100">ID</th>
                        <th class="text-start">FULL NAME</th>
                        <th width="40">SEX</th>
                        <th width="70">TOTAL</th>
                        <th width="70">AVG %</th>
                        <th width="60">GRADE</th>
                        <th class="text-start">SUBJECT BREAKDOWN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list_data as $row): ?>
                    <tr>
                        <td class="fw-bold bg-light"><?= $row['pos'] ?></td>
                        <td><?= $row['id'] ?></td>
                        <td class="text-start fw-bold"><?= strtoupper($row['name']) ?></td>
                        <td><?= $row['sex'] ?></td>
                        <td class="fw-bold text-primary"><?= $row['total'] ?></td>
                        <td class="fw-bold"><?= $row['avg'] ?>%</td>
                        <td><span class="grade-box" style="background: <?= getGradeColor($row['grade']) ?>"><?= $row['grade'] ?></span></td>
                        <td class="text-start"><?= $row['details'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-5 pt-4">
            <div class="col-4 text-center"><div class="border-top border-dark pt-2 fw-bold">CLASS TEACHER</div></div>
            <div class="col-4 text-center"><div class="border-top border-dark pt-2 fw-bold">ACADEMIC OFFICE</div></div>
            <div class="col-4 text-center"><div class="border-top border-dark pt-2 fw-bold">HEAD TEACHER / STAMP</div></div>
        </div>

        <div class="footer-likindy">POWERED BY SIR LIKINDY</div>
    </div>
</div>
</body>
</html>