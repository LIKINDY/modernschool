<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 1. Capture Filters
$class      = $_GET['class'] ?? '';
$stream     = $_GET['stream'] ?? '';
$exam_type  = $_GET['exam_type'] ?? '';
$year       = $_GET['year'] ?? '';
$subject_id = $_GET['subject'] ?? 'all'; // Default is 'all'

$results = [];
$subjects_list = [];
$search_performed = false;

if (isset($_GET['filter'])) {
    $class      = mysqli_real_escape_string($conn, $class);
    $stream     = mysqli_real_escape_string($conn, $stream);
    $exam_type  = mysqli_real_escape_string($conn, $exam_type);
    $year       = mysqli_real_escape_string($conn, $year);

    // Get the list of subjects to build table columns
    if ($subject_id == 'all') {
        $sub_query = "SELECT id, subject_name FROM nursery_subjects ORDER BY subject_name ASC";
    } else {
        $sub_id_clean = mysqli_real_escape_string($conn, $subject_id);
        $sub_query = "SELECT id, subject_name FROM nursery_subjects WHERE id = '$sub_id_clean'";
    }
    
    $sub_res = $conn->query($sub_query);
    while($s = $sub_res->fetch_assoc()) {
        $subjects_list[$s['id']] = $s['subject_name'];
    }

    // Main Query: Fetch students and their marks
    $sql = "SELECT s.id, s.fullname ";
    foreach ($subjects_list as $id => $name) {
        $sql .= ", MAX(CASE WHEN m.subject_id = '$id' THEN (m.ca_mark + m.monthly_mark + m.exam_mark) END) AS score_$id ";
    }
    
    $sql .= " FROM students s 
             LEFT JOIN nursery_marks m ON s.id = m.student_id 
             AND m.exam_type = '$exam_type' 
             AND m.academic_year = '$year'
             WHERE s.class_name = '$class' 
             AND s.stream = '$stream' 
             AND s.status = 'active'
             GROUP BY s.id, s.fullname
             ORDER BY s.fullname ASC";

    $res = $conn->query($sql);
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
    }
    $search_performed = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Marks | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --secondary: #64748b; --success: #10b981; }
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        
        .filter-card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: white; }
        .table-container { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); overflow-x: auto; }
        
        .score-cell { font-weight: 700; color: #1e293b; text-align: center; }
        .avg-badge { padding: 6px 12px; border-radius: 8px; font-weight: 800; }
        .bg-low { background: #fee2e2; color: #991b1b; }
        .bg-mid { background: #fef3c7; color: #92400e; }
        .bg-high { background: #dcfce7; color: #166534; }
        
        @media print { .btn, .filter-card, .no-print { display: none !important; } }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Nursery Progress Review</h2>
            <p class="text-muted mb-0">Analysis of student performance across subjects</p>
        </div>
        <div class="no-print d-flex gap-2">
            <!-- Link to Excel Export Page -->
            <a href="nursery_mkeka_excel.php?class=<?= $class ?>&stream=<?= $stream ?>&exam_type=<?= $exam_type ?>&year=<?= $year ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-2"></i>Export Mkeka
            </a>
            <button onclick="window.print()" class="btn btn-outline-dark"><i class="fas fa-print me-2"></i>Print Report</button>
            <a href="nursery_enter_result.php" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Back</a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card filter-card mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold"><i class="fas fa-book me-1"></i> Subject View</label>
                    <select name="subject" class="form-select">
                        <option value="all" <?= ($subject_id == 'all' ? 'selected' : '') ?>>--- All Subjects ---</option>
                        <?php 
                        $subs = $conn->query("SELECT * FROM nursery_subjects ORDER BY subject_name ASC");
                        while($s = $subs->fetch_assoc()) {
                            echo "<option value='{$s['id']}' ".($subject_id == $s['id'] ? 'selected' : '').">{$s['subject_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold"><i class="fas fa-school me-1"></i> Class</label>
                    <select name="class" class="form-select" required>
                        <?php foreach(['P.Group','KG1','KG2'] as $c) echo "<option value='$c' ".($class==$c?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold"><i class="fas fa-code-branch me-1"></i> Stream</label>
                    <select name="stream" class="form-select" required>
                        <?php foreach(range('A','M') as $str) echo "<option value='$str' ".($stream==$str?'selected':'').">$str</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold"><i class="fas fa-file-alt me-1"></i> Exam</label>
                    <select name="exam_type" class="form-select" required>
                        <?php foreach(['Term 1','Term 2','Special','Annual'] as $et) echo "<option value='$et' ".($exam_type==$et?'selected':'').">$et</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold"><i class="fas fa-calendar me-1"></i> Year</label>
                    <select name="year" class="form-select">
                        <?php for($y=2015; $y<=2036; $y++) { $v="$y/".($y+1); echo "<option value='$v' ".($year==$v?'selected':'').">$v</option>"; } ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" name="filter" class="btn btn-dark fw-bold"><i class="fas fa-filter me-2"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Display -->
    <?php if ($search_performed): ?>
    <div class="table-container">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-uppercase">
                Results for: <?= $class ?> <?= $stream ?> | <?= $exam_type ?> (<?= $year ?>)
            </h5>
            <span class="badge bg-primary px-3 py-2">Students Count: <?= count($results) ?></span>
        </div>
        
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr class="text-center">
                    <th width="50">#</th>
                    <th class="text-start">Student Name</th>
                    <?php foreach($subjects_list as $name): ?>
                        <th><?= $name ?></th>
                    <?php endforeach; ?>
                    <th class="bg-light">Total</th>
                    <th class="bg-light">Average</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $index => $row): 
                    $total_score = 0;
                    $subject_count = 0;
                ?>
                <tr>
                    <td class="text-center text-muted"><?= $index + 1 ?></td>
                    <td class="fw-bold text-uppercase"><?= $row['fullname'] ?></td>
                    
                    <?php foreach($subjects_list as $id => $name): 
                        $score = $row['score_'.$id] ?? 0;
                        $total_score += $score;
                        if($score > 0) $subject_count++;
                    ?>
                        <td class="score-cell"><?= ($score > 0) ? number_format($score, 1) : '<span class="text-muted small">-</span>' ?></td>
                    <?php endforeach; ?>

                    <td class="text-center fw-bold text-primary"><?= number_format($total_score, 1) ?></td>
                    <td class="text-center">
                        <?php 
                        $avg = ($subject_count > 0) ? $total_score / $subject_count : 0;
                        $class_type = ($avg >= 75) ? 'bg-high' : (($avg >= 50) ? 'bg-mid' : 'bg-low');
                        ?>
                        <span class="avg-badge <?= $class_type ?>"><?= number_format($avg, 1) ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-light mb-3"></i>
            <h4 class="text-muted">Select filters and click "Filter" to view marks</h4>
        </div>
    <?php endif; ?>
</div>

<footer class="text-center py-4 text-muted no-print">
    <small>Powered by <b>Likindy Digital Solution (LDS)</b> &copy; <?= date('Y') ?></small>
</footer>

</body>
</html>