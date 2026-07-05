<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 1. Fetch subject assignments kwa ajili ya dropdown ya filter
$assignments_query = "SELECT sa.*, t.fullname as teacher_name, s.subject_name 
                      FROM subject_assignments sa 
                      JOIN teachers t ON sa.teacher_id = t.id 
                      JOIN subjects s ON sa.subject_id = s.id";

if ($role == 'teacher') {
    $assignments_query .= " WHERE sa.teacher_id = '$user_id'";
}

$assignments = $conn->query($assignments_query);

// Filter Selections
$selected_aid = $_GET['assignment_id'] ?? '';
$selected_year = $_GET['year'] ?? '2025/2026';
$selected_term = $_GET['term'] ?? 'Special 1';
$selected_stream = $_GET['stream'] ?? '';

// SPECIAL WEIGHT SETTING (Default: M1=40, EXAM=60)
$m1_weight = $_GET['m1_w'] ?? 40;
$exam_weight = 100 - $m1_weight;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Marks & Filter | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .marks-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: #fff; }
        .setting-box { background: #eef2ff; border-radius: 12px; padding: 15px; border: 1px dashed #4e73df; }
        .table input { width: 80px; border-radius: 8px; border: 1px solid #ced4da; text-align: center; font-weight: 700; padding: 5px; }
        .grade-badge { padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 13px; }
        .A { background: #d1e7dd; color: #0f5132; }
        .B { background: #cfe2ff; color: #084298; }
        .C { background: #fff3cd; color: #664d03; }
        .D { background: #ffe5d0; color: #9a4e0f; }
        .F { background: #f8d7da; color: #842029; }
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        
        /* Hii ni kwa ajili ya kuremba filter page inapokuwa tupu */
        .filter-welcome { padding: 80px 20px; text-align: center; color: #6c757d; }
        .filter-welcome i { font-size: 60px; margin-bottom: 20px; opacity: 0.3; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-filter text-primary me-2"></i>Special Report Filter</h3>
            <p class="text-muted small">Select criteria below to manage marks or generate reports.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="marks_entry.php" class="btn btn-outline-secondary rounded-pill">
                <i class="fas fa-arrow-left me-2"></i>Back to Main Marks
            </a>
        </div>
    </div>

    <div class="card marks-card p-4 mb-4 border-primary" style="border-top: 5px solid;">
        <form method="GET" id="filterForm" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-bold small">Subject & Class</label>
                <select name="assignment_id" class="form-select" required>
                    <option value="">-- Choose Assignment --</option>
                    <?php 
                    $assignments->data_seek(0);
                    while($row = $assignments->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" <?= ($selected_aid == $row['id']) ? 'selected' : '' ?>>
                            <?= $row['class_name'] ?> | <?= $row['subject_name'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-3">
                <label class="form-label fw-bold small">Stream</label>
                <select name="stream" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach(range('A', 'G') as $st): ?>
                        <option value="<?= $st ?>" <?= ($selected_stream == $st) ? 'selected' : '' ?>>Stream <?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-3">
                <label class="form-label fw-bold small">Assessment</label>
                <select name="term" class="form-select">
                    <option value="Special 1" <?= ($selected_term == 'Special 1') ? 'selected' : '' ?>>Special Assessment 1</option>
                    <option value="Special 2" <?= ($selected_term == 'Special 2') ? 'selected' : '' ?>>Special Assessment 2</option>
                </select>
            </div>

            <div class="col-lg-3">
                <div class="setting-box">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="small fw-bold">M1 Weight %</label>
                            <input type="number" name="m1_w" id="m1_w" class="form-control form-control-sm" value="<?= $m1_weight ?>" oninput="updateWeights()">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">EXAM %</label>
                            <input type="number" id="exam_w" class="form-control form-control-sm bg-white" value="<?= $exam_weight ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow">
                    <i class="fas fa-search me-1"></i> FETCH DATA
                </button>
            </div>
            <input type="hidden" name="year" value="<?= $selected_year ?>">
        </form>
    </div>

    <hr class="my-5">

    <?php if(!$selected_aid): ?>
        <div class="filter-welcome">
            <i class="fas fa-folder-open"></i>
            <h4>Ready to manage marks?</h4>
            <p>Please select the <strong>Subject, Stream, and Assessment type</strong> above <br>then click "Fetch Data" to show the students list and report links.</p>
        </div>
    <?php else: 
        // HAPA CHINI NDIO DATA ZINATOKEA BAADA YA KU-FILTER
        $info = $conn->query("SELECT sa.*, s.subject_name FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.id WHERE sa.id = '$selected_aid'")->fetch_assoc();
        $class = $info['class_name'];
        $subject_id = $info['subject_id'];
        
        $students = $conn->query("SELECT s.id, s.fullname, m.monthly_1, m.exam_60, m.total_100, m.grade
                                   FROM students s 
                                   LEFT JOIN marks m ON s.id = m.student_id AND m.subject_id = '$subject_id' 
                                   AND m.term = '$selected_term' AND m.year = '$selected_year'
                                   WHERE s.class_name = '$class' AND s.stream = '$selected_stream' 
                                   AND s.status != 'deleted' ORDER BY s.fullname ASC");
    ?>
        <div class="d-flex justify-content-end mb-3 gap-2">
             <a href="special_report.php?assignment_id=<?= $selected_aid ?>&year=<?= $selected_year ?>&stream=<?= $selected_stream ?>&term=<?= $selected_term ?>&report_type=whole_year" class="btn btn-dark shadow-sm">
                <i class="fas fa-users me-2"></i>CLASS REPORT (<?= $selected_term ?>)
            </a>
            <a href="special_annual_report.php?assignment_id=<?= $selected_aid ?>&year=<?= $selected_year ?>&stream=<?= $selected_stream ?>" class="btn btn-warning fw-bold shadow-sm">
                <i class="fas fa-calendar-alt me-2"></i> ANNUAL (40/60)
            </a>
        </div>

        <form action="save_marks.php" method="POST">
            <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
            <input type="hidden" name="term" value="<?= $selected_term ?>">
            <input type="hidden" name="assignment_id" value="<?= $selected_aid ?>">
            <input type="hidden" name="year" value="<?= $selected_year ?>">
            <input type="hidden" name="m1_weight_val" value="<?= $m1_weight ?>">

            <div class="card marks-card overflow-hidden">
                <div class="card-header bg-primary text-white py-3 text-center">
                    <h5 class="mb-0 fw-bold"><?= strtoupper($info['subject_name']) ?> - <?= strtoupper($class) ?> (STREAM <?= $selected_stream ?>)</h5>
                    <small>Assessment: <?= $selected_term ?> | Mode: <?= $m1_weight ?>% / <?= $exam_weight ?>%</small>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-4">Student Name</th>
                                <th>M1 (40%)</th>
                                <th>EXAM (60%)</th>
                                <th class="bg-light">Total</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($students->num_rows > 0): 
                                while($st = $students->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-start ps-4 fw-bold">
                                        <?= strtoupper($st['fullname']) ?>
                                        <input type="hidden" name="student_id[]" value="<?= $st['id'] ?>">
                                    </td>
                                    <td><input type="number" name="m1[]" class="m1 nav-input" value="<?= $st['monthly_1'] ?>" step="0.1" oninput="calculate(this)"></td>
                                    <td><input type="number" name="exam[]" class="exam nav-input" value="<?= $st['exam_60'] ?>" step="0.1" oninput="calculate(this)"></td>
                                    <td class="bg-light"><input type="text" name="total[]" class="total border-0 bg-transparent fw-bold" value="<?= $st['total_100'] ?>" readonly style="width: 60px;"></td>
                                    <td><span class="grade-badge <?= $st['grade'] ?>"><?= $st['grade'] ?: '--' ?></span></td>
                                    <td>
                                        <a href="special_report.php?student_id=<?= $st['id'] ?>&year=<?= $selected_year ?>&term=<?= $selected_term ?>&report_type=single" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="fas fa-print"></i> Report
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; 
                            else: ?>
                                <tr><td colspan="6" class="py-5 text-muted">No students found in this stream.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer bg-white p-4 text-center">
                    <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow">
                        <i class="fas fa-save me-2"></i>SAVE CHANGES
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Logic ya JavaScript inabaki ile ile ya kufanya hesabu
function updateWeights() {
    let m1_w = document.getElementById('m1_w').value;
    if (m1_w > 100) m1_w = 100;
    if (m1_w < 0) m1_w = 0;
    document.getElementById('exam_w').value = 100 - m1_w;
    document.querySelectorAll('.m1').forEach(input => calculate(input));
}

function calculate(el) {
    let row = el.closest('tr');
    let m1_w = parseFloat(document.getElementById('m1_w').value) / 100;
    let exam_w = parseFloat(document.getElementById('exam_w').value) / 100;
    
    let m1_val = parseFloat(row.querySelector('.m1').value) || 0;
    let exam_val = parseFloat(row.querySelector('.exam').value) || 0;
    
    let total = (m1_val * m1_w) + (exam_val * exam_w);
    row.querySelector('.total').value = total.toFixed(1);

    let gradeBadge = row.querySelector('.grade-badge');
    let grade = '';
    if (total >= 81) grade = 'A';
    else if (total >= 61) grade = 'B';
    else if (total >= 41) grade = 'C';
    else if (total >= 21) grade = 'D';
    else grade = 'F';

    gradeBadge.innerText = grade;
    gradeBadge.className = 'grade-badge ' + grade;
}
</script>

</body>
</html>